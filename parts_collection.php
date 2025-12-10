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
    <script>
        // Suppress Tailwind CDN warning for development
        console.log('Tailwind CSS loaded from CDN (development mode)');
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .modal { display: none; }
        .modal.active { display: flex; }

        /* Enhanced Theme Styles */
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

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04), 0 0 0 1px rgba(14, 165, 233, 0.1);
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

        .table-gradient {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f8fafc 100%);
        }

        .status-badge {
            background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
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
                        <button onclick="window.location.href='index.php'" class="text-gray-600 hover:text-gray-700 transition-colors duration-200 p-2 rounded-lg hover:bg-white/50">
                            <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        </button>
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 gradient-accent rounded-xl flex items-center justify-center shadow-lg">
                                <i data-lucide="package" class="w-6 h-6 text-white"></i>
                            </div>
                            <h1 class="text-2xl font-bold gradient-text">Parts Collection</h1>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <div class="w-8 h-8 gradient-accent rounded-full flex items-center justify-center shadow-md">
                                <i data-lucide="user" class="w-4 h-4 text-white"></i>
                            </div>
                            <span>Welcome, <span class="font-medium"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span></span>
                        </div>
                        <button onclick="window.location.href='logout.php'" class="text-red-500 hover:text-red-600 text-sm font-medium px-3 py-2 rounded-lg hover:bg-red-50 transition-all duration-200">
                            <i data-lucide="log-out" class="w-4 h-4 inline mr-1"></i>
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <!-- Create Collection Form -->
                <div class="glass-card shadow-xl rounded-3xl p-8 mb-8 card-hover border border-white/20">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-12 h-12 gradient-accent rounded-2xl flex items-center justify-center shadow-lg float-animation">
                            <i data-lucide="plus-circle" class="w-7 h-7 text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Create New Parts Collection</h2>
                            <p class="text-sm text-gray-600">Select a transfer order and add the required parts</p>
                        </div>
                    </div>

                    <form id="collectionForm" class="space-y-6">
                        <div class="bg-white/50 rounded-2xl p-6 border border-white/30">
                            <label class="block text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                <i data-lucide="file-text" class="w-4 h-4 mr-2 text-indigo-600"></i>
                                Select Transfer Order
                            </label>
                            <select id="transferSelect" class="mt-1 block w-full rounded-xl border-2 border-gray-200 bg-white/80 shadow-sm input-focus focus:border-indigo-400 focus:ring-indigo-400 px-4 py-3 text-gray-900" required>
                                <option value="">Choose a transfer order...</option>
                            </select>
                        </div>

                        <div class="bg-white/50 rounded-2xl p-6 border border-white/30">
                            <div class="flex items-center justify-between mb-4">
                                <label class="block text-sm font-semibold text-gray-800 flex items-center">
                                    <i data-lucide="package" class="w-4 h-4 mr-2 text-purple-600"></i>
                                    Parts List
                                </label>
                                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">Add multiple parts as needed</span>
                            </div>
                            <div id="partsList" class="space-y-3 mb-4">
                                <!-- Parts will be added here -->
                            </div>
                            <button type="button" onclick="addPart()" class="inline-flex items-center px-4 py-2 border-2 border-dashed border-indigo-300 rounded-xl text-sm font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 transition-all duration-200">
                                <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                Add Part
                            </button>
                        </div>

                        <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                            <button type="button" onclick="clearForm()" class="px-6 py-3 border-2 border-gray-300 rounded-xl shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                <i data-lucide="x" class="w-4 h-4 mr-2 inline"></i>
                                Clear Form
                            </button>
                            <button type="submit" class="btn-gradient px-6 py-3 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i data-lucide="save" class="w-4 h-4 mr-2 inline"></i>
                                Create Collection
                            </button>
                        </div>
                    </form>
                </div>

        <!-- Part Suggestions Datalist -->
        <datalist id="partSuggestions"></datalist>                <!-- Collections List -->
                <div class="glass-card shadow-xl rounded-3xl card-hover border border-white/20">
                    <div class="px-8 py-6 border-b border-gray-200/50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 gradient-accent rounded-xl flex items-center justify-center shadow-lg">
                                    <i data-lucide="list" class="w-5 h-5 text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900">Existing Collections</h3>
                                    <p class="text-sm text-gray-600">Manage and track all parts collections</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="status-badge px-3 py-1 rounded-full text-xs font-medium text-white shadow-md">
                                    <i data-lucide="activity" class="w-3 h-3 inline mr-1"></i>
                                    Live Updates
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-8">
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
        <div class="flex items-center justify-center min-h-screen p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gradient-to-br from-gray-900/80 to-purple-900/80 backdrop-blur-sm transition-opacity"></div>

            <!-- Modal Content -->
            <div class="relative bg-white rounded-3xl shadow-2xl transform transition-all max-w-2xl w-full mx-4 border border-white/20">
                <div class="gradient-header px-8 py-6 rounded-t-3xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                <i data-lucide="edit-3" class="w-6 h-6 text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Edit Parts Collection</h3>
                                <p class="text-white/80 text-sm">Modify collection details and parts</p>
                            </div>
                        </div>
                        <button onclick="closeModal()" class="text-white/80 hover:text-white transition-colors p-2 hover:bg-white/10 rounded-lg">
                            <i data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-slate-50 to-blue-50 px-8 py-6 rounded-b-3xl">
                    <form id="editForm" class="space-y-6">
                        <input type="hidden" id="editId">
                        <div class="bg-white/60 rounded-2xl p-6 border border-white/40 backdrop-blur-sm">
                            <label class="block text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                <i data-lucide="file-text" class="w-4 h-4 mr-2 text-indigo-600"></i>
                                Transfer Order
                            </label>
                            <div id="editTransferInfo" class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3 border">
                                <!-- Transfer info will be loaded here -->
                            </div>
                        </div>

                        <div class="bg-white/60 rounded-2xl p-6 border border-white/40 backdrop-blur-sm">
                            <div class="flex items-center justify-between mb-4">
                                <label class="block text-sm font-semibold text-gray-800 flex items-center">
                                    <i data-lucide="package" class="w-4 h-4 mr-2 text-purple-600"></i>
                                    Parts List
                                </label>
                                <button type="button" onclick="addEditPart()" class="inline-flex items-center px-3 py-2 border-2 border-dashed border-purple-300 rounded-lg text-sm font-medium text-purple-600 bg-purple-50 hover:bg-purple-100 transition-all duration-200">
                                    <i data-lucide="plus" class="w-4 h-4 mr-1"></i>
                                    Add Part
                                </button>
                            </div>
                            <div id="editPartsList" class="space-y-3">
                                <!-- Parts will be added here -->
                            </div>
                        </div>

                        <div class="bg-white/60 rounded-2xl p-6 border border-white/40 backdrop-blur-sm">
                            <label class="block text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                <i data-lucide="activity" class="w-4 h-4 mr-2 text-blue-600"></i>
                                Status
                            </label>
                            <select id="editStatus" class="block w-full rounded-xl border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-4 py-3 text-gray-900">
                                <option value="pending">Pending</option>
                                <option value="collected">Collected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200/50">
                            <button type="button" onclick="closeModal()" class="px-6 py-3 border-2 border-gray-300 rounded-xl shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                <i data-lucide="x" class="w-4 h-4 mr-2 inline"></i>
                                Cancel
                            </button>
                            <button type="submit" class="btn-gradient px-6 py-3 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i data-lucide="save" class="w-4 h-4 mr-2 inline"></i>
                                Update Collection
                            </button>
                        </div>
                    </form>
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
            console.log('DOM loaded, initializing parts collection page');

            // Ensure modal elements exist before proceeding
            const requiredElements = ['editModal', 'editId', 'editStatus', 'editForm', 'editTransferInfo', 'editPartsList'];
            const missingElements = requiredElements.filter(id => !document.getElementById(id));

            if (missingElements.length > 0) {
                console.error('Missing required elements:', missingElements);
                showToast('Error: Page not properly loaded', 'error');
                return;
            }

            console.log('All required elements found');

            loadTransfers();
            loadCollections();
            loadPartSuggestions();
            lucide.createIcons();

            // Add event listener for edit form
            const editForm = document.getElementById('editForm');
            editForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                await saveEdit();
            });
            console.log('Edit form event listener added');
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
                    <table class="min-w-full divide-y divide-gray-200/50">
                        <thead class="table-gradient">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300/50">
                                    <div class="flex items-center">
                                        <i data-lucide="file-text" class="w-4 h-4 mr-2 text-indigo-600"></i>
                                        Transfer Details
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300/50">
                                    <div class="flex items-center">
                                        <i data-lucide="package" class="w-4 h-4 mr-2 text-purple-600"></i>
                                        Parts Count
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300/50">
                                    <div class="flex items-center">
                                        <i data-lucide="dollar-sign" class="w-4 h-4 mr-2 text-green-600"></i>
                                        Total Cost
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300/50">
                                    <div class="flex items-center">
                                        <i data-lucide="activity" class="w-4 h-4 mr-2 text-blue-600"></i>
                                        Status
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300/50">
                                    <div class="flex items-center">
                                        <i data-lucide="calendar" class="w-4 h-4 mr-2 text-gray-600"></i>
                                        Created
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-b border-gray-300/50">
                                    <div class="flex items-center">
                                        <i data-lucide="settings" class="w-4 h-4 mr-2 text-gray-600"></i>
                                        Actions
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white/60 divide-y divide-gray-200/30">
            `;

            collections.forEach(collection => {
                const parts = JSON.parse(collection.parts_list || '[]');
                const statusStyles = {
                    'pending': 'bg-gradient-to-r from-yellow-400 to-orange-500 text-white shadow-md',
                    'collected': 'bg-gradient-to-r from-green-400 to-emerald-500 text-white shadow-md',
                    'cancelled': 'bg-gradient-to-r from-red-400 to-pink-500 text-white shadow-md'
                };

                html += `
                    <tr class="hover:bg-white/80 transition-colors duration-200">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 gradient-accent rounded-lg flex items-center justify-center shadow-md mr-3">
                                    <i data-lucide="car" class="w-4 h-4 text-white"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">${collection.plate}</div>
                                    <div class="text-sm text-gray-600">${collection.name}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-sm font-bold text-purple-600">${parts.length}</span>
                                </div>
                                <span class="text-sm text-gray-600">parts</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                    <i data-lucide="dollar-sign" class="w-4 h-4 text-green-600"></i>
                                </div>
                                <span class="text-sm font-semibold text-gray-900">$${parseFloat(collection.total_cost || 0).toFixed(2)}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold ${statusStyles[collection.status] || 'bg-gradient-to-r from-gray-400 to-gray-500 text-white shadow-md'}">
                                <i data-lucide="circle" class="w-2 h-2 mr-1 fill-current"></i>
                                ${collection.status}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <div class="flex items-center">
                                <i data-lucide="calendar" class="w-4 h-4 mr-2 text-gray-400"></i>
                                ${new Date(collection.created_at).toLocaleDateString()}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button onclick="editCollection(${collection.id})" class="inline-flex items-center px-3 py-2 border-2 border-indigo-300 rounded-lg text-sm font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 transition-all duration-200">
                                    <i data-lucide="edit-3" class="w-4 h-4 mr-1"></i>
                                    Edit
                                </button>
                                <button onclick="deleteCollection(${collection.id})" class="inline-flex items-center px-3 py-2 border-2 border-red-300 rounded-lg text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 hover:border-red-400 transition-all duration-200">
                                    <i data-lucide="trash-2" class="w-4 h-4 mr-1"></i>
                                    Delete
                                </button>
                            </div>
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
            partDiv.className = 'flex space-x-3 items-end part-item bg-white/40 rounded-xl p-4 border border-white/30 backdrop-blur-sm';
            partDiv.innerHTML = `
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                        <i data-lucide="tag" class="w-4 h-4 mr-2 text-indigo-600"></i>
                        Part Name
                    </label>
                    <input type="text" class="block w-full rounded-xl border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-4 py-3 text-gray-900 placeholder-gray-500" value="${name}" list="partSuggestions" placeholder="Enter part name..." required>
                </div>
                <div class="w-28">
                    <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                        <i data-lucide="hash" class="w-4 h-4 mr-2 text-purple-600"></i>
                        Qty
                    </label>
                    <input type="number" class="block w-full rounded-xl border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-4 py-3 text-gray-900 text-center" value="${quantity}" min="1" required>
                </div>
                <div class="w-36">
                    <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                        <i data-lucide="dollar-sign" class="w-4 h-4 mr-2 text-green-600"></i>
                        Price
                    </label>
                    <input type="number" class="block w-full rounded-xl border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-4 py-3 text-gray-900" value="${price}" step="0.01" min="0" placeholder="0.00" required>
                </div>
                <button type="button" onclick="removePart(this)" class="mb-1 px-3 py-3 border-2 border-red-300 rounded-xl text-red-600 hover:bg-red-50 hover:border-red-400 transition-all duration-200 shadow-sm">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
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
            console.log('editCollection called with id:', id);
            console.log('Available elements:', {
                editModal: document.getElementById('editModal'),
                editId: document.getElementById('editId'),
                editStatus: document.getElementById('editStatus'),
                editTransferInfo: document.getElementById('editTransferInfo'),
                editPartsList: document.getElementById('editPartsList')
            });

            const collection = collections.find(c => c.id == id);
            if (!collection) {
                console.error('Collection not found:', id);
                return;
            }

            // Set hidden ID
            const editIdElement = document.getElementById('editId');
            if (editIdElement) {
                editIdElement.value = id;
                console.log('Set editId to:', id);
            } else {
                console.error('editId element not found');
                return;
            }

            // Populate transfer info display
            const transferInfo = document.getElementById('editTransferInfo');
            if (transferInfo) {
                transferInfo.innerHTML = `
                    <div class="font-medium text-gray-900">${collection.plate} - ${collection.name}</div>
                    <div class="text-gray-600 mt-1">Status: <span class="capitalize">${collection.status}</span></div>
                `;
                console.log('Set transfer info');
            }

            // Set status
            const editStatusElement = document.getElementById('editStatus');
            if (editStatusElement) {
                editStatusElement.value = collection.status;
                console.log('Set status to:', collection.status);
            }

            // Load parts
            const parts = JSON.parse(collection.parts_list || '[]');
            const editPartsList = document.getElementById('editPartsList');
            if (editPartsList) {
                editPartsList.innerHTML = '';

                parts.forEach((part, index) => {
                    console.log('Adding part:', index, part);
                    addEditPart(part.name, part.quantity, part.price);
                });
            }

            // Show modal
            const modal = document.getElementById('editModal');
            if (modal) {
                modal.classList.add('active');
                console.log('Modal shown');
            }
        }

        // Add part to edit form
        function addEditPart(name = '', quantity = 1, price = 0) {
            const editPartsList = document.getElementById('editPartsList');
            const partDiv = document.createElement('div');
            partDiv.className = 'flex space-x-3 items-end part-item bg-white/40 rounded-xl p-4 border border-white/30 backdrop-blur-sm';
            partDiv.innerHTML = `
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                        <i data-lucide="tag" class="w-4 h-4 mr-2 text-indigo-600"></i>
                        Part Name
                    </label>
                    <input type="text" class="block w-full rounded-xl border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-4 py-3 text-gray-900 placeholder-gray-500 part-name" value="${name}" list="partSuggestions" placeholder="Enter part name..." required>
                </div>
                <div class="w-28">
                    <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                        <i data-lucide="hash" class="w-4 h-4 mr-2 text-purple-600"></i>
                        Qty
                    </label>
                    <input type="number" class="block w-full rounded-xl border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-4 py-3 text-gray-900 text-center part-quantity" value="${quantity}" min="1" required>
                </div>
                <div class="w-36">
                    <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                        <i data-lucide="dollar-sign" class="w-4 h-4 mr-2 text-green-600"></i>
                        Price
                    </label>
                    <input type="number" class="block w-full rounded-xl border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-4 py-3 text-gray-900 part-price" value="${price}" step="0.01" min="0" placeholder="0.00" required>
                </div>
                <button type="button" onclick="removeEditPart(this)" class="mb-1 px-3 py-3 border-2 border-red-300 rounded-xl text-red-600 hover:bg-red-50 hover:border-red-400 transition-all duration-200 shadow-sm">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
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
            const editIdElement = document.getElementById('editId');
            const editStatusElement = document.getElementById('editStatus');

            if (!editIdElement || !editStatusElement) {
                console.error('Required form elements not found');
                showToast('Error: Form elements not found', 'error');
                return;
            }

            const id = editIdElement.value;
            const status = editStatusElement.value;

            const parts = [];
            const partItems = document.querySelectorAll('#editPartsList .part-item');

            partItems.forEach((item, index) => {
                const nameInput = item.querySelector('.part-name');
                const quantityInput = item.querySelector('.part-quantity');
                const priceInput = item.querySelector('.part-price');

                if (!nameInput || !quantityInput || !priceInput) {
                    console.error(`Part item ${index} missing required inputs`);
                    return;
                }

                const name = nameInput.value.trim();
                const quantity = parseInt(quantityInput.value);
                const price = parseFloat(priceInput.value);

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
            const modal = document.getElementById('editModal');
            if (modal) {
                modal.classList.remove('active');
                // Reset form
                const editForm = document.getElementById('editForm');
                if (editForm) {
                    editForm.reset();
                }
                const editPartsList = document.getElementById('editPartsList');
                if (editPartsList) {
                    editPartsList.innerHTML = '';
                }
                const editTransferInfo = document.getElementById('editTransferInfo');
                if (editTransferInfo) {
                    editTransferInfo.innerHTML = '';
                }
            }
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