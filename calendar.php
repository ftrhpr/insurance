<?php
// calendar.php - Calendar view for cases with due dates
session_start();
require_once 'config.php';
require_once 'language.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch all cases with due dates
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure transfers table has repair management columns (defensive migration compatible with older MySQL)
    try {
        $required = [
            'repair_status' => "VARCHAR(50) DEFAULT NULL",
            'repair_start_date' => "DATETIME DEFAULT NULL",
            'repair_end_date' => "DATETIME DEFAULT NULL",
            'assigned_mechanic' => "VARCHAR(100) DEFAULT NULL",
            'repair_notes' => "TEXT DEFAULT NULL",
            'repair_parts' => "TEXT DEFAULT NULL",
            'repair_labor' => "TEXT DEFAULT NULL",
            'repair_activity_log' => "TEXT DEFAULT NULL",
        ];
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'transfers' AND COLUMN_NAME = ?");
        foreach ($required as $col => $def) {
            $checkStmt->execute([DB_NAME, $col]);
            if ($checkStmt->fetchColumn() == 0) {
                $pdo->exec("ALTER TABLE transfers ADD COLUMN `$col` $def");
            }
        }
    } catch (Exception $e) {
        // Non-fatal: continue if the DB doesn't allow ALTER in this context
    }

    $stmt = $pdo->query("SELECT id, plate, name, due_date, status, service_date, amount, phone, franchise, user_response, reschedule_date, reschedule_comment, created_at, repair_status, assigned_mechanic, repair_start_date, repair_end_date, repair_notes, repair_parts, repair_labor, repair_activity_log FROM transfers WHERE due_date IS NOT NULL ORDER BY due_date ASC");
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

