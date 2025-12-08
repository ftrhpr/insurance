<?php
require_once 'session_config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
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
        
        <?php include 'header.php'; ?>

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
                                <?php if ($current_user_role === 'admin' || $current_user_role === 'manager'): ?>
                                <button onclick="window.openManualCreateModal()" class="text-xs font-semibold text-white bg-gradient-to-br from-emerald-600 to-teal-600 px-4 py-2.5 rounded-xl hover:from-emerald-700 hover:to-teal-700 transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                                    <span class="flex items-center gap-1.5">
                                        <i data-lucide="plus-circle" class="w-3 h-3"></i>
                                        Manual Create
                                    </span>
                                </button>
                                <?php endif; ?>
                                <button onclick="window.insertSample('მანქანის ნომერი: AA123BB დამზღვევი: სახელი გვარი, 1234.00 (ფრანშიზა 273.97)')" class="text-xs font-semibold text-primary-700 bg-gradient-to-br from-primary-50 to-accent-50 px-4 py-2.5 rounded-xl hover:from-primary-100 hover:to-accent-100 transition-all border border-primary-200/50 shadow-sm hover:shadow-md hover:-translate-y-0.5">
                                    <span class="flex items-center gap-1.5">
                                        <i data-lucide="sparkles" class="w-3 h-3"></i>
                                        Sample
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
                                <thead class="bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-600 text-white text-xs uppercase tracking-wider font-bold shadow-lg">
                                    <tr>
                                        <th class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="car" class="w-4 h-4"></i>
                                                <span>Vehicle & Owner</span>
                                            </div>
                                        </th>
                                        <th class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="coins" class="w-4 h-4"></i>
                                                <span>Amount</span>
                                            </div>
                                        </th>
                                        <th class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="activity" class="w-4 h-4"></i>
                                                <span>Status</span>
                                            </div>
                                        </th>
                                        <th class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="phone" class="w-4 h-4"></i>
                                                <span>Contact & Review</span>
                                            </div>
                                        </th>
                                        <th class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="calendar" class="w-4 h-4"></i>
                                                <span>Service Date</span>
                                            </div>
                                        </th>
                                        <th class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                                                <span>Customer Reply</span>
                                            </div>
                                        </th>
                                        <th class="px-5 py-4 text-right">
                                            <div class="flex items-center gap-2 justify-end">
                                                <i data-lucide="settings" class="w-4 h-4"></i>
                                                <span>Action</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="table-body" class="divide-y divide-slate-100 bg-white">
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
            <div id="view-vehicles" class="hidden space-y-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold text-slate-900">Vehicle Registry</h2>
                    <div class="text-sm text-slate-500" id="vehicles-count">0 vehicles</div>
                </div>

                <!-- Search -->
                <div class="bg-white/80 backdrop-blur-xl p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="relative">
                        <i data-lucide="search" class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="vehicles-search" placeholder="Search by plate or phone..." class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
                    </div>
                </div>

                <!-- Vehicles Table -->
                <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-slate-50 to-slate-100 border-b-2 border-slate-200">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Plate</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Added</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Source</th>
                                </tr>
                            </thead>
                            <tbody id="vehicles-table-body" class="divide-y divide-slate-200">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Empty State -->
                    <div id="vehicles-empty" class="hidden py-12 flex flex-col items-center justify-center text-slate-400">
                        <i data-lucide="car" class="w-16 h-16 mb-4 opacity-30"></i>
                        <p class="text-sm font-medium">No vehicles found</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex items-center justify-between bg-white/80 backdrop-blur-xl p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="text-sm text-slate-600" id="vehicles-page-info">
                        Showing <span id="vehicles-showing-start">0</span>-<span id="vehicles-showing-end">0</span> of <span id="vehicles-total">0</span>
                    </div>
                    <div class="flex gap-2" id="vehicles-pagination">
                        <!-- Pagination buttons populated by JavaScript -->
                    </div>
                </div>
            </div>

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
    <div id="edit-modal" class="hidden fixed inset-0 z-50" role="dialog" aria-modal="true">
        <!-- Enhanced Backdrop with Animation -->
        <div class="fixed inset-0 bg-gradient-to-br from-slate-900/60 via-blue-900/40 to-indigo-900/50 backdrop-blur-lg transition-all duration-300" onclick="window.closeModal()"></div>

        <!-- Fullscreen Dialog Container -->
        <div class="fixed inset-0 flex items-stretch p-0 sm:p-2 md:p-4 lg:p-6">
            <div class="relative flex-1 flex flex-col rounded-none sm:rounded-2xl lg:rounded-3xl bg-gradient-to-br from-white to-slate-50 text-left shadow-2xl shadow-blue-900/30 transition-all border-0 sm:border sm:border-slate-200/50 ring-0 sm:ring-1 sm:ring-white/50 w-full h-full">
                
                <!-- Premium Header with Enhanced Gradient - Fixed -->
                <div class="relative bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 md:py-2.5 flex justify-between items-center shadow-2xl shrink-0">
                    <!-- Decorative Background Pattern -->
                    <div class="absolute inset-0 bg-grid-white/[0.05] bg-[size:20px_20px]"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/10"></div>
                    
                    <div class="relative flex items-center gap-2 sm:gap-3 md:gap-4 lg:gap-5 overflow-hidden">
                         <!-- Vehicle Badge -->
                         <div class="relative shrink-0">
                             <div class="absolute inset-0 bg-white/30 blur-xl rounded-2xl"></div>
                             <div class="relative bg-white/20 backdrop-blur-md border-2 border-white/40 px-2 sm:px-3 md:px-4 lg:px-5 py-2 sm:py-2.5 md:py-3 rounded-xl sm:rounded-2xl text-xs sm:text-sm font-mono font-extrabold text-white shadow-2xl flex items-center gap-1.5 sm:gap-2 md:gap-3">
                                <div class="bg-white/20 p-1 sm:p-1.5 rounded-lg">
                                    <i data-lucide="car" class="w-3 sm:w-4 md:w-5 h-3 sm:h-4 md:h-5"></i>
                                </div>
                                <span id="modal-title-ref" class="tracking-wider text-sm sm:text-base md:text-lg truncate max-w-[80px] sm:max-w-[120px] md:max-w-none">AB-123-CD</span>
                             </div>
                         </div>
                         
                         <!-- Divider - Hidden on mobile -->
                         <div class="hidden sm:block h-8 md:h-10 lg:h-12 w-px bg-white/30 shrink-0"></div>
                         
                         <!-- Customer Info -->
                         <div class="flex flex-col gap-0.5 sm:gap-1 md:gap-1.5 min-w-0 flex-1">
                             <div class="flex items-center gap-1 sm:gap-2 flex-wrap">
                                 <span class="text-[8px] sm:text-[10px] text-white/60 font-bold uppercase tracking-widest">Order</span>
                                 <span class="text-xs sm:text-sm font-mono text-white bg-white/20 backdrop-blur-sm px-2 sm:px-3 py-0.5 sm:py-1 rounded-md sm:rounded-lg border border-white/30 shadow-lg" id="modal-order-id">#0</span>
                             </div>
                             <div class="flex items-center gap-1 sm:gap-2">
                                 <i data-lucide="user" class="w-3 sm:w-4 h-3 sm:h-4 text-white/70 shrink-0"></i>
                                 <span class="text-sm sm:text-base md:text-lg font-bold text-white truncate" id="modal-title-name">Customer Name</span>
                             </div>
                         </div>
                    </div>
                    
                    <button onclick="window.closeModal()" class="relative text-white/80 hover:text-white hover:bg-white/20 p-2 sm:p-2.5 md:p-3 rounded-lg sm:rounded-xl transition-all hover:rotate-90 duration-300 group shrink-0">
                        <i data-lucide="x" class="w-5 sm:w-5 md:w-6 h-5 sm:h-5 md:h-6 group-hover:scale-110 transition-transform"></i>
                    </button>
                </div>

                <!-- Enhanced Body with Responsive Columns - Compact Layout -->
                <div class="flex-1 overflow-y-auto custom-scrollbar px-1.5 sm:px-2 md:px-3 py-1.5 sm:py-2 md:py-2.5">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-1.5 sm:gap-2 md:gap-2.5">
                    
                    <!-- Left Column: Order Details & Status -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <!-- Order Information Card -->
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg p-1.5 sm:p-2 md:p-3 border border-blue-100 shadow-sm">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <div class="bg-blue-600 p-1 rounded-md shadow-sm">
                                    <i data-lucide="file-text" class="w-3 h-3 text-white"></i>
                                </div>
                                <h3 class="text-xs font-bold text-blue-900 uppercase tracking-wider">Order Details</h3>
                            </div>
                            <div class="space-y-1.5">
                                <div class="bg-white/80 rounded-lg p-2 border border-blue-100">
                                    <div class="text-[10px] text-blue-600 font-bold uppercase mb-1">Amount</div>
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="coins" class="w-5 h-5 text-emerald-500"></i>
                                        <span class="text-2xl font-bold text-emerald-600"><span id="modal-amount">0</span>₾</span>
                                    </div>
                                </div>
                                <div class="bg-white/80 rounded-lg p-2 border border-blue-100">
                                    <div class="text-[10px] text-blue-600 font-bold uppercase mb-1">Franchise</div>
                                    <input id="input-franchise" type="number" placeholder="0.00" class="w-full p-2 bg-white border border-slate-200 rounded-lg text-base font-bold text-orange-600 focus:border-orange-400 focus:ring-2 focus:ring-orange-400/20 outline-none">
                                </div>
                                <div class="bg-white/80 rounded-lg p-2 border border-blue-100">
                                    <div class="text-[10px] text-blue-600 font-bold uppercase mb-1">Created At</div>
                                    <div class="flex items-center gap-2 text-sm text-slate-700">
                                        <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
                                        <span id="modal-created-date" class="font-medium">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Selection -->
                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-lg p-1.5 sm:p-2 md:p-3 border border-purple-100 shadow-sm">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <div class="bg-purple-600 p-1 rounded-md shadow-sm">
                                    <i data-lucide="activity" class="w-3 h-3 text-white"></i>
                                </div>
                                <h3 class="text-xs font-bold text-purple-900 uppercase tracking-wider">Workflow Stage</h3>
                            </div>
                            <div class="relative">
                                <select id="input-status" class="w-full appearance-none bg-white border-2 border-purple-200 text-slate-800 py-4 pl-12 pr-10 rounded-xl leading-tight focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 text-sm font-bold shadow-lg transition-all cursor-pointer hover:border-purple-300">
                                    <option value="New">🔵 New Case</option>
                                    <option value="Processing">🟡 Processing</option>
                                    <option value="Called">🟣 Contacted</option>
                                    <option value="Parts Ordered">📦 Parts Ordered</option>
                                    <option value="Parts Arrived">🏁 Parts Arrived</option>
                                    <option value="Scheduled">🟠 Scheduled</option>
                                    <option value="Completed">🟢 Completed</option>
                                    <option value="Issue">🔴 Issue</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-purple-500">
                                    <i data-lucide="git-branch" class="w-5 h-5"></i>
                                </div>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-purple-400">
                                    <i data-lucide="chevron-down" class="w-5 h-5"></i>
                                </div>
                            </div>
                        </div>

                        <!-- System Activity Log -->
                        <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg border border-slate-200 overflow-hidden shadow-sm">
                            <div class="px-1.5 sm:px-2 py-1 sm:py-1.5 bg-gradient-to-r from-slate-700 to-slate-600 flex items-center gap-1.5">
                                <i data-lucide="history" class="w-3 h-3 text-white"></i>
                                <label class="text-[9px] sm:text-[10px] font-bold text-white uppercase tracking-wider">Activity Timeline</label>
                            </div>
                            <div id="activity-log-container" class="p-1.5 sm:p-2 h-16 sm:h-18 md:h-20 overflow-y-auto custom-scrollbar text-[10px] space-y-0.5 bg-white/50"></div>
                        </div>
                    </div>

                    <!-- Middle Column: Communication & Actions -->
                    <div class="space-y-1.5 sm:space-y-2">
                        <!-- Contact Information -->
                        <div class="bg-gradient-to-br from-teal-50 to-cyan-50 rounded-lg p-1.5 sm:p-2 md:p-3 border border-teal-100 shadow-sm">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <div class="bg-teal-600 p-1 rounded-md shadow-sm">
                                    <i data-lucide="phone" class="w-3 h-3 text-white"></i>
                                </div>
                                <h3 class="text-xs font-bold text-teal-900 uppercase tracking-wider">Contact Information</h3>
                            </div>
                            <div class="flex gap-2">
                                <div class="relative flex-1">
                                    <i data-lucide="smartphone" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-teal-500"></i>
                                    <input id="input-phone" type="text" placeholder="Phone Number" class="w-full pl-11 pr-3 py-3 bg-white border-2 border-teal-200 rounded-xl text-sm font-semibold text-slate-800 focus:ring-4 focus:ring-teal-500/20 focus:border-teal-400 outline-none shadow-sm">
                                </div>
                                <a id="btn-call-real" href="#" class="bg-white text-teal-600 border-2 border-teal-200 p-3 rounded-xl hover:bg-teal-50 hover:border-teal-300 hover:scale-105 transition-all shadow-lg active:scale-95">
                                    <i data-lucide="phone-call" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Service Appointment -->
                        <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-lg p-1.5 sm:p-2 md:p-3 border border-amber-100 shadow-sm">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <div class="bg-orange-600 p-1 rounded-md shadow-sm">
                                    <i data-lucide="calendar-check" class="w-3 h-3 text-white"></i>
                                </div>
                                <h3 class="text-xs font-bold text-orange-900 uppercase tracking-wider">Service Appointment</h3>
                            </div>
                            <div class="relative">
                                <i data-lucide="calendar" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-orange-500"></i>
                                <input id="input-service-date" type="datetime-local" class="w-full pl-11 pr-3 py-3 bg-white border-2 border-orange-200 rounded-xl text-sm font-semibold focus:border-orange-400 focus:ring-4 focus:ring-orange-400/20 outline-none shadow-sm">
                            </div>
                        </div>

                        <!-- Quick SMS Actions -->
                        <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-lg p-1.5 sm:p-2 md:p-3 border border-indigo-100 shadow-sm">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <div class="bg-indigo-600 p-1 rounded-md shadow-sm">
                                    <i data-lucide="message-circle" class="w-3 h-3 text-white"></i>
                                </div>
                                <h3 class="text-xs font-bold text-indigo-900 uppercase tracking-wider">Quick SMS Actions</h3>
                            </div>
                            <div class="space-y-2 sm:space-y-2.5 md:space-y-3">
                                <button id="btn-sms-register" class="group w-full flex justify-between items-center px-3 sm:px-4 md:px-5 py-3 sm:py-3.5 md:py-4 bg-white border-2 border-indigo-200 rounded-lg sm:rounded-xl hover:border-indigo-400 hover:shadow-xl hover:scale-[1.02] transition-all text-left active:scale-95">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800 group-hover:text-indigo-700">Send Welcome SMS</div>
                                        <div class="text-[10px] text-slate-500 mt-0.5">Registration confirmation</div>
                                    </div>
                                    <div class="bg-indigo-100 group-hover:bg-indigo-600 p-2 rounded-lg transition-colors">
                                        <i data-lucide="message-square" class="w-4 h-4 text-indigo-600 group-hover:text-white"></i>
                                    </div>
                                </button>
                                <button id="btn-sms-arrived" class="group w-full flex justify-between items-center px-3 sm:px-4 md:px-5 py-3 sm:py-3.5 md:py-4 bg-white border-2 border-teal-200 rounded-lg sm:rounded-xl hover:border-teal-400 hover:shadow-xl hover:scale-[1.02] transition-all text-left active:scale-95">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800 group-hover:text-teal-700">Parts Arrived SMS</div>
                                        <div class="text-[10px] text-slate-500 mt-0.5">Includes customer link</div>
                                    </div>
                                    <div class="bg-teal-100 group-hover:bg-teal-600 p-2 rounded-lg transition-colors">
                                        <i data-lucide="package-check" class="w-4 h-4 text-teal-600 group-hover:text-white"></i>
                                    </div>
                                </button>
                                <button id="btn-sms-schedule" class="group w-full flex justify-between items-center px-3 sm:px-4 md:px-5 py-3 sm:py-3.5 md:py-4 bg-white border-2 border-orange-200 rounded-lg sm:rounded-xl hover:border-orange-400 hover:shadow-xl hover:scale-[1.02] transition-all text-left active:scale-95">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800 group-hover:text-orange-700">Send Schedule SMS</div>
                                        <div class="text-[10px] text-slate-500 mt-0.5">Appointment reminder</div>
                                    </div>
                                    <div class="bg-orange-100 group-hover:bg-orange-600 p-2 rounded-lg transition-colors">
                                        <i data-lucide="calendar-check" class="w-4 h-4 text-orange-600 group-hover:text-white"></i>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Customer Feedback & Notes -->
                    <div class="space-y-1.5 sm:space-y-2 flex flex-col h-full">
                        <!-- Customer Review Preview -->
                        <div id="modal-review-section" class="hidden bg-gradient-to-br from-amber-50 to-yellow-50 rounded-lg sm:rounded-xl border-2 border-amber-200 overflow-hidden shadow-lg">
                            <div class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 bg-gradient-to-r from-amber-500 to-yellow-500 flex items-center gap-2">
                                <div class="bg-white/20 p-1.5 rounded-lg">
                                    <i data-lucide="star" class="w-4 h-4 text-white"></i>
                                </div>
                                <label class="text-xs font-bold text-white uppercase tracking-wider">Customer Review</label>
                            </div>
                            <div class="p-2 sm:p-3 md:p-4 space-y-2">
                                <div class="flex items-center gap-4">
                                    <div id="modal-review-stars" class="flex gap-1"></div>
                                    <span id="modal-review-rating" class="text-3xl font-black text-amber-600"></span>
                                </div>
                                <div class="bg-white/80 p-2 rounded-lg border border-amber-200">
                                    <p id="modal-review-comment" class="text-sm text-slate-700 italic leading-relaxed"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Reschedule Request Preview -->
                        <div id="modal-reschedule-section" class="hidden bg-gradient-to-br from-purple-50 to-fuchsia-50 rounded-lg sm:rounded-xl border-2 border-purple-200 overflow-hidden shadow-lg">
                            <div class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 bg-gradient-to-r from-purple-600 to-fuchsia-600 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="bg-white/20 p-1.5 rounded-lg">
                                        <i data-lucide="calendar-clock" class="w-4 h-4 text-white"></i>
                                    </div>
                                    <label class="text-xs font-bold text-white uppercase tracking-wider">Reschedule Request</label>
                                </div>
                                <span id="reschedule-status-badge" class="text-[10px] bg-white/20 backdrop-blur-sm text-white px-3 py-1 rounded-full font-bold border border-white/30">Pending</span>
                            </div>
                            <div class="p-2 sm:p-3 md:p-4 space-y-2">
                                <div class="bg-white/80 p-2 rounded-lg border-2 border-purple-200">
                                    <span class="text-xs text-purple-700 font-bold block mb-2 uppercase tracking-wider">Requested Date</span>
                                    <div class="flex items-center gap-2">
                                        <div class="bg-purple-100 p-2 rounded-lg">
                                            <i data-lucide="calendar" class="w-4 h-4 text-purple-600"></i>
                                        </div>
                                        <span id="modal-reschedule-date" class="text-base font-bold text-slate-800"></span>
                                    </div>
                                </div>
                                <div class="bg-white/80 p-4 rounded-xl border-2 border-purple-200">
                                    <span class="text-xs text-purple-700 font-bold block mb-2 uppercase tracking-wider">Customer Comment</span>
                                    <p id="modal-reschedule-comment" class="text-sm text-slate-700 leading-relaxed"></p>
                                </div>
                                <div id="reschedule-actions" class="flex gap-2 pt-2">
                                    <button onclick="window.acceptReschedule()" class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white py-3 px-4 rounded-xl font-bold text-sm transition-all active:scale-95 flex items-center justify-center gap-2 shadow-xl">
                                        <i data-lucide="check" class="w-5 h-5"></i> Accept & Update
                                    </button>
                                    <button onclick="window.declineReschedule()" class="flex-1 bg-white hover:bg-red-50 text-red-600 border-2 border-red-300 py-3 px-4 rounded-xl font-bold text-sm transition-all active:scale-95 hover:border-red-400">
                                        <i data-lucide="x" class="w-4 h-4 inline mr-1"></i> Decline
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Team Notes Section -->
                        <div class="flex-1 flex flex-col bg-gradient-to-br from-emerald-50 to-teal-50 rounded-lg sm:rounded-xl border-2 border-emerald-200 overflow-hidden shadow-lg">
                            <div class="px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 bg-gradient-to-r from-emerald-600 to-teal-600 flex justify-between items-center">
                                <div class="flex items-center gap-2">
                                    <div class="bg-white/20 p-1.5 rounded-lg">
                                        <i data-lucide="sticky-note" class="w-4 h-4 text-white"></i>
                                    </div>
                                    <label class="text-xs font-bold text-white uppercase tracking-wider">Team Notes</label>
                                </div>
                                <span class="text-[10px] bg-white/20 backdrop-blur-sm text-white px-3 py-1 rounded-full font-bold border border-white/30">Internal</span>
                            </div>
                            <div id="notes-list" class="flex-1 p-1.5 sm:p-2 overflow-y-auto custom-scrollbar space-y-0.5 sm:space-y-1 min-h-[80px] sm:min-h-[100px] md:min-h-[120px] bg-white/60"></div>
                            <div class="p-2 sm:p-3 bg-white border-t-2 border-emerald-200 flex gap-2">
                                <input id="new-note-input" type="text" placeholder="Add a note..." class="flex-1 text-sm px-4 py-2.5 bg-emerald-50 border-2 border-emerald-200 rounded-xl focus:border-emerald-400 focus:ring-4 focus:ring-emerald-400/20 outline-none font-medium">
                                <button onclick="window.addNote()" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white p-3 rounded-xl transition-all shadow-lg active:scale-95">
                                    <i data-lucide="send" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Premium Footer with Actions - Fixed/Floating -->
                <div class="bg-gradient-to-r from-slate-50 via-white to-slate-50 px-2 sm:px-3 md:px-4 py-1.5 sm:py-2 border-t-2 border-slate-200 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-1.5 sm:gap-2 rounded-b-xl shadow-[0_-10px_40px_-10px_rgba(0,0,0,0.3)] shrink-0 backdrop-blur-sm">
                    <button type="button" onclick="window.deleteRecord(window.currentEditingId)" class="group text-red-600 hover:text-white hover:bg-gradient-to-r hover:from-red-600 hover:to-red-700 text-sm font-bold flex items-center justify-center gap-2 px-4 sm:px-5 py-3 rounded-lg sm:rounded-xl transition-all border-2 border-red-200 hover:border-red-600 shadow-lg hover:shadow-2xl hover:shadow-red-600/50 active:scale-95 w-full sm:w-auto hover:scale-105">
                        <i data-lucide="trash-2" class="w-4 h-4"></i> 
                        <span>Delete Order</span>
                    </button>
                    <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                        <button type="button" onclick="window.closeModal()" class="flex-1 sm:flex-initial px-4 sm:px-6 py-3 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg sm:rounded-xl font-bold text-sm transition-all border-2 border-slate-200 hover:border-slate-300 shadow-lg hover:shadow-xl active:scale-95 hover:scale-105">
                            <i data-lucide="x" class="w-4 h-4 inline mr-1"></i> Cancel
                        </button>
                        <button type="button" onclick="window.saveEdit()" class="flex-1 sm:flex-initial px-6 sm:px-8 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg sm:rounded-xl font-bold text-sm shadow-2xl shadow-blue-600/50 transition-all active:scale-95 hover:scale-105 flex items-center justify-center gap-2 border border-blue-500/50">
                            <i data-lucide="save" class="w-5 h-5"></i> 
                            <span>Save Changes</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <!-- Manual Create Order Modal -->
    <div id="manual-create-modal" class="hidden fixed inset-0 z-[9999]" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gradient-to-br from-slate-900/60 via-emerald-900/40 to-teal-900/50 backdrop-blur-lg transition-all duration-300" onclick="window.closeManualCreateModal()"></div>

        <!-- Modal Container -->
        <div class="fixed inset-0 flex items-center justify-center p-4 z-[10000]">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl z-[10001]">
                
                <!-- Header -->
                <div class="relative bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 px-6 py-4 flex justify-between items-center rounded-t-2xl">
                    <div class="flex items-center gap-3">
                        <div class="bg-white/20 backdrop-blur-md border-2 border-white/40 p-2 rounded-xl">
                            <i data-lucide="plus-circle" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Create New Order</h3>
                            <p class="text-xs text-white/80">Manually add a new insurance order</p>
                        </div>
                    </div>
                    <button onclick="window.closeManualCreateModal()" class="text-white/80 hover:text-white hover:bg-white/20 p-2 rounded-lg transition-all">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                    <!-- Vehicle Plate -->
                    <div>
                        <label class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                            <i data-lucide="car" class="w-4 h-4 text-emerald-600"></i>
                            Vehicle Plate Number *
                        </label>
                        <input id="manual-plate" type="text" placeholder="AA-123-BB" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-semibold focus:border-emerald-400 focus:ring-4 focus:ring-emerald-400/20 outline-none">
                    </div>

                    <!-- Customer Name -->
                    <div>
                        <label class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                            <i data-lucide="user" class="w-4 h-4 text-emerald-600"></i>
                            Customer Name *
                        </label>
                        <input id="manual-name" type="text" placeholder="John Doe" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-semibold focus:border-emerald-400 focus:ring-4 focus:ring-emerald-400/20 outline-none">
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                            <i data-lucide="phone" class="w-4 h-4 text-emerald-600"></i>
                            Phone Number
                        </label>
                        <input id="manual-phone" type="text" placeholder="555123456" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-semibold focus:border-emerald-400 focus:ring-4 focus:ring-emerald-400/20 outline-none">
                    </div>

                    <!-- Amount Row -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Amount -->
                        <div>
                            <label class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                                <i data-lucide="coins" class="w-4 h-4 text-emerald-600"></i>
                                Amount (₾) *
                            </label>
                            <input id="manual-amount" type="number" step="0.01" placeholder="0.00" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-semibold focus:border-emerald-400 focus:ring-4 focus:ring-emerald-400/20 outline-none">
                        </div>

                        <!-- Franchise -->
                        <div>
                            <label class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                                <i data-lucide="percent" class="w-4 h-4 text-orange-600"></i>
                                Franchise (₾)
                            </label>
                            <input id="manual-franchise" type="number" step="0.01" placeholder="0.00" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-semibold focus:border-orange-400 focus:ring-4 focus:ring-orange-400/20 outline-none">
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                            <i data-lucide="activity" class="w-4 h-4 text-purple-600"></i>
                            Initial Status
                        </label>
                        <select id="manual-status" class="w-full appearance-none px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-semibold focus:border-purple-400 focus:ring-4 focus:ring-purple-400/20 outline-none cursor-pointer">
                            <option value="New">🔵 New Case</option>
                            <option value="Processing">🟡 Processing</option>
                            <option value="Called">🟣 Contacted</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                            <i data-lucide="sticky-note" class="w-4 h-4 text-slate-600"></i>
                            Internal Notes (Optional)
                        </label>
                        <textarea id="manual-notes" rows="3" placeholder="Add any additional notes..." class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm resize-none focus:border-slate-400 focus:ring-4 focus:ring-slate-400/20 outline-none"></textarea>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-slate-50 border-t-2 border-slate-200 flex justify-between items-center gap-3 rounded-b-2xl">
                    <button type="button" onclick="window.closeManualCreateModal()" class="px-6 py-2.5 text-slate-600 hover:text-slate-900 hover:bg-slate-200 rounded-xl font-bold text-sm transition-all border-2 border-slate-300">
                        Cancel
                    </button>
                    <button type="button" id="manual-create-submit" onclick="window.saveManualOrder()" class="px-8 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white rounded-xl font-bold text-sm shadow-lg transition-all flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Create Order
                    </button>
                </div>
            </div>
        </div>
    </div>

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
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        
        async function fetchAPI(action, method = 'GET', body = null) {
            const opts = { 
                method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            
            // Add CSRF token for POST requests
            if (method === 'POST' && CSRF_TOKEN) {
                opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
            }
            
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

            const loadingScreen = document.getElementById('loading-screen');
            const appContent = document.getElementById('app-content');
            
            loadingScreen?.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => {
                loadingScreen?.classList.add('hidden');
                appContent?.classList.remove('hidden');
            }, 500);
        }

        // Poll for updates every 10 seconds
        let pollInterval = setInterval(loadData, 10000);
        
        // Cleanup function for page unload
        window.addEventListener('beforeunload', () => {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        });

        // Premium Toast Notifications
        function showToast(title, message = '', type = 'success', duration = 4000) {
            const container = document.getElementById('toast-container');
            if (!container) {
                console.error('Toast container not found');
                return;
            }
            
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
            let dismissTimeout, removeTimeout;
            if (duration > 0 && type !== 'urgent') {
                dismissTimeout = setTimeout(() => {
                    toast.classList.add('translate-y-4', 'opacity-0');
                    removeTimeout = setTimeout(() => {
                        toast.remove();
                        // Clean up timeout references
                        dismissTimeout = null;
                        removeTimeout = null;
                    }, 500);
                }, duration);
            }
            
            // Add cleanup to manual close button
            const closeBtn = toast.querySelector('button');
            if (closeBtn) {
                closeBtn.onclick = () => {
                    if (dismissTimeout) clearTimeout(dismissTimeout);
                    if (removeTimeout) clearTimeout(removeTimeout);
                    toast.remove();
                };
            }
        }

        window.switchView = (v) => {
            // Toggle views (check if element exists before accessing)
            const dashboardView = document.getElementById('view-dashboard');
            const vehiclesView = document.getElementById('view-vehicles');
            const templatesView = document.getElementById('view-templates');
            const usersView = document.getElementById('view-users');
            
            if (dashboardView) dashboardView.classList.toggle('hidden', v !== 'dashboard');
            if (vehiclesView) vehiclesView.classList.toggle('hidden', v !== 'vehicles');
            if (templatesView) templatesView.classList.toggle('hidden', v !== 'templates');
            if (usersView) usersView.classList.toggle('hidden', v !== 'users');
            
            // Render vehicles when switching to that view
            if (v === 'vehicles') {
                renderVehicles();
            }
            
            const activeClass = "nav-active px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 bg-slate-900 text-white shadow-sm";
            const inactiveClass = "nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 text-slate-500 hover:text-slate-900 hover:bg-white";

            // Update nav button (check if element exists)
            const navDashboard = document.getElementById('nav-dashboard');
            const navVehicles = document.getElementById('nav-vehicles');
            if (navDashboard) navDashboard.className = v === 'dashboard' ? activeClass : inactiveClass;
            if (navVehicles) navVehicles.className = v === 'vehicles' ? activeClass : inactiveClass;
        };

        // --- VEHICLES PAGINATION ---
        let currentVehiclesPage = 1;
        const vehiclesPerPage = 10;

        function renderVehicles(page = 1) {
            if (!vehicles || vehicles.length === 0) {
                const tbody = document.getElementById('vehicles-table-body');
                const emptyState = document.getElementById('vehicles-empty');
                const countEl = document.getElementById('vehicles-count');
                const pageInfo = document.getElementById('vehicles-page-info');
                const pagination = document.getElementById('vehicles-pagination');
                
                if (tbody) tbody.innerHTML = '';
                emptyState?.classList.remove('hidden');
                if (countEl) countEl.textContent = '0 vehicles';
                pageInfo?.classList.add('hidden');
                if (pagination) pagination.innerHTML = '';
                return;
            }

            currentVehiclesPage = page;
            const searchTerm = document.getElementById('vehicles-search')?.value.toLowerCase() || '';
            
            // Filter vehicles
            let filtered = vehicles.filter(v => {
                const plate = (v.plate || '').toLowerCase();
                const phone = (v.phone || '').toLowerCase();
                return plate.includes(searchTerm) || phone.includes(searchTerm);
            });

            const totalVehicles = filtered.length;
            const totalPages = Math.ceil(totalVehicles / vehiclesPerPage);
            
            // Adjust page if out of range
            if (currentVehiclesPage > totalPages && totalPages > 0) {
                currentVehiclesPage = totalPages;
            }
            if (currentVehiclesPage < 1) {
                currentVehiclesPage = 1;
            }

            const startIndex = (currentVehiclesPage - 1) * vehiclesPerPage;
            const endIndex = Math.min(startIndex + vehiclesPerPage, totalVehicles);
            const pageVehicles = filtered.slice(startIndex, endIndex);

            // Update count
            const countEl = document.getElementById('vehicles-count');
            if (countEl) countEl.textContent = `${totalVehicles} vehicle${totalVehicles !== 1 ? 's' : ''}`;

            // Render table
            const tbody = document.getElementById('vehicles-table-body');
            const emptyState = document.getElementById('vehicles-empty');
            
            if (!tbody) return; // Critical element missing
            
            if (pageVehicles.length === 0) {
                tbody.innerHTML = '';
                emptyState?.classList.remove('hidden');
            } else {
                emptyState?.classList.add('hidden');
                tbody.innerHTML = pageVehicles.map(v => {
                    const addedDate = v.created_at ? new Date(v.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
                    const source = v.source || 'Manual';
                    return `
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="car" class="w-4 h-4 text-slate-400"></i>
                                    <span class="font-mono font-bold text-slate-900">${escapeHtml(v.plate || 'N/A')}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="phone" class="w-4 h-4 text-slate-400"></i>
                                    <span class="text-slate-700">${escapeHtml(v.phone || 'N/A')}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-sm">${addedDate}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
                                    source === 'Import' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-800'
                                }">${source}</span>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            // Update pagination info
            const pageInfo = document.getElementById('vehicles-page-info');
            
            if (totalVehicles > 0) {
                pageInfo?.classList.remove('hidden');
                
                const showingStart = document.getElementById('vehicles-showing-start');
                const showingEnd = document.getElementById('vehicles-showing-end');
                const totalEl = document.getElementById('vehicles-total');
                
                if (showingStart) showingStart.textContent = startIndex + 1;
                if (showingEnd) showingEnd.textContent = endIndex;
                if (totalEl) totalEl.textContent = totalVehicles;
            } else {
                pageInfo?.classList.add('hidden');
            }

            // Render pagination buttons
            renderVehiclesPagination(totalPages);
            
            // Re-init Lucide icons
            if (window.lucide) lucide.createIcons();
        }

        function renderVehiclesPagination(totalPages) {
            const container = document.getElementById('vehicles-pagination');
            if (!container) return;
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '';

            // Previous button
            html += `
                <button onclick="renderVehicles(${currentVehiclesPage - 1})" 
                    class="px-3 py-1.5 rounded-lg border transition-all ${
                        currentVehiclesPage === 1 
                            ? 'border-slate-200 text-slate-400 cursor-not-allowed' 
                            : 'border-slate-300 text-slate-700 hover:bg-slate-100'
                    }" 
                    ${currentVehiclesPage === 1 ? 'disabled' : ''}>
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
            `;

            // Page numbers (show max 5 pages)
            let startPage = Math.max(1, currentVehiclesPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            
            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }

            if (startPage > 1) {
                html += `<button onclick="renderVehicles(1)" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100">1</button>`;
                if (startPage > 2) {
                    html += `<span class="px-2 text-slate-400">...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <button onclick="renderVehicles(${i})" 
                        class="px-3 py-1.5 rounded-lg border transition-all ${
                            i === currentVehiclesPage 
                                ? 'bg-slate-900 text-white border-slate-900' 
                                : 'border-slate-300 text-slate-700 hover:bg-slate-100'
                        }">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="px-2 text-slate-400">...</span>`;
                }
                html += `<button onclick="renderVehicles(${totalPages})" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100">${totalPages}</button>`;
            }

            // Next button
            html += `
                <button onclick="renderVehicles(${currentVehiclesPage + 1})" 
                    class="px-3 py-1.5 rounded-lg border transition-all ${
                        currentVehiclesPage === totalPages 
                            ? 'border-slate-200 text-slate-400 cursor-not-allowed' 
                            : 'border-slate-300 text-slate-700 hover:bg-slate-100'
                    }" 
                    ${currentVehiclesPage === totalPages ? 'disabled' : ''}>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            `;

            container.innerHTML = html;
            if (window.lucide) lucide.createIcons();
        }

        // Search handler for vehicles (initialized at bottom with other listeners)

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

        // Notification Prompt & Load Templates (initialized at bottom)

        // --- HTML ESCAPING FUNCTION ---
        const escapeHtml = (text) => {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        };

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
                const parsedResult = document.getElementById('parsed-result');
                const parsedPlaceholder = document.getElementById('parsed-placeholder');
                const parsedContent = document.getElementById('parsed-content');
                const saveBtn = document.getElementById('btn-save-import');
                
                parsedResult?.classList.remove('hidden');
                parsedPlaceholder?.classList.add('hidden');
                
                // Escape HTML to prevent XSS
                const escapeHtml = (text) => {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                };
                
                if (parsedContent) {
                    parsedContent.innerHTML = parsedImportData.map(i => 
                    `<div class="bg-white p-3 border border-emerald-100 rounded-lg mb-2 text-xs flex justify-between items-center shadow-sm">
                        <div class="flex items-center gap-2">
                            <div class="font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">${escapeHtml(i.plate)}</div> 
                            <span class="text-slate-500">${escapeHtml(i.name)}</span>
                            ${i.franchise ? `<span class="text-orange-500 bg-orange-50 px-1.5 py-0.5 rounded ml-1">Franchise: ${escapeHtml(i.franchise)}</span>` : ''}
                        </div>
                        <div class="font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">${escapeHtml(i.amount)} ₾</div>
                    </div>`
                    ).join('');
                }
                if (saveBtn) saveBtn.innerHTML = `<i data-lucide="save" class="w-4 h-4"></i> Save ${parsedImportData.length} Items`;
                if (window.lucide) lucide.createIcons();
            } else {
                showToast("No matches found", "Could not parse any transfers from the text", "error");
            }
        };

        window.saveParsedImport = async () => {
            const btn = document.getElementById('btn-save-import');
            if (!btn) return;
            
            btn.disabled = true; 
            btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...`;
            
            let successCount = 0;
            let failCount = 0;
            
            for(let data of parsedImportData) {
                try {
                    const res = await fetchAPI('add_transfer', 'POST', data);
                    if (res && res.status === 'success') {
                        successCount++;
                        if (res.id && data.franchise) {
                            await fetchAPI(`update_transfer&id=${res.id}`, 'POST', { franchise: data.franchise });
                        }
                        await fetchAPI('sync_vehicle', 'POST', { plate: data.plate, ownerName: data.name });
                    } else {
                        failCount++;
                    }
                } catch (error) {
                    console.error('Error importing transfer:', error);
                    failCount++;
                }
            }
            
            if(MANAGER_PHONE && successCount > 0) {
                const msg = `System Alert: ${successCount} new transfer(s) added to OTOMOTORS portal.`;
                window.sendSMS(MANAGER_PHONE, msg, 'system');
            }
            
            if (successCount > 0) {
                await fetchAPI('send_broadcast', 'POST', { 
                    title: 'New Transfers Imported', 
                    body: `${successCount} new cases added.` 
                });
            }

            const importText = document.getElementById('import-text');
            const parsedResult = document.getElementById('parsed-result');
            const parsedPlaceholder = document.getElementById('parsed-placeholder');
            
            if (importText) importText.value = '';
            parsedResult?.classList.add('hidden');
            parsedPlaceholder?.classList.remove('hidden');
            loadData();
            
            if (failCount > 0) {
                showToast("Import Completed with Errors", `${successCount} succeeded, ${failCount} failed`, "error");
            } else {
                showToast("Import Successful", `${successCount} orders imported successfully`, "success");
            }
            
            btn.disabled = false;
            btn.innerHTML = `<i data-lucide="save" class="w-4 h-4"></i> Confirm & Save`;
            lucide.createIcons();
        };

        function renderTable() {
            const searchInput = document.getElementById('search-input');
            const statusFilter = document.getElementById('status-filter');
            const replyFilterEl = document.getElementById('reply-filter');
            
            const search = searchInput?.value.toLowerCase() || '';
            const filter = statusFilter?.value || 'All';
            const replyFilter = replyFilterEl?.value || 'All';
            
            const newContainer = document.getElementById('new-cases-grid');
            const activeContainer = document.getElementById('table-body');
            
            if (!newContainer || !activeContainer) {
                console.error('Required table containers not found');
                return;
            }
            
            newContainer.innerHTML = ''; 
            activeContainer.innerHTML = '';
            
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
                            <div class="flex justify-between items-start mb-3 pl-3">
                                <div class="flex flex-col gap-1">
                                    <span class="bg-primary-50 text-primary-700 border border-primary-100 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide flex items-center gap-1 w-fit"><i data-lucide="clock" class="w-3 h-3"></i> ${dateStr}</span>
                                    <span class="text-[9px] font-mono text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">ID: ${t.id}</span>
                                </div>
                                <span class="text-xs font-mono font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded">${escapeHtml(t.amount)} ₾</span>
                            </div>
                            <div class="pl-3 mb-5">
                                <h3 class="font-bold text-lg text-slate-800">${escapeHtml(t.plate)}</h3>
                                <p class="text-xs text-slate-500 font-medium">${escapeHtml(t.name)}</p>
                                ${displayPhone ? `<div class="flex items-center gap-1.5 mt-2 text-xs text-slate-600 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100 w-fit"><i data-lucide="phone" class="w-3 h-3 text-slate-400"></i> ${escapeHtml(displayPhone)}</div>` : ''}
                                ${t.franchise ? `<p class="text-[10px] text-orange-500 mt-1">Franchise: ${escapeHtml(t.franchise)}</p>` : ''}
                            </div>
                            <div class="pl-3 text-right">
                                <button class="btn-process-case bg-white border border-slate-200 text-slate-700 text-xs font-semibold px-4 py-2 rounded-lg hover:border-primary-500 hover:text-primary-600 transition-all shadow-sm flex items-center gap-2 ml-auto group-hover:bg-primary-50" data-transfer-id="${t.id}">
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
                        `<span class="flex items-center gap-1.5 text-slate-600 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100 w-fit"><i data-lucide="phone" class="w-3 h-3 text-slate-400"></i> ${escapeHtml(t.phone)}</span>` : 
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

                    // Service date formatting
                    let serviceDateDisplay = '<span class="text-slate-400 text-xs">Not scheduled</span>';
                    if (t.service_date) {
                        const svcDate = new Date(t.service_date.replace(' ', 'T'));
                        const svcDateStr = svcDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                        serviceDateDisplay = `<div class="flex items-center gap-1 text-xs text-slate-700 bg-blue-50 px-2 py-1 rounded-lg border border-blue-200 w-fit">
                            <i data-lucide="calendar-check" class="w-3.5 h-3.5 text-blue-600"></i>
                            <span class="font-semibold">${svcDateStr}</span>
                        </div>`;
                    }
                    
                    // Review stars display
                    let reviewDisplay = '';
                    if (t.reviewStars && t.reviewStars > 0) {
                        const stars = '⭐'.repeat(parseInt(t.reviewStars));
                        reviewDisplay = `<div class="flex items-center gap-1 mt-1">
                            <span class="text-xs">${stars}</span>
                            ${t.reviewComment ? `<i data-lucide="message-square" class="w-3 h-3 text-amber-500" title="${t.reviewComment}"></i>` : ''}
                        </div>`;
                    }

                    activeContainer.innerHTML += `
                        <tr class="border-b border-slate-50 hover:bg-gradient-to-r hover:from-slate-50/50 hover:via-blue-50/30 hover:to-slate-50/50 transition-all group cursor-pointer" data-transfer-id="${t.id}">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-2.5 rounded-xl shadow-lg shadow-blue-500/25 group-hover:shadow-blue-500/40 transition-all">
                                        <i data-lucide="car" class="w-4 h-4 text-white"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-mono font-extrabold text-slate-900 text-sm tracking-wide">${escapeHtml(t.plate)}</span>
                                            <span class="text-[9px] font-mono text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200">ID: ${t.id}</span>
                                        </div>
                                        <div class="font-semibold text-xs text-slate-700">${escapeHtml(t.name)}</div>
                                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                                            <span class="text-[10px] text-slate-400 flex items-center gap-1 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100">
                                                <i data-lucide="clock" class="w-3 h-3"></i> ${dateStr}
                                            </span>
                                            ${t.franchise ? `<span class="text-[10px] text-orange-600 flex items-center gap-1 bg-orange-50 px-1.5 py-0.5 rounded border border-orange-100">
                                                <i data-lucide="percent" class="w-3 h-3"></i> Franchise: ${escapeHtml(t.franchise)}₾
                                            </span>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="coins" class="w-4 h-4 text-emerald-500"></i>
                                    <span class="font-bold text-emerald-600 text-base">${escapeHtml(t.amount)}₾</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] uppercase tracking-wider font-bold border shadow-sm ${badgeClass}">
                                    <div class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></div>
                                    ${t.status}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                ${hasPhone}
                                ${reviewDisplay}
                            </td>
                            <td class="px-5 py-4">
                                ${serviceDateDisplay}
                            </td>
                            <td class="px-5 py-4">
                                ${replyBadge}
                            </td>
                            <td class="px-5 py-4 text-right">
                                ${CAN_EDIT ? 
                                    `<button class="btn-edit-transfer text-slate-400 hover:text-primary-600 p-2.5 hover:bg-primary-50 rounded-xl transition-all shadow-sm hover:shadow-lg hover:shadow-primary-500/25 active:scale-95 group-hover:bg-white" data-transfer-id="${t.id}">
                                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                                    </button>` :
                                    `<button class="btn-edit-transfer text-slate-400 hover:text-blue-600 p-2.5 hover:bg-blue-50 rounded-xl transition-all shadow-sm active:scale-95" data-transfer-id="${t.id}" title="View Only">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>`
                                }
                            </td>
                        </tr>`;
                }
            });

            const newCountEl = document.getElementById('new-count');
            const recordCountEl = document.getElementById('record-count');
            const newCasesEmpty = document.getElementById('new-cases-empty');
            const emptyState = document.getElementById('empty-state');
            
            if (newCountEl) newCountEl.innerText = `${newCount}`;
            if (recordCountEl) recordCountEl.innerText = `${activeCount} active`;
            newCasesEmpty?.classList.toggle('hidden', newCount > 0);
            emptyState?.classList.toggle('hidden', activeCount > 0);
            
            if (window.lucide) lucide.createIcons();
        }

        window.openEditModal = (id) => {
            if (!transfers || !Array.isArray(transfers)) {
                console.error('Transfers array not available');
                return;
            }
            
            const t = transfers.find(i => i.id == id);
            if (!t) {
                console.warn(`Transfer with id ${id} not found`);
                return;
            }
            window.currentEditingId = id; // Ensure global scope assignment
            
            // Auto-fill phone from registry if missing in transfer
            const linkedVehicle = vehicles.find(v => normalizePlate(v.plate) === normalizePlate(t.plate));
            const phoneToFill = t.phone || (linkedVehicle ? linkedVehicle.phone : '');

            const modalTitleRef = document.getElementById('modal-title-ref');
            const modalTitleName = document.getElementById('modal-title-name');
            const modalOrderId = document.getElementById('modal-order-id');
            const modalAmount = document.getElementById('modal-amount');
            const inputPhone = document.getElementById('input-phone');
            const inputServiceDate = document.getElementById('input-service-date');
            const inputFranchise = document.getElementById('input-franchise');
            const inputStatus = document.getElementById('input-status');
            
            if (modalTitleRef) modalTitleRef.innerText = t.plate || '';
            if (modalTitleName) modalTitleName.innerText = t.name || '';
            if (modalOrderId) modalOrderId.innerText = `#${t.id}`;
            if (modalAmount) modalAmount.innerText = t.amount || '0';
            if (inputPhone) inputPhone.value = phoneToFill;
            if (inputServiceDate) inputServiceDate.value = t.serviceDate ? t.serviceDate.replace(' ', 'T') : ''; 
            if (inputFranchise) inputFranchise.value = t.franchise || '';
            if (inputStatus) inputStatus.value = t.status || 'New';
            
            // Format and display created date
            const modalCreatedDate = document.getElementById('modal-created-date');
            if (modalCreatedDate) {
                if (t.created_at) {
                    const createdDate = new Date(t.created_at);
                    modalCreatedDate.innerText = createdDate.toLocaleString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric',
                        hour: '2-digit', 
                        minute: '2-digit' 
                    });
                } else {
                    modalCreatedDate.innerText = 'N/A';
                }
            }
            
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
                    <div class="text-[10px] text-slate-400 uppercase tracking-wider mb-0.5">${l.timestamp?.split('T')[0] || ''}</div>
                    ${escapeHtml(l.message)}
                </div>`).join('');
            
            const activityLogContainer = document.getElementById('activity-log-container');
            if (activityLogContainer) {
                activityLogContainer.innerHTML = logHTML || '<div class="text-center py-4"><span class="italic text-slate-300 text-xs">No system activity recorded</span></div>';
            }
            
            const noteHTML = (t.internalNotes || []).map(n => `
                <div class="bg-white p-3 rounded-lg border border-yellow-100 shadow-sm mb-3">
                    <p class="text-sm text-slate-700">${escapeHtml(n.text || '')}</p>
                    <div class="flex justify-end mt-2"><span class="text-[10px] text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">${escapeHtml(n.authorName || '')}</span></div>
                </div>`).join('');
            
            const notesList = document.getElementById('notes-list');
            if (notesList) {
                notesList.innerHTML = noteHTML || '<div class="h-full flex items-center justify-center text-slate-400 text-xs italic">No team notes yet</div>';
            }

            // Display customer review if exists
            const reviewSection = document.getElementById('modal-review-section');
            if (reviewSection) {
                if (t.reviewStars && t.reviewStars > 0) {
                    reviewSection.classList.remove('hidden');
                    
                    const reviewRating = document.getElementById('modal-review-rating');
                    if (reviewRating) reviewRating.innerText = t.reviewStars;
                    
                    // Render stars
                    const starsHTML = Array(5).fill(0).map((_, i) => 
                        `<i data-lucide="star" class="w-5 h-5 ${i < t.reviewStars ? 'text-yellow-400 fill-yellow-400' : 'text-gray-300'}"></i>`
                    ).join('');
                    
                    const reviewStars = document.getElementById('modal-review-stars');
                    if (reviewStars) reviewStars.innerHTML = starsHTML;
                    
                    // Display comment
                    const comment = t.reviewComment || 'No comment provided';
                    const reviewComment = document.getElementById('modal-review-comment');
                    if (reviewComment) reviewComment.innerText = comment;
                } else {
                    reviewSection.classList.add('hidden');
                }
            }

            // Display reschedule request if exists
            const rescheduleSection = document.getElementById('modal-reschedule-section');
            if (rescheduleSection) {
                if (t.userResponse === 'Reschedule Requested' && (t.rescheduleDate || t.rescheduleComment)) {
                    rescheduleSection.classList.remove('hidden');
                    
                    const rescheduleDateEl = document.getElementById('modal-reschedule-date');
                    if (rescheduleDateEl) {
                        if (t.rescheduleDate) {
                            const requestedDate = new Date(t.rescheduleDate.replace(' ', 'T'));
                            rescheduleDateEl.innerText = requestedDate.toLocaleString('en-US', { 
                                month: 'short', 
                                day: 'numeric', 
                                year: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit'
                            });
                        } else {
                            rescheduleDateEl.innerText = 'Not specified';
                        }
                    }
                    
                    const rescheduleComment = t.rescheduleComment || 'No additional comments';
                    const rescheduleCommentEl = document.getElementById('modal-reschedule-comment');
                    if (rescheduleCommentEl) rescheduleCommentEl.innerText = rescheduleComment;
                } else {
                    rescheduleSection.classList.add('hidden');
                }
            }

            const editModal = document.getElementById('edit-modal');
            if (editModal) editModal.classList.remove('hidden');
            
            if (window.lucide) lucide.createIcons();
        };

        window.closeModal = () => { 
            const editModal = document.getElementById('edit-modal');
            if (editModal) editModal.classList.add('hidden'); 
            window.currentEditingId = null; 
        };

        // Manual Create Modal Functions
        window.openManualCreateModal = async () => {
            // Check permissions
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You need Manager or Admin role to create orders', 'error');
                return;
            }
            
            const modal = document.getElementById('manual-create-modal');
            if (!modal) return;
            
            modal.classList.remove('hidden');
            
            // Clear all inputs with null checks
            const plateInput = document.getElementById('manual-plate');
            const nameInput = document.getElementById('manual-name');
            const phoneInput = document.getElementById('manual-phone');
            const amountInput = document.getElementById('manual-amount');
            const franchiseInput = document.getElementById('manual-franchise');
            const statusInput = document.getElementById('manual-status');
            const notesInput = document.getElementById('manual-notes');
            
            if (plateInput) plateInput.value = '';
            if (nameInput) nameInput.value = '';
            if (phoneInput) phoneInput.value = '';
            if (amountInput) amountInput.value = '';
            if (franchiseInput) franchiseInput.value = '';
            if (statusInput) statusInput.value = 'New';
            if (notesInput) notesInput.value = '';
            
            if (window.lucide) lucide.createIcons();
            
            // Focus on first input
            setTimeout(() => plateInput?.focus(), 100);
        };

        window.closeManualCreateModal = () => {
            const modal = document.getElementById('manual-create-modal');
            if (modal) modal.classList.add('hidden');
        };

        window.saveManualOrder = async () => {
            // Check permissions
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You need Manager or Admin role to create orders', 'error');
                return;
            }
            
            const plateInput = document.getElementById('manual-plate');
            const nameInput = document.getElementById('manual-name');
            const phoneInput = document.getElementById('manual-phone');
            const amountInput = document.getElementById('manual-amount');
            const franchiseInput = document.getElementById('manual-franchise');
            const statusInput = document.getElementById('manual-status');
            const notesInput = document.getElementById('manual-notes');
            
            if (!plateInput || !nameInput || !phoneInput || !amountInput) {
                showToast('Error', 'Required form fields not found', 'error');
                return;
            }
            
            const plate = plateInput.value.trim();
            const name = nameInput.value.trim();
            const phone = phoneInput.value.trim();
            const amount = parseFloat(amountInput.value) || 0;
            const franchise = parseFloat(franchiseInput?.value || '0') || 0;
            const status = statusInput?.value || 'New';
            const notes = notesInput?.value.trim() || '';

            // Validation
            if (!plate) {
                showToast('Validation Error', 'Vehicle plate number is required', 'error');
                plateInput?.focus();
                return;
            }
            if (!name) {
                showToast('Validation Error', 'Customer name is required', 'error');
                nameInput?.focus();
                return;
            }
            if (isNaN(amount) || amount <= 0) {
                showToast('Validation Error', 'Amount must be a valid number greater than 0', 'error');
                amountInput?.focus();
                return;
            }
            if (franchise < 0) {
                showToast('Validation Error', 'Franchise cannot be negative', 'error');
                franchiseInput?.focus();
                return;
            }

            // Disable submit button to prevent double submission
            const submitBtn = document.getElementById('manual-create-submit');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Creating...';
            
            // Prepare data
            const orderData = {
                plate: plate.toUpperCase(),
                name: name,
                phone: phone,
                amount: amount,
                franchise: franchise,
                status: status,
                internalNotes: notes ? [{ note: notes, timestamp: new Date().toISOString(), user: '<?php echo $current_user_name; ?>' }] : [],
                systemLogs: [{ 
                    message: `Order manually created by <?php echo $current_user_name; ?>`, 
                    timestamp: new Date().toISOString(), 
                    type: 'info' 
                }]
            };

            try {
                const result = await fetchAPI('create_transfer', 'POST', orderData);
                
                if (result && result.status === 'success') {
                    showToast('Success', 'Order created successfully!', 'success');
                    window.closeManualCreateModal();
                    
                    // Refresh the table
                    await loadData();
                    
                    // Open the newly created order
                    if (result.id) {
                        setTimeout(() => window.openEditModal(result.id), 500);
                    }
                } else {
                    const errorMsg = result?.message || 'Failed to create order';
                    showToast('Error', errorMsg, 'error');
                }
            } catch (error) {
                showToast('Error', error.message || 'Failed to create order', 'error');
            } finally {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Create Order';
                lucide.createIcons();
            }
        };

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

            const franchiseInput = document.getElementById('input-franchise');
            
            const updates = {
                status,
                phone,
                serviceDate: serviceDate || null,
                franchise: franchiseInput?.value || t.franchise || '',
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
                const connectionStatus = document.getElementById('connection-status');
                if (connectionStatus?.innerText.includes('Offline')) {
                    const v = vehicles.find(v => v.plate === t.plate);
                    if(v) v.phone = phone;
                } else {
                    await fetchAPI('sync_vehicle', 'POST', { plate: t.plate, phone: phone });
                }
            }

            const connectionStatus = document.getElementById('connection-status');
            if (connectionStatus?.innerText.includes('Offline')) {
                Object.assign(t, updates);
            } else {
                await fetchAPI(`update_transfer&id=${window.currentEditingId}`, 'POST', updates);
            }
            
            loadData();
            showToast("Changes Saved", "success");
        };

        window.addNote = async () => {
            const noteInput = document.getElementById('new-note-input');
            const text = noteInput?.value;
            if (!text) return;
            
            if (!transfers || !Array.isArray(transfers)) {
                console.error('Transfers array not available');
                return;
            }
            const t = transfers.find(i => i.id == window.currentEditingId);
            if (!t) return;
            const newNote = { text, authorName: 'Manager', timestamp: new Date().toISOString() };
            
            const connectionStatus = document.getElementById('connection-status');
            if (connectionStatus?.innerText.includes('Offline')) {
            if (document.getElementById('connection-status').innerText.includes('Offline')) {
                if(!t.internalNotes) t.internalNotes = [];
                t.internalNotes.push(newNote);
            } else {
                const notes = [...(t.internalNotes || []), newNote];
                await fetchAPI(`update_transfer&id=${window.currentEditingId}`, 'POST', { internalNotes: notes });
                t.internalNotes = notes;
            }
            
            if (noteInput) noteInput.value = '';
            
            // Re-render notes
            const noteHTML = (t.internalNotes || []).map(n => `
                <div class="bg-white p-3 rounded-lg border border-yellow-100 shadow-sm mb-3 animate-in slide-in-from-bottom-2 fade-in">
                    <p class="text-sm text-slate-700">${escapeHtml(n.text || '')}</p>
                    <div class="flex justify-end mt-2"><span class="text-[10px] text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">${escapeHtml(n.authorName || '')}</span></div>
                </div>`).join('');
            
            const notesList = document.getElementById('notes-list');
            if (notesList) notesList.innerHTML = noteHTML;
        };

        window.quickAcceptReschedule = async (id) => {
            if (!transfers || !Array.isArray(transfers)) return;
            
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
                    const logHTML = logsToRender.map(l => `<div class="mb-2 last:mb-0 pl-3 border-l-2 border-slate-200 text-slate-600"><div class="text-[10px] text-slate-400 uppercase tracking-wider mb-0.5">${l.timestamp.split('T')[0]}</div>${escapeHtml(l.message)}</div>`).join('');
                    document.getElementById('activity-log-container').innerHTML = logHTML;
                }
                showToast("SMS Sent", "success");
            } catch(e) { console.error(e); showToast("SMS Failed", "error"); }
        };

        // Consolidate all event listeners in one place
        document.addEventListener('DOMContentLoaded', () => {
            // Filter and search listeners
            document.getElementById('search-input')?.addEventListener('input', renderTable);
            document.getElementById('status-filter')?.addEventListener('change', renderTable);
            document.getElementById('reply-filter')?.addEventListener('change', renderTable);
            document.getElementById('new-note-input')?.addEventListener('keypress', (e) => { 
                if(e.key === 'Enter') window.addNote(); 
            });
            
            // Vehicle search handler
            const vehiclesSearch = document.getElementById('vehicles-search');
            if (vehiclesSearch) {
                vehiclesSearch.addEventListener('input', () => {
                    currentVehiclesPage = 1;
                    renderVehicles(1);
                });
            }
            
            // Event delegation for dynamic transfer rows
            const tableBody = document.getElementById('table-body');
            if (tableBody) {
                tableBody.addEventListener('click', (e) => {
                    const row = e.target.closest('tr[data-transfer-id]');
                    const editBtn = e.target.closest('.btn-edit-transfer');
                    
                    if (editBtn) {
                        e.stopPropagation();
                        const id = editBtn.dataset.transferId;
                        if (id) window.openEditModal(parseInt(id));
                    } else if (row) {
                        const id = row.dataset.transferId;
                        if (id) window.openEditModal(parseInt(id));
                    }
                });
            }
            
            // Event delegation for new cases grid
            const newCasesGrid = document.getElementById('new-cases-grid');
            if (newCasesGrid) {
                newCasesGrid.addEventListener('click', (e) => {
                    const btn = e.target.closest('.btn-process-case');
                    if (btn) {
                        const id = btn.dataset.transferId;
                        if (id) window.openEditModal(parseInt(id));
                    }
                });
            }
            
            // Notification prompt
            if ('Notification' in window && Notification.permission === 'default') {
                const prompt = document.getElementById('notification-prompt');
                if(prompt) setTimeout(() => prompt.classList.remove('hidden'), 2000);
            }
            
            // Load SMS templates
            loadSMSTemplates();
            
            // Ensure all modals are hidden
            document.getElementById('edit-modal')?.classList.add('hidden');
            
            // Initialize data and icons
            try {
                loadData();
            } catch (e) {
                console.error('Error loading initial data:', e);
                showToast('Error', 'Failed to load data. Please refresh the page.', 'error');
            }
            
            if(window.lucide) lucide.createIcons();
        });
        
        window.insertSample = (t) => document.getElementById('import-text').value = t;

    </script>
</body>
</html>