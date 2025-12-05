# DEPLOYMENT GUIDE - IDE Management System

## ✅ Pre-Deployment Checklist

### Files to Upload:
```
✅ pages/index.php (Feature selector)
✅ pages/dashboard.php
✅ pages/vehicles.php
✅ pages/reviews.php
✅ pages/templates.php
✅ pages/users.php
✅ includes/header.php (Updated with mode detection)
✅ IDE_MANAGEMENT_SYSTEM.md (Documentation)
```

### Already Deployed (From Previous Work):
```
✅ index-modular.php
✅ views/*.php (All view components)
✅ assets/js/*.js (All JS modules with 27 bugs fixed)
✅ includes/modals/*.php
✅ includes/auth.php
✅ api.php
✅ config.php
```

## 🚀 Deployment Steps

### Step 1: Upload New Files
Upload the `/pages/` directory to your server:

**Via FTP:**
```
Remote Directory: /public_html/pages/
Upload Files:
  - index.php
  - dashboard.php
  - vehicles.php
  - reviews.php
  - templates.php
  - users.php
```

**Via Command Line:**
```bash
# From your local machine
scp pages/*.php user@server:/var/www/html/pages/
```

### Step 2: Update Header
Replace the existing header file:

**Upload:**
```
Remote File: /public_html/includes/header.php
Local File: includes/header.php
```

This updated header includes:
- Mode detection (`$is_standalone`)
- Dynamic navigation (`navButton()` function)
- View mode toggle button
- Active page highlighting

### Step 3: Verify Permissions
Ensure all PHP files have correct permissions:

```bash
# Set permissions on server
chmod 644 pages/*.php
chmod 644 includes/header.php
```

### Step 4: Test Database Connection
Visit: `https://yourdomain.com/test_connection.php`

Expected Output: "✅ Database connection successful!"

## 🧪 Post-Deployment Testing

### Test 1: Feature Selector
**URL:** `https://yourdomain.com/pages/`

✅ Check:
- [ ] Page loads without errors
- [ ] All 5-6 feature cards visible (depends on role)
- [ ] Permission badges show correctly
- [ ] "Unified View" card visible
- [ ] Lucide icons render

### Test 2: Dashboard Page
**URL:** `https://yourdomain.com/pages/dashboard.php`

✅ Check:
- [ ] Page loads with authentication
- [ ] Stats cards display
- [ ] SMS import section visible
- [ ] Transfer tables render
- [ ] "Unified" button in header
- [ ] Navigation highlights "Dashboard"

### Test 3: Navigation Between Pages
**From Dashboard:**
- [ ] Click "Vehicle DB" → Loads `vehicles.php`
- [ ] Click "Reviews" → Loads `reviews.php` (if manager/admin)
- [ ] Click "SMS Templates" → Loads `templates.php` (if manager/admin)
- [ ] Click "Users" → Loads `users.php` (if admin)

### Test 4: Switch to Unified Mode
**From Any Standalone Page:**
- [ ] Click "Unified" button in header
- [ ] Redirects to `index-modular.php`
- [ ] Dashboard view loads
- [ ] "Pages" button visible in header

### Test 5: Switch to Standalone Mode
**From Unified View:**
- [ ] Click "Pages" button in header
- [ ] Redirects to `pages/` (feature selector)
- [ ] All features accessible

### Test 6: Permission Enforcement
**Test as different roles:**

**Staff/Manager:**
- [ ] Can access: dashboard.php, vehicles.php, reviews.php, templates.php
- [ ] Cannot access: users.php (should redirect/error)

**Admin:**
- [ ] Can access: All pages including users.php

### Test 7: CRUD Operations
**In Each Standalone Page:**

**Dashboard:**
- [ ] Import SMS text → Creates transfers
- [ ] Edit transfer → Opens modal, saves changes
- [ ] Delete transfer → Confirms and removes
- [ ] SMS sent successfully

**Vehicles:**
- [ ] Add vehicle → Opens modal, saves
- [ ] Edit vehicle → Updates data
- [ ] Delete vehicle → Removes record
- [ ] Service history displays

**Reviews:**
- [ ] Approve review → Status changes
- [ ] Reject review → Status changes
- [ ] Filter by status works

**Templates:**
- [ ] Edit template → Saves changes
- [ ] Preview placeholders work
- [ ] Reset to default functions

**Users:**
- [ ] Add user → Creates account
- [ ] Edit user → Updates details
- [ ] Change password → Updates successfully
- [ ] Delete user → Removes account

### Test 8: Browser Compatibility
Test in multiple browsers:
- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if available)

### Test 9: Mobile Responsiveness
- [ ] Navigation menu accessible
- [ ] Feature cards stack vertically
- [ ] Tables scroll horizontally
- [ ] Modals display correctly

