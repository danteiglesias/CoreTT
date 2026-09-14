<?php
/**
 * CoreTT - Indice del backend.
 * Health check simple y descripcion de la API.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // Si la base de datos responde, lo notificamos.
    $dbOk = false;
    try {
        db()->query('SELECT 1');
        $dbOk = true;
    } catch (PDOException $e) {
        $dbOk = false;
    }

    json_response([
        'name'    => 'CoreTT API',
        'version' => '1.0.0',
        'status'  => $dbOk ? 'ok' : 'degraded',
        'db'      => $dbOk ? 'connected' : 'unavailable',
        'endpoints' => [
            'GET  /backend/api/guides.php',
            'GET  /backend/api/guides.php?id={id}',
            'POST /backend/api/guides.php',
            'POST /backend/api/guides.php?action=vote',
            'PUT  /backend/api/guides.php?id={id}',
            'DELETE /backend/api/guides.php?id={id}',
            'GET  /backend/api/forum.php',
            'GET  /backend/api/forum.php?id={id}',
            'POST /backend/api/forum.php',
            'POST /backend/api/forum.php?action=comment',
            'DELETE /backend/api/forum.php?id={id}',
            'GET  /backend/api/games.php',
            'GET  /backend/api/users.php',
            'POST /backend/api/users.php?action=register',
            'POST /backend/api/users.php?action=login',
            'POST /backend/api/users.php?action=logout',
            'GET  /backend/api/users.php?action=profile',
        ],
    ]);
}

json_response(['error' => 'Metodo no permitido'], 405);