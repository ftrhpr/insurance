<?php
/**
 * Export ALL case–technician assignments from the database.
 * No filtering by status, discounts, role, or anything else.
 *
 * Assignments are stored in transfers.repair_assignments as JSON:
 *   {"body_work": 5, "painting": 12}  (stage → user ID)
 *
 * Usage: php export_all_assignments.php     (writes file + prints stats)
 *   or   open in browser while logged in    (sends JSON download + prints stats)
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

// ── 1. Diagnostic counts ────────────────────────────────────────────────
$totalTransfers = (int)$pdo->query("SELECT COUNT(*) FROM transfers")->fetchColumn();

// Count rows where repair_assignments has actual content
$withAssignments = (int)$pdo->query("
    SELECT COUNT(*) FROM transfers 
    WHERE repair_assignments IS NOT NULL 
      AND repair_assignments != '' 
      AND repair_assignments != '{}' 
      AND repair_assignments != 'null'
")->fetchColumn();

$stats = "=== ASSIGNMENT STATS ===\n"
       . "Total cases in DB:                $totalTransfers\n"
       . "Cases with repair_assignments:    $withAssignments\n";

// ── 2. User ID → full_name map (ALL users, no role filter) ─────────────
$userNames = [];
try {
    $rows = $pdo->query("SELECT id, full_name, username FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $userNames[(int)$r['id']] = trim($r['full_name'] ?: $r['username'] ?: ('User #' . $r['id']));
    }
} catch (Exception $e) {}

// ── 3. Fetch ALL transfers — no filters at all ──────────────────────────
$transfers = $pdo->query("
    SELECT id, slug, repair_assignments
    FROM transfers
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$output = [];
$totalAssignmentRows = 0;

foreach ($transfers as $t) {
    $raw = $t['repair_assignments'];
    if (empty($raw) || $raw === '{}' || $raw === 'null') continue;

    $assignments = json_decode($raw, true);
    if (!is_array($assignments) || empty($assignments)) continue;

    $slug = $t['slug'] ?: ('CASE-' . str_pad((int)$t['id'], 3, '0', STR_PAD_LEFT));
    $techs = [];

    foreach ($assignments as $stage => $techId) {
        if (!in_array($stage, $validStages, true)) continue;
        $techId = intval($techId);
        if ($techId <= 0) continue;

        $techs[] = [
            'fullName' => $userNames[$techId] ?? ('Technician #' . $techId),
            'stage'    => $stage,
        ];
        $totalAssignmentRows++;
    }

    if (empty($techs)) continue;

    $output[] = [
        'slug'        => $slug,
        'technicians' => $techs,
    ];
}

$stats .= "Cases with valid stage assignments: " . count($output) . "\n"
        . "Total assignment rows (stage→tech): $totalAssignmentRows\n"
        . "============================\n";

// ── 4. Output ───────────────────────────────────────────────────────────
$json     = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$filename = 'export_all_assignments_' . date('Y-m-d_His') . '.json';

if ($isCli) {
    echo $stats;
    $path = __DIR__ . '/' . $filename;
    file_put_contents($path, $json);
    echo "Written to $path (" . round(strlen($json) / 1024, 1) . " KB)\n";
} else {
    session_start();
    require_once __DIR__ . '/session_config.php';
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Authentication required']));
    }

    // If ?stats is passed, show stats page instead of downloading
    if (isset($_GET['stats'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $stats;
        echo "\nJSON preview (first 2 entries):\n";
        echo json_encode(array_slice($output, 0, 2), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    echo $json;
}
