<?php
// dashboard.php - Main dashboard page
require_once 'includes/auth.php';
$current_page = 'dashboard';
$page_title = 'Dashboard - OTOMOTORS Manager Portal';
require_once 'includes/header.php';
?>

<div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
    
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

<!-- Modals container -->
<div id="modals-container"></div>

<?php require_once 'includes/footer.php'; ?>
