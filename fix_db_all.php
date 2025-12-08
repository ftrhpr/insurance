<?php
/**
 * OTOMOTORS Database Repair Tool
 * Checks and fixes all required tables and columns.
 */

header('Content-Type: text/plain');

// --- CONFIGURATION (Matches api.php) ---
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';     
$db_user = 'otoexpre_userdb';     
$db_pass = 'p52DSsthB}=0AeZ#';     

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database successfully.\n\n";

    // ---------------------------------------------------------
    // 1. TABLE: transfers
    // ---------------------------------------------------------
    echo "Checking table 'transfers'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plate VARCHAR(20),
        name VARCHAR(100),
        amount DECIMAL(10,2),
        status VARCHAR(50) DEFAULT 'New',
        phone VARCHAR(20),
        franchise VARCHAR(50),
        rawText TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // List of required columns for 'transfers'
    $columns = [
        'user_response' => "VARCHAR(50) DEFAULT 'Pending'",
        'service_date'  => "DATETIME DEFAULT NULL",
        'reschedule_date' => "DATETIME DEFAULT NULL",
        'reschedule_comment' => "TEXT DEFAULT NULL",
        'review_stars'  => "INT DEFAULT NULL",
        'review_comment'=> "TEXT DEFAULT NULL",
        'internal_notes'=> "JSON DEFAULT NULL",  // or TEXT if MariaDB version is old
        'system_logs'   => "JSON DEFAULT NULL"   // or TEXT if MariaDB version is old
    ];

    foreach ($columns as $col => $def) {
        if (!columnExists($pdo, 'transfers', $col)) {
            $pdo->exec("ALTER TABLE transfers ADD COLUMN $col $def");
            echo " - Added missing column: $col\n";
        } else {
            echo " - Column $col exists.\n";
        }
    }

    // ---------------------------------------------------------
    // 2. TABLE: sms_templates
    // ---------------------------------------------------------
    echo "\nChecking table 'sms_templates'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS sms_templates (
        slug VARCHAR(50) PRIMARY KEY,
        content TEXT
    )";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // ---------------------------------------------------------
    // 3. TABLE: vehicles
    // ---------------------------------------------------------
    echo "\nChecking table 'vehicles'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plate VARCHAR(20) UNIQUE NOT NULL,
        ownerName VARCHAR(100),
        phone VARCHAR(20),
        model VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // ---------------------------------------------------------
    // 4. TABLE: manager_tokens (For Firebase)
    // ---------------------------------------------------------
    echo "\nChecking table 'manager_tokens'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS manager_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // ---------------------------------------------------------
    // 4.5. TABLE: users (User Management System)
    // ---------------------------------------------------------
    echo "\nChecking table 'users'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        role ENUM('admin', 'manager', 'viewer') DEFAULT 'manager',
        status ENUM('active', 'inactive') DEFAULT 'active',
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT DEFAULT NULL,
        INDEX idx_username (username),
        INDEX idx_role (role),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // Create default admin user if no users exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    if ($result['count'] == 0) {
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, 'admin', 'active')")
            ->execute(['admin', $defaultPassword, 'System Administrator']);
        echo " - Default admin user created (username: admin, password: admin123)\n";
    }

    // ---------------------------------------------------------
    // 4.6. TABLE: translations (Multilanguage System)
    // ---------------------------------------------------------
    echo "\nChecking table 'translations'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS translations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        translation_key VARCHAR(255) NOT NULL,
        language_code VARCHAR(5) NOT NULL,
        translation_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_key_lang (translation_key, language_code),
        INDEX idx_language (language_code),
        INDEX idx_key (translation_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    // ---------------------------------------------------------
    // 5. TABLE: customer_reviews
    // ---------------------------------------------------------
    echo "\nChecking table 'customer_reviews'...\n";
    $sql = "CREATE TABLE IF NOT EXISTS customer_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(100) NOT NULL,
        customer_name VARCHAR(255),
        customer_email VARCHAR(255),
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        ip_address VARCHAR(45),
        INDEX idx_order_id (order_id),
        INDEX idx_created_at (created_at),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo " - Table structure verified.\n";

    echo "\n---------------------------------------------------\n";
    echo "REPAIR COMPLETE. You can reload your app now.";

} catch (PDOException $e) {
    echo "CRITICAL ERROR: " . $e->getMessage();
}

// Helper function
function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch();
}
?>