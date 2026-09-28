<?php
/**
 * CoreTT - Smoke test de la API (HTTP).
 *
 * Uso:
 *   php tests/run_tests.php [baseUrl]
 *
 * Ejemplo:
 *   php tests/run_tests.php http://localhost:8080/backend/api/
 *
 * Si la API no responde, imprime SKIP y termina con codigo 0.
 * Si responde, valida el flujo completo y termina con codigo 1 ante fallos.
 *
 * Cubre los casos que antes no se probaban y que por eso llegaron a
 * produccion rotos: crear guias, los cuatro filtros de busqueda, los limites
 * de tamano de entrada, la autorizacion y la votacion entre dos usuarios.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo disponible por consola');
}

$argBase = $argv[1] ?? getenv('API_BASE');
$base = rtrim($argBase ?: 'http://localhost/backend/api/', '/') . '/';

$failures = 0;

function api(string $base, string $method, string $path, array $body = [], ?string $token = null, array $extraHeaders = []): array
{
    $headers = [];
    if ($method !== 'GET') {
        $headers[] = 'Content-Type: application/json';
    }
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    foreach ($extraHeaders as $h) {
        $headers[] = $h;
    }

    $options = [
        'http' => [
            'method'  => $method,
            'header'  => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ];
    if ($body !== [] && $method !== 'GET') {
        $options['http']['content'] = json_encode($body);
    }

    // Si el path ya es una URL absoluta (health check), se usa tal cual.
    $url = (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0)
        ? $path
        : $base . $path;

    $context = stream_context_create($options);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return ['transport' => false];
    }
    $statusLine = $http_response_header[0] ?? '';
    preg_match('#\s(\d{3})\s#', $statusLine, $m);
    $status = (int)($m[1] ?? 0);

    // $http_response_header solo existe dentro de la funcion: hay que
    // traducirlo aqui para poder inspeccionar las cabeceras de seguridad.
    $headers = [];
    foreach ($http_response_header as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }

    return ['status' => $status, 'json' => json_decode($raw, true), 'raw' => $raw, 'headers' => $headers];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? " ($detail)" : '') . "\n";
    if (!$ok) {
        $failures++;
    }
}

echo "Smoke test de CoreTT API\n";
echo "Base: $base\n\n";

/* ---------------------------------------------------------------------
   Disponibilidad
   --------------------------------------------------------------------- */

// El health check vive un nivel por encima del directorio api/.
$healthPath = preg_replace('#/api/?$#', '/index.php', $base);
if ($healthPath === $base) {
    $healthPath = $base . 'index.php';
}

$health = api($base, 'GET', $healthPath);
if (($health['transport'] ?? true) === false) {
    echo "SKIP: la API no responde en $base\n";
    echo "Levanta el servidor y ejecuta de nuevo.\n";
    exit(0);
}

check('Health check responde', isset($health['json']['name']), $health['json']['name'] ?? '?');
check(
    'Health check no filtra el estado de la base de datos',
    !array_key_exists('db', (array)($health['json'] ?? []))
);
check(
    'Health check no publica el mapa de endpoints',
    !isset($health['json']['endpoints'])
);

/* ---------------------------------------------------------------------
   Usuarios
   --------------------------------------------------------------------- */
$suffix = substr(bin2hex(random_bytes(3)), 0, 6);
$pass   = 'Passw0rd' . $suffix;

$emailA = "autora$suffix@corett.com";
$emailB = "autorb$suffix@corett.com";

$r = api($base, 'POST', 'users.php?action=register', [
    'name' => 'Autora' . $suffix, 'email' => $emailA, 'password' => $pass,
]);
check('Registro usuario A', ($r['status'] ?? 0) === 201, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'users.php?action=register', [
    'name' => 'Autorb' . $suffix, 'email' => $emailB, 'password' => $pass,
]);
check('Registro usuario B', ($r['status'] ?? 0) === 201, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'users.php?action=login', ['email' => $emailA, 'password' => $pass]);
check('Login usuario A', ($r['json']['success'] ?? false) === true && !empty($r['json']['token']));
$tokenA = $r['json']['token'] ?? '';

$r = api($base, 'POST', 'users.php?action=login', ['email' => $emailB, 'password' => $pass]);
$tokenB = $r['json']['token'] ?? '';

