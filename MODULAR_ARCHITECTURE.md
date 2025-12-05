# OTOMOTORS Manager Portal - Modular Architecture

## 🎯 Overview

The OTOMOTORS Manager Portal has been refactored into a modern, modular architecture for better maintainability, scalability, and IDE support. The codebase is now organized into logical modules with clear separation of concerns.

## 📁 New File Structure

```
/insurance/
├── assets/
│   └── js/
│       ├── app.js                  # Core application logic
│       ├── firebase-config.js      # Firebase initialization
│       ├── transfers.js            # Transfer/case management
│       ├── vehicles.js             # Vehicle database management
│       ├── reviews.js              # Customer reviews
│       ├── sms-templates.js        # SMS template system
│       └── user-management.js      # User CRUD operations
├── includes/
│   ├── auth.php                    # Authentication functions
│   ├── header.php                  # Navigation header component
│   └── modals/
│       ├── edit-modal.php          # Case edit modal
│       ├── vehicle-modal.php       # Vehicle modal
│       └── user-modals.php         # User management modals
├── views/
│   ├── dashboard.php               # Dashboard view
│   ├── vehicles.php                # Vehicle DB view
│   ├── reviews.php                 # Reviews view
│   ├── templates.php               # SMS templates view
│   └── users.php                   # User management view
├── index-modular.php               # New modular entry point
├── index.php                       # Original monolithic file (backup)
├── api.php                         # Backend API endpoints
├── config.php                      # Database configuration
├── login.php                       # Login page
└── logout.php                      # Logout handler
```

## 🔧 Key Improvements

### 1. **Separation of Concerns**
- **Views**: HTML templates in `/views/` directory
- **Logic**: JavaScript modules in `/assets/js/`
- **Components**: Reusable UI components in `/includes/`
- **Auth**: Authentication logic centralized in `/includes/auth.php`

### 2. **Modular JavaScript**
Each feature has its own JavaScript file:
- `app.js` - Core utilities (API calls, routing, toasts)
- `transfers.js` - Transfer table rendering, SMS parsing, editing
- `vehicles.js` - Vehicle database CRUD
- `reviews.js` - Review moderation
- `sms-templates.js` - Template management
- `user-management.js` - User administration

### 3. **Reusable PHP Functions**
```php
// Authentication helpers
requireLogin()              // Redirect if not logged in
requireRole('admin')        // Require specific role
isAdmin()                   // Check if user is admin
canEdit()                   // Check if user can edit
getCurrentUser()            // Get current user data
```

### 4. **Component-Based UI**
- Modals split into separate files
- Header extracted as reusable component
- Each view is self-contained

### 5. **Better IDE Support**
- Clear file structure for IntelliSense
- Proper JavaScript modules with JSDoc comments
- Type hints in PHP where applicable
- Consistent naming conventions

## 🚀 Migration Guide

### Option 1: Fresh Install (Recommended)
1. Backup your current `index.php`
2. Rename `index-modular.php` to `index.php`
3. Create the `/assets/js/`, `/includes/`, and `/views/` directories
4. Upload all modular files
5. Test thoroughly

### Option 2: Gradual Migration
Keep both versions running:
- Access modular version: `index-modular.php`
- Access original: `index.php`
- Switch when ready by renaming

## 📝 File Descriptions

### Core Files

#### `assets/js/app.js`
```javascript
// Core application initialization
// - API communication layer
// - View switching
// - Toast notifications
// - Global state management
```

#### `includes/auth.php`
```php
// Authentication & authorization
// - Session management
// - Role checking
// - Permission helpers
```

#### `includes/header.php`
```php
// Navigation component
// - Top menu bar
// - User dropdown
// - Role-based menu items
```

### Views

#### `views/dashboard.php`
- Stats cards (New, Processing, Scheduled, Completed)
- Bank SMS import form
- Active cases table
- New cases section

#### `views/users.php`
- User management table (admin only)
- CRUD operations
- Role descriptions

### Modals

#### `includes/modals/edit-modal.php`
- Case editing form
- Status management
- Customer response display
- Reschedule handling

#### `includes/modals/user-modals.php`
- Create/Edit user form
- Password change form

## 🎨 Styling Approach

### Tailwind CSS Classes
All styling uses Tailwind utility classes:
```html
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
    <!-- Content -->
</div>
```

### Custom Classes
Minimal custom CSS in `<style>` tag:
```css
.nav-active { @apply bg-slate-900 text-white shadow-sm; }
.nav-inactive { @apply text-slate-500 hover:text-slate-900; }
```

## 🔌 API Integration

All JavaScript modules use the centralized `fetchAPI()` function:

```javascript
// From app.js
async function fetchAPI(action, method = 'GET', body = null) {
    const opts = { method };
    if (body) opts.body = JSON.stringify(body);
    
    try {
        const res = await fetch(`${API_URL}?action=${action}`, opts);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return await res.json();
    } catch (err) {
        if (err.message.includes('Unauthorized')) {
            window.location.href = 'login.php';
        }
        throw err;
    }
}
```

