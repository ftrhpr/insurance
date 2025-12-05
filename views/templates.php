<!-- SMS TEMPLATES VIEW -->
<div id="view-templates" class="hidden space-y-6 animate-in fade-in duration-300">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">SMS Templates</h2>
            <p class="text-sm text-slate-500 mt-1">Customize automated SMS messages sent to customers</p>
        </div>
        <?php if (canEdit()): ?>
        <button onclick="window.saveAllTemplates()" class="flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg font-semibold hover:from-green-700 hover:to-emerald-700 transition-all shadow-lg hover:shadow-xl">
            <i data-lucide="save" class="w-4 h-4"></i>
            Save All Templates
        </button>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Templates Grid -->
        <div class="lg:col-span-2 grid grid-cols-1 gap-4">
            <!-- Welcome SMS -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="bg-blue-100 p-2 rounded-lg">
                            <i data-lucide="mail" class="w-4 h-4 text-blue-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Welcome SMS (Processing)</h3>
                            <p class="text-xs text-slate-500">Sent when status changes to Processing</p>
                        </div>
                    </div>
                    <button onclick="window.resetTemplate('registered')" class="text-xs text-slate-500 hover:text-slate-700">Reset</button>
                </div>
                <textarea id="tpl-registered" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none resize-none" <?php echo !canEdit() ? 'disabled' : ''; ?>></textarea>
            </div>

            <!-- Called SMS -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="bg-purple-100 p-2 rounded-lg">
                            <i data-lucide="phone" class="w-4 h-4 text-purple-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Called Confirmation</h3>
                            <p class="text-xs text-slate-500">Sent when status changes to Called</p>
                        </div>
                    </div>
                    <button onclick="window.resetTemplate('called')" class="text-xs text-slate-500 hover:text-slate-700">Reset</button>
                </div>
                <textarea id="tpl-called" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 outline-none resize-none" <?php echo !canEdit() ? 'disabled' : ''; ?>></textarea>
            </div>

            <!-- Scheduled SMS -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="bg-orange-100 p-2 rounded-lg">
                            <i data-lucide="calendar" class="w-4 h-4 text-orange-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Scheduled Confirmation</h3>
                            <p class="text-xs text-slate-500">Includes service date - sent when status is Scheduled</p>
                        </div>
                    </div>
                    <button onclick="window.resetTemplate('schedule')" class="text-xs text-slate-500 hover:text-slate-700">Reset</button>
                </div>
                <textarea id="tpl-schedule" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none resize-none" <?php echo !canEdit() ? 'disabled' : ''; ?>></textarea>
            </div>

            <!-- Parts Ordered -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="bg-indigo-100 p-2 rounded-lg">
                            <i data-lucide="package" class="w-4 h-4 text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Parts Ordered Notification</h3>
                            <p class="text-xs text-slate-500">Sent when status changes to Parts Ordered</p>
                        </div>
                    </div>
                    <button onclick="window.resetTemplate('parts_ordered')" class="text-xs text-slate-500 hover:text-slate-700">Reset</button>
                </div>
                <textarea id="tpl-parts_ordered" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none resize-none" <?php echo !canEdit() ? 'disabled' : ''; ?>></textarea>
            </div>

            <!-- Parts Arrived -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="bg-teal-100 p-2 rounded-lg">
                            <i data-lucide="check-circle" class="w-4 h-4 text-teal-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Parts Arrived (Confirmation Link)</h3>
                            <p class="text-xs text-slate-500">Includes confirmation link - sent when status is Parts Arrived</p>
                        </div>
                    </div>
                    <button onclick="window.resetTemplate('parts_arrived')" class="text-xs text-slate-500 hover:text-slate-700">Reset</button>
                </div>
                <textarea id="tpl-parts_arrived" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none resize-none" <?php echo !canEdit() ? 'disabled' : ''; ?>></textarea>
            </div>

            <!-- Rescheduled -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="bg-yellow-100 p-2 rounded-lg">
                            <i data-lucide="calendar-clock" class="w-4 h-4 text-yellow-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Rescheduled Notification</h3>
                            <p class="text-xs text-slate-500">Sent when service date is changed</p>
                        </div>
                    </div>
                    <button onclick="window.resetTemplate('rescheduled')" class="text-xs text-slate-500 hover:text-slate-700">Reset</button>
                </div>
                <textarea id="tpl-rescheduled" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-yellow-500 focus:ring-2 focus:ring-yellow-500/20 outline-none resize-none" <?php echo !canEdit() ? 'disabled' : ''; ?>></textarea>
            </div>

            <!-- Reschedule Accepted -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="bg-green-100 p-2 rounded-lg">
                            <i data-lucide="check" class="w-4 h-4 text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Reschedule Accepted (Manager)</h3>
                            <p class="text-xs text-slate-500">Sent when manager approves customer reschedule request</p>
                        </div>
                    </div>
                    <button onclick="window.resetTemplate('reschedule_accepted')" class="text-xs text-slate-500 hover:text-slate-700">Reset</button>
                </div>
                <textarea id="tpl-reschedule_accepted" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-green-500 focus:ring-2 focus:ring-green-500/20 outline-none resize-none" <?php echo !canEdit() ? 'disabled' : ''; ?>></textarea>
            </div>

            <!-- Completed -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="bg-emerald-100 p-2 rounded-lg">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Completion & Review Request</h3>
                            <p class="text-xs text-slate-500">Auto-sent when status changes to Completed</p>
                        </div>
                    </div>
                    <button onclick="window.resetTemplate('completed')" class="text-xs text-slate-500 hover:text-slate-700">Reset</button>
                </div>
                <textarea id="tpl-completed" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none resize-none" <?php echo !canEdit() ? 'disabled' : ''; ?>></textarea>
            </div>

            <!-- Issue -->
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="bg-red-100 p-2 rounded-lg">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">Issue Alert</h3>
                            <p class="text-xs text-slate-500">Sent when status changes to Issue</p>
                        </div>
                    </div>
                    <button onclick="window.resetTemplate('issue')" class="text-xs text-slate-500 hover:text-slate-700">Reset</button>
                </div>
                <textarea id="tpl-issue" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-red-500 focus:ring-2 focus:ring-red-500/20 outline-none resize-none" <?php echo !canEdit() ? 'disabled' : ''; ?>></textarea>
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
                        <p>Templates are automatically saved. Changes apply immediately to new SMS messages.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
