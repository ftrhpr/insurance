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
        .stage-column { min-width: 400px; flex: 1; background: rgba(255,255,255,0.9); border-radius: 24px; padding: 24px; box-shadow: 0 12px 40px rgba(0,0,0,0.15); border: 2px solid rgba(255,255,255,0.6); }
        .card { background: #ffffff; border-radius: 20px; padding: 24px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); border: 2px solid #f1f5f9; transition: all 0.3s; font-size: 18px; }
        .card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.15); }
        .tv-title { font-size: 48px; font-weight: 900; color: #1e293b; text-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .tv-sub { color: #64748b; font-size: 20px; font-weight: 600; }
        .timer-badge { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #92400e; font-weight: 800; padding: 12px 16px; border-radius: 999px; font-size: 20px; box-shadow: 0 4px 12px rgba(251,191,36,0.4); }
        .blink-finished { animation: finishedBlink 2s infinite; box-shadow: 0 0 32px rgba(16,185,129,0.6), 0 12px 40px rgba(0,0,0,0.15); border-color: #10b981; border-width: 3px; }
        @keyframes finishedBlink { 0%{transform:translateY(0);}50%{transform:translateY(-8px);}100%{transform:translateY(0);} }
        .btn { padding: 16px 24px; border-radius: 12px; font-weight: 700; transition: all 0.3s; border: none; cursor: pointer; font-size: 18px; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.2); }
        .connection-status { position: fixed; top: 30px; right: 30px; padding: 8px 16px; border-radius: 24px; font-size: 16px; font-weight: 700; z-index: 10; }
        .connection-online { background: #dcfce7; color: #166534; }
        .connection-offline { background: #fee2e2; color: #991b1b; }
        .empty-stage { text-align: center; color: #94a3b8; font-style: italic; padding: 60px 30px; font-size: 24px; }
        .stage-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 3px solid #e2e8f0; }
        .stage-title { font-size: 28px; font-weight: 800; color: #1e293b; }
        .stage-count { background: #3b82f6; color: white; padding: 6px 12px; border-radius: 16px; font-size: 18px; font-weight: 700; }
        @media (max-width: 768px) {
            .stage-column { min-width: 320px; padding: 20px; }
            .tv-title { font-size: 36px; }
            .tv-sub { font-size: 16px; }
            .card { padding: 20px; font-size: 16px; }
            .timer-badge { font-size: 16px; padding: 10px 14px; }
            .stage-title { font-size: 24px; }
            .empty-stage { font-size: 20px; padding: 50px 25px; }
        }
        @media (min-width: 1920px) {
            .stage-column { min-width: 500px; }
            .tv-title { font-size: 64px; }
            .timer-badge { font-size: 24px; padding: 14px 18px; }
            .card { font-size: 20px; }
        }
        @media (min-width: 2560px) {
            .stage-column { min-width: 600px; }
            .tv-title { font-size: 80px; }
            .timer-badge { font-size: 28px; padding: 16px 20px; }
            .card { font-size: 22px; }
        }
    </style>
</head>
<body x-data="workflowDisplay()" x-init="init()" class="flex flex-col antialiased">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between p-4 md:p-6 bg-white/80 backdrop-blur-sm border-b border-gray-200 shadow-sm">
        <div class="mb-4 md:mb-0">
            <div class="tv-title"><?php echo __('workflow.display.header', 'Repair Board'); ?></div>
            <div class="tv-sub mt-1" x-text="lastUpdatedText"></div>
        </div>
        <div class="flex items-center gap-2 md:gap-4">
            <button @click="refreshNow()" class="btn bg-gray-600 text-white hover:bg-gray-700">Refresh</button>
        </div>
    </div>

    <main class="overflow-auto flex-1">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6 gap-6 p-6">
            <template x-for="stage in stages" :key="stage.id">
                <div class="stage-column">
                    <div class="stage-header">
                        <div class="stage-title" x-text="stage.title"></div>
                        <div class="stage-count" x-text="(cases[stage.id]||[]).length"></div>
                    </div>
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <template x-for="caseItem in (cases[stage.id] || [])" :key="caseItem.id">
                            <div class="card" :class="{'blink-finished': caseItem.stage_statuses && caseItem.stage_statuses[stage.id] && caseItem.stage_statuses[stage.id].status === 'finished'}">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="text-lg font-bold text-gray-900 mb-1" x-text="`${caseItem.vehicle_make || ''} ${caseItem.vehicle_model || ''}`.trim() || 'Unknown Vehicle'"></div>
                                        <div class="text-sm text-gray-600" x-text="`${caseItem.plate} • #${caseItem.id}`"></div>
                                        <div class="text-xs text-gray-500 mt-1" x-text="(caseItem.repair_assignments && caseItem.repair_assignments[stage.id]) ? ('👤 ' + (getTechName(caseItem.repair_assignments[stage.id]) || caseItem.repair_assignments[stage.id])) : ''"></div>
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