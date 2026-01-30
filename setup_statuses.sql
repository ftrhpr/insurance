-- Create statuses table for centralized status management
CREATE TABLE IF NOT EXISTS `statuses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('case', 'repair') NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `color` VARCHAR(20) DEFAULT '#6B7280',
    `bg_color` VARCHAR(20) DEFAULT '#F3F4F6',
    `icon` VARCHAR(50) DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_type_name` (`type`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default case statuses
INSERT INTO `statuses` (`type`, `name`, `color`, `bg_color`, `sort_order`) VALUES
('case', 'New', '#3B82F6', '#DBEAFE', 1),
('case', 'Processing', '#F59E0B', '#FEF3C7', 2),
('case', 'Called', '#8B5CF6', '#EDE9FE', 3),
('case', 'Parts Ordered', '#EC4899', '#FCE7F3', 4),
('case', 'Parts Arrived', '#14B8A6', '#CCFBF1', 5),
('case', 'Scheduled', '#6366F1', '#E0E7FF', 6),
('case', 'Already in service', '#F97316', '#FFEDD5', 7),
('case', 'Completed', '#10B981', '#D1FAE5', 8),
('case', 'Issue', '#EF4444', '#FEE2E2', 9)
ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`);

-- Insert default repair statuses
INSERT INTO `statuses` (`type`, `name`, `color`, `bg_color`, `sort_order`) VALUES
('repair', 'წიანსწარი შეფასება', '#3B82F6', '#DBEAFE', 1),
('repair', 'მუშავდება', '#F59E0B', '#FEF3C7', 2),
('repair', 'იღებება', '#8B5CF6', '#EDE9FE', 3),
('repair', 'იშლება', '#EF4444', '#FEE2E2', 4),
('repair', 'აწყობა', '#A855F7', '#F3E8FF', 5),
('repair', 'თუნუქი', '#06B6D4', '#CFFAFE', 6),
('repair', 'პლასტმასის აღდგენა', '#84CC16', '#ECFCCB', 7),
('repair', 'პოლირება', '#EC4899', '#FCE7F3', 8),
('repair', 'დაშლილი და გასული', '#10B981', '#D1FAE5', 9)
ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`);
