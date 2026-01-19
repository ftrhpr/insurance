<?php
/**
 * Get Payments API Endpoint
 * Fetches payment records for a specific invoice from cPanel database
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
    $transferId = isset($_GET['transferId']) ? intval($_GET['transferId']) : null;

    if (!$transferId) {
        sendResponse(false, null, 'Missing required parameter: transferId', 400);
    }

    // Get database connection
    $pdo = getDBConnection();

    // Fetch payments for this transfer
    $sql = "SELECT * FROM payments WHERE transfer_id = :transfer_id ORDER BY payment_date DESC, created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':transfer_id' => $transferId]);

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Transform payments to app format
    $transformedPayments = array_map(function($payment) {
        return [
            'id' => intval($payment['id']),
            'transferId' => intval($payment['transfer_id']),
            'amount' => floatval($payment['amount'] ?? 0),
            'paymentDate' => $payment['payment_date'] ?? $payment['paid_at'] ?? null,
            'paymentMethod' => $payment['payment_method'] ?? $payment['method'] ?? 'Cash',
            'method' => $payment['method'] ?? $payment['payment_method'] ?? 'Cash',
            'reference' => $payment['reference'] ?? '',
            'notes' => $payment['notes'] ?? '',
            'recordedBy' => $payment['record_by'] ?? '',
            'currency' => $payment['currency'] ?? 'GEL',
            'createdAt' => $payment['created_at'] ?? null,
            'updatedAt' => $payment['updated_at'] ?? null,
        ];
    }, $payments);

    // Calculate total paid
    $totalPaid = array_reduce($transformedPayments, function($sum, $payment) {
        return $sum + $payment['amount'];
    }, 0);

    error_log("Fetched " . count($transformedPayments) . " payments for transfer ID: $transferId");

    sendResponse(true, [
        'payments' => $transformedPayments,
        'totalPaid' => $totalPaid,
        'count' => count($transformedPayments),
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
