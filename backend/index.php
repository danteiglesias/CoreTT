<?php
/**
 * CoreTT - Backend API Index
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'config/database.php';

// Respuesta básica
echo json_encode(array(
    "mensaje" => "CoreTT API",
    "version" => "1.0.0",
    "estado" => "activo",
    "endpoints" => array(
        "usuarios" => "api/users.php",
        "guias" => "api/guides.php",
        "foro" => "api/forum.php",
        "juegos" => "api/games.php",
        "votos" => "api/votes.php"
    )
));
?>
