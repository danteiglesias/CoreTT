# CORETT - Plataforma de Guías y Meta Competitivo

> Una comunidad estratégica y academia colaborativa para jugadores de League of Legends, Counter-Strike 2 y Fortnite.

## Descripción

CORETT es una pagina web impulsada por la comunidad, diseñada para ir más allá de las estadísticas crudas. Funciona como un híbrido entre un foro estructurado y una base de datos del *meta* actual. Permite a los jugadores aprender, debatir y compartir estrategias (guías de macrojuego, lineups, análisis de parches) validadas mediante un sistema de reputación por votos.

## Características Principales

- **Sistema de Reputación y Validación:** Las guías y estrategias suben o bajan de relevancia en base a los votos de la comunidad (Upvote/Downvote), garantizando contenido útil y actualizado.
- **Enfoque Multi-Juego (Módulos Específicos):**
  - **League of Legends:** Análisis de *matchups*, control de oleadas y rutas de jungla por parche.
  - **Counter-Strike 2:** Directorio interactivo de *lineups* (humos, destellos, molotovs) y guías de economía.
  - **Fortnite:** Evolución del meta de armas por temporada, rotaciones y zonas de drop.
- **Gestión de Usuarios Avanzada:** Perfiles personalizados con rango verificado, roles principales e historial de contribuciones al foro.
- **Diseño Relacional Robusto:** Base de datos estructurada para manejar distintas entidades complejas (Juegos, Parches/Temporadas, Guías de Usuarios, Votos, Estadísticas Oficiales).
- **API RESTful en PHP:** Backend completo con endpoints para todas las funcionalidades.
- **Frontend Responsive:** Interfaz adaptable a móviles y escritorio.

## Tecnologías Utilizadas

- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Backend:** PHP 8.x con PDO
- **Base de Datos:** MySQL/MariaDB
- **Servidor:** Compatible con Apache/Nginx en Linux/Windows

## Instalación y Despliegue

### Requisitos
- Servidor web con PHP 7.4+ o PHP 8.x
- MySQL 5.7+ o MariaDB 10.2+
- Acceso a phpMyAdmin o línea de comandos MySQL

### Despliegue en un servidor privado por SSH

SSH sirve para administrar y transferir los archivos; por sí solo no publica la
página. El servidor también necesita un servidor web (Apache/Nginx), PHP 7.4+
con `pdo_mysql`, y acceso de red desde PHP a MySQL/MariaDB.

1. **Conectarse y subir el proyecto**
   - Desde PowerShell, conecta con la cuenta del servidor: `ssh usuario@servidor`.
   - Sube los directorios `backend`, `database` y `frontend`, además de
     `index.php` y `.htaccess`, al directorio del sitio configurado en Apache o
     Nginx. No subas credenciales ni guardes `.env` dentro de un directorio
     públicamente descargable.
   - Configura el sitio para servir la carpeta del proyecto. Si el servidor es
     privado y el HTTP solo escucha en localhost, puedes acceder mediante un
     túnel SSH, por ejemplo: `ssh -L 8080:127.0.0.1:80 usuario@servidor`, y abrir
     `http://localhost:8080/` mientras el túnel esté activo. Si tiene dominio o
     HTTP(S) accesible por la red privada, usa esa URL.

2. **Crear y poblar la base de datos**
   - Importa `database/corett_completo.sql` en MySQL para crear `corett_db` con
     tablas y datos de prueba; o importa `database/schema.sql` y después
     `database/datos_iniciales.sql`.
   - Crea un usuario MySQL dedicado para la aplicación con permisos
     `SELECT`, `INSERT`, `UPDATE` y `DELETE` sobre `corett_db`. No uses la cuenta
     administrativa de MySQL desde la aplicación.

3. **Configurar las credenciales en el servidor**
   - Crea `.env` en la raíz del proyecto desplegado (o en `backend/`) a partir
     de `.env.example`; edítalo directamente en el servidor y no lo subas a Git.
   - Si MySQL está en el mismo servidor, normalmente `DB_HOST=127.0.0.1`. Si
     está en otro host, usa su IP/DNS privado y permite el puerto MySQL solo
     desde el servidor de la aplicación.
   - Configura los valores reales de conexión y un secreto aleatorio:
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=corett_db
   DB_USER=corett_app
   DB_PASS=tu_contraseña_mysql
   DB_CHARSET=utf8mb4
   SECRET_KEY=un_secreto_largo_y_aleatorio
   APP_DEBUG=false
   ```
   `DB_HOST` es el host de MySQL visto desde PHP; no necesariamente es el host
   SSH. El usuario SSH y las credenciales MySQL son independientes.

4. **Verificar el despliegue**
   - Abre `/backend/diagnostico.php?format=html` en la URL del sitio para
     comprobar PHP, la conexión, las tablas y los permisos.
   - Abre la raíz del sitio para cargar la web. El frontend y la API deben
     servirse desde el mismo sitio para que las rutas automáticas funcionen.

### Cuentas de prueba
- **Admin:** admin@corett.com / password
- **Usuario:** john@corett.com / password

## Estructura del Proyecto

```
CoreTT/
├── backend/           # API REST en PHP
│   ├── api/          # Endpoints (users, guides, forum, games, votes)
│   ├── config/       # Configuración de BD
│   └── includes/     # Funciones, auth, validaciones
├── database/         # Scripts SQL
│   ├── schema.sql    # Estructura de la BD
│   └── datos_iniciales.sql # Datos de prueba
├── frontend/         # Aplicación web
│   ├── assets/       # Imágenes
│   ├── css/          # Estilos
│   ├── js/           # JavaScript
│   └── pages/        # Páginas HTML
└── docs/             # Documentación adicional
```

## Estructura de la Base de Datos (Entidades Principales)

El proyecto se sostiene sobre un modelo Entidad-Relación que gestiona:
1. `usuarios` - Credenciales, perfil, rango, reputación, roles
2. `juegos` - Ecosistema (LoL, CS2, Fortnite)
3. `parches_temporadas` - Control de parches por juego
4. `guias` - Contenido generado por el usuario, vinculado a juego y parche
5. `foro_posts` - Publicaciones del foro
6. `foro_comentarios` - Comentarios en posts
7. `votos_guias` - Sistema de votos upvote/downvote
8. `meta_stats` - Estadísticas meta por juego/categoría

## Seguridad

- Contraseñas hasheadas con bcrypt
- Autenticación basada en tokens
- Validación de datos en servidor
- Protección contra SQL injection con prepared statements
- CORS configurado
- Sanitización de inputs

## Licencia

Ver archivo LICENSE para más detalles.
