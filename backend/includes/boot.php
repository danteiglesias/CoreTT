<?php
/**
 * CoreTT - Bootstrap de los endpoints.
 * Configura CORS, cabeceras de seguridad, manejo de errores y carga las
 * dependencias comunes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/validations.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/voting.php';

date_default_timezone_set('UTC');

// No anunciar la version de PHP.
if (function_exists('header_remove')) {
    @header_remove('X-Powered-By');
}

/* ---------------------------------------------------------------------
   Cabeceras de seguridad (se envian siempre, no solo en Apache)
   --------------------------------------------------------------------- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Permitted-Cross-Domain-Policies: none');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');

/* ---------------------------------------------------------------------
   CORS
   --------------------------------------------------------------------- */

/**
 * Origenes permitidos. "*" queda restringido al modo desarrollo porque
 * combinado con Authorization permite que cualquier sitio lea las respuestas
 * autenticadas de un navegador con la sesion abierta.
 */
function cors_allowed_origins(): array
{
    $raw = (string)env('ALLOWED_ORIGIN', '');
    $origins = [];
    foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $entry) {
        if ($entry !== '') {
            $origins[] = $entry;
        }
    }
    return $origins;
}

$origins = cors_allowed_origins();
$isWildcard = in_array('*', $origins, true);

if (!$isWildcard) {
    // Varia por origen: sin esto una cache podria servir la respuesta de
    // un dominio a otro.
    header('Vary: Origin');
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($isWildcard) {
    // Nunca se permiten credenciales con comodin.
    header('Access-Control-Allow-Origin: *');
} elseif ($origin !== '' && in_array($origin, $origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
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
error_reporting(E_ALL);

if (is_dev()) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
    // Los avisos/notices se registran pero nunca se imprimen en la respuesta.
    ini_set('log_errors', '1');
}

// Captura de excepciones genericas -> JSON 500
set_exception_handler(function (Throwable $e) {
    error_log('[CoreTT] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

    // El detalle real solo en desarrollo: en produccion un mensaje de
    // PDOMySQL filtra esquema, DSN y rutas del servidor.
    $message = is_dev() ? $e->getMessage() : 'Error interno del servidor';

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    }
    exit;
});
