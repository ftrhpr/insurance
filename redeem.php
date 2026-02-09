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
    </style>
</head>
<body class="flex items-center justify-center p-4 font-sans">

    <div class="w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-6">
            <a href="index.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700 text-sm mb-4">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                უკან დაბრუნება
            </a>
            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-violet-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-purple-500/30">
                <i data-lucide="ticket-check" class="w-8 h-8 text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">ვაუჩერის გამოყენება</h1>
            <p class="text-slate-500 text-sm mt-1">შეიყვანეთ კოდი და მომხმარებლის ნომერი</p>
        </div>

        <!-- Main Card -->
        <div id="redeem-card" class="bg-white rounded-2xl shadow-xl p-6 card-enter">
            <!-- Code Input -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    <i data-lucide="hash" class="w-4 h-4 inline mr-1"></i>
                    ვაუჩერის კოდი
                </label>
                <input type="text" id="offer-code" placeholder="მაგ: ABC123" 
                    class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 outline-none transition text-lg font-mono uppercase tracking-wider"
                    maxlength="12" autocomplete="off">
            </div>

            <!-- Phone Input -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    <i data-lucide="phone" class="w-4 h-4 inline mr-1"></i>
                    მომხმარებლის ტელეფონი
                </label>
                <input type="tel" id="customer-phone" placeholder="მაგ: 511123456" 
                    class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 outline-none transition text-lg"
                    maxlength="15" autocomplete="off">
            </div>

            <!-- Notes Input -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    <i data-lucide="message-square" class="w-4 h-4 inline mr-1"></i>
                    შენიშვნა (არასავალდებულო)
                </label>
                <textarea id="redeem-notes" placeholder="დამატებითი ინფორმაცია..." rows="2"
                    class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-purple-500 focus:ring-4 focus:ring-purple-500/20 outline-none transition resize-none"></textarea>
            </div>

            <!-- Offer Preview (hidden initially) -->
            <div id="offer-preview" class="hidden mb-6 bg-purple-50 border-2 border-purple-200 rounded-xl p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="gift" class="w-5 h-5 text-purple-600"></i>
                    </div>
                    <div>
                        <div id="preview-title" class="font-bold text-slate-800">...</div>
                        <div id="preview-discount" class="text-sm text-purple-600 font-semibold">...</div>
                    </div>
                </div>
                <div id="preview-customer" class="text-sm text-slate-600 bg-white rounded-lg px-3 py-2 border border-purple-100">
                    <span class="text-slate-400">მომხმარებელი:</span> <span id="preview-customer-name" class="font-medium">...</span>
                </div>
            </div>

            <!-- Submit Button -->
            <button onclick="redeemOffer()" id="redeem-btn" 
                class="w-full bg-gradient-to-r from-purple-500 to-violet-600 text-white py-4 px-6 rounded-xl font-bold text-lg hover:from-purple-600 hover:to-violet-700 transition-all shadow-lg shadow-purple-500/30 active:scale-[0.98] flex items-center justify-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5"></i>
                გამოყენება
            </button>

            <!-- Quick Lookup Button -->
            <button onclick="lookupOffer()" id="lookup-btn" 
                class="w-full mt-3 bg-slate-100 text-slate-600 py-3 px-6 rounded-xl font-semibold hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                <i data-lucide="search" class="w-4 h-4"></i>
                კოდის შემოწმება
            </button>
        </div>

        <!-- Success State -->
        <div id="success-card" class="hidden bg-white rounded-2xl shadow-xl p-6 text-center card-enter">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 ring-8 ring-green-50">
                <i data-lucide="check-circle" class="w-10 h-10 text-green-500"></i>
            </div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">წარმატებით გამოყენებულია!</h2>
            <p id="success-message" class="text-slate-500 mb-6">ვაუჩერი გამოყენებულია მომხმარებლისთვის.</p>
            
            <div id="success-details" class="bg-slate-50 rounded-xl p-4 mb-6 text-left">
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="text-slate-500">კოდი:</div>
                    <div id="success-code" class="font-bold text-slate-800">—</div>
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

        <!-- Error State -->
        <div id="error-card" class="hidden bg-white rounded-2xl shadow-xl p-6 text-center card-enter">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 ring-8 ring-red-50">
                <i data-lucide="x-circle" class="w-10 h-10 text-red-500"></i>
            </div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">შეცდომა</h2>
            <p id="error-message" class="text-slate-500 mb-6">ვერ მოხერხდა ვაუჩერის გამოყენება.</p>
            
            <button onclick="resetForm()" class="w-full bg-slate-100 text-slate-600 py-3 px-6 rounded-xl font-bold hover:bg-slate-200 transition-all flex items-center justify-center gap-2">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                თავიდან ცდა
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
        let currentOffer = null;

        // Initialize icons
        lucide.createIcons();

        // Auto-uppercase code input
        document.getElementById('offer-code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });

        // Phone number formatting
        document.getElementById('customer-phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Enter key handling
        document.getElementById('offer-code').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') document.getElementById('customer-phone').focus();
        });
        document.getElementById('customer-phone').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') redeemOffer();
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
            toast.className = `${colors[type]} text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-2 animate-slide-in`;
            toast.innerHTML = `<span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        async function lookupOffer() {
            const code = document.getElementById('offer-code').value.trim();
            if (!code || code.length < 6) {
                showToast('შეიყვანეთ სწორი კოდი', 'error');
                return;
            }

            const btn = document.getElementById('lookup-btn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> იძებნება...';
            lucide.createIcons();

            try {
                const data = await fetchAPI(`get_public_offer&code=${encodeURIComponent(code)}`);
                
                if (data.error) {
                    showToast('ვაუჩერი ვერ მოიძებნა', 'error');
                    document.getElementById('offer-preview').classList.add('hidden');
                    currentOffer = null;
                } else {
                    currentOffer = data;
                    
                    // Show preview
                    document.getElementById('offer-preview').classList.remove('hidden');
                    document.getElementById('preview-title').textContent = data.title;
                    
                    let discountText = '';
                    if (data.discount_type === 'percentage') {
                        discountText = `${parseFloat(data.discount_value)}% ფასდაკლება`;
                    } else if (data.discount_type === 'fixed') {
                        discountText = `${parseFloat(data.discount_value)}₾ ფასდაკლება`;
                    } else {
                        discountText = 'უფასო სერვისი';
                    }
                    document.getElementById('preview-discount').textContent = discountText;

                    // Check status
                    if (data.status !== 'active') {
                        showToast('ვაუჩერი არააქტიურია', 'error');
                    } else if (data.is_exhausted) {
                        showToast('ვაუჩერი ამოწურულია', 'error');
                    } else {
                        showToast('ვაუჩერი ნაპოვნია', 'success');
                    }
                }
            } catch (e) {
                showToast('კავშირის შეცდომა', 'error');
            }

            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="search" class="w-4 h-4"></i> კოდის შემოწმება';
            lucide.createIcons();
        }

        async function redeemOffer() {
            const code = document.getElementById('offer-code').value.trim();
            const phone = document.getElementById('customer-phone').value.trim();
            const notes = document.getElementById('redeem-notes').value.trim();

            if (!code || code.length < 6) {
                showToast('შეიყვანეთ სწორი კოდი', 'error');
                return;
            }
            if (!phone || phone.length < 9) {
                showToast('შეიყვანეთ სწორი ტელეფონის ნომერი', 'error');
                return;
            }

            const btn = document.getElementById('redeem-btn');
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> მუშავდება...';
            lucide.createIcons();

            try {
                // First get offer ID from code
                const offerData = await fetchAPI(`get_public_offer&code=${encodeURIComponent(code)}`);
                
                if (offerData.error) {
                    showError('ვაუჩერი ვერ მოიძებნა');
                    return;
                }

                // Redeem the offer
                const result = await fetchAPI('admin_redeem_offer', 'POST', {
                    offer_id: offerData.id,
                    phone: phone,
                    notes: notes
                });

                if (result.status === 'success') {
                    showSuccess(offerData, phone, result.customer_name || 'მომხმარებელი');
                } else {
                    showError(result.message || 'გამოყენება ვერ მოხერხდა');
                }
            } catch (e) {
                console.error(e);
                showError('კავშირის შეცდომა');
            }

            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> გამოყენება';
            lucide.createIcons();
        }

        function showSuccess(offer, phone, customerName) {
            document.getElementById('redeem-card').classList.add('hidden');
            document.getElementById('error-card').classList.add('hidden');
            document.getElementById('success-card').classList.remove('hidden');

            document.getElementById('success-message').textContent = `ვაუჩერი გამოყენებულია ${customerName}-სთვის`;
            document.getElementById('success-code').textContent = offer.code;
            document.getElementById('success-customer').textContent = `${customerName} (${phone})`;
            
            let discountText = '';
            if (offer.discount_type === 'percentage') {
                discountText = `${parseFloat(offer.discount_value)}%`;
            } else if (offer.discount_type === 'fixed') {
                discountText = `${parseFloat(offer.discount_value)}₾`;
            } else {
                discountText = 'უფასო სერვისი';
            }
            document.getElementById('success-discount').textContent = discountText;

            lucide.createIcons();
        }

        function showError(message) {
            document.getElementById('redeem-card').classList.add('hidden');
            document.getElementById('success-card').classList.add('hidden');
            document.getElementById('error-card').classList.remove('hidden');
            document.getElementById('error-message').textContent = message;
            lucide.createIcons();
        }

        function resetForm() {
            document.getElementById('offer-code').value = '';
            document.getElementById('customer-phone').value = '';
            document.getElementById('redeem-notes').value = '';
            document.getElementById('offer-preview').classList.add('hidden');
            currentOffer = null;

            document.getElementById('success-card').classList.add('hidden');
            document.getElementById('error-card').classList.add('hidden');
            document.getElementById('redeem-card').classList.remove('hidden');

            document.getElementById('offer-code').focus();
            lucide.createIcons();
        }

        // Focus on code input on load
        document.getElementById('offer-code').focus();
    </script>
</body>
</html>
