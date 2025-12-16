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
        const USER_ROLE = '<?php echo $_SESSION['role'] ?? 'viewer'; ?>';
        const USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
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
                <div class="flex justify-end mb-4">
                    <a href="create_collection.php" class="btn-gradient inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white">
                        <i data-lucide="plus-circle" class="w-5 h-5 mr-2"></i>
                        Create New Collection
                    </a>
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
                            <div class="flex items-center space-x-4">
                                <div class="status-badge px-3 py-1 rounded-full text-xs font-medium text-white shadow-md">
                                    <i data-lucide="activity" class="w-3 h-3 inline mr-1"></i>
                                    Live Updates
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-600">Status</label>
                                    <select id="filter-status" class="text-sm px-2 py-1 border rounded bg-white">
                                        <option value="all">All</option>
                                        <option value="pending">Pending</option>
                                        <option value="collected">Collected</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-600">Type</label>
                                    <select id="filter-type" class="text-sm px-2 py-1 border rounded bg-white">
                                        <option value="all">All</option>
                                        <option value="local">Local</option>
                                        <option value="order">Order</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-gray-600">Manager</label>
                                    <select id="filter-manager" class="text-sm px-2 py-1 border rounded bg-white">
                                        <option value="all">All</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input id="filter-search" placeholder="Search plate or #id" class="px-2 py-1 text-sm border rounded" />
                                    <button id="filter-clear" class="px-2 py-1 text-sm bg-gray-100 rounded">Clear</button>
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
        let collections = [];
        let managers = [];

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCollections();
            loadManagers();
            // Wire up filter events
            document.getElementById('filter-status')?.addEventListener('change', renderCollections);
            document.getElementById('filter-type')?.addEventListener('change', renderCollections);
            document.getElementById('filter-manager')?.addEventListener('change', renderCollections);
            document.getElementById('filter-search')?.addEventListener('input', renderCollections);
            document.getElementById('filter-clear')?.addEventListener('click', function(e){
                e.preventDefault();
                document.getElementById('filter-status').value = 'all';
                document.getElementById('filter-type').value = 'all';
                document.getElementById('filter-manager').value = 'all';
                document.getElementById('filter-search').value = '';
                renderCollections();
            });
            lucide.createIcons();
        });

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
        
        // Load managers
        async function loadManagers() {
            try {
                const response = await fetch('api.php?action=get_managers');
                const data = await response.json();
                if (data.managers) {
                    managers = data.managers;
                    populateManagerFilter();
                }
            } catch (error) {
                console.error('Error loading managers:', error);
            }
        }

        // Populate manager select filter
        function populateManagerFilter() {
            const sel = document.getElementById('filter-manager');
            if (!sel) return;
            sel.innerHTML = '<option value="all">All</option>';
            managers.forEach(m => {
                const opt = document.createElement('option');
                opt.value = String(m.id);
                opt.textContent = m.full_name || m.username || m.id;
                sel.appendChild(opt);
            });
        }

        function getFilteredCollections() {
            const status = document.getElementById('filter-status')?.value || 'all';
            const type = document.getElementById('filter-type')?.value || 'all';
            const manager = document.getElementById('filter-manager')?.value || 'all';
            const search = (document.getElementById('filter-search')?.value || '').trim().toLowerCase();

            return collections.filter(c => {
                if (status !== 'all' && String(c.status) !== String(status)) return false;
                const ct = (c.collection_type || 'local');
                if (type !== 'all' && String(ct) !== String(type)) return false;
                if (manager !== 'all' && String(c.assigned_manager_id || '') !== String(manager)) return false;
                if (search) {
                    const plate = (c.transfer_plate || '').toLowerCase();
                    const idStr = String(c.id || '');
                    if (!plate.includes(search) && idStr !== search) return false;
                }
                return true;
            });
        }

        // Render collections in a card-based layout (applies active filters)
        function renderCollections() {
            const container = document.getElementById('collectionsTable');
            if (!container) return;
            const list = getFilteredCollections();

            if (list.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <div class="mx-auto w-24 h-24 gradient-accent rounded-full flex items-center justify-center float-animation">
                            <i data-lucide="package-search" class="w-12 h-12 text-white"></i>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-800">No collections match filters</h3>
                        <p class="mt-1 text-sm text-gray-600">Try clearing filters or create a new collection.</p>
                    </div>
                `;
                lucide.createIcons();
                return;
            }

            let html = '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';

            list.forEach(collection => {
                const statusColors = {
                    pending: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: 'hourglass' },
                    collected: { bg: 'bg-green-100', text: 'text-green-800', icon: 'check-circle' },
                    cancelled: { bg: 'bg-red-100', text: 'text-red-800', icon: 'x-circle' }
                };
                const statusInfo = statusColors[collection.status] || { bg: 'bg-gray-100', text: 'text-gray-800', icon: 'help-circle' };
                
                const managerName = collection.manager_full_name || 'Unassigned';
                const totalItems = collection.parts_list.reduce((sum, item) => sum + parseInt(item.quantity, 10), 0);
                const totalPrice = collection.parts_list.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.quantity, 10)), 0);
                // Progress calculation
                const partItems = collection.parts_list.filter(item => item.type === 'part');
                const collectedCount = partItems.reduce((sum, item) => sum + (item.collected ? parseInt(item.quantity, 10) : 0), 0);
                const totalParts = partItems.reduce((sum, item) => sum + parseInt(item.quantity, 10), 0);
                const progressPercent = totalParts > 0 ? Math.round((collectedCount / totalParts) * 100) : 0;

                    const type = (collection.collection_type || 'local');
                    const typeIcon = (type === 'order') ? 'truck' : 'shopping-cart';
                    const typeColor = (type === 'order') ? 'text-amber-600' : 'text-teal-500';

                    const shareUrl = window.location.origin + '/share_collection.php?id=' + collection.id;
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
                                    <i data-lucide="${typeIcon}" class="w-4 h-4 mr-2 ${typeColor}"></i>
                                    Type: <span class="font-semibold ml-1">${type.charAt(0).toUpperCase() + type.slice(1)}</span>
                                </div>
                                ${collection.description ? `<div class="flex items-start text-gray-700">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2 text-blue-500 mt-0.5"></i>
                                    <div>
                                        <span class="font-semibold">Description:</span>
                                        <p class="text-xs mt-1 text-gray-600">${collection.description}</p>
                                    </div>
                                </div>` : ''}
                                <div class="flex items-center text-gray-700">
                                    <i data-lucide="package" class="w-4 h-4 mr-2 text-purple-500"></i>
                                    Items: <span class="font-semibold ml-1">${totalItems}</span>
                                </div>
                                <div class="flex items-center text-gray-700">
                                    <i data-lucide="receipt" class="w-4 h-4 mr-2 text-green-500"></i>
                                    Total: <span class="font-bold ml-1">₾${totalPrice.toFixed(2)}</span>
                                </div>
                                <div class="flex items-center mt-2">
                                    <i data-lucide="progress" class="w-4 h-4 mr-2 text-blue-400"></i>
                                    <div class="w-full bg-gray-200 rounded-full h-3 mr-2">
                                        <div class="bg-blue-500 h-3 rounded-full transition-all duration-300" style="width: ${progressPercent}%;"></div>
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700">${collectedCount}/${totalParts} collected</span>
                                </div>
                                <div class="flex items-center gap-3 mt-3">
                                    <input type="text" readonly value="${shareUrl}" class="text-xs bg-gray-100 rounded px-2 py-1 border border-gray-200 flex-1 cursor-pointer" onclick="this.select()" title="Shareable link">
                                    <button onclick="copyToClipboard('${shareUrl}');return false;" class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 border border-blue-200 hover:bg-blue-200">Copy Link</button>
                                    <button onclick="showQrModal(${collection.id}, '${shareUrl}');return false;" class="px-2 py-1 text-xs rounded bg-purple-100 text-purple-700 border border-purple-200 hover:bg-purple-200">QR Code</button>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50/50 px-5 py-3 flex justify-end space-x-2">
                            <button onclick="window.location.href='edit_collection.php?id=${collection.id}'" class="inline-flex items-center px-3 py-1.5 border-2 border-transparent rounded-lg text-xs font-medium text-indigo-600 bg-indigo-100 hover:bg-indigo-200 transition-all duration-200">
                                <i data-lucide="edit" class="w-3 h-3 mr-1"></i> Edit
                            </button>
                            ${ (USER_ROLE === 'admin' || USER_ROLE === 'manager') ? `
                            <button onclick="deleteCollection(${collection.id})" class="inline-flex items-center px-3 py-1.5 border-2 border-transparent rounded-lg text-xs font-medium text-red-600 bg-red-100 hover:bg-red-200 transition-all duration-200">
                                <i data-lucide="trash-2" class="w-3 h-3 mr-1"></i> Delete
                            </button>` : '' }
                        </div>
                    </div>
                `;
                    // Clipboard copy helper
                    window.copyToClipboard = function(text) {
                        navigator.clipboard.writeText(text).then(() => {
                            showToast('Link copied to clipboard', 'success');
                        });
                    };

                    // QR Modal logic
                    window.showQrModal = function(id, url) {
                        let modal = document.getElementById('qrModal');
                        if (!modal) {
                            modal = document.createElement('div');
                            modal.id = 'qrModal';
                            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/40';
                            modal.innerHTML = `
                                <div class="bg-white rounded-2xl shadow-2xl p-8 flex flex-col items-center relative min-w-[320px]">
                                    <button onclick="closeQrModal()" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700"><i data-lucide='x' class='w-5 h-5'></i></button>
                                    <div id="qrCodeContainer" class="mb-4"></div>
                                    <div class="mb-2 text-xs text-gray-700 break-all">${url}</div>
                                    <button onclick="printQrCode()" class="mt-2 px-4 py-2 rounded bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700">Print QR Code</button>
                                </div>
                            `;
                            document.body.appendChild(modal);
                            lucide.createIcons();
                        } else {
                            modal.querySelector('.mb-2').textContent = url;
                        }
                        modal.style.display = 'flex';
                        // Generate QR code
                        setTimeout(() => {
                            const qrDiv = document.getElementById('qrCodeContainer');
                            qrDiv.innerHTML = '';
                            new QRCode(qrDiv, { text: url, width: 180, height: 180 });
                        }, 100);
                    };
                    window.closeQrModal = function() {
                        const modal = document.getElementById('qrModal');
                        if (modal) modal.style.display = 'none';
                    };
                    window.printQrCode = function() {
                        const qrDiv = document.getElementById('qrCodeContainer');
                        const win = window.open('', '', 'width=400,height=500');
                        win.document.write('<html><head><title>Print QR Code</title></head><body style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;"><div>' + qrDiv.innerHTML + '</div></body></html>');
                        win.document.close();
                        win.focus();
                        setTimeout(() => { win.print(); win.close(); }, 500);
                    };
                // Add QRCode.js CDN
                (function() {
                    if (!window.QRCode) {
                        var script = document.createElement('script');
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
                        script.onload = function() { console.log('QRCode.js loaded'); };
                        document.head.appendChild(script);
                    }
                })();
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