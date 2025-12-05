<!-- VEHICLES VIEW -->
<div id="view-vehicles" class="hidden space-y-6 animate-in fade-in duration-300">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Customer Database</h2>
            <p class="text-sm text-slate-500 mt-1">Manage vehicle registrations and service history</p>
        </div>
        <?php if (canEdit()): ?>
        <button onclick="window.openAddVehicleModal()" class="flex items-center gap-2 px-4 py-2.5 bg-slate-900 text-white rounded-lg font-semibold hover:bg-slate-800 transition-all shadow-lg hover:shadow-xl">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Add Vehicle
        </button>
        <?php endif; ?>
    </div>

    <!-- Vehicles Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Plate</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Owner</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Model</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Service History</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="vehicles-table-body" class="divide-y divide-slate-100">
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                            <i data-lucide="loader" class="w-8 h-8 mx-auto mb-2 animate-spin"></i>
                            <p>Loading vehicles...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
