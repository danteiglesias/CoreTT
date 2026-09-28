-- =====================================================================
-- CoreTT - Datos iniciales (datos de referencia).
-- Usuarios demo y contenido de ejemplo se crean con el seed de PHP:
--     php backend/bin/seed.php
-- (asi se generan los hashes de password correctamente en runtime).
-- =====================================================================
USE `corett`;

-- ---------- Juegos ----------
INSERT INTO `juegos` (`slug`, `nombre`) VALUES
  ('lol', 'League of Legends'),
  ('cs2', 'Counter-Strike 2'),
  ('fn',  'Fortnite')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);

-- ---------- Parches / temporadas ----------
-- Se resuelve juego_id por slug en lugar de asumir id = 1/2/3: si la base ya
-- tenia juegos con otros ids, las claves foraneas apuntaban al juego equivocado.
INSERT INTO `parches` (`juego_id`, `nombre`, `version`, `fecha`)
SELECT j.id, v.nombre, v.version, v.fecha
  FROM (
              SELECT 'lol' AS slug, 'Temporada 2026 - Split 1'  AS nombre, '25.1' AS version, '2026-01-15' AS fecha
    UNION ALL SELECT 'lol', 'Temporada 2026 - Split 2',  '25.2', '2026-03-10'
    UNION ALL SELECT 'cs2', 'Parche 1.40 - Operacion Anubis', '1.40', '2026-02-01'
    UNION ALL SELECT 'cs2', 'Parche 1.42', '1.42', '2026-05-05'
    UNION ALL SELECT 'fn',  'Temporada 5 - Capitulo 3', 'S5C3', '2026-06-01'
  ) AS v
  JOIN `juegos` j ON j.slug = v.slug
  LEFT JOIN `parches` p ON p.juego_id = j.id AND p.nombre = v.nombre
 WHERE p.id IS NULL;

-- ---------- Meta stats de ejemplo ----------
-- juego_id tambien se resuelve por slug, por el mismo motivo.
INSERT INTO `meta_stats` (`juego_id`, `entidad`, `categoria`, `dato`, `valor`)
SELECT j.id, v.entidad, v.categoria, v.dato, v.valor
  FROM (
              SELECT 'lol' AS slug, 'Campeon' AS entidad, 'mid'    AS categoria, 'pick_rate' AS dato, 'Zed 14%'  AS valor
    UNION ALL SELECT 'lol', 'Campeon', 'jungla',   'pick_rate',   'Lee Sin 12%'
    UNION ALL SELECT 'lol', 'Campeon', 'top',      'win_rate',    'Ornn 52.3%'
    UNION ALL SELECT 'cs2', 'Arma',    'rifle',    'meta',        'AK-47 / M4A1-S'
    UNION ALL SELECT 'cs2', 'Mapa',    'competitive', 'pick_rate', 'Mirage 28%'
    UNION ALL SELECT 'fn',  'Arma',    'seasonal', 'mostly_used', 'Hammer Pump + Assault Rifle'
    UNION ALL SELECT 'fn',  'Drop',    'map',      'best_drop',   'Rebel''s Roost'
  ) AS v
  JOIN `juegos` j ON j.slug = v.slug
  LEFT JOIN `meta_stats` m
    ON m.juego_id = j.id
   AND m.entidad  = v.entidad
   AND m.categoria = v.categoria
   AND m.dato     = v.dato
 WHERE m.id IS NULL;