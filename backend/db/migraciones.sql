-- =====================================================================
-- CoreTT - Migraciones para bases de datos ya existentes.
--
-- schema.sql hace DROP TABLE de las 10 tablas: NO lo ejecutes sobre una
-- base con datos. Usa este archivo en su lugar.
--
-- Es idempotente: se puede ejecutar varias veces.
--   mysql -u root -p corett < backend/db/migraciones.sql
-- =====================================================================

USE `corett`;

-- ---------------------------------------------------------------------
-- 1. meta_stats: clave unica por (juego, entidad, categoria, dato).
--    Sin ella, el ON DUPLICATE KEY UPDATE de datos_iniciales.sql nunca
--    coincidia y cada reimport duplicaba las filas.
--    Primero se eliminan los duplicados que ya existieran.
-- ---------------------------------------------------------------------
DELETE m1 FROM `meta_stats` m1
  JOIN `meta_stats` m2
    ON m1.juego_id   = m2.juego_id
   AND m1.entidad    = m2.entidad
   AND m1.categoria  = m2.categoria
   AND m1.dato       = m2.dato
   AND m1.id         > m2.id;

SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'meta_stats'
     AND INDEX_NAME   = 'uq_meta_dato'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `meta_stats` ADD UNIQUE KEY `uq_meta_dato` (`juego_id`, `entidad`, `categoria`, `dato`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2. votos: restringir valor a {-1, 1}.
--    MySQL 5.7 y MariaDB ignoran CHECK, pero declararlo deja el requisito
--    documentado y activo en MySQL 8.0.16+ / MariaDB 10.2+.
--    Se limpian primero los valores fuera de rango.
-- ---------------------------------------------------------------------
UPDATE `votos` SET `valor` = 1  WHERE `valor` >  1;
UPDATE `votos` SET `valor` = -1 WHERE `valor` < -1;

SET @chk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA = DATABASE()
     AND TABLE_NAME         = 'votos'
     AND CONSTRAINT_NAME    = 'ck_votos_valor'
);
SET @sql := IF(@chk_exists = 0,
  'ALTER TABLE `votos` ADD CONSTRAINT `ck_votos_valor` CHECK (`valor` IN (-1, 1))',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3. Limpieza de filas huerfanas del rate limiting y de sesiones caducadas.
-- ---------------------------------------------------------------------
DELETE FROM `login_intentos`
 WHERE `bloqueado_hasta` IS NOT NULL AND `bloqueado_hasta` < NOW();

DELETE FROM `sesiones` WHERE `expira_en` < NOW();

-- ---------------------------------------------------------------------
-- 4. Normalizar emails a minusculas: la unicidad ahora se aplica en PHP
--    con normalize_email(), pero los datos previos pueden tener mayusculas
--    que dupliquen la misma cuenta segun el collation.
-- ---------------------------------------------------------------------
UPDATE `usuarios` SET `email` = LOWER(`email`);

-- Mismo nombre no puede aparecer dos veces tras normalizar.
DELETE u1 FROM `usuarios` u1
  JOIN `usuarios` u2 ON u1.email = u2.email AND u1.id > u2.id;

SELECT 'Migraciones aplicadas correctamente.' AS resultado;