### Test 10: Error Handling
**Test error scenarios:**
- [ ] Invalid URL (e.g., `pages/nonexistent.php`)
- [ ] Unauthenticated access → Redirects to login
- [ ] Insufficient permissions → Access denied
- [ ] Offline mode → Shows offline indicator

## 🐛 Common Issues & Solutions

### Issue: "Page Not Found" for standalone pages
**Solution:**
- Verify `/pages/` directory uploaded
- Check file permissions (644)
- Ensure `.htaccess` allows directory access

### Issue: Navigation doesn't highlight active page
**Solution:**
- Clear browser cache (Ctrl+Shift+R)
- Verify `includes/header.php` uploaded correctly
- Check `$current_page` variable in browser console

### Issue: "Unified" button redirects to wrong path
**Solution:**
- Verify `../index-modular.php` path exists
- Check server directory structure matches local
- Ensure `index-modular.php` is in root directory

### Issue: Permissions not enforced
**Solution:**
- Verify `includes/auth.php` uploaded
- Check session started in each page
- Test `requireRole()` function in `test_connection.php`

### Issue: JavaScript not loading
**Solution:**
- Check browser console for 404 errors
- Verify `assets/js/` paths correct (use `../assets/js/` in standalone pages)
- Ensure CDN scripts (Tailwind, Lucide) accessible

### Issue: Modals don't open
**Solution:**
- Verify modal includes present in each page
- Check `window.openEditModal()` function exists
- Ensure Lucide icons initialized after modal injection

## 🔍 Debugging Tools

### Browser Console Checks:
```javascript
// Verify mode detection
console.log(IS_STANDALONE); // Should be true in standalone pages

// Check switchView override
console.log(window.switchView.toString()); // Should show page navigation

// Verify data loaded
console.log(window.transfers); // Should show array
console.log(window.vehicles); // Should show array
```

### Network Tab:
- Check API calls to `api.php`
- Verify 200 status codes
- Inspect JSON responses
- Look for CORS errors

### PHP Error Log:
```bash
# On server, check for PHP errors
tail -f /var/www/html/error_log
```

## 📊 Performance Optimization

### After Deployment:

1. **Enable Gzip Compression:**
```apache
# Add to .htaccess
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript
</IfModule>
```

2. **Browser Caching:**
```apache
# Add to .htaccess
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

3. **Monitor Performance:**
- Use browser DevTools → Performance tab
- Check page load times
- Optimize slow API endpoints

## 📝 Rollback Plan

If issues occur, rollback to previous version:

### Rollback Steps:
1. **Restore old header:**
   ```bash
   cp includes/header.php.backup includes/header.php
   ```

2. **Remove standalone pages:**
   ```bash
   rm -rf pages/
   ```

3. **Keep unified view working:**
   - `index-modular.php` remains functional
   - All existing features still work

### Backup Before Deployment:
```bash
# Create backups
cp includes/header.php includes/header.php.backup
cp -r pages/ pages.backup/
```

## ✨ Success Criteria

Deployment is successful when:

✅ All 6 standalone pages load without errors  
✅ Navigation works in both modes (unified + standalone)  
✅ Mode toggle buttons function correctly  
✅ Active page/view highlighting accurate  
✅ CRUD operations work in all features  
✅ Permission enforcement correct for all roles  
✅ No JavaScript console errors  
✅ No PHP errors in error_log  
✅ Mobile responsive design works  
✅ Browser back/forward buttons work  

## 🎯 Next Steps After Deployment

1. **Update Documentation:**
   - Add standalone pages info to `README.md`
   - Document keyboard shortcuts (future feature)
   - Create video tutorial for users

2. **User Training:**
   - Show staff how to switch modes
   - Explain benefits of each mode
   - Demonstrate bookmarking features

3. **Monitor Usage:**
   - Track which mode users prefer
   - Identify most-used features
   - Gather feedback for improvements

4. **Implement Enhancements:**
   - Add breadcrumb navigation
   - Create keyboard shortcuts
   - Implement workspace presets

## 📞 Support

If issues persist after following this guide:

1. Check `error_log` for PHP errors
2. Review browser console for JS errors
3. Verify all files uploaded correctly
4. Compare local vs. server file timestamps
5. Test with hard refresh (Ctrl+Shift+R)

## 🎉 Congratulations!

You've successfully deployed the **OTOMOTORS IDE Management System** with dual-mode operation! Users can now choose between:

- **Unified View** for fast, SPA-style workflows
- **Standalone Pages** for IDE-friendly, bookmarkable features

Both modes share the same robust, bug-fixed codebase with zero duplication. 🚀
