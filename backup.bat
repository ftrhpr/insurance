@echo off
REM backup.bat - Backup and restore tool for OTOMOTORS project

if "%1"=="restore" (
    if "%2"=="" (
        echo Usage: backup restore ^<backup_file.tar.gz^>
        exit /b 1
    )
    php restore.php %2
) else if "%1"=="create" (
    php backup.php
) else (
    echo OTOMOTORS Backup Tool
    echo.
    echo Usage:
    echo   backup create          - Create a new backup
    echo   backup restore ^<file^>  - Restore from backup file
    echo.
    echo Example:
    echo   backup create
    echo   backup restore otomotos_backup_2025-12-14_12-00-00.tar.gz
)