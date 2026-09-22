# Esquema BD CoreTT (resumen)

Tablas: `users`, `games`, `guides`, `forum_posts`, `forum_comments`, `votes`.
Ver DDL ejecutable en `database/schema.sql` y seeds en `database/datos_iniciales.sql`.

Relaciones:
- `users 1—N guides`, `users 1—N forum_posts`, `posts 1—N comments`
- `users N—M guides` vía `votes(user_id, guide_id UNIQUE, value ±1)`
- `games 1—N guides`

Contratos API:
- `GET games.php` -> `string[]`
- `GET guides.php[?game=&id=]` / `POST guides.php` / `POST guides.php?action=vote` / `DELETE guides.php?id=` (admin)
- `GET/POST/DELETE forum.php` + `POST forum.php?action=comment`
- `POST users.php?action=login|register`, `GET users.php?action=profile`, `GET users.php` (admin)
