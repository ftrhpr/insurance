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
        'parts' => 'repair_parts',
        'images' => 'case_images',
        'photos' => 'case_images',
        'imageUrls' => 'case_images',
        'photoUrls' => 'case_images',
        'caseImages' => 'case_images',
        'vehicleImages' => 'case_images',
        'damageImages' => 'case_images',
        'attachments' => 'case_images',
        'services_discount_percent' => 'services_discount_percent',
        'parts_discount_percent' => 'parts_discount_percent',
        'global_discount_percent' => 'global_discount_percent',
        'includeVAT' => 'vat_enabled',
        'vatAmount' => 'vat_amount',
        'vatRate' => 'vat_rate',
        'subtotalBeforeVAT' => 'subtotal_before_vat',
        'internalNotes' => 'internalNotes',
    ];
    
    // Debug: Log all received data keys and image fields
    error_log("=== UPDATE INVOICE - DATA RECEIVED ===");
    error_log("All keys: " . implode(', ', array_keys($data)));
    error_log("VAT fields - includeVAT: " . ($data['includeVAT'] ?? 'NOT_SET') . ", vatAmount: " . ($data['vatAmount'] ?? 'NOT_SET') . ", vatRate: " . ($data['vatRate'] ?? 'NOT_SET') . ", subtotalBeforeVAT: " . ($data['subtotalBeforeVAT'] ?? 'NOT_SET'));
    error_log("Received data - vehicleMake: " . ($data['vehicleMake'] ?? 'NULL') . ", vehicleModel: " . ($data['vehicleModel'] ?? 'NULL'));
    
    // Check for image fields
    $imageFieldsToCheck = ['images', 'photos', 'imageUrls', 'photoUrls', 'caseImages', 'vehicleImages', 'damageImages', 'attachments'];
    foreach ($imageFieldsToCheck as $imgField) {
        if (isset($data[$imgField])) {
            error_log("Found images in '$imgField': " . (is_array($data[$imgField]) ? count($data[$imgField]) . " items" : gettype($data[$imgField])));
        }
    }
    
    // Track which DB columns have been processed to avoid duplicates
    $processedDbFields = [];
    
    foreach ($fieldMapping as $appField => $dbField) {
        // Skip if this DB column was already processed (handles multiple image field names mapping to same column)
        if (in_array($dbField, $processedDbFields)) {
            continue;
        }
        
        // Special handling for fields that can be null or need special processing
        $shouldProcess = false;
        if (in_array($dbField, ['vat_enabled', 'vat_amount', 'vat_rate', 'subtotal_before_vat'])) {
            $shouldProcess = array_key_exists($appField, $data);
        } elseif (in_array($dbField, ['repair_status', 'user_response', 'status'])) {
            // These fields should be processed even if they are null (to allow clearing)
            $shouldProcess = array_key_exists($appField, $data);
        } else {
            $shouldProcess = isset($data[$appField]) && $data[$appField] !== null;
        }
        
        if ($shouldProcess) {
            $value = $data[$appField] ?? null;
            
            // Debug VAT fields specifically
            if (in_array($dbField, ['vat_enabled', 'vat_amount', 'vat_rate', 'subtotal_before_vat'])) {
                error_log("Processing VAT field $appField -> $dbField with value: " . json_encode($value) . " (type: " . gettype($value) . ")");
            }
            
            // Handle discount and VAT fields as floats - allow empty strings to be 0
            if (in_array($dbField, ['services_discount_percent', 'parts_discount_percent', 'global_discount_percent', 'vat_amount', 'vat_rate', 'subtotal_before_vat'])) {
                $value = floatval($value ?? 0);
                if (in_array($dbField, ['vat_amount', 'vat_rate', 'subtotal_before_vat'])) {
                    error_log("Converted VAT field $dbField to float: $value");
                }
            }
            // Handle boolean VAT enabled field
            elseif ($dbField === 'vat_enabled') {
                // Handle both boolean and string values
                if (is_bool($value)) {
                    $value = $value ? 1 : 0;
                } elseif (is_string($value)) {
                    $value = ($value === 'true' || $value === '1') ? 1 : 0;
                } else {
                    $value = intval($value ?? 0);
                }
                error_log("Converted VAT enabled to int: $value (from " . json_encode($data[$appField]) . ")");
            }
            // Handle internalNotes as JSON array
            elseif ($dbField === 'internalNotes' && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                error_log("Internal notes transformed for update: " . $value);
            }
            // Handle JSON fields for arrays
            elseif (is_array($value) && in_array($dbField, ['repair_labor', 'repair_parts', 'case_images'])) {
                // Transform services to match portal format (same as create-invoice.php)
                if ($dbField === 'repair_labor') {
                    error_log("Processing services for update - count: " . count($value));
                    error_log("Raw services data: " . json_encode($value, JSON_UNESCAPED_UNICODE));
                    $transformedServices = array_map(function($service) {
                        // Log incoming service data for debugging
                        error_log("Processing service: " . json_encode($service, JSON_UNESCAPED_UNICODE));

                        // Prefer Georgian name, fallback to English, with better empty string handling
                        $serviceName = '';
                        
                        // Check each field, ensuring non-empty
                        if (!empty($service['serviceNameKa']) && trim($service['serviceNameKa']) !== '') {
                            $serviceName = trim($service['serviceNameKa']);
                            error_log("Using serviceNameKa: " . $serviceName);
                        } elseif (!empty($service['nameKa']) && trim($service['nameKa']) !== '') {
                            $serviceName = trim($service['nameKa']);
                            error_log("Using nameKa: " . $serviceName);
                        } elseif (!empty($service['serviceName']) && trim($service['serviceName']) !== '') {
                            $serviceName = trim($service['serviceName']);
                            error_log("Using serviceName: " . $serviceName);
                        } elseif (!empty($service['name']) && trim($service['name']) !== '') {
                            $serviceName = trim($service['name']);
                            error_log("Using name: " . $serviceName);
                        } elseif (!empty($service['description']) && trim($service['description']) !== '') {
                            $serviceName = trim($service['description']);
                            error_log("Using description: " . $serviceName);
                        } else {
                            $serviceName = 'Unnamed Labor';
                            error_log("WARNING: Service has no name field, using fallback. All fields: serviceNameKa=" . 
                                ($service['serviceNameKa'] ?? 'NULL') . ", nameKa=" . ($service['nameKa'] ?? 'NULL') . 
                                ", serviceName=" . ($service['serviceName'] ?? 'NULL') . ", name=" . ($service['name'] ?? 'NULL'));
                        }
                        
                        $servicePrice = !empty($service['price']) ? $service['price'] : (!empty($service['hourly_rate']) ? $service['hourly_rate'] : (!empty($service['rate']) ? $service['rate'] : 0));
                        
                        // Get count/hours
                        $serviceCount = !empty($service['hours']) ? $service['hours'] : (!empty($service['count']) ? $service['count'] : 1);
                        
                        // Calculate unit rate (price per item)
                        $unitRate = $serviceCount > 0 ? ($servicePrice / $serviceCount) : $servicePrice;

                        // Preserve service description as notes if available
                        $serviceDescription = !empty($service['description']) ? $service['description'] : '';
                        $serviceNotes = !empty($service['notes']) ? $service['notes'] : '';
                        // Combine description and notes if both exist
                        $combinedNotes = $serviceDescription && $serviceNotes
                            ? "$serviceDescription | $serviceNotes"
                            : ($serviceDescription ?: $serviceNotes);

                        // Get individual discount for this service
                        $serviceDiscount = isset($service['discount_percent']) ? floatval($service['discount_percent']) : 0;
                        $discountedPrice = $servicePrice * (1 - $serviceDiscount / 100);

                        return [
                            'name' => $serviceName,
                            'description' => $serviceDescription,
                            'hours' => $serviceCount,
                            'rate' => $unitRate,
                            'hourly_rate' => $unitRate,
                            'price' => $servicePrice, // Total price (unit rate * count)
                            'discounted_price' => $discountedPrice, // Price after individual discount
                            'discount_percent' => $serviceDiscount, // Individual service discount
                            'billable' => isset($service['billable']) ? $service['billable'] : true,
                            'notes' => $combinedNotes,
                        ];
                    }, $value);
                    $value = json_encode($transformedServices, JSON_UNESCAPED_UNICODE);
                    error_log("Services transformed for update: " . $value);
                } elseif ($dbField === 'repair_parts') {
                    // Transform parts to match database expectations (same as create-invoice.php)
                    $transformedParts = array_map(function($part) {
                        // Prefer Georgian name, fallback to English
                        $partName = !empty($part['nameKa']) ? $part['nameKa'] : 
                                   (!empty($part['name']) ? $part['name'] : 'Unnamed Part');
                        
                        $quantity = !empty($part['quantity']) ? intval($part['quantity']) : 1;
                        $unitPrice = !empty($part['unitPrice']) ? floatval($part['unitPrice']) : 0;
                        $totalPrice = !empty($part['totalPrice']) ? floatval($part['totalPrice']) : ($quantity * $unitPrice);
                        
                        return [
                            'name' => $partName,
                            'name_en' => !empty($part['name']) ? $part['name'] : $partName,
                            'part_number' => !empty($part['partNumber']) ? $part['partNumber'] : '',
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total_price' => $totalPrice,
                            'notes' => !empty($part['notes']) ? $part['notes'] : '',
                        ];
                    }, $value);
                    $value = json_encode($transformedParts, JSON_UNESCAPED_UNICODE);
                    error_log("Parts transformed for update: " . $value);
                } elseif ($dbField === 'case_images') {
                    // Normalize images with tagging info - extract URLs and tags
                    $imageUrls = [];
                    $imageTags = [];
                    
                    foreach ($value as $img) {
                        $url = null;
                        $tags = [];
                        $label = null;
                        
                        if (is_string($img)) {
                            // Simple URL string
                            $url = $img;
                        } elseif (is_array($img)) {
                            // Enriched photo object with tags
                            $url = $img['downloadURL'] ?? $img['downloadUrl'] ?? $img['url'] ?? $img['uri'] ?? $img['src'] ?? null;
                            $label = $img['label'] ?? null;
                            
                            // Extract tagging information if present
                            if (isset($img['tags']) && is_array($img['tags'])) {
                                $tags = array_map(function($tag) {
                                    return [
                                        'serviceName' => $tag['serviceName'] ?? 'Unknown',
                                        'servicePrice' => floatval($tag['servicePrice'] ?? 0),
                                        'x' => intval($tag['x'] ?? 0),
                                        'y' => intval($tag['y'] ?? 0),
                                        'xPercent' => floatval($tag['xPercent'] ?? 0),
                                        'yPercent' => floatval($tag['yPercent'] ?? 0),
                                    ];
                                }, $img['tags']);
                                
                                error_log("Photo tagged services found in update: " . count($tags) . " services");
                            }
                        }
                        
                        if ($url) {
                            $imageUrls[] = $url;
                            $imageTags[] = [
                                'url' => $url,
                                'label' => $label,
                                'tags' => $tags,
                                'tagCount' => count($tags),
                            ];
                        }
                    }
                    
                    $value = json_encode($imageUrls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    error_log("Images transformed for update: " . count($imageUrls) . " URLs with " . array_sum(array_column($imageTags, 'tagCount')) . " total tags");
                    
                    // If we have tags, also update systemLogs field
                    // Check if systemLogs is already being updated to avoid duplicate parameter
                    if (!empty($imageTags) && !in_array("systemLogs = :imageTagsData", $updateFields)) {
                        $imageTagsJson = json_encode($imageTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $updateFields[] = "systemLogs = :imageTagsData";
                        $bindParams[":imageTagsData"] = $imageTagsJson;
                        error_log("Will update systemLogs with " . count($imageTags) . " photo tag records");
                    }
                } else {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
            }
            
            $updateFields[] = "$dbField = :$appField";
            $bindParams[":$appField"] = $value;
            $processedDbFields[] = $dbField; // Track processed DB columns
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
