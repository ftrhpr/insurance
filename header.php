<?php
// Header component for OTOMOTORS portal
// Usage: include 'header.php'; before any HTML output
// Make sure $current_user_name and $current_user_role are set before including

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get current page name for active navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Navigation items
$nav_items = [
    'index' => ['icon' => 'layout-dashboard', 'label' => 'Dashboard', 'url' => 'index.php'],
    'vehicles' => ['icon' => 'database', 'label' => 'Vehicle DB', 'url' => 'vehicles.php'],
    'reviews' => ['icon' => 'star', 'label' => 'Reviews', 'url' => 'reviews.php'],
    'templates' => ['icon' => 'message-square-dashed', 'label' => 'SMS Templates', 'url' => 'templates.php']
];

// Add Users page only for admins
if ($current_user_role === 'admin') {
    $nav_items['users'] = ['icon' => 'users', 'label' => 'Users', 'url' => 'users.php'];
    $nav_items['translations'] = ['icon' => 'languages', 'label' => 'Translations', 'url' => 'translations.php'];
}
?>
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

/* Enhanced Navigation */
.nav-active { 
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    color: #ffffff; 
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3), 0 2px 4px rgba(14, 165, 233, 0.2);
}
.nav-inactive { 
    color: #64748b;
    background: transparent;
}
.nav-inactive:hover { 
    color: #0f172a;
    background: rgba(14, 165, 233, 0.08);
    transform: translateY(-1px);
}

/* Mobile Navigation Adjustments */
@media (max-width: 1023px) {
    .nav-active {
        box-shadow: 0 2px 8px rgba(14, 165, 233, 0.2);
    }
}

