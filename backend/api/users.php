<?php
/**
 * CoreTT - API de Usuarios.
 *
 * GET    /backend/api/users.php                        -> listado (admin)
 * POST   /backend/api/users.php?action=register        -> registro publico
 * POST   /backend/api/users.php?action=login            -> login
 * POST   /backend/api/users.php?action=logout           -> logout (auth)
 * GET    /backend/api/users.php?action=profile           -> perfil del usuario (auth)
 * PUT    /backend/api/users.php?action=profile           -> actualizar perfil (auth)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/boot.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = param_str('action');
$body   = get_json_body();
$me     = current_user();
$pdo    = db();

/**
 * Impide quedarse sin ningun administrador activo.
 */
function ensure_not_last_admin(PDO $pdo): void
{
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin'");
    if ((int)$stmt->fetch()['total'] <= 1) {
        error_response('Debe existir al menos un administrador', 422);
    }
}

switch (true) {

    /* ---------------- REGISTRO ---------------- */
    case $method === 'POST' && $action === 'register':
        $name     = trim_str(body_str_any($body, ['nombre', 'name']));
        $email    = normalize_email(body_str($body, 'email'));
        $password = body_str($body, 'password');

        $user = register_user($name, $email, $password);

        json_response([
            'success' => true,
            'message' => 'Cuenta creada correctamente. Ahora puedes iniciar sesion.',
            'user'    => $user,
        ], 201);
        break;

    /* ---------------- LOGIN ---------------- */
    case $method === 'POST' && $action === 'login':
        $email    = normalize_email(body_str($body, 'email'));
        $password = body_str($body, 'password');
        json_response(attempt_login($email, $password));
        break;

    /* ---------------- LOGOUT ---------------- */
    case $method === 'POST' && $action === 'logout':
        require_auth();
        destroy_current_session();
        success_response(['message' => 'Sesion cerrada']);
        break;

    /* ---------------- PERFIL: actualizar ---------------- */
    case $method === 'PUT' && $action === 'profile':
        require_auth();

        $rawNombre = body_str_any($body, ['nombre', 'name'], (string)$me['nombre']);
        $rawRango  = body_str_any($body, ['rango', 'rank'], (string)($me['rango'] ?? ''));

        $errors = [];
        if (mb_strlen($rawNombre) > MAX_NOMBRE) {
            $errors['nombre'] = 'El nombre no puede superar los ' . MAX_NOMBRE . ' caracteres.';
        }
        if (mb_strlen($rawRango) > MAX_RANGO) {
            $errors['rango'] = 'El rango no puede superar los ' . MAX_RANGO . ' caracteres.';
        }
        bail_on_errors($errors);

        $nombre = trim_str($rawNombre, MAX_NOMBRE);
        $rango  = trim_str($rawRango, MAX_RANGO);

        $nameErrors = validate_name($nombre);
        if ($nombre === '') {
            $nameErrors['nombre'] = 'El nombre es obligatorio.';
        }
        bail_on_errors($nameErrors);

        // Si el nombre cambio, verificar unicidad
        if ($nombre !== $me['nombre']) {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE nombre = :n AND id != :id LIMIT 1');
            $stmt->execute([':n' => $nombre, ':id' => $me['id']]);
            if ($stmt->fetch()) {
                error_response('Ese nombre ya esta en uso', 422, ['nombre' => 'Nombre no disponible']);
            }
        }

        $stmt = $pdo->prepare('UPDATE usuarios SET nombre = :n, rango = :r WHERE id = :id');
        $stmt->execute([':n' => $nombre, ':r' => $rango !== '' ? $rango : null, ':id' => $me['id']]);

        success_response(['nombre' => $nombre, 'rango' => $rango]);
        break;

    /* ---------------- PERFIL: consultar ---------------- */
    case $method === 'GET' && $action === 'profile':
        require_auth();

        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $me['id']]);
        $user = $stmt->fetch();
        if (!$user) {
            error_response('Usuario no encontrado', 404);
        }

        // Contar guias propias
        $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM guias WHERE usuario_id = :id');
        $stmt->execute([':id' => $me['id']]);
        $numGuides = (int)$stmt->fetch()['total'];

        // Suma de votos recibidos
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(v.valor), 0) AS votos
               FROM votos v
               JOIN guias g ON g.id = v.guia_id
              WHERE g.usuario_id = :id'
        );
        $stmt->execute([':id' => $me['id']]);
        $totalVotes = (int)$stmt->fetch()['votos'];

        json_response([
            'id'         => (int)$user['id'],
            'name'       => $user['nombre'],
            'email'      => $user['email'],
            'role'       => $user['rol'],
            'rank'       => $user['rango'] ?? null,
            'guides'     => $numGuides,
            'votes'      => $totalVotes,
            'reputation' => (int)$user['reputacion'],
            'created_at' => date_only($user['creado_en']),
        ]);
        break;

    /* ---------------- CAMBIAR ROL (admin) ---------------- */
    case $method === 'POST' && $action === 'role':
        require_admin();

        $userId = (int)($body['id'] ?? 0);
        $rol    = trim_str($body['rol'] ?? ($body['role'] ?? ''));
        if ($userId <= 0 || !in_array($rol, ['admin', 'user'], true)) {
            error_response('Datos invalidos', 422, ['usuario' => 'ID y rol requeridos (admin|user)']);
        }

        $stmt = $pdo->prepare('SELECT id, rol FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $target = $stmt->fetch();
        if (!$target) {
            error_response('Usuario no encontrado', 404);
        }
        if ((int)$userId === (int)$me['id']) {
            error_response('No puedes cambiar tu propio rol', 422);
        }

        // Sin esta comprobacion, degradar al unico admin deja el sitio
        // sin ninguna cuenta capaz de administrarlo.
        if ($target['rol'] === 'admin' && $rol !== 'admin') {
            ensure_not_last_admin($pdo);
        }

        $stmt = $pdo->prepare('UPDATE usuarios SET rol = :r WHERE id = :id');
        $stmt->execute([':r' => $rol, ':id' => $userId]);

        success_response(['id' => $userId, 'rol' => $rol]);
        break;

    /* ---------------- ELIMINAR USUARIO (admin) ---------------- */
    case $method === 'DELETE':
        require_admin();

        $userId = param_int('id', 0, array_merge($_GET, $body));
        if ($userId <= 0) {
            error_response('ID de usuario requerido', 422);
        }
        if ((int)$userId === (int)$me['id']) {
            error_response('No puedes eliminar tu propia cuenta', 422);
        }

        $stmt = $pdo->prepare('SELECT id, rol, email FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $target = $stmt->fetch();
        if (!$target) {
            error_response('Usuario no encontrado', 404);
        }

        if ($target['rol'] === 'admin') {
            ensure_not_last_admin($pdo);
        }

        // Eliminar historial de intentos (no tiene FK)
        $stmt = $pdo->prepare('DELETE FROM login_intentos WHERE email = :e');
        $stmt->execute([':e' => normalize_email($target['email'])]);

        $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmt->execute([':id' => $userId]);

        success_response(['id' => $userId]);
        break;

    /* ---------------- LISTADO (admin) ---------------- */
    case $method === 'GET':
        require_admin();

        $p = paginate(['page' => param_int('page', 1), 'limit' => param_int('limit', 25)]);

        $totalStmt = $pdo->query('SELECT COUNT(*) AS total FROM usuarios');
        $total = (int)$totalStmt->fetch()['total'];

        $stmt = $pdo->prepare(
            'SELECT u.id, u.nombre, u.email, u.rol, u.rango, u.reputacion, u.creado_en,
                    (SELECT COUNT(*) FROM guias g WHERE g.usuario_id = u.id) AS num_guias,
                    (SELECT COALESCE(SUM(v.valor),0) FROM votos v
                      JOIN guias gg ON gg.id = v.guia_id WHERE gg.usuario_id = u.id) AS votos
               FROM usuarios u
              ORDER BY u.reputacion DESC, u.creado_en DESC
              LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':lim', $p['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':off', $p['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $users = array_map(function ($u) {
            return [
                'id'         => (int)$u['id'],
                'name'       => $u['nombre'],
                'email'      => $u['email'],
                'role'       => $u['rol'],
                'rank'       => $u['rango'],
                'reputation' => (int)$u['reputacion'],
                'guides'     => (int)$u['num_guias'],
                'votes'      => (int)$u['votos'],
            ];
        }, $stmt->fetchAll());

        json_response(['users' => $users, 'total' => $total, 'page' => $p['page'], 'limit' => $p['limit']]);
        break;

    default:
        error_response('Metodo no soportado', 405);
}