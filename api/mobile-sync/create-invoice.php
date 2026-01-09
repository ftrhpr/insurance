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
    // Note: services/labors are stored as JSON in repair_labor column
    $sql = "INSERT INTO transfers (
        plate,
        name,
        phone,
        amount,
        status,
        parts,
        repair_labor,
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
        :repair_labor,
        :serviceDate,
        :service_date,
        :repair_status,
        :user_response,
        :operatorComment,
        :systemLogs
    )";
    
    $stmt = $pdo->prepare($sql);
    
    // Prepare system logs - keep empty/null
    $systemLogsJson = null;
    
    // Prepare parts JSON if exists
    $partsJson = null;
    if (isset($data['parts']) && !empty($data['parts'])) {
        $partsJson = json_encode($data['parts'], JSON_UNESCAPED_UNICODE);
    }
    
    // Prepare services/labors JSON from the services array
    // App sends: [{"serviceName":"Plastic Restoration","serviceNameKa":"პლასტმასის აღდგენა","price":75,"count":1}]
    // Convert to database format expected by portal - prefer Georgian names
    $servicesJson = null;
    if (isset($data['services']) && !empty($data['services'])) {
        $services = $data['services'];
        error_log("Raw services received: " . json_encode($services));
        // Transform field names to match portal expectations - prefer Georgian (nameKa) names
        $transformedServices = array_map(function($service) {
            // Prefer Georgian name, fallback to English, then description
            $serviceName = !empty($service['serviceNameKa']) ? $service['serviceNameKa'] : 
                          (!empty($service['nameKa']) ? $service['nameKa'] : 
                          (!empty($service['serviceName']) ? $service['serviceName'] : 
                          (!empty($service['name']) ? $service['name'] : 
                          (!empty($service['description']) ? $service['description'] : 'Unnamed Labor'))));
            $servicePrice = !empty($service['price']) ? $service['price'] : (!empty($service['hourly_rate']) ? $service['hourly_rate'] : (!empty($service['rate']) ? $service['rate'] : 0));
            
            return [
                'name' => $serviceName,
                'description' => $serviceName,
                'hours' => !empty($service['hours']) ? $service['hours'] : (!empty($service['count']) ? $service['count'] : 1),
                'rate' => $servicePrice,
                'hourly_rate' => $servicePrice,
                'price' => $servicePrice,
                'billable' => isset($service['billable']) ? $service['billable'] : true,
                'notes' => !empty($service['notes']) ? $service['notes'] : '',
            ];
        }, $services);
        $servicesJson = json_encode($transformedServices, JSON_UNESCAPED_UNICODE);
        error_log("Services transformed: " . $servicesJson);
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
        ':status' => 'Processing',                    // Default status - Processing
        ':parts' => $partsJson,                       // parts JSON (damage tags)
        ':repair_labor' => $servicesJson,             // repair_labor JSON (services with hours and hourly_rate)
        ':serviceDate' => $serviceDate,               // Service date (datetime)
        ':service_date' => $serviceDate,              // Service date (datetime) - duplicate column
        ':repair_status' => null,                     // Leave repair_status NULL
        ':user_response' => 'Processing',             // Default user response - Processing
        ':operatorComment' => 'Created from mobile app - Firebase ID: ' . ($data['firebaseId'] ?? 'N/A'),
        ':systemLogs' => $systemLogsJson
    ]);
    
    $insertId = $pdo->lastInsertId();
    
    // Log success with services info
    $servicesCount = $servicesJson ? count(json_decode($servicesJson, true)) : 0;
    if ($servicesJson) {
        $servicesData = json_decode($servicesJson, true);
        error_log("Invoice synced successfully. ID: $insertId, Firebase ID: " . ($data['firebaseId'] ?? 'N/A') . ", Services: $servicesCount, Data: " . json_encode($servicesData));
    } else {
        error_log("Invoice synced successfully. ID: $insertId, Firebase ID: " . ($data['firebaseId'] ?? 'N/A') . ", Services: 0");
    }
    
    sendResponse(true, [
        'id' => $insertId,
        'message' => 'Invoice synced successfully',
        'firebase_id' => $data['firebaseId'] ?? null,
        'status' => 'Processing',
        'service_date' => $serviceDate,
        'services_count' => $servicesCount,
        'services_synced' => $servicesJson ? json_decode($servicesJson, true) : []
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
