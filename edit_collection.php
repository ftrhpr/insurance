<?php
require_once 'session_config.php';
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$collection_id = $_GET['id'] ?? null;
if (!$collection_id) {
    header('Location: parts_collection.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Parts Collection - OTOMOTORS Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.9);
        }
        .gradient-header {
            background: linear-gradient(135deg, #0284c7 0%, #c026d3 100%);
        }
        .gradient-accent {
            background: linear-gradient(135deg, #0ea5e9 0%, #d946ef 100%);
        }
        .gradient-text {
            background: linear-gradient(135deg, #0284c7 0%, #c026d3 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #0ea5e9 0%, #d946ef 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3), 0 4px 6px -2px rgba(217, 70, 239, 0.2);
        }
        .input-focus {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            border-color: #0ea5e9;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen pb-24">
        <!-- Header -->
        <header class="glass-card shadow-lg border-b border-white/20 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <a href="parts_collection.php" class="text-gray-600 hover:text-gray-700 transition-colors duration-200 p-2 rounded-lg hover:bg-white/50">
                            <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        </a>
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 gradient-accent rounded-xl flex items-center justify-center shadow-lg">
                                <i data-lucide="edit-3" class="w-6 h-6 text-white"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold gradient-text">Edit Collection</h1>
                                <p id="editTransferInfo" class="text-xs text-gray-600">Loading collection data...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <form id="editForm" class="space-y-6">
                <input type="hidden" id="editId" value="<?php echo htmlspecialchars($collection_id); ?>">
                
                <!-- General Info -->
                <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">General Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                            <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                                <i data-lucide="user" class="w-4 h-4 mr-2 text-orange-600"></i>
                                Assigned Manager
                            </label>
                            <select id="editAssignedManager" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus focus:border-orange-400 focus:ring-orange-400 px-3 py-2 text-sm text-gray-900">
                                <option value="">Unassigned</option>
                            </select>
                        </div>
                        <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                            <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                                <i data-lucide="activity" class="w-4 h-4 mr-2 text-blue-600"></i>
                                Status
                            </label>
                            <select id="editStatus" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus focus:border-blue-400 focus:ring-blue-400 px-3 py-2 text-sm text-gray-900">
                                <option value="pending">Pending</option>
                                <option value="collected">Collected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Parts Section -->
                <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center">
                            <i data-lucide="package" class="w-5 h-5 mr-2 text-purple-600"></i>
                            Parts List
                        </h2>
                        <button type="button" onclick="addPart()" class="inline-flex items-center px-3 py-1.5 border-2 border-dashed border-indigo-300 rounded-lg text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 transition-all duration-200">
                            <i data-lucide="plus" class="w-3 h-3 mr-1"></i> Add Part
                        </button>
                    </div>
                    <div id="editPartsList" class="space-y-2"></div>
                </div>

                <!-- Labor Section -->
                <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center">
                            <i data-lucide="wrench" class="w-5 h-5 mr-2 text-sky-600"></i>
                            Labor & Services
                        </h2>
                        <button type="button" onclick="addLabor()" class="inline-flex items-center px-3 py-1.5 border-2 border-dashed border-sky-300 rounded-lg text-xs font-medium text-sky-600 bg-sky-50 hover:bg-sky-100 hover:border-sky-400 transition-all duration-200">
                            <i data-lucide="plus" class="w-3 h-3 mr-1"></i> Add Labor
                        </button>
                    </div>
                    <div id="editLaborList" class="space-y-2"></div>
                </div>
            </form>
        </main>
    </div>

    <!-- Floating Action Bar -->
    <div class="fixed bottom-0 left-0 right-0 z-50">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="glass-card shadow-2xl rounded-t-3xl p-4 border-t border-x border-white/20 flex justify-between items-center">
                <div id="editTotals" class="flex items-center space-x-6">
                    <div class="text-sm font-semibold text-gray-700">Total Items: <span id="editTotalItems" class="text-gray-900">0</span></div>
                    <div class="text-lg font-bold text-gray-800">Total Price: <span class="gradient-text" id="editTotalPrice">₾0.00</span></div>
                </div>
                <div class="flex space-x-3">
                    <a href="parts_collection.php" class="px-4 py-2 border-2 border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">Cancel</a>
                    <button type="submit" form="editForm" class="btn-gradient px-4 py-2 text-white rounded-lg shadow-md">
                        <i data-lucide="save" class="w-4 h-4 mr-1 inline"></i>
                        Update Collection
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadManagers();
            loadCollectionData();
            lucide.createIcons();
            document.getElementById('editForm').addEventListener('submit', saveEdit);
        });

        async function loadManagers() {
            // This function should be copied from parts_collection.php or refactored into a shared JS file
            try {
                const response = await fetch('api.php?action=get_managers');
                const data = await response.json();
                if (data.managers) {
                    const managerSelect = document.getElementById('editAssignedManager');
                    data.managers.forEach(manager => {
                        const option = new Option(`${manager.full_name} (${manager.username})`, manager.id);
                        managerSelect.add(option);
                    });
                }
            } catch (error) {
                console.error('Error loading managers:', error);
            }
        }

        async function loadCollectionData() {
            const id = document.getElementById('editId').value;
            try {
                // Also load suggestions
                await Promise.all([loadPartSuggestions(), loadLaborSuggestions()]);

                const response = await fetch(`api.php?action=get_parts_collections`);
                const data = await response.json();
                const collection = data.collections.find(c => c.id == id);

                if (collection) {
                    document.getElementById('editTransferInfo').textContent = `For: ${collection.transfer_plate} - ${collection.transfer_name}`;
                    document.getElementById('editStatus').value = collection.status;
                    document.getElementById('editAssignedManager').value = collection.assigned_manager_id || "";

                    const partsList = JSON.parse(collection.parts_list || '[]');
                    partsList.forEach(item => {
                        if (item.type === 'labor') {
                            addLabor(item.name, item.quantity, item.price);
                        } else {
                            addPart(item.name, item.quantity, item.price);
                        }
                    });
                    updateTotals();
                } else {
                    showToast('Collection not found.', 'error');
                }
            } catch (error) {
                console.error('Error loading collection data:', error);
                showToast('Error loading collection data.', 'error');
            }
        }

        function addPart(name = '', quantity = 1, price = 0) {
            const container = document.getElementById('editPartsList');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'part-item bg-white/40 rounded-lg p-3 border border-white/30 backdrop-blur-sm';
            itemDiv.innerHTML = `
                <div class="grid grid-cols-12 gap-x-3 items-end">
                    <div class="col-span-7">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Part Name</label>
                        <div class="relative">
                            <input type="text" class="part-name block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900" value="${name}" placeholder="Enter part name..." autocomplete="off">
                            <div class="autocomplete-results absolute z-10 w-full bg-white border border-gray-300 rounded-md mt-1 hidden shadow-lg max-h-48 overflow-y-auto"></div>
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Qty</label>
                        <input type="number" class="part-quantity block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-center" value="${quantity}" min="1" oninput="updateTotals()">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Price</label>
                        <input type="number" class="part-price block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm" value="${price}" step="0.01" min="0" oninput="updateTotals()">
                    </div>
                    <div class="col-span-1 flex items-end">
                        <button type="button" onclick="removeItem(this)" class="px-2 py-2 border-2 border-red-300 rounded-lg text-red-600 hover:bg-red-50 hover:border-red-400 transition-all duration-200 shadow-sm w-full flex justify-center">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(itemDiv);
            setupAutocomplete(itemDiv.querySelector('.part-name'), 'part');
            lucide.createIcons();
            updateTotals();
        }

        function addLabor(name = '', quantity = 1, price = 0) {
            const container = document.getElementById('editLaborList');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'labor-item bg-white/40 rounded-lg p-3 border border-white/30 backdrop-blur-sm';
            itemDiv.innerHTML = `
                <div class="grid grid-cols-12 gap-x-3 items-end">
                    <div class="col-span-7">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Service Name</label>
                        <div class="relative">
                            <input type="text" class="labor-name block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm" value="${name}" placeholder="Enter service name..." autocomplete="off">
                            <div class="autocomplete-results absolute z-10 w-full bg-white border border-gray-300 rounded-md mt-1 hidden shadow-lg max-h-48 overflow-y-auto"></div>
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Qty</label>
                        <input type="number" class="labor-quantity block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-center" value="${quantity}" min="1" oninput="updateTotals()">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Price</label>
                        <input type="number" class="labor-price block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm" value="${price}" step="0.01" min="0" oninput="updateTotals()">
                    </div>
                    <div class="col-span-1 flex items-end">
                        <button type="button" onclick="removeItem(this)" class="px-2 py-2 border-2 border-red-300 rounded-lg text-red-600 hover:bg-red-50 hover:border-red-400 transition-all duration-200 shadow-sm w-full flex justify-center">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(itemDiv);
            setupAutocomplete(itemDiv.querySelector('.labor-name'), 'labor');
            lucide.createIcons();
            updateTotals();
        }

        function removeItem(button) {
            button.closest('.part-item, .labor-item').remove();
            updateTotals();
        }

        function updateTotals() {
            let totalItems = 0;
            let totalPrice = 0;
            document.querySelectorAll('.part-item, .labor-item').forEach(item => {
                const quantity = parseInt(item.querySelector('.part-quantity, .labor-quantity')?.value || 0);
                const price = parseFloat(item.querySelector('.part-price, .labor-price')?.value || 0);
                totalItems += quantity;
                totalPrice += quantity * price;
            });
            document.getElementById('editTotalItems').textContent = totalItems;
            document.getElementById('editTotalPrice').textContent = `₾${totalPrice.toFixed(2)}`;
        }

        let partSuggestions = [];
        let laborSuggestions = [];

        async function loadPartSuggestions() {
            try {
                const response = await fetch('api.php?action=get_item_suggestions&type=part');
                const data = await response.json();
                partSuggestions = data.suggestions || [];
            } catch (error) {
                console.error('Error loading part suggestions:', error);
            }
        }

        async function loadLaborSuggestions() {
            try {
                const response = await fetch('api.php?action=get_item_suggestions&type=labor');
                const data = await response.json();
                laborSuggestions = data.suggestions || [];
            } catch (error) {
                console.error('Error loading labor suggestions:', error);
            }
        }

        function setupAutocomplete(inputElement, type) {
            const resultsContainer = inputElement.nextElementSibling;
            const suggestions = type === 'part' ? partSuggestions : laborSuggestions;

            inputElement.addEventListener('input', () => {
                const value = inputElement.value.toLowerCase();
                resultsContainer.innerHTML = '';
                if (!value) {
                    resultsContainer.classList.add('hidden');
                    return;
                }

                const filtered = suggestions.filter(item => item.toLowerCase().includes(value));

                if (filtered.length) {
                    resultsContainer.classList.remove('hidden');
                    filtered.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'p-2 hover:bg-gray-100 cursor-pointer';
                        div.textContent = item;
                        div.addEventListener('click', () => {
                            inputElement.value = item;
                            resultsContainer.classList.add('hidden');
                        });
                        resultsContainer.appendChild(div);
                    });
                } else {
                    resultsContainer.classList.add('hidden');
                }
            });

            document.addEventListener('click', (e) => {
                if (e.target !== inputElement) {
                    resultsContainer.classList.add('hidden');
                }
            });
        }

        async function saveEdit(e) {
            e.preventDefault();
            const id = document.getElementById('editId').value;
            const status = document.getElementById('editStatus').value;
            const assigned_manager_id = document.getElementById('editAssignedManager').value;

            const items = [];
            document.querySelectorAll('#editPartsList .part-item').forEach(item => {
                items.push({
                    name: item.querySelector('.part-name').value,
                    quantity: parseInt(item.querySelector('.part-quantity').value),
                    price: parseFloat(item.querySelector('.part-price').value),
                    type: 'part'
                });
            });
            document.querySelectorAll('#editLaborList .labor-item').forEach(item => {
                items.push({
                    name: item.querySelector('.labor-name').value,
                    quantity: parseInt(item.querySelector('.labor-quantity').value),
                    price: parseFloat(item.querySelector('.labor-price').value),
                    type: 'labor'
                });
            });

            try {
                const response = await fetch('api.php?action=update_parts_collection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, parts_list: items, status, assigned_manager_id })
                });
                const result = await response.json();
                if (result.success) {
                    window.location.href = 'parts_collection.php';
                } else {
                    showToast(result.error || 'Error updating collection', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error updating collection', 'error');
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
