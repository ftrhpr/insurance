#!/usr/bin/env bash
# Small API check for the common endpoints
set -e
BASE_URL="http://localhost/api.php"

echo "Checking API endpoints..."

echo "get_transfers:"
curl -s -S -w "\nHTTP_CODE:%{http_code}\n" "${BASE_URL}?action=get_transfers"

echo "get_sms_templates:"
curl -s -S -w "\nHTTP_CODE:%{http_code}\n" "${BASE_URL}?action=get_sms_templates"

echo "get_parsing_templates:"
curl -s -S -w "\nHTTP_CODE:%{http_code}\n" "${BASE_URL}?action=get_parsing_templates"

echo "Done."
