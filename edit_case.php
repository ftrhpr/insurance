<?php
require_once 'session_config.php';
require_once 'config.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo '<div class="p-8 text-red-600 font-bold">Invalid case ID</div>';
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
    $stmt->execute([$id]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) {
        echo '<div class="p-8 text-red-600 font-bold">Case not found</div>';
        exit;
    }
    $case['internalNotes'] = json_decode($case['internalNotes'] ?? '[]', true) ?: [];
    $case['systemLogs'] = json_decode($case['systemLogs'] ?? '[]', true) ?: [];
} catch (Exception $e) {
    echo '<div class="p-8 text-red-600 font-bold">DB error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Fetch SMS templates
$smsTemplates = [];
try {
    $stmt = $pdo->prepare("SELECT slug, content FROM sms_templates WHERE is_active = 1");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tpl) {
        $smsTemplates[$tpl['slug']] = $tpl['content'];
    }
} catch (Exception $e) {}
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
    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Back Button -->
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-800 transition-colors">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                <span class="font-medium">Back to Dashboard</span>
            </a>
        </div>

        <!-- Case Header -->
        <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden mb-6">
            <div class="relative bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-6 py-4 flex justify-between items-center">
                <!-- Decorative Background Pattern -->
                <div class="absolute inset-0 bg-grid-white/[0.05] bg-[size:20px_20px]"></div>
                <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/10"></div>

                <div class="relative flex items-center gap-4 overflow-hidden">
                     <!-- Vehicle Badge -->
                     <div class="relative shrink-0">
                         <div class="absolute inset-0 bg-white/30 blur-xl rounded-2xl"></div>
                         <div class="relative bg-white/20 backdrop-blur-md border-2 border-white/40 px-5 py-3 rounded-xl font-mono font-extrabold text-white shadow-2xl flex items-center gap-2">
                            <div class="bg-white/20 p-1.5 rounded-lg">
                                <i data-lucide="car" class="w-5 h-5"></i>
                            </div>
                            <span class="tracking-wider text-lg"><?php echo htmlspecialchars($case['plate']); ?></span>
                         </div>
                     </div>

                     <!-- Divider -->
                     <div class="h-12 w-px bg-white/30 shrink-0"></div>

                     <!-- Customer Info -->
                     <div class="flex flex-col gap-1 min-w-0 flex-1">
                         <div class="flex items-center gap-2 flex-wrap">
                             <span class="text-[10px] text-white/60 font-bold uppercase tracking-widest">Order</span>
                             <span class="text-sm font-mono text-white bg-white/20 backdrop-blur-sm px-3 py-1 rounded-lg border border-white/30 shadow-lg">#<?php echo $case_id; ?></span>
                         </div>
                         <div class="flex items-center gap-2">
                             <i data-lucide="user" class="w-4 h-4 text-white/70 shrink-0"></i>
                             <span class="text-lg font-bold text-white truncate"><?php echo htmlspecialchars($case['name']); ?></span>
                         </div>
                     </div>
                </div>

                <!-- Action Buttons -->
                <div class="relative flex items-center gap-3">
                    <button onclick="window.printCase()" class="text-white/80 hover:text-white hover:bg-white/20 px-4 py-2 rounded-lg transition-all" title="Print Case">
                        <i data-lucide="printer" class="w-5 h-5"></i>
                    </button>
                    <a href="index.php" class="text-white/80 hover:text-white hover:bg-white/20 px-4 py-2 rounded-lg transition-all">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>

            <!-- Workflow Progress -->
            <div class="px-6 py-4 bg-gradient-to-r from-slate-50 to-blue-50 border-b border-slate-200">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Case Progress</h4>
                    <span class="text-xs bg-slate-100 text-slate-600 px-3 py-1 rounded-full font-medium">Stage <span id="workflow-stage-number">1</span> of 8</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="flex-1 h-3 bg-slate-200 rounded-full overflow-hidden">
                        <div id="workflow-progress-bar" class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full transition-all duration-500" style="width: 12.5%"></div>
                    </div>
                </div>
                <div class="flex justify-between mt-2 text-xs text-slate-500 font-medium">
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
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left Column: Order Details & Status -->
            <div class="space-y-6">
                <!-- Order Information Card -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100 shadow-sm">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="bg-blue-600 p-2 rounded-lg shadow-sm">
                            <i data-lucide="file-text" class="w-4 h-4 text-white"></i>
                        </div>
                        <h3 class="text-sm font-bold text-blue-900 uppercase tracking-wider">Order Details</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="bg-white rounded-lg p-4 border border-blue-100">
                            <div class="text-xs text-blue-600 font-bold uppercase mb-2">Customer Name</div>
                            <input id="input-name" type="text" value="<?php echo htmlspecialchars($case['name']); ?>" placeholder="Customer Name" class="w-full p-3 bg-white border border-slate-200 rounded-lg text-lg font-bold text-slate-800 focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20 outline-none">
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-blue-100">
                            <div class="text-xs text-blue-600 font-bold uppercase mb-2">Vehicle Plate</div>
                            <input id="input-plate" type="text" value="<?php echo htmlspecialchars($case['plate']); ?>" placeholder="Vehicle Plate" class="w-full p-3 bg-white border border-slate-200 rounded-lg text-lg font-bold text-slate-800 focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20 outline-none">
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-blue-100">
                            <div class="text-xs text-blue-600 font-bold uppercase mb-2">Amount</div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="coins" class="w-6 h-6 text-emerald-500"></i>
                                <input id="input-amount" type="text" value="<?php echo htmlspecialchars($case['amount']); ?>" placeholder="0.00" class="flex-1 p-3 bg-white border border-slate-200 rounded-lg text-3xl font-bold text-emerald-600 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none">
                                <span class="text-3xl font-bold text-emerald-600">₾</span>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-blue-100">
                            <div class="text-xs text-blue-600 font-bold uppercase mb-2">Franchise</div>
                            <input id="input-franchise" type="number" value="<?php echo htmlspecialchars($case['franchise'] ?? 0); ?>" placeholder="0.00" class="w-full p-3 bg-white border border-slate-200 rounded-lg text-lg font-bold text-orange-600 focus:border-orange-400 focus:ring-2 focus:ring-orange-400/20 outline-none">
                        </div>
                        <div class="bg-white rounded-lg p-4 border border-blue-100">
                            <div class="text-xs text-blue-600 font-bold uppercase mb-2">Created At</div>
                            <div class="flex items-center gap-2 text-sm text-slate-700">
                                <i data-lucide="clock" class="w-5 h-5 text-slate-400"></i>
                                <span id="case-created-date" class="font-medium"><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Selection -->
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 border border-purple-100 shadow-sm">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="bg-purple-600 p-2 rounded-lg shadow-sm">
                            <i data-lucide="activity" class="w-4 h-4 text-white"></i>
                        </div>
                        <h3 class="text-sm font-bold text-purple-900 uppercase tracking-wider">Workflow Stage</h3>
                    </div>
                    <div class="relative">
                        <select id="input-status" class="w-full appearance-none bg-white border-2 border-purple-200 text-slate-800 py-4 pl-12 pr-10 rounded-xl leading-tight focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 text-base font-bold shadow-lg transition-all cursor-pointer hover:border-purple-300">
                            <option value="New" <?php echo $case['status'] === 'New' ? 'selected' : ''; ?>>🔵 New Case</option>
                            <option value="Processing" <?php echo $case['status'] === 'Processing' ? 'selected' : ''; ?>>🟡 Processing</option>
                            <option value="Called" <?php echo $case['status'] === 'Called' ? 'selected' : ''; ?>>🟣 Contacted</option>
                            <option value="Parts Ordered" <?php echo $case['status'] === 'Parts Ordered' ? 'selected' : ''; ?>>📦 Parts Ordered</option>
                            <option value="Parts Arrived" <?php echo $case['status'] === 'Parts Arrived' ? 'selected' : ''; ?>>🏁 Parts Arrived</option>
                            <option value="Scheduled" <?php echo $case['status'] === 'Scheduled' ? 'selected' : ''; ?>>🟠 Scheduled</option>
                            <option value="Completed" <?php echo $case['status'] === 'Completed' ? 'selected' : ''; ?>>🟢 Completed</option>
                            <option value="Issue" <?php echo $case['status'] === 'Issue' ? 'selected' : ''; ?>>🔴 Issue</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-purple-500">
                            <i data-lucide="git-branch" class="w-6 h-6"></i>
                        </div>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-purple-400">
                            <i data-lucide="chevron-down" class="w-6 h-6"></i>
                        </div>
                    </div>
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
                            <body class="bg-slate-50 min-h-screen">
                            <div class="max-w-4xl mx-auto my-8 bg-white rounded-2xl shadow-xl p-8">
                                <div class="flex items-center gap-4 mb-6">
                                    <a href="index.php" class="text-blue-600 hover:underline flex items-center gap-1"><i data-lucide="arrow-left"></i>Back</a>
                                    <h1 class="text-2xl font-bold flex-1">Edit Case: <?php echo htmlspecialchars($case['plate']); ?> <span class="ml-2 px-2 py-1 rounded text-xs font-semibold bg-slate-100 border border-slate-200 text-slate-600"><?php echo htmlspecialchars($case['status']); ?></span></h1>
                                </div>
                                <form id="edit-case-form" class="space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-bold mb-1">Name</label>
                                            <input name="name" type="text" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars($case['name']); ?>" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold mb-1">Phone</label>
                                            <input name="phone" type="text" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars($case['phone']); ?>" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold mb-1">Amount</label>
                                            <input name="amount" type="number" step="0.01" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars($case['amount']); ?>" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold mb-1">Franchise</label>
                                            <input name="franchise" type="text" class="w-full px-3 py-2 border rounded-lg" value="<?php echo htmlspecialchars($case['franchise']); ?>">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold mb-1">Status</label>
                                            <select name="status" class="w-full px-3 py-2 border rounded-lg" required>
                                                <?php
                                                $statuses = ['New','Processing','Called','Parts Ordered','Parts Arrived','Scheduled','Completed','Issue','Collected','Collected Waiting'];
                                                foreach ($statuses as $s) {
                                                    $sel = $case['status'] === $s ? 'selected' : '';
                                                    echo "<option value=\"$s\" $sel>$s</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold mb-1">Service Date</label>
                                            <input name="serviceDate" type="datetime-local" class="w-full px-3 py-2 border rounded-lg" value="<?php echo $case['serviceDate'] ? date('Y-m-d\TH:i', strtotime($case['serviceDate'])) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <label class="block text-sm font-bold mb-1">Parts List</label>
                                        <textarea name="parts_list" class="w-full px-3 py-2 border rounded-lg min-h-[60px]" placeholder="JSON array or comma-separated list"><?php echo htmlspecialchars(json_encode($case['parts_list'] ?? [])); ?></textarea>
                                        <button type="button" class="mt-2 px-4 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200" onclick="openPartsModal()">Edit Parts</button>
                                    </div>
                                    <div class="mt-6">
                                        <label class="block text-sm font-bold mb-1">Customer Response</label>
                                        <div class="flex items-center gap-4">
                                            <span class="px-2 py-1 rounded text-xs font-semibold bg-slate-100 border border-slate-200 text-slate-600"><?php echo htmlspecialchars($case['user_response'] ?? 'Pending'); ?></span>
                                            <?php if (($case['user_response'] ?? '') === 'Reschedule Requested'): ?>
                                                <span class="text-sm text-slate-600">Requested: <?php echo htmlspecialchars($case['reschedule_date'] ?? ''); ?> <?php echo htmlspecialchars($case['reschedule_comment'] ?? ''); ?></span>
                                                <button type="button" class="ml-2 px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200" onclick="acceptReschedule()">Accept</button>
                                                <button type="button" class="ml-2 px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200" onclick="declineReschedule()">Decline</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="mt-6">
                                        <label class="block text-sm font-bold mb-1">Internal Notes</label>
                                        <textarea name="internalNotes" class="w-full px-3 py-2 border rounded-lg min-h-[60px]" placeholder="Notes (JSON array)"><?php echo htmlspecialchars(json_encode($case['internalNotes'])); ?></textarea>
                                    </div>
                                    <div class="mt-6">
                                        <label class="block text-sm font-bold mb-1">System Logs</label>
                                        <div class="bg-slate-50 border rounded-lg p-3 max-h-40 overflow-y-auto text-xs">
                                            <?php foreach ($case['systemLogs'] as $log) {
                                                echo '<div class="mb-1">' . htmlspecialchars(is_array($log) ? ($log['timestamp'] ?? '') . ' ' . ($log['message'] ?? '') : $log) . '</div>';
                                            } ?>
                                        </div>
                                    </div>
                                    <div class="mt-6 flex gap-4">
                                        <button type="button" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" onclick="openSmsModal()">Send SMS</button>
                                        <button type="button" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700" onclick="testFCM()">Test FCM</button>
                                        <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700 font-bold">Save</button>
                                        <button type="button" class="px-6 py-2 bg-slate-200 text-slate-700 rounded hover:bg-slate-300" onclick="window.location='index.php'">Cancel</button>
                                    </div>
                                </form>
                                <div class="mt-8">
                                    <h2 class="text-lg font-bold mb-2">Review</h2>
                                    <?php if ($case['review_stars']): ?>
                                        <div class="flex items-center gap-2">
                                            <span class="text-yellow-500 font-bold text-xl"><?php echo str_repeat('★', intval($case['review_stars'])); ?></span>
                                            <span class="text-slate-600">(<?php echo intval($case['review_stars']); ?> stars)</span>
                                        </div>
                                        <div class="text-slate-700 mt-1"><?php echo htmlspecialchars($case['review_comment']); ?></div>
                                        <button class="mt-2 px-4 py-1 bg-orange-100 text-orange-700 rounded hover:bg-orange-200">Moderate Review</button>
                                    <?php else: ?>
                                        <button class="px-4 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200">Request Review</button>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-8">
                                    <h2 class="text-lg font-bold mb-2">Audit Timeline</h2>
                                    <div class="bg-slate-50 border rounded-lg p-3 max-h-40 overflow-y-auto text-xs">
                                        <?php foreach ($case['systemLogs'] as $log) {
                                            echo '<div class="mb-1">' . htmlspecialchars(is_array($log) ? ($log['timestamp'] ?? '') . ' ' . ($log['message'] ?? '') : $log) . '</div>';
                                        } ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Modals (Parts, SMS, etc.) would go here -->
                            <script>
                            // JS helpers for modals, API, validation, etc.
                            function openPartsModal() { alert('Parts modal not implemented in this demo.'); }
                            function openSmsModal() { alert('SMS modal not implemented in this demo.'); }
                            function testFCM() { alert('FCM test not implemented in this demo.'); }
                            function acceptReschedule() { alert('Accept reschedule not implemented.'); }
                            function declineReschedule() { alert('Decline reschedule not implemented.'); }

                            // Save handler
                            const form = document.getElementById('edit-case-form');
                            form.addEventListener('submit', async function(e) {
                                e.preventDefault();
                                const data = Object.fromEntries(new FormData(form).entries());
                                // Parse JSON fields
                                try {
                                    data.internalNotes = JSON.parse(data.internalNotes);
                                } catch { data.internalNotes = []; }
                                try {
                                    data.parts_list = JSON.parse(data.parts_list);
                                } catch { data.parts_list = []; }
                                // API call
                                try {
                                    const res = await fetch('api.php?action=update_transfer&id=<?php echo $id; ?>', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify(data)
                                    });
                                    if (!res.ok) throw new Error(await res.text());
                                    alert('Saved!');
                                    window.location.reload();
                                } catch (err) {
                                    alert('Save failed: ' + err.message);
                                }
                            });

                            lucide.createIcons();
                            </script>
                            </body>
                            </html>
                        <button id="btn-edit-review" class="text-white/80 hover:text-white hover:bg-white/20 px-3 py-1 rounded-lg transition-all text-xs font-bold">
                            <i data-lucide="edit" class="w-4 h-4 inline mr-1"></i>
                            Edit
                        </button>
                    </div>
                    <div id="review-display" class="p-4 space-y-3">
                        <?php if (!empty($case['reviewStars'])): ?>
                        <div class="flex items-center gap-4">
                            <div class="flex gap-1">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i data-lucide="star" class="w-5 h-5 <?php echo $i <= $case['reviewStars'] ? 'text-amber-400 fill-current' : 'text-slate-300'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-4xl font-black text-amber-600"><?php echo $case['reviewStars']; ?>/5</span>
                        </div>
                        <?php if (!empty($case['reviewComment'])): ?>
                        <div class="bg-white/80 p-3 rounded-lg border border-amber-200">
                            <p class="text-sm text-slate-700 italic leading-relaxed"><?php echo htmlspecialchars($case['reviewComment']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="text-center py-6">
                            <i data-lucide="star" class="w-12 h-12 text-amber-300 mx-auto mb-2"></i>
                            <p class="text-sm text-slate-500">No review yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div id="review-edit" class="p-4 space-y-3 hidden">
                        <div>
                            <label class="block text-xs text-amber-700 font-bold uppercase mb-2">Rating</label>
                            <select id="input-review-stars" class="w-full bg-white border-2 border-amber-200 rounded-xl p-3 text-base font-bold focus:border-amber-400 focus:ring-4 focus:ring-amber-400/20 outline-none">
                                <option value="">No rating</option>
                                <option value="1" <?php echo $case['reviewStars'] == 1 ? 'selected' : ''; ?>>⭐ 1 Star</option>
                                <option value="2" <?php echo $case['reviewStars'] == 2 ? 'selected' : ''; ?>>⭐⭐ 2 Stars</option>
                                <option value="3" <?php echo $case['reviewStars'] == 3 ? 'selected' : ''; ?>>⭐⭐⭐ 3 Stars</option>
                                <option value="4" <?php echo $case['reviewStars'] == 4 ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4 Stars</option>
                                <option value="5" <?php echo $case['reviewStars'] == 5 ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 Stars</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-amber-700 font-bold uppercase mb-2">Comment</label>
                            <textarea id="input-review-comment" rows="3" placeholder="Customer feedback..." class="w-full bg-white border-2 border-amber-200 rounded-xl p-3 text-sm focus:border-amber-400 focus:ring-4 focus:ring-amber-400/20 outline-none resize-none"><?php echo htmlspecialchars($case['reviewComment'] ?? ''); ?></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button id="btn-save-review" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-bold py-2 px-4 rounded-xl transition-all shadow-lg hover:shadow-xl active:scale-95">
                                <i data-lucide="save" class="w-4 h-4 inline mr-2"></i>
                                Save Review
                            </button>
                            <button id="btn-cancel-review" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl transition-all">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Reschedule Request Preview -->
                <?php if ($case['user_response'] === 'Reschedule Requested' && !empty($case['rescheduleDate'])): ?>
                <div class="bg-gradient-to-br from-purple-50 to-fuchsia-50 rounded-xl border-2 border-purple-200 overflow-hidden shadow-lg">
                    <div class="px-4 py-3 bg-gradient-to-r from-purple-600 to-fuchsia-600 flex items-center justify-between">
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

                <!-- Internal Notes -->
                <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 bg-gradient-to-r from-slate-700 to-slate-600 flex items-center gap-2">
                        <i data-lucide="sticky-note" class="w-4 h-4 text-white"></i>
                        <label class="text-sm font-bold text-white uppercase tracking-wider">Internal Notes</label>
                    </div>
                    <div class="p-4">
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
                            <input id="new-note-input" type="text" placeholder="Add a note..." class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:border-slate-400 focus:ring-2 focus:ring-slate-400/20 outline-none">
                            <button onclick="addNote()" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-all active:scale-95">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
                    <div class="flex gap-3">
                        <button onclick="saveChanges()" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white py-4 px-6 rounded-xl font-bold text-base shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-5 h-5"></i>
                            Save Changes
                        </button>
                        <button onclick="deleteCase()" class="bg-red-600 hover:bg-red-700 text-white py-4 px-6 rounded-xl font-bold text-base shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2">
                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                            Delete
                        </button>
                    </div>
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