<!-- Vehicle Modal -->
<div id="vehicle-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="window.closeVehicleModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200">
            <div class="bg-slate-900 px-6 py-4 rounded-t-2xl flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/10 p-2 rounded-lg">
                        <i data-lucide="car" class="w-5 h-5 text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white">Vehicle Information</h3>
                </div>
                <button onclick="window.closeVehicleModal()" class="text-white/80 hover:text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-6 bg-gradient-to-br from-slate-50 to-white">
                <div class="bg-white p-4 rounded-xl border border-slate-200 mb-4 flex items-center gap-4">
                    <div class="bg-slate-100 p-3 rounded-xl">
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
            </div>

            <div class="flex gap-3 justify-end px-6 pb-6">
                <button onclick="window.closeVehicleModal()" class="px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">Cancel</button>
                <button onclick="window.saveVehicle()" class="px-6 py-2.5 bg-slate-900 text-white hover:bg-slate-800 rounded-lg font-semibold shadow-lg transition-all">Save Vehicle</button>
            </div>
        </div>
    </div>
</div>
