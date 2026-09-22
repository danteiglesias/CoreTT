<?php
// CoreTT - Foro: GET lista/detalle, POST crear, POST?action=comment, DELETE (admin)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validations.php';
sendCors();
sendSecurityHeaders();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $db = getDB();

    if ($method === 'GET' && isset($_GET['id'])) {
        if (!validId($_GET['id'])) json(['error' => 'ID inválido'], 400);
        $st = $db->prepare('SELECT p.id, p.title, p.body, u.name AS author, DATE(p.created_at) AS date FROM forum_posts p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = ?');
        $st->execute([(int)$_GET['id']]);
        $post = $st->fetch();
        if (!$post) json(['error' => 'Post no encontrado'], 404);
        $st = $db->prepare('SELECT u.name AS author, DATE(c.created_at) AS date, c.text FROM forum_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.post_id = ? ORDER BY c.id LIMIT 200');
        $st->execute([$post['id']]);
        $post['comments'] = $st->fetchAll();
        $post['replies'] = count($post['comments']);
        $post['excerpt'] = mb_substr($post['body'], 0, 120);
        json($post);
    }

    if ($method === 'GET') {
        $posts = $db->query('SELECT p.id, p.title, LEFT(p.body, 120) AS excerpt, p.body, u.name AS author, DATE(p.created_at) AS date, (SELECT COUNT(*) FROM forum_comments c WHERE c.post_id = p.id) AS replies FROM forum_posts p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 100')->fetchAll();
        foreach ($posts as &$p) {
            $p['replies'] = (int)$p['replies'];
            $p['comments'] = [];
        }
        json($posts);
    }

    if ($method === 'POST' && ($_GET['action'] ?? '') === 'comment') {
        $auth = requireAuth();
        $in = getJsonInput();
        if (!validId($in['postId'] ?? null) || !validText($in['text'] ?? '', 1, 2000)) {
            json(['error' => 'Comentario inválido'], 400);
        }
        $st = $db->prepare('INSERT INTO forum_comments (post_id, user_id, text) VALUES (?, ?, ?)');
        $st->execute([(int)$in['postId'], (int)$auth['sub'], trim($in['text'])]);
        json(['success' => true, 'comment' => ['author' => 'Tú', 'text' => trim($in['text'])]], 201);
    }

    if ($method === 'POST') {
        $auth = requireAuth();
        $in = getJsonInput();
        if (!validText($in['title'] ?? '', 5, 200) || !validText($in['body'] ?? '', 10, 10000)) {
            json(['error' => 'Post inválido'], 400);
        }
        $st = $db->prepare('INSERT INTO forum_posts (user_id, title, body) VALUES (?, ?, ?)');
        $st->execute([(int)$auth['sub'], trim($in['title']), trim($in['body'])]);
        json(['success' => true, 'post' => ['id' => (int)$db->lastInsertId()]], 201);
    }

    if ($method === 'DELETE') {
        requireAdmin();
        if (!validId($_GET['id'] ?? null)) json(['error' => 'ID inválido'], 400);
        $db->prepare('DELETE FROM forum_posts WHERE id = ?')->execute([(int)$_GET['id']]);
        json(['success' => true]);
    }

    json(['error' => 'Método no permitido'], 405);
} catch (Throwable $e) {
    json(['error' => 'Error interno'], 500);
}
