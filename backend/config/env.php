<?php
/**
 * Carga de configuración desde .env para CoreTT
 *
 * PHP no lee los archivos .env automáticamente. Sin este cargador, los valores
 * de DB_USER / DB_PASS definidos en .env se ignoraban y la aplicación siempre
 * intentaba conectar como root sin contraseña.
 */

/**
 * Rutas donde se busca el archivo .env (la primera que exista gana).
 * - Raíz del proyecto:  CoreTT/.env
 * - Carpeta backend:    CoreTT/backend/.env
 *
 * @return string[]
 */
function corett_env_paths() {
    return array(
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
    );
}

/**
 * Lee un archivo .env y carga sus variables en getenv(), $_ENV y $_SERVER.
 * Las variables que ya existan en el entorno real tienen prioridad.
 *
 * @param string $path
 * @return bool
 */
function corett_load_env_file($path) {
    if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
        return false;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Ignorar líneas vacías y comentarios
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        $parts = explode('=', $line, 2);
        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // Permitir "export DB_HOST=..."
        if (stripos($key, 'export ') === 0) {
            $key = trim(substr($key, 7));
        }

        if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
        }

        // Quitar comillas simples o dobles que envuelvan el valor
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = substr($value, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
                if ($first === '"') {
                    $value = str_replace(
                        array('\\n', '\\r', '\\t', '\\"', '\\\\'),
                        array("\n", "\r", "\t", '"', '\\'),
                        $value
                    );
                }
            }
        }

        // No sobreescribir variables reales del servidor
        if (getenv($key) === false) {
            @putenv($key . '=' . $value);
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }
    }

    return true;
}

/**
 * Carga el primer .env disponible una sola vez por petición.
 *
 * @return string|false Ruta del archivo cargado o false si no existe ninguno.
 */
function corett_load_environment() {
    static $loaded = null;

    if ($loaded !== null) {
        return $loaded;
    }

    $loaded = false;
    foreach (corett_env_paths() as $path) {
        if (corett_load_env_file($path)) {
            $loaded = $path;
            break;
        }
    }

    return $loaded;
}

/**
 * Devuelve el valor de una variable de entorno.
 *
 * Se consultan getenv(), $_ENV y $_SERVER porque en algunos hostings
 * putenv() está deshabilitado.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function corett_env($key, $default = null) {
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER)) {
        return $_SERVER[$key];
    }
    return $default;
}

/**
 * Devuelve el valor de la primera variable de entorno existente en la lista.
 * Útil para aceptar nombres alternativos (DB_PASSWORD, MYSQL_HOST, etc.).
 *
 * @param string[] $keys
 * @param mixed    $default
 * @return mixed
 */
function corett_env_first(array $keys, $default = null) {
    foreach ($keys as $key) {
        $value = corett_env($key, null);
        if ($value !== null && $value !== false && trim((string) $value) !== '') {
            return $value;
        }
    }
    // Permitir valores vacíos explícitos solo si la clave existe
    foreach ($keys as $key) {
        $value = corett_env($key, null);
        if ($value !== null && $value !== false) {
            // Distinguir "no definida" de "definida vacía"
            if (getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) {
                return $value;
            }
        }
    }
    return $default;
}

/**
 * Devuelve la configuración de MySQL usada por la aplicación y el diagnóstico.
 *
 * @return array
 */
function corett_database_config() {
    corett_load_environment();

    $config = array(
        'host' => (string) corett_env_first(array('DB_HOST', 'MYSQL_HOST', 'DATABASE_HOST', 'DB_HOSTNAME'), 'localhost'),
        'port' => (string) corett_env_first(array('DB_PORT', 'MYSQL_PORT', 'DATABASE_PORT'), '3306'),
        'name' => (string) corett_env_first(array('DB_NAME', 'MYSQL_DATABASE', 'DATABASE_NAME', 'DB_DATABASE'), 'corett_db'),
        'user' => (string) corett_env_first(array('DB_USER', 'MYSQL_USER', 'DATABASE_USER', 'DB_USERNAME', 'MYSQL_USERNAME'), 'root'),
        'password' => (string) corett_env_first(array('DB_PASS', 'DB_PASSWORD', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD', 'MYSQL_ROOT_PASSWORD'), ''),
        'charset' => (string) corett_env_first(array('DB_CHARSET', 'MYSQL_CHARSET', 'DATABASE_CHARSET'), 'utf8mb4'),
    );

    if ($config['host'] === '') {
        $config['host'] = 'localhost';
    }
    if ($config['port'] === '') {
        $config['port'] = '3306';
    }
    if ($config['charset'] === '') {
        $config['charset'] = 'utf8mb4';
    }

    return $config;
}

/**
 * Indica si el modo depuración está activo.
 *
 * @return bool
 */
function corett_debug_enabled() {
    $value = strtolower(trim((string) corett_env('APP_DEBUG', 'false')));
    return in_array($value, array('1', 'true', 'yes', 'on'), true);
}
