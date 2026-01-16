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
    $stmt = $pdo->query("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses FROM transfers WHERE repair_stage IS NOT NULL AND status NOT IN ('Completed','Issue','Archived') ORDER BY id DESC");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $casesByStage = [];
    foreach ($stages as $stage) $casesByStage[$stage['id']] = [];
    foreach ($cases as $case) {
        $s = $case['repair_stage'] ?? 'disassembly';
        if (isset($casesByStage[$s])) {
            $case['repair_assignments'] = json_decode($case['repair_assignments'] ?? '{}', true);
            $case['stage_timers'] = json_decode($case['stage_timers'] ?? '{}', true);
            $case['stage_statuses'] = json_decode($case['stage_statuses'] ?? '{}', true);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['stages' => $stages, 'cases' => $casesByStage, 'now' => time() * 1000]);
    exit;
}

// Initial page render data
$stmt = $pdo->query("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses FROM transfers WHERE repair_stage IS NOT NULL AND status NOT IN ('Completed','Issue','Archived') ORDER BY id DESC");
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
                    // Poll server every 30 seconds for TV stability
                    setInterval(() => this.poll(), 30000);
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
                    fetch(location.pathname + '?json=1')
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
                    const secs = elapsed % 60;
                    if (hours > 0) return `${hours}:${String(minutes).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;
                    return `${minutes}:${String(secs).padStart(2,'0')}`;
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
        .stage-column { min-width: 200px; flex: 1; background: rgba(255,255,255,0.9); border-radius: 16px; padding: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.5); }
        .card { background: #ffffff; border-radius: 12px; padding: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; transition: all 0.2s; font-size: 14px; margin-bottom: 8px; }
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
        .stage-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; }
        .stage-title { font-size: 16px; font-weight: 700; color: #1e293b; }
        .stage-count { background: #3b82f6; color: white; padding: 2px 6px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        @media (max-width: 768px) {
            .stage-column { min-width: 180px; padding: 12px; }
            .tv-title { font-size: 20px; }
            .tv-sub { font-size: 12px; }
            .card { padding: 10px; font-size: 12px; }
            .timer-badge { font-size: 10px; padding: 3px 6px; }
            .stage-title { font-size: 14px; }
            .empty-stage { font-size: 14px; padding: 15px 8px; }
        }
        @media (min-width: 1920px) {
            .stage-column { min-width: 250px; }
            .tv-title { font-size: 32px; }
            .timer-badge { font-size: 14px; padding: 6px 10px; }
            .card { font-size: 16px; padding: 16px; }
        }
        @media (min-width: 2560px) {
            .stage-column { min-width: 300px; }
            .tv-title { font-size: 40px; }
            .timer-badge { font-size: 16px; padding: 8px 12px; }
            .card { font-size: 18px; padding: 20px; }
        }
    </style>
</head>
<body x-data="workflowDisplay()" x-init="init()" class="flex flex-col antialiased">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between p-3 md:p-4 bg-white/80 backdrop-blur-sm border-b border-gray-200 shadow-sm">
        <div class="mb-4 md:mb-0">
            <div class="tv-title"><?php echo __('workflow.display.header', 'Repair Board'); ?></div>
            <div class="tv-sub mt-1" x-text="lastUpdatedText"></div>
        </div>
        <div class="flex items-center gap-2 md:gap-4">
            <button @click="refreshNow()" class="btn bg-gray-600 text-white hover:bg-gray-700">Refresh</button>
        </div>
    </div>

    <main class="overflow-auto flex-1">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 p-3">
            <template x-for="stage in stages" :key="stage.id">
                <div class="stage-column">
                    <div class="stage-header">
                        <div class="stage-title" x-text="stage.title"></div>
                        <div class="stage-count" x-text="(cases[stage.id]||[]).length"></div>
                    </div>
                    <div class="space-y-2 overflow-y-auto max-h-80">
                        <template x-for="caseItem in (cases[stage.id] || [])" :key="caseItem.id">
                            <div class="card" :class="{'blink-finished': caseItem.stage_statuses && caseItem.stage_statuses[stage.id] && caseItem.stage_statuses[stage.id].status === 'finished'}">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-gray-900 truncate" x-text="caseItem.plate"></div>
                                        <div class="text-xs text-gray-600 truncate" x-text="`${caseItem.vehicle_make || ''} ${caseItem.vehicle_model || ''}`.trim() || 'Unknown'"></div>
                                        <div class="text-xs text-gray-500 truncate" x-text="(caseItem.repair_assignments && caseItem.repair_assignments[stage.id]) ? getTechName(caseItem.repair_assignments[stage.id]) || caseItem.repair_assignments[stage.id] : ''"></div>
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