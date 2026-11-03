<?php
/**
 * Autenticación CoreTT
 */

require_once __DIR__ . '/functions.php';

// Verificar autenticación requerida
function requireAuth() {
    $user = getUserFromToken();
    if (!$user) {
        sendError('No autorizado. Se requiere autenticación.', 401);
    }
    return $user;
}

// Verificar rol admin
function requireAdmin() {
    $user = requireAuth();
    if ($user['rol'] !== 'admin') {
        sendError('No autorizado. Se requieren permisos de administrador.', 403);
    }
    return $user;
}

// Verificar rol moderador o admin
function requireModerator() {
    $user = requireAuth();
    if ($user['rol'] !== 'admin' && $user['rol'] !== 'moderador') {
        sendError('No autorizado. Se requieren permisos de moderador.', 403);
    }
    return $user;
}
