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
                            <label class="block text-sm font-semibold text-gray-800 mb-2">Transfer Order</label>
                            <div class="relative search-dropdown">
                                <input type="text" id="transferSearch" class="block w-full rounded-lg border-2" placeholder="Search transfers...">
                                <input type="hidden" id="transferSelect" name="transfer_id" required>
                                <div id="transferDropdown" class="absolute z-10 w-full hidden dropdown-options">
                                    <div id="transferOptions" class="py-1"></div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                            <label class="block text-sm font-semibold text-gray-800 mb-2">Manager (Optional)</label>
                            <select id="assignedManager" class="block w-full rounded-lg border-2">
                                <option value="">Unassigned</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Parts Section -->
                <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                     <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-bold text-gray-900">Parts List</h2>
                        <button type="button" onclick="addPart()" class="text-xs font-medium text-indigo-600">Add Part</button>
                    </div>
                    <div id="partsList" class="space-y-2"></div>
                </div>

                <!-- Labor Section -->
                <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                     <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-bold text-gray-900">Labor & Services</h2>
                        <button type="button" onclick="addLabor()" class="text-xs font-medium text-sky-600">Add Labor</button>
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
                    <div class="text-sm font-semibold">Total Items: <span id="createTotalItems">0</span></div>
                    <div class="text-lg font-bold">Total Price: <span class="gradient-text" id="createTotalPrice">₾0.00</span></div>
                </div>
                <div class="flex space-x-3">
                    <a href="parts_collection.php" class="px-4 py-2 border-2 rounded-lg">Cancel</a>
                    <button type="submit" form="collectionForm" class="btn-gradient px-4 py-2 text-white rounded-lg">Create Collection</button>
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
            ]);
            initTransferSearch();
            lucide.createIcons();
            document.getElementById('collectionForm').addEventListener('submit', createCollection);
        });

        function initTransferSearch() {
            const searchInput = document.getElementById('transferSearch');
            const dropdown = document.getElementById('transferDropdown');
            searchInput.addEventListener('focus', () => updateTransferDropdown(searchInput.value));
            searchInput.addEventListener('input', () => updateTransferDropdown(searchInput.value));
            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target)) dropdown.classList.add('hidden');
                else dropdown.classList.remove('hidden');
            });
        }

        async function loadData(url, key) {
            try {
                const response = await fetch(url);
                const data = await response.json();
                window[key] = data[key] || data.suggestions || [];
            } catch (error) {
                console.error(`Error loading ${key}:`, error);
            }
        }

        async function loadTransfers() { await loadData('api.php?action=get_transfers_for_parts', 'transfers'); }
        async function loadManagers() { 
            await loadData('api.php?action=get_managers', 'managers');
            const managerSelect = document.getElementById('assignedManager');
            managers.forEach(m => managerSelect.add(new Option(`${m.full_name} (${m.username})`, m.id)));
        }
        async function loadPartSuggestions() { await loadData('api.php?action=get_item_suggestions&type=part', 'partSuggestions'); }
        async function loadLaborSuggestions() { await loadData('api.php?action=get_item_suggestions&type=labor', 'laborSuggestions'); }

        function updateTransferDropdown(filter = '') {
            const container = document.getElementById('transferOptions');
            container.innerHTML = '';
            const filtered = transfers.filter(t => `${t.plate} ${t.name}`.toLowerCase().includes(filter.toLowerCase()));
            filtered.forEach(t => {
                const div = document.createElement('div');
                div.className = 'dropdown-option';
                div.textContent = `${t.plate} - ${t.name}`;
                div.onclick = () => {
                    document.getElementById('transferSearch').value = `${t.plate} - ${t.name}`;
                    document.getElementById('transferSelect').value = t.id;
                    container.parentElement.classList.add('hidden');
                };
                container.appendChild(div);
            });
        }

        function addItem(type) {
            const containerId = type === 'part' ? 'partsList' : 'laborList';
            const container = document.getElementById(containerId);
            const itemDiv = document.createElement('div');
            itemDiv.className = `${type}-item grid grid-cols-12 gap-x-3 items-end`;
            itemDiv.innerHTML = `
                <div class="col-span-7 relative">
                    <input type="text" class="${type}-name w-full" placeholder="${type === 'part' ? 'Part' : 'Service'} Name" autocomplete="off">
                    <div class="autocomplete-results absolute z-10 w-full hidden"></div>
                </div>
                <div class="col-span-2"><input type="number" class="${type}-quantity w-full" value="1" min="1"></div>
                <div class="col-span-2"><input type="number" class="${type}-price w-full" value="0" step="0.01" min="0"></div>
                <div class="col-span-1"><button type="button" onclick="this.closest('.${type}-item').remove()">Remove</button></div>
            `;
            container.appendChild(itemDiv);
            setupAutocomplete(itemDiv.querySelector(`.${type}-name`), type);
        }

        function addPart() { addItem('part'); }
        function addLabor() { addItem('labor'); }

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
                        div.textContent = item;
                        div.onclick = () => { input.value = item; results.classList.add('hidden'); };
                        results.appendChild(div);
                    });
                } else {
                    results.classList.add('hidden');
                }
            });
            document.addEventListener('click', e => { if (e.target !== input) results.classList.add('hidden'); });
        }
        
        async function createCollection(e) {
            e.preventDefault();
            const transferId = document.getElementById('transferSelect').value;
            if (!transferId) { showToast('Please select a transfer.', 'error'); return; }
            
            const items = [];
            document.querySelectorAll('.part-item, .labor-item').forEach(row => {
                const type = row.classList.contains('part-item') ? 'part' : 'labor';
                items.push({
                    name: row.querySelector(`.${type}-name`).value,
                    quantity: parseInt(row.querySelector(`.${type}-quantity`).value),
                    price: parseFloat(row.querySelector(`.${type}-price`).value),
                    type
                });
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
                window.location.href = 'parts_collection.php';
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
