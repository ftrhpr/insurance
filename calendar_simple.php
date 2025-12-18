<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get month name
$monthName = date('F', strtotime("$currentYear-$currentMonth-01"));

// Fetch cases with due dates for the current month
$startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
$daysInMonth = date('t', strtotime("$currentYear-$currentMonth-01"));
$endDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $daysInMonth);

$cases = [];
try {
    // Check if due_date column exists first
    $stmt = $pdo->prepare("SHOW COLUMNS FROM transfers LIKE 'due_date'");
    $stmt->execute();
    $columnExists = $stmt->fetch();

    if ($columnExists) {
        $stmt = $pdo->prepare("
            SELECT id, plate, name, amount, status, due_date
            FROM transfers
            WHERE due_date IS NOT NULL
            AND DATE(due_date) BETWEEN ? AND ?
            ORDER BY due_date ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // If there's any database error, show empty calendar
    $cases = [];
}

// Group cases by date
$casesByDate = [];
foreach ($cases as $case) {
    if (!isset($case['due_date']) || empty($case['due_date'])) continue;
    $date = date('Y-m-d', strtotime($case['due_date']));
    if ($date === false) continue;
    if (!isset($casesByDate[$date])) {
        $casesByDate[$date] = [];
    }
    $casesByDate[$date][] = $case;
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(get_current_language() ?: 'en'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(__('calendar.title', 'Calendar View')); ?> - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.378.0/dist/umd/lucide.js"></script>
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <style>
        .calendar-day { min-height: 100px; padding: 8px; }
        .calendar-day.today { background-color: #fef3c7; }
        .calendar-day.has-cases { background-color: #fee2e2; border: 2px solid #ef4444; }
        .case-item { font-size: 10px; line-height: 1.2; margin-bottom: 2px; }
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
                // Get first day of month and number of days
                $firstDayOfMonth = date('N', strtotime("$currentYear-$currentMonth-01")); // 1=Monday, 7=Sunday
                $daysInMonth = date('t', strtotime("$currentYear-$currentMonth-01"));

                // Empty cells for days before the first day of the month
                for ($i = 1; $i < $firstDayOfMonth; $i++) {
                    echo "<div class='calendar-day bg-slate-50 rounded-lg border border-slate-100'></div>";
                }

                // Days of the month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                    $isToday = ($day == date('j') && $currentMonth == date('n') && $currentYear == date('Y'));
                    $hasCases = isset($casesByDate[$dateStr]);
                    $dayClass = 'calendar-day bg-white rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors';
                    if ($isToday) $dayClass .= ' today';
                    if ($hasCases) $dayClass .= ' has-cases';

                    echo "<div class='{$dayClass}' onclick=\"showDayDetails('{$dateStr}')\">";
                    echo "<div class='font-semibold text-slate-800 mb-1'>{$day}</div>";

                    if ($hasCases) {
                        $dateCases = $casesByDate[$dateStr];
                        $caseCount = count($dateCases);
                        echo "<div class='text-xs text-red-600 font-medium mb-1'>{$caseCount} case" . ($caseCount > 1 ? 's' : '') . "</div>";

                        // Show up to 2 cases
                        $casesToShow = array_slice($dateCases, 0, 2);
                        foreach ($casesToShow as $case) {
                            if (is_array($case)) {
                                $plate = isset($case['plate']) ? strval($case['plate']) : '';
                                $name = isset($case['name']) ? strval($case['name']) : '';
                                // Limit name length safely
                                if (strlen($name) > 15) {
                                    $name = substr($name, 0, 12) . '...';
                                }
                                echo "<div class='case-item bg-red-50 text-red-800 rounded px-1 py-0.5 text-xs'>{$plate} - {$name}</div>";
                            }
                        }

                        if ($caseCount > 2) {
                            echo "<div class='text-xs text-slate-500'>+{$caseCount - 2} more</div>";
                        }
                    }

                    echo "</div>";
                }

                // Empty cells for days after the last day of the month
                $totalCells = $firstDayOfMonth - 1 + $daysInMonth;
                $remainingCells = 42 - $totalCells; // 6 weeks * 7 days = 42 cells
                for ($i = 0; $i < $remainingCells; $i++) {
                    echo "<div class='calendar-day bg-slate-50 rounded-lg border border-slate-100'></div>";
                }
                ?>
            </div>
        </div>

        <!-- Day Details Modal -->
        <div id="day-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                    <div class="p-6 border-b border-slate-200">
                        <div class="flex items-center justify-between">
                            <h3 id="modal-date-title" class="text-xl font-bold text-slate-800">Cases Due</h3>
                            <button onclick="closeDayModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                                <i data-lucide="x" class="w-5 h-5 text-slate-600"></i>
                            </button>
                        </div>
                    </div>
                    <div id="modal-cases-content" class="p-6">
                        <!-- Cases will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function showDayDetails(dateStr) {
            // Safely encode the cases data
            const casesData = <?php
                $jsonData = json_encode($casesByDate, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                if ($jsonData === false) {
                    echo '{}'; // Fallback to empty object if encoding fails
                } else {
                    echo $jsonData;
                }
            ?>;
            const dateCases = casesData[dateStr] || [];

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
                content = '<p class="text-slate-500">No cases due on this date.</p>';
            } else {
                content = '<div class="space-y-4">';
                dateCases.forEach(caseItem => {
                    if (caseItem && typeof caseItem === 'object') {
                        const plate = (caseItem.plate || '').toString().replace(/[<>&"']/g, '');
                        const name = (caseItem.name || '').toString().replace(/[<>&"']/g, '');
                        const amount = caseItem.amount || '';
                        const status = caseItem.status || '';
                        const dueDate = caseItem.due_date || '';
                        const id = caseItem.id || '';

                        content += `
                            <div class="border border-slate-200 rounded-lg p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <div>
                                        <h4 class="font-semibold text-slate-800">${plate} - ${name}</h4>
                                        <p class="text-sm text-slate-600">Amount: ${amount}</p>
                                        <p class="text-sm text-slate-600">Status: ${status}</p>
                                    </div>
                                    ${id ? `<a href="edit_case.php?id=${id}" class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors">View Details</a>` : ''}
                                </div>
                                ${dueDate ? `<div class="text-xs text-slate-400">Due: ${new Date(dueDate).toLocaleDateString('en-US')} ${new Date(dueDate).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}</div>` : ''}
                            </div>
                        `;
                    }
                });
                content += '</div>';
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