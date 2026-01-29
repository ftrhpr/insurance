<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Only allow technicians and admins
$allowed_roles = ['technician', 'admin', 'manager'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header('Location: index.php');
    exit();
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'];

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get filter parameters
$selected_month = $_GET['month'] ?? date('Y-m');
$search_customer = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT 
    name as customer_name,
    plate,
    nachrebi_qty,
    amount,
    franchise,
    status,
    service_date,
    created_at,
    id
FROM transfers 
WHERE nachrebi_qty > 0";

$params = [];

// Month filter
if ($selected_month) {
    $query .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $params[] = $selected_month;
}

// Customer search filter
if ($search_customer) {
    $query .= " AND (name LIKE ? OR plate LIKE ?)";
    $params[] = "%$search_customer%";
    $params[] = "%$search_customer%";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_nachrebi = array_sum(array_column($records, 'nachrebi_qty'));
$total_amount = array_sum(array_column($records, 'amount'));

// Get available months for filter dropdown
$months_stmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month 
    FROM transfers 
    WHERE nachrebi_qty > 0 
    ORDER BY month DESC");
$available_months = $months_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ნაჭრების რაოდენობა - რეპორტი</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-border { border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body class="bg-slate-50">
    
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-slate-600 hover:text-slate-900">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                    </a>
                    <h1 class="text-2xl font-bold text-slate-900">ნაჭრების რაოდენობა - რეპორტი</h1>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-sm text-slate-600"><?= htmlspecialchars($current_user_name) ?></span>
                    <span class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700"><?= htmlspecialchars($current_user_role) ?></span>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6 no-print">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                
                <!-- Month Filter -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>
                        თვე
                    </label>
                    <select name="month" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">ყველა თვე</option>
                        <?php foreach ($available_months as $month): ?>
                            <option value="<?= $month ?>" <?= $month === $selected_month ? 'selected' : '' ?>>
                                <?= date('F Y', strtotime($month . '-01')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Customer Search -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        <i data-lucide="search" class="w-4 h-4 inline mr-1"></i>
                        მომხმარებლის ძებნა
                    </label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($search_customer) ?>"
                        placeholder="სახელი ან ნომერი..."
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <!-- Actions -->
                <div class="flex items-end space-x-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center">
                        <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                        ფილტრი
                    </button>
                    <a href="nachrebi_report.php" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </a>
                    <button type="button" onclick="window.print()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">სულ ჩანაწერები</p>
                        <p class="text-3xl font-bold text-slate-900"><?= count($records) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="file-text" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">სულ ნაჭრები</p>
                        <p class="text-3xl font-bold text-emerald-600"><?= number_format($total_nachrebi, 2) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="package" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">სულ თანხა</p>
                        <p class="text-3xl font-bold text-blue-600">₾<?= number_format($total_amount, 2) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="dollar-sign" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden print-border">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">მომხმარებელი</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">მანქანის ნომერი</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">ნაჭრების რაოდ.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">თანხა</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">ფრანშიზა</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">სტატუსი</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">თარიღი</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (count($records) > 0): ?>
                            <?php foreach ($records as $record): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 text-sm font-medium text-slate-900">#<?= $record['id'] ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-900"><?= htmlspecialchars($record['customer_name']) ?></td>
                                    <td class="px-4 py-3 text-sm font-mono text-slate-900"><?= htmlspecialchars($record['plate']) ?></td>
                                    <td class="px-4 py-3 text-sm font-bold text-emerald-600"><?= number_format($record['nachrebi_qty'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-900">₾<?= number_format($record['amount'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-600">₾<?= number_format($record['franchise'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium
                                            <?php 
                                            switch($record['status']) {
                                                case 'New': echo 'bg-gray-100 text-gray-700'; break;
                                                case 'Processing': echo 'bg-blue-100 text-blue-700'; break;
                                                case 'Called': echo 'bg-purple-100 text-purple-700'; break;
                                                case 'Parts ordered': echo 'bg-orange-100 text-orange-700'; break;
                                                case 'Parts arrived': echo 'bg-cyan-100 text-cyan-700'; break;
                                                case 'Already in service': echo 'bg-indigo-100 text-indigo-700'; break;
                                                case 'Done': echo 'bg-green-100 text-green-700'; break;
                                                case 'Issue': echo 'bg-red-100 text-red-700'; break;
                                                default: echo 'bg-slate-100 text-slate-700';
                                            }
                                            ?>">
                                            <?= htmlspecialchars($record['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        <?= date('d/m/Y', strtotime($record['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <i data-lucide="inbox" class="w-12 h-12 mb-3"></i>
                                        <p class="text-sm">მონაცემები არ მოიძებნა</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
