-- Add case images column to transfers table
-- Images are stored as JSON array of Firebase Storage URLs
ALTER TABLE transfers
ADD COLUMN case_images TEXT DEFAULT NULL COMMENT 'JSON array of image URLs from Firebase Storage';

-- Example format:
-- ["https://firebasestorage.googleapis.com/v0/b/autobodyestimator.firebasestorage.app/o/images%2F..."]
