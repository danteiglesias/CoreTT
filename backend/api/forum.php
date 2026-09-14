<?php
/**
 * CoreTT - API del Foro.
 * Hilos de discusion y comentarios.
 *
 * GET    /backend/api/forum.php?page=&limit=&sort=
 * GET    /backend/api/forum.php?id={id}
 * POST   /backend/api/forum.php                 (crear hilo, auth)
 * POST   /backend/api/forum.php?action=comment  (comentar, auth)
 * PUT    /backend/api/forum.php?id={id}         (editar hilo, autor/admin)
 * DELETE /backend/api/forum.php?id={id}         (borrar, autor/admin)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/boot.php';

/**
 * Serializa un hilo (listado).
 */
function post_payload(array $p): array
{
    $content = $p['contenido'] ?? '';
    $excerpt = mb_strlen($content) > 180 ? mb_substr($content, 0, 180) . '...' : $content;

    return [
        'id'      => (int)$p['id'],
        'title'   => $p['titulo'],
        'author'  => $p['autor'],
        'authorId' => (int)$p['usuario_id'],
        'date'    => date_only($p['creado_en']),
        'excerpt' => $excerpt,
        'body'    => $content,
        'replies' => (int)($p['num_comentarios'] ?? 0),
        'can_delete' => false,
    ];
}

function posts_select(): string
{
    return 'SELECT p.id, p.titulo, p.contenido, p.usuario_id, p.creado_en,
                   u.nombre AS autor,
                   (SELECT COUNT(*) FROM foro_comentarios c WHERE c.post_id = p.id) AS num_comentarios
              FROM foro_posts p
              JOIN usuarios u ON u.id = p.usuario_id';
}

function comment_payload(array $c): array
{
    return [
        'author' => $c['autor'],
        'authorId' => (int)$c['usuario_id'],
        'date'   => date_only($c['creado_en']),
        'text'   => $c['contenido'],
    ];
}

/* ===================================================================== */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = param_str('action');
$body   = get_json_body();
$me     = current_user();
$pdo    = db();

