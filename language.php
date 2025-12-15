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
        'status.collected' => 'Collected',
        'status.cancelled' => 'Cancelled',

        // SMS Templates
        'sms.registered' => "Hello {name}, payment received. Ref: {plate}. Welcome to OTOMOTORS service.",
        'sms.schedule' => "Hello {name}, your service is scheduled for {date}. Ref: {plate}. Confirm or reschedule: {link} - OTOMOTORS",
        'sms.parts_arrived' => "Hello {name}, parts arrived for {plate}. Confirm service: {link} - OTOMOTORS",
        'sms.completed' => "Service for {plate} is completed. Thank you for choosing OTOMOTORS! Rate your experience: {link}",
        'sms.reschedule_accepted' => "Hello {name}, your reschedule request has been approved! New appointment: {date}. Ref: {plate}. - OTOMOTORS",

        // Navigation additions
        'nav.parts_collection' => 'Parts Collection',
        'nav.sms_parsing' => 'SMS Parsing',
        'nav.translations' => 'Translations',

        // Parts collection
        'parts.title' => 'Parts Collection',
        'parts.desc' => 'Manage and track all parts collections',
        'parts.create_new' => 'Create New Collection',
        'parts.live_updates' => 'Live Updates',
        'parts.start_process' => 'Start Collection Process',
        'parts.share' => 'Share This Collection',
        'parts.copy_link' => 'Link copied to clipboard!',
        'parts.show_qr' => 'Show QR Code',
        'parts.print_qr' => 'Print QR Code',
        'parts.to_collect' => 'Parts to Collect',
        'parts.show_services' => 'Show Services',
        'parts.hide_services' => 'Hide Services',
        'parts.services' => 'Services',
        'parts.delete_confirm' => 'Are you sure you want to delete this parts collection?',
        'success.parts_deleted' => 'Parts collection deleted successfully',
        'parts.empty' => 'No parts listed for this collection.',
        'parts.services_empty' => 'No services listed for this collection.',

        // Collection / Edit page
        'collection.edit_title' => 'Edit Parts Collection - OTOMOTORS Manager',
        'collection.edit' => 'Edit Collection',
        'collection.parse_pdf' => 'Auto-Parse PDF Invoice',
        'collection.description_placeholder' => 'Describe the parts collection request...',
        'collection.labor_section' => 'Labor & Services',

        // Edit case / parts request
        'case.request_parts' => 'Request Parts Collection',
        'case.parts_request_created' => 'Parts Request Created',
        'case.parts_request_created_msg' => 'Parts collection request has been created.',
        'case.create_parts_request' => 'Create Parts Request',

        // Tabs and activity
        'tab.activity' => 'Activity',
        'tab.vehicle' => 'Vehicle',
        'tab.parts' => 'Parts',
        'tab.danger' => 'Danger',
        'activity.none' => 'No activity recorded.',

        // Vehicle labels
        'vehicle.owner' => 'Owner:',
        'vehicle.model' => 'Model:',

        // Form fields
        'field.supplier_optional' => 'Supplier (Optional)',
        'collection.type' => 'Collection Type',
        'collection.local_market' => 'Local Market',
        'collection.order' => 'Order',

        // Danger zone
        'danger.zone' => 'Danger Zone',
        'danger.permanent' => 'This action is permanent and cannot be undone.',
        'action.delete_case' => 'Delete This Case',

        // Login additions
        'login.subtitle' => 'Manager Portal — secure access',
        'login.remember' => 'Remember me',
        'login.forgot' => 'Forgot?',
        // Filters and labels
        'filter.status' => 'Status',
        'filter.type' => 'Type',
        'filter.manager' => 'Manager',
        'filter.all' => 'All',
        'filter.local' => 'Local',
        'filter.order' => 'Order',
        'greeting.welcome' => 'Welcome,'
        ,
        // Site labels
        'site.subtitle' => 'Service Manager',
        'label.language' => 'Language',
