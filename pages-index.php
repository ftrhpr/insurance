<?php
// Error handling - PRODUCTION
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $msg = "Pages Index [$errno] $errstr in $errfile on line $errline";
    error_log($msg);
    // Also display for debugging
    echo "<div style='background:#fee;border:2px solid red;padding:10px;margin:10px;font-family:monospace;'>";
    echo "<strong>PHP Error:</strong> $msg";
    echo "</div>";
    return true;
});

set_exception_handler(function($exception) {
    $msg = 'Pages Index Exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine();
    error_log($msg);
    http_response_code(500);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:20px;">';
    echo '<h1 style="color:red;">Error Occurred</h1>';
    echo '<p><strong>Message:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . '</p>';
    echo '<p><strong>Line:</strong> ' . $exception->getLine() . '</p>';
    echo '<pre style="background:#f5f5f5;padding:10px;overflow:auto;">' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
    echo '</body></html>';
    exit;
});

session_start();
require_once 'includes/auth.php';
require_once 'config.php';

requireLogin();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTOMOTORS - Feature Pages</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50">
    
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">OTOMOTORS Manager Portal</h1>
            <p class="text-slate-600">Select a feature page below to manage system components</p>
        </div>

        <!-- Feature Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <!-- Dashboard -->
            <a href="dashboard.php" class="group">
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 hover:border-blue-500 transition-all hover:shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-blue-100 rounded-lg group-hover:bg-blue-500 transition-colors">
                            <i data-lucide="layout-dashboard" class="w-8 h-8 text-blue-600 group-hover:text-white"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-400 group-hover:text-blue-600 transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">Dashboard</h3>
                    <p class="text-sm text-slate-600">View stats, import SMS, manage transfers</p>
                </div>
            </a>

            <!-- Vehicle Database -->
            <a href="vehicles.php" class="group">
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 hover:border-emerald-500 transition-all hover:shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-emerald-100 rounded-lg group-hover:bg-emerald-500 transition-colors">
                            <i data-lucide="database" class="w-8 h-8 text-emerald-600 group-hover:text-white"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-400 group-hover:text-emerald-600 transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">Vehicle Database</h3>
                    <p class="text-sm text-slate-600">Manage vehicle records and history</p>
                </div>
            </a>

            <!-- Reviews -->
            <?php if (isManager()): ?>
            <a href="reviews.php" class="group">
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 hover:border-amber-500 transition-all hover:shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-amber-100 rounded-lg group-hover:bg-amber-500 transition-colors">
                            <i data-lucide="star" class="w-8 h-8 text-amber-600 group-hover:text-white"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-400 group-hover:text-amber-600 transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">Customer Reviews</h3>
                    <p class="text-sm text-slate-600">Moderate and manage customer feedback</p>
                    <span class="inline-block mt-2 px-2 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded">Manager+</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- SMS Templates -->
            <?php if (isManager()): ?>
            <a href="templates.php" class="group">
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 hover:border-purple-500 transition-all hover:shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-purple-100 rounded-lg group-hover:bg-purple-500 transition-colors">
                            <i data-lucide="message-square-dashed" class="w-8 h-8 text-purple-600 group-hover:text-white"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-400 group-hover:text-purple-600 transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">SMS Templates</h3>
                    <p class="text-sm text-slate-600">Customize automated message templates</p>
                    <span class="inline-block mt-2 px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded">Manager+</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- User Management -->
            <?php if (isAdmin()): ?>
            <a href="users.php" class="group">
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 hover:border-red-500 transition-all hover:shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-red-100 rounded-lg group-hover:bg-red-500 transition-colors">
                            <i data-lucide="users" class="w-8 h-8 text-red-600 group-hover:text-white"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-400 group-hover:text-red-600 transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">User Management</h3>
                    <p class="text-sm text-slate-600">Manage system users and permissions</p>
                    <span class="inline-block mt-2 px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded">Admin Only</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- Back to Unified View -->
            <a href="index-modular.php" class="group">
                <div class="bg-gradient-to-br from-slate-700 to-slate-900 rounded-xl border-2 border-slate-700 p-6 hover:border-slate-500 transition-all hover:shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-white/10 rounded-lg group-hover:bg-white/20 transition-colors">
                            <i data-lucide="layout-grid" class="w-8 h-8 text-white"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-400 group-hover:text-white transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white mb-2">Unified Dashboard</h3>
                    <p class="text-sm text-slate-300">Switch to single-page multi-tab view</p>
                </div>
            </a>

        </div>

        <!-- Info Banner -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                <div>
                    <h4 class="font-semibold text-blue-900 mb-1">Feature Pages Mode</h4>
                    <p class="text-sm text-blue-700">You're viewing the separate pages interface. Each feature opens in its own page. Switch to Unified Dashboard for a single-page experience with tabs.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
