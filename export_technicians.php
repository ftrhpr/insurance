<?php
/**
 * Export ALL technicians and ALL case–technician assignments.
 *
 * Usage: php export_technicians.php         (writes file to disk)
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

$validStages = [
    'backlog', 'disassembly', 'body_work',
    'processing_for_painting', 'preparing_for_painting',
    'painting', 'assembling', 'done',
];

// ── 1. All technician users ─────────────────────────────────────────────
$userNames = [];
$techList  = [];
try {
    $rows = $pdo->query("SELECT id, full_name, username, role FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $name = trim($r['full_name'] ?: $r['username'] ?: ('User #' . $r['id']));
        $userNames[(int)$r['id']] = $name;
        if ($r['role'] === 'technician') {
            $techList[] = ['fullName' => $name];
        }
    }
} catch (Exception $e) {}

// ── 2. All cases with assignments ───────────────────────────────────────
$transfers = $pdo->query("
    SELECT id, slug, repair_assignments
    FROM transfers
    WHERE repair_assignments IS NOT NULL AND repair_assignments != '' AND repair_assignments != '{}'
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$assignments = [];
foreach ($transfers as $t) {
    $slug = $t['slug'] ?: ('CASE-' . str_pad((int)$t['id'], 3, '0', STR_PAD_LEFT));
    $raw  = json_decode($t['repair_assignments'] ?? '{}', true);
    if (!is_array($raw) || empty($raw)) continue;

    $techs = [];
    foreach ($raw as $stage => $techId) {
        if (!in_array($stage, $validStages, true)) continue;
        $techId = intval($techId);
        if ($techId <= 0) continue;
        $techs[] = [
            'fullName' => $userNames[$techId] ?? ('Technician #' . $techId),
            'stage'    => $stage,
        ];
    }
    if (empty($techs)) continue;

    $assignments[] = [
        'slug'        => $slug,
        'technicians' => $techs,
    ];
}

// ── 3. Output ───────────────────────────────────────────────────────────
$payload = [
    'technicians' => $techList,
    'assignments' => $assignments,
];

$json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$filename = 'export_technicians_' . date('Y-m-d_His') . '.json';

if ($isCli) {
    $path = __DIR__ . '/' . $filename;
    file_put_contents($path, $json);
    echo "Exported " . count($techList) . " technicians, " . count($assignments) . " cases with assignments → $path (" . round(strlen($json) / 1024, 1) . " KB)\n";
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
