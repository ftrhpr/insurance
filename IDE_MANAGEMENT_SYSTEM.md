# OTOMOTORS IDE Management System

## Overview
The OTOMOTORS Manager Portal now supports **two operational modes** for maximum flexibility:

1. **Unified View** (SPA) - `index-modular.php`
2. **Standalone Pages** - `pages/*.php`

Both modes share the same codebase (components, modules, and APIs) with zero code duplication.

## Dual-Mode Architecture

### Unified View (Single-Page Application)
**File:** `index-modular.php`

**Features:**
- Instant view switching without page reloads
- Shared state across features
- Faster navigation for multi-tasking
- Single browser tab operation

**Best For:**
- Quick task switching
- Continuous workflow
- Minimal browser tabs
- Real-time data sharing between views

**Navigation:**
```javascript
window.switchView('dashboard'); // Changes view instantly
window.switchView('vehicles');  // No page reload
```

### Standalone Pages Mode
**Files:** `pages/dashboard.php`, `pages/vehicles.php`, etc.

**Features:**
- Dedicated URL per feature
- Bookmarkable pages
- Better browser history
- Independent debugging
- IDE-friendly file structure

**Best For:**
- Bookmarking specific features
- Direct URL access
- Debugging isolated features
- Working in multiple browser tabs
- IDE navigation and search

**Navigation:**
```php
window.location.href = 'dashboard.php'; // Full page load
window.location.href = 'vehicles.php';  // New page context
```

## File Structure

```
/
├── index-modular.php              # Unified SPA entry point
├── pages/                         # Standalone pages directory
│   ├── index.php                  # Feature selector page
│   ├── dashboard.php              # Dashboard standalone
│   ├── vehicles.php               # Vehicle DB standalone
│   ├── reviews.php                # Reviews standalone (manager+)
│   ├── templates.php              # SMS Templates standalone (manager+)
│   └── users.php                  # User Management standalone (admin only)
├── includes/
│   ├── header.php                 # Smart navigation header (mode-aware)
│   └── auth.php                   # Role-based access control
├── views/                         # Shared view components
│   ├── dashboard.php
│   ├── vehicles.php
│   ├── reviews.php
│   ├── templates.php
│   └── users.php
├── assets/js/                     # Shared JavaScript modules
│   ├── app.js
│   ├── transfers.js
│   ├── vehicles.js
│   ├── reviews.js
│   ├── sms-templates.js
│   └── user-management.js
└── api.php                        # Unified backend API
```

## Smart Header Navigation

The `includes/header.php` automatically detects the current mode and adjusts:

### In Unified View:
- Navigation buttons call `window.switchView('viewName')`
- Shows "Pages" button to switch to standalone mode
- Active tab highlighted in yellow

### In Standalone Pages:
- Navigation buttons use `window.location.href = 'page.php'`
- Shows "Unified" button to switch to SPA mode
- Active page highlighted in yellow
- Full page reload on navigation

## Page Access Permissions

Each standalone page enforces role-based access:

| Page | Permission | Roles Allowed |
|------|-----------|---------------|
| `dashboard.php` | `requireLogin()` | All authenticated users |
| `vehicles.php` | `requireLogin()` | All authenticated users |
| `reviews.php` | `requireRole('manager')` | Manager, Admin |
| `templates.php` | `requireRole('manager')` | Manager, Admin |
| `users.php` | `requireRole('admin')` | Admin only |

## Switching Between Modes

### From Unified to Standalone:
1. Click "Pages" button in header (purple badge)
2. Redirects to `pages/` (feature selector)
3. Select desired feature page

### From Standalone to Unified:
1. Click "Unified" button in header (indigo badge)
2. Redirects to `index-modular.php`
3. Loads last viewed feature or defaults to dashboard

### Direct Access:
- Unified: `https://yourdomain.com/index-modular.php`
- Standalone: `https://yourdomain.com/pages/dashboard.php`
- Feature Selector: `https://yourdomain.com/pages/`

## Standalone Page Structure

Each standalone page follows this pattern:

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../config.php';

requireLogin();
requireRole('manager'); // Optional, based on feature
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feature Name - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50">
    
    <?php include '../includes/header.php'; ?>
    
    <div id="main-content" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <?php include '../views/feature.php'; ?>
    </div>
    
    <?php include '../includes/modals/feature-modal.php'; ?>
    
    <script type="module" src="../assets/js/app.js"></script>
    <script type="module" src="../assets/js/feature.js"></script>
    
    <script>
        const IS_STANDALONE = true;
        
        // Override switchView for page navigation
        window.switchView = function(view) {
            window.location.href = `${view}.php`;
        };
        
        // Initialize data on page load
        document.addEventListener('DOMContentLoaded', async () => {
            await window.loadData();
            await window.loadFeatureData(); // Feature-specific
            window.initLucide();
        });
    </script>
    
