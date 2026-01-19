<?php
/**
 * Create Payment API Endpoint
 * Creates a new payment record in the cPanel database
 */

define('API_ACCESS', true);
require_once 'config.php';

// Verify API key
verifyAPIKey();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, 'Method not allowed. Use POST request.', 405);
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, null, 'Invalid JSON data', 400);
    }

    // Validate required fields
    if (!isset($data['transferId']) || !isset($data['amount'])) {
        sendResponse(false, null, 'Missing required fields: transferId, amount', 400);
    }

    // Get database connection
    $pdo = getDBConnection();

    $transferId = intval($data['transferId']);
    $amount = floatval($data['amount']);
    $paymentMethod = $data['paymentMethod'] ?? $data['method'] ?? 'Cash';
    $method = $data['method'] ?? $paymentMethod;
    $reference = $data['reference'] ?? '';
    $notes = $data['notes'] ?? '';
    $recordedBy = $data['recordedBy'] ?? 'მობილური აპი';
    $currency = $data['currency'] ?? 'GEL';
    $paymentDate = $data['paymentDate'] ?? date('Y-m-d H:i:s');

    // Verify transfer exists
    $checkSql = "SELECT id, amount FROM transfers WHERE id = :id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $transferId]);

    if ($checkStmt->rowCount() === 0) {
        sendResponse(false, null, 'Transfer not found', 404);
    }

    // Insert payment (only use columns that exist in your table)
    $sql = "INSERT INTO payments (transfer_id, amount, payment_date, payment_method, method, reference, notes, currency, paid_at, created_at, updated_at)
            VALUES (:transfer_id, :amount, :payment_date, :payment_method, :method, :reference, :notes, :currency, :paid_at, NOW(), NOW())";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':transfer_id' => $transferId,
        ':amount' => $amount,
        ':payment_date' => $paymentDate,
        ':payment_method' => $paymentMethod,
        ':method' => $method,
        ':reference' => $reference,
        ':notes' => $notes,
        ':currency' => $currency,
        ':paid_at' => $paymentDate,
    ]);

    if (!$result) {
        sendResponse(false, null, 'Failed to create payment', 500);
    }

    $paymentId = $pdo->lastInsertId();

    // Fetch the created payment
    $selectSql = "SELECT * FROM payments WHERE id = :id LIMIT 1";
    $selectStmt = $pdo->prepare($selectSql);
    $selectStmt->execute([':id' => $paymentId]);
    $payment = $selectStmt->fetch(PDO::FETCH_ASSOC);

    // Calculate new total paid for this transfer
    $totalSql = "SELECT SUM(amount) as total_paid FROM payments WHERE transfer_id = :transfer_id";
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute([':transfer_id' => $transferId]);
    $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $totalPaid = floatval($totalResult['total_paid'] ?? 0);

    error_log("Payment created successfully. ID: $paymentId for transfer: $transferId, amount: $amount");

    sendResponse(true, [
        'id' => intval($paymentId),
        'payment' => [
            'id' => intval($payment['id']),
            'transferId' => intval($payment['transfer_id']),
            'amount' => floatval($payment['amount']),
            'paymentDate' => $payment['payment_date'],
            'paymentMethod' => $payment['payment_method'],
            'method' => $payment['method'],
            'reference' => $payment['reference'],
            'notes' => $payment['notes'],
            'recordedBy' => 'მობილური აპი',
            'currency' => $payment['currency'],
            'createdAt' => $payment['created_at'],
        ],
        'totalPaid' => $totalPaid,
        'message' => 'Payment created successfully',
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
