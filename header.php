<?php
// Header component for OTOMOTORS portal
// Usage: include 'header.php'; before any HTML output
// Make sure $current_user_name and $current_user_role are set before including

// Check for language functionality (only if already loaded by the main page)
$language_available = false;
$current_lang = 'en'; // default
$LANGUAGES = ['en' => 'English', 'ka' => 'ქართული', 'ru' => 'Русский']; // fallback

// Only use language system if functions are already available
if (function_exists('get_current_language') && isset($GLOBALS['LANGUAGES'])) {
    $current_lang = get_current_language();
    $LANGUAGES = $GLOBALS['LANGUAGES'];
    $language_available = true;
}

// Ensure required variables are set
if (!isset($current_user_name)) $current_user_name = $_SESSION['full_name'] ?? 'User';
if (!isset($current_user_role)) $current_user_role = $_SESSION['role'] ?? 'user';

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
    'parts_collection' => ['icon' => 'wrench', 'label' => 'Parts Collection', 'url' => 'parts_collection.php'],
    'reviews' => ['icon' => 'star', 'label' => 'Reviews', 'url' => 'reviews.php'],
    'templates' => ['icon' => 'message-square-dashed', 'label' => 'SMS Templates', 'url' => 'templates.php']
];

// Add SMS Parsing page for admins
if ($current_user_role === 'admin') {
    $nav_items['sms_parsing'] = ['icon' => 'settings', 'label' => 'SMS Parsing', 'url' => 'sms_parsing.php'];
    $nav_items['users'] = ['icon' => 'users', 'label' => 'Users', 'url' => 'users.php'];
    $nav_items['translations'] = ['icon' => 'languages', 'label' => 'Translations', 'url' => 'translations.php'];
}
?>
<header class="bg-white/80 backdrop-blur-lg sticky top-0 z-40 border-b border-slate-200/80">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Header Left Side: Logo and Navigation -->
            <div class="flex items-center gap-8">
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-2">
                    <img src="https://otomotors.ge/wp-content/uploads/2023/10/logo.svg" alt="OTOMOTORS Logo" class="h-8 w-auto">
                    <span class="font-bold text-lg text-slate-800 tracking-tight">Manager Portal</span>
                </a>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex items-center gap-6">
                    <?php foreach ($nav_items as $page => $item): ?>
                        <a href="<?php echo $item['url']; ?>" 
                           class="flex items-center gap-2 text-sm font-semibold transition-colors
                                  <?php echo ($current_page === $page) 
                                      ? 'text-blue-600' 
                                      : 'text-slate-600 hover:text-blue-600'; ?>">
                            <i data-lucide="<?php echo $item['icon']; ?>" class="w-4 h-4"></i>
                            <span><?php echo $item['label']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Header Right Side: User Menu & Actions -->
            <div class="flex items-center gap-4">
                 <!-- Language Switcher -->
                <?php if ($language_available): ?>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900">
                        <i data-lucide="globe" class="w-4 h-4"></i>
                        <span><?php echo $LANGUAGES[$current_lang]; ?></span>
                        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="{'rotate-180': open}"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak class="absolute top-full right-0 mt-2 w-40 bg-white rounded-lg shadow-lg border border-slate-200 py-1">
                        <?php foreach ($LANGUAGES as $code => $name): ?>
                        <a href="?lang=<?php echo $code; ?>" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100"><?php echo $name; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- User Profile Dropdown -->
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2">
                        <img class="h-8 w-8 rounded-full object-cover" src="https://ui-avatars.com/api/?name=<?php echo urlencode($current_user_name); ?>&background=0D8ABC&color=fff" alt="User avatar">
                        <div>
                            <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($current_user_name); ?></span>
                            <span class="block text-xs text-slate-500 text-left"><?php echo ucfirst($current_user_role); ?></span>
                        </div>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak class="absolute top-full right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-slate-200 py-1">
                        <a href="logout.php" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            Logout
                        </a>
                    </div>
                </div>
                
                <!-- Mobile Menu Button -->
                <button class="md:hidden" @click="mobileMenuOpen = !mobileMenuOpen">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div x-show="mobileMenuOpen" x-cloak class="md:hidden border-t border-slate-200" x-transition>
        <nav class="flex flex-col gap-4 p-4">
             <?php foreach ($nav_items as $page => $item): ?>
                <a href="<?php echo $item['url']; ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg <?php echo ($current_page === $page) ? 'bg-blue-50 text-blue-600' : 'text-slate-700 hover:bg-slate-100'; ?>">
                    <i data-lucide="<?php echo $item['icon']; ?>" class="w-5 h-5"></i>
                    <span class="font-medium"><?php echo $item['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('header', () => ({
            mobileMenuOpen: false,
        }))
    })
</script>
