<?php
// Header component
$user = getCurrentUser();
?>
<nav class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-20 items-center justify-between">
            <div class="flex items-center gap-8">
                <div class="flex items-center gap-4">
                    <div class="bg-gradient-to-br from-primary-500 to-indigo-600 text-white p-3 rounded-xl shadow-lg">
                        <i data-lucide="car" class="w-7 h-7"></i>
                    </div>
                    <div class="flex flex-col">
                        <h1 class="text-lg font-bold text-slate-900 leading-tight tracking-tight">OTOMOTORS</h1>
                        <span class="text-[10px] text-slate-500 font-semibold uppercase tracking-wider">Service Manager</span>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="hidden md:flex bg-slate-100/50 p-1 rounded-lg border border-slate-200/50">
                    <?php
                    $current_page = basename($_SERVER['PHP_SELF'], '.php');
                    
                    function navButton($page, $label, $icon, $current) {
                        $isActive = $current === $page;
                        $class = $isActive ? 'nav-active' : 'nav-inactive';
                        echo "<button onclick=\"window.location.href='{$page}.php'\" id=\"nav-{$page}\" class=\"{$class} px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2\">";
                        echo "<i data-lucide=\"{$icon}\" class=\"w-4 h-4\"></i> {$label}";
                        echo "</button>";
                    }
                    
                    navButton('dashboard', 'Dashboard', 'layout-dashboard', $current_page);
                    navButton('vehicles', 'Vehicle DB', 'database', $current_page);
                    navButton('reviews', 'Reviews', 'star', $current_page);
                    navButton('templates', 'SMS Templates', 'message-square-dashed', $current_page);
                    
                    if (isAdmin()) {
                        navButton('users', 'Users', 'users', $current_page);
                    }
                    ?>
                </div>
            </div>

            <!-- User Status -->
            <div class="flex items-center gap-4">
                <!-- Unified View Link -->
                <a href="index-modular.php" class="text-xs px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 rounded-full transition-colors flex items-center gap-1.5">
                    <i data-lucide="app-window" class="w-3.5 h-3.5"></i>
                    Unified View
                </a>
                
                <!-- Notification Bell -->
                <button id="btn-notify" onclick="window.requestNotificationPermission()" class="text-slate-400 hover:text-primary-600 transition-colors p-2 bg-slate-100 rounded-full group relative" title="Enable Notifications">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span id="notify-badge" class="absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white hidden"></span>
                </button>

                <div id="connection-status" class="flex items-center gap-2 text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1.5 rounded-full shadow-sm">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                    Server Connected
                </div>
                
                <!-- User Menu -->
                <div class="relative" id="user-menu-container">
                    <button onclick="window.toggleUserMenu()" class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                        <div class="w-7 h-7 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <div class="text-left hidden sm:block">
                            <div class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <div class="text-xs text-slate-500 capitalize"><?php echo htmlspecialchars($user['role']); ?></div>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-slate-200 py-2 z-50">
                        <div class="px-4 py-2 border-b border-slate-100">
                            <p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($user['username']); ?></p>
                        </div>
                        <button onclick="window.openChangePasswordModal()" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 flex items-center gap-2">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                            Change Password
                        </button>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
