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
        'plate' => 'plate',
        'vehicleMake' => 'vehicle_make',
        'vehicleModel' => 'vehicle_model',
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
                    // Transform services to match portal format (same as create-invoice.php)
                    if ($dbField === 'repair_labor') {
                        $transformedServices = array_map(function($service) {
                            // Prefer Georgian name, fallback to English
                            $serviceName = !empty($service['serviceNameKa']) ? $service['serviceNameKa'] :
                                          (!empty($service['nameKa']) ? $service['nameKa'] :
                                          (!empty($service['serviceName']) ? $service['serviceName'] :
                                          (!empty($service['name']) ? $service['name'] : 'Unnamed Labor')));
                            $servicePrice = !empty($service['price']) ? $service['price'] : (!empty($service['hourly_rate']) ? $service['hourly_rate'] : (!empty($service['rate']) ? $service['rate'] : 0));

                            // Preserve service description as notes if available
                            $serviceDescription = !empty($service['description']) ? $service['description'] : '';
                            $serviceNotes = !empty($service['notes']) ? $service['notes'] : '';
                            // Combine description and notes if both exist
                            $combinedNotes = $serviceDescription && $serviceNotes
                                ? "$serviceDescription | $serviceNotes"
                                : ($serviceDescription ?: $serviceNotes);

                            return [
                                'name' => $serviceName,
                                'description' => $serviceDescription,
                                'hours' => !empty($service['hours']) ? $service['hours'] : (!empty($service['count']) ? $service['count'] : 1),
                                'rate' => $servicePrice,
                                'hourly_rate' => $servicePrice,
                                'price' => $servicePrice,
                                'billable' => isset($service['billable']) ? $service['billable'] : true,
                                'notes' => $combinedNotes,
                            ];
                        }, $value);
                        $value = json_encode($transformedServices, JSON_UNESCAPED_UNICODE);
                        error_log("Services transformed for update: " . $value);
                    } else {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
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

    // Check if query executed successfully (not if rows were changed)
    // rowCount() can be 0 if values didn't change, which is still a successful update
    if (!$result) {
        sendResponse(false, null, 'Failed to execute update query', 500);
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
