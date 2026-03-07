<?php
/**
 * Export services & parts data for all cases as JSON.
 * Skips cases with no services AND no parts.
 *
 * Usage: php export_services_parts.php      (writes file to disk)
 *   or   open in browser while logged in    (sends JSON download)
 */

require_once __DIR__ . '/config.php';

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

// ── Helpers ─────────────────────────────────────────────────────────────

function safeJson($raw): array {
    if (is_array($raw)) return $raw;
    if (empty($raw) || $raw === 'null') return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

/**
 * Detect whether a string is primarily Georgian (Unicode range U+10A0–U+10FF).
 */
function isGeorgian(string $s): bool {
    return (bool) preg_match('/[\x{10A0}-\x{10FF}]/u', $s);
}

/**
 * Generate a snake_case key from a name string.
 * e.g. "Bumper Repair" → "bumper_repair"
 */
function toKey(string $name): string {
    // Transliterate Georgian → Latin (basic) or strip if not transliterable
    $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $name);
    if (empty($ascii)) $ascii = $name;
    $ascii = strtolower(trim($ascii));
    $ascii = preg_replace('/[^a-z0-9]+/', '_', $ascii);
    $ascii = trim($ascii, '_');
    return $ascii ?: 'service';
}

/**
 * Guess service category from its name (best-effort heuristic).
 */
function guessCategory(string $name): string {
    $lower = mb_strtolower($name, 'UTF-8');

    // Body work keywords (EN + KA)
    if (preg_match('/bumper|fender|dent|body|panel|door|hood|trunk|roof|quarter|sill|კარი|ბამპერი|თუნუქი|კაპოტი|საბარგული|სახურავი/ui', $lower)) {
        return 'body_work';
    }
    // Paint keywords
    if (preg_match('/paint|spray|lacquer|primer|clear\s*coat|color|polish|შეღებვა|ლაქი|პრაიმერი|პოლირება|საღებავი|შეფუთვა/ui', $lower)) {
        return 'paint';
    }
    // Mechanical keywords
    if (preg_match('/engine|motor|transmission|brake|suspension|wheel|align|oil|filter|belt|clutch|ძრავი|მუხრუჭი|საკიდარი|ფილტრი|ზეთი|ტრანსმისია/ui', $lower)) {
        return 'mechanical';
    }
    // Electrical
    if (preg_match('/electr|wiring|sensor|lamp|light|headlight|fuse|battery|ელექტრ|ნათურა|ფარი|სენსორი|აკუმულატორი/ui', $lower)) {
        return 'electrical';
    }
    // Glass
    if (preg_match('/glass|windshield|mirror|მინა|სარკე/ui', $lower)) {
        return 'glass';
    }
    // Disassembly / Assembly
    if (preg_match('/disassembl|assembl|დაშლა|აწყობა/ui', $lower)) {
        return 'assembly';
    }

    return 'general';
}

// ── 1. Fetch all transfers (id, slug, amount, parts/labor JSON) ─────────
$transfers = $pdo->query("
    SELECT id, slug, amount, repair_parts, repair_labor
    FROM transfers
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── 2. Fetch active case_versions keyed by transfer_id ──────────────────
$versionsMap = [];
try {
    $rows = $pdo->query("
        SELECT transfer_id, repair_parts, repair_labor
        FROM case_versions
        WHERE is_active = 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $versionsMap[(int)$r['transfer_id']] = $r;
    }
} catch (Exception $e) {
    // table may not exist
}

// ── 3. Build export ─────────────────────────────────────────────────────
$cases = [];

foreach ($transfers as $t) {
    $id   = (int)$t['id'];
    $slug = $t['slug'] ?: ('CASE-' . str_pad($id, 3, '0', STR_PAD_LEFT));

    // Prefer active version's items over base transfer
    $srcParts = isset($versionsMap[$id]) ? $versionsMap[$id]['repair_parts'] : $t['repair_parts'];
    $srcLabor = isset($versionsMap[$id]) ? $versionsMap[$id]['repair_labor'] : $t['repair_labor'];

    $rawParts = safeJson($srcParts);
    $rawLabor = safeJson($srcLabor);

    // Skip if both empty
    if (empty($rawParts) && empty($rawLabor)) continue;

    // ── Format services (from repair_labor) ─────────────────────────
    $services = [];
    foreach ($rawLabor as $l) {
        $name = trim($l['description'] ?? $l['name'] ?? '');
        if ($name === '') continue;

        $nameEn = '';
        $nameKa = '';
        if (isGeorgian($name)) {
            $nameKa = $name;
            // If there's no separate English name, duplicate into nameEn as well
            $nameEn = $name;
        } else {
            $nameEn = $name;
        }

        $qty   = max(1, intval($l['quantity'] ?? 1));
        $rate  = round(floatval($l['unit_rate'] ?? $l['price'] ?? 0), 2);
        $price = round($rate * $qty, 2);

        $services[] = [
            'key'      => toKey($name),
            'nameEn'   => $nameEn,
            'nameKa'   => $nameKa,
            'price'    => $price,
            'count'    => $qty,
            'category' => guessCategory($name),
        ];
    }

    // ── Format parts (from repair_parts) ────────────────────────────
    $parts = [];
    foreach ($rawParts as $p) {
        $name = trim($p['name'] ?? '');
        if ($name === '') continue;

        $nameKa = '';
        if (isGeorgian($name)) {
            $nameKa = $name;
        }

        $parts[] = [
            'name'       => $name,
            'nameKa'     => $nameKa,
            'partNumber' => trim($p['sku'] ?? $p['partNumber'] ?? $p['oem'] ?? '') ?: null,
            'quantity'    => max(1, intval($p['quantity'] ?? 1)),
            'unitPrice'  => round(floatval($p['unit_price'] ?? $p['price'] ?? 0), 2),
        ];
    }

    // After filtering blanks, re-check
    if (empty($services) && empty($parts)) continue;

    $cases[] = [
        'slug'     => $slug,
        'services' => $services,
        'parts'    => $parts,
        'amount'   => round(floatval($t['amount'] ?? 0), 2),
    ];
}

// ── 4. Build final payload ──────────────────────────────────────────────
$payload = [
    'exportDate' => gmdate('Y-m-d\TH:i:s\Z'),
    'totalCases' => count($cases),
    'cases'      => $cases,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ── 5. Output ───────────────────────────────────────────────────────────
$filename = 'export_services_parts_' . date('Y-m-d_His') . '.json';

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
