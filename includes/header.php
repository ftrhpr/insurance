<?php
// includes/header.php - Shared header and navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'OTOMOTORS Manager Portal'; ?></title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- FIREBASE SDKs -->
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    },
                    animation: {
                        'pulse-fast': 'pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .nav-item {
            transition: all 0.2s ease;
        }
        .nav-active { 
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .nav-inactive { 
            color: #64748b; 
        }
        .nav-inactive:hover { 
            color: #0f172a;
            background-color: #ffffff;
        }

        /* Custom Blink Animation for Urgent Toasts */
        @keyframes border-pulse {
            0% { border-color: rgba(99, 102, 241, 0.2); box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); transform: scale(1); }
            50% { border-color: rgba(99, 102, 241, 1); box-shadow: 0 0 20px 0 rgba(99, 102, 241, 0.4); transform: scale(1.02); }
            100% { border-color: rgba(99, 102, 241, 0.2); box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); transform: scale(1); }
        }
        .toast-urgent {
            animation: border-pulse 2s infinite;
            border-width: 2px;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen selection:bg-primary-100 selection:text-primary-700">

    <!-- Loading -->
    <div id="loading-screen" class="fixed inset-0 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center z-50 transition-opacity duration-500">
        <div class="relative">
            <div class="w-12 h-12 border-4 border-slate-200 border-t-primary-600 rounded-full animate-spin"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <i data-lucide="car" class="w-4 h-4 text-primary-600"></i>
            </div>
        </div>
        <div class="mt-4 text-slate-500 text-sm font-medium tracking-wide animate-pulse">CONNECTING...</div>
    </div>

    <!-- App Content -->
    <div id="app-content" class="hidden pb-20">
        
        <!-- Navbar -->
        <nav class="bg-white/80 backdrop-blur-md border-b border-slate-200/60 sticky top-0 z-20 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center gap-8">
                        <!-- Logo -->
                        <div class="flex items-center gap-3">
                            <div class="bg-gradient-to-br from-primary-600 to-primary-700 p-2 rounded-xl text-white shadow-lg shadow-primary-500/30">
                                <i data-lucide="car" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold text-slate-900 leading-tight tracking-tight">OTOMOTORS</h1>
                                <span class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider">Service Manager</span>
                            </div>
                        </div>
                        
                        <!-- Navigation -->
                        <div class="hidden md:flex bg-slate-100/50 p-1 rounded-lg border border-slate-200/50">
                            <a href="dashboard.php" class="<?php echo ($current_page === 'dashboard' ? 'nav-active' : 'nav-inactive'); ?> px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                            </a>
                            <a href="vehicles.php" class="<?php echo ($current_page === 'vehicles' ? 'nav-active' : 'nav-inactive'); ?> px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="database" class="w-4 h-4"></i> Vehicle DB
                            </a>
                            <a href="reviews.php" class="<?php echo ($current_page === 'reviews' ? 'nav-active' : 'nav-inactive'); ?> px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="star" class="w-4 h-4"></i> Reviews
                            </a>
                            <a href="templates.php" class="<?php echo ($current_page === 'templates' ? 'nav-active' : 'nav-inactive'); ?> px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="message-square-dashed" class="w-4 h-4"></i> SMS Templates
                            </a>
                            <?php if ($current_user_role === 'admin'): ?>
                            <a href="users.php" class="<?php echo ($current_page === 'users' ? 'nav-active' : 'nav-inactive'); ?> px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="users" class="w-4 h-4"></i> Users
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- User Status -->
                    <div class="flex items-center gap-4">
                        <!-- Notification Bell -->
                        <button id="btn-notify" onclick="window.requestNotificationPermission()" class="text-slate-400 hover:text-primary-600 transition-colors p-2 bg-slate-100 rounded-full group relative" title="Enable Notifications">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                            <span id="notify-badge" class="absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white hidden"></span>
                        </button>

                        <div id="connection-status" class="flex items-center gap-2 text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1.5 rounded-full shadow-sm">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            Server Connected
                        </div>
                        
                        <!-- User Menu -->
                        <div class="relative" id="user-menu-container">
                            <button onclick="window.toggleUserMenu()" class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                                <div class="w-7 h-7 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                    <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                </div>
                                <div class="text-left hidden sm:block">
                                    <div class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($current_user_name); ?></div>
                                    <div class="text-xs text-slate-500 capitalize"><?php echo htmlspecialchars($current_user_role); ?></div>
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-slate-200 py-2 z-50">
                                <div class="px-4 py-2 border-b border-slate-100">
                                    <p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($current_user_name); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></p>
                                </div>
                                <button onclick="window.openChangePasswordModal()" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                                    <i data-lucide="lock" class="w-4 h-4"></i>
                                    Change Password
                                </button>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                                    <i data-lucide="log-out" class="w-4 h-4"></i>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
