<?php
/**
 * Export case completion data: status, dates, repair progress,
 * signature, review, assigned technician, and time tracking.
 *
 * Usage: php export_completion.php     (writes file to disk)
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

function isoDate($v): ?string {
    if (empty($v) || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return null;
    $ts = strtotime($v);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

// ── 1. Status lookup ────────────────────────────────────────────────────
$statusesExist = false;
try {
    $tc = $pdo->query("SHOW TABLES LIKE 'statuses'");
    $statusesExist = ($tc->rowCount() > 0);
} catch (Exception $e) {}

// ── 2. User lookup (for technician names) ───────────────────────────────
$userNames = [];
try {
    $rows = $pdo->query("SELECT id, full_name, username FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $userNames[(int)$r['id']] = trim($r['full_name'] ?: $r['username'] ?: ('User #' . $r['id']));
    }
} catch (Exception $e) {}

$validStages = [
    'backlog', 'disassembly', 'body_work',
    'processing_for_painting', 'preparing_for_painting',
    'painting', 'assembling', 'done',
];

// ── 3. Fetch transfers ──────────────────────────────────────────────────
if ($statusesExist) {
    $sql = "
        SELECT t.id, t.slug, t.plate, t.name,
               t.status, t.status_id,
               t.repair_stage, t.repair_status, t.repair_start_date, t.repair_end_date,
               t.repair_assignments, t.assigned_mechanic,
               t.stage_statuses, t.stage_timers, t.work_times,
               t.repair_labor,
               t.completed_at, t.completion_signature, t.signature_date,
               t.service_date, t.due_date,
               t.review_stars, t.review_comment,
               t.user_response, t.created_at, t.updated_at,
               COALESCE(s.name, t.status) AS resolved_status
        FROM transfers t
        LEFT JOIN statuses s ON t.status_id = s.id
        ORDER BY t.id ASC
    ";
} else {
    $sql = "
        SELECT t.id, t.slug, t.plate, t.name,
               t.status, t.status_id,
               t.repair_stage, t.repair_status, t.repair_start_date, t.repair_end_date,
               t.repair_assignments, t.assigned_mechanic,
               t.stage_statuses, t.stage_timers, t.work_times,
               t.repair_labor,
               t.completed_at, t.completion_signature, t.signature_date,
               t.service_date, t.due_date,
               t.review_stars, t.review_comment,
               t.user_response, t.created_at, t.updated_at,
               t.status AS resolved_status
        FROM transfers t
        ORDER BY t.id ASC
    ";
}

// ── Active case_versions (prefer version's services) ────────────────────
$versionsMap = [];
try {
    foreach ($pdo->query("SELECT transfer_id, repair_labor FROM case_versions WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $versionsMap[(int)$r['transfer_id']] = $r;
    }
} catch (Exception $e) {}
$transfers = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ── 4. Build export ─────────────────────────────────────────────────────
$cases = [];

foreach ($transfers as $t) {
    $id   = (int)$t['id'];
    $slug = $t['slug'] ?: ('CASE-' . str_pad($id, 3, '0', STR_PAD_LEFT));

    $statusRaw   = $t['resolved_status'] ?? $t['status'] ?? 'New';
    $isCompleted = (stripos($statusRaw, 'complet') !== false || stripos($statusRaw, 'done') !== false || stripos($statusRaw, 'დასრულ') !== false);

    // ── Technicians from repair_assignments ──────────────────────────
    $assignments      = safeJson($t['repair_assignments'] ?? '{}');
    $assignedMechanic = trim($t['assigned_mechanic'] ?? '') ?: null;
    $technicians = [];
    foreach ($assignments as $stage => $techId) {
        if (!in_array($stage, $validStages, true)) continue;
        $techId = intval($techId);
        if ($techId <= 0) continue;
        $technicians[] = [
            'fullName' => $userNames[$techId] ?? ('Technician #' . $techId),
            'userId'   => $techId,
            'stage'    => $stage,
        ];
    }

    // ── Services with quantities ──────────────────────────────────────
    $ver = $versionsMap[$id] ?? null;
    $rawLabor = safeJson($ver['repair_labor'] ?? $t['repair_labor'] ?? '[]');
    $services = [];
    foreach ($rawLabor as $l) {
        $name = trim($l['description'] ?? $l['name'] ?? '');
        if ($name === '') continue;
        $qty  = max(1, intval($l['quantity'] ?? 1));
        $rate = round(floatval($l['unit_rate'] ?? $l['price'] ?? 0), 2);
        $services[] = [
            'name'      => $name,
            'quantity'  => $qty,
            'unitPrice' => $rate,
            'lineTotal' => round($rate * $qty, 2),
        ];
    }

    // ── Stage statuses & timers ─────────────────────────────────────
    $stageStatuses = safeJson($t['stage_statuses'] ?? '{}');
    $stageTimers   = safeJson($t['stage_timers'] ?? '{}');
    $workTimes     = safeJson($t['work_times'] ?? '{}');

    // ── Duration calculation ────────────────────────────────────────
    $createdTs   = strtotime($t['created_at'] ?? '');
    $completedTs = strtotime($t['completed_at'] ?? '');
    $durationDays = null;
    if ($createdTs && $completedTs && $completedTs > $createdTs) {
        $durationDays = round(($completedTs - $createdTs) / 86400, 1);
    }

    $cases[] = [
        'slug'           => $slug,
        'caseId'         => $id,
        'plate'          => trim($t['plate'] ?? '') ?: null,
        'customerName'   => trim($t['name'] ?? '') ?: null,
        'status'         => $statusRaw,
        'isCompleted'    => $isCompleted,
        'completedAt'    => isoTs($t['completed_at'] ?? null),

        'services' => $services,

        'dates' => [
            'createdAt'      => isoTs($t['created_at'] ?? null),
            'scheduledDate'  => isoDate($t['service_date'] ?? null),
            'dueDate'        => isoDate($t['due_date'] ?? null),
            'repairStarted'  => isoTs($t['repair_start_date'] ?? null),
            'repairEnded'    => isoTs($t['repair_end_date'] ?? null),
            'completedAt'    => isoTs($t['completed_at'] ?? null),
            'updatedAt'      => isoTs($t['updated_at'] ?? null),
            'durationDays'   => $durationDays,
        ],

        'repairProgress' => [
            'currentStage'  => $t['repair_stage'] ?? null,
            'repairStatus'  => $t['repair_status'] ?? null,
            'stageStatuses' => !empty($stageStatuses) ? $stageStatuses : null,
            'stageTimers'   => !empty($stageTimers) ? $stageTimers : null,
            'workTimes'     => !empty($workTimes) ? $workTimes : null,
        ],

        'assignedMechanic' => $assignedMechanic,
        'technicians'      => $technicians,

        'signature' => [
            'signed'        => !empty($t['completion_signature']),
            'signatureDate' => isoTs($t['signature_date'] ?? null),
        ],

        'review' => [
            'stars'          => $t['review_stars'] !== null ? (int)$t['review_stars'] : null,
            'comment'        => trim($t['review_comment'] ?? '') ?: null,
            'userResponse'   => $t['user_response'] ?? null,
        ],
    ];
}

// ── 5. Summary stats ────────────────────────────────────────────────────
$completedCount = 0;
$signedCount    = 0;
$reviewedCount  = 0;
$totalDuration  = 0;
$durationCount  = 0;

foreach ($cases as $c) {
    if ($c['isCompleted']) $completedCount++;
    if ($c['signature']['signed']) $signedCount++;
    if ($c['review']['stars'] !== null) $reviewedCount++;
    if ($c['dates']['durationDays'] !== null) {
        $totalDuration += $c['dates']['durationDays'];
        $durationCount++;
    }
}

// ── 6. Output ───────────────────────────────────────────────────────────
$payload = [
    'exportedAt' => gmdate('Y-m-d\TH:i:s\Z'),
    'totalCases' => count($cases),
    'summary' => [
        'completedCases'    => $completedCount,
        'signedCases'       => $signedCount,
        'reviewedCases'     => $reviewedCount,
        'avgDurationDays'   => $durationCount > 0 ? round($totalDuration / $durationCount, 1) : null,
    ],
    'cases' => $cases,
];

$json     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$filename = 'export_completion_' . date('Y-m-d_His') . '.json';

if ($isCli) {
    $path = __DIR__ . '/' . $filename;
    file_put_contents($path, $json);
    echo "Exported {$payload['totalCases']} cases → $path (" . round(strlen($json) / 1024, 1) . " KB)\n";
    echo "Completed: $completedCount | Signed: $signedCount | Reviewed: $reviewedCount\n";
    if ($durationCount > 0) echo "Avg duration: " . round($totalDuration / $durationCount, 1) . " days\n";
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
