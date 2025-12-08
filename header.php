<?php
// Header component for OTOMOTORS portal
// Usage: include 'header.php'; before any HTML output
// Make sure $current_user_name and $current_user_role are set before including

require_once 'language.php';

$current_lang = get_current_language();

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
/* Modern Header Styles - CSP Compatible */
.modern-header {
    background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,250,252,0.95) 100%);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(226,232,240,0.8);
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    position: sticky;
    top: 0;
    z-index: 40;
}

.header-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 1rem;
}

.header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 4rem;
}

.logo-section {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.logo-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    color: inherit;
}

.logo-icon {
    position: relative;
    width: 2.5rem;
    height: 2.5rem;
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 4px 12px rgba(14,165,233,0.3);
}

.logo-text {
    font-size: 1.125rem;
    font-weight: 700;
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #c026d3 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
}

.logo-subtitle {
    font-size: 0.625rem;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
}

.nav-section {
    display: none;
}

@media (min-width: 1024px) {
    .nav-section {
        display: flex;
        background: rgba(241,245,249,0.8);
        padding: 0.375rem;
        border-radius: 0.75rem;
        border: 1px solid rgba(226,232,240,0.6);
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
    }
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    text-decoration: none;
    font-size: 0.875rem;
    transition: all 0.2s;
    font-weight: 500;
}

.nav-link-active {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    color: white;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(14,165,233,0.3);
}

.nav-link-inactive {
    color: #64748b;
}

.nav-link-inactive:hover {
    color: #0f172a;
    background: rgba(14,165,233,0.08);
    transform: translateY(-1px);
}

.user-section {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    position: relative;
    width: 2rem;
    height: 2rem;
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 0.875rem;
    box-shadow: 0 2px 8px rgba(14,165,233,0.3);
}

.user-info {
    display: none;
}

@media (min-width: 1024px) {
    .user-info {
        display: block;
        text-align: left;
    }
}

.user-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.user-role {
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 500;
    text-transform: capitalize;
    margin: 0;
}

.logout-link {
    color: #dc2626;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    transition: background-color 0.2s;
}

.logout-link:hover {
    background: rgba(220,38,38,0.1);
    color: #b91c1c;
}

/* Language Selector */
.language-selector {
    position: relative;
    display: none;
}

@media (min-width: 640px) {
    .language-selector {
        display: block;
    }
}

.language-select {
    appearance: none;
    background: rgba(241,245,249,0.8);
    border: 1px solid rgba(226,232,240,0.6);
    border-radius: 0.5rem;
    color: #475569;
    font-size: 0.875rem;
    font-weight: 500;
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 80px;
}

.language-select:hover {
    background: rgba(14,165,233,0.05);
    border-color: rgba(14,165,233,0.3);
}

.language-select:focus {
    outline: none;
    ring: 2px;
    ring-color: rgba(14,165,233,0.2);
    border-color: #0ea5e9;
}

.language-icon {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #64748b;
    width: 1rem;
    height: 1rem;
}

/* Mobile menu button */
.mobile-menu-btn {
    display: block;
    padding: 0.5rem;
    background: rgba(241,245,249,0.8);
    border: 1px solid rgba(226,232,240,0.6);
    border-radius: 0.5rem;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}

.mobile-menu-btn:hover {
    background: rgba(14,165,233,0.1);
    color: #0ea5e9;
}

@media (min-width: 1024px) {
    .mobile-menu-btn {
        display: none;
    }
}
</style>

