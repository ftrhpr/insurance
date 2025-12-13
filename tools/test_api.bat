@echo off
REM Small Windows batch test for API endpoints
SET BASE_URL=http://localhost/api.php

echo Checking API endpoints...

echo get_transfers:
curl -s -S "%BASE_URL%?action=get_transfers"

echo.
echo get_sms_templates:
curl -s -S "%BASE_URL%?action=get_sms_templates"

echo.
echo get_parsing_templates:
curl -s -S "%BASE_URL%?action=get_parsing_templates"

echo Done.
