# CORETT - Plataforma de Guias y Meta Competitivo

> Una comunidad estrategica y academia colaborativa para jugadores de League of Legends, Counter-Strike 2 y Fortnite.

## Descripcion

CORETT es una pagina web impulsada por la comunidad, disenada para ir mas alla de las estadisticas crudas. Funciona como un hibrido entre un foro estructurado y una base de datos del *meta* actual. Permite a los jugadores aprender, debatir y compartir estrategias (guias de macrojuego, lineups, analisis de parches) validadas mediante un sistema de reputacion por votos.

## Caracteristicas

- **Sistema de Reputacion y Validacion:** las guias suben o bajan de relevancia con los votos de la comunidad (Upvote/Downvote) con unicidad por usuario y toggle.
- **Enfoque Multi-Juego:** League of Legends (macrojuego, jungle), Counter-Strike 2 (lineups, economia), Fortnite (meta de armas, drops).
- **Gestion de Usuarios:** registro y login con tokens, perfiles con rango, reputacion y panel de administracion con roles.
- **Foro de discusion** con hilos, comentarios, busqueda y paginacion.
- **API REST JSON** con autenticacion Bearer, validacion de entrada, rate limiting de login y CORS configurable.
- **Base de datos relacional** con FK, indices y FULLTEXT para busquedas (MySQL/MariaDB).

## Tecnologias

| Capa      | Tecnologia          |
|-----------|---------------------|
| Frontend  | HTML5, CSS3, JavaScript (vanilla) |
| Backend   | PHP 7.4+ / 8.x, PDO |
| Base de datos | MySQL 5.7+ / MariaDB 10.3+ |
| Infra     | Apache (XAMPP/WAMP/LAMP) o Docker Compose |

## Requisitos

- PHP 7.4+ con extensiones `pdo_mysql`, `mbstring` y `openssl`.
- MySQL 5.7+ / MariaDB.
- (Opcional) Docker + Docker Compose.
- (Opcional) Composer para los scripts auxiliares.

## Instalacion rapida (Apache/PHP local)

1. Clona el repositorio dentro del docroot de tu servidor (o apunta el docroot a la raiz del proyecto para que `/frontend` y `/backend` sean accesibles).

2. Crea la base de datos:

   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p corett < database/datos_iniciales.sql
   ```

3. Configura el entorno: copia `.env.example` a `.env` y ajusta las credenciales:

   ```bash
   cp .env.example .env
   ```

4. Instala el usuario administrador y los datos demo (genera los hashes de password en runtime):

   ```bash
   php backend/bin/seed.php
   ```

5. Abre `http://localhost/frontend/` (o la ruta segun tu docroot).

### Usuario administrador por defecto

| Campo    | Valor                          |
|----------|--------------------------------|
| Email    | `admin@corett.com`             |
| Password | `admin1234` (o el de tu `.env`)|

> Cambia estas credenciales en produccion.

## Instalacion con Docker Compose

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec web php backend/bin/seed.php
# Abre http://localhost:8080/frontend/
```

El contenedor `db` importa automaticamente `database/schema.sql` y `database/datos_iniciales.sql` la primera vez que se levanta.

## Estructura del proyecto

```
backend/
  api/            # Endpoints REST: guides, forum, games, users, votes
  bin/seed.php    # Seed de administrador y datos demo (CLI)
  config/         # Conexion PDO + lectura de .env
  includes/       # boot (CORS/errores), functions, validations, auth, votes
  index.php       # Health check de la API
database/
  schema.sql      # Esquema completo del modelo relacional
  datos_iniciales.sql  # Juegos, parches y meta stats de referencia
frontend/
  index.html            # Portada
  pages/                # guides.html, forum.html, profile.html, admin.html, guide.html
  js/                   # core.js (utils), layout.js, api.js, auth.js y por pagina
  css/                  # style.css, responsive.css
  assets/               # Portadas de juegos
docs/
  README.md        # Guia de uso de la API
  ESQUEMA_BD.md    # Detalle del modelo de datos
tests/
  run_tests.php    # Smoke test de la API (HTTP)
```

## API

Base por defecto: `/backend/api/`. Todos los endpoints responden JSON. Los metodos que modifican datos requieren el header `Authorization: Bearer <token>`.

| Metodo | Ruta                                   | Descripcion                    |
|--------|----------------------------------------|--------------------------------|
| GET    | `/backend/api/guides.php`              | Lista guias (busqueda, paginacion, filtro por juego) |
| GET    | `/backend/api/guides.php?id={id}`      | Detalle de una guia            |
| POST   | `/backend/api/guides.php`              | Crear guia (auth)              |
| POST   | `/backend/api/guides.php?action=vote`  | Votar `{id, vote: 1|-1}` (auth)|
| PUT    | `/backend/api/guides.php?id={id}`      | Editar guia (autor/admin)      |
| DELETE | `/backend/api/guides.php?id={id}`      | Eliminar guia (autor/admin)    |
| GET    | `/backend/api/forum.php`               | Hilos del foro                 |
| GET    | `/backend/api/forum.php?id={id}`       | Hilo con comentarios           |
| POST   | `/backend/api/forum.php`               | Crear hilo (auth)              |
| POST   | `/backend/api/forum.php?action=comment`| Comentar `{postId, text}`      |
| DELETE | `/backend/api/forum.php?id={id}`       | Eliminar hilo (autor/admin)    |
| GET    | `/backend/api/games.php`               | Juegos con parche actual       |
| GET    | `/backend/api/users.php`               | Listar usuarios (admin)        |
| POST   | `/backend/api/users.php?action=register` | Registro                    |
| POST   | `/backend/api/users.php?action=login`  | Login (devuelve token)         |
| POST   | `/backend/api/users.php?action=logout` | Logout (auth)                  |
| GET    | `/backend/api/users.php?action=profile`| Perfil del usuario (auth)      |

## Pruebas

Smoke test por HTTP (requiere el servidor levantado):

```bash
php tests/run_tests.php http://localhost/backend/api/
```

Si la API no responde, el script informa `SKIP` y termina con codigo 0.

## Roadmap / trabajo pendiente

- Editar guias desde la vista de lista y gestion de `estado` (borradores/ocultas).
- Subcategorias por juego (mapas, campeones, armas) y panel de `meta_stats`.
- Paginacion del panel de administracion.
- Notificaciones, insignias y rango verificado por juego.
- CI (GitHub Actions) para lint de PHP y smoke tests.