# 📦 UPLOAD CHECKLIST

## Files to Upload for IDE Management System Deployment

### Priority 1: Essential Files (Required)
These files MUST be uploaded for the system to work:

```
✅ pages/index.php
✅ pages/dashboard.php
✅ pages/vehicles.php
✅ pages/reviews.php
✅ pages/templates.php
✅ pages/users.php
✅ includes/header.php (UPDATED - REPLACE EXISTING)
```

**Total: 7 files**

### Priority 2: Documentation (Recommended)
These files help with understanding and maintenance:

```
✅ IDE_MANAGEMENT_SYSTEM.md
✅ DEPLOYMENT_IDE.md
✅ QUICK_START_IDE.md
✅ DEPLOYMENT_SUMMARY.md
✅ MODULAR_ARCHITECTURE.md (UPDATED)
✅ VISUAL_SUMMARY.txt
✅ UPLOAD_CHECKLIST.md (this file)
```

**Total: 7 files**

## Upload Instructions

### Via FTP/FileZilla:
1. Connect to your server
2. Navigate to `/public_html/` (or your web root)
3. Create `/pages/` directory if it doesn't exist
4. Upload all 6 files from local `pages/` to remote `pages/`
5. Replace `includes/header.php` with updated version
6. (Optional) Upload documentation files to root

### Via Command Line (SSH/SCP):
```bash
# Upload pages directory
scp -r pages/ user@server:/var/www/html/

# Upload updated header
scp includes/header.php user@server:/var/www/html/includes/

# Upload documentation (optional)
scp *.md user@server:/var/www/html/
```

### Via cPanel File Manager:
1. Login to cPanel
2. Open File Manager
3. Navigate to `public_html/`
4. Create new folder: `pages`
5. Upload all 6 PHP files to `pages/` folder
6. Navigate to `includes/`
7. Delete old `header.php`
8. Upload new `header.php`

## Post-Upload Verification

### Step 1: Check File Permissions
```bash
# SSH into server
cd /var/www/html/pages/
chmod 644 *.php

cd ../includes/
chmod 644 header.php
```

### Step 2: Verify Files Uploaded
Access each file via browser to check for 404 errors:

```
✓ https://yourdomain.com/pages/
✓ https://yourdomain.com/pages/dashboard.php
✓ https://yourdomain.com/pages/vehicles.php
✓ https://yourdomain.com/pages/reviews.php
✓ https://yourdomain.com/pages/templates.php
✓ https://yourdomain.com/pages/users.php
```

Expected: Each should redirect to login (if not authenticated) or load page (if authenticated).
Error: "404 Not Found" means file not uploaded correctly.

### Step 3: Test Navigation
1. Login to system
2. Visit `pages/` (feature selector)
3. Click each feature card
4. Verify page loads without errors
5. Check browser console (F12) for JavaScript errors

### Step 4: Test Mode Switching
1. From any standalone page: Click "Unified" button
2. Should redirect to `index-modular.php`
3. From unified view: Click "Pages" button
4. Should redirect to `pages/` (feature selector)

### Step 5: Test Permissions
**As Staff:**
- ✓ Can access: dashboard.php, vehicles.php
- ✓ Cannot access: reviews.php, templates.php, users.php

**As Manager:**
- ✓ Can access: dashboard.php, vehicles.php, reviews.php, templates.php
- ✓ Cannot access: users.php

**As Admin:**
- ✓ Can access: All pages

## Backup Before Upload (Recommended)

Create backups of files you'll be replacing:

```bash
# Backup existing header
cp includes/header.php includes/header.php.backup.$(date +%Y%m%d)

# Or download via FTP before uploading new version
```

## Rollback Plan (If Issues Occur)

If something goes wrong, you can easily rollback:

### Rollback Step 1: Restore Old Header
```bash
# If you backed up header.php
cp includes/header.php.backup includes/header.php
```

### Rollback Step 2: Remove Standalone Pages
```bash
# Remove pages directory
rm -rf pages/
```

