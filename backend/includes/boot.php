<?php
/**
 * CoreTT - Bootstrap de los endpoints.
 * Configura CORS, manejo de errores y carga las dependencias comunes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/validations.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/votes.php';

date_default_timezone_set('UTC');

/* ---------------------------------------------------------------------
   CORS
--------------------------------------------------------------------- */
$allowedOrigin = env('ALLOWED_ORIGIN', '*');
if ($allowedOrigin === '*') {
    header('Access-Control-Allow-Origin: *');
} else {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === $allowedOrigin) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

// Respuesta a preflight CORS
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ---------------------------------------------------------------------
   Errores y logging
--------------------------------------------------------------------- */
$appEnv = env('APP_ENV', 'development');
if ($appEnv === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

// Captura de excepciones genericas -> JSON 500
set_exception_handler(function (Throwable $e) {
    $appEnv = env('APP_ENV', 'development');
    $message = $appEnv === 'development' ? $e->getMessage() : 'Error interno del servidor';
    error_log('[CoreTT] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
});