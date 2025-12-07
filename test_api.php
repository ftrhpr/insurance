<?php
header("Content-Type: application/json");
echo json_encode([
    'status' => 'ok',
    'message' => 'API is working',
    'php_version' => PHP_VERSION,
    'time' => date('Y-m-d H:i:s')
]);
