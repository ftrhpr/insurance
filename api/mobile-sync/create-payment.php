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

    // Log the incoming data for debugging
    error_log("Payment create - paymentMethod: '$paymentMethod', method: '$method'");

    // Check column types for debugging
    try {
        $colInfo = $pdo->query("SHOW COLUMNS FROM payments WHERE Field IN ('method', 'payment_method')");
        $columns = $colInfo->fetchAll(PDO::FETCH_ASSOC);
        error_log("Payment columns info: " . json_encode($columns));
    } catch (Exception $e) {
        error_log("Could not check columns: " . $e->getMessage());
    }

    // Validate and normalize method values (database may have constraints)
    $validMethods = ['Cash', 'BOG', 'TBC'];
    if (!in_array($method, $validMethods)) {
        // Try to map to valid method
        if (stripos($method, 'cash') !== false) {
            $method = 'Cash';
        } elseif (stripos($method, 'bog') !== false) {
            $method = 'BOG';
        } elseif (stripos($method, 'tbc') !== false) {
            $method = 'TBC';
        } else {
            $method = 'Cash'; // Default fallback
        }
    }

    // Validate payment_method
    $validPaymentMethods = ['Cash', 'Transfer'];
    if (!in_array($paymentMethod, $validPaymentMethods)) {
        if (stripos($paymentMethod, 'cash') !== false) {
            $paymentMethod = 'Cash';
        } else {
            $paymentMethod = 'Transfer';
        }
    }
    $notes = $data['notes'] ?? '';
    $recordedBy = $data['recordedBy'] ?? 'მობილური აპი';
    $currency = $data['currency'] ?? 'GEL';

    // Convert ISO date to MySQL datetime format
    $paymentDateInput = $data['paymentDate'] ?? null;
    if ($paymentDateInput) {
        $dateTime = new DateTime($paymentDateInput);
        $paymentDate = $dateTime->format('Y-m-d H:i:s');
    } else {
        $paymentDate = date('Y-m-d H:i:s');
    }

    // Verify transfer exists
    $checkSql = "SELECT id, amount FROM transfers WHERE id = :id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $transferId]);

    if ($checkStmt->rowCount() === 0) {
        sendResponse(false, null, 'Transfer not found', 404);
    }

    // Insert payment - skip 'method' column due to ENUM constraints
    // Store method info in payment_method: Cash, BOG, or TBC
    // If paymentMethod is Transfer, use the sub-method (BOG/TBC) from 'method' field
    $fullPaymentMethod = $paymentMethod;
    if ($paymentMethod === 'Transfer') {
        // Use BOG or TBC from the method field
        if ($method === 'BOG' || $method === 'TBC') {
            $fullPaymentMethod = $method;
        } else {
            $fullPaymentMethod = 'BOG'; // Default to BOG for transfers
        }
    }
    error_log("Final payment method to save: '$fullPaymentMethod' (original paymentMethod: '$paymentMethod', method: '$method')");

    $sql = "INSERT INTO payments (transfer_id, amount, payment_date, payment_method, reference, notes, currency, paid_at, created_at, updated_at)
            VALUES (:transfer_id, :amount, :payment_date, :payment_method, :reference, :notes, :currency, :paid_at, NOW(), NOW())";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':transfer_id' => $transferId,
        ':amount' => $amount,
        ':payment_date' => $paymentDate,
        ':payment_method' => $fullPaymentMethod,
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
