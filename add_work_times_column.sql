-- Add work_times JSON column to store cumulative work durations per stage and technician
ALTER TABLE transfers ADD COLUMN work_times JSON DEFAULT NULL COMMENT 'Cumulative work time per stage and technician in milliseconds';
-- Optionally add assignment_history for quick lookup (system_logs also stores entries)
ALTER TABLE transfers ADD COLUMN assignment_history JSON DEFAULT NULL COMMENT 'List of assignment changes {stage, from, to, by, at}';