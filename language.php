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
        'dashboard.due_date' => 'Due Date',
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
        'success.created' => 'Created successfully',

        // Case editing and templates
        'case.title' => 'Edit Case',
        'case.back_to_dashboard' => 'Back to Dashboard',
        'case.print' => 'Print',
        'case.save_changes' => 'Save Changes',
        'case.details' => 'Case Details',
        'case.customer_name' => 'Customer Name',
        'case.vehicle_plate' => 'Vehicle Plate',
        'case.phone_number' => 'Phone Number',
        'case.service_date' => 'Service Date',
        'case.due_date' => 'Due Date',
        'case.amount' => 'Amount',
        'case.franchise' => 'Franchise',
        'case.reschedule_request' => 'Reschedule Request Pending',
        'case.requested' => 'Requested',
        'case.accept' => 'Accept',
        'case.decline' => 'Decline',
        'case.communication' => 'Communication',
        'case.quick_sms' => 'Quick SMS',
        'case.advanced_sms' => 'Advanced SMS',
        'case.welcome' => 'Welcome',
        'case.called' => 'Called',
        'case.parts_arrived' => 'Parts Arrived',
        'case.scheduled' => 'Scheduled',
        'case.completed' => 'Completed',
        'case.choose_template' => 'Choose a template...',
        'case.select_template' => 'Select a template...',
        'case.send_custom_sms' => 'Send Custom SMS',
        'case.customer_feedback' => 'Customer Feedback',
        'case.edit' => 'Edit',
        'case.cancel' => 'Cancel',
        'case.no_review' => 'No review submitted yet.',
        'case.rating' => 'Rating',
        'case.no_rating' => 'No rating',
        'case.star' => 'Star',
        'case.stars' => 'Stars',
        'case.comment' => 'Comment',
        'case.save_review' => 'Save Review',
        'case.internal_notes' => 'Internal Notes',
        'case.add_note_placeholder' => 'Add a new note...',
        'case.add' => 'Add',
        'case.no_internal_notes' => 'No internal notes yet.',
        'case.activity' => 'Activity',
        'case.vehicle' => 'Vehicle',
        'case.parts' => 'Parts',
        'case.danger' => 'Danger',
        'case.no_activity' => 'No activity recorded.',
        'case.owner' => 'Owner',
        'case.model' => 'Model',
        'case.request_parts' => 'Request Parts Collection',
        'case.description' => 'Description',
        'case.describe_request' => 'Describe the parts collection request...',
        'case.supplier' => 'Supplier (Optional)',
        'case.supplier_name' => 'Supplier name',
        'case.collection_type' => 'Collection Type',
        'case.local_market' => 'Local Market',
        'case.order' => 'Order',
        'case.create_request' => 'Create Parts Request',
        'case.danger_zone' => 'Danger Zone',
        'case.permanent_action' => 'This action is permanent and cannot be undone.',
        'case.delete_case' => 'Delete This Case',

        // Templates page
        'templates.title' => 'Manage SMS Templates',
        'templates.save_all' => 'Save All Templates',
        'templates.view_only' => 'View only - editing disabled',
        'templates.welcome_sms' => 'Welcome SMS',
        'templates.customer_contacted' => 'Customer Contacted',
        'templates.contacted_notification' => 'Contacted Notification',
        'templates.service_scheduled' => 'Service Scheduled',
        'templates.parts_ordered' => 'Parts Ordered',
        'templates.parts_request_local' => 'Parts Request (Local)',
        'templates.parts_arrived' => 'Parts Arrived',
        'templates.reschedule_request' => 'Reschedule Request',
        'templates.reschedule_accepted' => 'Reschedule Accepted',
        'templates.service_completed' => 'Service Completed',
        'templates.issue_reported' => 'Issue Reported',
        'templates.system_alert' => 'System Alert',
        'templates.active' => 'Active',
        'templates.workflow_stages' => 'Workflow Stages:',
        'templates.variables' => 'Template Variables',
        'templates.customer_name' => "Customer's full name",
        'templates.plate_number' => 'Vehicle plate number',
        'templates.service_amount' => 'Service amount',
        'templates.service_date' => 'Service date',
        'templates.confirmation_link' => 'Customer confirmation link',
        'templates.count_system_alerts' => 'Count/number (for system alerts)',
        'templates.tip' => 'Tip:',
        'templates.tip_text' => 'Use these placeholders in your templates. They will be automatically replaced with actual customer data when SMS is sent.',

        // Calendar page
        'calendar.title' => 'Due Date Calendar',
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

        // Case editing and templates (Edit Case Page)
        'case.title' => 'დეტალის რედაქტირება',
        'case.back_to_dashboard' => 'დაბრუნება დაფაზე',
        'case.print' => 'ბეჭდვა',
        'case.save_changes' => 'ცვლილებების შენახვა',
        'case.details' => 'დეტალის ინფორმაცია',
        'case.customer_name' => 'კლიენტის სახელი',
        'case.vehicle_plate' => 'ავტომობილის ნომერი',
        'case.phone_number' => 'ტელეფონის ნომერი',
        'case.service_date' => 'სერვისის თარიღი',
        'case.due_date' => 'ვადა',
        'case.amount' => 'თანხა',
        'case.franchise' => 'ფრანშიზა',
        'case.repair_management' => 'რემონტის მენეჯმენტი',
        'case.not_started' => 'დაუწყებელი',
        'case.reschedule_request' => 'გადავადების მოთხოვნა მოლოდინეში',
        'case.requested' => 'მოთხოვნილი',
        'case.accept' => 'დამტკიცება',
        'case.decline' => 'უარყოფა',
        'case.communication' => 'კომუნიკაცია',
        'case.quick_sms' => 'სწრაფი SMS',
        'case.advanced_sms' => 'მოწინავე SMS',
        'case.welcome' => 'კეთილი მობრძანება',
        'case.called' => 'დაკავშირებულია',
        'case.parts_arrived' => 'ნაწილები მოვიდა',
        'case.scheduled' => 'დაგეგმილია',
        'case.completed' => 'დასრულებულია',
        'case.choose_template' => 'აირჩიეთ შაბლონი...',
        'case.select_template' => 'აირჩიეთ შაბლონი...',
        'case.send_custom_sms' => 'მორგებული SMS-ის გაგზავნა',
        'case.customer_feedback' => 'კლიენტის უკუკავშირი',
        'case.edit' => 'რედაქტირება',
        'case.cancel' => 'გაუქმება',
        'case.no_review' => 'მიმოხილვა ჯერ არ შეიქმნა.',
        'case.rating' => 'რეიტინგი',
        'case.no_rating' => 'რეიტინგი მინიჭებული არ არის',
        'case.star' => 'ვარსკვლავი',
        'case.stars' => 'ვარსკვლავი',
        'case.comment' => 'კომენტარი',
        'case.save_review' => 'მიმოხილვის შენახვა',
        'case.internal_notes' => 'შიდა შენიშვნები',
        'case.add_note_placeholder' => 'დაამატეთ ახალი შენიშვნა...',
        'case.add' => 'დამატება',
        'case.no_internal_notes' => 'შიდა შენიშვნები ჯერ არ არის.',
        'case.activity' => 'აქტივობა',
        'case.vehicle' => 'ავტომობილი',
        'case.parts' => 'ნაწილები',
        'case.danger' => 'სახطรობე',
        'case.no_activity' => 'აქტივობა ჯერ არ არის.',
        'case.owner' => 'მფლობელი',
        'case.model' => 'მოდელი',
        'case.request_parts' => 'ნაწილების კოლექციის მოთხოვნა',
        'case.description' => 'აღწერა',
        'case.describe_request' => 'აღწერეთ ნაწილების კოლექციის მოთხოვნა...',
        'case.supplier' => 'მომწოდებელი (არჩევითი)',
        'case.supplier_name' => 'მომწოდებლის სახელი',
        'case.collection_type' => 'კოლექციის ტიპი',
        'case.local_market' => 'ადგილობრივი ბაზარი',
        'case.order' => 'შეკვეთა',
        'case.create_request' => 'ნაწილების მოთხოვნის შექმნა',
        'case.danger_zone' => 'სახطრობე ზონა',
        'case.permanent_action' => 'ეს მოქმედება იქნება მუდმივი და მისი უკუშემობა შეუძლებელი იქნება.',
        'case.delete_case' => 'ამ დეტალის წაშლა',

        // SMS Templates
        'sms.registered' => "გამარჯობა {name}, გადახდა მიღებულია. მიმართ: {plate}. მოგესალმებით OTOMOTORS სერვისში.",
        'sms.schedule' => "გამარჯობა {name}, სერვისი დაგეგმილია {date} თარიღზე. მიმართ: {plate}. დაადასტურეთ ან გადაავადეთ: {link}",
        'sms.parts_arrived' => "გამარჯობა {name}, თქვენი ნაწილები მოვიდა! დაადასტურეთ ვიზიტი აქ: {link}",
        'sms.completed' => "{plate} სერვისი დასრულებულია. შეაფასეთ თქვენი გამოცდილება: {link}",
        'sms.reschedule_accepted' => "გამარჯობა {name}, თქვენი გადავადების მოთხოვნა დადასტურებულია! ახალი დანიშვნა: {date}. მიმართ: {plate}."
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