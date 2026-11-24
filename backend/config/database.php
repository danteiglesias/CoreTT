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
        $config = corett_database_config();
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->db_name = $config['name'];
        $this->username = $config['user'];
        $this->password = $config['password'];
        $this->charset = $config['charset'];
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
