<!-- REVIEWS VIEW -->
<div id="view-reviews" class="hidden space-y-6 animate-in fade-in duration-300">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Customer Reviews</h2>
            <p class="text-sm text-slate-500 mt-1">Moderate and manage customer feedback</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="bg-gradient-to-br from-yellow-400 to-orange-500 text-white px-6 py-3 rounded-xl shadow-lg">
                <div class="text-xs font-semibold opacity-90">Average Rating</div>
                <div class="text-3xl font-bold flex items-center gap-2">
                    <span id="avg-rating">0.0</span>
                    <i data-lucide="star" class="w-6 h-6 fill-current"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Buttons -->
    <?php if (canEdit()): ?>
    <div class="flex gap-2">
        <button onclick="window.filterReviews('all')" data-filter="all" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold transition-colors">
            All Reviews
        </button>
        <button onclick="window.filterReviews('pending')" data-filter="pending" class="px-4 py-2 text-slate-600 hover:bg-slate-50 rounded-lg text-sm font-semibold transition-colors">
            Pending
        </button>
        <button onclick="window.filterReviews('approved')" data-filter="approved" class="px-4 py-2 text-slate-600 hover:bg-slate-50 rounded-lg text-sm font-semibold transition-colors">
            Approved
        </button>
        <button onclick="window.filterReviews('rejected')" data-filter="rejected" class="px-4 py-2 text-slate-600 hover:bg-slate-50 rounded-lg text-sm font-semibold transition-colors">
            Rejected
        </button>
    </div>
    <?php endif; ?>

    <!-- Reviews Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Comment</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="reviews-table-body" class="divide-y divide-slate-100">
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                            <i data-lucide="loader" class="w-8 h-8 mx-auto mb-2 animate-spin"></i>
                            <p>Loading reviews...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
