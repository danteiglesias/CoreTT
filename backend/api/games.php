<?php
/**
 * API de Juegos - CoreTT
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
} catch (Throwable $e) {
    sendError('No se pudo conectar a la base de datos. Revisa el archivo .env.', 500);
}
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $query = "SELECT id, nombre, slug, descripcion, imagen FROM juegos WHERE activo = 1 ORDER BY id ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $juegos = $stmt->fetchAll();
    
    // Devolver solo nombres para compatibilidad con frontend
    $result = array();
    foreach ($juegos as $juego) {
        $result[] = $juego['nombre'];
    }
    
    sendResponse($result);
} else {
    sendError('Método no permitido', 405);
}
?>
