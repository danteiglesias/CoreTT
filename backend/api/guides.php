<?php
/**
 * CoreTT - API de Guias.
 * CRUD de guias + votacion (con unicidad por usuario).
 *
 * GET    /backend/api/guides.php?game=&search=&page=&limit=&sort=
 * GET    /backend/api/guides.php?id={id}
 * GET    /backend/api/guides.php?action=my (mis guias)
 * POST   /backend/api/guides.php                 (crear, auth)
 * POST   /backend/api/guides.php?action=vote     (votar, auth)
 * PUT    /backend/api/guides.php?id={id}         (editar, autor/admin)
 * DELETE /backend/api/guides.php?id={id}         (borrar, autor/admin)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/boot.php';

const VALID_SORTS = ['recent', 'votes'];

/**
 * Asigna el conteo de votos y el voto del usuario a un conjunto de guias.
 */
function with_votes(array $rows, ?array $me): array
{
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    if (!$ids) {
        return [];
    }

    $pdo = db();
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $sumStmt = $pdo->prepare("SELECT guia_id, COALESCE(SUM(valor), 0) AS votos FROM votos WHERE guia_id IN ($in) GROUP BY guia_id");
    $sumStmt->execute($ids);
    $voteSums = [];
    foreach ($sumStmt->fetchAll() as $row) {
        $voteSums[(int)$row['guia_id']] = (int)$row['votos'];
    }

    $myVotes = [];
    if ($me) {
        $myVoteQuery = $pdo->prepare("SELECT guia_id, valor FROM votos WHERE usuario_id = ? AND guia_id IN ($in)");
        $myVoteQuery->execute(array_merge([$me['id']], $ids));
        foreach ($myVoteQuery->fetchAll() as $v) {
            $myVotes[(int)$v['guia_id']] = (int)$v['valor'];
        }
    }

    return array_map(function ($row) use ($voteSums, $myVotes, $me) {
        return guide_payload($row, $voteSums[(int)$row['id']] ?? 0, $myVotes[(int)$row['id']] ?? 0, $me);
    }, $rows);
}

/**
 * Serializa una guia para la API.
 */
function guide_payload(array $g, int $votes, int $myVote, ?array $me): array
{
    $isOwner = $me && ((int)$me['id'] === (int)$g['usuario_id']);
    $isAdmin = $me && $me['rol'] === 'admin';

    return [
        'id'          => (int)$g['id'],
        'title'       => $g['titulo'],
        'game'        => $g['juego'],
        'gameSlug'    => $g['slug'],
        'patch'       => $g['parche'] !== null ? $g['parche'] : null,
        'author'      => $g['autor'],
        'authorId'    => (int)$g['usuario_id'],
        'description' => $g['descripcion'] ?? '',
        'content'     => $g['contenido'] ?? '',
        'date'        => date_only($g['creado_en']),
        'votes'       => $votes,
        'myVote'      => $myVote,
        'can_edit'    => $isOwner || $isAdmin,
        'can_delete'  => $isOwner || $isAdmin,
    ];
}

/**
 * Base SELECT comun para listar guias publicadas.
 */
function guides_select(): string
{
    return 'SELECT g.id, g.titulo, g.descripcion, g.contenido, g.usuario_id,
                   g.juego_id, g.creado_en, g.parche_id,
                   j.nombre AS juego, j.slug,
                   u.nombre AS autor,
                   p.nombre AS parche
              FROM guias g
              JOIN juegos j ON j.id = g.juego_id
              JOIN usuarios u ON u.id = g.usuario_id
             LEFT JOIN parches p ON p.id = g.parche_id
             WHERE g.estado = \'publicada\'';
}

