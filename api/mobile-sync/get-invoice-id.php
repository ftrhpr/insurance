<?php
/**
 * Get Invoice ID and Data API Endpoint
 * Fetches invoice ID and optionally full invoice data from cPanel database
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
    $firebaseId = isset($_GET['firebaseId']) ? $_GET['firebaseId'] : null;
    $invoiceId = isset($_GET['invoiceId']) ? $_GET['invoiceId'] : null;
    $fullData = isset($_GET['fullData']) ? ($_GET['fullData'] === 'true' || $_GET['fullData'] === '1') : false;
    
    if (!$firebaseId && !$invoiceId) {
        sendResponse(false, null, 'Missing required parameter: firebaseId or invoiceId', 400);
    }
    
    // Get database connection
    $pdo = getDBConnection();
    
    // Build query based on provided parameter
    if ($invoiceId) {
        // Direct lookup by cPanel invoice ID
        $sql = "SELECT * FROM transfers WHERE id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $invoiceId]);
    } else {
        // Search for invoice by firebase ID stored in operatorComment or systemLogs
        // Use separate parameter names since PDO doesn't support reusing named params
        $searchPattern = "%$firebaseId%";
        $sql = "SELECT * FROM transfers 
                WHERE operatorComment LIKE :firebaseId1 
                OR systemLogs LIKE :firebaseId2 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':firebaseId1' => $searchPattern,
            ':firebaseId2' => $searchPattern
        ]);
    }
    
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        sendResponse(false, null, 'Invoice not found', 404);
    }
    
    error_log("Found cPanel invoice ID: " . $invoice['id'] . " for request");
    
    // If fullData is not requested, return just the ID (backward compatible)
    if (!$fullData) {
        sendResponse(true, [
            'cpanelInvoiceId' => $invoice['id'],
            'firebaseId' => $firebaseId,
        ]);
        exit;
    }
    
    // Parse JSON fields for full data response
    $repairLabor = [];
    if (!empty($invoice['repair_labor'])) {
        $repairLabor = json_decode($invoice['repair_labor'], true) ?? [];
    }
    
    $parts = [];
    if (!empty($invoice['repair_parts'])) {
        $rawParts = json_decode($invoice['repair_parts'], true) ?? [];
        // Transform parts to app format
        $parts = array_map(function($part) {
            $quantity = intval($part['quantity'] ?? 1);
            $unitPrice = floatval($part['unit_price'] ?? $part['unitPrice'] ?? 0);
            $totalPrice = floatval($part['total_price'] ?? $part['totalPrice'] ?? 0);

            // Calculate totalPrice if it's 0 but unitPrice exists
            if ($totalPrice == 0 && $unitPrice > 0) {
                $totalPrice = $unitPrice * $quantity;
            }

            // Include damages (tagged work) if present - for photo markers
            $damages = [];
            if (!empty($part['damages']) && is_array($part['damages'])) {
                $damages = $part['damages'];
            }

            return [
                'name' => $part['name'] ?? $part['name_en'] ?? 'Unknown Part',
                'nameKa' => $part['name'] ?? $part['name_en'] ?? 'უცნობი ნაწილი',
                'partName' => $part['partName'] ?? $part['name'] ?? 'Unknown Part',
                'partNumber' => $part['part_number'] ?? $part['partNumber'] ?? '',
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'totalPrice' => $totalPrice,
                'notes' => $part['notes'] ?? '',
                'damages' => $damages,
            ];
        }, $rawParts);
    }
    
    // Parse photos from case_images column
    $photos = [];
    if (!empty($invoice['case_images'])) {
        $rawImages = json_decode($invoice['case_images'], true) ?? [];
        
        // Parse systemLogs for enriched photo metadata (tags, labels)
        $photoMetadata = [];
        if (!empty($invoice['systemLogs'])) {
            $systemLogs = json_decode($invoice['systemLogs'], true);
            if (is_array($systemLogs)) {
                foreach ($systemLogs as $meta) {
                    if (isset($meta['url'])) {
                        $photoMetadata[$meta['url']] = $meta;
                    }
                }
            }
        }
        
        // Transform to app photo format: { url, label, tags, tagCount, uploadedAt }
        foreach ($rawImages as $index => $img) {
            $url = is_string($img) ? $img : ($img['url'] ?? $img['downloadURL'] ?? $img['uri'] ?? null);
            if (!$url) continue;
            
            $label = 'Photo ' . ($index + 1);
            $tags = [];
            $uploadedAt = null;
            
            // Enrich from systemLogs metadata if available
            if (isset($photoMetadata[$url])) {
                $meta = $photoMetadata[$url];
                $label = $meta['label'] ?? $label;
                $tags = $meta['tags'] ?? [];
                $uploadedAt = $meta['uploadedAt'] ?? null;
            }
            // Or enrich from object properties if image was stored as object
            if (is_array($img)) {
                $label = $img['label'] ?? $label;
                $tags = $img['tags'] ?? $tags;
                $uploadedAt = $img['uploadedAt'] ?? $uploadedAt;
            }
            
            $photos[] = [
                'url' => $url,
                'label' => $label,
                'tags' => $tags,
                'tagCount' => count($tags),
                'uploadedAt' => $uploadedAt ?? date('c'),
            ];
        }
        error_log("get-invoice-id: Parsed " . count($photos) . " photos from case_images");
    }

    // Transform repair_labor back to app format
    // Debug: log raw labor data to understand what CPanel stores
    if (!empty($repairLabor)) {
        error_log("get-invoice-id: Invoice ID " . $invoice['id'] . " - raw repair_labor: " . json_encode($repairLabor));
    }

    $services = array_map(function($labor) {
        // CPanel stores service name in 'description' field, app uses 'name'
        $laborName = $labor['name'] ?? $labor['description'] ?? 'Unknown Service';

        // Get quantity - CPanel uses 'quantity', app uses 'hours' or 'count'
        $serviceCount = floatval($labor['quantity'] ?? $labor['hours'] ?? $labor['count'] ?? 1);

        // Get unit rate - CPanel uses 'unit_rate'
        $unitRate = floatval($labor['unit_rate'] ?? $labor['rate'] ?? $labor['hourly_rate'] ?? 0);

        // Calculate total price = unit_rate * quantity
        $servicePrice = floatval($labor['price'] ?? 0);
        if ($servicePrice == 0 && $unitRate > 0) {
            $servicePrice = $unitRate * $serviceCount;
        }

        // If still 0, try total_price or amount fields
        if ($servicePrice == 0) {
            $servicePrice = floatval($labor['total_price'] ?? $labor['amount'] ?? $labor['total'] ?? 0);
        }

        return [
            'serviceName' => $laborName,
            'serviceNameKa' => $laborName,
            'name' => $laborName,
            'nameKa' => $laborName,
            'price' => $servicePrice,
            'count' => $serviceCount,
            'unitRate' => $unitRate,
            'discount_percent' => floatval($labor['discount_percent'] ?? 0),
            'discountedPrice' => floatval($labor['discounted_price'] ?? $servicePrice),
            'description' => $labor['description'] ?? '',
            'notes' => $labor['notes'] ?? '',
            'billable' => $labor['billable'] ?? true,
        ];
    }, $repairLabor);
    
    // Build response in app format
    $responseData = [
        'cpanelId' => $invoice['id'],
        'slug' => $invoice['slug'] ?? '',
        'customerName' => $invoice['name'] ?? '',
        'customerPhone' => $invoice['phone'] ?? '',
        'plate' => $invoice['plate'] ?? '',
        'vehicleMake' => $invoice['vehicle_make'] ?? '',
        'vehicleModel' => $invoice['vehicle_model'] ?? '',
        'carMake' => $invoice['vehicle_make'] ?? '',
        'carModel' => $invoice['vehicle_model'] ?? '',
        'totalPrice' => floatval($invoice['amount'] ?? 0),
        'status' => $invoice['status'] ?? 'New',
        'repair_status' => $invoice['repair_status'] ?? null,
        'user_response' => $invoice['user_response'] ?? null,
        'services' => $services,
        'parts' => $parts,
        'photos' => $photos,
        'services_discount_percent' => floatval($invoice['services_discount_percent'] ?? 0),
        'parts_discount_percent' => floatval($invoice['parts_discount_percent'] ?? 0),
        'global_discount_percent' => floatval($invoice['global_discount_percent'] ?? 0),
        'includeVAT' => intval($invoice['vat_enabled'] ?? 0),
        'vatAmount' => floatval($invoice['vat_amount'] ?? 0),
        'vatRate' => floatval($invoice['vat_rate'] ?? 0),
        'subtotalBeforeVAT' => floatval($invoice['subtotal_before_vat'] ?? 0),
        'serviceDate' => $invoice['serviceDate'] ?? $invoice['service_date'] ?? null,
        'createdAt' => $invoice['created_at'] ?? $invoice['serviceDate'] ?? $invoice['service_date'] ?? null,
        'updatedAt' => $invoice['updatedAt'] ?? $invoice['updated_at'] ?? null,
        'internalNotes' => !empty($invoice['internalNotes']) ? json_decode($invoice['internalNotes'], true) : [],
        'voiceNotes' => !empty($invoice['voiceNotes']) ? json_decode($invoice['voiceNotes'], true) : [],
        'caseType' => $invoice['case_type'] ?? null,
        'assigned_mechanic' => $invoice['assigned_mechanic'] ?? null,
        'assignedMechanic' => $invoice['assigned_mechanic'] ?? null,
        'nachrebi_qty' => isset($invoice['nachrebi_qty']) ? floatval($invoice['nachrebi_qty']) : null,
        'status_id' => isset($invoice['status_id']) ? intval($invoice['status_id']) : null,
        'statusId' => isset($invoice['status_id']) ? intval($invoice['status_id']) : null,
        'repair_status_id' => isset($invoice['repair_status_id']) ? intval($invoice['repair_status_id']) : null,
        'repairStatusId' => isset($invoice['repair_status_id']) ? intval($invoice['repair_status_id']) : null,
        'status_changed_at' => $invoice['status_changed_at'] ?? null,
        'statusChangedAt' => $invoice['status_changed_at'] ?? null,
    ];
    
    error_log("Invoice full data fetched successfully. ID: " . $invoice['id']);
    
    sendResponse(true, $responseData);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
