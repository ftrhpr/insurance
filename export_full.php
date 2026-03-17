<?php
/**
 * FULL export of ALL cases with every detail.
 *
 * Special rules applied:
 *  - Active queue cases (not Completed/Issue) → caseType = "insurance"
 *  - Completed cases → paymentStatus = "paid"
 *  - All cases except franchise amounts → marked as paid
 *
 * Usage: php export_full.php               (writes file to disk)
 *   or   open in browser while logged in   (sends JSON download)
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

// ─── Helpers ────────────────────────────────────────────────────────────

function safeJson($raw): array {
    if (is_array($raw)) return $raw;
    if (empty($raw) || $raw === 'null') return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function isoDate($v): ?string {
    if (empty($v) || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return null;
    $ts = strtotime($v);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

function isoTs($v): ?string {
    if (empty($v) || $v === '0000-00-00 00:00:00') return null;
    $ts = strtotime($v);
    return $ts !== false ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
}

function toKey(string $name): string {
    $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name);
    if (empty($ascii)) $ascii = $name;
    $ascii = strtolower(trim($ascii));
    $ascii = preg_replace('/[^a-z0-9]+/', '_', $ascii);
    return trim($ascii, '_') ?: 'item';
}

function isGeorgian(string $s): bool {
    return (bool) preg_match('/[\x{10A0}-\x{10FF}]/u', $s);
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

// ─── 1. User lookup (for technicians) ───────────────────────────────────
$userNames = [];
try {
    $rows = $pdo->query("SELECT id, full_name, username FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $userNames[(int)$r['id']] = trim($r['full_name'] ?: $r['username'] ?: ('User #' . $r['id']));
    }
} catch (Exception $e) {}

// ─── 2. Statuses lookup ─────────────────────────────────────────────────
$statusesExist = false;
try {
    $tc = $pdo->query("SHOW TABLES LIKE 'statuses'");
    $statusesExist = ($tc->rowCount() > 0);
} catch (Exception $e) {}

// ─── 3. ALL transfers ───────────────────────────────────────────────────
if ($statusesExist) {
    $sql = "
        SELECT t.*,
               COALESCE(cs.name, t.status) AS resolved_status,
               COALESCE(rs.name, t.repair_status) AS resolved_repair_status
        FROM transfers t
        LEFT JOIN statuses cs ON t.status_id = cs.id AND cs.type = 'case'
        LEFT JOIN statuses rs ON t.repair_status_id = rs.id AND rs.type = 'repair'
        ORDER BY t.id ASC
    ";
} else {
    $sql = "SELECT t.*, t.status AS resolved_status, t.repair_status AS resolved_repair_status FROM transfers t ORDER BY t.id ASC";
}
$transfers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ─── 4. ALL payments keyed by transfer_id ───────────────────────────────
$paymentsMap  = [];
$totalPaidMap = [];
try {
    $payCols = array_column(
        $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'payments'")->fetchAll(PDO::FETCH_ASSOC),
        'COLUMN_NAME'
    );
    $hasPaidAt     = in_array('paid_at', $payCols);
    $hasRecordedBy = in_array('recorded_by', $payCols);
    $dateCol = $hasPaidAt ? 'paid_at' : (in_array('payment_date', $payCols) ? 'payment_date' : 'created_at');

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

// ─── 5. Active case_versions ────────────────────────────────────────────
$versionsMap = [];
try {
    foreach ($pdo->query("SELECT * FROM case_versions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $versionsMap[(int)$r['transfer_id']] = $r;
    }
} catch (Exception $e) {}

// ─── 6. Vehicles table ─────────────────────────────────────────────────
$vehiclesDb = [];
try {
    foreach ($pdo->query("SELECT * FROM vehicles ORDER BY plate ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $vehiclesDb[strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $r['plate']))] = $r;
    }
} catch (Exception $e) {}

// ─── 7. Build cases array ───────────────────────────────────────────────
$cases = [];

foreach ($transfers as $t) {
    $id   = (int)$t['id'];
    $slug = $t['slug'] ?: ('CASE-' . str_pad($id, 3, '0', STR_PAD_LEFT));

    // ── Resolved status ─────────────────────────────────────────────
    $statusRaw  = $t['resolved_status'] ?? $t['status'] ?? 'New';
    $isCompleted = (stripos($statusRaw, 'complet') !== false || stripos($statusRaw, 'done') !== false || stripos($statusRaw, 'დასრულ') !== false);
    $isCancelled = (stripos($statusRaw, 'issue') !== false || stripos($statusRaw, 'cancel') !== false);
    $isActiveQueue = (!$isCompleted && !$isCancelled);

    // ── Rule: active queue → insurance type ─────────────────────────
    $caseType = $isActiveQueue ? 'insurance' : (
        (($t['case_type'] ?? '') === 'დაზღვევა' || stripos($t['case_type'] ?? '', 'insurance') !== false) ? 'insurance' : 'retail'
    );

    // ── Phone normalization ─────────────────────────────────────────
    $phone = trim($t['phone'] ?? '');
    if ($phone && !str_starts_with($phone, '+')) {
        if (str_starts_with($phone, '995'))                              $phone = '+' . $phone;
        elseif (str_starts_with($phone, '5') && strlen($phone) === 9)    $phone = '+995' . $phone;
        elseif (strlen($phone) >= 6)                                     $phone = '+995' . ltrim($phone, '0');
    }

    // ── Vehicle ─────────────────────────────────────────────────────
    $plate = trim($t['plate'] ?? '');
    $normPlate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate));
    $veh = $vehiclesDb[$normPlate] ?? null;

    // ── Items: prefer active version ────────────────────────────────
    $ver = $versionsMap[$id] ?? null;
    $rawParts = safeJson($ver['repair_parts']      ?? $t['repair_parts'] ?? '[]');
    $rawLabor = safeJson($ver['repair_labor']       ?? $t['repair_labor'] ?? '[]');
    $partsDsc = floatval($ver['parts_discount_percent']    ?? $t['parts_discount_percent']    ?? 0);
    $svcsDsc  = floatval($ver['services_discount_percent'] ?? $t['services_discount_percent'] ?? 0);
    $globDsc  = floatval($ver['global_discount_percent']   ?? $t['global_discount_percent']   ?? 0);
    $vatOn    = !empty($ver['vat_enabled'] ?? $t['vat_enabled'] ?? 0);

    // Format services
    $services = [];
    foreach ($rawLabor as $l) {
        $name = trim($l['description'] ?? $l['name'] ?? '');
        if ($name === '') continue;
        $nameEn = ''; $nameKa = '';
        if (isGeorgian($name)) { $nameKa = $name; $nameEn = $name; } else { $nameEn = $name; }
        $qty   = max(1, intval($l['quantity'] ?? 1));
        $rate  = round(floatval($l['unit_rate'] ?? $l['price'] ?? 0), 2);
        $disc  = round(floatval($l['discount_percent'] ?? 0), 2);
        $services[] = [
            'key'             => toKey($name),
            'nameEn'          => $nameEn,
            'nameKa'          => $nameKa,
            'unitPrice'       => $rate,
            'quantity'        => $qty,
            'lineTotal'       => round($rate * $qty, 2),
            'discountPercent' => $disc > 0 ? $disc : null,
        ];
    }

    // Format parts
    $parts = [];
    foreach ($rawParts as $p) {
        $name = trim($p['name'] ?? '');
        if ($name === '') continue;
        $nameKa = isGeorgian($name) ? $name : '';
        $qty   = max(1, intval($p['quantity'] ?? 1));
        $price = round(floatval($p['unit_price'] ?? $p['price'] ?? 0), 2);
        $disc  = round(floatval($p['discount_percent'] ?? 0), 2);
        $parts[] = [
            'name'            => $name,
            'nameKa'          => $nameKa,
            'partNumber'      => trim($p['sku'] ?? $p['partNumber'] ?? '') ?: null,
            'supplier'        => trim($p['supplier'] ?? '') ?: null,
            'quantity'        => $qty,
            'unitPrice'       => $price,
            'lineTotal'       => round($price * $qty, 2),
            'ordered'         => !empty($p['ordered']),
            'discountPercent' => $disc > 0 ? $disc : null,
        ];
    }

    // ── Payments ────────────────────────────────────────────────────
    $payments = [];
    foreach (($paymentsMap[$id] ?? []) as $pr) {
        $payments[] = [
            'amount'     => round(floatval($pr['amount']), 2),
            'method'     => mapPaymentMethod($pr['method'] ?? null),
            'note'       => trim($pr['notes'] ?? $pr['reference'] ?? '') ?: null,
            'recordedBy' => trim($pr['recorded_by_name'] ?? '') ?: null,
            'createdAt'  => isoTs($pr['paid_at'] ?? $pr['created_at'] ?? null),
        ];
    }
    $dbTotalPaid = $totalPaidMap[$id] ?? floatval($t['amount_paid'] ?? 0);

    // ── Rule: mark paid ─────────────────────────────────────────────
    // Completed → paid. Active queue → paid (everything except franchise).
    $amount    = round(floatval($t['amount'] ?? 0), 2);
    $franchise = round(floatval($t['franchise'] ?? 0), 2);

    if ($isCompleted) {
        $paymentStatus = 'paid';
        $totalPaid     = $amount; // entire amount paid
    } elseif ($isActiveQueue) {
        // Paid everything except franchise
        $paymentStatus = 'paid';
        $totalPaid     = round(max(0, $amount - $franchise), 2);
    } else {
        // Cancelled / other — use actual DB values
        $paymentStatus = $t['payment_status'] ?? 'unpaid';
        $totalPaid     = round($dbTotalPaid, 2);
    }

    // ── Notes ───────────────────────────────────────────────────────
    $notes = [];
    foreach (safeJson($t['internalNotes'] ?? $t['internal_notes'] ?? '[]') as $n) {
        $notes[] = [
            'content'    => trim($n['text'] ?? $n['content'] ?? ''),
            'authorName' => trim($n['authorName'] ?? $n['author'] ?? 'System'),
            'createdAt'  => isoTs($n['timestamp'] ?? $n['createdAt'] ?? null),
        ];
    }

    // ── System logs ─────────────────────────────────────────────────
    $systemLogs = safeJson($t['systemLogs'] ?? $t['system_logs'] ?? '[]');

    // ── Activity log ────────────────────────────────────────────────
    $activityLog = safeJson($t['repair_activity_log'] ?? '[]');

    // ── Images ──────────────────────────────────────────────────────
    $images = safeJson($t['case_images'] ?? '[]');

    // ── Technicians (from repair_assignments) ───────────────────────
    $techAssignments = [];
    $assignments = safeJson($t['repair_assignments'] ?? '{}');
    foreach ($assignments as $stage => $techId) {
        if (!in_array($stage, $validStages, true)) continue;
        $techId = intval($techId);
        if ($techId <= 0) continue;
        $techAssignments[] = [
            'fullName' => $userNames[$techId] ?? ('Technician #' . $techId),
            'userId'   => $techId,
            'stage'    => $stage,
        ];
    }

    // ── Stage statuses & timers ─────────────────────────────────────
    $stageStatuses = safeJson($t['stage_statuses'] ?? '{}');
    $stageTimers   = safeJson($t['stage_timers'] ?? '{}');
    $workTimes     = safeJson($t['work_times'] ?? '{}');

    // ── VAT ─────────────────────────────────────────────────────────
    $vatRate   = $vatOn ? floatval($t['vat_rate'] ?? 18) : null;
    $vatAmount = $vatOn ? round(floatval($t['vat_amount'] ?? 0), 2) : null;

    // ── Build case ──────────────────────────────────────────────────
    $cases[] = [
        'slug'      => $slug,
        'caseId'    => $id,
        'caseType'  => $caseType,
        'status'    => $statusRaw,
        'cancelled' => $isCancelled,
        'urgent'    => (bool)($t['urgent'] ?? false),

        'repairStage'   => $t['repair_stage'] ?? null,
        'repairStatus'  => $t['resolved_repair_status'] ?? $t['repair_status'] ?? null,

        'customer' => [
            'name'  => trim($t['name'] ?? '') ?: null,
            'phone' => $phone ?: null,
        ],

        'vehicle' => [
            'plate' => $plate ?: null,
            'make'  => $t['vehicle_make'] ?? null,
            'model' => $t['vehicle_model'] ?? ($veh['model'] ?? null),
        ],

        'amount'         => $amount,
        'franchise'      => $franchise,
        'totalPaid'      => $totalPaid,
        'paymentStatus'  => $paymentStatus,

        'servicesDiscountPercent' => $svcsDsc > 0 ? $svcsDsc : null,
        'partsDiscountPercent'   => $partsDsc > 0 ? $partsDsc : null,
        'globalDiscountPercent'  => $globDsc > 0 ? $globDsc : null,

        'vatEnabled' => $vatOn,
        'vatRate'    => $vatRate,
        'vatAmount'  => $vatAmount,

        'services'  => $services,
        'parts'     => $parts,
        'payments'  => $payments,

        'technicians'   => $techAssignments,
        'stageStatuses' => !empty($stageStatuses) ? $stageStatuses : null,
        'stageTimers'   => !empty($stageTimers) ? $stageTimers : null,
        'workTimes'     => !empty($workTimes) ? $workTimes : null,

        'nachrebiQty' => ($t['nachrebi_qty'] ?? null) !== null && $t['nachrebi_qty'] !== '' ? round(floatval($t['nachrebi_qty']), 2) : null,

        'dueDate'       => isoDate($t['due_date'] ?? null),
        'scheduledDate' => isoDate($t['service_date'] ?? null),
        'completedAt'   => isoTs($t['completed_at'] ?? null),

        'assignedMechanic' => trim($t['assigned_mechanic'] ?? '') ?: null,
        'repairStartDate'  => isoTs($t['repair_start_date'] ?? null),
        'repairEndDate'    => isoTs($t['repair_end_date'] ?? null),
        'repairNotes'      => trim($t['repair_notes'] ?? '') ?: null,

        'operatorComment'  => trim($t['operatorComment'] ?? '') ?: null,
        'userResponse'     => $t['user_response'] ?? null,
        'rescheduleDate'   => isoTs($t['reschedule_date'] ?? null),
        'rescheduleComment'=> trim($t['reschedule_comment'] ?? '') ?: null,

        'reviewStars'   => $t['review_stars'] ?? null,
        'reviewComment' => trim($t['review_comment'] ?? '') ?: null,

        'images'      => $images,
        'notes'       => $notes,
        'systemLogs'  => $systemLogs,
        'activityLog' => $activityLog,

        'completionSignature' => !empty($t['completion_signature']) ? true : false,
        'signatureDate'       => isoTs($t['signature_date'] ?? null),

        'createdAt' => isoTs($t['created_at'] ?? null),
    ];
}

// ─── 8. Output ──────────────────────────────────────────────────────────
$payload = [
    'exportedAt' => gmdate('Y-m-d\TH:i:s\Z'),
    'totalCases' => count($cases),
    'cases'      => $cases,
];

$json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$filename = 'export_full_' . date('Y-m-d_His') . '.json';

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
