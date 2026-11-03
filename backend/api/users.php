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

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validations.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    $db = getDB();
} catch (Throwable $e) {
    sendError('No se pudo conectar a la base de datos. Revisa el archivo .env.', 500);
}

switch ($method) {
    case 'GET':
        if ($action === 'profile') {
            // Obtener perfil del usuario autenticado
            $user = requireAuth();
            $query = "SELECT id, nombre_usuario AS name, email, rol AS role, rango AS rank,
                             reputacion, reputacion AS reputation, fecha_registro
                      FROM usuarios WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', (int) $user['id'], PDO::PARAM_INT);
            $stmt->execute();
            $profile = $stmt->fetch();

            if (!$profile) {
                sendError('Usuario no encontrado', 404);
            }

            // Obtener estadísticas
            $statsQuery = "SELECT
                (SELECT COUNT(*) FROM guias WHERE usuario_id = :id AND activo = 1) AS guides,
                (SELECT COALESCE(SUM(votos_positivos), 0) FROM guias WHERE usuario_id = :id) AS votes";
            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->bindValue(':id', (int) $user['id'], PDO::PARAM_INT);
            $statsStmt->execute();
            $stats = $statsStmt->fetch();

            $profile['guides'] = (int) $stats['guides'];
            $profile['votes'] = (int) $stats['votes'];

            sendResponse($profile);
        } else {
            // Listar usuarios (solo admin)
            $user = requireAdmin();
            $query = "SELECT u.id, u.nombre_usuario AS name, u.email, u.rol AS role,
                             u.rango AS rank, u.reputacion, u.reputacion AS reputation, u.activo, u.fecha_registro,
                             (SELECT COUNT(*) FROM guias g WHERE g.usuario_id = u.id AND g.activo = 1) AS guides,
                             (SELECT COALESCE(SUM(g.votos_positivos), 0) FROM guias g WHERE g.usuario_id = u.id) AS votes
                      FROM usuarios u
                      ORDER BY u.fecha_registro DESC";
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
            if (empty($data['email']) || empty($data['password'])) {
                sendError('Email y contraseña son requeridos');
            }

            $email = strtolower(trim((string) $data['email']));
            $password = (string) $data['password'];

            $query = "SELECT id, nombre_usuario AS name, email, password_hash, rol AS role,
                             rango AS rank, reputacion AS reputation, activo
                      FROM usuarios WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch();

            if (!$user || (int) $user['activo'] !== 1) {
                sendError('Credenciales inválidas', 401);
            }

            if (!verifyPassword($password, $user['password_hash'])) {
                sendError('Credenciales inválidas', 401);
            }

            // Actualizar última conexión
            $updateQuery = "UPDATE usuarios SET ultima_conexion = NOW() WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindValue(':id', (int) $user['id'], PDO::PARAM_INT);
            $updateStmt->execute();

            unset($user['password_hash'], $user['activo']);
            $user['id'] = (int) $user['id'];
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
            $email = strtolower(sanitize($data['email']));
            $password = (string) $data['password'];

            if (!validateEmail($email)) {
                sendError('Email inválido');
            }
            if (!validatePassword($password)) {
                sendError('La contraseña debe tener al menos 4 caracteres');
            }
            if (!validateUsername($name)) {
                sendError('El nombre de usuario debe tener entre 3 y 50 caracteres (letras, números, espacios, guiones o guiones bajos)');
            }

            // Verificar si ya existe
            $checkQuery = "SELECT id FROM usuarios WHERE email = :email OR nombre_usuario = :name LIMIT 1";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindValue(':email', $email);
            $checkStmt->bindValue(':name', $name);
            $checkStmt->execute();

            if ($checkStmt->fetch()) {
                sendError('El email o nombre de usuario ya están registrados');
            }

            $passwordHash = hashPassword($password);
            $query = "INSERT INTO usuarios (nombre_usuario, email, password_hash, rol, rango)
                      VALUES (:name, :email, :password_hash, 'usuario', 'Bronce')";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':password_hash', $passwordHash);

            try {
                $stmt->execute();
            } catch (PDOException $e) {
                // Clave duplicada por dos registros simultáneos
                if ($e->getCode() === '23000') {
                    sendError('El email o nombre de usuario ya están registrados');
                }
                error_log('CoreTT register error: ' . $e->getMessage());
                sendError('Error al crear la cuenta. Revisa los permisos del usuario de la base de datos.', 500);
            }

            sendResponse(array(
                "success" => true,
                "message" => "Cuenta creada correctamente. Ahora puedes iniciar sesión."
            ));
        } else {
            sendError('Acción no válida');
        }
        break;

    default:
        sendError('Método no permitido', 405);
        break;
}
