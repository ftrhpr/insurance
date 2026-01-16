<?php
session_start();
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

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
    $stmt = $pdo->query("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses FROM transfers WHERE status IN ('Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled') ORDER BY id DESC");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $assigned = [];
    foreach ($cases as $c) {
        $assignments = json_decode($c['repair_assignments'] ?? '{}', true);
        $statuses = json_decode($c['stage_statuses'] ?? '{}', true);
        $timers = json_decode($c['stage_timers'] ?? '{}', true);
        foreach ($assignments as $stage => $techId) {
            if (intval($techId) === intval($userId)) {
                $assigned[] = [
                    'id' => $c['id'],
                    'plate' => $c['plate'],
                    'vehicle_make' => $c['vehicle_make'],
                    'vehicle_model' => $c['vehicle_model'],
                    'stage' => $stage,
                    'status' => $statuses[$stage] ?? null,
                    'timer' => $timers[$stage] ?? null
                ];
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['cases' => $assigned, 'now' => time() * 1000]);
    exit;
}

// Fetch active cases (we'll filter by assignment in PHP for simplicity)
$stmt = $pdo->query("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses FROM transfers WHERE status IN ('Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled') ORDER BY id DESC");
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Filter assigned to current user
$assigned = [];
foreach ($cases as $c) {
    $assignments = json_decode($c['repair_assignments'] ?? '{}', true);
    $statuses = json_decode($c['stage_statuses'] ?? '{}', true);
    $timers = json_decode($c['stage_timers'] ?? '{}', true);
    foreach ($assignments as $stage => $techId) {
        if (intval($techId) === intval($userId)) {
            $assigned[] = [
                'id' => $c['id'],
                'plate' => $c['plate'],
                'vehicle_make' => $c['vehicle_make'],
                'vehicle_model' => $c['vehicle_model'],
                'stage' => $stage,
                'status' => $statuses[$stage] ?? null,
                'timer' => $timers[$stage] ?? null
            ];
        }
    }
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
<body class="bg-slate-100 p-6">
    <div class="max-w-4xl mx-auto" x-data="techDashboard()">
        <h1 class="text-2xl font-bold mb-4"><?php echo __('technician.dashboard.header', 'Your Assigned Work'); ?></h1>
        <div class="space-y-4">
            <template x-if="cases.length === 0">
                <div class="p-4 bg-white rounded shadow">No assigned cases</div>
            </template>
            <template x-for="c in cases" :key="c.id">
                <div class="p-4 bg-white rounded-lg shadow flex items-center justify-between" :class="{'opacity-60': c.status && c.status.status === 'finished'}">
                    <div>
                        <div class="text-lg font-bold" x-text="`${c.vehicle_make} ${c.vehicle_model}`"></div>
                        <div class="text-sm text-slate-500" x-text="`${c.plate} - #${c.id} (${c.stage})`"></div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-sm font-mono text-slate-700" x-text="displayTimer(c.timer)"></div>
                        <button x-show="!(c.status && c.status.status === 'finished')" @click="finish(c)" class="px-4 py-2 bg-emerald-600 text-white rounded">Finished</button>
                        <div x-show="c.status && c.status.status === 'finished'" class="px-3 py-1 bg-green-100 text-green-800 rounded font-semibold">Finished</div>
                    </div>
                </div>
            </template>
        </div>
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
                        if (!data || !data.cases) return;
                        this.cases = data.cases;
                    }).catch(e => console.error('Failed to refresh tech cases', e));
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