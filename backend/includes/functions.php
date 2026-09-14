<?php
/**
 * CoreTT - Funciones auxiliares: respuestas JSON, peticiones, usuario actual,
 * saneamiento y utilidades compartidas por los endpoints.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
   Respuestas HTTP
--------------------------------------------------------------------- */

/**
 * Envia una respuesta JSON y termina la ejecucion.
 */
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Envia una respuesta de error estandar.
 */
function error_response(string $message, int $status = 400, array $errors = []): void
{
    $payload = ['error' => $message];
    if ($errors) {
        $payload['errors'] = $errors;
    }
    json_response($payload, $status);
}

/**
 * Respuesta de exito generica.
 */
function success_response(array $data = []): void
{
    json_response(array_merge(['success' => true], $data));
}

/* ---------------------------------------------------------------------
   Entrada HTTP
--------------------------------------------------------------------- */

/**
 * Lee el cuerpo JSON de la peticion como array asociativo.
 */
function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * IP del cliente (con soporte de proxies estandar).
 */
function client_ip(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* ---------------------------------------------------------------------
   Autenticacion
--------------------------------------------------------------------- */

/**
 * Extrae el token Bearer del header Authorization.
 */
function bearer_token(): ?string
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        $headers = [];
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        }
    }

    $auth = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        return trim($m[1]);
    }
    return null;
}

/**
 * Devuelve el usuario autenticado (array asociativo) o null.
 */
function current_user(): ?array
{
    $token = bearer_token();
    if (!$token) {
        return null;
    }

    $hash = hash('sha256', $token);
    try {
        $stmt = db()->prepare(
            'SELECT u.* FROM sesiones s
               JOIN usuarios u ON u.id = s.usuario_id
              WHERE s.token_hash = :hash AND s.expira_en > NOW()
              LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        $user = $stmt->fetch();
        return $user ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Requiere usuario autenticado; si no lo hay responde 401.
 */
function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        error_response('Autenticacion requerida', 401);
    }
    return $user;
}

/**
 * Requiere usuario administrador; responde 403 en caso contrario.
 */
function require_admin(): array
{
    $user = require_auth();
    if ($user['rol'] !== 'admin') {
        error_response('Acceso restringido a administradores', 403);
    }
    return $user;
}

/* ---------------------------------------------------------------------
   Utilidades varias
--------------------------------------------------------------------- */

/**
 * Trimea y limita un string (para inputs textuales).
 */
function trim_str(?string $value, int $max = 0): string
{
    $value = trim((string)$value);
    if ($max > 0 && mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

/**
 * Trimea y limita un texto largo (minimizando saltos raros).
 */
function trim_text(?string $value, int $max = 0): string
{
    $value = preg_replace('/\r\n?/', "\n", (string)$value);
    $value = trim($value);
    if ($max > 0 && mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

/**
 * Extrae un entero de los parametros (query o body), con valor por defecto.
 */
function param_int(string $key, int $default = 0, array $from = null): int
{
    $source = $from ?? $_GET;
    return isset($source[$key]) ? (int)$source[$key] : $default;
}

/**
 * Extrae un string de los parametros.
 */
function param_str(string $key, string $default = '', array $from = null): string
{
    $source = $from ?? $_GET;
    return isset($source[$key]) ? trim((string)$source[$key]) : $default;
}

/**
 * Formatea una fecha MySQL a ISO sin horas: 'YYYY-MM-DD'.
 */
function date_only($value): string
{
    if ($value === null) {
        return '';
    }
    $t = is_int($value) ? $value : strtotime((string)$value);
    return $t ? date('Y-m-d', $t) : (string)$value;
}

/**
 * Paginacion segura.
 */
function paginate(array $params = []): array
{
    $defaults = ['page' => 1, 'limit' => 12];
    $params   = array_merge($defaults, $params);

    $page  = max(1, (int)$params['page']);
    $limit = min(100, max(1, (int)$params['limit']));
    $offset = ($page - 1) * $limit;

    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}