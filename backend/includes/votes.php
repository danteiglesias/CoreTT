<?php
/**
 * CoreTT - Logica de votacion.
 * Aplica un voto a una guia con unicidad por usuario y toggle.
 */

declare(strict_types=1);

/**
 * Aplica un voto con toggle: mismo valor lo anula, distinto lo cambia.
 * Devuelve ['success'=>true,'votes'=>int,'myVote'=>int].
 */
function apply_vote(int $guideId, int $userId, int $valor): array
{
    if ($valor !== 1 && $valor !== -1) {
        error_response('Voto invalido', 422);
    }

    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, valor FROM votos WHERE guia_id = :g AND usuario_id = :u LIMIT 1');
    $stmt->execute([':g' => $guideId, ':u' => $userId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $stmt = $pdo->prepare('INSERT INTO votos (guia_id, usuario_id, valor) VALUES (:g, :u, :v)');
        $stmt->execute([':g' => $guideId, ':u' => $userId, ':v' => $valor]);
        $myVote = $valor;
    } elseif ((int)$existing['valor'] === $valor) {
        $stmt = $pdo->prepare('DELETE FROM votos WHERE id = :id');
        $stmt->execute([':id' => $existing['id']]);
        $myVote = 0;
    } else {
        $stmt = $pdo->prepare('UPDATE votos SET valor = :v WHERE id = :id');
        $stmt->execute([':v' => $valor, ':id' => $existing['id']]);
        $myVote = $valor;
    }

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(valor), 0) AS votos FROM votos WHERE guia_id = :g');
    $stmt->execute([':g' => $guideId]);
    $votes = (int)$stmt->fetch()['votos'];

    return ['success' => true, 'votes' => $votes, 'myVote' => $myVote];
}