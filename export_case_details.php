<?php
/**
 * Export: customer info, vehicle details, full payment records,
 * technician assignments (linked by plate), and case photos.
 *
 * Usage: php export_case_details.php     (writes file to disk)
 *   or   open in browser while logged in (sends JSON download)
 */

require_once __DIR__ . '/config.php';

$isCli = (php_sapi_name() === 'cli');

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    $msg = "DB connection failed: " . $e->getMessage();
    if ($isCli) { fwrite(STDERR, $msg . "\n"); exit(1); }
    http_response_code(500);
    die(json_encode(['error' => $msg]));
}

// ── Helpers ─────────────────────────────────────────────────────────────

function safeJson($raw): array {
    if (is_array($raw)) return $raw;
    if (empty($raw) || $raw === 'null') return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function isoTs($v): ?string {
    if (empty($v) || $v === '0000-00-00 00:00:00') return null;
    $ts = strtotime($v);
    return $ts !== false ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
}

function mapPaymentMethod(?string $m): string {
    if (empty($m)) return 'other';
    $m = strtolower(trim($m));
    return in_array($m, ['cash', 'card', 'transfer']) ? $m : 'other';
}

$validStages = [
    'backlog', 'disassembly', 'body_work',
    'processing_for_painting', 'preparing_for_painting',
    'painting', 'assembling', 'done',
];

// ── 1. User lookup ──────────────────────────────────────────────────────
$userNames = [];
try {
    $rows = $pdo->query("SELECT id, full_name, username FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $userNames[(int)$r['id']] = trim($r['full_name'] ?: $r['username'] ?: ('User #' . $r['id']));
    }
} catch (Exception $e) {}

// ── 2. Status lookup ────────────────────────────────────────────────────
$statusesExist = false;
try {
    $tc = $pdo->query("SHOW TABLES LIKE 'statuses'");
    $statusesExist = ($tc->rowCount() > 0);
} catch (Exception $e) {}

// ── 3. Vehicles table (fallback for make/model) ─────────────────────────
$vehiclesDb = [];
try {
    foreach ($pdo->query("SELECT plate, make, model FROM vehicles ORDER BY plate")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $vehiclesDb[strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $r['plate']))] = $r;
    }
} catch (Exception $e) {}