/* El email debe normalizarse a minusculas en el login. */
$r = api($base, 'POST', 'users.php?action=login', ['email' => strtoupper($emailA), 'password' => $pass]);
check('Login con email en mayusculas', ($r['json']['success'] ?? false) === true);

/* ---------------------------------------------------------------------
   Guias: crear (este camino devolvia 500 por un marcador SQL repetido)
   --------------------------------------------------------------------- */
$r = api($base, 'POST', 'guides.php', [
    'title'       => 'Guia de prueba ' . $suffix,
    'game'        => 'lol',
    'description' => 'Guia temporal para el smoke test.',
    'content'     => 'Contenido de ejemplo con mas de veinte caracteres.',
], $tokenA);
check('Crear guia', ($r['status'] ?? 0) === 201, 'status ' . ($r['status'] ?? '?'));
$guideId = (int)($r['json']['id'] ?? 0);
check('ID de guia valido', $guideId > 0);

$r = api($base, 'POST', 'guides.php', [
    'title'   => 'Guia CS2 de ' . $suffix,
    'game'    => 'cs2',
    'content' => 'Contenido de ejemplo con mas de veinte caracteres.',
], $tokenA);
$guideId2 = (int)($r['json']['id'] ?? 0);
check('Crear guia de segundo juego', ($r['status'] ?? 0) === 201, 'status ' . ($r['status'] ?? '?'));

/* ---------------------------------------------------------------------
   Guias: listados que antes lanzaban SQLSTATE[HY093]
   --------------------------------------------------------------------- */
$r = api($base, 'GET', 'guides.php?search=' . rawurlencode('prueba ' . $suffix));
check('Buscar guias no devuelve 500', ($r['status'] ?? 0) === 200, 'status ' . ($r['status'] ?? '?'));
check('La busqueda encuentra la guia creada', count($r['json']['guides'] ?? []) >= 1);

$r = api($base, 'GET', 'guides.php?game=cs2');
check('Filtrar guias por juego no devuelve 500', ($r['status'] ?? 0) === 200, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'GET', 'guides.php?game=JuegoInexistenteZZZ');
check('Juego inexistente devuelve 404', ($r['status'] ?? 0) === 404, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'GET', 'forum.php?search=' . rawurlencode('Post de prueba'));
check('Buscar posts no devuelve 500', ($r['status'] ?? 0) === 200, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'GET', 'games.php?action=stats&game=lol');
check('Meta stats no devuelve 500', ($r['status'] ?? 0) === 200, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'GET', 'games.php');
check('Listado de juegos', ($r['status'] ?? 0) === 200 && is_array($r['json']));

/* Los comodines de LIKE no deben interpretarse. */
$r = api($base, 'GET', 'guides.php?search=%25');
check('Comodin LIKE escapado no rompe la consulta', ($r['status'] ?? 0) === 200, 'status ' . ($r['status'] ?? '?'));

/* ---------------------------------------------------------------------
   Limites de entrada: deben ser 422, nunca 500
   --------------------------------------------------------------------- */
$r = api($base, 'POST', 'guides.php', [
    'title' => 'Titulo largo ' . $suffix,
    'game'  => 'lol',
    'description' => str_repeat('a', 600),
    'content' => 'Contenido de ejemplo con mas de veinte caracteres.',
], $tokenA);
check('Descripcion excedida devuelve 422', ($r['status'] ?? 0) === 422, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'guides.php', [
    'title'   => str_repeat('b', 200),
    'game'    => 'lol',
    'content' => 'Contenido de ejemplo con mas de veinte caracteres.',
], $tokenA);
check('Titulo excedido devuelve 422', ($r['status'] ?? 0) === 422, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'users.php?action=register', [
    'name' => 'Largocorreo' . $suffix,
    'email' => str_repeat('a', 185) . '@example.com',
    'password' => $pass,
]);
check('Email demasiado largo devuelve 422', ($r['status'] ?? 0) === 422, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'users.php?action=register', [
    'name' => '<script>alert(1)</script>' . substr($suffix, 0, 3),
    'email' => "xss$suffix@corett.com",
    'password' => $pass,
]);
check('Nombre con HTML rechazado', ($r['status'] ?? 0) === 422, 'status ' . ($r['status'] ?? '?'));

/* ---------------------------------------------------------------------
   Votacion (ahora se exige un segundo usuario y se bloquea el autovoto)
   --------------------------------------------------------------------- */
