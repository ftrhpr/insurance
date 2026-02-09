<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Special Offer | OTOMOTORS</title>
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
                        primary: '#a855f7',
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444',
                    }
                }
            }
        }
    </script>
    <style>
        body { background: linear-gradient(135deg, #faf5ff 0%, #f0e7fe 50%, #ede9fe 100%); min-height: 100vh; }
        .card-enter { animation: cardEnter 0.5s ease forwards; }
        @keyframes cardEnter {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .shimmer {
            background: linear-gradient(90deg, transparent 30%, rgba(255,255,255,0.4) 50%, transparent 70%);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        .confetti { animation: confettiDrop 1s ease forwards; }
        @keyframes confettiDrop {
            from { opacity: 0; transform: translateY(-20px) rotate(0deg); }
            to { opacity: 1; transform: translateY(0) rotate(5deg); }
        }
    </style>
</head>
<body class="flex items-center justify-center p-4 font-sans">

    <!-- Loading State -->
    <div id="loader" class="text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500 mx-auto"></div>
        <p class="mt-3 text-purple-400 font-medium">Loading offer...</p>
    </div>

    <!-- Main Card -->
    <div id="card" class="hidden max-w-lg w-full bg-white rounded-3xl shadow-2xl shadow-purple-500/10 overflow-hidden">
        <!-- Header -->
        <div id="header" class="bg-gradient-to-br from-purple-500 via-violet-500 to-purple-600 text-white px-6 py-10 text-center relative overflow-hidden">
            <!-- Decorative circles -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full translate-y-1/2 -translate-x-1/2"></div>
            <div class="relative z-10">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm" id="header-icon-container">
                    <i data-lucide="ticket" class="w-8 h-8"></i>
                </div>
                <h1 id="header-title" class="text-2xl font-bold mb-1">Special Offer</h1>
                <p class="opacity-80 text-sm">OTOMOTORS</p>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <!-- Offer Title -->
            <div class="text-center mb-5">
                <h2 id="offer-title" class="text-2xl font-bold text-slate-800">...</h2>
                <p id="offer-description" class="text-sm text-slate-500 mt-2"></p>
            </div>

            <!-- Discount Badge -->
            <div class="flex justify-center mb-6">
                <div id="discount-badge" class="bg-gradient-to-r from-purple-500 to-violet-500 text-white px-8 py-4 rounded-2xl text-center shadow-lg shadow-purple-500/30">
                    <div id="discount-value" class="text-4xl font-extrabold">—</div>
                    <div id="discount-label" class="text-sm font-medium opacity-90 mt-1">ფასდაკლება</div>
                </div>
            </div>

            <!-- Offer Details -->
            <div class="space-y-3 mb-6">
                <div id="offer-code-row" class="flex items-center justify-between bg-purple-50 border border-purple-100 rounded-xl px-4 py-3">
                    <div class="flex items-center gap-2 text-sm text-purple-700">
                        <i data-lucide="hash" class="w-4 h-4"></i>
                        <span class="font-medium">Offer Code</span>
                    </div>
                    <code id="offer-code" class="bg-white border border-purple-200 px-3 py-1 rounded-lg text-sm font-bold text-purple-700 tracking-wider">—</code>
                </div>
                <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span class="font-medium">Valid Until</span>
                    </div>
                    <span id="offer-validity" class="text-sm font-bold text-slate-700">—</span>
                </div>
                <div id="min-order-row" class="hidden flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <i data-lucide="banknote" class="w-4 h-4"></i>
                        <span class="font-medium">Minimum Order</span>
                    </div>
                    <span id="offer-min-order" class="text-sm font-bold text-slate-700">—</span>
                </div>
            </div>

            <!-- Redeem Form (active state) -->
            <div id="redeem-form">
                <div class="space-y-3 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">თქვენი სახელი *</label>
                        <input type="text" id="customer-name" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:border-purple-500 focus:outline-none transition text-sm" placeholder="სახელი და გვარი">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">ტელეფონის ნომერი *</label>
                        <input type="tel" id="customer-phone" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 focus:border-purple-500 focus:outline-none transition text-sm" placeholder="5XXXXXXXX">
                    </div>
                </div>
                <button onclick="redeemOffer()" id="redeem-btn" class="w-full bg-gradient-to-r from-purple-500 to-violet-600 text-white py-3.5 px-4 rounded-xl font-bold hover:from-purple-600 hover:to-violet-700 transition-all flex items-center justify-center gap-2 shadow-lg shadow-purple-500/30 active:scale-[0.98]">
                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                    <span>გამოყენება</span>
                </button>
            </div>

            <!-- Success State -->
            <div id="success-state" class="hidden text-center py-4">
                <div class="confetti">
                    <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4 ring-8 ring-green-50/50">
                        <i data-lucide="check" class="w-10 h-10 text-green-500"></i>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">შეთავაზება გამოყენებულია!</h3>
                <p class="text-slate-500 text-sm mb-4">თქვენი ფასდაკლება აქტივირებულია. გთხოვთ მოგვმართოთ სერვისის დროს.</p>
                <div class="bg-green-50 border border-green-100 rounded-xl p-4 text-left">
                    <p class="text-xs text-green-600 font-semibold uppercase tracking-wider mb-1">შეთავაზების კოდი</p>
                    <p id="success-code" class="text-lg font-bold text-green-700">—</p>
                </div>
            </div>

            <!-- Expired / Inactive State -->
            <div id="inactive-state" class="hidden text-center py-4">
                <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-4 ring-8 ring-amber-50/50">
                    <i data-lucide="clock" class="w-10 h-10 text-amber-400"></i>
                </div>
                <h3 id="inactive-title" class="text-xl font-bold text-slate-800 mb-2">შეთავაზება ვადაგასულია</h3>
                <p id="inactive-message" class="text-slate-500 text-sm">ეს შეთავაზება აღარ არის აქტიური.</p>
            </div>

            <!-- Already Redeemed State -->
            <div id="exhausted-state" class="hidden text-center py-4">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 ring-8 ring-slate-50">
                    <i data-lucide="ban" class="w-10 h-10 text-slate-400"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">შეთავაზება ამოწურულია</h3>
                <p class="text-slate-500 text-sm">ეს შეთავაზება მაქსიმალურ რაოდენობას მიაღწია.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-slate-50 text-center py-4 border-t border-slate-100">
            <a href="https://api.whatsapp.com/send?phone=995511144486" class="inline-flex items-center gap-2 text-purple-600 font-bold hover:text-purple-700 transition-colors text-sm">
                <i data-lucide="phone" class="w-4 h-4"></i>
                მენეჯერთან დაკავშირება
            </a>
        </div>
    </div>

    <!-- Error State -->
    <div id="error-state" class="hidden text-center max-w-md w-full bg-white rounded-3xl shadow-2xl p-8">
        <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-triangle" class="w-10 h-10 text-red-500"></i>
        </div>
        <h3 class="text-xl font-bold mb-2 text-slate-800">შეთავაზება ვერ მოიძებნა</h3>
        <p class="text-slate-500 text-sm">ეს ბმული არასწორია ან ვადაგასულია.</p>
    </div>

    <script>
        const API_URL = 'api.php';
        const urlParams = new URLSearchParams(window.location.search);
        const offerCode = (urlParams.get('code') || '').toUpperCase().trim();
        let currentOffer = null;

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(text || ''));
            return div.innerHTML;
        }

        async function init() {
            if (!offerCode || !/^[A-Z0-9]{6,12}$/.test(offerCode)) {
                return showError();
            }

            try {
                const res = await fetch(`${API_URL}?action=get_public_offer&code=${encodeURIComponent(offerCode)}`);
                const data = await res.json();

                if (data.error) {
                    return showError();
                }

                currentOffer = data;
                renderOffer(data);
            } catch (e) {
                console.error('Fetch error:', e);
                showError();
            }
        }

        function renderOffer(data) {
            const loader = document.getElementById('loader');
            const card = document.getElementById('card');

            loader.style.opacity = '0';
            setTimeout(() => loader.classList.add('hidden'), 300);

            card.classList.remove('hidden');
            card.classList.add('card-enter');

            // Set offer info
            document.getElementById('offer-title').textContent = data.title || 'Special Offer';
            document.getElementById('offer-description').textContent = data.description || '';
            document.getElementById('offer-code').textContent = data.code;

            // Discount display
            if (data.discount_type === 'percentage') {
                document.getElementById('discount-value').textContent = `${parseFloat(data.discount_value)}%`;
                document.getElementById('discount-label').textContent = 'ფასდაკლება';
            } else if (data.discount_type === 'fixed') {
                document.getElementById('discount-value').textContent = `${parseFloat(data.discount_value)}₾`;
                document.getElementById('discount-label').textContent = 'ფასდაკლება';
            } else {
                document.getElementById('discount-value').textContent = '🎁';
                document.getElementById('discount-label').textContent = 'უფასო სერვისი';
            }

            // Validity
            if (data.valid_until) {
                const d = new Date(data.valid_until.replace(' ', 'T'));
                document.getElementById('offer-validity').textContent = d.toLocaleDateString('ka-GE', {
                    day: 'numeric', month: 'long', year: 'numeric'
                });
            }

            // Min order
            if (data.min_order_amount && parseFloat(data.min_order_amount) > 0) {
                document.getElementById('min-order-row').classList.remove('hidden');
                document.getElementById('offer-min-order').textContent = `${parseFloat(data.min_order_amount)}₾`;
            }

            // If offer has target name, pre-fill customer name
            if (data.target_name) {
                document.getElementById('customer-name').value = data.target_name;
            }

            // State checks
            if (data.status === 'expired') {
                document.getElementById('redeem-form').classList.add('hidden');
                document.getElementById('inactive-state').classList.remove('hidden');
                // Change header to gray
                document.getElementById('header').className = 'bg-gradient-to-br from-slate-400 to-slate-500 text-white px-6 py-10 text-center relative overflow-hidden';
            } else if (data.status === 'paused') {
                document.getElementById('redeem-form').classList.add('hidden');
                document.getElementById('inactive-state').classList.remove('hidden');
                document.getElementById('inactive-title').textContent = 'შეთავაზება დროებით შეჩერებულია';
                document.getElementById('inactive-message').textContent = 'ეს შეთავაზება ამჟამად მიუწვდომელია.';
                document.getElementById('header').className = 'bg-gradient-to-br from-amber-400 to-amber-500 text-white px-6 py-10 text-center relative overflow-hidden';
            } else if (data.is_exhausted) {
                document.getElementById('redeem-form').classList.add('hidden');
                document.getElementById('exhausted-state').classList.remove('hidden');
                document.getElementById('header').className = 'bg-gradient-to-br from-slate-400 to-slate-500 text-white px-6 py-10 text-center relative overflow-hidden';
            }

            lucide.createIcons();
        }

        async function redeemOffer() {
            const name = document.getElementById('customer-name').value.trim();
            const phone = document.getElementById('customer-phone').value.trim();

            if (!name) {
                alert('გთხოვთ შეიყვანოთ სახელი');
                document.getElementById('customer-name').focus();
                return;
            }
            if (!phone) {
                alert('გთხოვთ შეიყვანოთ ტელეფონის ნომერი');
                document.getElementById('customer-phone').focus();
                return;
            }

            const btn = document.getElementById('redeem-btn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> <span>მიმდინარეობს...</span>';
            lucide.createIcons();

            try {
                const res = await fetch(`${API_URL}?action=redeem_offer`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        code: offerCode,
                        customer_name: name,
                        customer_phone: phone
                    })
                });
                const data = await res.json();

                if (data.status === 'success') {
                    showSuccess();
                } else {
                    alert(data.message || 'Error occurred');
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> <span>გამოყენება</span>';
                    lucide.createIcons();
                }
            } catch (e) {
                alert('კავშირის შეცდომა. გთხოვთ სცადოთ ხელახლა.');
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> <span>გამოყენება</span>';
                lucide.createIcons();
            }
        }

        function showSuccess() {
            document.getElementById('redeem-form').classList.add('hidden');
            document.getElementById('success-state').classList.remove('hidden');
            document.getElementById('success-code').textContent = offerCode;

            // Change header to green
            document.getElementById('header').className = 'bg-gradient-to-br from-green-500 to-emerald-600 text-white px-6 py-10 text-center relative overflow-hidden';
            document.getElementById('header-title').textContent = 'გამოყენებულია ✓';
            document.getElementById('header-icon-container').innerHTML = '<i data-lucide="check-circle" class="w-8 h-8"></i>';

            lucide.createIcons();
        }

        function showError() {
            document.getElementById('loader').classList.add('hidden');
            document.getElementById('error-state').classList.remove('hidden');
            lucide.createIcons();
        }

        init();
    </script>
</body>
</html>
