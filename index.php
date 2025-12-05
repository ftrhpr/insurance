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
            background-color: #f1f5f9; 
            color: #0f172a; 
            font-weight: 600;
        }
        .nav-inactive { 
            color: #64748b; 
        }
        .nav-inactive:hover { 
            color: #0f172a;
            background-color: #f8fafc;
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
                            <button onclick="window.switchView('dashboard')" id="nav-dashboard" class="nav-active px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                            </button>
                            <button onclick="window.switchView('vehicles')" id="nav-vehicles" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="database" class="w-4 h-4"></i> Vehicle DB
                            </button>
                            <button onclick="window.switchView('reviews')" id="nav-reviews" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="star" class="w-4 h-4"></i> Reviews
                            </button>
                            <button onclick="window.switchView('templates')" id="nav-templates" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                                <i data-lucide="message-square-dashed" class="w-4 h-4"></i> SMS Templates
                            </button>
                        </div>
                    </div>

                    <!-- User Status -->
                    <div class="flex items-center gap-4">
                        <!-- Notification Bell (Manual Trigger) -->
                        <button id="btn-notify" onclick="window.requestNotificationPermission()" class="text-slate-400 hover:text-primary-600 transition-colors p-2 bg-slate-100 rounded-full group relative" title="Enable Notifications">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                            <span id="notify-badge" class="absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white hidden"></span>
                        </button>

                        <div id="connection-status" class="flex items-center gap-2 text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1.5 rounded-full shadow-sm">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            Server Connected
                        </div>
                        <div id="user-display" class="w-8 h-8 bg-slate-200 rounded-full flex items-center justify-center text-slate-500 text-xs font-bold border border-slate-300">
                            M
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

            <!-- DASHBOARD VIEW -->
            <div id="view-dashboard" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                
                <!-- Import Section -->
                <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md group">
                    <div class="p-6 sm:p-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                    <i data-lucide="file-input" class="w-5 h-5 text-primary-500"></i>
                                    Quick Import
                                </h2>
                                <p class="text-sm text-slate-500 mt-1">Paste SMS or bank statement text to auto-detect transfers.</p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="window.insertSample('მანქანის ნომერი: AA123BB დამზღვევი: სახელი გვარი, 1234.00 (ფრანშიზა 273.97)')" class="text-xs font-medium text-indigo-600 bg-indigo-50 px-3 py-2 rounded-lg hover:bg-indigo-100 transition-colors border border-indigo-100">
                                    Sample with Franchise
                                </button>
                                <button onclick="window.insertSample('მანქანის ნომერი: GE-123-GE დამზღვევი: Sample User, 150.00')" class="text-xs font-medium text-slate-600 bg-slate-50 px-3 py-2 rounded-lg hover:bg-slate-100 transition-colors border border-slate-200">
                                    Simple Sample
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex flex-col md:flex-row gap-6">
                            <!-- Text Input -->
                            <div class="flex-1 space-y-3">
                                <div class="relative">
                                    <textarea id="import-text" class="w-full h-32 p-4 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none text-sm font-mono resize-none transition-all placeholder:text-slate-400" placeholder="Paste bank text here..."></textarea>
                                    <div class="absolute bottom-3 right-3">
                                        <button onclick="window.parseBankText()" id="btn-analyze" class="bg-slate-900 text-white px-4 py-2 rounded-lg hover:bg-slate-800 active:scale-95 transition-all text-xs font-semibold flex items-center gap-2 shadow-lg shadow-slate-900/20">
                                            <i data-lucide="sparkles" class="w-3 h-3"></i> Detect
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

                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50/80 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500 font-semibold">
                                    <tr>
                                        <th class="px-6 py-4">Vehicle & Owner</th>
                                        <th class="px-6 py-4">Stage</th>
                                        <th class="px-6 py-4">Contact Info</th>
                                        <th class="px-6 py-4">Customer Reply</th>
                                        <th class="px-6 py-4 text-right">Action</th>
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
            <div id="view-vehicles" class="hidden space-y-6 animate-in fade-in duration-300">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">Customer DB</h2>
                        <p class="text-slate-500 text-sm">Centralized database of all customers, vehicles and service history.</p>
                    </div>
                    <button onclick="window.openVehicleModal()" class="bg-slate-900 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-800 active:scale-95 transition-all flex items-center gap-2 shadow-lg shadow-slate-900/10">
                        <i data-lucide="plus" class="w-4 h-4"></i> Add Customer
                    </button>
                </div>

                <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-sm flex items-center">
                    <div class="p-3"><i data-lucide="search" class="w-5 h-5 text-slate-400"></i></div>
                    <input id="vehicle-search" type="text" placeholder="Search registry by plate, owner or model..." class="w-full bg-transparent outline-none text-sm h-full py-2">
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50/80 border-b border-slate-200 text-xs uppercase text-slate-500 font-semibold">
                                <tr>
                                    <th class="px-6 py-4">Plate</th>
                                    <th class="px-6 py-4">Owner</th>
                                    <th class="px-6 py-4">Phone</th>
                                    <th class="px-6 py-4">Model</th>
                                    <th class="px-6 py-4">Service History</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="vehicle-table-body" class="divide-y divide-slate-50"></tbody>
                        </table>
                        <div id="vehicle-empty" class="hidden py-16 text-center text-slate-400 text-sm">No vehicles found.</div>
                    </div>
                </div>
            </div>

            <!-- VIEW: REVIEWS -->
            <div id="view-reviews" class="hidden space-y-6 animate-in fade-in duration-300">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">Customer Reviews</h2>
                        <p class="text-slate-500 text-sm">Manage and approve customer feedback.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="bg-gradient-to-r from-yellow-400 to-orange-400 px-4 py-2 rounded-xl text-white font-bold flex items-center gap-2">
                            <i data-lucide="star" class="w-5 h-5"></i>
                            <span id="avg-rating">0.0</span>
                        </div>
                        <div class="bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-600">
                            <span id="total-reviews">0</span> Total Reviews
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="bg-white rounded-2xl border border-slate-200 p-2 flex gap-2">
                    <button onclick="window.filterReviews('all')" id="filter-all" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold bg-slate-900 text-white transition-all">
                        All Reviews
                    </button>
                    <button onclick="window.filterReviews('pending')" id="filter-pending" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                        Pending <span id="pending-count" class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full text-xs ml-1">0</span>
                    </button>
                    <button onclick="window.filterReviews('approved')" id="filter-approved" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                        Approved
                    </button>
                    <button onclick="window.filterReviews('rejected')" id="filter-rejected" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                        Rejected
                    </button>
                </div>

                <!-- Reviews Grid -->
                <div id="reviews-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <!-- Reviews injected here -->
                </div>

                <div id="reviews-empty" class="hidden py-20 flex flex-col items-center justify-center bg-white rounded-2xl border border-dashed border-slate-200 text-slate-400">
                    <div class="bg-slate-50 p-3 rounded-full mb-3"><i data-lucide="star-off" class="w-6 h-6"></i></div>
                    <span class="text-sm font-medium">No reviews yet</span>
                </div>
            </div>

            <!-- VIEW: TEMPLATES -->
            <div id="view-templates" class="hidden space-y-6 animate-in fade-in duration-300">
                <div class="flex justify-between items-center border-b border-slate-200 pb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">SMS Templates</h2>
                        <p class="text-slate-500 text-sm mt-1">Customize automated messages sent to customers.</p>
                    </div>
                    <button onclick="window.saveAllTemplates()" class="bg-slate-900 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-800 active:scale-95 transition-all flex items-center gap-2 shadow-lg shadow-slate-900/10">
                        <i data-lucide="save" class="w-4 h-4"></i> Save All Changes
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Template List -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Template Card -->
                        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="bg-indigo-100 p-2 rounded-lg text-indigo-600"><i data-lucide="message-square" class="w-4 h-4"></i></div>
                                <h3 class="font-bold text-slate-800">Welcome SMS (New Case)</h3>
                            </div>
                            <textarea id="tpl-registered" class="w-full h-24 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none resize-none leading-relaxed" placeholder="Enter template..."></textarea>
                        </div>

                        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="bg-purple-100 p-2 rounded-lg text-purple-600"><i data-lucide="phone-call" class="w-4 h-4"></i></div>
                                <h3 class="font-bold text-slate-800">Customer Contacted (Called)</h3>
                            </div>
                            <textarea id="tpl-called" class="w-full h-24 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none resize-none leading-relaxed" placeholder="Enter template..."></textarea>
                        </div>

                        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="bg-orange-100 p-2 rounded-lg text-orange-600"><i data-lucide="calendar" class="w-4 h-4"></i></div>
                                <h3 class="font-bold text-slate-800">Service Scheduled</h3>
                            </div>
                            <textarea id="tpl-schedule" class="w-full h-24 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none resize-none leading-relaxed" placeholder="Enter template..."></textarea>
                        </div>

                        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="bg-blue-100 p-2 rounded-lg text-blue-600"><i data-lucide="package" class="w-4 h-4"></i></div>
                                <h3 class="font-bold text-slate-800">Parts Ordered</h3>
                            </div>
                            <textarea id="tpl-parts_ordered" class="w-full h-24 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none resize-none leading-relaxed" placeholder="Enter template..."></textarea>
                        </div>

                        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="bg-teal-100 p-2 rounded-lg text-teal-600"><i data-lucide="package-check" class="w-4 h-4"></i></div>
                                <h3 class="font-bold text-slate-800">Parts Arrived</h3>
                            </div>
                            <textarea id="tpl-parts_arrived" class="w-full h-24 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none resize-none leading-relaxed" placeholder="Enter template..."></textarea>
                        </div>

                        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="bg-purple-100 p-2 rounded-lg text-purple-600"><i data-lucide="calendar-clock" class="w-4 h-4"></i></div>
                                <h3 class="font-bold text-slate-800">Reschedule (Response)</h3>
                            </div>
                            <textarea id="tpl-rescheduled" class="w-full h-24 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none resize-none leading-relaxed" placeholder="Enter template..."></textarea>
                        </div>
                        
                        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="bg-emerald-100 p-2 rounded-lg text-emerald-600"><i data-lucide="check-circle" class="w-4 h-4"></i></div>
                                <h3 class="font-bold text-slate-800">Service Completed</h3>
                            </div>
                            <textarea id="tpl-completed" class="w-full h-24 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none resize-none leading-relaxed" placeholder="Enter template..."></textarea>
                        </div>

                        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="bg-red-100 p-2 rounded-lg text-red-600"><i data-lucide="alert-circle" class="w-4 h-4"></i></div>
                                <h3 class="font-bold text-slate-800">Issue Reported</h3>
                            </div>
                            <textarea id="tpl-issue" class="w-full h-24 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none resize-none leading-relaxed" placeholder="Enter template..."></textarea>
                        </div>

                    </div>

                    <!-- Sidebar: Placeholders -->
                    <div class="lg:col-span-1">
                        <div class="bg-slate-50 rounded-xl border border-slate-200 p-5 sticky top-24">
                            <h4 class="font-bold text-slate-700 mb-4 flex items-center gap-2"><i data-lucide="code" class="w-4 h-4"></i> Available Variables</h4>
                            <p class="text-xs text-slate-500 mb-4">Click to copy placeholders into your templates.</p>
                            
                            <div class="space-y-2">
                                <button onclick="navigator.clipboard.writeText('{name}'); showToast('Copied {name}')" class="w-full text-left px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-mono text-primary-600 hover:border-primary-300 hover:bg-primary-50 transition-colors flex justify-between">
                                    <span>{name}</span> <span class="text-slate-400 font-sans">Customer Name</span>
                                </button>
                                <button onclick="navigator.clipboard.writeText('{plate}'); showToast('Copied {plate}')" class="w-full text-left px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-mono text-primary-600 hover:border-primary-300 hover:bg-primary-50 transition-colors flex justify-between">
                                    <span>{plate}</span> <span class="text-slate-400 font-sans">Car Plate</span>
                                </button>
                                <button onclick="navigator.clipboard.writeText('{amount}'); showToast('Copied {amount}')" class="w-full text-left px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-mono text-primary-600 hover:border-primary-300 hover:bg-primary-50 transition-colors flex justify-between">
                                    <span>{amount}</span> <span class="text-slate-400 font-sans">Transfer Amount</span>
                                </button>
                                <button onclick="navigator.clipboard.writeText('{date}'); showToast('Copied {date}')" class="w-full text-left px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-mono text-primary-600 hover:border-primary-300 hover:bg-primary-50 transition-colors flex justify-between">
                                    <span>{date}</span> <span class="text-slate-400 font-sans">Service Date</span>
                                </button>
                                <button onclick="navigator.clipboard.writeText('{link}'); showToast('Copied {link}')" class="w-full text-left px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-mono text-primary-600 hover:border-primary-300 hover:bg-primary-50 transition-colors flex justify-between">
                                    <span>{link}</span> <span class="text-slate-400 font-sans">Confirmation URL</span>
                                </button>
                            </div>
                            
                            <div class="mt-6 pt-6 border-t border-slate-200">
                                <div class="flex items-start gap-2 text-xs text-slate-500">
                                    <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
                                    <p>Templates are automatically saved to your browser. Changes apply immediately to new SMS messages.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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

    <!-- Edit Modal (SaaS Style) -->
    <div id="edit-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="window.closeModal()"></div>

        <!-- Dialog -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-4xl border border-slate-100">
                
                <!-- Header -->
                <div class="bg-white px-6 py-4 border-b border-slate-100 flex justify-between items-center sticky top-0 z-10">
                    <div class="flex items-center gap-4">
                         <div class="bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-lg text-sm font-mono font-bold text-slate-800 shadow-sm">
                            <span id="modal-title-ref">AB-123-CD</span>
                         </div>
                         <div class="h-6 w-px bg-slate-200"></div>
                         <div class="flex flex-col">
                             <span class="text-xs text-slate-400 font-semibold uppercase">Customer</span>
                             <span class="text-sm font-medium text-slate-700" id="modal-title-name">User Name</span>
                         </div>
                    </div>
                    <button onclick="window.closeModal()" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-full transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="px-8 py-8 grid grid-cols-1 lg:grid-cols-2 gap-10 max-h-[75vh] overflow-y-auto custom-scrollbar bg-slate-50/30">
                    
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

    <!-- Vehicle Modal -->
    <div id="vehicle-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="window.closeVehicleModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-5">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Customer Details</h3>
                        <p class="text-xs text-slate-500">Edit customer and vehicle information.</p>
                    </div>
                    <div class="bg-slate-100 p-2 rounded-full text-slate-400">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <input type="hidden" id="veh-id">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Plate Number</label>
                        <input id="veh-plate" type="text" class="w-full p-3 border border-slate-200 bg-slate-50 rounded-xl text-sm uppercase font-mono font-bold tracking-wider focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none" placeholder="AA-000-AA">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Owner Name</label>
                        <input id="veh-owner" type="text" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none" placeholder="Full Name">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Phone</label>
                        <input id="veh-phone" type="text" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none" placeholder="599 00 00 00">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Car Model</label>
                        <input id="veh-model" type="text" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none" placeholder="e.g. Toyota Prius, Silver">
                    </div>
                </div>

                <!-- Service History -->
                <div id="veh-history-section" class="hidden bg-slate-50 rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-2 bg-slate-100 border-b border-slate-200">
                        <label class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="history" class="w-3 h-3"></i> Service History
                        </label>
                    </div>
                    <div id="veh-history-list" class="p-4 max-h-48 overflow-y-auto space-y-2 text-xs"></div>
                </div>

                <div class="flex gap-3 justify-end pt-2 border-t border-slate-100">
                    <button onclick="window.closeVehicleModal()" class="px-4 py-2.5 text-slate-500 hover:bg-slate-50 rounded-xl text-sm font-medium transition-colors">Cancel</button>
                    <button onclick="window.saveVehicle()" class="px-6 py-2.5 bg-slate-900 text-white hover:bg-slate-800 rounded-xl text-sm font-semibold shadow-lg shadow-slate-900/10 transition-all active:scale-95">Save Vehicle</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <script>
        const API_URL = 'api.php';
        const MANAGER_PHONE = "511144486"; 
        
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
                const newTransfers = await fetchAPI('get_transfers');
                const newVehicles = await fetchAPI('get_vehicles');
                
                if(Array.isArray(newTransfers)) transfers = newTransfers;
                if(Array.isArray(newVehicles)) vehicles = newVehicles;

                renderTable();
                renderVehicleTable();
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

        // Stylish Toast Function
        function showToast(title, message = '', type = 'success', duration = 4000) {
            const container = document.getElementById('toast-container');
            
            // Handle legacy calls
            if (typeof type === 'number') { duration = type; type = 'success'; } // fallback
            if (!message && !type) { type = 'success'; }
            else if (['success', 'error', 'info', 'urgent'].includes(message)) { type = message; message = ''; }
            
            // Create toast
            const toast = document.createElement('div');
            
            const colors = {
                success: { bg: 'bg-white', border: 'border-emerald-100', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-600', icon: 'check-circle-2' },
                error: { bg: 'bg-white', border: 'border-red-100', iconBg: 'bg-red-50', iconColor: 'text-red-600', icon: 'alert-circle' },
                info: { bg: 'bg-white', border: 'border-blue-100', iconBg: 'bg-blue-50', iconColor: 'text-blue-600', icon: 'info' },
                urgent: { bg: 'bg-white', border: 'border-indigo-200 toast-urgent', iconBg: 'bg-indigo-50', iconColor: 'text-indigo-600', icon: 'bell-ring' }
            };
            
            const style = colors[type] || colors.info;

            toast.className = `pointer-events-auto w-80 ${style.bg} border ${style.border} shadow-xl shadow-slate-200/60 rounded-xl p-4 flex items-start gap-3 transform transition-all duration-500 translate-y-10 opacity-0`;
            
            toast.innerHTML = `
                <div class="${style.iconBg} p-2.5 rounded-full shrink-0">
                    <i data-lucide="${style.icon}" class="w-5 h-5 ${style.iconColor}"></i>
                </div>
                <div class="flex-1 pt-1">
                    <h4 class="text-sm font-bold text-slate-800 leading-none mb-1">${title}</h4>
                    ${message ? `<p class="text-xs text-slate-500 leading-relaxed">${message}</p>` : ''}
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-slate-500 transition-colors -mt-1 -mr-1 p-1">
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
            document.getElementById('view-dashboard').classList.toggle('hidden', v !== 'dashboard');
            document.getElementById('view-vehicles').classList.toggle('hidden', v !== 'vehicles');
            document.getElementById('view-reviews').classList.toggle('hidden', v !== 'reviews');
            document.getElementById('view-templates').classList.toggle('hidden', v !== 'templates');
            
            const activeClass = "nav-active px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 bg-slate-900 text-white shadow-sm";
            const inactiveClass = "nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 text-slate-500 hover:text-slate-900 hover:bg-white";

            document.getElementById('nav-dashboard').className = v === 'dashboard' ? activeClass : inactiveClass;
            document.getElementById('nav-vehicles').className = v === 'vehicles' ? activeClass : inactiveClass;
            document.getElementById('nav-reviews').className = v === 'reviews' ? activeClass : inactiveClass;
            document.getElementById('nav-templates').className = v === 'templates' ? activeClass : inactiveClass;

            if (v === 'reviews') {
                loadReviews();
            }
        };

        // --- TEMPLATE LOGIC (UPDATED TO USE API) ---
        const defaultTemplates = {
            'registered': "Hello {name}, payment received. Ref: {plate}. Welcome to OTOMOTORS service.",
            'called': "Hello {name}, we contacted you regarding {plate}. Service details will follow shortly.",
            'schedule': "Hello {name}, service scheduled for {date}. Ref: {plate}.",
            'parts_ordered': "Parts ordered for {plate}. We will notify you when ready.",
            'parts_arrived': "Hello {name}, your parts have arrived! Please confirm your visit here: {link}",
            'rescheduled': "Hello {name}, your service has been rescheduled to {date}. Please confirm: {link}",
            'completed': "Service for {plate} is completed. Thank you for choosing OTOMOTORS! Rate your experience: {link}",
            'issue': "Hello {name}, we detected an issue with {plate}. Our team will contact you shortly."
        };
        
        let smsTemplates = defaultTemplates;

        // Explicitly defined function to ensure it's available immediately
        async function saveAllTemplates() {
            try {
                // Safeguard against missing elements
                const getVal = (id) => {
                    const el = document.getElementById(id);
                    return el ? el.value : '';
                };

                smsTemplates.registered = getVal('tpl-registered');
                smsTemplates.called = getVal('tpl-called');
                smsTemplates.schedule = getVal('tpl-schedule');
                smsTemplates.parts_ordered = getVal('tpl-parts_ordered');
                smsTemplates.parts_arrived = getVal('tpl-parts_arrived');
                smsTemplates.rescheduled = getVal('tpl-rescheduled');
                smsTemplates.completed = getVal('tpl-completed');
                smsTemplates.issue = getVal('tpl-issue');
                
                // SAVE TO API
                await fetchAPI('save_templates', 'POST', smsTemplates);
                showToast("Templates Saved to Database", "success");
            } catch (e) {
                console.error("Save error:", e);
                showToast("Error saving templates", "error");
            }
        }
        // Assign to window for HTML onClick handler safety
        window.saveAllTemplates = saveAllTemplates;

        async function loadTemplatesToUI() {
            try {
                // FETCH FROM API
                const serverTemplates = await fetchAPI('get_templates');
                
                // Merge default with server (server wins, but defaults fill gaps)
                smsTemplates = { ...defaultTemplates, ...serverTemplates };
                
                const setVal = (id, val) => {
                    const el = document.getElementById(id);
                    if(el) el.value = val || '';
                };

                setVal('tpl-registered', smsTemplates.registered);
                setVal('tpl-called', smsTemplates.called);
                setVal('tpl-schedule', smsTemplates.schedule);
                setVal('tpl-parts_ordered', smsTemplates.parts_ordered);
                setVal('tpl-parts_arrived', smsTemplates.parts_arrived);
                setVal('tpl-rescheduled', smsTemplates.rescheduled);
                setVal('tpl-completed', smsTemplates.completed);
                setVal('tpl-issue', smsTemplates.issue);
            } catch (e) {
                console.error("UI Load Error", e);
            }
        }

        function getFormattedMessage(type, data) {
            let template = smsTemplates[type] || defaultTemplates[type] || "";
            // Generate Link: Assume public_view.html is in same dir as index.html
            const baseUrl = window.location.href.replace(/index\.html.*/, '').replace(/\/$/, '');
            const link = `${baseUrl}/public_view.php?id=${data.id}`;

            return template
                .replace(/{name}/g, data.name || '')
                .replace(/{plate}/g, data.plate || '')
                .replace(/{amount}/g, data.amount || '')
                .replace(/{link}/g, link)
                .replace(/{date}/g, data.serviceDate ? data.serviceDate.replace('T', ' ') : '');
        }

        // Notification Prompt & Template Load
        document.addEventListener('DOMContentLoaded', () => {
            if ('Notification' in window && Notification.permission === 'default') {
                const prompt = document.getElementById('notification-prompt');
                if(prompt) setTimeout(() => prompt.classList.remove('hidden'), 2000);
            }
            loadTemplatesToUI(); // Load from API on start
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
                        replyBadge = `<span class="bg-orange-100 text-orange-700 border border-orange-200 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 w-fit"><i data-lucide="clock" class="w-3 h-3"></i> Reschedule</span>`;
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
                                <button onclick="window.openEditModal(${t.id})" class="text-slate-400 hover:text-primary-600 p-2 hover:bg-primary-50 rounded-lg transition-all"><i data-lucide="settings-2" class="w-4 h-4"></i></button>
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

            document.getElementById('edit-modal').classList.remove('hidden');
            lucide.createIcons();
        };

        window.closeModal = () => { document.getElementById('edit-modal').classList.add('hidden'); window.currentEditingId = null; };

        window.saveEdit = async () => {
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

        function renderVehicleTable() {
            const term = document.getElementById('vehicle-search').value.toLowerCase();
            const rows = vehicles.filter(v => (v.plate+v.ownerName).toLowerCase().includes(term));
            
            const html = rows.map(v => {
                // Get service history for this plate
                const serviceHistory = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(v.plate));
                const historyCount = serviceHistory.length;
                const lastService = serviceHistory.length > 0 ? serviceHistory[serviceHistory.length - 1] : null;
                
                let historyBadge = '';
                if (historyCount > 0) {
                    historyBadge = `<span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2 py-1 rounded-lg text-xs font-semibold">
                        <i data-lucide="file-text" class="w-3 h-3"></i> ${historyCount} service${historyCount > 1 ? 's' : ''}
                    </span>`;
                    if (lastService) {
                        const statusColors = {
                            'New': 'bg-blue-50 text-blue-600',
                            'Processing': 'bg-yellow-50 text-yellow-600',
                            'Completed': 'bg-green-50 text-green-600',
                            'Scheduled': 'bg-orange-50 text-orange-600'
                        };
                        const colorClass = statusColors[lastService.status] || 'bg-slate-50 text-slate-600';
                        historyBadge += ` <span class="ml-1 text-[10px] ${colorClass} px-1.5 py-0.5 rounded">${lastService.status}</span>`;
                    }
                } else {
                    historyBadge = '<span class="text-slate-300 text-xs italic">No history</span>';
                }
                
                return `
                <tr class="border-b border-slate-50 hover:bg-slate-50/50 group transition-colors">
                    <td class="px-6 py-4 font-mono font-bold text-slate-800">${v.plate}</td>
                    <td class="px-6 py-4 text-slate-600">${v.ownerName || '-'}</td>
                    <td class="px-6 py-4 text-sm text-slate-500">${v.phone || '-'}</td>
                    <td class="px-6 py-4 text-sm text-slate-500">${v.model || ''}</td>
                    <td class="px-6 py-4">${historyBadge}</td>
                    <td class="px-6 py-4 text-right">
                        <button onclick="window.editVehicle(${v.id})" class="text-primary-600 hover:bg-primary-50 p-2 rounded-lg transition-all"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                        <button onclick="window.deleteVehicle(${v.id})" class="text-red-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-lg transition-all"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </td>
                </tr>`;
            }).join('');
            
            document.getElementById('vehicle-table-body').innerHTML = html;
            document.getElementById('vehicle-empty').classList.toggle('hidden', rows.length > 0);
            lucide.createIcons();
        }

        window.openVehicleModal = () => {
            document.getElementById('veh-id').value = '';
            document.getElementById('veh-plate').value = '';
            document.getElementById('veh-owner').value = '';
            document.getElementById('veh-phone').value = '';
            document.getElementById('veh-model').value = '';
            document.getElementById('vehicle-modal').classList.remove('hidden');
        };
        window.closeVehicleModal = () => document.getElementById('vehicle-modal').classList.add('hidden');

        window.editVehicle = (id) => {
            const v = vehicles.find(i => i.id == id);
            document.getElementById('veh-id').value = id;
            document.getElementById('veh-plate').value = v.plate;
            document.getElementById('veh-owner').value = v.ownerName;
            document.getElementById('veh-phone').value = v.phone;
            document.getElementById('veh-model').value = v.model;
            
            // Show service history
            const serviceHistory = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(v.plate));
            const historySection = document.getElementById('veh-history-section');
            
            if (serviceHistory.length > 0) {
                historySection.classList.remove('hidden');
                const historyHTML = serviceHistory.map(s => {
                    const date = s.serviceDate ? new Date(s.serviceDate.replace(' ', 'T')).toLocaleDateString() : 'Not scheduled';
                    const statusColors = {
                        'New': 'bg-blue-100 text-blue-700',
                        'Processing': 'bg-yellow-100 text-yellow-700',
                        'Called': 'bg-purple-100 text-purple-700',
                        'Parts Ordered': 'bg-indigo-100 text-indigo-700',
                        'Parts Arrived': 'bg-teal-100 text-teal-700',
                        'Scheduled': 'bg-orange-100 text-orange-700',
                        'Completed': 'bg-green-100 text-green-700',
                        'Issue': 'bg-red-100 text-red-700'
                    };
                    const statusClass = statusColors[s.status] || 'bg-slate-100 text-slate-700';
                    return `
                        <div class="bg-white p-3 rounded-lg border border-slate-200 hover:border-indigo-300 transition-all cursor-pointer" onclick="window.openEditModal(${s.id})">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-semibold text-slate-700">${s.name}</span>
                                <span class="text-[10px] ${statusClass} px-2 py-0.5 rounded-full font-bold">${s.status}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 flex items-center gap-3">
                                <span><i data-lucide="calendar" class="w-3 h-3 inline"></i> ${date}</span>
                                <span><i data-lucide="coins" class="w-3 h-3 inline"></i> ${s.amount || 0} GEL</span>
                            </div>
                        </div>
                    `;
                }).join('');
                document.getElementById('veh-history-list').innerHTML = historyHTML;
            } else {
                historySection.classList.add('hidden');
            }
            
            document.getElementById('vehicle-modal').classList.remove('hidden');
            lucide.createIcons();
        };

        window.saveVehicle = async () => {
            const id = document.getElementById('veh-id').value;
            const data = {
                plate: document.getElementById('veh-plate').value,
                ownerName: document.getElementById('veh-owner').value,
                phone: document.getElementById('veh-phone').value,
                model: document.getElementById('veh-model').value
            };
            
            if (document.getElementById('connection-status').innerText.includes('Offline')) {
                if(id) {
                    const idx = vehicles.findIndex(v => v.id == id);
                    if(idx > -1) vehicles[idx] = { ...vehicles[idx], ...data };
                } else {
                    vehicles.push({ id: Math.floor(Math.random()*10000), ...data });
                }
            } else {
                await fetchAPI(`save_vehicle${id ? '&id='+id : ''}`, 'POST', data);
            }
            window.closeVehicleModal();
            loadData();
            showToast("Vehicle Saved", "success");
        };

        window.deleteVehicle = async (id) => {
            if(confirm("Delete vehicle?")) {
                if (document.getElementById('connection-status').innerText.includes('Offline')) {
                    vehicles = vehicles.filter(v => v.id != id);
                } else {
                    await fetchAPI(`delete_vehicle&id=${id}`, 'POST');
                }
                loadData();
                showToast("Vehicle Deleted", "success");
            }
        };

        document.getElementById('search-input').addEventListener('input', renderTable);
        document.getElementById('status-filter').addEventListener('change', renderTable);
        document.getElementById('reply-filter').addEventListener('change', renderTable);
        document.getElementById('vehicle-search').addEventListener('input', renderVehicleTable);
        document.getElementById('new-note-input').addEventListener('keypress', (e) => { if(e.key === 'Enter') window.addNote(); });
        window.insertSample = (t) => document.getElementById('import-text').value = t;

        // --- REVIEWS SYSTEM ---
        let customerReviews = [];
        let currentReviewFilter = 'all';

        async function loadReviews() {
            try {
                console.log('Loading reviews...');
                const data = await fetchAPI('get_reviews');
                console.log('Reviews data received:', data);
                
                if (data && data.reviews) {
                    customerReviews = data.reviews;
                    console.log('Number of reviews:', customerReviews.length);
                    
                    document.getElementById('avg-rating').textContent = data.average_rating || '0.0';
                    document.getElementById('total-reviews').textContent = data.total || 0;
                    
                    const pendingCount = customerReviews.filter(r => r.status === 'pending').length;
                    document.getElementById('pending-count').textContent = pendingCount;
                    
                    renderReviews();
                } else {
                    console.warn('No reviews data in response:', data);
                    // Show empty state
                    renderReviews();
                }
            } catch(e) {
                console.error('Load reviews error:', e);
                showToast('Failed to load reviews', 'error');
            }
        }

        window.filterReviews = (filter) => {
            currentReviewFilter = filter;
            
            // Update button states
            ['all', 'pending', 'approved', 'rejected'].forEach(f => {
                const btn = document.getElementById(`filter-${f}`);
                if (f === filter) {
                    btn.className = 'flex-1 px-4 py-2 rounded-lg text-sm font-semibold bg-slate-900 text-white transition-all';
                } else {
                    btn.className = 'flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all';
                }
            });
            
            renderReviews();
        };

        function renderReviews() {
            const container = document.getElementById('reviews-grid');
            const emptyState = document.getElementById('reviews-empty');
            
            console.log('Rendering reviews, total:', customerReviews.length, 'filter:', currentReviewFilter);
            
            let filteredReviews = customerReviews || [];
            if (currentReviewFilter !== 'all') {
                filteredReviews = filteredReviews.filter(r => r.status === currentReviewFilter);
            }
            
            console.log('Filtered reviews:', filteredReviews.length);
            
            if (filteredReviews.length === 0) {
                container.innerHTML = '';
                emptyState.classList.remove('hidden');
                return;
            }
            
            emptyState.classList.add('hidden');
            
            const html = filteredReviews.map(review => {
                const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
                const statusColors = {
                    pending: 'bg-yellow-50 border-yellow-200 text-yellow-700',
                    approved: 'bg-green-50 border-green-200 text-green-700',
                    rejected: 'bg-red-50 border-red-200 text-red-700'
                };
                const statusColor = statusColors[review.status] || statusColors.pending;
                
                const date = new Date(review.created_at).toLocaleDateString('en-GB', { 
                    month: 'short', day: 'numeric', year: 'numeric' 
                });
                
                return `
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-all">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h3 class="font-bold text-slate-800">${review.customer_name || 'Anonymous'}</h3>
                                <p class="text-xs text-slate-400 mt-0.5">${date}</p>
                            </div>
                            <span class="text-2xl text-yellow-400">${stars}</span>
                        </div>
                        
                        <p class="text-sm text-slate-600 leading-relaxed mb-4 line-clamp-3">${review.comment || ''}</p>
                        
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                            <span class="text-[10px] font-mono font-bold text-slate-400">Order #${review.order_id}</span>
                            <span class="px-2 py-1 rounded-full text-[10px] font-bold border ${statusColor} uppercase">
                                ${review.status}
                            </span>
                        </div>
                        
                        ${review.status === 'pending' ? `
                            <div class="flex gap-2 mt-3">
                                <button onclick="window.approveReview(${review.id})" class="flex-1 bg-green-500 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-green-600 active:scale-95 transition-all flex items-center justify-center gap-1">
                                    <i data-lucide="check" class="w-4 h-4"></i> Approve
                                </button>
                                <button onclick="window.rejectReview(${review.id})" class="flex-1 bg-red-500 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-red-600 active:scale-95 transition-all flex items-center justify-center gap-1">
                                    <i data-lucide="x" class="w-4 h-4"></i> Reject
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
            
            container.innerHTML = html;
            if (window.lucide) lucide.createIcons();
        }

        window.approveReview = async (id) => {
            try {
                await fetchAPI(`update_review_status&id=${id}`, 'POST', { status: 'approved' });
                showToast('Review Approved', 'success');
                loadReviews();
            } catch(e) {
                showToast('Failed to approve review', 'error');
            }
        };

        window.rejectReview = async (id) => {
            if (confirm('Reject this review permanently?')) {
                try {
                    await fetchAPI(`update_review_status&id=${id}`, 'POST', { status: 'rejected' });
                    showToast('Review Rejected', 'error');
                    loadReviews();
                } catch(e) {
                    showToast('Failed to reject review', 'error');
                }
            }
        };

        // Auto-send review link when marking as Completed
        const originalSaveEdit = window.saveEdit;
        window.saveEdit = async function() {
            const t = transfers.find(i => i.id == window.currentEditingId);
            const newStatus = document.getElementById('input-status').value;
            const phone = document.getElementById('input-phone').value;
            
            // If status changed to Completed, send review link
            if (newStatus === 'Completed' && t.status !== 'Completed' && phone) {
                const baseUrl = window.location.href.replace(/index\.php.*/, '').replace(/\/$/, '');
                const reviewLink = `${baseUrl}/public_view.php?id=${t.id}`;
                const reviewMsg = `Thank you for choosing OTOMOTORS! Your service for ${t.plate} is completed. Please share your experience: ${reviewLink}`;
                
                // Send review invitation SMS
                setTimeout(() => {
                    window.sendSMS(phone, reviewMsg, 'review_invitation');
                    showToast('Review Link Sent', 'SMS sent to customer', 'success');
                }, 500);
            }
            
            // Call original function
            await originalSaveEdit();
        };

        loadData();
        if(window.lucide) lucide.createIcons();

    </script>
</body>
</html>