<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database configuration
require_once 'config.php';

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';

// Get database connection for initial data load
try {
    $pdo = getDBConnection();
    
    // Fetch vehicles
    $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY plate ASC");
    $vehicles_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch transfers for service history
    $stmt = $pdo->query("SELECT * FROM transfers ORDER BY id DESC");
    $transfers_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Database error in vehicles.php: " . $e->getMessage());
    $vehicles_data = [];
    $transfers_data = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer DB - OTOMOTORS</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        accent: {
                            50: '#fdf4ff',
                            100: '#fae8ff',
                            500: '#d946ef',
                            600: '#c026d3',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        /* Premium Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { 
            width: 8px; 
            height: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track { 
            background: rgba(148, 163, 184, 0.1); 
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb { 
            background: linear-gradient(180deg, #0ea5e9 0%, #0284c7 100%);
            border-radius: 10px;
            border: 2px solid transparent;
            background-clip: padding-box;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { 
            background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%);
            background-clip: padding-box;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 text-slate-800 font-sans min-h-screen">

    <!-- Navigation Bar -->
    <nav class="bg-white/95 backdrop-blur-xl border-b border-slate-200/80 sticky top-0 z-20 shadow-lg shadow-slate-200/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-18 items-center">
                <div class="flex items-center gap-4">
                    <!-- Back to Dashboard -->
                    <a href="index.php" class="flex items-center gap-2 px-4 py-2 bg-slate-50 hover:bg-slate-100 rounded-xl transition-all text-sm font-semibold text-slate-700">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to Dashboard
                    </a>
                    
                    <div class="h-8 w-px bg-slate-200"></div>
                    
                    <!-- Logo -->
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-br from-primary-400 to-accent-500 rounded-xl blur-md opacity-60"></div>
                            <div class="relative bg-gradient-to-br from-primary-500 via-primary-600 to-accent-600 p-2.5 rounded-xl text-white shadow-lg">
                                <i data-lucide="database" class="w-5 h-5"></i>
                            </div>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-slate-900">Customer DB</h1>
                            <span class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider">OTOMOTORS</span>
                        </div>
                    </div>
                </div>

                <!-- User Info -->
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($current_user_name); ?></div>
                        <div class="text-xs text-slate-500 capitalize"><?php echo htmlspecialchars($current_user_role); ?></div>
                    </div>
                    <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-accent-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                        <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800">Customer Database</h2>
                    <p class="text-slate-500 text-sm">Centralized database of all customers, vehicles and service history.</p>
                </div>
                <?php if ($current_user_role === 'admin' || $current_user_role === 'manager'): ?>
                <button onclick="window.openVehicleModal()" class="bg-gradient-to-r from-primary-600 to-accent-600 hover:from-primary-700 hover:to-accent-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-lg active:scale-95 transition-all">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add Customer
                </button>
                <?php endif; ?>
            </div>

            <!-- Search Bar -->
            <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-sm flex items-center">
                <div class="p-3"><i data-lucide="search" class="w-5 h-5 text-slate-400"></i></div>
                <input id="vehicle-search" type="text" placeholder="Search registry by plate, owner or model..." class="w-full bg-transparent outline-none text-sm h-full py-2">
            </div>

            <!-- Table -->
            <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-lg shadow-slate-200/60 border border-slate-200/80 overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gradient-to-r from-slate-50 via-primary-50/30 to-slate-50 border-b-2 border-primary-200/50 text-xs uppercase tracking-wider text-slate-600 font-bold">
                            <tr>
                                <th class="px-6 py-5">Plate</th>
                                <th class="px-6 py-5">Owner</th>
                                <th class="px-6 py-5">Phone</th>
                                <th class="px-6 py-5">Model</th>
                                <th class="px-6 py-5">Service History</th>
                                <th class="px-6 py-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="vehicle-table-body" class="divide-y divide-slate-50"></tbody>
                    </table>
                    <div id="vehicle-empty" class="hidden py-16 text-center text-slate-400 text-sm">
                        <div class="flex flex-col items-center">
                            <div class="bg-slate-50 p-4 rounded-full mb-3">
                                <i data-lucide="database" class="w-8 h-8 text-slate-300"></i>
                            </div>
                            <p>No vehicles found.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Vehicle Modal -->
    <div id="vehicle-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="window.closeVehicleModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-5">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Customer Details</h3>
                        <p class="text-xs text-slate-500">Edit customer and vehicle information.</p>
                    </div>
                    <div class="bg-slate-100 p-2 rounded-full text-slate-400">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <input type="hidden" id="veh-id">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Plate Number</label>
                        <input id="veh-plate" type="text" class="w-full p-3 border border-slate-200 bg-slate-50 rounded-xl text-sm uppercase font-mono font-bold tracking-wider focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none" placeholder="AA-000-AA">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Owner Name</label>
                        <input id="veh-owner" type="text" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none" placeholder="Full Name">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Phone</label>
                        <input id="veh-phone" type="text" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none" placeholder="599 00 00 00">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1 ml-1">Car Model</label>
                        <input id="veh-model" type="text" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:border-primary-500 outline-none" placeholder="e.g. Toyota Prius, Silver">
                    </div>
                </div>

                <!-- Service History -->
                <div id="veh-history-section" class="hidden bg-slate-50 rounded-xl border border-slate-200 overflow-hidden">
                    <div class="px-4 py-2 bg-slate-100 border-b border-slate-200">
                        <label class="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="history" class="w-3 h-3"></i> Service History
                        </label>
                    </div>
                    <div id="veh-history-list" class="p-4 max-h-48 overflow-y-auto space-y-2 text-xs custom-scrollbar"></div>
                </div>

                <div class="flex gap-3 justify-end pt-2 border-t border-slate-100">
                    <button onclick="window.closeVehicleModal()" class="px-4 py-2.5 text-slate-500 hover:bg-slate-50 rounded-xl text-sm font-medium transition-colors">Cancel</button>
                    <?php if ($current_user_role === 'admin' || $current_user_role === 'manager'): ?>
                    <button onclick="window.saveVehicle()" class="px-6 py-2.5 bg-slate-900 text-white hover:bg-slate-800 rounded-xl text-sm font-semibold shadow-lg shadow-slate-900/10 transition-all active:scale-95">Save Vehicle</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <script>
        const API_URL = 'api.php';
        const USER_ROLE = '<?php echo $current_user_role; ?>';
        const CAN_EDIT = USER_ROLE === 'admin' || USER_ROLE === 'manager';

        // Data arrays - Initialize with database data
        let vehicles = <?php echo json_encode($vehicles_data); ?>;
        let transfers = <?php echo json_encode($transfers_data); ?>;
        
        // Debug logs
        console.log('Initial data loaded:');
        console.log('Vehicles count:', vehicles.length);
        console.log('Vehicles data:', vehicles);
        console.log('Transfers count:', transfers.length);
        console.log('User role:', USER_ROLE);
        console.log('Can edit:', CAN_EDIT);

        // Helper
        const normalizePlate = (p) => p ? p.replace(/[^a-zA-Z0-9]/g, '').toUpperCase() : '';

        // API Helper
        async function fetchAPI(action, method = 'GET', body = null) {
            const opts = { method };
            if (body) opts.body = JSON.stringify(body);
            
            try {
                const res = await fetch(`${API_URL}?action=${action}`, opts);
                
                if (!res.ok) {
                    const errorData = await res.json().catch(() => ({}));
                    throw new Error(errorData.error || `HTTP ${res.status}`);
                }
                
                return await res.json();
            } catch (err) {
                console.error('API Error:', err);
                throw err;
            }
        }

        // Load Data
        async function loadData() {
            try {
                console.log('loadData: Fetching from API...');
                const data = await fetchAPI('get_transfers');
                console.log('loadData: API response:', data);
                console.log('loadData: Transfers from API:', data.transfers?.length || 0);
                console.log('loadData: Vehicles from API:', data.vehicles?.length || 0);
                
                transfers = data.transfers || [];
                vehicles = data.vehicles || [];
                
                console.log('loadData: Updated arrays - Transfers:', transfers.length, 'Vehicles:', vehicles.length);
                renderVehicleTable();
            } catch (err) {
                console.error('Load error:', err);
                showToast('Failed to load data', 'Please refresh the page', 'error');
            }
        }

        // Render Vehicle Table
        function renderVehicleTable() {
            console.log('renderVehicleTable called');
            console.log('Current vehicles array:', vehicles);
            
            const term = document.getElementById('vehicle-search').value.toLowerCase();
            const rows = vehicles.filter(v => (v.plate+v.ownerName+v.model).toLowerCase().includes(term));
            
            console.log('Filtered rows:', rows.length);
            
            const html = rows.map(v => {
                // Get service history for this plate
                const serviceHistory = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(v.plate));
                const historyCount = serviceHistory.length;
                const lastService = serviceHistory.length > 0 ? serviceHistory[serviceHistory.length - 1] : null;
                
                let historyBadge = '';
                if (historyCount > 0) {
                    historyBadge = `<span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2 py-1 rounded-lg text-xs font-semibold">
                        <i data-lucide="file-text" class="w-3 h-3"></i> ${historyCount} service${historyCount > 1 ? 's' : ''}
                    </span>`;
                    if (lastService) {
                        const statusColors = {
                            'New': 'bg-blue-50 text-blue-600',
                            'Processing': 'bg-yellow-50 text-yellow-600',
                            'Completed': 'bg-green-50 text-green-600',
                            'Scheduled': 'bg-orange-50 text-orange-600'
                        };
                        const colorClass = statusColors[lastService.status] || 'bg-slate-50 text-slate-600';
                        historyBadge += ` <span class="ml-1 text-[10px] ${colorClass} px-1.5 py-0.5 rounded">${lastService.status}</span>`;
                    }
                } else {
                    historyBadge = '<span class="text-slate-300 text-xs italic">No history</span>';
                }
                
                return `
                <tr class="border-b border-slate-50 hover:bg-slate-50/50 group transition-colors">
                    <td class="px-6 py-4 font-mono font-bold text-slate-800">${v.plate}</td>
                    <td class="px-6 py-4 text-slate-600">${v.ownerName || '-'}</td>
                    <td class="px-6 py-4 text-sm text-slate-500">${v.phone || '-'}</td>
                    <td class="px-6 py-4 text-sm text-slate-500">${v.model || ''}</td>
                    <td class="px-6 py-4">${historyBadge}</td>
                    <td class="px-6 py-4 text-right">
                        ${CAN_EDIT ? `
                            <button onclick="window.editVehicle(${v.id})" class="text-primary-600 hover:bg-primary-50 p-2 rounded-lg transition-all"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                            <button onclick="window.deleteVehicle(${v.id})" class="text-red-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-lg transition-all"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        ` : `
                            <button onclick="window.editVehicle(${v.id})" class="text-slate-400 hover:bg-slate-50 p-2 rounded-lg transition-all" title="View Only"><i data-lucide="eye" class="w-4 h-4"></i></button>
                        `}
                    </td>
                </tr>`;
            }).join('');
            
            console.log('Generated HTML length:', html.length);
            console.log('Updating table body...');
            
            document.getElementById('vehicle-table-body').innerHTML = html;
            document.getElementById('vehicle-empty').classList.toggle('hidden', rows.length > 0);
            
            console.log('Empty state visible:', rows.length === 0);
            console.log('Reinitializing icons...');
            lucide.createIcons();
        }

        // Modal Functions
        window.openVehicleModal = () => {
            document.getElementById('veh-id').value = '';
            document.getElementById('veh-plate').value = '';
            document.getElementById('veh-owner').value = '';
            document.getElementById('veh-phone').value = '';
            document.getElementById('veh-model').value = '';
            document.getElementById('veh-history-section').classList.add('hidden');
            document.getElementById('vehicle-modal').classList.remove('hidden');
            lucide.createIcons();
        };

        window.closeVehicleModal = () => document.getElementById('vehicle-modal').classList.add('hidden');

        window.editVehicle = (id) => {
            const v = vehicles.find(i => i.id == id);
            document.getElementById('veh-id').value = id;
            document.getElementById('veh-plate').value = v.plate;
            document.getElementById('veh-owner').value = v.ownerName;
            document.getElementById('veh-phone').value = v.phone;
            document.getElementById('veh-model').value = v.model;
            
            // Show service history
            const serviceHistory = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(v.plate));
            const historySection = document.getElementById('veh-history-section');
            
            if (serviceHistory.length > 0) {
                historySection.classList.remove('hidden');
                const historyHTML = serviceHistory.map(s => {
                    const date = s.serviceDate ? new Date(s.serviceDate.replace(' ', 'T')).toLocaleDateString() : 'Not scheduled';
                    const statusColors = {
                        'New': 'bg-blue-100 text-blue-700',
                        'Processing': 'bg-yellow-100 text-yellow-700',
                        'Called': 'bg-purple-100 text-purple-700',
                        'Parts Ordered': 'bg-indigo-100 text-indigo-700',
                        'Parts Arrived': 'bg-teal-100 text-teal-700',
                        'Scheduled': 'bg-orange-100 text-orange-700',
                        'Completed': 'bg-green-100 text-green-700',
                        'Issue': 'bg-red-100 text-red-700'
                    };
                    const statusClass = statusColors[s.status] || 'bg-slate-100 text-slate-700';
                    return `
                        <div class="bg-white p-3 rounded-lg border border-slate-200 hover:border-indigo-300 transition-all">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-semibold text-slate-700">${s.name}</span>
                                <span class="text-[10px] ${statusClass} px-2 py-0.5 rounded-full font-bold">${s.status}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 flex items-center gap-3">
                                <span><i data-lucide="calendar" class="w-3 h-3 inline"></i> ${date}</span>
                                <span><i data-lucide="coins" class="w-3 h-3 inline"></i> ${s.amount || 0} GEL</span>
                            </div>
                        </div>
                    `;
                }).join('');
                document.getElementById('veh-history-list').innerHTML = historyHTML;
            } else {
                historySection.classList.add('hidden');
            }
            
            document.getElementById('vehicle-modal').classList.remove('hidden');
            lucide.createIcons();
        };

        window.saveVehicle = async () => {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to edit vehicles', 'error');
                return;
            }

            const id = document.getElementById('veh-id').value;
            const data = {
                plate: document.getElementById('veh-plate').value,
                ownerName: document.getElementById('veh-owner').value,
                phone: document.getElementById('veh-phone').value,
                model: document.getElementById('veh-model').value
            };
            
            try {
                await fetchAPI(`save_vehicle${id ? '&id='+id : ''}`, 'POST', data);
                window.closeVehicleModal();
                await loadData();
                showToast("Vehicle Saved", "Customer information updated successfully", "success");
            } catch (err) {
                showToast("Error", "Failed to save vehicle", "error");
            }
        };

        window.deleteVehicle = async (id) => {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to delete vehicles', 'error');
                return;
            }

            if(confirm("Delete this vehicle record?")) {
                try {
                    await fetchAPI(`delete_vehicle&id=${id}`, 'POST');
                    await loadData();
                    showToast("Vehicle Deleted", "Customer record removed", "success");
                } catch (err) {
                    showToast("Error", "Failed to delete vehicle", "error");
                }
            }
        };

        // Toast Notification
        function showToast(title, message = '', type = 'success', duration = 4000) {
            const container = document.getElementById('toast-container');
            
            if (typeof type === 'number') { duration = type; type = 'success'; }
            if (!message && !type) { type = 'success'; }
            else if (['success', 'error', 'info'].includes(message)) { type = message; message = ''; }
            
            const toast = document.createElement('div');
            
            const colors = {
                success: { 
                    bg: 'bg-white/95 backdrop-blur-xl', 
                    border: 'border-emerald-200/60', 
                    iconBg: 'bg-gradient-to-br from-emerald-50 to-teal-50', 
                    iconColor: 'text-emerald-600', 
                    icon: 'check-circle-2',
                    shadow: 'shadow-emerald-500/20' 
                },
                error: { 
                    bg: 'bg-white/95 backdrop-blur-xl', 
                    border: 'border-red-200/60', 
                    iconBg: 'bg-gradient-to-br from-red-50 to-orange-50', 
                    iconColor: 'text-red-600', 
                    icon: 'alert-circle',
                    shadow: 'shadow-red-500/20' 
                },
                info: { 
                    bg: 'bg-white/95 backdrop-blur-xl', 
                    border: 'border-primary-200/60', 
                    iconBg: 'bg-gradient-to-br from-primary-50 to-accent-50', 
                    iconColor: 'text-primary-600', 
                    icon: 'info',
                    shadow: 'shadow-primary-500/20' 
                }
            };
            
            const style = colors[type] || colors.info;

            toast.className = `pointer-events-auto w-80 ${style.bg} border-2 ${style.border} shadow-2xl ${style.shadow} rounded-2xl p-4 flex items-start gap-3 transform transition-all duration-500 translate-y-10 opacity-0`;
            
            toast.innerHTML = `
                <div class="${style.iconBg} p-3 rounded-xl shrink-0 shadow-inner">
                    <i data-lucide="${style.icon}" class="w-5 h-5 ${style.iconColor}"></i>
                </div>
                <div class="flex-1 pt-1">
                    <h4 class="text-sm font-bold text-slate-900 leading-none mb-1.5">${title}</h4>
                    ${message ? `<p class="text-xs text-slate-600 leading-relaxed font-medium">${message}</p>` : ''}
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-slate-600 transition-colors -mt-1 -mr-1 p-1.5 hover:bg-slate-100 rounded-lg">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            `;

            container.appendChild(toast);
            lucide.createIcons();

            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-10', 'opacity-0');
            });

            if (duration > 0) {
                setTimeout(() => {
                    toast.classList.add('translate-y-4', 'opacity-0');
                    setTimeout(() => toast.remove(), 500);
                }, duration);
            }
        }

        // Event Listeners
        document.getElementById('vehicle-search').addEventListener('input', renderVehicleTable);

        // Initialize - Render table with initial PHP data, then refresh from API
        console.log('Starting initialization...');
        
        try {
            renderVehicleTable();
            console.log('Initial render complete');
        } catch(e) {
            console.error('Render error:', e);
        }
        
        loadData();
        lucide.createIcons();
        
        console.log('Initialization complete');
    </script>
</body>
</html>
