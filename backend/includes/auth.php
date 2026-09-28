<?php
/**
 * CoreTT - Autenticacion.
 * Registro, login con rate limiting, logout y helpers de sesiones por token.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------------
   Sesiones (token)
   --------------------------------------------------------------------- */

/**
 * Numero maximo de sesiones activas por usuario. Sin esto, un login
 * automatizado deja filas huerfanas en sesiones para siempre.
 */
const MAX_SESSIONS_PER_USER = 5;

/**
 * Genera un token aleatorio y guarda su hash en la tabla sesiones.
 * Devuelve el token en claro (solo se envia una vez al cliente).
 */
function create_session(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    $ttlDays = max(1, (int)env('SESSION_TTL_DAYS', 7));
    $ttl     = $ttlDays * 86400;
    $expires = date('Y-m-d H:i:s', time() + $ttl);

    $stmt = db()->prepare(
        'INSERT INTO sesiones (usuario_id, token_hash, expira_en) VALUES (:uid, :hash, :exp)'
    );
    $stmt->execute([
        ':uid'  => $userId,
        ':hash' => $hash,
        ':exp'  => $expires,
    ]);

    // Recorta las sesiones mas antiguas del mismo usuario.
    $stmt = db()->prepare(
        'DELETE FROM sesiones
          WHERE usuario_id = :uid
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM sesiones
                     WHERE usuario_id = :uid2
                     ORDER BY id DESC
                     LIMIT ' . MAX_SESSIONS_PER_USER . '
                ) AS keep
            )'
    );
    $stmt->execute([':uid' => $userId, ':uid2' => $userId]);

    // El token tambien viaja en cookie HttpOnly: un XSS no puede leerla.
    send_session_cookie($token, $ttl);

    return $token;
}

/**
 * Invalida la sesion actual (logout).
 */
function destroy_current_session(): void
{
    $token = bearer_token();
    clear_session_cookie();
    if (!$token) {
        return;
    }
    $stmt = db()->prepare('DELETE FROM sesiones WHERE token_hash = :hash');
    $stmt->execute([':hash' => hash('sha256', $token)]);
}

/**
 * Elimina sesiones y registros de rate limit caducados.
 * Se invoca al iniciar sesion para que las tablas no crezcan sin limite.
 */
function gc_auth_tables(): void
{
    try {
        $stmt = db()->prepare('DELETE FROM sesiones WHERE expira_en < NOW()');
        $stmt->execute();
        $stmt = db()->prepare(
            'DELETE FROM login_intentos
              WHERE bloqueado_hasta IS NOT NULL AND bloqueado_hasta < NOW()'
        );
        $stmt->execute();
    } catch (PDOException $e) {
        // La limpieza es best-effort: nunca debe romper una peticion.
    }
}

/* ---------------------------------------------------------------------
   Rate limiting
   --------------------------------------------------------------------- */

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_MAX_BLOCK_SECONDS = 900;

/**
 * Comprueba el rate limiting del login para una clave email+IP.
 * Si hay bloqueo activo, responde 429.
 */
function rate_limit_check(string $key): void
{
    $ip = client_ip();
    $stmt = db()->prepare(
        'SELECT id, intentos, bloqueado_hasta FROM login_intentos
          WHERE email = :key AND ip = :ip LIMIT 1'
    );
    $stmt->execute([':key' => $key, ':ip' => $ip]);
    $row = $stmt->fetch();

    if ($row && $row['bloqueado_hasta'] && strtotime($row['bloqueado_hasta']) > time()) {
        $until = date('H:i:s', strtotime($row['bloqueado_hasta']));
        error_response("Demasiados intentos. Volve a intentar despues de las $until.", 429);
    }
}

/**
 * Registra un intento fallido de login.
 *
 * El bloqueo crece de forma progresiva pero con tope: sin tope, quien
 * conoces el email de una victima podia bloquear la cuenta indefinidamente
 * encadenando reintentos.
 */
function rate_limit_failed(string $key): void
{
    $ip = client_ip();
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT id, intentos FROM login_intentos
          WHERE email = :key AND ip = :ip LIMIT 1'
    );
    $stmt->execute([':key' => $key, ':ip' => $ip]);
    $row = $stmt->fetch();

    $attempts = $row ? (int)$row['intentos'] + 1 : 1;

    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        $seconds = min(LOGIN_MAX_BLOCK_SECONDS, ($attempts - LOGIN_MAX_ATTEMPTS + 1) * 120);
        $blockedUntil = date('Y-m-d H:i:s', time() + $seconds);
    } else {
        $blockedUntil = null;
    }

    if ($row) {
        $stmt = $pdo->prepare(
            'UPDATE login_intentos SET intentos = :i, bloqueado_hasta = :b WHERE id = :id'
        );
        $stmt->execute([':i' => $attempts, ':b' => $blockedUntil, ':id' => $row['id']]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO login_intentos (email, ip, intentos, bloqueado_hasta)
             VALUES (:key, :ip, :i, :b)'
        );
        $stmt->execute([':key' => $key, ':ip' => $ip, ':i' => $attempts, ':b' => $blockedUntil]);
    }
}

/**
 * Limpia el historial de intentos tras un login exitoso.
 */
function rate_limit_clear(string $key): void
{
    $stmt = db()->prepare('DELETE FROM login_intentos WHERE email = :key AND ip = :ip');
    $stmt->execute([':key' => $key, ':ip' => client_ip()]);
}