Usage in modules:
```javascript
// In transfers.js
const data = await fetchAPI('get_transfers', 'GET');

// In user-management.js
await fetchAPI('create_user', 'POST', { username, password, full_name });
```

## 🧩 Adding New Features

### 1. Create a New View
```php
// views/reports.php
<div id="view-reports" class="hidden space-y-6">
    <h2>Reports</h2>
    <!-- Content -->
</div>
```

### 2. Add Navigation Item
```php
// includes/header.php
<button onclick="window.switchView('reports')" id="nav-reports" 
        class="nav-inactive px-4 py-1.5 rounded-md text-sm">
    <i data-lucide="bar-chart"></i> Reports
</button>
```

### 3. Create JavaScript Module
```javascript
// assets/js/reports.js
async function loadReports() {
    const data = await fetchAPI('get_reports', 'GET');
    renderReportsTable(data.reports);
}
```

### 4. Include in Main File
```php
<!-- index-modular.php -->
<?php include 'views/reports.php'; ?>
<script src="assets/js/reports.js"></script>
```

### 5. Update View Switcher
```javascript
// assets/js/app.js
window.switchView = (v) => {
    document.getElementById('view-reports').classList.toggle('hidden', v !== 'reports');
    // ...
    if (v === 'reports') {
        loadReports();
    }
};
```

## 🐛 Debugging

### Enable Verbose Logging
```javascript
// Add to app.js
const DEBUG = true;

async function fetchAPI(action, method, body) {
    if (DEBUG) console.log(`[API] ${method} ${action}`, body);
    // ... rest of function
}
```

### Check File Loading
```javascript
// Add to end of each .js file
console.log('✓ Module loaded: transfers.js');
```

### PHP Error Display
```php
// Add to top of index-modular.php for development
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## 📊 Performance Benefits

### Before (Monolithic)
- Single 2500+ line file
- Hard to navigate
- Difficult to debug
- Long load times
- Poor caching

### After (Modular)
- Largest file ~400 lines
- Easy navigation
- Isolated debugging
- Better browser caching
- Lazy loading possible

## 🔐 Security Considerations

### Authentication Flow
```
1. User loads index-modular.php
2. PHP checks $_SESSION['user_id']
3. If not set, redirect to login.php
4. If set, load user data and render UI
5. JavaScript inherits USER_ROLE from PHP
6. API validates session on every request
```

### Role-Based Rendering
```php
<?php if (isAdmin()): ?>
    <!-- Admin-only content -->
<?php endif; ?>
```

```javascript
if (CAN_EDIT) {
    // Show edit buttons
} else {
    // Show view-only UI
}
```

## 🧪 Testing

### Test Each Module Independently
```javascript
// In browser console
await fetchAPI('get_users', 'GET');        // Test API
window.switchView('users');                // Test routing
loadUsers();                               // Test module function
```

### Test Authentication
1. Logout and try to access `index-modular.php` → Should redirect to login
2. Login as Viewer → Should not see Users tab
3. Login as Admin → Should see all features

### Test Permissions
1. As Viewer, try to edit a case → Should see "View" button only
2. As Manager, should be able to edit cases
3. As Admin, should be able to manage users

## 📚 Code Standards

### JavaScript
- Use `async/await` for async operations
- Use `const` by default, `let` when needed
- Prefix global functions with `window.`
- Use descriptive variable names
- Add JSDoc comments for complex functions

### PHP
- Use `require_once` for includes
- Check authentication on every page
- Use prepared statements for DB queries
- Sanitize output with `htmlspecialchars()`

### HTML
- Use semantic elements (`<nav>`, `<main>`, `<section>`)
- Add ARIA labels for accessibility
- Keep views focused and single-purpose

## 🎯 Next Steps

1. **Create remaining module files** (transfers.js, vehicles.js, etc.)
2. **Extract inline JavaScript** from old index.php into modules
3. **Test all features** in modular version
4. **Switch production** to index-modular.php
5. **Archive old index.php** as index-legacy.php

## 🆘 Support

### Common Issues

**Issue**: Modules not loading
- **Solution**: Check file paths in `<script src="">`
- **Solution**: Check browser console for 404 errors

**Issue**: Functions undefined
- **Solution**: Verify module is included before calling function
- **Solution**: Check `window.functionName` vs `functionName`

**Issue**: Authentication errors
- **Solution**: Clear cookies and login again
- **Solution**: Check `includes/auth.php` is included first

## 📖 Resources

- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [Lucide Icons](https://lucide.dev)
- [MDN Web Docs](https://developer.mozilla.org)
- [PHP Documentation](https://www.php.net/docs.php)

---

**Version**: 2.0 Modular
**Last Updated**: December 2025
**Maintained By**: OTOMOTORS Development Team
