# CoreTT - Esquema de base de datos

Modelo relacional sobre MySQL/MariaDB. Vocabulario en espanol por dominio.

## Entidades

### usuarios
| Campo           | Tipo          | Notas                                |
|-----------------|---------------|--------------------------------------|
| id              | INT UNSIGNED  | PK, autoincrement                    |
| nombre          | VARCHAR(50)   | UNIQUE                               |
| email           | VARCHAR(190)  | UNIQUE                               |
| password_hash   | VARCHAR(255)  | `password_hash()` (bcrypt)           |
| rol             | ENUM          | `admin` o `user`                     |
| rango           | VARCHAR(50)   | Ej: Diamante, Global Elite           |
| reputacion      | INT           | Suma de reputacion acumulada         |
| creado_en / actualizado_en | DATETIME |                              |

### juegos
`id`, `slug` (UNIQUE: `lol`, `cs2`, `fn`), `nombre` (UNIQUE).

### parches
`id`, `juego_id` (FK), `nombre`, `version`, `fecha`. Representa parches y temporadas.

### guias
| Campo        | Tipo      | Notas                              |
|--------------|-----------|------------------------------------|
| id           | INT UNS   | PK                                 |
| juego_id     | FK        | -> juegos                          |
| usuario_id   | FK        | -> usuarios (autor)                |
| titulo       | VARCHAR   |                                    |
| descripcion  | VARCHAR   |                                    |
| contenido    | MEDIUMTEXT|                                    |
| parche_id    | FK        | -> parches (SET NULL al borrar)    |
| estado       | ENUM      | `publicada`, `borrador`, `oculta`  |
| creado/actualizado | DATETIME |                          |

Tiene indice FULLTEXT sobre `(titulo, descripcion, contenido)` para la busqueda.

### votos
`id`, `guia_id` (FK), `usuario_id` (FK), `valor TINYINT` (`1` up / `-1` down), `creado_en`.
Unicidad por `(guia_id, usuario_id)`: cada usuario puede votar una unica vez por guia.

### foro_posts y foro_comentarios
Hilos y comentarios, ambos con FK a `usuarios`. Borrar un hilo elimina sus comentarios (CASCADE).

### meta_stats
`id`, `juego_id` (FK), `entidad` (arma/campeon/mapa), `categoria`, `dato`, `valor`, `actualizado_en`.
Datos de referencia actualizados por administradores.

### sesiones
`id`, `usuario_id` (FK), `token_hash` (UNIQUE, SHA-256 del token), `expira_en`, `creado_en`.
El token en claro solo se devuelve una vez en el login.

### login_intentos
`id`, `email`, `ip`, `intentos`, `bloqueado_hasta`, `actualizado_en`.
Unicidad por `(email, ip)`. Soporta el rate limiting del login.

## Relaciones (resumen)

- `juegos 1-N parches`
- `juegos 1-N meta_stats`
- `usuarios 1-N guias` (autor)
- `juegos 1-N guias`
- `guias 1-N votos N-1 usuarios`
- `usuarios 1-N foro_posts 1-N foro_comentarios`
- `usuarios 1-N sesiones`
- `guias N-1 parches`

## Votos y reputacion

El conteo de votos de una guia es `SUM(votos.valor)`. La reputacion de un usuario puede derivarse
de la suma de votos de sus guias (el endpoint de perfil ya lo calcula). El campo `reputacion`
en `usuarios` queda disponible para un ranking persistente.

## Seed

`backend/db/datos_iniciales.sql` solo inserta datos de referencia (juegos, parches, meta_stats).
Los usuarios, guias demo y foro los crea `backend/bin/seed.php` para generar hashes correctos
en runtime:
```bash
php backend/bin/seed.php
```