</body>
</html>
```

## Code Reusability

### Shared Components (0% Duplication):
- ✅ `includes/header.php` - Navigation header
- ✅ `includes/auth.php` - Authentication/authorization
- ✅ `views/*.php` - Feature view HTML
- ✅ `includes/modals/*.php` - Modal dialogs
- ✅ `assets/js/*.js` - JavaScript modules
- ✅ `api.php` - Backend endpoints
- ✅ `config.php` - Database configuration

### Mode-Specific Code:
- `index-modular.php` - View switching logic
- `pages/*.php` - Page initialization wrappers
- `IS_STANDALONE` flag - Context detection

## Development Workflow

### Adding a New Feature:

1. **Create Shared View:**
```php
// views/new-feature.php
<div id="new-feature-view" class="hidden">
    <!-- Feature HTML -->
</div>
```

2. **Create JavaScript Module:**
```javascript
// assets/js/new-feature.js
export function loadNewFeature() { /* ... */ }
window.loadNewFeature = loadNewFeature;
```

3. **Add to Unified View:**
```php
// index-modular.php
<?php include 'views/new-feature.php'; ?>
<script src="assets/js/new-feature.js"></script>
```

4. **Create Standalone Page:**
```php
// pages/new-feature.php
<?php
// Standard page structure
require_once '../includes/auth.php';
requireLogin();
?>
<!DOCTYPE html>
<!-- Include view and scripts -->
```

5. **Update Navigation:**
```php
// includes/header.php - Add button to navButton() calls
navButton('new-feature', 'Feature Name', 'icon-name', $current_page, $base_path);
```

6. **Add to Feature Selector:**
```html
<!-- pages/index.php - Add feature card -->
<a href="new-feature.php">...</a>
```

## Benefits of Dual-Mode

### For Developers:
- ✅ Test features in isolation (standalone)
- ✅ Debug without SPA complexity
- ✅ IDE file navigation works naturally
- ✅ Direct URL access for specific features
- ✅ Easier git diff and code review

### For Users:
- ✅ Choose preferred workflow style
- ✅ Bookmark frequently used features
- ✅ Open multiple features in tabs (standalone)
- ✅ Fast multi-tasking (unified)
- ✅ Browser history works correctly

### For System:
- ✅ Zero code duplication
- ✅ Single source of truth for logic
- ✅ Consistent authentication across modes
- ✅ Shared API endpoints
- ✅ Unified styling and components

## Testing Checklist

### Unified View Testing:
- [ ] All views load correctly
- [ ] Navigation switches views instantly
- [ ] No page reloads on view change
- [ ] Data persists across views
- [ ] Firebase notifications work
- [ ] SMS import functions correctly
- [ ] "Pages" button redirects to feature selector

### Standalone Pages Testing:
- [ ] Each page loads independently
- [ ] Authentication enforced on all pages
- [ ] Role permissions work correctly
- [ ] Navigation reloads pages
- [ ] Data loads on page initialization
- [ ] Modals open/close correctly
- [ ] "Unified" button returns to SPA
- [ ] Browser back/forward works

### Cross-Mode Testing:
- [ ] Switch from unified to standalone
- [ ] Switch from standalone to unified
- [ ] Navigation highlights active page/view
- [ ] Permissions consistent across modes
- [ ] API responses identical in both modes
- [ ] Lucide icons render in both modes

## Deployment

### Upload Files:
```bash
# Upload standalone pages directory
ftp://server/pages/
  - index.php
  - dashboard.php
  - vehicles.php
  - reviews.php
  - templates.php
  - users.php

# Upload updated header
ftp://server/includes/header.php
```

### Test After Deployment:
1. Visit `https://yourdomain.com/pages/` (feature selector)
2. Click each feature card, verify loads
3. Test navigation between pages
4. Verify "Unified" button works
5. Visit `https://yourdomain.com/index-modular.php`
6. Test "Pages" button works
7. Test view switching in unified mode

## Troubleshooting

### "Unified" button doesn't work:
- Check `$_SERVER['PHP_SELF']` contains `/pages/`
- Verify `../index-modular.php` path is correct

### Navigation doesn't highlight active page:
- Verify `basename($_SERVER['PHP_SELF'], '.php')` returns page name
- Check `$base_path` calculation in header.php

### Permission errors:
- Verify `requireRole()` matches intended access level
- Check `isManager()` and `isAdmin()` helper functions

### JavaScript not loading:
- Check `IS_STANDALONE` flag is set correctly
- Verify script paths use `../assets/js/`
- Ensure `window.switchView` override is present

## Future Enhancements

- [ ] Add breadcrumb navigation to standalone pages
- [ ] Implement keyboard shortcuts (Alt+1 for dashboard, etc.)
- [ ] Add page transition animations
- [ ] Create "Recent Pages" dropdown
- [ ] Add "Pin Favorite Pages" feature
- [ ] Mobile-responsive sidebar for standalone mode
- [ ] Add search functionality to feature selector
- [ ] Create workspace presets (save preferred tab layouts)

## Conclusion

The dual-mode system provides maximum flexibility while maintaining code quality:
- **Choose your workflow:** SPA or separate pages
- **Zero duplication:** All code shared between modes
- **Easy development:** Add features once, works in both modes
- **Better UX:** Users pick what works best for them

Start with the **feature selector** at `pages/` to explore all available features! 🚀
