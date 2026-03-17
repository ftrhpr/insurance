<?php
/**
 * Combined export: services, parts, discounts & technicians for all cases.
 * Merges data from both export_services_parts.php and export_discounts_techs.php.
 * Includes all cases that have at least one service, part, discount, or technician.
 *
 * Usage: php export_combined.php     (writes file to disk)
 *   or   open in browser while logged in    (sends JSON download)
 */

require_once __DIR__ . '/config.php';

$isCli = (php_sapi_name() === 'cli');

// ── DB ──────────────────────────────────────────────────────────────────
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

function isGeorgian(string $s): bool {
    return (bool) preg_match('/[\x{10A0}-\x{10FF}]/u', $s);
}

function toKey(string $name): string {
    $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name);
    if (empty($ascii)) $ascii = $name;
    $ascii = strtolower(trim($ascii));
    $ascii = preg_replace('/[^a-z0-9]+/', '_', $ascii);
    return trim($ascii, '_') ?: 'service';
}

function guessCategory(string $name): string {
    $lower = mb_strtolower($name, 'UTF-8');

    if (preg_match('/bumper|fender|dent|body|panel|door|hood|trunk|roof|quarter|sill|კარი|ბამპერი|თუნუქი|კაპოტი|საბარგული|სახურავი/ui', $lower))
        return 'body_work';
    if (preg_match('/paint|spray|lacquer|primer|clear\s*coat|color|polish|შეღებვა|ლაქი|პრაიმერი|პოლირება|საღებავი|შეფუთვა/ui', $lower))
        return 'paint';
    if (preg_match('/engine|motor|transmission|brake|suspension|wheel|align|oil|filter|belt|clutch|ძრავი|მუხრუჭი|საკიდარი|ფილტრი|ზეთი|ტრანსმისია/ui', $lower))
        return 'mechanical';
    if (preg_match('/electr|wiring|sensor|lamp|light|headlight|fuse|battery|ელექტრ|ნათურა|ფარი|სენსორი|აკუმულატორი/ui', $lower))
        return 'electrical';
    if (preg_match('/glass|windshield|mirror|მინა|სარკე/ui', $lower))
        return 'glass';
    if (preg_match('/disassembl|assembl|დაშლა|აწყობა/ui', $lower))
        return 'assembly';

    return 'general';
}

// Valid workflow stages
$validStages = [
    'backlog', 'disassembly', 'body_work',
    'processing_for_painting', 'preparing_for_painting',
    'painting', 'assembling', 'done',
];

// ── 1. Build user ID → full_name map ────────────────────────────────────
$userNames = [];
try {
    $rows = $pdo->query("SELECT id, full_name, username FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $userNames[(int)$r['id']] = trim($r['full_name'] ?: $r['username'] ?: ('User #' . $r['id']));
    }
} catch (Exception $e) {}

