<?php
error_log('[workflow.php] reached file top');
session_start();
// Production-safety: don't display PHP errors to users, but log them
ini_set('display_errors', 0);

// Use system temp dir for guaranteed-writable debug log
$workflow_debug_file = sys_get_temp_dir() . '/workflow_debug.log';
function workflowDebug($m) {
    global $workflow_debug_file;
    @error_log("[workflow_debug] " . $m);
    @file_put_contents($workflow_debug_file, '[' . date('c') . '] ' . $m . "\n", FILE_APPEND | LOCK_EX);
}
workflowDebug('start request, user=' . ($_SESSION['user_id'] ?? 'guest'));

// Register shutdown handler to capture fatal errors
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        $msg = "[shutdown] last_error: " . json_encode($err);
        workflowDebug($msg);
    }
});

set_exception_handler(function($e){
    $msg = "[exception] " . $e->getMessage() . " | " . $e->getFile() . ':' . $e->getLine();
    workflowDebug($msg);
    http_response_code(500);
    // Friendly message for the user; admin can add ?debug=1 to view more detail
    echo "<h2>Internal Server Error</h2><p>The server encountered an unexpected condition.</p>";
    if (isset($_GET['debug']) && in_array($_SESSION['role'] ?? '', ['admin'])) {
        echo "<pre>" . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . "</pre>";
    }
    exit;
});

require_once 'config.php';
require_once 'language.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: login.php');
    exit;
}

try {
    workflowDebug('connecting to DB');
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    workflowDebug('DB connected');
} catch (PDOException $e) {
    workflowDebug('DB connect failed: ' . $e->getMessage());
    // give a user-friendly message and stop
    http_response_code(500);
    echo "<h2>Internal Server Error</h2><p>Database connection failed. Admins: check logs.</p>";
    if (isset($_GET['debug']) && in_array($_SESSION['role'] ?? '', ['admin'])) {
        echo "<pre>" . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . "</pre>";
    }
    exit;
}

// Fetch users who can be assigned (technicians) - assuming managers and admins
workflowDebug('fetching technicians');
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'manager') ORDER BY full_name");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    workflowDebug('got technicians: ' . count($technicians));
} catch (Exception $e) {
    workflowDebug('technicians query failed: ' . $e->getMessage());
    $technicians = [];
}

// Define workflow stages (always include all expected stages)
$defaultStages = [
    ['id' => 'backlog', 'title' => __('workflow.stage.backlog', 'Backlog')],
    ['id' => 'disassembly', 'title' => __('workflow.stage.disassembly', 'Disassembly')],
    ['id' => 'body_work', 'title' => __('workflow.stage.body_work', 'Body Work')],
    ['id' => 'processing_for_painting', 'title' => __('workflow.stage.processing_for_painting', 'Processing for Painting')],
    ['id' => 'preparing_for_painting', 'title' => __('workflow.stage.preparing_for_painting', 'Preparing for Painting')],
    ['id' => 'painting', 'title' => __('workflow.stage.painting', 'Painting')],
    ['id' => 'assembling', 'title' => __('workflow.stage.assembling', 'Assembling')],
    ['id' => 'done', 'title' => __('workflow.stage.done', 'DONE')],
];

// Load any additional stages from existing repair_stage values
try {
    $stmt = $pdo->query("SELECT DISTINCT repair_stage FROM transfers WHERE repair_stage IS NOT NULL AND repair_stage NOT IN ('backlog', 'disassembly', 'body_work', 'processing_for_painting', 'preparing_for_painting', 'painting', 'assembling', 'done')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stageId = $row['repair_stage'];
        $defaultStages[] = [
            'id' => $stageId,
            'title' => __('workflow.stage.' . $stageId, ucfirst(str_replace('_', ' ', $stageId)))
        ];
    }
} catch (Exception $e) {
    // Ignore database errors, use default stages
}

$stages = $defaultStages;

