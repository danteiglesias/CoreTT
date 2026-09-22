<?php
// CoreTT - Validaciones mínimas de servidor
function validEmail(string $email): bool {
    return filter_var(strtolower(trim($email)), FILTER_VALIDATE_EMAIL) !== false;
}

function validPassword(string $pass): bool {
    return mb_strlen($pass) >= 8;
}

function validId($id): bool {
    return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
}

function validGame(string $game, array $allowed): bool {
    return in_array($game, $allowed, true);
}

function validText(string $text, int $min, int $max): bool {
    $len = mb_strlen(trim($text));
    return $len >= $min && $len <= $max;
}
