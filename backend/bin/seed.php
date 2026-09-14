<?php
/**
 * CoreTT - Seed inicial (CLI).
 *
 * Crea/actualiza el usuario administrador (desde .env), usuarios demo,
 * guias de ejemplo y hilos del foro. Es idempotente: se puede ejecutar
 * varias veces sin duplicar datos.
 *
 * Uso:
 *   php backend/bin/seed.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo disponible por consola');
}

require_once __DIR__ . '/../config/database.php';

echo "=== CoreTT seed ===\n";

$pdo = db();

/* ---------------------------------------------------------------------
   Aseguramos juegos y parches basicos (por si no se importaron por SQL)
--------------------------------------------------------------------- */
$games = [
    'lol' => 'League of Legends',
    'cs2' => 'Counter-Strike 2',
    'fn'  => 'Fortnite',
];
$gameIds = [];
foreach ($games as $slug => $nombre) {
    $stmt = $pdo->prepare('SELECT id FROM juegos WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $slug]);
    if ($row = $stmt->fetch()) {
        $gameIds[$slug] = (int)$row['id'];
    } else {
        $stmt = $pdo->prepare('INSERT INTO juegos (slug, nombre) VALUES (:s, :n)');
        $stmt->execute([':s' => $slug, ':n' => $nombre]);
        $gameIds[$slug] = (int)$pdo->lastInsertId();
    }
}
echo "Juegos listos: " . implode(', ', array_keys($gameIds)) . "\n";

/* ---------------------------------------------------------------------
   Usuarios
--------------------------------------------------------------------- */
$adminEmail = env('ADMIN_EMAIL', 'admin@corett.com');
$adminPass  = env('ADMIN_PASSWORD', 'admin1234');

function ensure_user(PDO $pdo, string $nombre, string $email, string $password, string $rol, ?string $rango): int
{
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :e OR nombre = :n LIMIT 1');
    $stmt->execute([':e' => $email, ':n' => $nombre]);
    if ($row = $stmt->fetch()) {
        $id = (int)$row['id'];
        // Actualizamos hash y rol del admin para reflejar el .env actual
        $stmt = $pdo->prepare('UPDATE usuarios SET rol = :r, rango = COALESCE(:rango, rango) WHERE id = :id');
        $stmt->execute([':r' => $rol, ':rango' => $rango, ':id' => $id]);
        return $id;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash, rol, rango) VALUES (:n, :e, :p, :r, :rango)'
    );
    $stmt->execute([
        ':n'     => $nombre,
        ':e'     => $email,
        ':p'     => password_hash($password, PASSWORD_DEFAULT),
        ':r'     => $rol,
        ':rango' => $rango,
    ]);
    return (int)$pdo->lastInsertId();
}

// Use el password configurado en .env para el admin demo
$adminId = ensure_user($pdo, 'Admin', $adminEmail, $adminPass, 'admin', 'Diamante');
echo "Admin listo: {$adminEmail}\n";

$demoPass = env('DEMO_PASSWORD', 'password123');
$demoUsers = [
    ['JohnDoe',       'john@corett.com',       'user', 'Oro'],
    ['SmokeMaster',   'smoke@corett.com',      'user', 'Global Elite'],
    ['VictoryRoyale', 'victory@corett.com',    'user', 'Champion'],
    ['NewPlayer',     'new@corett.com',        'user', 'Bronce'],
    ['MidDiff',       'mid@corett.com',        'user', 'Platino'],
    ['EcoMaster',     'eco@corett.com',        'user', 'Oro'],
];

$demoIds = ['admin' => $adminId];
foreach ($demoUsers as [$nombre, $email, $rol, $rango]) {
    $demoIds[$nombre] = ensure_user($pdo, $nombre, $email, $demoPass, $rol, $rango);
}
echo 'Usuarios demo: ' . count($demoUsers) . "\n";

