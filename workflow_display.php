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
        html,body { height: 100%; }
        body { margin: 0; background: #0f172a; }
        .stage-column { min-width: 360px; }
        .card { background: rgba(255,255,255,0.95); border-radius: 12px; padding: 18px; }
        .tv-title { font-size: 36px; font-weight: 700; color: #0f172a; }
        .tv-sub { color: #1f2937; font-size: 20px; }
        .timer-badge { background: #ffedd5; color: #92400e; font-weight: 700; padding: 6px 10px; border-radius: 999px; font-size: 18px; }
        .blink-finished { animation: finishedBlink 1.2s infinite; box-shadow: 0 0 18px rgba(16,185,129,0.35); }
        @keyframes finishedBlink { 0%{transform:translateY(0);}50%{transform:translateY(-2px);}100%{transform:translateY(0);} }
        @media (min-width: 1920px) {
            .stage-column { min-width: 520px; }
            .tv-title { font-size: 56px; }
            .timer-badge { font-size: 24px; padding: 10px 14px; }
        }
    </style>
</head>
<body x-data="workflowDisplay()" x-init="init()" class="antialiased">
    <div class="flex items-center justify-between p-6 bg-white/5 border-b border-white/6">
        <div>
            <div class="tv-title"><?php echo __('workflow.display.header', 'Repair Board'); ?></div>
            <div class="tv-sub mt-1" x-text="lastUpdatedText"></div>
        </div>
        <div class="flex items-center gap-4">
            <button @click="toggleFullscreen()" class="px-4 py-2 rounded bg-white/10 text-white">Go Fullscreen</button>
            <button @click="refreshNow()" class="px-4 py-2 rounded bg-white/10 text-white">Refresh</button>
        </div>
    </div>

    <main class="overflow-auto" style="height: calc(100% - 92px);">
        <div class="flex gap-6 px-6 py-6" style="min-width: 100%;">
            <template x-for="stage in stages" :key="stage.id">
                <div class="stage-column bg-white/6 rounded-xl p-4 flex-shrink-0" style="height: calc(100vh - 140px);">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="text-white text-2xl font-semibold" x-text="stage.title"></div>
                        <div class="text-white/80 text-xl font-medium" x-text="(cases[stage.id]||[]).length"></div>
                    </div>
                    <div class="space-y-4 overflow-auto" style="height: calc(100% - 56px);">
                        <template x-for="caseItem in (cases[stage.id] || [])" :key="caseItem.id">
                            <div class="card">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-2xl font-bold" x-text="`${caseItem.vehicle_make} ${caseItem.vehicle_model}`"></div>
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

    <script>
        function workflowDisplay() {
            return {
                stages: <?php echo json_encode($stages); ?>,
                cases: <?php echo json_encode($casesByStage); ?>,
                technicians: <?php echo json_encode($technicians); ?>,
                lastUpdated: Date.now(),
                lastUpdatedText: '',
                currentTime: Date.now(),
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
                    fetch(location.pathname + '?json=1').then(r => r.json()).then(data => {
                        if (data && data.cases) {
                            this.cases = data.cases;
                            this.lastUpdated = data.now || Date.now();
                            this.updateLastText();
                        }
                    }).catch(e => console.error('Failed polling workflow:', e));
                },
                toggleFullscreen() {
                    if (!document.fullscreenElement) document.documentElement.requestFullscreen(); else document.exitFullscreen();
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
</body>
</html>