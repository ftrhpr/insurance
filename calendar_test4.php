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

// Test fetching full case data
$startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
$daysInMonth = date('t', strtotime("$currentYear-$currentMonth-01"));
$endDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $daysInMonth);

$fetchSuccess = false;
$cases = [];
$caseDetails = '';
try {
    $stmt = $pdo->prepare("
        SELECT id, plate, name, amount, status, due_date
        FROM transfers
        WHERE due_date IS NOT NULL
        AND DATE(due_date) BETWEEN ? AND ?
        ORDER BY due_date ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $fetchSuccess = true;

    // Build debug output
    $caseDetails = '<div class="space-y-2">';
    foreach ($cases as $case) {
        $caseDetails .= '<div class="p-2 bg-gray-50 rounded text-sm">';
        $caseDetails .= '<strong>' . htmlspecialchars($case['plate'] ?? 'N/A') . '</strong> - ';
        $caseDetails .= htmlspecialchars($case['name'] ?? 'N/A') . '<br>';
        $caseDetails .= 'Due: ' . htmlspecialchars($case['due_date'] ?? 'N/A');
        $caseDetails .= '</div>';
    }
    $caseDetails .= '</div>';
} catch (PDOException $e) {
    $fetchSuccess = false;
    $caseDetails = '<p class="text-red-600">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(get_current_language() ?: 'en'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Test 4 - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.378.0/dist/umd/lucide.js"></script>
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <style>
        .calendar-day { min-height: 100px; padding: 8px; }
        .calendar-day.today { background-color: #fef3c7; }
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

            <!-- Debug Info -->
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-blue-800">Fetch success: <?php echo $fetchSuccess ? 'YES' : 'NO'; ?></p>
                <p class="text-blue-800">Cases fetched: <?php echo count($cases); ?></p>
                <p class="text-sm text-blue-600">Date range: <?php echo $startDate; ?> to <?php echo $endDate; ?></p>
                <?php if ($fetchSuccess && count($cases) > 0): ?>
                    <div class="mt-4">
                        <p class="text-blue-800 font-semibold">Case Details:</p>
                        <?php echo $caseDetails; ?>
                    </div>
                <?php endif; ?>
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
                    $isToday = ($day == date('j') && $currentMonth == date('n') && $currentYear == date('Y'));
                    $dayClass = 'calendar-day bg-white rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors';
                    if ($isToday) $dayClass .= ' today';

                    echo "<div class='{$dayClass}'>";
                    echo "<div class='font-semibold text-slate-800 mb-1'>{$day}</div>";
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
    </main>

    <script>
        // Initialize icons
        if (window.lucide) {
            lucide.createIcons();
        }
    </script>
</body>
</html>