<?php
/**
 * Focused export: plate, status, vehicle make/model, customer info,
 * case photos, technicians with nachrebi qty, and payment status.
 *
 * Rules:
 *  - repairStage = "done" only for Completed cases; others get actual DB value
 *  - nachrebi_qty linked to assigned_mechanic (text field), not repair_assignments
 *  - Only insurance cases may be marked paid; non-insurance keep actual DB values
 *
 * Usage: php export_assignments.php     (writes file to disk)
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

// ── 4. Payments totals keyed by transfer_id ─────────────────────────────
$totalPaidMap = [];
try {
    foreach ($pdo->query("SELECT transfer_id, SUM(amount) as total FROM payments GROUP BY transfer_id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $totalPaidMap[(int)$r['transfer_id']] = round(floatval($r['total']), 2);
    }
} catch (Exception $e) {}

// ── 5. Fetch transfers ──────────────────────────────────────────────────
if ($statusesExist) {
    $sql = "
        SELECT t.id, t.slug, t.plate, t.name, t.phone,
               t.status, t.status_id, t.case_type,
               t.vehicle_make, t.vehicle_model, t.repair_stage,
               t.repair_assignments, t.nachrebi_qty,
               t.assigned_mechanic, t.case_images,
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
               t.vehicle_make, t.vehicle_model, t.repair_stage,
               t.repair_assignments, t.nachrebi_qty,
               t.assigned_mechanic, t.case_images,
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

    // Resolve make/model from transfer first, fallback to vehicles table
    $make  = trim($t['vehicle_make'] ?? '') ?: ($veh['make'] ?? null);
    $model = trim($t['vehicle_model'] ?? '') ?: ($veh['model'] ?? null);

    // ── Status detection ────────────────────────────────────────────
    $statusRaw   = $t['resolved_status'] ?? $t['status'] ?? 'New';
    $isCompleted = (stripos($statusRaw, 'complet') !== false || stripos($statusRaw, 'done') !== false || stripos($statusRaw, 'დასრულ') !== false);
    $isCancelled = (stripos($statusRaw, 'issue') !== false || stripos($statusRaw, 'cancel') !== false);

    // ── Repair stage: only "done" for completed cases ───────────────
    $repairStage = $t['repair_stage'] ?? null;
    if ($isCompleted) {
        $repairStage = 'done';
    }

    // ── Case type ───────────────────────────────────────────────────
    $rawType  = trim($t['case_type'] ?? '');
    $isInsurance = ($rawType === 'დაზღვევა' || stripos($rawType, 'insurance') !== false);
    $caseType = $isInsurance ? 'insurance' : 'retail';

    // ── Payment: only mark insurance cases as paid ──────────────────
    $amount    = round(floatval($t['amount'] ?? 0), 2);
    $franchise = round(floatval($t['franchise'] ?? 0), 2);
    $dbTotalPaid = $totalPaidMap[$id] ?? floatval($t['amount_paid'] ?? 0);

    if ($isInsurance && $isCompleted) {
        $paymentStatus = 'paid';
        $totalPaid     = $amount;
    } elseif ($isInsurance && !$isCancelled) {
        $paymentStatus = 'paid';
        $totalPaid     = round(max(0, $amount - $franchise), 2);
    } else {
        // Non-insurance or cancelled → actual DB values
        $paymentStatus = $t['payment_status'] ?? 'unpaid';
        $totalPaid     = round($dbTotalPaid, 2);
    }

    // ── Nachrebi qty ────────────────────────────────────────────────
    $nachrebiQty = ($t['nachrebi_qty'] ?? null) !== null && $t['nachrebi_qty'] !== ''
        ? round(floatval($t['nachrebi_qty']), 2)
        : null;

    // ── Assigned mechanic (nachrebi is linked to this, not repair_assignments) ─
    $assignedMechanic = trim($t['assigned_mechanic'] ?? '') ?: null;

    // ── Technician stage assignments (from repair_assignments JSON) ──
    $assignments = safeJson($t['repair_assignments'] ?? '{}');
    $technicians = [];
    foreach ($assignments as $stage => $techId) {
        if (!in_array($stage, $validStages, true)) continue;
        $techId = intval($techId);
        if ($techId <= 0) continue;
        $techName = $userNames[$techId] ?? ('Technician #' . $techId);
        $technicians[] = [
            'fullName' => $techName,
            'userId'   => $techId,
            'stage'    => $stage,
            // Nachrebi belongs to this tech only if they are the assigned_mechanic
            'nachrebiQty' => ($assignedMechanic !== null && $assignedMechanic === $techName) ? $nachrebiQty : null,
        ];
    }

    // If assigned_mechanic exists but isn't in repair_assignments, still include them
    if ($assignedMechanic && $nachrebiQty !== null) {
        $alreadyListed = false;
        foreach ($technicians as $tech) {
            if ($tech['fullName'] === $assignedMechanic) {
                $alreadyListed = true;
                break;
            }
        }
        if (!$alreadyListed) {
            $technicians[] = [
                'fullName'    => $assignedMechanic,
                'userId'      => null,
                'stage'       => null,
                'nachrebiQty' => $nachrebiQty,
            ];
        }
    }

    // ── Phone normalization ─────────────────────────────────────────
    $phone = trim($t['phone'] ?? '');
    if ($phone && !str_starts_with($phone, '+')) {
        if (str_starts_with($phone, '995'))                            $phone = '+' . $phone;
        elseif (str_starts_with($phone, '5') && strlen($phone) === 9)  $phone = '+995' . $phone;
        elseif (strlen($phone) >= 6)                                   $phone = '+995' . ltrim($phone, '0');
    }

    // ── Case images ─────────────────────────────────────────────────
    $images = safeJson($t['case_images'] ?? '[]');

    $cases[] = [
        'slug'         => $slug,
        'caseId'       => $id,
        'caseType'     => $caseType,
        'plate'        => $plate ?: null,
        'status'       => $statusRaw,
        'repairStage'  => $repairStage,
        'vehicleMake'  => $make ?: null,
        'vehicleModel' => $model ?: null,

        'customer' => [
            'name'  => trim($t['name'] ?? '') ?: null,
            'phone' => $phone ?: null,
        ],

        'amount'        => $amount,
        'franchise'     => $franchise,
        'paymentStatus' => $paymentStatus,
        'totalPaid'     => $totalPaid,

        'nachrebiQty'  => $nachrebiQty,
        'technicians'  => $technicians,

        'images' => $images,
    ];
}

// ── 6. Output ───────────────────────────────────────────────────────────
$payload = [
    'exportedAt' => gmdate('Y-m-d\TH:i:s\Z'),
    'totalCases' => count($cases),
    'cases'      => $cases,
];

$json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$filename = 'export_assignments_' . date('Y-m-d_His') . '.json';

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
