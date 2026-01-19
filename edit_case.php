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
    SELECT t.*, v.ownerName as vehicle_owner
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

// Ensure VAT fields are properly typed
$case['vat_enabled'] = !empty($case['vat_enabled']) && $case['vat_enabled'] != '0';
$case['vat_amount'] = (float)($case['vat_amount'] ?? 0.00);

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
    <script>
        // Provide initialCaseData early so Alpine has it when initializing
        let initialCaseData = {};

    </script>


    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 2px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        [x-cloak] { display: none !important; }
        .step-complete .step-line { background-color: #2563eb; }
        .step-complete .step-icon { background-color: #2563eb; color: white; }
        /* Selection visuals */
        .selected-row { background: rgba(59,130,246,0.06); border-left: 4px solid rgba(59,130,246,0.3); }
        .bulk-actions { position: sticky; top: 0; z-index: 30; background: rgba(255,255,255,0.9); padding: 8px; border-bottom: 1px solid #e6e6e6; display:flex; gap:8px; align-items:center; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display:flex; align-items:center; justify-content:center; z-index: 60; }
        .modal { background: white; border-radius: 8px; padding: 16px; width: 480px; max-width: calc(100% - 32px); box-shadow: 0 10px 25px rgba(2,6,23,0.15); }
        .step-current .step-icon { background-color: #2563eb; color: white; border-color: #2563eb; }
        .step-incomplete .step-icon { background-color: white; color: #6b7280; border-color: #d1d5db; }
        .btn-gradient { background: linear-gradient(135deg, #0ea5e9 0%, #d946ef 100%); transition: all 0.3s; }
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3); }
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
                        <button @click="shareInvoiceLink()" class="text-slate-600 h-10 px-4 inline-flex items-center justify-center rounded-lg border bg-white hover:bg-slate-50 font-semibold text-sm gap-2" title="Share Invoice Link">
                            <i data-lucide="share-2" class="w-4 h-4"></i>
                            <span class="hidden sm:inline">Share</span>
                        </button>
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
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform" :class="{'rotate-180': isSectionOpen('details')}"></i>
                    </button>
                    <div x-show="isSectionOpen('details')" x-cloak x-transition class="px-5 pb-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5 border-t border-slate-200 pt-6">
                            <!-- Form Fields -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.customer_name', 'Customer Name'); ?></label>
                                <input id="input-name" type="text" value="<?php echo htmlspecialchars($case['name']); ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                             <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5 flex items-center gap-2">
                                    <?php echo __('case.vehicle_plate', 'Vehicle Plate'); ?>
                                    <?php if (!empty($case['operatorComment']) && strpos($case['operatorComment'], 'Created from mobile app') === 0): ?>
                                        <span title="Synced from Mobile app" class="inline-flex items-center text-blue-500">
                                            <i data-lucide="smartphone" class="w-4 h-4"></i>
                                        </span>
                                    <?php endif; ?>
                                </label>
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

                            <!-- Payments Summary -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Paid (₾)</label>
                                <div id="payments-paid" class="text-lg font-semibold text-green-600">₾<?php echo number_format($case['amount_paid'] ?? 0,2); ?></div>
                                <label class="block text-sm font-medium text-slate-700 mt-2 mb-1.5">Balance (₾)</label>
                                <div id="payments-balance" class="text-lg font-semibold text-orange-600">₾<?php echo number_format(max(0,($case['amount'] ?? 0) - ($case['amount_paid'] ?? 0)),2); ?></div>
                                <div class="mt-3">
                                    <button type="button" onclick="openPaymentsModal()" class="h-10 px-4 rounded-lg bg-blue-600 text-white text-sm inline-flex items-center gap-2"><i data-lucide="dollar-sign" class="w-4 h-4"></i> Record Payment</button>
                                </div>
                            </div>

                            <!-- Vehicle Make -->
                            <div>

                            <!-- Payments Modal -->
                            <div id="payments-modal" class="hidden modal-backdrop" x-cloak>
                                <div class="modal">
                                    <h3 class="text-lg font-bold mb-2">Record Payment</h3>
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Amount (₾)</label>
                                            <input id="payment-amount" type="number" step="0.01" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Method</label>
                                            <select id="payment-method" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg"><option value="cash">Cash</option><option value="transfer">Transfer</option></select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Reference</label>
                                            <input id="payment-reference" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Notes</label>
                                            <textarea id="payment-notes" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg"></textarea>
                                        </div>
                                        <div class="flex justify-end gap-2 mt-4">
                                            <button type="button" onclick="closePaymentsModal()" class="px-4 py-2 rounded-lg border bg-white">Cancel</button>
                                            <button type="button" onclick="submitPayment()" class="px-4 py-2 rounded-lg bg-blue-600 text-white">Save Payment</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.vehicle_make', 'Vehicle Make'); ?></label>
                                <select id="input-vehicle-make" onchange="updateModelOptions('edit')" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                                    <option value="">Select Make</option>
                                    <option value="Toyota" <?php echo ($case['vehicle_make'] ?? '') === 'Toyota' ? 'selected' : ''; ?>>Toyota</option>
                                    <option value="Mercedes-Benz" <?php echo ($case['vehicle_make'] ?? '') === 'Mercedes-Benz' ? 'selected' : ''; ?>>Mercedes-Benz</option>
                                    <option value="BMW" <?php echo ($case['vehicle_make'] ?? '') === 'BMW' ? 'selected' : ''; ?>>BMW</option>
                                    <option value="Hyundai" <?php echo ($case['vehicle_make'] ?? '') === 'Hyundai' ? 'selected' : ''; ?>>Hyundai</option>
                                    <option value="Nissan" <?php echo ($case['vehicle_make'] ?? '') === 'Nissan' ? 'selected' : ''; ?>>Nissan</option>
                                    <option value="Lexus" <?php echo ($case['vehicle_make'] ?? '') === 'Lexus' ? 'selected' : ''; ?>>Lexus</option>
                                    <option value="Honda" <?php echo ($case['vehicle_make'] ?? '') === 'Honda' ? 'selected' : ''; ?>>Honda</option>
                                    <option value="Volkswagen" <?php echo ($case['vehicle_make'] ?? '') === 'Volkswagen' ? 'selected' : ''; ?>>Volkswagen</option>
                                    <option value="Audi" <?php echo ($case['vehicle_make'] ?? '') === 'Audi' ? 'selected' : ''; ?>>Audi</option>
                                    <option value="Subaru" <?php echo ($case['vehicle_make'] ?? '') === 'Subaru' ? 'selected' : ''; ?>>Subaru</option>
                                    <option value="Kia" <?php echo ($case['vehicle_make'] ?? '') === 'Kia' ? 'selected' : ''; ?>>Kia</option>
                                    <option value="Ford" <?php echo ($case['vehicle_make'] ?? '') === 'Ford' ? 'selected' : ''; ?>>Ford</option>
                                    <option value="Chevrolet" <?php echo ($case['vehicle_make'] ?? '') === 'Chevrolet' ? 'selected' : ''; ?>>Chevrolet</option>
                                    <option value="Mazda" <?php echo ($case['vehicle_make'] ?? '') === 'Mazda' ? 'selected' : ''; ?>>Mazda</option>
                                    <option value="Mitsubishi" <?php echo ($case['vehicle_make'] ?? '') === 'Mitsubishi' ? 'selected' : ''; ?>>Mitsubishi</option>
                                    <option value="Porsche" <?php echo ($case['vehicle_make'] ?? '') === 'Porsche' ? 'selected' : ''; ?>>Porsche</option>
                                    <option value="Land Rover" <?php echo ($case['vehicle_make'] ?? '') === 'Land Rover' ? 'selected' : ''; ?>>Land Rover</option>
                                    <option value="Jeep" <?php echo ($case['vehicle_make'] ?? '') === 'Jeep' ? 'selected' : ''; ?>>Jeep</option>
                                    <option value="Volvo" <?php echo ($case['vehicle_make'] ?? '') === 'Volvo' ? 'selected' : ''; ?>>Volvo</option>
                                    <option value="Opel" <?php echo ($case['vehicle_make'] ?? '') === 'Opel' ? 'selected' : ''; ?>>Opel</option>
                                    <option value="Peugeot" <?php echo ($case['vehicle_make'] ?? '') === 'Peugeot' ? 'selected' : ''; ?>>Peugeot</option>
                                    <option value="Renault" <?php echo ($case['vehicle_make'] ?? '') === 'Renault' ? 'selected' : ''; ?>>Renault</option>
                                    <option value="Suzuki" <?php echo ($case['vehicle_make'] ?? '') === 'Suzuki' ? 'selected' : ''; ?>>Suzuki</option>
                                    <option value="Other" <?php echo ($case['vehicle_make'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <!-- Vehicle Model -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.vehicle_model', 'Vehicle Model'); ?></label>
                                <input id="input-vehicle-model" type="text" value="<?php echo htmlspecialchars($case['vehicle_model'] ?? ''); ?>" placeholder="e.g. Camry, E-Class" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-blue-500/50 outline-none">
                            </div>
                            
                            <!-- Link Status -->
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5"><?php echo __('case.link_status', 'Public Link Status'); ?></label>
                                <div class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                                    <?php if (!empty($case['link_opened_at'])): ?>
                                        <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-100 text-blue-700 border border-blue-200 rounded-full text-sm font-medium">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                            <?php echo __('case.link_viewed', 'Viewed'); ?>
                                        </span>
                                        <span class="text-sm text-slate-600">
                                            <?php echo date('M j, Y g:i A', strtotime($case['link_opened_at'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-2 px-3 py-1 bg-slate-100 text-slate-500 border border-slate-200 rounded-full text-sm font-medium">
                                            <i data-lucide="eye-off" class="w-4 h-4"></i>
                                            <?php echo __('case.link_not_viewed', 'Not viewed yet'); ?>
                                        </span>
                                        <span class="text-xs text-slate-400"><?php echo __('case.link_not_viewed_desc', 'Customer has not opened the public link'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collapsible Section: Case Photos -->
                <?php
                $caseImages = [];
                if (!empty($case['case_images'])) {
                    $decoded = json_decode($case['case_images'], true);
                    if (is_array($decoded)) {
                        $caseImages = $decoded;
                    }
                }
                ?>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                    <button @click="toggleSection('photos')" class="w-full flex items-center justify-between p-6 hover:bg-slate-50/50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg flex items-center justify-center">
                                <i data-lucide="camera" class="w-5 h-5 text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-slate-800"><?php echo __('case.photos', 'Case Photos'); ?></h2>
                                <p class="text-sm text-slate-600 mt-0.5">Vehicle damage photos from mobile app</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php if (count($caseImages) > 0): ?>
                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">
                                <?php echo count($caseImages); ?> <?php echo count($caseImages) === 1 ? 'photo' : 'photos'; ?>
                            </span>
                            <?php else: ?>
                            <span class="px-3 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-medium">
                                No photos
                            </span>
                            <?php endif; ?>
                            <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform" :class="{'rotate-180': isSectionOpen('photos')}"></i>
                        </div>
                    </button>

                    <div x-show="isSectionOpen('photos')" x-cloak x-transition class="border-t border-slate-200">
                        <div class="p-6">
                            <?php if (count($caseImages) > 0): ?>
                            <!-- Image Gallery Grid -->
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="photo-gallery">
                                <?php foreach ($caseImages as $index => $imageUrl): ?>
                                <div class="relative group aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-100 cursor-pointer" onclick="openImageModal('<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>', <?php echo $index; ?>)">
                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                         alt="Case photo <?php echo $index + 1; ?>" 
                                         class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
                                         loading="lazy"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect fill=%22%23e2e8f0%22 width=%22100%22 height=%22100%22/><text x=%2250%22 y=%2255%22 text-anchor=%22middle%22 fill=%22%2394a3b8%22 font-size=%2212%22>No Image</text></svg>';">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between">
                                            <span class="text-white text-sm font-medium">Photo <?php echo $index + 1; ?></span>
                                            <div class="flex gap-2">
                                                <a href="<?php echo htmlspecialchars($imageUrl); ?>" target="_blank" class="p-2 bg-white/20 backdrop-blur-sm rounded-lg hover:bg-white/30 transition-colors" onclick="event.stopPropagation()">
                                                    <i data-lucide="external-link" class="w-4 h-4 text-white"></i>
                                                </a>
                                                <a href="<?php echo htmlspecialchars($imageUrl); ?>" download class="p-2 bg-white/20 backdrop-blur-sm rounded-lg hover:bg-white/30 transition-colors" onclick="event.stopPropagation()">
                                                    <i data-lucide="download" class="w-4 h-4 text-white"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Download All Button -->
                            <div class="mt-4 flex justify-end">
                                <button onclick="downloadAllImages()" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
                                    <i data-lucide="download" class="w-4 h-4"></i>
                                    Download All Photos
                                </button>
                            </div>
                            <?php else: ?>
                            <!-- Empty State -->
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i data-lucide="image-off" class="w-8 h-8 text-slate-400"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-slate-700 mb-2">No Photos Uploaded</h3>
                                <p class="text-sm text-slate-500 max-w-sm mx-auto">
                                    Photos will appear here when uploaded from the mobile app during vehicle inspection.
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Collapsible Section: Repair Management -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm">
                    <button @click="toggleSection('repair')" class="w-full flex items-center justify-between p-6 hover:bg-slate-50/50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                                <i data-lucide="wrench" class="w-5 h-5 text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-slate-800"><?php echo __('case.repair_management', 'Repair Management'); ?></h2>
                                <p class="text-sm text-slate-600 mt-0.5">Track repair progress, manage parts & services, monitor costs</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="hidden sm:flex items-center gap-2 px-3 py-1 bg-slate-100 rounded-full text-xs font-medium text-slate-700">
                                <span id="repair-status-badge" class="w-2 h-2 rounded-full bg-slate-400"></span>
                                <span id="repair-status-text"><?php echo __('case.not_started', 'Not Started'); ?></span>
                            </div>
                            <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform" :class="{'rotate-180': isSectionOpen('repair')}"></i>
                        </div>
                    </button>

                    <div x-show="isSectionOpen('repair')" x-cloak x-transition class="border-t border-slate-200">
                        <!-- Repair Progress Overview -->
                        <div class="px-6 py-4 bg-gradient-to-r from-slate-50 to-blue-50/30 border-b border-slate-200">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-semibold text-slate-800">Repair Progress</h3>
                                <div class="flex items-center gap-2 text-sm text-slate-600">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                    <span id="repair-duration">--</span>
                                </div>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2 mb-2">
                                <div id="repair-progress-bar" class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-slate-600">
                                <span>შეფასება</span>
                                <span>მუშავდება</span>
                                <span>დასრულებულია</span>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <!-- Quick Actions Bar -->
                            <div class="flex flex-wrap items-center justify-between gap-3 p-4 bg-slate-50 rounded-xl border border-slate-200">
                                <div class="flex items-center gap-3">
                                    <button @click="quickAddPart()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                        <i data-lucide="plus" class="w-4 h-4"></i>
                                        Add Part
                                    </button>
                                    <button @click="quickAddLabor()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                                        <i data-lucide="wrench" class="w-4 h-4"></i>
                                        Add Service
                                    </button>
                                    <button @click="parseInvoice()" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
                                        <i data-lucide="file-text" class="w-4 h-4"></i>
                                        Parse Invoice
                                    </button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click="showInvoice()" class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                                        <i data-lucide="printer" class="w-4 h-4"></i>
                                        Invoice
                                    </button>
                                    <button @click="exportRepairData()" class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                                        <i data-lucide="download" class="w-4 h-4"></i>
                                        Export
                                    </button>
                                </div>
                            </div>

                            <!-- Main Content Tabs -->
                            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                                <div class="flex border-b border-slate-200">
                                    <button @click="repairTab = 'overview'" :class="{'bg-blue-50 text-blue-700 border-b-2 border-blue-500': repairTab === 'overview'}" class="flex-1 px-6 py-4 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
                                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                                        Overview
                                    </button>
                                    <button @click="repairTab = 'items'" :class="{'bg-blue-50 text-blue-700 border-b-2 border-blue-500': repairTab === 'items'}" class="flex-1 px-6 py-4 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
                                        <i data-lucide="package" class="w-4 h-4"></i>
                                        Parts & Services
                                        <span id="items-count" class="ml-1 px-2 py-0.5 bg-slate-200 text-slate-700 text-xs rounded-full">0</span>
                                    </button>
                                    <button @click="repairTab = 'timeline'" :class="{'bg-blue-50 text-blue-700 border-b-2 border-blue-500': repairTab === 'timeline'}" class="flex-1 px-6 py-4 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
                                        <i data-lucide="clock" class="w-4 h-4"></i>
                                        Timeline
                                    </button>
                                    <button @click="repairTab = 'collections'" :class="{'bg-blue-50 text-blue-700 border-b-2 border-blue-500': repairTab === 'collections'}" class="flex-1 px-6 py-4 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
                                        <i data-lucide="archive" class="w-4 h-4"></i>
                                        Collections
                                    </button>
                                </div>

                                <div class="p-6">
                                    <!-- Overview Tab -->
                                    <div x-show="repairTab === 'overview'" x-transition class="space-y-6">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                            <!-- Repair Status Card -->
                                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl border border-blue-200">
                                                <div class="flex items-center justify-between mb-2">
                                                    <i data-lucide="settings" class="w-5 h-5 text-blue-600"></i>
                                                    <span class="text-xs font-medium text-blue-700 bg-blue-200 px-2 py-1 rounded-full" id="status-indicator">Not Started</span>
                                                </div>
                                                <h4 class="font-semibold text-slate-800 mb-1">Repair Status</h4>
                                                <select x-model="currentCase.repair_status" @change="updateRepairProgress()" class="w-full text-sm bg-white border border-blue-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="">Not Started</option>
                                                    <option value="წიანსწარი შეფასება">წიანსწარი შეფასება</option>
                                                    <option value="მუშავდება">მუშავდება</option>
                                                    <option value="იღებება">იღებება</option>
                                                    <option value="იშლება">იშლება</option>
                                                    <option value="აწყობა">აწყობა</option>
                                                    <option value="თუნუქი">თუნუქი</option>
                                                    <option value="პლასტმასის აღდგენა">პლასტმასის აღდგენა</option>
                                                    <option value="პოლირება">პოლირება</option>
                                                    <option value="დაშლილი და გასული">დაშლილი და გასული</option>
                                                </select>
                                            </div>

                                            <!-- Assigned Mechanic Card -->
                                            <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-xl border border-green-200">
                                                <div class="flex items-center justify-between mb-2">
                                                    <i data-lucide="user" class="w-5 h-5 text-green-600"></i>
                                                    <span class="text-xs font-medium text-green-700 bg-green-200 px-2 py-1 rounded-full">Assigned</span>
                                                </div>
                                                <h4 class="font-semibold text-slate-800 mb-1">Mechanic</h4>
                                                <input x-model="currentCase.assigned_mechanic" type="text" placeholder="Assign mechanic..." class="w-full text-sm bg-white border border-green-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                            </div>

                                            <!-- Timeline Card -->
                                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-xl border border-purple-200">
                                                <div class="flex items-center justify-between mb-2">
                                                    <i data-lucide="calendar" class="w-5 h-5 text-purple-600"></i>
                                                    <span class="text-xs font-medium text-purple-700 bg-purple-200 px-2 py-1 rounded-full">Schedule</span>
                                                </div>
                                                <h4 class="font-semibold text-slate-800 mb-1">Start Date</h4>
                                                <input x-model="currentCase.repair_start_date" type="datetime-local" class="w-full text-sm bg-white border border-purple-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                            </div>

                                            <!-- Cost Summary Card -->
                                            <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-4 rounded-xl border border-amber-200">
                                                <div class="flex items-center justify-between mb-2">
                                                    <i data-lucide="banknote" class="w-5 h-5 text-amber-600"></i>
                                                    <span class="text-xs font-medium text-amber-700 bg-amber-200 px-2 py-1 rounded-full">Total</span>
                                                </div>
                                                <h4 class="font-semibold text-slate-800 mb-1">Estimated Cost</h4>
                                                <div class="text-lg font-bold text-amber-700" id="overview-total-cost">₾0.00</div>
                                            </div>
                                        </div>

                                        <!-- Repair Notes -->
                                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                                            <div class="flex items-center gap-2 mb-3">
                                                <i data-lucide="file-text" class="w-5 h-5 text-slate-600"></i>
                                                <h4 class="font-semibold text-slate-800">Repair Notes</h4>
                                            </div>
                                            <textarea x-model="currentCase.repair_notes" rows="4" placeholder="Add notes about the repair process, special instructions, or observations..." class="w-full text-sm bg-white border border-slate-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
                                        </div>

                                        <!-- Quick Stats -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
                                                <div class="text-2xl font-bold text-blue-600" id="overview-parts-count">0</div>
                                                <div class="text-sm text-slate-600">Parts Required</div>
                                            </div>
                                            <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
                                                <div class="text-2xl font-bold text-green-600" id="overview-labor-count">0</div>
                                                <div class="text-sm text-slate-600">Services</div>
                                            </div>
                                            <div class="bg-white p-4 rounded-xl border border-slate-200 text-center">
                                                <div class="text-2xl font-bold text-purple-600" id="overview-activities-count">0</div>
                                                <div class="text-sm text-slate-600">Activities Logged</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Parts & Services Tab -->
                                    <div x-show="repairTab === 'items'" x-transition class="space-y-4">
                                        <!-- Search and Filter Bar -->
                                        <div class="flex flex-wrap items-center justify-between gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                            <div class="flex items-center gap-3">
                                                <div class="relative">
                                                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                                                    <input type="text" id="items-search" placeholder="Search parts & services..." class="pl-9 pr-4 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64">
                                                </div>
                                                <select id="items-filter" class="px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="all">All Items</option>
                                                    <option value="parts">Parts Only</option>
                                                    <option value="labor">Services Only</option>
                                                    <option value="pending">Pending</option>
                                                    <option value="completed">Completed</option>
                                                </select>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button @click="selectAllItems()" class="px-3 py-2 text-sm bg-white border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                                                    Select All
                                                </button>
                                                <button @click="createCollectionRequestFromSelected()" id="collection-request-btn" class="px-3 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors hidden">
                                                    <i data-lucide="package" class="w-4 h-4 inline mr-1"></i>
                                                    Create Collection Request
                                                </button>
                                                <button @click="bulkActions()" id="bulk-actions-btn" class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors hidden">
                                                    Bulk Actions
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Items List -->
                                        <div id="items-container" class="space-y-3 min-h-[400px]">
                                            <!-- Items will be rendered here by JavaScript -->
                                        </div>

                                        <!-- Empty State -->
                                        <div id="items-empty-state" class="text-center py-12">
                                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <i data-lucide="package" class="w-8 h-8 text-slate-400"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold text-slate-700 mb-2">No Items Added</h3>
                                            <p class="text-slate-600 mb-4">Start by adding parts and services for this repair.</p>
                                            <div class="flex justify-center gap-3">
                                                <button @click="quickAddPart()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                                    Add First Part
                                                </button>
                                                <button @click="parseInvoice()" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors">
                                                    <i data-lucide="file-text" class="w-4 h-4"></i>
                                                    Parse Invoice
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Professional Cost Breakdown Card -->
                                        <div class="bg-gradient-to-br from-slate-50 to-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
                                            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                                                <h3 class="text-white font-bold text-lg flex items-center gap-2">
                                                    <i data-lucide="receipt" class="w-5 h-5"></i>
                                                    Cost Breakdown
                                                </h3>
                                            </div>
                                            <div class="p-6">
                                                <div class="space-y-4">
                                                    <!-- Parts Section -->
                                                    <div class="flex items-center justify-between py-3 border-b border-slate-100">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                                                <i data-lucide="package" class="w-5 h-5 text-blue-600"></i>
                                                            </div>
                                                            <div>
                                                                <div class="font-medium text-slate-800">Parts</div>
                                                                <div class="text-sm text-slate-500" id="parts-count-label">0 items</div>
                                                            </div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-lg font-bold text-blue-600" id="items-parts-cost">₾0.00</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Services Section -->
                                                    <div class="flex items-center justify-between py-3 border-b border-slate-100">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                                                <i data-lucide="wrench" class="w-5 h-5 text-green-600"></i>
                                                            </div>
                                                            <div>
                                                                <div class="font-medium text-slate-800">Services</div>
                                                                <div class="text-sm text-slate-500" id="labor-count-label">0 items</div>
                                                            </div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-lg font-bold text-green-600" id="items-labor-cost">₾0.00</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Subtotal -->
                                                    <div class="flex items-center justify-between py-3 border-b border-slate-200">
                                                        <div class="font-medium text-slate-600">Subtotal</div>
                                                        <div class="text-lg font-semibold text-slate-700" id="items-total-cost">₾0.00</div>
                                                    </div>
                                                    
                                                    <!-- Discount Section -->
                                                    <div class="py-4 border-b border-slate-200 space-y-3">
                                                        <div class="flex items-center gap-2 mb-2">
                                                            <i data-lucide="percent" class="w-4 h-4 text-red-500"></i>
                                                            <span class="font-medium text-slate-700">Discounts</span>
                                                        </div>
                                                        
                                                        <!-- Parts Discount -->
                                                        <div class="flex items-center justify-between gap-4">
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center">
                                                                    <i data-lucide="package" class="w-3 h-3 text-blue-600"></i>
                                                                </div>
                                                                <span class="text-sm text-slate-600">Parts Discount</span>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <input type="number" id="parts-discount-pct" 
                                                                    x-model.number="currentCase.parts_discount_percent"
                                                                    @input="updateDiscounts()" 
                                                                    class="w-16 px-2 py-1 text-sm border border-slate-300 rounded text-center" 
                                                                    placeholder="0" min="0" max="100" step="0.5">
                                                                <span class="text-sm text-slate-500">%</span>
                                                                <span class="text-sm font-medium text-red-500 w-20 text-right" id="parts-discount-amount">-₾0.00</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Services Discount -->
                                                        <div class="flex items-center justify-between gap-4">
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-6 h-6 bg-green-100 rounded flex items-center justify-center">
                                                                    <i data-lucide="wrench" class="w-3 h-3 text-green-600"></i>
                                                                </div>
                                                                <span class="text-sm text-slate-600">Services Discount</span>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <input type="number" id="services-discount-pct" 
                                                                    x-model.number="currentCase.services_discount_percent"
                                                                    @input="updateDiscounts()" 
                                                                    class="w-16 px-2 py-1 text-sm border border-slate-300 rounded text-center" 
                                                                    placeholder="0" min="0" max="100" step="0.5">
                                                                <span class="text-sm text-slate-500">%</span>
                                                                <span class="text-sm font-medium text-red-500 w-20 text-right" id="services-discount-amount">-₾0.00</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Global Discount -->
                                                        <div class="flex items-center justify-between gap-4 pt-2 border-t border-slate-100">
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-6 h-6 bg-purple-100 rounded flex items-center justify-center">
                                                                    <i data-lucide="tag" class="w-3 h-3 text-purple-600"></i>
                                                                </div>
                                                                <span class="text-sm font-medium text-slate-700">Global Discount</span>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <input type="number" id="global-discount-pct" 
                                                                    x-model.number="currentCase.global_discount_percent"
                                                                    @input="updateDiscounts()" 
                                                                    class="w-16 px-2 py-1 text-sm border border-slate-300 rounded text-center" 
                                                                    placeholder="0" min="0" max="100" step="0.5">
                                                                <span class="text-sm text-slate-500">%</span>
                                                                <span class="text-sm font-medium text-red-500 w-20 text-right" id="global-discount-amount">-₾0.00</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Total Savings -->
                                                        <div class="flex items-center justify-between py-2 px-3 bg-red-50 rounded-lg mt-2">
                                                            <span class="text-sm font-medium text-red-700">Total Savings</span>
                                                            <span class="text-lg font-bold text-red-600" id="total-discount-amount">-₾0.00</span>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Grand Total -->
                                                    <div class="flex items-center justify-between py-4 bg-gradient-to-r from-indigo-50 to-purple-50 -mx-6 px-6 mt-4">
                                                        <div class="text-lg font-bold text-slate-800">Grand Total</div>
                                                        <div class="text-2xl font-bold text-indigo-600" id="items-grand-total">₾0.00</div>
                                                    </div>
                                                    
                                                    <!-- VAT Section -->
                                                    <div class="py-4 border-t border-slate-200">
                                                        <div class="flex items-center justify-between">
                                                            <div class="flex items-center gap-2">
                                                                <input type="checkbox" id="vat-enabled" 
                                                                    x-model="currentCase.vat_enabled"
                                                                    @change="updateVAT()" 
                                                                    class="w-4 h-4 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500">
                                                                <label for="vat-enabled" class="text-sm font-medium text-slate-700 cursor-pointer">
                                                                    Include VAT (დღგ) 18%
                                                                </label>
                                                            </div>
                                                            <div class="text-lg font-bold text-orange-600" id="vat-amount">₾0.00</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Final Total with VAT -->
                                                    <div class="flex items-center justify-between py-4 bg-gradient-to-r from-orange-50 to-red-50 -mx-6 px-6 border-t border-slate-200" x-show="currentCase.vat_enabled">
                                                        <div class="text-xl font-bold text-slate-800">Final Total (with VAT)</div>
                                                        <div class="text-3xl font-bold text-orange-600" id="final-total-with-vat">₾0.00</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Timeline Tab -->
                                    <div x-show="repairTab === 'timeline'" x-transition class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-lg font-semibold text-slate-800">Repair Timeline</h3>
                                            <button @click="addTimelineEvent()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                                <i data-lucide="plus" class="w-4 h-4"></i>
                                                Add Event
                                            </button>
                                        </div>

                                        <div id="timeline-container" class="space-y-4">
                                            <!-- Timeline events will be rendered here -->
                                        </div>

                                        <!-- Timeline Empty State -->
                                        <div id="timeline-empty-state" class="text-center py-12">
                                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <i data-lucide="clock" class="w-8 h-8 text-slate-400"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold text-slate-700 mb-2">No Timeline Events</h3>
                                            <p class="text-slate-600 mb-4">Track repair progress with timeline events.</p>
                                            <button @click="addTimelineEvent()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                                <i data-lucide="plus" class="w-4 h-4"></i>
                                                Add First Event
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Collections Tab -->
                                    <div x-show="repairTab === 'collections'" x-transition class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-lg font-semibold text-slate-800">Parts Collections</h3>
                                            <button @click="createNewCollection()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                                                <i data-lucide="plus" class="w-4 h-4"></i>
                                                New Collection
                                            </button>
                                        </div>

                                        <div id="collections-container" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <!-- Collections will be rendered here -->
                                        </div>

                                        <!-- Collections Empty State -->
                                        <div id="collections-empty-state" class="text-center py-12">
                                            <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                                <i data-lucide="archive" class="w-8 h-8 text-slate-400"></i>
                                            </div>
                                            <h3 class="text-lg font-semibold text-slate-700 mb-2">No Parts Collections</h3>
                                            <p class="text-slate-600 mb-4">Create collections to organize and track parts orders.</p>
                                            <button @click="createNewCollection()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                                                <i data-lucide="plus" class="w-4 h-4"></i>
                                                Create First Collection
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Manual Appointment Confirmation -->
                <?php if ($case['status'] === 'Scheduled' && $case['user_response'] !== 'Confirmed'): ?>
                <div class="bg-blue-50/80 border-2 border-blue-200 rounded-2xl p-5">
                    <div class="flex items-start gap-4">
                        <i data-lucide="check-circle-2" class="w-8 h-8 text-blue-600 mt-1 flex-shrink-0"></i>
                        <div>
                            <h3 class="text-lg font-bold text-blue-900"><?php echo __('case.confirm_appointment', 'Manual Appointment Confirmation'); ?></h3>
                            <p class="text-slate-600 mt-2"><?php echo __('case.confirm_appointment_desc', 'If the customer confirmed via phone or other means, manually mark this appointment as confirmed.'); ?></p>
                            <div class="flex gap-2 mt-4">
                                <button onclick="manuallyConfirmAppointment()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-4 rounded-md text-sm flex items-center gap-2">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                    <?php echo __('case.confirm', 'Confirm Appointment'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

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
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform" :class="{'rotate-180': isSectionOpen('communication')}"></i>
                    </button>
                    <div x-show="isSectionOpen('communication')" x-cloak x-transition class="px-5 pb-6">
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
                        <i data-lucide="chevron-down" class="w-5 h-5 text-slate-500 transition-transform" :class="{'rotate-180': isSectionOpen('feedback')}"></i>
                    </button>
                    <div x-show="isSectionOpen('feedback')" x-cloak x-transition class="px-5 pb-6">
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

    <!-- Modals -->
    <!-- Collections Modal -->
    <div id="collections-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 id="collections-modal-title" class="text-lg font-semibold text-gray-900">Parts Collections</h3>
                    <button onclick="document.getElementById('collections-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                <div id="collections-modal-content" class="p-6 overflow-y-auto max-h-[60vh]">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Parsed Items Modal -->
    <div id="parsed-items-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between p-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">Select Items to Add</h3>
                    <button onclick="document.getElementById('parsed-items-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                <div id="parsed-items-container" class="p-6">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="p-6 border-t border-gray-200 flex justify-end gap-3">
                    <button onclick="document.getElementById('parsed-items-modal').classList.add('hidden')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button onclick="caseEditor.addSelectedParsedItems()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Add Selected Items
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_URL = 'api.php';
        const CASE_ID = <?php echo $case_id; ?>;
        const CAN_EDIT = <?php echo $CAN_EDIT ? 'true' : 'false'; ?>;
        
        initialCaseData = initialCaseData || {};
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
                repairTab: 'overview',
                lastRemovedPart: null,
                lastRemovedTimer: null,
                markupPercentage: 0,
                collectionNote: '',
                dragPartIndex: null,
                dragLabIndex: null,
                selectedItems: [],
                searchQuery: '',
                filterType: 'all',
                timelineEvents: [],
                parsedItems: [],
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
                    this.$nextTick(() => {
                        initializeIcons();
                        
                        // Initialize repair tables and UI after DOM is ready
                        this.updatePartsList();
                        this.updateLaborList();
                        this.updateActivityLog();
                        this.updateRepairSummary();
                        this.updateRepairProgress();
                        this.updateOverviewStats();
                        this.loadCollections();
                        this.renderTimeline();
                        this.renderCollections();

                        // Initialize PDF parsing for repair
                        this.initRepairPdfParsing();

                        // Setup search and filter listeners
                        this.initSearchAndFilter();
                    });

                    document.getElementById('sms-template-selector')?.addEventListener('change', this.updateSmsPreview.bind(this));

                    // Load suggestions
                    loadData('api.php?action=get_item_suggestions&type=part', 'partSuggestions');
                    loadData('api.php?action=get_item_suggestions&type=labor', 'laborSuggestions');

                    // Initialize new features (only call methods that exist)
                    if (typeof this.initSearchAndFilter === 'function') this.initSearchAndFilter();
                    if (typeof this.loadCollections === 'function') this.loadCollections();
                    if (typeof this.updateOverviewStats === 'function') this.updateOverviewStats();
                    if (typeof this.renderTimeline === 'function') this.renderTimeline();

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
                },

                // New methods for redesigned UI
                updateRepairProgress() {
                    const status = this.currentCase.repair_status || '';
                    const progressBar = document.getElementById('repair-progress-bar');
                    const statusBadge = document.getElementById('repair-status-badge');
                    const statusText = document.getElementById('repair-status-text');

                    if (!progressBar || !statusBadge || !statusText) return;

                    const statusMap = {
                        '': { progress: 0, color: 'bg-slate-400', text: 'Not Started' },
                        'წიანსწარი შეფასება': { progress: 10, color: 'bg-blue-500', text: 'წიანსწარი შეფასება' },
                        'მუშავდება': { progress: 20, color: 'bg-yellow-500', text: 'მუშავდება' },
                        'იღებება': { progress: 30, color: 'bg-orange-500', text: 'იღებება' },
                        'იშლება': { progress: 40, color: 'bg-red-500', text: 'იშლება' },
                        'აწყობა': { progress: 60, color: 'bg-purple-500', text: 'აწყობა' },
                        'თუნუქი': { progress: 70, color: 'bg-pink-500', text: 'თუნუქი' },
                        'პლასტმასის აღდგენა': { progress: 80, color: 'bg-indigo-500', text: 'პლასტმასის აღდგენა' },
                        'პოლირება': { progress: 90, color: 'bg-teal-500', text: 'პოლირება' },
                        'დაშლილი და გასული': { progress: 100, color: 'bg-green-500', text: 'დაშლილი და გასული' }
                    };

                    const currentStatus = statusMap[status] || statusMap[''];
                    progressBar.style.width = `${currentStatus.progress}%`;
                    progressBar.className = `bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full transition-all duration-500`;
                    statusBadge.className = `w-2 h-2 rounded-full ${currentStatus.color.replace('bg-', 'bg-')}`;
                    statusText.textContent = currentStatus.text;

                    // Calculate duration
                    const startDate = this.currentCase.repair_start_date;
                    const endDate = this.currentCase.repair_end_date;
                    const durationEl = document.getElementById('repair-duration');

                    if (durationEl) {
                        if (startDate && endDate) {
                            const start = new Date(startDate);
                            const end = new Date(endDate);
                            const diffTime = Math.abs(end - start);
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            durationEl.textContent = `${diffDays} days`;
                        } else if (startDate) {
                            const start = new Date(startDate);
                            const now = new Date();
                            const diffTime = Math.abs(now - start);
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            durationEl.textContent = `${diffDays} days elapsed`;
                        } else {
                            durationEl.textContent = '--';
                        }
                    }
                },

                updateOverviewStats() {
                    const partsCount = (this.currentCase.repair_parts || []).length;
                    const laborCount = (this.currentCase.repair_labor || []).length;
                    const activitiesCount = (this.currentCase.repair_activity_log || []).length;
                    const totalCost = this.calculateTotalCost();

                    document.getElementById('overview-parts-count').textContent = partsCount;
                    document.getElementById('overview-labor-count').textContent = laborCount;
                    document.getElementById('overview-activities-count').textContent = activitiesCount;
                    document.getElementById('overview-total-cost').textContent = `₾${totalCost.toFixed(2)}`;
                },

                calculateTotalCost() {
                    // Calculate totals with individual item discounts
                    const partsTotal = (this.currentCase.repair_parts || []).reduce((sum, part) => {
                        const itemTotal = (part.quantity || 1) * (part.unit_price || 0);
                        const itemDiscount = part.discount_percent || 0;
                        return sum + (itemTotal * (1 - itemDiscount / 100));
                    }, 0);
                    
                    const laborTotal = (this.currentCase.repair_labor || []).reduce((sum, labor) => {
                        const itemTotal = (labor.quantity || labor.hours || 1) * (labor.unit_rate || labor.hourly_rate || 0);
                        const itemDiscount = labor.discount_percent || 0;
                        return sum + (itemTotal * (1 - itemDiscount / 100));
                    }, 0);
                    
                    // Apply category-level and global discounts
                    const partsDiscountPct = this.currentCase.parts_discount_percent || 0;
                    const servicesDiscountPct = this.currentCase.services_discount_percent || 0;
                    const globalDiscountPct = this.currentCase.global_discount_percent || 0;
                    
                    const afterPartsDiscount = partsTotal * (1 - partsDiscountPct / 100);
                    const afterServicesDiscount = laborTotal * (1 - servicesDiscountPct / 100);
                    const subtotalAfterCategory = afterPartsDiscount + afterServicesDiscount;
                    const grandTotal = subtotalAfterCategory * (1 - globalDiscountPct / 100);
                    
                    // Include VAT if enabled
                    const vatEnabled = this.currentCase.vat_enabled || false;
                    const vatAmount = vatEnabled ? grandTotal * 0.18 : 0;
                    const finalTotal = grandTotal + vatAmount;
                    
                    return finalTotal;
                },

                quickAddPart() {
                    const name = prompt('Enter part name:');
                    if (!name || !name.trim()) return;
                    const qty = parseInt(prompt('Enter quantity:', '1')) || 1;
                    const price = parseFloat(prompt('Enter unit price:', '0')) || 0;
                    this.addPart(name.trim(), qty, price);
                    showToast('Part Added', `${name} added successfully.`, 'success');
                },

                quickAddLabor() {
                    const description = prompt('Enter service description:');
                    if (!description || !description.trim()) return;
                    const quantity = parseFloat(prompt('Enter quantity (pcs):', '1')) || 1;
                    const rate = parseFloat(prompt('Enter unit rate (₾):', '0')) || 0;
                    this.addLabor(description.trim(), quantity, rate);
                    showToast('Service Added', `${description} added successfully.`, 'success');
                },

                parseInvoice() {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = '.pdf';
                    input.onchange = (e) => {
                        const file = e.target.files[0];
                        if (file) {
                            this.processInvoiceFile(file);
                        }
                    };
                    input.click();
                },

                async processInvoiceFile(file) {
                    showToast('Processing', 'Parsing invoice PDF...', 'info');
                    const formData = new FormData();
                    formData.append('pdf', file);

                    try {
                        const response = await fetch('api.php?action=parse_invoice_pdf', { method: 'POST', body: formData });
                        const data = await response.json();

                        if (data.success && Array.isArray(data.items) && data.items.length > 0) {
                            this.showParsedItemsModal(data.items);
                            showToast('Success', `Found ${data.items.length} items in invoice.`, 'success');
                        } else {
                            showToast('No Items Found', data.error || 'Could not parse any items from the PDF.', 'warning');
                        }
                    } catch (error) {
                        console.error('PDF parsing error:', error);
                        showToast('Error', 'Failed to parse invoice PDF.', 'error');
                    }
                },

                showParsedItemsModal(items) {
                    const modal = document.createElement('div');
                    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                    modal.innerHTML = `
                        <div class="bg-white rounded-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-hidden">
                            <div class="p-6 border-b border-slate-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-slate-800">Parsed Invoice Items</h3>
                                    <button onclick="this.closest('.fixed').remove()" class="text-slate-400 hover:text-slate-600">
                                        <i data-lucide="x" class="w-6 h-6"></i>
                                    </button>
                                </div>
                                <p class="text-sm text-slate-600 mt-1">Review and select items to add to your repair.</p>
                            </div>
                            <div class="p-6 overflow-y-auto max-h-96">
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left"><input type="checkbox" id="select-all-parsed" class="rounded"></th>
                                            <th class="px-3 py-2 text-left">Type</th>
                                            <th class="px-3 py-2 text-left">Name</th>
                                            <th class="px-3 py-2 text-left">Qty</th>
                                            <th class="px-3 py-2 text-left">Price</th>
                                            <th class="px-3 py-2 text-left">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="parsed-items-table">
                                        ${items.map((item, index) => `
                                            <tr class="border-t border-slate-100">
                                                <td class="px-3 py-2"><input type="checkbox" class="parsed-item-checkbox" data-index="${index}" checked></td>
                                                <td class="px-3 py-2">
                                                    <select class="parsed-type text-sm border rounded px-2 py-1" data-index="${index}">
                                                        <option value="part" ${item.type === 'part' ? 'selected' : ''}>Part</option>
                                                        <option value="labor" ${item.type === 'labor' ? 'selected' : ''}>Labor</option>
                                                    </select>
                                                </td>
                                                <td class="px-3 py-2"><input type="text" class="parsed-name w-full border rounded px-2 py-1 text-sm" value="${escapeHtml(item.name || '')}" data-index="${index}"></td>
                                                <td class="px-3 py-2"><input type="number" class="parsed-qty w-20 border rounded px-2 py-1 text-sm" value="${item.quantity || 1}" min="1" data-index="${index}"></td>
                                                <td class="px-3 py-2"><input type="number" class="parsed-price w-24 border rounded px-2 py-1 text-sm" value="${item.price || 0}" step="0.01" data-index="${index}"></td>
                                                <td class="px-3 py-2"><input type="text" class="parsed-notes w-full border rounded px-2 py-1 text-sm" value="${escapeHtml(item.notes || '')}" data-index="${index}"></td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-6 border-t border-slate-200 flex justify-end gap-3">
                                <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button onclick="window.caseEditor.addSelectedParsedItems(${JSON.stringify(items).replace(/"/g, '&quot;')})" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Add Selected Items
                                </button>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                    lucide.createIcons();

                    // Select all functionality
                    document.getElementById('select-all-parsed').addEventListener('change', (e) => {
                        document.querySelectorAll('.parsed-item-checkbox').forEach(cb => cb.checked = e.target.checked);
                    });
                },

                addSelectedParsedItems(originalItems) {
                    const selectedIndices = Array.from(document.querySelectorAll('.parsed-item-checkbox:checked')).map(cb => parseInt(cb.dataset.index));
                    let addedCount = 0;

                    selectedIndices.forEach(index => {
                        const item = originalItems[index];
                        if (!item) return;

                        const type = document.querySelector(`.parsed-type[data-index="${index}"]`).value;
                        const name = document.querySelector(`.parsed-name[data-index="${index}"]`).value.trim();
                        const qty = parseFloat(document.querySelector(`.parsed-qty[data-index="${index}"]`).value) || 1;
                        const price = parseFloat(document.querySelector(`.parsed-price[data-index="${index}"]`).value) || 0;
                        const notes = document.querySelector(`.parsed-notes[data-index="${index}"]`).value;

                        if (type === 'labor') {
                            this.addLabor(name, qty, price);
                            if (notes && this.currentCase.repair_labor.length > 0) {
                                this.currentCase.repair_labor[this.currentCase.repair_labor.length - 1].notes = notes;
                            }
                        } else {
                            this.addPart(name, qty, price);
                            if (notes && this.currentCase.repair_parts.length > 0) {
                                this.currentCase.repair_parts[this.currentCase.repair_parts.length - 1].notes = notes;
                            }
                        }
                        addedCount++;
                    });

                    this.updatePartsList();
                    this.updateLaborList();
                    this.updateOverviewStats();
                    document.querySelector('.fixed')?.remove();
                    showToast('Items Added', `${addedCount} items added to repair.`, 'success');
                },

                exportRepairData() {
                    const data = {
                        case: {
                            id: CASE_ID,
                            customer: this.currentCase.name,
                            plate: this.currentCase.plate,
                            status: this.currentCase.repair_status,
                            startDate: this.currentCase.repair_start_date,
                            endDate: this.currentCase.repair_end_date,
                            mechanic: this.currentCase.assigned_mechanic,
                            notes: this.currentCase.repair_notes
                        },
                        parts: this.currentCase.repair_parts || [],
                        labor: this.currentCase.repair_labor || [],
                        activities: this.currentCase.repair_activity_log || [],
                        totalCost: this.calculateTotalCost()
                    };

                    const json = JSON.stringify(data, null, 2);
                    const blob = new Blob([json], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `repair-case-${CASE_ID}.json`;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    showToast('Export Complete', 'Repair data exported successfully.', 'success');
                },

                initSearchAndFilter() {
                    const searchInput = document.getElementById('items-search');
                    const filterSelect = document.getElementById('items-filter');

                    if (searchInput) {
                        searchInput.addEventListener('input', () => {
                            this.searchQuery = searchInput.value.toLowerCase();
                            this.renderItemsList();
                        });
                    }

                    if (filterSelect) {
                        filterSelect.addEventListener('change', () => {
                            this.filterType = filterSelect.value;
                            this.renderItemsList();
                        });
                    }
                },

                renderItemsList() {
                    const container = document.getElementById('items-container');
                    const emptyState = document.getElementById('items-empty-state');
                    const countBadge = document.getElementById('items-count');

                    if (!container) return;

                    const parts = this.currentCase.repair_parts || [];
                    const labor = this.currentCase.repair_labor || [];
                    let allItems = [];

                    // Convert to unified format
                    parts.forEach((item, index) => {
                        allItems.push({
                            ...item,
                            type: 'part',
                            originalIndex: index,
                            displayName: item.name || 'Unnamed Part',
                            searchableText: `${item.name || ''} ${item.sku || ''} ${item.supplier || ''} ${item.notes || ''}`.toLowerCase()
                        });
                    });

                    labor.forEach((item, index) => {
                        allItems.push({
                            ...item,
                            type: 'labor',
                            originalIndex: index,
                            displayName: item.description || 'Unnamed Labor',
                            searchableText: `${item.description || ''} ${item.notes || ''}`.toLowerCase()
                        });
                    });

                    // Apply search filter
                    if (this.searchQuery) {
                        allItems = allItems.filter(item => item.searchableText.includes(this.searchQuery));
                    }

                    // Apply type filter
                    if (this.filterType !== 'all') {
                        if (this.filterType === 'parts') {
                            allItems = allItems.filter(item => item.type === 'part');
                        } else if (this.filterType === 'labor') {
                            allItems = allItems.filter(item => item.type === 'labor');
                        } else if (this.filterType === 'pending') {
                            allItems = allItems.filter(item => (item.status || 'Pending') === 'Pending');
                        } else if (this.filterType === 'completed') {
                            allItems = allItems.filter(item => (item.status || 'Pending') === 'Completed' || (item.status || 'Pending') === 'Billed');
                        }
                    }

                    // Update count badge
                    if (countBadge) {
                        countBadge.textContent = allItems.length;
                    }

                    // Show empty state if no items
                    if (allItems.length === 0) {
                        container.innerHTML = '';
                        if (emptyState) emptyState.classList.remove('hidden');
                        return;
                    }

                    if (emptyState) emptyState.classList.add('hidden');

                    // Group by type
                    const partsItems = allItems.filter(item => item.type === 'part');
                    const laborItems = allItems.filter(item => item.type === 'labor');

                    let html = '';

                    if (partsItems.length > 0) {
                        html += `<div class="mb-6">
                            <div class="flex items-center gap-2 mb-3">
                                <i data-lucide="package" class="w-5 h-5 text-blue-600"></i>
                                <h4 class="font-semibold text-slate-800">Parts (${partsItems.length})</h4>
                            </div>
                            <div class="space-y-3">
                                ${partsItems.map(item => this.renderItemCard(item)).join('')}
                            </div>
                        </div>`;
                    }

                    if (laborItems.length > 0) {
                        html += `<div class="mb-6">
                            <div class="flex items-center gap-2 mb-3">
                                <i data-lucide="user-plus" class="w-5 h-5 text-green-600"></i>
                                <h4 class="font-semibold text-slate-800">Services (${laborItems.length})</h4>
                            </div>
                            <div class="space-y-3">
                                ${laborItems.map(item => this.renderItemCard(item)).join('')}
                            </div>
                        </div>`;
                    }

                    container.innerHTML = html;
                    lucide.createIcons();
                    this.updateItemsCostSummary();
                    
                    // Add event listeners to checkboxes for updating visuals
                    document.querySelectorAll('#items-container .select-item').forEach(cb => {
                        cb.addEventListener('change', () => this.updateSelectVisuals());
                    });
                },

                renderItemCard(item) {
                    const isPart = item.type === 'part';
                    const statusColors = {
                        'Pending': 'bg-yellow-100 text-yellow-800',
                        'Ordered': 'bg-blue-100 text-blue-800',
                        'Received': 'bg-purple-100 text-purple-800',
                        'In Progress': 'bg-orange-100 text-orange-800',
                        'Completed': 'bg-green-100 text-green-800',
                        'Billed': 'bg-slate-100 text-slate-800'
                    };

                    const statusColor = statusColors[item.status || 'Pending'] || 'bg-slate-100 text-slate-800';

                    if (isPart) {
                        const itemDiscount = item.discount_percent || 0;
                        const itemTotal = (item.quantity || 1) * (item.unit_price || 0);
                        const discountedTotal = itemTotal * (1 - itemDiscount / 100);
                        return `
                            <div class="bg-white border border-slate-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" class="select-item w-4 h-4 text-blue-600 rounded" data-type="part" data-index="${item.originalIndex}">
                                        <div>
                                            <h5 class="font-medium text-slate-800">${escapeHtml(item.displayName)}</h5>
                                            ${item.sku ? `<p class="text-sm text-slate-600">SKU: ${escapeHtml(item.sku)}</p>` : ''}
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${statusColor}">${item.status || 'Pending'}</span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                                    <div>
                                        <span class="text-slate-600">Qty:</span>
                                        <input type="number" class="w-full mt-1 px-2 py-1 border rounded text-center" value="${item.quantity || 1}" min="1" onchange="updatePart(${item.originalIndex}, 'quantity', this.value)">
                                    </div>
                                    <div>
                                        <span class="text-slate-600">Unit Price:</span>
                                        <input type="number" class="w-full mt-1 px-2 py-1 border rounded text-center" value="${item.unit_price || 0}" step="0.01" onchange="updatePart(${item.originalIndex}, 'unit_price', this.value)">
                                    </div>
                                    <div>
                                        <span class="text-slate-600 flex items-center gap-1"><i data-lucide="percent" class="w-3 h-3 text-red-500"></i>Disc:</span>
                                        <input type="number" class="w-full mt-1 px-2 py-1 border border-red-200 rounded text-center bg-red-50" value="${itemDiscount}" min="0" max="100" step="0.5" onchange="updatePart(${item.originalIndex}, 'discount_percent', this.value)">
                                    </div>
                                    <div>
                                        <span class="text-slate-600">Total:</span>
                                        <div class="mt-1 font-semibold text-slate-800 ${itemDiscount > 0 ? 'line-through text-slate-400 text-xs' : ''}">₾${itemTotal.toFixed(2)}</div>
                                        ${itemDiscount > 0 ? `<div class="font-bold text-green-600">₾${discountedTotal.toFixed(2)}</div>` : ''}
                                    </div>
                                    <div class="flex items-end">
                                        <button onclick="window.caseEditor.removePart(${item.originalIndex})" class="w-full px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                                ${item.notes ? `<div class="mt-3 p-2 bg-slate-50 rounded text-sm text-slate-700">${escapeHtml(item.notes)}</div>` : ''}
                            </div>
                        `;
                    } else {
                        const itemDiscount = item.discount_percent || 0;
                        const itemTotal = (item.quantity || item.hours || 1) * (item.unit_rate || item.hourly_rate || 0);
                        const discountedTotal = itemTotal * (1 - itemDiscount / 100);
                        return `
                            <div class="bg-white border border-slate-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" class="select-item w-4 h-4 text-green-600 rounded" data-type="labor" data-index="${item.originalIndex}">
                                        <div>
                                            <h5 class="font-medium text-slate-800">${escapeHtml(item.displayName)}</h5>
                                            ${item.completed_by ? `<p class="text-sm text-slate-600">Completed by: ${escapeHtml(item.completed_by)}</p>` : ''}
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full ${statusColor}">${item.status || 'Pending'}</span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                                    <div>
                                        <span class="text-slate-600">Qty:</span>
                                        <input type="number" class="w-full mt-1 px-2 py-1 border rounded text-center" value="${item.quantity || item.hours || 1}" step="1" min="1" onchange="updateLabor(${item.originalIndex}, 'quantity', this.value)">
                                    </div>
                                    <div>
                                        <span class="text-slate-600">Unit Rate:</span>
                                        <input type="number" class="w-full mt-1 px-2 py-1 border rounded text-center" value="${item.unit_rate || item.hourly_rate || 0}" step="0.01" onchange="updateLabor(${item.originalIndex}, 'unit_rate', this.value)">
                                    </div>
                                    <div>
                                        <span class="text-slate-600 flex items-center gap-1"><i data-lucide="percent" class="w-3 h-3 text-red-500"></i>Disc:</span>
                                        <input type="number" class="w-full mt-1 px-2 py-1 border border-red-200 rounded text-center bg-red-50" value="${itemDiscount}" min="0" max="100" step="0.5" onchange="updateLabor(${item.originalIndex}, 'discount_percent', this.value)">
                                    </div>
                                    <div>
                                        <span class="text-slate-600">Total:</span>
                                        <div class="mt-1 font-semibold text-slate-800 ${itemDiscount > 0 ? 'line-through text-slate-400 text-xs' : ''}">₾${itemTotal.toFixed(2)}</div>
                                        ${itemDiscount > 0 ? `<div class="font-bold text-green-600">₾${discountedTotal.toFixed(2)}</div>` : ''}
                                    </div>
                                    <div class="flex items-end">
                                        <button onclick="window.caseEditor.removeLabor(${item.originalIndex})" class="w-full px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                                ${item.notes ? `<div class="mt-3 p-2 bg-slate-50 rounded text-sm text-slate-700">${escapeHtml(item.notes)}</div>` : ''}
                            </div>
                        `;
                    }
                },

                updateItemsCostSummary() {
                    // Delegate to updateRepairSummary for consistent discount handling
                    this.updateRepairSummary();
                },

                renderTimeline() {
                    const container = document.getElementById('timeline-container');
                    const emptyState = document.getElementById('timeline-empty-state');

                    if (!container) return;

                    // Combine activities and status changes into timeline
                    let events = [];

                    // Add status changes
                    const statusChanges = [
                        { status: 'New', timestamp: this.currentCase.created_at, type: 'status' },
                        { status: this.currentCase.repair_status, timestamp: this.currentCase.repair_start_date, type: 'status' }
                    ].filter(e => e.timestamp);

                    // Add activities
                    const activities = (this.currentCase.repair_activity_log || []).map(activity => ({
                        ...activity,
                        type: 'activity',
                        timestamp: activity.timestamp
                    }));

                    events = [...statusChanges, ...activities].sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

                    if (events.length === 0) {
                        container.innerHTML = '';
                        if (emptyState) emptyState.classList.remove('hidden');
                        return;
                    }

                    if (emptyState) emptyState.classList.add('hidden');

                    const html = events.map(event => {
                        if (event.type === 'status') {
                            return `
                                <div class="flex gap-4">
                                    <div class="flex flex-col items-center">
                                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                        <div class="w-px h-16 bg-slate-300"></div>
                                    </div>
                                    <div class="pb-8">
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <div class="flex items-center gap-2 mb-2">
                                                <i data-lucide="settings" class="w-4 h-4 text-blue-600"></i>
                                                <span class="font-medium text-blue-800">Status Changed</span>
                                            </div>
                                            <p class="text-sm text-slate-700">Repair status set to: <strong>${event.status || 'Not Started'}</strong></p>
                                            <p class="text-xs text-slate-500 mt-2">${new Date(event.timestamp).toLocaleString()}</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            return `
                                <div class="flex gap-4">
                                    <div class="flex flex-col items-center">
                                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                        <div class="w-px h-16 bg-slate-300"></div>
                                    </div>
                                    <div class="pb-8">
                                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                            <div class="flex items-center gap-2 mb-2">
                                                <i data-lucide="activity" class="w-4 h-4 text-green-600"></i>
                                                <span class="font-medium text-green-800">${escapeHtml(event.action || 'Activity')}</span>
                                            </div>
                                            ${event.details ? `<p class="text-sm text-slate-700 mb-2">${escapeHtml(event.details)}</p>` : ''}
                                            <div class="flex items-center justify-between text-xs text-slate-500">
                                                <span>By ${escapeHtml(event.user || 'Unknown')}</span>
                                                <span>${new Date(event.timestamp).toLocaleString()}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                    }).join('');

                    container.innerHTML = html;
                    lucide.createIcons();
                },

                addTimelineEvent() {
                    const action = prompt('Enter activity action:');
                    if (!action || !action.trim()) return;

                    const details = prompt('Enter activity details (optional):') || '';

                    const event = {
                        action: action.trim(),
                        details: details.trim(),
                        user: '<?php echo addslashes($current_user_name); ?>',
                        timestamp: new Date().toISOString(),
                        type: 'activity'
                    };

                    if (!this.currentCase.repair_activity_log) this.currentCase.repair_activity_log = [];
                    this.currentCase.repair_activity_log.push(event);

                    this.renderTimeline();
                    this.updateOverviewStats();
                    showToast('Activity Added', 'Timeline event added successfully.', 'success');
                },

                renderCollections() {
                    const container = document.getElementById('collections-container');
                    const emptyState = document.getElementById('collections-empty-state');

                    if (!container) return;

                    if (!this.collections || this.collections.length === 0) {
                        container.innerHTML = '';
                        if (emptyState) emptyState.classList.remove('hidden');
                        return;
                    }

                    if (emptyState) emptyState.classList.add('hidden');

                    const html = this.collections.map(collection => {
                        let parts = [];
                        try { parts = JSON.parse(collection.parts_list || '[]'); } catch (e) { parts = []; }
                        const totalCost = Number(collection.total_cost || 0);

                        return `
                            <div class="bg-white border border-slate-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h4 class="font-semibold text-slate-800">Collection #${collection.id}</h4>
                                        <p class="text-sm text-slate-600">${escapeHtml(collection.description || 'No description')}</p>
                                        <p class="text-xs text-slate-500 mt-1">${new Date(collection.created_at).toLocaleDateString()}</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-slate-800">₾${totalCost.toFixed(2)}</div>
                                        <div class="text-sm text-slate-600">${parts.length} items</div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="window.caseEditor.addCollectionItems(${collection.id})" class="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                                        Add All Items
                                    </button>
                                    <button onclick="window.caseEditor.viewCollectionDetails(${collection.id})" class="px-3 py-2 border border-slate-300 text-slate-700 text-sm rounded hover:bg-slate-50 transition-colors">
                                        View Details
                                    </button>
                                </div>
                            </div>
                        `;
                    }).join('');

                    container.innerHTML = html;
                },

                createNewCollection() {
                    const description = prompt('Enter collection description:');
                    if (!description || !description.trim()) return;

                    const selectedItems = this.getSelectedItems();
                    if (selectedItems.parts.length === 0) {
                        showToast('No Parts Selected', 'Please select parts to create a collection.', 'warning');
                        return;
                    }

                    // Create collection from selected parts
                    const partsList = selectedItems.parts.map(part => ({
                        name: part.name,
                        quantity: part.quantity || 1,
                        price: part.unit_price || 0
                    }));

                    this.savePartsCollectionFromItems(partsList, description.trim());
                },

                async savePartsCollectionFromItems(partsList, description) {
                    try {
                        const response = await fetch(`${API_URL}?action=create_parts_collection`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                transfer_id: CASE_ID,
                                parts_list: partsList,
                                description: description,
                                collection_type: 'manual'
                            })
                        });

                        const result = await response.json();
                        if (result.success) {
                            showToast('Collection Created', 'Parts collection created successfully.', 'success');
                            this.loadCollections();
                        } else {
                            showToast('Error', result.error || 'Failed to create collection.', 'error');
                        }
                    } catch (e) {
                        showToast('Error', 'Failed to create collection.', 'error');
                    }
                },

                viewCollectionDetails(collectionId) {
                    const collection = this.collections.find(c => c.id == collectionId);
                    if (!collection) return;

                    let parts = [];
                    try { parts = JSON.parse(collection.parts_list || '[]'); } catch (e) { parts = []; }

                    const modal = document.createElement('div');
                    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                    modal.innerHTML = `
                        <div class="bg-white rounded-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden">
                            <div class="p-6 border-b border-slate-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-slate-800">Collection #${collection.id}</h3>
                                    <button onclick="this.closest('.fixed').remove()" class="text-slate-400 hover:text-slate-600">
                                        <i data-lucide="x" class="w-6 h-6"></i>
                                    </button>
                                </div>
                                <p class="text-sm text-slate-600 mt-1">${escapeHtml(collection.description || 'No description')}</p>
                            </div>
                            <div class="p-6 overflow-y-auto max-h-96">
                                <div class="space-y-3">
                                    ${parts.map(part => `
                                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                                            <div>
                                                <div class="font-medium text-slate-800">${escapeHtml(part.name || 'Unnamed')}</div>
                                                <div class="text-sm text-slate-600">Qty: ${part.quantity || 1}</div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-semibold text-slate-800">₾${(Number(part.price || 0)).toFixed(2)}</div>
                                                <div class="text-sm text-slate-600">₾${((part.quantity || 1) * (part.price || 0)).toFixed(2)}</div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                                <div class="mt-4 pt-4 border-t border-slate-200">
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-slate-800">Total Cost:</span>
                                        <span class="text-lg font-bold text-slate-800">₾${Number(collection.total_cost || 0).toFixed(2)}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="p-6 border-t border-slate-200 flex justify-end">
                                <button onclick="window.caseEditor.addCollectionItems(${collection.id}); this.closest('.fixed').remove()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Add All Items to Repair
                                </button>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                    lucide.createIcons();
                },

                // Update existing methods to work with new UI
                updatePartsList() {
                    this.updateRepairSummary();
                    this.updateOverviewStats();
                    this.renderItemsList();
                    this.renderTimeline();
                    lucide.createIcons();
                },

                updateLaborList() {
                    this.updateRepairSummary();
                    this.updateOverviewStats();
                    this.renderItemsList();
                    this.renderTimeline();
                    lucide.createIcons();
                },
                isSectionOpen(section) {
                    return this.openSections && Array.isArray(this.openSections) ? this.openSections.includes(section) : false;
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
                
                // Share invoice link functionality
                async shareInvoiceLink() {
                    // Generate random slug if not exists
                    if (!this.currentCase.slug) {
                        this.currentCase.slug = this.generateRandomSlug();
                        // Save slug to database
                        try {
                            await fetchAPI('update_transfer', 'POST', { id: CASE_ID, slug: this.currentCase.slug });
                        } catch (err) {
                            console.error('Failed to save slug:', err);
                            showToast('Error', 'Failed to generate share link. Please try again.', 'error');
                            return;
                        }
                    }
                    
                    const invoiceUrl = `${window.location.origin}${window.location.pathname.replace('edit_case.php', 'public_invoice.php')}?slug=${this.currentCase.slug}`;
                    
                    // Create share modal
                    const modal = document.createElement('div');
                    modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4';
                    modal.id = 'share-modal';
                    modal.innerHTML = `
                        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-white font-bold text-lg flex items-center gap-2">
                                        <i data-lucide="share-2" class="w-5 h-5"></i>
                                        Share Invoice
                                    </h3>
                                    <button onclick="document.getElementById('share-modal').remove()" class="text-white/80 hover:text-white">
                                        <i data-lucide="x" class="w-5 h-5"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="p-6 space-y-4">
                                <p class="text-gray-600 text-sm">Share this link with your customer to view their invoice:</p>
                                
                                <div class="flex gap-2">
                                    <input type="text" readonly value="${invoiceUrl}" 
                                        class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600"
                                        id="share-invoice-url">
                                    <button onclick="window.caseEditor.copyInvoiceLink()" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                                        id="copy-link-btn">
                                        <i data-lucide="copy" class="w-4 h-4"></i>
                                        Copy
                                    </button>
                                </div>
                                
                                <div class="flex gap-3 pt-4 border-t border-gray-200">
                                    <a href="https://api.whatsapp.com/send?text=${encodeURIComponent('Invoice #' + CASE_ID + ': ' + invoiceUrl)}" 
                                        target="_blank"
                                        class="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-medium">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                        WhatsApp
                                    </a>
                                    <a href="mailto:?subject=${encodeURIComponent('Invoice #' + CASE_ID + ' from OTOMOTORS')}&body=${encodeURIComponent('View your invoice here: ' + invoiceUrl)}" 
                                        class="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors font-medium">
                                        <i data-lucide="mail" class="w-5 h-5"></i>
                                        Email
                                    </a>
                                </div>
                                
                                <div class="pt-4 border-t border-gray-200">
                                    <a href="${invoiceUrl}" target="_blank" 
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors font-medium">
                                        <i data-lucide="external-link" class="w-4 h-4"></i>
                                        Open Invoice Preview
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                    lucide.createIcons();
                    
                    // Close on backdrop click
                    modal.addEventListener('click', (e) => {
                        if (e.target === modal) modal.remove();
                    });
                },
                
                copyInvoiceLink() {
                    const urlInput = document.getElementById('share-invoice-url');
                    const copyBtn = document.getElementById('copy-link-btn');
                    if (urlInput) {
                        navigator.clipboard.writeText(urlInput.value).then(() => {
                            copyBtn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copied!';
                            copyBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                            copyBtn.classList.add('bg-green-600');
                            lucide.createIcons();
                            setTimeout(() => {
                                copyBtn.innerHTML = '<i data-lucide="copy" class="w-4 h-4"></i> Copy';
                                copyBtn.classList.remove('bg-green-600');
                                copyBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                                lucide.createIcons();
                            }, 2000);
                        }).catch(() => {
                            // Fallback for older browsers
                            urlInput.select();
                            document.execCommand('copy');
                            showToast('Link Copied', 'Invoice link copied to clipboard', 'success');
                        });
                    }
                },
                
                generateRandomSlug() {
                    // Generate a random 16-character slug using alphanumeric characters
                    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                    let slug = '';
                    for (let i = 0; i < 16; i++) {
                        slug += chars.charAt(Math.floor(Math.random() * chars.length));
                    }
                    return slug;
                },
                
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

                    // Calculate discounted amount from parts and labor
                    const calculatedAmount = this.calculateTotalCost();
                    
                    const updates = {
                        id: CASE_ID,
                        name: document.getElementById('input-name').value.trim(),
                        plate: document.getElementById('input-plate').value.trim(),
                        amount: calculatedAmount > 0 ? calculatedAmount.toFixed(2) : document.getElementById('input-amount').value.trim(),
                        status: status,
                        phone: document.getElementById('input-phone').value.trim(),
                        serviceDate: serviceDate || null,
                        dueDate: dueDate || null,
                        franchise: document.getElementById('input-franchise').value || 0,
                        vehicleMake: document.getElementById('input-vehicle-make')?.value.trim() || null,
                        vehicleModel: document.getElementById('input-vehicle-model')?.value.trim() || null,
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
                        // Discount fields
                        parts_discount_percent: this.currentCase.parts_discount_percent || 0,
                        services_discount_percent: this.currentCase.services_discount_percent || 0,
                        global_discount_percent: this.currentCase.global_discount_percent || 0,
                        // VAT fields
                        vatEnabled: this.currentCase.vat_enabled ? 1 : 0,
                        vatAmount: parseFloat(this.currentCase.vat_amount || 0),
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
                        this.currentCase.repair_parts[index][field] = (field === 'quantity' || field === 'unit_price' || field === 'discount_percent') ? parseFloat(value) || 0 : value;
                        this.updatePartsList();
                        this.syncAmountWithTotal();
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
                    // Sync amount field with discounted total
                    this.syncAmountWithTotal();
                }, 
                addLabor(description = '', quantity = 1, unit_rate = 0) {
                    if (!this.currentCase.repair_labor) this.currentCase.repair_labor = [];
                    this.currentCase.repair_labor.push({ description, quantity, unit_rate, billable: true, notes: '' });
                    this.updateLaborList();
                },
                updateLabor(index, field, value) {
                    if (this.currentCase.repair_labor && this.currentCase.repair_labor[index]) {
                        this.currentCase.repair_labor[index][field] = (field === 'quantity' || field === 'unit_rate' || field === 'discount_percent') ? parseFloat(value) || 0 : value;
                        this.updateLaborList();
                        this.syncAmountWithTotal();
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
                        const total = (this.currentCase.repair_labor || []).reduce((sum, labor) => sum + ((labor.quantity || labor.hours || 1) * (labor.unit_rate || labor.hourly_rate || 0)), 0);
                        totalEl.textContent = total.toFixed(2) + '₾';
                    }
                    lucide.createIcons();
                    // re-render combined list
                    if (typeof this.updateItemsList === 'function') this.updateItemsList();
                    // Sync amount field with discounted total
                    this.syncAmountWithTotal();
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
                // Drag enter / leave visuals
                dragEnterPart(ev, idx) { const el = document.querySelectorAll('.part-item')[idx]; if(el) el.classList.add('drag-over'); },
                dragLeavePart(ev, idx) { const el = document.querySelectorAll('.part-item')[idx]; if(el) el.classList.remove('drag-over'); },
                dragEnterLab(ev, idx) { const el = document.querySelectorAll('.labor-item')[idx]; if(el) el.classList.add('drag-over'); },
                dragLeaveLab(ev, idx) { const el = document.querySelectorAll('.labor-item')[idx]; if(el) el.classList.remove('drag-over'); },
                // Receipt / completion confirmations
                confirmReceipt(index) { const name = prompt('Received by (name):'); if (!name) return; const p = this.currentCase.repair_parts[index]; if (!p) return; p.received_by = name; p.received_at = new Date().toISOString(); p.status = 'Received'; this.updatePartsList(); showToast('Receipt Confirmed', `Received by ${name}`, 'success'); },
                editReceipt(index) { const p = this.currentCase.repair_parts[index]; if (!p) return; const name = prompt('Received by (name):', p.received_by || ''); if (name === null) return; p.received_by = name; this.updatePartsList(); showToast('Receipt Updated', '', 'success'); },
                confirmComplete(index) { const name = prompt('Completed by (name):'); if (!name) return; const l = this.currentCase.repair_labor[index]; if (!l) return; l.completed_by = name; l.completed_at = new Date().toISOString(); l.status = 'Completed'; this.updateLaborList(); showToast('Service Marked Completed', `Completed by ${name}`, 'success'); },
                editComplete(index) { const l = this.currentCase.repair_labor[index]; if (!l) return; const name = prompt('Completed by (name):', l.completed_by || ''); if (name === null) return; l.completed_by = name; this.updateLaborList(); showToast('Completion Updated', '', 'success'); },
                updateRepairSummary() {
                    // Calculate raw totals (before any discounts)
                    const partsTotal = (this.currentCase.repair_parts || []).reduce((sum, part) => {
                        const itemTotal = (part.quantity || 1) * (part.unit_price || 0);
                        const itemDiscount = part.discount_percent || 0;
                        return sum + (itemTotal * (1 - itemDiscount / 100));
                    }, 0);
                    
                    const laborTotal = (this.currentCase.repair_labor || []).reduce((sum, labor) => {
                        const itemTotal = (labor.quantity || labor.hours || 1) * (labor.unit_rate || labor.hourly_rate || 0);
                        const itemDiscount = labor.discount_percent || 0;
                        return sum + (itemTotal * (1 - itemDiscount / 100));
                    }, 0);
                    
                    const subtotal = partsTotal + laborTotal;
                    
                    // Apply category-level discounts
                    const partsDiscountPct = this.currentCase.parts_discount_percent || 0;
                    const servicesDiscountPct = this.currentCase.services_discount_percent || 0;
                    const globalDiscountPct = this.currentCase.global_discount_percent || 0;
                    
                    const partsDiscount = partsTotal * (partsDiscountPct / 100);
                    const servicesDiscount = laborTotal * (servicesDiscountPct / 100);
                    const afterCategoryDiscounts = subtotal - partsDiscount - servicesDiscount;
                    const globalDiscount = afterCategoryDiscounts * (globalDiscountPct / 100);
                    const totalDiscount = partsDiscount + servicesDiscount + globalDiscount;
                    const grandTotal = afterCategoryDiscounts - globalDiscount;
                    
                    // Calculate VAT if enabled (18% of grand total)
                    const vatEnabled = this.currentCase.vat_enabled || false;
                    const vatAmount = vatEnabled ? grandTotal * 0.18 : 0;
                    const finalTotal = grandTotal + vatAmount;
                    
                    // Update DOM elements
                    const partsEl = document.getElementById('items-parts-cost');
                    const laborEl = document.getElementById('items-labor-cost');
                    const grandEl = document.getElementById('items-grand-total');
                    const subtotalEl = document.getElementById('items-total-cost');
                    const partsCountEl = document.getElementById('parts-count-label');
                    const laborCountEl = document.getElementById('labor-count-label');
                    const partsDiscountAmountEl = document.getElementById('parts-discount-amount');
                    const servicesDiscountAmountEl = document.getElementById('services-discount-amount');
                    const globalDiscountAmountEl = document.getElementById('global-discount-amount');
                    const totalDiscountAmountEl = document.getElementById('total-discount-amount');
                    const vatAmountEl = document.getElementById('vat-amount');
                    const finalTotalEl = document.getElementById('final-total-with-vat');
                    
                    const partsCount = (this.currentCase.repair_parts || []).length;
                    const laborCount = (this.currentCase.repair_labor || []).length;
                    
                    if (partsEl) partsEl.textContent = `₾${partsTotal.toFixed(2)}`;
                    if (laborEl) laborEl.textContent = `₾${laborTotal.toFixed(2)}`;
                    if (grandEl) grandEl.textContent = `₾${grandTotal.toFixed(2)}`;
                    if (subtotalEl) subtotalEl.textContent = `₾${subtotal.toFixed(2)}`;
                    if (partsCountEl) partsCountEl.textContent = `${partsCount} item${partsCount !== 1 ? 's' : ''}`;
                    if (laborCountEl) laborCountEl.textContent = `${laborCount} item${laborCount !== 1 ? 's' : ''}`;
                    if (partsDiscountAmountEl) partsDiscountAmountEl.textContent = `-₾${partsDiscount.toFixed(2)}`;
                    if (servicesDiscountAmountEl) servicesDiscountAmountEl.textContent = `-₾${servicesDiscount.toFixed(2)}`;
                    if (globalDiscountAmountEl) globalDiscountAmountEl.textContent = `-₾${globalDiscount.toFixed(2)}`;
                    if (totalDiscountAmountEl) totalDiscountAmountEl.textContent = `-₾${totalDiscount.toFixed(2)}`;
                    if (vatAmountEl) vatAmountEl.textContent = `₾${vatAmount.toFixed(2)}`;
                    if (finalTotalEl) finalTotalEl.textContent = `₾${finalTotal.toFixed(2)}`;
                    
                    // Store VAT amount for saving
                    this.currentCase.vat_amount = vatAmount;
                },
                
                // Method to update discounts from inputs
                updateDiscounts() {
                    this.updateRepairSummary();
                    this.updateOverviewStats();
                    // Sync amount field with discounted total
                    this.syncAmountWithTotal();
                },
                
                // Method to update VAT calculations
                async updateVAT() {
                    this.updateRepairSummary();
                    this.updateOverviewStats();
                    // Sync amount field with total including VAT if enabled
                    this.syncAmountWithTotal();
                    
                    // Save VAT changes immediately
                    try {
                        await fetchAPI('update_transfer', 'POST', { 
                            id: CASE_ID, 
                            vatEnabled: this.currentCase.vat_enabled ? 1 : 0,
                            vatAmount: parseFloat(this.currentCase.vat_amount || 0)
                        });
                    } catch (err) {
                        console.error('Failed to save VAT:', err);
                        showToast('Error', 'Failed to save VAT setting. Please try again.', 'error');
                    }
                },
                
                // Sync the amount input field with the calculated discounted total
                syncAmountWithTotal() {
                    const total = this.calculateTotalCost();
                    if (total > 0) {
                        const amountInput = document.getElementById('input-amount');
                        if (amountInput) {
                            amountInput.value = total.toFixed(2);
                        }
                    }
                },

                // Render combined items (parts + labor) into a single view
                updateItemsList() {
                    this.renderItemsList();
                },

                showInvoice() {
                    const parts = this.currentCase.repair_parts || [];
                    const labor = this.currentCase.repair_labor || [];
                    // Calculate totals with individual item discounts
                    const partsRaw = parts.reduce((s,p) => {
                        const itemTotal = (p.quantity||1)*(p.unit_price||0);
                        const itemDiscount = p.discount_percent || 0;
                        return s + itemTotal;
                    }, 0);
                    const partsTotal = parts.reduce((s,p) => {
                        const itemTotal = (p.quantity||1)*(p.unit_price||0);
                        const itemDiscount = p.discount_percent || 0;
                        return s + (itemTotal * (1 - itemDiscount/100));
                    }, 0);
                    
                    const laborRaw = labor.reduce((s,l) => {
                        const itemTotal = (l.quantity||l.hours||1)*(l.unit_rate||l.hourly_rate||0);
                        return s + itemTotal;
                    }, 0);
                    const laborTotal = labor.reduce((s,l) => {
                        const itemTotal = (l.quantity||l.hours||1)*(l.unit_rate||l.hourly_rate||0);
                        const itemDiscount = l.discount_percent || 0;
                        return s + (itemTotal * (1 - itemDiscount/100));
                    }, 0);
                    
                    const subtotal = partsTotal + laborTotal;
                    
                    // Category and global discounts
                    const partsDiscountPct = this.currentCase.parts_discount_percent || 0;
                    const servicesDiscountPct = this.currentCase.services_discount_percent || 0;
                    const globalDiscountPct = this.currentCase.global_discount_percent || 0;
                    
                    const partsDiscount = partsTotal * (partsDiscountPct/100);
                    const servicesDiscount = laborTotal * (servicesDiscountPct/100);
                    const afterCategoryDiscounts = subtotal - partsDiscount - servicesDiscount;
                    const globalDiscount = afterCategoryDiscounts * (globalDiscountPct/100);
                    const totalDiscount = partsDiscount + servicesDiscount + globalDiscount;
                    const grand = afterCategoryDiscounts - globalDiscount;

                    const caseInfo = this.currentCase || {};
                    let rows = '';
                    parts.forEach(p => {
                        const itemDiscount = p.discount_percent || 0;
                        const itemTotal = (p.quantity||1)*(p.unit_price||0);
                        const discountedTotal = itemTotal * (1 - itemDiscount/100);
                        const discountNote = itemDiscount > 0 ? ` <span style="color:#dc2626">(-${itemDiscount}%)</span>` : '';
                        rows += `<tr><td>${escapeHtml(p.name||'')}${discountNote}</td><td>${p.quantity||1}</td><td>₾${(p.unit_price||0).toFixed(2)}</td><td>₾${discountedTotal.toFixed(2)}</td></tr>`;
                    });
                    labor.forEach(l => {
                        const itemDiscount = l.discount_percent || 0;
                        const itemTotal = (l.quantity||l.hours||1)*(l.unit_rate||l.hourly_rate||0);
                        const discountedTotal = itemTotal * (1 - itemDiscount/100);
                        const discountNote = itemDiscount > 0 ? ` <span style="color:#dc2626">(-${itemDiscount}%)</span>` : '';
                        rows += `<tr><td>${escapeHtml(l.description||'')}${discountNote}</td><td>${l.quantity||l.hours||1}</td><td>₾${(l.unit_rate||l.hourly_rate||0).toFixed(2)}</td><td>₾${discountedTotal.toFixed(2)}</td></tr>`;
                    });

                    // Build discount rows for footer
                    let discountRows = '';
                    if (partsDiscountPct > 0) discountRows += `<tr style="color:#dc2626"><td colspan="3">Parts Discount (${partsDiscountPct}%)</td><td>-₾${partsDiscount.toFixed(2)}</td></tr>`;
                    if (servicesDiscountPct > 0) discountRows += `<tr style="color:#dc2626"><td colspan="3">Services Discount (${servicesDiscountPct}%)</td><td>-₾${servicesDiscount.toFixed(2)}</td></tr>`;
                    if (globalDiscountPct > 0) discountRows += `<tr style="color:#dc2626"><td colspan="3">Global Discount (${globalDiscountPct}%)</td><td>-₾${globalDiscount.toFixed(2)}</td></tr>`;

                    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Invoice - Case ${CASE_ID}</title><style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;color:#111}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f7f7f7}.total-row{background:#f0f9ff;font-weight:bold}</style></head><body>
                        <h2>Invoice - Case #${CASE_ID}</h2>
                        <div><strong>Customer:</strong> ${escapeHtml(caseInfo.name||'')} &nbsp; <strong>Plate:</strong> ${escapeHtml(caseInfo.plate||'')}</div>
                        <table class="mt-4"><thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead><tbody>${rows}</tbody>
                        <tfoot>
                            <tr><th colspan="3">Parts Subtotal</th><th>₾${partsTotal.toFixed(2)}</th></tr>
                            <tr><th colspan="3">Services Subtotal</th><th>₾${laborTotal.toFixed(2)}</th></tr>
                            <tr><th colspan="3">Subtotal</th><th>₾${subtotal.toFixed(2)}</th></tr>
                            ${discountRows}
                            ${totalDiscount > 0 ? `<tr style="color:#dc2626;font-weight:bold"><td colspan="3">Total Savings</td><td>-₾${totalDiscount.toFixed(2)}</td></tr>` : ''}
                            <tr class="total-row"><th colspan="3">Grand Total</th><th style="font-size:1.2em">₾${grand.toFixed(2)}</th></tr>
                        </tfoot></table>
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

                    this.renderCollections();
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

                // Selection helpers
                selectAllItems() {
                    const checkboxes = document.querySelectorAll('#items-container .select-item');
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    checkboxes.forEach(cb => cb.checked = !allChecked);
                    this.updateSelectVisuals();
                },

                getSelectedItems() {
                    const parts = [];
                    const labor = [];
                    document.querySelectorAll('#items-container .select-item:checked').forEach(cb => {
                        const type = cb.dataset.type;
                        const idx = parseInt(cb.dataset.index, 10);
                        if (type === 'part' && this.currentCase.repair_parts && this.currentCase.repair_parts[idx]) {
                            parts.push({ ...this.currentCase.repair_parts[idx] });
                        }
                        if (type === 'labor' && this.currentCase.repair_labor && this.currentCase.repair_labor[idx]) {
                            labor.push({ ...this.currentCase.repair_labor[idx] });
                        }
                    });
                    return { parts, labor };
                },

                async createCollectionFromSelected() {
                    const sel = this.getSelectedItems();
                    if (!sel.parts || sel.parts.length === 0) return showToast('No parts selected', 'Select parts to create a collection.', 'info');
                    const items = sel.parts.map(p => ({ name: p.name, quantity: p.quantity || 1, price: p.unit_price || 0 }));
                    try {
                        const resp = await fetch('api.php?action=create_parts_collection', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ transfer_id: CASE_ID, parts_list: items, description: this.collectionNote }) });
                        const result = await resp.json();
                        if (result.success) { showToast('Collection Created', 'Selected parts saved to new collection.', 'success'); if (typeof this.loadCollections === 'function') this.loadCollections(); } else { showToast('Error', result.error || 'Failed to create collection.', 'error'); }
                    } catch (e) { showToast('Error', 'Failed to create collection.', 'error'); }
                },

                exportSelectedCSV() {
                    const sel = this.getSelectedItems();
                    const rows = [];
                    if ((sel.parts || []).length > 0) {
                        rows.push(['Parts']);
                        rows.push(['Name','SKU','Supplier','Qty','Unit Price','Total','Status','Notes']);
                        sel.parts.forEach(p => rows.push([p.name||'', p.sku||'', p.supplier||'', p.quantity||'', p.unit_price||'', ((p.quantity||0)*(p.unit_price||0)).toFixed(2), p.status||'', p.notes||'']));
                    }
                    if ((sel.labor || []).length > 0) {
                        rows.push([]);
                        rows.push(['Services']);
                        rows.push(['Description','Qty','Unit Rate','Total','Status','Notes']);
                        sel.labor.forEach(l => rows.push([l.description||'', l.quantity||1, l.unit_rate||'', ((l.quantity||1)*(l.unit_rate||0)).toFixed(2), l.status||'', l.notes||'']));
                    }
                    if (rows.length === 0) return showToast('No items selected', 'Select parts or services to export.', 'info');
                    const csv = rows.map(r => r.map(c=>`"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `case-${CASE_ID}-selected.csv`; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
                },

                // New: update select visuals and bulk toolbar
                updateSelectVisuals() {
                    const checkboxes = document.querySelectorAll('#items-container .select-item');
                    const checkedCount = document.querySelectorAll('#items-container .select-item:checked').length;
                    const partsCheckedCount = document.querySelectorAll('#items-container .select-item[data-type="part"]:checked').length;
                    const selectAllBtn = document.getElementById('select-all-btn');
                    const bulkActions = document.getElementById('bulk-actions');
                    const collectionRequestBtn = document.getElementById('collection-request-btn');

                    if (selectAllBtn) {
                        const allChecked = checkedCount === checkboxes.length && checkboxes.length > 0;
                        const someChecked = checkedCount > 0;
                        selectAllBtn.innerHTML = allChecked ? '<i data-lucide="check-square"></i> Deselect All' : '<i data-lucide="square"></i> Select All';
                        selectAllBtn.classList.toggle('text-blue-600', someChecked);
                        lucide.createIcons();
                    }

                    if (bulkActions) {
                        bulkActions.classList.toggle('hidden', checkedCount === 0);
                    }

                    if (collectionRequestBtn) {
                        collectionRequestBtn.classList.toggle('hidden', partsCheckedCount === 0);
                    }
                },

                // Bulk operations
                bulkDeleteItems() {
                    const selected = this.getSelectedItems();
                    if (selected.parts.length === 0 && selected.labor.length === 0) {
                        showToast('No items selected', 'error');
                        return;
                    }

                    if (!confirm(`Delete ${selected.parts.length + selected.labor.length} selected items?`)) return;

                    // Remove parts
                    selected.parts.forEach(item => {
                        const idx = this.currentCase.repair_parts.findIndex(p => p.id === item.id);
                        if (idx !== -1) this.currentCase.repair_parts.splice(idx, 1);
                    });

                    // Remove labor
                    selected.labor.forEach(item => {
                        const idx = this.currentCase.repair_labor.findIndex(l => l.id === item.id);
                        if (idx !== -1) this.currentCase.repair_labor.splice(idx, 1);
                    });

                    this.saveRepairData();
                    this.renderItemsList();
                    this.updateOverviewStats();
                    showToast('Items deleted successfully');
                },

                bulkDuplicateItems() {
                    const selected = this.getSelectedItems();
                    if (selected.parts.length === 0 && selected.labor.length === 0) {
                        showToast('No items selected', 'error');
                        return;
                    }

                    // Duplicate parts
                    selected.parts.forEach(item => {
                        const newItem = { ...item, id: Date.now() + Math.random() };
                        this.currentCase.repair_parts.push(newItem);
                    });

                    // Duplicate labor
                    selected.labor.forEach(item => {
                        const newItem = { ...item, id: Date.now() + Math.random() };
                        this.currentCase.repair_labor.push(newItem);
                    });

                    this.saveRepairData();
                    this.renderItemsList();
                    this.updateOverviewStats();
                    showToast('Items duplicated successfully');
                },

                bulkMoveToCollection() {
                    const selected = this.getSelectedItems();
                    if (selected.parts.length === 0 && selected.labor.length === 0) {
                        showToast('No items selected', 'error');
                        return;
                    }

                    // Open collections modal and pre-select items
                    this.selectedItems = selected;
                    this.showCollectionsModal('move');
                },

                bulkRequestPartsCollection() {
                    const selected = this.getSelectedItems();
                    if (selected.parts.length === 0 && selected.labor.length === 0) {
                        showToast('No items selected', 'error');
                        return;
                    }

                    // Create a description from selected items
                    const partsList = selected.parts.map(part => `${part.name} (Qty: ${part.quantity})`).join(', ');
                    const laborList = selected.labor.map(labor => `${labor.description} (Qty: ${labor.quantity})`).join(', ');
                    
                    const description = `Parts Collection Request for: ${partsList}${laborList ? `, Services: ${laborList}` : ''}`;
                    
                    // Pre-fill the parts request form
                    this.partsRequest.description = description;
                    this.partsRequest.collection_type = 'local';
                    
                    // Scroll to the parts request section and expand it
                    this.openSections = [...this.openSections, 'parts'];
                    localStorage.setItem('openSections', JSON.stringify(this.openSections));
                    
                    // Scroll to the parts request section
                    const partsSection = document.querySelector('[data-section="parts"]');
                    if (partsSection) {
                        partsSection.scrollIntoView({ behavior: 'smooth' });
                        // Focus on the description field
                        setTimeout(() => {
                            const descField = document.querySelector('textarea[x-model="partsRequest.description"]');
                            if (descField) descField.focus();
                        }, 500);
                    }
                    
                    showToast('Parts Request Form Ready', 'Please fill in supplier details and submit the request.', 'success');
                },

                async createCollectionRequestFromSelected() {
                    const selected = this.getSelectedItems();
                    if (selected.parts.length === 0) {
                        showToast('No parts selected', 'Please select at least one part to create a collection request.', 'error');
                        return;
                    }

                    // Create a description from selected parts only
                    const partsList = selected.parts.map(part => `${part.name} (Qty: ${part.quantity})`).join(', ');
                    const description = `Parts Collection Request: ${partsList}`;

                    try {
                        // Create the parts collection directly via API
                        const apiPartsList = selected.parts.map(part => ({
                            name: part.name,
                            quantity: part.quantity || 1,
                            price: part.unit_price || 0,
                            sku: part.sku || null,
                            supplier: part.supplier || null
                        }));
                        
                        const response = await fetch(`${API_URL}?action=create_parts_collection`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                transfer_id: CASE_ID,
                                parts_list: apiPartsList,
                                assigned_manager_id: null,
                                description: description,
                                supplier: null, // No supplier required
                                collection_type: 'local'
                            })
                        });
                        
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const result = await response.json();
                        
                        showToast("Parts Request Created", `Collection request created for ${selected.parts.length} selected part(s).`, "success");
                        
                        // Clear selection
                        document.querySelectorAll('#items-container .select-item:checked').forEach(cb => {
                            cb.checked = false;
                        });
                        this.updateSelectVisuals();
                        
                    } catch (error) {
                        showToast("Error", "Failed to create parts collection request.", "error");
                        console.error('Parts collection creation error:', error);
                    }
                },

                // Modal management
                showCollectionsModal(mode = 'view') {
                    const modal = document.getElementById('collections-modal');
                    if (!modal) return;

                    // Set modal title and content based on mode
                    const title = mode === 'move' ? 'Move Items to Collection' : 'Parts Collections';
                    document.getElementById('collections-modal-title').textContent = title;

                    // Render collections list
                    this.renderCollectionsModal(mode);

                    modal.classList.remove('hidden');
                },

                renderCollectionsModal(mode) {
                    const container = document.getElementById('collections-modal-content');
                    if (!container) return;

                    if (this.collections.length === 0) {
                        container.innerHTML = `
                            <div class="text-center py-8">
                                <i data-lucide="package" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                                <p class="text-gray-500">No collections found</p>
                                <button onclick="caseEditor.createNewCollection()" class="mt-4 btn-gradient text-white px-4 py-2 rounded-lg">
                                    Create New Collection
                                </button>
                            </div>
                        `;
                        lucide.createIcons();
                        return;
                    }

                    let html = '<div class="space-y-3">';
                    this.collections.forEach(collection => {
                        const parts = JSON.parse(collection.parts_list || '[]');
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900">${escapeHtml(collection.description || 'Unnamed Collection')}</h4>
                                        <p class="text-sm text-gray-500">${parts.length} parts • Created ${new Date(collection.created_at).toLocaleDateString()}</p>
                                    </div>
                                    <div class="flex gap-2">
                                        ${mode === 'move' ? 
                                            `<button onclick="caseEditor.moveItemsToCollection(${collection.id})" class="btn-gradient text-white px-3 py-1 rounded text-sm">
                                                Move Here
                                            </button>` : 
                                            `<button onclick="caseEditor.viewCollectionDetails(${collection.id})" class="text-blue-600 hover:text-blue-800 text-sm">
                                                View
                                            </button>`
                                        }
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';

                    container.innerHTML = html;
                    lucide.createIcons();
                },

                moveItemsToCollection(collectionId) {
                    if (!this.selectedItems) return;

                    // Add selected items to the collection
                    const collection = this.collections.find(c => c.id == collectionId);
                    if (!collection) return;

                    let parts = JSON.parse(collection.parts_list || '[]');

                    // Add selected parts
                    this.selectedItems.parts.forEach(item => {
                        parts.push({
                            name: item.name,
                            quantity: item.quantity || 1,
                            price: item.unit_price || 0
                        });
                    });

                    // Update collection
                    collection.parts_list = JSON.stringify(parts);

                    // Save to server
                    this.saveCollectionUpdate(collection);

                    // Close modal
                    document.getElementById('collections-modal').classList.add('hidden');
                    showToast('Items moved to collection');
                },

                async saveCollectionUpdate(collection) {
                    try {
                        const response = await fetch('api.php?action=update_parts_collection', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                id: collection.id,
                                parts_list: collection.parts_list,
                                description: collection.description
                            })
                        });
                        const result = await response.json();
                        if (result.success) {
                            this.loadCollections();
                        }
                    } catch (e) {
                        console.error('Failed to update collection:', e);
                    }
                },

                showParsedItemsModal(items) {
                    const modal = document.getElementById('parsed-items-modal');
                    if (!modal) return;

                    // Render parsed items for selection
                    this.renderParsedItemsModal(items);
                    modal.classList.remove('hidden');
                },

                renderParsedItemsModal(items) {
                    const container = document.getElementById('parsed-items-container');
                    if (!container) return;

                    let html = `
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium">Select Items to Add</h3>
                                <button onclick="caseEditor.selectAllParsedItems()" class="text-blue-600 hover:text-blue-800 text-sm">
                                    Select All
                                </button>
                            </div>
                    `;

                    items.forEach((item, index) => {
                        html += `
                            <div class="flex items-center p-3 border border-gray-200 rounded-lg">
                                <input type="checkbox" id="parsed-item-${index}" class="parsed-item-checkbox h-4 w-4 text-blue-600 rounded" data-index="${index}">
                                <label for="parsed-item-${index}" class="ml-3 flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-medium">${escapeHtml(item.name || item.description || 'Unknown Item')}</span>
                                            <span class="text-sm text-gray-500 ml-2">[${item.type || 'part'}]</span>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            Qty: ${item.quantity || 1} • ₾${(parseFloat(item.price) || 0).toFixed(2)}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        `;
                    });

                    html += `
                    </div>
                    `;

                    container.innerHTML = html;
                    lucide.createIcons();
                },

                selectAllParsedItems() {
                    const checkboxes = document.querySelectorAll('.parsed-item-checkbox');
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    checkboxes.forEach(cb => cb.checked = !allChecked);
                },

                addSelectedParsedItems() {
                    const selectedIndices = [];
                    document.querySelectorAll('.parsed-item-checkbox:checked').forEach(cb => {
                        selectedIndices.push(parseInt(cb.dataset.index));
                    });

                    if (selectedIndices.length === 0) {
                        showToast('No items selected', 'error');
                        return;
                    }

                    // Add selected items to repair data
                    selectedIndices.forEach(index => {
                        const item = this.parsedItems[index];
                        if (!item) return;

                        if (item.type === 'labor') {
                            this.addLabor(item.name || item.description, item.quantity || 1, item.price || 0);
                        } else {
                            this.addPart(item.name || item.description, item.quantity || 1, item.price || 0);
                        }
                    });

                    this.saveRepairData();
                    this.renderItemsList();
                    this.updateOverviewStats();

                    // Close modal
                    document.getElementById('parsed-items-modal').classList.add('hidden');
                    showToast(`${selectedIndices.length} items added successfully`);
                },

                exportRepairData() {
                    const data = {
                        case: this.currentCase,
                        parts: this.currentCase.repair_parts || [],
                        labor: this.currentCase.repair_labor || [],
                        timeline: this.currentCase.repair_activity_log || [],
                        exported_at: new Date().toISOString()
                    };

                    const json = JSON.stringify(data, null, 2);
                    const blob = new Blob([json], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `repair-case-${CASE_ID}-${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);

                    showToast('Repair data exported');
                },

                // Collection management
                createNewCollection() {
                    const name = prompt('Enter collection name:');
                    if (!name) return;

                    // Create from current repair items
                    const parts = (this.currentCase.repair_parts || []).map(p => ({
                        name: p.name,
                        quantity: p.quantity || 1,
                        price: p.unit_price || 0
                    }));

                    if (parts.length === 0) {
                        showToast('No parts to create collection from', 'error');
                        return;
                    }

                    this.savePartsCollectionFromItems(parts, name);
                },

                async savePartsCollectionFromItems(parts, description) {
                    try {
                        const response = await fetch(`${API_URL}?action=create_parts_collection`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                transfer_id: CASE_ID,
                                parts_list: parts,
                                description: description,
                                collection_type: 'local'
                            })
                        });
                        const result = await response.json();
                        if (result.success) {
                            showToast('Collection Created', 'Parts collection created successfully.', 'success');
                            this.loadCollections();
                        } else {
                            showToast('Error', result.error || 'Failed to create collection.', 'error');
                        }
                    } catch (e) {
                        showToast('Error', 'Failed to create collection.', 'error');
                    }
                },

                viewCollectionDetails(collectionId) {
                    const collection = this.collections.find(c => c.id == collectionId);
                    if (!collection) return;

                    // Show collection details modal
                    const parts = JSON.parse(collection.parts_list || '[]');
                    let details = `Collection: ${collection.description}\n\nParts:\n`;
                    parts.forEach((part, index) => {
                        details += `${index + 1}. ${part.name} (Qty: ${part.quantity}, ₾${part.price})\n`;
                    });

                    alert(details); // Simple alert for now, could be enhanced with a modal
                },

                // Cost calculations with discount support (duplicate function - kept for backwards compatibility)
                // Note: Primary calculateTotalCost is defined earlier in the caseEditor object

                // Quick add methods
                quickAddPart(name = '', quantity = 1, price = 0) {
                    if (!name.trim()) {
                        name = prompt('Enter part name:');
                        if (!name) return;
                    }
                    this.addPart(name, quantity, price);
                    this.renderItemsList();
                    this.updateOverviewStats();
                },

                quickAddLabor(description = '', quantity = 1, rate = 0) {
                    if (!description.trim()) {
                        description = prompt('Enter service description:');
                        if (!description) return;
                    }
                    this.addLabor(description, quantity, rate);
                    this.renderItemsList();
                    this.updateOverviewStats();
                },

                // PDF parsing methods
                parseInvoice() {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = '.pdf';
                    input.onchange = (e) => this.processInvoiceFile(e.target.files[0]);
                    input.click();
                },

                async processInvoiceFile(file) {
                    if (!file) return;

                    const formData = new FormData();
                    formData.append('pdf', file);

                    try {
                        showToast('Processing PDF...', 'info');
                        const response = await fetch('api.php?action=parse_invoice_pdf', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();

                        if (data.success && data.items && data.items.length > 0) {
                            this.parsedItems = data.items;
                            this.showParsedItemsModal(data.items);
                            showToast(`Found ${data.items.length} items`, 'Select which items to add');
                        } else {
                            showToast('No items found', 'Could not parse any items from the PDF', 'error');
                        }
                    } catch (error) {
                        console.error('PDF parsing error:', error);
                        showToast('Parsing failed', 'Error processing PDF file', 'error');
                    }
                },

                updateItemsCostSummary() {
                    const partsCount = (this.currentCase.repair_parts || []).length;
                    const laborCount = (this.currentCase.repair_labor || []).length;
                    const totalCost = this.calculateTotalCost();

                    // Update summary displays
                    const partsEl = document.getElementById('overview-parts-count');
                    const laborEl = document.getElementById('overview-labor-count');
                    const costEl = document.getElementById('overview-total-cost');

                    if (partsEl) partsEl.textContent = partsCount;
                    if (laborEl) laborEl.textContent = laborCount;
                    if (costEl) costEl.textContent = `₾${totalCost.toFixed(2)}`;
                },

                // Bulk actions menu
                bulkActions() {
                    const selected = this.getSelectedItems();
                    if (selected.parts.length === 0 && selected.labor.length === 0) {
                        showToast('No items selected', 'error');
                        return;
                    }

                    // Simple action menu - could be enhanced with a proper dropdown
                    const action = prompt(`Selected ${selected.parts.length + selected.labor.length} items. Choose action:\n1. Delete\n2. Duplicate\n3. Move to Collection\n4. Request Parts Collection\n\nEnter 1, 2, 3, or 4:`);
                    
                    switch(action) {
                        case '1':
                            this.bulkDeleteItems();
                            break;
                        case '2':
                            this.bulkDuplicateItems();
                            break;
                        case '3':
                            this.bulkMoveToCollection();
                            break;
                        case '4':
                            this.bulkRequestPartsCollection();
                            break;
                        default:
                            showToast('Cancelled', 'info');
                    }
                },

                addTimelineEvent(action, details = '') {
                    if (!action) {
                        action = prompt('Enter activity action:');
                        if (!action) return;
                    }
                    if (!details) {
                        details = prompt('Enter activity details:') || '';
                    }

                    if (!this.currentCase.repair_activity_log) {
                        this.currentCase.repair_activity_log = [];
                    }

                    this.currentCase.repair_activity_log.push({
                        action: action,
                        details: details,
                        user: '<?php echo addslashes($current_user_name); ?>',
                        timestamp: new Date().toISOString()
                    });

                    this.saveRepairData();
                    this.renderTimeline();
                },

                renderCollections() {
                    const container = document.getElementById('collections-container');
                    if (!container) return;

                    if (this.collections.length === 0) {
                        container.innerHTML = `
                            <div class="text-center py-8">
                                <i data-lucide="package" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                                <p class="text-gray-500">No collections found</p>
                                <button onclick="caseEditor.createNewCollection()" class="mt-4 btn-gradient text-white px-4 py-2 rounded-lg">
                                    Create New Collection
                                </button>
                            </div>
                        `;
                        lucide.createIcons();
                        return;
                    }

                    let html = '<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">';
                    this.collections.forEach(collection => {
                        const parts = JSON.parse(collection.parts_list || '[]');
                        const totalValue = parts.reduce((sum, part) => sum + ((part.quantity || 1) * (part.price || 0)), 0);
                        
                        html += `
                            <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-medium text-gray-900 truncate">${escapeHtml(collection.description || 'Unnamed Collection')}</h4>
                                    <span class="text-sm text-gray-500">${parts.length} items</span>
                                </div>
                                <div class="text-sm text-gray-600 mb-3">
                                    Total value: ₾${totalValue.toFixed(2)}
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="caseEditor.addCollectionItems(${collection.id})" 
                                            class="flex-1 text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">
                                        Add to Case
                                    </button>
                                    <button onclick="caseEditor.viewCollectionDetails(${collection.id})" 
                                            class="text-sm text-gray-600 hover:text-gray-800 px-2 py-1">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';

                    container.innerHTML = html;
                    lucide.createIcons();
                },

                // Item management
                editItem(type, index) {
                    const item = type === 'part' ? 
                        this.currentCase.repair_parts[index] : 
                        this.currentCase.repair_labor[index];
                    
                    if (!item) return;

                    // Simple edit modal - could be enhanced
                    const newName = prompt(`Edit ${type} name:`, item.name || item.description || '');
                    if (newName === null) return;

                    if (type === 'part') {
                        item.name = newName;
                        this.updatePartsList();
                    } else {
                        item.description = newName;
                        this.updateLaborList();
                    }

                    this.renderItemsList();
                    this.saveRepairData();
                },

                removeItem(type, index) {
                    if (!confirm(`Remove this ${type}?`)) return;

                    if (type === 'part') {
                        this.currentCase.repair_parts.splice(index, 1);
                        this.updatePartsList();
                    } else {
                        this.currentCase.repair_labor.splice(index, 1);
                        this.updateLaborList();
                    }

                    this.renderItemsList();
                    this.updateOverviewStats();
                    this.saveRepairData();
                },

                // Open modal to confirm collection creation
                openCreateCollectionModal() {
                    const sel = this.getSelectedItems();
                    if (!sel.parts || sel.parts.length === 0) return showToast('No parts selected', 'Select parts to create a collection.', 'info');
                    const modal = document.getElementById('collectionModal');
                    if (!modal) return this.createCollectionFromSelectedConfirmed(); // fallback
                    document.getElementById('collectionModalDesc').value = this.collectionNote || '';
                    modal.classList.remove('hidden');
                },

                // Confirmed action: send selected parts to create collection
                async createCollectionFromSelectedConfirmed() {
                    const sel = this.getSelectedItems();
                    const items = (sel.parts || []).map(p => ({ name: p.name, quantity: p.quantity || 1, price: p.unit_price || 0 }));
                    if (items.length === 0) return showToast('No parts selected', 'Select parts to create a collection.', 'info');
                    try {
                        const resp = await fetch('api.php?action=create_parts_collection', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ transfer_id: CASE_ID, parts_list: items, description: document.getElementById('collectionModalDesc')?.value || this.collectionNote }) });
                        const result = await resp.json();
                        if (result.success) { showToast('Collection Created', 'Selected parts saved to new collection.', 'success'); if (typeof this.loadCollections === 'function') this.loadCollections(); }
                        else { showToast('Error', result.error || 'Failed to create collection.', 'error'); }
                    } catch (e) { showToast('Error', 'Failed to create collection.', 'error'); }
                    // close modal
                    const modal = document.getElementById('collectionModal'); if (modal) modal.classList.add('hidden');
                },

                deselectAll() { document.querySelectorAll('.select-item').forEach(cb => cb.checked = false); this.updateSelectVisuals(); },

                // Keyboard shortcuts
                initSelectionShortcuts() {
                    document.addEventListener('keydown', (e) => {
                        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') { e.preventDefault(); this.selectAllItems(true); this.updateSelectVisuals(); }
                        if (e.key === 'Escape') { this.deselectAll(); }
                    });
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
                                    <td class="px-3 parsed-total text-sm">${((item.quantity||1)*(parseFloat(item.price)||0)).toFixed(2)}₾</td>
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
                },

                // Bulk actions menu
                bulkActions() {
                    const selected = this.getSelectedItems();
                    if (selected.parts.length === 0 && selected.labor.length === 0) {
                        showToast('No items selected', 'error');
                        return;
                    }

                    // Simple action menu - could be enhanced with a proper dropdown
                    const action = prompt(`Selected ${selected.parts.length + selected.labor.length} items. Choose action:\n1. Delete\n2. Duplicate\n3. Move to Collection\n4. Request Parts Collection\n\nEnter 1, 2, 3, or 4:`);
                    
                    switch(action) {
                        case '1':
                            this.bulkDeleteItems();
                            break;
                        case '2':
                            this.bulkDuplicateItems();
                            break;
                        case '3':
                            this.bulkMoveToCollection();
                            break;
                        case '4':
                            this.bulkRequestPartsCollection();
                            break;
                        default:
                            showToast('Cancelled', 'info');
                    }
                },

                // Data persistence
                async saveRepairData() {
                    try {
                        const response = await fetch('api.php?action=update_transfer&id=' + CASE_ID, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                id: CASE_ID,
                                repair_parts: this.currentCase.repair_parts || [],
                                repair_labor: this.currentCase.repair_labor || [],
                                repair_activity_log: this.currentCase.repair_activity_log || [],
                                repair_status: this.currentCase.repair_status || '',
                                repair_notes: this.currentCase.repair_notes || '',
                                assigned_mechanic: this.currentCase.assigned_mechanic || '',
                                repair_start_date: this.currentCase.repair_start_date || null
                            })
                        });
                        
                        if (!response.ok) throw new Error('Save failed');
                        const result = await response.json();
                        if (result.success) {
                            showToast('Data saved successfully');
                        } else {
                            showToast('Save failed', 'error');
                        }
                    } catch (error) {
                        console.error('Save error:', error);
                        showToast('Save failed', 'error');
                    }
                }
            }
        }
        
        // Helper function for model suggestions based on make
        function updateModelOptions(prefix) {
            const makeEl = document.getElementById(prefix + '-vehicle-make');
            const modelEl = document.getElementById(prefix + '-vehicle-model');
            if (!makeEl || !modelEl) return;
            
            // Set placeholder based on selected make
            const modelSuggestions = {
                'Toyota': 'e.g. Camry, Corolla, RAV4, Land Cruiser, Prius',
                'Mercedes-Benz': 'e.g. E-Class, S-Class, C-Class, GLE, GLC',
                'BMW': 'e.g. 3 Series, 5 Series, X5, X3, 7 Series',
                'Hyundai': 'e.g. Sonata, Tucson, Santa Fe, Elantra, i30',
                'Nissan': 'e.g. Altima, Qashqai, X-Trail, Patrol, Maxima',
                'Lexus': 'e.g. RX, ES, GX, LX, IS',
                'Honda': 'e.g. Accord, Civic, CR-V, HR-V, Pilot',
                'Volkswagen': 'e.g. Golf, Passat, Tiguan, Touareg, Jetta',
                'Audi': 'e.g. A4, A6, Q5, Q7, A3',
                'Subaru': 'e.g. Outback, Forester, Impreza, XV, Legacy',
                'Kia': 'e.g. Sportage, Optima, Sorento, Ceed, Rio',
                'Ford': 'e.g. Focus, Mustang, Explorer, F-150, Escape',
                'Chevrolet': 'e.g. Camaro, Malibu, Tahoe, Equinox, Cruze',
                'Mazda': 'e.g. Mazda3, Mazda6, CX-5, CX-9, MX-5',
                'Mitsubishi': 'e.g. Outlander, Pajero, Eclipse Cross, Lancer, ASX',
                'Porsche': 'e.g. Cayenne, Panamera, 911, Macan, Taycan',
                'Land Rover': 'e.g. Range Rover, Discovery, Defender, Evoque, Velar',
                'Jeep': 'e.g. Grand Cherokee, Wrangler, Cherokee, Compass, Renegade',
                'Volvo': 'e.g. XC90, XC60, S60, V60, XC40',
                'Opel': 'e.g. Astra, Insignia, Corsa, Mokka, Grandland',
                'Peugeot': 'e.g. 308, 508, 3008, 5008, 208',
                'Renault': 'e.g. Megane, Clio, Kadjar, Captur, Duster',
                'Suzuki': 'e.g. Vitara, Swift, Jimny, SX4, Ignis'
            };
            
            const make = makeEl.value;
            modelEl.placeholder = modelSuggestions[make] || 'Enter vehicle model';
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

        // ============ IMAGE GALLERY FUNCTIONS ============
        const caseImages = <?php echo json_encode($caseImages); ?>;
        let currentImageIndex = 0;
        
        function openImageModal(imageUrl, index) {
            currentImageIndex = index;
            const modal = document.getElementById('image-modal');
            const img = document.getElementById('modal-image');
            const counter = document.getElementById('image-counter');
            
            if (modal && img) {
                img.src = imageUrl;
                counter.textContent = `${index + 1} / ${caseImages.length}`;
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeImageModal() {
            const modal = document.getElementById('image-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }
        
        function navigateImage(direction) {
            currentImageIndex = (currentImageIndex + direction + caseImages.length) % caseImages.length;
            const img = document.getElementById('modal-image');
            const counter = document.getElementById('image-counter');
            
            if (img && caseImages[currentImageIndex]) {
                img.src = caseImages[currentImageIndex];
                counter.textContent = `${currentImageIndex + 1} / ${caseImages.length}`;
            }
        }
        
        function downloadAllImages() {
            if (caseImages.length === 0) {
                showToast('No images to download', '', 'error');
                return;
            }
            
            showToast('Starting download...', 'Opening images in new tabs', 'info');
            
            // Open each image in a new tab for download
            caseImages.forEach((url, index) => {
                setTimeout(() => {
                    window.open(url, '_blank');
                }, index * 500);
            });
        }
        
        // Keyboard navigation for image modal
        document.addEventListener('keydown', (e) => {
            const modal = document.getElementById('image-modal');
            if (modal && !modal.classList.contains('hidden')) {
                if (e.key === 'Escape') closeImageModal();
                if (e.key === 'ArrowLeft') navigateImage(-1);
                if (e.key === 'ArrowRight') navigateImage(1);
            }
        });
        // ============ END IMAGE GALLERY FUNCTIONS ============

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

        // -------------------- Payments handling --------------------
        async function loadPayments() {
            try {
                const resp = await fetchAPI(`get_payments&transfer_id=<?php echo $case_id; ?>`, 'GET');
                if (resp && resp.payments) {
                    renderPaymentsList(resp.payments);
                    const totalPaid = parseFloat(resp.total_paid || 0);
                    const amount = parseFloat(document.getElementById('input-amount')?.value || <?php echo json_encode((float)$case['amount']); ?>);
                    const paidEl = document.getElementById('payments-paid');
                    const balanceEl = document.getElementById('payments-balance');
                    if (paidEl) paidEl.textContent = `₾${totalPaid.toFixed(2)}`;
                    if (balanceEl) balanceEl.textContent = `₾${Math.max(0, (amount - totalPaid)).toFixed(2)}`;
                }
            } catch (e) {
                console.error('Failed to load payments', e);
            }
        }

        function openPaymentsModal() {
            const modal = document.getElementById('payments-modal');
            if (modal) modal.classList.remove('hidden');
            initializeIcons();
        }

        function closePaymentsModal() {
            const modal = document.getElementById('payments-modal');
            if (modal) modal.classList.add('hidden');
            document.getElementById('payment-amount').value = '';
            document.getElementById('payment-reference').value = '';
            document.getElementById('payment-notes').value = '';
            document.getElementById('payment-method').value = 'cash';
        }

        async function submitPayment() {
            const amount = parseFloat(document.getElementById('payment-amount').value || 0);
            const method = document.getElementById('payment-method').value;
            const reference = document.getElementById('payment-reference').value;
            const notes = document.getElementById('payment-notes').value;
            if (!amount || amount <= 0) { showToast('Validation Error', 'Please enter a positive amount', 'error'); return; }
            try {
                const res = await fetchAPI('create_payment', 'POST', { transfer_id: <?php echo $case_id; ?>, amount, method, reference, notes });
                if (res && res.status === 'success') {
                    closePaymentsModal();
                    await loadPayments();
                    showToast('Payment recorded', '', 'success');
                } else {
                    showToast('Failed to record payment', res.error || 'Unknown error', 'error');
                }
            } catch (e) {
                console.error('Payment error', e);
                showToast('Payment error', e.message, 'error');
            }
        }

        function renderPaymentsList(payments) {
            let container = document.getElementById('payments-list');
            if (!container) {
                container = document.createElement('div');
                container.id = 'payments-list';
                const ref = document.getElementById('payments-paid');
                ref && ref.parentNode && ref.parentNode.appendChild(container);
            }
            if (!payments || payments.length === 0) {
                container.innerHTML = '<div class="text-sm text-gray-500 mt-2">No payments recorded.</div>';
                return;
            }
            let html = '<div class="mt-2 space-y-2">';
            payments.forEach(p => {
                const when = p.paid_at || p.created_at || '';
                const user = p.recorded_by_username || '';
                html += `<div class="p-2 bg-white border rounded-lg flex justify-between items-center"><div><div class="text-sm font-medium">₾${parseFloat(p.amount).toFixed(2)} <span class="text-xs text-slate-500">(${p.method})</span></div><div class="text-xs text-gray-500">${p.reference ? 'Ref: '+p.reference+' • ' : ''}${p.notes ? p.notes : ''}</div></div><div class="text-xs text-gray-400 text-right">${when}<br>${user}</div></div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        document.addEventListener('DOMContentLoaded', () => { loadPayments().catch(console.error); });

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

        async function manuallyConfirmAppointment() {
            if (initialCaseData.status !== 'Scheduled') {
                showToast("Error", "Only Scheduled appointments can be confirmed.", "error");
                return;
            }
            if (initialCaseData.user_response === 'Confirmed') {
                showToast("Already Confirmed", "This appointment is already confirmed.", "info");
                return;
            }
            if (!confirm('Manually confirm this appointment for ' + initialCaseData.name + '?')) return;
            try {
                await fetchAPI(`confirm_appointment&id=${CASE_ID}`, 'POST', { user_response: 'Confirmed' });
                showToast("Appointment Confirmed", initialCaseData.name + " has confirmed their appointment.", "success");
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) { showToast("Error", "Failed to confirm appointment.", "error"); }
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
    
    <!-- Image Lightbox Modal -->
    <div id="image-modal" class="hidden fixed inset-0 z-[100] bg-black/95 backdrop-blur-sm flex items-center justify-center" onclick="if(event.target === this) closeImageModal()">
        <!-- Close Button -->
        <button onclick="closeImageModal()" class="absolute top-4 right-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors z-10">
            <i data-lucide="x" class="w-6 h-6 text-white"></i>
        </button>
        
        <!-- Image Counter -->
        <div id="image-counter" class="absolute top-4 left-4 px-4 py-2 bg-white/10 rounded-full text-white text-sm font-medium">
            1 / 1
        </div>
        
        <!-- Navigation Arrows -->
        <button onclick="navigateImage(-1)" class="absolute left-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors z-10">
            <i data-lucide="chevron-left" class="w-8 h-8 text-white"></i>
        </button>
        <button onclick="navigateImage(1)" class="absolute right-4 p-3 bg-white/10 hover:bg-white/20 rounded-full transition-colors z-10">
            <i data-lucide="chevron-right" class="w-8 h-8 text-white"></i>
        </button>
        
        <!-- Image Container -->
        <div class="max-w-[90vw] max-h-[90vh] flex items-center justify-center">
            <img id="modal-image" src="" alt="Case photo" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl">
        </div>
        
        <!-- Download Button -->
        <a id="modal-download" href="" download class="absolute bottom-4 right-4 inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-white text-sm font-medium transition-colors">
            <i data-lucide="download" class="w-4 h-4"></i>
            Download
        </a>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>