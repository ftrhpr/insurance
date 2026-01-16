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
$stmt = $pdo->query("SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers FROM transfers WHERE repair_stage IS NOT NULL AND status NOT IN ('Completed','Issue','Archived') ORDER BY id DESC");
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
$casesByStage = [];
foreach ($stages as $stage) $casesByStage[$stage['id']] = [];
foreach ($cases as $case) {
    $s = $case['repair_stage'] ?? 'disassembly';
    if (isset($casesByStage[$s])) {
        $case['repair_assignments'] = json_decode($case['repair_assignments'] ?? '{}', true);
        $case['stage_timers'] = json_decode($case['stage_timers'] ?? '{}', true);
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        html,body { height: 100%; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { margin: 0; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .stage-column { min-width: 280px; flex: 1; }
        .card { background: rgba(255,255,255,0.98); border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .tv-title { font-size: 28px; font-weight: 700; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
        .tv-sub { color: #e2e8f0; font-size: 16px; }
        .timer-badge { background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%); color: #9a3412; font-weight: 700; padding: 8px 12px; border-radius: 999px; font-size: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .blink-finished { animation: finishedBlink 1.2s infinite; box-shadow: 0 0 20px rgba(16,185,129,0.4), 0 4px 20px rgba(0,0,0,0.1); }
        @keyframes finishedBlink { 0%{transform:translateY(0);}50%{transform:translateY(-4px);}100%{transform:translateY(0);} }
        .btn { padding: 10px 16px; border-radius: 8px; font-weight: 600; transition: all 0.2s; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .connection-status { position: fixed; top: 10px; right: 10px; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .connection-online { background: #dcfce7; color: #166534; }
        .connection-offline { background: #fee2e2; color: #991b1b; }
        @media (max-width: 768px) {
            .stage-column { min-width: 240px; }
            .tv-title { font-size: 24px; }
            .tv-sub { font-size: 14px; }
            .card { padding: 16px; }
            .timer-badge { font-size: 14px; padding: 6px 10px; }
        }
        @media (min-width: 1920px) {
            .stage-column { min-width: 400px; }
            .tv-title { font-size: 48px; }
            .timer-badge { font-size: 20px; padding: 10px 14px; }
        }
        @media (min-width: 2560px) {
            .stage-column { min-width: 500px; }
            .tv-title { font-size: 56px; }
            .timer-badge { font-size: 24px; padding: 12px 16px; }
        }
    </style>
</head>
<body x-data="workflowDisplay()" x-init="init()" class="antialiased">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between p-4 md:p-6 bg-white/5 border-b border-white/6">
        <div class="mb-4 md:mb-0">
            <div class="tv-title"><?php echo __('workflow.display.header', 'Repair Board'); ?></div>
            <div class="tv-sub mt-1" x-text="lastUpdatedText"></div>
        </div>
        <div class="flex items-center gap-2 md:gap-4">
            <div class="connection-status" :class="connectionStatus === 'online' ? 'connection-online' : 'connection-offline'" x-text="connectionStatus === 'online' ? 'Online' : 'Offline'"></div>
            <button @click="toggleFullscreen()" class="btn bg-white/10 text-white hover:bg-white/20">Go Fullscreen</button>
            <button @click="refreshNow()" class="btn bg-white/10 text-white hover:bg-white/20">Refresh</button>
        </div>
    </div>

    <main class="overflow-auto" style="height: calc(100% - 92px);">
        <div class="flex flex-wrap gap-6 px-6 py-6 justify-center md:justify-start">
            <template x-for="stage in stages" :key="stage.id">
                <div class="stage-column bg-white/6 rounded-xl p-4 flex-shrink-0" style="height: calc(100vh - 140px); min-height: 400px;">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="text-white text-xl md:text-2xl font-semibold" x-text="stage.title"></div>
                        <div class="text-white/80 text-lg md:text-xl font-medium bg-black/20 px-3 py-1 rounded-full" x-text="(cases[stage.id]||[]).length"></div>
                    </div>
                    <div class="space-y-4 overflow-auto" style="height: calc(100% - 56px);">
                        <template x-for="caseItem in (cases[stage.id] || [])" :key="caseItem.id">
                            <div class="card" :class="{'blink-finished': caseItem.stage_statuses && caseItem.stage_statuses[stage.id] && caseItem.stage_statuses[stage.id].status === 'finished'}">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-xl md:text-2xl font-bold text-gray-900" x-text="`${caseItem.vehicle_make || ''} ${caseItem.vehicle_model || ''}`.trim() || 'Unknown Vehicle'"></div>
                                        <div class="text-sm text-slate-600 mt-1" x-text="`${caseItem.plate} - #${caseItem.id}`"></div>
                                    </div>
                                    <div class="flex flex-col items-end gap-2">
                                        <div class="timer-badge" x-text="getTimerDisplay(caseItem.id, stage.id)" :class="{'blink-finished': caseItem.stage_statuses && caseItem.stage_statuses[stage.id] && caseItem.stage_statuses[stage.id].status === 'finished'}"></div>
                                        <div class="text-xs text-slate-500 mt-1" x-text="(caseItem.repair_assignments && caseItem.repair_assignments[stage.id]) ? ('Tech: '+ (getTechName(caseItem.repair_assignments[stage.id]) || caseItem.repair_assignments[stage.id])) : ''"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </main>

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
                    // Try to enter fullscreen after a short delay
                    setTimeout(() => { try { document.documentElement.requestFullscreen(); } catch(e){} }, 1500);
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
                            if (data && data.cases) {
                                this.cases = data.cases;
                                this.lastUpdated = data.now || Date.now();
                                this.updateLastText();
                                this.connectionStatus = 'online';
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
</body>
</html>