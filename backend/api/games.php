<?php
// CoreTT - GET games.php -> string[]
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
sendCors();
sendSecurityHeaders();

try {
    $rows = getDB()->query('SELECT name FROM games ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    json(array_values($rows));
} catch (Throwable $e) {
    json(['error' => 'Error interno'], 500);
}
