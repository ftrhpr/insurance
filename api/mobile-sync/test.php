<?php
define('API_ACCESS', true);
require_once 'config.php';

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
        'message' => 'API is working!',
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
