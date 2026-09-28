<?php
/**
 * CoreTT - API de Juegos.
 *
 * GET /backend/api/games.php                -> juegos con parche actual
 * GET /backend/api/games.php?action=stats&game={slug} -> meta stats del juego
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/boot.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = param_str('action');
$pdo    = db();

if ($method !== 'GET') {
    error_response('Metodo no permitido', 405);
}

/**
 * Busca un juego por slug o por nombre.
 */
function find_game(PDO $pdo, string $game): ?array
{
    // Marcadores separados: reutilizar el mismo marcador nombrado con
    // PDO::ATTR_EMULATE_PREPARES desactivado lanza SQLSTATE[HY093].
    $stmt = $pdo->prepare('SELECT id, slug, nombre FROM juegos WHERE slug = :slug OR nombre = :nombre LIMIT 1');
    $stmt->execute([':slug' => $game, ':nombre' => $game]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* -------- Meta stats por juego -------- */
if ($action === 'stats') {
    $game = param_str('game');
    if ($game === '') {
        error_response('Parametro game requerido', 422);
    }

    $juego = find_game($pdo, mb_substr($game, 0, MAX_BUSQUEDA));
    if (!$juego) {
        error_response('Juego no encontrado', 404);
    }

    $stmt = $pdo->prepare(
        'SELECT entidad, categoria, dato, valor, actualizado_en
           FROM meta_stats
          WHERE juego_id = :id
          ORDER BY categoria, entidad'
    );
    $stmt->execute([':id' => $juego['id']]);

    $stats = array_map(function ($s) {
        return [
            'entity'    => $s['entidad'],
            'category'  => $s['categoria'],
            'stat'      => $s['dato'],
            'value'     => $s['valor'],
            'updated'   => date_only($s['actualizado_en']),
        ];
    }, $stmt->fetchAll());

    // Se devuelve el nombre canonico de la BD, nunca el parametro recibido:
    // reflejarlo crudo convertia la respuesta en un sink de contenido.
    json_response([
        'game'  => $juego['nombre'],
        'slug'  => $juego['slug'],
        'stats' => $stats,
    ]);
}

/* -------- Listado de juegos -------- */
$stmt = $pdo->query(
    'SELECT j.id, j.slug, j.nombre,
            (SELECT p.nombre FROM parches p WHERE p.juego_id = j.id
              ORDER BY p.fecha DESC, p.id DESC LIMIT 1) AS parche_actual,
            (SELECT COUNT(*) FROM meta_stats m WHERE m.juego_id = j.id) AS stats_count
       FROM juegos j
      ORDER BY j.id'
);

$games = array_map(function ($g) {
    return [
        'id'           => (int)$g['id'],
        'slug'         => $g['slug'],
        'name'         => $g['nombre'],
        'patch'        => $g['parche_actual'] ?? null,
        'stats_count'  => (int)$g['stats_count'],
    ];
}, $stmt->fetchAll());

json_response($games);
