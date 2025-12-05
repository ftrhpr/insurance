# User Management System - OTOMOTORS Manager Portal

## Overview

A comprehensive user authentication and role-based access control system has been added to the OTOMOTORS Manager Portal. The system includes user login, role-based permissions, and a full user management interface for administrators.

## Features

### 1. User Authentication
- Secure login system with username/password
- Session-based authentication
- Password hashing using PHP's `password_hash()`
- Automatic logout functionality

### 2. User Roles

#### Admin
- **Full system access**
- Can manage all users (create, edit, delete)
- Can edit all cases and data
- Access to User Management section
- Can change passwords for any user

#### Manager
- **Standard operational access**
- Can view and edit all cases
- Can send SMS notifications
- Can manage appointments
- Cannot access user management

#### Viewer
- **Read-only access**
- Can view all cases and data
- Cannot edit or modify anything
- Cannot send SMS
- Cannot access user management

### 3. User Management Interface (Admin Only)
- Create new users with username, password, full name, email, role, and status
- Edit existing users (all fields except username)
- Change user passwords
- Delete users (with safety checks)
- View user activity (last login)
- Active/Inactive user status toggle

## Database Schema

### `users` Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'manager', 'viewer') DEFAULT 'manager',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
)
```

## Files Modified/Created

### New Files
1. **`login.php`** - Login page with modern UI
2. **`logout.php`** - Session destruction script

### Modified Files
1. **`fix_db_all.php`** - Added users table creation with default admin user
2. **`api.php`** - Added authentication checks and 8 new user management endpoints
3. **`index.php`** - Added:
   - Session authentication check
   - User menu with profile dropdown
   - Users management view (admin only)
   - User management modals
   - Role-based UI controls
   - JavaScript functions for user management

## API Endpoints

### Authentication
- **Session-based**: All requests check `$_SESSION['user_id']`
- **Public endpoints**: `login`, `get_order_status`, `submit_review` (no auth required)
- **Protected endpoints**: All others require active session

### User Management Endpoints (Admin Only)
1. `GET api.php?action=get_users` - Retrieve all users
2. `POST api.php?action=create_user` - Create new user
3. `POST api.php?action=update_user&id={id}` - Update user details
4. `POST api.php?action=change_password&id={id}` - Change user password
5. `POST api.php?action=delete_user&id={id}` - Delete user
6. `GET api.php?action=get_current_user` - Get current session user info

### Permission Function
```php
function checkPermission($required_role) {
    $user_role = $_SESSION['role'] ?? 'viewer';
    $hierarchy = ['viewer' => 1, 'manager' => 2, 'admin' => 3];
    return $hierarchy[$user_role] >= $hierarchy[$required_role];
}
```

## Installation & Setup

### 1. Run Database Migration
Access via browser: `https://yourdomain.com/fix_db_all.php`

This will:
- Create the `users` table
- Add default admin account
- Display success confirmation

### 2. Default Admin Credentials
```
Username: admin
Password: admin123
```

**⚠️ IMPORTANT**: Change this password immediately after first login!

### 3. Upload Files
Upload the following files to your server:
- `login.php`
- `logout.php`
- `index.php` (modified)
- `api.php` (modified)
- `fix_db_all.php` (modified)

### 4. First Login
1. Navigate to `https://yourdomain.com/login.php`
2. Login with default credentials
3. Click your profile menu → Change Password
4. Set a strong password

## Usage Guide

### For Admins

#### Creating a New User
1. Login and navigate to **Users** tab
2. Click **Add User** button
3. Fill in required fields:
   - Username (unique, cannot be changed later)
   - Password (min 6 characters)
   - Full Name
   - Email (optional)
   - Role (Admin/Manager/Viewer)
   - Status (Active/Inactive)
4. Click **Create User**

#### Editing a User
1. In Users table, click the **pencil icon** next to user
2. Modify full name, email, role, or status
3. Click **Update User**
4. Note: Username cannot be changed (create new user instead)

#### Changing User Password
1. Click the **key icon** next to user in table
2. Enter new password (min 6 characters)
3. Confirm password
4. Click **Update Password**

#### Deleting a User
1. Click the **trash icon** next to user
2. Confirm deletion in dialog
3. Safety checks:
   - Cannot delete yourself
   - Cannot delete last active admin

