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

// Fetch users who can be assigned (technicians) - assuming managers and admins
$stmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'manager') ORDER BY full_name");
$technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define the workflow stages
$stages = [
    ['id' => 'backlog', 'title' => __('workflow.stage.backlog', 'Backlog')],
    ['id' => 'disassembly', 'title' => __('workflow.stage.disassembly', 'Disassembly')],
    ['id' => 'body_work', 'title' => __('workflow.stage.body_work', 'Body Work')],
    ['id' => 'processing_for_painting', 'title' => __('workflow.stage.processing_for_painting', 'Processing for Painting')],
    ['id' => 'preparing_for_painting', 'title' => __('workflow.stage.preparing_for_painting', 'Preparing for Painting')],
    ['id' => 'painting', 'title' => __('workflow.stage.painting', 'Painting')],
    ['id' => 'assembling', 'title' => __('workflow.stage.assembling', 'Assembling')],
];

// Fetch cases for the workflow board
$stmt = $pdo->query("
    SELECT id, plate, vehicle_make, vehicle_model, repair_stage, repair_assignments, stage_timers, stage_statuses
    FROM transfers
    WHERE status IN ('Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled')
    ORDER BY 
        CASE WHEN repair_stage IS NULL THEN 0 ELSE 1 END,
        id DESC
");
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <div class="flex space-x-4 min-w-max">
                    <template x-for="stage in stages" :key="stage.id">
                        <div :class="stage.id === 'backlog' ? 'w-72 bg-amber-100/60 rounded-xl shadow-sm flex-shrink-0 border-2 border-amber-200' : 'w-72 bg-slate-200/60 rounded-xl shadow-sm flex-shrink-0'">
                            <div class="p-4 border-b border-slate-300">
                                <h3 :class="stage.id === 'backlog' ? 'text-lg font-semibold text-amber-800 flex items-center justify-between' : 'text-lg font-semibold text-slate-700 flex items-center justify-between'">
                                    <span x-text="stage.title"></span>
                                    <span :class="stage.id === 'backlog' ? 'text-sm font-medium bg-amber-200 text-amber-700 rounded-full px-2 py-0.5' : 'text-sm font-medium bg-slate-300 text-slate-600 rounded-full px-2 py-0.5'" x-text="cases[stage.id] ? cases[stage.id].length : 0"></span>
                                </h3>
                            </div>
                            <div :class="stage.id === 'backlog' ? 'p-4 space-y-4 min-h-[60vh] bg-amber-50/50' : 'p-4 space-y-4 min-h-[60vh]'" :data-stage-id="stage.id" x-ref="`stage-${stage.id}`">
                                <template x-for="caseItem in cases[stage.id]" :key="caseItem.id">
                                    <div :class="{'blink-finished': caseItem.stage_statuses && caseItem.stage_statuses[stage.id] && caseItem.stage_statuses[stage.id].status === 'finished'}" class="bg-white rounded-lg p-4 shadow-md case-card" :data-case-id="caseItem.id">
                                        <div class="font-bold text-slate-800" x-text="`${caseItem.vehicle_make} ${caseItem.vehicle_model}`"></div>
                                        <div class="text-sm text-slate-500 flex items-center justify-between">
                                            <span x-text="`${caseItem.plate} - #${caseItem.id}`"></span>
                                            <span x-show="stage.id !== 'backlog' && hasTimer(caseItem.id, stage.id)" class="ml-2 inline-flex items-center gap-2 px-2 py-0.5 rounded-full text-sm font-semibold bg-amber-100 text-amber-800 border border-amber-200" x-text="getTimerDisplay(caseItem.id, stage.id)"></span>
                                            <span x-show="stage.id !== 'backlog' && !hasTimer(caseItem.id, stage.id)" class="ml-2 text-xs text-slate-400">—</span>
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
                    });
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


                dumpTimers(caseId, stageId) {
                    const caseItem = this.cases[stageId]?.find(c => c.id == caseId);
                    if (!caseItem) return '{}';
                    try {
                        return JSON.stringify(caseItem.stage_timers || {});
                    } catch (e) {
                        return 'invalid timers';
                    }
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
