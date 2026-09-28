<?php
/**
 * CoreTT - Validaciones de entrada.
 *
 * Devuelven un array de errores: ['campo' => 'mensaje'].
 *
 * Los limites coinciden con el ancho de las columnas MySQL del esquema, de
 * forma que un valor que pasa la validacion nunca revienta un INSERT con un
 * "Data too long".
 */

declare(strict_types=1);

/* Limites alineados con backend/db/schema.sql */
const MAX_NOMBRE = 50;
const MAX_EMAIL = 190;
const MIN_PASSWORD = 8;
const MAX_PASSWORD = 200;
const MAX_TITULO = 150;
const MAX_DESCRIPCION = 500;
const MAX_GUIA_CONTENIDO = 50000;
const MAX_POST_CONTENIDO = 20000;
const MAX_COMENTARIO = 2000;
const MAX_RANGO = 50;
const MAX_BUSQUEDA = 100;

/**
 * Valida nombre de usuario.
 */
function validate_name(string $name): array
{
    $errors = [];
    $len = mb_strlen($name);

    if ($len < 3 || $len > MAX_NOMBRE) {
        $errors['name'] = 'El nombre debe tener entre 3 y 50 caracteres.';
        return $errors;
    }

    // Solo letras, digitos, espacio, guion y guion bajo. Evita nombres con
    // etiquetas HTML, caracteres de control o separadores de linea.
    if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._-]*$/u', $name)) {
        $errors['name'] = 'El nombre solo admite letras, numeros, espacios, punto, guion y guion bajo.';
    }

    if (preg_match('/\s{2,}/u', $name)) {
        $errors['name'] = 'El nombre no puede tener espacios consecutivos.';
    }

    return $errors;
}

/**
 * Valida email.
 */
function validate_email(string $email): array
{
    $errors = [];

    if ($email === '' || mb_strlen($email) > MAX_EMAIL) {
        $errors['email'] = 'Email invalido.';
        return $errors;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    $len = mb_strlen($password);

    if ($len < MIN_PASSWORD) {
        $errors['password'] = 'La contrasena debe tener al menos 8 caracteres.';
        return $errors;
    }

    // bcrypt solo considera los primeros 72 bytes: sin tope, dos contrasenas
    // que difieren al final serian equivalentes.
    if ($len > MAX_PASSWORD) {
        $errors['password'] = 'La contrasena no puede superar los ' . MAX_PASSWORD . ' caracteres.';
        return $errors;
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'La contrasena debe incluir letras y numeros.';
    }

    return $errors;
}

/**
 * Normaliza y valida los datos de una guia.
 *
 * Devuelve ['datos' => [...], 'errores' => [...]] con los valores ya
 * recortados a los limites de columna. Importante: los valores saneados se
 * devuelven para que el llamante los inserte, no solo se validan.
 */
function sanitize_guide(array $data): array
{
    $errors = [];

    $titulo      = trim_str($data['titulo'] ?? '', MAX_TITULO);
    $descripcion = trim_str($data['descripcion'] ?? '', MAX_DESCRIPCION);
    $contenido   = trim_text($data['contenido'] ?? '', MAX_GUIA_CONTENIDO);

    if ($titulo === '') {
        $errors['titulo'] = 'El titulo es obligatorio.';
    } elseif (mb_strlen($titulo) < 5) {
        $errors['titulo'] = 'El titulo debe tener al menos 5 caracteres.';
    } elseif (mb_strlen($data['titulo'] ?? '') > MAX_TITULO) {
        // Recortar en silencio perderia datos del usuario sin avisar.
        $errors['titulo'] = 'El titulo no puede superar los ' . MAX_TITULO . ' caracteres.';
    }

    if (empty($data['juego_id'])) {
        $errors['juego_id'] = 'Debes seleccionar un juego.';
    }

    if ($contenido === '') {
        $errors['contenido'] = 'El contenido es obligatorio.';
    } elseif (mb_strlen($contenido) < 20) {
        $errors['contenido'] = 'El contenido debe tener al menos 20 caracteres.';
    } elseif (mb_strlen($data['contenido'] ?? '') > MAX_GUIA_CONTENIDO) {
        $errors['contenido'] = 'El contenido no puede superar los ' . MAX_GUIA_CONTENIDO . ' caracteres.';
    }

    if (mb_strlen($data['descripcion'] ?? '') > MAX_DESCRIPCION) {
        $errors['descripcion'] = 'La descripcion no puede superar los ' . MAX_DESCRIPCION . ' caracteres.';
    }

    return [
        'errores' => $errors,
        'datos'   => [
            'titulo'      => $titulo,
            'descripcion' => $descripcion,
            'contenido'   => $contenido,
            'juego_id'    => $data['juego_id'] ?? null,
        ],
    ];
}

/**
 * Normaliza y valida los datos de un post del foro.
 *
 * Igual que sanitize_guide(): devuelve los datos saneados para insertar.
 */
function sanitize_post(array $data): array
{
    $errors = [];

    $titulo    = trim_str($data['titulo'] ?? '', MAX_TITULO);
    $contenido = trim_text($data['contenido'] ?? '', MAX_POST_CONTENIDO);

    if ($titulo === '') {
        $errors['titulo'] = 'El titulo es obligatorio.';
    } elseif (mb_strlen($titulo) < 5) {
        $errors['titulo'] = 'El titulo debe tener al menos 5 caracteres.';
    } elseif (mb_strlen($data['titulo'] ?? '') > MAX_TITULO) {
        $errors['titulo'] = 'El titulo no puede superar los ' . MAX_TITULO . ' caracteres.';
    }

    if ($contenido === '') {
        $errors['contenido'] = 'El contenido es obligatorio.';
    } elseif (mb_strlen($data['contenido'] ?? '') > MAX_POST_CONTENIDO) {
        $errors['contenido'] = 'El contenido no puede superar los ' . MAX_POST_CONTENIDO . ' caracteres.';
    }

    return [
        'errores' => $errors,
        'datos'   => [
            'titulo'    => $titulo,
            'contenido' => $contenido,
        ],
    ];
}

/**
 * Valida un comentario ya recortado por el llamante.
 */
function validate_comment(string $text): array
{
    $errors = [];
    if ($text === '') {
        $errors['texto'] = 'El comentario no puede estar vacio.';
    } elseif (mb_strlen($text) > MAX_COMENTARIO) {
        $errors['texto'] = 'El comentario no puede superar los ' . MAX_COMENTARIO . ' caracteres.';
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
