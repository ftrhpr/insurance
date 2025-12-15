<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include database configuration
require_once 'config.php';

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';

// Get database connection for initial data load
try {
    $pdo = getDBConnection();
    
    // Fetch reviews with statistics
    $stmt = $pdo->query("SELECT * FROM customer_reviews ORDER BY created_at DESC");
    $reviews_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total = count($reviews_data);
    $avg_rating = 0;
    if ($total > 0) {
        $sum = array_sum(array_column($reviews_data, 'rating'));
        $avg_rating = round($sum / $total, 1);
    }
    
} catch (Exception $e) {
    error_log("Database error in reviews.php: " . $e->getMessage());
    $reviews_data = [];
    $total = 0;
    $avg_rating = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews - OTOMOTORS</title>
    
    <!-- Google Fonts: Inter -->
    <!-- Prefer local BPG Arial; keep Google Fonts link as fallback -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/fonts/include_fonts.php'; ?>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e'
                        },
                        accent: {
                            50: '#fdf4ff', 100: '#fae8ff', 200: '#f5d0fe', 300: '#f0abfc',
                            400: '#e879f9', 500: '#d946ef', 600: '#c026d3', 700: '#a21caf',
                            800: '#86198f', 900: '#701a75'
                        }
                    },
                    fontFamily: { sans: ['BPG Arial Caps', 'BPG Arial', 'Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .float-icon { animation: float 3s ease-in-out infinite; }
        .gradient-text { 
            background: linear-gradient(135deg, #0ea5e9 0%, #d946ef 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { 
            background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb { 
            background: linear-gradient(180deg, #0ea5e9 0%, #0284c7 100%);
            border-radius: 10px;
            background-clip: padding-box;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { 
            background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%);
            background-clip: padding-box;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">
    <div class="flex">
        <?php include 'sidebar.php'; ?>
        <main class="flex-1 ml-64 p-8">

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800">Customer Reviews</h2>
                    <p class="text-slate-500 text-sm">Manage and approve customer feedback.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="bg-gradient-to-r from-yellow-400 to-orange-400 px-4 py-2 rounded-xl text-white font-bold flex items-center gap-2">
                        <i data-lucide="star" class="w-5 h-5"></i>
                        <span id="avg-rating"><?php echo $avg_rating; ?></span>
                    </div>
                    <div class="bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-600">
                        <span id="total-reviews"><?php echo $total; ?></span> Total Reviews
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="bg-white rounded-2xl border border-slate-200 p-2 flex gap-2">
                <button onclick="window.filterReviews('all')" id="filter-all" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold bg-slate-900 text-white transition-all">
                    All Reviews
                </button>
                <button onclick="window.filterReviews('pending')" id="filter-pending" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                    Pending <span id="pending-count" class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full text-xs ml-1">0</span>
                </button>
                <button onclick="window.filterReviews('approved')" id="filter-approved" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                    Approved
                </button>
                <button onclick="window.filterReviews('rejected')" id="filter-rejected" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                    Rejected
                </button>
            </div>

            <!-- Reviews Grid -->
            <div id="reviews-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <!-- Reviews injected here -->
            </div>

            <div id="reviews-empty" class="hidden py-20 flex flex-col items-center justify-center bg-white rounded-2xl border border-dashed border-slate-200 text-slate-400">
                <div class="bg-slate-50 p-3 rounded-full mb-3"><i data-lucide="star-off" class="w-6 h-6"></i></div>
                <span class="text-sm font-medium">No reviews yet</span>
            </div>
        </div>
    </main>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <script>
        const API_URL = 'api.php';
        const USER_ROLE = '<?php echo $current_user_role; ?>';
        const CAN_EDIT = USER_ROLE === 'admin' || USER_ROLE === 'manager';

        // Data arrays - Initialize with database data
        let customerReviews = <?php echo json_encode($reviews_data); ?>;
        let currentReviewFilter = 'all';
        
        // Debug logs
        console.log('Initial data loaded:');
        console.log('Reviews count:', customerReviews.length);
        console.log('Average rating:', <?php echo $avg_rating; ?>);
        console.log('User role:', USER_ROLE);
        console.log('Can edit:', CAN_EDIT);

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
                    const clone = res.clone();
                    const jsonErr = await clone.json().catch(() => ({}));
                    const txtErr = await res.text().catch(() => '');
                    const message = jsonErr?.message || jsonErr?.error || txtErr || `HTTP ${res.status}`;
                    throw new Error(message);
                }
                
                return await res.json();
            } catch (err) {
                console.error('API Error:', err);
                throw err;
            }
        }

        // Load Reviews
        async function loadReviews() {
            try {
                console.log('loadReviews: Fetching from API...');
                const data = await fetchAPI('get_reviews');
                console.log('loadReviews: API response:', data);
                
                if (data && data.reviews) {
                    customerReviews = data.reviews;
                    console.log('loadReviews: Reviews loaded:', customerReviews.length);
                    
                    document.getElementById('avg-rating').textContent = data.average_rating || '0.0';
                    document.getElementById('total-reviews').textContent = data.total || 0;
                    
                    const pendingCount = customerReviews.filter(r => r.status === 'pending').length;
                    document.getElementById('pending-count').textContent = pendingCount;
                    
                    renderReviews();
                } else {
                    console.warn('No reviews data in response:', data);
                    renderReviews();
                }
            } catch (err) {
                console.error('Load error:', err);
                showToast('Failed to load reviews', 'Please refresh the page', 'error');
            }
        }

        // Filter Reviews
        window.filterReviews = (filter) => {
            currentReviewFilter = filter;
            
            // Update button states
            ['all', 'pending', 'approved', 'rejected'].forEach(f => {
                const btn = document.getElementById(`filter-${f}`);
                if (f === filter) {
                    btn.className = 'flex-1 px-4 py-2 rounded-lg text-sm font-semibold bg-slate-900 text-white transition-all';
                } else {
                    btn.className = 'flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all';
                }
            });
            
            renderReviews();
        };

        // Render Reviews
        function renderReviews() {
            console.log('renderReviews called');
            console.log('Current reviews array:', customerReviews);
            
            const container = document.getElementById('reviews-grid');
            const emptyState = document.getElementById('reviews-empty');
            
            if (!container || !emptyState) {
                console.error('Reviews container or empty state element not found');
                return;
            }
            
            let filteredReviews = customerReviews || [];
            if (currentReviewFilter !== 'all') {
                filteredReviews = filteredReviews.filter(r => r.status === currentReviewFilter);
            }
            
            console.log('Filtered reviews:', filteredReviews.length, 'Filter:', currentReviewFilter);
            
            if (filteredReviews.length === 0) {
                container.innerHTML = '';
                emptyState.classList.remove('hidden');
                return;
            }
            
            emptyState.classList.add('hidden');
            
            const html = filteredReviews.map(review => {
                const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
                const statusColors = {
                    pending: 'bg-yellow-50 border-yellow-200 text-yellow-700',
                    approved: 'bg-green-50 border-green-200 text-green-700',
                    rejected: 'bg-red-50 border-red-200 text-red-700'
                };
                const statusColor = statusColors[review.status] || statusColors.pending;
                
                const date = new Date(review.created_at).toLocaleDateString('en-GB', { 
                    month: 'short', day: 'numeric', year: 'numeric' 
                });
                
                return `
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-all">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h3 class="font-bold text-slate-800">${review.customer_name || 'Anonymous'}</h3>
                                <p class="text-xs text-slate-400 mt-0.5">${date}</p>
                            </div>
                            <span class="text-2xl text-yellow-400">${stars}</span>
                        </div>
                        
                        <p class="text-sm text-slate-600 leading-relaxed mb-4 line-clamp-3">${review.comment || ''}</p>
                        
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                            <span class="text-[10px] font-mono font-bold text-slate-400">Order #${review.order_id}</span>
                            <span class="px-2 py-1 rounded-full text-[10px] font-bold border ${statusColor} uppercase">
                                ${review.status}
                            </span>
                        </div>
                        
                        ${review.status === 'pending' && CAN_EDIT ? `
                            <div class="flex gap-2 mt-3">
                                <button onclick="window.approveReview(${review.id})" class="flex-1 bg-green-500 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-green-600 active:scale-95 transition-all flex items-center justify-center gap-1">
                                    <i data-lucide="check" class="w-4 h-4"></i> Approve
                                </button>
                                <button onclick="window.rejectReview(${review.id})" class="flex-1 bg-red-500 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-red-600 active:scale-95 transition-all flex items-center justify-center gap-1">
                                    <i data-lucide="x" class="w-4 h-4"></i> Reject
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
            
            container.innerHTML = html;
            
            if (window.lucide) lucide.createIcons();
        }

        // Approve Review
        window.approveReview = async (id) => {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to approve reviews', 'error');
                return;
            }
            
            if (!id) {
                showToast('Error', 'Invalid review ID', 'error');
                return;
            }
            
            try {
                await fetchAPI(`update_review_status&id=${id}`, 'POST', { status: 'approved' });
                showToast('Review Approved', 'The review is now visible to customers', 'success');
                loadReviews();
            } catch(e) {
                showToast('Failed to approve review', e.message, 'error');
            }
        };

        // Reject Review
        window.rejectReview = async (id) => {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to reject reviews', 'error');
                return;
            }
            
            if (!id) {
                showToast('Error', 'Invalid review ID', 'error');
                return;
            }
            
            if (confirm('Reject this review permanently?')) {
                try {
                    await fetchAPI(`update_review_status&id=${id}`, 'POST', { status: 'rejected' });
                    showToast('Review Rejected', 'The review has been rejected', 'error');
                    loadReviews();
                } catch(e) {
                    showToast('Failed to reject review', e.message, 'error');
                }
            }
        };

        // Toast Notifications
        function showToast(title, message = '', type = 'success', duration = 4000) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            
            const colors = {
                success: { 
                    bg: 'bg-white/95', 
                    border: 'border-emerald-200', 
                    iconBg: 'bg-emerald-50', 
                    iconColor: 'text-emerald-600',
                    icon: 'check-circle',
                    shadow: 'shadow-emerald-500/20' 
                },
                error: { 
                    bg: 'bg-white/95', 
                    border: 'border-red-200', 
                    iconBg: 'bg-red-50', 
                    iconColor: 'text-red-600',
                    icon: 'x-circle',
                    shadow: 'shadow-red-500/20' 
                },
                info: { 
                    bg: 'bg-white/95', 
                    border: 'border-blue-200', 
                    iconBg: 'bg-blue-50', 
                    iconColor: 'text-blue-600',
                    icon: 'info',
                    shadow: 'shadow-blue-500/20' 
                },
                urgent: { 
                    bg: 'bg-white/95', 
                    border: 'border-primary-200', 
                    iconBg: 'bg-primary-50', 
                    iconColor: 'text-primary-600',
                    icon: 'bell',
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

        // Initialize
        
        // Update pending count
        const pendingCount = customerReviews.filter(r => r.status === 'pending').length;
        document.getElementById('pending-count').textContent = pendingCount;
        
        try {
            renderReviews();
        } catch(e) {
            console.error('Render error:', e);
        }
        
        loadReviews();
        lucide.createIcons();
        
        console.log('Initialization complete');
    </script>
</body>
</html>
