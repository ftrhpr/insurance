<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';

// Only admin can access language management
if ($current_user_role !== 'admin') {
    header('Location: index.php');
    exit();
}

require_once 'language.php';
require_once 'config.php';

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_string':
                if (isset($_POST['key']) && isset($_POST['value'])) {
                    Language::setString($_POST['key'], $_POST['value']);
                    $message = 'Language string updated successfully!';
                    $messageType = 'success';
                }
                break;

            case 'add_language':
                if (isset($_POST['lang_code']) && isset($_POST['lang_name'])) {
                    $langCode = trim($_POST['lang_code']);
                    $langName = trim($_POST['lang_name']);

                    if (preg_match('/^[a-z]{2,3}$/', $langCode)) {
                        // Create new language file based on English
                        $enStrings = json_decode(file_get_contents(__DIR__ . '/languages/en.json'), true);
                        $enStrings['app']['language_name'] = $langName;

                        if (Language::saveLanguage($langCode, $enStrings)) {
                            $message = "Language '$langName' ($langCode) created successfully!";
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to create language file.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Invalid language code. Use 2-3 lowercase letters (e.g., en, ka, ru).';
                        $messageType = 'error';
                    }
                }
                break;

            case 'delete_language':
                if (isset($_POST['lang_code']) && $_POST['lang_code'] !== 'en') {
                    if (Language::deleteLanguage($_POST['lang_code'])) {
                        $message = 'Language deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete language.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Cannot delete English language.';
                    $messageType = 'error';
                }
                break;

            case 'switch_language':
                if (isset($_POST['language'])) {
                    $_SESSION['language'] = $_POST['language'];
                    Language::init($_POST['language']);
                    $message = 'Language switched successfully!';
                    $messageType = 'success';
                }
                break;
        }
    }
}

$currentLang = Language::getCurrentLanguage();
$availableLanguages = Language::getAvailableLanguages();
$allStrings = Language::getAllStrings();

// Flatten the nested array for easier editing
function flattenArray($array, $prefix = '') {
    $result = [];
    foreach ($array as $key => $value) {
        $newKey = $prefix ? $prefix . '.' . $key : $key;
        if (is_array($value)) {
            $result = array_merge($result, flattenArray($value, $newKey));
        } else {
            $result[$newKey] = $value;
        }
    }
    return $result;
}

$flatStrings = flattenArray($allStrings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Language::get('languages.title'); ?> - OTOMOTORS</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
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
                        }
                    }
                }
            }
        }
    </script>

    <style>
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
    </style>
</head>
<body class="bg-slate-50 font-sans">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-slate-900"><?php echo Language::get('languages.title'); ?></h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-slate-600"><?php echo htmlspecialchars($current_user_name); ?></span>
                    <a href="index.php" class="text-slate-600 hover:text-slate-900">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Language Selection -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4"><?php echo Language::get('languages.current_language'); ?></h2>
            <form method="POST" class="flex items-center space-x-4">
                <input type="hidden" name="action" value="switch_language">
                <select name="language" class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <?php foreach ($availableLanguages as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo $code === $currentLang ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?> (<?php echo $code; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500">
                    <?php echo Language::get('common.save'); ?>
                </button>
            </form>
        </div>

        <!-- Add New Language -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4"><?php echo Language::get('languages.add_language'); ?></h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input type="hidden" name="action" value="add_language">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?php echo Language::get('languages.language_code'); ?></label>
                    <input type="text" name="lang_code" placeholder="en" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?php echo Language::get('languages.language_name'); ?></label>
                    <input type="text" name="lang_name" placeholder="English" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500">
                        <?php echo Language::get('languages.add_language'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Language Strings Editor -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900"><?php echo Language::get('languages.edit_language'); ?> (<?php echo strtoupper($currentLang); ?>)</h2>
                <p class="text-sm text-slate-600 mt-1"><?php echo Language::get('languages.description'); ?></p>
            </div>

            <div class="p-6">
                <div class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar">
                    <?php foreach ($flatStrings as $key => $value): ?>
                    <div class="border border-slate-200 rounded-lg p-4">
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="save_string">
                            <input type="hidden" name="key" value="<?php echo htmlspecialchars($key); ?>">

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1"><?php echo Language::get('languages.key'); ?></label>
                                <input type="text" value="<?php echo htmlspecialchars($key); ?>" class="w-full px-3 py-2 bg-slate-100 border border-slate-300 rounded-lg font-mono text-sm" readonly>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1"><?php echo Language::get('languages.value'); ?></label>
                                <textarea name="value" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none"><?php echo htmlspecialchars($value); ?></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 text-sm">
                                    <?php echo Language::get('languages.save'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Available Languages Management -->
        <?php if (count($availableLanguages) > 1): ?>
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mt-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4"><?php echo Language::get('languages.select_language'); ?></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($availableLanguages as $code => $name): ?>
                <div class="border border-slate-200 rounded-lg p-4 <?php echo $code === $currentLang ? 'bg-primary-50 border-primary-300' : ''; ?>">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-medium text-slate-900"><?php echo htmlspecialchars($name); ?></h3>
                            <p class="text-sm text-slate-500"><?php echo $code; ?></p>
                        </div>
                        <?php if ($code !== 'en'): ?>
                        <form method="POST" class="ml-2" onsubmit="return confirm('<?php echo Language::get('languages.confirm_delete'); ?>')">
                            <input type="hidden" name="action" value="delete_language">
                            <input type="hidden" name="lang_code" value="<?php echo $code; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800 p-1">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('[class*="bg-green-50"], [class*="bg-red-50"]');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>