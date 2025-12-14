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
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            border-radius: 1px;
        }

        /* Enhanced animations and effects */
        .tab-content {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Enhanced focus states */
        .focus-enhanced:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            border-color: #3b82f6;
        }

        /* Status badge animations */
        .status-badge {
            transition: all 0.2s ease;
        }

        /* Button hover effects */
        .btn-hover-lift:hover {
            transform: translateY(-1px);
        }

        /* Card hover effects */
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Print styles */
        @media print {
            .no-print { display: none !important; }
            .tab-navigation { display: none !important; }
            .floating-actions { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-3 pointer-events-none"></div>

    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Back Button -->
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-800 transition-colors">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                <span class="font-medium">Back to Dashboard</span>
            </a>
        </div>

        <!-- Case Header -->
        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 mb-6 p-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center gap-4 flex-1 min-w-0">
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-3 rounded-xl shadow-lg shadow-blue-500/30">
                        <i data-lucide="car" class="w-6 h-6 text-white"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs text-slate-500 uppercase font-bold tracking-widest">Order #<?php echo $case_id; ?></div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xl font-bold text-slate-800 truncate"><?php echo htmlspecialchars($case['name']); ?></span>
                            <span class="text-slate-400">/</span>
                            <span class="font-mono text-lg text-blue-700 tracking-wider"><?php echo htmlspecialchars($case['plate']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Status Badge -->
                    <div class="hidden lg:flex items-center gap-2">
                        <span class="text-sm text-slate-500 font-medium">Status:</span>
                        <span id="header-status-badge" class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                            <?php
                            $statusColors = [
                                'New' => 'bg-blue-100 text-blue-800 border border-blue-200',
                                'Processing' => 'bg-yellow-100 text-yellow-800 border border-yellow-200',
                                'Called' => 'bg-purple-100 text-purple-800 border border-purple-200',
                                'Parts Ordered' => 'bg-orange-100 text-orange-800 border border-orange-200',
                                'Parts Arrived' => 'bg-green-100 text-green-800 border border-green-200',
                                'Scheduled' => 'bg-indigo-100 text-indigo-800 border border-indigo-200',
                                'Completed' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                                'Issue' => 'bg-red-100 text-red-800 border border-red-200'
                            ];
                            echo $statusColors[$case['status']] ?? 'bg-gray-100 text-gray-800 border border-gray-200';
                            ?>">
                            <?php echo htmlspecialchars($case['status']); ?>
                        </span>
                    </div>
                    <button onclick="window.printCase()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 p-3 rounded-xl transition-all hover:shadow-md" title="Print Case">
                        <i data-lucide="printer" class="w-5 h-5"></i>
                    </button>
                    <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 p-3 rounded-xl transition-all hover:shadow-md">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
            <!-- Tab Navigation -->
            <div class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200/80">
                <div class="flex px-2">
                    <button id="tab-overview" class="tab-button active flex-1 py-4 px-4 text-center font-bold text-slate-700 hover:bg-slate-200/50 transition-all duration-200 border-b-3 border-blue-500 rounded-t-lg relative group">
                        <div class="flex flex-col items-center gap-2">
                            <div class="bg-blue-100 group-hover:bg-blue-200 p-2 rounded-lg transition-colors">
                                <i data-lucide="eye" class="w-5 h-5 text-blue-600"></i>
                            </div>
                            <span class="text-sm font-semibold">Overview</span>
                        </div>
                        <div class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-0 h-0.5 bg-blue-500 transition-all duration-300 group-hover:w-full"></div>
                    </button>
                    <button id="tab-communication" class="tab-button flex-1 py-4 px-4 text-center font-bold text-slate-600 hover:bg-slate-200/50 transition-all duration-200 rounded-t-lg relative group">
                        <div class="flex flex-col items-center gap-2">
                            <div class="bg-slate-100 group-hover:bg-slate-200 p-2 rounded-lg transition-colors">
                                <i data-lucide="message-circle" class="w-5 h-5 text-slate-500"></i>
                            </div>
                            <span class="text-sm font-semibold">Communication</span>
                        </div>
                    </button>
                    <button id="tab-history" class="tab-button flex-1 py-4 px-4 text-center font-bold text-slate-600 hover:bg-slate-200/50 transition-all duration-200 rounded-t-lg relative group">
                        <div class="flex flex-col items-center gap-2">
                            <div class="bg-slate-100 group-hover:bg-slate-200 p-2 rounded-lg transition-colors">
                                <i data-lucide="history" class="w-5 h-5 text-slate-500"></i>
                            </div>
                            <span class="text-sm font-semibold">History & Notes</span>
                        </div>
                    </button>
                    <button id="tab-actions" class="tab-button flex-1 py-4 px-4 text-center font-bold text-slate-600 hover:bg-slate-200/50 transition-all duration-200 rounded-t-lg relative group">
                        <div class="flex flex-col items-center gap-2">
                            <div class="bg-slate-100 group-hover:bg-slate-200 p-2 rounded-lg transition-colors">
                                <i data-lucide="settings" class="w-5 h-5 text-slate-500"></i>
                            </div>
                            <span class="text-sm font-semibold">Actions</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="p-4">

                <!-- Overview Tab -->
                <div id="tab-content-overview" class="tab-content">
                    <!-- Priority Alert Section -->
                    <div class="mb-6">
                        <?php if ($case['user_response'] === 'Reschedule Requested'): ?>
                        <div class="bg-gradient-to-r from-purple-500 to-pink-500 rounded-xl p-4 text-white shadow-lg border border-purple-200">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                    <i data-lucide="calendar-clock" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold">Reschedule Request Pending</h3>
                                    <p class="text-purple-100 text-sm">Customer requested to change appointment</p>
                                </div>
                            </div>
                            <div class="bg-white/10 backdrop-blur-sm rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="clock" class="w-5 h-5 text-purple-200"></i>
                                        <span class="font-semibold"><?php echo date('M j, Y g:i A', strtotime($case['rescheduleDate'])); ?></span>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="acceptReschedule()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-all active:scale-95 shadow-lg">
                                            <i data-lucide="check" class="w-4 h-4 inline mr-1"></i>Accept
                                        </button>
                                        <button onclick="declineReschedule()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-all active:scale-95 shadow-lg">
                                            <i data-lucide="x" class="w-4 h-4 inline mr-1"></i>Decline
                                        </button>
                                    </div>
                                </div>
                                <?php if (!empty($case['rescheduleComment'])): ?>
                                <div class="mt-3 pt-3 border-t border-white/20">
                                    <p class="text-sm text-purple-100 italic"><?php echo htmlspecialchars($case['rescheduleComment']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php elseif ($case['status'] === 'Issue'): ?>
                        <div class="bg-gradient-to-r from-red-500 to-orange-500 rounded-xl p-4 text-white shadow-lg border border-red-200">
                            <div class="flex items-center gap-3">
                                <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold">Issue Status - Action Required</h3>
                                    <p class="text-red-100 text-sm">This case needs immediate attention</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Internal Notes - High Priority -->
                    <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden mb-6 card-hover">
                        <div class="bg-gradient-to-r from-slate-600 to-slate-700 px-4 py-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                        <i data-lucide="sticky-note" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-white uppercase tracking-wider">Internal Notes</h3>
                                </div>
                                <div class="text-xs text-slate-300 bg-white/10 px-2 py-1 rounded-full">
                                    <?php echo count($case['internalNotes'] ?? []); ?> notes
                                </div>
                            </div>
                        </div>
                        <div class="p-4">
                            <div id="notes-container" class="space-y-3 mb-4 max-h-64 overflow-y-auto custom-scrollbar">
                                <?php
                                if (!empty($case['internalNotes'])) {
                                    foreach ($case['internalNotes'] as $note) {
                                        $date = date('M j, g:i A', strtotime($note['timestamp']));
                                        echo "<div class='bg-slate-50 p-4 rounded-lg border border-slate-200 hover:bg-slate-100 transition-colors group'>";
                                        echo "<p class='text-sm text-slate-700 mb-2 leading-relaxed'>" . htmlspecialchars($note['text']) . "</p>";
                                        echo "<div class='flex justify-between items-center'>";
                                        echo "<span class='text-xs text-slate-400 bg-white px-3 py-1 rounded-full font-medium border border-slate-200'>" . htmlspecialchars($note['authorName'] ?? 'Manager') . "</span>";
                                        echo "<span class='text-xs text-slate-500 font-medium'>{$date}</span>";
                                        echo "</div>";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<div class='text-center py-8'>";
                                    echo "<i data-lucide='inbox' class='w-12 h-12 text-slate-300 mx-auto mb-3'></i>";
                                    echo "<p class='text-sm text-slate-500 font-medium'>No internal notes yet</p>";
                                    echo "<p class='text-xs text-slate-400 mt-1'>Add notes to track important information</p>";
                                    echo "</div>";
                                }
                                ?>
                            </div>
                            <div class="flex gap-3">
                                <input id="new-note-input" type="text" placeholder="Add a note..." class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:bg-white focus:border-slate-400 focus:ring-2 focus:ring-slate-200 outline-none transition-all">
                                <button onclick="addNote()" class="bg-slate-700 hover:bg-slate-800 text-white px-6 py-3 rounded-lg font-bold text-sm transition-all shadow-lg hover:shadow-xl active:scale-95">
                                    <i data-lucide="plus" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <!-- Left Column: Core Information -->
                        <div class="space-y-4">
                            <!-- Order Information Card -->
                            <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden card-hover">
                                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                            <i data-lucide="file-text" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-white uppercase tracking-wider">Order Details</h3>
                                    </div>
                                </div>
                                <div class="p-4 space-y-4">
                                    <div class="space-y-2">
                                        <label class="block text-xs text-blue-600 font-bold uppercase tracking-wider">Customer Name</label>
                                        <input id="input-name" type="text" value="<?php echo htmlspecialchars($case['name']); ?>" placeholder="Customer Name" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-lg font-semibold text-slate-800 focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20 outline-none transition-all">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-xs text-blue-600 font-bold uppercase tracking-wider">Vehicle Plate</label>
                                        <input id="input-plate" type="text" value="<?php echo htmlspecialchars($case['plate']); ?>" placeholder="Vehicle Plate" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-lg font-semibold text-slate-800 focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20 outline-none transition-all">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-xs text-blue-600 font-bold uppercase tracking-wider">Amount</label>
                                        <div class="flex items-center gap-3">
                                            <div class="bg-emerald-100 p-3 rounded-lg">
                                                <i data-lucide="coins" class="w-6 h-6 text-emerald-600"></i>
                                            </div>
                                            <input id="input-amount" type="text" value="<?php echo htmlspecialchars($case['amount']); ?>" placeholder="0.00" class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-2xl font-bold text-emerald-600 focus:bg-white focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all">
                                            <span class="text-2xl font-bold text-emerald-600">₾</span>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-xs text-blue-600 font-bold uppercase tracking-wider">Franchise</label>
                                        <input id="input-franchise" type="number" value="<?php echo htmlspecialchars($case['franchise'] ?? 0); ?>" placeholder="0.00" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-lg font-semibold text-orange-600 focus:bg-white focus:border-orange-400 focus:ring-2 focus:ring-orange-400/20 outline-none transition-all">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-xs text-blue-600 font-bold uppercase tracking-wider">Created At</label>
                                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                            <i data-lucide="clock" class="w-5 h-5 text-slate-400"></i>
                                            <span id="case-created-date" class="font-medium text-slate-700"><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Selection -->
                            <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden card-hover">
                                <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                            <i data-lucide="activity" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-white uppercase tracking-wider">Workflow Stage</h3>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <div class="relative">
                                        <select id="input-status" class="w-full appearance-none bg-slate-50 border-2 border-purple-200 text-slate-800 py-4 px-4 rounded-xl leading-tight focus:outline-none focus:bg-white focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 text-lg font-bold shadow-lg transition-all cursor-pointer hover:border-purple-300">
                                            <option value="New" <?php echo $case['status'] === 'New' ? 'selected' : ''; ?>>🔵 New Case</option>
                                            <option value="Processing" <?php echo $case['status'] === 'Processing' ? 'selected' : ''; ?>>🟡 Processing</option>
                                            <option value="Called" <?php echo $case['status'] === 'Called' ? 'selected' : ''; ?>>🟣 Contacted</option>
                                            <option value="Parts Ordered" <?php echo $case['status'] === 'Parts Ordered' ? 'selected' : ''; ?>>📦 Parts Ordered</option>
                                            <option value="Parts Arrived" <?php echo $case['status'] === 'Parts Arrived' ? 'selected' : ''; ?>>🏁 Parts Arrived</option>
                                            <option value="Scheduled" <?php echo $case['status'] === 'Scheduled' ? 'selected' : ''; ?>>🟠 Scheduled</option>
                                            <option value="Completed" <?php echo $case['status'] === 'Completed' ? 'selected' : ''; ?>>🟢 Completed</option>
                                            <option value="Issue" <?php echo $case['status'] === 'Issue' ? 'selected' : ''; ?>>🔴 Issue</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-purple-400">
                                            <i data-lucide="chevron-down" class="w-6 h-6"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <button onclick="saveChanges()" class="w-full bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2">
                                            <i data-lucide="save" class="w-5 h-5"></i>
                                            <span>Update Status</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Middle Column: Contact & Appointment -->
                        <div class="space-y-4">
                            <!-- Contact Information -->
                            <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden card-hover">
                                <div class="bg-gradient-to-r from-teal-600 to-teal-700 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                            <i data-lucide="phone" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-white uppercase tracking-wider">Contact Information</h3>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <div class="space-y-3">
                                        <div class="flex gap-3">
                                            <div class="relative flex-1">
                                                <i data-lucide="smartphone" class="absolute left-4 top-1/2 -translate-y-1/2 w-6 h-6 text-teal-500"></i>
                                                <input id="input-phone" type="text" value="<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" placeholder="Phone Number" class="w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-teal-200 rounded-xl text-lg font-semibold text-slate-800 focus:bg-white focus:ring-4 focus:ring-teal-500/20 focus:border-teal-400 outline-none shadow-sm transition-all">
                                            </div>
                                            <a id="btn-call-real" href="tel:<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" class="bg-teal-600 hover:bg-teal-700 text-white p-4 rounded-xl hover:scale-105 transition-all shadow-lg active:scale-95">
                                                <i data-lucide="phone-call" class="w-6 h-6"></i>
                                            </a>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <button onclick="sendQuickSMS('welcome')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 p-3 rounded-xl transition-all hover:shadow-md active:scale-95 flex flex-col items-center gap-1">
                                                <i data-lucide="message-square" class="w-5 h-5"></i>
                                                <span class="text-xs font-medium">Welcome</span>
                                            </button>
                                            <button onclick="sendQuickSMS('schedule')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 p-3 rounded-xl transition-all hover:shadow-md active:scale-95 flex flex-col items-center gap-1">
                                                <i data-lucide="calendar-check" class="w-5 h-5"></i>
                                                <span class="text-xs font-medium">Schedule</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Service Appointment -->
                            <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden card-hover">
                                <div class="bg-gradient-to-r from-orange-600 to-orange-700 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                            <i data-lucide="calendar-check" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-white uppercase tracking-wider">Service Appointment</h3>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <div class="space-y-3">
                                        <div class="relative">
                                            <i data-lucide="calendar" class="absolute left-4 top-1/2 -translate-y-1/2 w-6 h-6 text-orange-500"></i>
                                            <input id="input-service-date" type="datetime-local" value="<?php echo $case['serviceDate'] ? date('Y-m-d\TH:i', strtotime($case['serviceDate'])) : ''; ?>" class="w-full pl-12 pr-4 py-4 bg-slate-50 border-2 border-orange-200 rounded-xl text-lg font-semibold focus:bg-white focus:border-orange-400 focus:ring-4 focus:ring-orange-400/20 outline-none shadow-sm transition-all">
                                        </div>
                                        <?php if ($case['serviceDate']): ?>
                                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                                            <div class="flex items-center gap-2 text-orange-700">
                                                <i data-lucide="clock" class="w-4 h-4"></i>
                                                <span class="text-sm font-medium">Scheduled for: <?php echo date('M j, Y g:i A', strtotime($case['serviceDate'])); ?></span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <button onclick="sendQuickSMS('parts_arrived')" class="w-full bg-gradient-to-r from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2">
                                            <i data-lucide="package-check" class="w-5 h-5"></i>
                                            <span>Send Parts Arrived SMS</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Vehicle Info -->
                        <div class="space-y-3">
                            <!-- Vehicle Information -->
                            <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden card-hover">
                                <div class="bg-gradient-to-r from-slate-600 to-slate-700 px-3 py-2">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                            <i data-lucide="car" class="w-5 h-5 text-white"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-white uppercase tracking-wider">Vehicle Information</h3>
                                    </div>
                                </div>
                                <div class="p-3 space-y-4">
                                    <div class="space-y-2">
                                        <label class="block text-xs text-slate-600 font-bold uppercase tracking-wider">Owner Name</label>
                                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                            <i data-lucide="user" class="w-5 h-5 text-slate-400"></i>
                                            <span class="font-medium text-slate-700"><?php echo htmlspecialchars($case['vehicle_owner'] ?? 'Not specified'); ?></span>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-xs text-slate-600 font-bold uppercase tracking-wider">Model</label>
                                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                            <i data-lucide="car" class="w-5 h-5 text-slate-400"></i>
                                            <span class="font-medium text-slate-700"><?php echo htmlspecialchars($case['vehicle_model'] ?? 'Not specified'); ?></span>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="block text-xs text-slate-600 font-bold uppercase tracking-wider">License Plate</label>
                                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                            <i data-lucide="hash" class="w-5 h-5 text-slate-400"></i>
                                            <span class="font-mono font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($case['plate']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Communication Tab -->
                <div id="tab-content-communication" class="tab-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- Quick SMS Actions -->
                        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
                            <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                        <i data-lucide="message-circle" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-white uppercase tracking-wider">Quick SMS Actions</h3>
                                </div>
                            </div>
                            <div class="p-4 space-y-3">
                                <button id="btn-sms-register" class="group w-full flex justify-between items-center px-6 py-5 bg-gradient-to-r from-slate-50 to-slate-100 border-2 border-indigo-200 rounded-xl hover:border-indigo-400 hover:shadow-xl hover:scale-[1.02] transition-all text-left active:scale-95 hover:from-indigo-50 hover:to-indigo-100">
                                    <div>
                                        <div class="text-lg font-bold text-slate-800 group-hover:text-indigo-700">Send Welcome SMS</div>
                                        <div class="text-sm text-slate-500 mt-1">Registration confirmation</div>
                                    </div>
                                    <div class="bg-indigo-100 group-hover:bg-indigo-600 p-3 rounded-lg transition-colors">
                                        <i data-lucide="message-square" class="w-6 h-6 text-indigo-600 group-hover:text-white"></i>
                                    </div>
                                </button>
                                <button id="btn-sms-arrived" class="group w-full flex justify-between items-center px-6 py-5 bg-gradient-to-r from-slate-50 to-slate-100 border-2 border-teal-200 rounded-xl hover:border-teal-400 hover:shadow-xl hover:scale-[1.02] transition-all text-left active:scale-95 hover:from-teal-50 hover:to-teal-100">
                                    <div>
                                        <div class="text-lg font-bold text-slate-800 group-hover:text-teal-700">Parts Arrived SMS</div>
                                        <div class="text-sm text-slate-500 mt-1">Includes customer link</div>
                                    </div>
                                    <div class="bg-teal-100 group-hover:bg-teal-600 p-3 rounded-lg transition-colors">
                                        <i data-lucide="package-check" class="w-6 h-6 text-teal-600 group-hover:text-white"></i>
                                    </div>
                                </button>
                                <button id="btn-sms-schedule" class="group w-full flex justify-between items-center px-6 py-5 bg-gradient-to-r from-slate-50 to-slate-100 border-2 border-orange-200 rounded-xl hover:border-orange-400 hover:shadow-xl hover:scale-[1.02] transition-all text-left active:scale-95 hover:from-orange-50 hover:to-orange-100">
                                    <div>
                                        <div class="text-lg font-bold text-slate-800 group-hover:text-orange-700">Send Schedule SMS</div>
                                        <div class="text-sm text-slate-500 mt-1">Appointment reminder</div>
                                    </div>
                                    <div class="bg-orange-100 group-hover:bg-orange-600 p-3 rounded-lg transition-colors">
                                        <i data-lucide="calendar-check" class="w-6 h-6 text-orange-600 group-hover:text-white"></i>
                                    </div>
                                </button>
                                <button id="btn-sms-called" class="group w-full flex justify-between items-center px-6 py-5 bg-gradient-to-r from-slate-50 to-slate-100 border-2 border-purple-200 rounded-xl hover:border-purple-400 hover:shadow-xl hover:scale-[1.02] transition-all text-left active:scale-95 hover:from-purple-50 hover:to-purple-100">
                                    <div>
                                        <div class="text-lg font-bold text-slate-800 group-hover:text-purple-700">Send Called SMS</div>
                                        <div class="text-sm text-slate-500 mt-1">Contact confirmation</div>
                                    </div>
                                    <div class="bg-purple-100 group-hover:bg-purple-600 p-3 rounded-lg transition-colors">
                                        <i data-lucide="phone-call" class="w-6 h-6 text-purple-600 group-hover:text-white"></i>
                                    </div>
                                </button>
                                <button id="btn-sms-completed" class="group w-full flex justify-between items-center px-6 py-5 bg-gradient-to-r from-slate-50 to-slate-100 border-2 border-green-200 rounded-xl hover:border-green-400 hover:shadow-xl hover:scale-[1.02] transition-all text-left active:scale-95 hover:from-green-50 hover:to-green-100">
                                    <div>
                                        <div class="text-lg font-bold text-slate-800 group-hover:text-green-700">Send Completed SMS</div>
                                        <div class="text-sm text-slate-500 mt-1">Service completion & review</div>
                                    </div>
                                    <div class="bg-green-100 group-hover:bg-green-600 p-3 rounded-lg transition-colors">
                                        <i data-lucide="check-circle" class="w-6 h-6 text-green-600 group-hover:text-white"></i>
                                    </div>
                                </button>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
                            <div class="bg-gradient-to-r from-violet-600 to-violet-700 px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                        <i data-lucide="message-square" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-white uppercase tracking-wider">Advanced SMS</h3>
                                </div>
                            </div>
                            <div class="p-3 space-y-4">
                                <div class="space-y-2">
                                    <label class="block text-sm text-violet-600 font-bold uppercase tracking-wider">Select Template</label>
                                    <select id="sms-template-selector" class="w-full bg-slate-50 border-2 border-violet-200 rounded-xl p-4 text-lg font-medium focus:bg-white focus:border-violet-400 focus:ring-4 focus:ring-violet-400/20 outline-none shadow-sm transition-all">
                                        <option value="">Choose a template...</option>
                                        <?php foreach ($smsTemplates as $slug => $template): ?>
                                        <option value="<?php echo htmlspecialchars($slug); ?>" data-content="<?php echo htmlspecialchars($template['content']); ?>">
                                            <?php echo htmlspecialchars($template['name'] ?? ucfirst(str_replace('_', ' ', $slug))); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm text-violet-600 font-bold uppercase tracking-wider">Message Preview</label>
                                    <div id="sms-preview" class="bg-slate-50 border-2 border-violet-200 rounded-xl p-4 min-h-[100px] text-sm text-slate-700 whitespace-pre-wrap shadow-sm">
                                        <span class="text-slate-400 italic">Select a template to see preview...</span>
                                    </div>
                                </div>
                                <button id="btn-send-custom-sms" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">
                                    <i data-lucide="send" class="w-5 h-5 inline mr-2"></i>
                                    Send Custom SMS
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History & Notes Tab -->
                <div id="tab-content-history" class="tab-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <!-- Activity Timeline -->
                        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
                            <div class="bg-gradient-to-r from-slate-700 to-slate-600 px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="history" class="w-5 h-5 text-white"></i>
                                    <h3 class="text-lg font-bold text-white uppercase tracking-wider">Activity Timeline</h3>
                                </div>
                            </div>
                            <div id="activity-log-container" class="p-3 max-h-80 overflow-y-auto custom-scrollbar space-y-4">
                                <?php
                                if (!empty($case['systemLogs'])) {
                                    foreach (array_reverse($case['systemLogs']) as $log) {
                                        $date = date('M j, g:i A', strtotime($log['timestamp']));
                                        echo "<div class='flex items-start gap-4 p-4 bg-slate-50 rounded-lg border border-slate-200 hover:bg-slate-100 transition-colors'>";
                                        echo "<div class='bg-slate-200 rounded-full p-2 mt-0.5'>";
                                        echo "<i data-lucide='activity' class='w-4 h-4 text-slate-600'></i>";
                                        echo "</div>";
                                        echo "<div class='flex-1 min-w-0'>";
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

                        <!-- Customer Review Section -->
                        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
                            <div class="bg-gradient-to-r from-amber-500 to-yellow-500 px-3 py-2 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                        <i data-lucide="star" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-white uppercase tracking-wider">Customer Review</h3>
                                </div>
                                <button id="btn-edit-review" class="text-white/80 hover:text-white hover:bg-white/20 px-4 py-2 rounded-lg transition-all text-sm font-bold">
                                    <i data-lucide="edit" class="w-4 h-4 inline mr-1"></i>
                                    Edit
                                </button>
                            </div>
                            <div id="review-display" class="p-3 space-y-4">
                                <?php if (!empty($case['reviewStars'])): ?>
                                <div class="flex items-center gap-4">
                                    <div class="flex gap-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i data-lucide="star" class="w-6 h-6 <?php echo $i <= $case['reviewStars'] ? 'text-amber-400 fill-current' : 'text-slate-300'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-4xl font-black text-amber-600"><?php echo $case['reviewStars']; ?>/5</span>
                                </div>
                                <?php if (!empty($case['reviewComment'])): ?>
                                <div class="bg-amber-50 p-4 rounded-lg border-2 border-amber-200">
                                    <p class="text-sm text-slate-700 italic leading-relaxed"><?php echo htmlspecialchars($case['reviewComment']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="text-center py-8">
                                    <i data-lucide="star" class="w-16 h-16 text-amber-300 mx-auto mb-3"></i>
                                    <p class="text-lg text-slate-500 font-medium">No review yet</p>
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
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- Case Actions -->
                        <div class="bg-white rounded-xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                        <i data-lucide="settings" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-white uppercase tracking-wider">Case Actions</h3>
                                </div>
                            </div>
                            <div class="p-4 space-y-4">
                                <button onclick="saveChanges()" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-3">
                                    <i data-lucide="save" class="w-6 h-6"></i>
                                    <span>Save All Changes</span>
                                </button>
                                <button onclick="printCase()" class="w-full bg-gradient-to-r from-slate-600 to-slate-700 hover:from-slate-700 hover:to-slate-800 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-3">
                                    <i data-lucide="printer" class="w-6 h-6"></i>
                                    <span>Print Case Details</span>
                                </button>
                                <button onclick="exportCase()" class="w-full bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-3">
                                    <i data-lucide="download" class="w-6 h-6"></i>
                                    <span>Export Case Data</span>
                                </button>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="bg-white rounded-xl shadow-lg shadow-red-200/60 border border-red-200/80 overflow-hidden">
                            <div class="bg-gradient-to-r from-red-600 to-red-700 px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white/20 backdrop-blur-sm p-2 rounded-lg">
                                        <i data-lucide="alert-triangle" class="w-5 h-5 text-white"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-white uppercase tracking-wider">Danger Zone</h3>
                                </div>
                            </div>
                            <div class="p-4 space-y-4">
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <h4 class="text-sm font-bold text-red-800 mb-2 flex items-center gap-2">
                                        <i data-lucide="alert-circle" class="w-4 h-4"></i>
                                        Irreversible Actions
                                    </h4>
                                    <p class="text-sm text-red-700 mb-4">These actions cannot be undone. Please proceed with caution.</p>
                                </div>
                                <button onclick="archiveCase()" class="w-full bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-3">
                                    <i data-lucide="archive" class="w-6 h-6"></i>
                                    <span>Archive Case</span>
                                </button>
                                <button onclick="deleteCase()" class="w-full bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-3">
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
    <div class="fixed bottom-6 right-6 flex flex-col gap-3 z-50 no-print">
        <button onclick="saveChanges()" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white p-4 rounded-full font-bold shadow-xl hover:shadow-2xl transition-all duration-300 flex items-center justify-center gap-2 active:scale-95 group">
            <i data-lucide="save" class="w-6 h-6 group-hover:scale-110 transition-transform"></i>
            <span class="hidden lg:inline ml-2">Save Changes</span>
        </button>
        <button onclick="sendQuickSMS('welcome')" class="bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white p-4 rounded-full font-bold shadow-xl hover:shadow-2xl transition-all duration-300 flex items-center justify-center gap-2 active:scale-95 group">
            <i data-lucide="message-square" class="w-6 h-6 group-hover:scale-110 transition-transform"></i>
            <span class="hidden lg:inline ml-2">Send SMS</span>
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

        // Send quick SMS
        function sendQuickSMS(type) {
            const phone = document.getElementById('input-phone')?.value;
            if (!phone) {
                showToast('No Phone Number', 'Please enter a phone number first', 'error');
                return;
            }

            const templateData = {
                name: currentCase.name,
                plate: currentCase.plate,
                amount: currentCase.amount,
                serviceDate: document.getElementById('input-service-date')?.value || currentCase.serviceDate,
                date: document.getElementById('input-service-date')?.value || currentCase.serviceDate,
                link: `${window.location.origin + window.location.pathname.replace('edit_case.php', 'public_view.php')}?id=${CASE_ID}`
            };

            const msg = getFormattedMessage(type, templateData);
            sendSMS(phone, msg, type);
        }

        // Export case data
        function exportCase() {
            const data = {
                case: currentCase,
                exported_at: new Date().toISOString(),
                exported_by: '<?php echo addslashes($current_user_name); ?>'
            };

            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `case_${CASE_ID}_${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            showToast('Case Exported', 'Case data has been downloaded', 'success');
        }

        // Archive case
        async function archiveCase() {
            if (!confirm('Archive this case? It will be moved to archived status and hidden from active cases.')) return;

            try {
                await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', {
                    status: 'Archived',
                    systemLogs: [...(currentCase.systemLogs || []), {
                        message: 'Case archived',
                        timestamp: new Date().toISOString(),
                        type: 'info'
                    }]
                });

                showToast('Case Archived', 'The case has been moved to archive', 'success');
                setTimeout(() => window.location.href = 'index.php', 1000);

            } catch (error) {
                console.error('Archive error:', error);
                showToast('Archive Failed', 'Failed to archive case', 'error');
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

                // Update header status badge
                updateHeaderStatus(status);

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

            // Tab switching functionality
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all tabs
                    tabButtons.forEach(btn => {
                        btn.classList.remove('active');
                        btn.classList.remove('border-b-3', 'border-blue-500');
                        btn.classList.add('text-slate-600');
                        // Reset icon backgrounds
                        const iconBg = btn.querySelector('.bg-blue-100');
                        if (iconBg) {
                            iconBg.classList.remove('bg-blue-100');
                            iconBg.classList.add('bg-slate-100');
                        }
                    });

                    // Hide all tab contents
                    tabContents.forEach(content => {
                        content.classList.add('hidden');
                    });

                    // Add active class to clicked tab
                    button.classList.add('active');
                    button.classList.add('border-b-3', 'border-blue-500');
                    button.classList.remove('text-slate-600');
                    button.classList.add('text-slate-700');

                    // Update icon background for active tab
                    const iconBg = button.querySelector('.bg-slate-100');
                    if (iconBg) {
                        iconBg.classList.remove('bg-slate-100');
                        iconBg.classList.add('bg-blue-100');
                    }

                    // Show corresponding tab content
                    const tabId = button.id.replace('tab-', 'tab-content-');
                    const tabContent = document.getElementById(tabId);
                    if (tabContent) {
                        tabContent.classList.remove('hidden');
                        // Smooth scroll to top of tab content
                        tabContent.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        });
    </script>
</body>
</html>