// Fetch cases for the workflow board
workflowDebug('fetching cases');
try {
    $stmt = $pdo->query("
        SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses, urgent
        FROM transfers
        WHERE status IN ('Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled')
        ORDER BY 
            CASE WHEN repair_stage IS NULL THEN 0 ELSE 1 END,
            id DESC
    ");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    workflowDebug('got cases: ' . count($cases));
} catch (Exception $e) {
    workflowDebug('cases query failed: ' . $e->getMessage());
    http_response_code(500);
    echo "<h2>Internal Server Error</h2><p>Unable to load cases. Admins: check logs.</p>";
    if (isset($_GET['debug']) && in_array($_SESSION['role'] ?? '', ['admin'])) {
        echo "<pre>" . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . "</pre>";
    }
    exit;
}

// Group cases by stage for Alpine.js
$casesByStage = [];
foreach ($stages as $stage) {
    $casesByStage[$stage['id']] = [];
}
foreach ($cases as $case) {
    $stageId = $case['repair_stage'] ?? 'backlog';
    if (array_key_exists($stageId, $casesByStage)) {
        $case['repair_assignments'] = json_decode($case['repair_assignments'] ?? '{}', true);
        $case['stage_timers'] = json_decode($case['stage_timers'] ?? '{}', true);
        $case['stage_statuses'] = json_decode($case['stage_statuses'] ?? '{}', true);
        $casesByStage[$stageId][] = $case;
    }
}

?>
<!DOCTYPE html>
<html lang="<?php echo get_current_language(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('workflow.title', 'Case Processing Workflow'); ?> - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.378.0/dist/umd/lucide.js"></script>
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <style>
        .ghost { opacity: 0.5; background: #c8ebfb; }
        .sortable-chosen { cursor: grabbing; }
        .case-card { transition: box-shadow 0.2s; }
        .case-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        /* Finished blink */
        @keyframes finishedBlink {
            0% { box-shadow: 0 0 0px rgba(16,185,129,0); transform: translateY(0); }
            50% { box-shadow: 0 0 20px rgba(16,185,129,0.35); transform: translateY(-2px); }
            100% { box-shadow: 0 0 0px rgba(16,185,129,0); transform: translateY(0); }
        }
        .blink-finished { border-left: 4px solid #10B981; animation: finishedBlink 1.2s infinite; }
        .finished-badge { background: #D1FAE5; color: #065F46; font-weight: 700; padding: 4px 8px; border-radius: 999px; font-size: 12px; }
    </style>
</head>
<body class="bg-slate-100">
    <div id="toast-container" class="fixed top-6 right-6 z-[100] space-y-3"></div>

    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 ml-64 py-10 px-6">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-800"><?php echo __('workflow.header', 'Repair Workflow'); ?></h1>
                <p class="text-slate-600 mt-1"><?php echo __('workflow.description', 'Cases in the backlog are ready to be processed. Drag them into workflow stages to begin repair work.'); ?></p>
            </div>

            <div x-data="workflowBoard()" class="overflow-x-auto pb-4">
                <!-- Case History Modal (moved inside Alpine scope so close binds correctly) -->
                <div x-show="showHistoryModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" @click.self="closeCaseHistory()">
                    <div class="bg-white rounded-lg p-4 max-w-2xl w-full mx-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold">Case History <span class="text-sm text-slate-500">#<span x-text="historyCaseId"></span></span></h3>
                            <button @click="closeCaseHistory()" class="text-slate-500">Close</button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <h4 class="font-medium mb-2">System Logs</h4>
                                <div class="max-h-64 overflow-auto text-xs bg-slate-50 p-2 rounded">
                                    <template x-for="log in historyLogs.slice().reverse()" :key="JSON.stringify(log)">
                                        <div class="mb-2">
                                            <div class="text-xs text-slate-500" x-text="(new Date(log.timestamp || Date.now())).toLocaleString()"></div>
                                            <div class="text-sm" x-text="formatLogEntry(log)"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-medium mb-2">Work Times</h4>
                                <div class="max-h-64 overflow-auto text-xs bg-slate-50 p-2 rounded">
                                    <template x-if="Object.keys(historyWorkTimes || {}).length === 0">
                                        <div class="text-sm text-slate-500">No recorded work times</div>
                                    </template>
                                    <template x-for="(stageTimes, stage) in historyWorkTimes" :key="stage">
                                        <div class="mb-3">
                                            <div class="text-sm font-semibold" x-text="stage"></div>
                                            <div class="mt-1 text-xs text-slate-600">
                                                <template x-for="(duration, tech) in stageTimes" :key="tech">
                                                    <div x-text="`${getTechnicianName(tech)}: ${formatDuration(duration)}`"></div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-medium mb-2">Assignment History</h4>
                                <div class="max-h-64 overflow-auto text-xs bg-slate-50 p-2 rounded">
                                    <template x-if="historyAssignmentHistory && historyAssignmentHistory.length === 0">
                                        <div class="text-sm text-slate-500">No assignment history</div>
                                    </template>
                                    <template x-for="entry in historyAssignmentHistory.slice().reverse()" :key="JSON.stringify(entry)">
                                        <div class="mb-2">
                                            <div class="text-xs text-slate-500" x-text="(new Date(entry.timestamp || Date.now())).toLocaleString()"></div>
                                            <div class="text-sm" x-text="formatAssignmentEntry(entry)"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-4 min-w-max">
                    <template x-for="stage in stages" :key="stage.id">
                        <div :class="stage.id === 'backlog' ? 'w-72 bg-amber-100/60 rounded-xl shadow-sm flex-shrink-0 border-2 border-amber-200' : 'w-72 bg-slate-200/60 rounded-xl shadow-sm flex-shrink-0'">
                            <div class="p-4 border-b border-slate-300">
                                <h3 :class="stage.id === 'backlog' ? 'text-lg font-semibold text-amber-800 flex items-center justify-between' : 'text-lg font-semibold text-slate-700 flex items-center justify-between'">
                                    <span x-text="stage.title"></span>
                                    <span :class="stage.id === 'backlog' ? 'text-sm font-medium bg-amber-200 text-amber-700 rounded-full px-2 py-0.5' : 'text-sm font-medium bg-slate-300 text-slate-600 rounded-full px-2 py-0.5'" x-text="stage.id === 'backlog' ? getTotalBacklogCount() : (cases[stage.id] ? cases[stage.id].length : 0)"></span>
                                </h3>
                                <div x-show="stage.id === 'backlog'" class="mt-2">
                                    <input x-model="backlogSearch" @input="backlogPage = 1" type="text" placeholder="Search backlog..." class="w-full px-3 py-2 text-sm border border-amber-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                            </div>
                            <div :class="stage.id === 'backlog' ? 'p-4 space-y-4 min-h-[60vh] bg-amber-50/50' : 'p-4 space-y-4 min-h-[60vh]'" :data-stage-id="stage.id" x-ref="`stage-${stage.id}`">
                                <template x-for="caseItem in getFilteredCases(stage.id)" :key="caseItem.id">
                                    <div :class="{'blink-finished': caseItem.stage_statuses && caseItem.stage_statuses[stage.id] && caseItem.stage_statuses[stage.id].status === 'finished'}" class="bg-white rounded-lg p-4 shadow-md case-card" :data-case-id="caseItem.id">
                                        <div class="font-bold text-slate-800" x-text="`${caseItem.vehicle_make} ${caseItem.vehicle_model}`"></div>
                                        <div class="text-sm text-slate-500 flex items-center justify-between">
                                            <span x-text="`${caseItem.plate} - #${caseItem.id}`"></span>
                                            <span x-show="stage.id !== 'backlog' && hasTimer(caseItem.id, stage.id)" class="ml-2 inline-flex items-center gap-2 px-2 py-0.5 rounded-full text-sm font-semibold bg-amber-100 text-amber-800 border border-amber-200" x-text="getTimerDisplay(caseItem.id, stage.id)"></span>
                                            <span x-show="stage.id !== 'backlog' && !hasTimer(caseItem.id, stage.id)" class="ml-2 text-xs text-slate-400">—</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-2">
                                            <input type="checkbox" :checked="caseItem.urgent" @change="updateUrgent(caseItem.id, $event.target.checked)" class="w-4 h-4 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500">
                                            <label class="text-sm font-medium text-gray-700">სასწრაფო 🔥</label>
                                            <button @click="openCaseHistory(caseItem.id)" class="ml-auto text-xs text-sky-600 hover:underline">View history</button>
                                        </div>
                                        <template x-if="stage.id !== 'backlog'">
                                            <div class="mt-4">
                                                <label class="text-xs font-medium text-slate-500">Technician</label>
                                                <select @change="assignTechnician(caseItem.id, stage.id, $event.target.value)" class="mt-1 block w-full bg-slate-50 border-slate-200 rounded-md shadow-sm text-sm focus:ring-sky-500 focus:border-sky-500">
                                                    <option value="">Unassigned</option>
                                                    <template x-for="tech in technicians" :key="tech.id">
                                                        <option :value="tech.id" :selected="caseItem.repair_assignments && caseItem.repair_assignments[stage.id] == tech.id" x-text="tech.full_name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                            <template x-if="stage.id !== 'backlog'">
                                                <div class="mt-3">
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-medium text-slate-500">Timer</span>
                                                        <div class="flex items-center gap-2">
                                                            <span x-show="caseItem.stage_statuses && caseItem.stage_statuses[stage.id] && caseItem.stage_statuses[stage.id].status === 'finished'" class="finished-badge">Finished</span>
                                                            <span x-show="hasTimer(caseItem.id, stage.id)" class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-amber-100 text-amber-800 font-semibold text-sm">
                                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2 2"/></svg>
                                                                <span x-text="getTimerDisplay(caseItem.id, stage.id)"></span>
                                                            </span>
                                                            <span x-show="!hasTimer(caseItem.id, stage.id)" class="text-xs text-slate-400">No timer</span>
                                                        </div>
                                                    </div>
                                                    <!-- DEBUG: show raw timer and assignment data -->
                                                    <div class="mt-1">
                                                        <div class="text-xs text-amber-700">Assignment: <span x-text="(caseItem.repair_assignments && caseItem.repair_assignments[stage.id]) || 'none'"></span></div>
                                                        <div class="text-xs text-rose-600 mt-1 hidden debug-timers" x-text="dumpTimers(caseItem.id, stage.id)"></div>
                                                    </div>
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                </template>
                                <div x-show="stage.id === 'backlog' && hasMoreBacklog()" class="mt-4 text-center">
                                    <button @click="loadMoreBacklog()" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors">
                                        Load More Cases
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </main>


    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('workflowBoard', () => ({
                cases: <?php echo json_encode($casesByStage); ?>,
                stages: <?php echo json_encode($stages); ?>,
                technicians: <?php echo json_encode($technicians); ?>,
                activeTimers: {},
                currentTime: Date.now(),
                backlogSearch: '',
                backlogPage: 1,
                backlogPageSize: 20,
                showHistoryModal: false,
                historyLogs: [],
                historyWorkTimes: {},
                historyCaseId: null,
                init() {
                    this.$nextTick(() => {
                        this.stages.forEach(stage => {
                            const el = document.querySelector(`[data-stage-id="${stage.id}"]`);
                            if (el) {
                                new Sortable(el, {
                                    group: 'cases',
                                    animation: 150,
                                    ghostClass: 'ghost',
                                    chosenClass: 'sortable-chosen',
                                    onEnd: (evt) => {
                                        const caseId = evt.item.dataset.caseId;
                                        const newStageId = evt.to.dataset.stageId;
                                        const oldStageId = evt.from.dataset.stageId;
                                        const newIndex = evt.newDraggableIndex;
                                        
                                        if (newStageId !== oldStageId) {
                                           this.moveCase(caseId, newStageId, oldStageId, newIndex);
                                        }
                                    }
                                });
                            }
                        });
                        lucide.createIcons();

                                // modal state for viewing history
                        this.showHistoryModal = false;
                        this.historyLogs = [];
                        this.historyWorkTimes = {};
                        this.historyCaseId = null;

                        // Re-poll backlog/timers immediately after initialization to ensure fresh data
                        setTimeout(() => this.$nextTick(() => this.refreshBacklog()), 500);
                        
                        // Initialize timers for cases with assigned technicians
                        this.stages.forEach(stage => {
                            if (stage.id !== 'backlog' && this.cases[stage.id]) {
                                this.cases[stage.id].forEach(caseItem => {
                                    if (caseItem.repair_assignments && caseItem.repair_assignments[stage.id]) {
                                        this.startTimer(caseItem.id, stage.id);
                                    }
                                });
                            }
                        });
                        
                        // Start global timer for reactive updates
                        setInterval(() => {
                            this.currentTime = Date.now();
                        }, 1000);

                        // Periodically poll for statuses/timers updates (every 15s)
                        setInterval(() => {
                            fetch('workflow_display.php?json=1').then(r => r.json()).then(data => {
                                if (!data || !data.cases) return;
                                // Merge per-stage updates to avoid wiping local-only stages (like backlog)
                                for (const stageId in data.cases) {
                                    this.cases[stageId] = data.cases[stageId];
                                }
                                // Trigger reactivity update
                                this.cases = { ...this.cases };
                            }).catch(() => {});
                        }, 15000);
                    });
                },
                refreshBacklog() {
                    fetch('api.php?action=get_backlog').then(r => r.json()).then(data => {
                        if (data && data.cases) {
                            // replace backlog data with authoritative server copy
                            this.cases['backlog'] = data.cases;
                            this.cases = { ...this.cases };
                        }
                    }).catch(() => {});
                },
                moveCase(caseId, newStageId, oldStageId, newIndex) {
                    // Optimistically update the UI first
                    const caseToMove = this.cases[oldStageId].find(c => c.id == caseId);
                    if (caseToMove) {
                        // Remove from old array
                        this.cases[oldStageId] = this.cases[oldStageId].filter(c => c.id != caseId);
                        // Add to new array at the correct position
                        this.cases[newStageId].splice(newIndex, 0, caseToMove);
                    }

                    fetch('api.php?action=update_repair_stage', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ case_id: caseId, stage: newStageId })
                    }).then(res => res.json()).then(data => {
                        if (data.status === 'success') {
                            showToast('Case Updated', `Moved to ${this.stages.find(s => s.id === newStageId).title}`, 'success');
                            // Find case and update its stage property for consistency
                            const caseToUpdate = this.cases[newStageId].find(c => c.id == caseId);
                            if(caseToUpdate) {
                                caseToUpdate.repair_stage = newStageId;
                                // Clear timers when moving stages
                                caseToUpdate.stage_timers = {};
                                
                                // Handle timer transitions
                                if (oldStageId !== 'backlog') {
                                    this.stopTimer(caseId, oldStageId);
                                }
                                // Timer will start when technician is assigned to new stage
                            }
                        } else {
                            showToast('Error', 'Failed to update case stage.', 'error');
                            // Note: A full revert would be complex. For now, we rely on a page refresh if errors occur.
                        }
                    });
                },
                openCaseHistory(caseId) {
                    // Fetch case logs and work times
                    this.historyCaseId = caseId;
                    this.historyLogs = [];
                    this.historyWorkTimes = {};
                    this.historyAssignmentHistory = [];
                    this.showHistoryModal = true;
                    fetch(`api.php?action=get_transfer&id=${caseId}`).then(r => r.json()).then(data => {
                        if (data.status === 'success' && data.case) {
                            this.historyLogs = data.case.system_logs || [];
                            this.historyWorkTimes = data.case.work_times || {};
                            this.historyAssignmentHistory = data.case.assignment_history || [];

                            // Fallback: if no work_times stored, derive from system_logs entries of type 'work_time'
                            if (!this.historyWorkTimes || Object.keys(this.historyWorkTimes).length === 0) {
                                const derived = {};
                                (this.historyLogs || []).forEach(raw => {
                                    let entry = raw;
                                    try {
                                        if (typeof raw === 'string') {
                                            // try to extract JSON portion if logs were stored with a prefix like "work_time: {...}"
                                            const m = raw.match(/(\{.*\})/);
                                            if (m) entry = JSON.parse(m[1]);
                                            else entry = JSON.parse(raw);
                                        }
                                    } catch (e) {
                                        // ignore parse errors
                                    }

                                    // normalize shape: some logs store type in top-level or in a wrapper
                                    if (entry && (entry.type === 'work_time' || raw && String(raw).startsWith('work_time'))) {
                                        const stage = entry.stage || entry.stage_name || 'unknown';
                                        const tech = entry.tech || entry.tech_id || entry.by || 'unknown';
                                        const dur = Number(entry.duration_ms || entry.duration || 0);
                                        if (!derived[stage]) derived[stage] = {};
                                        if (!derived[stage][tech]) derived[stage][tech] = 0;
                                        derived[stage][tech] += dur;
                                    }
                                });

                                if (Object.keys(derived).length > 0) {
                                    this.historyWorkTimes = derived;
                                }
                            }
                        } else {
                            this.historyLogs = [{ type: 'error', message: data.message || 'Failed to load history' }];
                        }
                    }).catch(() => {
                        this.historyLogs = [{ type: 'error', message: 'Failed to load history' }];
                    });
                },
                closeCaseHistory() {
                    this.showHistoryModal = false;
                    this.historyLogs = [];
                    this.historyWorkTimes = {};
                    this.historyCaseId = null;
                },
                formatLogEntry(log) {
                    try {
                        let entry = log;
                        if (typeof log === 'string') {
                            // Try to extract JSON portion
                            const m = log.match(/(\{.*\})/);
                            if (m) entry = JSON.parse(m[1]);
                            else entry = JSON.parse(log);
                        }
                        
                        switch (entry.type) {
                            case 'assignment':
                                const techName = entry.to ? this.getTechnicianName(entry.to) : 'unassigned';
                                return `Assigned ${techName} to ${entry.stage} stage`;
                            case 'move':
                                return `Case moved from ${entry.from || 'backlog'} to ${entry.to} by ${this.getTechnicianName(entry.by)}`;
                            case 'work_time':
                                const duration = Math.round((entry.duration_ms || 0) / 1000);
                                return `Work time logged: ${duration}s by ${this.getTechnicianName(entry.tech)} on ${entry.stage} stage`;
                            case 'finish_stage':
                                return `Stage ${entry.stage} finished by ${this.getTechnicianName(entry.by)}`;
                            default:
                                return entry.message || JSON.stringify(entry);
                        }
                    } catch (e) {
                        return log.message || String(log);
                    }
                },
                formatAssignmentEntry(entry) {
                    if (!entry.to) {
                        return `Technician unassigned from ${entry.stage} stage by ${this.getTechnicianName(entry.by)}`;
                    } else if (!entry.from) {
                        return `Assigned ${this.getTechnicianName(entry.to)} to ${entry.stage} stage by ${this.getTechnicianName(entry.by)}`;
                    } else {
                        return `Technician assignment changed for ${entry.stage} stage: ${this.getTechnicianName(entry.from)} → ${this.getTechnicianName(entry.to)} by ${this.getTechnicianName(entry.by)}`;
                    }
                },
                getTechnicianName(id) {
                    if (!id) return 'system';
                    const tech = this.technicians.find(t => t.id == id);
                    return tech ? tech.full_name : `technician ${id}`;
                },
                formatDuration(ms) {
                    if (!ms) return '0m';
                    let totalSeconds = Math.round(ms / 1000);
                    if (totalSeconds < 60) return `${totalSeconds}s`;

                    let hours = Math.floor(totalSeconds / 3600);
                    let minutes = Math.floor((totalSeconds % 3600) / 60);
                    let seconds = totalSeconds % 60;

                    if (seconds >= 30) {
                        minutes++;
                        if (minutes === 60) {
                            hours++;
                            minutes = 0;
                        }
                    }
                    
                    const parts = [];
                    if (hours > 0) parts.push(`${hours}h`);
                    if (minutes > 0) parts.push(`${minutes}m`);
                    
                    return parts.length > 0 ? parts.join(' ') : '0m';
                },
                assignTechnician(caseId, stageId, technicianId) {
                    const caseToUpdate = this.cases[stageId].find(c => c.id == caseId);
                    const wasAssigned = caseToUpdate && caseToUpdate.repair_assignments && caseToUpdate.repair_assignments[stageId];
                    
                    if (caseToUpdate) {
                        // Update repair_assignments
                        if (!caseToUpdate.repair_assignments) caseToUpdate.repair_assignments = {};
                        caseToUpdate.repair_assignments[stageId] = technicianId;
                        
                        // Update stage_timers
                        if (!caseToUpdate.stage_timers) caseToUpdate.stage_timers = {};
                        if (technicianId) {
                            caseToUpdate.stage_timers[stageId] = Date.now();
                        } else {
                            delete caseToUpdate.stage_timers[stageId];
                        }
                        
                        // Trigger reactivity by updating the cases array
                        this.cases = { ...this.cases };
                    }

                    fetch('api.php?action=assign_technician', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ case_id: caseId, stage: stageId, technician_id: technicianId })
                    }).then(res => res.json()).then(data => {
                        console.log('assign_technician response', data);
                        if (data.status === 'success') {
                            const techName = technicianId ? this.technicians.find(t => t.id == technicianId).full_name : 'nobody';
                            showToast('Technician Assigned', `Assigned to ${techName}`, 'success');
                            
                            // Apply authoritative assignments/timers from server
                            const caseObj = this.cases[stageId].find(c => c.id == caseId);
                            if (caseObj) {
                                if (data.assignments) caseObj.repair_assignments = data.assignments;
                                if (data.timers) caseObj.stage_timers = data.timers;
                                if (data.statuses) caseObj.stage_statuses = data.statuses;
                                this.cases = { ...this.cases };
                            }

                            // Handle timer
                            if (technicianId) {
                                this.startTimer(caseId, stageId);
                            } else if (wasAssigned) {
                                this.stopTimer(caseId, stageId);
                            }
                        } else {
                            showToast('Error', 'Failed to assign technician.', 'error');
                        }
                    });
                },
                startTimer(caseId, stageId) {
                    const timerKey = `${caseId}-${stageId}`;
                    if (this.activeTimers[timerKey]) {
                        clearInterval(this.activeTimers[timerKey]);
                    }
                    
                    // Timer updates are now handled reactively via currentTime
                    // Just mark this timer as active for cleanup purposes
                    this.activeTimers[timerKey] = true;
                },
                stopTimer(caseId, stageId) {
                    const timerKey = `${caseId}-${stageId}`;
                    if (this.activeTimers[timerKey]) {
                        delete this.activeTimers[timerKey];
                    }
                },
                formatTimer(seconds) {
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    const secs = seconds % 60;
                    
                    if (hours > 0) {
                        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                    } else {
                        return `${minutes}:${secs.toString().padStart(2, '0')}`;
                    }
                },
                hasTimer(caseId, stageId) {
                    const caseItem = this.cases[stageId]?.find(c => c.id == caseId);
                    return !!(caseItem && caseItem.stage_timers && caseItem.stage_timers[stageId] && caseItem.repair_assignments && caseItem.repair_assignments[stageId]);
                },
                getTimerDisplay(caseId, stageId) {
                    const caseItem = this.cases[stageId]?.find(c => c.id == caseId);
                    if (!caseItem) return '';

                    const hasAssignment = caseItem.repair_assignments && caseItem.repair_assignments[stageId];
                    const hasTimer = caseItem.stage_timers && caseItem.stage_timers[stageId];

                    if (hasTimer) {
                        const startTime = Number(caseItem.stage_timers[stageId]);
                        if (!startTime) return '00:00';
                        const elapsed = Math.floor((this.currentTime - startTime) / 1000);
                        if (elapsed < 0) return '00:00';
                        return this.formatTimer(elapsed);
                    }

                    // If there is an assignment but timer not yet present, show a starting label
                    if (hasAssignment && !hasTimer) return 'Starting…';

                    return '';
                },


                getFilteredCases(stageId) {
                    const stageCases = this.cases[stageId] || [];
                    if (stageId !== 'backlog') {
                        return stageCases;
                    }
                    
                    // Sort backlog cases by newest first (higher ID)
                    let filteredCases = [...stageCases].sort((a, b) => b.id - a.id);
                    
                    // Apply search filter if there's a search term
                    if (this.backlogSearch.trim()) {
                        const searchTerm = this.backlogSearch.toLowerCase().trim();
                        filteredCases = filteredCases.filter(caseItem => {
                            const plate = (caseItem.plate || '').toLowerCase();
                            const make = (caseItem.vehicle_make || '').toLowerCase();
                            const model = (caseItem.vehicle_model || '').toLowerCase();
                            const caseId = String(caseItem.id).toLowerCase();
                            
                            return plate.includes(searchTerm) || 
                                   make.includes(searchTerm) || 
                                   model.includes(searchTerm) || 
                                   caseId.includes(searchTerm) ||
                                   `${make} ${model}`.toLowerCase().includes(searchTerm);
                        });
                    }
                    
                    // Apply pagination
                    const startIndex = 0;
                    const endIndex = this.backlogPage * this.backlogPageSize;
                    return filteredCases.slice(startIndex, endIndex);
                },
                getTotalBacklogCount() {
                    const stageCases = this.cases['backlog'] || [];
                    if (!this.backlogSearch.trim()) {
                        return stageCases.length;
                    }
                    
                    const searchTerm = this.backlogSearch.toLowerCase().trim();
                    return stageCases.filter(caseItem => {
                        const plate = (caseItem.plate || '').toLowerCase();
                        const make = (caseItem.vehicle_make || '').toLowerCase();
                        const model = (caseItem.vehicle_model || '').toLowerCase();
                        const caseId = String(caseItem.id).toLowerCase();
                        
                        return plate.includes(searchTerm) || 
                               make.includes(searchTerm) || 
                               model.includes(searchTerm) || 
                               caseId.includes(searchTerm) ||
                               `${make} ${model}`.toLowerCase().includes(searchTerm);
                    }).length;
                },
                loadMoreBacklog() {
                    this.backlogPage++;
                },
                hasMoreBacklog() {
                    const stageCases = this.cases['backlog'] || [];
                    let filteredCases = [...stageCases].sort((a, b) => b.id - a.id);
                    
                    if (this.backlogSearch.trim()) {
                        const searchTerm = this.backlogSearch.toLowerCase().trim();
                        filteredCases = filteredCases.filter(caseItem => {
                            const plate = (caseItem.plate || '').toLowerCase();
                            const make = (caseItem.vehicle_make || '').toLowerCase();
                            const model = (caseItem.vehicle_model || '').toLowerCase();
                            const caseId = String(caseItem.id).toLowerCase();
                            
                            return plate.includes(searchTerm) || 
                                   make.includes(searchTerm) || 
                                   model.includes(searchTerm) || 
                                   caseId.includes(searchTerm) ||
                                   `${make} ${model}`.toLowerCase().includes(searchTerm);
                        });
                    }
                    
                    return filteredCases.length > this.backlogPage * this.backlogPageSize;
                },
                dumpTimers(caseId, stageId) {
                    const caseItem = this.cases[stageId]?.find(c => c.id == caseId);
                    if (!caseItem) return '{}';
                    try {
                        return JSON.stringify(caseItem.stage_timers || {});
                    } catch (e) {
                        return 'invalid timers';
                    }
                },
                updateUrgent(caseId, urgent) {
                    // Find the case in any stage
                    let caseItem = null;
                    let stageId = null;
                    for (const sId in this.cases) {
                        caseItem = this.cases[sId].find(c => c.id == caseId);
                        if (caseItem) {
                            stageId = sId;
                            break;
                        }
                    }
                    if (!caseItem) return;

                    // Optimistically update UI
                    caseItem.urgent = urgent;
                    this.cases = { ...this.cases };

                    fetch('api.php?action=update_urgent', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ case_id: caseId, urgent: urgent ? 1 : 0 })
                    }).then(res => res.json()).then(data => {
                        if (data.status === 'success') {
                            showToast('Urgent Status Updated', urgent ? 'Marked as urgent' : 'Unmarked as urgent', 'success');
                        } else {
                            showToast('Error', 'Failed to update urgent status.', 'error');
                            // Revert on failure
                            caseItem.urgent = !urgent;
                            this.cases = { ...this.cases };
                        }
                    }).catch(() => {
                        showToast('Error', 'Failed to update urgent status.', 'error');
                        // Revert on failure
                        caseItem.urgent = !urgent;
                        this.cases = { ...this.cases };
                    });
                },
            }));
        });
        
        // Standard toast function from other pages
        function showToast(title, message = '', type = 'success', duration = 4000) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            const colors = {
                success: { border: 'border-emerald-200', iconBg: 'bg-emerald-100', iconColor: 'text-emerald-600', icon: 'check-circle-2' },
                error: { border: 'border-red-200', iconBg: 'bg-red-100', iconColor: 'text-red-600', icon: 'alert-circle' },
                info: { border: 'border-blue-200', iconBg: 'bg-blue-100', iconColor: 'text-blue-600', icon: 'info' }
            };
            const style = colors[type] || colors.info;
            
            // Create icon element first
            const iconEl = document.createElement('i');
            iconEl.setAttribute('data-lucide', style.icon);
            iconEl.className = `w-6 h-6 ${style.iconColor}`;
            
            toast.className = `pointer-events-auto w-96 bg-white border ${style.border} shadow-lg rounded-xl p-4 flex items-start gap-4 transform transition-all duration-300 translate-x-full`;
            toast.innerHTML = `
                <div class="${style.iconBg} p-2 rounded-full"></div>
                <div class="flex-1"><h4 class="text-md font-bold text-slate-800">${title}</h4>${message ? `<p class="text-sm text-slate-600 mt-1">${message}</p>` : ''}</div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 p-1 -mt-1 -mr-1"><i data-lucide="x" class="w-5 h-5"></i></button>`;
            
            // Insert the icon into the icon container
            toast.querySelector(`.${style.iconBg}`).appendChild(iconEl);
            
            container.appendChild(toast);
            setTimeout(() => lucide.createIcons(), 0);
            requestAnimationFrame(() => toast.classList.remove('translate-x-full'));
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    </script>
</body>
</html>
