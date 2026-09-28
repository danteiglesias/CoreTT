# CoreTT - Notas de seguridad

Este documento resume que se corrigio, como verificarlo y que queda pendiente.

## Correcciones aplicadas

### 1. Marcadores SQL reutilizados (HTTP 500)

`backend/config/database.php` activaba `PDO::ATTR_EMULATE_PREPARES = false`. Con la
emulacion desactivada, PDO no admite usar dos veces el mismo marcador nombrado
en una sentencia, y lanza `SQLSTATE[HY093] Invalid parameter number`.

Cuatro consultas reutilizaban marcador, y por tanto devolvian 500:

| Fichero                          | Efecto                                    |
|----------------------------------|-------------------------------------------|
| `backend/api/guides.php`         | Crear guia, editar guia y filtrar por juego |
| `backend/api/guides.php`         | Buscar guias                              |
| `backend/api/forum.php`          | Buscar posts                              |
| `backend/api/games.php`          | `?action=stats`                           |

La creacion de guias estaba **completamente rota**: ningun usuario podia
publicar una guia. Ahora cada columna tiene su marcador (`:q1`, `:q2`, `:q3`).

La regla general: con emulacion desactivada, un marcador nombrado se usa una
sola vez por sentencia.

### 2. Exposicion de `.git` y `.env` en Docker

La imagen `php:8.2-apache` viene con `AllowOverride None` para `/var/www/`, asi
que el `.htaccess` del proyecto se ignoraba por completo. Como
`docker-compose.yml` monta el proyecto entero en el docroot, `/.git/config` y
`/.env` se servian como texto plano (codigo fuente, `DB_PASSWORD` y
`ADMIN_PASSWORD`).

Ahora el `Dockerfile` pone `AllowOverride All` y anade reglas a nivel de
servidor que bloquean `.git`, `.env`, `.sql`, `.md`, `.json` y `.log`, mas
`Options -Indexes`, `expose_php Off` y la ejecucion como usuario sin privilegios.

El `.htaccess` tambien se reforzo (CSP, `RedirectMatch 404 /\.git`, mas
cabeceras de seguridad) para despliegues en Apache del sistema.

> Si instalas en Apache fuera de Docker, remember configurar `AllowOverride All`.

### 2b. El SQL sigue dentro del docroot

El proyecto se sirve desde su propia raiz, asi que `backend/db/*.sql` queda
dentro del area web. Un `.sql` descargable publica el esquema completo de la
base de datos. Esta bloqueado en tres sitios, a proposito:

1. `backend/db/.htaccess` con `Require all denied`: la proteccion **viaja con
   la carpeta**, asi que sigue aplicando aunque alguien sirva el proyecto con
   otro `DocumentRoot`, sustituya el `.htaccess` de la raiz o monte solo un
   subdirectorio.
2. El `.htaccess` de la raiz, con `<FilesMatch "\.(sql|...)$">`.
3. La configuracion de Apache del `Dockerfile`, a nivel de servidor, para que
   no dependa de que el override este habilitado.

Los ficheros solo hacen falta al instalar o migrar (el `docker-entrypoint-initdb.d`
y el cliente `mysql`), nunca en runtime, asi que bloquearlos no afecta a la
aplicacion.

**Lo que esta proteccion NO cubre:** Nginx, el servidor embebido de PHP y
cualquier servidor que no lea `.htaccess`. Si despliegas en alguno de esos, el
`.sql` se sirve. Equivalente para Nginx:

```nginx
location ~ ^/backend/db/ { deny all; }
location ~* \.(sql|md|lock)$ { deny all; }
```

La unica solucion que no deja margen es **sacar el SQL del docroot**, lo que
exige que el docroot pase a ser un subdirectorio (por ejemplo `public/`) en vez
de la raiz del proyecto. Eso cambia todas las rutas de assets y de la API, asi
que no se hizo aqui.

### 3. Validacion de entrada que se descartaba

`validate_guide()` y `validate_post()` recibian el array **por valor** y
recortaban titulo, descripcion y contenido sobre una copia local. El
llamante insertaba los valores crudos, asi que un titulo de 200 caracteres
contra un `VARCHAR(150)` terminaba en `Data too long` (HTTP 500).

Ahora `sanitize_guide()` y `sanitize_post()` devuelven `['datos' => ..., 'errores' => ...]`
y el llamante inserta los valores saneados. Los limites coinciden con el ancho
de las columnas, y superar el maximo es un 422 con mensaje claro, no un 500.

