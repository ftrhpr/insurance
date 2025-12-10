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
    <title>Parts Collection - OTOMOTORS Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <button onclick="window.location.href='index.php'" class="text-gray-600 hover:text-gray-900">
                            <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        </button>
                        <h1 class="text-2xl font-bold text-gray-900">Parts Collection</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                        <button onclick="window.location.href='logout.php'" class="text-red-600 hover:text-red-800 text-sm">Logout</button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <!-- Create Collection Form -->
                <div class="bg-white shadow rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Create New Parts Collection</h2>
                    
                    <form id="collectionForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Select Transfer Order</label>
                            <select id="transferSelect" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Choose a transfer...</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Parts List</label>
                            <div id="partsList" class="space-y-2">
                                <!-- Parts will be added here -->
                            </div>
                            <button type="button" onclick="addPart()" class="mt-2 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                Add Part
                            </button>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="clearForm()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Clear
                            </button>
                            <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Create Collection
                            </button>
                        </div>
            </form>
        </div>

        <!-- Part Suggestions Datalist -->
        <datalist id="partSuggestions"></datalist>                <!-- Collections List -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Existing Collections</h3>
                        <div id="collectionsTable">
                            <!-- Collections will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Parts Collection</h3>
                            <form id="editForm" class="space-y-4">
                                <input type="hidden" id="editId">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Transfer</label>
                                    <input type="text" id="editTransfer" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" readonly>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Parts List</label>
                                    <div id="editPartsList" class="space-y-2">
                                        <!-- Parts will be added here -->
                                    </div>
                                    <button type="button" onclick="addEditPart()" class="mt-2 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                        Add Part
                                    </button>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <select id="editStatus" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="pending">Pending</option>
                                        <option value="collected">Collected</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="saveEdit()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Save Changes
                    </button>
                    <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let transfers = [];
        let collections = [];
        let partSuggestions = [];
        let currentParts = [];

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTransfers();
            loadCollections();
            loadPartSuggestions();
            lucide.createIcons();
        });

        // Load transfers for dropdown
        async function loadTransfers() {
            try {
                const response = await fetch('api.php?action=get_transfers');
                const data = await response.json();
                transfers = data.transfers || [];
                
                const select = document.getElementById('transferSelect');
                select.innerHTML = '<option value="">Choose a transfer...</option>';
                
                transfers.forEach(transfer => {
                    const option = document.createElement('option');
                    option.value = transfer.id;
                    option.textContent = `${transfer.plate} - ${transfer.name} (${transfer.status})`;
                    select.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading transfers:', error);
                showToast('Error loading transfers', 'error');
            }
        }

        // Load part name suggestions
        async function loadPartSuggestions() {
            try {
                const response = await fetch('api.php?action=get_parts_suggestions');
                const data = await response.json();
                partSuggestions = data.suggestions || [];
                
                // Update existing datalist
                updateDatalist();
            } catch (error) {
                console.error('Error loading part suggestions:', error);
            }
        }

        // Update datalist with suggestions
        function updateDatalist() {
            const datalist = document.getElementById('partSuggestions');
            if (!datalist) return;
            
            datalist.innerHTML = '';
            partSuggestions.forEach(suggestion => {
                const option = document.createElement('option');
                option.value = suggestion;
                datalist.appendChild(option);
            });
        }

        // Load collections
        async function loadCollections() {
            try {
                const response = await fetch('api.php?action=get_parts_collections');
                const data = await response.json();
                collections = data.collections || [];
                renderCollections();
            } catch (error) {
                console.error('Error loading collections:', error);
                showToast('Error loading collections', 'error');
            }
        }

        // Render collections table
        function renderCollections() {
            const container = document.getElementById('collectionsTable');
            
            if (collections.length === 0) {
                container.innerHTML = '<p class="text-gray-500">No parts collections found.</p>';
                return;
            }

            let html = `
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Parts Count</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;

            collections.forEach(collection => {
                const parts = JSON.parse(collection.parts_list || '[]');
                const statusColors = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'collected': 'bg-green-100 text-green-800',
                    'cancelled': 'bg-red-100 text-red-800'
                };

                html += `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            ${collection.plate} - ${collection.name}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ${parts.length}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            $${parseFloat(collection.total_cost || 0).toFixed(2)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[collection.status] || 'bg-gray-100 text-gray-800'}">
                                ${collection.status}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ${new Date(collection.created_at).toLocaleDateString()}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editCollection(${collection.id})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                            <button onclick="deleteCollection(${collection.id})" class="text-red-600 hover:text-red-900">Delete</button>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            container.innerHTML = html;
            lucide.createIcons();
        }

        // Add part to form
        function addPart(name = '', quantity = 1, price = 0) {
            const partsList = document.getElementById('partsList');
            const partDiv = document.createElement('div');
            partDiv.className = 'flex space-x-2 items-end part-item';
            partDiv.innerHTML = `
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Part Name</label>
                    <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 part-name" value="${name}" list="partSuggestions" required>
                </div>
                <div class="w-24">
                    <label class="block text-sm font-medium text-gray-700">Qty</label>
                    <input type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 part-quantity" value="${quantity}" min="1" required>
                </div>
                <div class="w-32">
                    <label class="block text-sm font-medium text-gray-700">Price</label>
                    <input type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 part-price" value="${price}" step="0.01" min="0" required>
                </div>
                <button type="button" onclick="removePart(this)" class="mb-1 px-2 py-1 border border-gray-300 rounded-md text-red-600 hover:bg-red-50">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            `;
            partsList.appendChild(partDiv);
            lucide.createIcons();
        }

        // Remove part from form
        function removePart(button) {
            button.closest('.part-item').remove();
        }

        // Clear form
        function clearForm() {
            document.getElementById('transferSelect').value = '';
            document.getElementById('partsList').innerHTML = '';
            currentParts = [];
        }

        // Submit collection form
        document.getElementById('collectionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const transferId = document.getElementById('transferSelect').value;
            if (!transferId) {
                showToast('Please select a transfer', 'error');
                return;
            }

            const parts = [];
            const partItems = document.querySelectorAll('.part-item');
            
            if (partItems.length === 0) {
                showToast('Please add at least one part', 'error');
                return;
            }

            partItems.forEach(item => {
                const name = item.querySelector('.part-name').value.trim();
                const quantity = parseInt(item.querySelector('.part-quantity').value);
                const price = parseFloat(item.querySelector('.part-price').value);
                
                if (name && quantity > 0 && price >= 0) {
                    parts.push({ name, quantity, price });
                }
            });

            if (parts.length === 0) {
                showToast('Please fill in all part details', 'error');
                return;
            }

            try {
                const response = await fetch('api.php?action=create_parts_collection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ transfer_id: transferId, parts_list: parts })
                });
                
                const result = await response.json();
                if (result.success) {
                    showToast('Parts collection created successfully', 'success');
                    clearForm();
                    loadCollections();
                } else {
                    showToast(result.error || 'Error creating collection', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error creating collection', 'error');
            }
        });

        // Edit collection
        function editCollection(id) {
            const collection = collections.find(c => c.id == id);
            if (!collection) return;

            document.getElementById('editId').value = id;
            document.getElementById('editTransfer').value = `${collection.plate} - ${collection.name}`;
            document.getElementById('editStatus').value = collection.status;

            const parts = JSON.parse(collection.parts_list || '[]');
            const editPartsList = document.getElementById('editPartsList');
            editPartsList.innerHTML = '';

            parts.forEach(part => {
                addEditPart(part.name, part.quantity, part.price);
            });

            document.getElementById('editModal').classList.add('active');
        }

        // Add part to edit form
        function addEditPart(name = '', quantity = 1, price = 0) {
            const editPartsList = document.getElementById('editPartsList');
            const partDiv = document.createElement('div');
            partDiv.className = 'flex space-x-2 items-end part-item';
            partDiv.innerHTML = `
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Part Name</label>
                    <input type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 part-name" value="${name}" list="partSuggestions" required>
                </div>
                <div class="w-24">
                    <label class="block text-sm font-medium text-gray-700">Qty</label>
                    <input type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 part-quantity" value="${quantity}" min="1" required>
                </div>
                <div class="w-32">
                    <label class="block text-sm font-medium text-gray-700">Price</label>
                    <input type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 part-price" value="${price}" step="0.01" min="0" required>
                </div>
                <button type="button" onclick="removeEditPart(this)" class="mb-1 px-2 py-1 border border-gray-300 rounded-md text-red-600 hover:bg-red-50">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            `;
            editPartsList.appendChild(partDiv);
            lucide.createIcons();
        }

        // Remove part from edit form
        function removeEditPart(button) {
            button.closest('.part-item').remove();
        }

        // Save edit
        async function saveEdit() {
            const id = document.getElementById('editId').value;
            const status = document.getElementById('editStatus').value;
            
            const parts = [];
            const partItems = document.querySelectorAll('#editPartsList .part-item');
            
            partItems.forEach(item => {
                const name = item.querySelector('.part-name').value.trim();
                const quantity = parseInt(item.querySelector('.part-quantity').value);
                const price = parseFloat(item.querySelector('.part-price').value);
                
                if (name && quantity > 0 && price >= 0) {
                    parts.push({ name, quantity, price });
                }
            });

            if (parts.length === 0) {
                showToast('Please add at least one part', 'error');
                return;
            }

            try {
                const response = await fetch('api.php?action=update_parts_collection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, parts_list: parts, status })
                });
                
                const result = await response.json();
                if (result.success) {
                    showToast('Parts collection updated successfully', 'success');
                    closeModal();
                    loadCollections();
                } else {
                    showToast(result.error || 'Error updating collection', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error updating collection', 'error');
            }
        }

        // Delete collection
        async function deleteCollection(id) {
            if (!confirm('Are you sure you want to delete this parts collection?')) return;

            try {
                const response = await fetch('api.php?action=delete_parts_collection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                
                const result = await response.json();
                if (result.success) {
                    showToast('Parts collection deleted successfully', 'success');
                    loadCollections();
                } else {
                    showToast(result.error || 'Error deleting collection', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error deleting collection', 'error');
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Toast notification
        function showToast(message, type = 'info') {
            // Simple toast implementation
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-2 rounded-md text-white z-50 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>
</html>