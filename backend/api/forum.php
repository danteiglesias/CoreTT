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

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validations.php';

try {
    $db = getDB();
} catch (Throwable $e) {
    sendError('No se pudo conectar a la base de datos. Revisa el archivo .env.', 500);
}
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

/**
 * Recorta texto UTF-8 sin cortar caracteres multibyte.
 */
function excerptText($text, $length = 120) {
    $text = trim(strip_tags((string) $text));
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $length) {
            return mb_substr($text, 0, $length, 'UTF-8') . '...';
        }
        return $text;
    }
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

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
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $post = $stmt->fetch();

            if (!$post) {
                sendError('Post no encontrado', 404);
            }

            // Incrementar vistas
            $updateViews = "UPDATE foro_posts SET vistas = vistas + 1 WHERE id = :id";
            $stmtViews = $db->prepare($updateViews);
            $stmtViews->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtViews->execute();

            if (isset($post['vistas'])) {
                $post['vistas'] = (int) $post['vistas'] + 1;
            }

            // Obtener comentarios
            $commentQuery = "SELECT c.id, c.contenido as text, u.nombre_usuario as author, DATE(c.fecha_creacion) as date
                            FROM foro_comentarios c
                            JOIN usuarios u ON c.usuario_id = u.id
                            WHERE c.post_id = :post_id AND c.activo = 1
                            ORDER BY c.fecha_creacion ASC";
            $stmtComments = $db->prepare($commentQuery);
            $stmtComments->bindValue(':post_id', $id, PDO::PARAM_INT);
            $stmtComments->execute();
            $comments = $stmtComments->fetchAll();

            $post['comments'] = $comments;
            $post['replies'] = count($comments);

            sendResponse($post);
        } else {
            // Listar posts
            $query = "SELECT p.id, p.titulo as title, p.contenido as body, u.nombre_usuario as author,
                             DATE(p.fecha_creacion) as date, p.fijado,
                             (SELECT COUNT(*) FROM foro_comentarios WHERE post_id = p.id AND activo = 1) as replies
                      FROM foro_posts p
                      JOIN usuarios u ON p.usuario_id = u.id
                      WHERE p.activo = 1
                      ORDER BY p.fijado DESC, p.fecha_creacion DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $posts = $stmt->fetchAll();

            // Procesar excerpt
            foreach ($posts as $key => $post) {
                $posts[$key]['excerpt'] = excerptText($post['body']);
                $posts[$key]['replies'] = (int) $post['replies'];
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

            if ($text === '') {
                sendError('El comentario no puede estar vacío');
            }

            // Verificar que el post existe
            $checkPost = "SELECT id FROM foro_posts WHERE id = :id AND activo = 1 AND cerrado = 0";
            $stmtCheck = $db->prepare($checkPost);
            $stmtCheck->bindValue(':id', $postId, PDO::PARAM_INT);
            $stmtCheck->execute();

            if (!$stmtCheck->fetch()) {
                sendError('Post no encontrado o cerrado', 404);
            }

            $query = "INSERT INTO foro_comentarios (post_id, usuario_id, contenido) VALUES (:post_id, :usuario_id, :contenido)";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
            $stmt->bindValue(':usuario_id', (int) $user['id'], PDO::PARAM_INT);
            $stmt->bindValue(':contenido', $text);

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
            $contenido = trim((string) $data['body']);

            if ($titulo === '' || $contenido === '') {
                sendError('El título y el contenido no pueden estar vacíos');
            }

            $query = "INSERT INTO foro_posts (usuario_id, titulo, contenido) VALUES (:usuario_id, :titulo, :contenido)";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':usuario_id', (int) $user['id'], PDO::PARAM_INT);
            $stmt->bindValue(':titulo', $titulo);
            $stmt->bindValue(':contenido', $contenido);

            if ($stmt->execute()) {
                $postId = (int) $db->lastInsertId();
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
        $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        $post = $stmtCheck->fetch();

        if (!$post) {
            sendError('Post no encontrado', 404);
        }

        if ((int) $post['usuario_id'] !== (int) $user['id'] && $user['rol'] !== 'admin' && $user['rol'] !== 'moderador') {
            sendError('No autorizado', 403);
        }

        $query = "UPDATE foro_posts SET activo = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

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
