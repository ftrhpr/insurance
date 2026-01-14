<?php
/**
 * Get Invoice API Endpoint
 * Fetches invoice details from cPanel database by ID or Firebase ID
 * Used for syncing updates from cPanel back to mobile app
 */

define('API_ACCESS', true);
require_once 'config.php';

// Verify API key
verifyAPIKey();

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, 'Method not allowed. Use GET request.', 405);
}

try {
    // Get parameters
    $invoiceId = $_GET['invoiceId'] ?? null;
    $firebaseId = $_GET['firebaseId'] ?? null;
    
    if (!$invoiceId && !$firebaseId) {
        sendResponse(false, null, 'Missing required parameter: invoiceId or firebaseId', 400);
    }
    
    // Get database connection
    $pdo = getDBConnection();
    
    // Build query based on provided parameter
    if ($invoiceId) {
        $sql = "SELECT * FROM transfers WHERE id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $invoiceId]);
    } else {
        // Search in operatorComment for Firebase ID
        $sql = "SELECT * FROM transfers WHERE operatorComment LIKE :firebaseId LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':firebaseId' => '%' . $firebaseId . '%']);
    }
    
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        sendResponse(false, null, 'Invoice not found', 404);
    }
    
    // Parse JSON fields
    $repairLabor = [];
    if (!empty($invoice['repair_labor'])) {
        $repairLabor = json_decode($invoice['repair_labor'], true) ?? [];
    }
    
    $parts = [];
    if (!empty($invoice['repair_parts'])) {
        $parts = json_decode($invoice['repair_parts'], true) ?? [];
    }
    
    // Transform repair_labor back to app format
    $services = array_map(function($labor) {
        return [
            'serviceName' => $labor['name'] ?? $labor['description'] ?? 'Unknown Service',
            'price' => floatval($labor['price'] ?? $labor['rate'] ?? $labor['hourly_rate'] ?? 0),
            'count' => intval($labor['hours'] ?? 1),
        ];
    }, $repairLabor);
    
    // Build response in app format
    $responseData = [
        'cpanelId' => $invoice['id'],
        'customerName' => $invoice['name'] ?? '',
        'customerPhone' => $invoice['phone'] ?? '',
        'carModel' => $invoice['plate'] ?? '',
        'totalPrice' => floatval($invoice['amount'] ?? 0),
        'status' => $invoice['status'] ?? 'New',
        'repair_status' => $invoice['repair_status'] ?? null,
        'user_response' => $invoice['user_response'] ?? null,
        'services' => $services,
        'parts' => $parts,
        'serviceDate' => $invoice['serviceDate'] ?? $invoice['service_date'] ?? null,
        'updatedAt' => $invoice['updatedAt'] ?? null,
    ];
    
    error_log("Invoice fetched successfully. ID: " . $invoice['id']);
    
    sendResponse(true, $responseData);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
