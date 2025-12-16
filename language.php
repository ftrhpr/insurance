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

        // Index / Dashboard specific messages and small UI strings
        'status.import_errors_title' => 'Import Completed with Errors',
        'status.import_successful_title' => 'Import Successful',
        'import.no_matches' => 'No matches found',
        'import.no_matches_desc' => 'Could not parse any transfers from the text',
        'notifications.enabled' => 'Notifications Enabled',
        'action.saving' => 'Saving...',

        // Validation and workflow
        'validation.time_required' => 'Time Required',
        'validation.set_appointment_parts_arrived' => 'Please set an Appointment date for Parts Arrived SMS',
        'validation.set_appointment_first' => 'Please set an Appointment date first',
        'validation.scheduling_required' => 'Scheduling Required',
        'validation.scheduling_required_desc' => "Please select a service date to save 'Parts Arrived' status.",
        'error.no_edit_permission' => 'You do not have permission to edit cases',

        // Reschedule flow
        'processing' => 'Processing...',
        'reschedule.accept_confirm' => 'Accept reschedule request for {name} ({plate})?\n\nNew appointment: {date}\n\nCustomer will receive SMS confirmation.',
        'reschedule.accepting' => 'Accepting reschedule request',
        'reschedule.accepted_title' => 'Reschedule Accepted',
        'reschedule.accepted_msg' => 'Appointment updated and SMS sent to {name}',
        'reschedule.accept_failed' => 'Failed to accept reschedule request',
        'reschedule.accept_update_confirm' => 'Accept reschedule request and update appointment to {date}?',
        'reschedule.decline_confirm' => 'Decline this reschedule request? The customer will need to be contacted manually.',
        'reschedule.declined_title' => 'Request Declined',
        'reschedule.declined_msg' => 'Reschedule request removed',
        'reschedule.decline_failed' => 'Failed to decline request',

        // Delete / Misc
        'error.no_record_id' => 'Error: No record ID',
        'action.delete_confirm' => 'Delete this case permanently?',
        'success.order_deleted' => 'Order deleted successfully',
        'error.delete_failed' => 'Failed to delete order',
        'error.no_phone' => 'No phone number',
        'sms.sent' => 'SMS Sent',
        'sms.failed' => 'SMS Failed',
        'error.create_order_permission' => 'You need Manager or Admin role to create orders',
        'success.changes_saved' => 'Changes Saved' 

        // SMS Templates
        'sms.registered' => "Hello {name}, payment received. Ref: {plate}. Welcome to OTOMOTORS service.",
        'sms.schedule' => "Hello {name}, your service is scheduled for {date}. Ref: {plate}. Confirm or reschedule: {link} - OTOMOTORS",
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
        'error.create_order_failed' => 'Failed to create order',
        'error.order_not_found' => 'Order not found',
        'error.load_failed' => 'Failed to load data. Please refresh the page.',

        // Validation
        'validation.title' => 'Validation Error',
        'validation.plate_required' => 'Vehicle plate number is required',
        'validation.name_required' => 'Customer name is required',
        'validation.amount_invalid' => 'Amount must be a valid number greater than 0',
        'validation.franchise_negative' => 'Franchise cannot be negative',

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
        'sms.schedule' => "გამარჯობა {name}, სერვისი დაგეგმილია {date} თარიღზე. მიმართ: {plate}. დაადასტურეთ ან გადაავადეთ: {link}",
        'sms.parts_arrived' => "გამარჯობა {name}, თქვენი ნაწილები მოვიდა! დაადასტურეთ ვიზიტი აქ: {link}",
        'sms.completed' => "{plate} სერვისი დასრულებულია. შეაფასეთ თქვენი გამოცდილება: {link}",
        'sms.reschedule_accepted' => "გამარჯობა {name}, თქვენი გადავადების მოთხოვნა დადასტურებულია! ახალი დანიშვნა: {date}. მიმართ: {plate}.",

        // Georgian placeholders for newly added index / dashboard strings
        'status.import_errors_title' => 'იმპორტი დასრულდა შეცდომებით',
        'status.import_successful_title' => 'იმპორტი დასრულდა წარმატებით',
        'import.no_matches' => 'ჩანაწერები ვერ მოიძებნა',
        'import.no_matches_desc' => 'ტექსტიდან ვერ გამოვლინდა გადაცემები',
        'notifications.enabled' => 'შეტყობინებები ჩართულია',
        'action.saving' => 'მისახა...',
        'validation.time_required' => 'დაინიშნა დროა საჭირო',
        'validation.set_appointment_parts_arrived' => 'გთხოვთ დააყენოთ დანიშვნა ნაწილების მისვლის SMS-ისთვის',
        'validation.set_appointment_first' => 'გთხოვთ, პირველ რიგში დააყენოთ დანიშვნა',
        'validation.scheduling_required' => 'დაგეგმვა აუცილებელია',
        'validation.scheduling_required_desc' => "Ընդունեք შეკვეთის გადაწყვეტას 'ნაწილები მივიდა' სტატუსის შენახვისთვის.",
        'error.no_edit_permission' => 'თქვენ არ გაქვთ უფლება რედაქტირების',
        'processing' => 'მუშავდება...',
        'reschedule.accept_confirm' => 'მიიღეთ გადადების მოთხოვნა {name} ({plate})?\n\nახალი დანიშვნა: {date}\n\nკლიენტი მიიღებს SMS დადასტურებას.',
        'reschedule.accepting' => 'გადადების მოთხოვნის მიღება',
        'reschedule.accepted_title' => 'გადმოყვანა მიღებულია',
        'reschedule.accepted_msg' => 'დანიშვნა განახლდა და SMS გაგზავნილია {name}',
        'reschedule.accept_failed' => 'გადადების მოთხოვნის მიღება ვერ მოხერხდა',
        'reschedule.accept_update_confirm' => 'მიიღეთ გადადების მოთხოვნა და განახლეთ დანიშვნა: {date}?',
        'reschedule.decline_confirm' => 'უარყოფთ გადადების მოთხოვნას? კლიენტი უნდა იყოს კონტაქტირებული ხელით.',
        'reschedule.declined_title' => 'გადავადება უარყოფილია',
        'reschedule.declined_msg' => 'გადადების მოთხოვნა წაიშალა',
        'reschedule.decline_failed' => 'გადადების მოთხოვნის უარყოფა ვერ მოხერხდა',
        'error.no_record_id' => 'შეცდომა: ჩანაწერი ID არ არსებობს',
        'action.delete_confirm' => 'დაამყარეთ ჩანაწერის მუდმივად წაშლა?',
        'success.order_deleted' => 'შეკვეთა წარმატებით წაიშალა',
        'error.delete_failed' => 'ჩანიერთი წაშლა ვერ მოხერხდა',
        'error.no_phone' => 'ტელეფონი არ არის',
        'sms.sent' => 'SMS გაგზავნილია',
        'sms.failed' => 'SMS გაგზავნა ვერ შესრულდა',
        'error.create_order_permission' => 'თქვენს საჭიროა მენეჯერის ან ადმინის როლი შეკვეთის შესაქმნელად',
        'success.changes_saved' => 'ცვლილებები შენახულია',
        // Remaining Georgian placeholders for validation and misc messages
        'validation.title' => 'ვალიდაციის შეცდომა',
        'validation.plate_required' => 'ავტომობილის ნომერი აუცილებელია',
        'validation.name_required' => 'კლიენტის სახელი აუცილებელია',
        'validation.amount_invalid' => 'თანხა უნდა იყოს დადებითი რიცხვი',
        'validation.franchise_negative' => 'ფრანშიზა არ შეიძლება იყოს უარყოფითი',
        'success.order_created' => 'შეკვეთა წარმატებით შეიქმნა!',
        'error.create_order_failed' => 'შეკვეთის შექმნა ვერ მოხერხდა',
        'error.order_not_found' => 'შეკვეთა ვერ მოიძებნა',
        'error.load_failed' => 'მონაცემების ჩატვირთვა ვერ მოხერხდა. გთხოვთ, განაახლოთ გვერდი.'
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