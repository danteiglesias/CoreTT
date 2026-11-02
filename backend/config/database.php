<?php
/**
 * Configuración de base de datos CoreTT
 * Para uso en servidor SSH con PHP y MySQL/phpMyAdmin
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $conn;
    
    public function __construct() {
        // Configuración desde variables de entorno o valores por defecto
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'corett_db';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
        $this->charset = 'utf8mb4';
    }
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            );
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            // En producción, registrar error en log en lugar de mostrarlo
            error_log("Error de conexión: " . $exception->getMessage());
            die(json_encode(array("error" => "Error de conexión a la base de datos")));
        }
        
        return $this->conn;
    }
}

// Función helper para obtener conexión
function getDB() {
    $database = new Database();
    return $database->getConnection();
}
?>
