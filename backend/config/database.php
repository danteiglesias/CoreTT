<?php
/**
 * CoreTT - Conexion a base de datos (PDO).
 *
 * Lee la configuracion desde un archivo .env en la raiz del proyecto
 * o desde variables de entorno del sistema.
 */

declare(strict_types=1);

if (!defined('CORETT_ROOT')) {
    define('CORETT_ROOT', dirname(__DIR__, 2));
}

if (!function_exists('env')) {
    /**
     * Devuelve una variable de entorno o su valor por defecto.
     * Soporta un archivo .env sencillo en la raiz del proyecto.
     */
    function env(string $key, $default = null)
    {
        static $loaded = false;

        if (!$loaded) {
            $loaded = true;
            $dotenv = CORETT_ROOT . DIRECTORY_SEPARATOR . '.env';
            if (is_file($dotenv)) {
                $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }
                    if (strpos($line, '=') === false) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    $v = trim($v, '"\'');
                    if ($k !== '') {
                        putenv($k . '=' . $v);
                        $_ENV[$k] = $v;
                    }
                }
            }
        }

        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }
        return $value !== null && $value !== false ? $value : $default;
    }
}

if (!function_exists('db')) {
    /**
     * Devuelve una unica instancia de PDO (singleton).
     */
    function db(): PDO
    {
        static $pdo = null;

        if ($pdo === null) {
            $host    = env('DB_HOST', '127.0.0.1');
            $port    = env('DB_PORT', '3306');
            $name    = env('DB_NAME', 'corett');
            $user    = env('DB_USER', 'root');
            $pass    = env('DB_PASSWORD', '');
            $charset = env('DB_CHARSET', 'utf8mb4');

            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return $pdo;
    }
}