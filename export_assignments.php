<?php
/**
 * Focused export: plate, status, vehicle make/model, technicians + nachrebi qty per case.
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

// ── 4. Fetch transfers ──────────────────────────────────────────────────
if ($statusesExist) {
    $sql = "
        SELECT t.id, t.slug, t.plate, t.status, t.status_id,
               t.vehicle_make, t.vehicle_model, t.repair_stage,
               t.repair_assignments, t.nachrebi_qty,
               COALESCE(s.name, t.status) AS resolved_status
        FROM transfers t
        LEFT JOIN statuses s ON t.status_id = s.id
        ORDER BY t.id ASC
    ";
} else {
    $sql = "
        SELECT t.id, t.slug, t.plate, t.status, t.status_id,
               t.vehicle_make, t.vehicle_model, t.repair_stage,
               t.repair_assignments, t.nachrebi_qty,
               t.status AS resolved_status
        FROM transfers t
        ORDER BY t.id ASC
    ";
}
$transfers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ── 5. Build export ─────────────────────────────────────────────────────
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

    $status = $t['resolved_status'] ?? $t['status'] ?? 'New';
    $nachrebiQty = ($t['nachrebi_qty'] ?? null) !== null && $t['nachrebi_qty'] !== ''
        ? round(floatval($t['nachrebi_qty']), 2)
        : null;

    // Technician assignments
    $assignments = safeJson($t['repair_assignments'] ?? '{}');
    $technicians = [];
    foreach ($assignments as $stage => $techId) {
        if (!in_array($stage, $validStages, true)) continue;
        $techId = intval($techId);
        if ($techId <= 0) continue;
        $technicians[] = [
            'fullName'    => $userNames[$techId] ?? ('Technician #' . $techId),
            'userId'      => $techId,
            'stage'       => $stage,
            'nachrebiQty' => $nachrebiQty,
        ];
    }

    $cases[] = [
        'slug'         => $slug,
        'caseId'       => $id,
        'plate'        => $plate ?: null,
        'status'       => $status,
        'repairStage'  => $t['repair_stage'] ?? null,
        'vehicleMake'  => $make ?: null,
        'vehicleModel' => $model ?: null,
        'nachrebiQty'  => $nachrebiQty,
        'technicians'  => $technicians,
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
