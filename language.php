<?php
// language.php - Multilanguage system for OTOMOTORS portal
// Include this file in all PHP files that need translations

require_once 'config.php';

// Default language
define('DEFAULT_LANGUAGE', 'en');

// Available languages
$LANGUAGES = [
    'en' => 'English',
    'ka' => 'ქართული (Georgian)',
    'ru' => 'Русский (Russian)'
];

// Get current language from session or default
function get_current_language() {
    return $_SESSION['language'] ?? DEFAULT_LANGUAGE;
}

// Set current language
function set_language($lang) {
    if (array_key_exists($lang, $GLOBALS['LANGUAGES'])) {
        $_SESSION['language'] = $lang;
        return true;
    }
    return false;
}

// Translation function
function __($key, $default = '', $lang = null) {
    static $translations = [];

    $current_lang = $lang ?? get_current_language();

    // If default language, return the key or default
    if ($current_lang === DEFAULT_LANGUAGE) {
        return $default ?: $key;
    }

    // Load translations for this language if not already loaded
    if (!isset($translations[$current_lang])) {
        $translations[$current_lang] = load_translations($current_lang);
    }

    // Return translation or fallback to default/key
    return $translations[$current_lang][$key] ?? ($default ?: $key);
}

// Load translations from database
function load_translations($lang) {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT translation_key, translation_text FROM translations WHERE language_code = ?");
        $stmt->execute([$lang]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $translations = [];
        foreach ($results as $row) {
            $translations[$row['translation_key']] = $row['translation_text'];
        }

        return $translations;
    } catch (PDOException $e) {
        error_log("Error loading translations: " . $e->getMessage());
        return [];
    }
}

// Save translation to database
function save_translation($key, $text, $lang) {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Insert or update
        $stmt = $pdo->prepare("INSERT INTO translations (translation_key, language_code, translation_text, updated_at)
                              VALUES (?, ?, ?, NOW())
                              ON DUPLICATE KEY UPDATE translation_text = VALUES(translation_text), updated_at = NOW()");
        return $stmt->execute([$key, $lang, $text]);
    } catch (PDOException $e) {
        error_log("Error saving translation: " . $e->getMessage());
        return false;
    }
}

// Get all translations for a language (for admin interface)
function get_all_translations($lang = null) {
    $lang = $lang ?? get_current_language();

    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT translation_key, translation_text FROM translations WHERE language_code = ? ORDER BY translation_key");
        $stmt->execute([$lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting all translations: " . $e->getMessage());
        return [];
    }
}

// Get translation keys that need translation (missing translations)
function get_missing_translations($lang) {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get all keys from default language
        $stmt = $pdo->prepare("SELECT DISTINCT translation_key FROM translations WHERE language_code = ?");
        $stmt->execute([DEFAULT_LANGUAGE]);
        $default_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($default_keys)) {
            return [];
        }

        // Get keys that exist in target language
        $placeholders = str_repeat('?,', count($default_keys) - 1) . '?';
        $stmt = $pdo->prepare("SELECT translation_key FROM translations WHERE language_code = ? AND translation_key IN ($placeholders)");
        $params = array_merge([$lang], $default_keys);
        $stmt->execute($params);
        $existing_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Return keys that don't exist in target language
        return array_diff($default_keys, $existing_keys);
    } catch (PDOException $e) {
        error_log("Error getting missing translations: " . $e->getMessage());
        return [];
    }
}

