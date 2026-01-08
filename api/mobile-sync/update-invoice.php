<?php
define('API_ACCESS', true);
require_once 'config.php';

// Verify API key
verifyAPIKey();

// Only accept PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendResponse(false, null, 'Method not allowed. Use PUT request.', 405);
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
    
    // Build dynamic UPDATE query based on provided fields
    $updateFields = [];
    $bindParams = [':id' => $invoiceId];
    
    $fieldMapping = [
        'customerName' => 'name',
        'customerPhone' => 'phone',
        'carModel' => 'plate',
        'totalPrice' => 'amount',
        'status' => 'status',
        'repair_status' => 'repair_status',
        'user_response' => 'user_response',
        'services' => 'repair_labor',
        'parts' => 'parts',
    ];
    
    foreach ($fieldMapping as $appField => $dbField) {
        if (isset($data[$appField]) && $data[$appField] !== null) {
            $value = $data[$appField];
            
            // Handle JSON fields
            if (in_array($dbField, ['repair_labor', 'parts'])) {
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
            }
            
            $updateFields[] = "$dbField = :$appField";
            $bindParams[":$appField"] = $value;
        }
    }
    
    // If no fields to update, return error
    if (empty($updateFields)) {
        sendResponse(false, null, 'No fields to update', 400);
    }
    
    // Build and execute UPDATE query
    $sql = "UPDATE transfers SET " . implode(", ", $updateFields) . " WHERE id = :id";
    error_log("Update query: $sql with params: " . json_encode($bindParams));
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($bindParams);
    
    if (!$result || $stmt->rowCount() === 0) {
        sendResponse(false, null, 'Failed to update invoice', 500);
    }
    
    // Fetch updated invoice data to return
    $selectSql = "SELECT * FROM transfers WHERE id = :id LIMIT 1";
    $selectStmt = $pdo->prepare($selectSql);
    $selectStmt->execute([':id' => $invoiceId]);
    $updatedInvoice = $selectStmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Invoice updated successfully. ID: $invoiceId");
    
    sendResponse(true, [
        'id' => $invoiceId,
        'message' => 'Invoice updated successfully',
        'data' => $updatedInvoice,
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
