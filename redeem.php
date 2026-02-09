<?php
require_once 'session_config.php';
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'viewer';
$user_name = $_SESSION['username'] ?? 'User';

// Only allow admin, manager, and operator roles to access this page
$allowed_roles = ['admin', 'manager', 'operator'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ვაუჩერის გამოყენება | OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['BPG Arial Caps', 'BPG Arial', 'Inter', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        body { background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); min-height: 100vh; }
        .card-enter { animation: cardEnter 0.4s ease forwards; }
        @keyframes cardEnter {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .offer-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="flex items-center justify-center p-4 font-sans">

    <div class="w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-6">
            <?php if ($user_role !== 'operator'): ?>
            <a href="index.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700 text-sm mb-4">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                უკან დაბრუნება
            </a>
            <?php else: ?>
            <div class="flex justify-between items-center mb-4">
                <span class="text-sm text-slate-500"><?php echo htmlspecialchars($user_name); ?> (ოპერატორი)</span>
                <a href="logout.php" class="inline-flex items-center gap-1 text-red-500 hover:text-red-700 text-sm">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    გასვლა
                </a>
            </div>
            <?php endif; ?>
            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-violet-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-purple-500/30">
                <i data-lucide="ticket-check" class="w-8 h-8 text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">ვაუჩერის გამოყენება</h1>
            <p class="text-slate-500 text-sm mt-1">შეიყვანეთ მომხმარებლის ტელეფონის ნომერი</p>
        </div>

        <!-- Phone Lookup Card -->
        <div id="lookup-card" class="bg-white rounded-2xl shadow-xl p-6 card-enter">
            <!-- Phone Input -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    <i data-lucide="phone" class="w-4 h-4 inline mr-1"></i>
                    მომხმარებლის ტელეფონი
                </label>
                <input type="tel" id="customer-phone" placeholder="მაგ: 511123456" 
                    class="w-full px-4 py-4 border-2 border-slate-200 rounded-xl focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 outline-none transition text-xl text-center tracking-wider"
                    maxlength="15" autocomplete="off">
            </div>

            <!-- Search Button -->
            <button onclick="lookupOffers()" id="lookup-btn" 
                class="w-full bg-gradient-to-r from-purple-500 to-violet-600 text-white py-4 px-6 rounded-xl font-bold text-lg hover:from-purple-600 hover:to-violet-700 transition-all shadow-lg shadow-purple-500/30 active:scale-[0.98] flex items-center justify-center gap-2">
                <i data-lucide="search" class="w-5 h-5"></i>
                ვაუჩერების ძებნა
            </button>
        </div>

        <!-- Offers List Card (hidden initially) -->
        <div id="offers-card" class="hidden bg-white rounded-2xl shadow-xl p-6 card-enter">
            <!-- Customer Info -->
            <div class="bg-slate-50 rounded-xl p-4 mb-4 flex items-center gap-3">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <i data-lucide="user" class="w-6 h-6 text-purple-600"></i>
                </div>
                <div>
                    <div id="customer-name" class="font-bold text-slate-800">მომხმარებელი</div>
                    <div id="customer-phone-display" class="text-sm text-slate-500">...</div>
                </div>
                <button onclick="resetForm()" class="ml-auto p-2 hover:bg-slate-200 rounded-lg transition">
                    <i data-lucide="x" class="w-5 h-5 text-slate-400"></i>
                </button>
            </div>

            <!-- Offers List -->
            <div id="offers-list" class="space-y-3 mb-4">
                <!-- Offers will be rendered here -->
            </div>

            <!-- No Offers State -->
            <div id="no-offers" class="hidden text-center py-6">
                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="inbox" class="w-8 h-8 text-slate-400"></i>
                </div>
                <p class="text-slate-500">არ მოიძებნა აქტიური ვაუჩერები</p>
            </div>

            <!-- Back Button -->
            <button onclick="resetForm()" class="w-full mt-4 bg-slate-100 text-slate-600 py-3 px-6 rounded-xl font-semibold hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                სხვა ნომრის ძებნა
            </button>
        </div>

        <!-- Success State -->
        <div id="success-card" class="hidden bg-white rounded-2xl shadow-xl p-6 text-center card-enter">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 ring-8 ring-green-50">
                <i data-lucide="check-circle" class="w-10 h-10 text-green-500"></i>
            </div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">წარმატებით გამოყენებულია!</h2>
            <p id="success-message" class="text-slate-500 mb-6">ვაუჩერი გამოყენებულია.</p>
            
            <div id="success-details" class="bg-slate-50 rounded-xl p-4 mb-6 text-left">
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-slate-500">ვაუჩერი:</div>
                    <div id="success-title" class="font-bold text-slate-800">—</div>
                    <div class="text-slate-500">მომხმარებელი:</div>
                    <div id="success-customer" class="font-bold text-slate-800">—</div>
                    <div class="text-slate-500">ფასდაკლება:</div>
                    <div id="success-discount" class="font-bold text-purple-600">—</div>
                </div>
            </div>

            <button onclick="resetForm()" class="w-full bg-gradient-to-r from-purple-500 to-violet-600 text-white py-3 px-6 rounded-xl font-bold hover:from-purple-600 hover:to-violet-700 transition-all flex items-center justify-center gap-2">
                <i data-lucide="plus" class="w-5 h-5"></i>
                ახალი გამოყენება
            </button>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-sm text-slate-400">
            <span>შესულია როგორც: <strong class="text-slate-600"><?php echo htmlspecialchars($user_name); ?></strong></span>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        const API_URL = 'api.php';
        const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        let currentPhone = '';
        let currentCustomerName = '';
        let availableOffers = [];

        // Initialize icons
        lucide.createIcons();

        // Phone number formatting
        document.getElementById('customer-phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Enter key handling
        document.getElementById('customer-phone').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') lookupOffers();
        });

        async function fetchAPI(action, method = 'GET', body = null) {
            const options = {
                method,
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'include'
            };
            if (body) options.body = JSON.stringify(body);
            const res = await fetch(`${API_URL}?action=${action}`, options);
            return res.json();
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            };
            const toast = document.createElement('div');
            toast.className = `${colors[type]} text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-2`;
            toast.innerHTML = `<span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function formatPhone(phone) {
            if (!phone) return '';
            const clean = phone.replace(/\D/g, '');
            if (clean.length === 9) {
                return `${clean.slice(0, 3)} ${clean.slice(3, 5)} ${clean.slice(5, 7)} ${clean.slice(7)}`;
            }
            return phone;
        }

        function getDiscountText(offer) {
            if (offer.discount_type === 'percentage') {
                return `${parseFloat(offer.discount_value)}% ფასდაკლება`;
            } else if (offer.discount_type === 'fixed') {
                return `${parseFloat(offer.discount_value)}₾ ფასდაკლება`;
            } else {
                return 'უფასო სერვისი';
            }
        }

        async function lookupOffers() {
            const phone = document.getElementById('customer-phone').value.trim();
            if (!phone || phone.length < 9) {
                showToast('შეიყვანეთ სწორი ტელეფონის ნომერი', 'error');
                return;
            }

            const btn = document.getElementById('lookup-btn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> იძებნება...';
            lucide.createIcons();

            try {
                const data = await fetchAPI(`get_offers_for_phone&phone=${encodeURIComponent(phone)}`);
                
                if (data.status === 'success') {
                    currentPhone = phone;
                    currentCustomerName = data.customer_name || 'მომხმარებელი';
                    availableOffers = data.offers || [];

                    // Show offers card
                    document.getElementById('lookup-card').classList.add('hidden');
                    document.getElementById('offers-card').classList.remove('hidden');

                    // Update customer info
                    document.getElementById('customer-name').textContent = currentCustomerName;
                    document.getElementById('customer-phone-display').textContent = formatPhone(phone);

                    // Render offers
                    renderOffers();
                } else {
                    showToast(data.message || 'შეცდომა', 'error');
                }
            } catch (e) {
                console.error(e);
                showToast('კავშირის შეცდომა', 'error');
            }

            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="search" class="w-5 h-5"></i> ვაუჩერების ძებნა';
            lucide.createIcons();
        }

        function renderOffers() {
            const container = document.getElementById('offers-list');
            const noOffers = document.getElementById('no-offers');

            if (availableOffers.length === 0) {
                container.innerHTML = '';
                noOffers.classList.remove('hidden');
                lucide.createIcons();
                return;
            }

            noOffers.classList.add('hidden');
            container.innerHTML = availableOffers.map(offer => `
                <div class="offer-card bg-gradient-to-r from-purple-50 to-violet-50 border-2 border-purple-200 rounded-xl p-4 transition-all cursor-pointer hover:border-purple-400 hover:shadow-md" onclick="redeemOffer(${offer.id})">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="font-bold text-slate-800">${escapeHtml(offer.title)}</div>
                            <div class="text-sm text-purple-600 font-semibold">${getDiscountText(offer)}</div>
                            <div class="text-xs text-slate-400 mt-1">კოდი: ${offer.code}</div>
                        </div>
                        <button onclick="event.stopPropagation(); redeemOffer(${offer.id})" class="ml-3 bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg font-semibold text-sm transition flex items-center gap-1">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            გამოყენება
                        </button>
                    </div>
                </div>
            `).join('');

            lucide.createIcons();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        async function redeemOffer(offerId) {
            const offer = availableOffers.find(o => o.id == offerId);
            if (!offer) return;

            // Confirm
            if (!confirm(`გამოიყენოს "${offer.title}" ვაუჩერი ${currentCustomerName}-სთვის?`)) {
                return;
            }

            showToast('მუშავდება...', 'info');

            try {
                const result = await fetchAPI('admin_redeem_offer', 'POST', {
                    offer_id: offerId,
                    customer_phone: currentPhone,
                    notes: ''
                });

                if (result.status === 'success') {
                    showSuccess(offer, result.customer_name || currentCustomerName);
                } else {
                    showToast(result.message || 'შეცდომა', 'error');
                }
            } catch (e) {
                console.error(e);
                showToast('კავშირის შეცდომა', 'error');
            }
        }

        function showSuccess(offer, customerName) {
            document.getElementById('lookup-card').classList.add('hidden');
            document.getElementById('offers-card').classList.add('hidden');
            document.getElementById('success-card').classList.remove('hidden');

            document.getElementById('success-message').textContent = `ვაუჩერი გამოყენებულია ${customerName}-სთვის`;
            document.getElementById('success-title').textContent = offer.title;
            document.getElementById('success-customer').textContent = `${customerName} (${formatPhone(currentPhone)})`;
            document.getElementById('success-discount').textContent = getDiscountText(offer);

            lucide.createIcons();
        }

        function resetForm() {
            document.getElementById('customer-phone').value = '';
            currentPhone = '';
            currentCustomerName = '';
            availableOffers = [];

            document.getElementById('success-card').classList.add('hidden');
            document.getElementById('offers-card').classList.add('hidden');
            document.getElementById('lookup-card').classList.remove('hidden');

            document.getElementById('customer-phone').focus();
            lucide.createIcons();
        }

        // Focus on phone input on load
        document.getElementById('customer-phone').focus();
    </script>
</body>
</html>
