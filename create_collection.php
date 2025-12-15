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
                                <h1 class="text-2xl font-bold gradient-text"><?php echo __('collection.create','Create Collection'); ?></h1>
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
                <!-- PDF Invoice Upload -->
                <div class="glass-card shadow-xl rounded-3xl p-8 border border-white/20">
                    <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i data-lucide="file-scan" class="w-5 h-5 mr-2 text-teal-600"></i>
                        Auto-Parse PDF Invoice
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <div class="md:col-span-2">
                             <label for="pdf-upload" class="block text-sm font-medium text-gray-700 mb-1">Upload an invoice to automatically add parts and labor.</label>
                            <input type="file" id="pdfInvoiceInput" accept="application/pdf" class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                        </div>
                        <div>
                            <button type="button" id="parsePdfBtn" class="w-full btn-gradient px-4 py-2 text-white rounded-lg shadow-md mt-4" disabled>
                                <i data-lucide="wand-2" class="w-4 h-4 mr-1 inline"></i>
                                Parse PDF
                            </button>
                        </div>
                    </div>
                    <div id="pdfParseStatus" class="text-xs text-gray-500 mt-2"></div>
                    <div id="parsedPartsPreview" class="mt-4"></div>
                </div>

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
                    <a href="parts_collection.php" class="px-4 py-2 border-2 border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><?php echo __('action.cancel','Cancel'); ?></a>
                    <button type="submit" form="collectionForm" class="btn-gradient px-4 py-2 text-white rounded-lg shadow-md">
                        <i data-lucide="save" class="w-4 h-4 mr-1 inline"></i> <?php echo __('collection.create','Create Collection'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Define global variables to hold the data
        let transfers = [];
        let managers = [];
        let partSuggestions = [];
        let laborSuggestions = [];

        /**
         * A generic and robust function to fetch data from the API.
         * @param {string} url - The API endpoint to fetch data from.
         * @param {string} key - The key in the response JSON that holds the data array.
         * @returns {Promise<Array>} A promise that resolves to the data array.
         */
        async function loadData(url, key) {
            try {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`API request failed for ${key} with status ${response.status}`);
                }
                const data = await response.json();
                // Ensure the response has the expected key and it's an array
                if (data && Array.isArray(data[key])) {
                    console.log(`Successfully loaded ${data[key].length} items for ${key}.`);
                    return data[key];
                } else if (data && Array.isArray(data.suggestions)) { // Handle suggestions endpoint format
                    console.log(`Successfully loaded ${data.suggestions.length} items for ${key}.`);
                    return data.suggestions;
                }
                else {
                    console.warn(`No items found for ${key} in API response.`, data);
                    return [];
                }
            } catch (error) {
                console.error(`Failed to load or parse data for ${key}:`, error);
                showToast(`Could not load ${key}.`, 'error');
                return []; // Return an empty array on failure to prevent further errors
            }
        }

        /**
         * Main function to initialize the page after the DOM is loaded.
         */
        document.addEventListener('DOMContentLoaded', async function() {
            console.log("DOM fully loaded. Starting data fetch...");

            // Fetch all required data in parallel and wait for all to complete
            [transfers, managers, partSuggestions, laborSuggestions] = await Promise.all([
                loadData('api.php?action=get_transfers_for_parts', 'transfers'),
                loadData('api.php?action=get_managers', 'managers'),
                loadData('api.php?action=get_item_suggestions&type=part', 'suggestions'),
                loadData('api.php?action=get_item_suggestions&type=labor', 'suggestions')
            ]);
            
            console.log("All data loading finished.");

            // Now that all data is guaranteed to be loaded, populate the UI
            populateManagers();
            updateTransferDropdown(''); // Initial population
            
            // Set up event listeners
            initTransferSearch();
            document.getElementById('collectionForm').addEventListener('submit', createCollection);
            initPdfParsing();

            // Initialize icons
            lucide.createIcons();
            console.log("Page initialization complete.");
        });

        /**
         * Populates the managers dropdown with the loaded data.
         */
        function populateManagers() {
            const managerSelect = document.getElementById('assignedManager');
            if (!managerSelect) {
                console.error("Manager select dropdown not found.");
                return;
            }
            if (managers && managers.length > 0) {
                managers.forEach(m => {
                    managerSelect.add(new Option(`${m.full_name} (${m.username})`, m.id));
                });
                console.log(`Populated ${managers.length} managers.`);
            } else {
                console.log("No managers to populate.");
            }
        }
        
        /**
         * Initializes the event listeners for the transfer search dropdown.
         */
        function initTransferSearch() {
            const searchInput = document.getElementById('transferSearch');
            if (!searchInput) {
                console.error("Transfer search input not found.");
                return;
            }
            searchInput.addEventListener('focus', () => toggleTransferDropdown(true));
            searchInput.addEventListener('input', (e) => updateTransferDropdown(e.target.value));
            document.addEventListener('click', (e) => {
                if (!searchInput.parentElement.contains(e.target)) {
                    toggleTransferDropdown(false);
                }
            });
             console.log("Transfer search initialized.");
        }
        
        /**
         * Initializes the PDF parsing functionality.
         */
        function initPdfParsing() {
            const pdfInput = document.getElementById('pdfInvoiceInput');
            const parseBtn = document.getElementById('parsePdfBtn');
            const statusDiv = document.getElementById('pdfParseStatus');
            const previewDiv = document.getElementById('parsedPartsPreview');

            if (!pdfInput || !parseBtn) return;

            pdfInput.addEventListener('change', () => {
                parseBtn.disabled = !pdfInput.files.length;
                statusDiv.textContent = '';
                previewDiv.innerHTML = '';
            });

            parseBtn.addEventListener('click', async () => {
                if (!pdfInput.files.length) return;

                statusDiv.textContent = 'Parsing PDF, please wait...';
                parseBtn.disabled = true;
                const formData = new FormData();
                formData.append('pdf', pdfInput.files[0]);

                try {
                    const response = await fetch('api.php?action=parse_invoice_pdf', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success && Array.isArray(data.items) && data.items.length > 0) {
                        statusDiv.textContent = `Successfully parsed ${data.items.length} items. Select which items to add.`;
                        
                        let checklistHtml = '';
                        data.items.forEach((item, index) => {
                            const itemData = JSON.stringify(item);
                            checklistHtml += `
                                <div class="flex items-center p-1 rounded-md hover:bg-teal-100">
                                    <input id="item-${index}" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 parsed-item-checkbox" data-item='${itemData}' checked>
                                    <label for="item-${index}" class="ml-3 text-sm text-gray-700">
                                        <span class="font-medium text-indigo-700">[${item.type}]</span> ${item.name} 
                                        <span class="text-gray-500">(Qty: ${item.quantity}, Price: ₾${item.price})</span>
                                    </label>
                                </div>`;
                        });

                        previewDiv.innerHTML = `
                            <div class="bg-teal-50 border border-teal-200 rounded-lg p-3">
                                <h4 class="font-bold mb-2 text-gray-800">Parsed Items</h4>
                                <div class="flex items-center border-b pb-2 mb-2">
                                    <input id="selectAllParsed" type="checkbox" class="h-4 w-4 rounded border-gray-300" checked>
                                    <label for="selectAllParsed" class="ml-3 text-sm font-medium text-gray-800">Select All</label>
                                </div>
                                <div id="parsedItemsChecklist" class="space-y-1 max-h-40 overflow-y-auto">
                                    ${checklistHtml}
                                </div>
                                <button type="button" id="addParsedItemsBtn" class="mt-3 btn-gradient text-white px-3 py-1 rounded-md text-sm">Add Selected Items</button>
                            </div>
                        `;

                        // Add event listener for 'Select All'
                        document.getElementById('selectAllParsed').addEventListener('change', (e) => {
                            document.querySelectorAll('.parsed-item-checkbox').forEach(checkbox => {
                                checkbox.checked = e.target.checked;
                            });
                        });
                        
                        // Add event listener for 'Add Selected Items'
                        document.getElementById('addParsedItemsBtn').onclick = () => {
                            const selectedItems = [];
                            document.querySelectorAll('.parsed-item-checkbox:checked').forEach(checkbox => {
                                selectedItems.push(JSON.parse(checkbox.dataset.item));
                            });

                            if (selectedItems.length === 0) {
                                showToast('<?php echo addslashes(__('info.no_items_selected','No items selected.')); ?>', '', 'info');
                                return;
                            }

                            selectedItems.forEach(item => {
                                if (item.type === 'labor') {
                                    addLabor(item.name, item.quantity, item.price);
                                } else {
                                    addPart(item.name, item.quantity, item.price);
                                }
                            });
                            previewDiv.innerHTML = '';
                            statusDiv.textContent = `${selectedItems.length} items have been added to the lists below.`;
                        };

                    } else {
                        statusDiv.textContent = data.error || 'Could not parse any items from the PDF.';
                    }
                } catch (error) {
                    console.error('PDF parsing error:', error);
                    statusDiv.textContent = 'An error occurred while parsing the PDF.';
                } finally {
                    parseBtn.disabled = false;
                }
            });
        }
        
        /**
         * Shows or hides the transfer dropdown.
         */
        function toggleTransferDropdown(show) {
            const dropdown = document.getElementById('transferDropdown');
            const arrowContainer = document.querySelector('.search-dropdown .dropdown-arrow');
            if (dropdown && arrowContainer) {
                dropdown.classList.toggle('hidden', !show);
                arrowContainer.classList.toggle('open', show);
            }
        }

        /**
         * Updates the transfer dropdown options based on the filter text.
         */
        function updateTransferDropdown(filter = '') {
            const container = document.getElementById('transferOptions');
            if (!container) {
                console.error("transferOptions container not found.");
                return;
            }
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
        
        // Functions for adding/removing items, autocomplete, and form submission remain here...
        // (These were correct in the previous version but are included in this complete rewrite for clarity)

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

        function addPart(name = '', quantity = 1, price = 0) {
            const container = document.getElementById('partsList');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'part-item bg-white/40 rounded-lg p-3 border border-white/30';
            itemDiv.innerHTML = `
                <div class="grid grid-cols-12 gap-x-3 items-end">
                    <div class="col-span-7">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Part Name</label>
                        <div class="relative">
                            <input type="text" class="part-name block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm" placeholder="Enter name..." autocomplete="off" value="${name}">
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
                        <button type="button" onclick="removeItem(this)" class="px-2 py-2 border-2 border-red-300 rounded-lg text-red-600 hover:bg-red-50 w-full flex justify-center"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </div>
                </div>
            `;
            container.appendChild(itemDiv);
            setupAutocomplete(itemDiv.querySelector('.part-name'), 'part');
            lucide.createIcons();
            updateTotals();
        }

        function addLabor(name = '', quantity = 1, price = 0) {
            const container = document.getElementById('laborList');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'labor-item bg-white/40 rounded-lg p-3 border border-white/30';
            itemDiv.innerHTML = `
                <div class="grid grid-cols-12 gap-x-3 items-end">
                    <div class="col-span-7">
                        <label class="block text-xs font-semibold text-gray-800 mb-1">Service Name</label>
                        <div class="relative">
                            <input type="text" class="labor-name block w-full rounded-lg border-2 border-gray-200 bg-white/80 shadow-sm input-focus px-3 py-2 text-sm" placeholder="Enter name..." autocomplete="off" value="${name}">
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
                        <button type="button" onclick="removeItem(this)" class="px-2 py-2 border-2 border-red-300 rounded-lg text-red-600 hover:bg-red-50 w-full flex justify-center"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
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
            if (!transferId) { showToast('<?php echo addslashes(__('validation.title','Validation Error')); ?>', '<?php echo addslashes(__('validation.select_transfer','Please select a transfer.')); ?>', 'error'); return; }
            
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

            if (items.length === 0) { showToast('<?php echo addslashes(__('validation.title','Validation Error')); ?>', '<?php echo addslashes(__('validation.add_item','Please add at least one item.')); ?>', 'error'); return; }

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
                showToast('<?php echo addslashes(__('success.collection_created','Collection created successfully!')); ?>', '', 'success');
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
