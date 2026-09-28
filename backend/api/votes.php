<?php
/**
 * CoreTT - API de Votos.
 * Endpoint dedicado; el flujo principal tambien esta disponible como
 * POST /backend/api/guides.php?action=vote.
 *
 * GET  /backend/api/votes.php?guide={id}       -> conteo y voto del usuario
 * POST /backend/api/votes.php?guide={id}       -> votar (body: {vote: 1|-1}, auth)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/boot.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = get_json_body();
$me     = current_user();
$pdo    = db();

$guideId = param_int('guide', 0, array_merge($_GET, $body));
if ($guideId <= 0) {
    $guideId = param_int('id', 0, array_merge($_GET, $body));
}
if ($guideId <= 0) {
    error_response('Parametro guide requerido', 422);
}

$stmt = $pdo->prepare('SELECT id FROM guias WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $guideId]);
if (!$stmt->fetch()) {
    error_response('Guia no encontrada', 404);
}

switch ($method) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(valor), 0) AS votos FROM votos WHERE guia_id = :g');
        $stmt->execute([':g' => $guideId]);
        $votes = (int)$stmt->fetch()['votos'];

        $myVote = 0;
        if ($me) {
            $stmt = $pdo->prepare('SELECT valor FROM votos WHERE guia_id = :g AND usuario_id = :u LIMIT 1');
            $stmt->execute([':g' => $guideId, ':u' => $me['id']]);
            $row = $stmt->fetch();
            $myVote = $row ? (int)$row['valor'] : 0;
        }

        json_response(['guide' => $guideId, 'votes' => $votes, 'myVote' => $myVote]);
        break;

    case 'POST':
        require_auth();
        throttle('vote', (int)env('VOTE_MAX_PER_WINDOW', 300), (int)env('WRITE_WINDOW_SECONDS', 3600));
        $vote = (int)body_str($body, 'vote', '0');
        json_response(apply_vote($guideId, (int)$me['id'], $vote));
        break;

    default:
        error_response('Metodo no soportado', 405);
}