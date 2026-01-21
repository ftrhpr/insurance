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
        $rawParts = json_decode($invoice['repair_parts'], true) ?? [];
        // Transform parts to app format
        $parts = array_map(function($part) {
            return [
                'name' => $part['name'] ?? $part['name_en'] ?? 'Unknown Part',
                'nameKa' => $part['name'] ?? $part['name_en'] ?? 'უცნობი ნაწილი',
                'partNumber' => $part['part_number'] ?? $part['partNumber'] ?? '',
                'quantity' => intval($part['quantity'] ?? 1),
                'unitPrice' => floatval($part['unit_price'] ?? $part['unitPrice'] ?? 0),
                'totalPrice' => floatval($part['total_price'] ?? $part['totalPrice'] ?? 0),
                'notes' => $part['notes'] ?? '',
            ];
        }, $rawParts);
    }

    // Transform repair_labor back to app format
    $services = array_map(function($labor) {
        $laborName = $labor['name'] ?? $labor['description'] ?? 'Unknown Service';
        return [
            'serviceName' => $laborName,
            'serviceNameKa' => $laborName,
            'name' => $laborName,
            'nameKa' => $laborName,
            'price' => floatval($labor['price'] ?? $labor['rate'] ?? $labor['hourly_rate'] ?? 0),
            'count' => intval($labor['hours'] ?? $labor['count'] ?? 1),
            'discount_percent' => floatval($labor['discount_percent'] ?? 0),
            'discountedPrice' => floatval($labor['discounted_price'] ?? $labor['price'] ?? 0),
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
        'services_discount_percent' => floatval($invoice['services_discount_percent'] ?? 0),
        'parts_discount_percent' => floatval($invoice['parts_discount_percent'] ?? 0),
        'global_discount_percent' => floatval($invoice['global_discount_percent'] ?? 0),
        'includeVAT' => intval($invoice['vat_enabled'] ?? 0),
        'vatAmount' => floatval($invoice['vat_amount'] ?? 0),
        'vatRate' => floatval($invoice['vat_rate'] ?? 0),
        'subtotalBeforeVAT' => floatval($invoice['subtotal_before_vat'] ?? 0),
        'serviceDate' => $invoice['serviceDate'] ?? $invoice['service_date'] ?? null,
        'updatedAt' => $invoice['updatedAt'] ?? null,
        'internalNotes' => !empty($invoice['internalNotes']) ? json_decode($invoice['internalNotes'], true) : [],
        'caseType' => $invoice['case_type'] ?? null,
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