### Rollback Step 3: Verify Unified View Still Works
```
Visit: https://yourdomain.com/index-modular.php
Expected: Should work normally without standalone pages
```

**Note:** The unified view (`index-modular.php`) is NOT affected by adding standalone pages. It will continue to work even if standalone pages have issues.

## Troubleshooting Common Upload Issues

### Issue: "500 Internal Server Error" after upload
**Solution:**
- Check file permissions (should be 644)
- Check PHP error log: `/var/www/html/error_log`
- Verify PHP syntax: Run `php -l filename.php` on server

### Issue: "404 Not Found" for pages/
**Solution:**
- Verify `pages/` directory created in correct location
- Check directory permissions (should be 755)
- Ensure all 6 PHP files uploaded to `pages/` folder

### Issue: "Permission Denied" errors
**Solution:**
- Check file ownership: `chown www-data:www-data pages/*.php`
- Check SELinux context (if enabled): `chcon -R -t httpd_sys_content_t pages/`

### Issue: Navigation doesn't work
**Solution:**
- Clear browser cache (Ctrl+Shift+R)
- Verify `includes/header.php` was replaced (check file timestamp)
- Check browser console for JavaScript errors

### Issue: Mode toggle button doesn't appear
**Solution:**
- Hard refresh browser (Ctrl+Shift+R)
- Verify updated `header.php` uploaded
- Check for PHP errors in header: View source, look for error messages

## Files Already on Server (DO NOT DELETE)

These existing files should NOT be touched:

```
❌ DO NOT DELETE:
   - index-modular.php
   - views/*.php
   - assets/js/*.js (all modules)
   - includes/auth.php
   - includes/modals/*.php
   - api.php
   - config.php
   - login.php
   - logout.php
```

**Only ADD new files and REPLACE header.php**

## Upload Size Reference

Approximate file sizes (for upload time estimation):

```
pages/index.php      : ~10 KB
pages/dashboard.php  : ~3 KB
pages/vehicles.php   : ~3 KB
pages/reviews.php    : ~3 KB
pages/templates.php  : ~3 KB
pages/users.php      : ~3 KB
includes/header.php  : ~6 KB
Documentation files  : ~150 KB total
```

**Total upload: ~180 KB** (should take less than 1 minute even on slow connections)

## Success Indicators

You'll know the deployment was successful when:

✅ Feature selector loads at `pages/`
✅ All 6 feature cards visible (role-dependent)
✅ Clicking cards loads respective pages
✅ "Unified" button redirects to `index-modular.php`
✅ "Pages" button redirects to `pages/` from unified
✅ Navigation highlights active page
✅ No JavaScript console errors
✅ No PHP errors in error_log
✅ CRUD operations work (add/edit/delete)
✅ Permissions enforced correctly

## Final Checklist Before Going Live

Before announcing to users:

- [ ] All files uploaded successfully
- [ ] File permissions set correctly (644)
- [ ] Feature selector loads without errors
- [ ] All 6 pages accessible
- [ ] Navigation works in both modes
- [ ] Mode switching functional
- [ ] Permissions tested with different roles
- [ ] CRUD operations verified on each page
- [ ] Mobile responsiveness checked
- [ ] Browser console shows no errors
- [ ] PHP error_log shows no errors
- [ ] Backup of old files created
- [ ] Documentation uploaded (optional)

## Need Help?

If you encounter issues during upload:

1. Check `DEPLOYMENT_IDE.md` → Common Issues section
2. Review browser console (F12) for JavaScript errors
3. Check server error_log: `/var/www/html/error_log`
4. Compare file checksums: `md5sum filename.php`
5. Test with different browsers
6. Try incognito/private mode (clear cache)

## Contact & Support

For technical issues:
- Check documentation files (6 guides available)
- Review error logs (browser console + server error_log)
- Test with different user roles
- Verify all prerequisites met

---

**Remember:** The unified view continues to work independently. Adding standalone pages is a NON-BREAKING change. If issues occur, simply remove the `pages/` directory and unified view continues normally.

🚀 **Ready to upload? Follow the steps above and you'll be live in minutes!**
