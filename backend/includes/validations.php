<?php
/**
 * Validaciones CoreTT
 */

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    // Mínimo 4 caracteres
    return strlen($password) >= 4;
}

function validateUsername($username) {
    // Solo letras, números, guiones y guiones bajos, 3-50 caracteres
    return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username);
}

function validateRequired($data, $fields) {
    $errors = array();
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $errors[] = "El campo $field es requerido";
        }
    }
    return $errors;
}
?>
