<?php
session_start();
require_once 'session_config.php';
require_once 'config.php';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Case #<?php echo $case_id; ?> - OTOMOTORS Manager Portal</title>

    <!-- Tailwind CSS -->
    <!-- DEVELOPMENT NOTE: Using CDN for rapid prototyping and development.
         For production deployment, consider:
         1. Install Tailwind: npm install -D tailwindcss
         2. Initialize: npx tailwindcss init
         3. Configure content paths in tailwind.config.js
         4. Build: npx tailwindcss -i input.css -o output.css --watch
         5. Replace CDN with: <link href="/path/to/output.css" rel="stylesheet">
         This warning is expected in development and can be safely ignored.
    -->
    <script>
        // Suppress Tailwind CDN warning in development
        const originalWarn = console.warn;
        console.warn = function(...args) {
            if (args[0] && args[0].includes && args[0].includes('cdn.tailwindcss.com should not be used in production')) {
                // Silently ignore this expected development warning
                return;
            }
            originalWarn.apply(console, args);
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.344.0/dist/umd/lucide.js"></script>

    <!-- Custom Styles -->
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .bg-grid-white\/\[0\.05\] {
            background-image: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px);
        }
        .bg-\[size\:20px_20px\] {
            background-size: 20px 20px;
        }

        /* Tab Styles */
        .tab-button {
            position: relative;
            transition: all 0.3s ease;
        }
        .tab-button:hover {
            background-color: rgba(148, 163, 184, 0.1);
        }
        .tab-button.active {
            background-color: rgba(59, 130, 246, 0.1);
            color: #1e293b;
            font-weight: 700;
        }
        .tab-button.active::after {
            content: '';
            position: absolute;
    <style>
        /* Modern Design Styles */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .section-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(148, 163, 184, 0.3), transparent);
        }

        .modern-input {
            background: rgba(255, 255, 255, 0.8);
            border: 2px solid rgba(148, 163, 184, 0.2);
            transition: all 0.3s ease;
        }

        .modern-input:focus {
            background: white;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .action-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .section-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(148, 163, 184, 0.1);
            transition: all 0.3s ease;
        }

        .section-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .timeline-item {
            position: relative;
            padding-left: 2rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0.5rem;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: #3b82f6;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(148, 163, 184, 0.1);
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.5);
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-3 pointer-events-none"></div>

    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Back Button -->
        <div class="mb-8 no-print">
            <a href="index.php" class="inline-flex items-center gap-3 text-slate-600 hover:text-slate-800 transition-all duration-200 group">
                <div class="p-2 rounded-full bg-white shadow-sm group-hover:shadow-md transition-shadow">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </div>
                <span class="font-semibold text-lg">Back to Dashboard</span>
            </a>
        </div>

        <!-- Case Header Card -->
        <div class="section-card p-8 mb-8 no-print">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="flex items-center gap-6 flex-1 min-w-0">
                    <div class="gradient-bg p-4 rounded-2xl shadow-lg">
                        <i data-lucide="car" class="w-8 h-8 text-white"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm text-slate-500 uppercase font-bold tracking-wider mb-1">Order #<?php echo $case_id; ?></div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="text-2xl font-bold text-slate-800 truncate"><?php echo htmlspecialchars($case['name']); ?></span>
                            <span class="text-slate-400">•</span>
                            <span class="font-mono text-xl text-blue-700 tracking-wider"><?php echo htmlspecialchars($case['plate']); ?></span>
                        </div>
                        <div class="flex items-center gap-4 text-sm text-slate-600">
                            <span>Created <?php echo date('M j, Y', strtotime($case['created_at'])); ?></span>
                            <span class="status-badge bg-blue-100 text-blue-800">
                                <?php echo htmlspecialchars($case['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <button onclick="window.printCase()" class="action-button flex items-center gap-2">
                        <i data-lucide="printer" class="w-5 h-5"></i>
                        Print Case
                    </button>
                    <a href="index.php" class="p-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all hover:shadow-md">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">

            <!-- Left Column: Case Details -->
            <div class="xl:col-span-2 space-y-8">

                <!-- Case Information Section -->
                <div class="section-card p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="gradient-bg p-3 rounded-xl">
                            <i data-lucide="file-text" class="w-6 h-6 text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-slate-800">Case Information</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Customer Details -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-slate-700 mb-4">Customer Details</h3>

                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">Customer Name</label>
                                <input id="input-name" type="text" value="<?php echo htmlspecialchars($case['name']); ?>" placeholder="Customer Name"
                                       class="modern-input w-full px-4 py-3 rounded-xl text-lg font-medium">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">License Plate</label>
                                <input id="input-plate" type="text" value="<?php echo htmlspecialchars($case['plate']); ?>" placeholder="Vehicle Plate"
                                       class="modern-input w-full px-4 py-3 rounded-xl text-lg font-medium font-mono">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">Phone Number</label>
                                <div class="relative">
                                    <i data-lucide="phone" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                                    <input id="input-phone" type="text" value="<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" placeholder="Phone Number"
                                           class="modern-input w-full pl-12 pr-4 py-3 rounded-xl text-lg font-medium">
                                    <a id="btn-call-real" href="tel:<?php echo htmlspecialchars($case['phone'] ?? ''); ?>"
                                       class="absolute right-3 top-1/2 -translate-y-1/2 p-2 rounded-lg bg-green-100 hover:bg-green-200 text-green-600 transition-colors">
                                        <i data-lucide="phone-call" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Service Details -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-slate-700 mb-4">Service Details</h3>

                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">Service Amount</label>
                                <div class="relative">
                                    <input id="input-amount" type="text" value="<?php echo htmlspecialchars($case['amount']); ?>" placeholder="0.00"
                                           class="modern-input w-full pr-12 pl-4 py-3 rounded-xl text-2xl font-bold text-emerald-600">
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-2xl font-bold text-emerald-600">₾</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">Franchise Amount</label>
                                <input id="input-franchise" type="number" value="<?php echo htmlspecialchars($case['franchise'] ?? 0); ?>" placeholder="0.00"
                                       class="modern-input w-full px-4 py-3 rounded-xl text-lg font-medium text-orange-600">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">Service Date & Time</label>
                                <input id="input-service-date" type="datetime-local"
                                       value="<?php echo $case['serviceDate'] ? date('Y-m-d\TH:i', strtotime($case['serviceDate'])) : ''; ?>"
                                       class="modern-input w-full px-4 py-3 rounded-xl text-lg font-medium">
                            </div>
                        </div>
                    </div>

                    <!-- Status Selection -->
                    <div class="mt-8">
                        <label class="block text-sm font-medium text-slate-600 mb-3">Workflow Status</label>
                        <select id="input-status" class="modern-input w-full px-4 py-4 rounded-xl text-xl font-bold appearance-none cursor-pointer">
                            <option value="New" <?php echo $case['status'] === 'New' ? 'selected' : ''; ?>>🔵 New Case</option>
                            <option value="Processing" <?php echo $case['status'] === 'Processing' ? 'selected' : ''; ?>>🟡 Processing</option>
                            <option value="Called" <?php echo $case['status'] === 'Called' ? 'selected' : ''; ?>>🟣 Contacted</option>
                            <option value="Parts Ordered" <?php echo $case['status'] === 'Parts Ordered' ? 'selected' : ''; ?>>📦 Parts Ordered</option>
                            <option value="Parts Arrived" <?php echo $case['status'] === 'Parts Arrived' ? 'selected' : ''; ?>>🏁 Parts Arrived</option>
                            <option value="Scheduled" <?php echo $case['status'] === 'Scheduled' ? 'selected' : ''; ?>>🟠 Scheduled</option>
                            <option value="Completed" <?php echo $case['status'] === 'Completed' ? 'selected' : ''; ?>>🟢 Completed</option>
                            <option value="Issue" <?php echo $case['status'] === 'Issue' ? 'selected' : ''; ?>>🔴 Issue</option>
                        </select>
                    </div>
                </div>

                <!-- Communication Section -->
                <div class="section-card p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="gradient-bg p-3 rounded-xl">
                            <i data-lucide="message-circle" class="w-6 h-6 text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-slate-800">Communication</h2>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Quick SMS Actions -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-slate-700 mb-4">Quick SMS Actions</h3>

                            <button id="btn-sms-register" class="w-full group flex justify-between items-center px-6 py-4 bg-gradient-to-r from-indigo-50 to-blue-50 border-2 border-indigo-200 rounded-xl hover:border-indigo-400 hover:shadow-lg transition-all text-left">
                                <div>
                                    <div class="text-lg font-bold text-slate-800 group-hover:text-indigo-700">Send Welcome SMS</div>
                                    <div class="text-sm text-slate-500 mt-1">Registration confirmation</div>
                                </div>
                                <div class="bg-indigo-100 group-hover:bg-indigo-600 p-3 rounded-lg transition-colors">
                                    <i data-lucide="message-square" class="w-6 h-6 text-indigo-600 group-hover:text-white"></i>
                                </div>
                            </button>

                            <button id="btn-sms-arrived" class="w-full group flex justify-between items-center px-6 py-4 bg-gradient-to-r from-teal-50 to-cyan-50 border-2 border-teal-200 rounded-xl hover:border-teal-400 hover:shadow-lg transition-all text-left">
                                <div>
                                    <div class="text-lg font-bold text-slate-800 group-hover:text-teal-700">Parts Arrived SMS</div>
                                    <div class="text-sm text-slate-500 mt-1">Includes customer link</div>
                                </div>
                                <div class="bg-teal-100 group-hover:bg-teal-600 p-3 rounded-lg transition-colors">
                                    <i data-lucide="package-check" class="w-6 h-6 text-teal-600 group-hover:text-white"></i>
                                </div>
                            </button>

                            <button id="btn-sms-schedule" class="w-full group flex justify-between items-center px-6 py-4 bg-gradient-to-r from-orange-50 to-amber-50 border-2 border-orange-200 rounded-xl hover:border-orange-400 hover:shadow-lg transition-all text-left">
                                <div>
                                    <div class="text-lg font-bold text-slate-800 group-hover:text-orange-700">Send Schedule SMS</div>
                                    <div class="text-sm text-slate-500 mt-1">Appointment reminder</div>
                                </div>
                                <div class="bg-orange-100 group-hover:bg-orange-600 p-3 rounded-lg transition-colors">
                                    <i data-lucide="calendar-check" class="w-6 h-6 text-orange-600 group-hover:text-white"></i>
                                </div>
                            </button>

                            <button id="btn-sms-called" class="w-full group flex justify-between items-center px-6 py-4 bg-gradient-to-r from-purple-50 to-violet-50 border-2 border-purple-200 rounded-xl hover:border-purple-400 hover:shadow-lg transition-all text-left">
                                <div>
                                    <div class="text-lg font-bold text-slate-800 group-hover:text-purple-700">Send Called SMS</div>
                                    <div class="text-sm text-slate-500 mt-1">Contact confirmation</div>
                                </div>
                                <div class="bg-purple-100 group-hover:bg-purple-600 p-3 rounded-lg transition-colors">
                                    <i data-lucide="phone-call" class="w-6 h-6 text-purple-600 group-hover:text-white"></i>
                                </div>
                            </button>

                            <button id="btn-sms-completed" class="w-full group flex justify-between items-center px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl hover:border-green-400 hover:shadow-lg transition-all text-left">
                                <div>
                                    <div class="text-lg font-bold text-slate-800 group-hover:text-green-700">Send Completed SMS</div>
                                    <div class="text-sm text-slate-500 mt-1">Service completion & review</div>
                                </div>
                                <div class="bg-green-100 group-hover:bg-green-600 p-3 rounded-lg transition-colors">
                                    <i data-lucide="check-circle" class="w-6 h-6 text-green-600 group-hover:text-white"></i>
                                </div>
                            </button>
                        </div>

                        <!-- Advanced SMS -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-slate-700 mb-4">Advanced SMS</h3>

                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">Select Template</label>
                                <select id="sms-template-selector" class="modern-input w-full px-4 py-3 rounded-xl text-lg font-medium">
                                    <option value="">Choose a template...</option>
                                    <?php foreach ($smsTemplates as $slug => $template): ?>
                                    <option value="<?php echo htmlspecialchars($slug); ?>" data-content="<?php echo htmlspecialchars($template['content']); ?>">
                                        <?php echo htmlspecialchars($template['name'] ?? ucfirst(str_replace('_', ' ', $slug))); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-2">Message Preview</label>
                                <div id="sms-preview" class="bg-slate-50 border-2 border-slate-200 rounded-xl p-4 min-h-[120px] text-sm text-slate-700 whitespace-pre-wrap">
                                    <span class="text-slate-400 italic">Select a template to see preview...</span>
                                </div>
                            </div>

                            <button id="btn-send-custom-sms" class="action-button w-full flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i data-lucide="send" class="w-5 h-5"></i>
                                Send Custom SMS
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Internal Notes Section -->
                <div class="section-card p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="gradient-bg p-3 rounded-xl">
                            <i data-lucide="sticky-note" class="w-6 h-6 text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-slate-800">Internal Notes</h2>
                    </div>

                    <div id="notes-container" class="space-y-4 mb-6 max-h-80 overflow-y-auto custom-scrollbar">
                        <?php
                        if (!empty($case['internalNotes'])) {
                            foreach ($case['internalNotes'] as $note) {
                                $date = date('M j, g:i A', strtotime($note['timestamp']));
                                echo "<div class='bg-gradient-to-r from-slate-50 to-blue-50 p-4 rounded-xl border border-slate-200 hover:shadow-md transition-shadow'>";
                                echo "<p class='text-sm text-slate-700 mb-2 leading-relaxed'>" . htmlspecialchars($note['text']) . "</p>";
                                echo "<div class='flex justify-end'>";
                                echo "<span class='text-xs text-slate-400 bg-white px-3 py-1 rounded-full font-medium border border-slate-200'>" . htmlspecialchars($note['authorName'] ?? 'Manager') . " - {$date}</span>";
                                echo "</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='text-center py-12'>";
                            echo "<i data-lucide='inbox' class='w-16 h-16 text-slate-300 mx-auto mb-4'></i>";
                            echo "<p class='text-lg text-slate-500 font-medium'>No internal notes yet</p>";
                            echo "<p class='text-sm text-slate-400 mt-1'>Add notes to track important information</p>";
                            echo "</div>";
                        }
                        ?>
                    </div>

                    <div class="flex gap-3">
                        <input id="new-note-input" type="text" placeholder="Add a note..." class="modern-input flex-1 px-4 py-3 rounded-xl text-sm">
                        <button onclick="addNote()" class="action-button px-6 py-3 flex items-center gap-2">
                            <i data-lucide="plus" class="w-5 h-5"></i>
                            Add Note
                        </button>
                    </div>
                </div>

            </div>

            <!-- Right Column: Activity & Actions -->
            <div class="space-y-8">

                <!-- Activity Timeline -->
                <div class="section-card p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="gradient-bg p-3 rounded-xl">
                            <i data-lucide="history" class="w-6 h-6 text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">Activity Timeline</h3>
                    </div>

                    <div id="activity-log-container" class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar">
                        <?php
                        if (!empty($case['systemLogs'])) {
                            foreach (array_reverse($case['systemLogs']) as $log) {
                                $date = date('M j, g:i A', strtotime($log['timestamp']));
                                echo "<div class='timeline-item'>";
                                echo "<div class='bg-white p-4 rounded-xl border border-slate-200 hover:shadow-md transition-shadow'>";
                                echo "<div class='text-xs text-slate-500 mb-1 font-medium'>{$date}</div>";
                                echo "<div class='text-sm text-slate-700 leading-relaxed'>" . htmlspecialchars($log['message']) . "</div>";
                                echo "</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='text-center py-8'>";
                            echo "<i data-lucide='inbox' class='w-12 h-12 text-slate-300 mx-auto mb-3'></i>";
                            echo "<p class='text-sm text-slate-500 font-medium'>No activity recorded</p>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Customer Review -->
                <div class="section-card p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="gradient-bg p-3 rounded-xl">
                                <i data-lucide="star" class="w-6 h-6 text-white"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800">Customer Review</h3>
                        </div>
                        <button id="btn-edit-review" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <i data-lucide="edit" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <div id="review-display">
                        <?php if (!empty($case['reviewStars'])): ?>
                        <div class="text-center mb-4">
                            <div class="flex justify-center gap-1 mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i data-lucide="star" class="w-8 h-8 <?php echo $i <= $case['reviewStars'] ? 'text-amber-400 fill-current' : 'text-slate-300'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-3xl font-black text-amber-600"><?php echo $case['reviewStars']; ?>/5</span>
                        </div>
                        <?php if (!empty($case['reviewComment'])): ?>
                        <div class="bg-amber-50 p-4 rounded-xl border-2 border-amber-200">
                            <p class="text-sm text-slate-700 italic leading-relaxed"><?php echo htmlspecialchars($case['reviewComment']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <i data-lucide="star" class="w-16 h-16 text-amber-300 mx-auto mb-4"></i>
                            <p class="text-lg text-slate-500 font-medium">No review yet</p>
                            <p class="text-sm text-slate-400 mt-1">Customer feedback will appear here</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div id="review-edit" class="space-y-4 hidden">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-2">Rating</label>
                            <select id="input-review-stars" class="modern-input w-full px-4 py-3 rounded-xl text-lg font-bold">
                                <option value="">No rating</option>
                                <option value="1" <?php echo $case['reviewStars'] == 1 ? 'selected' : ''; ?>>⭐ 1 Star</option>
                                <option value="2" <?php echo $case['reviewStars'] == 2 ? 'selected' : ''; ?>>⭐⭐ 2 Stars</option>
                                <option value="3" <?php echo $case['reviewStars'] == 3 ? 'selected' : ''; ?>>⭐⭐⭐ 3 Stars</option>
                                <option value="4" <?php echo $case['reviewStars'] == 4 ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4 Stars</option>
                                <option value="5" <?php echo $case['reviewStars'] == 5 ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 Stars</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-2">Comment</label>
                            <textarea id="input-review-comment" rows="4" placeholder="Customer feedback..." class="modern-input w-full px-4 py-3 rounded-xl text-sm resize-none"><?php echo htmlspecialchars($case['reviewComment'] ?? ''); ?></textarea>
                        </div>
                        <div class="flex gap-3">
                            <button id="btn-save-review" class="action-button flex-1 flex items-center justify-center gap-2">
                                <i data-lucide="save" class="w-5 h-5"></i>
                                Save Review
                            </button>
                            <button id="btn-cancel-review" class="px-4 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl transition-all">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Information -->
                <div class="section-card p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="gradient-bg p-3 rounded-xl">
                            <i data-lucide="car" class="w-6 h-6 text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">Vehicle Info</h3>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <i data-lucide="user" class="w-5 h-5 text-slate-400"></i>
                            <div>
                                <div class="text-xs text-slate-500 font-medium uppercase tracking-wider">Owner</div>
                                <div class="font-medium text-slate-700"><?php echo htmlspecialchars($case['vehicle_owner'] ?? 'Not specified'); ?></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <i data-lucide="car" class="w-5 h-5 text-slate-400"></i>
                            <div>
                                <div class="text-xs text-slate-500 font-medium uppercase tracking-wider">Model</div>
                                <div class="font-medium text-slate-700"><?php echo htmlspecialchars($case['vehicle_model'] ?? 'Not specified'); ?></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <i data-lucide="hash" class="w-5 h-5 text-slate-400"></i>
                            <div>
                                <div class="text-xs text-slate-500 font-medium uppercase tracking-wider">Plate</div>
                                <div class="font-mono font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($case['plate']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Panel -->
                <div class="section-card p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="gradient-bg p-3 rounded-xl">
                            <i data-lucide="settings" class="w-6 h-6 text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">Actions</h3>
                    </div>

                    <div class="space-y-4">
                        <button onclick="saveChanges()" class="action-button w-full flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-5 h-5"></i>
                            <span>Save All Changes</span>
                        </button>

                        <button onclick="printCase()" class="w-full bg-slate-600 hover:bg-slate-700 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2">
                            <i data-lucide="printer" class="w-5 h-5"></i>
                            <span>Print Case Details</span>
                        </button>

                        <div class="section-divider my-6"></div>

                        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                            <h4 class="text-sm font-bold text-red-800 mb-2 flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                Danger Zone
                            </h4>
                            <p class="text-sm text-red-700 mb-4">This action cannot be undone.</p>
                            <button onclick="deleteCase()" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                                <span>Delete This Case</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Reschedule Request Section (if applicable) -->
        <?php if ($case['user_response'] === 'Reschedule Requested' && !empty($case['rescheduleDate'])): ?>
        <div class="section-card p-8 mt-8 border-2 border-purple-200">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="bg-gradient-to-r from-purple-600 to-fuchsia-600 p-3 rounded-xl">
                        <i data-lucide="calendar-clock" class="w-6 h-6 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-800">Reschedule Request</h3>
                        <span class="status-badge bg-purple-100 text-purple-800 mt-1">Pending</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white/80 p-4 rounded-xl border-2 border-purple-200">
                    <span class="text-sm text-purple-700 font-bold block mb-2 uppercase tracking-wider">Requested Date</span>
                    <div class="flex items-center gap-3">
                        <div class="bg-purple-100 p-2 rounded-lg">
                            <i data-lucide="calendar" class="w-5 h-5 text-purple-600"></i>
                        </div>
                        <span class="text-lg font-bold text-slate-800"><?php echo date('M j, Y g:i A', strtotime($case['rescheduleDate'])); ?></span>
                    </div>
                </div>

                <?php if (!empty($case['rescheduleComment'])): ?>
                <div class="bg-white/80 p-4 rounded-xl border-2 border-purple-200">
                    <span class="text-sm text-purple-700 font-bold block mb-2 uppercase tracking-wider">Customer Comment</span>
                    <p class="text-sm text-slate-700 leading-relaxed"><?php echo htmlspecialchars($case['rescheduleComment']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex gap-4">
                <button onclick="acceptReschedule()" class="action-button flex-1 flex items-center justify-center gap-2">
                    <i data-lucide="check" class="w-5 h-5"></i>
                    Accept Request
                </button>
                <button onclick="declineReschedule()" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2">
                    <i data-lucide="x" class="w-5 h-5"></i>
                    Decline Request
                </button>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Floating Action Buttons -->
    <div class="fixed bottom-6 right-6 flex flex-col gap-3 z-50 no-print">
        <button onclick="saveChanges()" class="action-button p-4 rounded-full flex items-center justify-center gap-2 shadow-2xl">
            <i data-lucide="save" class="w-6 h-6"></i>
        </button>
        <button onclick="deleteCase()" class="bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white p-4 rounded-full font-bold shadow-2xl hover:shadow-3xl transition-all duration-300 flex items-center justify-center gap-2">
            <i data-lucide="trash-2" class="w-6 h-6"></i>
        </button>
    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div id="review-edit" class="p-3 space-y-4 hidden">
                                <div class="space-y-2">
                                    <label class="block text-sm text-amber-700 font-bold uppercase tracking-wider">Rating</label>
                                    <select id="input-review-stars" class="w-full bg-slate-50 border-2 border-amber-200 rounded-xl p-4 text-lg font-bold focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/20 outline-none">
                                        <option value="">No rating</option>
                                        <option value="1" <?php echo $case['reviewStars'] == 1 ? 'selected' : ''; ?>>⭐ 1 Star</option>
                                        <option value="2" <?php echo $case['reviewStars'] == 2 ? 'selected' : ''; ?>>⭐⭐ 2 Stars</option>
                                        <option value="3" <?php echo $case['reviewStars'] == 3 ? 'selected' : ''; ?>>⭐⭐⭐ 3 Stars</option>
                                        <option value="4" <?php echo $case['reviewStars'] == 4 ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4 Stars</option>
                                        <option value="5" <?php echo $case['reviewStars'] == 5 ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 Stars</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm text-amber-700 font-bold uppercase tracking-wider">Comment</label>
                                    <textarea id="input-review-comment" rows="4" placeholder="Customer feedback..." class="w-full bg-slate-50 border-2 border-amber-200 rounded-xl p-4 text-sm focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/20 outline-none resize-none"><?php echo htmlspecialchars($case['reviewComment'] ?? ''); ?></textarea>
                                </div>
                                <div class="flex gap-3">
                                    <button id="btn-save-review" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl active:scale-95">
                                        <i data-lucide="save" class="w-5 h-5 inline mr-2"></i>
                                        Save Review
                                    </button>
                                    <button id="btn-cancel-review" class="px-6 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl transition-all">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Reschedule Request Preview -->
                        <?php if ($case['user_response'] === 'Reschedule Requested' && !empty($case['rescheduleDate'])): ?>
                        <div class="bg-white border border-purple-200 rounded shadow-sm text-sm lg:col-span-2">
                            <div class="px-3 py-2 bg-gradient-to-r from-purple-600 to-fuchsia-600 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="bg-white/20 p-2 rounded-lg">
                                        <i data-lucide="calendar-clock" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <label class="text-sm font-bold text-white uppercase tracking-wider">Reschedule Request</label>
                                </div>
                                <span class="text-xs bg-white/20 backdrop-blur-sm text-white px-3 py-1 rounded-full font-bold border border-white/30">Pending</span>
                            </div>
                            <div class="p-4 space-y-3">
                                <div class="bg-white/80 p-3 rounded-lg border-2 border-purple-200">
                                    <span class="text-xs text-purple-700 font-bold block mb-2 uppercase tracking-wider">Requested Date</span>
                                    <div class="flex items-center gap-2">
                                        <div class="bg-purple-100 p-2 rounded-lg">
                                            <i data-lucide="calendar" class="w-5 h-5 text-purple-600"></i>
                                        </div>
                                        <span class="text-lg font-bold text-slate-800"><?php echo date('M j, Y g:i A', strtotime($case['rescheduleDate'])); ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($case['rescheduleComment'])): ?>
                                <div class="bg-white/80 p-4 rounded-xl border-2 border-purple-200">
                                    <span class="text-xs text-purple-700 font-bold block mb-2 uppercase tracking-wider">Customer Comment</span>
                                    <p class="text-sm text-slate-700 leading-relaxed"><?php echo htmlspecialchars($case['rescheduleComment']); ?></p>
                                </div>
                                <?php endif; ?>
                                <div class="flex gap-3 pt-2">
                                    <button onclick="acceptReschedule()" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-lg font-bold text-sm transition-all active:scale-95 shadow-lg">
                                        <i data-lucide="check" class="w-4 h-4 inline mr-2"></i>Accept Request
                                    </button>
                                    <button onclick="declineReschedule()" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-lg font-bold text-sm transition-all active:scale-95 shadow-lg">
                                        <i data-lucide="x" class="w-4 h-4 inline mr-2"></i>Decline Request
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- Actions Tab -->
                <div id="tab-content-actions" class="tab-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <!-- Case Actions -->
                        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                        <i data-lucide="settings" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-white uppercase tracking-wider">Case Actions</h3>
                                </div>
                            </div>
                            <div class="p-3 space-y-4">
                                <button onclick="saveChanges()" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-3">
                                    <i data-lucide="save" class="w-6 h-6"></i>
                                    <span>Save All Changes</span>
                                </button>
                                <button onclick="printCase()" class="w-full bg-slate-600 hover:bg-slate-700 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-3">
                                    <i data-lucide="printer" class="w-6 h-6"></i>
                                    <span>Print Case Details</span>
                                </button>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="bg-white rounded-xl shadow-lg shadow-red-200/60 border border-red-200/80 overflow-hidden">
                            <div class="bg-gradient-to-r from-red-600 to-red-700 px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                        <i data-lucide="alert-triangle" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-white uppercase tracking-wider">Danger Zone</h3>
                                </div>
                            </div>
                            <div class="p-3 space-y-4">
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <h4 class="text-sm font-bold text-red-800 mb-2">⚠️ Irreversible Actions</h4>
                                    <p class="text-sm text-red-700 mb-4">These actions cannot be undone. Please proceed with caution.</p>
                                </div>
                                <button onclick="deleteCase()" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-3">
                                    <i data-lucide="trash-2" class="w-6 h-6"></i>
                                    <span>Delete This Case</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Floating Action Buttons -->
    <div class="fixed bottom-6 right-6 flex flex-col gap-3 z-50">
        <button onclick="saveChanges()" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white p-4 rounded-full font-bold shadow-xl hover:shadow-2xl transition-all duration-300 flex items-center justify-center gap-2 active:scale-95 group">
            <i data-lucide="save" class="w-6 h-6 group-hover:scale-110 transition-transform"></i>
            <span class="hidden lg:inline ml-2">Save Changes</span>
        </button>
        <button onclick="deleteCase()" class="bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white p-4 rounded-full font-bold shadow-xl hover:shadow-2xl transition-all duration-300 flex items-center justify-center gap-2 active:scale-95 group">
            <i data-lucide="trash-2" class="w-6 h-6 group-hover:scale-110 transition-transform"></i>
            <span class="hidden lg:inline ml-2">Delete</span>
        </button>
    </div>

    <!-- JavaScript -->
    <script>
        const API_URL = 'api.php';
        const CASE_ID = <?php echo $case_id; ?>;
        const CAN_EDIT = <?php echo $CAN_EDIT ? 'true' : 'false'; ?>;
        const MANAGER_PHONE = "<?php echo MANAGER_PHONE; ?>";

        // SMS Templates and workflow bindings
        let smsTemplates, smsWorkflowBindings;
        try {
            smsTemplates = <?php echo json_encode($smsTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
            smsWorkflowBindings = <?php echo json_encode($smsWorkflowBindings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
        } catch (e) {
            console.error('Error parsing SMS templates:', e);
            smsTemplates = {};
            smsWorkflowBindings = {};
        }

        // Current case data
        let currentCase;
        try {
            currentCase = <?php echo json_encode($case, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: 'null'; ?>;
            if (!currentCase) {
                currentCase = {};
            }
        } catch (e) {
            console.error('Error parsing case data:', e);
            currentCase = {};
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getFormattedMessage(type, data) {
            let template = smsTemplates[type]?.content || '';
            template = template.replace(/{name}/g, data.name || '');
            template = template.replace(/{plate}/g, data.plate || '');
            template = template.replace(/{amount}/g, data.amount || '');
            template = template.replace(/{date}/g, data.date || '');
            template = template.replace(/{link}/g, data.link || '');
            return template;
        }

        function showToast(title, message = '', type = 'success', duration = 4000) {
            const container = document.getElementById('toast-container');
            if (!container) return;

            // Handle legacy calls
            if (typeof type === 'number') { duration = type; type = 'success'; } // fallback
            if (!message && !type) { type = 'success'; }
            else if (['success', 'error', 'info', 'urgent'].includes(message)) { type = message; message = ''; }

            // Create toast
            const toast = document.createElement('div');

            const colors = {
                success: {
                    bg: 'bg-white/95 backdrop-blur-xl',
                    border: 'border-emerald-200/60',
                    iconBg: 'bg-gradient-to-br from-emerald-50 to-teal-50',
                    iconColor: 'text-emerald-600',
                    icon: 'check-circle',
                    shadow: 'shadow-emerald-500/20'
                },
                error: {
                    bg: 'bg-white/95 backdrop-blur-xl',
                    border: 'border-red-200/60',
                    iconBg: 'bg-gradient-to-br from-red-50 to-orange-50',
                    iconColor: 'text-red-600',
                    icon: 'alert-circle',
                    shadow: 'shadow-red-500/20'
                },
                info: {
                    bg: 'bg-white/95 backdrop-blur-xl',
                    border: 'border-blue-200/60',
                    iconBg: 'bg-gradient-to-br from-blue-50 to-indigo-50',
                    iconColor: 'text-blue-600',
                    icon: 'info',
                    shadow: 'shadow-blue-500/20'
                },
                urgent: {
                    bg: 'bg-white/95 backdrop-blur-xl toast-urgent',
                    border: 'border-purple-300',
                    iconBg: 'bg-gradient-to-br from-purple-100 to-pink-100',
                    iconColor: 'text-purple-700',
                    icon: 'bell',
                    shadow: 'shadow-purple-500/30'
                }
            };

            const style = colors[type] || colors.info;

            toast.className = `pointer-events-auto w-80 ${style.bg} border-2 ${style.border} shadow-2xl ${style.shadow} rounded-2xl p-4 flex items-start gap-3 transform transition-all duration-500 translate-y-10 opacity-0`;

            toast.innerHTML = `
                <div class="${style.iconBg} p-3 rounded-xl shrink-0 shadow-inner">
                    <i data-lucide="${style.icon}" class="w-5 h-5 ${style.iconColor}"></i>
                </div>
                <div class="flex-1 pt-1">
                    <h4 class="text-sm font-bold text-slate-900 leading-none mb-1.5">${title}</h4>
                    ${message ? `<p class="text-xs text-slate-600 leading-relaxed font-medium">${message}</p>` : ''}
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-slate-600 transition-colors -mt-1 -mr-1 p-1.5 hover:bg-slate-100 rounded-lg">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            `;

            container.appendChild(toast);
            initializeIcons();

            // Animate In
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-10', 'opacity-0');
            });

            // Auto Dismiss (unless persistent/urgent)
            if (duration > 0 && type !== 'urgent') {
                setTimeout(() => {
                    toast.classList.add('translate-y-4', 'opacity-0');
                    setTimeout(() => toast.remove(), 500);
                }, duration);
            }
        }

        // Initialize Lucide icons with retry
        function initializeIcons() {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                try {
                    window.lucide.createIcons();
                } catch (e) {
                    console.warn('Lucide icon initialization failed:', e);
                    // Retry after a short delay
                    setTimeout(initializeIcons, 500);
                }
            } else {
                // Retry after a short delay if Lucide hasn't loaded yet
                setTimeout(initializeIcons, 100);
            }
        }

        // API call helper
        async function fetchAPI(endpoint, method = 'GET', data = null) {
            const config = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                }
            };

            if (data) {
                config.body = JSON.stringify(data);
            }

            const response = await fetch(`${API_URL}?action=${endpoint}`, config);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.json();
        }

        // Send SMS
        async function sendSMS(phone, text, type) {
            if (!phone) return showToast("No phone number", "error");
            const clean = phone.replace(/\D/g, '');
            try {
                const result = await fetchAPI('send_sms', 'POST', { to: clean, text: text });

                // Log SMS in activity
                const newLog = {
                    message: `SMS Sent (${type})`,
                    timestamp: new Date().toISOString(),
                    type: 'sms'
                };
                const logs = [...(currentCase.systemLogs || []), newLog];
                await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', { systemLogs: logs });
                currentCase.systemLogs = logs;

                updateActivityLog();
                showToast("SMS Sent", "success");

            } catch (e) {
                console.error(e);
                showToast("SMS Failed", "error");
            }
        }

        // Accept reschedule
        async function acceptReschedule() {
            if (!confirm('Accept reschedule request and update appointment?')) return;

            try {
                const rescheduleDateTime = currentCase.rescheduleDate.replace(' ', 'T');
                await fetchAPI(`accept_reschedule&id=${CASE_ID}`, 'POST', {
                    service_date: rescheduleDateTime
                });

                currentCase.serviceDate = rescheduleDateTime;
                currentCase.userResponse = 'Confirmed';
                currentCase.rescheduleDate = null;
                currentCase.rescheduleComment = null;

                const serviceDateInput = document.getElementById('input-service-date');
                if (serviceDateInput) serviceDateInput.value = rescheduleDateTime;
                showToast("Reschedule Accepted", "Appointment updated and SMS sent to customer", "success");

                // Reload page to hide reschedule section
                setTimeout(() => window.location.reload(), 1000);

            } catch (e) {
                console.error('Accept reschedule error:', e);
                showToast("Error", "Failed to accept reschedule request", "error");
            }
        }

        // Decline reschedule
        async function declineReschedule() {
            if (!confirm('Decline this reschedule request? The customer will need to be contacted manually.')) return;

            try {
                await fetchAPI(`decline_reschedule&id=${CASE_ID}`, 'POST', {});

                currentCase.rescheduleDate = null;
                currentCase.rescheduleComment = null;
                currentCase.userResponse = 'Pending';

                showToast("Request Declined", "Reschedule request removed", "info");

                // Reload page to hide reschedule section
                setTimeout(() => window.location.reload(), 1000);

            } catch (e) {
                console.error('Decline reschedule error:', e);
                showToast("Error", "Failed to decline request", "error");
            }
        }

        // Add note
        async function addNote() {
            const newNoteInputEl = document.getElementById('new-note-input');
            const text = newNoteInputEl ? newNoteInputEl.value.trim() : '';
            if (!text) return;

            const newNote = {
                text,
                authorName: '<?php echo addslashes($current_user_name); ?>',
                timestamp: new Date().toISOString()
            };

            try {
                const notes = [...(currentCase.internalNotes || []), newNote];
                await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', { internalNotes: notes });
                currentCase.internalNotes = notes;

                // Update notes display
                updateNotesDisplay();

                if (newNoteInputEl) newNoteInputEl.value = '';
                showToast("Note Added", "Internal note has been added", "success");

            } catch (error) {
                console.error('Add note error:', error);
                showToast("Error", "Failed to add note", "error");
            }
        }

        // Delete case
        async function deleteCase() {
            if (!confirm("Delete this case permanently?")) return;

            try {
                const result = await fetchAPI(`delete_transfer&id=${CASE_ID}`, 'POST');
                if (result.status === 'deleted') {
                    showToast("Case Deleted", "The case has been permanently removed", "success");
                    setTimeout(() => window.location.href = 'index.php', 1000);
                } else {
                    showToast(result.message || "Failed to delete case", "error");
                }
            } catch (error) {
                console.error('Delete error:', error);
                showToast("Failed to delete case", "error");
            }
        }

        // Update activity log display
        function updateActivityLog() {
            const activityLog = document.getElementById('activity-log-container');
            if (!currentCase.systemLogs || currentCase.systemLogs.length === 0) {
                activityLog.innerHTML = '<div class="text-sm text-slate-500 italic">No activity recorded</div>';
                return;
            }

            const logHTML = currentCase.systemLogs.slice().reverse().map(log => {
                const date = new Date(log.timestamp).toLocaleDateString('en-US');
                const time = new Date(log.timestamp).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                return `
                    <div class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <div class="bg-slate-200 rounded-full p-1 mt-0.5">
                            <i data-lucide="activity" class="w-3 h-3 text-slate-600"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-xs text-slate-500 mb-1">${date} at ${time}</div>
                            <div class="text-sm text-slate-700">${escapeHtml(log.message)}</div>
                        </div>
                    </div>
                `;
            }).join('');
            activityLog.innerHTML = logHTML;
            initializeIcons();
        }

        // Update notes display
        function updateNotesDisplay() {
            const notesContainer = document.getElementById('notes-container');
            if (!currentCase.internalNotes || currentCase.internalNotes.length === 0) {
                notesContainer.innerHTML = '<div class="text-sm text-slate-500 italic text-center py-4">No internal notes yet</div>';
                return;
            }

            const notesHTML = currentCase.internalNotes.map(note => {
                const date = new Date(note.timestamp).toLocaleDateString('en-US');
                const time = new Date(note.timestamp).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                return `
                    <div class="bg-white p-3 rounded-lg border border-yellow-100 shadow-sm">
                        <p class="text-sm text-slate-700">${escapeHtml(note.text)}</p>
                        <div class="flex justify-end mt-2">
                            <span class="text-xs text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">${escapeHtml(note.authorName || 'Manager')} - ${date} ${time}</span>
                        </div>
                    </div>
                `;
            }).join('');
            notesContainer.innerHTML = notesHTML;
            initializeIcons();
        }

        // Print case
        function printCase() {
            window.print();
        }

        // Save changes
        async function saveChanges() {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to edit cases', 'error');
                return;
            }

            const nameEl = document.getElementById('input-name');
            const plateEl = document.getElementById('input-plate');
            const amountEl = document.getElementById('input-amount');
            const statusEl = document.getElementById('input-status');
            const phoneEl = document.getElementById('input-phone');
            const serviceDateEl = document.getElementById('input-service-date');
            const franchiseEl = document.getElementById('input-franchise');

            const name = nameEl ? nameEl.value.trim() : currentCase.name;
            const plate = plateEl ? plateEl.value.trim() : currentCase.plate;
            const amount = amountEl ? amountEl.value.trim() : currentCase.amount;
            const status = statusEl ? statusEl.value : currentCase.status;
            const phone = phoneEl ? phoneEl.value : currentCase.phone;
            const serviceDate = serviceDateEl ? serviceDateEl.value : currentCase.serviceDate;
            const franchise = franchiseEl ? franchiseEl.value : currentCase.franchise;

            // Validation: Parts Arrived and Scheduled require a date
            if ((status === 'Parts Arrived' || status === 'Scheduled') && !serviceDate) {
                showToast("Scheduling Required", `Please select a service date to save '${status}' status.`, "error");
                return;
            }

            const updates = {
                name,
                plate,
                amount,
                status,
                phone,
                serviceDate: serviceDate || null,
                franchise: franchise || 0,
                internalNotes: currentCase.internalNotes || [],
                systemLogs: currentCase.systemLogs || []
            };

            // AUTO-RESCHEDULE LOGIC
            const currentDateStr = currentCase.serviceDate ? currentCase.serviceDate.replace(' ', 'T').slice(0, 16) : '';
            if (currentCase.user_response === 'Reschedule Requested' && serviceDate && serviceDate !== currentDateStr) {
                updates.user_response = 'Pending';
                updates.systemLogs.push({
                    message: `Rescheduled to ${serviceDate.replace('T', ' ')}`,
                    timestamp: new Date().toISOString(),
                    type: 'info'
                });
                const templateData = {
                    id: currentCase.id,
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount,
                    serviceDate: serviceDate
                };
                const msg = getFormattedMessage('rescheduled', templateData);
                sendSMS(phone, msg, 'rescheduled');
            }

            // Status change SMS logic
            if (status !== currentCase.status) {
                updates.systemLogs.push({
                    message: `Status: ${currentCase.status} -> ${status}`,
                    timestamp: new Date().toISOString(),
                    type: 'status'
                });

                if (phone && smsWorkflowBindings && smsWorkflowBindings[status]) {
                    const publicUrl = window.location.origin + window.location.pathname.replace('edit_case.php', 'public_view.php');
                    const templateData = {
                        id: currentCase.id,
                        name: currentCase.name,
                        plate: currentCase.plate,
                        amount: currentCase.amount,
                        serviceDate: serviceDate || currentCase.serviceDate,
                        link: `${publicUrl}?id=${CASE_ID}`
                    };

                    smsWorkflowBindings[status].forEach(template => {
                        const msg = getFormattedMessage(template.slug, templateData);
                        sendSMS(phone, msg, `${template.slug}_sms`);
                    });
                }

                // Special handling for Processing status - auto-assign schedule
                if (status === 'Processing') {
                    let assignedDate = serviceDate || currentCase.serviceDate;
                    if (!assignedDate) {
                        const today = new Date();
                        const nextDay = new Date(today);
                        nextDay.setDate(today.getDate() + 1);
                        // Skip weekends
                        if (nextDay.getDay() === 0) nextDay.setDate(nextDay.getDate() + 1);
                        if (nextDay.getDay() === 6) nextDay.setDate(nextDay.getDate() + 2);
                        nextDay.setHours(10, 0, 0, 0);
                        assignedDate = nextDay.toISOString().slice(0, 16);
                        updates.serviceDate = assignedDate;
                        updates.systemLogs.push({
                            message: `Auto-assigned service date: ${assignedDate.replace('T', ' ')}`,
                            timestamp: new Date().toISOString(),
                            type: 'info'
                        });
                    }
                }
            }

            try {
                await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', updates);

                // Update local case data
                Object.assign(currentCase, updates);

                showToast("Changes Saved", "Case has been updated successfully", "success");

                // Refresh activity log
                updateActivityLog();

            } catch (error) {
                console.error('Save error:', error);
                showToast("Error", "Failed to save changes", "error");
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize icons when DOM is ready
            initializeIcons();

            // Also try to initialize when window loads (backup)
            window.addEventListener('load', function() {
                setTimeout(initializeIcons, 100);
            });

            // Enter key for notes
            const noteInputEl = document.getElementById('new-note-input');
            if (noteInputEl) noteInputEl.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') addNote();
            });

            // SMS button handlers
            const smsRegisterBtn = document.getElementById('btn-sms-register');
            if (smsRegisterBtn) smsRegisterBtn.addEventListener('click', () => {
                const phone = document.getElementById('input-phone')?.value;
                const publicUrl = window.location.origin + window.location.pathname.replace('edit_case.php', 'public_view.php');
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount,
                    link: `${publicUrl}?id=${CASE_ID}`
                };
                const msg = getFormattedMessage('registered', templateData);
                sendSMS(phone, msg, 'welcome');
            });

            const smsArrivedBtn = document.getElementById('btn-sms-arrived');
            if (smsArrivedBtn) smsArrivedBtn.addEventListener('click', () => {
                const phone = document.getElementById('input-phone')?.value;
                const publicUrl = window.location.origin + window.location.pathname.replace('edit_case.php', 'public_view.php');
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount,
                    link: `${publicUrl}?id=${CASE_ID}`
                };
                const msg = getFormattedMessage('parts_arrived', templateData);
                sendSMS(phone, msg, 'parts_arrived');
            });

            const smsScheduleBtn = document.getElementById('btn-sms-schedule');
            if (smsScheduleBtn) smsScheduleBtn.addEventListener('click', () => {
                const phone = document.getElementById('input-phone')?.value;
                const serviceDate = document.getElementById('input-service-date')?.value;
                if (!serviceDate) {
                    showToast('No Service Date', 'Please set a service date first', 'error');
                    return;
                }
                const date = new Date(serviceDate).toLocaleString('ka-GE', {
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                const publicUrl = window.location.origin + window.location.pathname.replace('edit_case.php', 'public_view.php');
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount,
                    date: date,
                    link: `${publicUrl}?id=${CASE_ID}`
                };
                const msg = getFormattedMessage('schedule', templateData);
                sendSMS(phone, msg, 'schedule');
            });

            const smsCalledBtn = document.getElementById('btn-sms-called');
            if (smsCalledBtn) smsCalledBtn.addEventListener('click', () => {
                const phone = document.getElementById('input-phone')?.value;
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount
                };
                const msg = getFormattedMessage('called', templateData);
                sendSMS(phone, msg, 'called');
            });

            const smsCompletedBtn = document.getElementById('btn-sms-completed');
            if (smsCompletedBtn) smsCompletedBtn.addEventListener('click', () => {
                const phone = document.getElementById('input-phone')?.value;
                const publicUrl = window.location.origin + window.location.pathname.replace('edit_case.php', 'public_view.php');
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount,
                    link: `${publicUrl}?id=${CASE_ID}`
                };
                const msg = getFormattedMessage('completed', templateData);
                sendSMS(phone, msg, 'completed');
            });

            // SMS template selector
            const smsSelector = document.getElementById('sms-template-selector');
            if (smsSelector) smsSelector.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const templateSlug = this.value;
                const sendButton = document.getElementById('btn-send-custom-sms');
                const previewDiv = document.getElementById('sms-preview');

                if (!templateSlug) {
                    previewDiv.innerHTML = '<span class="text-slate-400 italic">Select a template to see preview...</span>';
                    sendButton.disabled = true;
                    return;
                }

                // Get template data and format message
                const template = smsTemplates[templateSlug];
                if (template) {
                    const publicUrl = window.location.origin + window.location.pathname.replace('edit_case.php', 'public_view.php');
                    const templateData = {
                        id: CASE_ID,
                        name: (document.getElementById('input-name')?.value || currentCase.name),
                        plate: (document.getElementById('input-plate')?.value || currentCase.plate),
                        amount: (document.getElementById('input-amount')?.value || currentCase.amount),
                        serviceDate: (document.getElementById('input-service-date')?.value || currentCase.serviceDate),
                        date: (document.getElementById('input-service-date')?.value || currentCase.serviceDate),
                        link: `${publicUrl}?id=${CASE_ID}`
                    };

                    const formattedMessage = getFormattedMessage(templateSlug, templateData);
                    previewDiv.textContent = formattedMessage;
                    sendButton.disabled = false;
                }
            });

            // Send Custom SMS Button
            const sendSmsBtn = document.getElementById('btn-send-custom-sms');
            if (sendSmsBtn) sendSmsBtn.addEventListener('click', () => {
                const templateSelector = document.getElementById('sms-template-selector');
                const templateSlug = templateSelector?.value;
                const phone = document.getElementById('input-phone')?.value;

                if (!templateSlug) {
                    showToast('No Template Selected', 'Please select an SMS template first', 'error');
                    return;
                }

                const templateData = {
                    id: CASE_ID,
                    name: (document.getElementById('input-name')?.value || currentCase.name),
                    plate: (document.getElementById('input-plate')?.value || currentCase.plate),
                    amount: (document.getElementById('input-amount')?.value || currentCase.amount),
                    serviceDate: (document.getElementById('input-service-date')?.value || currentCase.serviceDate),
                    date: (document.getElementById('input-service-date')?.value || currentCase.serviceDate),
                    link: `${window.location.origin + window.location.pathname.replace('edit_case.php', 'public_view.php')}?id=${CASE_ID}`
                };

                const msg = getFormattedMessage(templateSlug, templateData);
                sendSMS(phone, msg, `custom_${templateSlug}`);
            });

            // Review Editing
            const editReviewBtn = document.getElementById('btn-edit-review');
            if (editReviewBtn) editReviewBtn.addEventListener('click', () => {
                const reviewDisplay = document.getElementById('review-display');
                const reviewEdit = document.getElementById('review-edit');
                if (reviewDisplay) reviewDisplay.classList.add('hidden');
                if (reviewEdit) reviewEdit.classList.remove('hidden');
            });

            const cancelReviewBtn = document.getElementById('btn-cancel-review');
            if (cancelReviewBtn) cancelReviewBtn.addEventListener('click', () => {
                const reviewEdit = document.getElementById('review-edit');
                const reviewDisplay = document.getElementById('review-display');
                if (reviewEdit) reviewEdit.classList.add('hidden');
                if (reviewDisplay) reviewDisplay.classList.remove('hidden');
            });

            const saveReviewBtn = document.getElementById('btn-save-review');
            if (saveReviewBtn) saveReviewBtn.addEventListener('click', async () => {
                const stars = document.getElementById('input-review-stars')?.value;
                const comment = document.getElementById('input-review-comment')?.value?.trim();

                try {
                    await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', {
                        reviewStars: stars || null,
                        reviewComment: comment || null
                    });

                    // Update local case data
                    currentCase.reviewStars = stars || null;
                    currentCase.reviewComment = comment || null;

                    showToast("Review Updated", "Customer review has been saved successfully", "success");

                    // Refresh the page to show updated review
                    setTimeout(() => window.location.reload(), 1000);

                } catch (error) {
                    console.error('Save review error:', error);
                    showToast("Error", "Failed to save review", "error");
                }
            });

            // Initialize
            initializeIcons();

            // Tab switching functionality - REMOVED (no longer using tabs)

            // Enter key for notes
            const noteInputEl = document.getElementById('new-note-input');
            if (noteInputEl) noteInputEl.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') addNote();
            });
        });
    </script>
</body>
</html>