<?php
/**
 * Delete Payment API Endpoint
 * Deletes a payment record from the cPanel database
 */

define('API_ACCESS', true);
require_once 'config.php';

// Verify API key
verifyAPIKey();

// Only accept DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendResponse(false, null, 'Method not allowed. Use DELETE request.', 405);
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, null, 'Invalid JSON data', 400);
    }

    // Validate required fields
    if (!isset($data['paymentId'])) {
        sendResponse(false, null, 'Missing required field: paymentId', 400);
    }

    // Get database connection
    $pdo = getDBConnection();

    $paymentId = intval($data['paymentId']);

    // Check if payment exists and get transfer_id
    $checkSql = "SELECT id, transfer_id FROM payments WHERE id = :id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $paymentId]);
    $payment = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        sendResponse(false, null, 'Payment not found', 404);
    }

    $transferId = $payment['transfer_id'];

    // Delete payment
    $sql = "DELETE FROM payments WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([':id' => $paymentId]);

    if (!$result) {
        sendResponse(false, null, 'Failed to delete payment', 500);
    }

    // Calculate new total paid for this transfer
    $totalSql = "SELECT SUM(amount) as total_paid FROM payments WHERE transfer_id = :transfer_id";
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute([':transfer_id' => $transferId]);
    $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $totalPaid = floatval($totalResult['total_paid'] ?? 0);

    error_log("Payment deleted successfully. ID: $paymentId");

    sendResponse(true, [
        'id' => $paymentId,
        'transferId' => intval($transferId),
        'totalPaid' => $totalPaid,
        'message' => 'Payment deleted successfully',
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
