<?php
// CoreTT - Usuarios: login, register, profile, listado (admin)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validations.php';
sendCors();
sendSecurityHeaders();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $db = getDB();
    $action = $_GET['action'] ?? '';

    if ($method === 'POST' && $action === 'register') {
        $in = getJsonInput();
        if (!validText($in['name'] ?? '', 3, 50) || !validEmail($in['email'] ?? '') || !validPassword($in['password'] ?? '')) {
            json(['error' => 'Datos inválidos (nombre 3-50, email válido, password 8+)'], 400);
        }
        $hash = password_hash($in['password'], PASSWORD_BCRYPT);
        try {
            $st = $db->prepare("INSERT INTO users (name, email, password_hash, role, `rank`) VALUES (?, ?, ?, 'user', 'Bronce')");
            $st->execute([trim($in['name']), strtolower(trim($in['email'])), $hash]);
        } catch (PDOException $e) {
            json(['error' => 'Email o usuario ya registrado'], 409);
        }
        json(['success' => true, 'message' => 'Cuenta creada correctamente. Ahora puedes iniciar sesión.'], 201);
    }

    if ($method === 'POST' && $action === 'login') {
        $in = getJsonInput();
        if (!validEmail($in['email'] ?? '') || empty($in['password'])) {
            json(['error' => 'Credenciales inválidas'], 401);
        }
        $st = $db->prepare('SELECT id, name, email, password_hash, role, `rank` FROM users WHERE email = ?');
        $st->execute([strtolower(trim($in['email']))]);
        $u = $st->fetch();
        if (!$u || !password_verify($in['password'], $u['password_hash'])) {
            json(['error' => 'Credenciales inválidas'], 401); // mensaje genérico anti-enumeración
        }
        unset($u['password_hash']);
        json(['success' => true, 'token' => createToken((int)$u['id'], $u['role']), 'user' => $u]);
    }

    if ($method === 'GET' && $action === 'profile') {
        $auth = requireAuth();
        $st = $db->prepare('SELECT name, email, `rank`, role FROM users WHERE id = ?');
        $st->execute([(int)$auth['sub']]);
        $u = $st->fetch();
        if (!$u) json(['error' => 'No autenticado'], 401);
        $st = $db->prepare('SELECT COUNT(*), COALESCE(SUM(votes),0) FROM guides WHERE user_id = ?');
        $st->execute([(int)$auth['sub']]);
        [$nGuides, $nVotes] = $st->fetch(PDO::FETCH_NUM);
        json(['name' => $u['name'], 'email' => $u['email'], 'rank' => $u['rank'], 'guides' => (int)$nGuides, 'votes' => (int)$nVotes, 'reputation' => $u['role'] === 'admin' ? 100 : '--']);
    }

    if ($method === 'GET' && $action === '') {
        requireAdmin(); // solo admins listan emails
        $users = $db->query('SELECT u.id, u.name, u.email, u.role, u.`rank` AS `rank`, (SELECT COUNT(*) FROM guides g WHERE g.user_id = u.id) AS guides, (SELECT COALESCE(SUM(g.votes),0) FROM guides g WHERE g.user_id = u.id) AS votes FROM users u ORDER BY u.id LIMIT 200')->fetchAll();
        foreach ($users as &$u) { $u['guides'] = (int)$u['guides']; $u['votes'] = (int)$u['votes']; }
        json($users);
    }

    json(['error' => 'Método no permitido'], 405);
} catch (Throwable $e) {
    json(['error' => 'Error interno'], 500);
}
