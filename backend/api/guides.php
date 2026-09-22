<?php
// CoreTT - Guías: GET (lista/detalle), POST (crear), POST?action=vote, DELETE (admin)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validations.php';
sendCors();
sendSecurityHeaders();

$ALLOWED_GAMES = ['League of Legends', 'Counter-Strike 2', 'Fortnite'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $db = getDB();

    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            if (!validId($_GET['id'])) json(['error' => 'ID inválido'], 400);
            $st = $db->prepare('SELECT g.id, g.title, gm.name AS game, u.name AS author, g.description, g.content, g.votes, DATE(g.created_at) AS date FROM guides g LEFT JOIN games gm ON gm.id = g.game_id LEFT JOIN users u ON u.id = g.user_id WHERE g.id = ?');
            $st->execute([(int)$_GET['id']]);
            $guide = $st->fetch();
            if (!$guide) json(['error' => 'Guía no encontrada'], 404);
            json($guide);
        }
        $sql = 'SELECT g.id, g.title, gm.name AS game, u.name AS author, g.description, g.votes, DATE(g.created_at) AS date FROM guides g LEFT JOIN games gm ON gm.id = g.game_id LEFT JOIN users u ON u.id = g.user_id';
        $params = [];
        if (!empty($_GET['game']) && $_GET['game'] !== 'Todos') {
            if (!validGame($_GET['game'], $ALLOWED_GAMES)) json(['error' => 'Juego inválido'], 400);
            $sql .= ' WHERE gm.name = ?';
            $params[] = $_GET['game'];
        }
        $sql .= ' ORDER BY g.votes DESC LIMIT 100';
        $st = $db->prepare($sql);
        $st->execute($params);
        json($st->fetchAll());
    }

    if ($method === 'POST' && ($_GET['action'] ?? '') === 'vote') {
        $auth = requireAuth();
        $in = getJsonInput();
        if (!validId($in['id'] ?? null) || !in_array($in['vote'] ?? null, [1, -1], true)) {
            json(['error' => 'Voto inválido'], 400);
        }
        $db->beginTransaction();
        $st = $db->prepare('INSERT INTO votes (user_id, guide_id, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $st->execute([(int)$auth['sub'], (int)$in['id'], (int)$in['vote']]);
        $db->prepare('UPDATE guides SET votes = (SELECT COALESCE(SUM(value),0) FROM votes WHERE guide_id = ?) WHERE id = ?')
           ->execute([(int)$in['id'], (int)$in['id']]);
        $st = $db->prepare('SELECT votes FROM guides WHERE id = ?');
        $st->execute([(int)$in['id']]);
        $votes = (int)($st->fetchColumn() ?: 0);
        $db->commit();
        json(['success' => true, 'votes' => $votes]);
    }

    if ($method === 'POST') {
        $auth = requireAuth();
        $in = getJsonInput();
        if (!validText($in['title'] ?? '', 5, 200) || !validGame($in['game'] ?? '', $ALLOWED_GAMES)
            || !validText($in['description'] ?? '', 10, 500) || !validText($in['content'] ?? '', 20, 20000)) {
            json(['error' => 'Datos de guía inválidos'], 400);
        }
        $st = $db->prepare('SELECT id FROM games WHERE name = ?');
        $st->execute([$in['game']]);
        $gameId = $st->fetchColumn();
        $st = $db->prepare('INSERT INTO guides (user_id, game_id, title, description, content) VALUES (?, ?, ?, ?, ?)');
        $st->execute([(int)$auth['sub'], $gameId, trim($in['title']), trim($in['description']), trim($in['content'])]);
        json(['success' => true, 'guide' => ['id' => (int)$db->lastInsertId()]], 201);
    }

    if ($method === 'DELETE') {
        requireAdmin();
        if (!validId($_GET['id'] ?? null)) json(['error' => 'ID inválido'], 400);
        $db->prepare('DELETE FROM guides WHERE id = ?')->execute([(int)$_GET['id']]);
        json(['success' => true]);
    }

    json(['error' => 'Método no permitido'], 405);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    json(['error' => 'Error interno'], 500);
}
