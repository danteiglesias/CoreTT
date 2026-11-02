-- CoreTT Database Schema
-- Base de datos para plataforma de guías y meta competitivo

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS `corett_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `corett_db`;

-- --------------------------------------------------------
-- Tabla: juegos
-- --------------------------------------------------------

CREATE TABLE `juegos` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `imagen` VARCHAR(255) DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: usuarios
-- --------------------------------------------------------

CREATE TABLE `usuarios` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_usuario` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol` ENUM('usuario','admin','moderador') DEFAULT 'usuario',
  `rango` VARCHAR(50) DEFAULT 'Sin rango',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `biografia` TEXT DEFAULT NULL,
  `reputacion` INT(11) DEFAULT 0,
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ultima_conexion` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_rol` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: parches_temporadas
-- --------------------------------------------------------

CREATE TABLE `parches_temporadas` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `juego_id` INT(11) UNSIGNED NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `version` VARCHAR(50) DEFAULT NULL,
  `fecha_inicio` DATE DEFAULT NULL,
  `fecha_fin` DATE DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `juego_id` (`juego_id`),
  CONSTRAINT `fk_parche_juego` FOREIGN KEY (`juego_id`) REFERENCES `juegos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: guias
-- --------------------------------------------------------

CREATE TABLE `guias` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) UNSIGNED NOT NULL,
  `juego_id` INT(11) UNSIGNED NOT NULL,
  `parche_id` INT(11) UNSIGNED DEFAULT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `descripcion` VARCHAR(500) DEFAULT NULL,
  `contenido` LONGTEXT NOT NULL,
  `imagen_destacada` VARCHAR(255) DEFAULT NULL,
  `votos_positivos` INT(11) DEFAULT 0,
  `votos_negativos` INT(11) DEFAULT 0,
  `vistas` INT(11) DEFAULT 0,
  `destacado` TINYINT(1) DEFAULT 0,
  `aprobado` TINYINT(1) DEFAULT 1,
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `juego_id` (`juego_id`),
  KEY `parche_id` (`parche_id`),
  KEY `idx_destacado` (`destacado`, `aprobado`),
  FULLTEXT KEY `ft_busqueda` (`titulo`, `descripcion`, `contenido`),
  CONSTRAINT `fk_guia_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_guia_juego` FOREIGN KEY (`juego_id`) REFERENCES `juegos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_guia_parche` FOREIGN KEY (`parche_id`) REFERENCES `parches_temporadas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: foro_posts
-- --------------------------------------------------------

CREATE TABLE `foro_posts` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) UNSIGNED NOT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `contenido` LONGTEXT NOT NULL,
  `vistas` INT(11) DEFAULT 0,
  `fijado` TINYINT(1) DEFAULT 0,
  `cerrado` TINYINT(1) DEFAULT 0,
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_fijado` (`fijado`),
  FULLTEXT KEY `ft_foro` (`titulo`, `contenido`),
  CONSTRAINT `fk_foro_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: foro_comentarios
-- --------------------------------------------------------

CREATE TABLE `foro_comentarios` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT(11) UNSIGNED NOT NULL,
  `usuario_id` INT(11) UNSIGNED NOT NULL,
  `contenido` TEXT NOT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fk_comentario_post` FOREIGN KEY (`post_id`) REFERENCES `foro_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comentario_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: votos_guias
-- --------------------------------------------------------

CREATE TABLE `votos_guias` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `guia_id` INT(11) UNSIGNED NOT NULL,
  `usuario_id` INT(11) UNSIGNED NOT NULL,
  `tipo_voto` TINYINT(1) NOT NULL COMMENT '1 = positivo, -1 = negativo',
  `fecha_voto` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unico_voto` (`guia_id`, `usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `fk_voto_guia` FOREIGN KEY (`guia_id`) REFERENCES `guias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_voto_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: meta_stats
-- --------------------------------------------------------

CREATE TABLE `meta_stats` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `juego_id` INT(11) UNSIGNED NOT NULL,
  `categoria` VARCHAR(50) NOT NULL COMMENT 'campeon, arma, personaje, etc',
  `nombre` VARCHAR(100) NOT NULL,
  `imagen` VARCHAR(255) DEFAULT NULL,
  `tier` VARCHAR(10) DEFAULT NULL COMMENT 'S, A, B, C, D',
  `winrate` DECIMAL(5,2) DEFAULT NULL,
  `pickrate` DECIMAL(5,2) DEFAULT NULL,
  `banrate` DECIMAL(5,2) DEFAULT NULL,
  `datos_extra` JSON DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `juego_id` (`juego_id`),
  KEY `idx_categoria` (`categoria`),
  CONSTRAINT `fk_meta_juego` FOREIGN KEY (`juego_id`) REFERENCES `juegos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
