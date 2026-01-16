<?php
session_start();
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Define the workflow stages to display (no backlog)
$stages = [
    ['id' => 'disassembly', 'title' => __('workflow.stage.disassembly', 'Disassembly')],
    ['id' => 'body_work', 'title' => __('workflow.stage.body_work', 'Body Work')],
    ['id' => 'processing_for_painting', 'title' => __('workflow.stage.processing_for_painting', 'Processing for Painting')],
    ['id' => 'preparing_for_painting', 'title' => __('workflow.stage.preparing_for_painting', 'Preparing for Painting')],
    ['id' => 'painting', 'title' => __('workflow.stage.painting', 'Painting')],
    ['id' => 'assembling', 'title' => __('workflow.stage.assembling', 'Assembling')],
];

// Fetch users who can be assigned (technicians)
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'manager') ORDER BY full_name");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $technicians = [];
}

// If JSON requested, return the cases grouped by stage (for polling)
if (isset($_GET['json'])) {
    $stmt = $pdo->query("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses FROM transfers WHERE repair_stage IS NOT NULL AND status IN ('Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Completed') ORDER BY id DESC");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $casesByStage = [];
    foreach ($stages as $stage) $casesByStage[$stage['id']] = [];
    foreach ($cases as $case) {
        $s = $case['repair_stage'] ?? 'disassembly';
        if (isset($casesByStage[$s])) {
            $case['repair_assignments'] = json_decode($case['repair_assignments'] ?? '{}', true);
            $case['stage_timers'] = json_decode($case['stage_timers'] ?? '{}', true);
            $case['stage_statuses'] = json_decode($case['stage_statuses'] ?? '{}', true);
            $casesByStage[$s][] = $case;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['stages' => $stages, 'cases' => $casesByStage, 'now' => time() * 1000]);
    exit;
}

// Initial page render data
$stmt = $pdo->query("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses FROM transfers WHERE repair_stage IS NOT NULL AND status IN ('Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Completed') ORDER BY id DESC");
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
$casesByStage = [];
foreach ($stages as $stage) $casesByStage[$stage['id']] = [];
foreach ($cases as $case) {
    $s = $case['repair_stage'] ?? 'disassembly';
    if (isset($casesByStage[$s])) {
        $case['repair_assignments'] = json_decode($case['repair_assignments'] ?? '{}', true);
        $case['stage_timers'] = json_decode($case['stage_timers'] ?? '{}', true);
        $case['stage_statuses'] = json_decode($case['stage_statuses'] ?? '{}', true);
        $casesByStage[$s][] = $case;
    }
}

?>
<!doctype html>
<html lang="<?php echo get_current_language(); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
    <title><?php echo __('workflow.display.title', 'Workflow Display'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        function workflowDisplay() {
            return {
                stages: <?php echo json_encode($stages); ?>,
                cases: <?php echo json_encode($casesByStage); ?>,
                technicians: <?php echo json_encode($technicians); ?>,
                lastUpdated: Date.now(),
                lastUpdatedText: '',
                currentTime: Date.now(),
                connectionStatus: 'online',
                init() {
                    this.updateLastText();
                    setInterval(() => { this.currentTime = Date.now(); this.updateLastText(); }, 1000);
                    // Poll server every 10 seconds
                    setInterval(() => this.poll(), 10000);
                    // Auto-enter fullscreen for TV
                    setTimeout(() => {
                        if (!document.fullscreenElement) {
                            document.documentElement.requestFullscreen().catch(e => console.log('Auto fullscreen failed:', e));
                        }
                    }, 1000);
                },
                updateLastText() {
                    const d = new Date(this.lastUpdated);
                    this.lastUpdatedText = 'Last updated: ' + d.toLocaleTimeString();
                },
                refreshNow() { this.poll(); },
                poll() {
                    fetch(location.pathname + '?json=1&t=' + Date.now())
                        .then(r => {
                            if (!r.ok) throw new Error('Network response not ok');
                            return r.json();
                        })
                        .then(data => {
                            console.log('Polling data:', data);
                            if (data && data.cases) {
                                this.cases = data.cases;
                                this.lastUpdated = data.now || Date.now();
                                this.updateLastText();
                                this.connectionStatus = 'online';
                            } else {
                                console.warn('No cases data in poll response');
                            }
                        })
                        .catch(e => {
                            console.error('Failed polling workflow:', e);
                            this.connectionStatus = 'offline';
                        });
                },
                toggleFullscreen() {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen().catch(e => console.log('Fullscreen failed:', e));
                    } else {
                        document.exitFullscreen().catch(e => console.log('Exit fullscreen failed:', e));
                    }
                },
                getTimerDisplay(caseId, stageId) {
                    const caseItem = (this.cases[stageId]||[]).find(c => c.id == caseId);
                    if (!caseItem) return '';
                    const timer = caseItem.stage_timers && caseItem.stage_timers[stageId];
                    if (!timer) return '—';
                    const elapsed = Math.floor((Date.now() - Number(timer)) / 1000);
                    if (elapsed < 0) return '00:00';
                    const hours = Math.floor(elapsed / 3600);
                    const minutes = Math.floor((elapsed % 3600) / 60);
                    return `${String(hours).padStart(2,'0')}:${String(minutes).padStart(2,'0')}`;
                },
                getTechName(id) {
                    const t = this.technicians.find(x => x.id == id);
                    return t ? t.full_name : null;
                }
            }
        }
    </script>
    <style>
        html,body { height: 100%; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { margin: 0; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); color: #1e293b; }
        .stage-column { min-width: 180px; flex: 1; background: rgba(255,255,255,0.9); border-radius: 12px; padding: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid rgba(255,255,255,0.5); }
        .card { background: #ffffff; border-radius: 8px; padding: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; transition: all 0.2s; font-size: 12px; margin-bottom: 4px; }
        .card:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .tv-title { font-size: 24px; font-weight: 700; color: #1e293b; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .tv-sub { color: #64748b; font-size: 14px; font-weight: 500; }
        .timer-badge { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #92400e; font-weight: 600; padding: 4px 8px; border-radius: 999px; font-size: 12px; box-shadow: 0 2px 6px rgba(251,191,36,0.3); }
        .blink-finished { animation: finishedBlink 1.5s infinite; box-shadow: 0 0 16px rgba(16,185,129,0.4), 0 4px 16px rgba(0,0,0,0.1); border-color: #10b981; }
        @keyframes finishedBlink { 0%{transform:translateY(0);}50%{transform:translateY(-3px);}100%{transform:translateY(0);} }
        .btn { padding: 8px 12px; border-radius: 8px; font-weight: 600; transition: all 0.2s; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .connection-status { position: fixed; top: 20px; right: 20px; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; z-index: 10; }
        .connection-online { background: #dcfce7; color: #166534; }
        .connection-offline { background: #fee2e2; color: #991b1b; }
        .empty-stage { text-align: center; color: #94a3b8; font-style: italic; padding: 20px 10px; font-size: 16px; }
        .stage-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
        .stage-title { font-size: 14px; font-weight: 600; color: #1e293b; }
        .stage-count { background: #3b82f6; color: white; padding: 2px 4px; border-radius: 6px; font-size: 10px; font-weight: 500; }
        @media (max-width: 768px) {
            .stage-column { min-width: 160px; padding: 8px; }
            .card { padding: 6px; font-size: 10px; }
            .timer-badge { font-size: 8px; padding: 2px 4px; }
            .stage-title { font-size: 12px; }
            .empty-stage { font-size: 12px; padding: 10px 6px; }
        }
        @media (min-width: 1920px) {
            .stage-column { min-width: 220px; }
            .card { font-size: 14px; padding: 10px; }
        }
        @media (min-width: 2560px) {
            .stage-column { min-width: 260px; }
            .card { font-size: 16px; padding: 12px; }
        }
    </style>
</head>
<body x-data="workflowDisplay()" x-init="init()" class="antialiased">
    <div class="fixed top-2 right-2 bg-black/70 text-white text-xs px-2 py-1 rounded z-10" x-text="`Updated: ${lastUpdatedText} | ${connectionStatus}`"></div>

    <main class="overflow-auto h-screen">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-2 p-2">
            <template x-for="stage in stages" :key="stage.id">
                <div class="stage-column">
                    <div class="stage-header">
                        <div class="stage-title" x-text="stage.title"></div>
                        <div class="stage-count" x-text="(cases[stage.id]||[]).length"></div>
                    </div>
                    <div class="space-y-1 overflow-y-auto max-h-80">
                        <template x-for="caseItem in (cases[stage.id] || [])" :key="caseItem.id">
                            <div class="card" :class="{'blink-finished': caseItem.stage_statuses && caseItem.stage_statuses[stage.id] && caseItem.stage_statuses[stage.id].status === 'finished', 'opacity-50': caseItem.status === 'Completed'}">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex-1 min-w-0 text-xs">
                                        <span class="font-semibold" x-text="caseItem.plate"></span>
                                        <span class="text-gray-600" x-text="` - ${caseItem.vehicle_make || ''} ${caseItem.vehicle_model || ''}`.trim() || ' - Unknown'"></span>
                                        <span class="text-gray-500" x-text="(caseItem.repair_assignments && caseItem.repair_assignments[stage.id]) ? ` - ${getTechName(caseItem.repair_assignments[stage.id]) || caseItem.repair_assignments[stage.id]}` : ''"></span>
                                    </div>
                                    <div class="timer-badge" x-text="getTimerDisplay(caseItem.id, stage.id)"></div>
                                </div>
                            </div>
                        </template>
                        <template x-if="(cases[stage.id] || []).length === 0">
                            <div class="empty-stage">
                                <div class="text-4xl mb-2">📋</div>
                                <div>No cases in this stage</div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </main>

</body>
</html>