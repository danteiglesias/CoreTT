-- =====================================================================
-- CoreTT - Esquema de base de datos (MySQL 5.7+ / MariaDB 10.3+)
--
-- ATENCION: este archivo DESTRUYE los datos. Contiene DROP TABLE sobre las
-- 10 tablas, asi que solo debe usarse en una base vacia o para reinstalar
-- desde cero. Para una base ya existente usa database/migraciones.sql.
--
-- Ejecuta primero schema.sql y despues datos_iniciales.sql.
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `corett`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `corett`;

-- Eliminamos en orden inverso a las dependencias
DROP TABLE IF EXISTS `login_intentos`;
DROP TABLE IF EXISTS `sesiones`;
DROP TABLE IF EXISTS `votos`;
DROP TABLE IF EXISTS `foro_comentarios`;
DROP TABLE IF EXISTS `foro_posts`;
DROP TABLE IF EXISTS `meta_stats`;
DROP TABLE IF EXISTS `guias`;
DROP TABLE IF EXISTS `parches`;
DROP TABLE IF EXISTS `juegos`;
DROP TABLE IF EXISTS `usuarios`;

-- ---------------------------------------------------------------------
-- Usuarios: credenciales, perfil, rango y reputacion.
-- ---------------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(50)      NOT NULL,
  `email`         VARCHAR(190)     NOT NULL,
  `password_hash` VARCHAR(255)     NOT NULL,
  `rol`           ENUM('admin','user') NOT NULL DEFAULT 'user',
  `rango`         VARCHAR(50)      NULL,
  `reputacion`    INT              NOT NULL DEFAULT 0,
  `creado_en`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_email` (`email`),
  UNIQUE KEY `uq_usuarios_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Juegos: ecosistema de CoreTT (LoL, CS2, Fortnite).
-- ---------------------------------------------------------------------
CREATE TABLE `juegos` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`   VARCHAR(30)  NOT NULL,
  `nombre` VARCHAR(50)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_juegos_slug` (`slug`),
  UNIQUE KEY `uq_juegos_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Parches / temporadas por juego.
-- ---------------------------------------------------------------------
CREATE TABLE `parches` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `juego_id` INT UNSIGNED NOT NULL,
  `nombre`   VARCHAR(50)  NOT NULL,
  `version`  VARCHAR(20)  NOT NULL,
  `fecha`    DATE         NULL,
  PRIMARY KEY (`id`),
  KEY `fk_parches_juego` (`juego_id`),
  CONSTRAINT `fk_parches_juego` FOREIGN KEY (`juego_id`)
    REFERENCES `juegos` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Guias: contenido generado por el usuario.
-- ---------------------------------------------------------------------
CREATE TABLE `guias` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `juego_id`    INT UNSIGNED    NOT NULL,
  `usuario_id`  INT UNSIGNED    NOT NULL,
  `titulo`      VARCHAR(150)    NOT NULL,
  `descripcion` VARCHAR(500)    NULL,
  `contenido`   MEDIUMTEXT      NOT NULL,
  `parche_id`   INT UNSIGNED    NULL,
  `estado`      ENUM('publicada','borrador','oculta') NOT NULL DEFAULT 'publicada',
  `creado_en`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_guias_juego` (`juego_id`),
  KEY `fk_guias_usuario` (`usuario_id`),
  KEY `fk_guias_parche` (`parche_id`),
  FULLTEXT KEY `ft_guias_busqueda` (`titulo`, `descripcion`, `contenido`),
  CONSTRAINT `fk_guias_juego` FOREIGN KEY (`juego_id`)
    REFERENCES `juegos` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_guias_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_guias_parche` FOREIGN KEY (`parche_id`)
    REFERENCES `parches` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Votos: reputacion por guia. Un unico voto por (guia, usuario).
-- valor: 1 = up, -1 = down.
-- ---------------------------------------------------------------------
CREATE TABLE `votos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guia_id`    INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `valor`      TINYINT      NOT NULL,
  `creado_en`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_votos_guia_usuario` (`guia_id`, `usuario_id`),
  KEY `fk_votos_usuario` (`usuario_id`),
  -- Sin esta restriccion, un valor fuera de {-1, 1} (por ejemplo desde una
  -- futura consulta o un script manual) contaminaria el SUM(valor).
  CONSTRAINT `ck_votos_valor` CHECK (`valor` IN (-1, 1)),
  CONSTRAINT `fk_votos_guia` FOREIGN KEY (`guia_id`)
    REFERENCES `guias` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_votos_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Foro: hilos y comentarios.
-- ---------------------------------------------------------------------
CREATE TABLE `foro_posts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `titulo`     VARCHAR(150) NOT NULL,
  `contenido`  TEXT         NOT NULL,
  `creado_en`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_foro_posts_usuario` (`usuario_id`),
  CONSTRAINT `fk_foro_posts_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `foro_comentarios` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`    INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `contenido`  TEXT         NOT NULL,
  `creado_en`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_foro_comentarios_post` (`post_id`),
  KEY `fk_foro_comentarios_usuario` (`usuario_id`),
  CONSTRAINT `fk_foro_comentarios_post` FOREIGN KEY (`post_id`)
    REFERENCES `foro_posts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_foro_comentarios_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Meta Stats: tablas de datos del meta (armas, campeones, etc.).
-- ---------------------------------------------------------------------
CREATE TABLE `meta_stats` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `juego_id`      INT UNSIGNED NOT NULL,
  `entidad`       VARCHAR(80)  NOT NULL,
  `categoria`     VARCHAR(50)  NOT NULL DEFAULT 'general',
  `dato`          VARCHAR(80)  NOT NULL,
  `valor`         TEXT         NULL,
  `actualizado_en` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_meta_juego` (`juego_id`),
  -- Sin esta clave unica, el ON DUPLICATE KEY UPDATE de datos_iniciales.sql
  -- nunca coincidia y cada reimport duplicaba las 7 filas de stats.
  UNIQUE KEY `uq_meta_dato` (`juego_id`, `entidad`, `categoria`, `dato`),
  CONSTRAINT `fk_meta_juego` FOREIGN KEY (`juego_id`)
    REFERENCES `juegos` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Sesiones de acceso basadas en token (Bearer).
-- ---------------------------------------------------------------------
CREATE TABLE `sesiones` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64)  NOT NULL,
  `expira_en`  DATETIME     NOT NULL,
  `creado_en`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sesiones_token` (`token_hash`),
  KEY `fk_sesiones_usuario` (`usuario_id`),
  KEY `idx_sesiones_expira` (`expira_en`),
  CONSTRAINT `fk_sesiones_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Rate limiting de login por email + IP.
-- ---------------------------------------------------------------------
CREATE TABLE `login_intentos` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`          VARCHAR(190) NOT NULL,
  `ip`             VARCHAR(45)  NOT NULL,
  `intentos`       INT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_hasta` DATETIME    NULL,
  `actualizado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_login_email_ip` (`email`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;