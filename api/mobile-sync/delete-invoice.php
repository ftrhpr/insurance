<?php
define('API_ACCESS', true);
require_once 'config.php';

// Verify API key
verifyAPIKey();

// Only accept DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendResponse(false, null, 'Method not allowed. Use DELETE request.', 405);
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, null, 'Invalid JSON data', 400);
    }
    
    // Validate required fields
    if (!isset($data['invoiceId'])) {
        sendResponse(false, null, 'Missing required field: invoiceId', 400);
    }
    
    // Get database connection
    $pdo = getDBConnection();
    
    $invoiceId = $data['invoiceId'];
    
    // Check if invoice exists
    $checkSql = "SELECT id FROM transfers WHERE id = :id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $invoiceId]);
    
    if ($checkStmt->rowCount() === 0) {
        sendResponse(false, null, 'Invoice not found', 404);
    }
    
    // Delete the invoice
    $sql = "DELETE FROM transfers WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $invoiceId]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(false, null, 'Failed to delete invoice', 500);
    }
    
    error_log("Invoice deleted successfully. ID: $invoiceId");
    
    sendResponse(true, [
        'id' => $invoiceId,
        'message' => 'Invoice deleted successfully',
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
