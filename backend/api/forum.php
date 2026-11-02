<?php
/**
 * API de Foro - CoreTT
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

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Obtener post específico con comentarios
            $id = intval($_GET['id']);
            $query = "SELECT p.id, p.titulo as title, p.contenido as body, u.nombre_usuario as author, 
                             DATE(p.fecha_creacion) as date, p.vistas
                      FROM foro_posts p
                      JOIN usuarios u ON p.usuario_id = u.id
                      WHERE p.id = :id AND p.activo = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $post = $stmt->fetch();
            
            if (!$post) {
                sendError('Post no encontrado', 404);
            }
            
            // Incrementar vistas
            $updateViews = "UPDATE foro_posts SET vistas = vistas + 1 WHERE id = :id";
            $stmtViews = $db->prepare($updateViews);
            $stmtViews->bindParam(':id', $id);
            $stmtViews->execute();
            
            // Obtener comentarios
            $commentQuery = "SELECT c.id, c.contenido as text, u.nombre_usuario as author, DATE(c.fecha_creacion) as date
                            FROM foro_comentarios c
                            JOIN usuarios u ON c.usuario_id = u.id
                            WHERE c.post_id = :post_id AND c.activo = 1
                            ORDER BY c.fecha_creacion ASC";
            $stmtComments = $db->prepare($commentQuery);
            $stmtComments->bindParam(':post_id', $id);
            $stmtComments->execute();
            $comments = $stmtComments->fetchAll();
            
            $post['comments'] = $comments;
            $post['replies'] = count($comments);
            
            sendResponse($post);
        } else {
            // Listar posts
            $query = "SELECT p.id, p.titulo as title, p.contenido as body, u.nombre_usuario as author, 
                             DATE(p.fecha_creacion) as date, p.fijado,
                             (SELECT COUNT(*) FROM foro_comentarios WHERE post_id = p.id AND activo = 1) as replies,
                             LEFT(p.contenido, 120) as excerpt
                      FROM foro_posts p
                      JOIN usuarios u ON p.usuario_id = u.id
                      WHERE p.activo = 1
                      ORDER BY p.fijado DESC, p.fecha_creacion DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $posts = $stmt->fetchAll();
            
            // Procesar excerpt
            foreach ($posts as $key => $post) {
                if (strlen($post['body']) > 120) {
                    $posts[$key]['excerpt'] = substr(strip_tags($post['body']), 0, 120) . '...';
                } else {
                    $posts[$key]['excerpt'] = strip_tags($post['body']);
                }
                unset($posts[$key]['body']);
            }
            
            sendResponse($posts);
        }
        break;
        
    case 'POST':
        if ($action === 'comment') {
            // Añadir comentario
            $user = requireAuth();
            $data = getJsonInput();
            
            if (!isset($data['postId']) || !isset($data['text'])) {
                sendError('ID del post y comentario son requeridos');
            }
            
            $postId = intval($data['postId']);
            $text = sanitize($data['text']);
            
            if (empty($text)) {
                sendError('El comentario no puede estar vacío');
            }
            
            // Verificar que el post existe
            $checkPost = "SELECT id FROM foro_posts WHERE id = :id AND activo = 1 AND cerrado = 0";
            $stmtCheck = $db->prepare($checkPost);
            $stmtCheck->bindParam(':id', $postId);
            $stmtCheck->execute();
            
            if ($stmtCheck->rowCount() == 0) {
                sendError('Post no encontrado o cerrado', 404);
            }
            
            $query = "INSERT INTO foro_comentarios (post_id, usuario_id, contenido) VALUES (:post_id, :usuario_id, :contenido)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':post_id', $postId);
            $stmt->bindParam(':usuario_id', $user['id']);
            $stmt->bindParam(':contenido', $text);
            
            if ($stmt->execute()) {
                sendResponse(array(
                    "success" => true,
                    "comment" => array(
                        "author" => $user['nombre_usuario'],
                        "date" => date('Y-m-d'),
                        "text" => $text
                    )
                ));
            } else {
                sendError('Error al añadir comentario');
            }
        } else {
            // Crear post
            $user = requireAuth();
            $data = getJsonInput();
            
            $errors = validateRequired($data, array('title', 'body'));
            if (!empty($errors)) {
                sendError(implode(', ', $errors));
            }
            
            $titulo = sanitize($data['title']);
            $contenido = $data['body'];
            
            $query = "INSERT INTO foro_posts (usuario_id, titulo, contenido) VALUES (:usuario_id, :titulo, :contenido)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':usuario_id', $user['id']);
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':contenido', $contenido);
            
            if ($stmt->execute()) {
                $postId = $db->lastInsertId();
                sendResponse(array(
                    "success" => true,
                    "post" => array(
                        "id" => $postId,
                        "title" => $titulo,
                        "body" => $contenido,
                        "author" => $user['nombre_usuario'],
                        "date" => date('Y-m-d'),
                        "comments" => array(),
                        "replies" => 0
                    )
                ));
            } else {
                sendError('Error al crear el post');
            }
        }
        break;
        
    case 'DELETE':
        $user = requireAuth();
        if (!isset($_GET['id'])) {
            sendError('ID requerido');
        }
        $id = intval($_GET['id']);
        
        $check = "SELECT usuario_id FROM foro_posts WHERE id = :id";
        $stmtCheck = $db->prepare($check);
        $stmtCheck->bindParam(':id', $id);
        $stmtCheck->execute();
        $post = $stmtCheck->fetch();
        
        if (!$post) {
            sendError('Post no encontrado', 404);
        }
        
        if ($post['usuario_id'] != $user['id'] && $user['rol'] !== 'admin' && $user['rol'] !== 'moderador') {
            sendError('No autorizado', 403);
        }
        
        $query = "UPDATE foro_posts SET activo = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            sendResponse(array("success" => true));
        } else {
            sendError('Error al eliminar el post');
        }
        break;
        
    default:
        sendError('Método no permitido', 405);
        break;
}
?>
