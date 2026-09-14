<?php
/**
 * CoreTT - Validaciones de entrada.
 * Devuelven un array de errores: ['campo' => 'mensaje'].
 */

declare(strict_types=1);

/**
 * Valida nombre de usuario.
 */
function validate_name(string $name): array
{
    $errors = [];
    $len = mb_strlen($name);
    if ($len < 3 || $len > 50) {
        $errors['name'] = 'El nombre debe tener entre 3 y 50 caracteres.';
    }
    return $errors;
}

/**
 * Valida email.
 */
function validate_email(string $email): array
{
    $errors = [];
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email invalido.';
    }
    return $errors;
}

/**
 * Valida password (minimo 8 caracteres con letra y numero).
 */
function validate_password(string $password): array
{
    $errors = [];
    if (mb_strlen($password) < 8) {
        $errors['password'] = 'La contrasena debe tener al menos 8 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'La contrasena debe incluir letras y numeros.';
    }
    return $errors;
}

/**
 * Valida los datos de una guia.
 */
function validate_guide(array $data): array
{
    $errors = [];

    $title = trim_str($data['titulo'] ?? '', 150);
    if ($title === '') {
        $errors['titulo'] = 'El titulo es obligatorio.';
    } elseif (mb_strlen($title) < 5) {
        $errors['titulo'] = 'El titulo debe tener al menos 5 caracteres.';
    }

    if (empty($data['juego_id'])) {
        $errors['juego_id'] = 'Debes seleccionar un juego.';
    }

    $description = trim_str($data['descripcion'] ?? '', 500);
    if ($description !== '') {
        $data['descripcion'] = $description;
    }

    $content = trim_text($data['contenido'] ?? '');
    if ($content === '') {
        $errors['contenido'] = 'El contenido es obligatorio.';
    } elseif (mb_strlen($content) < 20) {
        $errors['contenido'] = 'El contenido debe tener al menos 20 caracteres.';
    }

    return $errors;
}

/**
 * Valida los datos de un post del foro.
 */
function validate_post(array $data): array
{
    $errors = [];

    $title = trim_str($data['titulo'] ?? '', 150);
    if ($title === '') {
        $errors['titulo'] = 'El titulo es obligatorio.';
    } elseif (mb_strlen($title) < 5) {
        $errors['titulo'] = 'El titulo debe tener al menos 5 caracteres.';
    }

    $content = trim_text($data['contenido'] ?? '');
    if ($content === '') {
        $errors['contenido'] = 'El contenido es obligatorio.';
    }

    return $errors;
}

/**
 * Valida un comentario.
 */
function validate_comment(string $text): array
{
    $errors = [];
    $text = trim($text);
    if ($text === '') {
        $errors['texto'] = 'El comentario no puede estar vacio.';
    } elseif (mb_strlen($text) > 2000) {
        $errors['texto'] = 'El comentario no puede superar los 2000 caracteres.';
    }
    return $errors;
}

/**
 * Junta varios arrays de errores en uno solo y responde si hay alguno.
 */
function bail_on_errors(array $errors): void
{
    if ($errors) {
        error_response('Datos invalidos', 422, $errors);
    }
}