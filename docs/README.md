# CoreTT Docs

Levantar en local:
1. Copia `.env.example` a `.env` y ajusta `DB_*` y `JWT_SECRET`.
2. Importa `database/schema.sql` y luego `database/datos_iniciales.sql` en MySQL 8.
3. Sirve `frontend/` y `backend/` con PHP (`php -S localhost:8000 -t .` desde la raíz o Apache con docroot en el proyecto).
4. El frontend funciona sin backend (`API_AVAILABLE=false` en `frontend/js/api.js:8-9`). Ponlo a `true` cuando el backend responda.

Seguridad mínima incluida: PDO con prepared statements, `password_hash`, JWT con expiración, `requireAuth/requireAdmin`, validación servidor.
