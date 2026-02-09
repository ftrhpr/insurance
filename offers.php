<?php
require_once 'session_config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';

// Restrict technicians
if ($current_user_role === 'technician') {
    header('Location: technician_dashboard.php');
    exit();
}

require_once 'config.php';
require_once 'language.php';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offers | OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['BPG Arial Caps', 'BPG Arial', 'Inter', 'sans-serif'] },
                    colors: {
                        primary: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' },
                        accent: { 50: '#faf5ff', 100: '#f3e8ff', 500: '#a855f7', 600: '#9333ea', 700: '#7e22ce' }
                    }
                }
            }
        }
    </script>
    <style>
        body { background: #f8fafc; }
        .modal-backdrop { backdrop-filter: blur(4px); }
    </style>
</head>
<body class="min-h-screen font-sans">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 ml-64 p-8">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900 flex items-center gap-3">
                            <div class="p-2.5 bg-gradient-to-br from-accent-500 to-accent-700 rounded-xl shadow-lg shadow-accent-500/30">
                                <i data-lucide="ticket" class="w-6 h-6 text-white"></i>
                            </div>
                            Offers & Promotions
                        </h1>
                        <p class="text-slate-500 mt-1 ml-14">Create, manage and send promotional offers to customers</p>
                    </div>
                    <button onclick="openCreateModal()" class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-accent-500 to-accent-700 text-white font-semibold rounded-xl shadow-lg shadow-accent-500/30 hover:shadow-accent-500/50 transition-all active:scale-95">
                        <i data-lucide="plus" class="w-5 h-5"></i>
                        Create Offer
                    </button>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8" id="stats-row">
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-accent-100 rounded-lg"><i data-lucide="ticket" class="w-5 h-5 text-accent-600"></i></div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium uppercase">Total Offers</p>
                            <p class="text-2xl font-bold text-slate-800" id="stat-total">0</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-100 rounded-lg"><i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i></div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium uppercase">Active</p>
                            <p class="text-2xl font-bold text-green-600" id="stat-active">0</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 rounded-lg"><i data-lucide="refresh-cw" class="w-5 h-5 text-blue-600"></i></div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium uppercase">Total Redemptions</p>
                            <p class="text-2xl font-bold text-blue-600" id="stat-redeemed">0</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-amber-100 rounded-lg"><i data-lucide="clock" class="w-5 h-5 text-amber-600"></i></div>
                        <div>
                            <p class="text-xs text-slate-500 font-medium uppercase">Expired</p>
                            <p class="text-2xl font-bold text-amber-600" id="stat-expired">0</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Offers Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gradient-to-r from-slate-800 to-slate-900 text-white text-xs uppercase tracking-wider font-bold">
                            <tr>
                                <th class="px-5 py-4">Offer</th>
                                <th class="px-5 py-4">Code</th>
                                <th class="px-5 py-4">Discount</th>
                                <th class="px-5 py-4">Validity</th>
                                <th class="px-5 py-4">Views</th>
                                <th class="px-5 py-4">Redemptions</th>
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4">SMS</th>
                                <th class="px-5 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="offers-table-body" class="divide-y divide-slate-100">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
                <!-- Empty State -->
                <div id="empty-state" class="hidden py-16 text-center">
                    <div class="bg-accent-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="ticket" class="w-8 h-8 text-accent-300"></i>
                    </div>
                    <h3 class="text-slate-800 font-semibold text-lg">No offers yet</h3>
                    <p class="text-slate-400 text-sm mt-1">Click "Create Offer" to get started.</p>
                </div>
                <!-- Loading -->
                <div id="loading-state" class="py-16 text-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-500 mx-auto"></div>
                    <p class="mt-3 text-slate-400 text-sm">Loading offers...</p>
                </div>
            </div>
        </main>
    </div>

    <!-- ======================================= -->
    <!-- CREATE / EDIT OFFER MODAL -->
    <!-- ======================================= -->
    <div id="offer-modal" class="hidden fixed inset-0 bg-black/40 modal-backdrop flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <h2 id="modal-title" class="text-xl font-bold text-slate-800">Create Offer</h2>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <input type="hidden" id="edit-offer-id" value="">

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Title *</label>
                    <input type="text" id="offer-title" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition" placeholder="e.g. 10% ფასდაკლება">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                    <textarea id="offer-description" rows="2" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition" placeholder="Offer details..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Discount Type *</label>
                        <select id="offer-discount-type" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition" onchange="toggleDiscountValue()">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount (₾)</option>
                            <option value="free_service">Free Service</option>
                        </select>
                    </div>
                    <div id="discount-value-group">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Value *</label>
                        <input type="number" id="offer-discount-value" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition" min="0" step="0.01" placeholder="10">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Valid From *</label>
                        <input type="datetime-local" id="offer-valid-from" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Valid Until *</label>
                        <input type="datetime-local" id="offer-valid-until" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Max Redemptions</label>
                        <input type="number" id="offer-max-redemptions" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition" min="1" placeholder="Unlimited">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Min Order Amount (₾)</label>
                        <input type="number" id="offer-min-order" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition" min="0" step="0.01" placeholder="No minimum">
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Target Customer (optional)</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Name</label>
                            <input type="text" id="offer-target-name" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition" placeholder="Customer name">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Phone</label>
                            <input type="text" id="offer-target-phone" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition" placeholder="5XXXXXXXX">
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t border-slate-200 flex gap-3 justify-end">
                <button onclick="closeModal()" class="px-5 py-2.5 border-2 border-slate-200 rounded-xl font-semibold text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button onclick="saveOffer()" id="modal-save-btn" class="px-5 py-2.5 bg-gradient-to-r from-accent-500 to-accent-700 text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:scale-95">
                    <i data-lucide="save" class="w-4 h-4 inline mr-1"></i> Save Offer
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================= -->
    <!-- SEND SMS MODAL -->
    <!-- ======================================= -->
    <div id="sms-modal" class="hidden fixed inset-0 bg-black/40 modal-backdrop flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="send" class="w-5 h-5 text-accent-500"></i>
                    Send Offer via SMS
                </h2>
            </div>
            <div class="p-6 space-y-4">
                <input type="hidden" id="sms-offer-id" value="">
                <div class="bg-accent-50 border border-accent-200 rounded-xl p-4">
                    <p class="text-sm font-semibold text-accent-800" id="sms-offer-title">Offer Title</p>
                    <p class="text-xs text-accent-600 mt-1" id="sms-offer-code">Code: XXXXXXXX</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Customer Phone *</label>
                    <input type="text" id="sms-phone" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-accent-500 focus:outline-none transition" placeholder="5XXXXXXXX">
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                    <p class="text-xs text-slate-500 font-medium mb-1">SMS Preview:</p>
                    <p class="text-sm text-slate-700" id="sms-preview">...</p>
                </div>
            </div>
            <div class="p-6 border-t border-slate-200 flex gap-3 justify-end">
                <button onclick="closeSmsModal()" class="px-5 py-2.5 border-2 border-slate-200 rounded-xl font-semibold text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button onclick="sendOfferSms()" id="sms-send-btn" class="px-5 py-2.5 bg-gradient-to-r from-green-500 to-green-700 text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:scale-95">
                    <i data-lucide="send" class="w-4 h-4 inline mr-1"></i> Send SMS
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================= -->
    <!-- REDEMPTIONS MODAL -->
    <!-- ======================================= -->
    <div id="redemptions-modal" class="hidden fixed inset-0 bg-black/40 modal-backdrop flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[80vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="users" class="w-5 h-5 text-blue-500"></i>
                    Redemptions
                </h2>
                <button onclick="closeRedemptionsModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6" id="redemptions-content">
                <div class="text-center py-8 text-slate-400">Loading...</div>
            </div>
        </div>
    </div>

    <!-- ======================================= -->
    <!-- REDEEM OFFER MODAL -->
    <!-- ======================================= -->
    <div id="redeem-modal" class="hidden fixed inset-0 bg-black/40 modal-backdrop flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-500"></i>
                    Redeem Offer
                </h2>
            </div>
            <div class="p-6 space-y-4">
                <input type="hidden" id="redeem-offer-id" value="">
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                    <p class="text-sm font-semibold text-emerald-800" id="redeem-offer-title">Offer Title</p>
                    <p class="text-xs text-emerald-600 mt-1" id="redeem-offer-discount">Discount: —</p>
                    <code class="text-xs text-emerald-700 font-bold mt-1 block" id="redeem-offer-code">CODE</code>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Customer Phone *</label>
                    <input type="text" id="redeem-customer-phone" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-emerald-500 focus:outline-none transition" placeholder="5XXXXXXXX">
                    <p id="redeem-customer-info" class="text-xs text-slate-500 mt-1 hidden"></p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Notes (optional)</label>
                    <input type="text" id="redeem-notes" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-slate-300 focus:outline-none transition" placeholder="Order #, vehicle plate, etc.">
                </div>
            </div>
            <div class="p-6 border-t border-slate-200 flex gap-3 justify-end">
                <button onclick="closeRedeemModal()" class="px-5 py-2.5 border-2 border-slate-200 rounded-xl font-semibold text-slate-600 hover:bg-slate-50 transition">Cancel</button>
                <button onclick="submitRedemption()" id="redeem-submit-btn" class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-700 text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:scale-95">
                    <i data-lucide="check" class="w-4 h-4 inline mr-1"></i> Confirm Redemption
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================= -->
    <!-- BULK SEND SMS MODAL -->
    <!-- ======================================= -->
    <div id="bulk-sms-modal" class="hidden fixed inset-0 bg-black/40 modal-backdrop flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-hidden flex flex-col">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i data-lucide="users" class="w-5 h-5 text-blue-500"></i>
                    Bulk Send Offer
                </h2>
                <p class="text-sm text-slate-500 mt-1">Send this offer to multiple customers at once</p>
            </div>
            <div class="p-6 space-y-4 overflow-y-auto flex-1">
                <input type="hidden" id="bulk-offer-id" value="">
                
                <!-- Offer Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <p class="text-sm font-semibold text-blue-800" id="bulk-offer-title">Offer Title</p>
                    <p class="text-xs text-blue-600 mt-1" id="bulk-offer-discount">Discount: —</p>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Filter by Case Status</label>
                    <select id="bulk-status-filter" onchange="loadCustomersForBulk()" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2.5 focus:border-blue-500 focus:outline-none transition">
                        <option value="">All customers with phone numbers</option>
                        <option value="all_active">All active cases (not completed)</option>
                    </select>
                </div>

                <!-- Customer Count & Preview -->
                <div id="bulk-customer-count" class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="users" class="w-5 h-5 text-slate-500"></i>
                            <span class="font-semibold text-slate-700">Recipients:</span>
                            <span id="bulk-count" class="text-lg font-bold text-blue-600">0</span>
                        </div>
                        <button onclick="toggleCustomerList()" class="text-sm text-blue-600 hover:underline flex items-center gap-1">
                            <i data-lucide="eye" class="w-4 h-4"></i> Preview
                        </button>
                    </div>
                </div>

                <!-- Customer List Preview (hidden by default) -->
                <div id="bulk-customer-list" class="hidden bg-white border border-slate-200 rounded-xl max-h-48 overflow-y-auto">
                    <div class="p-3 text-sm text-slate-500">Loading...</div>
                </div>

                <!-- Progress Bar (hidden initially) -->
                <div id="bulk-progress" class="hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-slate-700">Sending...</span>
                        <span id="bulk-progress-text" class="text-sm text-slate-500">0 / 0</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2.5">
                        <div id="bulk-progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Results (hidden initially) -->
                <div id="bulk-results" class="hidden bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-center gap-2 text-green-700">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                        <span class="font-semibold" id="bulk-results-text">Sent to 0 customers</span>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t border-slate-200 flex gap-3 justify-end">
                <button onclick="closeBulkSmsModal()" class="px-5 py-2.5 border-2 border-slate-200 rounded-xl font-semibold text-slate-600 hover:bg-slate-50 transition">Close</button>
                <button onclick="sendBulkSms()" id="bulk-send-btn" class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition active:scale-95">
                    <i data-lucide="send" class="w-4 h-4 inline mr-1"></i> Send to All
                </button>
            </div>
        </div>
    </div>

    <!-- Views Modal -->
    <div id="views-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i data-lucide="eye" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg" id="views-modal-title">Offer Views</h3>
                        <p class="text-sm text-slate-500" id="views-modal-subtitle">Loading...</p>
                    </div>
                </div>
                <button onclick="closeViewsModal()" class="p-2 hover:bg-slate-100 rounded-xl transition">
                    <i data-lucide="x" class="w-5 h-5 text-slate-400"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto">
                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-xl p-4 text-center">
                        <div class="text-3xl font-bold text-blue-600" id="views-total">0</div>
                        <div class="text-sm text-blue-600/70">Total Views</div>
                    </div>
                    <div class="bg-purple-50 rounded-xl p-4 text-center">
                        <div class="text-3xl font-bold text-purple-600" id="views-unique">0</div>
                        <div class="text-sm text-purple-600/70">Unique Visitors</div>
                    </div>
                </div>

                <!-- Views Table -->
                <div id="views-loading" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-500 border-t-transparent"></div>
                    <p class="mt-2 text-slate-500">Loading views...</p>
                </div>
                <div id="views-empty" class="hidden text-center py-8">
                    <i data-lucide="eye-off" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
                    <p class="text-slate-500">No views yet</p>
                </div>
                <div id="views-table-container" class="hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-xs text-slate-500 uppercase">
                                <th class="pb-3 font-semibold">#</th>
                                <th class="pb-3 font-semibold">Customer</th>
                                <th class="pb-3 font-semibold">Device</th>
                                <th class="pb-3 font-semibold">Viewed At</th>
                            </tr>
                        </thead>
                        <tbody id="views-table-body" class="divide-y divide-slate-100">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="p-6 border-t border-slate-200 flex justify-end">
                <button onclick="closeViewsModal()" class="px-5 py-2.5 border-2 border-slate-200 rounded-xl font-semibold text-slate-600 hover:bg-slate-50 transition">Close</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2"></div>

    <script>
        const API_URL = 'api.php';
        let allOffers = [];

        // ===========================
        // TOAST NOTIFICATION
        // ===========================
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            };
            const icons = {
                success: 'check-circle',
                error: 'alert-circle',
                info: 'info'
            };
            const toast = document.createElement('div');
            toast.className = `${colors[type]} text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-2 text-sm font-medium animate-slide-in`;
            toast.innerHTML = `<i data-lucide="${icons[type]}" class="w-4 h-4"></i> ${escapeHtml(message)}`;
            container.appendChild(toast);
            lucide.createIcons({ nodes: [toast] });
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(text || ''));
            return div.innerHTML;
        }

        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

        // ===========================
        // API HELPER
        // ===========================
        async function fetchAPI(action, method = 'GET', body = null) {
            const opts = { 
                method, 
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include'
            };
            // Add CSRF token for POST requests
            if (method === 'POST' && CSRF_TOKEN) {
                opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
            }
            if (body) opts.body = JSON.stringify(body);
            const url = `${API_URL}?action=${action}`;
            const res = await fetch(url, opts);
            if (!res.ok) {
                const errorData = await res.json().catch(() => ({}));
                throw new Error(errorData.error || errorData.message || `HTTP ${res.status}`);
            }
            return res.json();
        }

        // ===========================
        // LOAD OFFERS
        // ===========================
        async function loadOffers() {
            try {
                const data = await fetchAPI('get_offers');
                if (data.status === 'success') {
                    allOffers = data.offers || [];
                    renderOffers();
                    updateStats();
                    // Load view counts for each offer
                    loadAllViewCounts();
                } else {
                    showToast(data.message || 'Failed to load offers', 'error');
                }
            } catch (e) {
                showToast('Connection error', 'error');
            }
            document.getElementById('loading-state').classList.add('hidden');
        }

        async function loadAllViewCounts() {
            for (const o of allOffers) {
                try {
                    const data = await fetchAPI(`get_offer_views&offer_id=${o.id}`);
                    const el = document.getElementById(`view-count-${o.id}`);
                    if (el && data.status === 'success') {
                        el.textContent = data.unique || 0;
                    }
                } catch (e) {
                    // Ignore
                }
            }
        }

        // ===========================
        // VIEWS MODAL
        // ===========================
        async function viewOfferViews(offerId) {
            const modal = document.getElementById('views-modal');
            const loading = document.getElementById('views-loading');
            const empty = document.getElementById('views-empty');
            const tableContainer = document.getElementById('views-table-container');
            const tableBody = document.getElementById('views-table-body');
            
            // Find offer title
            const offer = allOffers.find(o => o.id == offerId);
            document.getElementById('views-modal-title').textContent = offer ? offer.title : 'Offer Views';
            document.getElementById('views-modal-subtitle').textContent = 'Loading...';
            
            // Reset state
            loading.classList.remove('hidden');
            empty.classList.add('hidden');
            tableContainer.classList.add('hidden');
            tableBody.innerHTML = '';
            
            modal.classList.remove('hidden');
            lucide.createIcons();
            
            try {
                const data = await fetchAPI(`get_offer_views&offer_id=${offerId}`);
                loading.classList.add('hidden');
                
                if (data.status === 'success') {
                    document.getElementById('views-total').textContent = data.total || 0;
                    document.getElementById('views-unique').textContent = data.unique || 0;
                    document.getElementById('views-modal-subtitle').textContent = `${data.total || 0} total views, ${data.unique || 0} unique`;
                    
                    if (!data.views || data.views.length === 0) {
                        empty.classList.remove('hidden');
                    } else {
                        tableContainer.classList.remove('hidden');
                        tableBody.innerHTML = data.views.map((v, i) => {
                            const device = parseUserAgent(v.user_agent || '');
                            const viewedAt = formatDateTime(v.viewed_at);
                            const customerInfo = v.phone 
                                ? `<div class="font-medium text-slate-700">${escapeHtml(v.customer_name || 'Unknown')}</div><div class="text-xs text-slate-400">${formatPhone(v.phone)}</div>`
                                : `<div class="text-slate-400 italic text-sm">No tracking link</div><div class="text-xs text-slate-300 font-mono">${maskIP(v.ip_address || '')}</div>`;
                            return `
                                <tr class="hover:bg-slate-50">
                                    <td class="py-3 text-slate-400 text-sm">${i + 1}</td>
                                    <td class="py-3">${customerInfo}</td>
                                    <td class="py-3">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="${device.icon}" class="w-4 h-4 text-slate-400"></i>
                                            <span class="text-sm text-slate-600">${device.name}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-sm text-slate-500">${viewedAt}</td>
                                </tr>
                            `;
                        }).join('');
                        lucide.createIcons();
                    }
                } else {
                    empty.classList.remove('hidden');
                    document.getElementById('views-modal-subtitle').textContent = 'Could not load views';
                }
            } catch (e) {
                loading.classList.add('hidden');
                empty.classList.remove('hidden');
                document.getElementById('views-modal-subtitle').textContent = 'Error loading views';
            }
        }

        function closeViewsModal() {
            document.getElementById('views-modal').classList.add('hidden');
        }

        function parseUserAgent(ua) {
            const uaLower = ua.toLowerCase();
            if (uaLower.includes('iphone') || uaLower.includes('ipad')) {
                return { icon: 'smartphone', name: 'iOS' };
            } else if (uaLower.includes('android')) {
                return { icon: 'smartphone', name: 'Android' };
            } else if (uaLower.includes('macintosh') || uaLower.includes('mac os')) {
                return { icon: 'laptop', name: 'Mac' };
            } else if (uaLower.includes('windows')) {
                return { icon: 'monitor', name: 'Windows' };
            } else if (uaLower.includes('linux')) {
                return { icon: 'monitor', name: 'Linux' };
            } else {
                return { icon: 'globe', name: 'Unknown' };
            }
        }

        function maskIP(ip) {
            if (!ip) return '-';
            const parts = ip.split('.');
            if (parts.length === 4) {
                return `${parts[0]}.${parts[1]}.*.*`;
            }
            return ip.substring(0, Math.min(8, ip.length)) + '...';
        }

        function formatPhone(phone) {
            if (!phone) return '';
            // Format Georgian phone number
            const clean = phone.replace(/\D/g, '');
            if (clean.startsWith('995') && clean.length === 12) {
                return `+995 ${clean.slice(3, 6)} ${clean.slice(6, 8)} ${clean.slice(8, 10)} ${clean.slice(10)}`;
            }
            if (clean.length === 9) {
                return `${clean.slice(0, 3)} ${clean.slice(3, 5)} ${clean.slice(5, 7)} ${clean.slice(7)}`;
            }
            return phone;
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return '-';
            try {
                const d = new Date(dateStr);
                return d.toLocaleString('ka-GE', { 
                    day: 'numeric', 
                    month: 'short', 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
            } catch (e) {
                return dateStr;
            }
        }

        // ===========================
        // RENDER TABLE
        // ===========================
        function renderOffers() {
            const tbody = document.getElementById('offers-table-body');
            const emptyState = document.getElementById('empty-state');

            if (allOffers.length === 0) {
                tbody.innerHTML = '';
                emptyState.classList.remove('hidden');
                return;
            }

            emptyState.classList.add('hidden');
            tbody.innerHTML = allOffers.map(o => {
                const statusBadge = getStatusBadge(o.status);
                const discountText = getDiscountDisplay(o);
                const validFrom = formatDate(o.valid_from);
                const validUntil = formatDate(o.valid_until);
                const maxR = o.max_redemptions ? o.max_redemptions : '∞';
                const smsBadge = o.sms_sent_at
                    ? `<span class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full">Sent</span>`
                    : `<span class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-0.5 rounded-full">Not sent</span>`;
                const targetInfo = o.target_name
                    ? `<div class="text-[10px] text-slate-400 mt-0.5">${escapeHtml(o.target_name)} ${o.target_phone ? escapeHtml(o.target_phone) : ''}</div>`
                    : '';

                return `<tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-5 py-4">
                        <div class="font-semibold text-slate-800 text-sm">${escapeHtml(o.title)}</div>
                        <div class="text-xs text-slate-400 mt-0.5 max-w-[200px] truncate">${escapeHtml(o.description || '')}</div>
                        ${targetInfo}
                    </td>
                    <td class="px-5 py-4">
                        <code class="bg-accent-50 text-accent-700 px-2 py-1 rounded-lg text-xs font-bold tracking-wider">${escapeHtml(o.code)}</code>
                    </td>
                    <td class="px-5 py-4">
                        <span class="text-sm font-bold text-slate-700">${discountText}</span>
                        ${o.min_order_amount > 0 ? `<div class="text-[10px] text-slate-400">Min: ${o.min_order_amount}₾</div>` : ''}
                    </td>
                    <td class="px-5 py-4">
                        <div class="text-xs text-slate-600">${validFrom}</div>
                        <div class="text-xs text-slate-400">→ ${validUntil}</div>
                    </td>
                    <td class="px-5 py-4">
                        <button onclick="viewOfferViews(${o.id})" class="text-sm font-bold text-purple-600 hover:underline cursor-pointer flex items-center gap-1">
                            <i data-lucide="eye" class="w-3 h-3"></i>
                            <span id="view-count-${o.id}">...</span>
                        </button>
                    </td>
                    <td class="px-5 py-4">
                        <button onclick="viewRedemptions(${o.id})" class="text-sm font-bold text-blue-600 hover:underline cursor-pointer">${o.times_redeemed || 0} / ${maxR}</button>
                    </td>
                    <td class="px-5 py-4">${statusBadge}</td>
                    <td class="px-5 py-4">${smsBadge}</td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                            ${o.status === 'active' ? `<button onclick="openRedeemModal(${o.id})" class="text-slate-400 hover:text-emerald-600 p-1.5 rounded-lg hover:bg-emerald-50 transition" title="Redeem for Customer"><i data-lucide="check-circle" class="w-4 h-4"></i></button>` : ''}
                            ${o.status === 'active' ? `<button onclick="openSmsModal(${o.id})" class="text-slate-400 hover:text-green-600 p-1.5 rounded-lg hover:bg-green-50 transition" title="Send SMS"><i data-lucide="send" class="w-4 h-4"></i></button>` : ''}
                            ${o.status === 'active' ? `<button onclick="openBulkSmsModal(${o.id})" class="text-slate-400 hover:text-blue-600 p-1.5 rounded-lg hover:bg-blue-50 transition" title="Bulk Send to Customers"><i data-lucide="users" class="w-4 h-4"></i></button>` : ''}
                            <button onclick="copyOfferLink(${o.id})" class="text-slate-400 hover:text-blue-600 p-1.5 rounded-lg hover:bg-blue-50 transition" title="Copy Link"><i data-lucide="link" class="w-4 h-4"></i></button>
                            <button onclick="editOffer(${o.id})" class="text-slate-400 hover:text-accent-600 p-1.5 rounded-lg hover:bg-accent-50 transition" title="Edit"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                            ${o.status === 'active' ? `<button onclick="toggleStatus(${o.id}, 'paused')" class="text-slate-400 hover:text-amber-600 p-1.5 rounded-lg hover:bg-amber-50 transition" title="Pause"><i data-lucide="pause" class="w-4 h-4"></i></button>` : ''}
                            ${o.status === 'paused' ? `<button onclick="toggleStatus(${o.id}, 'active')" class="text-slate-400 hover:text-green-600 p-1.5 rounded-lg hover:bg-green-50 transition" title="Activate"><i data-lucide="play" class="w-4 h-4"></i></button>` : ''}
                            <button onclick="deleteOffer(${o.id})" class="text-slate-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 transition" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');

            lucide.createIcons();
        }

        // ===========================
        // HELPERS
        // ===========================
        function getStatusBadge(status) {
            const map = {
                active: '<span class="bg-green-100 text-green-700 border border-green-200 text-xs font-bold px-2.5 py-1 rounded-full">Active</span>',
                paused: '<span class="bg-amber-100 text-amber-700 border border-amber-200 text-xs font-bold px-2.5 py-1 rounded-full">Paused</span>',
                expired: '<span class="bg-slate-100 text-slate-500 border border-slate-200 text-xs font-bold px-2.5 py-1 rounded-full">Expired</span>'
            };
            return map[status] || map.expired;
        }

        function getDiscountDisplay(o) {
            if (o.discount_type === 'percentage') return `${parseFloat(o.discount_value)}%`;
            if (o.discount_type === 'fixed') return `${parseFloat(o.discount_value)}₾`;
            return 'Free Service';
        }

        function formatDate(dt) {
            if (!dt) return '—';
            try {
                const d = new Date(dt.replace(' ', 'T'));
                return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: '2-digit' });
            } catch { return dt; }
        }

        function updateStats() {
            document.getElementById('stat-total').textContent = allOffers.length;
            document.getElementById('stat-active').textContent = allOffers.filter(o => o.status === 'active').length;
            document.getElementById('stat-redeemed').textContent = allOffers.reduce((s, o) => s + (parseInt(o.times_redeemed) || 0), 0);
            document.getElementById('stat-expired').textContent = allOffers.filter(o => o.status === 'expired').length;
        }

        // ===========================
        // CREATE / EDIT MODAL
        // ===========================
        function openCreateModal() {
            document.getElementById('edit-offer-id').value = '';
            document.getElementById('modal-title').textContent = 'Create Offer';
            document.getElementById('offer-title').value = '';
            document.getElementById('offer-description').value = '';
            document.getElementById('offer-discount-type').value = 'percentage';
            document.getElementById('offer-discount-value').value = '';
            document.getElementById('offer-valid-from').value = new Date().toISOString().slice(0, 16);
            document.getElementById('offer-valid-until').value = '';
            document.getElementById('offer-max-redemptions').value = '';
            document.getElementById('offer-min-order').value = '';
            document.getElementById('offer-target-name').value = '';
            document.getElementById('offer-target-phone').value = '';
            toggleDiscountValue();
            document.getElementById('offer-modal').classList.remove('hidden');
            lucide.createIcons();
        }

        function editOffer(id) {
            const o = allOffers.find(x => x.id == id);
            if (!o) return;
            document.getElementById('edit-offer-id').value = o.id;
            document.getElementById('modal-title').textContent = 'Edit Offer';
            document.getElementById('offer-title').value = o.title || '';
            document.getElementById('offer-description').value = o.description || '';
            document.getElementById('offer-discount-type').value = o.discount_type || 'percentage';
            document.getElementById('offer-discount-value').value = o.discount_value || '';
            document.getElementById('offer-valid-from').value = (o.valid_from || '').replace(' ', 'T').slice(0, 16);
            document.getElementById('offer-valid-until').value = (o.valid_until || '').replace(' ', 'T').slice(0, 16);
            document.getElementById('offer-max-redemptions').value = o.max_redemptions || '';
            document.getElementById('offer-min-order').value = o.min_order_amount || '';
            document.getElementById('offer-target-name').value = o.target_name || '';
            document.getElementById('offer-target-phone').value = o.target_phone || '';
            toggleDiscountValue();
            document.getElementById('offer-modal').classList.remove('hidden');
            lucide.createIcons();
        }

        function closeModal() {
            document.getElementById('offer-modal').classList.add('hidden');
        }

        function toggleDiscountValue() {
            const type = document.getElementById('offer-discount-type').value;
            const group = document.getElementById('discount-value-group');
            group.style.display = type === 'free_service' ? 'none' : 'block';
        }

        async function saveOffer() {
            const id = document.getElementById('edit-offer-id').value;
            const payload = {
                title: document.getElementById('offer-title').value.trim(),
                description: document.getElementById('offer-description').value.trim(),
                discount_type: document.getElementById('offer-discount-type').value,
                discount_value: parseFloat(document.getElementById('offer-discount-value').value) || 0,
                valid_from: document.getElementById('offer-valid-from').value,
                valid_until: document.getElementById('offer-valid-until').value,
                max_redemptions: document.getElementById('offer-max-redemptions').value || null,
                min_order_amount: document.getElementById('offer-min-order').value || null,
                target_name: document.getElementById('offer-target-name').value.trim() || null,
                target_phone: document.getElementById('offer-target-phone').value.trim() || null
            };

            if (!payload.title) return showToast('Title is required', 'error');
            if (!payload.valid_until) return showToast('Expiry date is required', 'error');
            if (payload.discount_type !== 'free_service' && payload.discount_value <= 0) return showToast('Discount value is required', 'error');

            const btn = document.getElementById('modal-save-btn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin inline mr-1"></i> Saving...';
            lucide.createIcons();

            try {
                const action = id ? 'update_offer' : 'create_offer';
                if (id) payload.id = parseInt(id);
                const data = await fetchAPI(action, 'POST', payload);
                if (data.status === 'success') {
                    showToast(id ? 'Offer updated' : 'Offer created');
                    closeModal();
                    loadOffers();
                } else {
                    showToast(data.message || 'Failed to save', 'error');
                }
            } catch (e) {
                showToast('Connection error', 'error');
            }

            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="save" class="w-4 h-4 inline mr-1"></i> Save Offer';
            lucide.createIcons();
        }

        // ===========================
        // SMS MODAL
        // ===========================
        function openSmsModal(id) {
            const o = allOffers.find(x => x.id == id);
            if (!o) return;
            document.getElementById('sms-offer-id').value = o.id;
            document.getElementById('sms-offer-title').textContent = o.title;
            document.getElementById('sms-offer-code').textContent = `Code: ${o.code}`;
            document.getElementById('sms-phone').value = o.target_phone || '';
            updateSmsPreview(o);
            document.getElementById('sms-modal').classList.remove('hidden');
            lucide.createIcons();

            // Live preview update
            document.getElementById('sms-phone').oninput = () => updateSmsPreview(o);
        }

        function updateSmsPreview(o) {
            const name = o.target_name || 'მომხმარებელო';
            let discountText = '';
            if (o.discount_type === 'percentage') discountText = `${parseFloat(o.discount_value)}% ფასდაკლება`;
            else if (o.discount_type === 'fixed') discountText = `${parseFloat(o.discount_value)}₾ ფასდაკლება`;
            else discountText = 'უფასო სერვისი';

            const link = `https://portal.otoexpress.ge/redeem_offer.php?code=${o.code}`;
            document.getElementById('sms-preview').textContent =
                `გამარჯობა ${name}, OTOMOTORS გთავაზობთ: ${o.title}! ${discountText}. გამოიყენეთ: ${link}`;
        }

        function closeSmsModal() {
            document.getElementById('sms-modal').classList.add('hidden');
        }

        async function sendOfferSms() {
            const offer_id = document.getElementById('sms-offer-id').value;
            const phone = document.getElementById('sms-phone').value.trim();
            if (!phone) return showToast('Phone number is required', 'error');

            const btn = document.getElementById('sms-send-btn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin inline mr-1"></i> Sending...';
            lucide.createIcons();

            try {
                const data = await fetchAPI('send_offer_sms', 'POST', { offer_id: parseInt(offer_id), phone });
                if (data.status === 'success') {
                    showToast('SMS sent successfully!');
                    closeSmsModal();
                    loadOffers();
                } else {
                    showToast(data.message || 'Failed to send SMS', 'error');
                }
            } catch (e) {
                showToast('Connection error', 'error');
            }

            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="send" class="w-4 h-4 inline mr-1"></i> Send SMS';
            lucide.createIcons();
        }

        // ===========================
        // ACTIONS
        // ===========================
        async function toggleStatus(id, newStatus) {
            try {
                const data = await fetchAPI('toggle_offer_status', 'POST', { id, status: newStatus });
                if (data.status === 'success') {
                    showToast(`Offer ${newStatus === 'active' ? 'activated' : 'paused'}`);
                    loadOffers();
                } else {
                    showToast(data.message || 'Failed', 'error');
                }
            } catch (e) {
                showToast('Connection error', 'error');
            }
        }

        async function deleteOffer(id) {
            if (!confirm('Are you sure you want to delete this offer? This cannot be undone.')) return;
            try {
                const data = await fetchAPI('delete_offer', 'POST', { id });
                if (data.status === 'success') {
                    showToast('Offer deleted');
                    loadOffers();
                } else {
                    showToast(data.message || 'Failed', 'error');
                }
            } catch (e) {
                showToast('Connection error', 'error');
            }
        }

        function copyOfferLink(id) {
            const o = allOffers.find(x => x.id == id);
            if (!o) return;
            const link = `https://portal.otoexpress.ge/redeem_offer.php?code=${o.code}`;
            navigator.clipboard.writeText(link).then(() => {
                showToast('Link copied to clipboard!', 'info');
            }).catch(() => {
                // Fallback
                const ta = document.createElement('textarea');
                ta.value = link;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast('Link copied!', 'info');
            });
        }

        // ===========================
        // REDEMPTIONS MODAL
        // ===========================
        async function viewRedemptions(id) {
            document.getElementById('redemptions-modal').classList.remove('hidden');
            document.getElementById('redemptions-content').innerHTML = '<div class="text-center py-8 text-slate-400"><div class="animate-spin rounded-full h-6 w-6 border-b-2 border-accent-500 mx-auto mb-2"></div>Loading...</div>';
            lucide.createIcons();

            try {
                const data = await fetchAPI(`get_offer_redemptions&offer_id=${id}`);
                const redemptions = data.redemptions || [];

                if (redemptions.length === 0) {
                    document.getElementById('redemptions-content').innerHTML = `
                        <div class="text-center py-8">
                            <div class="bg-slate-100 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3"><i data-lucide="inbox" class="w-6 h-6 text-slate-400"></i></div>
                            <p class="text-slate-500 text-sm">No redemptions yet</p>
                        </div>`;
                } else {
                    document.getElementById('redemptions-content').innerHTML = `
                        <div class="space-y-2">
                            ${redemptions.map(r => `
                                <div class="bg-slate-50 rounded-xl px-4 py-3 border border-slate-100">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-semibold text-slate-700 text-sm">${escapeHtml(r.customer_name || 'Unknown')}</div>
                                            <div class="text-xs text-slate-400">${escapeHtml(r.customer_phone || '—')}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-slate-500">${formatDateTime(r.redeemed_at)}</div>
                                            ${r.redeemed_by_name ? `<div class="text-[10px] text-blue-500 font-medium">by ${escapeHtml(r.redeemed_by_name)}</div>` : '<div class="text-[10px] text-slate-400">Self-redeemed</div>'}
                                        </div>
                                    </div>
                                    ${r.notes ? `<div class="text-xs text-slate-500 mt-2 bg-white rounded-lg px-2 py-1 border border-slate-100"><i data-lucide="file-text" class="w-3 h-3 inline mr-1"></i>${escapeHtml(r.notes)}</div>` : ''}
                                </div>
                            `).join('')}
                        </div>`;
                }
                lucide.createIcons();
            } catch (e) {
                document.getElementById('redemptions-content').innerHTML = '<div class="text-center py-8 text-red-500 text-sm">Failed to load redemptions</div>';
            }
        }

        function closeRedemptionsModal() {
            document.getElementById('redemptions-modal').classList.add('hidden');
        }

        // ===========================
        // REDEEM MODAL FUNCTIONS
        // ===========================
        function openRedeemModal(id) {
            const o = allOffers.find(x => x.id == id);
            if (!o) return;
            
            document.getElementById('redeem-offer-id').value = o.id;
            document.getElementById('redeem-offer-title').textContent = o.title;
            document.getElementById('redeem-offer-code').textContent = o.code;
            document.getElementById('redeem-offer-discount').textContent = 'Discount: ' + getDiscountDisplay(o);
            document.getElementById('redeem-customer-phone').value = o.target_phone || '';
            document.getElementById('redeem-notes').value = '';
            document.getElementById('redeem-customer-info').classList.add('hidden');
            
            document.getElementById('redeem-modal').classList.remove('hidden');
            lucide.createIcons();
            document.getElementById('redeem-customer-phone').focus();
        }

        function closeRedeemModal() {
            document.getElementById('redeem-modal').classList.add('hidden');
        }

        async function submitRedemption() {
            const offer_id = parseInt(document.getElementById('redeem-offer-id').value);
            const customer_phone = document.getElementById('redeem-customer-phone').value.trim();
            const notes = document.getElementById('redeem-notes').value.trim();

            if (!customer_phone) {
                showToast('Customer phone is required', 'error');
                document.getElementById('redeem-customer-phone').focus();
                return;
            }

            const btn = document.getElementById('redeem-submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 inline mr-1 animate-spin"></i> Processing...';
            lucide.createIcons();

            try {
                const data = await fetchAPI('admin_redeem_offer', 'POST', {
                    offer_id,
                    customer_phone,
                    notes
                });

                if (data.status === 'success') {
                    showToast('Offer redeemed successfully!', 'success');
                    closeRedeemModal();
                    loadOffers();
                } else {
                    showToast(data.message || 'Redemption failed', 'error');
                }
            } catch (e) {
                showToast(e.message || 'Connection error', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="check" class="w-4 h-4 inline mr-1"></i> Confirm Redemption';
                lucide.createIcons();
            }
        }

        function formatDateTime(dt) {
            if (!dt) return '—';
            try {
                const d = new Date(dt.replace(' ', 'T'));
                return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
            } catch { return dt; }
        }

        // ===========================
        // BULK SMS FUNCTIONS
        // ===========================
        let bulkCustomers = [];
        let allStatuses = [];

        async function loadStatuses() {
            if (allStatuses.length > 0) return;
            try {
                const data = await fetchAPI('get_statuses&type=case');
                if (data.status === 'success' && data.data) {
                    allStatuses = data.data;
                    const select = document.getElementById('bulk-status-filter');
                    allStatuses.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = s.name;
                        select.appendChild(opt);
                    });
                }
            } catch (e) {
                console.error('Failed to load statuses', e);
            }
        }

        async function openBulkSmsModal(id) {
            const o = allOffers.find(x => x.id == id);
            if (!o) return;

            document.getElementById('bulk-offer-id').value = o.id;
            document.getElementById('bulk-offer-title').textContent = o.title;
            document.getElementById('bulk-offer-discount').textContent = 'Discount: ' + getDiscountDisplay(o);
            document.getElementById('bulk-status-filter').value = '';
            document.getElementById('bulk-customer-list').classList.add('hidden');
            document.getElementById('bulk-progress').classList.add('hidden');
            document.getElementById('bulk-results').classList.add('hidden');
            document.getElementById('bulk-send-btn').disabled = false;
            document.getElementById('bulk-send-btn').innerHTML = '<i data-lucide="send" class="w-4 h-4 inline mr-1"></i> Send to All';

            document.getElementById('bulk-sms-modal').classList.remove('hidden');
            lucide.createIcons();

            await loadStatuses();
            await loadCustomersForBulk();
        }

        function closeBulkSmsModal() {
            document.getElementById('bulk-sms-modal').classList.add('hidden');
            bulkCustomers = [];
        }

        async function loadCustomersForBulk() {
            const statusFilter = document.getElementById('bulk-status-filter').value;
            document.getElementById('bulk-count').textContent = '...';
            document.getElementById('bulk-customer-list').innerHTML = '<div class="p-3 text-sm text-slate-500">Loading...</div>';

            try {
                const data = await fetchAPI(`get_customers_for_bulk_sms&status_id=${statusFilter}`);
                if (data.status === 'success') {
                    bulkCustomers = data.customers || [];
                    document.getElementById('bulk-count').textContent = bulkCustomers.length;

                    if (bulkCustomers.length === 0) {
                        document.getElementById('bulk-customer-list').innerHTML = '<div class="p-4 text-sm text-slate-500 text-center">No customers found</div>';
                    } else {
                        document.getElementById('bulk-customer-list').innerHTML = `
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 sticky top-0">
                                    <tr>
                                        <th class="text-left px-3 py-2 font-semibold text-slate-600">Name</th>
                                        <th class="text-left px-3 py-2 font-semibold text-slate-600">Phone</th>
                                        <th class="text-left px-3 py-2 font-semibold text-slate-600">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${bulkCustomers.map(c => `
                                        <tr class="border-t border-slate-100">
                                            <td class="px-3 py-2 text-slate-700">${escapeHtml(c.name)}</td>
                                            <td class="px-3 py-2 text-slate-500">${escapeHtml(c.phone)}</td>
                                            <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">${escapeHtml(c.status_name || c.status || 'Unknown')}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    }
                }
            } catch (e) {
                document.getElementById('bulk-count').textContent = '0';
                document.getElementById('bulk-customer-list').innerHTML = '<div class="p-4 text-sm text-red-500 text-center">Failed to load customers</div>';
            }
            lucide.createIcons();
        }

        function toggleCustomerList() {
            const list = document.getElementById('bulk-customer-list');
            list.classList.toggle('hidden');
        }

        async function sendBulkSms() {
            if (bulkCustomers.length === 0) {
                showToast('No customers to send to', 'error');
                return;
            }

            const offerId = parseInt(document.getElementById('bulk-offer-id').value);
            const btn = document.getElementById('bulk-send-btn');

            if (!confirm(`Send this offer to ${bulkCustomers.length} customers?`)) return;

            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 inline mr-1 animate-spin"></i> Sending...';
            lucide.createIcons();

            document.getElementById('bulk-progress').classList.remove('hidden');
            document.getElementById('bulk-results').classList.add('hidden');

            const phones = bulkCustomers.map(c => c.phone);

            try {
                const data = await fetchAPI('bulk_send_offer_sms', 'POST', {
                    offer_id: offerId,
                    phones: phones
                });

                document.getElementById('bulk-progress').classList.add('hidden');
                document.getElementById('bulk-results').classList.remove('hidden');

                if (data.status === 'success') {
                    document.getElementById('bulk-results-text').textContent = `Successfully sent to ${data.sent_count} customers`;
                    document.getElementById('bulk-results').className = 'bg-green-50 border border-green-200 rounded-xl p-4';
                    showToast(`Sent to ${data.sent_count} customers`, 'success');
                    loadOffers(); // Refresh to update SMS sent indicator
                } else {
                    document.getElementById('bulk-results-text').textContent = data.message || 'Failed to send';
                    document.getElementById('bulk-results').className = 'bg-red-50 border border-red-200 rounded-xl p-4';
                    showToast(data.message || 'Failed', 'error');
                }
            } catch (e) {
                document.getElementById('bulk-progress').classList.add('hidden');
                showToast(e.message || 'Connection error', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="send" class="w-4 h-4 inline mr-1"></i> Send to All';
                lucide.createIcons();
            }
        }

        // ===========================
        // INIT
        // ===========================
        document.addEventListener('DOMContentLoaded', () => {
            loadOffers();
            lucide.createIcons();
        });
    </script>
</body>
</html>