<nav class="modern-header">
    <div class="header-container">
        <div class="header-flex">
            <!-- Logo Section -->
            <div class="logo-section">
                <a href="index.php" class="logo-link">
                    <div class="logo-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H9.6c-.7 0-1.3.3-1.8.7C7 8.6 5.7 10 5.7 10S4.3 10.6 3.5 11.1C2.7 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2"/>
                            <circle cx="7" cy="17" r="2"/>
                            <path d="M9 17h6"/>
                            <circle cx="17" cy="17" r="2"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="logo-text">OTOMOTORS</h1>
                        <p class="logo-subtitle">Service Manager</p>
                    </div>
                </a>
            </div>

            <!-- Navigation Section -->
            <div class="nav-section">
                <?php foreach ($nav_items as $page => $item): ?>
                    <?php $is_active = ($current_page === $page); ?>
                    <a href="<?php echo $item['url']; ?>" class="nav-link <?php echo $is_active ? 'nav-link-active' : 'nav-link-inactive'; ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <?php
                            $icons = [
                                'layout-dashboard' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
                                'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
                                'star' => '<polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>',
                                'message-square-dashed' => '<path d="M10 11V8"/><path d="M7 11v-1"/><path d="M14 11v-1"/><path d="M17 11V8"/><path d="M3 7.8c0-1.7 1.3-3 3-3h12c1.7 0 3 1.3 3 3v6.4c0 1.7-1.3 3-3 3h-2l-3 3-3-3H6c-1.7 0-3-1.3-3-3z"/>',
                                'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                                'languages' => '<path d="M5 8l6 6"/><path d="M4 14l6-6"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="M22 22l-5-10-5 10"/><path d="M14 18h4"/>'
                            ];
                            echo $icons[$item['icon']] ?? '<circle cx="12" cy="12" r="10"/>';
                            ?>
                        </svg>
                        <span><?php echo $item['label']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- User Section -->
            <div class="user-section">
                <div class="language-selector">
                    <select class="language-select" onchange="changeLanguage(this.value)">
                        <?php foreach ($LANGUAGES as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
                                <?php echo $code === 'en' ? 'EN' : ($code === 'ka' ? 'KA' : 'RU'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="language-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        <path d="M2 12h20"/>
                    </svg>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                </div>
                <div class="user-info">
                    <p class="user-name"><?php echo htmlspecialchars($current_user_name); ?></p>
                    <p class="user-role"><?php echo htmlspecialchars($current_user_role); ?></p>
                </div>
                <a href="logout.php" class="logout-link">Logout</a>
                <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 0 0 0.75rem 0.75rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <div style="padding: 1rem;">
            <!-- Mobile Language Selector -->
            <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e2e8f0;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem;">Language</label>
                <select class="language-select" onchange="changeLanguage(this.value)" style="width: 100%;">
                    <?php foreach ($LANGUAGES as $code => $name): ?>
                        <option value="<?php echo $code; ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php foreach ($nav_items as $page => $item): ?>
                <?php $is_active = ($current_page === $page); ?>
                <a href="<?php echo $item['url']; ?>" style="display: block; padding: 0.75rem; text-decoration: none; color: <?php echo $is_active ? '#0ea5e9' : '#475569'; ?>; font-weight: <?php echo $is_active ? '600' : '500'; ?>; border-radius: 0.375rem; margin-bottom: 0.25rem; <?php echo $is_active ? 'background: rgba(14,165,233,0.1);' : ''; ?>">
                    <?php echo $item['label']; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

function changeLanguage(lang) {
    // Create form data for POST request
    const formData = new FormData();
    formData.append('language', lang);

    // Use XMLHttpRequest instead of fetch for better compatibility
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api.php?action=set_language', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const result = JSON.parse(xhr.responseText);
                if (result.success) {
                    window.location.reload();
                } else {
                    console.error('Failed to change language:', result.message);
                }
            } catch (e) {
                console.error('Invalid response:', xhr.responseText);
            }
        } else {
            console.error('Request failed:', xhr.status);
        }
    };
    xhr.onerror = function() {
        console.error('Network error');
    };
    xhr.send(formData);
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(e) {
    const menu = document.getElementById('mobile-menu');
    const button = e.target.closest('.mobile-menu-btn');
    if (!button && !menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});
</script>
