# Database Connection Troubleshooting Guide

## Problem: "Database error. Please try again."

### Quick Fix Steps

1. **Run Database Diagnostic**
   - Open in browser: `https://yourdomain.com/test_db_connection.php`
   - This will show you exactly what's wrong

2. **Common Issues & Solutions**

#### Issue: "Users table does not exist"
**Solution:** 
```
Open: https://yourdomain.com/fix_db_all.php
This will create all tables including the users table
```

#### Issue: "Access denied for user"
**Solution:** Check database credentials in these files:
- `api.php` (lines 10-13)
- `login.php` (lines 18-21)
- `config.php` (lines 6-9)

Make sure all three files have matching credentials:
```php
$db_host = 'localhost';
$db_name = 'otoexpre_userdb';
$db_user = 'otoexpre_userdb';
$db_pass = 'p52DSsthB}=0AeZ#';
```

#### Issue: "Unknown database"
**Solution:** The database doesn't exist. Create it via cPanel or phpMyAdmin:
1. Login to cPanel
2. Go to MySQL Databases
3. Create database: `otoexpre_userdb`
4. Create user: `otoexpre_userdb` with password
5. Grant all privileges to user on database

#### Issue: "No users found"
**Solution:**
```
1. Open: https://yourdomain.com/fix_db_all.php
2. This creates default admin user
3. Default login: admin / admin123
```

### Step-by-Step Setup (First Time)

1. **Upload all files to server via FTP**

2. **Test database connection**
   ```
   https://yourdomain.com/test_db_connection.php
   ```

3. **Create database tables**
   ```
   https://yourdomain.com/fix_db_all.php
   ```
   Should see:
   - ✓ Table structure verified for all tables
   - ✓ Default admin user created

4. **Try logging in**
   ```
   https://yourdomain.com/login.php
   Username: admin
   Password: admin123
   ```

5. **Change default password immediately!**

### Files to Check

If still having issues, verify these files exist and have correct permissions (644):
- ✓ `api.php`
- ✓ `login.php`
- ✓ `logout.php`
- ✓ `index.php`
- ✓ `config.php`
- ✓ `fix_db_all.php`
- ✓ `test_db_connection.php`

### Error Messages & Meanings

| Error Message | Meaning | Solution |
|--------------|---------|----------|
| "Database connection failed" | Can't connect to MySQL | Check MySQL is running, check host/port |
| "Database error. Please try again." | Generic DB error | Run test_db_connection.php |
| "Unauthorized. Please login." | No valid session | Login at /login.php |
| "User system not initialized" | No users table | Run fix_db_all.php |
| "Invalid username or password" | Wrong credentials | Use admin/admin123 or correct credentials |

### Manual Database Setup (If Automated Fails)

If `fix_db_all.php` fails, manually run this SQL in phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create default admin (password: admin123)
INSERT INTO users (username, password, full_name, role, status) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'active');
```

### Still Not Working?

Contact your hosting provider and ask:
1. "Is PDO MySQL extension enabled?"
2. "Can you verify my database credentials?"
3. "Is my database user allowed to connect from localhost?"
4. Show them the output from `test_db_connection.php`

### Support Checklist

When asking for help, provide:
- [ ] Output from `test_db_connection.php`
- [ ] PHP version (from phpinfo.php or cPanel)
- [ ] Hosting provider name
- [ ] Exact error message from screen
- [ ] Browser console errors (F12 → Console tab)

### Quick Commands

**Test everything is working:**
```
https://yourdomain.com/test_db_connection.php
```

**Reset database (creates all tables):**
```
https://yourdomain.com/fix_db_all.php
```

**Login page:**
```
https://yourdomain.com/login.php
```

**Dashboard (after login):**
```
https://yourdomain.com/index.php
```
