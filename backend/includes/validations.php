<?php
/**
 * Validaciones CoreTT
 */

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    // Mínimo 4 caracteres (cuentas de prueba usan "password")
    return is_string($password) && strlen($password) >= 4;
}

function validateUsername($username) {
    // Letras (incluidas acentos/ñ), números, espacios, guiones y guiones bajos.
    // 3-50 caracteres.
    return is_string($username)
        && preg_match('/^[\p{L}\p{N} _.\-]{3,50}$/u', $username) === 1;
}

function validateRequired($data, $fields) {
    $errors = array();
    foreach ($fields as $field) {
        if (!isset($data[$field]) || !is_scalar($data[$field]) || trim((string) $data[$field]) === '') {
            $errors[] = "El campo $field es requerido";
        }
    }
    return $errors;
}
