# Header Component Implementation

## Overview
Created a shared header component (`header.php`) that provides consistent navigation across all pages in the OTOMOTORS portal.

## Files Modified

### New File
- **`header.php`** - Shared header component with navigation, user menu, and styling

### Updated Files
1. **`templates.php`** - Now uses shared header
2. **`users.php`** - Now uses shared header
3. **`vehicles.php`** - Now uses shared header
4. **`reviews.php`** - Now uses shared header

## Header Features

### Navigation
- Automatic active page detection based on current PHP file
- Dynamic navigation items:
  - Dashboard (index.php)
  - Vehicle DB (vehicles.php)
  - Reviews (reviews.php)
  - SMS Templates (templates.php)
  - Users (users.php) - **Admin only**

### User Menu
- User avatar with initials
- Username and role display
- Dropdown menu with:
  - Change Password (dashboard only)
  - Logout

### Page-Specific Elements
- **Dashboard only**: Notification bell and connection status indicator
- **All pages**: Full navigation bar with active state highlighting

### Styling
- Gradient logo with blur effect
- Premium navigation with active/hover states
- Gradient text effects
- Custom scrollbar styles
- Responsive design (mobile menu hidden on small screens)

## Usage

To use the header in any page:

```php
<?php
session_start();

// Set user info from session
$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Your head content -->
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Your page content -->
</main>

</body>
</html>
```

## Requirements

### Session Variables
The header requires these session variables to be set:
- `$_SESSION['user_id']` - User ID (required for authentication check)
- `$_SESSION['username']` - Username
- `$_SESSION['full_name']` - Full name for display
- `$_SESSION['role']` - User role (admin/manager/viewer)

### PHP Variables
Before including header.php, set:
- `$current_user_name` - Display name
- `$current_user_role` - User role

### External Dependencies
- Tailwind CSS (CDN)
- Lucide Icons (CDN)
- Inter font (Google Fonts)

## Design Consistency

All pages now share:
- Same color scheme (blue/indigo gradient)
- Consistent typography (Inter font)
- Unified navigation system
- Premium UI elements (glass morphism, gradients, shadows)
- Active state highlighting for current page

## Benefits

1. **Maintainability**: Single source of truth for header/navigation
2. **Consistency**: All pages look and behave the same
3. **Efficiency**: Changes to navigation apply globally
4. **Role-based access**: Admin-only pages automatically hidden for non-admins
5. **Active states**: Current page automatically highlighted in navigation
