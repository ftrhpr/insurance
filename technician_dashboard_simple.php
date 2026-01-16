<?php
require_once 'session_config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Only allow technicians to access this page
if ($_SESSION['role'] !== 'technician') {
    header('Location: index.php');
    exit();
}

require_once 'language.php';

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('technician_dashboard.title', 'Technician Dashboard - OTOMOTORS'); ?></title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/fonts/include_fonts.php'; ?>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['BPG Arial Caps', 'BPG Arial', 'Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <?php include 'header.php'; ?>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Page Header -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-slate-900 mb-2"><?php echo __('technician_dashboard.title', 'Technician Dashboard'); ?></h1>
                        <p class="text-slate-600"><?php echo __('technician_dashboard.subtitle', 'Manage your assigned repair cases and workflow'); ?></p>
                    </div>

                    <!-- Status Message -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <div class="lucide lucide-info text-blue-600 mr-3" style="width: 20px; height: 20px;"></div>
                            <div>
                                <h3 class="text-sm font-medium text-blue-800">Welcome, <?php echo htmlspecialchars($current_user_name); ?>!</h3>
                                <p class="text-sm text-blue-700 mt-1">You are logged in as a technician. This dashboard shows your assigned repair cases.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Workflow Section -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                        <h2 class="text-xl font-semibold text-slate-900 mb-4"><?php echo __('technician_dashboard.workflow', 'My Workflow Cases'); ?></h2>

                        <?php
                        require_once 'config.php';
                        try {
                            $pdo = getDBConnection();

                            // Get technician's assigned cases
                            $stmt = $pdo->prepare("
                                SELECT t.*, v.make, v.model, v.year
                                FROM transfers t
                                LEFT JOIN vehicles v ON t.vehicle_id = v.id
                                WHERE t.technician_id = ? AND t.status IN ('Processing', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Completed')
                                ORDER BY
                                    CASE t.status
                                        WHEN 'Processing' THEN 1
                                        WHEN 'Parts Ordered' THEN 2
                                        WHEN 'Parts Arrived' THEN 3
                                        WHEN 'Scheduled' THEN 4
                                        WHEN 'Completed' THEN 5
                                    END,
                                    t.service_date ASC
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                            $cases = [];
                        }
                        ?>

                        <?php if (empty($cases)): ?>
                            <div class="text-center py-12">
                                <div class="lucide lucide-wrench text-slate-400 mb-4" style="width: 48px; height: 48px; margin: 0 auto;"></div>
                                <h3 class="text-lg font-medium text-slate-900 mb-2"><?php echo __('technician_dashboard.no_cases', 'No Assigned Cases'); ?></h3>
                                <p class="text-slate-600"><?php echo __('technician_dashboard.no_cases_desc', 'You don\'t have any repair cases assigned to you yet. Contact your manager for case assignments.'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <?php foreach ($cases as $case): ?>
                                    <div class="border border-slate-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                        <div class="flex justify-between items-start mb-3">
                                            <div>
                                                <h3 class="font-semibold text-slate-900"><?php echo htmlspecialchars($case['plate']); ?></h3>
                                                <p class="text-sm text-slate-600">
                                                    <?php echo htmlspecialchars(($case['make'] ? $case['make'] . ' ' : '') . ($case['model'] ?: 'Unknown Model')); ?>
                                                </p>
                                            </div>
                                            <span class="px-2 py-1 text-xs rounded-full <?php
                                                $statusColors = [
                                                    'Processing' => 'bg-yellow-100 text-yellow-800',
                                                    'Parts Ordered' => 'bg-blue-100 text-blue-800',
                                                    'Parts Arrived' => 'bg-green-100 text-green-800',
                                                    'Scheduled' => 'bg-purple-100 text-purple-800',
                                                    'Completed' => 'bg-emerald-100 text-emerald-800'
                                                ];
                                                echo $statusColors[$case['status']] ?? 'bg-slate-100 text-slate-800';
                                            ?>">
                                                <?php echo htmlspecialchars($case['status']); ?>
                                            </span>
                                        </div>

                                        <div class="space-y-2 text-sm">
                                            <p><span class="font-medium"><?php echo __('technician_dashboard.customer', 'Customer:'); ?></span> <?php echo htmlspecialchars($case['name']); ?></p>
                                            <?php if ($case['service_date']): ?>
                                                <p><span class="font-medium"><?php echo __('technician_dashboard.service_date', 'Service Date:'); ?></span> <?php echo date('M j, Y', strtotime($case['service_date'])); ?></p>
                                            <?php endif; ?>
                                            <p><span class="font-medium"><?php echo __('technician_dashboard.amount', 'Amount:'); ?></span> <?php echo number_format($case['amount'], 2); ?> GEL</p>
                                        </div>

                                        <div class="mt-4">
                                            <button onclick="viewCaseDetails(<?php echo $case['id']; ?>)" class="w-full px-3 py-2 bg-primary-500 text-white text-sm rounded-lg hover:bg-primary-600 transition-colors">
                                                <?php echo __('technician_dashboard.view_details', 'View Details'); ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        function viewCaseDetails(caseId) {
            // For now, just show an alert. In a real implementation, this would open the workflow page
            alert('Case details for ID: ' + caseId + '\n\nThis feature would open the detailed workflow view for this case.');
        }

        // Auto-refresh cases every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>