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
                        <span id="modal-plate">AA-000-AA</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900" id="modal-customer-name">Customer Name</h3>
                        <p class="text-xs text-slate-500">Edit Case Details</p>
                    </div>
                </div>
                <button onclick="window.closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors p-2 hover:bg-slate-50 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="px-6 py-5 max-h-[70vh] overflow-y-auto">
                <input type="hidden" id="modal-transfer-id">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Status</label>
                            <select id="input-status" class="w-full p-3 border border-slate-200 rounded-xl text-sm font-semibold focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none bg-slate-50">
                                <option value="New">🆕 New</option>
                                <option value="Processing">⚙️ Processing</option>
                                <option value="Called">📞 Called (Contacted)</option>
                                <option value="Parts Ordered">📦 Parts Ordered</option>
                                <option value="Parts Arrived">✅ Parts Arrived</option>
                                <option value="Scheduled">📅 Scheduled</option>
                                <option value="Completed">✔️ Completed</option>
                                <option value="Issue">⚠️ Issue</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Phone Number</label>
                            <input id="input-phone" type="text" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none" placeholder="599 00 00 00">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Service Date & Time</label>
                            <input id="input-service-date" type="datetime-local" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Franchise (₾)</label>
                            <input id="input-franchise" type="text" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none" placeholder="e.g. 300">
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <h4 class="font-bold text-slate-700 mb-2 text-sm flex items-center gap-2">
                                <i data-lucide="info" class="w-4 h-4"></i> Transfer Info
                            </h4>
                            <div class="space-y-2 text-sm text-slate-600">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Amount:</span>
                                    <span class="font-mono font-bold" id="modal-amount">0 ₾</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Received:</span>
                                    <span class="text-xs" id="modal-date">-</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Internal Notes</label>
                            <textarea id="input-notes" rows="4" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none resize-none" placeholder="Add notes here..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Customer Response Section -->
                <div id="modal-response-section" class="mt-5 bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <h4 class="font-bold text-blue-900 mb-2 text-sm flex items-center gap-2">
                        <i data-lucide="message-circle" class="w-4 h-4"></i> Customer Response
                    </h4>
                    <p class="text-sm text-blue-700" id="modal-user-response">Not responded yet</p>
                </div>

                <!-- Reschedule Request Section -->
                <div id="modal-reschedule-section" class="hidden mt-5 bg-orange-50 border border-orange-200 rounded-xl p-4">
                    <h4 class="font-bold text-orange-900 mb-3 text-sm flex items-center gap-2">
                        <i data-lucide="calendar-clock" class="w-4 h-4"></i> Reschedule Request
                    </h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-start gap-2">
                            <span class="text-orange-600 font-semibold">Requested Date:</span>
                            <span class="text-orange-900 font-mono" id="modal-reschedule-date">-</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-orange-600 font-semibold">Comment:</span>
                            <span class="text-orange-900" id="modal-reschedule-comment">-</span>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button onclick="window.acceptReschedule()" class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold flex items-center justify-center gap-2 transition-all">
                            <i data-lucide="check" class="w-4 h-4"></i> Accept & Update
                        </button>
                        <button onclick="window.declineReschedule()" class="flex-1 bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg font-semibold flex items-center justify-center gap-2 transition-all">
                            <i data-lucide="x" class="w-4 h-4"></i> Decline
                        </button>
                    </div>
                </div>

                <!-- Reviews Section -->
                <div id="modal-reviews-section" class="hidden mt-5 bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                    <h4 class="font-bold text-yellow-900 mb-3 text-sm flex items-center gap-2">
                        <i data-lucide="star" class="w-4 h-4"></i> Customer Review
                    </h4>
                    <div class="flex items-center gap-2 mb-2">
                        <div id="modal-review-stars" class="flex gap-1"></div>
                        <span id="modal-review-rating" class="text-sm font-bold text-yellow-700"></span>
                    </div>
                    <p class="text-sm text-yellow-800" id="modal-review-comment">No comment provided</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-slate-50 px-6 py-4 flex justify-between items-center border-t border-slate-100">
                <button type="button" onclick="window.closeModal()" class="px-4 py-2 text-slate-600 hover:bg-white rounded-lg transition-colors border border-slate-200">Cancel</button>
                <button type="button" onclick="window.saveEdit()" class="px-6 py-2.5 bg-slate-900 text-white hover:bg-slate-800 rounded-xl font-semibold text-sm shadow-lg shadow-slate-900/20 transition-all active:scale-95 flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>
