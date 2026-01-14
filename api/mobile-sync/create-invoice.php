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
    
    // Debug: Log all received data to check image field names
    error_log("=== CREATE INVOICE - FULL DATA RECEIVED ===");
    error_log("All keys: " . implode(', ', array_keys($data)));
    error_log("Full data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    
    // Check for various possible image field names from mobile app
    $possibleImageFields = ['images', 'photos', 'imageUrls', 'photoUrls', 'caseImages', 'vehicleImages', 'damageImages', 'attachments'];
    foreach ($possibleImageFields as $field) {
        if (isset($data[$field])) {
            error_log("Found images in field '$field': " . json_encode($data[$field]));
        }
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
        vehicle_make,
        vehicle_model,
        name,
        phone,
        amount,
        status,
        parts,
        repair_labor,
        case_images,
        serviceDate,
        service_date,
        repair_status,
        user_response,
        operatorComment,
        systemLogs
    ) VALUES (
        :plate,
        :vehicle_make,
        :vehicle_model,
        :name,
        :phone,
        :amount,
        :status,
        :parts,
        :repair_labor,
        :case_images,
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
            // Prefer Georgian name, fallback to English
            $serviceName = !empty($service['serviceNameKa']) ? $service['serviceNameKa'] :
                          (!empty($service['nameKa']) ? $service['nameKa'] :
                          (!empty($service['serviceName']) ? $service['serviceName'] :
                          (!empty($service['name']) ? $service['name'] : 'Unnamed Labor')));
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

            return [
                'name' => $serviceName,
                'description' => $serviceDescription,
                'hours' => $serviceCount,
                'rate' => $unitRate,
                'hourly_rate' => $unitRate,
                'price' => $servicePrice, // Total price (unit rate * count)
                'billable' => isset($service['billable']) ? $service['billable'] : true,
                'notes' => $combinedNotes,
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
    
    // Handle images array with tagging information (Firebase Storage URLs + service tags)
    // Check multiple possible field names from mobile app
    $imagesJson = null;
    $imageTagsJson = null;  // New: Store tagging information separately
    $imageFields = ['images', 'photos', 'imageUrls', 'photoUrls', 'caseImages', 'vehicleImages', 'damageImages', 'attachments'];
    foreach ($imageFields as $field) {
        if (isset($data[$field]) && is_array($data[$field]) && !empty($data[$field])) {
            // Process enriched photos with tagging information
            $imageUrls = [];
            $imageTags = [];  // New: Store tags for each photo
            
            foreach ($data[$field] as $img) {
                $url = null;
                $tags = [];
                $label = null;
                
                if (is_string($img)) {
                    // Simple URL string
                    $url = $img;
                } elseif (is_array($img)) {
                    // Enriched photo object with tags
                    // Extract URL from various field names
                    $url = $img['downloadURL'] ?? $img['downloadUrl'] ?? $img['url'] ?? $img['uri'] ?? $img['src'] ?? null;
                    $label = $img['label'] ?? null;
                    
                    // New: Extract tagging information if present
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
                        
                        error_log("Photo tagged services found: " . count($tags) . " services, label: " . ($label ?: 'N/A'));
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
            
            if (!empty($imageUrls)) {
                // Store image URLs in case_images
                $imagesJson = json_encode($imageUrls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                // New: Store complete tagging information
                if (!empty($imageTags)) {
                    $imageTagsJson = json_encode($imageTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    error_log("Stored photo tags for " . count($imageTags) . " images with " . array_sum(array_column($imageTags, 'tagCount')) . " total tags");
                }
                
                error_log("Images found in field '$field': " . count($imageUrls) . " images extracted");
            }
            break;
        }
    }
    
    // Bind parameters
    $stmt->execute([
        ':plate' => $data['plate'] ?? 'N/A',          // License plate number only
        ':vehicle_make' => $data['vehicleMake'] ?? '',  // Vehicle make (e.g., Toyota, BMW)
        ':vehicle_model' => $data['vehicleModel'] ?? '', // Vehicle model (e.g., Camry, X5)
        ':name' => $data['customerName'] ?? 'N/A',    // customerName -> name
        ':phone' => $data['customerPhone'] ?? '',     // customerPhone -> phone
        ':amount' => $data['totalPrice'] ?? 0,        // totalPrice -> amount
        ':status' => 'Processing',                    // Default status - Processing
        ':parts' => $partsJson,                       // parts JSON (damage tags)
        ':repair_labor' => $servicesJson,             // repair_labor JSON (services with hours and hourly_rate)
        ':case_images' => $imagesJson,                // case_images JSON (Firebase Storage URLs)
        ':serviceDate' => $serviceDate,               // Service date (datetime)
        ':service_date' => $serviceDate,              // Service date (datetime) - duplicate column
        ':repair_status' => null,                     // Leave repair_status NULL
        ':user_response' => 'Processing',             // Default user response - Processing
        ':operatorComment' => 'Created from mobile app - Firebase ID: ' . ($data['firebaseId'] ?? 'N/A'),
        ':systemLogs' => $imageTagsJson ?? null      // Store photo tags with service locations
    ]);
    
    $insertId = $pdo->lastInsertId();
    
    // Log success with services and photo tagging info
    $servicesCount = $servicesJson ? count(json_decode($servicesJson, true)) : 0;
    $tagsCount = 0;
    if ($imageTagsJson) {
        $imageTags = json_decode($imageTagsJson, true);
        $tagsCount = array_sum(array_map(function($img) { return $img['tagCount'] ?? 0; }, $imageTags));
    }
    
    if ($servicesJson) {
        $servicesData = json_decode($servicesJson, true);
        error_log("Invoice synced successfully. ID: $insertId, Firebase ID: " . ($data['firebaseId'] ?? 'N/A') . ", Services: $servicesCount, Photo Tags: $tagsCount");
    } else {
        error_log("Invoice synced successfully. ID: $insertId, Firebase ID: " . ($data['firebaseId'] ?? 'N/A') . ", Services: 0, Photo Tags: $tagsCount");
    }
    
    sendResponse(true, [
        'id' => $insertId,
        'message' => 'Invoice synced successfully',
        'firebase_id' => $data['firebaseId'] ?? null,
        'status' => 'Processing',
        'service_date' => $serviceDate,
        'services_count' => $servicesCount,
        'photo_tags_count' => $tagsCount,
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