*** End Patch

        // Common actions
        'action.save' => 'Save',
        'action.copy' => 'Copy Link',
        'action.description' => 'Description',
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

        // Additional keys added for localization sweep
        'case.save_review' => 'Save Review',
        'confirm.delete_case' => 'Permanently delete this case? This cannot be undone.',
        'case.deleted' => 'Case Deleted',
        'case.deleted_msg' => 'Permanently removed.',
        'error.failed_delete' => 'Failed to delete.',
        'error.general' => 'Error',
        'case.delete_failed' => 'Failed to delete case.',
        'order.create_new' => 'Create New Order',
        'order.create' => 'Create Order',
        'order.created_success' => 'Order created successfully!',
        'vehicles.search_placeholder' => 'Search by plate, owner or model...',
        'users.create_new' => 'Create New User',
        'users.create' => 'Create User',
        'users.edit' => 'Edit User',
        'users.update' => 'Update User',
        'users.not_found' => 'User not found',
        'users.save_failed' => 'Failed to save user',
        'users.password_failed' => 'Failed to change password'
        'users.create_failed' => 'Failed to create user',
        'users.delete_failed' => 'Failed to delete user',
        'users.create_failed' => 'Failed to create user',
        'users.delete_failed' => 'Failed to delete user',
        'users.create_success' => 'User created successfully',
        'users.delete_confirm' => 'Are you sure you want to delete user "{username}"? This action cannot be undone.',
        'users.delete_success' => 'User deleted successfully',
        'sms.create_template' => 'Create Template',
        'sms.edit_template' => 'Edit Template',
        'sms.create_first_template' => 'Create First Template',
        'sms.delete_confirm_template' => 'Are you sure you want to delete the template "{name}"?',
        'translations.export_success' => 'Translations exported successfully',
        'public.loading' => 'Loading...',
        'vehicles.edit_order' => 'Edit Service Order',
        'vehicles.order_details' => 'Service Order Details',
        'vehicles.order_edit_sub' => 'Update order information below.',
        'vehicles.order_view_sub' => 'Complete order information and history.',
        'users.create_failed' => 'Failed to create user',
        'users.delete_failed' => 'Failed to delete user',
        'users.edit_coming_soon' => 'Edit functionality coming soon',
        'error.failed_create_order' => 'Failed to create order',
        'confirm.delete_transfer' => 'Delete this case permanently?',
        'error.failed_delete_vehicle' => 'Failed to delete vehicle'

        // Error messages
        'error.permission_denied' => 'Permission denied',
        'error.invalid_request' => 'Invalid request',
        'error.collection_not_found' => 'Collection not found',
        'error.updating_collection' => 'Error updating collection',
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
        'success.collection_updated' => 'Collection updated successfully',
        'success.deleted' => 'Deleted successfully',
        'success.updated' => 'Updated successfully',
        'success.created' => 'Created successfully',
        // Common validation & info
        'validation.title' => 'Validation Error',
        'validation.select_transfer' => 'Please select a transfer.',
        'validation.add_item' => 'Please add at least one item.',
        'success.collection_created' => 'Collection created successfully!',
        'info.no_items_selected' => 'No items selected.',
        'validation.set_service_date' => 'Please set a service date first.',
        'validation.select_sms_template' => 'Please select an SMS template.',
        'validation.plate_required' => 'Vehicle plate number is required',
        'validation.name_required' => 'Customer name is required',
        'validation.amount_positive' => 'Amount must be a valid number greater than 0',
        'validation.franchise_nonnegative' => 'Franchise cannot be negative',
        'error.order_not_found' => 'Order not found',
        'error.failed_load_data' => 'Failed to load data. Please refresh the page.',
        'templates.saved_success' => 'All templates saved successfully',
        'templates.save_failed' => 'Failed to save templates',
        'validation.phone_required' => 'Phone number is required',
        'validation.enter_translation' => 'Please enter a translation'
        'sms.sent' => 'Notification delivered successfully',
        'sms.failed' => 'Failed to send SMS notification',
        'order.updated' => 'Order Updated',
        'order.update_failed' => 'Failed to update order',
        'error.vehicle_not_found' => 'Vehicle not found',
        'translations.save_success' => 'Translation saved successfully',
        'translations.save_failed' => 'Failed to save translation',
        'translations.export_failed' => 'Failed to export translations',
        // Permission-related messages
        'error.no_permission_edit' => 'You do not have permission to edit.',
        'error.no_permission_templates' => 'You do not have permission to edit templates',
        'error.need_manager_role' => 'You need Manager or Admin role to create orders',
        'error.no_permission_edit_cases' => 'You do not have permission to edit cases',
        'error.no_permission_edit_orders' => 'You do not have permission to edit orders',
        'error.no_permission_edit_vehicles' => 'You do not have permission to edit vehicles',
        'error.no_permission_delete_vehicles' => 'You do not have permission to delete vehicles',
        'error.no_permission_approve_reviews' => 'You do not have permission to approve reviews',
        'error.no_permission_reject_reviews' => 'You do not have permission to reject reviews'
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
        // Georgian translations for newly added keys
        'case.save_review' => 'შენახვა',
        'confirm.delete_case' => 'ნამდვილად წაშალოთ ეს საქმე? ეს არ იქნება აღდგებადი.',
        'case.deleted' => 'საქმე წაშლილია',
        'case.deleted_msg' => 'წარმატებით წაიშალა.',
        'error.failed_delete' => 'წაშლა ვერ მოხერხდა.',
        'error.general' => 'შეცდომა',
        'case.delete_failed' => 'საქმის წაშლა ვერ მოხერხდა.',
        'order.create_new' => 'ახალი შეკვეთის შექმნა',
        'order.create' => 'შეკვეთის შექმნა',
        'order.created_success' => 'შეკვეთი წარმატებით შეიქმნა',
        'vehicles.search_placeholder' => 'ძებნა ნომრით, მფლობელით ან მოდელით...',
        'users.create_new' => 'ახალი მომხმარებლის შექმნა',
        'users.create' => 'მომხმარებლის შექმნა',
        'users.edit' => 'მომხმარებლის რედაქტირება',
        'users.update' => 'მომხმარებლის განახლება',
        'users.not_found' => 'მომხმარებელი ვერ მოიძებნა',
        'users.save_failed' => 'მომხმარებლის შენახვა ვერ მოხერხდა',
        'users.password_failed' => 'პაროლის შეცვლა არ გამოდგა'
        'users.create_failed' => 'მომხმარებლის შექმნა ვერ მოხერხდა',
        'users.delete_failed' => 'მომხმარებლის წაშლა ვერ შედგა'
        'users.create_failed' => 'მომხმარებლის შექმნა ვერ მოხერხდა',
        'users.delete_failed' => 'მომხმარებლის წაშლა ვერ შედგა'
        'users.create_success' => 'მომხმარებელი წარმატებით შეიქმნა',
        'users.delete_confirm' => 'ნამდვილად გსურთ მომხმარებლის "{username}" წაშლა? ეს ქმედება ვერ იქნება გაუქმებული.',
        'users.delete_success' => 'მომხმარებელი წარმატებით წაიშალა',
        'sms.create_template' => 'შაბლონის შექმნა',
        'sms.edit_template' => 'შაბლონის რედაქტირება',
        'sms.create_first_template' => 'პირველი შაბლონის შექმნა',
        'sms.delete_confirm_template' => 'ნამდვილად გსურთ შაბლონის "{name}" წაშლა?',
        'translations.export_success' => 'თარგმნები წარმატებით ექსპორტირდა',
        'public.loading' => 'ჩატვირთვა...',
        'vehicles.edit_order' => 'რედაქტირება - მომსახურების შეკვეთა',
        'vehicles.order_details' => 'მომსახურების შეკვეთის დეტალები',
        'vehicles.order_edit_sub' => 'განაახლეთ შეკვეთის ინფორმაცია ქვემოთ.',
        'vehicles.order_view_sub' => 'დასრულებული შეკვეთის სრული ინფორმაცია და ისტორია.',
        // Georgian translations for validation & info
        'validation.title' => 'შეყვანის შეცდომა',
        'validation.select_transfer' => 'გთხოვთ, აირჩიოთ გადარიცხვა.',
        'validation.add_item' => 'გთხოვთ, მიანიჭოთ მინიმუმ ერთი ნივთი.',
        'success.collection_created' => 'შეგროვება წარმატებით შეიქმნა!',
        'info.no_items_selected' => 'ნივთები არ არის შერჩენილი.',
        'validation.set_service_date' => 'გთხოვთ, ჯერ დააყენეთ მომსახურების თარიღი.',
        'validation.select_sms_template' => 'გთხოვთ, აირჩიოთ SMS შაბლონი.',
        'validation.plate_required' => 'მანქანის ნომერი აუცილებელია',
        'validation.name_required' => 'მომხმარებლის სახელი აუცილებელია',
        'validation.amount_positive' => 'თანხა უნდა იყოს მოქმედი რიცხვი და მეტი ვიდრე 0',
        'validation.franchise_nonnegative' => 'ფრანშიზა არ შეიძლება იყოს უარყოფითი',
        'error.order_not_found' => 'შეკვეთი ვერ მოიძებნა',
        'error.failed_load_data' => 'მონაცემების ჩატვირთვა ვერ მოხერხდა. გთხოვთ განაახლოთ გვერდი.',
        'templates.saved_success' => 'ყველა შაბლონი წარმატებით შეინახა',
        'templates.save_failed' => 'შაბლონების შენახვა ვერ მოხერხდა',
        'validation.phone_required' => 'ტელეფონის ნომერი აუცილებელია',
        'validation.enter_translation' => 'გთხოვთ, შეიყვანოთ თარგმანი'
        'sms.sent' => 'ცნობობა წარმატებით გაბმული',
        'sms.failed' => 'SMS-ს გამოგზავნა ვერ მოხერხდა',
        'order.updated' => 'შეკვეთი დარედაქტირდა',
        'order.update_failed' => 'შეკვეთის განახლება ვერ მოხერხდა',
        'error.vehicle_not_found' => 'მანქანა ვერ მოიძებნა',
        'translations.save_success' => 'თარგმანი წარმატებით შენახულია',
        'translations.save_failed' => 'თარგმანის შენახვა ვერ მოხერხდა',
        'translations.export_failed' => 'თარგმნების ექსპორტი ვერ მოხერხდა',
        // Georgian translations for permission-related messages
        'error.no_permission_edit' => 'თქვენ არ გაქვთ რედაქტირების უფლება.',
        'error.no_permission_templates' => 'თქვენ არ გაქვთ უფლება შაბლონების რედაქტირებაზე',
        'error.need_manager_role' => 'თქვენს საჭიროა მენეჯერი ან ადმინი როლი შეკვეთების შესაქმენათ',
        'error.no_permission_edit_cases' => 'თქვენ არ გაქვთ უფლება საქმეების რედაქტირებაზე',
        'error.no_permission_edit_orders' => 'თქვენ არ გაქვთ უფლება შეკვეთების რედაქტირებაზე',
        'error.no_permission_edit_vehicles' => 'თქვენ არ გაქვთ უფლება მანქანების რედაქტირებაზე',
        'error.no_permission_delete_vehicles' => 'თქვენ არ გაქვთ უფლება მანქანების წაშლაზე',
        'error.no_permission_approve_reviews' => 'თქვენ არ გაქვთ უფლება შეფასებების დამტკიცებაზე',
        'error.no_permission_reject_reviews' => 'თქვენ არ გაქვთ უფლება შეფასებების დაპრკვისებაზე'
        'users.create_failed' => 'მომხმარებლის შექმნა ვერ მოხერხდა',
        'users.delete_failed' => 'მომხმარებლის წაშლა ვერ მოხერხდა',
        'users.edit_coming_soon' => 'რედაქტირების ფუნქცია მალე დაემატება',
        'error.failed_create_order' => 'შეუძლებელია შეკვეთის შექმნა',
        'confirm.delete_transfer' => 'ნამდვილად გსურთ საქმის სრული წაშლა?',
        'error.failed_delete_vehicle' => 'მანქანის წაშლა ვერ მომხდარა'
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

        'status.collected' => 'შეკვეთილია',
        'status.cancelled' => 'გაუქმებული',

        // SMS Templates
        'sms.registered' => "გამარჯობა {name}, გადახდა მიღებულია. მიმართ: {plate}. მოგესალმებით OTOMOTORS სერვისში.",
        'sms.schedule' => "გამარჯობა {name}, სერვისი დაგეგმილია {date} თარიღზე. მიმართ: {plate}. დაადასტურეთ ან გადაავადეთ: {link}",
        'sms.parts_arrived' => "გამარჯობა {name}, თქვენი ნაწილები მოვიდა! დაადასტურეთ ვიზიტი აქ: {link}",
        'sms.completed' => "{plate} სერვისი დასრულებულია. შეაფასეთ თქვენი გამოცდილება: {link}",
        'sms.reschedule_accepted' => "გამარჯობა {name}, თქვენი გადავადების მოთხოვნა დადასტურებულია! ახალი დანიშვნა: {date}. მიმართ: {plate}."
        ,
        // Navigation additions
        'nav.parts_collection' => 'ნაწილების შეგროვება',
        'nav.sms_parsing' => 'SMS გამშიფვრა',
        'nav.translations' => 'თარგმნები',

        // Parts collection
        'parts.title' => 'ნაწილების შეგროვება',
        'parts.desc' => 'მართეთ და თვალყური ადევნეთ ყველა ნაწილების შეგროვებას',
        'parts.create_new' => 'ახალი შეგროვების შექმნა',
        'parts.live_updates' => 'ცოცხალი განახლებები',
        'parts.start_process' => 'გადაიწყო შეგროვების პროცესი',
        'parts.share' => 'კოლექციის გაზიარება',
        'parts.copy_link' => 'ბმული კოპირებულია კლიპბორდში!',
        'parts.show_qr' => 'QR კოდის ჩვენება',
        'parts.print_qr' => 'QR კოდის ბეჭდვა',
        'parts.to_collect' => 'ნაწილები გასაგროვებელია',
        'parts.show_services' => 'მომსახურებების ჩვენება',
        'parts.hide_services' => 'მომსახურებების დამალვა',
        'parts.services' => 'მომსახურებები',
        'parts.delete_confirm' => 'ნამდვილად გინდათ ამ ნაწილების შეგროვების წაშლა?',
        'success.parts_deleted' => 'ნაწილების შეგროვება წარმატებით წაიშალა',
        'parts.empty' => 'ამ შეკრებისთვის ნაწილები არ არის მითითებული.',
        'parts.services_empty' => 'ამ შეკრებისთვის მომსახურებები არ არის მითითებული.',

        // Collection / Edit page
        'collection.edit_title' => 'ნაწილების შეკრების რედაქტირება - OTOMOTORS მენეჯერი',
        'collection.edit' => 'რედაქტირება',
        'collection.parse_pdf' => 'ინვოისის ავტომატური გაშიფვრა',
        'collection.description_placeholder' => 'ჩაწერეთ ნაწილების შეგროვების მოთხოვნის აღწერილობა...',
        'collection.labor_section' => 'სამუშაოები და სერვისები',

        // Edit case / parts request
        'case.request_parts' => 'ნაწილების მოთხოვნა',
        'case.parts_request_created' => 'ნაწილების მოთხოვნა შეიქმნა',
        'case.parts_request_created_msg' => 'ნაწილების შეკვეთის მოთხოვნა წარმატებით შეიქმნა.',
        'case.create_parts_request' => 'ნაწილების მოთხოვნის შექმნა',

        // Tabs and activity
        'tab.activity' => 'აქტივობა',
        'tab.vehicle' => 'ავტომობილი',
        'tab.parts' => 'ნაწილები',
        'tab.danger' => 'საფრთხე',
        'activity.none' => 'აქტივობა არ დაფიქსირებულა',

        // Vehicle labels
        'vehicle.owner' => 'მფლობელი:',
        'vehicle.model' => 'მოდელი:',

        // Form fields
        'field.supplier_optional' => 'მომწოდებელი (არასავალდებულო)',
        'collection.type' => 'შეკრების ტიპი',
        'collection.local_market' => 'ადგილობრივი ბაზარი',
        'collection.order' => 'შეკვეთა',

        // Danger zone
        'danger.zone' => 'საფრთხე',
        'danger.permanent' => 'ეს მოქმედება მუდმივია და არ შეიძლება დაბრუნდეს.',
        'action.delete_case' => 'წაშალე ეს საქმე',
        // Site labels
        'site.subtitle' => 'სერვისის მენეჯერი',
        'label.language' => 'ენა',

        // Login additions
        'login.subtitle' => 'მენეჯერის პორტალი — უსაფრთხო წვდომა',
        'login.remember' => 'დამახსოვრება',
        'login.forgot' => 'პაროლი დაივიწყე?'
        ,
        // Filters and labels
        'filter.status' => 'სტატუსი',
        'action.description' => 'აღწერა',
        'filter.type' => 'ტიპი',
        'filter.manager' => 'მენეჯერი',
        'filter.all' => 'ყველა',
        'filter.local' => 'ადგილობრივი',
        'filter.order' => 'შეკვეთა',
        'greeting.welcome' => 'მოგესალმებით,'
        ,
        // Collection messages
        'error.collection_not_found' => 'კოლექცია ვერ მოიძებნა',
        'error.updating_collection' => 'კატალოგის განახლება ვერ მოხერხდა',
        'success.collection_updated' => 'კოლექცია წარმატებით განახლებულია',
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