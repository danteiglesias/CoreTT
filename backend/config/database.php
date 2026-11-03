<?php
/**
 * Configuración de base de datos CoreTT
 * Compatible con servidor Linux/apache/nginx + MySQL/MariaDB y phpMyAdmin.
 */

require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $conn;

    public function __construct() {
        // Cargar CoreTT/.env (o backend/.env) antes de leer las variables
        corett_load_environment();

        // getenv() devuelve false si la variable no existe; una contraseña
        // vacía es un valor válido (''), por eso no se usa el operador ?:.
        $this->host = (string) corett_env_first(array('DB_HOST', 'MYSQL_HOST', 'DATABASE_HOST', 'DB_HOSTNAME'), 'localhost');
        $this->port = (string) corett_env_first(array('DB_PORT', 'MYSQL_PORT', 'DATABASE_PORT'), '3306');
        $this->db_name = (string) corett_env_first(array('DB_NAME', 'MYSQL_DATABASE', 'DATABASE_NAME', 'DB_DATABASE'), 'corett_db');
        $this->username = (string) corett_env_first(array('DB_USER', 'MYSQL_USER', 'DATABASE_USER', 'DB_USERNAME', 'MYSQL_USERNAME'), 'root');
        $this->password = (string) corett_env_first(array('DB_PASS', 'DB_PASSWORD', 'MYSQL_PASSWORD', 'DATABASE_PASSWORD', 'MYSQL_ROOT_PASSWORD'), '');
        $this->charset = (string) corett_env_first(array('DB_CHARSET', 'MYSQL_CHARSET', 'DATABASE_CHARSET'), 'utf8mb4');

        if ($this->host === '') {
            $this->host = 'localhost';
        }
        if ($this->port === '') {
            $this->port = '3306';
        }
        if ($this->charset === '') {
            $this->charset = 'utf8mb4';
        }
    }

    public function getConnection() {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        $this->conn = null;

        try {
            if ($this->db_name === '') {
                throw new RuntimeException('La variable DB_NAME está vacía. Revisa tu archivo .env.');
            }

            $dsn = 'mysql:host=' . $this->host
                 . ';port=' . $this->port
                 . ';dbname=' . $this->db_name
                 . ';charset=' . $this->charset;

            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            );

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (Throwable $exception) {
            error_log('CoreTT DB error: ' . $exception->getMessage());

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=UTF-8');
            }

            $payload = array(
                'error' => 'No se pudo conectar a la base de datos.',
                'sugerencia' => 'Revisa DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS en el archivo .env. Puedes abrir backend/diagnostico.php para más detalles.',
            );

            if (corett_debug_enabled()) {
                $payload['detalle'] = $exception->getMessage();
                $payload['config'] = array(
                    'host' => $this->host,
                    'port' => $this->port,
                    'database' => $this->db_name,
                    'user' => $this->username,
                    'password_definida' => $this->password !== '',
                );
            }

            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }

        return $this->conn;
    }
}

/**
 * Devuelve una conexión PDO reutilizando la misma instancia por petición.
 *
 * @return PDO
 */
function getDB() {
    static $database = null;

    if (!$database instanceof Database) {
        $database = new Database();
    }

    return $database->getConnection();
}
