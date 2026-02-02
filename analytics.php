<?php
/**
 * Analytics Dashboard - OTOMOTORS Manager Portal
 * Comprehensive data analysis for cases, revenue, technicians, and performance metrics
 * 
 * @author Senior Developer
 * @version 2.0
 */

require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Only allow admin and manager roles
$allowed_roles = ['admin', 'manager'];
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

// ===== ANALYTICS DATA QUERIES =====

// Date range filter
$date_from = $_GET['from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['to'] ?? date('Y-m-d'); // Today
$year = $_GET['year'] ?? date('Y');

// 1. Overall Statistics
$overall_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_cases,
        COUNT(CASE WHEN status_id = 8 THEN 1 END) as completed_cases,
        COUNT(CASE WHEN status_id = 1 THEN 1 END) as new_cases,
        COUNT(CASE WHEN status_id NOT IN (8, 9) THEN 1 END) as active_cases,
        COUNT(CASE WHEN status_id = 9 THEN 1 END) as issue_cases,
        COALESCE(SUM(CASE WHEN status_id = 8 THEN amount ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN status_id = 8 THEN franchise ELSE 0 END), 0) as total_franchise,
        COALESCE(AVG(CASE WHEN status_id = 8 AND amount > 0 THEN amount END), 0) as avg_case_value,
        COUNT(CASE WHEN case_type = 'დაზღვევა' AND status_id = 8 THEN 1 END) as insurance_completed,
        COUNT(CASE WHEN case_type = 'საცალო' AND status_id = 8 THEN 1 END) as retail_completed,
        COALESCE(SUM(CASE WHEN case_type = 'დაზღვევა' AND status_id = 8 THEN amount ELSE 0 END), 0) as insurance_revenue,
        COALESCE(SUM(CASE WHEN case_type = 'საცალო' AND status_id = 8 THEN amount ELSE 0 END), 0) as retail_revenue
    FROM transfers
")->fetch(PDO::FETCH_ASSOC);

// 2. Monthly Revenue Trends (Last 12 months)
$monthly_revenue = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        DATE_FORMAT(created_at, '%b %Y') as month_label,
        COUNT(*) as total_cases,
        COUNT(CASE WHEN status_id = 8 THEN 1 END) as completed,
        COALESCE(SUM(CASE WHEN status_id = 8 THEN amount ELSE 0 END), 0) as revenue,
        COALESCE(SUM(CASE WHEN status_id = 8 AND case_type = 'დაზღვევა' THEN amount ELSE 0 END), 0) as insurance_rev,
        COALESCE(SUM(CASE WHEN status_id = 8 AND case_type = 'საცალო' THEN amount ELSE 0 END), 0) as retail_rev
    FROM transfers
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 3. Status Distribution
$status_distribution = $pdo->query("
    SELECT 
        COALESCE(s.name, t.status) as status_name,
        COALESCE(s.color, '#6B7280') as color,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM transfers), 1) as percentage
    FROM transfers t
    LEFT JOIN statuses s ON t.status_id = s.id AND s.type = 'case'
    GROUP BY t.status_id, s.name, s.color, t.status
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Technician Performance
$technician_stats = $pdo->query("
    SELECT 
        assigned_mechanic as technician,
        COUNT(*) as total_assigned,
        COUNT(CASE WHEN status_id = 8 THEN 1 END) as completed,
        COALESCE(SUM(CASE WHEN status_id = 8 THEN amount ELSE 0 END), 0) as revenue_generated,
        COALESCE(SUM(nachrebi_qty), 0) as total_nachrebi,
        COALESCE(AVG(CASE WHEN status_id = 8 THEN DATEDIFF(updated_at, created_at) END), 0) as avg_completion_days
    FROM transfers
    WHERE assigned_mechanic IS NOT NULL AND assigned_mechanic != ''
    GROUP BY assigned_mechanic
    ORDER BY completed DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Daily Activity (Last 30 days)
$daily_activity = $pdo->query("
    SELECT 
        DATE(created_at) as date,
        DATE_FORMAT(created_at, '%d %b') as date_label,
        COUNT(*) as new_cases,
        COUNT(CASE WHEN status_id = 8 THEN 1 END) as completed_cases
    FROM transfers
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 6. Case Type Analysis
$case_type_stats = $pdo->query("
    SELECT 
        COALESCE(case_type, 'Unknown') as case_type,
        COUNT(*) as total,
        COUNT(CASE WHEN status_id = 8 THEN 1 END) as completed,
        COALESCE(SUM(CASE WHEN status_id = 8 THEN amount ELSE 0 END), 0) as revenue,
        COALESCE(AVG(CASE WHEN status_id = 8 THEN amount END), 0) as avg_value
    FROM transfers
    GROUP BY case_type
")->fetchAll(PDO::FETCH_ASSOC);

// 7. Customer Response Analysis
$response_stats = $pdo->query("
    SELECT 
        COALESCE(user_response, 'No Response') as response,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM transfers WHERE user_response IS NOT NULL), 1) as percentage
    FROM transfers
    WHERE user_response IS NOT NULL
    GROUP BY user_response
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 8. Reviews Analysis
$reviews_stats = [];
try {
    $reviews_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_reviews,
            COALESCE(AVG(rating), 0) as avg_rating,
            COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
            COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
            COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
            COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
            COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
        FROM customer_reviews
    ")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $reviews_stats = [
        'total_reviews' => 0, 'avg_rating' => 0, 'five_star' => 0, 'four_star' => 0,
        'three_star' => 0, 'two_star' => 0, 'one_star' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0
    ];
}

// 9. Weekly Comparison
$weekly_comparison = $pdo->query("
    SELECT 
        'This Week' as period,
        COUNT(*) as cases,
        COALESCE(SUM(CASE WHEN status_id = 8 THEN amount ELSE 0 END), 0) as revenue
    FROM transfers
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    UNION ALL
    SELECT 
        'Last Week' as period,
        COUNT(*) as cases,
        COALESCE(SUM(CASE WHEN status_id = 8 THEN amount ELSE 0 END), 0) as revenue
    FROM transfers
    WHERE created_at >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
      AND created_at < DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
")->fetchAll(PDO::FETCH_ASSOC);

// 10. Top Vehicles by Amount
$top_vehicles = $pdo->query("
    SELECT 
        CONCAT(COALESCE(vehicle_make, ''), ' ', COALESCE(vehicle_model, '')) as vehicle,
        plate,
        name as customer,
        amount,
        status,
        created_at
    FROM transfers
    WHERE amount > 0
    ORDER BY amount DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 11. Parts & Services Analysis
$parts_services = $pdo->query("
    SELECT 
        COUNT(CASE WHEN repair_parts IS NOT NULL AND repair_parts != '' AND repair_parts != '[]' THEN 1 END) as cases_with_parts,
        COUNT(CASE WHEN repair_labor IS NOT NULL AND repair_labor != '' AND repair_labor != '[]' THEN 1 END) as cases_with_services,
        COUNT(*) as total_cases
    FROM transfers
")->fetch(PDO::FETCH_ASSOC);

// 12. Hour-by-hour activity pattern
$hourly_pattern = $pdo->query("
    SELECT 
        HOUR(created_at) as hour,
        COUNT(*) as count
    FROM transfers
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY HOUR(created_at)
    ORDER BY hour
")->fetchAll(PDO::FETCH_ASSOC);

// 13. Repair Status Distribution
$repair_status_dist = $pdo->query("
    SELECT 
        COALESCE(repair_status, 'Not Set') as repair_status,
        COUNT(*) as count
    FROM transfers
    WHERE status_id NOT IN (8, 9)
    GROUP BY repair_status
    ORDER BY count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 14. Nachrebi (Pieces) Analysis
$nachrebi_stats = $pdo->query("
    SELECT 
        COALESCE(SUM(nachrebi_qty), 0) as total_nachrebi,
        COALESCE(SUM(nachrebi_qty * 77), 0) as total_nachrebi_value,
        COUNT(CASE WHEN nachrebi_qty > 0 THEN 1 END) as cases_with_nachrebi,
        COALESCE(AVG(CASE WHEN nachrebi_qty > 0 THEN nachrebi_qty END), 0) as avg_nachrebi
    FROM transfers
    WHERE status_id = 8
")->fetch(PDO::FETCH_ASSOC);

// 15. Conversion Rate by Status
$conversion_funnel = $pdo->query("
    SELECT 
        COUNT(CASE WHEN status_id >= 1 THEN 1 END) as stage_new,
        COUNT(CASE WHEN status_id >= 2 THEN 1 END) as stage_processing,
        COUNT(CASE WHEN status_id >= 3 THEN 1 END) as stage_called,
        COUNT(CASE WHEN status_id >= 6 THEN 1 END) as stage_scheduled,
        COUNT(CASE WHEN status_id = 8 THEN 1 END) as stage_completed
    FROM transfers
")->fetch(PDO::FETCH_ASSOC);

// Prepare data for JavaScript charts
$chartData = [
    'monthlyRevenue' => $monthly_revenue,
    'statusDistribution' => $status_distribution,
    'technicianStats' => $technician_stats,
    'dailyActivity' => $daily_activity,
    'hourlyPattern' => $hourly_pattern,
    'repairStatusDist' => $repair_status_dist
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - OTOMOTORS</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/fonts/include_fonts.php'; ?>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- CountUp.js for animated numbers -->
    <script src="https://cdn.jsdelivr.net/npm/countup.js@2.8.0/dist/countUp.umd.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1', 800: '#075985', 900: '#0c4a6e' },
                        accent: { 50: '#fdf4ff', 100: '#fae8ff', 200: '#f5d0fe', 300: '#f0abfc', 400: '#e879f9', 500: '#d946ef', 600: '#c026d3', 700: '#a21caf', 800: '#86198f', 900: '#701a75' },
                        emerald: { 50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857', 800: '#065f46', 900: '#064e3b' }
                    },
                    fontFamily: { sans: ['BPG Arial Caps', 'BPG Arial', 'Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    
    <style>
        :root {
            --chart-1: #0ea5e9;
            --chart-2: #10b981;
            --chart-3: #f59e0b;
            --chart-4: #ef4444;
            --chart-5: #8b5cf6;
            --chart-6: #ec4899;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.4); }
            50% { box-shadow: 0 0 20px 10px rgba(14, 165, 233, 0); }
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .animate-fade-in { animation: fadeInUp 0.6s ease-out forwards; }
        .animate-delay-100 { animation-delay: 0.1s; opacity: 0; }
        .animate-delay-200 { animation-delay: 0.2s; opacity: 0; }
        .animate-delay-300 { animation-delay: 0.3s; opacity: 0; }
        .animate-delay-400 { animation-delay: 0.4s; opacity: 0; }
        .animate-delay-500 { animation-delay: 0.5s; opacity: 0; }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.7) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.5);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #0ea5e9 0%, #d946ef 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #0ea5e9, #10b981);
            background-size: 200% 100%;
            animation: shimmer 2s linear infinite;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #0ea5e9, #0284c7); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #0284c7, #0369a1); }
        
        .metric-ring {
            stroke-dasharray: 251.2;
            stroke-dashoffset: 251.2;
            transition: stroke-dashoffset 1s ease-out;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-purple-50/20 min-h-screen">
    
    <!-- Floating Background Elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-10 w-72 h-72 bg-blue-200/30 rounded-full blur-3xl"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-purple-200/30 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-emerald-200/20 rounded-full blur-3xl"></div>
    </div>
    
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200/50 no-print">
        <div class="max-w-[1800px] mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 transition-colors">
                        <i data-lucide="arrow-left" class="w-5 h-5 text-slate-600"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold">
                            <span class="gradient-text">Analytics Dashboard</span>
                        </h1>
                        <p class="text-sm text-slate-500">Real-time business intelligence & performance metrics</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Date Range Picker -->
                    <div class="hidden md:flex items-center space-x-2 bg-slate-100 rounded-xl px-4 py-2">
                        <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                        <span class="text-sm text-slate-600">Last 30 Days</span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                    </div>
                    
                    <!-- Export Button -->
                    <button onclick="window.print()" class="flex items-center space-x-2 px-4 py-2 bg-primary-500 text-white rounded-xl hover:bg-primary-600 transition-colors">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">Export</span>
                    </button>
                    
                    <!-- Refresh -->
                    <button onclick="location.reload()" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 transition-colors">
                        <i data-lucide="refresh-cw" class="w-5 h-5 text-slate-600"></i>
                    </button>
                    
                    <!-- User -->
                    <div class="flex items-center space-x-2">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($current_user_name, 0, 1)) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-[1800px] mx-auto px-4 sm:px-6 lg:px-8 py-8 relative">
        
        <!-- Key Metrics Row -->
        <section class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
            
            <!-- Total Cases -->
            <div class="stat-card rounded-2xl p-5 transition-all duration-300 animate-fade-in">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2.5 rounded-xl bg-blue-100">
                        <i data-lucide="folder" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <span class="text-xs font-medium text-blue-600 bg-blue-50 px-2 py-1 rounded-full">All Time</span>
                </div>
                <div class="text-3xl font-bold text-slate-800 tabular-nums" data-countup="<?= $overall_stats['total_cases'] ?>">0</div>
                <p class="text-sm text-slate-500 mt-1">Total Cases</p>
            </div>
            
            <!-- Completed Cases -->
            <div class="stat-card rounded-2xl p-5 transition-all duration-300 animate-fade-in animate-delay-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2.5 rounded-xl bg-emerald-100">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
                    </div>
                    <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                        <?= $overall_stats['total_cases'] > 0 ? round(($overall_stats['completed_cases'] / $overall_stats['total_cases']) * 100) : 0 ?>%
                    </span>
                </div>
                <div class="text-3xl font-bold text-slate-800 tabular-nums" data-countup="<?= $overall_stats['completed_cases'] ?>">0</div>
                <p class="text-sm text-slate-500 mt-1">Completed</p>
            </div>
            
            <!-- Active Cases -->
            <div class="stat-card rounded-2xl p-5 transition-all duration-300 animate-fade-in animate-delay-200">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2.5 rounded-xl bg-amber-100">
                        <i data-lucide="clock" class="w-5 h-5 text-amber-600"></i>
                    </div>
                    <span class="text-xs font-medium text-amber-600 bg-amber-50 px-2 py-1 rounded-full">In Progress</span>
                </div>
                <div class="text-3xl font-bold text-slate-800 tabular-nums" data-countup="<?= $overall_stats['active_cases'] ?>">0</div>
                <p class="text-sm text-slate-500 mt-1">Active Cases</p>
            </div>
            
            <!-- Total Revenue -->
            <div class="stat-card rounded-2xl p-5 transition-all duration-300 animate-fade-in animate-delay-300">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2.5 rounded-xl bg-green-100">
                        <i data-lucide="banknote" class="w-5 h-5 text-green-600"></i>
                    </div>
                    <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">Revenue</span>
                </div>
                <div class="text-2xl font-bold text-slate-800 tabular-nums">
                    ₾<span data-countup="<?= round($overall_stats['total_revenue']) ?>" data-decimals="0">0</span>
                </div>
                <p class="text-sm text-slate-500 mt-1">Total Revenue</p>
            </div>
            
            <!-- Average Case Value -->
            <div class="stat-card rounded-2xl p-5 transition-all duration-300 animate-fade-in animate-delay-400">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2.5 rounded-xl bg-purple-100">
                        <i data-lucide="trending-up" class="w-5 h-5 text-purple-600"></i>
                    </div>
                    <span class="text-xs font-medium text-purple-600 bg-purple-50 px-2 py-1 rounded-full">AVG</span>
                </div>
                <div class="text-2xl font-bold text-slate-800 tabular-nums">
                    ₾<span data-countup="<?= round($overall_stats['avg_case_value']) ?>" data-decimals="0">0</span>
                </div>
                <p class="text-sm text-slate-500 mt-1">Avg Case Value</p>
            </div>
            
            <!-- Nachrebi Value -->
            <div class="stat-card rounded-2xl p-5 transition-all duration-300 animate-fade-in animate-delay-500">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2.5 rounded-xl bg-pink-100">
                        <i data-lucide="package" class="w-5 h-5 text-pink-600"></i>
                    </div>
                    <span class="text-xs font-medium text-pink-600 bg-pink-50 px-2 py-1 rounded-full">Pieces</span>
                </div>
                <div class="text-2xl font-bold text-slate-800 tabular-nums">
                    ₾<span data-countup="<?= round($nachrebi_stats['total_nachrebi_value']) ?>" data-decimals="0">0</span>
                </div>
                <p class="text-sm text-slate-500 mt-1"><?= number_format($nachrebi_stats['total_nachrebi'], 1) ?> Nachrebi</p>
            </div>
            
        </section>
        
        <!-- Charts Row 1 -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- Revenue Trend Chart -->
            <div class="lg:col-span-2 glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Revenue Trend</h3>
                        <p class="text-sm text-slate-500">Monthly revenue over last 12 months</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="flex items-center text-xs text-slate-500">
                            <span class="w-3 h-3 rounded-full bg-blue-500 mr-1.5"></span> Insurance
                        </span>
                        <span class="flex items-center text-xs text-slate-500">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 mr-1.5"></span> Retail
                        </span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <!-- Status Distribution -->
            <div class="glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Case Status</h3>
                        <p class="text-sm text-slate-500">Distribution by status</p>
                    </div>
                </div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="mt-4 space-y-2">
                    <?php foreach (array_slice($status_distribution, 0, 5) as $status): ?>
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center">
                            <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?= $status['color'] ?>"></span>
                            <?= htmlspecialchars($status['status_name']) ?>
                        </span>
                        <span class="font-semibold"><?= $status['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </section>
        
        <!-- Technician Performance & Case Type -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Technician Performance -->
            <div class="glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Technician Performance</h3>
                        <p class="text-sm text-slate-500">Cases completed & revenue generated</p>
                    </div>
                    <a href="nachrebi_report.php" class="text-sm text-primary-600 hover:text-primary-700 flex items-center">
                        View Report <i data-lucide="arrow-right" class="w-4 h-4 ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                                <th class="pb-3">Technician</th>
                                <th class="pb-3 text-center">Completed</th>
                                <th class="pb-3 text-center">Nachrebi</th>
                                <th class="pb-3 text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($technician_stats as $index => $tech): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-400 to-accent-400 flex items-center justify-center text-white text-xs font-bold mr-3">
                                            <?= $index + 1 ?>
                                        </div>
                                        <span class="font-medium text-slate-800"><?= htmlspecialchars($tech['technician']) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        <?= $tech['completed'] ?>
                                    </span>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="text-sm font-medium text-slate-600"><?= number_format($tech['total_nachrebi'], 1) ?></span>
                                </td>
                                <td class="py-3 text-right">
                                    <span class="text-sm font-bold text-slate-800">₾<?= number_format($tech['revenue_generated']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($technician_stats)): ?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-400">
                                    <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                    <p>No technician data available</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Case Type Analysis -->
            <div class="glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Case Type Analysis</h3>
                        <p class="text-sm text-slate-500">Insurance vs Retail breakdown</p>
                    </div>
                </div>
                
                <!-- Case Type Cards -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-4 text-white">
                        <div class="flex items-center justify-between mb-2">
                            <i data-lucide="shield" class="w-6 h-6 opacity-80"></i>
                            <span class="text-xs bg-white/20 px-2 py-1 rounded-full"><?= $overall_stats['insurance_completed'] ?> cases</span>
                        </div>
                        <p class="text-2xl font-bold">₾<?= number_format($overall_stats['insurance_revenue']) ?></p>
                        <p class="text-sm opacity-80 mt-1">დაზღვევა (Insurance)</p>
                    </div>
                    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl p-4 text-white">
                        <div class="flex items-center justify-between mb-2">
                            <i data-lucide="shopping-cart" class="w-6 h-6 opacity-80"></i>
                            <span class="text-xs bg-white/20 px-2 py-1 rounded-full"><?= $overall_stats['retail_completed'] ?> cases</span>
                        </div>
                        <p class="text-2xl font-bold">₾<?= number_format($overall_stats['retail_revenue']) ?></p>
                        <p class="text-sm opacity-80 mt-1">საცალო (Retail)</p>
                    </div>
                </div>
                
                <!-- Case Type Chart -->
                <div class="chart-container" style="height: 200px;">
                    <canvas id="caseTypeChart"></canvas>
                </div>
            </div>
            
        </section>
        
        <!-- Daily Activity & Reviews -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- Daily Activity Chart -->
            <div class="lg:col-span-2 glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Daily Activity</h3>
                        <p class="text-sm text-slate-500">New vs Completed cases (last 30 days)</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="dailyActivityChart"></canvas>
                </div>
            </div>
            
            <!-- Customer Reviews -->
            <div class="glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Customer Reviews</h3>
                        <p class="text-sm text-slate-500">Rating distribution</p>
                    </div>
                    <a href="reviews.php" class="text-sm text-primary-600 hover:text-primary-700 flex items-center">
                        View All <i data-lucide="arrow-right" class="w-4 h-4 ml-1"></i>
                    </a>
                </div>
                
                <!-- Average Rating -->
                <div class="text-center mb-6">
                    <div class="text-5xl font-bold text-slate-800 mb-2"><?= number_format($reviews_stats['avg_rating'], 1) ?></div>
                    <div class="flex justify-center text-yellow-400 text-xl mb-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= round($reviews_stats['avg_rating'])): ?>
                                <i data-lucide="star" class="w-5 h-5 fill-current"></i>
                            <?php else: ?>
                                <i data-lucide="star" class="w-5 h-5"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <p class="text-sm text-slate-500"><?= $reviews_stats['total_reviews'] ?> total reviews</p>
                </div>
                
                <!-- Rating Breakdown -->
                <div class="space-y-2">
                    <?php 
                    $rating_bars = [
                        5 => $reviews_stats['five_star'],
                        4 => $reviews_stats['four_star'],
                        3 => $reviews_stats['three_star'],
                        2 => $reviews_stats['two_star'],
                        1 => $reviews_stats['one_star']
                    ];
                    $max_reviews = max($rating_bars) ?: 1;
                    foreach ($rating_bars as $stars => $count): 
                        $percentage = ($count / $max_reviews) * 100;
                    ?>
                    <div class="flex items-center space-x-2">
                        <span class="w-4 text-xs text-slate-500"><?= $stars ?></span>
                        <i data-lucide="star" class="w-3 h-3 text-yellow-400 fill-current"></i>
                        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-yellow-400 rounded-full transition-all duration-1000" style="width: <?= $percentage ?>%"></div>
                        </div>
                        <span class="w-8 text-xs text-slate-500 text-right"><?= $count ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Review Status -->
                <div class="grid grid-cols-3 gap-2 mt-6 pt-4 border-t border-slate-200">
                    <div class="text-center">
                        <div class="text-lg font-bold text-emerald-600"><?= $reviews_stats['approved'] ?></div>
                        <div class="text-xs text-slate-500">Approved</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-amber-600"><?= $reviews_stats['pending'] ?></div>
                        <div class="text-xs text-slate-500">Pending</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-red-600"><?= $reviews_stats['rejected'] ?></div>
                        <div class="text-xs text-slate-500">Rejected</div>
                    </div>
                </div>
            </div>
            
        </section>
        
        <!-- Customer Response & Conversion Funnel -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Customer Response -->
            <div class="glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Customer Responses</h3>
                        <p class="text-sm text-slate-500">Confirmation & reschedule rates</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <?php foreach ($response_stats as $response): 
                        $color = match($response['response']) {
                            'Confirmed' => 'emerald',
                            'Reschedule Requested' => 'amber',
                            'Pending' => 'blue',
                            default => 'slate'
                        };
                    ?>
                    <div class="flex items-center justify-between p-4 bg-<?= $color ?>-50 rounded-xl">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-lg bg-<?= $color ?>-100 flex items-center justify-center mr-3">
                                <?php if ($response['response'] === 'Confirmed'): ?>
                                    <i data-lucide="check" class="w-5 h-5 text-<?= $color ?>-600"></i>
                                <?php elseif ($response['response'] === 'Reschedule Requested'): ?>
                                    <i data-lucide="calendar-clock" class="w-5 h-5 text-<?= $color ?>-600"></i>
                                <?php else: ?>
                                    <i data-lucide="clock" class="w-5 h-5 text-<?= $color ?>-600"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="font-medium text-slate-800"><?= htmlspecialchars($response['response']) ?></p>
                                <p class="text-sm text-slate-500"><?= $response['percentage'] ?>% of responses</p>
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-<?= $color ?>-600"><?= $response['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Conversion Funnel -->
            <div class="glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Conversion Funnel</h3>
                        <p class="text-sm text-slate-500">Case progression through stages</p>
                    </div>
                </div>
                
                <?php 
                $funnel_stages = [
                    ['name' => 'New Cases', 'value' => $conversion_funnel['stage_new'], 'color' => 'blue'],
                    ['name' => 'Processing', 'value' => $conversion_funnel['stage_processing'], 'color' => 'purple'],
                    ['name' => 'Contacted', 'value' => $conversion_funnel['stage_called'], 'color' => 'indigo'],
                    ['name' => 'Scheduled', 'value' => $conversion_funnel['stage_scheduled'], 'color' => 'amber'],
                    ['name' => 'Completed', 'value' => $conversion_funnel['stage_completed'], 'color' => 'emerald'],
                ];
                $max_funnel = $funnel_stages[0]['value'] ?: 1;
                ?>
                
                <div class="space-y-3">
                    <?php foreach ($funnel_stages as $index => $stage): 
                        $width = ($stage['value'] / $max_funnel) * 100;
                        $conversion = $index > 0 ? round(($stage['value'] / $funnel_stages[$index-1]['value']) * 100) : 100;
                    ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-slate-700"><?= $stage['name'] ?></span>
                            <span class="text-slate-500">
                                <?= number_format($stage['value']) ?>
                                <?php if ($index > 0): ?>
                                    <span class="text-xs text-<?= $stage['color'] ?>-600 ml-1">(<?= $conversion ?>%)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="h-8 bg-slate-100 rounded-lg overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-<?= $stage['color'] ?>-400 to-<?= $stage['color'] ?>-500 rounded-lg transition-all duration-1000 flex items-center justify-end pr-2" style="width: <?= max($width, 10) ?>%">
                                <?php if ($width > 20): ?>
                                    <span class="text-xs font-medium text-white"><?= number_format($stage['value']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Overall Conversion Rate -->
                <div class="mt-6 p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i data-lucide="trophy" class="w-6 h-6 text-emerald-600 mr-2"></i>
                            <span class="font-medium text-emerald-800">Overall Completion Rate</span>
                        </div>
                        <span class="text-2xl font-bold text-emerald-600">
                            <?= $max_funnel > 0 ? round(($conversion_funnel['stage_completed'] / $max_funnel) * 100) : 0 ?>%
                        </span>
                    </div>
                </div>
            </div>
            
        </section>
        
        <!-- Top Cases & Hourly Pattern -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- Top Cases by Value -->
            <div class="glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Top Cases by Value</h3>
                        <p class="text-sm text-slate-500">Highest revenue cases</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                                <th class="pb-3">Vehicle</th>
                                <th class="pb-3">Customer</th>
                                <th class="pb-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($top_vehicles as $vehicle): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3">
                                    <div>
                                        <p class="font-medium text-slate-800"><?= htmlspecialchars(trim($vehicle['vehicle']) ?: 'N/A') ?></p>
                                        <p class="text-xs text-slate-500"><?= htmlspecialchars($vehicle['plate']) ?></p>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="text-sm text-slate-600"><?= htmlspecialchars($vehicle['customer']) ?></span>
                                </td>
                                <td class="py-3 text-right">
                                    <span class="font-bold text-emerald-600">₾<?= number_format($vehicle['amount']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Activity Pattern -->
            <div class="glass-card rounded-2xl p-6 shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Activity Pattern</h3>
                        <p class="text-sm text-slate-500">Cases created by hour of day</p>
                    </div>
                </div>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
            
        </section>
        
        <!-- Quick Stats Footer -->
        <section class="glass-card rounded-2xl p-6 shadow-lg mb-8">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6 text-center">
                <div>
                    <p class="text-3xl font-bold text-slate-800"><?= $parts_services['cases_with_parts'] ?></p>
                    <p class="text-sm text-slate-500">Cases with Parts</p>
                </div>
                <div>
                    <p class="text-3xl font-bold text-slate-800"><?= $parts_services['cases_with_services'] ?></p>
                    <p class="text-sm text-slate-500">Cases with Services</p>
                </div>
                <div>
                    <p class="text-3xl font-bold text-slate-800"><?= $nachrebi_stats['cases_with_nachrebi'] ?></p>
                    <p class="text-sm text-slate-500">Cases with Nachrebi</p>
                </div>
                <div>
                    <p class="text-3xl font-bold text-slate-800"><?= number_format($nachrebi_stats['avg_nachrebi'], 1) ?></p>
                    <p class="text-sm text-slate-500">Avg Nachrebi/Case</p>
                </div>
                <div>
                    <p class="text-3xl font-bold text-slate-800">₾<?= number_format($overall_stats['total_franchise']) ?></p>
                    <p class="text-sm text-slate-500">Total Franchise</p>
                </div>
                <div>
                    <p class="text-3xl font-bold text-slate-800"><?= $overall_stats['issue_cases'] ?></p>
                    <p class="text-sm text-slate-500">Issue Cases</p>
                </div>
            </div>
        </section>
        
    </main>
    
    <!-- Footer -->
    <footer class="border-t border-slate-200 bg-white/50 backdrop-blur-lg py-6 no-print">
        <div class="max-w-[1800px] mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-sm text-slate-500">
                <span class="font-medium">OTOMOTORS Analytics</span> • Data updated: <?= date('d M Y, H:i') ?>
            </p>
        </div>
    </footer>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Chart.js global configuration
        Chart.defaults.font.family = "'Inter', 'system-ui', sans-serif";
        Chart.defaults.color = '#64748b';
        
        // Data from PHP
        const chartData = <?= json_encode($chartData) ?>;
        
        // CountUp Animation
        document.querySelectorAll('[data-countup]').forEach(el => {
            const value = parseFloat(el.dataset.countup);
            const decimals = parseInt(el.dataset.decimals) || 0;
            
            const countUp = new countUp.CountUp(el, value, {
                duration: 2,
                decimalPlaces: decimals,
                separator: ',',
                enableScrollSpy: true,
                scrollSpyOnce: true
            });
            
            if (!countUp.error) {
                countUp.start();
            }
        });
        
        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: chartData.monthlyRevenue.map(m => m.month_label),
                datasets: [
                    {
                        label: 'Insurance Revenue',
                        data: chartData.monthlyRevenue.map(m => m.insurance_rev),
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    },
                    {
                        label: 'Retail Revenue',
                        data: chartData.monthlyRevenue.map(m => m.retail_rev),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { weight: '600' },
                        callbacks: {
                            label: ctx => `${ctx.dataset.label}: ₾${ctx.raw.toLocaleString()}`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { 
                        beginAtZero: true,
                        ticks: { callback: val => '₾' + val.toLocaleString() }
                    }
                }
            }
        });
        
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.statusDistribution.map(s => s.status_name),
                datasets: [{
                    data: chartData.statusDistribution.map(s => s.count),
                    backgroundColor: chartData.statusDistribution.map(s => s.color),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
        
        // Case Type Chart
        const caseTypeCtx = document.getElementById('caseTypeChart').getContext('2d');
        new Chart(caseTypeCtx, {
            type: 'bar',
            data: {
                labels: ['Insurance', 'Retail'],
                datasets: [{
                    data: [<?= $overall_stats['insurance_revenue'] ?>, <?= $overall_stats['retail_revenue'] ?>],
                    backgroundColor: ['#3b82f6', '#10b981'],
                    borderRadius: 8,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => '₾' + ctx.raw.toLocaleString()
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { 
                        beginAtZero: true,
                        ticks: { callback: val => '₾' + val.toLocaleString() }
                    }
                }
            }
        });
        
        // Daily Activity Chart
        const dailyCtx = document.getElementById('dailyActivityChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: chartData.dailyActivity.map(d => d.date_label),
                datasets: [
                    {
                        label: 'New Cases',
                        data: chartData.dailyActivity.map(d => d.new_cases),
                        backgroundColor: '#0ea5e9',
                        borderRadius: 4
                    },
                    {
                        label: 'Completed',
                        data: chartData.dailyActivity.map(d => d.completed_cases),
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'top',
                        labels: { usePointStyle: true, padding: 20 }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true }
                }
            }
        });
        
        // Hourly Pattern Chart
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        
        // Fill in missing hours with 0
        const hourlyData = Array(24).fill(0);
        chartData.hourlyPattern.forEach(h => {
            hourlyData[h.hour] = h.count;
        });
        
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => `${i}:00`),
                datasets: [{
                    data: hourlyData,
                    backgroundColor: hourlyData.map((_, i) => {
                        if (i >= 9 && i <= 18) return '#0ea5e9';
                        return '#cbd5e1';
                    }),
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { 
                        grid: { display: false },
                        ticks: { 
                            callback: (val, i) => i % 3 === 0 ? `${i}:00` : ''
                        }
                    },
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>
