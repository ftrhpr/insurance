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

// Get current user info
$current_user_name = $_SESSION['full_name'] ?? 'Manager';
$current_user_role = $_SESSION['role'] ?? 'manager';

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get current month/year from URL or default to current
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month/year
if ($currentMonth < 1 || $currentMonth > 12) $currentMonth = date('n');
if ($currentYear < 2020 || $currentYear > 2030) $currentYear = date('Y');

// Calculate previous/next month
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Fetch cases with due dates for the current month
$startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
$endDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear));

$stmt = $pdo->prepare("
    SELECT id, plate, name, amount, status, due_date
    FROM transfers
    WHERE due_date IS NOT NULL
    AND DATE(due_date) BETWEEN ? AND ?
    ORDER BY due_date ASC
");
$stmt->execute([$startDate, $endDate]);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group cases by date
$casesByDate = [];
foreach ($cases as $case) {
    $date = date('Y-m-d', strtotime($case['due_date']));
    if (!isset($casesByDate[$date])) {
        $casesByDate[$date] = [];
    }
    $casesByDate[$date][] = $case;
}

// Get month name
$monthName = date('F', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_language(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('calendar.title', 'Calendar View'); ?> - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.378.0/dist/umd/lucide.js"></script>
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 2px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
        .calendar-day { min-height: 120px; }
        .calendar-day.today { background-color: #fef3c7; }
        .calendar-day.has-cases { background-color: #fee2e2; border: 2px solid #ef4444; }
        .case-item { font-size: 10px; line-height: 1.2; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="ml-64 p-8">
        <!-- Calendar Header -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-slate-800">
                    <?php echo $monthName . ' ' . $currentYear; ?>
                </h2>
                <div class="flex items-center gap-2">
                    <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors">
                        <i data-lucide="chevron-left" class="w-5 h-5 text-slate-600"></i>
                    </a>
                    <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="p-2 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors">
                        <i data-lucide="chevron-right" class="w-5 h-5 text-slate-600"></i>
                    </a>
                    <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors text-sm font-medium">
                        Today
                    </a>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="grid grid-cols-7 gap-1">
                <!-- Day Headers -->
                <?php
                $daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                foreach ($daysOfWeek as $day) {
                    echo "<div class='p-3 text-center font-semibold text-slate-600 bg-slate-100 rounded-lg'>{$day}</div>";
                }
                ?>

                <!-- Calendar Days -->
                <?php
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
                $firstDayOfMonth = date('N', strtotime("$currentYear-$currentMonth-01")); // 1=Monday, 7=Sunday
                $today = date('Y-m-d');

                // Empty cells for days before the first day of the month
                for ($i = 1; $i < $firstDayOfMonth; $i++) {
                    echo "<div class='calendar-day bg-slate-50 rounded-lg border border-slate-200'></div>";
                }

                // Days of the month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                    $isToday = ($dateStr === $today);
                    $hasCases = isset($casesByDate[$dateStr]);
                    $dayClasses = 'calendar-day p-3 bg-white rounded-lg border border-slate-200 hover:shadow-md transition-all cursor-pointer';
                    if ($isToday) $dayClasses .= ' today';
                    if ($hasCases) $dayClasses .= ' has-cases';

                    echo "<div class='{$dayClasses}' onclick='showDayDetails(\"{$dateStr}\")'>";
                    echo "<div class='font-semibold text-slate-800 mb-2'>{$day}</div>";

                    if ($hasCases) {
                        $caseCount = count($casesByDate[$dateStr]);
                        echo "<div class='space-y-1'>";
                        foreach (array_slice($casesByDate[$dateStr], 0, 3) as $case) {
                            $statusColors = [
                                'New' => 'bg-slate-100 text-slate-800',
                                'Processing' => 'bg-yellow-100 text-yellow-800',
                                'Called' => 'bg-purple-100 text-purple-800',
                                'Parts Ordered' => 'bg-indigo-100 text-indigo-800',
                                'Parts Arrived' => 'bg-teal-100 text-teal-800',
                                'Scheduled' => 'bg-orange-100 text-orange-800',
                                'Completed' => 'bg-emerald-100 text-emerald-800',
                                'Issue' => 'bg-red-100 text-red-800'
                            ];
                            $badgeClass = $statusColors[$case['status']] ?? 'bg-slate-100 text-slate-600';
                            echo "<div class='case-item {$badgeClass} px-1 py-0.5 rounded text-xs font-medium truncate' title='{$case['plate']} - {$case['name']}'>";
                            echo htmlspecialchars($case['plate']);
                            echo "</div>";
                        }
                        if ($caseCount > 3) {
                            echo "<div class='text-xs text-slate-500'>+{$caseCount - 3} more</div>";
                        }
                        echo "</div>";
                    }

                    echo "</div>";
                }

                // Empty cells for days after the last day of the month
                $totalCells = $firstDayOfMonth - 1 + $daysInMonth;
                $remainingCells = 42 - $totalCells; // 6 weeks * 7 days
                for ($i = 0; $i < $remainingCells; $i++) {
                    echo "<div class='calendar-day bg-slate-50 rounded-lg border border-slate-200'></div>";
                }
                ?>
            </div>
        </div>

    </main>

    <script>
        function showDayDetails(dateStr) {
            const cases = <?php echo json_encode($casesByDate); ?>;
            const dateCases = cases[dateStr] || [];

            const date = new Date(dateStr + 'T00:00:00');
            const formattedDate = date.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            document.getElementById('modal-date-title').textContent = `Cases Due - ${formattedDate}`;

            let content = '';
            if (dateCases.length === 0) {
                content = '<p class="text-gray-500 text-center py-8">No cases due on this date.</p>';
            } else {
                dateCases.forEach(caseItem => {
                    const statusColors = {
                        'New': 'bg-slate-100 text-slate-800',
                        'Processing': 'bg-yellow-100 text-yellow-800',
                        'Called': 'bg-purple-100 text-purple-800',
                        'Parts Ordered': 'bg-indigo-100 text-indigo-800',
                        'Parts Arrived': 'bg-teal-100 text-teal-800',
                        'Scheduled': 'bg-orange-100 text-orange-800',
                        'Completed': 'bg-emerald-100 text-emerald-800',
                        'Issue': 'bg-red-100 text-red-800'
                    };
                    const badgeClass = statusColors[caseItem.status] || 'bg-slate-100 text-slate-600';

                    content += `
                        <div class="bg-white border border-slate-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='edit_case.php?id=${caseItem.id}'">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-mono font-bold text-slate-800">${caseItem.plate}</span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${badgeClass}">
                                    ${caseItem.status}
                                </span>
                            </div>
                            <div class="text-sm text-slate-600 mb-2">${caseItem.name}</div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-emerald-600">${caseItem.amount}₾</span>
                                <span class="text-xs text-slate-400">${new Date(caseItem.due_date).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}</span>
                            </div>
                        </div>
                    `;
                });
            }

            document.getElementById('modal-cases-content').innerHTML = content;
            document.getElementById('day-modal').classList.remove('hidden');
        }

        function closeDayModal() {
            document.getElementById('day-modal').classList.add('hidden');
        }

        // Initialize icons
        if (window.lucide) {
            lucide.createIcons();
        }
    </script>
</body>
</html>