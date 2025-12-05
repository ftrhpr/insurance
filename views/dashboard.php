<!-- DASHBOARD VIEW -->
<div id="view-dashboard" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-white/20 p-3 rounded-xl backdrop-blur-sm">
                    <i data-lucide="inbox" class="w-6 h-6"></i>
                </div>
                <span id="stat-new" class="text-3xl font-bold">0</span>
            </div>
            <h3 class="text-sm font-semibold opacity-90">New Cases</h3>
        </div>
        
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-white/20 p-3 rounded-xl backdrop-blur-sm">
                    <i data-lucide="clock" class="w-6 h-6"></i>
                </div>
                <span id="stat-processing" class="text-3xl font-bold">0</span>
            </div>
            <h3 class="text-sm font-semibold opacity-90">In Progress</h3>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-white/20 p-3 rounded-xl backdrop-blur-sm">
                    <i data-lucide="calendar" class="w-6 h-6"></i>
                </div>
                <span id="stat-scheduled" class="text-3xl font-bold">0</span>
            </div>
            <h3 class="text-sm font-semibold opacity-90">Scheduled</h3>
        </div>
        
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div class="bg-white/20 p-3 rounded-xl backdrop-blur-sm">
                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                </div>
                <span id="stat-completed" class="text-3xl font-bold">0</span>
            </div>
            <h3 class="text-sm font-semibold opacity-90">Completed</h3>
        </div>
    </div>

    <!-- Bank SMS Import -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="message-square" class="w-5 h-5 text-primary-600"></i>
                    Import Bank SMS
                </h2>
                <p class="text-sm text-slate-500 mt-1">Paste Georgian bank SMS to auto-import transfer</p>
            </div>
        </div>
        <div class="flex gap-3">
            <textarea id="sms-input" rows="3" class="flex-1 p-4 border border-slate-200 rounded-xl text-sm focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none resize-none" placeholder="Paste SMS text here..."></textarea>
            <button onclick="window.parseBankSMS()" class="px-6 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-semibold transition-all shadow-lg hover:shadow-xl active:scale-95 flex items-center gap-2">
                <i data-lucide="upload" class="w-5 h-5"></i>
                Import
            </button>
        </div>
    </div>

    <!-- Active Cases Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-800">Active Cases</h2>
            <div class="flex gap-2">
                <input type="text" id="search-input" placeholder="Search..." class="px-4 py-2 border border-slate-200 rounded-lg text-sm focus:border-primary-500 outline-none">
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Customer & Vehicle</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Response</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="active-transfers-body" class="divide-y divide-slate-50"></tbody>
            </table>
        </div>
    </div>

    <!-- New Cases Section -->
    <div id="new-cases-section" class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl border-2 border-blue-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 flex items-center justify-between">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i data-lucide="inbox" class="w-5 h-5"></i>
                New Imported Cases
            </h2>
            <span id="new-count-badge" class="bg-white text-blue-600 px-3 py-1 rounded-full text-sm font-bold">0</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-blue-100/50 border-b border-blue-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-900 uppercase">Customer & Vehicle</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-900 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-blue-900 uppercase">Date</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-blue-900 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody id="new-transfers-body" class="divide-y divide-blue-100"></tbody>
            </table>
        </div>
    </div>
</div>
