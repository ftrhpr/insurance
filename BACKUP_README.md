# OTOMOTORS Backup & Restore System

This system provides automated backup and restore functionality for the entire OTOMOTORS insurance management project.

## Features

- **Full Project Backup**: Backs up all project files and database
- **Database Backup**: Exports all tables and data to SQL format
- **Compression**: Uses tar.gz for efficient storage
- **Exclusion Rules**: Automatically excludes unnecessary files (.git, node_modules, logs, etc.)
- **Restore Functionality**: Complete restoration of files and database

## Usage

### Creating a Backup

```bash
# Using batch file (Windows)
backup create

# Or directly with PHP
php backup.php
```

This will create a backup file in the `backups/` directory with a timestamp, e.g.:
`otomotors_backup_2025-12-14_12-00-00.tar.gz`

### Restoring from Backup

```bash
# Using batch file (Windows)
backup restore otomotos_backup_2025-12-14_12-00-00.tar.gz

# Or directly with PHP
php restore.php otomotos_backup_2025-12-14_12-00-00.tar.gz
```

## What Gets Backed Up

### Files
- All PHP files, HTML, CSS, JavaScript
- Configuration files
- Templates and assets
- Excludes: .git/, node_modules/, *.log, error_log, backups/

### Database
- Complete MySQL database export
- All tables: transfers, vehicles, sms_templates, users, etc.
- Foreign key constraints preserved

## Backup Structure

The backup tarball contains:
```
/ (project root)
├── api.php
├── index.php
├── edit_case.php
├── config.php
├── ... (all project files)
└── otomotors_backup_TIMESTAMP_db.sql (database dump)
```

## Requirements

- PHP with PDO MySQL extension
- tar command (available on Linux/Mac, or use alternatives on Windows)
- MySQL database access
- Write permissions to project directory

## Files Created

- `backup.php` - Main backup script
- `restore.php` - Restore script
- `backup.bat` - Windows batch file for easy access
- `backups/` - Directory containing backup files

## Safety Notes

- Always test restore on a development environment first
- Backup files contain sensitive data (database credentials, user data)
- Store backups securely
- The restore process will overwrite existing files and database tables

## Troubleshooting

### tar command not found (Windows)
Install Git Bash, MSYS2, or use 7-Zip as alternative.

### Database connection errors
Check config.php for correct database credentials.

### Permission errors
Ensure PHP has write access to backups/ directory and database access.