$r = api($base, 'POST', 'guides.php?action=vote', ['id' => $guideId, 'vote' => 1], $tokenB);
check('Votar up desde otro usuario', ($r['json']['votes'] ?? null) === 1, 'votes ' . ($r['json']['votes'] ?? '?'));

$r = api($base, 'POST', 'guides.php?action=vote', ['id' => $guideId, 'vote' => 1], $tokenB);
check('Toggle anula voto', ($r['json']['votes'] ?? null) === 0 && ($r['json']['myVote'] ?? -1) === 0);

$r = api($base, 'POST', 'guides.php?action=vote', ['id' => $guideId, 'vote' => -1], $tokenB);
check('Voto negativo', ($r['json']['votes'] ?? null) === -1, 'votes ' . ($r['json']['votes'] ?? '?'));

$r = api($base, 'POST', 'guides.php?action=vote', ['id' => $guideId, 'vote' => 1], $tokenA);
check('Autovoto rechazado', ($r['status'] ?? 0) === 422, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'guides.php?action=vote', ['id' => 999999999, 'vote' => 1], $tokenB);
check('Voto a guia inexistente devuelve 404', ($r['status'] ?? 0) === 404, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'guides.php?action=vote', ['id' => $guideId, 'vote' => 5], $tokenB);
check('Valor de voto invalido devuelve 422', ($r['status'] ?? 0) === 422, 'status ' . ($r['status'] ?? '?'));

/* ---------------------------------------------------------------------
   Foro
   --------------------------------------------------------------------- */
$r = api($base, 'POST', 'forum.php', [
    'title' => 'Post de prueba ' . $suffix,
    'body'  => 'Contenido del post.',
], $tokenA);
check('Crear post', ($r['status'] ?? 0) === 201, 'status ' . ($r['status'] ?? '?'));
$postId = (int)($r['json']['id'] ?? 0);

$r = api($base, 'POST', 'forum.php?action=comment', ['postId' => $postId, 'text' => 'Un comentario'], $tokenB);
check('Comentar', ($r['json']['success'] ?? false) === true);

$r = api($base, 'GET', 'forum.php?id=' . $postId);
check('Detalle post con comentario', count($r['json']['comments'] ?? []) === 1);

$r = api($base, 'POST', 'forum.php?action=comment', [
    'postId' => $postId,
    'text'   => str_repeat('c', 2500),
], $tokenB);
check('Comentario excedido devuelve 422', ($r['status'] ?? 0) === 422, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'forum.php?action=comment', ['postId' => 999999999, 'text' => 'hola'], $tokenB);
check('Comentario en post inexistente devuelve 404', ($r['status'] ?? 0) === 404, 'status ' . ($r['status'] ?? '?'));

/* ---------------------------------------------------------------------
   Autorizacion
   --------------------------------------------------------------------- */
$r = api($base, 'POST', 'guides.php', ['title' => 'sin auth', 'game' => 'lol', 'content' => 'y']);
check('Crear guia sin auth devuelve 401', ($r['status'] ?? 0) === 401, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'forum.php', ['title' => 'sin auth', 'body' => 'y']);
check('Crear post sin auth devuelve 401', ($r['status'] ?? 0) === 401, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'GET', 'users.php?action=profile');
check('Perfil sin auth devuelve 401', ($r['status'] ?? 0) === 401, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'GET', 'users.php', [], $tokenB);
check('Listado de usuarios sin admin devuelve 403', ($r['status'] ?? 0) === 403, 'status ' . ($r['status'] ?? '?'));

$r = api($base, 'POST', 'users.php?action=role', ['id' => 1, 'rol' => 'admin'], $tokenB);
check('Cambio de rol sin admin devuelve 403', ($r['status'] ?? 0) === 403, 'status ' . ($r['status'] ?? '?'));

/* Un usuario normal no puede editar la guia de otro. */
if ($guideId2 > 0) {
    $r = api($base, 'PUT', 'guides.php?id=' . $guideId2, [
        'title'   => 'Titulo robado ' . $suffix,
        'game'    => 'cs2',
        'content' => 'Contenido modificado con mas de veinte caracteres.',
    ], $tokenB);
    check('Editar guia ajena devuelve 403', ($r['status'] ?? 0) === 403, 'status ' . ($r['status'] ?? '?'));

    $r = api($base, 'DELETE', 'guides.php?id=' . $guideId2, [], $tokenB);
    check('Borrar guia ajena devuelve 403', ($r['status'] ?? 0) === 403, 'status ' . ($r['status'] ?? '?'));
}

