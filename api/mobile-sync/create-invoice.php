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
    // Note: parts are stored as JSON in repair_parts column
    $sql = "INSERT INTO transfers (
        plate,
        vehicle_make,
        vehicle_model,
        name,
        phone,
        amount,
        status,
        repair_parts,
        repair_labor,
        case_images,
        serviceDate,
        service_date,
        repair_status,
        user_response,
        operatorComment,
        systemLogs,
        services_discount_percent,
        parts_discount_percent,
        global_discount_percent,
        vat_enabled,
        vat_amount,
        vat_rate,
        subtotal_before_vat,
        nachrebi_qty,
        status_id,
        repair_status_id,
        slug,
        due_date
    ) VALUES (
        :plate,
        :vehicle_make,
        :vehicle_model,
        :name,
        :phone,
        :amount,
        :status,
        :repair_parts,
        :repair_labor,
        :case_images,
        :serviceDate,
        :service_date,
        :repair_status,
        :user_response,
        :operatorComment,
        :systemLogs,
        :services_discount_percent,
        :parts_discount_percent,
        :global_discount_percent,
        :vat_enabled,
        :vat_amount,
        :vat_rate,
        :subtotal_before_vat,
        :nachrebi_qty,
        :status_id,
        :repair_status_id,
        :slug,
        :due_date
    )";
    
    $stmt = $pdo->prepare($sql);
    
    // Prepare system logs - keep empty/null
    $systemLogsJson = null;
    
    // Prepare parts JSON if exists
    // App sends parts array: [{"name":"Bumper","nameKa":"ბამპერი","partNumber":"OEM-123","quantity":1,"unitPrice":150,"totalPrice":150}]
    $partsJson = null;
    if (isset($data['parts']) && !empty($data['parts'])) {
        $parts = $data['parts'];
        error_log("Raw parts received: " . json_encode($parts));
        
        // Transform parts to match database expectations
        $transformedParts = array_map(function($part) {
            // Prefer Georgian name, fallback to English
            $partName = !empty($part['nameKa']) ? $part['nameKa'] : 
                       (!empty($part['name']) ? $part['name'] : 'Unnamed Part');
            
            $quantity = !empty($part['quantity']) ? intval($part['quantity']) : 1;
            $unitPrice = !empty($part['unitPrice']) ? floatval($part['unitPrice']) : 0;
            $totalPrice = !empty($part['totalPrice']) ? floatval($part['totalPrice']) : ($quantity * $unitPrice);
            
            // Preserve damages (tagged work on photos) if present
            $damages = [];
            if (!empty($part['damages']) && is_array($part['damages'])) {
                $damages = $part['damages'];
            }
            
            $result = [
                'name' => $partName,
                'name_en' => !empty($part['name']) ? $part['name'] : $partName,
                'part_number' => !empty($part['partNumber']) ? $part['partNumber'] : '',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'notes' => !empty($part['notes']) ? $part['notes'] : '',
                'damages' => $damages,
            ];
            
            // Preserve part id for client-side matching
            if (!empty($part['id'])) {
                $result['id'] = $part['id'];
            }
            
            return $result;
        }, $parts);
        
        $partsJson = json_encode($transformedParts, JSON_UNESCAPED_UNICODE);
        error_log("Parts transformed: " . $partsJson);
    }
    
    // Prepare services/labors JSON from the services array
    // App sends: [{"serviceName":"Plastic Restoration","serviceNameKa":"პლასტმასის აღდგენა","price":75,"count":1}]
    // Convert to database format expected by portal - prefer Georgian names
    $servicesJson = null;
    if (isset($data['services']) && !empty($data['services'])) {
        $services = $data['services'];
        error_log("Raw services received: " . json_encode($services, JSON_UNESCAPED_UNICODE));
        error_log("Number of services: " . count($services));
        
        // Log each service's name fields for debugging
        foreach ($services as $idx => $svc) {
            error_log("Service[$idx] fields - serviceName: " . ($svc['serviceName'] ?? 'NULL') . 
                      ", serviceNameKa: " . ($svc['serviceNameKa'] ?? 'NULL') . 
                      ", name: " . ($svc['name'] ?? 'NULL') . 
                      ", nameKa: " . ($svc['nameKa'] ?? 'NULL'));
        }
        
        // Transform field names to match portal expectations - prefer Georgian (nameKa) names
        $transformedServices = array_map(function($service) {
            // Prefer Georgian name, fallback to English, with better empty string handling
            $serviceName = '';
            
            // Check each field, ensuring non-empty
            if (!empty($service['serviceNameKa']) && trim($service['serviceNameKa']) !== '') {
                $serviceName = trim($service['serviceNameKa']);
            } elseif (!empty($service['nameKa']) && trim($service['nameKa']) !== '') {
                $serviceName = trim($service['nameKa']);
            } elseif (!empty($service['serviceName']) && trim($service['serviceName']) !== '') {
                $serviceName = trim($service['serviceName']);
            } elseif (!empty($service['name']) && trim($service['name']) !== '') {
                $serviceName = trim($service['name']);
            } elseif (!empty($service['description']) && trim($service['description']) !== '') {
                $serviceName = trim($service['description']);
            } else {
                $serviceName = 'Unnamed Labor';
                error_log("WARNING: Service has no name field, using fallback: " . json_encode($service, JSON_UNESCAPED_UNICODE));
            }
            
            error_log("Service name resolved to: " . $serviceName);
            
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
    
    // Generate unique slug for public sharing
    $slug = generateUniqueSlug($pdo, $data['customerName'] ?? 'customer', $data['plate'] ?? '');
    
    // Bind parameters
    $stmt->execute([
        ':plate' => $data['plate'] ?? 'N/A',          // License plate number only
        ':vehicle_make' => $data['vehicleMake'] ?? '',  // Vehicle make (e.g., Toyota, BMW)
        ':vehicle_model' => $data['vehicleModel'] ?? '', // Vehicle model (e.g., Camry, X5)
        ':name' => $data['customerName'] ?? 'N/A',    // customerName -> name
        ':phone' => $data['customerPhone'] ?? '',     // customerPhone -> phone
        ':amount' => $data['totalPrice'] ?? 0,        // totalPrice -> amount
        ':status' => 'Processing',                    // Default status - Processing
        ':repair_parts' => $partsJson,                // repair_parts JSON (car parts)
        ':repair_labor' => $servicesJson,             // repair_labor JSON (services with hours and hourly_rate)
        ':case_images' => $imagesJson,                // case_images JSON (Firebase Storage URLs)
        ':serviceDate' => $serviceDate,               // Service date (datetime)
        ':service_date' => $serviceDate,              // Service date (datetime) - duplicate column
        ':repair_status' => null,                     // Leave repair_status NULL
        ':user_response' => 'Processing',             // Default user response - Processing
        ':operatorComment' => 'Created from mobile app - Firebase ID: ' . ($data['firebaseId'] ?? 'N/A'),
        ':systemLogs' => $imageTagsJson ?? null,     // Store photo tags with service locations
        ':services_discount_percent' => floatval($data['services_discount_percent'] ?? 0),
        ':parts_discount_percent' => floatval($data['parts_discount_percent'] ?? 0),
        ':global_discount_percent' => floatval($data['global_discount_percent'] ?? 0),
        ':vat_enabled' => isset($data['includeVAT']) ? intval($data['includeVAT']) : 0,
        ':vat_amount' => floatval($data['vatAmount'] ?? 0),
        ':vat_rate' => floatval($data['vatRate'] ?? 0),
        ':subtotal_before_vat' => floatval($data['subtotalBeforeVAT'] ?? 0),
        ':nachrebi_qty' => !empty($data['nachrebi_qty']) ? floatval($data['nachrebi_qty']) : null,
        ':status_id' => !empty($data['status_id']) ? intval($data['status_id']) : (!empty($data['statusId']) ? intval($data['statusId']) : null),
        ':repair_status_id' => !empty($data['repair_status_id']) ? intval($data['repair_status_id']) : (!empty($data['repairStatusId']) ? intval($data['repairStatusId']) : null),
        ':slug' => $slug,
        ':due_date' => !empty($data['dueDate']) ? (date('H:i:s', strtotime($data['dueDate'])) !== '00:00:00' ? date('Y-m-d H:i:s', strtotime($data['dueDate'])) : date('Y-m-d', strtotime($data['dueDate']))) : (!empty($data['due_date']) ? (date('H:i:s', strtotime($data['due_date'])) !== '00:00:00' ? date('Y-m-d H:i:s', strtotime($data['due_date'])) : date('Y-m-d', strtotime($data['due_date']))) : null)
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

/**
 * Generate a unique slug for public invoice sharing
 * @param PDO $pdo Database connection
 * @param string $customerName Customer name
 * @param string $plate License plate
 * @return string Unique slug
 */
function generateUniqueSlug($pdo, $customerName, $plate) {
    // Clean and prepare base slug
    $baseSlug = strtolower(trim($customerName . '-' . $plate));
    $baseSlug = preg_replace('/[^a-z0-9\-]/', '-', $baseSlug);
    $baseSlug = preg_replace('/-+/', '-', $baseSlug);
    $baseSlug = trim($baseSlug, '-');
    
    // If base slug is empty (e.g. Georgian-only names), use plate or random
    if (empty($baseSlug)) {
        $baseSlug = 'case';
    }
    
    // Always append a unique random suffix to prevent collisions
    $uniqueSuffix = substr(md5(uniqid(mt_rand(), true)), 0, 6);
    $slug = $baseSlug . '-' . $uniqueSuffix;
    
    $counter = 1;
    
    // Ensure uniqueness (in the rare case of collision)
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transfers WHERE slug = :slug");
        $stmt->execute([':slug' => $slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            break; // Slug is unique
        }
        
        // Append counter and try again
        $slug = $baseSlug . '-' . $uniqueSuffix . '-' . $counter;
        $counter++;
        
        // Prevent infinite loop
        if ($counter > 1000) {
            $slug = $baseSlug . '-' . time() . '-' . rand(100, 999);
            break;
        }
    }
    
    return $slug;
}
?>
