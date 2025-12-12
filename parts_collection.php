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

        /* Searchable Dropdown Styles */
        .search-dropdown {
            position: relative;
        }

        .search-dropdown input {
            position: relative;
        }

        .search-dropdown .dropdown-arrow {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            transition: transform 0.2s;
        }

        .search-dropdown .dropdown-arrow.open {
            transform: translateY(-50%) rotate(180deg);
        }

        .dropdown-options {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            background: white;
        }

        .dropdown-option {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.15s;
        }

        .dropdown-option:hover {
            background-color: #f8fafc;
        }

        .dropdown-option:last-child {
            border-bottom: none;
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
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 gradient-accent rounded-xl flex items-center justify-center shadow-lg">
                            <i data-lucide="plus-circle" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Create New Parts Collection</h2>
                            <p class="text-xs text-gray-600">Select transfer and add parts</p>
                        </div>
                    </div>

                    <form id="collectionForm" class="space-y-4">


                        <!-- Transfer and Manager Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                                <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2 text-indigo-600"></i>
                                    Transfer Order
                                </label>
                                <div class="relative search-dropdown">
                                    <input type="text" id="transferSearch" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus focus:border-indigo-400 focus:ring-indigo-400 px-3 py-2 pr-10 text-sm text-gray-900" placeholder="Search transfers..." autocomplete="off">
                                    <div class="dropdown-arrow">
                                        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
                                    </div>
                                    <input type="hidden" id="transferSelect" name="transfer_id" required>
                                    <div id="transferDropdown" class="absolute z-10 w-full bg-white border-2 border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden dropdown-options">
                                        <div id="transferOptions" class="py-1">
                                            <!-- Options will be populated here -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                                <label class="block text-sm font-semibold text-gray-800 mb-2 flex items-center">
                                    <i data-lucide="user" class="w-4 h-4 mr-2 text-orange-600"></i>
                                    Manager (Optional)
                                </label>
                                <select id="assignedManager" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus focus:border-orange-400 focus:ring-orange-400 px-3 py-2 text-sm text-gray-900">
                                    <option value="">Unassigned</option>
                                    <!-- Managers will be loaded here -->
                                </select>
                            </div>
                        </div>

                        <!-- Parts Section -->
                        <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-sm font-semibold text-gray-800 flex items-center">
                                    <i data-lucide="package" class="w-4 h-4 mr-2 text-purple-600"></i>
                                    Parts List
                                </label>
                                <button type="button" onclick="addPart()" class="inline-flex items-center px-3 py-1.5 border-2 border-dashed border-indigo-300 rounded-lg text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 transition-all duration-200">
                                    <i data-lucide="plus" class="w-3 h-3 mr-1"></i>
                                    Add Part
                                </button>
                            </div>
                            <div id="partsList" class="space-y-2 mb-3">
                                <!-- Parts will be added here -->
                            </div>
                        </div>
                        
                        <!-- Labor Section -->
                        <div class="bg-white/50 rounded-xl p-4 border border-white/30">
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-sm font-semibold text-gray-800 flex items-center">
                                    <i data-lucide="wrench" class="w-4 h-4 mr-2 text-sky-600"></i>
                                    Labor & Services
                                </label>
                                <button type="button" onclick="addLabor()" class="inline-flex items-center px-3 py-1.5 border-2 border-dashed border-sky-300 rounded-lg text-xs font-medium text-sky-600 bg-sky-50 hover:bg-sky-100 hover:border-sky-400 transition-all duration-200">
                                    <i data-lucide="plus" class="w-3 h-3 mr-1"></i>
                                    Add Labor
                                </button>
                            </div>
                            <div id="laborList" class="space-y-2 mb-3">
                                <!-- Labor items will be added here -->
                            </div>
                        </div>

                        <!-- Totals Section -->
                        <div id="createTotals" class="mt-4 pt-4 border-t-2 border-dashed border-gray-200/80 flex justify-end items-center space-x-6">
                            <div class="text-sm font-semibold text-gray-700">
                                Total Items: <span id="createTotalItems" class="text-gray-900">0</span>
                            </div>
                            <div class="text-lg font-bold text-gray-800">
                                Total Price: <span class="gradient-text" id="createTotalPrice">₾0.00</span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-3 pt-3 border-t border-gray-200">
                            <button type="button" onclick="clearForm()" class="px-4 py-2 border-2 border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                <i data-lucide="x" class="w-4 h-4 mr-1 inline"></i>
                                Clear
                            </button>
                            <button type="submit" class="btn-gradient px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i data-lucide="save" class="w-4 h-4 mr-1 inline"></i>
                                Create
                            </button>
                        </div>
                    </form>
                </div>

        <!-- Collections List -->
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

    <script>
        let transfers = [];
        let collections = [];
        let partSuggestions = [];
        let laborSuggestions = [];
        let managers = [];

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTransfers();
            loadCollections();
            loadPartSuggestions();
            loadLaborSuggestions();
            loadManagers();
            lucide.createIcons();

            // Initialize searchable transfer dropdown
            initTransferSearch();
        });

        // Initialize transfer search functionality
        function initTransferSearch() {
            const searchInput = document.getElementById('transferSearch');
            const dropdown = document.getElementById('transferDropdown');

            if (!searchInput || !dropdown) return;

            // Focus event - show dropdown
            searchInput.addEventListener('focus', () => {
                updateTransferDropdown(searchInput.value);
                toggleTransferDropdown(true);
            });

            // Input event - filter results
            searchInput.addEventListener('input', (e) => {
                updateTransferDropdown(e.target.value);
                toggleTransferDropdown(true);
            });

            // Click outside to close dropdown
            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                    toggleTransferDropdown(false);
                }
            });

            // Keyboard navigation
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    toggleTransferDropdown(false);
                    searchInput.blur();
                }
            });
        }

        // Load transfers for dropdown
        async function loadTransfers() {
            try {
                const response = await fetch('api.php?action=get_transfers_for_parts');
                if (!response.ok) {
                    throw new Error(`API request failed: ${response.status} ${response.statusText}`);
                }
                const data = await response.json();
                
                // Add explicit logging
                console.log("API Response Data:", JSON.stringify(data, null, 2));

                if (!data || !Array.isArray(data.transfers)) {
                    console.error("API response is not in the expected format. Expected { transfers: [...] }.", data);
                    transfers = [];
                } else {
                    transfers = data.transfers;
                }
                
                console.log(`Loaded and assigned ${transfers.length} transfers.`);
                updateTransferDropdown(''); // Populate the dropdown initially
            } catch (error) {
                console.error('A critical error occurred in loadTransfers:', error);
                showToast('Could not load transfers due to a technical error.', 'error');
                transfers = []; // Reset on error
                updateTransferDropdown(''); // Show "no results"
            }
        }

        // Update transfer dropdown with search functionality
        function updateTransferDropdown(filter = '') {
            const optionsContainer = document.getElementById('transferOptions');
            if (!optionsContainer) {
                console.error('CRITICAL: The element with id "transferOptions" was not found in the HTML.');
                return;
            }

            optionsContainer.innerHTML = ''; // Clear previous results
            
            const lowercasedFilter = filter.toLowerCase();

            const filteredTransfers = transfers.filter(transfer => {
                const plate = (transfer && transfer.plate) ? transfer.plate : '';
                const name = (transfer && transfer.name) ? transfer.name : '';
                const status = (transfer && transfer.status) ? transfer.status : '';
                const searchText = `${plate} ${name} ${status}`.toLowerCase();
                return searchText.includes(lowercasedFilter);
            });

            if (filteredTransfers.length === 0) {
                optionsContainer.innerHTML = '<div class="dropdown-option text-center text-gray-500">No matching transfers found.</div>';
                return;
            }

            // More robust DOM creation
            filteredTransfers.forEach((transfer, index) => {
                const option = document.createElement('div');
                option.className = 'dropdown-option';
                option.setAttribute('data-id', transfer.id);

                const contentDiv = document.createElement('div');
                const boldSpan = document.createElement('span');
                boldSpan.className = 'font-bold';
                boldSpan.textContent = transfer.plate || 'NO PLATE';
                contentDiv.appendChild(boldSpan);
                contentDiv.append(` - ${transfer.name || 'NO NAME'}`);

                const statusDiv = document.createElement('div');
                statusDiv.className = 'text-xs text-gray-500';
                statusDiv.textContent = transfer.status || 'NO STATUS';

                option.appendChild(contentDiv);
                option.appendChild(statusDiv);
                
                option.addEventListener('click', () => {
                    document.getElementById('transferSearch').value = `${transfer.plate || ''} - ${transfer.name || ''}`;
                    document.getElementById('transferSelect').value = transfer.id;
                    toggleTransferDropdown(false);
                });
                
                // Final diagnostic log
                console.log(`Appending option ${index + 1}/${filteredTransfers.length} to the DOM:`, option);
                optionsContainer.appendChild(option);
            });
        }

        // Toggle transfer dropdown visibility
        function toggleTransferDropdown(show) {
            const dropdown = document.getElementById('transferDropdown');
            // Select the container of the arrow, not the icon itself, to avoid issues with Lucide's SVG replacement
            const arrowContainer = document.querySelector('.search-dropdown .dropdown-arrow');

            if (!dropdown || !arrowContainer) {
                console.error('Could not find dropdown or arrow container element for toggling.');
                return;
            }

            dropdown.classList.toggle('hidden', !show);
            arrowContainer.classList.toggle('open', show);
        }

        // Load collections from the server
        async function loadCollections() {
            try {
                const response = await fetch('api.php?action=get_parts_collections');
                const data = await response.json();
                if (data.success) {
                    collections = data.collections || [];
                    // Ensure parts_list is parsed from JSON string to array
                    collections.forEach(c => {
                        if (typeof c.parts_list === 'string') {
                            try {
                                c.parts_list = JSON.parse(c.parts_list);
                            } catch (e) {
                                console.error('Error parsing parts_list for collection:', c.id, e);
                                c.parts_list = []; // Default to empty array on parse error
                            }
                        }
                    });
                    renderCollections();
                } else {
                    showToast(data.error || 'Could not load collections', 'error');
                }
            } catch (error) {
                console.error('Error loading collections:', error);
                showToast('Error loading collections', 'error');
            }
        }
        
        // Load part suggestions for autocompletion
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

        // Load managers
        async function loadManagers() {
            try {
                const response = await fetch('api.php?action=get_managers');
                const data = await response.json();
                if (data.managers) {
                    managers = data.managers;
                    const managerSelects = document.querySelectorAll('#assignedManager, #editAssignedManager');
                    managerSelects.forEach(select => {
                        // Clear existing options except the first one
                        while (select.options.length > 1) {
                            select.remove(1);
                        }
                        managers.forEach(manager => {
                            const option = new Option(`${manager.full_name} (${manager.username})`, manager.id);
                            select.add(option);
                        });
                    });
                }
            } catch (error) {
                console.error('Error loading managers:', error);
            }
        }

        // Add part to the main collection form
        function addPart(name = '', quantity = 1, price = 0) {
            const partsList = document.getElementById('partsList');
            const partDiv = document.createElement('div');
            partDiv.className = 'part-item bg-white/40 rounded-lg p-3 border border-white/30 backdrop-blur-sm';
            
            partDiv.innerHTML = `
                <div class="grid grid-cols-12 gap-x-3 items-end">
                    <div class="col-span-7">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Part Name</label>
                        <div class="relative">
                            <input type="text" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900 part-name" value="${name}" placeholder="Enter part name..." required autocomplete="off">
                            <div class="autocomplete-results absolute z-10 w-full bg-white border border-gray-300 rounded-md mt-1 hidden shadow-lg max-h-48 overflow-y-auto"></div>
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Qty</label>
                        <input type="number" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900 text-center part-quantity" value="${quantity}" min="1" required oninput="updateCreateFormTotals()">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Price</label>
                        <input type="number" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900 part-price" value="${price}" step="0.01" min="0" placeholder="0.00" required oninput="updateCreateFormTotals()">
                    </div>
                    <div class="col-span-1 flex items-end">
                        <button type="button" onclick="removePart(this)" class="px-2 py-2 border-2 border-red-300 rounded-lg text-red-600 hover:bg-red-50 hover:border-red-400 transition-all duration-200 shadow-sm w-full flex justify-center">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `;
            partsList.appendChild(partDiv);

            setupAutocomplete(partDiv.querySelector('.part-name'), 'part');
            
            lucide.createIcons();
            updateCreateFormTotals();
        }

        // Add labor to the main collection form
        function addLabor(name = '', quantity = 1, price = 0) {
            const laborList = document.getElementById('laborList');
            const laborDiv = document.createElement('div');
            laborDiv.className = 'labor-item bg-white/40 rounded-lg p-3 border border-white/30 backdrop-blur-sm';
            
            laborDiv.innerHTML = `
                <div class="grid grid-cols-12 gap-x-3 items-end">
                    <div class="col-span-7">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Service Name</label>
                         <div class="relative">
                            <input type="text" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900 labor-name" value="${name}" placeholder="Enter service name..." required autocomplete="off">
                            <div class="autocomplete-results absolute z-10 w-full bg-white border border-gray-300 rounded-md mt-1 hidden shadow-lg max-h-48 overflow-y-auto"></div>
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Qty</label>
                        <input type="number" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900 text-center labor-quantity" value="${quantity}" min="1" required oninput="updateCreateFormTotals()">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Price</label>
                        <input type="number" class="block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm text-gray-900 labor-price" value="${price}" step="0.01" min="0" placeholder="0.00" required oninput="updateCreateFormTotals()">
                    </div>
                    <div class="col-span-1 flex items-end">
                        <button type="button" onclick="removePart(this)" class="px-2 py-2 border-2 border-red-300 rounded-lg text-red-600 hover:bg-red-50 hover:border-red-400 transition-all duration-200 shadow-sm w-full flex justify-center">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `;
            laborList.appendChild(laborDiv);

            setupAutocomplete(laborDiv.querySelector('.labor-name'), 'labor');

            lucide.createIcons();
            updateCreateFormTotals();
        }

        // Remove part from the main form
        function removePart(button) {
            button.closest('.part-item, .labor-item').remove();
            updateCreateFormTotals();
        }

        // Update totals in the main create form
        function updateCreateFormTotals() {
            const partItems = document.querySelectorAll('#partsList .part-item, #laborList .labor-item');
            let totalItems = 0;
            let totalPrice = 0;
            partItems.forEach(item => {
                const quantityInput = item.querySelector('.part-quantity, .labor-quantity');
                const priceInput = item.querySelector('.part-price, .labor-price');
                const quantity = parseInt(quantityInput?.value || 0);
                const price = parseFloat(priceInput?.value || 0);
                totalItems += quantity;
                totalPrice += quantity * price;
            });

            const totalItemsEl = document.getElementById('createTotalItems');
            const totalPriceEl = document.getElementById('createTotalPrice');

            if (totalItemsEl) totalItemsEl.textContent = totalItems;
            if (totalPriceEl) totalPriceEl.textContent = `₾${totalPrice.toFixed(2)}`;
        }

        // Handle form submission for creating a new collection
        document.getElementById('collectionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const transferId = document.getElementById('transferSelect').value;
            const assignedManager = document.getElementById('assignedManager').value;

            if (!transferId) {
                showToast('Please select a transfer order.', 'error');
                return;
            }

            const parts = [];
            document.querySelectorAll('#partsList .part-item').forEach(item => {
                const name = item.querySelector('.part-name').value.trim();
                const quantity = parseInt(item.querySelector('.part-quantity').value);
                const price = parseFloat(item.querySelector('.part-price').value);
                if (name && quantity > 0 && price >= 0) {
                    parts.push({ name, quantity, price, type: 'part' });
                }
            });

            document.querySelectorAll('#laborList .labor-item').forEach(item => {
                const name = item.querySelector('.labor-name').value.trim();
                const quantity = parseInt(item.querySelector('.labor-quantity').value);
                const price = parseFloat(item.querySelector('.labor-price').value);
                if (name && quantity > 0 && price >= 0) {
                    parts.push({ name, quantity, price, type: 'labor' });
                }
            });

            if (parts.length === 0) {
                showToast('Please add at least one part or service.', 'error');
                return;
            }

            try {
                const response = await fetch('api.php?action=create_parts_collection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transfer_id: transferId,
                        parts_list: parts,
                        assigned_manager_id: assignedManager || null
                    })
                });
                const result = await response.json();
                if (result.success) {
                    showToast('Parts collection created successfully!', 'success');
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

        // Clear the main collection form
        function clearForm() {
            document.getElementById('collectionForm').reset();
            document.getElementById('partsList').innerHTML = '';
            document.getElementById('laborList').innerHTML = '';
            document.getElementById('transferSearch').value = '';
            document.getElementById('transferSelect').value = '';
            updateCreateFormTotals();
        }

        // --- AUTOCOMPLETE ---
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

        // Render collections in a card-based layout
        function renderCollections() {
            const container = document.getElementById('collectionsTable');
            if (!container) return;

            if (collections.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <div class="mx-auto w-24 h-24 gradient-accent rounded-full flex items-center justify-center float-animation">
                            <i data-lucide="package-search" class="w-12 h-12 text-white"></i>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-800">No collections yet</h3>
                        <p class="mt-1 text-sm text-gray-600">Create a new collection to get started.</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';

            collections.forEach(collection => {
                const statusColors = {
                    pending: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: 'hourglass' },
                    collected: { bg: 'bg-green-100', text: 'text-green-800', icon: 'check-circle' },
                    cancelled: { bg: 'bg-red-100', text: 'text-red-800', icon: 'x-circle' }
                };
                const statusInfo = statusColors[collection.status] || { bg: 'bg-gray-100', text: 'text-gray-800', icon: 'help-circle' };
                
                const managerName = collection.manager_full_name || 'Unassigned';
                const totalItems = collection.parts_list.reduce((sum, item) => sum + parseInt(item.quantity, 10), 0);
                const totalPrice = collection.parts_list.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.quantity, 10)), 0);

                html += `
                    <div class="glass-card rounded-2xl shadow-lg card-hover border border-white/30 overflow-hidden">
                        <div class="p-5">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs text-gray-500">#${collection.id}</p>
                                    <p class="font-bold text-gray-800">${collection.transfer_plate} - ${collection.transfer_name}</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusInfo.bg} ${statusInfo.text}">
                                        <i data-lucide="${statusInfo.icon}" class="w-3 h-3 mr-1"></i>
                                        ${collection.status.charAt(0).toUpperCase() + collection.status.slice(1)}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 space-y-3 text-sm">
                                <div class="flex items-center text-gray-700">
                                    <i data-lucide="user-cog" class="w-4 h-4 mr-2 text-orange-500"></i>
                                    Manager: <span class="font-semibold ml-1">${managerName}</span>
                                </div>
                                <div class="flex items-center text-gray-700">
                                    <i data-lucide="package" class="w-4 h-4 mr-2 text-purple-500"></i>
                                    Items: <span class="font-semibold ml-1">${totalItems}</span>
                                </div>
                                <div class="flex items-center text-gray-700">
                                    <i data-lucide="receipt" class="w-4 h-4 mr-2 text-green-500"></i>
                                    Total: <span class="font-bold ml-1">₾${totalPrice.toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50/50 px-5 py-3 flex justify-end space-x-2">
                            <button onclick="window.location.href='edit_collection.php?id=${collection.id}'" class="inline-flex items-center px-3 py-1.5 border-2 border-transparent rounded-lg text-xs font-medium text-indigo-600 bg-indigo-100 hover:bg-indigo-200 transition-all duration-200">
                                <i data-lucide="edit" class="w-3 h-3 mr-1"></i> Edit
                            </button>
                            <button onclick="deleteCollection(${collection.id})" class="inline-flex items-center px-3 py-1.5 border-2 border-transparent rounded-lg text-xs font-medium text-red-600 bg-red-100 hover:bg-red-200 transition-all duration-200">
                                <i data-lucide="trash-2" class="w-3 h-3 mr-1"></i> Delete
                            </button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
            lucide.createIcons();
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