/* Gradient Text */
.gradient-text {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #c026d3 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
</style>
<!-- Premium Navbar with Gradient Accent -->
<nav class="bg-white/95 backdrop-blur-xl border-b border-slate-200/80 sticky top-0 z-40 shadow-lg shadow-slate-200/50">
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-8">
        <div class="flex justify-between items-center h-16 lg:h-18">
            <div class="flex items-center gap-3 sm:gap-4 lg:gap-8">
                <!-- Enhanced Logo with Gradient -->
                <a href="index.php" class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                    <div class="relative">
                        <div class="absolute inset-0 bg-gradient-to-br from-primary-400 to-accent-500 rounded-xl blur-md opacity-60"></div>
                        <div class="relative bg-gradient-to-br from-primary-500 via-primary-600 to-accent-600 p-2 sm:p-2.5 rounded-xl text-white shadow-lg">
                            <i data-lucide="car" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                        </div>
                    </div>
                    <div class="hidden sm:block">
                        <h1 class="text-base lg:text-lg font-bold gradient-text leading-tight tracking-tight">OTOMOTORS</h1>
                        <span class="text-[9px] lg:text-[10px] text-slate-500 font-semibold uppercase tracking-wider">Service Manager</span>
                    </div>
                    <div class="sm:hidden">
                        <h1 class="text-sm font-bold gradient-text leading-tight">OTOMOTORS</h1>
                    </div>
                </a>

                <!-- Enhanced Navigation -->
                <div class="hidden lg:flex bg-slate-50/80 p-1.5 rounded-xl border border-slate-200/60 shadow-inner">
                    <?php foreach ($nav_items as $page => $item): ?>
                        <?php
                            $is_active = ($current_page === $page);
                            $class = $is_active ? 'nav-active' : 'nav-inactive';
                        ?>
                        <a href="<?php echo $item['url']; ?>" class="<?php echo $class; ?> px-3 lg:px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-1.5 lg:gap-2">
                            <i data-lucide="<?php echo $item['icon']; ?>" class="w-4 h-4"></i>
                            <span class="hidden xl:inline"><?php echo $item['label']; ?></span>
                            <span class="xl:hidden"><?php echo substr($item['label'], 0, 4); ?>...</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Premium User Status Section -->
            <div class="flex items-center gap-1 sm:gap-2 lg:gap-3 flex-shrink-0">
                <!-- Language Selector - Hidden on very small screens -->
                <div class="relative hidden sm:block">
                    <select onchange="changeLanguage(this.value)" class="appearance-none bg-slate-50 border border-slate-200 text-slate-700 py-1.5 lg:py-2 pl-2 lg:pl-3 pr-6 lg:pr-8 rounded-lg text-xs lg:text-sm font-medium cursor-pointer hover:bg-slate-100 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                        <?php
                        require_once 'language.php';
                        $current_lang = get_current_language();
                        foreach ($LANGUAGES as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
                                <?php echo $code === 'en' ? 'EN' : ($code === 'ka' ? 'KA' : 'RU'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-1.5 lg:px-2 text-slate-500">
                        <i data-lucide="globe" class="w-3 h-3 lg:w-4 lg:h-4"></i>
                    </div>
                </div>

                <?php if ($current_page === 'index'): ?>
                <!-- Enhanced Notification Bell (only on dashboard) - Hidden on small screens -->
                <button id="btn-notify" onclick="window.requestNotificationPermission()" class="relative text-slate-400 hover:text-primary-600 transition-all p-2 lg:p-2.5 bg-slate-50 hover:bg-primary-50 rounded-xl group shadow-sm hover:shadow-md hidden sm:flex" title="Enable Notifications">
                    <i data-lucide="bell" class="w-4 h-4 lg:w-5 lg:h-5 group-hover:scale-110 transition-transform"></i>
                    <span id="notify-badge" class="absolute -top-1 -right-1 w-3 h-3 bg-gradient-to-br from-red-500 to-red-600 rounded-full border-2 border-white hidden animate-pulse shadow-lg shadow-red-500/50"></span>
                </button>

                <!-- Premium Connection Status (only on dashboard) - Compact on smaller screens -->
                <div id="connection-status" class="hidden md:flex items-center gap-1.5 lg:gap-2 text-xs font-semibold bg-gradient-to-r from-emerald-50 to-teal-50 text-emerald-700 border border-emerald-200/60 px-2 lg:px-3.5 py-1.5 lg:py-2 rounded-xl shadow-sm">
                    <div class="relative">
                        <span class="w-1.5 h-1.5 lg:w-2 lg:h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        <span class="absolute inset-0 w-1.5 h-1.5 lg:w-2 lg:h-2 bg-emerald-400 rounded-full animate-ping opacity-75"></span>
                    </div>
                    <span class="hidden lg:inline tracking-wide">Connected</span>
                    <span class="lg:hidden">●</span>
                </div>
                <?php endif; ?>

                <!-- Mobile Navigation Menu -->
                <div class="lg:hidden relative" id="mobile-menu-container">
                    <button onclick="toggleMobileMenu()" class="flex items-center justify-center w-8 h-8 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors border border-slate-200/50">
                        <i data-lucide="menu" class="w-5 h-5 text-slate-600"></i>
                    </button>

                    <!-- Mobile Menu Dropdown -->
                    <div id="mobile-menu" class="hidden absolute right-0 mt-2 w-64 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-slate-200/80 py-2 z-50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-primary-50/50 to-accent-50/50">
                            <h3 class="text-sm font-bold text-slate-800">Navigation</h3>
                        </div>
                        <?php foreach ($nav_items as $page => $item): ?>
                            <?php
                                $is_active = ($current_page === $page);
                                $class = $is_active ? 'bg-primary-50 text-primary-700 border-primary-200' : 'text-slate-700 hover:bg-slate-50';
                            ?>
                            <a href="<?php echo $item['url']; ?>" onclick="document.getElementById('mobile-menu').classList.add('hidden')" class="block px-4 py-3 text-sm <?php echo $class; ?> flex items-center gap-3 transition-colors border-l-4 <?php echo $is_active ? 'border-primary-500' : 'border-transparent'; ?>">
                                <i data-lucide="<?php echo $item['icon']; ?>" class="w-4 h-4"></i>
                                <span class="font-medium"><?php echo $item['label']; ?></span>
                            </a>
                        <?php endforeach; ?>

                        <!-- Mobile Language Selector -->
                        <div class="border-t border-slate-100 mt-2 pt-2">
                            <div class="px-4 py-2">
                                <label class="block text-xs font-bold text-slate-600 mb-2">Language</label>
                                <select onchange="changeLanguage(this.value); document.getElementById('mobile-menu').classList.add('hidden')" class="w-full appearance-none bg-slate-50 border border-slate-200 text-slate-700 py-2 pl-3 pr-8 rounded-lg text-sm font-medium cursor-pointer hover:bg-slate-100 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                                    <?php
                                    require_once 'language.php';
                                    $current_lang = get_current_language();
                                    foreach ($LANGUAGES as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                    <button onclick="toggleUserMenu()" class="flex items-center gap-1.5 lg:gap-2.5 px-2 lg:px-3.5 py-1.5 lg:py-2 bg-slate-50 hover:bg-slate-100 rounded-xl transition-all shadow-sm hover:shadow-md border border-slate-200/50">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-br from-primary-400 to-accent-500 rounded-full blur opacity-40"></div>
                            <div class="relative w-6 h-6 lg:w-8 lg:h-8 bg-gradient-to-br from-primary-500 via-primary-600 to-accent-600 rounded-full flex items-center justify-center text-white text-xs font-bold shadow-lg">
                                <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                            </div>
                        </div>
                        <div class="text-left hidden lg:block">
                            <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($current_user_name); ?></div>
                            <div class="text-xs text-slate-500 capitalize font-medium"><?php echo htmlspecialchars($current_user_role); ?></div>
                        </div>
                        <i data-lucide="chevron-down" class="w-3 h-3 lg:w-4 lg:h-4 text-slate-400 transition-transform"></i>
                    </button>
                    
                    <!-- Premium Dropdown Menu -->
                    <div id="user-dropdown" class="hidden absolute right-0 mt-3 w-64 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-slate-200/80 py-2 z-50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100 bg-gradient-to-r from-primary-50/50 to-accent-50/50">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-accent-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                                    <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($current_user_name); ?></p>
                                    <p class="text-xs text-slate-500 font-medium">@<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php if ($current_page === 'index'): ?>
                        <button onclick="window.openChangePasswordModal()" class="w-full text-left px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-3 transition-colors group">
                            <div class="w-8 h-8 bg-slate-100 group-hover:bg-primary-50 rounded-lg flex items-center justify-center transition-colors">
                                <i data-lucide="lock" class="w-4 h-4 text-slate-600 group-hover:text-primary-600"></i>
                            </div>
                            <span class="font-medium">Change Password</span>
                        </button>
                        <?php endif; ?>
                        <a href="logout.php" class="block px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors group">
                            <div class="w-8 h-8 bg-red-50 group-hover:bg-red-100 rounded-lg flex items-center justify-center transition-colors">
                                <i data-lucide="log-out" class="w-4 h-4 text-red-600"></i>
                            </div>
                            <span class="font-semibold">Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    // User menu toggle function
    function toggleUserMenu() {
        const dropdown = document.getElementById('user-dropdown');
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            lucide.createIcons();
        }
    }

    // Mobile menu toggle function
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
        if (!menu.classList.contains('hidden')) {
            lucide.createIcons();
        }
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const userContainer = document.getElementById('user-menu-container');
        const userDropdown = document.getElementById('user-dropdown');
        if (userContainer && userDropdown && !userContainer.contains(e.target)) {
            userDropdown.classList.add('hidden');
        }

        const mobileContainer = document.getElementById('mobile-menu-container');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileContainer && mobileMenu && !mobileContainer.contains(e.target)) {
            mobileMenu.classList.add('hidden');
        }
    });

    // Language change function
    async function changeLanguage(lang) {
        try {
            const response = await fetch('api.php?action=set_language', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ language: lang })
            });
            const result = await response.json();
            if (result.success) {
                window.location.reload();
            } else {
                alert('Failed to change language');
            }
        } catch (error) {
            console.error('Error changing language:', error);
            alert('Failed to change language');
        }
    }
</script>
