<?php
// CoreTT - Front controller mínimo / healthcheck
require_once __DIR__ . '/includes/functions.php';
sendCors();
sendSecurityHeaders();

json(['status' => 'ok', 'service' => 'corett-api', 'version' => 1]);
