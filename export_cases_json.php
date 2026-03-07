<?php
/**
 * Export ALL cases with full related data as a single JSON file.
 * Deduplicates customers by phone, vehicles by plate.
 *
 * Usage: php export_cases_json.php          (writes export_cases.json)
 *   or   open in browser (sends JSON download)
 */

require_once __DIR__ . '/config.php';

// ── Detect CLI vs. web ──────────────────────────────────────────────────
$isCli = (php_sapi_name() === 'cli');

// ── DB connection ───────────────────────────────────────────────────────
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    $msg = "DB connection failed: " . $e->getMessage();
    if ($isCli) { fwrite(STDERR, $msg . "\n"); exit(1); }
    http_response_code(500);
    die(json_encode(['error' => $msg]));
}

// ── Helper: safe JSON decode ────────────────────────────────────────────
function safeJson($raw, bool $assoc = true) {
    if (is_array($raw)) return $raw;
    if (empty($raw) || $raw === 'null') return $assoc ? [] : null;
    $d = json_decode($raw, $assoc);
    return ($d !== null) ? $d : ($assoc ? [] : null);
}

// ── Helper: ISO-8601 date formatting ────────────────────────────────────
function isoDate($v): ?string {
    if (empty($v) || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return null;
    $ts = strtotime($v);
    if ($ts === false) return null;
    return date('Y-m-d', $ts);
}
function isoTimestamp($v): ?string {
    if (empty($v) || $v === '0000-00-00 00:00:00') return null;
    $ts = strtotime($v);
    if ($ts === false) return null;
    return gmdate('Y-m-d\TH:i:s\Z', $ts);
}

// ── Map case_type to english ────────────────────────────────────────────
function mapCaseType(?string $ct): string {
    if ($ct === null) return 'individual';
    $ct = trim($ct);
    // Georgian "დაზღვევა" = insurance
    if ($ct === 'დაზღვევა' || stripos($ct, 'insurance') !== false) return 'insurance';
    return 'individual';
}

// ── Map repair_stage to requested values ────────────────────────────────
function mapRepairStage(?string $stage): ?string {
    if (empty($stage)) return null;
    $map = [
        'backlog'                 => 'backlog',
        'disassembly'             => 'disassembly',
        'body_work'               => 'bodywork',
        'processing_for_painting' => 'paint',
        'preparing_for_painting'  => 'paint',
        'painting'                => 'paint',
        'assembling'              => 'assembly',
        'done'                    => 'done',
    ];
    return $map[$stage] ?? $stage;
}

// ── Map status name to closest requested value ──────────────────────────
function mapStatusName(?string $s): string {
    if (empty($s)) return 'New';
    $s = trim($s);
    $exact = [
        'Assessment', 'New', 'Processing', 'Called',
        'Parts Ordered', 'Parts Arrived', 'Scheduled',
        'Already in service', 'Completed',
    ];
    foreach ($exact as $e) {
        if (strcasecmp($s, $e) === 0) return $e;
    }
    // Fuzzy mapping for common variants
    $lower = mb_strtolower($s, 'UTF-8');
    if (str_contains($lower, 'assess') || str_contains($lower, 'შეფასება')) return 'Assessment';
    if (str_contains($lower, 'new') || str_contains($lower, 'ახალი'))       return 'New';
    if (str_contains($lower, 'process') || str_contains($lower, 'მუშავ'))   return 'Processing';
    if (str_contains($lower, 'call') || str_contains($lower, 'დარეკ'))      return 'Called';
    if (str_contains($lower, 'order') || str_contains($lower, 'შეკვეთ'))    return 'Parts Ordered';
    if (str_contains($lower, 'arrived') || str_contains($lower, 'მოვიდა'))  return 'Parts Arrived';
    if (str_contains($lower, 'schedul') || str_contains($lower, 'დაგეგმ'))  return 'Scheduled';
    if (str_contains($lower, 'service') || str_contains($lower, 'სერვის'))  return 'Already in service';
    if (str_contains($lower, 'complet') || str_contains($lower, 'done') || str_contains($lower, 'დასრულ')) return 'Completed';
    if (str_contains($lower, 'issue') || str_contains($lower, 'cancel'))    return 'Completed'; // closest
    return $s; // return as-is if no match
}

// ── Map payment method ──────────────────────────────────────────────────
function mapPaymentMethod(?string $m): string {
    if (empty($m)) return 'other';
    $m = strtolower(trim($m));
    if (in_array($m, ['cash', 'card', 'transfer'])) return $m;
    return 'other';
}

// ── 1. Fetch all transfers ──────────────────────────────────────────────
$statusesExist = false;
try {
    $tc = $pdo->query("SHOW TABLES LIKE 'statuses'");
    $statusesExist = ($tc->rowCount() > 0);
} catch (Exception $e) {}

if ($statusesExist) {
    $sql = "
        SELECT t.*,
               COALESCE(cs.name, t.status) AS resolved_status
        FROM transfers t
        LEFT JOIN statuses cs ON t.status_id = cs.id AND cs.type = 'case'
        ORDER BY t.id ASC
    ";
} else {
    $sql = "SELECT t.*, t.status AS resolved_status FROM transfers t ORDER BY t.id ASC";
}
$transfers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ── 2. Fetch all payments keyed by transfer_id ──────────────────────────
$paymentsMap = [];
$totalPaidMap = [];
try {
    // Detect available columns
    $payCols = array_column(
        $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'payments'")->fetchAll(PDO::FETCH_ASSOC),
        'COLUMN_NAME'
    );
    $hasPaidAt = in_array('paid_at', $payCols);
    $hasRecordedBy = in_array('recorded_by', $payCols);

    $dateCol = $hasPaidAt ? 'paid_at' : (in_array('payment_date', $payCols) ? 'payment_date' : 'created_at');

    $paySql = "SELECT p.*, " . ($hasRecordedBy ? "u.username AS recorded_by_username, u.full_name AS recorded_by_name" : "NULL AS recorded_by_username, NULL AS recorded_by_name") . "
               FROM payments p " . ($hasRecordedBy ? "LEFT JOIN users u ON u.id = p.recorded_by" : "") . "
               ORDER BY p.$dateCol ASC";
    $rows = $pdo->query($paySql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $tid = (int)$r['transfer_id'];
        if (!isset($paymentsMap[$tid])) { $paymentsMap[$tid] = []; $totalPaidMap[$tid] = 0; }
        $paymentsMap[$tid][] = $r;
        $totalPaidMap[$tid] += floatval($r['amount']);
    }
} catch (Exception $e) {
    // payments table might not exist
}

// ── 3. Fetch active case_versions keyed by transfer_id ──────────────────
$versionsMap = [];
try {
    $rows = $pdo->query("SELECT * FROM case_versions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $versionsMap[(int)$r['transfer_id']] = $r;
    }
} catch (Exception $e) {}

// ── 4. Fetch vehicles table for extra info ──────────────────────────────
$vehiclesDb = [];
try {
    $rows = $pdo->query("SELECT * FROM vehicles ORDER BY plate ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $vehiclesDb[strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $r['plate']))] = $r;
    }
} catch (Exception $e) {}

// ── 5. Build export ─────────────────────────────────────────────────────
$cases = [];
$customersByPhone = [];
$vehiclesByPlate  = [];

foreach ($transfers as $t) {
    $id    = (int)$t['id'];
    $slug  = $t['slug'] ?? ('CASE-' . str_pad($id, 3, '0', STR_PAD_LEFT));
    $phone = trim($t['phone'] ?? '');
    $plate = trim($t['plate'] ?? '');

    // Normalize phone to +995...
    if ($phone && !str_starts_with($phone, '+')) {
        if (str_starts_with($phone, '995')) {
            $phone = '+' . $phone;
        } elseif (str_starts_with($phone, '5') && strlen($phone) === 9) {
            $phone = '+995' . $phone;
        } elseif (strlen($phone) >= 6) {
            $phone = '+995' . ltrim($phone, '0');
        }
    }

    // ── Customer (deduplicate by phone) ─────────────────────────────
    $customerName = trim($t['name'] ?? '');
    $customer = [
        'name'  => $customerName ?: null,
        'phone' => $phone ?: null,
        'email' => null,
        'notes' => null,
    ];
    if ($phone && isset($customersByPhone[$phone])) {
        // reuse existing reference (already deduplicated)
    } elseif ($phone) {
        $customersByPhone[$phone] = $customer;
    }

    // ── Vehicle (deduplicate by plate) ──────────────────────────────
    $normalizedPlate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate));
    $veh = $vehiclesDb[$normalizedPlate] ?? null;
    $vehicle = [
        'plate' => $plate ?: null,
        'make'  => $t['vehicle_make'] ?? null,
        'model' => $t['vehicle_model'] ?? ($veh['model'] ?? null),
        'year'  => null,
        'vin'   => null,
        'color' => null,
    ];
    if ($normalizedPlate && !isset($vehiclesByPlate[$normalizedPlate])) {
        $vehiclesByPlate[$normalizedPlate] = $vehicle;
    }

    // ── Services & Parts (prefer active version, fallback to transfer) ─
    $repairParts = [];
    $repairLabor = [];

    $partsDiscount    = null;
    $servicesDiscount = null;
    $globalDiscount   = null;
    $vatEnabled       = false;

    if (isset($versionsMap[$id])) {
        $ver = $versionsMap[$id];
        $repairParts      = safeJson($ver['repair_parts']);
        $repairLabor      = safeJson($ver['repair_labor']);
        $partsDiscount    = isset($ver['parts_discount_percent'])    ? floatval($ver['parts_discount_percent'])    : null;
        $servicesDiscount = isset($ver['services_discount_percent']) ? floatval($ver['services_discount_percent']) : null;
        $globalDiscount   = isset($ver['global_discount_percent'])   ? floatval($ver['global_discount_percent'])   : null;
        $vatEnabled       = !empty($ver['vat_enabled']);
    } else {
        $repairParts      = safeJson($t['repair_parts'] ?? '[]');
        $repairLabor      = safeJson($t['repair_labor'] ?? '[]');
        $partsDiscount    = isset($t['parts_discount_percent'])    ? floatval($t['parts_discount_percent'])    : null;
        $servicesDiscount = isset($t['services_discount_percent']) ? floatval($t['services_discount_percent']) : null;
        $globalDiscount   = isset($t['global_discount_percent'])   ? floatval($t['global_discount_percent'])   : null;
        $vatEnabled       = !empty($t['vat_enabled']);
    }

    // Format parts
    $parts = [];
    foreach ($repairParts as $p) {
        $parts[] = [
            'name'     => trim($p['name'] ?? ''),
            'nameKa'   => '',
            'price'    => floatval($p['unit_price'] ?? $p['price'] ?? 0),
            'quantity' => intval($p['quantity'] ?? 1),
        ];
    }

    // Format services (labor)
    $services = [];
    foreach ($repairLabor as $l) {
        $services[] = [
            'name'     => trim($l['description'] ?? $l['name'] ?? ''),
            'nameKa'   => '',
            'price'    => floatval($l['unit_rate'] ?? $l['price'] ?? 0),
            'quantity' => intval($l['quantity'] ?? 1),
        ];
    }

    // ── Payments ────────────────────────────────────────────────────
    $payments = [];
    $payRows  = $paymentsMap[$id] ?? [];
    foreach ($payRows as $pr) {
        $payments[] = [
            'amount'    => floatval($pr['amount']),
            'method'    => mapPaymentMethod($pr['method'] ?? null),
            'note'      => trim($pr['notes'] ?? $pr['reference'] ?? '') ?: null,
            'createdAt' => isoTimestamp($pr['paid_at'] ?? $pr['created_at'] ?? null),
        ];
    }
    $totalPaid = $totalPaidMap[$id] ?? floatval($t['amount_paid'] ?? 0);

    // ── Notes (internalNotes) ───────────────────────────────────────
    $rawNotes = safeJson($t['internalNotes'] ?? $t['internal_notes'] ?? '[]');
    $notes = [];
    foreach ($rawNotes as $n) {
        $notes[] = [
            'content'    => trim($n['text'] ?? $n['content'] ?? ''),
            'authorName' => trim($n['authorName'] ?? $n['author'] ?? 'System'),
            'createdAt'  => isoTimestamp($n['timestamp'] ?? $n['createdAt'] ?? null),
        ];
    }

    // ── Images ──────────────────────────────────────────────────────
    $images = safeJson($t['case_images'] ?? '[]');
    if (!is_array($images)) $images = [];

    // ── VAT ─────────────────────────────────────────────────────────
    $vatRate   = $vatEnabled ? floatval($t['vat_rate'] ?? 18) : null;
    $vatAmount = $vatEnabled ? floatval($t['vat_amount'] ?? 0) : null;
    if ($vatRate == 0 && !$vatEnabled) $vatRate = null;
    if ($vatAmount == 0 && !$vatEnabled) $vatAmount = null;

    // Zero-out null-ish discount values
    if ($partsDiscount !== null && $partsDiscount == 0)    $partsDiscount = null;
    if ($servicesDiscount !== null && $servicesDiscount == 0) $servicesDiscount = null;
    if ($globalDiscount !== null && $globalDiscount == 0)  $globalDiscount = null;

    // ── Status / cancellation ───────────────────────────────────────
    $statusRaw    = $t['resolved_status'] ?? $t['status'] ?? 'New';
    $cancelled    = (stripos($statusRaw, 'issue') !== false || stripos($statusRaw, 'cancel') !== false);
    $mappedStatus = mapStatusName($statusRaw);

    // ── Build case entry ────────────────────────────────────────────
    $cases[] = [
        'slug'       => $slug,
        'caseType'   => mapCaseType($t['case_type'] ?? null),
        'statusName' => $mappedStatus,
        'repairStage'=> mapRepairStage($t['repair_stage'] ?? null),
        'urgent'     => (bool)($t['urgent'] ?? false),
        'cancelled'  => $cancelled,

        'customer' => $customer,
        'vehicle'  => $vehicle,

        'amount'                 => floatval($t['amount'] ?? 0),
        'franchise'              => floatval($t['franchise'] ?? 0),
        'totalPaid'              => round($totalPaid, 2),
        'vatEnabled'             => $vatEnabled,
        'vatRate'                => $vatRate,
        'vatAmount'              => $vatAmount,
        'servicesDiscountPercent' => $servicesDiscount,
        'partsDiscountPercent'   => $partsDiscount,
        'globalDiscountPercent'  => $globalDiscount,

        'services' => $services,
        'parts'    => $parts,

        'dueDate'       => isoDate($t['due_date'] ?? null),
        'scheduledDate' => isoDate($t['service_date'] ?? null),

        'needsColorMatching'     => false,
        'colorMatchingCompleted' => false,
        'colorMixes'             => null,
        'colorFormula'           => null,
        'colorMixMarkupPercent'  => null,

        'nachrebiQty'     => ($t['nachrebi_qty'] ?? null) !== null ? floatval($t['nachrebi_qty']) : null,
        'nachrebiEntries' => [],
        'bodyWorkEntries' => [],

        'images'   => $images,
        'payments' => $payments,
        'notes'    => $notes,

        'createdAt' => isoTimestamp($t['created_at'] ?? null),
    ];
}

// ── 6. Output ───────────────────────────────────────────────────────────
$output = json_encode(['cases' => $cases], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($isCli) {
    $file = __DIR__ . '/export_cases.json';
    file_put_contents($file, $output);
    echo "Exported " . count($cases) . " cases to $file (" . round(strlen($output) / 1024, 1) . " KB)\n";
} else {
    // Auth check for web access
    session_start();
    require_once __DIR__ . '/session_config.php';
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Authentication required']));
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_cases_' . date('Y-m-d_His') . '.json"');
    header('Content-Length: ' . strlen($output));
    echo $output;
}
