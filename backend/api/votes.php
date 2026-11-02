<?php
/**
 * API de Votos - CoreTT
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../includes/functions.php';
require_once '../includes/auth.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user = requireAuth();
    $guiaId = isset($_GET['guia_id']) ? intval($_GET['guia_id']) : 0;
    
    if ($guiaId) {
        $query = "SELECT tipo_voto FROM votos_guias WHERE guia_id = :guia_id AND usuario_id = :usuario_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':guia_id', $guiaId);
        $stmt->bindParam(':usuario_id', $user['id']);
        $stmt->execute();
        $vote = $stmt->fetch();
        
        sendResponse(array("vote" => $vote ? $vote['tipo_voto'] : 0));
    } else {
        sendResponse(array("votes" => array()));
    }
} else {
    sendError('Método no permitido', 405);
}
?>