/* Editar la propia guia si debe funcionar. */
if ($guideId > 0) {
    $r = api($base, 'PUT', 'guides.php?id=' . $guideId, [
        'title'   => 'Guia editada ' . $suffix,
        'game'    => 'lol',
        'content' => 'Contenido editado con mas de veinte caracteres.',
    ], $tokenA);
    check('Editar guia propia', ($r['status'] ?? 0) === 200, 'status ' . ($r['status'] ?? '?'));
}

/* ---------------------------------------------------------------------
   Cabeceras de seguridad
   --------------------------------------------------------------------- */
$r = api($base, 'GET', 'guides.php');
$headers = array_change_key_case((array)($r['headers'] ?? []), CASE_LOWER);

check('Cabecera X-Content-Type-Options: nosniff',
    ($headers['x-content-type-options'] ?? '') === 'nosniff',
    $headers['x-content-type-options'] ?? 'ausente');
check('Cabecera X-Frame-Options presente',
    in_array($headers['x-frame-options'] ?? '', ['DENY', 'SAMEORIGIN'], true),
    $headers['x-frame-options'] ?? 'ausente');
check('Las respuestas de la API no se cachean',
    strpos($headers['cache-control'] ?? '', 'no-store') !== false,
    $headers['cache-control'] ?? 'ausente');

/* ---------------------------------------------------------------------
   X-Forwarded-For falsificado: antes derrubaba el login con 500
   --------------------------------------------------------------------- */
$r = api($base, 'POST', 'users.php?action=login', [
    'email' => $emailA, 'password' => 'contrasenaIncorrecta1',
], null, ['X-Forwarded-For: ' . str_repeat('9', 300)]);
check(
    'X-Forwarded-For enorme no provoca 500',
    ($r['status'] ?? 0) === 401,
    'status ' . ($r['status'] ?? '?')
);

/* ---------------------------------------------------------------------
   Parametros no escalares
   --------------------------------------------------------------------- */
$r = api($base, 'GET', 'guides.php?id%5B%5D=1');
check('id[] no provoca error', in_array($r['status'] ?? 0, [200, 404], true), 'status ' . ($r['status'] ?? '?'));

/* ---------------------------------------------------------------------
   Perfil
   --------------------------------------------------------------------- */
$r = api($base, 'GET', 'users.php?action=profile', [], $tokenA);
check('Perfil autenticado', ($r['json']['email'] ?? '') === $emailA, $r['json']['email'] ?? '?');
check(
    'El perfil no expone el hash de la contrasena',
    !isset($r['json']['password_hash'])
        && strpos((string)($r['raw'] ?? ''), 'password_hash') === false
        && strpos((string)($r['raw'] ?? ''), '$2y$') === false
);

$r = api($base, 'PUT', 'users.php?action=profile', ['name' => 'Autora' . $suffix . 'x'], $tokenA);
check('Actualizar perfil', ($r['status'] ?? 0) === 200, 'status ' . ($r['status'] ?? '?'));

/* ---------------------------------------------------------------------
   Token invalido
   --------------------------------------------------------------------- */
$r = api($base, 'GET', 'users.php?action=profile', [], str_repeat('a', 64));
check('Token invalido devuelve 401', ($r['status'] ?? 0) === 401, 'status ' . ($r['status'] ?? '?'));

/* ---------------------------------------------------------------------
   Limpieza
   --------------------------------------------------------------------- */
if ($guideId > 0) {
    api($base, 'DELETE', 'guides.php?id=' . $guideId, [], $tokenA);
}
if ($guideId2 > 0) {
    api($base, 'DELETE', 'guides.php?id=' . $guideId2, [], $tokenA);
}
if ($postId > 0) {
    api($base, 'DELETE', 'forum.php?id=' . $postId, [], $tokenA);
}
api($base, 'POST', 'users.php?action=logout', [], $tokenA);
api($base, 'POST', 'users.php?action=logout', [], $tokenB);

echo "\n";
if ($failures > 0) {
    echo "Resultado: $failures error(es)\n";
    exit(1);
}
echo "Resultado: OK\n";
exit(0);
