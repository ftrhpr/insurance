<?php
require_once 'session_config.php';
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Parts Collection - OTOMOTORS Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.9); }
        .gradient-accent { background: linear-gradient(135deg, #0ea5e9 0%, #d946ef 100%); }
        .gradient-text { background: linear-gradient(135deg, #0284c7 0%, #c026d3 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .btn-gradient { background: linear-gradient(135deg, #0ea5e9 0%, #d946ef 100%); transition: all 0.3s; }
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3); }
        .input-focus:focus { box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1); border-color: #0ea5e9; }
        .search-dropdown { position: relative; }
        .dropdown-options { max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); background: white; }
        .dropdown-option { padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; }
        .dropdown-option:hover { background-color: #f8fafc; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen pb-24">
        <!-- Header -->
        <header class="glass-card shadow-lg border-b border-white/20 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <a href="parts_collection.php" class="text-gray-600 hover:text-gray-700 p-2 rounded-lg hover:bg-white/50">
                            <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        </a>
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 gradient-accent rounded-xl flex items-center justify-center shadow-lg">
                                <i data-lucide="plus-circle" class="w-6 h-6 text-white"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold gradient-text">Create Collection</h1>
                                <p class="text-xs text-gray-600">Select transfer and add parts/labor</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <form id="collectionForm" class="space-y-6">
                <!-- General Info -->
                <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">General Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                            <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                                <i data-lucide="file-text" class="w-4 h-4 mr-2 text-indigo-600"></i>
                                Transfer Order
                            </label>
                            <div class="relative search-dropdown">
                                <input type="text" id="transferSearch" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 pr-10 text-sm text-gray-900" placeholder="Search transfers..." autocomplete="off">
                                <div class="dropdown-arrow">
                                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
                                </div>
                                <input type="hidden" id="transferSelect" name="transfer_id" required>
                                <div id="transferDropdown" class="absolute z-10 w-full bg-white border-2 border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden dropdown-options">
                                    <div id="transferOptions" class="py-1"></div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                            <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                                <i data-lucide="user" class="w-4 h-4 mr-2 text-orange-600"></i>
                                Manager (Optional)
                            </label>
                            <select id="assignedManager" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900">
                                <option value="">Unassigned</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Parts Section -->
                <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                     <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center"><i data-lucide="package" class="w-5 h-5 mr-2 text-purple-600"></i>Parts List</h2>
                        <button type="button" onclick="addPart()" class="inline-flex items-center px-3 py-1.5 border-2 border-dashed border-indigo-300 rounded-lg text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100">
                            <i data-lucide="plus" class="w-3 h-3 mr-1"></i> Add Part
                        </button>
                    </div>
                    <div id="partsList" class="space-y-2"></div>
                </div>

                <!-- Labor Section -->
                <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                     <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center"><i data-lucide="wrench" class="w-5 h-5 mr-2 text-sky-600"></i>Labor & Services</h2>
                        <button type="button" onclick="addLabor()" class="inline-flex items-center px-3 py-1.5 border-2 border-dashed border-sky-300 rounded-lg text-xs font-medium text-sky-600 bg-sky-50 hover:bg-sky-100">
                            <i data-lucide="plus" class="w-3 h-3 mr-1"></i> Add Labor
                        </button>
                    </div>
                    <div id="laborList" class="space-y-2"></div>
                </div>
            </form>
        </main>
    </div>

    <!-- Floating Action Bar -->
    <div class="fixed bottom-0 left-0 right-0 z-50">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="glass-card shadow-2xl rounded-t-3xl p-4 border-t border-x border-white/20 flex justify-between items-center">
                <div id="createTotals" class="flex items-center space-x-6">
                    <div class="text-sm font-semibold text-gray-700">Total Items: <span id="createTotalItems" class="text-gray-900">0</span></div>
                    <div class="text-lg font-bold text-gray-800">Total Price: <span class="gradient-text" id="createTotalPrice">₾0.00</span></div>
                </div>
                <div class="flex space-x-3">
                    <a href="parts_collection.php" class="px-4 py-2 border-2 border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                    <button type="submit" form="collectionForm" class="btn-gradient px-4 py-2 text-white rounded-lg shadow-md">
                        <i data-lucide="save" class="w-4 h-4 mr-1 inline"></i> Create Collection
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let transfers = [], managers = [], partSuggestions = [], laborSuggestions = [];

        document.addEventListener('DOMContentLoaded', function() {
            Promise.all([
                loadTransfers(),
                loadManagers(),
                loadPartSuggestions(),
                loadLaborSuggestions()
            ]).then(() => {
                initTransferSearch();
                lucide.createIcons();
            });
            document.getElementById('collectionForm').addEventListener('submit', createCollection);
        });

        function initTransferSearch() {
            const searchInput = document.getElementById('transferSearch');
            const dropdown = document.getElementById('transferDropdown');
            searchInput.addEventListener('focus', () => {
                updateTransferDropdown(searchInput.value);
                toggleTransferDropdown(true);
            });
            searchInput.addEventListener('input', (e) => {
                updateTransferDropdown(e.target.value);
                toggleTransferDropdown(true);
            });
            document.addEventListener('click', (e) => {
                if (!searchInput.parentElement.contains(e.target)) {
                    toggleTransferDropdown(false);
                }
            });
        }
        
        function toggleTransferDropdown(show) {
            const dropdown = document.getElementById('transferDropdown');
            const arrowContainer = document.querySelector('.search-dropdown .dropdown-arrow');
            if (dropdown && arrowContainer) {
                dropdown.classList.toggle('hidden', !show);
                arrowContainer.classList.toggle('open', show);
            }
        }

        async function loadData(url, key, callback) {
            try {
                const response = await fetch(url);
                const data = await response.json();
                window[key] = data[key] || data.suggestions || [];
                if(callback) callback();
            } catch (error) {
                console.error(`Error loading ${key}:`, error);
            }
        }

        function loadTransfers() { loadData('api.php?action=get_transfers_for_parts', 'transfers', () => updateTransferDropdown('')); }
        function loadManagers() { 
            loadData('api.php?action=get_managers', 'managers', () => {
                const managerSelect = document.getElementById('assignedManager');
                managers.forEach(m => managerSelect.add(new Option(`${m.full_name} (${m.username})`, m.id)));
            });
        }
        function loadPartSuggestions() { loadData('api.php?action=get_item_suggestions&type=part', 'partSuggestions'); }
        function loadLaborSuggestions() { loadData('api.php?action=get_item_suggestions&type=labor', 'laborSuggestions'); }

        function updateTransferDropdown(filter = '') {
            const container = document.getElementById('transferOptions');
            container.innerHTML = '';
            const filtered = transfers.filter(t => `${t.plate || ''} ${t.name || ''}`.toLowerCase().includes(filter.toLowerCase()));
            if(filtered.length === 0) {
                container.innerHTML = '<div class="dropdown-option text-center text-gray-500">No transfers found.</div>';
                return;
            }
            filtered.forEach(t => {
                const option = document.createElement('div');
                option.className = 'dropdown-option';
                option.innerHTML = `<div><span class="font-bold">${t.plate}</span> - ${t.name}</div><div class="text-xs text-gray-500">${t.status}</div>`;
                option.onclick = () => {
                    document.getElementById('transferSearch').value = `${t.plate} - ${t.name}`;
                    document.getElementById('transferSelect').value = t.id;
                    toggleTransferDropdown(false);
                };
                container.appendChild(option);
            });
        }

        function addItem(type) {
            const container = document.getElementById(type === 'part' ? 'partsList' : 'laborList');
            const itemDiv = document.createElement('div');
            itemDiv.className = `${type}-item bg-white/40 rounded-lg p-3 border border-white/30`;
            const placeholder = type === 'part' ? 'Part Name' : 'Service Name';
            itemDiv.innerHTML = `
                <div class="grid grid-cols-12 gap-x-3 items-end">
                    <div class="col-span-7">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">${placeholder}</label>
                        <div class="relative">
                            <input type="text" class="${type}-name block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm" placeholder="Enter name..." autocomplete="off">
                            <div class="autocomplete-results absolute z-10 w-full bg-white border border-gray-300 rounded-md mt-1 hidden shadow-lg max-h-48 overflow-y-auto"></div>
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Qty</label>
                        <input type="number" class="${type}-quantity block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-center" value="1" min="1" oninput="updateTotals()">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Price</label>
                        <input type="number" class="${type}-price block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm" value="0" step="0.01" min="0" oninput="updateTotals()">
                    </div>
                    <div class="col-span-1 flex items-end">
                        <button type="button" onclick="removeItem(this)" class="px-2 py-2 border-2 border-red-300 rounded-lg text-red-600 hover:bg-red-50 w-full flex justify-center"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </div>
                </div>
            `;
            container.appendChild(itemDiv);
            setupAutocomplete(itemDiv.querySelector(`.${type}-name`), type);
            lucide.createIcons();
            updateTotals();
        }

        function addPart() { addItem('part'); }
        function addLabor() { addItem('labor'); }
        function removeItem(button) { 
            button.closest('.part-item, .labor-item').remove();
            updateTotals();
        }

        function updateTotals() {
            let totalItems = 0, totalPrice = 0;
            document.querySelectorAll('.part-item, .labor-item').forEach(row => {
                const qty = parseInt(row.querySelector('.part-quantity, .labor-quantity').value) || 0;
                const price = parseFloat(row.querySelector('.part-price, .labor-price').value) || 0;
                totalItems += qty;
                totalPrice += qty * price;
            });
            document.getElementById('createTotalItems').textContent = totalItems;
            document.getElementById('createTotalPrice').textContent = `₾${totalPrice.toFixed(2)}`;
        }

        function setupAutocomplete(input, type) {
            const results = input.nextElementSibling;
            const suggestions = type === 'part' ? partSuggestions : laborSuggestions;
            input.addEventListener('input', () => {
                const val = input.value.toLowerCase();
                results.innerHTML = '';
                if (!val) { results.classList.add('hidden'); return; }
                const filtered = suggestions.filter(s => s.toLowerCase().includes(val));
                if (filtered.length) {
                    results.classList.remove('hidden');
                    filtered.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'p-2 hover:bg-gray-100 cursor-pointer text-sm';
                        div.textContent = item;
                        div.onclick = () => { input.value = item; results.classList.add('hidden'); };
                        results.appendChild(div);
                    });
                } else {
                    results.classList.add('hidden');
                }
            });
            document.addEventListener('click', e => { if (!input.parentElement.contains(e.target)) results.classList.add('hidden'); });
        }
        
        async function createCollection(e) {
            e.preventDefault();
            const transferId = document.getElementById('transferSelect').value;
            if (!transferId) { showToast('Please select a transfer.', 'error'); return; }
            
            const items = [];
            document.querySelectorAll('.part-item, .labor-item').forEach(row => {
                const type = row.classList.contains('part-item') ? 'part' : 'labor';
                const name = row.querySelector(`.${type}-name`).value;
                if(name) {
                    items.push({
                        name: name,
                        quantity: parseInt(row.querySelector(`.${type}-quantity`).value),
                        price: parseFloat(row.querySelector(`.${type}-price`).value),
                        type
                    });
                }
            });

            if (items.length === 0) { showToast('Please add at least one item.', 'error'); return; }

            const response = await fetch('api.php?action=create_parts_collection', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    transfer_id: transferId,
                    parts_list: items,
                    assigned_manager_id: document.getElementById('assignedManager').value || null
                })
            });
            const result = await response.json();
            if (result.success) {
                showToast('Collection created successfully!', 'success');
                setTimeout(() => window.location.href = 'parts_collection.php', 1000);
            } else {
                showToast(result.error || 'Error creating collection.', 'error');
            }
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-2 rounded-md text-white z-50 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>
</html>
