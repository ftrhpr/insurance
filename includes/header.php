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
                    <button onclick="window.switchView('dashboard')" id="nav-dashboard" class="nav-active px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                    </button>
                    <button onclick="window.switchView('vehicles')" id="nav-vehicles" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                        <i data-lucide="database" class="w-4 h-4"></i> Vehicle DB
                    </button>
                    <button onclick="window.switchView('reviews')" id="nav-reviews" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                        <i data-lucide="star" class="w-4 h-4"></i> Reviews
                    </button>
                    <button onclick="window.switchView('templates')" id="nav-templates" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                        <i data-lucide="message-square-dashed" class="w-4 h-4"></i> SMS Templates
                    </button>
                    <?php if (isAdmin()): ?>
                    <button onclick="window.switchView('users')" id="nav-users" class="nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2">
                        <i data-lucide="users" class="w-4 h-4"></i> Users
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User Status -->
            <div class="flex items-center gap-4">
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
