-- Add urgent column to transfers table for marking urgent cases
ALTER TABLE transfers ADD COLUMN urgent TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if case is urgent, 0 otherwise';