<?php
/**
 * Export case types (insurance vs retail) for ALL cases.
 *
 * Usage: php export_casetypes.php           (writes file to disk)
 *   or   open in browser while logged in    (sends JSON download)
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

// ── Fetch all transfers ─────────────────────────────────────────────────
$rows = $pdo->query("
    SELECT id, slug, plate, case_type
    FROM transfers
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Map case_type to "insurance" | "retail" ─────────────────────────────
// Source DB uses: "დაზღვევა" (insurance), "საცალო" (retail/individual)
function mapCaseType(?string $ct): string {
    if ($ct === null || $ct === '') return 'retail';
    $ct = trim($ct);
    $lower = mb_strtolower($ct, 'UTF-8');
    if ($ct === 'დაზღვევა' || $lower === 'insurance' || $lower === 'ინშურანსი' || $lower === 'corporate') {
        return 'insurance';
    }
    return 'retail';
}

$cases = [];
foreach ($rows as $r) {
    $cases[] = [
        'slug'     => $r['slug'] ?: ('CASE-' . str_pad((int)$r['id'], 3, '0', STR_PAD_LEFT)),
        'plate'    => $r['plate'] ?? '',
        'caseType' => mapCaseType($r['case_type']),
    ];
}

$payload = [
    'exportedAt' => gmdate('Y-m-d\TH:i:s.000\Z'),
    'totalCases' => count($cases),
    'cases'      => $cases,
];

$json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$filename = 'export_casetypes_' . date('Y-m-d_His') . '.json';

if ($isCli) {
    $path = __DIR__ . '/' . $filename;
    file_put_contents($path, $json);
    echo "Exported " . count($cases) . " cases → $path (" . round(strlen($json) / 1024, 1) . " KB)\n";
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
