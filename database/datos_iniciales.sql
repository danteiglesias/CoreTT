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
INSERT INTO `parches` (`id`, `juego_id`, `nombre`, `version`, `fecha`) VALUES
  (1, 1, 'Temporada 2026 - Split 1',  '25.1',  '2026-01-15'),
  (2, 1, 'Temporada 2026 - Split 2',  '25.2',  '2026-03-10'),
  (3, 2, 'Parche 1.40 - Operacion Anubis', '1.40', '2026-02-01'),
  (4, 2, 'Parche 1.42',                    '1.42', '2026-05-05'),
  (5, 3, 'Temporada 5 - Capitulo 3',       'S5C3', '2026-06-01')
ON DUPLICATE KEY UPDATE `version` = VALUES(`version`);

-- ---------- Meta stats de ejemplo ----------
INSERT INTO `meta_stats` (`juego_id`, `entidad`, `categoria`, `dato`, `valor`) VALUES
  (1, 'Campeon', 'mid',   'pick_rate', 'Zed 14%'),
  (1, 'Campeon', 'jungla','pick_rate', 'Lee Sin 12%'),
  (1, 'Campeon', 'top',   'win_rate',  'Ornn 52.3%'),
  (2, 'Arma',    'rifle', 'meta',      'AK-47 / M4A1-S'),
  (2, 'Mapa',    'competitive', 'pick_rate', 'Mirage 28%'),
  (3, 'Arma',    'seasonal', 'mostly_used', 'Hammer Pump + Assault Rifle'),
  (3, 'Drop',    'map',    'best_drop', 'Rebel\'s Roost')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);