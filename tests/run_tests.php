<?php
/**
 * CoreTT - Smoke test de la API (HTTP).
 *
 * Uso:
 *   php tests/run_tests.php [baseUrl]
 *
 * Ejemplo:
 *   php tests/run_tests.php http://localhost/backend/api/
 *
 * Si la API no responde, imprime SKIP y termina con codigo 0.
 * Si responde, valida el flujo: registro -> login -> crear guia -> votar ->
 * crear post -> comentar -> limpiar, y termina con codigo 1 ante fallos.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo disponible por consola');
}

$base = $argv[1] ?? getenv('API_BASE') ?: 'http://localhost/backend/api/';
$base = rtrim($base, '/') . '/';

$failures = 0;

function api(string $base, string $method, string $path, array $body = [], ?string $token = null): array
{
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $options = [
        'http' => [
            'method'  => $method,
            'header'  => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ];
    if ($body !== []) {
        $options['http']['content'] = json_encode($body);
    }

    $context = stream_context_create($options);
    $raw = @file_get_contents($base . $path, false, $context);
    if ($raw === false) {
        return ['transport' => false];
    }
    $statusLine = $http_response_header[0] ?? '';
    preg_match('#\s(\d{3})\s#', $statusLine, $m);
    $status = (int)($m[1] ?? 0);
    return ['status' => $status, 'json' => json_decode($raw, true)];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    $mark = $ok ? 'PASS' : 'FAIL';
    echo "[$mark] $label" . ($detail !== '' ? " ($detail)" : '') . "\n";
    if (!$ok) {
        $failures++;
    }
}

echo "Smoke test de CoreTT API\n";
echo "Base: $base\n\n";

// Comprobar disponibilidad
$health = api($base, 'GET', 'index.php');
if (($health['transport'] ?? true) === false) {
    echo "SKIP: la API no responde en $base\n";
    echo "Levanta el servidor y ejecuta de nuevo.\n";
    exit(0);
}

check('Health check responde', isset($health['json']['name']), $health['json']['name'] ?? '?');

// Datos unicos por ejecucion
$suffix  = substr(bin2hex(random_bytes(3)), 0, 6);
$name    = 'TestUser' . $suffix;
$email   = "test$suffix@corett.com";
$pass    = 'Passw0rd' . $suffix;
$token   = '';

/* Registro */
$r = api($base, 'POST', 'users.php?action=register', ['name' => $name, 'email' => $email, 'password' => $pass]);
check('Registro', ($r['status'] ?? 0) === 201, 'status ' . ($r['status'] ?? '?'));

/* Login */
$r = api($base, 'POST', 'users.php?action=login', ['email' => $email, 'password' => $pass]);
check('Login', ($r['json']['success'] ?? false) === true && !empty($r['json']['token']));
$token = $r['json']['token'] ?? '';

/* Perfil */
$r = api($base, 'GET', 'users.php?action=profile', [], $token);
check('Perfil autenticado', ($r['json']['email'] ?? '') === $email, $r['json']['email'] ?? '?');

/* Crear guia */
$r = api($base, 'POST', 'guides.php', [
    'title'       => 'Guia de prueba ' . $suffix,
    'game'        => 'lol',
    'description' => 'Guia temporal para el smoke test.',
    'content'     => 'Contenido de ejemplo con mas de veinte caracteres.',
], $token);
check('Crear guia', ($r['status'] ?? 0) === 201, 'status ' . ($r['status'] ?? '?'));
$guideId = (int)($r['json']['id'] ?? 0);
check('ID de guia valido', $guideId > 0);

/* Votar */
$r = api($base, 'POST', 'guides.php?action=vote', ['id' => $guideId, 'vote' => 1], $token);
check('Votar up', ($r['json']['votes'] ?? null) === 1, 'votes ' . ($r['json']['votes'] ?? '?'));
$r = api($base, 'POST', 'guides.php?action=vote', ['id' => $guideId, 'vote' => 1], $token);
check('Toggle anula voto', ($r['json']['votes'] ?? null) === 0 && ($r['json']['myVote'] ?? -1) === 0);

/* Crear post y comentar */
$r = api($base, 'POST', 'forum.php', ['title' => 'Post de prueba ' . $suffix, 'body' => 'Contenido del post.'],
    $token);
check('Crear post', ($r['status'] ?? 0) === 201, 'status ' . ($r['status'] ?? '?'));
$postId = (int)($r['json']['id'] ?? 0);

$r = api($base, 'POST', 'forum.php?action=comment', ['postId' => $postId, 'text' => 'Un comentario'],
    $token);
check('Comentar', ($r['json']['success'] ?? false) === true);

$r = api($base, 'GET', 'forum.php?id=' . $postId);
check('Detalle post con comentario', count($r['json']['comments'] ?? []) === 1);

/* Sin token -> 401 */
$r = api($base, 'POST', 'guides.php', ['title' => 'x', 'game' => 'lol', 'content' => 'y']);
check('Guia sin auth rechazada', ($r['status'] ?? 0) === 401, 'status ' . ($r['status'] ?? '?'));

/* Limpieza */
if ($guideId > 0) {
    api($base, 'DELETE', 'guides.php?id=' . $guideId, [], $token);
}
if ($postId > 0) {
    api($base, 'DELETE', 'forum.php?id=' . $postId, [], $token);
}
api($base, 'POST', 'users.php?action=logout', [], $token);

echo "\n";
if ($failures > 0) {
    echo "Resultado: $failures error(es)\n";
    exit(1);
}
echo "Resultado: OK\n";
exit(0);