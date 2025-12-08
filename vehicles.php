<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'language.php';

// Simple language function for vehicles
function __($key, $default = '') {
    $fallbacks = [
        'vehicles.title' => 'Vehicle Registry',
        'vehicles.add_vehicle' => 'Add Vehicle',
        'vehicles.plate' => 'Plate Number'
    ];
    return $fallbacks[$key] ?? $default ?: $key;
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
    <title><?php echo __('vehicles.title', 'Vehicles'); ?> - OTOMOTORS</title>
    
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
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

<?php include 'header.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800"><?php echo __('vehicles.title', 'Vehicles'); ?></h2>
                    <p class="text-slate-500 text-sm"><?php echo __('vehicles.description', 'Manage customer vehicles and their service records.'); ?></p>
                </div>
                <?php if ($current_user_role === 'admin' || $current_user_role === 'manager'): ?>
                <button onclick="window.openVehicleModal()" class="bg-gradient-to-r from-primary-600 to-accent-600 hover:from-primary-700 hover:to-accent-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-lg active:scale-95 transition-all">
                    <i data-lucide="plus" class="w-4 h-4"></i> <?php echo __('vehicles.add_customer', 'Add Customer'); ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Search and Filter Bar -->
            <div class="bg-white/95 backdrop-blur-xl rounded-2xl border border-slate-200 shadow-lg p-4">
                <div class="flex flex-col lg:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1 flex items-center bg-slate-50 rounded-xl border border-slate-200 px-4 py-2.5">
                        <i data-lucide="search" class="w-5 h-5 text-slate-400"></i>
                        <input id="vehicle-search" type="text" placeholder="<?php echo __('vehicles.search_placeholder', 'Search vehicles...'); ?>" class="w-full bg-transparent outline-none text-sm ml-3">
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="flex items-center gap-2">
                        <div class="flex items-center bg-slate-50 rounded-xl border border-slate-200 px-4 py-2.5 min-w-[200px]">
                            <i data-lucide="filter" class="w-5 h-5 text-slate-400"></i>
                            <select id="status-filter" class="w-full bg-transparent outline-none text-sm ml-3 cursor-pointer">
                                <option value=""><?php echo __('vehicles.all_status', 'All Status'); ?></option>
                                <option value="New">New</option>
                                <option value="Processing">Processing</option>
                                <option value="Called">Called</option>
                                <option value="Parts Ordered">Parts Ordered</option>
                                <option value="Parts Arrived">Parts Arrived</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="Completed">Completed</option>
                                <option value="Issue">Issue</option>
                                <option value="no-history">No History</option>
                            </select>
                        </div>
                        
                        <!-- Sort -->
                        <div class="flex items-center bg-slate-50 rounded-xl border border-slate-200 px-4 py-2.5 min-w-[180px]">
                            <i data-lucide="arrow-up-down" class="w-5 h-5 text-slate-400"></i>
                            <select id="sort-select" class="w-full bg-transparent outline-none text-sm ml-3 cursor-pointer">
                                <option value="plate-asc">Plate (A-Z)</option>
                                <option value="plate-desc">Plate (Z-A)</option>
                                <option value="owner-asc">Owner (A-Z)</option>
                                <option value="owner-desc">Owner (Z-A)</option>
                                <option value="services-desc">Most Services</option>
                                <option value="services-asc">Least Services</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl shadow-slate-300/40 border border-slate-200/80 overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-600 text-white">
                            <tr>
                                <th class="px-6 py-4 text-xs uppercase tracking-wider font-extrabold">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="hash" class="w-4 h-4"></i>
                                        <span>Plate</span>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-xs uppercase tracking-wider font-extrabold">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="user" class="w-4 h-4"></i>
                                        <span>Owner</span>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-xs uppercase tracking-wider font-extrabold">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="phone" class="w-4 h-4"></i>
                                        <span>Phone</span>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-xs uppercase tracking-wider font-extrabold">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="car" class="w-4 h-4"></i>
                                        <span>Model</span>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-xs uppercase tracking-wider font-extrabold">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="clock" class="w-4 h-4"></i>
                                        <span>Service History</span>
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-xs uppercase tracking-wider font-extrabold text-right">
                                    <div class="flex items-center gap-2 justify-end">
                                        <i data-lucide="settings" class="w-4 h-4"></i>
                                        <span>Actions</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="vehicle-table-body" class="divide-y divide-slate-100"></tbody>
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

            <!-- Pagination -->
            <div class="flex items-center justify-between bg-white/95 backdrop-blur-xl p-4 rounded-2xl border border-slate-200 shadow-sm">
                <div class="text-sm text-slate-600" id="vehicles-page-info">
                    Showing <span id="vehicles-showing-start">0</span>-<span id="vehicles-showing-end">0</span> of <span id="vehicles-total">0</span>
                </div>
                <div class="flex gap-2" id="vehicles-pagination">
                    <!-- Pagination buttons populated by JavaScript -->
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

    <!-- Order Details Modal -->
    <div id="order-modal" class="hidden fixed inset-0 z-[60] overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="window.closeOrderModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 space-y-5">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800" id="order-modal-title">Service Order Details</h3>
                        <p class="text-xs text-slate-500" id="order-modal-subtitle">Complete order information and history.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($current_user_role === 'admin' || $current_user_role === 'manager'): ?>
                        <button id="order-edit-btn" onclick="window.toggleOrderEdit()" class="bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white p-2 rounded-xl transition-all shadow-sm" title="Edit Order">
                            <i data-lucide="edit-2" class="w-5 h-5"></i>
                        </button>
                        <?php endif; ?>
                        <button onclick="window.closeOrderModal()" class="bg-slate-100 hover:bg-slate-200 p-2 rounded-full text-slate-400 transition-colors">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                
                <div id="order-details-content" class="space-y-4">
                    <!-- Content will be populated by JavaScript -->
                </div>
                
                <div id="order-edit-actions" class="hidden flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <button onclick="window.cancelOrderEdit()" class="px-4 py-2.5 text-slate-500 hover:bg-slate-50 rounded-xl text-sm font-medium transition-colors">Cancel</button>
                    <button onclick="window.saveOrderEdit()" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 rounded-xl text-sm font-semibold shadow-lg shadow-blue-500/25 transition-all active:scale-95">Save Changes</button>
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
        
        // Helper
        const normalizePlate = (p) => p ? p.replace(/[^a-zA-Z0-9]/g, '').toUpperCase() : '';

        // SMS Templates
        const defaultTemplates = {
            registered: "გამარჯობა {name}! თქვენი მანქანა {plate} დარეგისტრირდა სერვისზე. თანხა: {amount} ლარი. მალე დაგიკავშირდებით! 🚗",
            called: "გამარჯობა {name}! გვესმის თქვენი მანქანის {plate} შეკეთების საჭიროება. მალე დაგიკავშირდებით დეტალებისთვის. 📞",
            schedule: "გამარჯობა {name}! თქვენი მანქანის {plate} სერვისი დაინიშნა: {date}. გთხოვთ იყოთ დროულად! ⏰",
            parts_ordered: "გამარჯობა {name}! თქვენი მანქანის {plate} ნაწილები შეკვეთილია. მალე მოგვა! 📦",
            parts_arrived: "გამარჯობა {name}! თქვენი მანქანის {plate} ნაწილები მივიდა! გთხოვთ დაადასტუროთ: {link} ✅",
            rescheduled: "გამარჯობა {name}! თქვენი მანქანის {plate} სერვისი გადატანილია: {date}. მადლობა! 📅",
            reschedule_accepted: "გამარჯობა {name}! თქვენი გადატანის მოთხოვნა დადასტურდა. ახალი თარიღი: {date}. გნახავთ! 👍",
            completed: "გამარჯობა {name}! თქვენი მანქანის {plate} სერვისი დასრულდა! თანხა: {amount} ლარი. გთხოვთ შეაფასოთ: {link} ⭐",
            issue: "გამარჯობა {name}! თქვენი მანქანის {plate} სერვისთან დაკავშირებით პრობლემაა. გთხოვთ დაგვიკავშირდით. ☎️"
        };

        let smsTemplates = defaultTemplates;

        // Load SMS templates from API
        async function loadSMSTemplates() {
            try {
                const serverTemplates = await fetchAPI('get_sms_templates');
                smsTemplates = { ...defaultTemplates, ...serverTemplates };
            } catch(e) {
                console.warn('Could not load SMS templates:', e);
                smsTemplates = defaultTemplates;
            }
        }

        // Format SMS message with template placeholders
        function getFormattedMessage(type, data) {
            let template = smsTemplates[type] || defaultTemplates[type] || "";
            return template
                .replace(/{name}/g, data.name || '')
                .replace(/{plate}/g, data.plate || '')
                .replace(/{amount}/g, data.amount || '0')
                .replace(/{date}/g, data.date || '')
                .replace(/{link}/g, data.link || '');
        }

        // Send SMS function
        async function sendSMS(phone, message, context = 'manual') {
            if (!phone) {
                showToast('Error', 'Phone number is required', 'error');
                return;
            }
            
            try {
                await fetchAPI('send_sms', 'POST', { to: phone, text: message, context });
                console.log('SMS sent:', context, phone);
                showToast('SMS Sent', 'Notification delivered successfully', 'success');
            } catch(e) {
                console.error('SMS send error:', e);
                showToast('SMS Error', 'Failed to send SMS notification', 'error');
            }
        }

        // API Helper
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        
        async function fetchAPI(action, method = 'GET', body = null) {
            const opts = { 
                method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            
            // Add CSRF token for POST requests
            if (method === 'POST' && CSRF_TOKEN) {
                opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
            }
            
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
                const data = await fetchAPI('get_transfers');
                
                transfers = data.transfers || [];
                vehicles = data.vehicles || [];
                
                renderVehicleTable();
            } catch (err) {
                console.error('Load error:', err);
                showToast('Failed to load data', 'Please refresh the page', 'error');
            }
        }

        // Pagination variables
        let currentVehiclesPage = 1;
        const vehiclesPerPage = 10;

        // Render Vehicle Table
        function renderVehicleTable(page = 1) {
            currentVehiclesPage = page;
            const term = document.getElementById('vehicle-search').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const sortBy = document.getElementById('sort-select').value;
            
            // Filter by search term
            let rows = vehicles.filter(v => (v.plate+v.ownerName+v.model).toLowerCase().includes(term));
            
            // Filter by status
            if (statusFilter) {
                rows = rows.filter(v => {
                    const serviceHistory = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(v.plate));
                    if (statusFilter === 'no-history') {
                        return serviceHistory.length === 0;
                    } else {
                        const lastService = serviceHistory.length > 0 ? serviceHistory[serviceHistory.length - 1] : null;
                        return lastService && lastService.status === statusFilter;
                    }
                });
            }
            
            // Sort
            rows.sort((a, b) => {
                const aServiceCount = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(a.plate)).length;
                const bServiceCount = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(b.plate)).length;
                
                switch(sortBy) {
                    case 'plate-asc': return a.plate.localeCompare(b.plate);
                    case 'plate-desc': return b.plate.localeCompare(a.plate);
                    case 'owner-asc': return (a.ownerName || '').localeCompare(b.ownerName || '');
                    case 'owner-desc': return (b.ownerName || '').localeCompare(a.ownerName || '');
                    case 'services-desc': return bServiceCount - aServiceCount;
                    case 'services-asc': return aServiceCount - bServiceCount;
                    default: return 0;
                }
            });
            
            // Pagination logic
            const totalVehicles = rows.length;
            const totalPages = Math.ceil(totalVehicles / vehiclesPerPage);
            
            // Adjust page if out of range
            if (currentVehiclesPage > totalPages && totalPages > 0) {
                currentVehiclesPage = totalPages;
            }
            if (currentVehiclesPage < 1) {
                currentVehiclesPage = 1;
            }
            
            const startIndex = (currentVehiclesPage - 1) * vehiclesPerPage;
            const endIndex = Math.min(startIndex + vehiclesPerPage, totalVehicles);
            const pageRows = rows.slice(startIndex, endIndex);
            
            // Update pagination info
            if (totalVehicles > 0) {
                document.getElementById('vehicles-page-info').classList.remove('hidden');
                document.getElementById('vehicles-showing-start').textContent = startIndex + 1;
                document.getElementById('vehicles-showing-end').textContent = endIndex;
                document.getElementById('vehicles-total').textContent = totalVehicles;
            } else {
                document.getElementById('vehicles-page-info').classList.add('hidden');
            }
            
            const html = pageRows.map(v => {
                // Get service history for this plate
                const serviceHistory = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(v.plate));
                const historyCount = serviceHistory.length;
                const lastService = serviceHistory.length > 0 ? serviceHistory[serviceHistory.length - 1] : null;
                
                let historyBadge = '';
                if (historyCount > 0) {
                    const statusColors = {
                        'New': 'bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-lg shadow-blue-500/30',
                        'Processing': 'bg-gradient-to-r from-yellow-500 to-yellow-600 text-white shadow-lg shadow-yellow-500/30',
                        'Called': 'bg-gradient-to-r from-purple-500 to-purple-600 text-white shadow-lg shadow-purple-500/30',
                        'Parts Ordered': 'bg-gradient-to-r from-indigo-500 to-indigo-600 text-white shadow-lg shadow-indigo-500/30',
                        'Parts Arrived': 'bg-gradient-to-r from-teal-500 to-teal-600 text-white shadow-lg shadow-teal-500/30',
                        'Scheduled': 'bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-lg shadow-orange-500/30',
                        'Completed': 'bg-gradient-to-r from-green-500 to-green-600 text-white shadow-lg shadow-green-500/30',
                        'Issue': 'bg-gradient-to-r from-red-500 to-red-600 text-white shadow-lg shadow-red-500/30'
                    };
                    const colorClass = statusColors[lastService.status] || 'bg-gradient-to-r from-slate-500 to-slate-600 text-white shadow-sm shadow-slate-500/30';
                    historyBadge = `
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold text-xs">
                                <i data-lucide="file-text" class="w-3 h-3"></i>
                                ${historyCount}
                            </span>
                            ${lastService ? `<button onclick="event.stopPropagation(); window.openOrderModal(${lastService.id})" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg ${colorClass} font-semibold text-xs hover:scale-105 transition-transform active:scale-95">
                                ${lastService.status}
                            </button>` : ''}
                        </div>
                    `;
                } else {
                    historyBadge = '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-50 text-slate-400 text-xs"><i data-lucide="minus" class="w-3 h-3"></i>No history</span>';
                }
                
                // Escape HTML to prevent XSS
                const escapeHtml = (str) => String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
                
                return `
                <tr class="hover:bg-gradient-to-r hover:from-blue-50/50 hover:via-indigo-50/30 hover:to-blue-50/50 group transition-all duration-200 cursor-pointer" onclick="window.editVehicle(${v.id})">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2.5">
                            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-2 rounded-lg shadow-lg shadow-blue-500/25">
                                <i data-lucide="car" class="w-3.5 h-3.5 text-white"></i>
                            </div>
                            <span class="font-mono font-extrabold text-slate-900 text-sm tracking-wide">${escapeHtml(v.plate)}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="font-semibold text-slate-800 text-sm">${escapeHtml(v.ownerName) || '-'}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <i data-lucide="phone" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span class="text-sm text-slate-600">${escapeHtml(v.phone) || '-'}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-sm text-slate-600">${escapeHtml(v.model) || '<span class="text-slate-400 italic">Not specified</span>'}</span>
                    </td>
                    <td class="px-6 py-4">${historyBadge}</td>
                    <td class="px-6 py-4 text-right" onclick="event.stopPropagation()">
                        <div class="flex items-center justify-end gap-2">
                            ${CAN_EDIT ? `
                                <button onclick="window.editVehicle(${v.id})" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white p-2.5 rounded-xl transition-all shadow-sm hover:shadow-lg hover:shadow-blue-500/25 active:scale-95" title="Edit">
                                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                                </button>
                                <button onclick="window.deleteVehicle(${v.id})" class="bg-red-50 text-red-600 hover:bg-red-600 hover:text-white p-2.5 rounded-xl transition-all shadow-sm hover:shadow-lg hover:shadow-red-500/25 active:scale-95" title="Delete">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            ` : `
                                <button onclick="window.editVehicle(${v.id})" class="bg-slate-100 text-slate-600 hover:bg-slate-200 p-2.5 rounded-xl transition-all shadow-sm active:scale-95" title="View Only">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            `}
                        </div>
                    </td>
                </tr>`;
            }).join('');
            
            document.getElementById('vehicle-table-body').innerHTML = html;
            document.getElementById('vehicle-empty').classList.toggle('hidden', rows.length > 0);
            
            // Render pagination
            renderVehiclesPagination(totalPages);
            
            lucide.createIcons();
        }

        // Pagination rendering
        function renderVehiclesPagination(totalPages) {
            const container = document.getElementById('vehicles-pagination');
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '';

            // Previous button
            html += `
                <button onclick="renderVehicleTable(${currentVehiclesPage - 1})" 
                    class="px-3 py-1.5 rounded-lg border transition-all ${
                        currentVehiclesPage === 1 
                            ? 'border-slate-200 text-slate-400 cursor-not-allowed' 
                            : 'border-slate-300 text-slate-700 hover:bg-slate-100'
                    }" 
                    ${currentVehiclesPage === 1 ? 'disabled' : ''}>
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
            `;

            // Page numbers (show max 5 pages)
            let startPage = Math.max(1, currentVehiclesPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            
            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }

            if (startPage > 1) {
                html += `<button onclick="renderVehicleTable(1)" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100">1</button>`;
                if (startPage > 2) {
                    html += `<span class="px-2 text-slate-400">...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <button onclick="renderVehicleTable(${i})" 
                        class="px-3 py-1.5 rounded-lg border transition-all ${
                            i === currentVehiclesPage 
                                ? 'bg-blue-600 text-white border-blue-600' 
                                : 'border-slate-300 text-slate-700 hover:bg-slate-100'
                        }">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="px-2 text-slate-400">...</span>`;
                }
                html += `<button onclick="renderVehicleTable(${totalPages})" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100">${totalPages}</button>`;
            }

            // Next button
            html += `
                <button onclick="renderVehicleTable(${currentVehiclesPage + 1})" 
                    class="px-3 py-1.5 rounded-lg border transition-all ${
                        currentVehiclesPage === totalPages 
                            ? 'border-slate-200 text-slate-400 cursor-not-allowed' 
                            : 'border-slate-300 text-slate-700 hover:bg-slate-100'
                    }" 
                    ${currentVehiclesPage === totalPages ? 'disabled' : ''}>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            `;

            container.innerHTML = html;
            lucide.createIcons();
        }

        // Modal Functions
        window.openVehicleModal = () => {
            const modal = document.getElementById('vehicle-modal');
            if (!modal) {
                console.error('Vehicle modal not found');
                return;
            }
            
            document.getElementById('veh-id').value = '';
            document.getElementById('veh-plate').value = '';
            document.getElementById('veh-owner').value = '';
            document.getElementById('veh-phone').value = '';
            document.getElementById('veh-model').value = '';
            document.getElementById('veh-history-section').classList.add('hidden');
            modal.classList.remove('hidden');
            lucide.createIcons();
        };

        window.closeVehicleModal = () => document.getElementById('vehicle-modal').classList.add('hidden');

        // Order Modal Functions
        let currentOrderId = null;
        let isEditMode = false;
        
        window.openOrderModal = async (orderId) => {
            let order = transfers.find(t => t.id == orderId);
            
            // If not found in local cache, try fetching from API
            if (!order) {
                try {
                    await loadData();
                    order = transfers.find(t => t.id == orderId);
                } catch(e) {
                    console.error('Failed to reload data:', e);
                }
            }
            
            if (!order) {
                console.error('Order not found:', orderId);
                showToast('Error', 'Order not found. Please refresh the page.', 'error');
                return;
            }
            
            currentOrderId = orderId;
            isEditMode = false;
            renderOrderModal(order, false);
            document.getElementById('order-modal').classList.remove('hidden');
            lucide.createIcons();
        };

        function renderOrderModal(order, editMode = false) {
            // Escape HTML to prevent XSS vulnerabilities
            const escapeHtml = (str) => String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
            
            const statusColors = {
                'New': 'bg-gradient-to-r from-blue-500 to-blue-600 text-white',
                'Processing': 'bg-gradient-to-r from-yellow-500 to-yellow-600 text-white',
                'Called': 'bg-gradient-to-r from-purple-500 to-purple-600 text-white',
                'Parts Ordered': 'bg-gradient-to-r from-indigo-500 to-indigo-600 text-white',
                'Parts Arrived': 'bg-gradient-to-r from-teal-500 to-teal-600 text-white',
                'Scheduled': 'bg-gradient-to-r from-orange-500 to-orange-600 text-white',
                'Completed': 'bg-gradient-to-r from-green-500 to-green-600 text-white',
                'Issue': 'bg-gradient-to-r from-red-500 to-red-600 text-white'
            };
            const statusClass = statusColors[order.status] || 'bg-gradient-to-r from-slate-500 to-slate-600 text-white';
            
            const serviceDate = order.serviceDate ? new Date(order.serviceDate.replace(' ', 'T')).toLocaleString() : 'Not scheduled';
            const serviceDateValue = order.serviceDate ? order.serviceDate.replace(' ', 'T').substring(0, 16) : '';
            const createdAt = order.createdAt ? new Date(order.createdAt.replace(' ', 'T')).toLocaleString() : 'N/A';
            
            // Update modal title
            document.getElementById('order-modal-title').textContent = editMode ? 'Edit Service Order' : 'Service Order Details';
            document.getElementById('order-modal-subtitle').textContent = editMode ? 'Update order information below.' : 'Complete order information and history.';
            document.getElementById('order-edit-actions').classList.toggle('hidden', !editMode);
            
            const contentHTML = editMode ? `
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-200/50">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-3 rounded-xl shadow-lg shadow-blue-500/25">
                            <i data-lucide="car" class="w-6 h-6 text-white"></i>
                        </div>
                        <div>
                            <input type="text" id="edit-plate" value="${order.plate}" class="font-mono font-extrabold text-slate-900 text-xl tracking-wide bg-white border border-slate-200 rounded-lg px-3 py-1 uppercase" placeholder="Plate">
                            <p class="text-sm text-slate-600">Order #${order.id}</p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Customer Name</label>
                        <input type="text" id="edit-name" value="${escapeHtml(order.name)}" class="w-full p-2 border border-slate-200 rounded-lg font-semibold text-slate-800" placeholder="Customer Name">
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Phone Number</label>
                        <input type="text" id="edit-phone" value="${escapeHtml(order.phone)}" class="w-full p-2 border border-slate-200 rounded-lg font-semibold text-slate-800" placeholder="Phone Number">
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Amount (GEL)</label>
                        <input type="number" id="edit-amount" value="${parseFloat(order.amount) || 0}" class="w-full p-2 border border-slate-200 rounded-lg font-bold text-green-600" placeholder="0">
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Franchise (GEL)</label>
                        <input type="number" id="edit-franchise" value="${order.franchise || 0}" class="w-full p-2 border border-slate-200 rounded-lg font-semibold text-slate-800" placeholder="0">
                    </div>
                    
                    <div class="col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Status</label>
                        <select id="edit-status" class="w-full p-2 border border-slate-200 rounded-lg font-semibold text-slate-800 cursor-pointer">
                            <option value="New" ${order.status === 'New' ? 'selected' : ''}>New</option>
                            <option value="Processing" ${order.status === 'Processing' ? 'selected' : ''}>Processing</option>
                            <option value="Called" ${order.status === 'Called' ? 'selected' : ''}>Called</option>
                            <option value="Parts Ordered" ${order.status === 'Parts Ordered' ? 'selected' : ''}>Parts Ordered</option>
                            <option value="Parts Arrived" ${order.status === 'Parts Arrived' ? 'selected' : ''}>Parts Arrived</option>
                            <option value="Scheduled" ${order.status === 'Scheduled' ? 'selected' : ''}>Scheduled</option>
                            <option value="Completed" ${order.status === 'Completed' ? 'selected' : ''}>Completed</option>
                            <option value="Issue" ${order.status === 'Issue' ? 'selected' : ''}>Issue</option>
                        </select>
                    </div>
                    
                    <div class="col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Service Date</label>
                        <input type="datetime-local" id="edit-service-date" value="${serviceDateValue}" class="w-full p-2 border border-slate-200 rounded-lg font-semibold text-slate-800">
                    </div>
                    
                    <div class="col-span-2 bg-indigo-50 p-4 rounded-xl border border-indigo-200">
                        <label class="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-3 block flex items-center gap-2">
                            <i data-lucide="message-circle" class="w-4 h-4"></i>
                            SMS Notification Settings
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-3 cursor-pointer p-2 rounded-lg hover:bg-indigo-100/50 transition-colors">
                                <input type="checkbox" id="send-sms-on-save" class="w-4 h-4 text-indigo-600 border-indigo-300 rounded focus:ring-indigo-500">
                                <span class="text-sm text-slate-700 font-medium">Send SMS notification after saving changes</span>
                            </label>
                            <p class="text-xs text-slate-500 ml-7">SMS will be sent based on the selected status (Processing, Scheduled, Called, Parts Ordered, Parts Arrived, Completed, Issue)</p>
                        </div>
                    </div>
                </div>
            ` : `
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-200/50">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 p-3 rounded-xl shadow-lg shadow-blue-500/25">
                                <i data-lucide="car" class="w-6 h-6 text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-mono font-extrabold text-slate-900 text-xl tracking-wide">${escapeHtml(order.plate)}</h4>
                                <p class="text-sm text-slate-600">Order #${parseInt(order.id) || 0}</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ${statusClass} font-bold text-sm shadow-lg">
                            <i data-lucide="activity" class="w-4 h-4"></i>
                            ${escapeHtml(order.status)}
                        </span>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Customer Name</label>
                        <div class="flex items-center gap-2">
                            <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                            <span class="font-semibold text-slate-800">${escapeHtml(order.name)}</span>
                        </div>
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Phone Number</label>
                        <div class="flex items-center gap-2">
                            <i data-lucide="phone" class="w-4 h-4 text-slate-400"></i>
                            <span class="font-semibold text-slate-800">${escapeHtml(order.phone) || 'N/A'}</span>
                        </div>
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Amount</label>
                        <div class="flex items-center gap-2">
                            <i data-lucide="coins" class="w-4 h-4 text-slate-400"></i>
                            <span class="font-bold text-green-600 text-lg">${order.amount || 0} GEL</span>
                        </div>
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Franchise</label>
                        <div class="flex items-center gap-2">
                            <i data-lucide="percent" class="w-4 h-4 text-slate-400"></i>
                            <span class="font-semibold text-slate-800">${order.franchise || 0} GEL</span>
                        </div>
                    </div>
                    
                    <div class="col-span-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Service Date</label>
                        <div class="flex items-center gap-2">
                            <i data-lucide="calendar" class="w-4 h-4 text-slate-400"></i>
                            <span class="font-semibold text-slate-800">${serviceDate}</span>
                        </div>
                    </div>
                    
                    ${order.userResponse ? `
                    <div class="col-span-2 bg-blue-50 p-4 rounded-xl border border-blue-200">
                        <label class="text-xs font-bold text-blue-700 uppercase tracking-wider mb-2 block flex items-center gap-2">
                            <i data-lucide="message-circle" class="w-3 h-3"></i>
                            Customer Response
                        </label>
                        <span class="font-semibold text-blue-800">${escapeHtml(order.userResponse)}</span>
                    </div>
                    ` : ''}
                    
                    ${order.reviewStars ? `
                    <div class="col-span-2 bg-green-50 p-4 rounded-xl border border-green-200">
                        <label class="text-xs font-bold text-green-700 uppercase tracking-wider mb-2 block flex items-center gap-2">
                            <i data-lucide="star" class="w-3 h-3"></i>
                            Customer Review
                        </label>
                        <div class="flex items-center gap-2 mb-1">
                            ${'⭐'.repeat(parseInt(order.reviewStars) || 0)}
                        </div>
                        ${order.reviewComment ? `<p class="text-sm text-green-800 mt-2">${escapeHtml(order.reviewComment)}</p>` : ''}
                    </div>
                    ` : ''}
                </div>
                
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Created At</label>
                    <div class="flex items-center gap-2">
                        <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
                        <span class="text-sm text-slate-600">${createdAt}</span>
                    </div>
                </div>
            `;
            
            document.getElementById('order-details-content').innerHTML = contentHTML;
            lucide.createIcons();
        }

        window.toggleOrderEdit = () => {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to edit orders', 'error');
                return;
            }
            
            const order = transfers.find(t => t.id == currentOrderId);
            if (!order) return;
            
            isEditMode = !isEditMode;
            renderOrderModal(order, isEditMode);
        };

        window.cancelOrderEdit = () => {
            const order = transfers.find(t => t.id == currentOrderId);
            if (!order) return;
            
            isEditMode = false;
            renderOrderModal(order, false);
        };

        window.saveOrderEdit = async () => {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to edit orders', 'error');
                return;
            }

            const oldOrder = transfers.find(t => t.id == currentOrderId);
            const data = {
                plate: document.getElementById('edit-plate').value.trim(),
                name: document.getElementById('edit-name').value.trim(),
                phone: document.getElementById('edit-phone').value.trim(),
                amount: parseFloat(document.getElementById('edit-amount').value) || 0,
                franchise: parseFloat(document.getElementById('edit-franchise').value) || 0,
                status: document.getElementById('edit-status').value,
                serviceDate: document.getElementById('edit-service-date').value
            };

            // Validation
            if (!data.plate || !data.name) {
                showToast('Validation Error', 'Plate and name are required', 'error');
                return;
            }

            try {
                await fetchAPI(`update_transfer&id=${currentOrderId}`, 'POST', data);
                
                // Check if SMS should be sent
                const sendSmsChecked = document.getElementById('send-sms-on-save').checked;
                const statusChanged = oldOrder.status !== data.status;
                
                if (sendSmsChecked && statusChanged && data.phone) {
                    const publicUrl = window.location.origin + window.location.pathname.replace('vehicles.php', 'public_view.php');
                    const serviceDate = data.serviceDate ? new Date(data.serviceDate).toLocaleString('ka-GE', { 
                        month: 'long', 
                        day: 'numeric', 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    }) : 'არ არის დანიშნული';
                    
                    const templateData = {
                        name: data.name,
                        plate: data.plate,
                        amount: data.amount,
                        date: serviceDate,
                        link: `${publicUrl}?id=${currentOrderId}`
                    };

                    // Send SMS based on status
                    let smsType = null;
                    switch(data.status) {
                        case 'Processing':
                            smsType = 'registered';
                            break;
                        case 'Called':
                            smsType = 'called';
                            break;
                        case 'Scheduled':
                            smsType = 'schedule';
                            break;
                        case 'Parts Ordered':
                            smsType = 'parts_ordered';
                            break;
                        case 'Parts Arrived':
                            smsType = 'parts_arrived';
                            break;
                        case 'Completed':
                            smsType = 'completed';
                            break;
                        case 'Issue':
                            smsType = 'issue';
                            break;
                    }

                    if (smsType) {
                        const message = getFormattedMessage(smsType, templateData);
                        await sendSMS(data.phone, message, `${smsType}_from_vehicles`);
                        showToast('Order Updated', 'Changes saved and SMS notification sent', 'success');
                    } else {
                        showToast('Order Updated', 'Changes saved (no SMS sent for this status)', 'success');
                    }
                } else if (sendSmsChecked && !data.phone) {
                    showToast('Order Updated', 'Changes saved but no phone number for SMS', 'info');
                } else {
                    showToast('Order Updated', 'Service order has been updated successfully', 'success');
                }
                
                // Update local array
                const idx = transfers.findIndex(t => t.id == currentOrderId);
                if (idx !== -1) {
                    transfers[idx] = { ...transfers[idx], ...data };
                }
                
                // Reload data and close modal
                await loadData();
                window.closeOrderModal();
            } catch (err) {
                console.error('Save error:', err);
                showToast('Error', 'Failed to update order', 'error');
            }
        };

        window.closeOrderModal = () => {
            document.getElementById('order-modal').classList.add('hidden');
            currentOrderId = null;
            isEditMode = false;
        };

        window.editVehicle = (id) => {
            const v = vehicles.find(i => i.id == id);
            if (!v) {
                console.error('Vehicle not found:', id);
                showToast('Error', 'Vehicle not found', 'error');
                return;
            }
            
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
                        <div class="bg-white p-3 rounded-lg border border-slate-200 hover:border-indigo-300 hover:shadow-md transition-all cursor-pointer" onclick="window.openOrderModal(${s.id})">
                            <div class="flex justify-between items-start mb-1">
                                <div class="flex flex-col gap-1">
                                    <span class="font-semibold text-slate-700">${s.name}</span>
                                    <span class="text-[9px] font-mono text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-200 w-fit">Order #${s.id}</span>
                                </div>
                                <span class="text-[10px] ${statusClass} px-2 py-0.5 rounded-full font-bold">${s.status}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 flex items-center gap-3 mt-2">
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
                plate: document.getElementById('veh-plate').value.trim(),
                ownerName: document.getElementById('veh-owner').value.trim(),
                phone: document.getElementById('veh-phone').value.trim(),
                model: document.getElementById('veh-model').value.trim()
            };
            
            // Validation
            if (!data.plate) {
                showToast('Validation Error', 'Plate number is required', 'error');
                return;
            }
            
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
        const searchInput = document.getElementById('vehicle-search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                currentVehiclesPage = 1; // Reset to first page on search
                renderVehicleTable(1);
            });
        }
        
        // Add filter and sort listeners
        const statusFilter = document.getElementById('status-filter');
        const sortSelect = document.getElementById('sort-select');
        
        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                currentVehiclesPage = 1; // Reset to first page on filter
                renderVehicleTable(1);
            });
        }
        
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                currentVehiclesPage = 1; // Reset to first page on sort
                renderVehicleTable(1);
            });
        }

        // Initialize - Ensure modal is hidden and render table
        
        // Ensure vehicle modal is hidden on page load
        document.getElementById('vehicle-modal')?.classList.add('hidden');
        
        try {
            renderVehicleTable();
        } catch(e) {
            console.error('Render error:', e);
        }
        
        loadData();
        loadSMSTemplates(); // Load SMS templates from API
        lucide.createIcons();
        
        console.log('Initialization complete');
    </script>
</body>
</html>
