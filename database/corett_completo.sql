-- CoreTT Database Schema
-- Base de datos para plataforma de guÃ­as y meta competitivo

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
-- Datos iniciales para CoreTT
CREATE DATABASE IF NOT EXISTS `corett_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `corett_db`;

-- --------------------------------------------------------
-- Datos: juegos
-- --------------------------------------------------------

INSERT INTO `juegos` (`nombre`, `slug`, `descripcion`, `imagen`, `activo`) VALUES
('League of Legends', 'league-of-legends', 'MOBA estratÃ©gico de Riot Games con campeones y builds competitivos', '../assets/League of Legends.png', 1),
('Counter-Strike 2', 'counter-strike-2', 'FPS tÃ¡ctico competitivo de Valve con lineups y estrategias', '../assets/Counter Strike 2.png', 1),
('Fortnite', 'fortnite', 'Battle Royale de Epic Games con meta de armas y rotaciones', '../assets/Fortnite.png', 1);

-- --------------------------------------------------------
-- Datos: usuario administrador
-- --------------------------------------------------------

INSERT INTO `usuarios` (`nombre_usuario`, `email`, `password_hash`, `rol`, `rango`, `reputacion`, `activo`) VALUES
('Admin', 'admin@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Diamante', 100, 1),
('JohnDoe', 'john@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'usuario', 'Oro', 50, 1),
('SmokeMaster', 'smoke@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'usuario', 'Global Elite', 80, 1),
('VictoryRoyale', 'victory@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'usuario', 'Champion', 65, 1),
('NewPlayer', 'new@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'usuario', 'Bronce', 10, 1);

-- Nota: Las contraseÃ±as son 'password' para todas las cuentas de prueba (hash bcrypt)

-- --------------------------------------------------------
-- Datos: parches
-- --------------------------------------------------------

INSERT INTO `parches_temporadas` (`juego_id`, `nombre`, `version`, `activo`) VALUES
(1, 'Parche 14.19', '14.19', 1),
(2, 'ActualizaciÃ³n reciente', 'Latest', 1),
(3, 'Temporada 5', 'Chapter 6 Season 1', 1);

-- --------------------------------------------------------
-- Datos: guÃ­as
-- --------------------------------------------------------

INSERT INTO `guias` (`usuario_id`, `juego_id`, `parche_id`, `titulo`, `descripcion`, `contenido`, `votos_positivos`, `votos_negativos`, `destacado`, `aprobado`) VALUES
(2, 1, 1, 'CÃ³mo dominar el macro en League of Legends', 'Control de oleadas, visiÃ³n y toma de decisiones para subir de elo.', 
'El macrojuego es la habilidad de tomar las decisiones correctas en el momento correcto. Esto incluye el control de oleadas, la colocaciÃ³n de wards, las rotaciones y la toma de objetivos.\n\n## Control de oleadas\n- Congelar la oleada cuando estÃ¡s en desventaja\n- Empujar cuando quieres rotar o hacer roams\n- Dividir la oleada segÃºn la situaciÃ³n\n\n## VisiÃ³n\nLa visiÃ³n es fundamental. Coloca wards en los puntos clave para mantenerte informado sobre la posiciÃ³n enemiga.', 128, 0, 1, 1),
(3, 2, 2, 'Lineups de humo en Mirage - CS2', 'Los mejores lineups de humo para Mirage, con posiciÃ³n y referencia.', 
'En Mirage los humos en ventana (palace) y coctelera (CT spawn) son esenciales para ejecutar correctamente.\n\n## Humo ventana desde T spawn\n1. ColÃ³cate en la esquina...\n2. Apunta a la referencia...\n3. Lanza el humo\n\n## Humo coctelera\nCrÃ­tico para cortar la rotaciÃ³n de CT.', 96, 0, 1, 1),
(4, 3, 3, 'Meta de armas temporada 5 - Fortnite', 'Ranking de armas segÃºn el parche actual y rotaciones Ã³ptimas.', 
'La temporada 5 trae cambios importantes en drops y rotaciones de la tormenta.\n\n## Tier S\n- Assault Rifle (Alta precisiÃ³n)\n- Shotgun (Close range)\n\n## Tier A\n- SMG\n- Sniper Rifle\n\nLas rotaciones Ã³ptimas dependen de la zona final.', 74, 0, 1, 1),
(3, 2, 2, 'EconomÃ­a en CS2: cuÃ¡ndo comprar y cuÃ¡ndo ahorrar', 'GuÃ­a de la economÃ­a del equipo: ecos, force buys y full buys.', 
'Entender la economÃ­a es clave para ganar rondas importantes.\n\n## Reglas bÃ¡sicas\n- Pistola perdida: generalmente eco\n- Pistola ganada: compra ligera o full buy segÃºn dinero\n- Tras ganar varias rondas: mantener economÃ­a\n\n## Force buy\nSolo hacer force buy en situaciones muy especÃ­ficas donde la informaciÃ³n lo justifica.', 61, 0, 0, 1),
(2, 1, 1, 'Matchup Zed vs control mages en mid lane', 'CÃ³mo sobrevivir al early contra Zed y escalar al mid game.', 
'Zed es un campeÃ³n de all-in temprano; la clave es el posicionamiento.\n\n## Early game\n- MantÃ©n distancia\n- Guarda tus habilidades para defender\n- Farmea de forma segura\n\n## Mid game\nUna vez pasas el nivel 6 con ventaja, puedes empezar a buscar oportunidades.', 45, 0, 0, 1),
(4, 3, 3, 'Zonas de drop seguras en Fortnite', 'Mejores zonas de drop para farmear y sobrevivir al early game.', 
'Las zonas de drop determinan tu early game y tus rotaciones.\n\n## Zonas recomendadas\n- Zona menos concurrida al inicio\n- Buena cobertura\n- Acceso fÃ¡cil a materiales\n\nPrioriza la supervivencia sobre el loot en las primeras partidas.', 52, 0, 0, 1);

-- --------------------------------------------------------
-- Datos: foro posts
-- --------------------------------------------------------

INSERT INTO `foro_posts` (`usuario_id`, `titulo`, `contenido`, `vistas`, `fijado`) VALUES
(5, 'Â¿CuÃ¡l es el mejor campeÃ³n para empezar en LoL?', 
'Soy nuevo y no sÃ© por quiÃ©n empezar. Â¿Recomendaciones de campeones sencillos?\n\nLlevo unas semanas jugando y me pierdo con la cantidad de campeones. Â¿CuÃ¡les recomiendan para aprender las mecÃ¡nicas base?', 150, 0),
(4, 'EconomÃ­a en CS2: estrategias de ecos', 
'En partidas igualadas la economÃ­a decide muchas rondas. Compartid vuestras reglas de oro.\n\nÂ¿CuÃ¡ndo conviene hacer eco completo y cuÃ¡ndo forzar compra?', 120, 0),
(1, 'Rotaciones del final de partida en Fortnite', 
'Siempre me pillan las rotaciones y pierdo por la tormenta. Â¿Consejos para el late game?\n\nBusco tips para posicionarme mejor en las Ãºltimas zonas.', 98, 0);

-- --------------------------------------------------------
-- Datos: foro comentarios
-- --------------------------------------------------------

INSERT INTO `foro_comentarios` (`post_id`, `usuario_id`, `contenido`) VALUES
(1, 2, 'Empieza con Garen o Annie. Son simples y te enseÃ±an conceptos clave.'),
(1, 1, 'TambiÃ©n te recomiendo jugar todos los roles una vez para entender el juego.'),
(2, 3, 'Regla simple: nunca compres de mÃ¡s si perdiste la ronda de pistolas.'),
(2, 3, 'Los force buys funcionan en maps pequeÃ±os (Mirage) si ganÃ¡is el primer duelo.'),
(3, 4, 'Mueve siempre con altura y nunca te quedes sin materiales en la cuarta zona.');

COMMIT;
