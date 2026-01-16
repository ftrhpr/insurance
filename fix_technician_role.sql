-- Fix the users table to include technician role
-- Run this SQL command in your MySQL database:

ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'manager', 'viewer', 'technician') DEFAULT 'manager';

-- Verify the change:
-- DESCRIBE users;