// Initialize default translations if they don't exist
function initialize_default_translations() {
    $default_translations = [
        // Navigation and common
        'nav.dashboard' => 'Dashboard',
        'nav.transfers' => 'Transfers',
        'nav.vehicles' => 'Vehicles',
        'nav.reviews' => 'Reviews',
        'nav.templates' => 'Templates',
        'nav.users' => 'Users',
        'nav.logout' => 'Logout',

        // Dashboard
        'dashboard.title' => 'Dashboard',
        'dashboard.quick_import' => 'Quick Import',
        'dashboard.quick_import_desc' => 'Paste SMS or bank statement text to auto-detect transfers.',
        'dashboard.manual_create' => 'Manual Create',
        'dashboard.sample' => 'Sample',
        'dashboard.detect' => 'Detect',
        'dashboard.ready_to_import' => 'Ready to Import',
        'dashboard.confirm_save' => 'Confirm & Save',
        'dashboard.search_placeholder' => 'Search plates, names, phones...',
        'dashboard.all_replies' => 'All Replies',
        'dashboard.confirmed' => 'Confirmed',
        'dashboard.reschedule' => 'Reschedule',
        'dashboard.pending' => 'Not Responded',
        'dashboard.all_active_stages' => 'All Active Stages',
        'dashboard.processing' => 'Processing',
        'dashboard.called' => 'Contacted',
        'dashboard.parts_ordered' => 'Parts Ordered',
        'dashboard.parts_arrived' => 'Parts Arrived',
        'dashboard.scheduled' => 'Scheduled',
        'dashboard.completed' => 'Completed',
        'dashboard.issue' => 'Issue',
        'dashboard.new_requests' => 'New Requests',
        'dashboard.processing_queue' => 'Processing Queue',
        'dashboard.vehicle_owner' => 'Vehicle & Owner',
        'dashboard.status' => 'Status',
        'dashboard.amount' => 'Amount',
        'dashboard.phone' => 'Phone',
        'dashboard.actions' => 'Actions',
        'dashboard.edit' => 'Edit',
        'dashboard.delete' => 'Delete',
        'dashboard.view_details' => 'View Details',
        'dashboard.no_new_requests' => 'No new incoming requests',
        'dashboard.loading' => 'Loading your workspace...',
        'dashboard.connecting' => 'CONNECTING...',

        // Status messages
        'status.new_cases_added' => 'new cases added.',
        'status.import_successful' => 'orders imported successfully',
        'status.import_errors' => 'succeeded, {failed} failed',
        'status.system_alert' => 'System Alert: {count} new transfer(s) added to OTOMOTORS portal.',

        // SMS Templates
        'sms.registered' => "Hello {name}, payment received. Ref: {plate}. Welcome to OTOMOTORS service.",
        'sms.schedule' => "Hello {name}, your service is scheduled for {date}. Ref: {plate}. - OTOMOTORS",
        'sms.parts_arrived' => "Hello {name}, parts arrived for {plate}. Confirm service: {link} - OTOMOTORS",
        'sms.completed' => "Service for {plate} is completed. Thank you for choosing OTOMOTORS! Rate your experience: {link}",
        'sms.reschedule_accepted' => "Hello {name}, your reschedule request has been approved! New appointment: {date}. Ref: {plate}. - OTOMOTORS",

        // Common actions
        'action.save' => 'Save',
        'action.cancel' => 'Cancel',
        'action.close' => 'Close',
        'action.confirm' => 'Confirm',
        'action.delete' => 'Delete',
        'action.edit' => 'Edit',
        'action.add' => 'Add',
        'action.remove' => 'Remove',
        'action.refresh' => 'Refresh',
        'action.export' => 'Export',
        'action.import' => 'Import',

        // Error messages
        'error.permission_denied' => 'Permission denied',
        'error.invalid_request' => 'Invalid request',
        'error.database_error' => 'Database error',
        'error.file_not_found' => 'File not found',
        'error.unknown_error' => 'Unknown error',

        // Common actions
        'action.save' => 'Save',
        'action.cancel' => 'Cancel',
        'action.close' => 'Close',
        'action.confirm' => 'Confirm',
        'action.delete' => 'Delete',
        'action.edit' => 'Edit',
        'action.add' => 'Add',
        'action.remove' => 'Remove',
        'action.refresh' => 'Refresh',
        'action.export' => 'Export',
        'action.import' => 'Import',

        // Error messages
        'error.permission_denied' => 'Permission denied',
        'error.invalid_request' => 'Invalid request',
        'error.database_error' => 'Database error',
        'error.file_not_found' => 'File not found',
        'error.unknown_error' => 'Unknown error',

        // Success messages
        'success.saved' => 'Saved successfully',
        'success.deleted' => 'Deleted successfully',
        'success.updated' => 'Updated successfully',
        'success.created' => 'Created successfully'
    ];

    // Georgian translations
    $georgian_translations = [
        // Navigation and common
        'nav.dashboard' => 'მთავარი',
        'nav.transfers' => 'ტრანსფერები',
        'nav.vehicles' => 'ავტომობილები',
        'nav.reviews' => 'შეფასებები',
        'nav.templates' => 'შაბლონები',
        'nav.users' => 'მომხმარებლები',
        'nav.logout' => 'გამოსვლა',

        // Dashboard
        'dashboard.title' => 'OTOMOTORS მენეჯერის პორტალი',
        'dashboard.quick_import' => 'სწრაფი იმპორტი',
        'dashboard.quick_import_desc' => 'SMS ან ბანკის განცხადების ტექსტი ჩასვით ავტომატურად გადარიცხვების აღმოსაჩენად.',
        'dashboard.manual_create' => 'ხელით შექმნა',
        'dashboard.sample' => 'მაგალითი',
        'dashboard.detect' => 'აღმოჩენა',
        'dashboard.ready_to_import' => 'იმპორტისთვის მზადაა',
        'dashboard.confirm_save' => 'დადასტურება და შენახვა',
        'dashboard.search_placeholder' => 'ძიება ნომრების, სახელების, ტელეფონების მიხედვით...',
        'dashboard.all_replies' => 'ყველა პასუხი',
        'dashboard.confirmed' => 'დადასტურებულია',
        'dashboard.reschedule' => 'გადავადება',
        'dashboard.pending' => 'პასუხგაუცემელი',
        'dashboard.all_active_stages' => 'ყველა აქტიური ეტაპი',
        'dashboard.processing' => 'დამუშავება',
        'dashboard.called' => 'დაკავშირებულია',
        'dashboard.parts_ordered' => 'ნაწილები შეკვეთილია',
        'dashboard.parts_arrived' => 'ნაწილები მოვიდა',
        'dashboard.scheduled' => 'დაგეგმილია',
        'dashboard.completed' => 'დასრულებულია',
        'dashboard.issue' => 'პრობლემა',
        'dashboard.new_requests' => 'ახალი მოთხოვნები',
        'dashboard.processing_queue' => 'დამუშავების რიგი',
        'dashboard.vehicle_owner' => 'ავტომობილი და მფლობელი',
        'dashboard.status' => 'სტატუსი',
        'dashboard.amount' => 'თანხა',
        'dashboard.phone' => 'ტელეფონი',
        'dashboard.actions' => 'მოქმედებები',
        'dashboard.edit' => 'რედაქტირება',
        'dashboard.delete' => 'წაშლა',
        'dashboard.view_details' => 'დეტალების ნახვა',
        'dashboard.no_new_requests' => 'ახალი შემომავალი მოთხოვნები არ არის',
        'dashboard.loading' => 'თქვენი სამუშაო სივრცის ჩატვირთვა...',
        'dashboard.connecting' => 'დაკავშირება...',

        // SMS Templates
        'sms.registered' => "გამარჯობა {name}, გადახდა მიღებულია. მიმართ: {plate}. მოგესალმებით OTOMOTORS სერვისში.",
        'sms.schedule' => "გამარჯობა {name}, სერვისი დაგეგმილია {date} თარიღზე. მიმართ: {plate}.",
        'sms.parts_arrived' => "გამარჯობა {name}, თქვენი ნაწილები მოვიდა! დაადასტურეთ ვიზიტი აქ: {link} - OTOMOTORS",
        'sms.completed' => "{plate} სერვისი დასრულებულია. გმადლობთ OTOMOTORS-ის არჩევისთვის! შეაფასეთ თქვენი გამოცდილება: {link}",
        'sms.reschedule_accepted' => "გამარჯობა {name}, თქვენი გადავადების მოთხოვნა დადასტურებულია! ახალი დანიშვნა: {date}. მიმართ: {plate}. - OTOMOTORS"
    ];

    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Insert default English translations
        foreach ($default_translations as $key => $text) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO translations (translation_key, language_code, translation_text, created_at, updated_at)
                                  VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$key, DEFAULT_LANGUAGE, $text]);
        }

        // Insert Georgian translations
        foreach ($georgian_translations as $key => $text) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO translations (translation_key, language_code, translation_text, created_at, updated_at)
                                  VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$key, 'ka', $text]);
        }

        return true;
    } catch (PDOException $e) {
        error_log("Error initializing default translations: " . $e->getMessage());
        return false;
    }
}
?>