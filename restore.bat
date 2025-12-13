@echo off
REM Wrapper for Windows to restore backups via tools\fx.php
php "%~dp0tools\fx.php" restore %1
