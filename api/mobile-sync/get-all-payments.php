<?php
/**
 * Get All Payments API Endpoint
 * Fetches aggregate payment data across all invoices
 * Used for analytics: total collected, outstanding, by method, etc.
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
    $pdo = getDBConnection();

    // Fetch all payments with their associated transfer amounts
    $sql = "SELECT 
                p.id,
                p.transfer_id,
                p.amount AS payment_amount,
                p.payment_date,
                p.payment_method,
                p.method,
                p.currency,
                p.created_at,
                t.amount AS invoice_amount,
                t.name AS customer_name,
                t.phone AS customer_phone,
                t.plate,
                t.status AS invoice_status,
                t.assigned_mechanic,
                t.case_type,
                t.created_at AS invoice_created_at
            FROM payments p
            LEFT JOIN transfers t ON p.transfer_id = t.id
            ORDER BY p.payment_date DESC, p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $allPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate aggregates
    $totalCollected = 0;
    $byMethod = [];
    $byMonth = [];
    $byMechanic = [];

    foreach ($allPayments as $payment) {
        $amount = floatval($payment['payment_amount'] ?? 0);
        $totalCollected += $amount;

        // By payment method
        $method = $payment['method'] ?? $payment['payment_method'] ?? 'Cash';
        if (!isset($byMethod[$method])) {
            $byMethod[$method] = 0;
        }
        $byMethod[$method] += $amount;

        // By month
        $date = $payment['payment_date'] ?? $payment['created_at'] ?? null;
        if ($date) {
            $monthKey = date('Y-m', strtotime($date));
            if (!isset($byMonth[$monthKey])) {
                $byMonth[$monthKey] = ['collected' => 0, 'count' => 0];
            }
            $byMonth[$monthKey]['collected'] += $amount;
            $byMonth[$monthKey]['count'] += 1;
        }

        // By mechanic
        $mechanic = $payment['assigned_mechanic'] ?? 'არ არის მინიჭებული';
        if (!isset($byMechanic[$mechanic])) {
            $byMechanic[$mechanic] = 0;
        }
        $byMechanic[$mechanic] += $amount;
    }

    // Get outstanding balances: total invoiced minus total paid per transfer
    $outstandingSql = "SELECT 
                t.id,
                t.name AS customer_name,
                t.phone AS customer_phone,
                t.plate,
                t.amount AS invoice_amount,
                t.assigned_mechanic,
                t.case_type,
                t.status,
                t.created_at,
                COALESCE(SUM(p.amount), 0) AS total_paid
            FROM transfers t
            LEFT JOIN payments p ON t.id = p.transfer_id
            WHERE t.amount > 0
            GROUP BY t.id
            HAVING t.amount > COALESCE(SUM(p.amount), 0)
            ORDER BY (t.amount - COALESCE(SUM(p.amount), 0)) DESC";

    $outstandingStmt = $pdo->prepare($outstandingSql);
    $outstandingStmt->execute();
    $outstandingInvoices = $outstandingStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalOutstanding = 0;
    $outstandingList = [];
    foreach ($outstandingInvoices as $inv) {
        $invoiceAmount = floatval($inv['invoice_amount']);
        $paidAmount = floatval($inv['total_paid']);
        $balance = $invoiceAmount - $paidAmount;
        $totalOutstanding += $balance;

        $outstandingList[] = [
            'transferId' => intval($inv['id']),
            'customerName' => $inv['customer_name'] ?? '',
            'customerPhone' => $inv['customer_phone'] ?? '',
            'plate' => $inv['plate'] ?? '',
            'invoiceAmount' => $invoiceAmount,
            'totalPaid' => $paidAmount,
            'balance' => $balance,
            'status' => $inv['status'] ?? 'New',
            'caseType' => $inv['case_type'] ?? null,
            'assignedMechanic' => $inv['assigned_mechanic'] ?? null,
            'createdAt' => $inv['created_at'] ?? null,
            'daysOld' => $inv['created_at'] ? floor((time() - strtotime($inv['created_at'])) / 86400) : 0,
        ];
    }

    // Total invoiced
    $totalInvoicedSql = "SELECT COALESCE(SUM(amount), 0) AS total FROM transfers WHERE amount > 0";
    $totalInvoicedStmt = $pdo->prepare($totalInvoicedSql);
    $totalInvoicedStmt->execute();
    $totalInvoiced = floatval($totalInvoicedStmt->fetch(PDO::FETCH_ASSOC)['total']);

    // Transform by-method to array
    $methodBreakdown = [];
    foreach ($byMethod as $method => $amount) {
        $methodBreakdown[] = [
            'method' => $method,
            'amount' => round($amount, 2),
            'percentage' => $totalCollected > 0 ? round(($amount / $totalCollected) * 100, 1) : 0,
        ];
    }
    usort($methodBreakdown, function($a, $b) { return $b['amount'] <=> $a['amount']; });

    // Transform monthly data (last 12 months)
    $monthlyData = [];
    for ($i = 11; $i >= 0; $i--) {
        $monthKey = date('Y-m', strtotime("-$i months"));
        $monthlyData[] = [
            'month' => $monthKey,
            'label' => date('M', strtotime($monthKey . '-01')),
            'collected' => round($byMonth[$monthKey]['collected'] ?? 0, 2),
            'count' => $byMonth[$monthKey]['count'] ?? 0,
        ];
    }

    // Transform mechanic breakdown
    $mechanicBreakdown = [];
    foreach ($byMechanic as $mechanic => $amount) {
        $mechanicBreakdown[] = [
            'mechanic' => $mechanic,
            'collected' => round($amount, 2),
        ];
    }
    usort($mechanicBreakdown, function($a, $b) { return $b['collected'] <=> $a['collected']; });

    $collectionRate = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0;

    sendResponse(true, [
        'totalCollected' => round($totalCollected, 2),
        'totalInvoiced' => round($totalInvoiced, 2),
        'totalOutstanding' => round($totalOutstanding, 2),
        'collectionRate' => $collectionRate,
        'paymentCount' => count($allPayments),
        'methodBreakdown' => $methodBreakdown,
        'monthlyData' => $monthlyData,
        'mechanicBreakdown' => $mechanicBreakdown,
        'outstandingInvoices' => array_slice($outstandingList, 0, 20),
        'outstandingCount' => count($outstandingList),
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, null, 'Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 500);
}
?>
