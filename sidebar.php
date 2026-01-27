<?php
// sidebar.php

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure required variables are set, with fallbacks
$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'user';

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!-- Sidebar -->
<aside class="w-64 bg-white border-r border-slate-200 flex flex-col justify-between py-6 px-4 fixed inset-y-0 left-0 z-40">
    <!-- Logo -->
    <div>
        <a href="index.php" class="flex items-center gap-3 mb-8">
            <div class="rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 w-10 h-10 flex items-center justify-center text-white shadow-lg">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H9.6c-.7 0-1.3.3-1.8.7C7 8.6 5.7 10 5.7 10S4.3 10.6 3.5 11.1C2.7 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
            </div>
            <div>
                <span class="block text-lg font-bold bg-gradient-to-r from-blue-500 via-blue-700 to-purple-600 bg-clip-text text-transparent">OTOMOTORS</span>
                <span class="block text-xs text-slate-400 font-semibold uppercase tracking-wide">Service Manager</span>
            </div>
        </a>
        <!-- Navigation -->
        <nav class="flex flex-col gap-1 mb-8">
            <?php
            $current_page = basename($_SERVER['PHP_SELF'], '.php');
            $nav_items = [
                'index' => ['icon' => 'layout-dashboard', 'label' => 'Dashboard', 'url' => 'index.php'],
                'calendar' => ['icon' => 'calendar', 'label' => 'Calendar', 'url' => 'calendar.php'],
                'vehicles' => ['icon' => 'database', 'label' => 'Vehicle DB', 'url' => 'vehicles.php'],
                'parts_collection' => ['icon' => 'wrench', 'label' => 'Parts Collection', 'url' => 'parts_collection.php'],
                'technician_dashboard' => ['icon' => 'users', 'label' => 'Technician Dashboard', 'url' => 'technician_dashboard.php'],
                'reviews' => ['icon' => 'star', 'label' => 'Reviews', 'url' => 'reviews.php'],
                'templates' => ['icon' => 'message-square-dashed', 'label' => 'SMS Templates', 'url' => 'templates.php']
            ];
            
            // Filter navigation items based on role
            if ($current_user_role === 'technician') {
                // Technicians see their dashboard and basic info pages
                $nav_items = [
                    'technician_dashboard' => ['icon' => 'users', 'label' => 'My Dashboard', 'url' => 'https://portal.otoexpress.ge/technician_dashboard.php'],
                    'calendar' => ['icon' => 'calendar', 'label' => 'Calendar', 'url' => 'calendar.php'],
                    'vehicles' => ['icon' => 'database', 'label' => 'Vehicle DB', 'url' => 'vehicles.php']
                ];
            } elseif ($current_user_role === 'viewer') {
                // Viewers see limited navigation
                unset($nav_items['parts_collection'], $nav_items['templates']);
            }
            
            if ($current_user_role === 'admin') {
                $nav_items['sms_parsing'] = ['icon' => 'settings', 'label' => 'SMS Parsing', 'url' => 'sms_parsing.php'];
                $nav_items['users'] = ['icon' => 'users', 'label' => 'Users', 'url' => 'users.php'];
                $nav_items['translations'] = ['icon' => 'languages', 'label' => 'Translations', 'url' => 'translations.php'];
                $nav_items['workflow'] = ['icon' => 'layout-kanban', 'label' => 'Workflow', 'url' => 'workflow.php'];
                $nav_items['status_settings'] = ['icon' => 'layers', 'label' => 'Status Settings', 'url' => 'status_settings.php'];
            }
            $icons = [
                'layout-dashboard' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
                'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
                'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
                'star' => '<polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>',
                'message-square-dashed' => '<path d="M10 11V8"/><path d="M7 11v-1"/><path d="M14 11v-1"/><path d="M17 11V8"/><path d="M3 7.8c0-1.7 1.3-3 3-3h12c1.7 0 3 1.3 3 3v6.4c0 1.7-1.3 3-3 3h-2l-3 3-3-3H6c-1.7 0-3-1.3-3-3z"/>',
                'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                'languages' => '<path d="M5 8l6 6"/><path d="M4 14l6-6"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="M22 22l-5-10-5 10"/><path d="M14 18h4"/>',
                'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
                'layers' => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
                'layout-kanban' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7v6"/><path d="M12 7v10"/><path d="M17 7v4"/>'
            ];
            foreach ($nav_items as $page => $item):
                $is_active = ($current_page === $page);
            ?>
                <a href="<?php echo $item['url']; ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg font-medium text-sm transition <?php echo $is_active ? 'bg-blue-100 text-blue-700' : 'text-slate-700 hover:bg-slate-100'; ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="flex-shrink-0"><?php echo $icons[$item['icon']] ?? '<circle cx="12" cy="12" r="10"/>'; ?></svg>
                    <span><?php echo $item['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <!-- Language Selector -->
        <?php if (function_exists('get_current_language') && isset($GLOBALS['LANGUAGES'])): ?>
        <div class="mb-4">
            <label class="block text-xs font-semibold text-slate-500 mb-1">Language</label>
            <select class="w-full rounded-md border border-slate-200 py-1.5 px-2 text-sm text-slate-700" onchange="changeLanguage(this.value)">
                <?php foreach ($GLOBALS['LANGUAGES'] as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo $code === get_current_language() ? 'selected' : ''; ?>><?php echo $name; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <!-- Refresh Button -->
        <button onclick="refreshProcessingQueue()" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-100 hover:bg-blue-100 text-slate-700 font-medium text-sm mb-4">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
            <span>Refresh</span>
        </button>
    </div>
    <!-- User Info & Logout -->
    <div>
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white font-bold text-base shadow">
                <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
            </div>
            <div>
                <div class="font-semibold text-slate-800 text-sm"><?php echo htmlspecialchars($current_user_name); ?></div>
                <div class="text-xs text-slate-500 capitalize"><?php echo htmlspecialchars($current_user_role); ?></div>
            </div>
        </div>
        <div id="connection-status" class="flex items-center gap-2 text-xs text-slate-500 mb-2">
            <span class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></span> Connecting...
        </div>
        <a href="logout.php" class="block w-full text-center py-2 rounded-md bg-red-50 text-red-600 font-semibold text-sm hover:bg-red-100 transition">Logout</a>
    </div>
</aside>
<script>
// This script can be included in pages that use the sidebar, or in a global script file.
function changeLanguage(lang) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api.php?action=set_language', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function() {
        if (xhr.status === 200) {
            const result = JSON.parse(xhr.responseText);
            if (result.success) {
                window.location.reload();
            } else {
                console.error('Failed to change language:', result.message);
            }
        } else {
            console.error('Request failed to change language');
        }
    };
    xhr.send(JSON.stringify({ language: lang }));
}

function refreshProcessingQueue() {
    if (typeof loadData === 'function') {
        loadData();
    } else {
        window.location.reload();
    }
}
</script>
