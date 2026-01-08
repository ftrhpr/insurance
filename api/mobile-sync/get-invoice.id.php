<?php
define('API_ACCESS', true);
require_once 'config.php';

// Verify API key
verifyAPIKey();

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Method not allowed. Use GET request.', 405);
}

try {
    // Get firebase ID from query parameters
    $firebaseId = isset($_GET['firebaseId']) ? $_GET['firebaseId'] : null;
    
    if (!$firebaseId) {
        sendResponse(false, null, 'Missing required parameter: firebaseId', 400);
    }
    
    // Get database connection
    $pdo = getDBConnection();
    
    // Search for invoice by firebase ID stored in operatorComment or systemLogs
    $sql = "SELECT id, operatorComment, systemLogs FROM transfers 
            WHERE operatorComment LIKE :firebaseId 
            OR systemLogs LIKE :firebaseId 
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':firebaseId' => "%$firebaseId%"]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        sendResponse(false, null, 'Invoice not found for the given Firebase ID', 404);
    }
    
    error_log("Found cPanel invoice ID: " . $result['id'] . " for Firebase ID: $firebaseId");
    
    sendResponse(true, [
        'cpanelInvoiceId' => $result['id'],
        'firebaseId' => $firebaseId,
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
