<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>შეთავაზება | OTOMOTORS</title>
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
                <h1 id="header-title" class="text-2xl font-bold mb-1">შეთავაზება</h1>
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
                        <span class="font-medium">კოდი</span>
                    </div>
                    <code id="offer-code" class="bg-white border border-purple-200 px-3 py-1 rounded-lg text-sm font-bold text-purple-700 tracking-wider">—</code>
                </div>
                <div class="flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span class="font-medium">ძალაშია</span>
                    </div>
                    <span id="offer-validity" class="text-sm font-bold text-slate-700">—</span>
                </div>
                <div id="min-order-row" class="hidden flex items-center justify-between bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <i data-lucide="banknote" class="w-4 h-4"></i>
                        <span class="font-medium">მინ. შენაძენი</span>
                    </div>
                    <span id="offer-min-order" class="text-sm font-bold text-slate-700">—</span>
                </div>
            </div>

            <!-- Call To Action (active state) -->
            <div id="redeem-form">
                <div class="bg-gradient-to-br from-purple-50 to-violet-50 border-2 border-purple-200 rounded-2xl p-5 text-center mb-4">
                    <div class="w-14 h-14 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="phone-call" class="w-7 h-7 text-purple-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">შეთავაზების გამოსაყენებლად</h3>
                    <p class="text-sm text-slate-600 mb-3">ეწვიეთ ჩვენს სერვისს</p>
                    <div class="bg-white/80 rounded-xl p-3 border border-purple-100">
                        <p class="text-xs text-purple-600 font-semibold uppercase tracking-wider mb-1">კითხვები გაქვს?</p>
                        <a href="tel:+9950322052626" class="text-xl font-bold text-purple-700 hover:text-purple-800">+995 032 2 05 26 26</a>
                    </div>
                </div>
                <a href="https://api.whatsapp.com/send?phone=995511144486" class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3.5 px-4 rounded-xl font-bold hover:from-green-600 hover:to-emerald-700 transition-all flex items-center justify-center gap-2 shadow-lg shadow-green-500/30 active:scale-[0.98]">
                    <i data-lucide="message-circle" class="w-5 h-5"></i>
                    <span>WhatsApp-ით დაკავშირება</span>
                </a>
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

            <!-- Already Redeemed By This Customer State -->
            <div id="already-redeemed-state" class="hidden text-center py-4">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 ring-8 ring-green-50">
                    <i data-lucide="check-circle" class="w-10 h-10 text-green-500"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-2">ვაუჩერი უკვე გამოყენებულია</h3>
                <p class="text-slate-500 text-sm">თქვენ უკვე გამოიყენეთ ეს შეთავაზება.</p>
                <div class="mt-4 bg-green-50 border border-green-200 rounded-xl p-4">
                    <p class="text-xs text-green-600 font-semibold uppercase tracking-wider mb-1">კითხვები გაქვს?</p>
                    <a href="tel:0322052626" class="text-lg font-bold text-green-700 hover:text-green-800">032 2 05 26 26</a>
                </div>
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
        const trackingSlug = urlParams.get('t') || '';
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
                let url = `${API_URL}?action=get_public_offer&code=${encodeURIComponent(offerCode)}`;
                if (trackingSlug) {
                    url += `&t=${encodeURIComponent(trackingSlug)}`;
                }
                const res = await fetch(url);
                const data = await res.json();

                if (data.error) {
                    return showError();
                }

                currentOffer = data;
                renderOffer(data);

                // Track view with tracking slug (fire and forget)
                fetch(`${API_URL}?action=track_offer_view`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ offer_id: data.id, tracking_slug: trackingSlug })
                }).catch(() => {});
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

            // Georgian month names
            const georgianMonths = ['იანვარი', 'თებერვალი', 'მარტი', 'აპრილი', 'მაისი', 'ივნისი', 'ივლისი', 'აგვისტო', 'სექტემბერი', 'ოქტომბერი', 'ნოემბერი', 'დეკემბერი'];

            // Validity
            if (data.valid_until) {
                const d = new Date(data.valid_until.replace(' ', 'T'));
                const day = d.getDate();
                const month = georgianMonths[d.getMonth()];
                const year = d.getFullYear();
                document.getElementById('offer-validity').textContent = `${day} ${month}, ${year}`;
            }

            // Min order
            if (data.min_order_amount && parseFloat(data.min_order_amount) > 0) {
                document.getElementById('min-order-row').classList.remove('hidden');
                document.getElementById('offer-min-order').textContent = `${parseFloat(data.min_order_amount)}₾`;
            }

            // State checks - order matters: already redeemed by viewer takes priority
            if (data.is_redeemed_by_viewer) {
                document.getElementById('redeem-form').classList.add('hidden');
                document.getElementById('already-redeemed-state').classList.remove('hidden');
                document.getElementById('header').className = 'bg-gradient-to-br from-green-500 to-emerald-600 text-white px-6 py-10 text-center relative overflow-hidden';
            } else if (data.status === 'expired') {
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

        function showError() {
            document.getElementById('loader').classList.add('hidden');
            document.getElementById('error-state').classList.remove('hidden');
            lucide.createIcons();
        }

        init();
    </script>
</body>
</html>
