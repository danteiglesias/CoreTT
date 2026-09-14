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
    json_response(['error' => 'Metodo no permitido'], 405);
}

/* -------- Meta stats por juego -------- */
if ($action === 'stats') {
    $game = param_str('game');
    if ($game === '') {
        error_response('Parametro game requerido', 422);
    }

    $stmt = $pdo->prepare(
        'SELECT m.entidad, m.categoria, m.dato, m.valor, m.actualizado_en
           FROM meta_stats m JOIN juegos j ON j.id = m.juego_id
          WHERE j.slug = :g OR j.nombre = :g
          ORDER BY m.categoria, m.entidad'
    );
    $stmt->execute([':g' => $game]);

    json_response(['game' => $game, 'stats' => $stmt->fetchAll()]);
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