-- Add currency column to parts_collections table
-- Run this SQL script to add GEL currency support

-- Add the currency column if it doesn't exist
ALTER TABLE parts_collections
ADD COLUMN currency VARCHAR(3) DEFAULT 'GEL' COMMENT 'Currency for the collection: GEL';

-- Verify the column was added
-- DESCRIBE parts_collections;