### 4. IP de cliente falsificable

`client_ip()` devolvia `X-Forwarded-For` sin validar ni parsear. Eso rompia
tres cosas a la vez:

- **Evasion del rate limiting**: rotando ese header, cada intento usaba una IP
  nueva y el umbral nunca se alcanzaba.
- **Bloqueo permanente de cuentas**: encadenando fallos con la IP de la
  victima, el bloqueo crecia sin tope (`(intentos - 4) * 120` segundos).
- **DoS sin autenticar**: un header de 300 caracteres hacia fallar el login con
  `Data too long for column 'ip'` -> HTTP 500 accesible sin credenciales.

Ahora `client_ip()` usa `REMOTE_ADDR` y solo mira `X-Forwarded-For` si el
servidor esta detras de un proxy declarado en `TRUSTED_PROXIES`, tomando la
direccion mas a la derecha que no sea el propio proxy. El bloqueo tiene tope
(15 minutos) y se recolectan los registros caducados.

### 5. Enumeracion de usuarios por tiempo de respuesta

`attempt_login()` hacia `if (!$user || !password_verify(...))`: con
cortocircuito, un email inexistente no ejecutaba el bcrypt y respondia mas
rapido. Ahora se verifica siempre contra un hash ficticio cuando el usuario no
existe.

Medido tras el cambio (mediana de 10 muestras, 140 ms de bcrypt en ambos casos):

```
mediana email existente   : 139.8 ms
mediana email inexistente : 142.7 ms
diferencia                :   2.9 ms
```

### 6. Filtracion de informacion

- `APP_ENV` passa a `production` por defecto. Antes, `docker-compose.yml` y
  `.env.example` ponian `development`, y el manejador de excepciones devolvia
  el mensaje crudo de PDO (esquema, DSN y rutas del servidor).
- `games.php?action=stats` devolvia el parametro `game` recibido tal cual.
  Ahora devuelve el nombre canonico de la base de datos.
- `backend/index.php` publicaba el estado de la base de datos y el mapa
  completo de endpoints. Ahora solo devuelve nombre, version y estado.
- El registro respondia distinguiendo "email ocupado" de "nombre ocupado".
  Ahora un unico mensaje.
- Las respuestas JSON escapan `<`, `>`, `&`, `'` y `"` (`JSON_HEX_*`).

### 7. Autorizacion y logica de negocio

- **Autovoto**: `apply_vote()` no comprobaba el autor, asi que cada usuario
  podia votarse a si mismo e inflar su reputacion. Ahora devuelve 422.
- **Clausura total de administradores**: degradar o borrar al unico admin
  dejaba el sitio sin administracion. Ahora hay `ensure_not_last_admin()`
  (ademas de las reglas que ya impedian auto-degradarse y auto-borrarse).
- **Condicion de carrera en votos**: el leer-insertar sin transaccion choca
  con la clave unica `(guia_id, usuario_id)` bajo concurrencia. Ahora usa
  transaccion y `SELECT ... FOR UPDATE`.
- **Voto a guia inexistente**: `guides.php` no comprobaba la existencia y
  terminaba en un error de clave foranea. Ahora 404.
- **Parametros no escalares**: `?id[]=1` pasaba por un cast a entero. `param_int`,
  `param_str` y los lectores del cuerpo JSON ignoran arrays y objetos.

### 8. Superficie de ataque y DoS

- Limite de tamano del cuerpo (`MAX_BODY_BYTES`, 413 si se excede).
- Limite de escritura por IP y ventana para registro, guias, posts,
  comentarios y votos. El limite de registro se aplica **despues** de validar,
  porque lo que hay que proteger es el coste del hash bcrypt, que solo ocurre
  en los registros validos.
- `sesiones` se recorta a 5 por usuario y se limpian las caducadas.
- Los comodines `%` y `_` de `LIKE` se escapan, para que una busqueda no
  fuerce un escaneo completo de la tabla.

### 9. Cabeceras y CORS

- Se envian desde PHP (no solo desde Apache): `X-Content-Type-Options`,
  `X-Frame-Options: DENY`, `Referrer-Policy`, `Content-Security-Policy`,
  `Cache-Control: no-store` y se elimina `X-Powered-By`.
