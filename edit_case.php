<?php
session_start();
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get case ID from URL
$case_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$case_id) {
    header('Location: index.php');
    exit;
}

// Get current user info
$current_user_name = $_SESSION['full_name'] ?? 'Manager';
$current_user_role = $_SESSION['role'] ?? 'manager';

// Check permissions
$CAN_EDIT = in_array($current_user_role, ['admin', 'manager']);

// Manager phone number for notifications
define('MANAGER_PHONE', '511144486');

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch case data
$stmt = $pdo->prepare("
    SELECT t.*, v.ownerName as vehicle_owner, v.model as vehicle_model
    FROM transfers t
    LEFT JOIN vehicles v ON t.plate = v.plate
    WHERE t.id = ?
");
$stmt->execute([$case_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    header('Location: index.php');
    exit;
}

// Decode JSON fields
$case['internalNotes'] = json_decode($case['internalNotes'] ?? '[]', true);
$case['systemLogs'] = json_decode($case['systemLogs'] ?? '[]', true);
$case['repair_parts'] = json_decode($case['repair_parts'] ?? '[]', true);
$case['repair_labor'] = json_decode($case['repair_labor'] ?? '[]', true);
$case['repair_activity_log'] = json_decode($case['repair_activity_log'] ?? '[]', true);

// Get SMS templates for workflow bindings
$smsTemplates = [];
$smsWorkflowBindings = [];

try {
    $stmt = $pdo->query("SELECT * FROM sms_templates WHERE is_active = 1 ORDER BY slug");
    while ($template = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $smsTemplates[$template['slug']] = $template;
        $workflowStages = json_decode($template['workflow_stages'] ?? '[]', true);
        foreach ($workflowStages as $stage) {
            if (!isset($smsWorkflowBindings[$stage])) {
                $smsWorkflowBindings[$stage] = [];
            }
            $smsWorkflowBindings[$stage][] = $template;
        }
    }
} catch (Exception $e) {
    // SMS templates table might not exist yet - templates will be empty
    $smsTemplates = [];
    $smsWorkflowBindings = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_language(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('case.title', 'Edit Case'); ?> #<?php echo $case_id; ?> - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.378.0/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 2px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        [x-cloak] { display: none !important; }
        .step-complete .step-line { background-color: #2563eb; }
        .step-complete .step-icon { background-color: #2563eb; color: white; }
        .step-current .step-icon { background-color: #2563eb; color: white; border-color: #2563eb; }
        .step-incomplete .step-icon { background-color: white; color: #6b7280; border-color: #d1d5db; }
    </style>
</head>

<body class="bg-slate-100" x-data="caseEditor()">
    <div id="toast-container" class="fixed top-6 right-6 z-[100] space-y-3"></div>

    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 ml-64 py-10 px-6">
            <!-- Page Header (now inside main content) -->
            <div class="mb-8">
                <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 mb-2">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span><?php echo __('case.back_to_dashboard', 'Back to Dashboard'); ?></span>
                </a>
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <div class="flex items-center gap-4">
                        <h1 class="text-3xl font-bold text-slate-800">
                            Case #<?php echo $case_id; ?>: <?php echo htmlspecialchars($case['name']); ?>
                        </h1>
                        <span class="font-mono text-sm bg-blue-100 text-blue-800 px-2.5 py-1 rounded-full font-medium" x-text="currentCase.status"></span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="printCase()" class="text-slate-600 h-10 px-4 inline-flex items-center justify-center rounded-lg border bg-white hover:bg-slate-50 font-semibold text-sm"><?php echo __('case.print', 'Print'); ?></button>
                        <button @click="saveChanges()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold h-10 px-6 rounded-lg flex items-center gap-2 text-sm shadow-sm">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span><?php echo __('case.save_changes', 'Save Changes'); ?></span>
                        </button>
                    </div>
                </div>
            </div>

        <!-- Workflow Stepper -->
        <section class="mb-8">
             <div class="flex items-start justify-between -mx-2 sm:-mx-4">
                <template x-for="(status, index) in statuses" :key="status.id">
                    <div class="flex-1 px-2 sm:px-4" :class="{ 'step-complete': currentStatusIndex >= index, 'step-current': currentStatusIndex === index, 'step-incomplete': currentStatusIndex < index }">
                        <div @click="setStatus(status.id)" class="flex flex-col sm:flex-row items-center gap-3 cursor-pointer group">
                            <div class="step-icon flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full border-2 font-bold transition-all duration-300">
                                <i :data-lucide="status.icon" class="w-5 h-5"></i>
                            </div>
                            <div class="hidden sm:block text-sm font-semibold text-slate-600 group-hover:text-slate-900 transition" x-text="status.name"></div>
                            <div class="step-line flex-1 w-full h-1 mt-2 sm:mt-0 bg-slate-200 transition-all duration-300"></div>
                        </div>
                    </div>
                </template>
            </div>
             <input type="hidden" id="input-status" :value="currentCase.status">
        </section>


        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Collapsible Section: Case Details -->
                <div class="bg-white rounded-2xl border border-slate-200/80">
                    <button @click="toggleSection('details')" class="w-full flex items-center justify-between p-5">
                        <h2 class="text-xl font-bold text-slate-800"><?php echo __('case.details', 'Case Details'); ?></h2>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform" :class="{'rotate-180': openSections.includes('details')}"></i>
                    </button>
                    <div x-show="openSections.includes('details')" x-cloak x-transition class="px-5 pb-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 border-t border-slate-200 pt-6">
                            <!-- Form Fields -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.customer_name', 'Customer Name'); ?></label>
                                <input id="input-name" type="text" value="<?php echo htmlspecialchars($case['name']); ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                             <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.vehicle_plate', 'Vehicle Plate'); ?></label>
                                <input id="input-plate" type="text" value="<?php echo htmlspecialchars($case['plate']); ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.phone_number', 'Phone Number'); ?></label>
                                <div class="flex items-center gap-2">
                                    <input id="input-phone" type="text" value="<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                                    <a id="btn-call-real" href="tel:<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" class="h-10 w-10 flex-shrink-0 flex items-center justify-center rounded-lg border bg-white hover:bg-slate-50"><i data-lucide="phone" class="w-4 h-4 text-slate-600"></i></a>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.service_date', 'Service Date'); ?></label>
                                <input id="input-service-date" type="datetime-local" value="<?php echo $case['service_date'] ? date('Y-m-d\TH:i', strtotime($case['service_date'])) : ''; ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.due_date', 'Due Date'); ?></label>
                                <input id="input-due-date" type="datetime-local" value="<?php echo $case['due_date'] ? date('Y-m-d\TH:i', strtotime($case['due_date'])) : ''; ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.amount', 'Amount'); ?> (₾)</label>
                                <input id="input-amount" type="text" value="<?php echo htmlspecialchars($case['amount']); ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.franchise', 'Franchise'); ?> (₾)</label>
                                <input id="input-franchise" type="number" value="<?php echo htmlspecialchars($case['franchise'] ?? 0); ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collapsible Section: Repair Management -->
                <div class="bg-white rounded-2xl border border-slate-200/80">
                    <button @click="toggleSection('repair')" class="w-full flex items-center justify-between p-5">
                        <h2 class="text-xl font-bold text-slate-800"><?php echo __('case.repair_management', 'Repair Management'); ?></h2>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform" :class="{'rotate-180': openSections.includes('repair')}"></i>
                    </button>
                    <div x-show="openSections.includes('repair')" x-cloak x-transition class="px-5 pb-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 border-t border-slate-200 pt-6">
                            <!-- Repair Status -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.repair_status', 'Repair Status'); ?></label>
                                <select x-model="currentCase.repair_status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                                    <option value=""><?php echo __('case.not_started', 'Not Started'); ?></option>
                                    <option value="Repair Started"><?php echo __('case.repair_started', 'Repair Started'); ?></option>
                                    <option value="In Progress"><?php echo __('case.in_progress', 'In Progress'); ?></option>
                                    <option value="Parts Waiting"><?php echo __('case.parts_waiting', 'Parts Waiting'); ?></option>
                                    <option value="Repair Completed"><?php echo __('case.repair_completed', 'Repair Completed'); ?></option>
                                </select>
                            </div>
                            <!-- Assigned Mechanic -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.assigned_mechanic', 'Assigned Mechanic'); ?></label>
                                <input x-model="currentCase.assigned_mechanic" type="text" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                            <!-- Repair Start Date -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.repair_start_date', 'Repair Start Date'); ?></label>
                                <input x-model="currentCase.repair_start_date" type="datetime-local" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                            <!-- Repair End Date -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.repair_end_date', 'Repair End Date'); ?></label>
                                <input x-model="currentCase.repair_end_date" type="datetime-local" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                            <!-- Repair Notes -->
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.repair_notes', 'Repair Notes'); ?></label>
                                <textarea x-model="currentCase.repair_notes" rows="3" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none resize-vertical"></textarea>
                            </div>
                        </div>
                        <!-- Items & Activity -->
                        <div class="mt-6 border-t border-slate-200 pt-6">
                            <div class="flex items-center justify-center mb-4">
                                <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-lg">
                                    <button @click="repairTab = 'items'" :class="{'bg-white text-blue-600 shadow-sm': repairTab === 'items'}" class="px-4 py-1.5 text-sm font-semibold rounded-md text-slate-600">Items</button>
                                    <button @click="repairTab = 'activity'" :class="{'bg-white text-blue-600 shadow-sm': repairTab === 'activity'}" class="px-4 py-1.5 text-sm font-semibold rounded-md text-slate-600">Activity History</button>
                                </div>
                            </div>

                            <!-- Combined Items View -->
                            <div x-show="repairTab === 'items'" x-cloak class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-slate-800">Parts & Labor</h3>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="showInvoice()" class="bg-white border border-slate-200 text-slate-700 px-2 py-1 rounded text-sm">Invoice</button>
                                        <button type="button" onclick="window.caseEditor.exportAllCSV()" class="bg-white border border-slate-200 text-slate-700 px-2 py-1 rounded text-sm">Export CSV</button>
                                        <button type="button" onclick="window.caseEditor.exportAllXLS()" class="bg-white border border-slate-200 text-slate-700 px-2 py-1 rounded text-sm">Export XLS</button>
                                        <button type="button" onclick="window.caseEditor.exportAllXLSX()" class="bg-white border border-slate-200 text-slate-700 px-2 py-1 rounded text-sm">Export XLSX</button>
                                        <button @click="addPart()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-3 rounded-lg text-sm flex items-center gap-1">
                                            <i data-lucide="plus" class="w-4 h-4"></i> Add Part
                                        </button>
                                        <button @click="addLabor()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-3 rounded-lg text-sm flex items-center gap-1">
                                            <i data-lucide="plus" class="w-4 h-4"></i> Add Labor
                                        </button>
                                    </div>
                                </div>

                                <!-- Import controls -->
                                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex items-center gap-3">
                                    <div class="flex items-center gap-2">
                                        <input type="file" id="repairPdfInput" accept=".pdf" class="text-sm">
                                        <button type="button" id="parseRepairPdfBtn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-1.5 px-3 rounded-lg text-sm flex items-center gap-1 disabled:opacity-50" disabled>
                                            <i data-lucide="file-text" class="w-4 h-4"></i> Parse PDF
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <input type="file" id="partsCsvInput" accept=".csv" class="text-sm">
                                        <button type="button" id="importPartsCsvBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-1 px-3 rounded text-sm">Import Parts CSV</button>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <input type="file" id="laborCsvInput" accept=".csv" class="text-sm">
                                        <button type="button" id="importLaborCsvBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-1 px-3 rounded text-sm">Import Labor CSV</button>
                                    </div>

                                    <div id="repairPdfStatus" class="text-sm text-slate-600 ml-auto"></div>
                                </div>

                                <div id="repairParsedPreview" class="mt-3"></div>

                                <!-- Collections (existing parts collections for this case) -->
                                <div id="collectionsList" class="mt-3 space-y-3">
                                    <!-- Populated by JS: collection cards -->
                                </div>

                                <!-- Combined items list -->
                                <div id="itemsList" class="space-y-3">
                                    <!-- Parts and Labor entries will render here together -->
                                </div>

                                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-slate-600">Notes for Collection:</div>
                                        <input id="collectionNote" class="w-2/3 px-2 py-1 rounded border border-slate-200 text-sm" placeholder="Optional note to include with parts collection" x-model="collectionNote">
                                    </div>
                                    <div class="text-right font-semibold text-slate-800 mt-3">Parts Cost: <span id="parts-total">0.00₾</span> &nbsp; | &nbsp; Labor Cost: <span id="labor-total">0.00₾</span></div>
                                </div>
                            </div>

                            <!-- Activity History Tab (unchanged) -->
                            <div x-show="repairTab === 'activity'" x-cloak class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-lg font-semibold text-slate-800">Repair Activity History</h3>
                                    <button @click="addActivity()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-3 rounded-lg text-sm flex items-center gap-1">
                                        <i data-lucide="plus" class="w-4 h-4"></i> Add Activity
                                    </button>
                                </div>
                                <div class="space-y-3 max-h-96 overflow-y-auto" id="activity-log">
                                    <?php foreach (array_reverse($case['repair_activity_log'] ?? []) as $activity): ?>
                                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <p class="text-sm text-slate-800 font-medium"><?php echo htmlspecialchars($activity['action'] ?? ''); ?></p>
                                                <p class="text-xs text-slate-600 mt-1"><?php echo htmlspecialchars($activity['details'] ?? ''); ?></p>
                                            </div>
                                            <div class="text-right text-xs text-slate-500">
                                                <div><?php echo htmlspecialchars($activity['user'] ?? ''); ?></div>
                                                <div><?php echo isset($activity['timestamp']) ? date('M j, Y H:i', strtotime($activity['timestamp'])) : ''; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <!-- Repair Cost Summary -->
                        <div class="mt-6 border-t border-slate-200 pt-6">
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-3">
                                    <h3 class="text-lg font-semibold text-slate-800">Repair Cost Summary</h3>
                                    <div class="flex items-center gap-2">
                                        <button onclick="window.caseEditor.exportAllCSV()" class="bg-white border border-slate-200 text-slate-700 px-2 py-1 rounded text-sm">Export CSV</button>
                                        <button onclick="window.caseEditor.exportAllXLS()" class="bg-white border border-slate-200 text-slate-700 px-2 py-1 rounded text-sm">Export XLS</button>
                                        <button onclick="window.caseEditor.exportAllXLSX()" class="bg-white border border-slate-200 text-slate-700 px-2 py-1 rounded text-sm">Export XLSX</button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-600" id="summary-parts-total">0.00₾</div>
                                        <div class="text-sm text-slate-600">Parts Cost</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-600" id="summary-labor-total">0.00₾</div>
                                        <div class="text-sm text-slate-600">Labor Cost</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-slate-800" id="summary-grand-total">0.00₾</div>
                                        <div class="text-sm text-slate-600">Total Cost</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reschedule Request -->
                <?php if ($case['user_response'] === 'Reschedule Requested' && !empty($case['rescheduleDate'])): ?>
                <div class="bg-yellow-50/80 border-2 border-yellow-200 rounded-2xl p-5">
                    <div class="flex items-start gap-4">
                        <i data-lucide="calendar-clock" class="w-8 h-8 text-yellow-600 mt-1 flex-shrink-0"></i>
                        <div>
                            <h3 class="text-lg font-bold text-yellow-900"><?php echo __('case.reschedule_request', 'Reschedule Request Pending'); ?></h3>
                            <p class="font-bold text-slate-700 mt-2"><?php echo __('case.requested', 'Requested'); ?>: <span class="font-normal"><?php echo date('M j, Y g:i A', strtotime($case['rescheduleDate'])); ?></span></p>
                            <?php if (!empty($case['rescheduleComment'])): ?>
                            <p class="text-sm text-slate-600 mt-1 italic">"<?php echo htmlspecialchars($case['rescheduleComment']); ?>"</p>
                            <?php endif; ?>
                            <div class="flex gap-2 mt-4">
                                <button onclick="acceptReschedule()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-1.5 px-4 rounded-md text-sm"><?php echo __('case.accept', 'Accept'); ?></button>
                                <button onclick="declineReschedule()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-1.5 px-4 rounded-md text-sm"><?php echo __('case.decline', 'Decline'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Collapsible Section: Communication -->
                 <div class="bg-white rounded-2xl border border-slate-200/80">
                    <button @click="toggleSection('communication')" class="w-full flex items-center justify-between p-5">
                        <h2 class="text-xl font-bold text-slate-800"><?php echo __('case.communication', 'Communication'); ?></h2>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform" :class="{'rotate-180': openSections.includes('communication')}"></i>
                    </button>
                    <div x-show="openSections.includes('communication')" x-cloak x-transition class="px-5 pb-6">
                        <div class="border-t border-slate-200 pt-6 space-y-5">
                            <div class="flex items-center justify-center">
                                 <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-lg">
                                    <button @click="activeTab = 'quick'" :class="{'bg-white text-blue-600 shadow-sm': activeTab === 'quick'}" class="px-4 py-1.5 text-sm font-semibold rounded-md text-slate-600"><?php echo __('case.quick_sms', 'Quick SMS'); ?></button>
                                    <button @click="activeTab = 'advanced'" :class="{'bg-white text-blue-600 shadow-sm': activeTab === 'advanced'}" class="px-4 py-1.5 text-sm font-semibold rounded-md text-slate-600"><?php echo __('case.advanced_sms', 'Advanced SMS'); ?></button>
                                </div>
                            </div>
                            <!-- Quick SMS -->
                            <div x-show="activeTab === 'quick'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                                <button id="btn-sms-register" class="text-center p-4 bg-slate-50/80 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-lg group transition"><i data-lucide="party-popper" class="w-7 h-7 mx-auto text-slate-500 group-hover:text-blue-600"></i><span class="text-xs mt-2 block font-semibold"><?php echo __('case.welcome', 'Welcome'); ?></span></button>
                                <button id="btn-sms-called" class="text-center p-4 bg-slate-50/80 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-lg group transition"><i data-lucide="phone-outgoing" class="w-7 h-7 mx-auto text-slate-500 group-hover:text-blue-600"></i><span class="text-xs mt-2 block font-semibold"><?php echo __('case.called', 'Called'); ?></span></button>
                                <button id="btn-sms-arrived" class="text-center p-4 bg-slate-50/80 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-lg group transition"><i data-lucide="package-check" class="w-7 h-7 mx-auto text-slate-500 group-hover:text-blue-600"></i><span class="text-xs mt-2 block font-semibold"><?php echo __('case.parts_arrived', 'Parts Arrived'); ?></span></button>
                                <button id="btn-sms-schedule" class="text-center p-4 bg-slate-50/80 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-lg group transition"><i data-lucide="calendar-check" class="w-7 h-7 mx-auto text-slate-500 group-hover:text-blue-600"></i><span class="text-xs mt-2 block font-semibold"><?php echo __('case.scheduled', 'Scheduled'); ?></span></button>
                                <button id="btn-sms-completed" class="text-center p-4 bg-slate-50/80 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-lg group transition"><i data-lucide="check-circle" class="w-7 h-7 mx-auto text-slate-500 group-hover:text-blue-600"></i><span class="text-xs mt-2 block font-semibold"><?php echo __('case.completed', 'Completed'); ?></span></button>
                            </div>
                            <!-- Advanced SMS -->
                            <div x-show="activeTab === 'advanced'" x-cloak class="space-y-3">
                                 <select id="sms-template-selector" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm">
                                    <option value=""><?php echo __('case.choose_template', 'Choose a template...'); ?></option>
                                    <?php foreach ($smsTemplates as $slug => $template): ?>
                                    <option value="<?php echo htmlspecialchars($slug); ?>"><?php echo htmlspecialchars($template['name'] ?? ucfirst(str_replace('_', ' ', $slug))); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="sms-preview" class="bg-slate-100 border border-slate-200 rounded-lg p-3 min-h-[120px] text-sm text-slate-700 whitespace-pre-wrap"><span class="text-slate-400 italic"><?php echo __('case.select_template', 'Select a template...'); ?></span></div>
                                <button id="btn-send-custom-sms" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg flex items-center justify-center gap-2 text-sm">
                                    <i data-lucide="send" class="w-4 h-4"></i> <?php echo __('case.send_custom_sms', 'Send Custom SMS'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                 <!-- Collapsible Section: Customer Feedback -->
                <div class="bg-white rounded-2xl border border-slate-200/80">
                    <button @click="toggleSection('feedback')" class="w-full flex items-center justify-between p-5">
                        <h2 class="text-xl font-bold text-slate-800"><?php echo __('case.customer_feedback', 'Customer Feedback'); ?></h2>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform" :class="{'rotate-180': openSections.includes('feedback')}"></i>
                    </button>
                    <div x-show="openSections.includes('feedback')" x-cloak x-transition class="px-5 pb-6">
                        <div class="border-t border-slate-200 pt-6">
                             <div class="flex justify-end mb-4 -mt-2">
                                <button @click="editingReview = !editingReview" id="btn-edit-review" class="text-sm font-semibold text-blue-600 hover:underline">
                                    <span x-show="!editingReview"><?php echo __('case.edit', 'Edit'); ?></span>
                                    <span x-show="editingReview"><?php echo __('case.cancel', 'Cancel'); ?></span>
                                </button>
                            </div>
                            <!-- Display View -->
                            <div id="review-display" x-show="!editingReview">
                                <?php if (empty($case['review_stars'])): ?>
                                    <div class="text-center py-6 text-slate-500 text-sm"><?php echo __('case.no_review', 'No review submitted yet.'); ?></div>
                                <?php else: ?>
                                    <div class="flex items-center gap-4">
                                        <div class="flex gap-1">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i data-lucide="star" class="w-6 h-6 <?php echo $i <= $case['review_stars'] ? 'text-amber-400 fill-amber-400' : 'text-slate-300'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-xl font-bold text-slate-700"><?php echo $case['review_stars']; ?> out of 5</span>
                                    </div>
                                    <?php if (!empty($case['review_comment'])): ?>
                                    <blockquote class="bg-slate-50 p-4 rounded-lg border border-slate-200 mt-4 text-sm text-slate-700 italic">"<?php echo htmlspecialchars($case['review_comment']); ?>"</blockquote>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <!-- Edit View -->
                             <div id="review-edit" x-show="editingReview" x-cloak class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-600 mb-1.5"><?php echo __('case.rating', 'Rating'); ?></label>
                                    <select id="input-review-stars" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5">
                                        <option value=""><?php echo __('case.no_rating', 'No rating'); ?></option>
                                        <option value="1" <?php echo $case['review_stars'] == 1 ? 'selected' : ''; ?>>⭐ 1 <?php echo __('case.star', 'Star'); ?></option>
                                        <option value="2" <?php echo $case['review_stars'] == 2 ? 'selected' : ''; ?>>⭐⭐ 2 <?php echo __('case.stars', 'Stars'); ?></option>
                                        <option value="3" <?php echo $case['review_stars'] == 3 ? 'selected' : ''; ?>>⭐⭐⭐ 3 <?php echo __('case.stars', 'Stars'); ?></option>
                                        <option value="4" <?php echo $case['review_stars'] == 4 ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4 <?php echo __('case.stars', 'Stars'); ?></option>
                                        <option value="5" <?php echo $case['review_stars'] == 5 ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 <?php echo __('case.stars', 'Stars'); ?></option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-600 mb-1.5"><?php echo __('case.comment', 'Comment'); ?></label>
                                    <textarea id="input-review-comment" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5"><?php echo htmlspecialchars($case['review_comment'] ?? ''); ?></textarea>
                                </div>
                                <button id="btn-save-review" @click="editingReview = false" class="w-full bg-blue-600 text-white font-bold py-2.5 px-4 rounded-lg text-sm"><?php echo __('case.save_review', 'Save Review'); ?></button>
                            </div>
                        </div>
                    </div>
                 </div>

            </div>

            <!-- Sidebar -->
            <aside class="lg:col-span-1 space-y-6 lg:sticky lg:top-8 self-start">
                 <!-- Internal Notes -->
                <div class="bg-white rounded-2xl border border-slate-200/80">
                    <h2 class="text-xl font-bold text-slate-800 p-5"><?php echo __('case.internal_notes', 'Internal Notes'); ?></h2>
                    <div class="px-5 pb-5 border-t border-slate-200">
                        <div class="flex gap-2 my-5">
                            <input id="new-note-input" type="text" placeholder="<?php echo __('case.add_note_placeholder', 'Add a new note...'); ?>" class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/50 outline-none">
                            <button onclick="addNote()" class="bg-slate-800 hover:bg-slate-900 text-white px-4 rounded-lg font-semibold text-sm"><?php echo __('case.add', 'Add'); ?></button>
                        </div>
                        <div id="notes-container" class="space-y-3 max-h-72 overflow-y-auto custom-scrollbar -mr-3 pr-3">
                             <?php if (empty($case['internalNotes'])): ?>
                                <div class="text-center py-4 text-slate-500 text-sm"><?php echo __('case.no_internal_notes', 'No internal notes yet.'); ?></div>
                            <?php else: ?>
                                <?php foreach (array_reverse($case['internalNotes']) as $note): ?>
                                <div class='bg-slate-100 p-3 rounded-lg border border-slate-200/80'>
                                    <p class='text-sm text-slate-800'><?php echo htmlspecialchars($note['text']); ?></p>
                                    <div class='text-xs text-slate-500 text-right mt-2'><?php echo htmlspecialchars($note['authorName'] ?? 'Manager'); ?> &middot; <?php echo date('M j, g:i A', strtotime($note['timestamp'])); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Tabs -->
                <div x-data="{ tab: 'activity' }" class="bg-white rounded-2xl border border-slate-200/80">
                    <div class="p-3 border-b border-slate-200">
                        <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-lg">
                            <button @click="tab = 'activity'" :class="{'bg-white text-blue-600 shadow-sm': tab === 'activity'}" class="flex-1 px-3 py-1.5 text-sm font-semibold rounded-md text-slate-600"><?php echo __('case.activity', 'Activity'); ?></button>
                            <button @click="tab = 'vehicle'" :class="{'bg-white text-blue-600 shadow-sm': tab === 'vehicle'}" class="flex-1 px-3 py-1.5 text-sm font-semibold rounded-md text-slate-600"><?php echo __('case.vehicle', 'Vehicle'); ?></button>
                            <button @click="tab = 'parts'" :class="{'bg-white text-green-600 shadow-sm': tab === 'parts'}" class="flex-1 px-3 py-1.5 text-sm font-semibold rounded-md text-slate-600"><?php echo __('case.parts', 'Parts'); ?></button>
                            <button @click="tab = 'danger'" :class="{'bg-white text-red-600 shadow-sm': tab === 'danger'}" class="flex-1 px-3 py-1.5 text-sm font-semibold rounded-md text-slate-600"><?php echo __('case.danger', 'Danger'); ?></button>
                        </div>
                    </div>
                    <div class="p-5">
                        <!-- Activity Log -->
                        <div x-show="tab === 'activity'" id="activity-log-container" class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar -mr-3 pr-3">
                             <?php if (empty($case['systemLogs'])): ?>
                                <div class="text-center py-4 text-slate-500 text-sm"><?php echo __('case.no_activity', 'No activity recorded.'); ?></div>
                            <?php else: ?>
                                 <?php foreach (array_reverse($case['systemLogs']) as $log): ?>
                                    <div class='flex items-start gap-3'>
                                        <div class='bg-slate-100 rounded-full p-2 mt-0.5'><i data-lucide='history' class='w-4 h-4 text-slate-500'></i></div>
                                        <div>
                                            <p class='text-sm text-slate-700 font-medium'><?php echo htmlspecialchars($log['message']); ?></p>
                                            <time class='text-xs text-slate-400'><?php echo date('M j, Y, g:i A', strtotime($log['timestamp'])); ?></time>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <!-- Vehicle Info -->
                         <div x-show="tab === 'vehicle'" x-cloak class="space-y-3">
                            <div class="flex justify-between text-sm"><span class="font-medium text-slate-600"><?php echo __('case.owner', 'Owner'); ?>:</span> <span class="text-slate-500"><?php echo htmlspecialchars($case['vehicle_owner'] ?? 'N/A'); ?></span></div>
                            <div class="flex justify-between text-sm"><span class="font-medium text-slate-600"><?php echo __('case.model', 'Model'); ?>:</span> <span class="text-slate-500"><?php echo htmlspecialchars($case['vehicle_model'] ?? 'N/A'); ?></span></div>
                        </div>
                        <!-- Parts Request -->
                        <div x-show="tab === 'parts'" x-cloak class="space-y-4">
                            <h3 class="font-semibold text-slate-700"><?php echo __('case.request_parts', 'Request Parts Collection'); ?></h3>
                            <form @submit.prevent="requestParts" class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-600 mb-1"><?php echo __('case.description', 'Description'); ?></label>
                                    <textarea x-model="partsRequest.description" placeholder="<?php echo __('case.describe_request', 'Describe the parts collection request...'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" rows="3" required></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-600 mb-1"><?php echo __('case.supplier', 'Supplier (Optional)'); ?></label>
                                    <input x-model="partsRequest.supplier" type="text" placeholder="<?php echo __('case.supplier_name', 'Supplier name'); ?>" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-600 mb-1"><?php echo __('case.collection_type', 'Collection Type'); ?></label>
                                    <select x-model="partsRequest.collection_type" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                        <option value="local"><?php echo __('case.local_market', 'Local Market'); ?></option>
                                        <option value="order"><?php echo __('case.order', 'Order'); ?></option>
                                    </select>
                                </div>
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                                    <i data-lucide="plus" class="w-4 h-4 inline mr-2"></i>
                                    <?php echo __('case.create_request', 'Create Parts Request'); ?>
                                </button>
                            </form>
                        </div>
                        <!-- Danger Zone -->
                        <div x-show="tab === 'danger'" x-cloak>
                            <h3 class="font-bold text-red-700"><?php echo __('case.danger_zone', 'Danger Zone'); ?></h3>
                            <p class="text-sm text-red-600 mt-1"><?php echo __('case.permanent_action', 'This action is permanent and cannot be undone.'); ?></p>
                            <button onclick="deleteCase()" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg mt-4 text-sm"><?php echo __('case.delete_case', 'Delete This Case'); ?></button>
                        </div>
                    </div>
                </div>

            </aside>
        </div>
    </main>

    <script>
        const API_URL = 'api.php';
        const CASE_ID = <?php echo $case_id; ?>;
        const CAN_EDIT = <?php echo $CAN_EDIT ? 'true' : 'false'; ?>;
        
        let initialCaseData = {};
        try {
            initialCaseData = <?php echo json_encode($case, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
        } catch (e) { console.error('Error parsing case data:', e); initialCaseData = {}; }

        let smsTemplates = {};
        try {
            smsTemplates = <?php echo json_encode($smsTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
        } catch(e) { console.error('Error parsing sms templates'); }
        
        let smsWorkflowBindings = {};
        try {
            smsWorkflowBindings = <?php echo json_encode($smsWorkflowBindings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
        } catch(e) { console.error('Error parsing sms workflow bindings'); }

        let partSuggestions = [];
        let laborSuggestions = [];

        async function loadData(url, target) {
            try {
                const response = await fetch(url);
                const data = await response.json();
                if (target === 'partSuggestions') partSuggestions = data;
                else if (target === 'laborSuggestions') laborSuggestions = data;
                console.log(`${target} loaded:`, data.length, 'items');
            } catch (error) {
                console.error(`Failed to load ${target}:`, error);
            }
        }

        function setupAutocomplete(input, type) {
            const results = input.nextElementSibling;
            const suggestions = type === 'part' ? partSuggestions : laborSuggestions;
            input.addEventListener('input', () => {
                const val = input.value.toLowerCase();
                results.innerHTML = '';
                if (!val) { results.classList.add('hidden'); return; }
                const filtered = suggestions.filter(s => s.toLowerCase().includes(val));
                if (filtered.length) {
                    results.classList.remove('hidden');
                    filtered.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'p-2 hover:bg-gray-100 cursor-pointer text-sm';
                        div.textContent = item;
                        div.onclick = () => { input.value = item; results.classList.add('hidden'); };
                        results.appendChild(div);
                    });
                } else {
                    results.classList.add('hidden');
                }
            });
            document.addEventListener('click', e => { if (!input.parentElement.contains(e.target)) results.classList.add('hidden'); });
        }

        function caseEditor() {
            return {
                currentCase: { ...initialCaseData },
                collections: [],
                openSections: JSON.parse(localStorage.getItem('openSections')) || ['details', 'communication', 'feedback', 'repair'],
                partsRequest: { description: '', supplier: '', collection_type: 'local' },
                editingReview: false,
                activeTab: 'quick',
                repairTab: 'items',
                lastRemovedPart: null,
                lastRemovedTimer: null,
                markupPercentage: 0,
                collectionNote: '',
                dragPartIndex: null,
                dragLabIndex: null,
                statuses: [
                    { id: 'New', name: 'New', icon: 'file-plus-2' },
                    { id: 'Processing', name: 'Processing', icon: 'loader-circle' },
                    { id: 'Called', name: 'Contacted', icon: 'phone' },
                    { id: 'Parts Ordered', name: 'Parts Ordered', icon: 'box-select' },
                    { id: 'Parts Arrived', name: 'Parts Arrived', icon: 'package-check' },
                    { id: 'Scheduled', name: 'Scheduled', icon: 'calendar-days' },
                    { id: 'Completed', name: 'Completed', icon: 'check-circle-2' },
                    { id: 'Issue', name: 'Issue', icon: 'alert-triangle' },
                ],
                get currentStatusIndex() {
                    const index = this.statuses.findIndex(s => s.id === this.currentCase.status);
                    return index > -1 ? index : 0;
                },
                init() {
                    window.caseEditor = this;
                    this.$nextTick(() => initializeIcons());
                    document.getElementById('sms-template-selector')?.addEventListener('change', this.updateSmsPreview.bind(this));

                    // Load suggestions
                    loadData('api.php?action=get_item_suggestions&type=part', 'partSuggestions');
                    loadData('api.php?action=get_item_suggestions&type=labor', 'laborSuggestions');

                    // Auto-fill phone if plate exists in Vehicle DB
                    const plateEl = document.getElementById('input-plate');
                    const phoneEl = document.getElementById('input-phone');
                    const callBtn = document.getElementById('btn-call-real');
                    const normalizePlate = (p) => p ? p.replace(/[^a-zA-Z0-9]/g, '').toUpperCase() : '';

                    const lookupAndFillPhone = async (value) => {
                        const plate = (value || '').trim();
                        if (!plate) return;
                        if (!phoneEl || phoneEl.value.trim()) return; // don't overwrite existing phone
                        try {
                            const vehicles = await fetchAPI('get_vehicles');
                            if (!Array.isArray(vehicles)) return;
                            const match = vehicles.find(v => normalizePlate(v.plate) === normalizePlate(plate));
                            if (match && match.phone) {
                                phoneEl.value = match.phone;
                                if (callBtn) callBtn.href = 'tel:' + match.phone;
                                showToast('Phone Auto-filled', 'Phone number populated from Vehicle DB.', 'success');
                            }
                        } catch (e) {
                            // ignore lookup errors
                        }
                    };

                    plateEl?.addEventListener('blur', (e) => lookupAndFillPhone(e.target.value));
                    plateEl?.addEventListener('change', (e) => lookupAndFillPhone(e.target.value));
                    plateEl?.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') setTimeout(() => lookupAndFillPhone(e.target.value), 10);
                    });

                    // Try to auto-fill on load if we already have a plate and no phone
                    if (plateEl && plateEl.value) {
                        setTimeout(() => lookupAndFillPhone(plateEl.value), 50);
                    }

                    // Initialize repair tables
                    this.updatePartsList();
                    this.updateLaborList();
                    this.updateActivityLog();
                    this.updateRepairSummary();

                    // Initialize PDF parsing for repair
                    this.initRepairPdfParsing();

                    // Load existing parts collections for this case
                    if (typeof this.loadCollections === 'function') this.loadCollections();
                },
                isSectionOpen(section) {
                    return this.openSections.includes(section);
                },
                toggleSection(section) {
                    const index = this.openSections.indexOf(section);
                    if (index === -1) {
                        this.openSections.push(section);
                    } else {
                        this.openSections.splice(index, 1);
                    }
                    localStorage.setItem('openSections', JSON.stringify(this.openSections));
                },
                setStatus(statusId) {
                    this.currentCase.status = statusId;
                },
                updateSmsPreview() {
                    const selector = document.getElementById('sms-template-selector');
                    const preview = document.getElementById('sms-preview');
                    const templateSlug = selector.value;
                    if (!templateSlug) {
                        preview.innerHTML = '<span class="text-slate-400 italic">Select a template...</span>';
                        return;
                    }
                    const msg = getFormattedMessage(templateSlug, this.getTemplateData());
                    preview.textContent = msg;
                },
                getTemplateData() {
                    const publicUrl = `${window.location.origin}${window.location.pathname.replace('edit_case.php', 'public_view.php')}`;
                    return {
                        id: CASE_ID,
                        name: document.getElementById('input-name')?.value || this.currentCase.name,
                        plate: document.getElementById('input-plate')?.value || this.currentCase.plate,
                        amount: document.getElementById('input-amount')?.value || this.currentCase.amount,
                        date: document.getElementById('input-service-date')?.value || this.currentCase.serviceDate,
                        link: `${publicUrl}?id=${CASE_ID}`
                    };
                },
                printCase() { window.print(); },
                async saveChanges() {
                    if (!CAN_EDIT) return showToast('Permission Denied', 'You do not have permission to edit.', 'error');
                    
                    const status = this.currentCase.status;
                    let serviceDate = document.getElementById('input-service-date').value;
                    let dueDate = document.getElementById('input-due-date').value;

                    if ((status === 'Parts Arrived' || status === 'Scheduled') && !serviceDate) {
                        return showToast("Scheduling Required", `Please select a service date for the '${status}' status.`, "error");
                    }

                    // Format service date for MySQL (add seconds if missing)
                    if (serviceDate) {
                        serviceDate = serviceDate.replace('T', ' ');
                        if (serviceDate.split(':').length === 2) {
                            serviceDate += ':00';
                        }
                    }

                    // Format due date for MySQL (add seconds if missing)
                    if (dueDate) {
                        dueDate = dueDate.replace('T', ' ');
                        if (dueDate.split(':').length === 2) {
                            dueDate += ':00';
                        }
                    }

                    const updates = {
                        id: CASE_ID,
                        name: document.getElementById('input-name').value.trim(),
                        plate: document.getElementById('input-plate').value.trim(),
                        amount: document.getElementById('input-amount').value.trim(),
                        status: status,
                        phone: document.getElementById('input-phone').value.trim(),
                        serviceDate: serviceDate || null,
                        dueDate: dueDate || null,
                        franchise: document.getElementById('input-franchise').value || 0,
                        user_response: this.currentCase.user_response || null,
                        internalNotes: this.currentCase.internalNotes || [],
                        repair_status: this.currentCase.repair_status || null,
                        assigned_mechanic: this.currentCase.assigned_mechanic?.trim() || null,
                        repair_start_date: (() => {

                            let d = this.currentCase.repair_start_date;

                            if (d) {

                                d = d.replace('T', ' ');

                                if (d.split(':').length === 2) d += ':00';

                            }

                            return d || null;

                        })(),
                        repair_end_date: (() => {

                            let d = this.currentCase.repair_end_date;

                            if (d) {

                                d = d.replace('T', ' ');

                                if (d.split(':').length === 2) d += ':00';

                            }

                            return d || null;

                        })(),
                        repair_notes: this.currentCase.repair_notes?.trim() || null,
                        repair_parts: this.currentCase.repair_parts || [],
                        repair_labor: this.currentCase.repair_labor || [],
                        repair_activity_log: this.currentCase.repair_activity_log || [],
                    };

                    const systemLogs = [...(this.currentCase.systemLogs || [])];
                    if (status !== initialCaseData.status) {
                        systemLogs.push({ message: `Status: ${initialCaseData.status} -> ${status}`, timestamp: new Date().toISOString(), type: 'status' });

                        if (updates.phone && smsWorkflowBindings && smsWorkflowBindings[status]) {
                            const templateData = this.getTemplateData();
                            smsWorkflowBindings[status].forEach(template => {
                                const msg = getFormattedMessage(template.slug, templateData);
                                sendSmsAndUpdateLog(updates.phone, msg, `${template.slug}_sms`);
                            });
                        }
                    }

                    try {
                        await fetchAPI('update_transfer', 'POST', { ...updates, systemLogs });
                        Object.assign(this.currentCase, updates, { systemLogs });
                        initialCaseData = { ...this.currentCase };
                        showToast("Changes Saved", "Case updated successfully.", "success");
                        updateActivityLog(this.currentCase.systemLogs);
                    } catch (error) {
                        showToast("Error", "Failed to save changes.", "error");
                    }
                },
                toggleSection(section) {
                    if (this.openSections.includes(section)) {
                        this.openSections = this.openSections.filter(s => s !== section);
                    } else {
                        this.openSections.push(section);
                    }
                    localStorage.setItem('openSections', JSON.stringify(this.openSections));
                },
                addPart(name = '', quantity = 1, unit_price = 0) {
                    if (!this.currentCase.repair_parts) this.currentCase.repair_parts = [];
                    this.currentCase.repair_parts.push({ name, quantity, unit_price, ordered: false, sku: '', supplier: '', notes: '' });
                    this.updatePartsList();
                },
                bulkMarkOrdered() {
                    if (!this.currentCase.repair_parts) return;
                    this.currentCase.repair_parts.forEach(p => p.ordered = true);
                    this.updatePartsList();
                },
                updatePart(index, field, value) {
                    if (this.currentCase.repair_parts && this.currentCase.repair_parts[index]) {
                        this.currentCase.repair_parts[index][field] = field === 'quantity' || field === 'unit_price' ? parseFloat(value) || 0 : value;
                        this.updatePartsList();
                    }
                },
                removePart(index) {
                    if (!this.currentCase.repair_parts) return;
                    const removed = this.currentCase.repair_parts.splice(index, 1)[0];
                    // store for undo
                    this.lastRemovedPart = { item: removed, index };
                    if (this.lastRemovedTimer) clearTimeout(this.lastRemovedTimer);
                    this.lastRemovedTimer = setTimeout(() => { this.lastRemovedPart = null; }, 10000); // 10s to undo
                    this.updatePartsList();
                    showToast('Part Removed', `<button onclick="window.caseEditor.undoRemovePart()" class="bg-white px-2 py-1 rounded text-sm border">Undo</button>`, 'info', 10000);
                },
                undoRemovePart() {
                    if (!this.lastRemovedPart) return;
                    const { item, index } = this.lastRemovedPart;
                    this.currentCase.repair_parts.splice(index, 0, item);
                    this.lastRemovedPart = null;
                    if (this.lastRemovedTimer) { clearTimeout(this.lastRemovedTimer); this.lastRemovedTimer = null; }
                    this.updatePartsList();
                },
                updatePartsList() {
                    // Update parts total and ensure combined list refresh
                    const totalEl = document.getElementById('parts-total');
                    if (totalEl) {
                        const total = (this.currentCase.repair_parts || []).reduce((sum, part) => sum + ((part.quantity || 1) * (part.unit_price || 0)), 0);
                        totalEl.textContent = total.toFixed(2) + '₾';
                    }
                    // keep icons and summary updated
                    lucide.createIcons();
                    this.updateRepairSummary();
                    // Re-render combined items list
                    if (typeof this.updateItemsList === 'function') this.updateItemsList();
                }, 
                addLabor(description = '', hours = 0, hourly_rate = 0) {
                    if (!this.currentCase.repair_labor) this.currentCase.repair_labor = [];
                    this.currentCase.repair_labor.push({ description, hours, hourly_rate, billable: true, notes: '' });
                    this.updateLaborList();
                },
                updateLabor(index, field, value) {
                    if (this.currentCase.repair_labor && this.currentCase.repair_labor[index]) {
                        this.currentCase.repair_labor[index][field] = field === 'hours' || field === 'hourly_rate' ? parseFloat(value) || 0 : value;
                        this.updateLaborList();
                    }
                },
                removeLabor(index) {
                    if (this.currentCase.repair_labor) {
                        this.currentCase.repair_labor.splice(index, 1);
                        this.updateLaborList();
                    }
                },
                updateLaborList() {
                    const totalEl = document.getElementById('labor-total');
                    if (totalEl) {
                        const total = (this.currentCase.repair_labor || []).reduce((sum, labor) => sum + ((labor.hours || 0) * (labor.hourly_rate || 0)), 0);
                        totalEl.textContent = total.toFixed(2) + '₾';
                    }
                    lucide.createIcons();
                    // re-render combined list
                    if (typeof this.updateItemsList === 'function') this.updateItemsList();
                },
                incrementQty(index) {
                    if (!this.currentCase.repair_parts || !this.currentCase.repair_parts[index]) return;
                    this.currentCase.repair_parts[index].quantity = (parseFloat(this.currentCase.repair_parts[index].quantity) || 0) + 1;
                    this.updatePartsList();
                },
                decrementQty(index) {
                    if (!this.currentCase.repair_parts || !this.currentCase.repair_parts[index]) return;
                    this.currentCase.repair_parts[index].quantity = Math.max(1, (parseFloat(this.currentCase.repair_parts[index].quantity) || 1) - 1);
                    this.updatePartsList();
                },
                toggleOrdered(index) {
                    if (!this.currentCase.repair_parts || !this.currentCase.repair_parts[index]) return;
                    this.currentCase.repair_parts[index].ordered = !this.currentCase.repair_parts[index].ordered;
                    this.updatePartsList();
                },
                reorderUp(index) {
                    if (index <= 0) return;
                    const arr = this.currentCase.repair_parts;
                    [arr[index-1], arr[index]] = [arr[index], arr[index-1]];
                    this.updatePartsList();
                },
                reorderDown(index) {
                    const arr = this.currentCase.repair_parts;
                    if (index >= arr.length - 1) return;
                    [arr[index+1], arr[index]] = [arr[index], arr[index+1]];
                    this.updatePartsList();
                },
                reorderUpLab(index) {
                    if (index <= 0) return; const arr = this.currentCase.repair_labor; [arr[index-1], arr[index]] = [arr[index], arr[index-1]]; this.updateLaborList();
                },
                reorderDownLab(index) {
                    const arr = this.currentCase.repair_labor; if (index >= arr.length -1) return; [arr[index+1], arr[index]] = [arr[index], arr[index+1]]; this.updateLaborList();
                },
                applyMarkup() {
                    const pct = parseFloat(this.markupPercentage) || 0;
                    if (pct === 0) return showToast('No markup', 'Please enter a markup percentage greater than 0.', 'info');
                    this.currentCase.repair_parts = this.currentCase.repair_parts.map(p => ({ ...p, unit_price: parseFloat(((parseFloat(p.unit_price) || 0) * (1 + pct/100)).toFixed(2)) }));
                    this.updatePartsList();
                    showToast('Markup Applied', `Applied ${pct}% markup to all parts.`, 'success');
                },
                async savePartsCollection() {
                    if (!this.currentCase.repair_parts || this.currentCase.repair_parts.length === 0) return showToast('No items', 'Add parts before saving a collection.', 'error');
                    const items = this.currentCase.repair_parts.map(p => ({ name: p.name, quantity: p.quantity, price: p.unit_price, type: 'part' }));
                    try {
                        const response = await fetch(`${API_URL}?action=create_parts_collection`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ transfer_id: CASE_ID, parts_list: items, description: this.collectionNote, collection_type: 'local' })
                        });
                        const result = await response.json();
                        if (result.success) {
                            showToast('Collection Saved', 'Parts collection created successfully.', 'success');
                            if (typeof this.loadCollections === 'function') this.loadCollections();
                        } else {
                            showToast('Error', result.error || 'Failed to create collection.', 'error');
                        }
                    } catch (e) { showToast('Error', 'Failed to save collection.', 'error'); }
                },
                bulkMarkOrdered() {
                    if (!this.currentCase.repair_parts) return;
                    this.currentCase.repair_parts.forEach(p => p.ordered = true);
                    this.updatePartsList();
                },
                // Drag & Drop handlers for Parts
                dragStartPart(ev, index) {
                    this.dragPartIndex = index;
                    ev.dataTransfer.effectAllowed = 'move';
                },
                dropPart(ev, targetIndex) {
                    ev.preventDefault();
                    const src = this.dragPartIndex;
                    if (src === null || typeof src === 'undefined') return;
                    const arr = this.currentCase.repair_parts;
                    const item = arr.splice(src, 1)[0];
                    arr.splice(targetIndex, 0, item);
                    this.dragPartIndex = null;
                    // remove visual indicator
                    const tgtEl = document.querySelectorAll('.part-item')[targetIndex]; if (tgtEl) tgtEl.classList.remove('drag-over');
                    this.updatePartsList();
                },

                // Drag & Drop for Labor
                dragStartLab(ev, index) {
                    this.dragLabIndex = index;
                    ev.dataTransfer.effectAllowed = 'move';
                },
                dropLab(ev, targetIndex) {
                    ev.preventDefault();
                    const src = this.dragLabIndex;
                    if (src === null || typeof src === 'undefined') return;
                    const arr = this.currentCase.repair_labor;
                    const item = arr.splice(src, 1)[0];
                    arr.splice(targetIndex, 0, item);
                    this.dragLabIndex = null;
                    const tgtEl = document.querySelectorAll('.labor-item')[targetIndex]; if (tgtEl) tgtEl.classList.remove('drag-over');
                    this.updateLaborList();
                },

                // Per-item workflow transitions
                nextStatusPart(index) {
                    const order = ['Pending','Ordered','Received','Billed'];
                    const p = this.currentCase.repair_parts[index];
                    if (!p) return;
                    const i = order.indexOf(p.status || 'Pending');
                    p.status = order[Math.min(order.length-1, i+1)];
                    this.updatePartsList();
                },
                nextStatusLab(index) {
                    const order = ['Pending','In Progress','Completed','Billed'];
                    const l = this.currentCase.repair_labor[index];
                    if (!l) return;
                    const i = order.indexOf(l.status || 'Pending');
                    l.status = order[Math.min(order.length-1, i+1)];
                    this.updateLaborList();
                },
                // Export helpers
                exportPartsCSV() {
                    if (!this.currentCase.repair_parts || this.currentCase.repair_parts.length === 0) return showToast('No parts', 'Nothing to export.', 'info');
                    const rows = [['Name','SKU','Supplier','Qty','Unit Price','Total','Status','Notes']];
                    this.currentCase.repair_parts.forEach(p => rows.push([p.name||'', p.sku||'', p.supplier||'', p.quantity||'', p.unit_price||'', ((p.quantity||0)*(p.unit_price||0)).toFixed(2), p.status||'Pending', p.notes||'']));
                    const csv = rows.map(r => r.map(c=>`"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-parts.csv`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },
                exportPartsXLS() {
                    // Simple Excel-compatible HTML table
                    if (!this.currentCase.repair_parts || this.currentCase.repair_parts.length === 0) return showToast('No parts', 'Nothing to export.', 'info');
                    let html = '<table><tr><th>Name</th><th>SKU</th><th>Supplier</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th><th>Notes</th></tr>';
                    this.currentCase.repair_parts.forEach(p => html += `<tr><td>${escapeHtml(p.name||'')}</td><td>${escapeHtml(p.sku||'')}</td><td>${escapeHtml(p.supplier||'')}</td><td>${p.quantity||''}</td><td>${p.unit_price||''}</td><td>${((p.quantity||0)*(p.unit_price||0)).toFixed(2)}</td><td>${escapeHtml(p.status||'')}</td><td>${escapeHtml(p.notes||'')}</td></tr>`);
                    html += '</table>';
                    const blob = new Blob([`<html><body>${html}</body></html>`], { type: 'application/vnd.ms-excel' });
                    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-parts.xls`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },
                exportLaborCSV() {
                    if (!this.currentCase.repair_labor || this.currentCase.repair_labor.length === 0) return showToast('No labor', 'Nothing to export.', 'info');
                    const rows = [['Description','Hours','Rate','Total','Status','Notes']];
                    this.currentCase.repair_labor.forEach(l => rows.push([l.description||'', l.hours||'', l.hourly_rate||'', ((l.hours||0)*(l.hourly_rate||0)).toFixed(2), l.status||'Pending', l.notes||'']));
                    const csv = rows.map(r => r.map(c=>`"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-labor.csv`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },
                exportLaborXLS() {
                    if (!this.currentCase.repair_labor || this.currentCase.repair_labor.length === 0) return showToast('No labor', 'Nothing to export.', 'info');
                    let html = '<table><tr><th>Description</th><th>Hours</th><th>Rate</th><th>Total</th><th>Status</th><th>Notes</th></tr>';
                    this.currentCase.repair_labor.forEach(l => html += `<tr><td>${escapeHtml(l.description||'')}</td><td>${l.hours||''}</td><td>${l.hourly_rate||''}</td><td>${((l.hours||0)*(l.hourly_rate||0)).toFixed(2)}</td><td>${escapeHtml(l.status||'')}</td><td>${escapeHtml(l.notes||'')}</td></tr>`);
                    html += '</table>';
                    const blob = new Blob([`<html><body>${html}</body></html>`], { type: 'application/vnd.ms-excel' });
                    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-labor.xls`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },
                exportAllCSV() {
                    // Combine both into one CSV with section headers (includes receipt/completion fields)
                    const rows = [['Parts']]; rows.push(['Name','SKU','Supplier','Qty','Unit Price','Total','Status','Notes','Received By','Received At']);
                    this.currentCase.repair_parts.forEach(p => rows.push([p.name||'', p.sku||'', p.supplier||'', p.quantity||'', p.unit_price||'', ((p.quantity||0)*(p.unit_price||0)).toFixed(2), p.status||'Pending', p.notes||'', p.received_by||'', p.received_at||'']));
                    rows.push([]); rows.push(['Labor']); rows.push(['Description','Hours','Rate','Total','Status','Notes','Completed By','Completed At']);
                    this.currentCase.repair_labor.forEach(l => rows.push([l.description||'', l.hours||'', l.hourly_rate||'', ((l.hours||0)*(l.hourly_rate||0)).toFixed(2), l.status||'Pending', l.notes||'', l.completed_by||'', l.completed_at||'']));
                    const csv = rows.map(r => r.map(c=>`"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-parts-labor.csv`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },
                exportAllXLS() {
                    // Legacy HTML-based XLS export (kept for compatibility)
                    let html = '<h2>Parts</h2><table><tr><th>Name</th><th>SKU</th><th>Supplier</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Status</th><th>Notes</th><th>Received By</th><th>Received At</th></tr>';
                    this.currentCase.repair_parts.forEach(p => html += `<tr><td>${escapeHtml(p.name||'')}</td><td>${escapeHtml(p.sku||'')}</td><td>${escapeHtml(p.supplier||'')}</td><td>${p.quantity||''}</td><td>${p.unit_price||''}</td><td>${((p.quantity||0)*(p.unit_price||0)).toFixed(2)}</td><td>${escapeHtml(p.status||'')}</td><td>${escapeHtml(p.notes||'')}</td><td>${escapeHtml(p.received_by||'')}</td><td>${escapeHtml(p.received_at||'')}</td></tr>`);
                    html += '</table><h2>Labor</h2><table><tr><th>Description</th><th>Hours</th><th>Rate</th><th>Total</th><th>Status</th><th>Notes</th><th>Completed By</th><th>Completed At</th></tr>';
                    this.currentCase.repair_labor.forEach(l => html += `<tr><td>${escapeHtml(l.description||'')}</td><td>${l.hours||''}</td><td>${l.hourly_rate||''}</td><td>${((l.hours||0)*(l.hourly_rate||0)).toFixed(2)}</td><td>${escapeHtml(l.status||'')}</td><td>${escapeHtml(l.notes||'')}</td><td>${escapeHtml(l.completed_by||'')}</td><td>${escapeHtml(l.completed_at||'')}</td></tr>`);
                    html += '</table>';
                    const blob = new Blob([`<html><body>${html}</body></html>`], { type: 'application/vnd.ms-excel' });
                    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-parts-labor.xls`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },
                // Proper XLSX export using SheetJS (if available)
                exportPartsXLSX() {
                    if (typeof XLSX === 'undefined') return this.exportPartsXLS();
                    const ws = XLSX.utils.json_to_sheet(this.currentCase.repair_parts.map(p=>({ Name: p.name||'', SKU: p.sku||'', Supplier: p.supplier||'', Qty: p.quantity||0, UnitPrice: p.unit_price||0, Total: ((p.quantity||0)*(p.unit_price||0)).toFixed(2), Status: p.status||'Pending', Notes: p.notes||'', ReceivedBy: p.received_by||'', ReceivedAt: p.received_at||'' })));
                    const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Parts');
                    const wbout = XLSX.write(wb, {bookType:'xlsx', type:'array'});
                    const blob = new Blob([wbout], {type: 'application/octet-stream'});
                    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-parts.xlsx`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },
                exportLaborXLSX() {
                    if (typeof XLSX === 'undefined') return this.exportLaborXLS();
                    const ws = XLSX.utils.json_to_sheet(this.currentCase.repair_labor.map(l=>({ Description: l.description||'', Hours: l.hours||0, Rate: l.hourly_rate||0, Total: ((l.hours||0)*(l.hourly_rate||0)).toFixed(2), Status: l.status||'Pending', Notes: l.notes||'', CompletedBy: l.completed_by||'', CompletedAt: l.completed_at||'' })));
                    const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Labor');
                    const wbout = XLSX.write(wb, {bookType:'xlsx', type:'array'});
                    const blob = new Blob([wbout], {type: 'application/octet-stream'});
                    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-labor.xlsx`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },
                exportAllXLSX() {
                    if (typeof XLSX === 'undefined') return this.exportAllXLS();
                    // create combined workbook
                    const wb = XLSX.utils.book_new();
                    const wsParts = XLSX.utils.json_to_sheet(this.currentCase.repair_parts.map(p=>({ Name: p.name||'', SKU: p.sku||'', Supplier: p.supplier||'', Qty: p.quantity||0, UnitPrice: p.unit_price||0, Total: ((p.quantity||0)*(p.unit_price||0)).toFixed(2), Status: p.status||'Pending', Notes: p.notes||'', ReceivedBy: p.received_by||'', ReceivedAt: p.received_at||'' })));
                    const wsLab = XLSX.utils.json_to_sheet(this.currentCase.repair_labor.map(l=>({ Description: l.description||'', Hours: l.hours||0, Rate: l.hourly_rate||0, Total: ((l.hours||0)*(l.hourly_rate||0)).toFixed(2), Status: l.status||'Pending', Notes: l.notes||'', CompletedBy: l.completed_by||'', CompletedAt: l.completed_at||'' })));
                    XLSX.utils.book_append_sheet(wb, wsParts, 'Parts'); XLSX.utils.book_append_sheet(wb, wsLab, 'Labor');
                    const wbout = XLSX.write(wb, {bookType:'xlsx', type:'array'}); const blob = new Blob([wbout], {type: 'application/octet-stream'}); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-parts-labor.xlsx`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },
                // CSV parsing helper (handles quoted fields)
                parseCSV(text) {
                    const rows = [];
                    const lines = text.split(/\r?\n/);
                    for (let i=0;i<lines.length;i++) {
                        const line = lines[i].trim(); if (!line) continue;
                        const fields = [];
                        let cur = '', inQuotes = false;
                        for (let j=0;j<line.length;j++) {
                            const ch = line[j];
                            if (ch === '"') { if (inQuotes && line[j+1] === '"') { cur += '"'; j++; } else { inQuotes = !inQuotes; } }
                            else if (ch === ',' && !inQuotes) { fields.push(cur); cur = ''; }
                            else { cur += ch; }
                        }
                        fields.push(cur);
                        rows.push(fields);
                    }
                    return rows;
                },
                importPartsCSVFromText(text) {
                    const rows = this.parseCSV(text);
                    const header = rows.shift().map(h => h.toLowerCase());
                    rows.forEach(r => {
                        const obj = {};
                        header.forEach((h,i) => { obj[h] = r[i] ?? ''; });
                        const name = obj['name'] || obj['part'] || '';
                        if (!name) return;
                        const qty = parseFloat(obj['qty'] || obj['quantity'] || '1') || 1;
                        const price = parseFloat(obj['unit price'] || obj['price'] || '0') || 0;
                        const status = obj['status'] || 'Pending';
                        this.currentCase.repair_parts.push({ name, sku: obj['sku']||'', supplier: obj['supplier']||'', quantity: qty, unit_price: price, status: status, notes: obj['notes']||'', received_by: obj['received by']||'', received_at: obj['received at']||'' });
                    });
                    this.updatePartsList();
                    showToast('CSV Imported', 'Parts imported from CSV.', 'success');
                },
                importLaborCSVFromText(text) {
                    const rows = this.parseCSV(text);
                    const header = rows.shift().map(h => h.toLowerCase());
                    rows.forEach(r => {
                        const obj = {};
                        header.forEach((h,i) => { obj[h] = r[i] ?? ''; });
                        const desc = obj['description'] || obj['service'] || '';
                        if (!desc) return;
                        const hours = parseFloat(obj['hours'] || '0') || 0;
                        const rate = parseFloat(obj['rate'] || obj['hourly rate'] || '0') || 0;
                        const status = obj['status'] || 'Pending';
                        this.currentCase.repair_labor.push({ description: desc, hours, hourly_rate: rate, status, notes: obj['notes']||'', completed_by: obj['completed by']||'', completed_at: obj['completed at']||'' });
                    });
                    this.updateLaborList();
                    showToast('CSV Imported', 'Labor imported from CSV.', 'success');
                },
                // Drag enter / leave visuals
                dragEnterPart(ev, idx) { const el = document.querySelectorAll('.part-item')[idx]; if(el) el.classList.add('drag-over'); },
                dragLeavePart(ev, idx) { const el = document.querySelectorAll('.part-item')[idx]; if(el) el.classList.remove('drag-over'); },
                dragEnterLab(ev, idx) { const el = document.querySelectorAll('.labor-item')[idx]; if(el) el.classList.add('drag-over'); },
                dragLeaveLab(ev, idx) { const el = document.querySelectorAll('.labor-item')[idx]; if(el) el.classList.remove('drag-over'); },
                // Receipt / completion confirmations
                confirmReceipt(index) { const name = prompt('Received by (name):'); if (!name) return; const p = this.currentCase.repair_parts[index]; if (!p) return; p.received_by = name; p.received_at = new Date().toISOString(); p.status = 'Received'; this.updatePartsList(); showToast('Receipt Confirmed', `Received by ${name}`, 'success'); },
                editReceipt(index) { const p = this.currentCase.repair_parts[index]; if (!p) return; const name = prompt('Received by (name):', p.received_by || ''); if (name === null) return; p.received_by = name; this.updatePartsList(); showToast('Receipt Updated', '', 'success'); },
                confirmComplete(index) { const name = prompt('Completed by (name):'); if (!name) return; const l = this.currentCase.repair_labor[index]; if (!l) return; l.completed_by = name; l.completed_at = new Date().toISOString(); l.status = 'Completed'; this.updateLaborList(); showToast('Labor Marked Completed', `Completed by ${name}`, 'success'); },
                editComplete(index) { const l = this.currentCase.repair_labor[index]; if (!l) return; const name = prompt('Completed by (name):', l.completed_by || ''); if (name === null) return; l.completed_by = name; this.updateLaborList(); showToast('Completion Updated', '', 'success'); },
                updateRepairSummary() {
                    const partsTotal = (this.currentCase.repair_parts || []).reduce((sum, part) => sum + ((part.quantity || 1) * (part.unit_price || 0)), 0);
                    const laborTotal = (this.currentCase.repair_labor || []).reduce((sum, labor) => sum + ((labor.hours || 0) * (labor.hourly_rate || 0)), 0);
                    const grandTotal = partsTotal + laborTotal;
                    
                    document.getElementById('summary-parts-total').textContent = partsTotal.toFixed(2) + '₾';
                    document.getElementById('summary-labor-total').textContent = laborTotal.toFixed(2) + '₾';
                    document.getElementById('summary-grand-total').textContent = grandTotal.toFixed(2) + '₾';
                },

                // Render combined items (parts + labor) into a single view
                updateItemsList() {
                    const container = document.getElementById('itemsList');
                    if (!container) return;
                    const parts = this.currentCase.repair_parts || [];
                    const labor = this.currentCase.repair_labor || [];
                    let html = '';

                    if (parts.length > 0) {
                        html += `<div class="text-sm font-semibold text-slate-700">Parts (${parts.length})</div>`;
                        parts.forEach((part, index) => {
                            html += `
                                <div class="part-item bg-white/40 rounded-lg p-3 border border-white/30">
                                    <div class="grid grid-cols-12 gap-x-3 items-start">
                                        <div class="col-span-8">
                                            <div class="flex items-center gap-2 mb-1">
                                                <div class="drag-handle" draggable="true" ondragstart="window.caseEditor.dragStartPart(event, ${index})" title="Drag to reorder"><i data-lucide="move" class="w-4 h-4"></i></div>
                                                <div class="text-xs font-semibold px-2 py-0.5 rounded-md bg-slate-100">Part</div>
                                                <input type="text" class="part-name block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm" value="${escapeHtml(part.name||'')}" onchange="updatePart(${index}, 'name', this.value)">
                                            </div>
                                            <div class="flex items-center gap-3 text-xs text-slate-500">
                                                <div>SKU: <input class="inline-block ml-1 px-1 py-0.5 rounded border border-gray-200 text-xs" value="${escapeHtml(part.sku||'')}" onchange="updatePart(${index}, 'sku', this.value)"></div>
                                                <div>Supplier: <input class="inline-block ml-1 px-1 py-0.5 rounded border border-gray-200 text-xs" value="${escapeHtml(part.supplier||'')}" onchange="updatePart(${index}, 'supplier', this.value)"></div>
                                            </div>
                                            <textarea class="mt-2 w-full px-2 py-1 text-sm border rounded" placeholder="Optional note..." onchange="updatePart(${index}, 'notes', this.value)">${escapeHtml(part.notes||'')}</textarea>
                                        </div>
                                        <div class="col-span-2 text-center">
                                            <label class="text-xs">Qty</label>
                                            <input type="number" class="block w-20 mt-1 text-center rounded border" value="${part.quantity||1}" min="1" onchange="updatePart(${index}, 'quantity', this.value)">
                                        </div>
                                        <div class="col-span-1 text-center">
                                            <label class="text-xs">Unit</label>
                                            <input type="number" class="block w-24 mt-1 text-center rounded border" value="${part.unit_price||0}" step="0.01" onchange="updatePart(${index}, 'unit_price', this.value)">
                                            <div class="text-xs mt-2">${(((part.quantity||1)*(part.unit_price||0))).toFixed(2)}₾</div>
                                        </div>
                                        <div class="col-span-1 flex flex-col items-end gap-2">
                                            <select onchange="updatePart(${index}, 'status', this.value)" class="text-sm px-2 py-1 rounded border">
                                                <option value="Pending" ${part.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                                <option value="Ordered" ${part.status === 'Ordered' ? 'selected' : ''}>Ordered</option>
                                                <option value="Received" ${part.status === 'Received' ? 'selected' : ''}>Received</option>
                                                <option value="Billed" ${part.status === 'Billed' ? 'selected' : ''}>Billed</option>
                                            </select>
                                            <button class="px-2 py-1 border rounded text-xs" onclick="window.caseEditor.nextStatusPart(${index})">Next</button>
                                            <button class="px-2 py-1 border-2 border-red-300 rounded-lg text-red-600 hover:bg-red-50 w-full flex justify-center" onclick="if(confirm('Remove this part?')) window.caseEditor.removePart(${index})"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                        </div>
                                    </div>
                                </div>`;
                        });
                    }

                    if (labor.length > 0) {
                        html += `<div class="text-sm font-semibold text-slate-700 mt-3">Labor (${labor.length})</div>`;
                        labor.forEach((l, idx) => {
                            html += `
                                <div class="labor-item bg-white/40 rounded-lg p-3 border border-white/30">
                                    <div class="grid grid-cols-12 gap-x-3 items-end">
                                        <div class="col-span-8">
                                            <div class="flex items-center gap-2 mb-1">
                                                <div class="drag-handle" draggable="true" ondragstart="window.caseEditor.dragStartLab(event, ${idx})" title="Drag to reorder"><i data-lucide="move" class="w-4 h-4"></i></div>
                                                <div class="text-xs font-semibold px-2 py-0.5 rounded-md bg-slate-100">Labor</div>
                                                <input type="text" class="labor-name block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm" value="${escapeHtml(l.description||'')}" onchange="updateLabor(${idx}, 'description', this.value)">
                                            </div>
                                            <textarea class="mt-2 w-full px-2 py-1 text-sm border rounded" placeholder="Notes" onchange="updateLabor(${idx}, 'notes', this.value)">${escapeHtml(l.notes||'')}</textarea>
                                        </div>
                                        <div class="col-span-2 text-center">
                                            <label class="text-xs">Hours</label>
                                            <input type="number" class="block w-20 mt-1 text-center rounded border" value="${l.hours||0}" step="0.5" onchange="updateLabor(${idx}, 'hours', this.value)">
                                        </div>
                                        <div class="col-span-1 text-center">
                                            <label class="text-xs">Rate</label>
                                            <input type="number" class="block w-24 mt-1 text-center rounded border" value="${l.hourly_rate||0}" step="0.01" onchange="updateLabor(${idx}, 'hourly_rate', this.value)">
                                            <div class="text-xs mt-2">${(((l.hours||0)*(l.hourly_rate||0))).toFixed(2)}₾</div>
                                        </div>
                                        <div class="col-span-1 flex flex-col items-end gap-2">
                                            <select onchange="updateLabor(${idx}, 'status', this.value)" class="text-sm px-2 py-1 rounded border">
                                                <option value="Pending" ${l.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                                <option value="In Progress" ${l.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                                                <option value="Completed" ${l.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                                <option value="Billed" ${l.status === 'Billed' ? 'selected' : ''}>Billed</option>
                                            </select>
                                            <button class="px-2 py-1 border rounded text-xs" onclick="window.caseEditor.nextStatusLab(${idx})">Next</button>
                                            <button class="px-2 py-1 border-2 border-red-300 rounded-lg text-red-600 hover:bg-red-50 w-full flex justify-center" onclick="if(confirm('Remove this labor entry?')) window.caseEditor.removeLabor(${idx})"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                        </div>
                                    </div>
                                </div>`;
                        });
                    }

                    container.innerHTML = html || '<div class="text-sm text-slate-500">No parts or labor added yet.</div>';

                    // Setup autocomplete for new inputs
                    container.querySelectorAll('.part-name').forEach(inp => setupAutocomplete(inp, 'part'));
                    container.querySelectorAll('.labor-name').forEach(inp => setupAutocomplete(inp, 'labor'));

                    // Recalculate totals and icons
                    this.updateRepairSummary();
                    lucide.createIcons();
                },

                showInvoice() {
                    const parts = this.currentCase.repair_parts || [];
                    const labor = this.currentCase.repair_labor || [];
                    const partsTotal = parts.reduce((s,p)=>s+((p.quantity||1)*(p.unit_price||0)),0);
                    const laborTotal = labor.reduce((s,l)=>s+((l.hours||0)*(l.hourly_rate||0)),0);
                    const grand = partsTotal + laborTotal;

                    const caseInfo = this.currentCase || {};
                    let rows = '';
                    parts.forEach(p => rows += `<tr><td>${escapeHtml(p.name||'')}</td><td>${p.quantity||1}</td><td>${(p.unit_price||0).toFixed(2)}₾</td><td>${(((p.quantity||1)*(p.unit_price||0))).toFixed(2)}₾</td></tr>`);
                    labor.forEach(l => rows += `<tr><td>${escapeHtml(l.description||'')}</td><td>${l.hours||0}</td><td>${(l.hourly_rate||0).toFixed(2)}₾</td><td>${(((l.hours||0)*(l.hourly_rate||0))).toFixed(2)}₾</td></tr>`);

                    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Invoice - Case ${CASE_ID}</title><style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;color:#111}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f7f7f7}</style></head><body>
                        <h2>Invoice - Case #${CASE_ID}</h2>
                        <div><strong>Customer:</strong> ${escapeHtml(caseInfo.name||'')} &nbsp; <strong>Plate:</strong> ${escapeHtml(caseInfo.plate||'')}</div>
                        <table class="mt-4"><thead><tr><th>Description</th><th>Qty/Hours</th><th>Unit</th><th>Total</th></tr></thead><tbody>${rows}</tbody>
                        <tfoot><tr><th colspan="3">Parts Total</th><th>${partsTotal.toFixed(2)}₾</th></tr><tr><th colspan="3">Labor Total</th><th>${laborTotal.toFixed(2)}₾</th></tr><tr><th colspan="3">Grand Total</th><th>${grand.toFixed(2)}₾</th></tr></tfoot></table>
                        <div style="margin-top:20px"><button onclick="window.print()">Print</button></div>
                    </body></html>`;

                    const win = window.open('', '_blank');
                    win.document.write(html); win.document.close(); win.focus();
                },

                // Collections: load, render, import
                async loadCollections() {
                    try {
                        const resp = await fetch(`api.php?action=get_parts_collections&transfer_id=${CASE_ID}`);
                        const data = await resp.json();
                        if (data.success) {
                            this.collections = data.collections || [];
                        } else {
                            this.collections = [];
                        }
                    } catch (e) {
                        this.collections = [];
                    }

                    const container = document.getElementById('collectionsList');
                    if (!container) return;
                    if (!this.collections || this.collections.length === 0) {
                        container.innerHTML = '<div class="text-sm text-slate-500">No collections found for this case.</div>';
                        return;
                    }

                    let html = '';
                    this.collections.forEach((c, ci) => {
                        let parts = [];
                        try { parts = JSON.parse(c.parts_list || '[]'); } catch (e) { parts = []; }
                        html += `
                            <div class="bg-white p-3 rounded-lg border">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="text-sm font-semibold">Collection #${c.id} <span class="text-xs text-slate-500">${escapeHtml(c.collection_type || '')} ${c.status ? '· ' + escapeHtml(c.status) : ''}</span></div>
                                        <div class="text-xs text-slate-500 mt-1">${escapeHtml(c.description || '')}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-semibold">${(c.total_cost||0).toFixed(2)}₾</div>
                                        <div class="text-xs text-slate-400">${escapeHtml(c.created_at || '')}</div>
                                        <div class="mt-2"><button class="bg-blue-600 text-white px-2 py-1 rounded text-xs" onclick="window.caseEditor.addCollectionItems(${c.id})">Add all</button></div>
                                    </div>
                                </div>
                                <div class="mt-2 overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead><tr><th class="text-left">Name</th><th>Qty</th><th>Price</th><th></th></tr></thead>
                                        <tbody>`;
                        (parts || []).forEach((p, pi) => {
                            html += `<tr><td>${escapeHtml(p.name||'')}</td><td>${p.quantity||1}</td><td>${(p.price||p.unit_price||0).toFixed(2)}₾</td><td><button class="text-xs px-2 py-1 border rounded" onclick="window.caseEditor.addCollectionItem(${c.id}, ${pi})">Add</button></td></tr>`;
                        });
                        html += `</tbody></table></div>
                            </div><div class="h-2"></div>`;
                    });

                    container.innerHTML = html;
                },

                addCollectionItems(collectionId) {
                    const c = (this.collections || []).find(x => Number(x.id) === Number(collectionId));
                    if (!c) return showToast('Not found', 'Collection not found', 'error');
                    let parts = [];
                    try { parts = JSON.parse(c.parts_list || '[]'); } catch (e) { parts = []; }
                    let added = 0;
                    parts.forEach(p => {
                        const name = p.name || p.description || '';
                        const qty = parseFloat(p.quantity) || 1;
                        const price = parseFloat(p.price || p.unit_price) || 0;
                        this.addPart(name, qty, price);
                        added++;
                    });
                    if (added) { this.updatePartsList(); showToast('Added', `${added} parts added from collection.`, 'success'); }
                },

                addCollectionItem(collectionId, index) {
                    const c = (this.collections || []).find(x => Number(x.id) === Number(collectionId));
                    if (!c) return showToast('Not found', 'Collection not found', 'error');
                    let parts = [];
                    try { parts = JSON.parse(c.parts_list || '[]'); } catch (e) { parts = []; }
                    const p = parts[index];
                    if (!p) return;
                    const name = p.name || p.description || '';
                    const qty = parseFloat(p.quantity) || 1;
                    const price = parseFloat(p.price || p.unit_price) || 0;
                    this.addPart(name, qty, price);
                    this.updatePartsList();
                    showToast('Added', `${name} added.`, 'success');
                },


                addActivity() {
                    const action = prompt('Enter activity action:');
                    if (!action) return;
                    const details = prompt('Enter activity details:');
                    if (!this.currentCase.repair_activity_log) this.currentCase.repair_activity_log = [];
                    this.currentCase.repair_activity_log.push({
                        action: action,
                        details: details || '',
                        user: '<?php echo addslashes($current_user_name); ?>',
                        timestamp: new Date().toISOString()
                    });
                    this.updateActivityLog();
                },
                updateActivityLog() {
                    const container = document.getElementById('activity-log');
                    if (!container) return;
                    container.innerHTML = this.currentCase.repair_activity_log.slice().reverse().map(activity => `
                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm text-slate-800 font-medium">${escapeHtml(activity.action || '')}</p>
                                    <p class="text-xs text-slate-600 mt-1">${escapeHtml(activity.details || '')}</p>
                                </div>
                                <div class="text-right text-xs text-slate-500">
                                    <div>${escapeHtml(activity.user || '')}</div>
                                    <div>${activity.timestamp ? new Date(activity.timestamp).toLocaleString() : ''}</div>
                                </div>
                            </div>
                        </div>
                    `).join('');
                },
                initRepairPdfParsing() {
                    const pdfInput = document.getElementById('repairPdfInput');
                    const parseBtn = document.getElementById('parseRepairPdfBtn');
                    const statusDiv = document.getElementById('repairPdfStatus');
                    const previewDiv = document.getElementById('repairParsedPreview');

                    if (!pdfInput || !parseBtn) return;

                    pdfInput.addEventListener('change', () => {
                        parseBtn.disabled = !pdfInput.files.length;
                        statusDiv.textContent = '';
                        previewDiv.innerHTML = '';
                    });

                    parseBtn.addEventListener('click', async () => {
                        if (!pdfInput.files.length) return;

                        statusDiv.textContent = 'Parsing PDF, please wait...';
                        parseBtn.disabled = true;
                        const formData = new FormData();
                        formData.append('pdf', pdfInput.files[0]);

                        try {
                            const response = await fetch('api.php?action=parse_invoice_pdf', { method: 'POST', body: formData });
                            const data = await response.json();

                            if (data.success && Array.isArray(data.items) && data.items.length > 0) {
                                statusDiv.textContent = `Successfully parsed ${data.items.length} items. Select which items to add.`;
                                
                                let checklistHtml = '';
                                data.items.forEach((item, index) => {
                                    const itemData = JSON.stringify(item);
                                    checklistHtml += `
                                        <div class="flex items-center p-1 rounded-md hover:bg-teal-100">
                                            <input id="repair-item-${index}" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 repair-parsed-item-checkbox" data-item='${itemData}' checked>
                                            <label for="repair-item-${index}" class="ml-3 text-sm text-gray-700">
                                                <span class="font-medium text-indigo-700">[${item.type}]</span> ${item.name} 
                                                <span class="text-gray-500">(Qty: ${item.quantity}, Price: ₾${item.price})</span>
                                            </label>
                                        </div>`;
                                });

                                // Build a review table where user can edit parsed rows before saving
                        let rowsHtml = '';
                        data.items.forEach((item, index) => {
                            const safe = (v) => escapeHtml(String(v || ''));
                            const type = item.type === 'labor' ? 'labor' : 'part';
                            rowsHtml += `
                                <tr data-idx="${index}">
                                    <td class="px-2"><input class="repair-parsed-checkbox" type="checkbox" checked></td>
                                    <td class="px-2"><select class="parsed-type text-sm" onchange="(function(r){ r.querySelector('.parsed-total').textContent = ((parseFloat(r.querySelector('.parsed-qty').value||0) * parseFloat(r.querySelector('.parsed-price').value||0)).toFixed(2)+'₾');})(this.closest('tr'))"><option value="part" ${type==='part' ? 'selected' : ''}>Part</option><option value="labor" ${type==='labor' ? 'selected' : ''}>Labor</option></select></td>
                                    <td class="px-3"><input class="parsed-name block w-full text-sm px-2 py-1 border rounded" value="${safe(item.name)}"></td>
                                    <td class="px-2"><input type="number" min="0" step="1" class="parsed-qty text-sm w-20 px-2 py-1 border rounded" value="${escapeHtml(item.quantity || 1)}"></td>
                                    <td class="px-2"><input type="number" min="0" step="0.01" class="parsed-price text-sm w-28 px-2 py-1 border rounded" value="${escapeHtml(item.price || 0)}"></td>
                                    <td class="px-3 parsed-total text-sm">${((item.quantity||1)*(item.price||0)).toFixed(2)}₾</td>
                                    <td class="px-3"><input class="parsed-notes block w-full text-sm px-2 py-1 border rounded" value="${safe(item.notes || '')}"></td>
                                    <td class="px-2"><button type="button" class="text-xs text-slate-600 remove-parse-row">Remove</button></td>
                                </tr>`;
                        });

                        previewDiv.innerHTML = `
                            <div class="bg-teal-50 border border-teal-200 rounded-lg p-3">
                                <h4 class="font-bold mb-2 text-gray-800">Parsed Items Preview</h4>
                                <div class="flex items-center border-b pb-2 mb-2">
                                    <input id="selectAllRepairParsed" type="checkbox" class="h-4 w-4 rounded border-gray-300" checked>
                                    <label for="selectAllRepairParsed" class="ml-3 text-sm font-medium text-gray-800">Select All</label>
                                    <div class="ml-auto text-sm text-slate-500">Edit fields and click Save Selected Items</div>
                                </div>
                                <div class="max-h-60 overflow-y-auto">
                                    <table class="w-full text-sm table-auto">
                                        <thead class="bg-white sticky top-0">
                                            <tr><th></th><th>Type</th><th>Name</th><th>Qty</th><th>Price</th><th>Total</th><th>Notes</th><th></th></tr>
                                        </thead>
                                        <tbody id="parsedItemsTable">${rowsHtml}</tbody>
                                    </table>
                                </div>
                                <div class="mt-3 flex items-center gap-2">
                                    <button type="button" id="saveParsedSelectedBtn" class="btn-gradient text-white px-3 py-1 rounded-md text-sm">Save Selected Items</button>
                                    <button type="button" id="createCollectionFromParsedBtn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-1 px-3 rounded text-sm">Create Parts Collection from Selected</button>
                                </div>
                            </div>
                        `;

                        // Select All behavior
                        document.getElementById('selectAllRepairParsed').addEventListener('change', (e) => {
                            document.querySelectorAll('.repair-parsed-checkbox').forEach(cb => cb.checked = e.target.checked);
                        });

                        // Row remove
                        document.querySelectorAll('.remove-parse-row').forEach(btn => btn.addEventListener('click', (ev) => {
                            const tr = ev.target.closest('tr'); if (!tr) return; tr.remove();
                        }));

                        // Recalculate totals when qty/price change
                        document.querySelectorAll('.parsed-qty, .parsed-price').forEach(inp => inp.addEventListener('input', (e) => {
                            const tr = e.target.closest('tr'); if (!tr) return; const qty = parseFloat(tr.querySelector('.parsed-qty').value||0); const price = parseFloat(tr.querySelector('.parsed-price').value||0); tr.querySelector('.parsed-total').textContent = (qty*price).toFixed(2) + '₾';
                        }));

                        // Save selected items into current case
                        document.getElementById('saveParsedSelectedBtn').onclick = () => {
                            const selected = [];
                            document.querySelectorAll('#parsedItemsTable tr').forEach(tr => {
                                const cb = tr.querySelector('.repair-parsed-checkbox'); if (!cb || !cb.checked) return;
                                const type = tr.querySelector('.parsed-type').value;
                                const name = tr.querySelector('.parsed-name').value.trim();
                                const qty = parseFloat(tr.querySelector('.parsed-qty').value) || 1;
                                const price = parseFloat(tr.querySelector('.parsed-price').value) || 0;
                                const notes = tr.querySelector('.parsed-notes').value || '';
                                selected.push({ type, name, qty, price, notes });
                            });

                            if (selected.length === 0) { showToast('No items selected', '', 'info'); return; }

                            selected.forEach(it => {
                                if (it.type === 'labor') {
                                    this.addLabor(it.name, it.qty, it.price);
                                    // attach notes
                                    const last = this.currentCase.repair_labor.length - 1; if (last >= 0) this.currentCase.repair_labor[last].notes = it.notes;
                                } else {
                                    this.addPart(it.name, it.qty, it.price);
                                    const last = this.currentCase.repair_parts.length -1; if (last >= 0) { this.currentCase.repair_parts[last].notes = it.notes; }
                                }
                            });

                            previewDiv.innerHTML = '';
                            statusDiv.textContent = `${selected.length} items have been added to the lists below.`;
                            this.updatePartsList(); this.updateLaborList();
                        };

                        // Create parts collection from selected rows (only parts)
                        document.getElementById('createCollectionFromParsedBtn').onclick = async () => {
                            const parts = [];
                            document.querySelectorAll('#parsedItemsTable tr').forEach(tr => {
                                const cb = tr.querySelector('.repair-parsed-checkbox'); if (!cb || !cb.checked) return;
                                const type = tr.querySelector('.parsed-type').value;
                                if (type !== 'part') return;
                                const name = tr.querySelector('.parsed-name').value.trim();
                                const qty = parseFloat(tr.querySelector('.parsed-qty').value) || 1;
                                const price = parseFloat(tr.querySelector('.parsed-price').value) || 0;
                                parts.push({ name, quantity: qty, price });
                            });

                            if (parts.length === 0) { showToast('No parts selected', '', 'info'); return; }

                            try {
                                const resp = await fetch('api.php?action=create_parts_collection', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ transfer_id: CASE_ID, parts_list: parts, description: this.collectionNote }) });
                                const result = await resp.json();
                                if (result.success) {
                                    showToast('Parts Collection Created', 'Collection created successfully.', 'success');
                                    previewDiv.innerHTML = '';
                                    if (typeof window.caseEditor?.loadCollections === 'function') window.caseEditor.loadCollections();
                                } else {
                                    showToast('Error', result.error || 'Failed to create collection.', 'error');
                                }
                            } catch (e) { showToast('Error', 'Failed to create collection.', 'error'); }
                        };

                            } else {
                                statusDiv.textContent = data.error || 'Could not parse any items from the PDF.';
                            }
                        } catch (error) {
                            console.error('PDF parsing error:', error);
                            statusDiv.textContent = 'An error occurred while parsing the PDF.';
                        } finally {
                            parseBtn.disabled = false;
                        }
                    });

                    // CSV import handlers (parts)
                    const partsCsvInput = document.getElementById('partsCsvInput');
                    const importPartsBtn = document.getElementById('importPartsCsvBtn');
                    if (importPartsBtn && partsCsvInput) {
                        importPartsBtn.addEventListener('click', async () => {
                            if (!partsCsvInput.files.length) return showToast('No file', 'Select a CSV to import.', 'error');
                            const text = await partsCsvInput.files[0].text();
                            this.importPartsCSVFromText(text);
                        });
                    }

                    // CSV import handlers (labor) - labor inputs exist in DOM
                    const laborCsvInput = document.getElementById('laborCsvInput');
                    const importLaborBtn = document.getElementById('importLaborCsvBtn');
                    if (importLaborBtn && laborCsvInput) {
                        importLaborBtn.addEventListener('click', async () => {
                            if (!laborCsvInput.files.length) return showToast('No file', 'Select a CSV to import.', 'error');
                            const text = await laborCsvInput.files[0].text();
                            this.importLaborCSVFromText(text);
                        });
                    }
                },
                async requestParts() {
                    if (!this.partsRequest.description.trim()) {
                        return showToast("Error", "Please provide a description.", "error");
                    }

                    try {
                        const partsList = [{
                            name: "Parts Request",
                            quantity: 1,
                            price: 0
                        }];
                        
                        const response = await fetch(`${API_URL}?action=create_parts_collection`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                transfer_id: CASE_ID,
                                parts_list: partsList,
                                assigned_manager_id: null,
                                description: this.partsRequest.description,
                                supplier: this.partsRequest.supplier || null,
                                collection_type: this.partsRequest.collection_type || 'local'
                            })
                        });
                        
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const result = await response.json();
                        
                        showToast("Parts Request Created", "Parts collection request has been created.", "success");
                        this.partsRequest = { description: '', supplier: '', collection_type: 'local' };
                    } catch (error) {
                        showToast("Error", "Failed to create parts request.", "error");
                    }
                }
            }
        }
        
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
            toast.className = `pointer-events-auto w-96 bg-white border ${style.border} shadow-lg rounded-xl p-4 flex items-start gap-4 transform transition-all duration-300 translate-x-full`;
            toast.innerHTML = `
                <div class="${style.iconBg} p-2 rounded-full"><i data-lucide="${style.icon}" class="w-6 h-6 ${style.iconColor}"></i></div>
                <div class="flex-1"><h4 class="text-md font-bold text-slate-800">${title}</h4>${message ? `<p class="text-sm text-slate-600 mt-1">${message}</p>` : ''}</div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 p-1 -mt-1 -mr-1"><i data-lucide="x" class="w-5 h-5"></i></button>`;
            container.appendChild(toast);
            lucide.createIcons();
            requestAnimationFrame(() => toast.classList.remove('translate-x-full'));
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        function initializeIcons() { if (window.lucide) { lucide.createIcons(); } }
        async function fetchAPI(endpoint, method = 'GET', data = null) {
            const config = { method };
            if (data) {
                const formData = new FormData();
                for (const key in data) {
                    const val = data[key];
                    if (val === null || typeof val === 'undefined') {
                        // Append empty string for null/undefined so server treats it as NULL
                        formData.append(key, '');
                    } else if (typeof val === 'object') {
                        formData.append(key, JSON.stringify(val));
                    } else {
                        formData.append(key, String(val));
                    }
                }
                config.body = formData;
            }
            const response = await fetch(`${API_URL}?action=${endpoint}`, config);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        }

        async function sendSmsAndUpdateLog(phone, text, type) {
             if (!phone) return showToast("No phone number", "", "error");
            try {
                await fetchAPI('send_sms', 'POST', { to: phone.replace(/\D/g, ''), text: text });
                const newLog = { message: `SMS Sent (${type})`, timestamp: new Date().toISOString(), type: 'sms' };
                const logs = [...(initialCaseData.systemLogs || []), newLog];
                
                // This is a fire-and-forget update just for the log
                fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', { systemLogs: logs, id: CASE_ID });

                initialCaseData.systemLogs = logs;
                updateActivityLog(logs);
                showToast("SMS Sent", `Type: ${type}`, "success");
            } catch (e) {
                showToast("SMS Failed", "", "error");
            }
        }
        
        async function acceptReschedule() {
            if (!confirm('Accept reschedule and update appointment?')) return;
            try {
                const rescheduleDateTime = initialCaseData.rescheduleDate.replace(' ', 'T');
                await fetchAPI(`accept_reschedule&id=${CASE_ID}`, 'POST', { service_date: rescheduleDateTime });
                showToast("Reschedule Accepted", "Appointment updated.", "success");
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) { showToast("Error", "Failed to accept reschedule.", "error"); }
        }

        async function declineReschedule() {
            if (!confirm('Decline this reschedule request?')) return;
            try {
                await fetchAPI(`decline_reschedule&id=${CASE_ID}`, 'POST', {});
                showToast("Request Declined", "Reschedule request removed.", "info");
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) { showToast("Error", "Failed to decline request.", "error"); }
        }

        async function addNote() {
            const input = document.getElementById('new-note-input');
            const text = input.value.trim();
            if (!text) return;
            const newNote = { text, authorName: '<?php echo addslashes($current_user_name); ?>', timestamp: new Date().toISOString() };
            try {
                const notes = [...(initialCaseData.internalNotes || []), newNote];
                await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', { internalNotes: notes, id: CASE_ID });
                initialCaseData.internalNotes = notes;
                updateNotesDisplay(notes);
                input.value = '';
                showToast("Note Added", "", "success");
            } catch (error) { showToast("Error", "Failed to add note.", "error"); }
        }
        
        async function deleteCase() {
            if (!confirm("Permanently delete this case? This cannot be undone.")) return;
            try {
                const result = await fetchAPI(`delete_transfer&id=${CASE_ID}`, 'POST');
                if (result.status === 'deleted') {
                    showToast("Case Deleted", "Permanently removed.", "success");
                    setTimeout(() => window.location.href = 'index.php', 1000);
                } else {
                    showToast(result.message || "Failed to delete.", "error");
                }
            } catch (error) { showToast("Error", "Failed to delete case.", "error"); }
        }

        function updateActivityLog(logs) {
            const container = document.getElementById('activity-log-container');
            if (!logs || logs.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-slate-500 text-sm">No activity recorded.</div>';
                return;
            }
            container.innerHTML = logs.slice().reverse().map(log => `
                <div class='flex items-start gap-3'>
                    <div class='bg-slate-100 rounded-full p-2 mt-0.5'><i data-lucide='history' class='w-4 h-4 text-slate-500'></i></div>
                    <div>
                        <p class='text-sm text-slate-700 font-medium'>${escapeHtml(log.message)}</p>
                        <time class='text-xs text-slate-400'>${new Date(log.timestamp).toLocaleString()}</time>
                    </div>
                </div>`).join('');
            lucide.createIcons();
        }

        function updateNotesDisplay(notes) {
            const container = document.getElementById('notes-container');
            if (!notes || notes.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-slate-500 text-sm">No internal notes yet.</div>';
                return;
            }
            container.innerHTML = notes.slice().reverse().map(note => `
                <div class='bg-slate-100 p-3 rounded-lg border border-slate-200/80'>
                    <p class='text-sm text-slate-800'>${escapeHtml(note.text)}</p>
                    <div class='text-xs text-slate-500 text-right mt-2'>${escapeHtml(note.authorName || 'Manager')} &middot; ${new Date(note.timestamp).toLocaleString()}</div>
                </div>`).join('');
        }

        function escapeHtml(text) {
            if (typeof text !== 'string') return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        function getFormattedMessage(type, data) {
            let template = smsTemplates[type]?.content || '';
            return template.replace(/{name}/g, data.name || '')
                           .replace(/{plate}/g, data.plate || '')
                           .replace(/{amount}/g, data.amount || '')
                           .replace(/{date}/g, data.date ? new Date(data.date).toLocaleString('ka-GE', { month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '')
                           .replace(/{link}/g, data.link || '');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const getTemplateData = () => ({
                id: CASE_ID,
                name: document.getElementById('input-name')?.value,
                plate: document.getElementById('input-plate')?.value,
                amount: document.getElementById('input-amount')?.value,
                date: document.getElementById('input-service-date')?.value,
                link: `${window.location.origin}${window.location.pathname.replace('edit_case.php', 'public_view.php')}?id=${CASE_ID}`
            });

            const quickSmsActions = { 'btn-sms-register': 'registered', 'btn-sms-arrived': 'parts_arrived', 'btn-sms-schedule': 'schedule', 'btn-sms-called': 'called', 'btn-sms-completed': 'completed' };
            for (const [btnId, slug] of Object.entries(quickSmsActions)) {
                document.getElementById(btnId)?.addEventListener('click', () => {
                    const phone = document.getElementById('input-phone')?.value;
                    if (slug === 'schedule' && !document.getElementById('input-service-date')?.value) {
                        return showToast('No Service Date', 'Please set a service date first.', 'error');
                    }
                    sendSmsAndUpdateLog(phone, getFormattedMessage(slug, getTemplateData()), slug);
                });
            }

            document.getElementById('btn-send-custom-sms')?.addEventListener('click', () => {
                const slug = document.getElementById('sms-template-selector')?.value;
                const phone = document.getElementById('input-phone')?.value;
                if (!slug) return showToast('No Template', 'Please select an SMS template.', 'error');
                sendSmsAndUpdateLog(phone, getFormattedMessage(slug, getTemplateData()), `custom_${slug}`);
            });

            document.getElementById('btn-save-review')?.addEventListener('click', async () => {
                const stars = document.getElementById('input-review-stars')?.value;
                const comment = document.getElementById('input-review-comment')?.value?.trim();
                try {
                    await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', { reviewStars: stars || null, reviewComment: comment || null, id: CASE_ID });
                    showToast("Review Updated", "Customer review saved.", "success");
                    setTimeout(() => window.location.reload(), 1000);
                } catch (error) { showToast("Error", "Failed to save review.", "error"); }
            });
        });

        // Global functions for repair management
        window.updatePart = function(index, field, value) {
            window.caseEditor.updatePart(index, field, value);
        };
        window.removePart = function(index) {
            window.caseEditor.removePart(index);
        };
        window.updateLabor = function(index, field, value) {
            window.caseEditor.updateLabor(index, field, value);
        };
        window.removeLabor = function(index) {
            window.caseEditor.removeLabor(index);
        };
    </script>
</body>
</html>