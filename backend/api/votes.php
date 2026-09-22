<?php
// CoreTT - votes.php unificado: delega a guides.php?action=vote.
// Se mantiene por compatibilidad; lo recomendado es usar guides.php?action=vote.
require_once __DIR__ . '/../includes/functions.php';
sendCors();
sendSecurityHeaders();
json(['error' => 'Usa POST guides.php?action=vote con {id, vote}'], 410);
