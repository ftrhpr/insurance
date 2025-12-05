<?php
session_start();
require_once '../includes/auth.php';
require_once '../config.php';

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
    
    <?php include '../includes/header.php'; ?>

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
                    <p class="text-sm text-slate-600">Manage vehicle records and service history</p>
                </div>
            </a>

            <!-- Reviews -->
            <?php if (isManager() || isAdmin()): ?>
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
                    <span class="inline-block mt-2 text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded-full">Manager+</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- SMS Templates -->
            <?php if (isManager() || isAdmin()): ?>
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
                    <span class="inline-block mt-2 text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded-full">Manager+</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- User Management -->
            <?php if (isAdmin()): ?>
            <a href="users.php" class="group">
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 hover:border-rose-500 transition-all hover:shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-rose-100 rounded-lg group-hover:bg-rose-500 transition-colors">
                            <i data-lucide="users" class="w-8 h-8 text-rose-600 group-hover:text-white"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-slate-400 group-hover:text-rose-600 transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">User Management</h3>
                    <p class="text-sm text-slate-600">Manage system users and permissions</p>
                    <span class="inline-block mt-2 text-xs px-2 py-1 bg-purple-100 text-purple-700 rounded-full">Admin Only</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- Unified View Card -->
            <a href="../index-modular.php" class="group">
                <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-xl border-2 border-indigo-200 p-6 hover:border-indigo-500 transition-all hover:shadow-lg">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-white/80 rounded-lg group-hover:bg-indigo-500 transition-colors">
                            <i data-lucide="app-window" class="w-8 h-8 text-indigo-600 group-hover:text-white"></i>
                        </div>
                        <i data-lucide="arrow-right" class="w-5 h-5 text-indigo-400 group-hover:text-indigo-600 transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">Unified View</h3>
                    <p class="text-sm text-slate-600">Single-page app with instant view switching</p>
                    <span class="inline-block mt-2 text-xs px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full">SPA Mode</span>
                </div>
            </a>

        </div>

        <!-- Info Box -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-6">
            <div class="flex gap-4">
                <div class="flex-shrink-0">
                    <i data-lucide="info" class="w-6 h-6 text-blue-600"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-blue-900 mb-2">Two Ways to Work</h4>
                    <div class="space-y-2 text-sm text-blue-800">
                        <p><strong>Standalone Pages:</strong> Access each feature via dedicated URL. Better for bookmarking, debugging, and IDE integration.</p>
                        <p><strong>Unified View:</strong> Single-page app with instant view switching. Faster navigation, shared state, better for quick multi-tasking.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>

</body>
</html>