?><!DOCTYPE html>
                    <html lang="<?php echo get_current_language(); ?>">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title><?php echo __('calendar.title', 'Due Date Calendar'); ?> - OTOMOTORS</title>
                        <script src="https://cdn.tailwindcss.com"></script>
                        <script src="https://cdn.jsdelivr.net/npm/lucide@0.378.0/dist/umd/lucide.js"></script>
                        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
                        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
                        <style>
                            .fc-event { cursor: pointer; }
                            .fc-toolbar-title { font-size: 1.5rem; font-weight: 700; color: #2563eb; }
                            .fc-day-today { background: #dbeafe !important; }
                            .fc-event { border-radius: 8px; border: none; }
                            .fc-daygrid-event-dot { display: none; }
                            .completed-case::after {
                                content: '✓';
                                position: absolute;
                                top: 2px;
                                right: 2px;
                                background: white;
                                border-radius: 50%;
                                width: 16px;
                                height: 16px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 10px;
                                font-weight: bold;
                                color: #22c55e;
                            }
                            .due-today {
                                box-shadow: 0 0 12px rgba(59, 130, 246, 0.6) !important;
                                border: 2px solid #3b82f6 !important;
                            }
                        </style>
                    </head>
                    <body class="bg-slate-50 min-h-screen">
                        <div class="flex">
                            <!-- Sidebar -->
                            <?php include 'sidebar.php'; ?>
                            <!-- Main Content -->
                            <main class="flex-1 ml-64 min-h-screen p-8 bg-gradient-to-br from-blue-50 via-white to-slate-100">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                                    <h1 class="text-3xl font-extrabold flex items-center gap-3 text-blue-900">
                                        <i data-lucide="calendar" class="w-8 h-8 text-blue-600"></i>
                                        <?php echo __('calendar.title', 'Due Date Calendar'); ?>
                                    </h1>
                                    <div class="flex gap-2 flex-wrap">
                                        <button onclick="window.location.href='index.php'" class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-lg font-semibold shadow-sm hover:bg-blue-50 transition flex items-center gap-2">
                                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                                        </button>
                                        <button onclick="window.location.href='edit_case.php'" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold shadow-sm hover:bg-blue-700 transition flex items-center gap-2">
                                            <i data-lucide="plus-circle" class="w-4 h-4"></i> New Case
                                        </button>
                                        <button onclick="window.location.reload()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg font-semibold shadow-sm hover:bg-blue-100 transition flex items-center gap-2">
                                            <i data-lucide="refresh-ccw" class="w-4 h-4"></i> Refresh
                                        </button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                                    <div class="bg-white rounded-2xl shadow border border-blue-100 p-6 flex flex-col items-center">
                                        <div class="text-3xl font-bold text-blue-600 mb-1" id="stat-total-cases">0</div>
                                        <div class="text-xs font-semibold text-slate-500 uppercase">Total Cases</div>
                                    </div>
                                    <div class="bg-white rounded-2xl shadow border border-blue-100 p-6 flex flex-col items-center">
                                        <div class="text-3xl font-bold text-emerald-600 mb-1" id="stat-due-today">0</div>
                                        <div class="text-xs font-semibold text-slate-500 uppercase">Due Today</div>
                                    </div>
                                    <div class="bg-white rounded-2xl shadow border border-blue-100 p-6 flex flex-col items-center">
                                        <div class="text-3xl font-bold text-orange-500 mb-1" id="stat-overdue">0</div>
                                        <div class="text-xs font-semibold text-slate-500 uppercase">Overdue</div>
                                    </div>
                                    <div class="bg-white rounded-2xl shadow border border-blue-100 p-6 flex flex-col items-center">
                                        <div class="text-3xl font-bold text-slate-700 mb-1" id="stat-upcoming">0</div>
                                        <div class="text-xs font-semibold text-slate-500 uppercase">Upcoming</div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-2xl shadow border border-slate-200 p-4">
                                    <div id="calendar"></div>
                                </div>
                                <div class="mt-8">
                                    <h2 class="text-xl font-bold mb-4 flex items-center gap-2"><i data-lucide="alert-triangle" class="w-5 h-5 text-orange-600"></i> Overdue Cases</h2>
                                    <div class="overflow-x-auto rounded-xl shadow">
                                        <table class="min-w-full bg-white text-sm">
                                            <thead>
                                                <tr class="bg-orange-50 text-orange-900">
                                                    <th class="px-4 py-2 text-left">Plate</th>
                                                    <th class="px-4 py-2 text-left">Customer</th>
                                                    <th class="px-4 py-2 text-left">Due Date</th>
                                                    <th class="px-4 py-2 text-left">Status</th>
                                                    <th class="px-4 py-2 text-left">Repair Status</th>
                                                    <th class="px-4 py-2 text-left">Phone</th>
                                                    <th class="px-4 py-2 text-left">Amount</th>
                                                    <th class="px-4 py-2 text-left">Service Date</th>
                                                    <th class="px-4 py-2 text-left">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $overdue_cases = array_filter($cases, function($c) {
                                                    return strtotime($c['due_date']) < time() && $c['status'] !== 'Completed';
                                                });
                                                foreach ($overdue_cases as $c): 
                                                ?>
                                                <tr class="border-b hover:bg-orange-50 transition">
                                                    <td class="px-4 py-2 font-mono font-bold text-orange-700"><?php echo htmlspecialchars($c['plate']); ?></td>
                                                    <td class="px-4 py-2"><?php echo htmlspecialchars($c['name']); ?></td>
                                                    <td class="px-4 py-2"><?php echo date('M j, Y H:i', strtotime($c['due_date'])); ?></td>
                                                    <td class="px-4 py-2">
                                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-bold <?php
                                                            if ($c['status'] === 'Completed') echo 'bg-emerald-100 text-emerald-700';
                                                            elseif ($c['status'] === 'Issue') echo 'bg-red-100 text-red-700';
                                                            else echo 'bg-orange-100 text-orange-700';
                                                        ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-bold <?php
                                                            $rs = $c['repair_status'] ?? '';
                                                            if ($rs === 'Repair Completed') echo 'bg-emerald-100 text-emerald-700';
                                                            elseif ($rs === 'In Progress') echo 'bg-blue-100 text-blue-700';
                                                            elseif ($rs === 'Parts Waiting') echo 'bg-yellow-100 text-yellow-700';
                                                            elseif ($rs === 'Repair Started') echo 'bg-purple-100 text-purple-700';
                                                            else echo 'bg-gray-100 text-gray-700';
                                                        ?>"><?php echo htmlspecialchars($rs ?: 'Not Started'); ?></span>
                                                    </td>
                                                    <td class="px-4 py-2"><?php echo htmlspecialchars($c['phone']); ?></td>
                                                    <td class="px-4 py-2 font-mono text-slate-700"><?php echo htmlspecialchars($c['amount']); ?>₾</td>
                                                    <td class="px-4 py-2"><?php echo $c['service_date'] ? date('M j, Y H:i', strtotime($c['service_date'])) : '-'; ?></td>
                                                    <td class="px-4 py-2">
                                                        <a href="edit_case.php?id=<?php echo $c['id']; ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-orange-600 text-white text-xs font-bold hover:bg-orange-700 transition"><i data-lucide="edit-2" class="w-3 h-3"></i> Edit</a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($overdue_cases)): ?>
                                                <tr>
                                                    <td colspan="8" class="px-4 py-8 text-center text-slate-500">No overdue cases</td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="mt-8">
                                    <div class="overflow-x-auto rounded-xl shadow">
                                        <table class="min-w-full bg-white text-sm">
                                            <thead>
                                                <tr class="bg-blue-50 text-blue-900">
                                                    <th class="px-4 py-2 text-left">Plate</th>
                                                    <th class="px-4 py-2 text-left">Customer</th>
                                                    <th class="px-4 py-2 text-left">Due Date</th>
                                                    <th class="px-4 py-2 text-left">Status</th>
                                                    <th class="px-4 py-2 text-left">Repair Status</th>
                                                    <th class="px-4 py-2 text-left">Phone</th>
                                                    <th class="px-4 py-2 text-left">Amount</th>
                                                    <th class="px-4 py-2 text-left">Service Date</th>
                                                    <th class="px-4 py-2 text-left">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cases as $c): ?>
                                                <tr class="border-b hover:bg-blue-50 transition">
                                                    <td class="px-4 py-2 font-mono font-bold text-blue-700"><?php echo htmlspecialchars($c['plate']); ?></td>
                                                    <td class="px-4 py-2"><?php echo htmlspecialchars($c['name']); ?></td>
                                                    <td class="px-4 py-2"><?php echo date('M j, Y H:i', strtotime($c['due_date'])); ?></td>
                                                    <td class="px-4 py-2">
                                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-bold <?php
                                                            if ($c['status'] === 'Completed') echo 'bg-emerald-100 text-emerald-700';
                                                            elseif ($c['status'] === 'Issue') echo 'bg-red-100 text-red-700';
                                                            elseif (strtotime($c['due_date']) < strtotime('today')) echo 'bg-orange-100 text-orange-700';
                                                            else echo 'bg-blue-100 text-blue-700';
                                                        ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-bold <?php
                                                            $rs = $c['repair_status'] ?? '';
                                                            if ($rs === 'Repair Completed') echo 'bg-emerald-100 text-emerald-700';
                                                            elseif ($rs === 'In Progress') echo 'bg-blue-100 text-blue-700';
                                                            elseif ($rs === 'Parts Waiting') echo 'bg-yellow-100 text-yellow-700';
                                                            elseif ($rs === 'Repair Started') echo 'bg-purple-100 text-purple-700';
                                                            else echo 'bg-gray-100 text-gray-700';
                                                        ?>"><?php echo htmlspecialchars($rs ?: 'Not Started'); ?></span>
                                                    </td>
                                                    <td class="px-4 py-2"><?php echo htmlspecialchars($c['phone']); ?></td>
                                                    <td class="px-4 py-2 font-mono text-slate-700"><?php echo htmlspecialchars($c['amount']); ?>₾</td>
                                                    <td class="px-4 py-2"><?php echo $c['service_date'] ? date('M j, Y H:i', strtotime($c['service_date'])) : '-'; ?></td>
                                                    <td class="px-4 py-2">
                                                        <a href="edit_case.php?id=<?php echo $c['id']; ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition"><i data-lucide="edit-2" class="w-3 h-3"></i> Edit</a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </main>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var calendarEl = document.getElementById('calendar');
                                var calendar = new FullCalendar.Calendar(calendarEl, {
                                    initialView: 'dayGridMonth',
                                    height: 700,
                                    headerToolbar: {
                                        left: 'prev,next today',
                                        center: 'title',
                                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                                    },
                                    events: [
                                        <?php foreach ($cases as $c): ?>
                                        {
                                            title: '<?php echo addslashes($c['plate']); ?>',
                                            start: '<?php echo date('Y-m-d\TH:i:s', strtotime($c['due_date'])); ?>',
                                            url: 'edit_case.php?id=<?php echo $c['id']; ?>',
                                            color: '<?php echo ($c['status'] === 'Completed') ? '#22c55e' : (($c['status'] === 'Issue') ? '#ef4444' : ((strtotime($c['due_date']) < strtotime('today')) ? '#f59e42' : '#2563eb')); ?>',
                                            className: '<?php echo ($c['status'] === 'Completed') ? 'completed-case' : ((date('Y-m-d', strtotime($c['due_date'])) === date('Y-m-d')) ? 'due-today' : ''); ?>',
                                            extendedProps: {
                                                status: '<?php echo addslashes($c['status']); ?>',
                                                name: '<?php echo addslashes($c['name']); ?>',
                                                phone: '<?php echo addslashes($c['phone']); ?>',
                                                amount: '<?php echo addslashes($c['amount']); ?>',
                                                serviceDate: '<?php echo addslashes($c['service_date']); ?>',
                                                franchise: '<?php echo addslashes($c['franchise']); ?>',
                                                userResponse: '<?php echo addslashes($c['user_response']); ?>',
                                                rescheduleDate: '<?php echo addslashes($c['reschedule_date']); ?>',
                                                rescheduleComment: '<?php echo addslashes($c['reschedule_comment']); ?>',
                                                createdAt: '<?php echo addslashes($c['created_at']); ?>',
                                                repairStatus: '<?php echo addslashes($c['repair_status'] ?? ''); ?>',
                                                assignedMechanic: '<?php echo addslashes($c['assigned_mechanic'] ?? ''); ?>',
                                                repairStartDate: '<?php echo addslashes($c['repair_start_date'] ?? ''); ?>',
                                                repairEndDate: '<?php echo addslashes($c['repair_end_date'] ?? ''); ?>',
                                                repairNotes: '<?php echo addslashes($c['repair_notes'] ?? ''); ?>',
                                                repairParts: '<?php echo addslashes(json_encode($c['repair_parts'] ?? [])); ?>',
                                                repairLabor: '<?php echo addslashes(json_encode($c['repair_labor'] ?? [])); ?>',
                                                repairActivityLog: '<?php echo addslashes(json_encode($c['repair_activity_log'] ?? [])); ?>'
                                            }
                                        },
                                        <?php endforeach; ?>
                                    ],
                                    eventClick: function(info) {
                                        info.jsEvent.preventDefault();
                                        if (info.event.url) {
                                            window.location.href = info.event.url;
                                        }
                                    },
                                    eventDidMount: function(info) {
                                        // Tooltip for event details
                                        var tooltip = document.createElement('div');
                                        tooltip.className = 'absolute z-50 p-2 bg-white border border-slate-200 rounded shadow text-xs text-slate-700 hidden';
                                        tooltip.innerHTML =
                                            '<div><b>Plate:</b> ' + info.event.title + '</div>' +
                                            '<div><b>Customer:</b> ' + info.event.extendedProps.name + '</div>' +
                                            '<div><b>Status:</b> ' + info.event.extendedProps.status + '</div>' +
                                            '<div><b>Due:</b> ' + info.event.start.toLocaleString() + '</div>' +
                                            '<div><b>Phone:</b> ' + info.event.extendedProps.phone + '</div>' +
                                            '<div><b>Amount:</b> ' + info.event.extendedProps.amount + '₾</div>' +
                                            '<div><b>Franchise:</b> ' + (info.event.extendedProps.franchise ? info.event.extendedProps.franchise + '₾' : '-') + '</div>' +
                                            '<div><b>Service Date:</b> ' + (info.event.extendedProps.serviceDate ? info.event.extendedProps.serviceDate : '-') + '</div>' +
                                            '<div><b>User Response:</b> ' + (info.event.extendedProps.userResponse ? info.event.extendedProps.userResponse : '-') + '</div>' +
                                            '<div><b>Reschedule Date:</b> ' + (info.event.extendedProps.rescheduleDate ? info.event.extendedProps.rescheduleDate : '-') + '</div>' +
                                            '<div><b>Reschedule Comment:</b> ' + (info.event.extendedProps.rescheduleComment ? info.event.extendedProps.rescheduleComment : '-') + '</div>' +
                                            '<div><b>Created:</b> ' + (info.event.extendedProps.createdAt ? new Date(info.event.extendedProps.createdAt).toLocaleString() : '-') + '</div>' +
                                            '<div><b>Repair Status:</b> ' + (info.event.extendedProps.repairStatus ? info.event.extendedProps.repairStatus : '-') + '</div>' +
                                            '<div><b>Assigned Mechanic:</b> ' + (info.event.extendedProps.assignedMechanic ? info.event.extendedProps.assignedMechanic : '-') + '</div>' +
                                            '<div><b>Repair Start:</b> ' + (info.event.extendedProps.repairStartDate ? new Date(info.event.extendedProps.repairStartDate).toLocaleString() : '-') + '</div>' +
                                            '<div><b>Repair End:</b> ' + (info.event.extendedProps.repairEndDate ? new Date(info.event.extendedProps.repairEndDate).toLocaleString() : '-') + '</div>' +
                                            '<div><b>Repair Notes:</b> ' + (info.event.extendedProps.repairNotes ? info.event.extendedProps.repairNotes : '-') + '</div>';
                                        document.body.appendChild(tooltip);
                                        info.el.addEventListener('mouseenter', function(e) {
                                            tooltip.style.left = (e.pageX + 10) + 'px';
                                            tooltip.style.top = (e.pageY + 10) + 'px';
                                            tooltip.classList.remove('hidden');
                                        });
                                        info.el.addEventListener('mousemove', function(e) {
                                            tooltip.style.left = (e.pageX + 10) + 'px';
                                            tooltip.style.top = (e.pageY + 10) + 'px';
                                        });
                                        info.el.addEventListener('mouseleave', function() {
                                            tooltip.classList.add('hidden');
                                        });
                                    }
                                });
                                calendar.render();
                                // Stats
                                var total = <?php echo count($cases); ?>;
                                var today = 0, overdue = 0, upcoming = 0;
                                var now = new Date();
                                <?php foreach ($cases as $c): ?>
                                    (function(){
                                        var status = '<?php echo addslashes($c['status']); ?>';
                                        if (status === 'Completed') return; // Exclude completed cases from active counts
                                        var due = new Date('<?php echo date('Y-m-d\TH:i:s', strtotime($c['due_date'])); ?>');
                                        if (due.toDateString() === now.toDateString()) today++;
                                        else if (due < now) overdue++;
                                        else upcoming++;
                                    })();
                                <?php endforeach; ?>
                                document.getElementById('stat-total-cases').textContent = total;
                                document.getElementById('stat-due-today').textContent = today;
                                document.getElementById('stat-overdue').textContent = overdue;
                                document.getElementById('stat-upcoming').textContent = upcoming;
                                if(window.lucide) lucide.createIcons();
                            });
                        </script>
                    </body>
                    </html>