/**
 * Limita las operaciones de escritura por IP (ventana fija).
 *
 * Protege el registro y la creacion de contenido frente a spam y frente a
 * agotar CPU con hashes bcrypt.
 */
function throttle(string $bucket, int $maxAttempts, int $windowSeconds, string $message = 'Demasiadas peticiones'): void
{
    $key = 'throttle:' . $bucket;
    $ip  = client_ip();
    $now = time();

    $stmt = db()->prepare(
        'SELECT id, intentos, bloqueado_hasta FROM login_intentos
          WHERE email = :key AND ip = :ip LIMIT 1'
    );
    $stmt->execute([':key' => $key, ':ip' => $ip]);
    $row = $stmt->fetch();

    $windowEnds = date('Y-m-d H:i:s', $now + $windowSeconds);
    $inWindow = $row && $row['bloqueado_hasta'] && strtotime($row['bloqueado_hasta']) > $now;

    if ($inWindow && (int)$row['intentos'] >= $maxAttempts) {
        error_response($message, 429);
    }

    if (!$row) {
        $stmt = db()->prepare(
            'INSERT INTO login_intentos (email, ip, intentos, bloqueado_hasta)
             VALUES (:key, :ip, 1, :b)'
        );
        $stmt->execute([':key' => $key, ':ip' => $ip, ':b' => $windowEnds]);
        return;
    }

    $intentos = $inWindow ? (int)$row['intentos'] + 1 : 1;
    $stmt = db()->prepare(
        'UPDATE login_intentos SET intentos = :i, bloqueado_hasta = :b WHERE id = :id'
    );
    $stmt->execute([':i' => $intentos, ':b' => $windowEnds, ':id' => $row['id']]);

    if ($intentos > $maxAttempts) {
        error_response($message, 429);
    }
}

/* ---------------------------------------------------------------------
   Registro y login
   --------------------------------------------------------------------- */

/**
 * Registra un usuario nuevo. Devuelve el usuario creado.
 */
function register_user(string $name, string $email, string $password): array
{
    $email = normalize_email($email);

    $errors = array_merge(
        validate_name($name),
        validate_email($email),
        validate_password($password)
    );

    bail_on_errors($errors);

    // El limite se aplica DESPUES de validar, justo antes del INSERT: el
    // coste que hay que proteger es el hash bcrypt, que solo se calcula en
    // los registros validos. Contando tambien los intentos fallidos, un
    // usuario que se equivoca unas pocas veces se quedaria bloqueado.
    throttle(
        'register',
        (int)env('REGISTER_MAX_PER_WINDOW', 20),
        (int)env('REGISTER_WINDOW_SECONDS', 3600),
        'Demasiadas cuentas creadas desde esta conexion. Intenta mas tarde.'
    );

    $pdo = db();

    // Mensaje generico: no revela si el email o el nombre estan en uso.
    $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE email = :e OR nombre = :n LIMIT 1');
    $stmt->execute([':e' => $email, ':n' => $name]);
    if ($stmt->fetch()) {
        error_response('No se pudo crear la cuenta con esos datos.', 422, [
            'email' => 'Email o nombre no disponibles.',
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash) VALUES (:n, :e, :p)'
    );
    try {
        $stmt->execute([
            ':n' => $name,
            ':e' => $email,
            ':p' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    } catch (PDOException $e) {
        // Carrera entre dos registros simultaneos con el mismo email.
        if ($e->getCode() === '23000') {
            error_response('No se pudo crear la cuenta con esos datos.', 422, [
                'email' => 'Email o nombre no disponibles.',
            ]);
        }
        throw $e;
    }

    $id = (int)$pdo->lastInsertId();
    return [
        'id'         => $id,
        'nombre'     => $name,
        'email'      => $email,
        'rol'        => 'user',
        'rango'      => null,
        'reputacion' => 0,
    ];
}

/**
 * Intenta iniciar sesion. Devuelve ['success' => true, 'token' => ..., 'user' => ...]
 * o termina con error 401/429.
 */
function attempt_login(string $email, string $password): array
{
    $email = normalize_email($email);

    // Trimeo la longitud para no hacer un hash de una entrada enorme.
    $password = substr($password, 0, 1024);

    if (!$email || $password === '') {
        error_response('Credenciales invalidas', 401);
    }

    rate_limit_check($email);

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();

    // Si el email no existe se verifica igualmente contra un hash ficticio:
    // de lo contrario el tiempo de respuesta revela que cuentas existen.
    $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $valid = password_verify($password, $hash);

    if (!$user || !$valid) {
        rate_limit_failed($email);
        error_response('Credenciales invalidas', 401);
    }

    rate_limit_clear($email);
    gc_auth_tables();
    $token = create_session((int)$user['id']);

    return [
        'success' => true,
        'token'   => $token,
        'user'    => public_user($user),
    ];
}

/* ---------------------------------------------------------------------
   Serializacion de usuario
   --------------------------------------------------------------------- */

/**
 * Devuelve la representacion publica del usuario (sin password_hash).
 */
function public_user(array $user): array
{
    return [
        'id'         => (int)$user['id'],
        'nombre'     => $user['nombre'],
        'email'      => $user['email'],
        'rol'        => $user['rol'],
        'rango'      => $user['rango'] ?? null,
        'reputacion' => (int)($user['reputacion'] ?? 0),
        'creado_en'  => date_only($user['creado_en'] ?? null),
    ];
}
