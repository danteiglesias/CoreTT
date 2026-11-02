<?php
/**
 * API de Guías - CoreTT
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
            // Obtener una guía específica
            $id = intval($_GET['id']);
            $query = "SELECT g.id, g.titulo as title, j.nombre as game, u.nombre_usuario as author, 
                             g.descripcion as description, g.contenido as content, g.votos_positivos - g.votos_negativos as votes,
                             DATE(g.fecha_creacion) as date, g.vistas
                      FROM guias g
                      JOIN usuarios u ON g.usuario_id = u.id
                      JOIN juegos j ON g.juego_id = j.id
                      WHERE g.id = :id AND g.activo = 1 AND g.aprobado = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $guia = $stmt->fetch();
            
            if (!$guia) {
                sendError('Guía no encontrada', 404);
            }
            
            // Incrementar vistas
            $updateViews = "UPDATE guias SET vistas = vistas + 1 WHERE id = :id";
            $stmtViews = $db->prepare($updateViews);
            $stmtViews->bindParam(':id', $id);
            $stmtViews->execute();
            
            sendResponse($guia);
        } else {
            // Listar guías
            $gameFilter = isset($_GET['game']) ? $_GET['game'] : '';
            $searchFilter = isset($_GET['search']) ? $_GET['search'] : '';
            
            $query = "SELECT g.id, g.titulo as title, j.nombre as game, u.nombre_usuario as author, 
                             g.descripcion as description, g.votos_positivos - g.votos_negativos as votes,
                             DATE(g.fecha_creacion) as date
                      FROM guias g
                      JOIN usuarios u ON g.usuario_id = u.id
                      JOIN juegos j ON g.juego_id = j.id
                      WHERE g.activo = 1 AND g.aprobado = 1";
            
            $params = array();
            
            if ($gameFilter && $gameFilter !== 'Todos') {
                $query .= " AND j.nombre = :game";
                $params[':game'] = $gameFilter;
            }
            
            if ($searchFilter) {
                $query .= " AND (g.titulo LIKE :search OR g.descripcion LIKE :search OR u.nombre_usuario LIKE :search)";
                $params[':search'] = '%' . $searchFilter . '%';
            }
            
            $query .= " ORDER BY g.destacado DESC, votes DESC, g.fecha_creacion DESC";
            
            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $guias = $stmt->fetchAll();
            
            sendResponse($guias);
        }
        break;
        
    case 'POST':
        if ($action === 'vote') {
            // Votar guía
            $user = requireAuth();
            $data = getJsonInput();
            
            if (!isset($data['id']) || !isset($data['vote'])) {
                sendError('ID de guía y voto son requeridos');
            }
            
            $guiaId = intval($data['id']);
            $vote = intval($data['vote']); // +1 o -1
            
            if ($vote !== 1 && $vote !== -1) {
                sendError('Voto inválido');
            }
            
            // Verificar si ya votó
            $checkVote = "SELECT id, tipo_voto FROM votos_guias WHERE guia_id = :guia_id AND usuario_id = :usuario_id";
            $stmtCheck = $db->prepare($checkVote);
            $stmtCheck->bindParam(':guia_id', $guiaId);
            $stmtCheck->bindParam(':usuario_id', $user['id']);
            $stmtCheck->execute();
            $existingVote = $stmtCheck->fetch();
            
            if ($existingVote) {
                if ($existingVote['tipo_voto'] == $vote) {
                    // Quitar voto
                    $deleteVote = "DELETE FROM votos_guias WHERE id = :id";
                    $stmtDel = $db->prepare($deleteVote);
                    $stmtDel->bindParam(':id', $existingVote['id']);
                    $stmtDel->execute();
                    
                    // Actualizar contadores
                    if ($vote == 1) {
                        $updateGuia = "UPDATE guias SET votos_positivos = votos_positivos - 1 WHERE id = :guia_id";
                    } else {
                        $updateGuia = "UPDATE guias SET votos_negativos = votos_negativos - 1 WHERE id = :guia_id";
                    }
                    $stmtUp = $db->prepare($updateGuia);
                    $stmtUp->bindParam(':guia_id', $guiaId);
                    $stmtUp->execute();
                } else {
                    // Cambiar voto
                    $updateVote = "UPDATE votos_guias SET tipo_voto = :vote WHERE id = :id";
                    $stmtUpVote = $db->prepare($updateVote);
                    $stmtUpVote->bindParam(':vote', $vote);
                    $stmtUpVote->bindParam(':id', $existingVote['id']);
                    $stmtUpVote->execute();
                    
                    // Ajustar contadores
                    if ($existingVote['tipo_voto'] == 1) {
                        $updateGuia1 = "UPDATE guias SET votos_positivos = votos_positivos - 1, votos_negativos = votos_negativos + 1 WHERE id = :guia_id";
                    } else {
                        $updateGuia1 = "UPDATE guias SET votos_positivos = votos_positivos + 1, votos_negativos = votos_negativos - 1 WHERE id = :guia_id";
                    }
                    $stmtUp1 = $db->prepare($updateGuia1);
                    $stmtUp1->bindParam(':guia_id', $guiaId);
                    $stmtUp1->execute();
                }
            } else {
                // Nuevo voto
                $insertVote = "INSERT INTO votos_guias (guia_id, usuario_id, tipo_voto) VALUES (:guia_id, :usuario_id, :vote)";
                $stmtIns = $db->prepare($insertVote);
                $stmtIns->bindParam(':guia_id', $guiaId);
                $stmtIns->bindParam(':usuario_id', $user['id']);
                $stmtIns->bindParam(':vote', $vote);
                $stmtIns->execute();
                
                if ($vote == 1) {
                    $updateGuia = "UPDATE guias SET votos_positivos = votos_positivos + 1 WHERE id = :guia_id";
                } else {
                    $updateGuia = "UPDATE guias SET votos_negativos = votos_negativos + 1 WHERE id = :guia_id";
                }
                $stmtUp = $db->prepare($updateGuia);
                $stmtUp->bindParam(':guia_id', $guiaId);
                $stmtUp->execute();
            }
            
            // Obtener nuevo conteo
            $getVotes = "SELECT votos_positivos - votos_negativos as votes FROM guias WHERE id = :id";
            $stmtGet = $db->prepare($getVotes);
            $stmtGet->bindParam(':id', $guiaId);
            $stmtGet->execute();
            $result = $stmtGet->fetch();
            
            sendResponse(array("success" => true, "votes" => $result['votes']));
        } else {
            // Crear guía
            $user = requireAuth();
            $data = getJsonInput();
            
            $errors = validateRequired($data, array('title', 'game', 'description', 'content'));
            if (!empty($errors)) {
                sendError(implode(', ', $errors));
            }
            
            // Obtener juego_id
            $getGame = "SELECT id FROM juegos WHERE nombre = :game AND activo = 1";
            $stmtGame = $db->prepare($getGame);
            $stmtGame->bindParam(':game', $data['game']);
            $stmtGame->execute();
            $juego = $stmtGame->fetch();
            
            if (!$juego) {
                sendError('Juego no válido');
            }
            
            $query = "INSERT INTO guias (usuario_id, juego_id, titulo, descripcion, contenido) 
                      VALUES (:usuario_id, :juego_id, :titulo, :descripcion, :contenido)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':usuario_id', $user['id']);
            $stmt->bindParam(':juego_id', $juego['id']);
            $stmt->bindParam(':titulo', sanitize($data['title']));
            $stmt->bindParam(':descripcion', sanitize($data['description']));
            $stmt->bindParam(':contenido', $data['content']); // Permitir HTML básico si necesario
            
            if ($stmt->execute()) {
                $guiaId = $db->lastInsertId();
                sendResponse(array("success" => true, "id" => $guiaId));
            } else {
                sendError('Error al crear la guía');
            }
        }
        break;
        
    case 'DELETE':
        $user = requireAuth();
        if (!isset($_GET['id'])) {
            sendError('ID requerido');
        }
        $id = intval($_GET['id']);
        
        // Verificar si es propietario o admin
        $check = "SELECT usuario_id FROM guias WHERE id = :id";
        $stmtCheck = $db->prepare($check);
        $stmtCheck->bindParam(':id', $id);
        $stmtCheck->execute();
        $guia = $stmtCheck->fetch();
        
        if (!$guia) {
            sendError('Guía no encontrada', 404);
        }
        
        if ($guia['usuario_id'] != $user['id'] && $user['rol'] !== 'admin') {
            sendError('No autorizado', 403);
        }
        
        $query = "UPDATE guias SET activo = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            sendResponse(array("success" => true));
        } else {
            sendError('Error al eliminar la guía');
        }
        break;
        
    default:
        sendError('Método no permitido', 405);
        break;
}
?>
