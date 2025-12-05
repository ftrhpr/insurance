/**
 * Vehicles Management Module
 * Handles vehicle database CRUD operations
 */

window.loadVehicles = async function() {
    try {
        const data = await fetchAPI('get_vehicles', 'GET');
        vehicles = data.vehicles || [];
        renderVehiclesTable();
    } catch (err) {
        console.error('Error loading vehicles:', err);
    }
}

function renderVehiclesTable() {
    const tbody = document.getElementById('vehicles-table-body');
    if (!tbody) return;
    
    if (vehicles.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                    <i data-lucide="car" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                    <p>No vehicles registered yet</p>
                </td>
            </tr>
        `;
        initLucide();
        return;
    }
    
    tbody.innerHTML = vehicles.map(v => {
        // Find service history for this plate
        const serviceHistory = transfers.filter(t => 
            normalizePlate(t.plate) === normalizePlate(v.plate)
        ).length;
        
        const lastService = transfers
            .filter(t => normalizePlate(t.plate) === normalizePlate(v.plate))
            .sort((a, b) => new Date(b.date) - new Date(a.date))[0];
        
        const lastServiceDate = lastService ? 
            new Date(lastService.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 
            'Never';
        
        return `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-mono font-bold text-sm bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-lg inline-block shadow-sm">
                        ${v.plate}
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-semibold text-slate-800">${v.ownerName || '-'}</div>
                    ${v.phone ? `<div class="text-xs text-slate-500 flex items-center gap-1 mt-0.5"><i data-lucide="phone" class="w-3 h-3"></i> ${v.phone}</div>` : ''}
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">${v.model || '-'}</td>
                <td class="px-6 py-4">
                    <div class="text-sm text-slate-700">${serviceHistory} service${serviceHistory !== 1 ? 's' : ''}</div>
                    <div class="text-xs text-slate-400">Last: ${lastServiceDate}</div>
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        ${CAN_EDIT ? `
                            <button onclick="window.openEditVehicleModal(${v.id})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Vehicle">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            <button onclick="window.deleteVehicle(${v.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete Vehicle">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        ` : `
                            <button onclick="window.viewVehicleHistory('${v.plate}')" class="p-2 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors" title="View History">
                                <i data-lucide="history" class="w-4 h-4"></i>
                            </button>
                        `}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    initLucide();
}

window.openAddVehicleModal = function() {
    document.getElementById('veh-id').value = '';
    document.getElementById('veh-plate').value = '';
    document.getElementById('veh-plate').disabled = false;
    document.getElementById('veh-owner').value = '';
    document.getElementById('veh-phone').value = '';
    document.getElementById('veh-model').value = '';
    document.getElementById('vehicle-modal').classList.remove('hidden');
    initLucide();
};

window.openEditVehicleModal = function(id) {
    const vehicle = vehicles.find(v => v.id === id);
    if (!vehicle) return;
    
    document.getElementById('veh-id').value = vehicle.id;
    document.getElementById('veh-plate').value = vehicle.plate;
    document.getElementById('veh-plate').disabled = true;
    document.getElementById('veh-owner').value = vehicle.ownerName || '';
    document.getElementById('veh-phone').value = vehicle.phone || '';
    document.getElementById('veh-model').value = vehicle.model || '';
    document.getElementById('vehicle-modal').classList.remove('hidden');
    initLucide();
};

window.closeVehicleModal = function() {
    document.getElementById('vehicle-modal').classList.add('hidden');
};

window.saveVehicle = async function() {
    const id = document.getElementById('veh-id').value;
    const plate = document.getElementById('veh-plate').value.trim().toUpperCase();
    const ownerName = document.getElementById('veh-owner').value.trim();
    const phone = document.getElementById('veh-phone').value.trim();
    const model = document.getElementById('veh-model').value.trim();
    
    if (!plate) {
        showToast('Validation Error', 'Plate number is required', 'error');
        return;
    }
    
    const data = { plate, ownerName, phone, model };
    
    try {
        const action = id ? `update_vehicle&id=${id}` : 'add_vehicle';
        await fetchAPI(action, 'POST', data);
        showToast('Success', id ? 'Vehicle updated' : 'Vehicle added', 'success');
        window.closeVehicleModal();
        loadVehicles();
    } catch (err) {
        showToast('Error', err.message, 'error');
    }
};

window.deleteVehicle = async function(id) {
    const vehicle = vehicles.find(v => v.id === id);
    if (!vehicle) return;
    
    if (!confirm(`Delete vehicle ${vehicle.plate}?`)) return;
    
    try {
        await fetchAPI(`delete_vehicle&id=${id}`, 'POST', {});
        showToast('Success', 'Vehicle deleted', 'success');
        loadVehicles();
    } catch (err) {
        showToast('Error', err.message, 'error');
    }
};

window.viewVehicleHistory = function(plate) {
    const history = transfers.filter(t => 
        normalizePlate(t.plate) === normalizePlate(plate)
    );
    
    if (history.length === 0) {
        showToast('No History', 'No service records found for this vehicle', 'info');
        return;
    }
    
    const historyHtml = history
        .sort((a, b) => new Date(b.date) - new Date(a.date))
        .map(t => {
            const date = new Date(t.date).toLocaleDateString();
            return `<li class="text-sm"><strong>${date}</strong>: ${t.status} - ${t.amount} ₾</li>`;
        })
        .join('');
    
    // You could open a modal here instead
    showToast('Service History', `${history.length} service(s) found`, 'info');
};