- CORS: `Vary: Origin` siempre en modo lista blanca, credenciales solo con
  origen concreto (nunca con `*`), y sin `ALLOWED_ORIGIN` no se envia ninguna
  cabecera (solo mismo origen).
- El frontend recibe un CSP completo en el `.htaccess`.

### 10. Sesion y frontend

- El token se envia ademas en una cookie `HttpOnly; SameSite=Lax` y el backend
  la acepta. **Ojo:** el frontend sigue usando el header `Bearer` con el token
  guardado en `localStorage`, asi que un XSS todavia puede leerlo del
  `localStorage`. La cookie es defensa en profundidad, no un sustituto de
  migrar por completo a cookie.
- `Auth.init()` confirma la sesion contra el servidor. Antes, `isLoggedIn()`
  solo miraba que existiera un objeto en `localStorage`, asi que un token
  caducado seguia mostrando la interfaz de sesion iniciada.
- `admin.js` ya no decide el acceso leyendo `role` del `localStorage` (editable
  desde el navegador): pide el perfil al servidor. El backend siempre habia
  validado con `require_admin()`, pero la UI mentia.
- `logout()` ahora espera a que el servidor invalide la sesion antes de limpiar
  el estado local; antes se perdian tokens de 7 dias al navegar.
- Otros arreglos: null-checks en los botones del navbar, `window.Auth`/`window.API`
  exposedos para que el orden de carga no importe, `Content-Type` solo en
  peticiones con cuerpo, grilla anidada eliminada en la portada, y el listado
  de guias ya no se re-renderiza entero en cada "cargar mas".

### 11. Seed

`seed.php` abortaba con un `TypeError` a mitad: un post de ejemplo pedia
`$demoIds['TeamPilot']`, un usuario que no existe entre los usuarios demo. Como
es el paso final de las dos instalaciones documentadas, el seed nunca
terminaba. Ademas, cambiar `ADMIN_PASSWORD` no tenia efecto tras la primera
ejecucion (el `UPDATE` no tocaba el hash).

### 12. Infra

- Credenciales por defecto (`admin1234`, `password123`, `DB_USER=root`) fuera
  de `.env.example`, `docker-compose.yml`, README y docs.
- Puerto 3306 de MySQL ya no se publica al host.
- `composer lint` usaba `find`, que no existe en Windows: ahora es
  `php tests/lint.php`.
- `meta_stats` tiene clave unica, asi que `datos_iniciales.sql` ya no duplica
  filas al reimportarse; y `datos_iniciales.sql` resuelve `juego_id` por slug
  en vez de asumir `id` 1/2/3.
- `votos.valor` con `CHECK (valor IN (-1, 1))`.

## Verificacion

```bash
php tests/lint.php
php tests/run_tests.php http://localhost:8080/backend/api/
```

El smoke test cubre los caminos que antes no se probaban y por eso llegaron
rotos: crear guias, los cuatro listados con marcador duplicado, limites de
tamano, votacion entre dos usuarios distintos, autorizacion y cabeceras de
seguridad.

## Pendiente (decisiones de producto o de despliegue)

- **Token en `localStorage`.** El arreglo definitivo es no devolver el token al
  JavaScript y migrar a cookie de sesion. Es un cambio de frontend mayor.
- **`ALLOWED_ORIGIN=*`** activa el modo demo del frontend, que se puede
  activar con `?demo=1` en la URL. Un enlace con ese parametro muestra
  contenido fabricado en el dominio real. Si molesta, hay que quitar el
  disparador por URL y dejar solo `window.API_CONFIG.demo`.
- **El usuario puede escribir su propio `rango`** ("Diamante", "Global
  Elite"). Es cosmetico, pero sin verificar. El roadmap ya preveia un rango
  verificado.
- **`usuarios.reputacion` nunca se escribe.** La reputacion se calcula al
  vuelo como `SUM(votos)` de sus guias, pero la columna y el orden del listado
  de administracion la usan como si existiera.
- **`guias.estado`** solo puede ser `publicada`: nada en la API permite crear
  borradores ni ocultar.
- **Sin registro de auditoria** para acciones privilegiadas (cambio de rol,
  borrado de usuarios o contenido).
- **Sin CI.** El lint y el smoke test se ejecutan a mano.
- **Auditoria de dependencias**: no hay `composer.lock` porque no hay
  dependencias, pero tampoco hay analisis estatico (PHPStan/Psalm).