// ── 2. Fetch transfers ──────────────────────────────────────────────────
$transfers = $pdo->query("
    SELECT id, slug, amount,
           repair_parts, repair_labor, repair_assignments,
           parts_discount_percent, services_discount_percent, global_discount_percent
    FROM transfers
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── 3. Fetch active case_versions ───────────────────────────────────────
$versionsMap = [];
try {
    $rows = $pdo->query("
        SELECT transfer_id, repair_parts, repair_labor,
               parts_discount_percent, services_discount_percent, global_discount_percent
        FROM case_versions
        WHERE is_active = 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $versionsMap[(int)$r['transfer_id']] = $r;
    }
} catch (Exception $e) {}

// ── 4. Build export ─────────────────────────────────────────────────────
$cases = [];

foreach ($transfers as $t) {
    $id   = (int)$t['id'];
    $slug = $t['slug'] ?: ('CASE-' . str_pad($id, 3, '0', STR_PAD_LEFT));

    // Prefer active version for items & category discounts
    $ver = $versionsMap[$id] ?? null;

    $partsDiscount    = round(floatval($ver['parts_discount_percent']    ?? $t['parts_discount_percent']    ?? 0), 2);
    $servicesDiscount = round(floatval($ver['services_discount_percent'] ?? $t['services_discount_percent'] ?? 0), 2);
    $globalDiscount   = round(floatval($ver['global_discount_percent']   ?? $t['global_discount_percent']   ?? 0), 2);

    $rawParts = safeJson($ver['repair_parts'] ?? $t['repair_parts']);
    $rawLabor = safeJson($ver['repair_labor'] ?? $t['repair_labor']);

    // ── Services (from repair_labor) ────────────────────────────────
    $services = [];
    $hasItemDiscount = false;
    foreach ($rawLabor as $l) {
        $name = trim($l['description'] ?? $l['name'] ?? '');
        if ($name === '') continue;

        $nameEn = '';
        $nameKa = '';
        if (isGeorgian($name)) {
            $nameKa = $name;
            $nameEn = $name;
        } else {
            $nameEn = $name;
        }

        $qty       = max(1, intval($l['quantity'] ?? 1));
        $unitPrice = round(floatval($l['unit_rate'] ?? $l['unit_price'] ?? $l['price'] ?? 0), 2);
        $price     = round($unitPrice * $qty, 2);
        $disc      = round(floatval($l['discount_percent'] ?? 0), 2);
        if ($disc > 0) $hasItemDiscount = true;

        $services[] = [
            'key'       => toKey($name),
            'nameEn'    => $nameEn,
            'nameKa'    => $nameKa,
            'unitPrice' => $unitPrice,
            'count'     => $qty,
            'price'     => $price,
            'category'  => guessCategory($name),
            'discount'  => $disc,
        ];
    }

    // ── Parts (from repair_parts) ───────────────────────────────────
    $parts = [];
    foreach ($rawParts as $p) {
        $name = trim($p['name'] ?? '');
        if ($name === '') continue;

        $nameKa = '';
        if (isGeorgian($name)) {
            $nameKa = $name;
        }

        $qty       = max(1, intval($p['quantity'] ?? 1));
        $unitPrice = round(floatval($p['unit_price'] ?? $p['price'] ?? 0), 2);
        $disc      = round(floatval($p['discount_percent'] ?? 0), 2);
        if ($disc > 0) $hasItemDiscount = true;

        $parts[] = [
            'name'       => $name,
            'nameKa'     => $nameKa,
            'partNumber' => trim($p['sku'] ?? $p['partNumber'] ?? $p['oem'] ?? '') ?: null,
            'quantity'   => $qty,
            'unitPrice'  => $unitPrice,
            'discount'   => $disc,
        ];
    }

    // ── Technicians (from repair_assignments) ───────────────────────
    $assignments = safeJson($t['repair_assignments']);
    $technicians = [];
    foreach ($assignments as $stage => $techId) {
        if (!in_array($stage, $validStages, true)) continue;
        $techId = intval($techId);
        if ($techId <= 0) continue;
        $technicians[] = [
            'fullName' => $userNames[$techId] ?? ('Technician #' . $techId),
            'stage'    => $stage,
        ];
    }

    // ── Skip truly empty cases ──────────────────────────────────────
    $hasCategoryDiscount = ($partsDiscount > 0 || $servicesDiscount > 0 || $globalDiscount > 0);
    $hasTechs    = !empty($technicians);
    $hasServices = !empty($services);
    $hasParts    = !empty($parts);

    if (!$hasServices && !$hasParts && !$hasCategoryDiscount && !$hasItemDiscount && !$hasTechs) continue;

    // ── Build entry ─────────────────────────────────────────────────
    $cases[] = [
        'slug'                    => $slug,
        'amount'                  => round(floatval($t['amount'] ?? 0), 2),
        'servicesDiscountPercent' => $servicesDiscount ?: null,
        'partsDiscountPercent'    => $partsDiscount    ?: null,
        'globalDiscountPercent'   => $globalDiscount   ?: null,
        'services'                => $services,
        'parts'                   => $parts,
        'technicians'             => $technicians,
    ];
}

// ── 5. Output ───────────────────────────────────────────────────────────
$payload = [
    'exportDate' => gmdate('Y-m-d\TH:i:s\Z'),
    'totalCases' => count($cases),
    'cases'      => $cases,
];

$json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$filename = 'export_combined_' . date('Y-m-d_His') . '.json';

if ($isCli) {
    $path = __DIR__ . '/' . $filename;
    file_put_contents($path, $json);
    echo "Exported {$payload['totalCases']} cases to $path (" . round(strlen($json) / 1024, 1) . " KB)\n";
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
