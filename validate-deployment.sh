#!/bin/bash
# Final Pre-Deployment Validation Script
# Checks for common bugs and issues before deployment

echo "========================================="
echo "  FINAL PRE-DEPLOYMENT VALIDATION"
echo "========================================="
echo ""

ERRORS=0

# Check 1: Verify all root-level page files exist
echo "✓ Checking root-level page files..."
FILES=("dashboard.php" "vehicles.php" "reviews.php" "templates.php" "users.php")
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file exists"
    else
        echo "  ✗ MISSING: $file"
        ((ERRORS++))
    fi
done
echo ""

# Check 2: Verify no ../ paths in root files
echo "✓ Checking for old paths in root files..."
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        if grep -q "require_once '../" "$file" 2>/dev/null; then
            echo "  ✗ ERROR: $file contains '../' in require_once"
            ((ERRORS++))
        elif grep -q "include '../" "$file" 2>/dev/null; then
            echo "  ✗ ERROR: $file contains '../' in include"
            ((ERRORS++))
        elif grep -q 'src="../' "$file" 2>/dev/null; then
            echo "  ✗ ERROR: $file contains '../' in script src"
            ((ERRORS++))
        else
            echo "  ✓ $file has correct paths"
        fi
    fi
done
echo ""

# Check 3: Verify critical JavaScript functions are exposed
echo "✓ Checking JavaScript global functions..."
if grep -q "window.loadData = " "assets/js/app.js" 2>/dev/null; then
    echo "  ✓ window.loadData is exposed"
else
    echo "  ✗ MISSING: window.loadData in app.js"
    ((ERRORS++))
fi

if grep -q "window.showToast = " "assets/js/app.js" 2>/dev/null; then
    echo "  ✓ window.showToast is exposed"
else
    echo "  ✗ MISSING: window.showToast in app.js"
    ((ERRORS++))
fi

if grep -q "window.fetchAPI = " "assets/js/app.js" 2>/dev/null; then
    echo "  ✓ window.fetchAPI is exposed"
else
    echo "  ✗ MISSING: window.fetchAPI in app.js"
    ((ERRORS++))
fi
echo ""

# Check 4: Verify API endpoints
echo "✓ Checking critical API endpoints..."
ENDPOINTS=("get_transfers" "update_transfer" "get_vehicles" "get_users" "get_templates" "get_reviews")
for endpoint in "${ENDPOINTS[@]}"; do
    if grep -q "action === '$endpoint'" "api.php" 2>/dev/null; then
        echo "  ✓ $endpoint endpoint exists"
    else
        echo "  ✗ MISSING: $endpoint endpoint in api.php"
        ((ERRORS++))
    fi
done
echo ""

# Check 5: Verify no invalid PDO constants
echo "✓ Checking for invalid PDO constants..."
if grep -q "PDO::MYSQL_ATTR_CONNECT_TIMEOUT" "config.php" 2>/dev/null; then
    echo "  ✗ ERROR: Invalid PDO constant in config.php"
    ((ERRORS++))
else
    echo "  ✓ No invalid PDO constants in config.php"
fi

if grep -q "PDO::MYSQL_ATTR_CONNECT_TIMEOUT" "api.php" 2>/dev/null; then
    echo "  ✗ ERROR: Invalid PDO constant in api.php"
    ((ERRORS++))
else
    echo "  ✓ No invalid PDO constants in api.php"
fi
echo ""

# Check 6: Verify includes directory structure
echo "✓ Checking includes directory..."
INCLUDE_FILES=("includes/auth.php" "includes/header.php" "includes/modals/edit-modal.php" "includes/modals/vehicle-modal.php" "includes/modals/user-modals.php")
for file in "${INCLUDE_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file exists"
    else
        echo "  ✗ MISSING: $file"
        ((ERRORS++))
    fi
done
echo ""

# Check 7: Verify views directory
echo "✓ Checking views directory..."
VIEW_FILES=("views/dashboard.php" "views/vehicles.php" "views/reviews.php" "views/templates.php" "views/users.php")
for file in "${VIEW_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file exists"
    else
        echo "  ✗ MISSING: $file"
        ((ERRORS++))
    fi
done
echo ""

# Check 8: Verify JavaScript modules
echo "✓ Checking JavaScript modules..."
JS_FILES=("assets/js/app.js" "assets/js/transfers.js" "assets/js/vehicles.js" "assets/js/reviews.js" "assets/js/sms-templates.js" "assets/js/user-management.js")
for file in "${JS_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file exists"
    else
        echo "  ✗ MISSING: $file"
        ((ERRORS++))
    fi
done
echo ""

# Final report
echo "========================================="
if [ $ERRORS -eq 0 ]; then
    echo "✅ VALIDATION PASSED - NO ERRORS FOUND"
    echo "========================================="
    echo ""
    echo "All files are ready for deployment!"
    echo "Proceed with uploading files as listed in DEPLOYMENT_READY.txt"
    exit 0
else
    echo "❌ VALIDATION FAILED - $ERRORS ERROR(S) FOUND"
    echo "========================================="
    echo ""
    echo "Please fix the errors above before deployment."
    exit 1
fi