// ── 4. Payments keyed by transfer_id ────────────────────────────────────
$paymentsMap  = [];
$totalPaidMap = [];
try {
    // Detect available columns
    $payCols = array_column(
        $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'payments'")->fetchAll(PDO::FETCH_ASSOC),
        'COLUMN_NAME'
    );
    $hasPaidAt     = in_array('paid_at', $payCols);
    $hasRecordedBy = in_array('recorded_by', $payCols);
    $dateCol       = $hasPaidAt ? 'paid_at' : (in_array('payment_date', $payCols) ? 'payment_date' : 'created_at');

    $paySql = "SELECT p.*"
        . ($hasRecordedBy ? ", u.full_name AS recorded_by_name" : ", NULL AS recorded_by_name")
        . " FROM payments p"
        . ($hasRecordedBy ? " LEFT JOIN users u ON u.id = p.recorded_by" : "")
        . " ORDER BY p.$dateCol ASC";

    foreach ($pdo->query($paySql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $tid = (int)$r['transfer_id'];
        $paymentsMap[$tid][] = $r;
        $totalPaidMap[$tid]  = ($totalPaidMap[$tid] ?? 0) + floatval($r['amount']);
    }
} catch (Exception $e) {}

// ── 5. Fetch transfers ──────────────────────────────────────────────────
if ($statusesExist) {
    $sql = "
        SELECT t.id, t.slug, t.plate, t.name, t.phone,
               t.status, t.status_id, t.case_type,
               t.vehicle_make, t.vehicle_model,
               t.repair_stage, t.repair_assignments,
               t.nachrebi_qty, t.assigned_mechanic, t.case_images,
               t.amount, t.franchise, t.payment_status, t.amount_paid,
               COALESCE(s.name, t.status) AS resolved_status
        FROM transfers t
        LEFT JOIN statuses s ON t.status_id = s.id
        ORDER BY t.id ASC
    ";
} else {
    $sql = "
        SELECT t.id, t.slug, t.plate, t.name, t.phone,
               t.status, t.status_id, t.case_type,
               t.vehicle_make, t.vehicle_model,
               t.repair_stage, t.repair_assignments,
               t.nachrebi_qty, t.assigned_mechanic, t.case_images,
               t.amount, t.franchise, t.payment_status, t.amount_paid,
               t.status AS resolved_status
        FROM transfers t
        ORDER BY t.id ASC
    ";
}
$transfers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ── 6. Build export ─────────────────────────────────────────────────────
$cases = [];

foreach ($transfers as $t) {
    $id   = (int)$t['id'];
    $slug = $t['slug'] ?: ('CASE-' . str_pad($id, 3, '0', STR_PAD_LEFT));
    $plate = trim($t['plate'] ?? '');
    $normPlate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate));
    $veh = $vehiclesDb[$normPlate] ?? null;

    $make  = trim($t['vehicle_make'] ?? '') ?: ($veh['make'] ?? null);
    $model = trim($t['vehicle_model'] ?? '') ?: ($veh['model'] ?? null);

    $statusRaw = $t['resolved_status'] ?? $t['status'] ?? 'New';

    // ── Phone normalization ─────────────────────────────────────────
    $phone = trim($t['phone'] ?? '');
    if ($phone && !str_starts_with($phone, '+')) {
        if (str_starts_with($phone, '995'))                            $phone = '+' . $phone;
        elseif (str_starts_with($phone, '5') && strlen($phone) === 9)  $phone = '+995' . $phone;
        elseif (strlen($phone) >= 6)                                   $phone = '+995' . ltrim($phone, '0');
    }

    // ── Amounts ─────────────────────────────────────────────────────
    $amount    = round(floatval($t['amount'] ?? 0), 2);
    $franchise = round(floatval($t['franchise'] ?? 0), 2);
    $dbPaid    = round($totalPaidMap[$id] ?? floatval($t['amount_paid'] ?? 0), 2);

    // ── Individual payment records ──────────────────────────────────
    $payments = [];
    foreach (($paymentsMap[$id] ?? []) as $pr) {
        $payments[] = [
            'amount'     => round(floatval($pr['amount']), 2),
            'method'     => mapPaymentMethod($pr['method'] ?? null),
            'note'       => trim($pr['notes'] ?? $pr['reference'] ?? '') ?: null,
            'recordedBy' => trim($pr['recorded_by_name'] ?? '') ?: null,
            'date'       => isoTs($pr['paid_at'] ?? $pr['payment_date'] ?? $pr['created_at'] ?? null),
        ];
    }

    // ── Nachrebi & assigned mechanic ────────────────────────────────
    $nachrebiQty      = ($t['nachrebi_qty'] ?? null) !== null && $t['nachrebi_qty'] !== '' ? round(floatval($t['nachrebi_qty']), 2) : null;
    $assignedMechanic = trim($t['assigned_mechanic'] ?? '') ?: null;

    // ── Technician stage assignments ────────────────────────────────
    $assignments = safeJson($t['repair_assignments'] ?? '{}');
    $technicians = [];
    foreach ($assignments as $stage => $techId) {
        if (!in_array($stage, $validStages, true)) continue;
        $techId = intval($techId);
        if ($techId <= 0) continue;
        $techName = $userNames[$techId] ?? ('Technician #' . $techId);
        $technicians[] = [
            'fullName'    => $techName,
            'userId'      => $techId,
            'stage'       => $stage,
            'nachrebiQty' => ($assignedMechanic !== null && $assignedMechanic === $techName) ? $nachrebiQty : null,
        ];
    }

    // Include assigned_mechanic if not already in repair_assignments
    if ($assignedMechanic && $nachrebiQty !== null) {
        $found = false;
        foreach ($technicians as $tech) {
            if ($tech['fullName'] === $assignedMechanic) { $found = true; break; }
        }
        if (!$found) {
            $technicians[] = [
                'fullName'    => $assignedMechanic,
                'userId'      => null,
                'stage'       => null,
                'nachrebiQty' => $nachrebiQty,
            ];
        }
    }

    // ── Case images ─────────────────────────────────────────────────
    $images = safeJson($t['case_images'] ?? '[]');

    $cases[] = [
        'slug'   => $slug,
        'caseId' => $id,
        'plate'  => $plate ?: null,
        'status' => $statusRaw,

        'customer' => [
            'name'  => trim($t['name'] ?? '') ?: null,
            'phone' => $phone ?: null,
        ],

        'vehicle' => [
            'make'  => $make ?: null,
            'model' => $model ?: null,
        ],

        'payment' => [
            'amount'        => $amount,
            'franchise'     => $franchise,
            'totalPaid'     => $dbPaid,
            'paymentStatus' => $t['payment_status'] ?? 'unpaid',
            'records'       => $payments,
        ],

        'technicians' => $technicians,
        'images'      => $images,
    ];
}

// ── 7. Output ───────────────────────────────────────────────────────────
$payload = [
    'exportedAt' => gmdate('Y-m-d\TH:i:s\Z'),
    'totalCases' => count($cases),
    'cases'      => $cases,
];

$json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$filename = 'export_case_details_' . date('Y-m-d_His') . '.json';

if ($isCli) {
    $path = __DIR__ . '/' . $filename;
    file_put_contents($path, $json);
    echo "Exported {$payload['totalCases']} cases → $path (" . round(strlen($json) / 1024, 1) . " KB)\n";
} else {
    session_start();
    require_once __DIR__ . '/session_config.php';
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Authentication required']));
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    echo $json;
}
