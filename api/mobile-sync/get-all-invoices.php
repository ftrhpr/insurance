<?php
/**
 * Get All Invoices API Endpoint
 * Fetches all invoices from cPanel database
 * Used for syncing cPanel cases to Firebase and displaying them in the app
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
    // Get optional parameters for filtering
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $includeFirebaseSynced = isset($_GET['includeFirebaseSynced']) ? $_GET['includeFirebaseSynced'] === 'true' : true;
    $onlyCPanelOnly = isset($_GET['onlyCPanelOnly']) ? $_GET['onlyCPanelOnly'] === 'true' : false;

    // Limit max results to prevent memory issues
    $limit = min($limit, 500);

    // Get database connection
    $pdo = getDBConnection();

    // Build query - get all invoices, optionally filter by Firebase sync status
    $sql = "SELECT * FROM transfers";
    $params = [];

    if ($onlyCPanelOnly) {
        // Only get invoices that are NOT synced with Firebase (no operatorComment with Firebase ID)
        $sql .= " WHERE (operatorComment IS NULL OR operatorComment = '' OR operatorComment NOT LIKE '%Firebase%')";
    }

    $sql .= " ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM transfers";
    if ($onlyCPanelOnly) {
        $countSql .= " WHERE (operatorComment IS NULL OR operatorComment = '' OR operatorComment NOT LIKE '%Firebase%')";
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute();
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Transform invoices to app format
    $transformedInvoices = array_map(function($invoice) {
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
                $quantity = intval($part['quantity'] ?? 1);
                $unitPrice = floatval($part['unit_price'] ?? $part['unitPrice'] ?? 0);
                $totalPrice = floatval($part['total_price'] ?? $part['totalPrice'] ?? 0);

                // Calculate totalPrice if it's 0 but unitPrice exists
                if ($totalPrice == 0 && $unitPrice > 0) {
                    $totalPrice = $unitPrice * $quantity;
                }

                return [
                    'name' => $part['name'] ?? $part['name_en'] ?? 'Unknown Part',
                    'nameKa' => $part['name'] ?? $part['name_en'] ?? 'უცნობი ნაწილი',
                    'partNumber' => $part['part_number'] ?? $part['partNumber'] ?? '',
                    'quantity' => $quantity,
                    'unitPrice' => $unitPrice,
                    'totalPrice' => $totalPrice,
                    'notes' => $part['notes'] ?? '',
                ];
            }, $rawParts);
        }

        $photos = [];
        if (!empty($invoice['photos'])) {
            $photos = json_decode($invoice['photos'], true) ?? [];
        }

        // Transform repair_labor to app services format
        // Debug: log raw labor data to understand what CPanel stores
        if (!empty($repairLabor)) {
            error_log("get-all-invoices: Invoice ID " . $invoice['id'] . " - raw repair_labor: " . json_encode($repairLabor));
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
                'key' => $labor['key'] ?? null,
                'discount_percent' => floatval($labor['discount_percent'] ?? 0),
                'discountedPrice' => floatval($labor['discounted_price'] ?? $servicePrice),
                'billable' => $labor['billable'] ?? true,
            ];
        }, $repairLabor);

        // Check if this invoice has a Firebase ID in operatorComment
        $firebaseId = null;
        if (!empty($invoice['operatorComment'])) {
            // Try to extract Firebase ID from comment
            if (preg_match('/Firebase[:\s]+([a-zA-Z0-9]+)/i', $invoice['operatorComment'], $matches)) {
                $firebaseId = $matches[1];
            }
        }

        return [
            'cpanelId' => $invoice['id'],
            'firebaseId' => $firebaseId,
            'isCPanelOnly' => empty($firebaseId),
            'customerName' => $invoice['name'] ?? '',
            'customerPhone' => $invoice['phone'] ?? '',
            'plate' => $invoice['plate'] ?? '',
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
            'createdAt' => $invoice['created_at'] ?? $invoice['serviceDate'] ?? null,
            'updatedAt' => $invoice['updatedAt'] ?? $invoice['updated_at'] ?? null,
            'slug' => $invoice['slug'] ?? null,
            'internalNotes' => !empty($invoice['internalNotes']) ? json_decode($invoice['internalNotes'], true) : [],
            'caseType' => $invoice['case_type'] ?? null,
        ];
    }, $invoices);

    error_log("Fetched " . count($transformedInvoices) . " invoices from cPanel");

    sendResponse(true, [
        'invoices' => $transformedInvoices,
        'total' => intval($totalCount),
        'limit' => $limit,
        'offset' => $offset,
        'hasMore' => ($offset + count($transformedInvoices)) < $totalCount,
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
