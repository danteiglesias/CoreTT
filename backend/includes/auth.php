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
 * Genera un token aleatorio y guarda su hash en la tabla sesiones.
 * Devuelve el token en claro (solo se envia una vez al cliente).
 */
function create_session(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);

    $ttlDays = max(1, (int)env('SESSION_TTL_DAYS', 7));
    $expires = date('Y-m-d H:i:s', time() + ($ttlDays * 86400));

    $stmt = db()->prepare(
        'INSERT INTO sesiones (usuario_id, token_hash, expira_en) VALUES (:uid, :hash, :exp)'
    );
    $stmt->execute([
        ':uid'  => $userId,
        ':hash' => $hash,
        ':exp'  => $expires,
    ]);

    return $token;
}

/**
 * Invalida la sesion actual (logout).
 */
function destroy_current_session(): void
{
    $token = bearer_token();
    if (!$token) {
        return;
    }
    $stmt = db()->prepare('DELETE FROM sesiones WHERE token_hash = :hash');
    $stmt->execute([':hash' => hash('sha256', $token)]);
}

/* ---------------------------------------------------------------------
   Registro y login
--------------------------------------------------------------------- */

/**
 * Registra un usuario nuevo. Devuelve el usuario creado.
 */
function register_user(string $name, string $email, string $password): array
{
    $errors = array_merge(
        validate_name($name),
        validate_email($email),
        validate_password($password)
    );

    // Unicidad de email / nombre
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :e OR nombre = :n LIMIT 1');
    $stmt->execute([':e' => $email, ':n' => $name]);
    if ($row = $stmt->fetch()) {
        $errors['email'] = 'Ya existe una cuenta con ese email o nombre.';
    }

    bail_on_errors($errors);

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password_hash) VALUES (:n, :e, :p)'
    );
    $stmt->execute([
        ':n' => $name,
        ':e' => $email,
        ':p' => password_hash($password, PASSWORD_DEFAULT),
    ]);

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
 * Comprueba y registra el rate limiting del login para una clave email+IP.
 * Si hay bloqueo activo, responde 429.
 */
function rate_limit_check(string $key): void
{
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT id, intentos, bloqueado_hasta FROM login_intentos
          WHERE email = :key AND ip = :ip LIMIT 1'
    );
    $stmt->execute([':key' => $key, ':ip' => client_ip()]);
    $row = $stmt->fetch();

    if ($row && $row['bloqueado_hasta'] && strtotime($row['bloqueado_hasta']) > time()) {
        $until = date('H:i:s', strtotime($row['bloqueado_hasta']));
        error_response("Demasiados intentos. Volve a intentar despues de las $until.", 429);
    }
}

/**
 * Registra un intento fallido de login.
 */
function rate_limit_failed(string $key): void
{
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT id, intentos FROM login_intentos
          WHERE email = :key AND ip = :ip LIMIT 1'
    );
    $stmt->execute([':key' => $key, ':ip' => client_ip()]);
    $row = $stmt->fetch();

    $attempts = $row ? (int)$row['intentos'] + 1 : 1;

    if ($attempts >= 5) {
        // Bloqueo progresivo: 2, 4, 6... minutos
        $blockedUntil = date('Y-m-d H:i:s', time() + ($attempts - 4) * 120);
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
        $stmt->execute([':key' => $key, ':ip' => client_ip(), ':i' => $attempts, ':b' => $blockedUntil]);
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
 * Intenta iniciar sesion. Devuelve ['success' => true, 'token' => ..., 'user' => ...]
 * o termina con error 401/429.
 */
function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    if (!$email || $password === '') {
        error_response('Credenciales invalidas', 401);
    }

    rate_limit_check($email);

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        rate_limit_failed($email);
        error_response('Credenciales invalidas', 401);
    }

    rate_limit_clear($email);
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
        'rango'      => $user['rango'],
        'reputacion' => (int)$user['reputacion'],
        'creado_en'  => date_only($user['creado_en'] ?? null),
    ];
}