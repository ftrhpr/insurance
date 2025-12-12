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
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            border-color: #0ea5e9;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="glass-card shadow-lg border-b border-white/20 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <button onclick="window.location.href='parts_collection.php'" class="text-gray-600 hover:text-gray-700 transition-colors duration-200 p-2 rounded-lg hover:bg-white/50">
                            <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        </button>
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 gradient-accent rounded-xl flex items-center justify-center shadow-lg">
                                <i data-lucide="edit-3" class="w-6 h-6 text-white"></i>
                            </div>
                            <h1 class="text-2xl font-bold gradient-text">Edit Collection</h1>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <form id="editForm" class="space-y-4">
                    <input type="hidden" id="editId" value="<?php echo htmlspecialchars($collection_id); ?>">
                    
                    <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                        <div id="editTransferInfo" class="mb-4 text-lg font-bold text-gray-800"></div>

                        <!-- Manager and Status Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                                <label class="block text-sm font-semibold text-gray-800 mb-2">Assigned Manager</label>
                                <select id="editAssignedManager" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900">
                                    <option value="">Unassigned</option>
                                </select>
                            </div>

                            <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                                <label class="block text-sm font-semibold text-gray-800 mb-2">Status</label>
                                <select id="editStatus" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900">
                                    <option value="pending">Pending</option>
                                    <option value="collected">Collected</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <!-- Parts Section -->
                        <div class="bg-white/50 rounded-xl p-4 border border-white/30 mt-4">
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-sm font-semibold text-gray-800">Parts List</label>
                                <button type="button" onclick="addPart()" class="text-xs font-medium text-indigo-600">Add Part</button>
                            </div>
                            <div id="editPartsList" class="space-y-2"></div>
                        </div>

                        <!-- Labor Section -->
                        <div class="bg-white/50 rounded-xl p-4 border border-white/30 mt-4">
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-sm font-semibold text-gray-800">Labor & Services</label>
                                <button type="button" onclick="addLabor()" class="text-xs font-medium text-sky-600">Add Labor</button>
                            </div>
                            <div id="editLaborList" class="space-y-2"></div>
                        </div>

                        <!-- Totals & Actions -->
                        <div class="mt-6 pt-4 border-t-2 flex justify-between items-center">
                            <div id="editTotals" class="flex items-center space-x-6">
                                <div class="text-sm font-semibold">Total Items: <span id="editTotalItems">0</span></div>
                                <div class="text-lg font-bold">Total Price: <span class="gradient-text" id="editTotalPrice">₾0.00</span></div>
                            </div>
                            <div class="flex space-x-3">
                                <button type="button" onclick="window.location.href='parts_collection.php'" class="px-4 py-2 border-2 rounded-lg">Cancel</button>
                                <button type="submit" class="btn-gradient px-4 py-2 text-white rounded-lg">Update Collection</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
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
                const response = await fetch(`api.php?action=get_parts_collections`);
                const data = await response.json();
                const collection = data.collections.find(c => c.id == id);

                if (collection) {
                    document.getElementById('editTransferInfo').textContent = `Editing Collection for: ${collection.transfer_plate} - ${collection.transfer_name}`;
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
            itemDiv.className = 'part-item grid grid-cols-12 gap-x-3 items-end';
            itemDiv.innerHTML = `
                <div class="col-span-7"><input type="text" class="part-name w-full" value="${name}" placeholder="Part Name"></div>
                <div class="col-span-2"><input type="number" class="part-quantity w-full" value="${quantity}" min="1" oninput="updateTotals()"></div>
                <div class="col-span-2"><input type="number" class="part-price w-full" value="${price}" step="0.01" min="0" oninput="updateTotals()"></div>
                <div class="col-span-1"><button type="button" onclick="removeItem(this)">Remove</button></div>
            `;
            container.appendChild(itemDiv);
            updateTotals();
        }

        function addLabor(name = '', quantity = 1, price = 0) {
            const container = document.getElementById('editLaborList');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'labor-item grid grid-cols-12 gap-x-3 items-end';
            itemDiv.innerHTML = `
                <div class="col-span-7"><input type="text" class="labor-name w-full" value="${name}" placeholder="Service Name"></div>
                <div class="col-span-2"><input type="number" class="labor-quantity w-full" value="${quantity}" min="1" oninput="updateTotals()"></div>
                <div class="col-span-2"><input type="number" class="labor-price w-full" value="${price}" step="0.01" min="0" oninput="updateTotals()"></div>
                <div class="col-span-1"><button type="button" onclick="removeItem(this)">Remove</button></div>
            `;
            container.appendChild(itemDiv);
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
