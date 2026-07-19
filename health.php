<?php
/**
 * Lightweight health check — no DB required.
 * Use for uptime pings: https://care-connect-sl-1.onrender.com/health.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
http_response_code(200);
echo json_encode([
    'ok' => true,
    'service' => 'care-connect-sl',
    'time' => gmdate('c'),
]);
