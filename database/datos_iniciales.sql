-- CoreTT - Datos iniciales (refleja frontend/js/api.js demoDB)
USE corett;

INSERT IGNORE INTO games (id, name) VALUES
(1, 'League of Legends'),
(2, 'Counter-Strike 2'),
(3, 'Fortnite');

-- Admin demo: password = 'Admin123!' (cambiar en producción).
-- Hash generado con: password_hash('Admin123!', PASSWORD_BCRYPT)
INSERT IGNORE INTO users (id, name, email, password_hash, role, `rank`) VALUES
(1, 'Admin', 'admin@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Diamante'),
(2, 'JohnDoe', 'john@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Oro'),
(3, 'SmokeMaster', 'smoke@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Global Elite'),
(4, 'VictoryRoyale', 'victory@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Champion'),
(5, 'NewPlayer', 'new@corett.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'Bronce');

INSERT IGNORE INTO guides (id, user_id, game_id, title, description, content, votes) VALUES
(1, 2, 1, 'Cómo dominar el macro en League of Legends', 'Control de oleadas, visión y toma de decisiones para subir de elo.', 'El macrojuego es la habilidad de tomar las decisiones correctas...', 128),
(2, 3, 2, 'Lineups de humo en Mirage - CS2', 'Los mejores lineups de humo para Mirage, con posición y referencia.', 'En Mirage los humos en ventana (palace) y coctelera...', 96),
(3, 4, 3, 'Meta de armas temporada 5 - Fortnite', 'Ranking de armas según el parche actual y rotaciones óptimas.', 'La temporada 5 trae cambios importantes...', 74),
(4, NULL, 2, 'Economía en CS2: cuándo comprar y cuándo ahorrar', 'Guía de la economía del equipo: ecos, force buys y full buys.', 'Entender la economía es clave...', 61),
(5, NULL, 1, 'Matchup Zed vs control mages en mid lane', 'Cómo sobrevivir al early contra Zed y escalar al mid game.', 'Zed es un campeón de all-in temprano...', 45),
(6, NULL, 3, 'Zonas de drop seguras en Fortnite', 'Mejores zonas de drop para farmear y sobrevivir al early game.', 'Las zonas de drop determinan tu early game...', 52);

INSERT IGNORE INTO forum_posts (id, user_id, title, body) VALUES
(1, 5, '¿Cuál es el mejor campeón para empezar en LoL?', 'Llevo unas semanas jugando y me pierdo con la cantidad de campeones. ¿Cuáles recomiendan?'),
(2, NULL, 'Economía en CS2: estrategias de ecos', 'En partidas igualadas la economía decide muchas rondas. Compartid vuestras reglas de oro.'),
(3, NULL, 'Rotaciones del final de partida en Fortnite', 'Siempre me pillan las rotaciones y pierdo por la tormenta. ¿Consejos para el late game?');