/* ---------------------------------------------------------------------
   Guias de ejemplo
--------------------------------------------------------------------- */
function ensure_guide(PDO $pdo, int $userId, int $gameId, string $titulo, string $descripcion, string $contenido): int
{
    $stmt = $pdo->prepare('SELECT id FROM guias WHERE titulo = :t LIMIT 1');
    $stmt->execute([':t' => $titulo]);
    if ($row = $stmt->fetch()) {
        return (int)$row['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO guias (juego_id, usuario_id, titulo, descripcion, contenido)
         VALUES (:g, :u, :t, :d, :c)'
    );
    $stmt->execute([
        ':g' => $gameId,
        ':u' => $userId,
        ':t' => $titulo,
        ':d' => $descripcion,
        ':c' => $contenido,
    ]);
    return (int)$pdo->lastInsertId();
}

$sampleGuides = [
    [
        'user'  => 'JohnDoe',
        'game'  => 'lol',
        'title' => 'Como dominar el macro en League of Legends',
        'desc'  => 'Control de oleadas, vision y toma de decisiones para subir de elo.',
        'body'  => "El macro es la habilidad de tomar las decisiones correctas en el momento correcto.\n\n" .
                   '1. Gestion de oleadas: congela en tu lado del mapa cuando vas ganando la linea.\n' .
                   '2. Vision: controla los arbustos clave 45 segundos antes de cada objetivo.\n' .
                   '3. Rotaciones: empuja la ola antes de moverte a dragon o baron.\n\n' .
                   'Estos conceptos te dan ventaja sin necesidad de ganar la linea a nivel mecanico.',
    ],
    [
        'user'  => 'EcoMaster',
        'game'  => 'cs2',
        'title' => 'Economia en CS2: cuando comprar y cuando ahorrar',
        'desc'  => 'Guia de la economia de equipo: ecos, force buys y full buys.',
        'body'  => "Entender la economia define muchas rondas.\n\n" .
                   'Pistol loss: nunca compres mas de 1000$ en la ronda 2.\n' .
                   'Force buys: funcionan en mapas pequenos como Mirage si ganas el primer duelo.\n' .
                   'Full buy: requiere 4700$ por jugador y plantas de bomba previas.',
    ],
    [
        'user'  => 'VictoryRoyale',
        'game'  => 'fn',
        'title' => 'Meta de armas temporada 5 - Fortnite',
        'desc'  => 'Ranking de armas segun el parche actual y rotaciones optimas.',
        'body'  => "La temporada 5 trae cambios importantes en drops y rotaciones.\n\n" .
                   'Armas top: Hammer Pump + Assault Rifle para el mid game.\n' .
                   'Drops recomendados: zonas con cofres garantizados y salida facil.',
    ],
];

foreach ($sampleGuides as $g) {
    ensure_guide(
        $pdo,
        $demoIds[$g['user']],
        $gameIds[$g['game']],
        $g['title'],
        $g['desc'],
        $g['body']
    );
}
echo 'Guias demo: ' . count($sampleGuides) . "\n";

/* ---------------------------------------------------------------------
   Hilos del foro
--------------------------------------------------------------------- */
function ensure_post(PDO $pdo, int $userId, string $titulo, string $contenido): int
{
    $stmt = $pdo->prepare('SELECT id FROM foro_posts WHERE titulo = :t LIMIT 1');
    $stmt->execute([':t' => $titulo]);
    if ($row = $stmt->fetch()) {
        return (int)$row['id'];
    }

    $stmt = $pdo->prepare('INSERT INTO foro_posts (usuario_id, titulo, contenido) VALUES (:u, :t, :c)');
    $stmt->execute([':u' => $userId, ':t' => $titulo, ':c' => $contenido]);
    return (int)$pdo->lastInsertId();
}

$samplePosts = [
    [
        'user'  => 'NewPlayer',
        'title' => 'Cual es el mejor campeon para empezar en LoL?',
        'body'  => "Soy nuevo y no se por quien empezar. Recomendaciones de campeones sencillos que ensenen conceptos base.",
    ],
    [
        'user'  => 'TeamPilot',
        'title' => 'Economia en CS2: estrategias de ecos',
        'body'  => "Cuando conviene hacer eco completo y cuando forzar compra? Compartid vuestras reglas de oro.",
    ],
];

$postIds = [];
foreach ($samplePosts as $p) {
    $postIds[] = ensure_post($pdo, $demoIds[$p['user']], $p['title'], $p['body']);
}
echo 'Posts demo: ' . count($samplePosts) . "\n";

/* --- Comentarios de ejemplo --- */
$stmt = $pdo->query('SELECT COUNT(*) AS total FROM foro_comentarios');
$hasComments = (int)$stmt->fetch()['total'] > 0;

if (!$hasComments && $postIds) {
    $comments = [
        ['post' => $postIds[0] ?? 0, 'user' => 'SmokeMaster', 'text' => 'Empieza con Garen o Annie, son simples y ensenan conceptos clave.'],
        ['post' => $postIds[0] ?? 0, 'user' => 'MidDiff',     'text' => 'Juega los cinco roles una vez para entender el juego completo.'],
        ['post' => $postIds[1] ?? 0, 'user' => 'EcoMaster',   'text' => 'Regla simple: no compres de mas si perdiste la ronda de pistolas.'],
        ['post' => $postIds[1] ?? 0, 'user' => 'SmokeMaster', 'text' => 'Los force buys funcionan en mapas pequenos si ganas el primer duelo.'],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO foro_comentarios (post_id, usuario_id, contenido) VALUES (:p, :u, :c)'
    );
    foreach ($comments as $c) {
        if ($c['post'] > 0) {
            $stmt->execute([':p' => $c['post'], ':u' => $demoIds[$c['user']], ':c' => $c['text']]);
        }
    }
    echo "Comentarios demo: " . count($comments) . "\n";
}

/* ---------------------------------------------------------------------
   Votos de ejemplo
--------------------------------------------------------------------- */
$stmt = $pdo->query('SELECT COUNT(*) AS total FROM votos');
if ((int)$stmt->fetch()['total'] === 0) {
    $stmt = $pdo->query('SELECT id, usuario_id FROM guias');
    $guideRows = $stmt->fetchAll();
    $voterNames = ['JohnDoe', 'SmokeMaster', 'VictoryRoyale', 'NewPlayer', 'MidDiff', 'EcoMaster'];
    $stmt = $pdo->prepare('INSERT INTO votos (guia_id, usuario_id, valor) VALUES (:g, :u, 1)');
    $added = 0;
    foreach ($guideRows as $i => $g) {
        $voter = $demoIds[$voterNames[$i % count($voterNames)]];
        try {
            $stmt->execute([':g' => $g['id'], ':u' => $voter]);
            $added++;
        } catch (PDOException $e) {
            // duplicado: ignorar
        }
    }
    echo "Votos demo: {$added}\n";
}

echo "\nSeed completo.\n";