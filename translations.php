<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'language.php';

// Check admin access
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get user info from session
$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'];

// Database connection
require_once 'config.php';

// Initialize default translations if needed
initialize_default_translations();

$current_lang = $_GET['lang'] ?? get_current_language();
$available_langs = $GLOBALS['LANGUAGES'];

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get translations for current language
    $stmt = $pdo->prepare("
        SELECT t.translation_key, t.translation_text, d.translation_text as default_text
        FROM translations t
        LEFT JOIN translations d ON t.translation_key = d.translation_key AND d.language_code = ?
        WHERE t.language_code = ?
        ORDER BY t.translation_key
    ");
    $stmt->execute([DEFAULT_LANGUAGE, $current_lang]);
    $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get missing translations
    $missing_keys = get_missing_translations($current_lang);
    $missing_translations = [];
    if (!empty($missing_keys)) {
        $placeholders = str_repeat('?,', count($missing_keys) - 1) . '?';
        $stmt = $pdo->prepare("SELECT translation_key, translation_text FROM translations WHERE language_code = ? AND translation_key IN ($placeholders)");
        $stmt->execute(array_merge([DEFAULT_LANGUAGE], $missing_keys));
        $defaults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($defaults as $default) {
            $missing_translations[] = [
                'translation_key' => $default['translation_key'],
                'translation_text' => '',
                'default_text' => $default['translation_text']
            ];
        }
    }

} catch (PDOException $e) {
    $translations = [];
    $missing_translations = [];
    error_log("Database error in translations.php: " . $e->getMessage());
}

// Helper: determine a human-friendly group for a translation key (used to surface index/dashboard keys)
function get_translation_group($key) {
    $map = [
        'dashboard.' => 'Dashboard',
        'nav.' => 'Navigation',
        'status.' => 'Status',
        'reschedule.' => 'Reschedule',
        'validation.' => 'Validation',
        'sms.' => 'SMS',
        'import.' => 'Import',
        'notifications.' => 'Notifications',
        'action.' => 'Actions',
        'error.' => 'Errors',
        'success.' => 'Success'
    ];
    foreach ($map as $prefix => $label) {
        if (strpos($key, $prefix) === 0) return $label;
    }
    if ($key === 'processing') return 'Status';
    return 'Other';
}

