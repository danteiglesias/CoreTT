<?php
/**
 * CoreTT - Indice del backend.
 * Health check. No expone el mapa de endpoints ni el estado de la base de
 * datos: eso es un oraculo de alcance y de disponibilidad para un atacante.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/boot.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $dbOk = false;
    try {
        db()->query('SELECT 1');
        $dbOk = true;
    } catch (PDOException $e) {
        $dbOk = false;
    }

    // 503 cuando la base de datos no responde: un health check que siempre
    // devuelve 200 no sirve para detectar caidas.
    json_response([
        'name'    => 'CoreTT API',
        'version' => '1.0.0',
        'status'  => $dbOk ? 'ok' : 'degraded',
    ], $dbOk ? 200 : 503);
}

error_response('Metodo no permitido', 405);
