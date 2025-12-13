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
    // SMS templates table might not exist yet
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
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-3 pointer-events-none"></div>

    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <div class="w-full min-h-screen px-4 py-8">
        <!-- Back Button and Case Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6 max-w-7xl mx-auto">
            <a href="index.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-blue-600 text-sm">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                Back to Dashboard
            </a>
            <div class="flex items-center gap-3 text-slate-700 text-base font-semibold">
                <span class="inline-flex items-center gap-2 bg-slate-100 px-3 py-1 rounded">
                    <i data-lucide="car" class="w-4 h-4 text-blue-500"></i>
                    <?php echo htmlspecialchars($case['plate']); ?>
                </span>
                <span class="inline-flex items-center gap-2 bg-slate-100 px-3 py-1 rounded">
                    <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                    <?php echo htmlspecialchars($case['name']); ?>
                </span>
                <span class="inline-flex items-center gap-2 bg-slate-100 px-3 py-1 rounded font-mono">
                    #<?php echo $case_id; ?>
                </span>
            </div>
        </div>

        <!-- Workflow Progress Bar -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Progress</span>
                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded">Stage <span id="workflow-stage-number">1</span> of 8</span>
            </div>
            <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                <div id="workflow-progress-bar" class="h-full bg-blue-500 rounded-full transition-all duration-500" style="width: 12.5%"></div>
            </div>
            <div class="flex justify-between mt-1 text-[11px] text-slate-400 font-medium">
                <span>New</span>
                <span>Processing</span>
                <span>Contacted</span>
                <span>Parts Ordered</span>
                <span>Parts Arrived</span>
                <span>Scheduled</span>
                <span>Completed</span>
                <span>Issue</span>
            </div>
        </div>

        <!-- Main Content: Structured Sections -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-7xl mx-auto">
            <!-- Left: Case Info, Workflow, Communication -->
            <div class="flex flex-col gap-8">
                <!-- Case Info Section -->
                <section class="flex flex-col gap-6">
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="file-text" class="w-4 h-4 text-blue-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Order Details</h3>
                        </div>
                        <div class="space-y-4">
                            <!-- ...existing code for order details fields... -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Customer Name</label>
                                <input id="input-name" type="text" value="<?php echo htmlspecialchars($case['name']); ?>" placeholder="Customer Name" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Vehicle Plate</label>
                                <input id="input-plate" type="text" value="<?php echo htmlspecialchars($case['plate']); ?>" placeholder="Vehicle Plate" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Amount</label>
                                <div class="flex items-center gap-2">
                                    <input id="input-amount" type="text" value="<?php echo htmlspecialchars($case['amount']); ?>" placeholder="0.00" class="flex-1 p-2 bg-slate-50 border border-slate-200 rounded text-lg font-bold text-emerald-600">
                                    <span class="text-lg font-bold text-emerald-600">₾</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Franchise</label>
                                <input id="input-franchise" type="number" value="<?php echo htmlspecialchars($case['franchise'] ?? 0); ?>" placeholder="0.00" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Created At</label>
                                <div class="flex items-center gap-2 text-sm text-slate-700">
                                    <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
                                    <span id="case-created-date" class="font-medium"><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Workflow & Status Section -->
                <section class="flex flex-col gap-6">
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="activity" class="w-4 h-4 text-purple-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Workflow Stage</h3>
                        </div>
                        <select id="input-status" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-base font-bold">
                            <option value="New" <?php echo $case['status'] === 'New' ? 'selected' : ''; ?>>New Case</option>
                            <option value="Processing" <?php echo $case['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Called" <?php echo $case['status'] === 'Called' ? 'selected' : ''; ?>>Contacted</option>
                            <option value="Parts Ordered" <?php echo $case['status'] === 'Parts Ordered' ? 'selected' : ''; ?>>Parts Ordered</option>
                            <option value="Parts Arrived" <?php echo $case['status'] === 'Parts Arrived' ? 'selected' : ''; ?>>Parts Arrived</option>
                            <option value="Scheduled" <?php echo $case['status'] === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="Completed" <?php echo $case['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Issue" <?php echo $case['status'] === 'Issue' ? 'selected' : ''; ?>>Issue</option>
                        </select>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="mb-2 flex items-center gap-2">
                            <i data-lucide="history" class="w-4 h-4 text-slate-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Activity Timeline</h3>
                        </div>
                        <div id="activity-log-container" class="p-2 h-32 overflow-y-auto custom-scrollbar text-sm space-y-2 bg-white/50">
                            <?php
                            if (!empty($case['systemLogs'])) {
                                foreach (array_reverse($case['systemLogs']) as $log) {
                                    $date = date('M j, g:i A', strtotime($log['timestamp']));
                                    echo "<div class='flex items-start gap-3 p-2 bg-slate-50 rounded-lg border border-slate-200'>";
                                    echo "<div class='bg-slate-200 rounded-full p-1 mt-0.5'>";
                                    echo "<i data-lucide='activity' class='w-3 h-3 text-slate-600'></i>";
                                    echo "</div>";
                                    echo "<div class='flex-1'>";
                                    echo "<div class='text-xs text-slate-500 mb-1'>{$date}</div>";
                                    echo "<div class='text-sm text-slate-700'>" . htmlspecialchars($log['message']) . "</div>";
                                    echo "</div>";
                                    echo "</div>";
                                }
                            } else {
                                echo "<div class='text-sm text-slate-500 italic'>No activity recorded</div>";
                            }
                            ?>
                        </div>
                    </div>
                </section>

                <!-- Communication Section -->
                <section class="flex flex-col gap-6">
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="phone" class="w-4 h-4 text-teal-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Contact</h3>
                        </div>
                        <div class="flex gap-2">
                            <input id="input-phone" type="text" value="<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" placeholder="Phone Number" class="flex-1 p-2 bg-slate-50 border border-slate-200 rounded text-base">
                            <a id="btn-call-real" href="tel:<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" class="bg-slate-100 text-teal-600 border border-slate-200 p-2 rounded hover:bg-teal-50 transition">
                                <i data-lucide="phone-call" class="w-5 h-5"></i>
                            </a>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="calendar-check" class="w-4 h-4 text-orange-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Service Appointment</h3>
                        </div>
                        <input id="input-service-date" type="datetime-local" value="<?php echo $case['service_date'] ? date('Y-m-d\TH:i', strtotime($case['service_date'])) : ''; ?>" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="message-circle" class="w-4 h-4 text-blue-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Quick SMS</h3>
                        </div>
                        <div class="flex flex-col gap-2">
                            <button id="btn-sms-register" class="w-full bg-slate-100 hover:bg-blue-100 text-slate-700 font-semibold py-2 px-4 rounded transition">Send Welcome SMS</button>
                            <button id="btn-sms-arrived" class="w-full bg-slate-100 hover:bg-teal-100 text-slate-700 font-semibold py-2 px-4 rounded transition">Parts Arrived SMS</button>
                            <button id="btn-sms-schedule" class="w-full bg-slate-100 hover:bg-orange-100 text-slate-700 font-semibold py-2 px-4 rounded transition">Send Schedule SMS</button>
                        </div>
                    </div>
                    <details class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm" style="margin-top: -8px;">
                        <summary class="flex items-center gap-2 cursor-pointer select-none text-base font-semibold text-slate-700 mb-2">
                            <i data-lucide="message-square" class="w-4 h-4 text-violet-500"></i>
                            Advanced SMS
                        </summary>
                        <div class="space-y-3 mt-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Select Template</label>
                                <select id="sms-template-selector" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-base">
                                    <option value="">Choose a template...</option>
                                    <?php foreach ($smsTemplates as $slug => $template): ?>
                                    <option value="<?php echo htmlspecialchars($slug); ?>" data-content="<?php echo htmlspecialchars($template['content']); ?>">
                                        <?php echo htmlspecialchars($template['name'] ?? ucfirst(str_replace('_', ' ', $slug))); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Message Preview</label>
                                <div id="sms-preview" class="bg-slate-50 border border-slate-200 rounded p-3 min-h-[60px] text-sm text-slate-700 whitespace-pre-wrap">
                                    <span class="text-slate-400 italic">Select a template to see preview...</span>
                                </div>
                            </div>
                            <button id="btn-send-custom-sms" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold py-2 px-4 rounded transition disabled:opacity-50" disabled>
                                <i data-lucide="send" class="w-4 h-4 inline mr-2"></i>
                                Send Custom SMS
                            </button>
                        </div>
                    </details>
                </section>
            </div>

            <!-- Right: Customer Feedback, Reschedule, Notes, Actions -->
            <div class="flex flex-col gap-8">
                <!-- Customer Feedback Section -->
                <section class="flex flex-col gap-6">
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="mb-4 flex items-center gap-2 justify-between">
                            <span class="flex items-center gap-2">
                                <i data-lucide="star" class="w-4 h-4 text-amber-400"></i>
                                <h3 class="text-base font-semibold text-slate-700">Customer Review</h3>
                            </span>
                            <button id="btn-edit-review" class="text-xs text-slate-500 hover:text-blue-600 px-2 py-1 rounded transition flex items-center gap-1">
                                <i data-lucide="edit" class="w-4 h-4"></i> Edit
                            </button>
                        </div>
                        <div id="review-display" class="space-y-3">
                            <?php if (!empty($case['reviewStars'])): ?>
                            <div class="flex items-center gap-2">
                                <div class="flex gap-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i data-lucide="star" class="w-5 h-5 <?php echo $i <= $case['reviewStars'] ? 'text-amber-400 fill-current' : 'text-slate-300'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-2xl font-bold text-amber-600"><?php echo $case['reviewStars']; ?>/5</span>
                            </div>
                            <?php if (!empty($case['reviewComment'])): ?>
                            <div class="bg-slate-50 p-3 rounded border border-slate-200">
                                <p class="text-sm text-slate-700 italic leading-relaxed"><?php echo htmlspecialchars($case['reviewComment']); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i data-lucide="star" class="w-8 h-8 text-amber-200 mx-auto mb-2"></i>
                                <p class="text-sm text-slate-400">No review yet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div id="review-edit" class="space-y-3 hidden">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Rating</label>
                                <select id="input-review-stars" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-base">
                                    <option value="">No rating</option>
                                    <option value="1" <?php echo $case['reviewStars'] == 1 ? 'selected' : ''; ?>>⭐ 1 Star</option>
                                    <option value="2" <?php echo $case['reviewStars'] == 2 ? 'selected' : ''; ?>>⭐⭐ 2 Stars</option>
                                    <option value="3" <?php echo $case['reviewStars'] == 3 ? 'selected' : ''; ?>>⭐⭐⭐ 3 Stars</option>
                                    <option value="4" <?php echo $case['reviewStars'] == 4 ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4 Stars</option>
                                    <option value="5" <?php echo $case['reviewStars'] == 5 ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 Stars</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Comment</label>
                                <textarea id="input-review-comment" rows="3" placeholder="Customer feedback..." class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm resize-none"><?php echo htmlspecialchars($case['reviewComment'] ?? ''); ?></textarea>
                            </div>
                            <div class="flex gap-2">
                                <button id="btn-save-review" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-4 rounded transition">Save Review</button>
                                <button id="btn-cancel-review" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded transition">Cancel</button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Reschedule Section -->
                <?php if ($case['user_response'] === 'Reschedule Requested' && !empty($case['rescheduleDate'])): ?>
                <section>
                    <div class="bg-white rounded-xl p-5 border border-purple-200 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="calendar-clock" class="w-4 h-4 text-purple-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Reschedule Request</h3>
                            <span class="ml-auto text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded">Pending</span>
                        </div>
                        <div class="space-y-2">
                            <div class="bg-slate-50 p-3 rounded border border-slate-200">
                                <span class="block text-xs font-semibold text-purple-700 mb-1">Requested Date</span>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-4 h-4 text-purple-500"></i>
                                    <span class="text-base font-bold text-slate-800"><?php echo date('M j, Y g:i A', strtotime($case['rescheduleDate'])); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($case['rescheduleComment'])): ?>
                            <div class="bg-slate-50 p-3 rounded border border-slate-200">
                                <span class="block text-xs font-semibold text-purple-700 mb-1">Customer Comment</span>
                                <p class="text-sm text-slate-700 leading-relaxed"><?php echo htmlspecialchars($case['rescheduleComment']); ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="flex gap-2 pt-2">
                                <button onclick="acceptReschedule()" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded font-bold text-sm transition">Accept</button>
                                <button onclick="declineReschedule()" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded font-bold text-sm transition">Decline</button>
                            </div>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Internal Notes & Actions Section -->
                <section class="flex flex-col gap-6">
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="sticky-note" class="w-4 h-4 text-yellow-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Internal Notes</h3>
                        </div>
                        <div>
                            <div id="notes-container" class="space-y-3 mb-4 max-h-64 overflow-y-auto">
                                <?php
                                if (!empty($case['internalNotes'])) {
                                    foreach ($case['internalNotes'] as $note) {
                                        $date = date('M j, g:i A', strtotime($note['timestamp']));
                                        echo "<div class='bg-white p-3 rounded-lg border border-yellow-100 shadow-sm'>";
                                        echo "<p class='text-sm text-slate-700'>" . htmlspecialchars($note['text']) . "</p>";
                                        echo "<div class='flex justify-end mt-2'>";
                                        echo "<span class='text-xs text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full'>" . htmlspecialchars($note['authorName'] ?? 'Manager') . " - {$date}</span>";
                                        echo "</div>";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<div class='text-sm text-slate-500 italic text-center py-4'>No internal notes yet</div>";
                                }
                                ?>
                            </div>
                            <div class="flex gap-2">
                                <input id="new-note-input" type="text" placeholder="Add a note..." class="flex-1 px-2 py-2 bg-slate-50 border border-slate-200 rounded text-sm">
                                <button onclick="addNote()" class="bg-slate-600 hover:bg-slate-700 text-white px-3 py-2 rounded font-bold text-sm transition">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-2">
                        <button onclick="saveChanges()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded font-bold text-base transition flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-5 h-5"></i>
                            Save Changes
                        </button>
                        <button onclick="deleteCase()" class="bg-red-500 hover:bg-red-600 text-white py-3 px-4 rounded font-bold text-base transition flex items-center justify-center gap-2">
                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                            Delete
                        </button>
                    </div>
                </section>
            </div>
        </div>

            <!-- Order Details Card -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <i data-lucide="file-text" class="w-4 h-4 text-blue-500"></i>
                    <h3 class="text-base font-semibold text-slate-700">Order Details</h3>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Customer Name</label>
                        <input id="input-name" type="text" value="<?php echo htmlspecialchars($case['name']); ?>" placeholder="Customer Name" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Vehicle Plate</label>
                        <input id="input-plate" type="text" value="<?php echo htmlspecialchars($case['plate']); ?>" placeholder="Vehicle Plate" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Amount</label>
                        <div class="flex items-center gap-2">
                            <input id="input-amount" type="text" value="<?php echo htmlspecialchars($case['amount']); ?>" placeholder="0.00" class="flex-1 p-2 bg-slate-50 border border-slate-200 rounded text-lg font-bold text-emerald-600">
                            <span class="text-lg font-bold text-emerald-600">₾</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Franchise</label>
                        <input id="input-franchise" type="number" value="<?php echo htmlspecialchars($case['franchise'] ?? 0); ?>" placeholder="0.00" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Created At</label>
                        <div class="flex items-center gap-2 text-sm text-slate-700">
                            <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
                            <span id="case-created-date" class="font-medium"><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></span>
                        </div>
                    </div>
                    </div>

            <!-- Status Selection Card -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <i data-lucide="activity" class="w-4 h-4 text-purple-500"></i>
                    <h3 class="text-base font-semibold text-slate-700">Workflow Stage</h3>
                </div>
                <select id="input-status" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-base font-bold">
                    <option value="New" <?php echo $case['status'] === 'New' ? 'selected' : ''; ?>>New Case</option>
                    <option value="Processing" <?php echo $case['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="Called" <?php echo $case['status'] === 'Called' ? 'selected' : ''; ?>>Contacted</option>
                    <option value="Parts Ordered" <?php echo $case['status'] === 'Parts Ordered' ? 'selected' : ''; ?>>Parts Ordered</option>
                    <option value="Parts Arrived" <?php echo $case['status'] === 'Parts Arrived' ? 'selected' : ''; ?>>Parts Arrived</option>
                    <option value="Scheduled" <?php echo $case['status'] === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="Completed" <?php echo $case['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Issue" <?php echo $case['status'] === 'Issue' ? 'selected' : ''; ?>>Issue</option>
                </select>
            </div>

                <!-- System Activity Log -->
                <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 bg-gradient-to-r from-slate-700 to-slate-600 flex items-center gap-2">
                        <i data-lucide="history" class="w-4 h-4 text-white"></i>
                        <label class="text-sm font-bold text-white uppercase tracking-wider">Activity Timeline</label>
                    </div>
                    <div id="activity-log-container" class="p-4 h-32 overflow-y-auto custom-scrollbar text-sm space-y-2 bg-white/50">
                        <?php
                        if (!empty($case['systemLogs'])) {
                            foreach (array_reverse($case['systemLogs']) as $log) {
                                $date = date('M j, g:i A', strtotime($log['timestamp']));
                                echo "<div class='flex items-start gap-3 p-2 bg-slate-50 rounded-lg border border-slate-200'>";
                                echo "<div class='bg-slate-200 rounded-full p-1 mt-0.5'>";
                                echo "<i data-lucide='activity' class='w-3 h-3 text-slate-600'></i>";
                                echo "</div>";
                                echo "<div class='flex-1'>";
                                echo "<div class='text-xs text-slate-500 mb-1'>{$date}</div>";
                                echo "<div class='text-sm text-slate-700'>" . htmlspecialchars($log['message']) . "</div>";
                                echo "</div>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='text-sm text-slate-500 italic'>No activity recorded</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Contact Card -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <i data-lucide="phone" class="w-4 h-4 text-teal-500"></i>
                    <h3 class="text-base font-semibold text-slate-700">Contact</h3>
                </div>
                <div class="flex gap-2">
                    <input id="input-phone" type="text" value="<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" placeholder="Phone Number" class="flex-1 p-2 bg-slate-50 border border-slate-200 rounded text-base">
                    <a id="btn-call-real" href="tel:<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" class="bg-slate-100 text-teal-600 border border-slate-200 p-2 rounded hover:bg-teal-50 transition">
                        <i data-lucide="phone-call" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>

            <!-- Appointment Card -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <i data-lucide="calendar-check" class="w-4 h-4 text-orange-500"></i>
                    <h3 class="text-base font-semibold text-slate-700">Service Appointment</h3>
                </div>
                <input id="input-service-date" type="datetime-local" value="<?php echo $case['service_date'] ? date('Y-m-d\TH:i', strtotime($case['service_date'])) : ''; ?>" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
            </div>

            <!-- Quick SMS Actions Card -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <i data-lucide="message-circle" class="w-4 h-4 text-blue-500"></i>
                    <h3 class="text-base font-semibold text-slate-700">Quick SMS</h3>
                </div>
                <div class="flex flex-col gap-2">
                    <button id="btn-sms-register" class="w-full bg-slate-100 hover:bg-blue-100 text-slate-700 font-semibold py-2 px-4 rounded transition">Send Welcome SMS</button>
                    <button id="btn-sms-arrived" class="w-full bg-slate-100 hover:bg-teal-100 text-slate-700 font-semibold py-2 px-4 rounded transition">Parts Arrived SMS</button>
                    <button id="btn-sms-schedule" class="w-full bg-slate-100 hover:bg-orange-100 text-slate-700 font-semibold py-2 px-4 rounded transition">Send Schedule SMS</button>
                </div>
            </div>

            <!-- Advanced SMS (Collapsible) -->
            <details class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm" style="margin-top: -8px;">
                <summary class="flex items-center gap-2 cursor-pointer select-none text-base font-semibold text-slate-700 mb-2">
                    <i data-lucide="message-square" class="w-4 h-4 text-violet-500"></i>
                    Advanced SMS
                </summary>
                <div class="space-y-3 mt-2">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Select Template</label>
                        <select id="sms-template-selector" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-base">
                            <option value="">Choose a template...</option>
                            <?php foreach ($smsTemplates as $slug => $template): ?>
                            <option value="<?php echo htmlspecialchars($slug); ?>" data-content="<?php echo htmlspecialchars($template['content']); ?>">
                                <?php echo htmlspecialchars($template['name'] ?? ucfirst(str_replace('_', ' ', $slug))); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Message Preview</label>
                        <div id="sms-preview" class="bg-slate-50 border border-slate-200 rounded p-3 min-h-[60px] text-sm text-slate-700 whitespace-pre-wrap">
                            <span class="text-slate-400 italic">Select a template to see preview...</span>
                        </div>
                    </div>
                    <button id="btn-send-custom-sms" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold py-2 px-4 rounded transition disabled:opacity-50" disabled>
                        <i data-lucide="send" class="w-4 h-4 inline mr-2"></i>
                        Send Custom SMS
                    </button>
                </div>
            </details>
            </div>

            <!-- Customer Review Card -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                <div class="mb-4 flex items-center gap-2 justify-between">
                    <span class="flex items-center gap-2">
                        <i data-lucide="star" class="w-4 h-4 text-amber-400"></i>
                        <h3 class="text-base font-semibold text-slate-700">Customer Review</h3>
                    </span>
                    <button id="btn-edit-review" class="text-xs text-slate-500 hover:text-blue-600 px-2 py-1 rounded transition flex items-center gap-1">
                        <i data-lucide="edit" class="w-4 h-4"></i> Edit
                    </button>
                </div>
                <div id="review-display" class="space-y-3">
                        <?php if (!empty($case['reviewStars'])): ?>
                        <div class="flex items-center gap-2">
                            <div class="flex gap-1">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i data-lucide="star" class="w-5 h-5 <?php echo $i <= $case['reviewStars'] ? 'text-amber-400 fill-current' : 'text-slate-300'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-2xl font-bold text-amber-600"><?php echo $case['reviewStars']; ?>/5</span>
                        </div>
                        <?php if (!empty($case['reviewComment'])): ?>
                        <div class="bg-slate-50 p-3 rounded border border-slate-200">
                            <p class="text-sm text-slate-700 italic leading-relaxed"><?php echo htmlspecialchars($case['reviewComment']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i data-lucide="star" class="w-8 h-8 text-amber-200 mx-auto mb-2"></i>
                            <p class="text-sm text-slate-400">No review yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                <div id="review-edit" class="space-y-3 hidden">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Rating</label>
                        <select id="input-review-stars" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-base">
                            <option value="">No rating</option>
                            <option value="1" <?php echo $case['reviewStars'] == 1 ? 'selected' : ''; ?>>⭐ 1 Star</option>
                            <option value="2" <?php echo $case['reviewStars'] == 2 ? 'selected' : ''; ?>>⭐⭐ 2 Stars</option>
                            <option value="3" <?php echo $case['reviewStars'] == 3 ? 'selected' : ''; ?>>⭐⭐⭐ 3 Stars</option>
                            <option value="4" <?php echo $case['reviewStars'] == 4 ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4 Stars</option>
                            <option value="5" <?php echo $case['reviewStars'] == 5 ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 Stars</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Comment</label>
                        <textarea id="input-review-comment" rows="3" placeholder="Customer feedback..." class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm resize-none"><?php echo htmlspecialchars($case['reviewComment'] ?? ''); ?></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button id="btn-save-review" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-4 rounded transition">Save Review</button>
                        <button id="btn-cancel-review" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded transition">Cancel</button>
                    </div>
                </div>
                </div>

            <!-- Reschedule Request Card -->
            <?php if ($case['user_response'] === 'Reschedule Requested' && !empty($case['rescheduleDate'])): ?>
            <div class="bg-white rounded-xl p-5 border border-purple-200 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <i data-lucide="calendar-clock" class="w-4 h-4 text-purple-500"></i>
                    <h3 class="text-base font-semibold text-slate-700">Reschedule Request</h3>
                    <span class="ml-auto text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded">Pending</span>
                </div>
                <div class="space-y-2">
                    <div class="bg-slate-50 p-3 rounded border border-slate-200">
                        <span class="block text-xs font-semibold text-purple-700 mb-1">Requested Date</span>
                        <div class="flex items-center gap-2">
                            <i data-lucide="calendar" class="w-4 h-4 text-purple-500"></i>
                            <span class="text-base font-bold text-slate-800"><?php echo date('M j, Y g:i A', strtotime($case['rescheduleDate'])); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($case['rescheduleComment'])): ?>
                    <div class="bg-slate-50 p-3 rounded border border-slate-200">
                        <span class="block text-xs font-semibold text-purple-700 mb-1">Customer Comment</span>
                        <p class="text-sm text-slate-700 leading-relaxed"><?php echo htmlspecialchars($case['rescheduleComment']); ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="flex gap-2 pt-2">
                        <button onclick="acceptReschedule()" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded font-bold text-sm transition">Accept</button>
                        <button onclick="declineReschedule()" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded font-bold text-sm transition">Decline</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Internal Notes Card -->
            <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <i data-lucide="sticky-note" class="w-4 h-4 text-yellow-500"></i>
                    <h3 class="text-base font-semibold text-slate-700">Internal Notes</h3>
                </div>
                <div>
                    <div id="notes-container" class="space-y-3 mb-4 max-h-64 overflow-y-auto">
                            <?php
                            if (!empty($case['internalNotes'])) {
                                foreach ($case['internalNotes'] as $note) {
                                    $date = date('M j, g:i A', strtotime($note['timestamp']));
                                    echo "<div class='bg-white p-3 rounded-lg border border-yellow-100 shadow-sm'>";
                                    echo "<p class='text-sm text-slate-700'>" . htmlspecialchars($note['text']) . "</p>";
                                    echo "<div class='flex justify-end mt-2'>";
                                    echo "<span class='text-xs text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full'>" . htmlspecialchars($note['authorName'] ?? 'Manager') . " - {$date}</span>";
                                    echo "</div>";
                                    echo "</div>";
                                }
                            } else {
                                echo "<div class='text-sm text-slate-500 italic text-center py-4'>No internal notes yet</div>";
                            }
                            ?>
                        </div>
                    <div class="flex gap-2">
                        <input id="new-note-input" type="text" placeholder="Add a note..." class="flex-1 px-2 py-2 bg-slate-50 border border-slate-200 rounded text-sm">
                        <button onclick="addNote()" class="bg-slate-600 hover:bg-slate-700 text-white px-3 py-2 rounded font-bold text-sm transition">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </button>
                    </div>
                    </div>
                </div>

            <!-- Action Buttons Card -->
            <div class="flex gap-2 mt-2">
                <button onclick="saveChanges()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded font-bold text-base transition flex items-center justify-center gap-2">
                    <i data-lucide="save" class="w-5 h-5"></i>
                    Save Changes
                </button>
                <button onclick="deleteCase()" class="bg-red-500 hover:bg-red-600 text-white py-3 px-4 rounded font-bold text-base transition flex items-center justify-center gap-2">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                    Delete
                </button>
            </div>
            </div>
        </div>
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

        // Update workflow progress bar
        function updateWorkflowProgress() {
            const status = document.getElementById('input-status').value;
            const stages = ['New', 'Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Completed', 'Issue'];
            const currentIndex = stages.indexOf(status);
            const progress = ((currentIndex + 1) / stages.length) * 100;

            document.getElementById('workflow-stage-number').textContent = currentIndex + 1;
            document.getElementById('workflow-progress-bar').style.width = progress + '%';
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

                document.getElementById('input-service-date').value = rescheduleDateTime;
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

            // Validation: Parts Arrived requires a date
            if (status === 'Parts Arrived' && !serviceDate) {
                showToast("Scheduling Required", "Please select a service date to save 'Parts Arrived' status.", "error");
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
                    const templateData = {
                        id: currentCase.id,
                        name: currentCase.name,
                        plate: currentCase.plate,
                        amount: currentCase.amount,
                        serviceDate: serviceDate || currentCase.serviceDate
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

                // Update workflow progress
                updateWorkflowProgress();

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
            // Update workflow progress on status change
            document.getElementById('input-status').addEventListener('change', updateWorkflowProgress);

            // Enter key for notes
            document.getElementById('new-note-input').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') addNote();
            });

            // SMS button handlers
            document.getElementById('btn-sms-register').addEventListener('click', () => {
                const phone = document.getElementById('input-phone').value;
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount
                };
                const msg = getFormattedMessage('registered', templateData);
                sendSMS(phone, msg, 'welcome');
            });

            document.getElementById('btn-sms-arrived').addEventListener('click', () => {
                const phone = document.getElementById('input-phone').value;
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

            document.getElementById('btn-sms-schedule').addEventListener('click', () => {
                const phone = document.getElementById('input-phone').value;
                const serviceDate = document.getElementById('input-service-date').value;
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
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount,
                    date: date
                };
                const msg = getFormattedMessage('schedule', templateData);
                sendSMS(phone, msg, 'schedule');
            });

            // SMS Template Selector
            document.getElementById('sms-template-selector').addEventListener('change', function() {
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
                    const templateData = {
                        id: CASE_ID,
                        name: document.getElementById('input-name').value || currentCase.name,
                        plate: document.getElementById('input-plate').value || currentCase.plate,
                        amount: document.getElementById('input-amount').value || currentCase.amount,
                        serviceDate: document.getElementById('input-service-date').value || currentCase.serviceDate,
                        date: document.getElementById('input-service-date').value || currentCase.serviceDate
                    };

                    const formattedMessage = getFormattedMessage(templateSlug, templateData);
                    previewDiv.textContent = formattedMessage;
                    sendButton.disabled = false;
                }
            });

            // Send Custom SMS Button
            document.getElementById('btn-send-custom-sms').addEventListener('click', () => {
                const templateSelector = document.getElementById('sms-template-selector');
                const templateSlug = templateSelector.value;
                const phone = document.getElementById('input-phone').value;

                if (!templateSlug) {
                    showToast('No Template Selected', 'Please select an SMS template first', 'error');
                    return;
                }

                const templateData = {
                    id: CASE_ID,
                    name: document.getElementById('input-name').value || currentCase.name,
                    plate: document.getElementById('input-plate').value || currentCase.plate,
                    amount: document.getElementById('input-amount').value || currentCase.amount,
                    serviceDate: document.getElementById('input-service-date').value || currentCase.serviceDate,
                    date: document.getElementById('input-service-date').value || currentCase.serviceDate
                };

                const msg = getFormattedMessage(templateSlug, templateData);
                sendSMS(phone, msg, `custom_${templateSlug}`);
            });

            // Review Editing
            document.getElementById('btn-edit-review').addEventListener('click', () => {
                document.getElementById('review-display').classList.add('hidden');
                document.getElementById('review-edit').classList.remove('hidden');
            });

            document.getElementById('btn-cancel-review').addEventListener('click', () => {
                document.getElementById('review-edit').classList.add('hidden');
                document.getElementById('review-display').classList.remove('hidden');
            });

            document.getElementById('btn-save-review').addEventListener('click', async () => {
                const stars = document.getElementById('input-review-stars').value;
                const comment = document.getElementById('input-review-comment').value.trim();

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
            updateWorkflowProgress();
            initializeIcons();
        });
    </script>
</body>
</html>