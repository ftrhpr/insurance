<?php

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$response = [
    'status' => 'ok',
    'message' => 'API is working',
    'php_version' => PHP_VERSION,
    'time' => date('Y-m-d H:i:s')
];

try {
    require_once 'config.php';
    $pdo = getDBConnection();
    $response['db'] = 'ok';
    $stmt = $pdo->query('SHOW TABLES');
    $response['tables'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $response['db'] = 'error';
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