// Build list of groups present in fetched translations (for the filter selector)
$allKeys = array_column(array_merge($translations, $missing_translations), 'translation_key');
$groupsFound = [];
foreach ($allKeys as $k) {
    if (!$k) continue;
    $g = get_translation_group($k);
    if (!in_array($g, $groupsFound)) $groupsFound[] = $g;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('nav.translations', 'Translations'); ?> - OTOMOTORS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/fonts/include_fonts.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['BPG Arial Caps', 'BPG Arial', 'Inter', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e'
                        },
                        accent: {
                            50: '#fdf4ff', 100: '#fae8ff', 500: '#d946ef', 600: '#c026d3'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Use BPG Arial family when available */
        * { font-family: 'BPG Arial Caps', 'BPG Arial', Arial, sans-serif; }
        .gradient-primary {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        }
        .gradient-primary:hover {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 text-slate-800 font-sans">
    <div class="flex">
        <?php include 'sidebar.php'; ?>
        <main class="flex-1 ml-64 p-8">

        <!-- Main Content -->
        <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-slate-200/60 p-8">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800"><?php echo __('nav.translations', 'Translations'); ?></h2>
                    <p class="text-slate-600 mt-1">Manage translations for different languages</p>
                </div>
                <div class="flex gap-3">
                    <!-- Language Selector -->
                    <select id="language-select" onchange="changeLanguage(this.value)" class="px-4 py-2 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                        <?php foreach ($available_langs as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="exportTranslations()" class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors text-sm font-medium">
                        <i data-lucide="download" class="w-4 h-4 inline mr-2"></i>Export
                    </button>
                </div>

                <!-- Group filter & search -->
                <div class="flex items-center gap-3 mt-3">
                    <label class="text-sm text-slate-600">Group:</label>
                    <select id="group-filter" onchange="filterByGroup(this.value)" class="px-3 py-2 border border-slate-200 rounded-lg text-sm">
                        <option value="">All</option>
                        <?php foreach ($groupsFound as $g): ?>
                            <option value="<?php echo htmlspecialchars($g); ?>"><?php echo htmlspecialchars($g); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input id="key-search" placeholder="Search key or default..." oninput="filterTable()" class="px-3 py-2 border border-slate-200 rounded-lg text-sm ml-2 flex-1" />
                </div>
            </div>

            <!-- Progress Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-100 p-2 rounded-lg">
                            <i data-lucide="check-circle" class="w-5 h-5 text-blue-600"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-blue-900"><?php echo count($translations); ?></div>
                            <div class="text-sm text-blue-700">Translated</div>
                        </div>
                    </div>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-orange-100 p-2 rounded-lg">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-orange-600"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-orange-900"><?php echo count($missing_translations); ?></div>
                            <div class="text-sm text-orange-700">Missing</div>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-green-100 p-2 rounded-lg">
                            <i data-lucide="percent" class="w-5 h-5 text-green-600"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-green-900">
                                <?php
                                $total = count($translations) + count($missing_translations);
                                echo $total > 0 ? round((count($translations) / $total) * 100) : 0;
                                ?>%
                            </div>
                            <div class="text-sm text-green-700">Complete</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Translations Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-800">
                        <?php echo $available_langs[$current_lang] ?? $current_lang; ?> Translations
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Key</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Default (English)</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">
                                    Translation (<?php echo $available_langs[$current_lang] ?? $current_lang; ?>)
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <!-- Existing Translations -->
                            <?php foreach ($translations as $translation): ?>
                            <?php $group = get_translation_group($translation['translation_key']); ?>
                            <tr class="hover:bg-slate-50 translation-row" data-group="<?php echo htmlspecialchars($group); ?>">
                                <td class="px-6 py-4 text-sm font-mono text-slate-800"><?php echo htmlspecialchars($translation['translation_key']); ?> <span class="ml-2 text-xs text-slate-400"><?php echo htmlspecialchars($group); ?></span></td>
                                <td class="px-6 py-4 text-sm text-slate-600"><?php echo htmlspecialchars($translation['default_text']); ?></td>
                                <td class="px-6 py-4">
                                    <input type="text"
                                           class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none translation-input"
                                           data-key="<?php echo htmlspecialchars($translation['translation_key']); ?>"
                                           value="<?php echo htmlspecialchars($translation['translation_text']); ?>">
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button onclick="saveTranslation('<?php echo htmlspecialchars($translation['translation_key']); ?>')"
                                            class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors save-btn">
                                        Save
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <!-- Missing Translations -->
                            <?php foreach ($missing_translations as $translation): ?>
                            <?php $group = get_translation_group($translation['translation_key']); ?>
                            <tr class="hover:bg-orange-50 bg-orange-25 translation-row" data-group="<?php echo htmlspecialchars($group); ?>">
                                <td class="px-6 py-4 text-sm font-mono text-orange-800"><?php echo htmlspecialchars($translation['translation_key']); ?> <span class="ml-2 text-xs text-orange-600"><?php echo htmlspecialchars($group); ?></span></td>
                                <td class="px-6 py-4 text-sm text-slate-600"><?php echo htmlspecialchars($translation['default_text']); ?></td>
                                <td class="px-6 py-4">
                                    <input type="text"
                                           class="w-full px-3 py-2 border border-orange-200 rounded-lg text-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none translation-input"
                                           data-key="<?php echo htmlspecialchars($translation['translation_key']); ?>"
                                           placeholder="Enter translation..."
                                           value="">
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button onclick="saveTranslation('<?php echo htmlspecialchars($translation['translation_key']); ?>')"
                                            class="px-3 py-1 bg-orange-600 text-white text-xs rounded hover:bg-orange-700 transition-colors save-btn">
                                        Add
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (empty($translations) && empty($missing_translations)): ?>
            <div class="text-center py-12">
                <i data-lucide="file-text" class="w-12 h-12 text-slate-400 mx-auto mb-4"></i>
                <h3 class="text-lg font-medium text-slate-900 mb-2">No translations found</h3>
                <p class="text-slate-600">Default translations will be initialized automatically.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const API_URL = 'api.php';
        const CURRENT_LANG = '<?php echo $current_lang; ?>';
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

        function changeLanguage(lang) {
            window.location.href = `translations.php?lang=${lang}`;
        }

        async function saveTranslation(key) {
            const input = document.querySelector(`input[data-key="${key}"]`);
            const saveBtn = input.closest('tr').querySelector('.save-btn');
            const originalText = saveBtn.textContent;

            if (!input || !input.value.trim()) {
                showToast('Please enter a translation', '', 'error');
                return;
            }

            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;

            try {
                const response = await fetch(`${API_URL}?action=save_translation`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        key: key,
                        text: input.value.trim(),
                        lang: CURRENT_LANG
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Translation saved successfully', '', 'success');
                    saveBtn.textContent = 'Saved';
                    setTimeout(() => {
                        saveBtn.textContent = originalText;
                    }, 2000);
                } else {
                    throw new Error(result.message || 'Failed to save translation');
                }
            } catch (error) {
                console.error('Error saving translation:', error);
                showToast('Failed to save translation', error.message, 'error');
                saveBtn.textContent = originalText;
            } finally {
                saveBtn.disabled = false;
            }
        }

        async function exportTranslations() {
            try {
                const response = await fetch(`${API_URL}?action=export_translations&lang=${CURRENT_LANG}`);
                const data = await response.json();

                if (data.success) {
                    // Create and download JSON file
                    const blob = new Blob([JSON.stringify(data.translations, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `translations_${CURRENT_LANG}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);

                    showToast('Translations exported successfully', '', 'success');
                } else {
                    throw new Error(data.message || 'Export failed');
                }
            } catch (error) {
                console.error('Error exporting translations:', error);
                showToast('Failed to export translations', error.message, 'error');
            }
        }

        function showToast(title, message = '', type = 'info') {
            const container = document.getElementById('toast-container') || createToastContainer();

            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 transform transition-all duration-300 translate-x-full`;

            const icons = {
                success: 'check-circle',
                error: 'x-circle',
                info: 'info',
                warning: 'alert-circle'
            };

            const colors = {
                success: 'text-green-600 bg-green-100',
                error: 'text-red-600 bg-red-100',
                info: 'text-blue-600 bg-blue-100',
                warning: 'text-yellow-600 bg-yellow-100'
            };

            toast.innerHTML = `
                <div class="p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i data-lucide="${icons[type]}" class="w-6 h-6 ${colors[type].split(' ')[0]}"></i>
                        </div>
                        <div class="ml-3 w-0 flex-1 pt-0.5">
                            <p class="text-sm font-medium text-gray-900">${title}</p>
                            ${message ? `<p class="mt-1 text-sm text-gray-500">${message}</p>` : ''}
                        </div>
                        <div class="ml-4 flex-shrink-0 flex">
                            <button onclick="this.parentElement.parentElement.parentElement.remove()" class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(toast);
            lucide.createIcons();

            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);

            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-0 right-0 z-50 p-4 space-y-4 pointer-events-none';
            document.body.appendChild(container);
            return container;
        }

        // Filtering helpers for groups & search
        function filterByGroup(group) {
            document.querySelectorAll('table tbody tr.translation-row').forEach(r => {
                const rowGroup = r.dataset.group || '';
                r.style.display = (!group || group === '' || group === 'All' || rowGroup === group) ? '' : 'none';
            });
        }

        function filterTable() {
            const group = document.getElementById('group-filter')?.value || '';
            const q = (document.getElementById('key-search')?.value || '').toLowerCase().trim();
            document.querySelectorAll('table tbody tr.translation-row').forEach(r => {
                const rowGroup = r.dataset.group || '';
                const key = (r.querySelector('td')?.textContent || '').toLowerCase();
                const defaultText = (r.children[1]?.textContent || '').toLowerCase();
                const matchesGroup = !group || group === '' || group === 'All' || rowGroup === group;
                const matchesQuery = !q || key.includes(q) || defaultText.includes(q);
                r.style.display = (matchesGroup && matchesQuery) ? '' : 'none';
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('group-filter')?.addEventListener('change', (e) => filterByGroup(e.target.value));
            document.getElementById('key-search')?.addEventListener('input', filterTable);
        });

        // Auto-save on input change (debounced)
        let saveTimeout;
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('translation-input')) {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    const key = e.target.dataset.key;
                    if (key && e.target.value.trim()) {
                        saveTranslation(key);
                    }
                }, 2000); // Auto-save after 2 seconds of no typing
            }
        });

        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>