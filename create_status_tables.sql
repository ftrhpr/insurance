-- Create tables for dynamic case statuses and repair statuses
-- Run this migration to set up the new status management system

-- Case Statuses Table (for workflow pipeline: New, Processing, Called, etc.)
CREATE TABLE IF NOT EXISTS case_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ka VARCHAR(100) DEFAULT NULL COMMENT 'Georgian translation',
    name_en VARCHAR(100) DEFAULT NULL COMMENT 'English translation',
    slug VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) DEFAULT 'slate' COMMENT 'Tailwind color name',
    icon VARCHAR(50) DEFAULT 'circle' COMMENT 'Lucide icon name',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0 COMMENT 'Default status for new cases',
    is_final TINYINT(1) DEFAULT 0 COMMENT 'Final status (e.g., Completed)',
    triggers_sms VARCHAR(100) DEFAULT NULL COMMENT 'SMS template slug to trigger',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repair Statuses Table (for repair workflow: Assessment, In Progress, etc.)
CREATE TABLE IF NOT EXISTS repair_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ka VARCHAR(100) DEFAULT NULL COMMENT 'Georgian translation',
    name_en VARCHAR(100) DEFAULT NULL COMMENT 'English translation',
    slug VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) DEFAULT 'amber' COMMENT 'Tailwind color name',
    icon VARCHAR(50) DEFAULT 'wrench' COMMENT 'Lucide icon name',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0 COMMENT 'Default repair status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add status_id columns to transfers table (keep old columns for backward compatibility)
ALTER TABLE transfers 
    ADD COLUMN status_id INT DEFAULT NULL AFTER status,
    ADD COLUMN repair_status_id INT DEFAULT NULL AFTER repair_status,
    ADD CONSTRAINT fk_transfers_status FOREIGN KEY (status_id) REFERENCES case_statuses(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_transfers_repair_status FOREIGN KEY (repair_status_id) REFERENCES repair_statuses(id) ON DELETE SET NULL;

-- Create index for faster lookups
CREATE INDEX idx_case_statuses_sort ON case_statuses(sort_order, is_active);
CREATE INDEX idx_repair_statuses_sort ON repair_statuses(sort_order, is_active);
CREATE INDEX idx_transfers_status_id ON transfers(status_id);
CREATE INDEX idx_transfers_repair_status_id ON transfers(repair_status_id);

-- Insert default case statuses
INSERT INTO case_statuses (name, name_ka, name_en, slug, color, icon, sort_order, is_default, is_final, triggers_sms) VALUES
('New', 'ახალი', 'New', 'new', 'blue', 'file-plus-2', 1, 1, 0, NULL),
('Processing', 'დამუშავება', 'Processing', 'processing', 'yellow', 'loader-circle', 2, 0, 0, 'registered'),
('Called', 'დაკავშირებული', 'Contacted', 'called', 'purple', 'phone', 3, 0, 0, 'schedule'),
('Parts Ordered', 'ნაწილები შეკვეთილია', 'Parts Ordered', 'parts_ordered', 'orange', 'box-select', 4, 0, 0, 'parts_ordered'),
('Parts Arrived', 'ნაწილები მოვიდა', 'Parts Arrived', 'parts_arrived', 'teal', 'package-check', 5, 0, 0, 'parts_arrived'),
('Scheduled', 'დაგეგმილი', 'Scheduled', 'scheduled', 'indigo', 'calendar-days', 6, 0, 0, 'schedule'),
('Already in service', 'სერვისზეა', 'In Service', 'in_service', 'cyan', 'wrench', 7, 0, 0, NULL),
('Completed', 'დასრულებული', 'Completed', 'completed', 'green', 'check-circle-2', 8, 0, 1, 'completed'),
('Issue', 'პრობლემა', 'Issue', 'issue', 'red', 'alert-triangle', 9, 0, 0, NULL);

-- Insert default repair statuses
INSERT INTO repair_statuses (name, name_ka, name_en, slug, color, icon, sort_order, is_default) VALUES
('Not Started', 'დაუწყებელი', 'Not Started', 'not_started', 'slate', 'circle', 0, 1),
('წიანსწარი შეფასება', 'წიანსწარი შეფასება', 'Preliminary Assessment', 'preliminary_assessment', 'blue', 'clipboard-check', 1, 0),
('მუშავდება', 'მუშავდება', 'In Progress', 'in_progress', 'amber', 'loader', 2, 0),
('იღებება', 'იღებება', 'Being Received', 'receiving', 'cyan', 'download', 3, 0),
('იშლება', 'იშლება', 'Disassembly', 'disassembly', 'orange', 'unplug', 4, 0),
('აწყობა', 'აწყობა', 'Assembly', 'assembly', 'indigo', 'puzzle', 5, 0),
('თუნუქი', 'თუნუქი', 'Body Work', 'body_work', 'purple', 'hammer', 6, 0),
('პლასტმასის აღდგენა', 'პლასტმასის აღდგენა', 'Plastic Restoration', 'plastic_restoration', 'pink', 'sparkles', 7, 0),
('პოლირება', 'პოლირება', 'Polishing', 'polishing', 'emerald', 'star', 8, 0),
('დაშლილი და გასული', 'დაშლილი და გასული', 'Disassembled & Released', 'disassembled_released', 'green', 'check', 9, 0);

-- Migration script to populate status_id from existing status strings
-- Run after inserting default statuses
UPDATE transfers t
JOIN case_statuses cs ON t.status = cs.name OR t.status = cs.name_en
SET t.status_id = cs.id
WHERE t.status_id IS NULL AND t.status IS NOT NULL;

UPDATE transfers t
JOIN repair_statuses rs ON t.repair_status = rs.name OR t.repair_status = rs.name_ka
SET t.repair_status_id = rs.id
WHERE t.repair_status_id IS NULL AND t.repair_status IS NOT NULL;