function resolve_game_id(PDO $pdo, string $game): ?int
{
    // Cada marcador con nombre debe usarse una sola vez cuando
    // PDO::ATTR_EMULATE_PREPARES esta desactivado: reutilizarlo lanza
    // SQLSTATE[HY093] y convertia crear/editar/filtrar guias en un HTTP 500.
    $stmt = $pdo->prepare('SELECT id FROM juegos WHERE slug = :slug OR nombre = :nombre LIMIT 1');
    $stmt->execute([':slug' => $game, ':nombre' => $game]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

/**
 * Aplica un voto con toggle: mismo valor lo anula, distinto lo cambia.
 * (Implementado en backend/includes/votes.php e incluido via boot.php.)
 */

/* =====================================================================
   Ruteo
===================================================================== */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = param_str('action');
$body   = get_json_body();
$me     = current_user();
$pdo    = db();

switch (true) {

    /* ---------------- LISTADO / DETALLE ---------------- */
    case $method === 'GET':

        // Guias del usuario autenticado (sin pasar por estado publicada)
        if ($action === 'my') {
            require_auth();
            $stmt = $pdo->prepare(guides_select() . ' AND g.usuario_id = :uid ORDER BY g.creado_en DESC');
            $stmt->execute([':uid' => $me['id']]);
            json_response(with_votes($stmt->fetchAll(), $me));
        }

        // Detalle
        if (isset($_GET['id']) && is_scalar($_GET['id'])) {
            $stmt = $pdo->prepare(guides_select() . ' AND g.id = :id LIMIT 1');
            $stmt->execute([':id' => param_int('id')]);
            $guide = $stmt->fetch();
            if (!$guide) {
                error_response('Guia no encontrada', 404);
            }

            $votes = 0;
            $myVote = 0;
            $stmt = $pdo->prepare('SELECT COALESCE(SUM(valor),0) v FROM votos WHERE guia_id = :g');
            $stmt->execute([':g' => $guide['id']]);
            $votes = (int)$stmt->fetch()['v'];

            if ($me) {
                $stmt = $pdo->prepare('SELECT valor FROM votos WHERE guia_id = :g AND usuario_id = :u LIMIT 1');
                $stmt->execute([':g' => $guide['id'], ':u' => $me['id']]);
                $row = $stmt->fetch();
                $myVote = $row ? (int)$row['valor'] : 0;
            }

            json_response(guide_payload($guide, $votes, $myVote, $me));
        }

        // Listado con filtros y paginacion
        $where  = '';
        $params = [];

        $game = param_str('game');
        if ($game !== '' && $game !== 'Todos') {
            $gameId = resolve_game_id($pdo, $game);
            if ($gameId === null) {
                error_response('Juego no encontrado', 404);
            }
            $where  .= ' AND g.juego_id = :gid';
            $params[':gid'] = $gameId;
        }

        $search = param_str('search');
        if ($search !== '') {
            $search = mb_substr($search, 0, MAX_BUSQUEDA);
            // Marcadores distintos por columna: un mismo marcador nombrado
            // repetido provoca SQLSTATE[HY093] sin emulacion de prepares.
            $where .= ' AND (g.titulo LIKE :q1 OR g.descripcion LIKE :q2 OR g.contenido LIKE :q3)';
            $like = '%' . escape_like($search) . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        $sort = param_str('sort', 'recent');
        if (!in_array($sort, VALID_SORTS, true)) {
            $sort = 'recent';
        }
        $order = $sort === 'votes'
            ? 'ORDER BY (SELECT COALESCE(SUM(valor),0) FROM votos v WHERE v.guia_id = g.id) DESC, g.creado_en DESC'
            : 'ORDER BY g.creado_en DESC';

        $totalStmt = $pdo->prepare(
            "SELECT COUNT(*) AS total FROM guias g JOIN juegos j ON j.id = g.juego_id WHERE g.estado = 'publicada' $where"
        );
        $totalStmt->execute($params);
        $total = (int)$totalStmt->fetch()['total'];

        $p  = paginate(['page' => param_int('page', 1), 'limit' => param_int('limit', 12)]);
        $stmt = $pdo->prepare(
            guides_select() . $where . " $order LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $p['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':off', $p['offset'], PDO::PARAM_INT);
        $stmt->execute();

        json_response([
            'guides' => with_votes($stmt->fetchAll(), $me),
            'total'  => $total,
            'page'   => $p['page'],
            'limit'  => $p['limit'],
        ]);
        break;

    /* ---------------- VOTAR ---------------- */
    case $method === 'POST' && $action === 'vote':
        require_auth();
        throttle('vote', (int)env('VOTE_MAX_PER_WINDOW', 300), (int)env('WRITE_WINDOW_SECONDS', 3600));
        $guideId = (int)body_str($body, 'id', '0');
        if ($guideId <= 0) {
            error_response('ID de guia requerido', 422);
        }
        json_response(apply_vote($guideId, (int)$me['id'], (int)body_str($body, 'vote', '0')));
        break;

    /* ---------------- CREAR ---------------- */
    case $method === 'POST':
        require_auth();
        throttle('guide_create', (int)env('GUIDE_MAX_PER_WINDOW', 20), (int)env('WRITE_WINDOW_SECONDS', 3600));

        $game  = trim_str(body_str($body, 'game'));
        $patch = trim_str(body_str($body, 'patch'));

        $gameId = $game !== '' ? resolve_game_id($pdo, $game) : null;
        if ($gameId === null) {
            error_response('Juego invalido', 422, ['juego_id' => 'Selecciona un juego valido.']);
        }

        $result = sanitize_guide([
            'titulo'      => body_str_any($body, ['titulo', 'title']),
            'descripcion' => body_str_any($body, ['descripcion', 'description']),
            'contenido'   => body_str_any($body, ['contenido', 'content']),
            'juego_id'    => $gameId,
        ]);
        bail_on_errors($result['errores']);
        $data = $result['datos'];

        $patchId = null;
        if ($patch !== '') {
            $stmt = $pdo->prepare('SELECT id FROM parches WHERE nombre = :p AND juego_id = :g LIMIT 1');
            $stmt->execute([':p' => $patch, ':g' => $gameId]);
            $row = $stmt->fetch();
            if ($row) {
                $patchId = (int)$row['id'];
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO guias (juego_id, usuario_id, titulo, descripcion, contenido, parche_id)
             VALUES (:g, :u, :t, :d, :c, :p)'
        );
        try {
            $stmt->execute([
                ':g' => $gameId,
                ':u' => $me['id'],
                ':t' => $data['titulo'],
                ':d' => $data['descripcion'],
                ':c' => $data['contenido'],
                ':p' => $patchId,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                error_response('No se pudo guardar la guia', 409);
            }
            throw $e;
        }

        $id = (int)$pdo->lastInsertId();
        json_response(['success' => true, 'id' => $id], 201);
        break;

    /* ---------------- EDITAR ---------------- */
    case $method === 'PUT':
        require_auth();
        $guideId = param_int('id');
        if ($guideId <= 0) {
            error_response('ID de guia requerido', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM guias WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $guideId]);
        $guide = $stmt->fetch();
        if (!$guide) {
            error_response('Guia no encontrada', 404);
        }
        if ((int)$guide['usuario_id'] !== (int)$me['id'] && $me['rol'] !== 'admin') {
            error_response('No tienes permiso para editar esta guia', 403);
        }

        $game  = trim_str(body_str($body, 'game'));
        $patch = trim_str(body_str($body, 'patch'));

        $gameId = $game !== '' ? resolve_game_id($pdo, $game) : (int)$guide['juego_id'];
        if ($gameId === null) {
            error_response('Juego invalido', 422, ['juego_id' => 'Selecciona un juego valido.']);
        }

        $result = sanitize_guide([
            'titulo'      => body_str_any($body, ['titulo', 'title'], (string)$guide['titulo']),
            'descripcion' => body_str_any($body, ['descripcion', 'description'], (string)($guide['descripcion'] ?? '')),
            'contenido'   => body_str_any($body, ['contenido', 'content'], (string)$guide['contenido']),
            'juego_id'    => $gameId,
        ]);
        bail_on_errors($result['errores']);
        $data = $result['datos'];

        $patchId = null;
        if ($patch !== '') {
            $stmt = $pdo->prepare('SELECT id FROM parches WHERE nombre = :p AND juego_id = :g LIMIT 1');
            $stmt->execute([':p' => $patch, ':g' => $gameId]);
            $row = $stmt->fetch();
            if ($row) {
                $patchId = (int)$row['id'];
            }
        } elseif (isset($body['patch'])) {
            $patchId = null;
        }

        $stmt = $pdo->prepare(
            'UPDATE guias SET juego_id = :g, titulo = :t, descripcion = :d,
                    contenido = :c, parche_id = :p WHERE id = :id'
        );
        $stmt->execute([
            ':g' => $gameId,
            ':t' => $data['titulo'],
            ':d' => $data['descripcion'],
            ':c' => $data['contenido'],
            ':p' => $patchId,
            ':id' => $guideId,
        ]);

        success_response(['id' => $guideId]);
        break;

    /* ---------------- ELIMINAR ---------------- */
    case $method === 'DELETE':
        require_auth();
        $guideId = param_int('id', 0, array_merge($_GET, $body));
        if ($guideId <= 0) {
            error_response('ID de guia requerido', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM guias WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $guideId]);
        $guide = $stmt->fetch();
        if (!$guide) {
            error_response('Guia no encontrada', 404);
        }
        if ((int)$guide['usuario_id'] !== (int)$me['id'] && $me['rol'] !== 'admin') {
            error_response('No tienes permiso para eliminar esta guia', 403);
        }

        $stmt = $pdo->prepare('DELETE FROM guias WHERE id = :id');
        $stmt->execute([':id' => $guideId]);

        success_response();
        break;

    default:
        error_response('Metodo no soportado', 405);
}