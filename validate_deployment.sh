#!/bin/bash
# Production Deployment Validation Script
# Run this AFTER uploading all files to verify system integrity

echo "==================================="
echo "OTOMOTORS - Production Validation"
echo "==================================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASS_COUNT=0
FAIL_COUNT=0

function check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} $1 exists"
        ((PASS_COUNT++))
    else
        echo -e "${RED}✗${NC} $1 MISSING"
        ((FAIL_COUNT++))
    fi
}

function check_dir() {
    if [ -d "$1" ]; then
        echo -e "${GREEN}✓${NC} $1/ directory exists"
        ((PASS_COUNT++))
    else
        echo -e "${RED}✗${NC} $1/ directory MISSING"
        ((FAIL_COUNT++))
    fi
}

function check_old_dir() {
    if [ -d "$1" ]; then
        echo -e "${YELLOW}⚠${NC} $1/ STILL EXISTS (should be deleted)"
        ((FAIL_COUNT++))
    else
        echo -e "${GREEN}✓${NC} $1/ removed (correct)"
        ((PASS_COUNT++))
    fi
}

echo "1. Core Application Files"
echo "--------------------------"
check_file "api.php"
check_file "config.php"
check_file "login.php"
check_file "index.php"
check_file "public_view.php"
echo ""

echo "2. Page Files (Root Level)"
echo "--------------------------"
check_file "dashboard.php"
check_file "vehicles.php"
check_file "reviews.php"
check_file "templates.php"
check_file "users.php"
check_file "pages-index.php"
echo ""

echo "3. Includes Directory"
echo "---------------------"
check_dir "includes"
check_file "includes/auth.php"
check_file "includes/header.php"
check_dir "includes/modals"
check_file "includes/modals/edit-modal.php"
check_file "includes/modals/vehicle-modal.php"
check_file "includes/modals/user-modals.php"
echo ""

echo "4. Views Directory"
echo "------------------"
check_dir "views"
check_file "views/dashboard.php"
check_file "views/vehicles.php"
check_file "views/reviews.php"
check_file "views/templates.php"
check_file "views/users.php"
echo ""

echo "5. Assets Directory"
echo "-------------------"
check_dir "assets"
check_dir "assets/js"
check_file "assets/js/app.js"
check_file "assets/js/transfers.js"
check_file "assets/js/vehicles.js"
check_file "assets/js/reviews.js"
check_file "assets/js/sms-templates.js"
check_file "assets/js/user-management.js"
check_file "assets/js/firebase-config.js"
echo ""

echo "6. Firebase Configuration"
echo "-------------------------"
check_file "firebase-messaging-sw.js"
check_file "service-account.json"
echo ""

echo "7. Cleanup Checks"
echo "-----------------"
check_old_dir "pages"
echo ""

echo "8. PHP Error Display Check"
echo "--------------------------"
echo "Checking for display_errors=0 in production files..."

FILES_TO_CHECK=("api.php" "dashboard.php" "vehicles.php" "reviews.php" "templates.php" "users.php")
for file in "${FILES_TO_CHECK[@]}"; do
    if [ -f "$file" ]; then
        if grep -q "ini_set('display_errors', 0)" "$file"; then
            echo -e "${GREEN}✓${NC} $file has display_errors disabled"
            ((PASS_COUNT++))
        else
            echo -e "${RED}✗${NC} $file may have display_errors enabled!"
            ((FAIL_COUNT++))
        fi
    fi
done
echo ""

echo "9. Console.log Cleanup Check"
echo "----------------------------"
LOG_COUNT=$(grep -r "console.log('\[" assets/js/*.js 2>/dev/null | wc -l)
if [ "$LOG_COUNT" -eq 0 ]; then
    echo -e "${GREEN}✓${NC} No verbose [TAG] console logs found"
    ((PASS_COUNT++))
else
    echo -e "${YELLOW}⚠${NC} Found $LOG_COUNT verbose console.log statements"
    ((FAIL_COUNT++))
fi
echo ""

echo "=================================="
echo "VALIDATION SUMMARY"
echo "=================================="
echo -e "Passed: ${GREEN}$PASS_COUNT${NC}"
echo -e "Failed: ${RED}$FAIL_COUNT${NC}"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}✓ ALL CHECKS PASSED - READY FOR PRODUCTION!${NC}"
    exit 0
else
    echo -e "${RED}✗ SOME CHECKS FAILED - REVIEW ABOVE OUTPUT${NC}"
    exit 1
fi
