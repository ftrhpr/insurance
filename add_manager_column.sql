-- Add assigned_manager_id column to parts_collections table
-- Run this SQL script if you cannot execute the PHP migration

-- Add the column if it doesn't exist
ALTER TABLE parts_collections
ADD COLUMN assigned_manager_id INT DEFAULT NULL COMMENT 'ID of assigned manager from users table';

-- Add foreign key constraint (only if it doesn't already exist)
-- Note: You may need to run this separately if the constraint already exists
-- ALTER TABLE parts_collections
-- ADD CONSTRAINT fk_assigned_manager
-- FOREIGN KEY (assigned_manager_id) REFERENCES users(id) ON DELETE SET NULL;

-- Add index for performance
ALTER TABLE parts_collections
ADD INDEX idx_assigned_manager (assigned_manager_id);

-- Verify the column was added
-- DESCRIBE parts_collections;