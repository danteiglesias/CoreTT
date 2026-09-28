# CoreTT - API REST (uso desde el frontend o herramientas)

La API vive en `/backend/api/`. Responde JSON en UTF-8. Los metodos que
modifican datos requieren token.

## Autenticacion

1. `POST /backend/api/users.php?action=login` con `{ "email": "...", "password": "..." }`.
2. Respuesta: `{ "success": true, "token": "....", "user": {...} }`.
3. Enviar en cada peticion protegida: `Authorization: Bearer <token>`.

El login ademuas establece una cookie `corett_session` `HttpOnly; SameSite=Lax`
que el backend tambien acepta, para clientes que no puedan manejar cabeceras.

El email se normaliza a minusculas, asi que `JUAN@Ejemplo.COM` y
`juan@ejemplo.com` son la misma cuenta.

Ejemplo con `curl` (usa las credenciales que definiste en `ADMIN_EMAIL` y
`ADMIN_PASSWORD`):

```bash
# Login
TOKEN=$(curl -s -X POST http://localhost/backend/api/users.php?action=login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@corett.com","password":"TU_CLAVE"}' | \
  php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["token"] ?? "";')

# Listar guias (admite game, search, sort, page, limit)
curl -s "http://localhost/backend/api/guides.php?page=1&limit=10"
curl -s "http://localhost/backend/api/guides.php?search=eco&sort=votes"

# Crear una guia
curl -s -X POST http://localhost/backend/api/guides.php \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"title":"Mi guia","game":"lol","description":"...","content":"..."}'

# Votar (no se puede votar una guia propia)
curl -s -X POST "http://localhost/backend/api/guides.php?action=vote" \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"id":1,"vote":1}'
```

## Respuestas de error

```json
{ "error": "mensaje" }
{ "error": "Datos invalidos", "errors": { "campo": "detalle" } }
```

Codigos: `400` peticion invalida, `401` sin autenticar, `403` sin permisos,
`404` no encontrado, `405` metodo no permitido, `413` cuerpo demasiado grande,
`422` datos invalidos, `429` rate limited, `500` error interno.

Los mensajes de `500` son genericos salvo que `APP_ENV=development`. En ese modo
el backend devuelve el detalle real, y **no debe usarse en un entorno
accesible desde internet** porque filtra informacion del servidor y de la base
de datos.

## Limites de entrada

Los limites coinciden con las columnas de la base de datos. Superarlos es un
`422` con el detalle, nunca un `500`.

| Campo                | Limite  |
|----------------------|---------|
| nombre               | 3-50    |
| email                | <= 190  |
| password             | 8-200   |
| guias.titulo         | 5-150   |
| guias.descripcion    | <= 500  |
| guias.contenido      | 20-50000|
| foro_posts.titulo    | 5-150   |
| foro_posts.contenido | <= 20000|
| foro_comentarios     | 1-2000  |
| busqueda             | <= 100  |

## Votacion

Posterior a la creacion el primer voto suma (`vote: 1`) o resta (`vote: -1`).
Volver a enviar el mismo valor anula la votacion (toggle); cambiar de signo
actualiza el valor. La respuesta devuelve `votes` (total) y `myVote`
(`1`, `-1` o `0`).

No se puede votar la propia guia: devuelve `422`. Solo votan guias en estado
`publicada`.

## Rate limiting

- **Login**: 5 intentos fallidos por email+IP, con bloqueo progresivo hasta un
  tope de 15 minutos. Devuelve `429`.
- **Escritura**: guias, posts, comentarios y votos tienen limite por IP y
  ventana. Configurable con `GUIDE_MAX_PER_WINDOW`, `POST_MAX_PER_WINDOW`,
  `COMMENT_MAX_PER_WINDOW` y `VOTE_MAX_PER_WINDOW`.
- **Registro**: `REGISTER_MAX_PER_WINDOW` cuentas por IP. Se cuenta solo tras
  validar la entrada, porque el coste protegido es el hash de la contrasena.

La IP se toma de `REMOTE_ADDR`. Solo se lee `X-Forwarded-For` si el servidor esta
detras de un proxy declarado en `TRUSTED_PROXIES`.

## Modo demo del frontend

El frontend funciona por defecto contra la API real. Para verlo con datos de
ejemplo sin servidor, agrega `?demo=1` a la URL. Las operaciones de escritura
solo persisten en memoria mientras dura la pagina.

**Ojo:** el modo demo se puede activar desde la URL, asi que un enlace con
`?demo=1` muestra contenido fabricado dentro del dominio real. Si eso es un
problema, quita el disparador por URL de `frontend/js/api.js` y deja el modo
demo solo via `window.API_CONFIG.demo`.