<?php
/**
 * Funciones auxiliares para CoreTT
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Polyfill de getallheaders() para PHP-FPM / servidores sin Apache.
 */
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
}

/**
 * Enviar respuesta JSON
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Enviar error JSON
 */
function sendError($message, $statusCode = 400) {
    sendResponse(array('error' => $message), $statusCode);
}

/**
 * Obtener datos JSON del body
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : array();
}

/**
 * Limpiar datos de entrada.
 *
 * Se eliminan etiquetas HTML y espacios sobrantes. No se usa htmlspecialchars
 * aquí porque el frontend ya escapa al mostrar y, si se escapara dos veces,
 * aparecerían textos como "&amp;amp;".
 */
function sanitize($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize($value);
        }
        return $data;
    }
    if (!is_scalar($data)) {
        return '';
    }
    return trim(strip_tags((string) $data));
}

/**
 * Obtener la clave secreta de los tokens.
 */
function getSecretKey() {
    $key = (string) corett_env('SECRET_KEY', 'corett_secret_key_change_in_production');
    return $key !== '' ? $key : 'corett_secret_key_change_in_production';
}

/**
 * Generar token JWT simple
 */
function generateToken($userId) {
    $header = base64_encode(json_encode(array('typ' => 'JWT', 'alg' => 'HS256')));
    $payload = base64_encode(json_encode(array(
        'user_id' => (int) $userId,
        'iat' => time(),
        'exp' => time() + (86400 * 7) // 7 días
    )));
    $signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, getSecretKey(), true));
    return $header . '.' . $payload . '.' . $signature;
}

/**
 * Verificar token JWT simple
 */
function verifyToken($token) {
    if (!$token || !is_string($token)) {
        return false;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    list($header, $payload, $signature) = $parts;

    $expectedSignature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, getSecretKey(), true));
    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }

    $payloadData = json_decode(base64_decode($payload), true);
    if (!is_array($payloadData)) {
        return false;
    }
    if (isset($payloadData['exp']) && (int) $payloadData['exp'] < time()) {
        return false;
    }

    return $payloadData;
}

/**
 * Obtener el encabezado Authorization en distintos SAPIs.
 */
function getAuthorizationHeader() {
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        return $headers['Authorization'];
    }
    if (isset($headers['authorization'])) {
        return $headers['authorization'];
    }

    return '';
}

/**
 * Obtener usuario desde token
 */
function getUserFromToken() {
    $authHeader = getAuthorizationHeader();

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
        $payload = verifyToken($token);
        if ($payload && isset($payload['user_id'])) {
            $db = getDB();
            $query = "SELECT id, nombre_usuario, email, rol, rango, reputacion
                      FROM usuarios WHERE id = :id AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', (int) $payload['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch();
            return $user ?: false;
        }
    }

    return false;
}

/**
 * Hash de contraseña
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verificar contraseña
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
