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

    // La guia debe existir y estar publicada: sin esta comprobacion un id
    // inexistente terminaba en un error de clave foranea (HTTP 500).
    $stmt = $pdo->prepare('SELECT id, usuario_id FROM guias WHERE id = :id AND estado = :estado LIMIT 1');
    $stmt->execute([':id' => $guideId, ':estado' => 'publicada']);
    $guide = $stmt->fetch();
    if (!$guide) {
        error_response('Guia no encontrada', 404);
    }

    // Votar la propia guia inflaria la reputacion del autor sin supervision.
    if ((int)$guide['usuario_id'] === $userId) {
        error_response('No puedes votar tu propia guia', 422);
    }

    $pdo->beginTransaction();
    try {
        // SELECT ... FOR UPDATE serializa los votos concurrentes del mismo
        // usuario y guia: sin el, dos peticiones a la vez chocan con la
        // clave unica (guia_id, usuario_id) y devuelven 500.
        $stmt = $pdo->prepare(
            'SELECT id, valor FROM votos
              WHERE guia_id = :g AND usuario_id = :u LIMIT 1 FOR UPDATE'
        );
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

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['success' => true, 'votes' => $votes, 'myVote' => $myVote];
}
