<?php
/**
 * API de Usuarios - CoreTT
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/validations.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$db = getDB();

switch ($method) {
    case 'GET':
        if ($action === 'profile') {
            // Obtener perfil del usuario autenticado
            $user = requireAuth();
            $query = "SELECT id, nombre_usuario as name, email, rol as role, rango as rank, reputacion, fecha_registro 
                      FROM usuarios WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $user['id']);
            $stmt->execute();
            $profile = $stmt->fetch();
            
            // Obtener estadísticas
            $statsQuery = "SELECT 
                (SELECT COUNT(*) FROM guias WHERE usuario_id = :id AND activo = 1) as guides,
                (SELECT COALESCE(SUM(votos_positivos), 0) FROM guias WHERE usuario_id = :id) as votes
            ";
            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->bindParam(':id', $user['id']);
            $statsStmt->execute();
            $stats = $statsStmt->fetch();
            
            $profile['guides'] = $stats['guides'];
            $profile['votes'] = $stats['votes'];
            
            sendResponse($profile);
        } else {
            // Listar usuarios (solo admin)
            $user = requireAdmin();
            $query = "SELECT id, nombre_usuario as name, email, rol as role, rango as rank, reputacion, activo, fecha_registro 
                      FROM usuarios ORDER BY fecha_registro DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $usuarios = $stmt->fetchAll();
            sendResponse($usuarios);
        }
        break;
        
    case 'POST':
        $data = getJsonInput();
        
        if ($action === 'login') {
            // Iniciar sesión
            if (!isset($data['email']) || !isset($data['password'])) {
                sendError('Email y contraseña son requeridos');
            }
            
            $email = sanitize($data['email']);
            $password = $data['password'];
            
            $query = "SELECT id, nombre_usuario as name, email, password_hash, rol as role, rango as rank, activo 
                      FROM usuarios WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if (!$user || !$user['activo']) {
                sendError('Credenciales inválidas', 401);
            }
            
            if (!verifyPassword($password, $user['password_hash'])) {
                sendError('Credenciales inválidas', 401);
            }
            
            // Actualizar última conexión
            $updateQuery = "UPDATE usuarios SET ultima_conexion = NOW() WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':id', $user['id']);
            $updateStmt->execute();
            
            unset($user['password_hash']);
            $token = generateToken($user['id']);
            
            sendResponse(array(
                "success" => true,
                "token" => $token,
                "user" => $user
            ));
            
        } elseif ($action === 'register') {
            // Registrar usuario
            $errors = validateRequired($data, array('name', 'email', 'password'));
            if (!empty($errors)) {
                sendError(implode(', ', $errors));
            }
            
            $name = sanitize($data['name']);
            $email = sanitize($data['email']);
            $password = $data['password'];
            
            if (!validateEmail($email)) {
                sendError('Email inválido');
            }
            if (!validatePassword($password)) {
                sendError('La contraseña debe tener al menos 4 caracteres');
            }
            if (!validateUsername($name)) {
                sendError('El nombre de usuario debe tener entre 3 y 50 caracteres alfanuméricos');
            }
            
            // Verificar si ya existe
            $checkQuery = "SELECT id FROM usuarios WHERE email = :email OR nombre_usuario = :name";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->bindParam(':name', $name);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                sendError('El email o nombre de usuario ya están registrados');
            }
            
            $passwordHash = hashPassword($password);
            $query = "INSERT INTO usuarios (nombre_usuario, email, password_hash, rol, rango) 
                      VALUES (:name, :email, :password_hash, 'usuario', 'Bronce')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $passwordHash);
            
            if ($stmt->execute()) {
                sendResponse(array(
                    "success" => true,
                    "message" => "Cuenta creada correctamente. Ahora puedes iniciar sesión."
                ));
            } else {
                sendError('Error al crear la cuenta');
            }
        } else {
            sendError('Acción no válida');
        }
        break;
        
    default:
        sendError('Método no permitido', 405);
        break;
}
?>
