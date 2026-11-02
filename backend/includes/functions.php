<?php
/**
 * Funciones auxiliares para CoreTT
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Enviar respuesta JSON
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Enviar error JSON
 */
function sendError($message, $statusCode = 400) {
    sendResponse(array("error" => $message), $statusCode);
}

/**
 * Obtener datos JSON del body
 */
function getJsonInput() {
    $input = file_get_contents("php://input");
    return json_decode($input, true);
}

/**
 * Sanitizar datos
 */
function sanitize($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitize($value);
        }
        return $data;
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generar token JWT simple
 */
function generateToken($userId) {
    $header = base64_encode(json_encode(array('typ' => 'JWT', 'alg' => 'HS256')));
    $payload = base64_encode(json_encode(array(
        'user_id' => $userId,
        'iat' => time(),
        'exp' => time() + (86400 * 7) // 7 días
    )));
    $signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, 'corett_secret_key', true));
    return $header . '.' . $payload . '.' . $signature;
}

/**
 * Verificar token JWT simple
 */
function verifyToken($token) {
    if (!$token) return false;
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    
    $header = $parts[0];
    $payload = $parts[1];
    $signature = $parts[2];
    
    $expectedSignature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, 'corett_secret_key', true));
    
    if ($signature !== $expectedSignature) return false;
    
    $payloadData = json_decode(base64_decode($payload), true);
    if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
        return false;
    }
    
    return $payloadData;
}

/**
 * Obtener usuario desde token
 */
function getUserFromToken() {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
    
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        $payload = verifyToken($token);
        if ($payload && isset($payload['user_id'])) {
            $db = getDB();
            $query = "SELECT id, nombre_usuario, email, rol, rango, reputacion FROM usuarios WHERE id = :id AND activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $payload['user_id']);
            $stmt->execute();
            return $stmt->fetch();
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
?>
