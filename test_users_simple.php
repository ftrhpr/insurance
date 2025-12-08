<?php
// Simple test version of users.php without authentication
require_once 'config.php';

echo "<!-- PHP DEBUG: Simple test started -->";

$defaultUser = [
    'id' => 1,
    'username' => 'admin',
    'full_name' => 'System Administrator',
    'email' => 'admin@otoexpress.ge',
    'role' => 'admin',
    'status' => 'active',
    'last_login' => date('Y-m-d H:i:s'),
    'created_at' => date('Y-m-d H:i:s')
];

try {
    echo "<!-- PHP DEBUG: Attempting database connection -->";
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<!-- PHP DEBUG: Database connection successful -->";

    // Check if users table exists, create if not
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() == 0) {
        echo "<!-- DEBUG: Creating users table -->";
        $sql = "CREATE TABLE users (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $pdo->exec($sql);

        // Create default admin user
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, 'admin', 'active')")
            ->execute(['admin', $defaultPassword, 'System Administrator']);
        echo "<!-- DEBUG: Default admin user created -->";
    }

    // Fetch all users
    $stmt = $pdo->query("SELECT id, username, full_name, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- PHP DEBUG: Users fetched: " . count($users) . " -->";

} catch (PDOException $e) {
    $users = [$defaultUser];
    echo "<!-- PHP DEBUG: Database error: " . $e->getMessage() . " -->";
}

echo "<!-- PHP DEBUG: PHP execution completed, users count: " . count($users) . " -->";
?>
<!-- HTML DEBUG: HTML rendering started -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management Test</title>
</head>
<body>
    <h1>User Management Test</h1>
    <div id="users-table-body">
        <p>Loading users...</p>
    </div>

    <script>
        let allUsers = <?php echo json_encode($users); ?>;
        console.log('JS DEBUG: allUsers loaded:', allUsers);

        function renderUsersTable() {
            console.log('JS DEBUG: renderUsersTable called');
            const tbody = document.getElementById('users-table-body');
            console.log('JS DEBUG: tbody element:', tbody);

            if (!tbody) {
                console.log('JS DEBUG: tbody not found!');
                return;
            }

            if (allUsers.length === 0) {
                tbody.innerHTML = '<p>No users found</p>';
                return;
            }

            let html = '<table border="1"><thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th></tr></thead><tbody>';
            allUsers.forEach(user => {
                html += `<tr>
                    <td>${user.id}</td>
                    <td>${user.username}</td>
                    <td>${user.full_name}</td>
                    <td>${user.role}</td>
                    <td>${user.status}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            tbody.innerHTML = html;
        }

        // Initialize table on page load
        renderUsersTable();
    </script>
</body>
</html>