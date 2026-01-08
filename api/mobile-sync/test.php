<?php
define('API_ACCESS', true);
require_once 'config.php';

// Accept both GET and POST for testing
$allowedMethods = ['GET', 'POST', 'OPTIONS'];
if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Debug: Log the request before verification
error_log("=== TEST.PHP REQUEST DEBUG ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Headers received: " . json_encode(getallheaders()));
error_log("HTTP_X_API_KEY: " . ($_SERVER['HTTP_X_API_KEY'] ?? 'NOT SET'));

// Verify API key
verifyAPIKey();

try {
    $pdo = getDBConnection();
    
    // Test database connection
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transfers");
    $result = $stmt->fetch();
    
    // Get database info
    $dbInfo = $pdo->query("SELECT DATABASE() as db_name")->fetch();
    
    // Get table columns info
    $columnsStmt = $pdo->query("DESCRIBE transfers");
    $columns = $columnsStmt->fetchAll();
    
    sendResponse(true, [
        'message' => '✅ API is working!',
        'database_connected' => true,
        'database_name' => $dbInfo['db_name'],
        'transfers_count' => $result['count'],
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'php_version' => phpversion(),
        'table_columns' => count($columns),
        'columns_list' => array_column($columns, 'Field')
    ]);
    
} catch (Exception $e) {
    sendResponse(false, null, 'Connection test failed: ' . $e->getMessage(), 500);
}
?>
