<?php
/**
 * Setup script for offers table
 * Run this once to create the promotional offers system
 * Safe to re-run — uses IF NOT EXISTS
 */

require_once 'config.php';

$isCli = php_sapi_name() === 'cli';
$br = $isCli ? "\n" : "<br>";

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Setup Offers</title></head><body><pre style='font-family: monospace; padding: 20px;'>";
}

try {
    $pdo = getDBConnection();

    // Create offers table
    $sql = "CREATE TABLE IF NOT EXISTS `offers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(32) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `discount_type` ENUM('percentage', 'fixed', 'free_service') NOT NULL DEFAULT 'percentage',
        `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `min_order_amount` DECIMAL(10,2) DEFAULT NULL,
        `valid_from` DATETIME NOT NULL,
        `valid_until` DATETIME NOT NULL,
        `max_redemptions` INT DEFAULT NULL COMMENT 'NULL = unlimited',
        `times_redeemed` INT NOT NULL DEFAULT 0,
        `status` ENUM('active', 'paused', 'expired') NOT NULL DEFAULT 'active',
        `target_phone` VARCHAR(20) DEFAULT NULL COMMENT 'NULL = open to all',
        `target_name` VARCHAR(255) DEFAULT NULL,
        `sms_sent_at` DATETIME DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_code` (`code`),
        INDEX `idx_status` (`status`),
        INDEX `idx_valid_until` (`valid_until`),
        INDEX `idx_target_phone` (`target_phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "✅ offers table created successfully!{$br}";

    // Create offer_redemptions table for tracking each use
    $sql2 = "CREATE TABLE IF NOT EXISTS `offer_redemptions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `offer_id` INT NOT NULL,
        `customer_name` VARCHAR(255) DEFAULT NULL,
        `customer_phone` VARCHAR(20) DEFAULT NULL,
        `redeemed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `notes` TEXT DEFAULT NULL,
        `redeemed_by` INT DEFAULT NULL COMMENT 'Operator user ID, NULL = self-redeemed',
        INDEX `idx_offer_id` (`offer_id`),
        INDEX `idx_redeemed_by` (`redeemed_by`),
        CONSTRAINT `fk_redemption_offer` FOREIGN KEY (`offer_id`) REFERENCES `offers`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql2);
    echo "✅ offer_redemptions table created successfully!{$br}";

    // Add redeemed_by column if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE offer_redemptions ADD COLUMN `redeemed_by` INT DEFAULT NULL COMMENT 'Operator user ID' AFTER `notes`");
        echo "✅ Added redeemed_by column to offer_redemptions{$br}";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ redeemed_by column already exists{$br}";
        }
    }

    echo "{$br}🎉 Offers system setup complete!{$br}";

} catch (PDOException $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage()) . $br;
}

if (!$isCli) {
    echo "</pre></body></html>";
}
?>
