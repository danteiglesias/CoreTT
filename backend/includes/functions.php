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
 *
 * Se escapan los caracteres especiales de HTML/JS para que la respuesta
 * no pueda interpretarse como otro tipo de contenido aunque un cliente
 * sniffee el Content-Type.
 */
function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
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
 *
 * Limita el tamano leído para evitar agotar la memoria con cuerpos
 * enormes; si se supera, devuelve [] y el llamador debe responder 413.
 */
function get_json_body(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $maxBytes = max(1024, (int)env('MAX_BODY_BYTES', 262144));

    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > $maxBytes) {
        request_too_large();
    }

    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || trim((string)$raw) === '') {
        return $cached = [];
    }
    if (strlen($raw) > $maxBytes) {
        request_too_large();
    }

    $data = json_decode($raw, true);
    return $cached = is_array($data) ? $data : [];
}

/**
 * Corta la peticion con 413.
 */
function request_too_large(): void
{
    error_response('Cuerpo de la peticion demasiado grande', 413);
}

/**
 * IP real del cliente.
 *
 * Por defecto solo se confia en REMOTE_ADDR. El header X-Forwarded-For es
 * controlable por el cliente, por lo que ignorarlo es lo que evita que un
 * atacante evada el rate limiting falseando la IP. Solo se usa si el servidor
 * esta detras de un proxy de confianza declarado en TRUSTED_PROXIES, y en ese
 * caso se toma la direccion mas a la derecha que no pertenezca al propio proxy.
 */
function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';

    $trusted = trusted_proxies();
    if ($trusted && $remote !== '' && ip_in_list($remote, $trusted)) {
        $chain = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($chain !== '') {
            $parts = array_reverse(array_map('trim', explode(',', $chain)));
            foreach ($parts as $candidate) {
                if (!valid_ip($candidate)) {
                    continue;
                }
                if (!ip_in_list($candidate, $trusted)) {
                    return $candidate;
                }
            }
        }
    }

    return valid_ip($remote) ? $remote : '0.0.0.0';
}

/**
 * Comprueba que el valor sea una direccion IPv4/IPv6 valida.
 */
function valid_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Lista de proxies de confianza desde TRUSTED_PROXIES (IPs o CIDR v4).
 */
function trusted_proxies(): array
{
    static $list = null;
    if ($list === null) {
        $list = [];
        $raw = (string)env('TRUSTED_PROXIES', '');
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $entry) {
            if ($entry !== '') {
                $list[] = $entry;
            }
        }
    }
    return $list;
}

/**
 * Indica si una IP pertenece a una lista de IPs exactas o rangos CIDR v4.
 */
function ip_in_list(string $ip, array $list): bool
{
    if (!valid_ip($ip)) {
        return false;
    }
    $long = ip2long($ip);
    if ($long === false) {
        return false;
    }

    foreach ($list as $entry) {
        if (strpos($entry, '/') === false) {
            if (strcasecmp($entry, $ip) === 0) {
                return true;
            }
            continue;
        }
        [$subnet, $bits] = array_pad(explode('/', $entry, 2), 2, '32');
        $subnetLong = ip2long($subnet);
        if ($subnetLong === false) {
            continue;
        }
        $bits = (int)$bits;
        if ($bits < 0 || $bits > 32) {
            continue;
        }
        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
        if (($long & $mask) === ($subnetLong & $mask)) {
            return true;
        }
    }
    return false;
}

/* ---------------------------------------------------------------------
   Autenticacion
   --------------------------------------------------------------------- */

/**
 * Normaliza un email para que las comparaciones sean consistentes
 * (MySQL collation utf8mb4_unicode_ci ya no es case-insensitive de forma
 * fiable con acentos, asi que se normaliza en PHP antes de tocar la BD).
 */
function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/**
 * Extrae el token Bearer del header Authorization o de la cookie de sesion.
 *
 * Se acepta el header Authorization (compatibilidad con clientes existentes)
 * y, como opcion mas segura, una cookie HttpOnly que el JavaScript no puede
 * leer (evita que un XSS robe el token).
 */
function bearer_token(): ?string
{
    $auth = '';
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $auth = (string)$value;
                    break;
                }
            }
        }
    }
    if ($auth === '') {
        $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    }
    if ($auth !== '' && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        $token = trim($m[1]);
        return looks_like_token($token) ? $token : null;
    }

    $cookie = $_COOKIE[session_cookie_name()] ?? '';
    return looks_like_token((string)$cookie) ? (string)$cookie : null;
}

/**
 * Nombre de la cookie de sesion.
 */
function session_cookie_name(): string
{
    return 'corett_session';
}

/**
 * Un token de sesion es siempre 64 caracteres hexadecimales (32 bytes).
 */
function looks_like_token(string $token): bool
{
    return (bool)preg_match('/^[a-f0-9]{64}$/', $token);
}

/**
 * Envia el token en una cookie HttpOnly para que no sea accesible a JavaScript.
 */
function send_session_cookie(string $token, int $ttlSeconds): void
{
    $secure = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    setcookie(session_cookie_name(), $token, [
        'expires'  => time() + $ttlSeconds,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
}

/**
 * Elimina la cookie de sesion.
 */
function clear_session_cookie(): void
{
    setcookie(session_cookie_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'secure'   => (($_SERVER['HTTPS'] ?? '') !== ''),
        'samesite' => 'Lax',
    ]);
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
            'SELECT u.id, u.nombre, u.email, u.rol, u.rango, u.reputacion, u.creado_en
               FROM sesiones s
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
    $value = strip_null_bytes($value);
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
    $value = trim(strip_null_bytes($value));
    if ($max > 0 && mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

/**
 * Elimina bytes NUL, que rompen la comparacion con la columna MySQL y
 * tampoco son utiles en ningun campo de texto del sitio.
 */
function strip_null_bytes(string $value): string
{
    return str_replace("\0", '', $value);
}

/**
 * Escapa los comodines de LIKE para que el usuario no pueda forzar
 * un escaneo completo de la tabla con '%' o '_'.
 */
function escape_like(string $value): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
}

/**
 * Extrae un entero de los parametros (query o body), con valor por defecto.
 *
 * Ignora valores no escalares (?id[]=1) para evitar conversiones surprise.
 */
function param_int(string $key, int $default = 0, array $from = null): int
{
    $source = $from ?? $_GET;
    if (!isset($source[$key]) || !is_scalar($source[$key])) {
        return $default;
    }
    $value = (string)$source[$key];
    return is_numeric($value) ? (int)$value : $default;
}

/**
 * Extrae un string de los parametros.
 */
function param_str(string $key, string $default = '', array $from = null): string
{
    $source = $from ?? $_GET;
    if (!isset($source[$key]) || !is_scalar($source[$key])) {
        return $default;
    }
    return trim((string)$source[$key]);
}

/**
 * Lee un campo del cuerpo JSON como string seguro (nunca array).
 */
function body_str(array $body, string $key, string $default = ''): string
{
    if (!isset($body[$key]) || !is_scalar($body[$key])) {
        return $default;
    }
    return (string)$body[$key];
}

/**
 * Lee el primer campo presente de una lista de aliases como string seguro.
 */
function body_str_any(array $body, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (isset($body[$key]) && is_scalar($body[$key])) {
            return (string)$body[$key];
        }
    }
    return $default;
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

    $page   = max(1, (int)$params['page']);
    $limit  = min(100, max(1, (int)$params['limit']));
    $offset = ($page - 1) * $limit;

    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}

/**
 * Indica si la app corre en modo desarrollo (para mensajes de error).
 */
function is_dev(): bool
{
    return env('APP_ENV', 'production') === 'development';
}
