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

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Obtener una guía específica
            $id = intval($_GET['id']);
            $query = "SELECT g.id, g.titulo as title, j.nombre as game, u.nombre_usuario as author,
                             g.descripcion as description, g.contenido as content,
                             g.votos_positivos - g.votos_negativos as votes,
                             DATE(g.fecha_creacion) as date, g.vistas
                      FROM guias g
                      JOIN usuarios u ON g.usuario_id = u.id
                      JOIN juegos j ON g.juego_id = j.id
                      WHERE g.id = :id AND g.activo = 1 AND g.aprobado = 1";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $guia = $stmt->fetch();

            if (!$guia) {
                sendError('Guía no encontrada', 404);
            }

            // Incrementar vistas
            $updateViews = "UPDATE guias SET vistas = vistas + 1 WHERE id = :id";
            $stmtViews = $db->prepare($updateViews);
            $stmtViews->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtViews->execute();

            if (isset($guia['vistas'])) {
                $guia['vistas'] = (int) $guia['vistas'] + 1;
            }
            sendResponse($guia);
        } else {
            // Listar guías
            $gameFilter = isset($_GET['game']) ? trim((string) $_GET['game']) : '';
            $searchFilter = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

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
                $params[':search'] = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $searchFilter) . '%';
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

            // Comprobar que la guía existe y está activa
            $stmtGuide = $db->prepare("SELECT id FROM guias WHERE id = :id AND activo = 1");
            $stmtGuide->bindValue(':id', $guiaId, PDO::PARAM_INT);
            $stmtGuide->execute();
            if (!$stmtGuide->fetch()) {
                sendError('Guía no encontrada', 404);
            }

            // Verificar si ya votó
            $checkVote = "SELECT id, tipo_voto FROM votos_guias WHERE guia_id = :guia_id AND usuario_id = :usuario_id";
            $stmtCheck = $db->prepare($checkVote);
            $stmtCheck->bindValue(':guia_id', $guiaId, PDO::PARAM_INT);
            $stmtCheck->bindValue(':usuario_id', (int) $user['id'], PDO::PARAM_INT);
            $stmtCheck->execute();
            $existingVote = $stmtCheck->fetch();

            if ($existingVote) {
                if ((int) $existingVote['tipo_voto'] === $vote) {
                    // Quitar voto
                    $deleteVote = "DELETE FROM votos_guias WHERE id = :id";
                    $stmtDel = $db->prepare($deleteVote);
                    $stmtDel->bindValue(':id', (int) $existingVote['id'], PDO::PARAM_INT);
                    $stmtDel->execute();

                    // Actualizar contadores
                    if ($vote === 1) {
                        $updateGuia = "UPDATE guias SET votos_positivos = GREATEST(votos_positivos - 1, 0) WHERE id = :guia_id";
                    } else {
                        $updateGuia = "UPDATE guias SET votos_negativos = GREATEST(votos_negativos - 1, 0) WHERE id = :guia_id";
                    }
                    $stmtUp = $db->prepare($updateGuia);
                    $stmtUp->bindValue(':guia_id', $guiaId, PDO::PARAM_INT);
                    $stmtUp->execute();
                } else {
                    // Cambiar voto
                    $updateVote = "UPDATE votos_guias SET tipo_voto = :vote WHERE id = :id";
                    $stmtUpVote = $db->prepare($updateVote);
                    $stmtUpVote->bindValue(':vote', $vote, PDO::PARAM_INT);
                    $stmtUpVote->bindValue(':id', (int) $existingVote['id'], PDO::PARAM_INT);
                    $stmtUpVote->execute();

                    // Ajustar contadores
                    if ((int) $existingVote['tipo_voto'] === 1) {
                        $updateGuia1 = "UPDATE guias SET votos_positivos = GREATEST(votos_positivos - 1, 0), votos_negativos = votos_negativos + 1 WHERE id = :guia_id";
                    } else {
                        $updateGuia1 = "UPDATE guias SET votos_positivos = votos_positivos + 1, votos_negativos = GREATEST(votos_negativos - 1, 0) WHERE id = :guia_id";
                    }
                    $stmtUp1 = $db->prepare($updateGuia1);
                    $stmtUp1->bindValue(':guia_id', $guiaId, PDO::PARAM_INT);
                    $stmtUp1->execute();
                }
            } else {
                // Nuevo voto
                $insertVote = "INSERT INTO votos_guias (guia_id, usuario_id, tipo_voto) VALUES (:guia_id, :usuario_id, :vote)";
                $stmtIns = $db->prepare($insertVote);
                $stmtIns->bindValue(':guia_id', $guiaId, PDO::PARAM_INT);
                $stmtIns->bindValue(':usuario_id', (int) $user['id'], PDO::PARAM_INT);
                $stmtIns->bindValue(':vote', $vote, PDO::PARAM_INT);
                $stmtIns->execute();

                if ($vote === 1) {
                    $updateGuia = "UPDATE guias SET votos_positivos = votos_positivos + 1 WHERE id = :guia_id";
                } else {
                    $updateGuia = "UPDATE guias SET votos_negativos = votos_negativos + 1 WHERE id = :guia_id";
                }
                $stmtUp = $db->prepare($updateGuia);
                $stmtUp->bindValue(':guia_id', $guiaId, PDO::PARAM_INT);
                $stmtUp->execute();
            }

            // Obtener nuevo conteo
            $getVotes = "SELECT votos_positivos - votos_negativos as votes FROM guias WHERE id = :id";
            $stmtGet = $db->prepare($getVotes);
            $stmtGet->bindValue(':id', $guiaId, PDO::PARAM_INT);
            $stmtGet->execute();
            $result = $stmtGet->fetch();

            sendResponse(array("success" => true, "votes" => (int) $result['votes']));
        } else {
            // Crear guía
            $user = requireAuth();
            $data = getJsonInput();

            $errors = validateRequired($data, array('title', 'game', 'description', 'content'));
            if (!empty($errors)) {
                sendError(implode(', ', $errors));
            }

            $title = sanitize($data['title']);
            $description = sanitize($data['description']);
            $content = trim((string) $data['content']);
            $game = sanitize($data['game']);

            if ($title === '' || $description === '' || $content === '') {
                sendError('Los campos de la guía no pueden estar vacíos');
            }

            // Obtener juego_id
            $getGame = "SELECT id FROM juegos WHERE nombre = :game AND activo = 1";
            $stmtGame = $db->prepare($getGame);
            $stmtGame->bindValue(':game', $game);
            $stmtGame->execute();
            $juego = $stmtGame->fetch();

            if (!$juego) {
                sendError('Juego no válido');
            }

            $query = "INSERT INTO guias (usuario_id, juego_id, titulo, descripcion, contenido)
                      VALUES (:usuario_id, :juego_id, :titulo, :descripcion, :contenido)";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':usuario_id', (int) $user['id'], PDO::PARAM_INT);
            $stmt->bindValue(':juego_id', (int) $juego['id'], PDO::PARAM_INT);
            $stmt->bindValue(':titulo', $title);
            $stmt->bindValue(':descripcion', $description);
            $stmt->bindValue(':contenido', $content); // El frontend escapa al mostrar

            if ($stmt->execute()) {
                $guiaId = (int) $db->lastInsertId();
                sendResponse(array("success" => true, "id" => $guiaId, "guide" => array("id" => $guiaId)));
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
        $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        $guia = $stmtCheck->fetch();

        if (!$guia) {
            sendError('Guía no encontrada', 404);
        }

        if ((int) $guia['usuario_id'] !== (int) $user['id'] && $user['rol'] !== 'admin') {
            sendError('No autorizado', 403);
        }

        $query = "UPDATE guias SET activo = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

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