#### Changing Your Own Password
1. Click your profile menu (top right)
2. Select **Change Password**
3. Enter new password twice
4. Click **Update Password**

### For Managers
- Full dashboard access
- Can edit cases, send SMS, manage appointments
- Cannot access Users tab
- Can change own password via profile menu

### For Viewers
- Read-only access to all data
- Edit buttons replaced with **View** (eye icon)
- Cannot modify cases or send SMS
- Can change own password via profile menu

## Security Features

### Password Security
- Passwords hashed with `PASSWORD_DEFAULT` algorithm (currently bcrypt)
- Minimum 6 characters enforced
- Never stored in plain text

### Session Security
- PHP session-based authentication
- Automatic redirect to login if session expired
- Logout clears all session data

### Permission Checks
- Server-side validation on all protected endpoints
- Client-side UI hiding for better UX
- Role hierarchy prevents privilege escalation

### Safety Mechanisms
- Cannot delete yourself
- Cannot delete last active admin
- Username uniqueness enforced at DB level
- Inactive users cannot login

## User Interface

### Login Page
- Modern gradient design
- Clear error messages
- Default credentials shown for initial setup
- Lucide icons throughout

### Profile Menu
- Displays current user name and role
- Avatar with user initial
- Dropdown with:
  - User info display
  - Change Password option
  - Logout link

### Users Management View (Admin Only)
- Clean table layout with user avatars
- Color-coded role and status badges
- Last login tracking
- Quick action buttons (Edit, Change Password, Delete)
- Role descriptions at bottom

### Modals
- **User Modal**: Create/Edit user with all fields
- **Password Modal**: Change password with confirmation

## Technical Details

### JavaScript Constants
```javascript
const USER_ROLE = '<?php echo $current_user_role; ?>';
const CAN_EDIT = USER_ROLE === 'admin' || USER_ROLE === 'manager';
```

### Permission-Based UI Rendering
- Edit buttons shown only if `CAN_EDIT === true`
- Users tab only rendered for admins (`<?php if ($current_user_role === 'admin'): ?>`)
- Save functions check permissions before API calls

### View-Only Mode for Viewers
When a Viewer clicks the eye icon:
1. Modal opens with all data
2. All inputs disabled
3. Save button replaced with Close button
4. Prevents accidental edit attempts

## Troubleshooting

### Issue: "Unauthorized" error when accessing dashboard
- **Solution**: Session expired. Login again at `/login.php`

### Issue: Cannot see Users tab
- **Solution**: Only Admins can access user management. Check your role.

### Issue: "Table 'users' doesn't exist"
- **Solution**: Run `fix_db_all.php` to create the table

### Issue: Default admin login not working
- **Solution**: 
  1. Verify `fix_db_all.php` was run successfully
  2. Check for database connection errors
  3. Manually insert admin user via phpMyAdmin if needed

### Issue: Forgot admin password
- **Solution**: Use phpMyAdmin to update password:
```sql
UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE username = 'admin';
```
(This sets password to: `password`)

## Best Practices

### For Administrators
1. **Change default password immediately**
2. **Create individual accounts** - Don't share credentials
3. **Use principle of least privilege** - Assign minimum required role
4. **Deactivate users** instead of deleting (preserves audit trail)
5. **Regular password rotation** - Change admin passwords quarterly
6. **Monitor last login** - Identify inactive accounts

### For All Users
1. **Never share passwords**
2. **Use strong passwords** - Mix of letters, numbers, symbols
3. **Logout when finished** - Especially on shared computers
4. **Report suspicious activity** to administrators

## Future Enhancements (Potential)

- Two-factor authentication (2FA)
- Password reset via email
- User activity logging
- Failed login attempt tracking
- Password complexity requirements
- Session timeout configuration
- LDAP/Active Directory integration
- Audit trail for all user actions

## Support

For issues or questions:
1. Check this README
2. Review database migration output
3. Check browser console for JavaScript errors
4. Check PHP error logs on server
5. Verify database credentials in `config.php`

---

**Version**: 1.0  
**Last Updated**: December 2025  
**Compatible With**: OTOMOTORS Manager Portal v2.0+
