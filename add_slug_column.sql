-- Add slug column to transfers table for random invoice links
ALTER TABLE transfers
ADD COLUMN slug VARCHAR(32) UNIQUE DEFAULT NULL COMMENT 'Random slug for secure invoice sharing';</content>
<parameter name="filePath">vscode-vfs://github/ftrhpr/insurance/add_slug_column.sql