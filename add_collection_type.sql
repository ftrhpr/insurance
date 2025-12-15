-- Add collection_type column to parts_collections table
ALTER TABLE parts_collections
ADD COLUMN IF NOT EXISTS collection_type VARCHAR(16) DEFAULT 'local' COMMENT 'Collection type: local or order';

-- Verify
DESCRIBE parts_collections;
