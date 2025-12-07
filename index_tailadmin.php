<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTOMOTORS - TailAdmin Dashboard</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js (Required for TailAdmin) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Firebase SDKs -->
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
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
                        gray: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        },
                        success: {
                            500: '#10b981',
                            600: '#059669',
                        },
                        error: {
                            500: '#ef4444',
                            600: '#dc2626',
                        },
                        warning: {
                            500: '#f59e0b',
                            600: '#d97706',
                        },
                    },
                }
            },
            darkMode: 'class',
        }
    </script>

    <style>
        /* Custom Scrollbar */
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
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { 
            background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%);
        }
        
        /* TailAdmin Menu Item Styles */
        .menu-item {
            @apply relative flex items-center gap-2.5 rounded-lg px-4 py-3 font-medium text-gray-500 duration-300 ease-in-out hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300;
        }
        .menu-item-active {
            @apply bg-brand-500 text-white hover:bg-brand-600 dark:bg-brand-500 dark:hover:bg-brand-600;
        }
        .menu-item-inactive {
            @apply text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300;
        }
        .menu-item-icon-active {
            @apply fill-white dark:fill-white;
        }
        .menu-item-icon-inactive {
            @apply fill-gray-500 dark:fill-gray-400;
        }
        .menu-item-arrow {
            @apply absolute right-2.5 top-1/2 -translate-y-1/2 stroke-current;
        }
        .menu-item-arrow-active {
            @apply rotate-180;
        }
        .menu-item-arrow-inactive {
            @apply rotate-0;
        }
        .menu-dropdown {
            @apply flex flex-col gap-1 mt-2 pl-9;
        }
        .menu-dropdown-item {
            @apply relative flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium duration-300 ease-in-out;
        }
        .menu-dropdown-item-active {
            @apply bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300;
        }
        .menu-dropdown-item-inactive {
            @apply text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300;
        }
    </style>
</head>
<body 
    x-data="{ 
        page: 'dashboard', 
        loaded: true, 
        darkMode: false, 
        stickyMenu: false, 
        sidebarToggle: false, 
        scrollTop: false 
    }"
    x-init="
        darkMode = JSON.parse(localStorage.getItem('darkMode'));
        $watch('darkMode', value => localStorage.setItem('darkMode', JSON.stringify(value)))
    "
    :class="{'dark bg-gray-900': darkMode === true}"
    class="font-sans antialiased text-gray-800 dark:text-gray-200"
