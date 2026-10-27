-- Datos iniciales para CoreTT
USE `corett_db`;

-- --------------------------------------------------------
-- Datos: juegos
-- --------------------------------------------------------

INSERT INTO `juegos` (`nombre`, `slug`, `descripcion`, `imagen`, `activo`) VALUES
('League of Legends', 'league-of-legends', 'MOBA estratégico de Riot Games con campeones y builds competitivos', '../assets/League of Legends.png', 1),
('Counter-Strike 2', 'counter-strike-2', 'FPS táctico competitivo de Valve con lineups y estrategias', '../assets/Counter Strike 2.png', 1),
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

-- Nota: Las contraseñas son 'password' para todas las cuentas de prueba (hash bcrypt)

-- --------------------------------------------------------
-- Datos: parches
-- --------------------------------------------------------

INSERT INTO `parches_temporadas` (`juego_id`, `nombre`, `version`, `activo`) VALUES
(1, 'Parche 14.19', '14.19', 1),
(2, 'Actualización reciente', 'Latest', 1),
(3, 'Temporada 5', 'Chapter 6 Season 1', 1);

-- --------------------------------------------------------
-- Datos: guías
-- --------------------------------------------------------

INSERT INTO `guias` (`usuario_id`, `juego_id`, `parche_id`, `titulo`, `descripcion`, `contenido`, `votos_positivos`, `votos_negativos`, `destacado`, `aprobado`) VALUES
(2, 1, 1, 'Cómo dominar el macro en League of Legends', 'Control de oleadas, visión y toma de decisiones para subir de elo.', 
'El macrojuego es la habilidad de tomar las decisiones correctas en el momento correcto. Esto incluye el control de oleadas, la colocación de wards, las rotaciones y la toma de objetivos.\n\n## Control de oleadas\n- Congelar la oleada cuando estás en desventaja\n- Empujar cuando quieres rotar o hacer roams\n- Dividir la oleada según la situación\n\n## Visión\nLa visión es fundamental. Coloca wards en los puntos clave para mantenerte informado sobre la posición enemiga.', 128, 0, 1, 1),
(3, 2, 2, 'Lineups de humo en Mirage - CS2', 'Los mejores lineups de humo para Mirage, con posición y referencia.', 
'En Mirage los humos en ventana (palace) y coctelera (CT spawn) son esenciales para ejecutar correctamente.\n\n## Humo ventana desde T spawn\n1. Colócate en la esquina...\n2. Apunta a la referencia...\n3. Lanza el humo\n\n## Humo coctelera\nCrítico para cortar la rotación de CT.', 96, 0, 1, 1),
(4, 3, 3, 'Meta de armas temporada 5 - Fortnite', 'Ranking de armas según el parche actual y rotaciones óptimas.', 
'La temporada 5 trae cambios importantes en drops y rotaciones de la tormenta.\n\n## Tier S\n- Assault Rifle (Alta precisión)\n- Shotgun (Close range)\n\n## Tier A\n- SMG\n- Sniper Rifle\n\nLas rotaciones óptimas dependen de la zona final.', 74, 0, 1, 1),
(3, 2, 2, 'Economía en CS2: cuándo comprar y cuándo ahorrar', 'Guía de la economía del equipo: ecos, force buys y full buys.', 
'Entender la economía es clave para ganar rondas importantes.\n\n## Reglas básicas\n- Pistola perdida: generalmente eco\n- Pistola ganada: compra ligera o full buy según dinero\n- Tras ganar varias rondas: mantener economía\n\n## Force buy\nSolo hacer force buy en situaciones muy específicas donde la información lo justifica.', 61, 0, 0, 1),
(2, 1, 1, 'Matchup Zed vs control mages en mid lane', 'Cómo sobrevivir al early contra Zed y escalar al mid game.', 
'Zed es un campeón de all-in temprano; la clave es el posicionamiento.\n\n## Early game\n- Mantén distancia\n- Guarda tus habilidades para defender\n- Farmea de forma segura\n\n## Mid game\nUna vez pasas el nivel 6 con ventaja, puedes empezar a buscar oportunidades.', 45, 0, 0, 1),
(4, 3, 3, 'Zonas de drop seguras en Fortnite', 'Mejores zonas de drop para farmear y sobrevivir al early game.', 
'Las zonas de drop determinan tu early game y tus rotaciones.\n\n## Zonas recomendadas\n- Zona menos concurrida al inicio\n- Buena cobertura\n- Acceso fácil a materiales\n\nPrioriza la supervivencia sobre el loot en las primeras partidas.', 52, 0, 0, 1);

-- --------------------------------------------------------
-- Datos: foro posts
-- --------------------------------------------------------

INSERT INTO `foro_posts` (`usuario_id`, `titulo`, `contenido`, `vistas`, `fijado`) VALUES
(5, '¿Cuál es el mejor campeón para empezar en LoL?', 
'Soy nuevo y no sé por quién empezar. ¿Recomendaciones de campeones sencillos?\n\nLlevo unas semanas jugando y me pierdo con la cantidad de campeones. ¿Cuáles recomiendan para aprender las mecánicas base?', 150, 0),
(4, 'Economía en CS2: estrategias de ecos', 
'En partidas igualadas la economía decide muchas rondas. Compartid vuestras reglas de oro.\n\n¿Cuándo conviene hacer eco completo y cuándo forzar compra?', 120, 0),
(1, 'Rotaciones del final de partida en Fortnite', 
'Siempre me pillan las rotaciones y pierdo por la tormenta. ¿Consejos para el late game?\n\nBusco tips para posicionarme mejor en las últimas zonas.', 98, 0);

-- --------------------------------------------------------
-- Datos: foro comentarios
-- --------------------------------------------------------

INSERT INTO `foro_comentarios` (`post_id`, `usuario_id`, `contenido`) VALUES
(1, 2, 'Empieza con Garen o Annie. Son simples y te enseñan conceptos clave.'),
(1, 1, 'También te recomiendo jugar todos los roles una vez para entender el juego.'),
(2, 3, 'Regla simple: nunca compres de más si perdiste la ronda de pistolas.'),
(2, 3, 'Los force buys funcionan en maps pequeños (Mirage) si ganáis el primer duelo.'),
(3, 4, 'Mueve siempre con altura y nunca te quedes sin materiales en la cuarta zona.');

COMMIT;
