<?php
session_start();
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

$stages = [
    ['id' => 'backlog', 'title' => __('workflow.stage.backlog', 'Backlog')],
    ['id' => 'disassembly', 'title' => __('workflow.stage.disassembly', 'Disassembly')],
    ['id' => 'body_work', 'title' => __('workflow.stage.body_work', 'Body Work')],
    ['id' => 'processing_for_painting', 'title' => __('workflow.stage.processing_for_painting', 'Processing for Painting')],
    ['id' => 'preparing_for_painting', 'title' => __('workflow.stage.preparing_for_painting', 'Preparing for Painting')],
    ['id' => 'painting', 'title' => __('workflow.stage.painting', 'Painting')],
    ['id' => 'assembling', 'title' => __('workflow.stage.assembling', 'Assembling')],
];
$stage_titles = array_column($stages, 'title', 'id');

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Support JSON polling for assigned cases
if (isset($_GET['json'])) {
    $stmt = $pdo->query("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses, urgent FROM transfers WHERE status IN ('Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled') ORDER BY id DESC");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $assigned = [];
    foreach ($cases as $c) {
        $assignments = json_decode($c['repair_assignments'] ?? '{}', true);
        $statuses = json_decode($c['stage_statuses'] ?? '{}', true);
        $timers = json_decode($c['stage_timers'] ?? '{}', true);
        foreach ($assignments as $stage => $techId) {
            if (intval($techId) === intval($userId) && ($statuses[$stage] ?? null) !== 'finished' && !empty($timers[$stage])) {
                $assigned[] = [
                    'id' => $c['id'],
                    'plate' => $c['plate'],
                    'vehicle_make' => $c['vehicle_make'],
                    'vehicle_model' => $c['vehicle_model'],
                    'stage' => $stage,
                    'stage_title' => $stage_titles[$stage] ?? $stage,
                    'status' => $statuses[$stage] ?? null,
                    'timer' => $timers[$stage] ?? null,
                    'urgent' => $c['urgent'] ?? 0
                ];
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['cases' => $assigned, 'now' => time() * 1000]);
    exit;
}

// Fetch active cases (we'll filter by assignment in PHP for simplicity)
$stmt = $pdo->query("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses, urgent FROM transfers WHERE status IN ('Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled') ORDER BY id DESC");
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Filter assigned to current user
$assigned = [];
foreach ($cases as $c) {
    $assignments = json_decode($c['repair_assignments'] ?? '{}', true);
    $statuses = json_decode($c['stage_statuses'] ?? '{}', true);
    $timers = json_decode($c['stage_timers'] ?? '{}', true);
    foreach ($assignments as $stage => $techId) {
        if (intval($techId) === intval($userId) && ($statuses[$stage] ?? null) !== 'finished' && !empty($timers[$stage])) {
            $assigned[] = [
                'id' => $c['id'],
                'plate' => $c['plate'],
                'vehicle_make' => $c['vehicle_make'],
                'vehicle_model' => $c['vehicle_model'],
                'stage' => $stage,
                'stage_title' => $stage_titles[$stage] ?? $stage,
                'status' => $statuses[$stage] ?? null,
                'timer' => $timers[$stage] ?? null,
                'urgent' => $c['urgent'] ?? 0
            ];
        }
    }
}

// Admin helper: show server-side assigned debug (safe for admins only)
$server_assigned_debug = '';
if (in_array($_SESSION['role'] ?? '', ['admin'])) {
    $server_assigned_debug = json_encode($assigned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?>
<!doctype html>
<html lang="<?php echo get_current_language(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo __('technician.dashboard.title', 'Technician Dashboard'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .blink { animation: finishedBlink 1.2s infinite; }
        @keyframes finishedBlink { 0%{transform:translateY(0);}50%{transform:translateY(-2px);}100%{transform:translateY(0);} }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="w-full max-w-md mx-auto" x-data="techDashboard()" x-init="init()">
        <header class="p-4 bg-white sticky top-0 z-20 border-b border-slate-200">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-lg font-semibold"><?php echo __('technician.dashboard.header', 'Your Assigned Work'); ?></div>
                    <div class="text-xs text-slate-500">Logged in: <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></strong></div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="text-sm text-slate-600">Assigned: <span class="font-semibold" x-text="cases.length"></span></div>
                    <button @click="refresh()" aria-label="Refresh list" class="px-3 py-2 rounded bg-sky-500 text-white">Refresh</button>
                </div>
            </div>
        </header>

        <main class="p-4 pb-28 space-y-4">
            <!-- Admin server-side debug: prints assigned cases JSON -->
            <?php if (!empty($server_assigned_debug)): ?>
                <details class="mb-3 p-3 bg-slate-50 rounded text-xs text-slate-700"> 
                    <summary class="font-semibold">Server-side assigned (admin debug)</summary>
                    <pre class="mt-2 text-xs overflow-auto" style="max-height:240px"><?php echo htmlspecialchars($server_assigned_debug); ?></pre>
                </details>
            <?php endif; ?>

            <template x-if="cases.length === 0">
                <div class="p-4 bg-white rounded shadow text-center">No assigned cases</div>
            </template>

            <!-- Grouped view: one card per case with per-stage rows -->
            <template x-for="caseGroup in groupedCases()" :key="caseGroup.id">
                <article class="bg-white rounded-xl shadow p-4 touch-manipulation" :class="{'opacity-60': caseGroup.stages.every(s=>s.status && s.status.status === 'finished')}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="text-lg font-bold truncate" x-text="caseGroup.title"></div>
                            <div class="text-sm text-slate-500 mt-1 truncate" x-text="`#${caseGroup.id}`"></div>
                            <div class="mt-3 flex gap-2 flex-wrap">
                                <template x-for="stage in caseGroup.stages" :key="stage.stage">
                                    <div class="inline-flex items-center gap-2 bg-amber-100 text-amber-800 rounded-full px-3 py-1 text-sm font-semibold">
                                        <span x-text="stage.stage_title"></span>
                                        <span class="font-mono text-sm" x-text="displayTimer(stage.timer)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-2">
                        <template x-for="stage in caseGroup.stages" :key="stage.stage">
                            <div class="flex gap-2">
                                <button x-show="!(stage.status && stage.status.status === 'finished')" @click="finishStage(caseGroup.id, stage.stage)" class="flex-1 h-12 rounded-md bg-emerald-600 text-white text-lg font-semibold touch-target">Finish</button>
                                <div x-show="stage.status && stage.status.status === 'finished'" class="w-36 h-12 rounded-md bg-green-100 text-green-800 text-center font-semibold flex items-center justify-center">Finished</div>
                            </div>
                        </template>
                    </div>
                </article>
            </template>

            <!-- Admin debug: show raw cases for troubleshooting -->
            <template x-if="cases.length === 0 && <?php echo in_array($_SESSION['role'] ?? '', ['admin']) ? 'true' : 'false'; ?>">
                <div class="mt-4 p-3 bg-yellow-50 rounded text-sm text-slate-700">No assigned cases found. Admins: try refreshing or check assignments in <a href="users.php">Users</a> or <a href="workflow.php">Workflow</a>.</div>
            </template>
        </main>

        <!-- Bottom action bar for one-handed use -->
        <nav class="fixed bottom-0 left-0 right-0 bg-white/95 border-t border-slate-200 p-3 flex items-center justify-between md:hidden">
            <div class="text-sm text-slate-600">Tasks: <span class="font-semibold" x-text="cases.length"></span></div>
            <div class="flex gap-2">
                <button @click="refresh()" class="px-4 py-2 rounded bg-sky-500 text-white">Refresh</button>
                <a href="logout.php" class="px-4 py-2 rounded bg-red-50 text-red-600">Logout</a>
            </div>
        </nav>
    </div>

    <script>
        function techDashboard() {
            return {
                cases: <?php echo json_encode($assigned); ?>,
                init() {
                    // Poll every 10s to update assignments/timers/status
                    setInterval(() => this.refresh(), 10000);
                },
                refresh() {
                    fetch(location.pathname + '?json=1').then(r => r.json()).then(data => {
                        console.log('tech refresh response', data);
                        if (!data || !data.cases) {
                            this.cases = [];
                            return;
                        }
                        this.cases = data.cases;
                    }).catch(e => { console.error('Failed to refresh tech cases', e); this.cases = []; });
                },
                groupedCases() {
                    // Group cases array (one entry per stage) into case cards
                    const map = {};
                    this.cases.forEach(s => {
                        const id = s.id;
                        if (!map[id]) {
                            map[id] = { id: id, title: s.plate + (s.vehicle_make ? ' - ' + s.vehicle_make + (s.vehicle_model ? ' ' + s.vehicle_model : '') : ''), stages: [], urgent: s.urgent };
                        }
                        map[id].stages.push({ stage: s.stage, stage_title: s.stage_title, status: s.status, timer: s.timer });
                    });
                    // Convert to array and sort by latest timer / id
                    const arr = Object.values(map);
                    arr.forEach(caseGroup => {
                        if (caseGroup.urgent) {
                            caseGroup.title += ' 🔥';
                        }
                    });
                    arr.sort((a,b)=> b.id - a.id);
                    return arr;
                },
                finishStage(caseId, stage) {
                    if (!confirm('Mark this stage as finished?')) return;
                    fetch('api.php?action=finish_stage', {
                        method: 'POST', headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({ case_id: caseId, stage: stage })
                    }).then(r => r.json()).then(data => {
                        if (data.status === 'success') {
                            // Refresh list to apply authoritative statuses
                            this.refresh();
                            // optional toast
                            alert('Stage marked finished');
                        } else {
                            alert('Failed: ' + (data.message || 'Unknown'));
                        }
                    }).catch(e => alert('Error: ' + e.message));
                },
                displayTimer(ms) {
                    if (!ms) return '—';
                    const elapsed = Math.floor((Date.now() - Number(ms)) / 1000);
                    const m = Math.floor(elapsed/60); const s = elapsed%60;
                    return `${m}:${String(s).padStart(2,'0')}`;
                },
                finish(c) {
                    if (!confirm('Mark this work as finished?')) return;
                    fetch('api.php?action=finish_stage', {
                        method: 'POST', headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({ case_id: c.id, stage: c.stage })
                    }).then(r => r.json()).then(data => {
                        if (data.status === 'success') {
                            // Update local case status
                            c.status = data.statuses && data.statuses[c.stage] ? data.statuses[c.stage] : {status:'finished'};
                            // stop showing timer
                            c.timer = null;
                            this.cases = [...this.cases];
                            alert('Marked finished');
                        } else {
                            alert('Failed: ' + (data.message || 'Unknown'));
                        }
                    }).catch(e => alert('Error: ' + e.message));
                }
            }
        }
    </script>
</body>
</html>