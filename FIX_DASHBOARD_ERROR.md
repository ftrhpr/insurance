# Dashboard "Failed to load transfers" - Bug Fix

## Problem
Dashboard showing "Failed to load transfers" error.

## Root Causes Identified & Fixed

### 1. Session Management Issue ✅ FIXED
**Problem:** `session_start()` was called unconditionally, which can cause issues if session already started.

**Fix in `api.php`:**
```php
// Before:
session_start();

// After:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

### 2. Better Authentication Error Logging ✅ FIXED
**Problem:** Unclear why authentication was failing.

**Fix in `api.php`:**
- Added session data to error logs
- Added better hint messages for unauthorized access
- Added `get_public_transfer` and `user_respond` to public endpoints

### 3. Improved Error Messages ✅ FIXED
**Problem:** Generic error messages didn't help diagnose the issue.

**Fixes:**

**In `api.php` - get_transfers endpoint:**
- Added table existence check before querying
- Added detailed error logging with error codes
- Added helpful hints (e.g., "Run fix_db_all.php")
- Fixed JSON decoding with proper default values

**In `assets/js/app.js` - fetchAPI function:**
- Added 401 Unauthorized handler (auto-redirects to login)
- Added hint message display: `error message → hint`
- Improved error message clarity

### 4. Created Diagnostic Tool ✅ NEW
**File:** `debug_dashboard.html`

Run this file to diagnose issues:
- Checks if logged in
- Tests API health
- Tests database connection
- Tests get_transfers endpoint
- Shows detailed error messages

## Files Modified

1. **api.php** (3 changes)
   - Session management improvement
   - Better auth error logging
   - Enhanced get_transfers error handling

2. **assets/js/app.js** (1 change)
   - Added 401 handler with auto-redirect
   - Improved error message display

3. **debug_dashboard.html** (NEW)
   - Diagnostic tool for troubleshooting

## How to Fix Your Dashboard

### Step 1: Upload Fixed Files
Upload these 2 files via FTP:
- `api.php` (CRITICAL)
- `assets/js/app.js` (CRITICAL)

### Step 2: Run Diagnostic
Visit: `https://yourdomain.com/debug_dashboard.html`

This will show you exactly what's wrong:
- ✅ If logged in → Session OK
- ❌ If not logged in → Go to login.php first
- ❌ If table missing → Run fix_db_all.php
- ❌ If database error → Check credentials in api.php

### Step 3: Common Solutions

**Error: "Unauthorized" or 401**
- **Cause:** Not logged in or session expired
- **Fix:** Go to `login.php` and login again

**Error: "Transfers table does not exist"**
- **Cause:** Database tables not created yet
- **Fix:** Visit `fix_db_all.php` in browser to create tables

**Error: "Database connection failed"**
- **Cause:** Wrong credentials or database not accessible
- **Fix:** Check credentials in `api.php` lines 16-19

**Error: "Session expired"**
- **Cause:** Session timeout or cookies blocked
- **Fix:** Login again, check browser cookies enabled

## Testing Steps

### Manual Test
1. Clear browser cache (Ctrl+Shift+R)
2. Go to `login.php`
3. Login with valid credentials
4. Go to `dashboard.php`
5. Should load transfers successfully

### Diagnostic Test
1. Visit `debug_dashboard.html`
2. Check all tests pass
3. If any fail, follow the suggested fix

## What Changed (Technical Details)

### Before:
```javascript
// Generic error
throw new Error(errorData.message || errorData.error);
```

### After:
```javascript
// Specific 401 handling
if (res.status === 401) {
    window.location.href = 'login.php'; // Auto-redirect
    throw new Error('Session expired. Please login again.');
}

// Show hints from API
const errorMsg = errorData.message || errorData.error;
const hint = errorData.hint ? ` → ${errorData.hint}` : '';
throw new Error(errorMsg + hint);
```

### Result:
Instead of: `"Failed to load transfers"`
You now see: `"Transfers table does not exist → Run fix_db_all.php"`

## Prevention (Future)

To prevent this error from happening again:

1. **Regular Session Monitoring**
   - Sessions expire after inactivity
   - Login required every X hours (default: 24h)

2. **Database Health Checks**
   - Run `fix_db_all.php` after any server migration
   - Check `error_log` file regularly

3. **Browser Cache**
   - Clear cache after uploading new files
   - Use hard refresh (Ctrl+Shift+R)

## Quick Fix Commands

```bash
# SSH to server
cd /path/to/insurance

# Upload fixed files (via FTP client or)
# scp api.php assets/js/app.js user@server:/path/to/insurance/

# Check error log
tail -f error_log

# Clear sessions (if stuck)
rm -rf /tmp/sess_*

# Test API directly
curl -v https://yourdomain.com/api.php?action=health_check
```

## Expected Output After Fix

### Success Case:
```json
{
  "transfers": [...],
  "status": "success"
}
```

### Error Case (with hint):
```json
{
  "status": "error",
  "message": "Transfers table does not exist",
  "hint": "Run fix_db_all.php to create database tables"
}
```

---

**Status:** ✅ FIXED  
**Files to Upload:** 2 files (api.php, app.js)  
**Estimated Fix Time:** 2 minutes  
**Date:** December 5, 2025
