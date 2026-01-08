<?php
define('API_ACCESS', true);
require_once 'config.php';

// Verify API key
verifyAPIKey();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Method not allowed', 405);
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, null, 'Invalid JSON data', 400);
    }
    
    // Validate required fields
    $requiredFields = ['customerPhone', 'totalPrice']; // Minimum required
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            sendResponse(false, null, "Missing required field: $field", 400);
        }
    }
    
    // Get database connection
    $pdo = getDBConnection();
    
    // Prepare INSERT query - Mapped to your actual database structure
    $sql = "INSERT INTO transfers (
        plate,
        name,
        phone,
        amount,
        status,
        parts,
        serviceDate,
        service_date,
        repair_status,
        user_response,
        operatorComment,
        systemLogs
    ) VALUES (
        :plate,
        :name,
        :phone,
        :amount,
        :status,
        :parts,
        :serviceDate,
        :service_date,
        :repair_status,
        :user_response,
        :operatorComment,
        :systemLogs
    )";
    
    $stmt = $pdo->prepare($sql);
    
    // Prepare system logs - keep clear, only store Firebase ID if needed
    $systemLogsData = [
        'firebase_id' => $data['firebaseId'] ?? null,
    ];
    $systemLogsJson = json_encode($systemLogsData, JSON_UNESCAPED_UNICODE);
    
    // Prepare parts JSON if exists
    $partsJson = null;
    if (isset($data['parts']) && !empty($data['parts'])) {
        $partsJson = json_encode($data['parts'], JSON_UNESCAPED_UNICODE);
    }
    
    // Set service dates (both serviceDate and service_date columns)
    $serviceDate = date('Y-m-d H:i:s');
    if (isset($data['serviceDate'])) {
        $serviceDate = date('Y-m-d H:i:s', strtotime($data['serviceDate']));
    } elseif (isset($data['createdAt'])) {
        $serviceDate = date('Y-m-d H:i:s', strtotime($data['createdAt']));
    }
    
    // Bind parameters
    $stmt->execute([
        ':plate' => $data['carModel'] ?? 'Unknown',  // carModel -> plate
        ':name' => $data['customerName'] ?? 'N/A',    // customerName -> name
        ':phone' => $data['customerPhone'] ?? '',     // customerPhone -> phone
        ':amount' => $data['totalPrice'] ?? 0,        // totalPrice -> amount
        ':status' => $data['status'] ?? 'Processing', // Default status
        ':parts' => $partsJson,                       // parts JSON
        ':serviceDate' => $serviceDate,               // Service date (datetime)
        ':service_date' => $serviceDate,              // Service date (datetime) - duplicate column
        ':repair_status' => 'Processing',             // Default repair status - Processing stage
        ':user_response' => 'Pending',                // Default user response
        ':operatorComment' => 'Created from mobile app - Firebase ID: ' . ($data['firebaseId'] ?? 'N/A'),
        ':systemLogs' => $systemLogsJson
    ]);
    
    $insertId = $pdo->lastInsertId();
    
    // Log success
    error_log("Invoice synced successfully. ID: $insertId, Firebase ID: " . ($data['firebaseId'] ?? 'N/A') . ", repair_status: Processing, serviceDate: $serviceDate");
    
    sendResponse(true, [
        'id' => $insertId,
        'message' => 'Invoice synced successfully',
        'firebase_id' => $data['firebaseId'] ?? null,
        'repair_status' => 'Processing',
        'service_date' => $serviceDate
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