>
    
    <!-- Preloader -->
    <div 
        x-show="!loaded" 
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed left-0 top-0 z-999999 flex h-screen w-screen items-center justify-center bg-white dark:bg-gray-900"
    >
        <div class="h-16 w-16 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent"></div>
    </div>

    <!-- Page Wrapper -->
    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <aside 
            :class="sidebarToggle ? 'translate-x-0 lg:w-[90px]' : '-translate-x-full'"
            class="sidebar fixed left-0 top-0 z-9999 flex h-screen w-[290px] flex-col overflow-y-hidden border-r border-gray-200 bg-white px-5 duration-300 ease-linear dark:border-gray-800 dark:bg-black lg:static lg:translate-x-0 custom-scrollbar"
            @click.outside="sidebarToggle = false"
        >
            <!-- Sidebar Header -->
            <div 
                :class="sidebarToggle ? 'justify-center' : 'justify-between'"
                class="flex items-center gap-2 pt-8 pb-7"
            >
                <a href="index.php">
                    <span class="logo" :class="sidebarToggle ? 'hidden' : ''">
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">OTOMOTORS</h1>
                    </span>
                    <span :class="sidebarToggle ? 'lg:block' : 'hidden'" class="text-2xl font-bold text-brand-500">OTM</span>
                </a>
            </div>

            <!-- Sidebar Menu -->
            <nav x-data="{selected: $persist('Dashboard')}">
                <!-- Menu Group -->
                <div>
                    <h3 class="mb-4 text-xs uppercase leading-[20px] text-gray-400">
                        <span :class="sidebarToggle ? 'lg:hidden' : ''">MENU</span>
                    </h3>

                    <ul class="flex flex-col gap-4 mb-6">
                        <!-- Dashboard -->
                        <li>
                            <a
                                href="#"
                                @click.prevent="selected = (selected === 'Dashboard' ? '':'Dashboard'); page = 'dashboard'"
                                class="menu-item group"
                                :class="(selected === 'Dashboard') || (page === 'dashboard') ? 'menu-item-active' : 'menu-item-inactive'"
                            >
                                <svg
                                    :class="(selected === 'Dashboard') || (page === 'dashboard') ? 'menu-item-icon-active'  :'menu-item-icon-inactive'"
                                    width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M3 6C3 4.34315 4.34315 3 6 3H9C10.6569 3 12 4.34315 12 6V9C12 10.6569 10.6569 12 9 12H6C4.34315 12 3 10.6569 3 9V6ZM6 5C5.44772 5 5 5.44772 5 6V9C5 9.55228 5.44772 10 6 10H9C9.55228 10 10 9.55228 10 9V6C10 5.44772 9.55228 5 9 5H6Z" fill=""/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M3 15C3 13.3431 4.34315 12 6 12H9C10.6569 12 12 13.3431 12 15V18C12 19.6569 10.6569 21 9 21H6C4.34315 21 3 19.6569 3 18V15ZM6 14C5.44772 14 5 14.4477 5 15V18C5 18.5523 5.44772 19 6 19H9C9.55228 19 10 18.5523 10 18V15C10 14.4477 9.55228 14 9 14H6Z" fill=""/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 6C12 4.34315 13.3431 3 15 3H18C19.6569 3 21 4.34315 21 6V9C21 10.6569 19.6569 12 18 12H15C13.3431 12 12 10.6569 12 9V6ZM15 5C14.4477 5 14 5.44772 14 6V9C14 9.55228 14.4477 10 15 10H18C18.5523 10 19 9.55228 19 9V6C19 5.44772 18.5523 5 18 5H15Z" fill=""/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 15C12 13.3431 13.3431 12 15 12H18C19.6569 12 21 13.3431 21 15V18C21 19.6569 19.6569 21 18 21H15C13.3431 21 12 19.6569 12 18V15ZM15 14C14.4477 14 14 14.4477 14 15V18C14 18.5523 14.4477 19 15 19H18C18.5523 19 19 18.5523 19 18V15C19 14.4477 18.5523 14 18 14H15Z" fill=""/>
                                </svg>
                                <span :class="sidebarToggle ? 'lg:hidden' : ''">Dashboard</span>
                            </a>
                        </li>

                        <!-- Vehicles -->
                        <li>
                            <a
                                href="#"
                                @click.prevent="selected = (selected === 'Vehicles' ? '':'Vehicles'); page = 'vehicles'"
                                class="menu-item group"
                                :class="(selected === 'Vehicles') || (page === 'vehicles') ? 'menu-item-active' : 'menu-item-inactive'"
                            >
                                <i data-lucide="car" :class="(selected === 'Vehicles') || (page === 'vehicles') ? 'text-white' : 'text-gray-500 dark:text-gray-400'"></i>
                                <span :class="sidebarToggle ? 'lg:hidden' : ''">Vehicles</span>
                            </a>
                        </li>

                        <!-- Templates -->
                        <li>
                            <a
                                href="templates.php"
                                class="menu-item group menu-item-inactive"
                            >
                                <i data-lucide="message-square" class="text-gray-500 dark:text-gray-400"></i>
                                <span :class="sidebarToggle ? 'lg:hidden' : ''">SMS Templates</span>
                            </a>
                        </li>

                        <?php if ($current_user_role === 'admin'): ?>
                        <!-- User Management -->
                        <li>
                            <a
                                href="users.php"
                                class="menu-item group menu-item-inactive"
                            >
                                <i data-lucide="users" class="text-gray-500 dark:text-gray-400"></i>
                                <span :class="sidebarToggle ? 'lg:hidden' : ''">User Management</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Promo Box -->
            <div :class="sidebarToggle ? 'lg:hidden' : ''" class="mx-auto mb-10 w-full max-w-60 rounded-2xl bg-gray-50 px-4 py-5 text-center dark:bg-white/[0.03]">
                <h3 class="mb-2 font-semibold text-gray-900 dark:text-white">OTOMOTORS</h3>
                <p class="mb-4 text-gray-500 text-sm dark:text-gray-400">Insurance Service Manager</p>
            </div>
        </aside>

        <!-- Content Area -->
        <div class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden">
            
            <!-- Header -->
            <header class="sticky top-0 z-999 flex w-full border-b border-gray-200 bg-white lg:border-b dark:border-gray-800 dark:bg-gray-900">
                <div class="flex grow flex-col items-center justify-between lg:flex-row lg:px-6">
                    <div class="flex w-full items-center justify-between gap-2 border-b border-gray-200 px-3 py-3 sm:gap-4 lg:justify-normal lg:border-b-0 lg:px-0 lg:py-4 dark:border-gray-800">
                        
                        <!-- Hamburger Toggle -->
                        <button 
                            :class="sidebarToggle ? 'lg:bg-transparent dark:lg:bg-transparent bg-gray-100 dark:bg-gray-800' : ''"
                            class="z-99999 flex h-10 w-10 items-center justify-center rounded-lg border-gray-200 text-gray-500 lg:h-11 lg:w-11 lg:border dark:border-gray-800 dark:text-gray-400"
                            @click.stop="sidebarToggle = !sidebarToggle"
                        >
                            <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3 6.75C3 6.33579 3.33579 6 3.75 6H20.25C20.6642 6 21 6.33579 21 6.75C21 7.16421 20.6642 7.5 20.25 7.5H3.75C3.33579 7.5 3 7.16421 3 6.75Z" fill=""/>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3 12C3 11.5858 3.33579 11.25 3.75 11.25H20.25C20.6642 11.25 21 11.5858 21 12C21 12.4142 20.6642 12.75 20.25 12.75H3.75C3.33579 12.75 3 12.4142 3 12Z" fill=""/>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3 17.25C3 16.8358 3.33579 16.5 3.75 16.5H20.25C20.6642 16.5 21 16.8358 21 17.25C21 17.6642 20.6642 18 20.25 18H3.75C3.33579 18 3 17.6642 3 17.25Z" fill=""/>
                            </svg>
                        </button>

                        <!-- Logo for mobile -->
                        <a href="index.php" class="lg:hidden">
                            <h1 class="text-xl font-bold text-gray-800 dark:text-white">OTOMOTORS</h1>
                        </a>
                    </div>

                    <div class="flex items-center justify-between gap-4 px-5 py-4 lg:px-0">
                        <div class="flex items-center gap-2">
                            <!-- Dark Mode Toggle -->
                            <button 
                                class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                                @click.prevent="darkMode = !darkMode"
                            >
                                <svg class="hidden dark:block" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10 1.5415C10.4142 1.5415 10.75 1.87729 10.75 2.2915V3.5415C10.75 3.95572 10.4142 4.2915 10 4.2915C9.58579 4.2915 9.25 3.95572 9.25 3.5415V2.2915C9.25 1.87729 9.58579 1.5415 10 1.5415Z" fill="currentColor"/>
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M10.0009 6.79327C8.22978 6.79327 6.79402 8.22904 6.79402 10.0001C6.79402 11.7712 8.22978 13.207 10.0009 13.207C11.772 13.207 13.2078 11.7712 13.2078 10.0001C13.2078 8.22904 11.772 6.79327 10.0009 6.79327ZM5.29402 10.0001C5.29402 7.40061 7.40135 5.29327 10.0009 5.29327C12.6004 5.29327 14.7078 7.40061 14.7078 10.0001C14.7078 12.5997 12.6004 14.707 10.0009 14.707C7.40135 14.707 5.29402 12.5997 5.29402 10.0001Z" fill="currentColor"/>
                                </svg>
                                <svg class="dark:hidden" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7.45532 2.04266C7.84218 1.90139 8.27246 2.08165 8.41373 2.46851C9.06087 4.23325 10.7668 5.46249 12.75 5.46249C13.5593 5.46249 14.3155 5.26249 14.9811 4.91249C15.3436 4.72186 15.7968 4.84936 15.9874 5.21186C16.178 5.57436 16.0505 6.02749 15.688 6.21811C14.8249 6.67499 13.8218 6.96249 12.75 6.96249C10.0218 6.96249 7.74682 5.18999 6.93932 2.78874C6.79805 2.40189 6.97846 1.97393 7.36532 1.83266L7.45532 2.04266Z" fill="currentColor"/>
                                </svg>
                            </button>

                            <!-- User Dropdown -->
                            <div x-data="{dropdownOpen: false}" @click.outside="dropdownOpen = false" class="relative">
                                <a 
                                    class="flex items-center text-gray-700 dark:text-gray-400"
                                    href="#"
                                    @click.prevent="dropdownOpen = ! dropdownOpen"
                                >
                                    <span class="mr-3 h-11 w-11 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800 flex items-center justify-center">
                                        <i data-lucide="user" class="w-5 h-5"></i>
                                    </span>
                                    <span class="text-sm mr-1 block font-medium"><?php echo htmlspecialchars($current_user_name); ?></span>
                                    <svg :class="dropdownOpen && 'rotate-180'" class="stroke-gray-500 dark:stroke-gray-400" width="18" height="20" viewBox="0 0 18 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M4.3125 8.65625L9 13.3437L13.6875 8.65625" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>

                                <!-- Dropdown -->
                                <div 
                                    x-show="dropdownOpen"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute right-0 mt-[17px] flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-800 dark:bg-gray-900"
                                >
                                    <div>
                                        <span class="text-sm block font-medium text-gray-700 dark:text-gray-400"><?php echo htmlspecialchars($current_user_name); ?></span>
                                        <span class="text-xs mt-0.5 block text-gray-500 dark:text-gray-400"><?php echo ucfirst($current_user_role); ?></span>
                                    </div>
                                    <ul class="flex flex-col gap-1 border-b border-gray-200 pt-4 pb-3 dark:border-gray-800">
                                        <li>
                                            <a href="logout.php" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                                                <i data-lucide="log-out" class="w-5 h-5"></i>
                                                Sign out
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main>
                <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                    
                    <!-- Dashboard View -->
                    <div x-show="page === 'dashboard'">
                        <div class="space-y-6">
                            <!-- Quick Import Section -->
                            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                                <div class="px-5 py-4 sm:px-6 sm:py-5">
                                    <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Quick Import</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Paste SMS or bank statement to auto-detect transfers</p>
                                </div>
                                <div class="border-t border-gray-100 p-5 dark:border-gray-800 sm:p-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <textarea 
                                                id="import-text" 
                                                rows="5"
                                                placeholder="Paste bank text here..."
                                                class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                                            ></textarea>
                                            <div class="mt-3 flex gap-2">
                                                <button onclick="window.parseBankText()" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-600">
                                                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                                                    Detect
                                                </button>
                                                <?php if ($current_user_role === 'admin' || $current_user_role === 'manager'): ?>
                                                <button onclick="window.openManualCreateModal()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                                                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                                                    Manual Create
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div id="parsed-placeholder" class="flex h-full items-center justify-center border-2 border-dashed border-gray-200 rounded-lg dark:border-gray-800">
                                                <span class="text-sm text-gray-400">Waiting for text input...</span>
                                            </div>
                                            <div id="parsed-result" class="hidden">
                                                <div id="parsed-content" class="space-y-2 max-h-[200px] overflow-y-auto custom-scrollbar"></div>
                                                <button id="btn-save-import" onclick="window.saveParsedImport()" class="mt-3 w-full bg-success-500 text-white py-2.5 rounded-lg hover:bg-success-600 font-medium flex items-center justify-center gap-2">
                                                    <i data-lucide="save" class="w-4 h-4"></i>
                                                    Save Import
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filters -->
                            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <div class="relative flex-1">
                                        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                                        <input 
                                            id="search-input" 
                                            type="text" 
                                            placeholder="Search plates, names, phones..."
                                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm bg-transparent focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900"
                                        >
                                    </div>
                                    <select id="reply-filter" class="px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white dark:border-gray-700 dark:bg-gray-900">
                                        <option value="All">All Replies</option>
                                        <option value="Confirmed">✅ Confirmed</option>
                                        <option value="Reschedule Requested">📅 Reschedule</option>
                                        <option value="Pending">⏳ Not Responded</option>
                                    </select>
                                    <select id="status-filter" class="px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white dark:border-gray-700 dark:bg-gray-900">
                                        <option value="All">All Active Stages</option>
                                        <option value="Processing">🟡 Processing</option>
                                        <option value="Called">🟣 Contacted</option>
                                        <option value="Parts Ordered">📦 Parts Ordered</option>
                                        <option value="Parts Arrived">🏁 Parts Arrived</option>
                                        <option value="Scheduled">🟠 Scheduled</option>
                                        <option value="Completed">🟢 Completed</option>
                                        <option value="Issue">🔴 Issue</option>
                                    </select>
                                </div>
                            </div>

                            <!-- New Cases -->
                            <section id="new-cases-section">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">
                                    New Requests <span id="new-count" class="text-sm text-gray-500">(0)</span>
                                </h2>
                                <div id="new-cases-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
                                <div id="new-cases-empty" class="hidden py-12 text-center text-gray-400">
                                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                                    <p class="text-sm">No new requests</p>
                                </div>
                            </section>

                            <!-- Active Queue Table -->
                            <section>
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Processing Queue</h2>
                                    <span id="record-count" class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded dark:bg-gray-800">0 active</span>
                                </div>
                                <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
                                    <div class="overflow-x-auto custom-scrollbar">
                                        <table class="min-w-full">
                                            <thead class="bg-gray-50 dark:bg-gray-900">
                                                <tr class="border-b border-gray-200 dark:border-gray-800">
                                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vehicle & Owner</th>
                                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contact</th>
                                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Service Date</th>
                                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer Reply</th>
                                                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="table-body" class="divide-y divide-gray-200 dark:divide-gray-800"></tbody>
                                        </table>
                                        <div id="empty-state" class="hidden py-16 text-center text-gray-400">
                                            <i data-lucide="filter" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                                            <p class="text-sm">No matching cases found</p>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <!-- Vehicles View -->
                    <div x-show="page === 'vehicles'" x-cloak>
                        <div class="space-y-6">
                            <div class="flex items-center justify-between">
                                <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Vehicle Registry</h2>
                                <span id="vehicles-count" class="text-sm text-gray-500">0 vehicles</span>
                            </div>

                            <!-- Search -->
                            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                                <div class="relative">
                                    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                                    <input 
                                        id="vehicles-search" 
                                        type="text" 
                                        placeholder="Search by plate or phone..."
                                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm bg-transparent focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900"
                                    >
                                </div>
                            </div>

                            <!-- Vehicles Table -->
                            <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full">
                                        <thead class="bg-gray-50 dark:bg-gray-900">
                                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider dark:text-gray-300">Plate</th>
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider dark:text-gray-300">Phone</th>
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider dark:text-gray-300">Added</th>
                                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider dark:text-gray-300">Source</th>
                                            </tr>
                                        </thead>
                                        <tbody id="vehicles-table-body" class="divide-y divide-gray-200 dark:divide-gray-800"></tbody>
                                    </table>
                                </div>
                                <div id="vehicles-empty" class="hidden py-12 text-center text-gray-400">
                                    <i data-lucide="car" class="w-16 h-16 mx-auto mb-4 opacity-30"></i>
                                    <p class="text-sm">No vehicles found</p>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    Showing <span id="vehicles-showing-start">0</span>-<span id="vehicles-showing-end">0</span> of <span id="vehicles-total">0</span>
                                </div>
                                <div id="vehicles-pagination" class="flex gap-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none"></div>

    <!-- Edit Modal (Simplified) -->
    <div id="edit-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="window.closeModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl dark:bg-gray-900">
                <!-- Modal content populated by JS -->
                <div class="p-6">
                    <h3 id="modal-title" class="text-2xl font-bold text-gray-800 dark:text-white/90 mb-4"></h3>
                    <div id="modal-content"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Create Modal (Placeholder) -->
    <div id="manual-create-modal" class="hidden fixed inset-0 z-50"></div>

    <script>
        // Global variables
        const API_URL = 'api.php';
        const USER_ROLE = '<?php echo $current_user_role; ?>';
        const CAN_EDIT = USER_ROLE === 'admin' || USER_ROLE === 'manager';
        let transfers = [];
        let vehicles = [];
        window.currentEditingId = null;
        let parsedImportData = [];

        // Toast notification function
        function showToast(title, message = '', type = 'success') {
            const container = document.getElementById('toast-container');
            const colors = {
                success: { bg: 'bg-success-500', icon: 'check-circle-2' },
                error: { bg: 'bg-error-500', icon: 'alert-circle' },
                info: { bg: 'bg-brand-500', icon: 'info' },
            };
            const style = colors[type] || colors.info;
            
            const toast = document.createElement('div');
            toast.className = `pointer-events-auto ${style.bg} text-white px-6 py-4 rounded-lg shadow-lg flex items-center gap-3`;
            toast.innerHTML = `
                <i data-lucide="${style.icon}" class="w-5 h-5"></i>
                <div>
                    <p class="font-semibold">${title}</p>
                    ${message ? `<p class="text-sm opacity-90">${message}</p>` : ''}
                </div>
                <button onclick="this.parentElement.remove()" class="ml-auto">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            `;
            container.appendChild(toast);
            if (window.lucide) lucide.createIcons();
            
            setTimeout(() => toast.remove(), 4000);
        }

        // API helper
        async function fetchAPI(action, method = 'GET', body = null) {
            const opts = { method };
            if (body) opts.body = JSON.stringify(body);
            opts.headers = { 'Content-Type': 'application/json' };
            
            try {
                const res = await fetch(`${API_URL}?action=${action}`, opts);
                return await res.json();
            } catch (e) {
                console.error('API Error:', e);
                showToast('Connection Error', e.message, 'error');
                throw e;
            }
        }

        // Load data
        async function loadData() {
            try {
                const response = await fetchAPI('get_transfers');
                if (response.transfers && response.vehicles) {
                    transfers = response.transfers;
                    vehicles = response.vehicles;
                } else if (Array.isArray(response)) {
                    transfers = response;
                    vehicles = await fetchAPI('get_vehicles');
                }
                renderTable();
            } catch (e) {
                console.error('Load data error:', e);
            }
        }

        // Render table
        function renderTable() {
            const search = document.getElementById('search-input').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const replyFilter = document.getElementById('reply-filter').value;
            
            const newGrid = document.getElementById('new-cases-grid');
            const tbody = document.getElementById('table-body');
            newGrid.innerHTML = '';
            tbody.innerHTML = '';
            
            let newCount = 0;
            let activeCount = 0;

            transfers.forEach(t => {
                const match = (t.plate + t.name + (t.phone || '')).toLowerCase().includes(search);
                if (!match) return;
                if (statusFilter !== 'All' && t.status !== statusFilter) return;
                if (replyFilter !== 'All') {
                    if (replyFilter === 'Pending' && t.user_response && t.user_response !== 'Pending') return;
                    if (replyFilter !== 'Pending' && t.user_response !== replyFilter) return;
                }

                const dateStr = new Date(t.created_at || Date.now()).toLocaleDateString('en-GB', { 
                    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
                });

                if (t.status === 'New') {
                    newCount++;
                    newGrid.innerHTML += `
                        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] hover:shadow-md transition-all cursor-pointer" onclick="window.openEditModal(${t.id})">
                            <div class="flex justify-between items-start mb-3">
                                <span class="text-xs text-brand-600 font-semibold">${dateStr}</span>
                                <span class="text-xs text-gray-500">${t.amount} ₾</span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white/90">${t.plate}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${t.name}</p>
                            ${t.phone ? `<p class="text-xs text-gray-500 mt-1">${t.phone}</p>` : ''}
                        </div>
                    `;
                } else {
                    activeCount++;
                    tbody.innerHTML += `
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02] cursor-pointer" onclick="window.openEditModal(${t.id})">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-800 dark:text-white/90">${t.plate}</div>
                                <div class="text-sm text-gray-500">${t.name}</div>
                            </td>
                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">${t.amount} ₾</td>
                            <td class="px-5 py-4"><span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">${t.status}</span></td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">${t.phone || 'N/A'}</td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">${t.service_date || 'N/A'}</td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">${t.user_response || 'Pending'}</td>
                            <td class="px-5 py-4 text-right">
                                <button class="text-brand-500 hover:text-brand-600">
                                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }
            });

            document.getElementById('new-count').textContent = `(${newCount})`;
            document.getElementById('record-count').textContent = `${activeCount} active`;
            document.getElementById('new-cases-empty').classList.toggle('hidden', newCount > 0);
            document.getElementById('empty-state').classList.toggle('hidden', activeCount > 0);
            
            if (window.lucide) lucide.createIcons();
        }

        // Simplified modal open
        window.openEditModal = (id) => {
            const t = transfers.find(i => i.id == id);
            if (!t) return;
            
            document.getElementById('modal-title').textContent = `${t.plate} - ${t.name}`;
            document.getElementById('modal-content').innerHTML = `
                <p class="text-gray-600 dark:text-gray-400">Status: ${t.status}</p>
                <p class="text-gray-600 dark:text-gray-400">Amount: ${t.amount} ₾</p>
                <p class="text-gray-600 dark:text-gray-400">Phone: ${t.phone || 'N/A'}</p>
                <div class="mt-4 flex gap-2">
                    <button onclick="window.closeModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Close</button>
                    ${CAN_EDIT ? '<button class="px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600">Save Changes</button>' : ''}
                </div>
            `;
            document.getElementById('edit-modal').classList.remove('hidden');
        };

        window.closeModal = () => {
            document.getElementById('edit-modal').classList.add('hidden');
        };

        // Parse bank text (placeholder)
        window.parseBankText = () => {
            const text = document.getElementById('import-text').value;
            if (!text) return;
            
            // Simple parsing logic
            parsedImportData = [{
                plate: 'AA123BB',
                name: 'Sample Customer',
                amount: '1234.00',
                franchise: '273.97'
            }];
            
            document.getElementById('parsed-result').classList.remove('hidden');
            document.getElementById('parsed-placeholder').classList.add('hidden');
            document.getElementById('parsed-content').innerHTML = parsedImportData.map(i => 
                `<div class="bg-gray-50 p-3 rounded-lg dark:bg-gray-800">
                    <div class="font-bold">${i.plate}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">${i.name} - ${i.amount} ₾</div>
                </div>`
            ).join('');
        };

        window.saveParsedImport = async () => {
            for (let data of parsedImportData) {
                await fetchAPI('add_transfer', 'POST', data);
            }
            document.getElementById('import-text').value = '';
            document.getElementById('parsed-result').classList.add('hidden');
            document.getElementById('parsed-placeholder').classList.remove('hidden');
            loadData();
            showToast('Import Successful', `${parsedImportData.length} orders imported`);
        };

        window.openManualCreateModal = () => {
            showToast('Feature Coming Soon', 'Manual create modal will be implemented', 'info');
        };

        // Render vehicles (placeholder)
        function renderVehicles() {
            const tbody = document.getElementById('vehicles-table-body');
            tbody.innerHTML = vehicles.map(v => `
                <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                    <td class="px-6 py-4 font-semibold">${v.plate}</td>
                    <td class="px-6 py-4">${v.phone || 'N/A'}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${new Date(v.created_at || Date.now()).toLocaleDateString()}</td>
                    <td class="px-6 py-4 text-sm">${v.source || 'Manual'}</td>
                </tr>
            `).join('');
            
            document.getElementById('vehicles-count').textContent = `${vehicles.length} vehicles`;
            document.getElementById('vehicles-empty').classList.toggle('hidden', vehicles.length > 0);
            if (window.lucide) lucide.createIcons();
        }

        // Event listeners
        document.getElementById('search-input').addEventListener('input', renderTable);
        document.getElementById('status-filter').addEventListener('change', renderTable);
        document.getElementById('reply-filter').addEventListener('change', renderTable);
        document.getElementById('vehicles-search')?.addEventListener('input', renderVehicles);

        // Initialize
        loadData();
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
