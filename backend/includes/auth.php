<?php
// CoreTT - Auth mínima: bcrypt + JWT HS256 sin dependencias
function jwtSecret(): string {
    return getenv('JWT_SECRET') ?: 'dev-secret-cambiar';
}

function base64urlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64urlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function createToken(int $userId, string $role): string {
    $exp = time() + (int)(getenv('JWT_EXPIRE') ?: 3600);
    $header = base64urlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64urlEncode(json_encode(['sub' => $userId, 'role' => $role, 'exp' => $exp]));
    $sig = base64urlEncode(hash_hmac('sha256', "$header.$payload", jwtSecret(), true));
    return "$header.$payload.$sig";
}

function verifyToken(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $expected = base64urlEncode(hash_hmac('sha256', "$h.$p", jwtSecret(), true));
    if (!hash_equals($expected, $s)) return null;
    $payload = json_decode(base64urlDecode($p), true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}

function requireAuth(): array {
    $token = getBearerToken();
    if (!$token) json(['error' => 'No autenticado'], 401);
    $payload = verifyToken($token);
    if (!$payload) json(['error' => 'Token inválido o expirado'], 401);
    return $payload;
}

function requireAdmin(): array {
    $payload = requireAuth();
    if (($payload['role'] ?? '') !== 'admin') json(['error' => 'Solo administradores'], 403);
    return $payload;
}