switch (true) {

    /* ---------------- LISTADO ---------------- */
    case $method === 'GET' && !isset($_GET['id']):
        $search = param_str('search');
        $where  = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE (p.titulo LIKE :q OR p.contenido LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $totalStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM foro_posts p' . $where);
        $totalStmt->execute($params);
        $total = (int)$totalStmt->fetch()['total'];

        $p  = paginate(['page' => param_int('page', 1), 'limit' => param_int('limit', 15)]);
        $sql = posts_select() . $where . ' ORDER BY p.creado_en DESC LIMIT :lim OFFSET :off';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $p['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':off', $p['offset'], PDO::PARAM_INT);
        $stmt->execute();

        json_response([
            'posts' => array_map('post_payload', $stmt->fetchAll()),
            'total' => $total,
            'page'  => $p['page'],
            'limit' => $p['limit'],
        ]);
        break;

    /* ---------------- DETALLE CON COMENTARIOS ---------------- */
    case $method === 'GET' && isset($_GET['id']):
        $stmt = $pdo->prepare(posts_select() . ' WHERE p.id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$_GET['id']]);
        $post = $stmt->fetch();
        if (!$post) {
            error_response('Post no encontrado', 404);
        }

        $post['num_comentarios'] = 0;

        $stmt = $pdo->prepare(
            'SELECT c.contenido, c.usuario_id, c.creado_en, u.nombre AS autor
               FROM foro_comentarios c JOIN usuarios u ON u.id = c.usuario_id
              WHERE c.post_id = :id ORDER BY c.creado_en ASC'
        );
        $stmt->execute([':id' => (int)$_GET['id']]);
        $comments = array_map('comment_payload', $stmt->fetchAll());

        $payload = post_payload($post);
        $payload['comments'] = $comments;
        $payload['replies'] = count($comments);
        if ($me) {
            $isOwner = (int)$me['id'] === (int)$post['usuario_id'];
            $isAdmin = $me['rol'] === 'admin';
            $payload['can_delete'] = $isOwner || $isAdmin;
            $payload['can_edit']   = $isOwner || $isAdmin;
        }

        json_response($payload);
        break;

    /* ---------------- COMENTAR ---------------- */
    case $method === 'POST' && $action === 'comment':
        require_auth();

        $postId = (int)($body['postId'] ?? 0);
        if ($postId <= 0) {
            error_response('ID de post requerido', 422);
        }

        $stmt = $pdo->prepare('SELECT id FROM foro_posts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $postId]);
        if (!$stmt->fetch()) {
            error_response('Post no encontrado', 404);
        }

        $text = trim_text($body['text'] ?? ($body['contenido'] ?? ''), 2000);
        bail_on_errors(validate_comment($text));

        $stmt = $pdo->prepare(
            'INSERT INTO foro_comentarios (post_id, usuario_id, contenido) VALUES (:p, :u, :c)'
        );
        $stmt->execute([':p' => $postId, ':u' => $me['id'], ':c' => $text]);

        json_response([
            'success' => true,
            'comment' => [
                'author'   => $me['nombre'],
                'authorId' => (int)$me['id'],
                'date'     => date('Y-m-d'),
                'text'     => $text,
            ],
        ], 201);
        break;

    /* ---------------- CREAR HILO ---------------- */
    case $method === 'POST':
        require_auth();

        $data = [
            'titulo'    => $body['titulo'] ?? ($body['title'] ?? ''),
            'contenido' => $body['contenido'] ?? ($body['body'] ?? ''),
        ];
        bail_on_errors(validate_post($data));

        $stmt = $pdo->prepare('INSERT INTO foro_posts (usuario_id, titulo, contenido) VALUES (:u, :t, :c)');
        $stmt->execute([':u' => $me['id'], ':t' => $data['titulo'], ':c' => $data['contenido']]);

        json_response(['success' => true, 'id' => (int)$pdo->lastInsertId()], 201);
        break;

    /* ---------------- EDITAR HILO ---------------- */
    case $method === 'PUT':
        require_auth();
        $postId = param_int('id');
        if ($postId <= 0) {
            error_response('ID de post requerido', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM foro_posts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();
        if (!$post) {
            error_response('Post no encontrado', 404);
        }

        $isOwner = (int)$me['id'] === (int)$post['usuario_id'];
        if (!$isOwner && $me['rol'] !== 'admin') {
            error_response('No tienes permiso para editar este post', 403);
        }

        $data = [
            'titulo'    => trim_str($body['titulo'] ?? ($body['title'] ?? $post['titulo']), 150),
            'contenido' => trim_text($body['contenido'] ?? ($body['body'] ?? $post['contenido'])),
        ];
        bail_on_errors(validate_post($data));

        $stmt = $pdo->prepare('UPDATE foro_posts SET titulo = :t, contenido = :c WHERE id = :id');
        $stmt->execute([':t' => $data['titulo'], ':c' => $data['contenido'], ':id' => $postId]);

        success_response(['id' => $postId]);
        break;

    /* ---------------- ELIMINAR HILO ---------------- */
    case $method === 'DELETE':
        require_auth();
        $postId = param_int('id', 0, array_merge($_GET, $body));
        if ($postId <= 0) {
            error_response('ID de post requerido', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM foro_posts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();
        if (!$post) {
            error_response('Post no encontrado', 404);
        }

        $isOwner = (int)$me['id'] === (int)$post['usuario_id'];
        if (!$isOwner && $me['rol'] !== 'admin') {
            error_response('No tienes permiso para eliminar este post', 403);
        }

        $stmt = $pdo->prepare('DELETE FROM foro_posts WHERE id = :id');
        $stmt->execute([':id' => $postId]);

        success_response();
        break;

    default:
        error_response('Metodo no soportado', 405);
}