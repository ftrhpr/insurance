<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTOMOTORS Manager Portal</title>
    
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
                        },
                        accent: {
                            50: '#fdf4ff',
                            100: '#fae8ff',
                            500: '#d946ef',
                            600: '#c026d3',
                        }
                    },
                    animation: {
                        'pulse-fast': 'pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'float': 'float 3s ease-in-out infinite',
                        'shimmer': 'shimmer 2s linear infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' },
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-200% center' },
                            '100%': { backgroundPosition: '200% center' },
                        }
                    },
                    backgroundImage: {
                        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                        'glass': 'linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%)',
                    }
                }
            }
        }
    </script>

    <style>
        /* Premium Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { 
            width: 8px; 
            height: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track { 
            background: rgba(148, 163, 184, 0.1); 
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb { 
            background: linear-gradient(180deg, #0ea5e9 0%, #0284c7 100%);
            border-radius: 10px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { 
            background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%);
            background-clip: padding-box;
        }
        
        /* Enhanced Navigation */
        .nav-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .nav-active { 
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: #ffffff; 
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3), 0 2px 4px rgba(14, 165, 233, 0.2);
        }
        .nav-inactive { 
            color: #64748b;
            background: transparent;
        }
        .nav-inactive:hover { 
            color: #0f172a;
            background: rgba(14, 165, 233, 0.08);
            transform: translateY(-1px);
        }

        /* Glass Morphism Effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.9);
        }

        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #c026d3 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Enhanced Card Hover */
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12), 0 10px 20px rgba(14, 165, 233, 0.1);
        }

        /* Animated Border for Urgent Toasts */
        @keyframes border-pulse {
            0% { 
                border-color: rgba(14, 165, 233, 0.3); 
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.5), 0 4px 12px rgba(14, 165, 233, 0.2);
                transform: scale(1); 
            }
            50% { 
                border-color: rgba(14, 165, 233, 1); 
                box-shadow: 0 0 30px 0 rgba(14, 165, 233, 0.5), 0 8px 20px rgba(14, 165, 233, 0.3);
                transform: scale(1.02); 
            }
            100% { 
                border-color: rgba(14, 165, 233, 0.3); 
                box-shadow: 0 0 0 0 rgba(14, 165, 233, 0.5), 0 4px 12px rgba(14, 165, 233, 0.2);
                transform: scale(1); 
            }
        }
        .toast-urgent {
            animation: border-pulse 2s infinite;
            border-width: 2px;
        }

        /* Shimmer Effect for Loading States */
        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.1), transparent);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }

        /* Premium Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            box-shadow: 0 8px 20px rgba(14, 165, 233, 0.4), 0 4px 8px rgba(14, 165, 233, 0.2);
            transform: translateY(-2px);
        }
        .btn-primary:active {
            transform: translateY(0px) scale(0.98);
        }

        /* Floating Animation for Icons */
        .float-icon {
            animation: float 3s ease-in-out infinite;
        }

        /* Modern Badge Styles */
        .badge-modern {
            position: relative;
            overflow: hidden;
        }
        .badge-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        .badge-modern:hover::before {
            left: 100%;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 text-slate-800 font-sans min-h-screen selection:bg-primary-200 selection:text-primary-900">

    <!-- Modern Loading Screen -->
    <div id="loading-screen" class="fixed inset-0 bg-gradient-to-br from-primary-500 via-primary-600 to-accent-600 flex flex-col items-center justify-center z-50 transition-opacity duration-500 pointer-events-auto">
        <div class="relative">
            <!-- Outer rotating ring -->
            <div class="w-20 h-20 border-4 border-white/20 border-t-white rounded-full animate-spin"></div>
            <!-- Inner pulsing circle -->
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center backdrop-blur-sm animate-pulse">
                    <i data-lucide="car" class="w-6 h-6 text-white float-icon"></i>
                </div>
            </div>
        </div>
        <div class="mt-8 text-center">
            <h3 class="text-white text-xl font-bold mb-2">OTOMOTORS</h3>
            <p class="text-white/80 text-sm font-medium">Loading your workspace...</p>
        </div>
        <div class="mt-4 text-slate-500 text-sm font-medium tracking-wide animate-pulse">CONNECTING...</div>
    </div>

    <!-- App Content -->
    <div id="app-content" class="hidden pb-20 relative z-0">
        
        <!-- Premium Navbar with Gradient Accent -->
        <nav class="bg-white/95 backdrop-blur-xl border-b border-slate-200/80 sticky top-0 z-40 shadow-lg shadow-slate-200/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-18">
                    <div class="flex items-center gap-8">
                        <!-- Enhanced Logo with Gradient -->
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="absolute inset-0 bg-gradient-to-br from-primary-400 to-accent-500 rounded-xl blur-md opacity-60"></div>
                                <div class="relative bg-gradient-to-br from-primary-500 via-primary-600 to-accent-600 p-2.5 rounded-xl text-white shadow-lg">
                                    <i data-lucide="car" class="w-5 h-5"></i>
                                </div>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold gradient-text leading-tight tracking-tight">OTOMOTORS</h1>
                                <span class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider">Service Manager</span>
                            </div>
                        </div>
                        
                        <!-- Enhanced Navigation -->
                        <div class="hidden md:flex bg-slate-50/80 p-1.5 rounded-xl border border-slate-200/60 shadow-inner">
                            <button onclick="window.switchView('dashboard')" id="nav-dashboard" class="nav-active px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                            </button>
                            <a href="vehicles.php" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="database" class="w-4 h-4"></i> Vehicle DB
                            </a>
                            <a href="reviews.php" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="star" class="w-4 h-4"></i> Reviews
                            </a>
                            <a href="templates.php" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="message-square-dashed" class="w-4 h-4"></i> SMS Templates
                            </a>
                            <?php if ($current_user_role === 'admin'): ?>
                            <a href="users.php" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="users" class="w-4 h-4"></i> Users
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Premium User Status Section -->
                    <div class="flex items-center gap-3">
                        <!-- Enhanced Notification Bell -->
                        <button id="btn-notify" onclick="window.requestNotificationPermission()" class="relative text-slate-400 hover:text-primary-600 transition-all p-2.5 bg-slate-50 hover:bg-primary-50 rounded-xl group shadow-sm hover:shadow-md" title="Enable Notifications">
                            <i data-lucide="bell" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                            <span id="notify-badge" class="absolute -top-1 -right-1 w-3 h-3 bg-gradient-to-br from-red-500 to-red-600 rounded-full border-2 border-white hidden animate-pulse shadow-lg shadow-red-500/50"></span>
                        </button>

                        <!-- Premium Connection Status -->
                        <div id="connection-status" class="flex items-center gap-2 text-xs font-semibold bg-gradient-to-r from-emerald-50 to-teal-50 text-emerald-700 border border-emerald-200/60 px-3.5 py-2 rounded-xl shadow-sm">
                            <div class="relative">
                                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                                <span class="absolute inset-0 w-2 h-2 bg-emerald-400 rounded-full animate-ping opacity-75"></span>
                            </div>
                            <span class="tracking-wide">Connected</span>
                        </div>
                        
                        <!-- Enhanced User Menu -->
                        <div class="relative" id="user-menu-container">
                            <button onclick="window.toggleUserMenu()" class="flex items-center gap-2.5 px-3.5 py-2 bg-slate-50 hover:bg-slate-100 rounded-xl transition-all shadow-sm hover:shadow-md border border-slate-200/50">
                                <div class="relative">
                                    <div class="absolute inset-0 bg-gradient-to-br from-primary-400 to-accent-500 rounded-full blur opacity-40"></div>
                                    <div class="relative w-8 h-8 bg-gradient-to-br from-primary-500 via-primary-600 to-accent-600 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-lg">
                                        <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                    </div>
                                </div>
                                <div class="text-left hidden sm:block">
                                    <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($current_user_name); ?></div>
                                    <div class="text-xs text-slate-500 capitalize font-medium"><?php echo htmlspecialchars($current_user_role); ?></div>
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform"></i>
                            </button>
                            
                            <!-- Premium Dropdown Menu -->
                            <div id="user-dropdown" class="hidden absolute right-0 mt-3 w-64 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-slate-200/80 py-2 z-50 overflow-hidden">
                                <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-primary-50/50 to-accent-50/50">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-accent-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                                            <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($current_user_name); ?></p>
                                            <p class="text-xs text-slate-500 font-medium">@<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="window.openChangePasswordModal()" class="w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors group">
                                    <div class="w-8 h-8 bg-slate-100 group-hover:bg-primary-50 rounded-lg flex items-center justify-center transition-colors">
                                        <i data-lucide="lock" class="w-4 h-4 text-slate-600 group-hover:text-primary-600"></i>
                                    </div>
                                    <span class="font-medium">Change Password</span>
                                </button>
                                <a href="logout.php" class="block px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors group">
                                    <div class="w-8 h-8 bg-red-50 group-hover:bg-red-100 rounded-lg flex items-center justify-center transition-colors">
                                        <i data-lucide="log-out" class="w-4 h-4 text-red-600"></i>
                                    </div>
                                    <span class="font-semibold">Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

            <!-- DASHBOARD VIEW -->
            <div id="view-dashboard" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                
                <!-- Premium Import Section -->
                <section class="relative bg-white/80 backdrop-blur-sm rounded-3xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden transition-all hover:shadow-2xl hover:shadow-primary-500/10 card-hover group">
                    <!-- Gradient accent bar -->
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-primary-500 via-accent-500 to-primary-600"></div>
                    <div class="p-6 sm:p-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900 flex items-center gap-3">
                                    <div class="p-2 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl shadow-lg shadow-primary-500/30">
                                        <i data-lucide="file-input" class="w-5 h-5 text-white"></i>
                                    </div>
                                    Quick Import
                                </h2>
                                <p class="text-sm text-slate-600 mt-2 font-medium">Paste SMS or bank statement text to auto-detect transfers.</p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="window.insertSample('მანქანის ნომერი: AA123BB დამზღვევი: სახელი გვარი, 1234.00 (ფრანშიზა 273.97)')" class="text-xs font-semibold text-primary-700 bg-gradient-to-br from-primary-50 to-accent-50 px-4 py-2.5 rounded-xl hover:from-primary-100 hover:to-accent-100 transition-all border border-primary-200/50 shadow-sm hover:shadow-md hover:-translate-y-0.5">
                                    <span class="flex items-center gap-1.5">
                                        <i data-lucide="sparkles" class="w-3 h-3"></i>
                                        Sample with Franchise
                                    </span>
                                </button>
                                <button onclick="window.insertSample('მანქანის ნომერი: GE-123-GE დამზღვევი: Sample User, 150.00')" class="text-xs font-semibold text-slate-700 bg-slate-50 px-4 py-2.5 rounded-xl hover:bg-slate-100 transition-all border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5">
                                    <span class="flex items-center gap-1.5">
                                        <i data-lucide="file-text" class="w-3 h-3"></i>
                                        Simple Sample
                                    </span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex flex-col md:flex-row gap-6">
                            <!-- Text Input -->
                            <div class="flex-1 space-y-3">
                                <div class="relative">
                                    <textarea id="import-text" class="w-full h-32 p-5 bg-gradient-to-br from-slate-50 to-slate-100/50 border-2 border-slate-200/60 rounded-2xl focus:bg-white focus:border-primary-400 focus:ring-4 focus:ring-primary-500/20 outline-none text-sm font-mono resize-none transition-all placeholder:text-slate-400 shadow-inner" placeholder="Paste bank text here..."></textarea>
                                    <div class="absolute bottom-4 right-4">
                                        <button onclick="window.parseBankText()" id="btn-analyze" class="btn-primary text-white px-5 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2 shadow-xl">
                                            <i data-lucide="sparkles" class="w-4 h-4"></i> Detect
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Result Preview -->
                            <div id="parsed-placeholder" class="hidden md:flex flex-1 items-center justify-center border-2 border-dashed border-slate-200 rounded-xl text-slate-400 text-sm font-medium bg-slate-50/50">
                                <div class="text-center">
                                    <i data-lucide="arrow-left" class="w-5 h-5 mx-auto mb-2 opacity-50"></i>
                                    Waiting for text input...
                                </div>
                            </div>

                            <div id="parsed-result" class="hidden flex-1 bg-emerald-50/50 rounded-xl border border-emerald-100 p-5 flex flex-col relative overflow-hidden">
                                <div class="absolute top-0 right-0 w-20 h-20 bg-emerald-400/10 rounded-bl-full -mr-10 -mt-10"></div>
                                <div class="flex justify-between items-center mb-3 relative z-10">
                                    <h3 class="text-sm font-bold text-emerald-800 flex items-center gap-2">
                                        <div class="bg-emerald-100 p-1 rounded-full"><i data-lucide="check" class="w-3 h-3 text-emerald-600"></i></div>
                                        Ready to Import
                                    </h3>
                                </div>
                                <div id="parsed-content" class="flex-1 overflow-y-auto max-h-[120px] mb-4 space-y-2 pr-2 custom-scrollbar relative z-10"></div>
                                <button id="btn-save-import" onclick="window.saveParsedImport()" class="relative z-10 w-full bg-emerald-600 text-white py-2.5 rounded-lg hover:bg-emerald-700 active:scale-95 font-medium text-sm shadow-md shadow-emerald-600/20 transition-all flex items-center justify-center gap-2">
                                    <i data-lucide="save" class="w-4 h-4"></i> Confirm & Save
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Search & Filters -->
                <div class="sticky top-20 z-10 bg-white/80 backdrop-blur-xl p-2 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-2 justify-between items-center">
                    <div class="relative w-full sm:w-80 group">
                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-primary-500 transition-colors"></i>
                        <input id="search-input" type="text" placeholder="Search plates, names, phones..." class="w-full pl-10 pr-4 py-2.5 bg-transparent border border-transparent rounded-xl text-sm focus:bg-white focus:border-slate-200 focus:shadow-sm outline-none transition-all">
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto pr-1">
                        <!-- REPLY FILTER -->
                        <div class="relative">
                            <select id="reply-filter" class="appearance-none bg-slate-50 border border-slate-200 text-slate-700 py-2.5 pl-4 pr-10 rounded-xl text-sm font-medium cursor-pointer hover:bg-slate-100 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                                <option value="All">All Replies</option>
                                <option value="Confirmed">✅ Confirmed</option>
                                <option value="Reschedule Requested">📅 Reschedule</option>
                                <option value="Pending">⏳ Not Responded</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </div>
                        </div>

                        <!-- STATUS FILTER -->
                        <div class="relative">
                            <select id="status-filter" class="appearance-none bg-slate-50 border border-slate-200 text-slate-700 py-2.5 pl-4 pr-10 rounded-xl text-sm font-medium cursor-pointer hover:bg-slate-100 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                                <option value="All">All Active Stages</option>
                                <option value="Processing">🟡 Processing</option>
                                <option value="Called">🟣 Contacted</option>
                                <option value="Parts Ordered">📦 Parts Ordered</option>
                                <option value="Parts Arrived">🏁 Parts Arrived</option>
                                <option value="Scheduled">🟠 Scheduled</option>
                                <option value="Completed">🟢 Completed</option>
                                <option value="Issue">🔴 Issue</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- New Requests Grid -->
                <section id="new-cases-section" class="space-y-4">
                    <div class="flex items-center justify-between px-1">
                        <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="relative flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-primary-500"></span>
                            </span>
                            New Requests <span id="new-count" class="text-slate-400 font-medium text-sm ml-2 bg-slate-100 px-2 py-0.5 rounded-full">(0)</span>
                        </h2>
                    </div>
                    
                    <div id="new-cases-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <!-- Cards injected here -->
                    </div>
                    
                    <div id="new-cases-empty" class="hidden py-12 flex flex-col items-center justify-center bg-white rounded-2xl border border-dashed border-slate-200 text-slate-400">
                        <div class="bg-slate-50 p-3 rounded-full mb-3"><i data-lucide="inbox" class="w-6 h-6"></i></div>
                        <span class="text-sm font-medium">No new incoming requests</span>
                    </div>
                </section>

                <!-- Active Queue Table -->
                <section>
                    <div class="flex items-center justify-between mb-4 px-1">
                        <h2 class="text-xl font-bold text-slate-800">Processing Queue</h2>
                        <span id="record-count" class="text-xs font-semibold bg-white text-slate-500 border border-slate-200 px-3 py-1 rounded-full shadow-sm">0 active</span>
                    </div>

                    <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden card-hover">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gradient-to-r from-slate-50 via-primary-50/30 to-slate-50 border-b-2 border-primary-200/50 text-xs uppercase tracking-wider text-slate-600 font-bold">
                                    <tr>
                                        <th class="px-6 py-5">Vehicle & Owner</th>
                                        <th class="px-6 py-5">Stage</th>
                                        <th class="px-6 py-5">Contact Info</th>
                                        <th class="px-6 py-5">Customer Reply</th>
                                        <th class="px-6 py-5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="table-body" class="divide-y divide-slate-100">
                                    <!-- Rows injected by JS -->
                                </tbody>
                            </table>
                            <div id="empty-state" class="hidden py-20 flex flex-col items-center justify-center text-center">
                                <div class="bg-slate-50 p-4 rounded-full mb-4 ring-8 ring-slate-50/50"><i data-lucide="filter" class="w-8 h-8 text-slate-300"></i></div>
                                <h3 class="text-slate-900 font-medium">No matching cases found</h3>
                                <p class="text-slate-400 text-sm mt-1 max-w-xs">Try adjusting your search filters or import new transfers above.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- VIEW: VEHICLES -->
            <!-- VIEW: REVIEWS -->
            <!-- VIEW: TEMPLATES (Moved to templates.php) -->
            <!-- VIEW: USERS (Moved to users.php) -->

        </main>
    </div>

    <!-- Notification Prompt (Banner) -->
    <div id="notification-prompt" class="hidden fixed top-24 right-4 z-50 max-w-sm w-full bg-white border border-slate-200 shadow-2xl rounded-2xl p-4 animate-in slide-in-from-right-10 fade-in duration-500">
        <div class="flex items-start gap-4">
            <div class="bg-primary-50 p-3 rounded-xl">
                <i data-lucide="bell-ring" class="w-6 h-6 text-primary-600"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-slate-800 text-sm">Enable Notifications</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">Don't miss out! Get instant alerts when new transfers or messages arrive.</p>
                <div class="mt-3 flex gap-2">
                    <button onclick="window.enableNotifications()" class="flex-1 bg-slate-900 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-slate-800 transition-all shadow-sm">Allow</button>
                    <button onclick="document.getElementById('notification-prompt').remove()" class="px-3 py-2 text-slate-400 hover:text-slate-600 text-xs font-medium transition-colors">Later</button>
                </div>
            </div>
            <button onclick="document.getElementById('notification-prompt').remove()" class="text-slate-300 hover:text-slate-500"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
    </div>

    <!-- Premium Edit Modal -->
    <div id="edit-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <!-- Enhanced Backdrop -->
        <div class="fixed inset-0 bg-gradient-to-br from-slate-900/50 via-primary-900/30 to-slate-900/50 backdrop-blur-md transition-opacity" onclick="window.closeModal()"></div>

        <!-- Dialog -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl shadow-primary-900/20 transition-all sm:my-8 sm:w-full sm:max-w-4xl border-2 border-slate-200/50">
                
                <!-- Premium Header with Gradient -->
                <div class="relative bg-gradient-to-r from-primary-500 via-primary-600 to-accent-600 px-6 py-5 flex justify-between items-center sticky top-0 z-10 shadow-lg">
                    <div class="flex items-center gap-4">
                         <div class="bg-white/20 backdrop-blur-sm border border-white/30 px-4 py-2 rounded-xl text-sm font-mono font-bold text-white shadow-xl">
                            <span id="modal-title-ref">AB-123-CD</span>
                         </div>
                         <div class="h-8 w-px bg-white/30"></div>
                         <div class="flex flex-col">
                             <span class="text-xs text-white/70 font-bold uppercase tracking-wider">Customer</span>
                             <span class="text-base font-bold text-white" id="modal-title-name">User Name</span>
                         </div>
                    </div>
                    <button onclick="window.closeModal()" class="text-white/80 hover:text-white hover:bg-white/20 p-2.5 rounded-xl transition-all">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Enhanced Body -->
                <div class="px-8 py-8 grid grid-cols-1 lg:grid-cols-2 gap-10 max-h-[75vh] overflow-y-auto custom-scrollbar bg-gradient-to-br from-slate-50 to-blue-50/30">
                    
                    <!-- Left Column: Actions -->
                    <div class="space-y-6">
                        <!-- Status -->
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Workflow Stage</label>
                            <div class="relative">
                                <select id="input-status" class="w-full appearance-none bg-white border border-slate-200 text-slate-700 py-3 pl-4 pr-10 rounded-xl leading-tight focus:outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 text-sm font-medium shadow-sm transition-all">
                                    <option value="New">🔵 New Case</option>
                                    <option value="Processing">🟡 Processing</option>
                                    <option value="Called">🟣 Contacted</option>
                                    <option value="Parts Ordered">📦 Parts Ordered</option>
                                    <option value="Parts Arrived">🏁 Parts Arrived</option>
                                    <option value="Scheduled">🟠 Scheduled</option>
                                    <option value="Completed">🟢 Completed</option>
                                    <option value="Issue">🔴 Issue</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-500">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Contact -->
                        <div class="bg-blue-50/50 p-5 rounded-2xl border border-blue-100/50">
                            <label class="block text-xs font-bold text-blue-800/70 uppercase tracking-wider mb-2">Contact Info</label>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <i data-lucide="phone" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-blue-400"></i>
                                    <input id="input-phone" type="text" placeholder="Phone Number" class="w-full pl-9 pr-3 py-2.5 border border-blue-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 outline-none">
                                </div>
                                <a id="btn-call-real" href="#" class="bg-white text-blue-600 border border-blue-200 p-2.5 rounded-xl hover:bg-blue-50 hover:border-blue-300 transition-colors shadow-sm">
                                    <i data-lucide="phone-call" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="space-y-3">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Communication</label>
                            <div class="grid grid-cols-1 gap-3">
                                <button id="btn-sms-register" class="group flex justify-between items-center px-4 py-3.5 bg-white border border-slate-200 rounded-xl hover:border-primary-300 hover:shadow-md transition-all text-left">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-700 group-hover:text-primary-600">Send Welcome SMS</div>
                                        <div class="text-[10px] text-slate-400">Uses 'Welcome' template</div>
                                    </div>
                                    <i data-lucide="message-square" class="w-4 h-4 text-slate-300 group-hover:text-primary-500 transition-colors"></i>
                                </button>
                                <button id="btn-sms-arrived" class="group flex justify-between items-center px-4 py-3.5 bg-white border border-slate-200 rounded-xl hover:border-teal-300 hover:shadow-md transition-all text-left">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-700 group-hover:text-teal-600">Parts Arrived SMS</div>
                                        <div class="text-[10px] text-slate-400">Includes {link}</div>
                                    </div>
                                    <i data-lucide="package-check" class="w-4 h-4 text-slate-300 group-hover:text-teal-500 transition-colors"></i>
                                </button>
                                <button id="btn-sms-schedule" class="group flex justify-between items-center px-4 py-3.5 bg-white border border-slate-200 rounded-xl hover:border-orange-300 hover:shadow-md transition-all text-left">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-700 group-hover:text-orange-600">Send Schedule SMS</div>
                                        <div class="text-[10px] text-slate-400">Uses 'Schedule' template</div>
                                    </div>
                                    <i data-lucide="calendar-check" class="w-4 h-4 text-slate-300 group-hover:text-orange-500 transition-colors"></i>
                                </button>
                            </div>
                        </div>

                        <!-- System Log -->
                        <div class="bg-slate-100/50 rounded-xl border border-slate-200 overflow-hidden">
                            <div class="px-4 py-2 border-b border-slate-200 bg-slate-50/50">
                                <label class="text-[10px] font-bold text-slate-400 uppercase">System Activity</label>
                            </div>
                            <div id="activity-log-container" class="p-3 h-32 overflow-y-auto custom-scrollbar text-xs space-y-1.5"></div>
                        </div>
                    </div>

                    <!-- Right Column: Logistics -->
                    <div class="space-y-6 flex flex-col h-full">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 block">Appointment</span>
                                <input id="input-service-date" type="datetime-local" class="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none shadow-sm">
                            </div>
                            <div>
                                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 block">Franchise (GEL)</span>
                                <input id="input-franchise" type="text" placeholder="0.00" class="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none shadow-sm">
                            </div>
                        </div>

                        <!-- Customer Review Preview -->
                        <div id="modal-review-section" class="hidden bg-gradient-to-br from-yellow-50 to-amber-50 rounded-2xl border border-yellow-200 overflow-hidden shadow-sm p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <i data-lucide="star" class="w-4 h-4 text-yellow-600"></i>
                                <label class="text-xs font-bold text-yellow-700 uppercase tracking-wider">Customer Review</label>
                            </div>
                            <div class="flex items-center gap-3 mb-2">
                                <div id="modal-review-stars" class="flex gap-1"></div>
                                <span id="modal-review-rating" class="text-2xl font-bold text-slate-800"></span>
                            </div>
                            <p id="modal-review-comment" class="text-sm text-slate-600 italic leading-relaxed bg-white/60 p-3 rounded-lg"></p>
                        </div>

                        <!-- Reschedule Request Preview -->
                        <div id="modal-reschedule-section" class="hidden bg-gradient-to-br from-purple-50 to-indigo-50 rounded-2xl border border-purple-200 overflow-hidden shadow-sm">
                            <div class="flex items-center justify-between mb-3 px-4 pt-4">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="calendar-clock" class="w-4 h-4 text-purple-600"></i>
                                    <label class="text-xs font-bold text-purple-700 uppercase tracking-wider">Reschedule Request</label>
                                </div>
                                <span id="reschedule-status-badge" class="text-[10px] bg-purple-100 text-purple-700 px-2 py-1 rounded-full font-bold">Pending</span>
                            </div>
                            <div class="space-y-3 px-4 pb-4">
                                <div class="bg-white/80 p-3 rounded-lg border border-purple-100">
                                    <span class="text-xs text-purple-600 font-semibold block mb-1">Requested Date:</span>
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="calendar" class="w-4 h-4 text-purple-500"></i>
                                        <span id="modal-reschedule-date" class="text-sm font-bold text-slate-800"></span>
                                    </div>
                                </div>
                                <div class="bg-white/80 p-3 rounded-lg border border-purple-100">
                                    <span class="text-xs text-purple-600 font-semibold block mb-1">Customer Comment:</span>
                                    <p id="modal-reschedule-comment" class="text-sm text-slate-600 italic leading-relaxed"></p>
                                </div>
                                <div id="reschedule-actions" class="flex gap-2 pt-2">
                                    <button onclick="window.acceptReschedule()" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2.5 px-4 rounded-lg font-semibold text-sm transition-all active:scale-95 flex items-center justify-center gap-2 shadow-sm">
                                        <i data-lucide="check" class="w-4 h-4"></i> Accept & Update
                                    </button>
                                    <button onclick="window.declineReschedule()" class="flex-1 bg-white hover:bg-red-50 text-red-600 border-2 border-red-200 py-2.5 px-4 rounded-lg font-semibold text-sm transition-all active:scale-95">
                                        Decline
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 flex flex-col bg-yellow-50/50 rounded-2xl border border-yellow-100 overflow-hidden shadow-sm">
                            <div class="px-4 py-3 bg-yellow-50 border-b border-yellow-100 flex justify-between items-center">
                                <label class="text-xs font-bold text-yellow-700 uppercase tracking-wider flex items-center gap-2">
                                    <i data-lucide="sticky-note" class="w-3 h-3"></i> Team Notes
                                </label>
                                <span class="text-[10px] bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full font-medium">Internal</span>
                            </div>
                            <div id="notes-list" class="flex-1 p-4 overflow-y-auto custom-scrollbar space-y-3 min-h-[200px] bg-white/50"></div>
                            <div class="p-3 bg-white border-t border-yellow-100 flex gap-2">
                                <input id="new-note-input" type="text" placeholder="Type a note..." class="flex-1 text-sm px-3 py-2 border border-slate-200 rounded-lg focus:border-yellow-400 outline-none">
                                <button onclick="window.addNote()" class="bg-yellow-500 text-white p-2 rounded-lg hover:bg-yellow-600 transition-colors shadow-sm active:scale-95">
                                    <i data-lucide="send" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-white px-6 py-4 border-t border-slate-100 flex justify-between items-center rounded-b-2xl">
                    <button type="button" onclick="window.deleteRecord(window.currentEditingId)" class="text-red-500 hover:text-red-700 hover:bg-red-50 text-sm font-semibold flex items-center gap-2 px-3 py-2 rounded-lg transition-colors">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                    </button>
                    <div class="flex gap-3">
                        <button type="button" onclick="window.closeModal()" class="px-5 py-2.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-xl font-medium text-sm transition-colors">Close</button>
                        <button type="button" onclick="window.saveEdit()" class="px-6 py-2.5 bg-slate-900 text-white hover:bg-slate-800 rounded-xl font-semibold text-sm shadow-lg shadow-slate-900/20 transition-all active:scale-95 flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <!-- User Management Modals -->
    <?php if ($current_user_role === 'admin'): ?>
    <!-- Create/Edit User Modal -->


    <script>
        const API_URL = 'api.php';
        const MANAGER_PHONE = "511144486";
        const USER_ROLE = '<?php echo $current_user_role; ?>';
        const CAN_EDIT = USER_ROLE === 'admin' || USER_ROLE === 'manager';
        
        // 1. FIREBASE CONFIG (REPLACE WITH YOURS)
        const firebaseConfig = {
            apiKey: "AIzaSyBRvdcvgMsOiVzeUQdSMYZFQ1GKkHZUWYI",
            authDomain: "otm-portal-312a5.firebaseapp.com",
            projectId: "otm-portal-312a5",
            storageBucket: "otm-portal-312a5.firebasestorage.app",
            messagingSenderId: "917547807534",
            appId: "1:917547807534:web:9021c744b7b0f62b4e80bf"
        };

        // Initialize Firebase
        try {
            firebase.initializeApp(firebaseConfig);
            const messaging = firebase.messaging();
            
            // Handle foreground messages
            messaging.onMessage((payload) => {
                console.log('Message received. ', payload);
                const { title, body } = payload.notification;
                showToast(`${title}: ${body}`, 'success');
            });
        } catch (e) {
            console.log("Firebase init failed (check config):", e);
        }

        // Notification Logic
        window.requestNotificationPermission = async () => {
            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    const token = await firebase.messaging().getToken({ vapidKey: 'BPmaDT11APIDJCEoLFGA7ZoUCmc2IM9wxsNPJsy4984GaZNhBEEJa1VG6C65t1oCMTtUPVSudeivYsAmINDGc-w' });
                    if (token) {
                        await fetchAPI('register_token', 'POST', { token });
                        showToast("Notifications Enabled");
                    }
                } else {
                    showToast("Permission denied", "error");
                }
            } catch (error) {
                console.error('Unable to get permission', error);
            }
        };

        let transfers = [];
        let vehicles = [];
        window.currentEditingId = null;
        let parsedImportData = [];
        const currentUser = { uid: "manager", name: "Manager" }; 

        // Helper
        const normalizePlate = (p) => p ? p.replace(/[^a-zA-Z0-9]/g, '').toUpperCase() : '';

        // --- API HELPERS ---
        async function fetchAPI(action, method = 'GET', body = null) {
            const opts = { method };
            if (body) opts.body = JSON.stringify(body);
            
            // If strictly using Mock Data, skip fetch
            if (USE_MOCK_DATA) {
                return getMockData(action, body);
            }

            try {
                const res = await fetch(`${API_URL}?action=${action}`, opts);
                
                // Check if response is NOT OK (e.g. 500 Error)
                if (!res.ok) {
                    // Try to parse the JSON error message from api.php
                    let errorText = res.statusText;
                    try {
                        const errorJson = await res.json();
                        if (errorJson.error) errorText = errorJson.error;
                    } catch (parseErr) {
                        // If parsing fails, use the text body or generic status
                        const text = await res.text();
                        if(text) errorText = text.substring(0, 100); // Limit length
                    }
                    throw new Error(`Server Error (${res.status}): ${errorText}`);
                }

                const data = await res.json();
                
                // Update UI Connection Status
                const statusEl = document.getElementById('connection-status');
                if(statusEl) statusEl.innerHTML = `<span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span> SQL Connected`;
                
                return data;
            } catch (e) {
                console.warn("Server unavailable:", e);
                const statusEl = document.getElementById('connection-status');
                if(statusEl) statusEl.innerHTML = `<span class="w-2 h-2 bg-red-500 rounded-full"></span> Connection Failed`;
                
                // Show detailed error in toast
                showToast("Connection Error", e.message, "error");
                throw e; 
            }
        }

        // Mock Data Handler (For Demo/Fallback)
        function getMockData(action, body) {
            // Update UI
            const statusEl = document.getElementById('connection-status');
            if(statusEl) statusEl.innerHTML = `<span class="w-2 h-2 bg-yellow-500 rounded-full"></span> Demo Mode`;

            return new Promise(resolve => {
                setTimeout(() => {
                    if (action === 'get_transfers') resolve(transfers.length ? transfers : []);
                    else if (action === 'get_vehicles') resolve(vehicles.length ? vehicles : []);
                    else if (action === 'add_transfer') {
                        const newId = Math.floor(Math.random()*10000);
                        resolve({ id: newId, status: 'success' });
                    }
                    else if (action === 'save_vehicle') resolve({ status: 'success' });
                    else resolve({ status: 'mock_success' });
                }, 100);
            });
        }

        // --- CONFIGURATION ---
        // Set to FALSE to stop using fake data and connect to your SQL Database
        const USE_MOCK_DATA = false; 

        async function loadData() {
            try {
                const response = await fetchAPI('get_transfers');
                
                // Handle new combined response format
                if (response.transfers && response.vehicles) {
                    transfers = response.transfers;
                    vehicles = response.vehicles;
                } else if (Array.isArray(response)) {
                    // Fallback for old format (just transfers array)
                    transfers = response;
                    const newVehicles = await fetchAPI('get_vehicles');
                    if(Array.isArray(newVehicles)) vehicles = newVehicles;
                }

                renderTable();
            } catch(e) {
                // Squelch load errors to prevent loop spam, alert user once via status
            }

            document.getElementById('loading-screen').classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                document.getElementById('loading-screen').classList.add('hidden');
                document.getElementById('app-content').classList.remove('hidden');
            }, 500);
        }

        // Poll for updates every 10 seconds
        setInterval(loadData, 10000);

        // Premium Toast Notifications
        function showToast(title, message = '', type = 'success', duration = 4000) {
            const container = document.getElementById('toast-container');
            
            // Handle legacy calls
            if (typeof type === 'number') { duration = type; type = 'success'; } // fallback
            if (!message && !type) { type = 'success'; }
            else if (['success', 'error', 'info', 'urgent'].includes(message)) { type = message; message = ''; }
            
            // Create toast
            const toast = document.createElement('div');
            
            const colors = {
                success: { 
                    bg: 'bg-white/95 backdrop-blur-xl', 
                    border: 'border-emerald-200/60', 
                    iconBg: 'bg-gradient-to-br from-emerald-50 to-teal-50', 
                    iconColor: 'text-emerald-600', 
                    icon: 'check-circle-2',
                    shadow: 'shadow-emerald-500/20' 
                },
                error: { 
                    bg: 'bg-white/95 backdrop-blur-xl', 
                    border: 'border-red-200/60', 
                    iconBg: 'bg-gradient-to-br from-red-50 to-orange-50', 
                    iconColor: 'text-red-600', 
                    icon: 'alert-circle',
                    shadow: 'shadow-red-500/20' 
                },
                info: { 
                    bg: 'bg-white/95 backdrop-blur-xl', 
                    border: 'border-primary-200/60', 
                    iconBg: 'bg-gradient-to-br from-primary-50 to-accent-50', 
                    iconColor: 'text-primary-600', 
                    icon: 'info',
                    shadow: 'shadow-primary-500/20' 
                },
                urgent: { 
                    bg: 'bg-white/95 backdrop-blur-xl toast-urgent', 
                    border: 'border-primary-300', 
                    iconBg: 'bg-gradient-to-br from-primary-100 to-accent-100', 
                    iconColor: 'text-primary-700', 
                    icon: 'bell-ring',
                    shadow: 'shadow-primary-500/30' 
                }
            };
            
            const style = colors[type] || colors.info;

            toast.className = `pointer-events-auto w-80 ${style.bg} border-2 ${style.border} shadow-2xl ${style.shadow} rounded-2xl p-4 flex items-start gap-3 transform transition-all duration-500 translate-y-10 opacity-0`;
            
            toast.innerHTML = `
                <div class="${style.iconBg} p-3 rounded-xl shrink-0 shadow-inner">
                    <i data-lucide="${style.icon}" class="w-5 h-5 ${style.iconColor}"></i>
                </div>
                <div class="flex-1 pt-1">
                    <h4 class="text-sm font-bold text-slate-900 leading-none mb-1.5">${title}</h4>
                    ${message ? `<p class="text-xs text-slate-600 leading-relaxed font-medium">${message}</p>` : ''}
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-slate-600 transition-colors -mt-1 -mr-1 p-1.5 hover:bg-slate-100 rounded-lg">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            `;

            container.appendChild(toast);
            if(window.lucide) lucide.createIcons();

            // Animate In
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-10', 'opacity-0');
            });

            // Auto Dismiss (unless persistent/urgent)
            if (duration > 0 && type !== 'urgent') {
                setTimeout(() => {
                    toast.classList.add('translate-y-4', 'opacity-0');
                    setTimeout(() => toast.remove(), 500);
                }, duration);
            }
        }

        window.switchView = (v) => {
            // Toggle views (check if element exists before accessing)
            const dashboardView = document.getElementById('view-dashboard');
            const templatesView = document.getElementById('view-templates');
            const usersView = document.getElementById('view-users');
            
            if (dashboardView) dashboardView.classList.toggle('hidden', v !== 'dashboard');
            if (templatesView) templatesView.classList.toggle('hidden', v !== 'templates');
            if (usersView) usersView.classList.toggle('hidden', v !== 'users');
            
            const activeClass = "nav-active px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 bg-slate-900 text-white shadow-sm";
            const inactiveClass = "nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 text-slate-500 hover:text-slate-900 hover:bg-white";

            // Update nav button (check if element exists)
            const navDashboard = document.getElementById('nav-dashboard');
            if (navDashboard) navDashboard.className = v === 'dashboard' ? activeClass : inactiveClass;
        };

        // --- SMS TEMPLATE LOGIC (Template editing moved to templates.php) ---
        const defaultTemplates = {
            'registered': "Hello {name}, payment received. Ref: {plate}. Welcome to OTOMOTORS service.",
            'called': "Hello {name}, we contacted you regarding {plate}. Service details will follow shortly.",
            'schedule': "Hello {name}, service scheduled for {date}. Ref: {plate}.",
            'parts_ordered': "Parts ordered for {plate}. We will notify you when ready.",
            'parts_arrived': "Hello {name}, your parts have arrived! Please confirm your visit here: {link}",
            'rescheduled': "Hello {name}, your service has been rescheduled to {date}. Please confirm: {link}",
            'reschedule_accepted': "Hello {name}, your reschedule request has been approved! New appointment: {date}. Ref: {plate}. - OTOMOTORS",
            'completed': "Service for {plate} is completed. Thank you for choosing OTOMOTORS! Rate your experience: {link}",
            'issue': "Hello {name}, we detected an issue with {plate}. Our team will contact you shortly."
        };
        
        let smsTemplates = defaultTemplates;

        // Load templates from API
        async function loadSMSTemplates() {
            try {
                const serverTemplates = await fetchAPI('get_templates');
                smsTemplates = { ...defaultTemplates, ...serverTemplates };
            } catch (e) {
                console.error("Template load error:", e);
                // Fallback to defaults
                smsTemplates = defaultTemplates;
            }
        }

        // Format SMS message with template placeholders
        function getFormattedMessage(type, data) {
            let template = smsTemplates[type] || defaultTemplates[type] || "";
            const baseUrl = window.location.href.replace(/index\.php.*/, '').replace(/\/$/, '');
            const link = `${baseUrl}/public_view.php?id=${data.id}`;

            return template
                .replace(/{name}/g, data.name || '')
                .replace(/{plate}/g, data.plate || '')
                .replace(/{amount}/g, data.amount || '')
                .replace(/{link}/g, link)
                .replace(/{date}/g, data.serviceDate ? data.serviceDate.replace('T', ' ') : '');
        }

        // Notification Prompt & Load Templates
        document.addEventListener('DOMContentLoaded', () => {
            if ('Notification' in window && Notification.permission === 'default') {
                const prompt = document.getElementById('notification-prompt');
                if(prompt) setTimeout(() => prompt.classList.remove('hidden'), 2000);
            }
            loadSMSTemplates(); // Load templates from API on start
        });

        // --- TRANSFERS ---
        window.parseBankText = () => {
            const text = document.getElementById('import-text').value;
            if(!text) return;
            const lines = text.split(/\r?\n/);
            parsedImportData = [];
            
            // Patterns
            const regexes = [
                /Transfer from ([\w\s]+), Plate: ([\w\d]+), Amt: (\d+)/i,
                /INSURANCE PAY \| ([\w\d]+) \| ([\w\s]+) \| (\d+)/i,
                /User: ([\w\s]+) Car: ([\w\d]+) Sum: ([\w\d\.]+)/i,
                /მანქანის ნომერი:\s*([A-Za-z0-9]+)\s*დამზღვევი:\s*([^,]+),\s*([\d\.]+)/i
            ];
            
            const franchiseRegex = /\(ფრანშიზა\s*([\d\.]+)\)/i;

            lines.forEach(line => {
                for(let r of regexes) {
                    const m = line.match(r);
                    if(m) {
                        let plate, name, amount;
                        if(r.source.includes('Transfer from')) { name=m[1]; plate=m[2]; amount=m[3]; }
                        else if(r.source.includes('INSURANCE')) { plate=m[1]; name=m[2]; amount=m[3]; }
                        else if(r.source.includes('User:')) { name=m[1]; plate=m[2]; amount=m[3]; }
                        else { plate=m[1]; name=m[2]; amount=m[3]; } 
                        
                        let franchise = '';
                        const fMatch = line.match(franchiseRegex);
                        if(fMatch) franchise = fMatch[1];

                        parsedImportData.push({ 
                            plate: plate.trim(), 
                            name: name.trim(), 
                            amount: amount.trim(), 
                            franchise: franchise,
                            rawText: line 
                        });
                        break;
                    }
                }
            });

            if(parsedImportData.length > 0) {
                document.getElementById('parsed-result').classList.remove('hidden');
                document.getElementById('parsed-placeholder').classList.add('hidden');
                document.getElementById('parsed-content').innerHTML = parsedImportData.map(i => 
                    `<div class="bg-white p-3 border border-emerald-100 rounded-lg mb-2 text-xs flex justify-between items-center shadow-sm">
                        <div class="flex items-center gap-2">
                            <div class="font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">${i.plate}</div> 
                            <span class="text-slate-500">${i.name}</span>
                            ${i.franchise ? `<span class="text-orange-500 bg-orange-50 px-1.5 py-0.5 rounded ml-1">Franchise: ${i.franchise}</span>` : ''}
                        </div>
                        <div class="font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">${i.amount} ₾</div>
                    </div>`
                ).join('');
                document.getElementById('btn-save-import').innerHTML = `<i data-lucide="save" class="w-4 h-4"></i> Save ${parsedImportData.length} Items`;
                lucide.createIcons();
            } else {
                showToast("No matches found", "error");
            }
        };

        window.saveParsedImport = async () => {
            const btn = document.getElementById('btn-save-import');
            btn.disabled = true; btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...`;
            
            for(let data of parsedImportData) {
                const res = await fetchAPI('add_transfer', 'POST', data);
                if (res && res.id && data.franchise) {
                    await fetchAPI(`update_transfer&id=${res.id}`, 'POST', { franchise: data.franchise });
                }
                await fetchAPI('sync_vehicle', 'POST', { plate: data.plate, ownerName: data.name });
            }
            
            if(MANAGER_PHONE) {
                const msg = `System Alert: ${parsedImportData.length} new transfer(s) added to OTOMOTORS portal.`;
                window.sendSMS(MANAGER_PHONE, msg, 'system');
            }
            
            await fetchAPI('send_broadcast', 'POST', { 
                title: 'New Transfers Imported', 
                body: `${parsedImportData.length} new cases added.` 
            });

            document.getElementById('import-text').value = '';
            document.getElementById('parsed-result').classList.add('hidden');
            document.getElementById('parsed-placeholder').classList.remove('hidden');
            loadData();
            showToast("Import Successful", "success");
            btn.disabled = false;
            btn.innerHTML = `<i data-lucide="save" class="w-4 h-4"></i> Confirm & Save`;
            lucide.createIcons();
        };

        function renderTable() {
            const search = document.getElementById('search-input').value.toLowerCase();
            const filter = document.getElementById('status-filter').value;
            const replyFilter = document.getElementById('reply-filter').value;
            
            const newContainer = document.getElementById('new-cases-grid');
            const activeContainer = document.getElementById('table-body');
            newContainer.innerHTML = ''; activeContainer.innerHTML = '';
            
            let newCount = 0;
            let activeCount = 0;

            transfers.forEach(t => {
                // 1. Text Search Filter
                const match = (t.plate+t.name+(t.phone||'')).toLowerCase().includes(search);
                if(!match) return;

                // 2. Status Filter
                if(filter !== 'All' && t.status !== filter) return;

                // 3. Reply Filter (Logic: 'Not Responded' matches 'Pending' or null)
                if (replyFilter !== 'All') {
                    if (replyFilter === 'Pending') {
                        // Match "Not Responded" (Pending or empty)
                        if (t.user_response && t.user_response !== 'Pending') return;
                    } else {
                        // Match specific reply (Confirmed / Reschedule)
                        if (t.user_response !== replyFilter) return;
                    }
                }

                const dateObj = new Date(t.created_at || Date.now());
                const dateStr = dateObj.toLocaleDateString('en-GB', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

                // Find linked vehicle info for display (Normalized Matching)
                const linkedVehicle = vehicles.find(v => normalizePlate(v.plate) === normalizePlate(t.plate));
                const displayPhone = t.phone || (linkedVehicle ? linkedVehicle.phone : null);

                if(t.status === 'New') {
                    newCount++;
                    newContainer.innerHTML += `
                        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-primary-500"></div>
                            <div class="flex justify-between mb-3 pl-3">
                                <span class="bg-primary-50 text-primary-700 border border-primary-100 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> ${dateStr}</span>
                                <span class="text-xs font-mono font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded">${t.amount} ₾</span>
                            </div>
                            <div class="pl-3 mb-5">
                                <h3 class="font-bold text-lg text-slate-800">${t.plate}</h3>
                                <p class="text-xs text-slate-500 font-medium">${t.name}</p>
                                ${displayPhone ? `<div class="flex items-center gap-1.5 mt-2 text-xs text-slate-600 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100 w-fit"><i data-lucide="phone" class="w-3 h-3 text-slate-400"></i> ${displayPhone}</div>` : ''}
                                ${t.franchise ? `<p class="text-[10px] text-orange-500 mt-1">Franchise: ${t.franchise}</p>` : ''}
                            </div>
                            <div class="pl-3 text-right">
                                <button onclick="window.openEditModal(${t.id})" class="bg-white border border-slate-200 text-slate-700 text-xs font-semibold px-4 py-2 rounded-lg hover:border-primary-500 hover:text-primary-600 transition-all shadow-sm flex items-center gap-2 ml-auto group-hover:bg-primary-50">
                                    Process Case <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </button>
                            </div>
                        </div>`;
                } else {
                    activeCount++;
                    
                    const statusColors = {
                        'Processing': 'bg-yellow-100 text-yellow-800 border-yellow-200',
                        'Called': 'bg-purple-100 text-purple-800 border-purple-200',
                        'Parts Ordered': 'bg-indigo-100 text-indigo-800 border-indigo-200',
                        'Parts Arrived': 'bg-teal-100 text-teal-800 border-teal-200',
                        'Scheduled': 'bg-orange-100 text-orange-800 border-orange-200',
                        'Completed': 'bg-emerald-100 text-emerald-800 border-emerald-200',
                        'Issue': 'bg-red-100 text-red-800 border-red-200'
                    };
                    const badgeClass = statusColors[t.status] || 'bg-slate-100 text-slate-600 border-slate-200';
                    
                    const hasPhone = t.phone ? 
                        `<span class="flex items-center gap-1.5 text-slate-600 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100 w-fit"><i data-lucide="phone" class="w-3 h-3 text-slate-400"></i> ${t.phone}</span>` : 
                        `<span class="text-red-400 text-xs flex items-center gap-1"><i data-lucide="alert-circle" class="w-3 h-3"></i> Missing</span>`;
                    
                    // USER RESPONSE LOGIC
                    let replyBadge = `<span class="bg-slate-100 text-slate-500 border border-slate-200 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 w-fit"><i data-lucide="help-circle" class="w-3 h-3"></i> Not Responded</span>`;
                    
                    if (t.user_response === 'Confirmed') {
                        replyBadge = `<span class="bg-green-100 text-green-700 border border-green-200 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 w-fit"><i data-lucide="check" class="w-3 h-3"></i> Confirmed</span>`;
                    } else if (t.user_response === 'Reschedule Requested') {
                        let rescheduleInfo = '';
                        let quickAcceptBtn = '';
                        if (t.rescheduleDate) {
                            const reqDate = new Date(t.rescheduleDate.replace(' ', 'T'));
                            const dateStr = reqDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                            rescheduleInfo = `<div class="text-[9px] text-orange-600 mt-0.5 flex items-center gap-1"><i data-lucide="calendar" class="w-2.5 h-2.5"></i> ${dateStr}</div>`;
                            quickAcceptBtn = `<button onclick="event.stopPropagation(); window.quickAcceptReschedule(${t.id})" class="mt-1 bg-green-600 hover:bg-green-700 text-white text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 transition-all active:scale-95 shadow-sm">
                                <i data-lucide="check" class="w-3 h-3"></i> Accept
                            </button>`;
                        }
                        replyBadge = `<div class="flex flex-col items-start gap-1">
                            <span class="bg-orange-100 text-orange-700 border border-orange-200 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 w-fit animate-pulse">
                                <i data-lucide="clock" class="w-3 h-3"></i> Reschedule Request
                            </span>
                            ${rescheduleInfo}
                            ${quickAcceptBtn}
                        </div>`;
                    }

                    activeContainer.innerHTML += `
                        <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="bg-white border border-slate-200 text-slate-800 font-mono font-bold px-2.5 py-1.5 rounded-lg text-sm shadow-sm">${t.plate}</div>
                                    <div>
                                        <div class="font-semibold text-sm text-slate-800">${t.name}</div>
                                        <div class="text-xs text-slate-400 font-mono">${t.amount} ₾</div>
                                        <div class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5"><i data-lucide="clock" class="w-3 h-3"></i> ${dateStr}</div>
                                        ${t.franchise ? `<div class="text-[10px] text-orange-500 mt-0.5">Franchise: ${t.franchise}</div>` : ''}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-[10px] uppercase tracking-wider font-bold border ${badgeClass}">${t.status}</span></td>
                            <td class="px-6 py-4 text-sm">${hasPhone}</td>
                            <td class="px-6 py-4">${replyBadge}</td>
                            <td class="px-6 py-4 text-right">
                                ${CAN_EDIT ? 
                                    `<button onclick="window.openEditModal(${t.id})" class="text-slate-400 hover:text-primary-600 p-2 hover:bg-primary-50 rounded-lg transition-all"><i data-lucide="settings-2" class="w-4 h-4"></i></button>` :
                                    `<button onclick="window.viewCase(${t.id})" class="text-slate-400 hover:text-blue-600 p-2 hover:bg-blue-50 rounded-lg transition-all" title="View Only"><i data-lucide="eye" class="w-4 h-4"></i></button>`
                                }
                            </td>
                        </tr>`;
                }
            });

            document.getElementById('new-count').innerText = `${newCount}`;
            document.getElementById('record-count').innerText = `${activeCount} active`;
            document.getElementById('new-cases-empty').classList.toggle('hidden', newCount > 0);
            document.getElementById('empty-state').classList.toggle('hidden', activeCount > 0);
            lucide.createIcons();
        }

        window.openEditModal = (id) => {
            const t = transfers.find(i => i.id == id);
            if(!t) return;
            window.currentEditingId = id; // Ensure global scope assignment
            
            // Auto-fill phone from registry if missing in transfer
            const linkedVehicle = vehicles.find(v => normalizePlate(v.plate) === normalizePlate(t.plate));
            const phoneToFill = t.phone || (linkedVehicle ? linkedVehicle.phone : '');

            document.getElementById('modal-title-ref').innerText = t.plate;
            document.getElementById('modal-title-name').innerText = t.name;
            document.getElementById('input-phone').value = phoneToFill;
            document.getElementById('input-service-date').value = t.serviceDate ? t.serviceDate.replace(' ', 'T') : ''; 
            document.getElementById('input-franchise').value = t.franchise || '';
            document.getElementById('input-status').value = t.status;
            
            document.getElementById('btn-call-real').href = t.phone ? `tel:${t.phone}` : '#';
            document.getElementById('btn-sms-register').onclick = () => {
                // Use Template for Welcome SMS
                const templateData = { 
                    id: t.id, // ID needed for link gen
                    name: t.name, 
                    plate: t.plate, 
                    amount: t.amount, 
                    serviceDate: document.getElementById('input-service-date').value 
                };
                const msg = getFormattedMessage('registered', templateData);
                window.sendSMS(document.getElementById('input-phone').value, msg, 'registered');
            };

            document.getElementById('btn-sms-arrived').onclick = () => {
                const date = document.getElementById('input-service-date').value;
                if (!date) return showToast("Time Required", "Please set an Appointment date for Parts Arrived SMS", "error");
                
                const templateData = { 
                    id: t.id,
                    name: t.name, 
                    plate: t.plate, 
                    amount: t.amount, 
                    serviceDate: date 
                };
                const msg = getFormattedMessage('parts_arrived', templateData);
                window.sendSMS(document.getElementById('input-phone').value, msg, 'parts_arrived');
            };

            document.getElementById('btn-sms-schedule').onclick = () => {
                const date = document.getElementById('input-service-date').value;
                if (!date) return showToast("Please set an Appointment date first", "error");
                // Use Template for Schedule SMS
                const templateData = { 
                    id: t.id,
                    name: t.name, 
                    plate: t.plate, 
                    amount: t.amount, 
                    serviceDate: date 
                };
                const msg = getFormattedMessage('schedule', templateData);
                window.sendSMS(document.getElementById('input-phone').value, msg, 'schedule');
            };

            const logHTML = (t.systemLogs || []).map(l => `
                <div class="mb-2 last:mb-0 pl-3 border-l-2 border-slate-200 text-slate-600">
                    <div class="text-[10px] text-slate-400 uppercase tracking-wider mb-0.5">${l.timestamp.split('T')[0]}</div>
                    ${l.message}
                </div>`).join('');
            document.getElementById('activity-log-container').innerHTML = logHTML || '<div class="text-center py-4"><span class="italic text-slate-300 text-xs">No system activity recorded</span></div>';
            
            const noteHTML = (t.internalNotes || []).map(n => `
                <div class="bg-white p-3 rounded-lg border border-yellow-100 shadow-sm mb-3">
                    <p class="text-sm text-slate-700">${n.text}</p>
                    <div class="flex justify-end mt-2"><span class="text-[10px] text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">${n.authorName}</span></div>
                </div>`).join('');
            document.getElementById('notes-list').innerHTML = noteHTML || '<div class="h-full flex items-center justify-center text-slate-400 text-xs italic">No team notes yet</div>';

            // Display customer review if exists
            const reviewSection = document.getElementById('modal-review-section');
            if (t.reviewStars && t.reviewStars > 0) {
                reviewSection.classList.remove('hidden');
                document.getElementById('modal-review-rating').innerText = t.reviewStars;
                
                // Render stars
                const starsHTML = Array(5).fill(0).map((_, i) => 
                    `<i data-lucide="star" class="w-5 h-5 ${i < t.reviewStars ? 'text-yellow-400 fill-yellow-400' : 'text-gray-300'}"></i>`
                ).join('');
                document.getElementById('modal-review-stars').innerHTML = starsHTML;
                
                // Display comment
                const comment = t.reviewComment || 'No comment provided';
                document.getElementById('modal-review-comment').innerText = comment;
            } else {
                reviewSection.classList.add('hidden');
            }

            // Display reschedule request if exists
            const rescheduleSection = document.getElementById('modal-reschedule-section');
            if (t.userResponse === 'Reschedule Requested' && (t.rescheduleDate || t.rescheduleComment)) {
                rescheduleSection.classList.remove('hidden');
                
                if (t.rescheduleDate) {
                    const requestedDate = new Date(t.rescheduleDate.replace(' ', 'T'));
                    document.getElementById('modal-reschedule-date').innerText = requestedDate.toLocaleString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit'
                    });
                } else {
                    document.getElementById('modal-reschedule-date').innerText = 'Not specified';
                }
                
                const rescheduleComment = t.rescheduleComment || 'No additional comments';
                document.getElementById('modal-reschedule-comment').innerText = rescheduleComment;
            } else {
                rescheduleSection.classList.add('hidden');
            }

            document.getElementById('edit-modal').classList.remove('hidden');
            lucide.createIcons();
        };

        window.closeModal = () => { document.getElementById('edit-modal').classList.add('hidden'); window.currentEditingId = null; };

        window.viewCase = function(id) {
            window.openEditModal(id);
            // Disable all form inputs for viewers
            if (!CAN_EDIT) {
                const modal = document.getElementById('edit-modal');
                modal.querySelectorAll('input, select, textarea, button[onclick*="save"]').forEach(el => {
                    el.disabled = true;
                });
                // Change save button to close
                const saveBtn = modal.querySelector('button[onclick*="saveEdit"]');
                if (saveBtn) {
                    saveBtn.textContent = 'Close';
                    saveBtn.onclick = window.closeModal;
                }
            }
        };

        window.saveEdit = async () => {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to edit cases', 'error');
                return;
            }
            const t = transfers.find(i => i.id == window.currentEditingId);
            const status = document.getElementById('input-status').value;
            const phone = document.getElementById('input-phone').value;
            const serviceDate = document.getElementById('input-service-date').value;
            
            // VALIDATION: Parts Arrived requires a date
            if (status === 'Parts Arrived' && !serviceDate) {
                return showToast("Scheduling Required", "Please select a service date to save 'Parts Arrived' status.", "error");
            }

            const updates = {
                status,
                phone,
                serviceDate: serviceDate || null,
                franchise: document.getElementById('input-franchise').value,
                internalNotes: t.internalNotes || [],
                systemLogs: t.systemLogs || []
            };

            // AUTO-RESCHEDULE LOGIC (Existing)
            const currentDateStr = t.serviceDate ? t.serviceDate.replace(' ', 'T').slice(0, 16) : '';
            if (t.user_response === 'Reschedule Requested' && serviceDate && serviceDate !== currentDateStr) {
                updates.user_response = 'Pending';
                updates.systemLogs.push({ message: `Rescheduled to ${serviceDate.replace('T', ' ')}`, timestamp: new Date().toISOString(), type: 'info' });
                const templateData = { id: t.id, name: t.name, plate: t.plate, amount: t.amount, serviceDate: serviceDate };
                const msg = getFormattedMessage('rescheduled', templateData);
                window.sendSMS(phone, msg, 'rescheduled');
            }

            // --- NEW AUTOMATED SMS LOGIC ---
            if(status !== t.status) {
                updates.systemLogs.push({ message: `Status: ${t.status} -> ${status}`, timestamp: new Date().toISOString(), type: 'status' });
                
                if (phone) {
                    const templateData = { 
                        id: t.id, 
                        name: t.name, 
                        plate: t.plate, 
                        amount: t.amount, 
                        serviceDate: serviceDate || t.serviceDate // Use new date if set, else old
                    };

                    // 1. Processing -> Welcome SMS
                    if (status === 'Processing') {
                        const msg = getFormattedMessage('registered', templateData);
                        window.sendSMS(phone, msg, 'welcome_sms');
                    }
                    
                    // 2. Scheduled -> Service Schedule SMS
                    else if (status === 'Scheduled') {
                        if(!serviceDate) showToast("Note", "Status set to Scheduled without a date.", "info");
                        const msg = getFormattedMessage('schedule', templateData);
                        window.sendSMS(phone, msg, 'schedule_sms');
                    }

                    // 3. Contacted -> Called SMS
                    else if (status === 'Called') {
                        const msg = getFormattedMessage('called', templateData);
                        window.sendSMS(phone, msg, 'contacted_sms');
                    }

                    // 4. Parts Ordered -> Parts Ordered SMS
                    else if (status === 'Parts Ordered') {
                        const msg = getFormattedMessage('parts_ordered', templateData);
                        window.sendSMS(phone, msg, 'parts_ordered_sms');
                    }

                    // 5. Parts Arrived -> Parts Arrived SMS
                    else if (status === 'Parts Arrived') {
                        const msg = getFormattedMessage('parts_arrived', templateData);
                        window.sendSMS(phone, msg, 'parts_arrived_sms');
                    }

                    // 6. Completed -> Completed SMS with review link
                    else if (status === 'Completed') {
                        const msg = getFormattedMessage('completed', templateData);
                        window.sendSMS(phone, msg, 'completed_sms');
                    }

                    // 7. Issue -> Issue SMS
                    else if (status === 'Issue') {
                        const msg = getFormattedMessage('issue', templateData);
                        window.sendSMS(phone, msg, 'issue_sms');
                    }
                }
            }

            if(phone) {
                if (document.getElementById('connection-status').innerText.includes('Offline')) {
                    const v = vehicles.find(v => v.plate === t.plate);
                    if(v) v.phone = phone;
                } else {
                    await fetchAPI('sync_vehicle', 'POST', { plate: t.plate, phone: phone });
                }
            }

            if (document.getElementById('connection-status').innerText.includes('Offline')) {
                Object.assign(t, updates);
            } else {
                await fetchAPI(`update_transfer&id=${window.currentEditingId}`, 'POST', updates);
            }
            
            loadData();
            showToast("Changes Saved", "success");
        };

        window.addNote = async () => {
            const text = document.getElementById('new-note-input').value;
            if(!text) return;
            const t = transfers.find(i => i.id == window.currentEditingId);
            const newNote = { text, authorName: 'Manager', timestamp: new Date().toISOString() };
            
            if (document.getElementById('connection-status').innerText.includes('Offline')) {
                if(!t.internalNotes) t.internalNotes = [];
                t.internalNotes.push(newNote);
            } else {
                const notes = [...(t.internalNotes || []), newNote];
                await fetchAPI(`update_transfer&id=${window.currentEditingId}`, 'POST', { internalNotes: notes });
                t.internalNotes = notes;
            }
            
            document.getElementById('new-note-input').value = '';
            
            // Re-render notes
            const noteHTML = (t.internalNotes || []).map(n => `
                <div class="bg-white p-3 rounded-lg border border-yellow-100 shadow-sm mb-3 animate-in slide-in-from-bottom-2 fade-in">
                    <p class="text-sm text-slate-700">${n.text}</p>
                    <div class="flex justify-end mt-2"><span class="text-[10px] text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">${n.authorName}</span></div>
                </div>`).join('');
            document.getElementById('notes-list').innerHTML = noteHTML;
        };

        window.quickAcceptReschedule = async (id) => {
            const t = transfers.find(i => i.id == id);
            if (!t || !t.rescheduleDate) return;

            const reqDate = new Date(t.rescheduleDate.replace(' ', 'T'));
            const dateStr = reqDate.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            
            if (!confirm(`Accept reschedule request for ${t.name} (${t.plate})?\n\nNew appointment: ${dateStr}\n\nCustomer will receive SMS confirmation.`)) {
                return;
            }

            try {
                showToast("Processing...", "Accepting reschedule request", "info");
                
                const rescheduleDateTime = t.rescheduleDate.replace(' ', 'T');
                await fetchAPI(`accept_reschedule&id=${id}`, 'POST', {
                    service_date: rescheduleDateTime
                });

                t.serviceDate = rescheduleDateTime;
                t.userResponse = 'Confirmed';
                t.rescheduleDate = null;
                t.rescheduleComment = null;
                
                showToast("Reschedule Accepted", `Appointment updated and SMS sent to ${t.name}`, "success");
                loadData();
            } catch(e) {
                console.error('Quick accept reschedule error:', e);
                showToast("Error", "Failed to accept reschedule request", "error");
            }
        };

        window.acceptReschedule = async () => {
            const t = transfers.find(i => i.id == window.currentEditingId);
            if (!t || !t.rescheduleDate) return;

            if (!confirm(`Accept reschedule request and update appointment to ${new Date(t.rescheduleDate.replace(' ', 'T')).toLocaleString()}?`)) {
                return;
            }

            try {
                // Update service date to the requested date
                const rescheduleDateTime = t.rescheduleDate.replace(' ', 'T');
                document.getElementById('input-service-date').value = rescheduleDateTime;
                
                // Call API to accept reschedule
                await fetchAPI(`accept_reschedule&id=${window.currentEditingId}`, 'POST', {
                    service_date: rescheduleDateTime
                });

                // Update local data
                t.serviceDate = rescheduleDateTime;
                t.userResponse = 'Confirmed';
                
                showToast("Reschedule Accepted", "Appointment updated and SMS sent to customer", "success");
                window.closeModal();
                loadData();
            } catch(e) {
                console.error('Accept reschedule error:', e);
                showToast("Error", "Failed to accept reschedule request", "error");
            }
        };

        window.declineReschedule = async () => {
            if (!confirm('Decline this reschedule request? The customer will need to be contacted manually.')) {
                return;
            }

            try {
                await fetchAPI(`decline_reschedule&id=${window.currentEditingId}`, 'POST', {});
                
                const t = transfers.find(i => i.id == window.currentEditingId);
                if (t) {
                    t.rescheduleDate = null;
                    t.rescheduleComment = null;
                    t.userResponse = 'Pending';
                }
                
                showToast("Request Declined", "Reschedule request removed", "info");
                window.closeModal();
                loadData();
            } catch(e) {
                console.error('Decline reschedule error:', e);
                showToast("Error", "Failed to decline request", "error");
            }
        };

        window.deleteRecord = async (id) => {
            if(!id) {
                showToast("Error: No record ID", "error");
                return;
            }
            if(confirm("Delete this case permanently?")) {
                if (document.getElementById('connection-status').innerText.includes('Offline')) {
                    transfers = transfers.filter(t => t.id !== id);
                } else {
                    await fetchAPI(`delete_transfer&id=${id}`, 'POST');
                }
                window.closeModal();
                loadData(); 
                showToast("Deleted", "error");
            }
        };

        window.sendSMS = async (phone, text, type) => {
            if(!phone) return showToast("No phone number", "error");
            const clean = phone.replace(/\D/g, '');
            try {
                const result = await fetchAPI('send_sms', 'POST', { to: clean, text: text });
                
                if(window.currentEditingId) {
                    const t = transfers.find(i => i.id == window.currentEditingId);
                    const newLog = { message: `SMS Sent (${type})`, timestamp: new Date().toISOString(), type: 'sms' };
                    
                    if (document.getElementById('connection-status').innerText.includes('Offline')) {
                        if(!t.systemLogs) t.systemLogs = [];
                        t.systemLogs.push(newLog);
                    } else {
                        const logs = [...(t.systemLogs || []), newLog];
                        await fetchAPI(`update_transfer&id=${window.currentEditingId}`, 'POST', { systemLogs: logs });
                    }
                    // Refresh Logs
                    const logsToRender = document.getElementById('connection-status').innerText.includes('Offline') ? t.systemLogs : [...(t.systemLogs || []), newLog];
                    const logHTML = logsToRender.map(l => `<div class="mb-2 last:mb-0 pl-3 border-l-2 border-slate-200 text-slate-600"><div class="text-[10px] text-slate-400 uppercase tracking-wider mb-0.5">${l.timestamp.split('T')[0]}</div>${l.message}</div>`).join('');
                    document.getElementById('activity-log-container').innerHTML = logHTML;
                }
                showToast("SMS Sent", "success");
            } catch(e) { console.error(e); showToast("SMS Failed", "error"); }
        };

        document.getElementById('search-input').addEventListener('input', renderTable);
        document.getElementById('status-filter').addEventListener('change', renderTable);
        document.getElementById('reply-filter').addEventListener('change', renderTable);
        document.getElementById('new-note-input').addEventListener('keypress', (e) => { if(e.key === 'Enter') window.addNote(); });
        window.insertSample = (t) => document.getElementById('import-text').value = t;

        // =====================================================
        // USER MANAGEMENT FUNCTIONS (Moved to users.php)
        // =====================================================
        
        window.toggleUserMenu = function() {
            const dropdown = document.getElementById('user-dropdown');
            dropdown.classList.toggle('hidden');
            if (!dropdown.classList.contains('hidden')) {
                lucide.createIcons();
            }
        };
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const container = document.getElementById('user-menu-container');
            const dropdown = document.getElementById('user-dropdown');
            if (container && dropdown && !container.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Ensure all modals and overlays are hidden on page load
        document.getElementById('edit-modal')?.classList.add('hidden');
        document.getElementById('user-dropdown')?.classList.add('hidden');
        
        // Initialize data and icons
        try {
            loadData();
        } catch (e) {
            console.error('Error loading initial data:', e);
            showToast('Error', 'Failed to load data. Please refresh the page.', 'error');
        }
        
        if(window.lucide) lucide.createIcons();

    </script>
</body>
</html>