<?php
require_once 'session_config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Simple language function for users
function __($key, $default = '') {
    $fallbacks = [
        'users.title' => 'User Management',
        'users.add_user' => 'Add User',
        'users.username' => 'Username'
    ];
    return $fallbacks[$key] ?? $default ?: $key;
}

// Check admin access
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get user info from session
$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'];

// Database connection
require_once 'config.php';

// Add current logged-in user as default
$defaultUser = [
    'id' => $_SESSION['user_id'] ?? 1,
    'username' => $_SESSION['username'] ?? 'admin',
    'full_name' => $_SESSION['full_name'] ?? 'System Administrator',
    'email' => $_SESSION['email'] ?? 'admin@otoexpress.ge',
    'role' => $_SESSION['role'] ?? 'admin',
    'status' => 'active',
    'last_login' => date('Y-m-d H:i:s'),
    'created_at' => date('Y-m-d H:i:s')
];

try {
    $pdo = getDBConnection();

    // Check if users table exists, create if not
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $result->rowCount() > 0;
    
    if (!$tableExists) {
        $sql = "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            role VARCHAR(20) DEFAULT 'manager',
            status VARCHAR(20) DEFAULT 'active',
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT DEFAULT NULL
        )";
        $pdo->exec($sql);

        // Create default admin user
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, 'admin', 'active')")
            ->execute(['admin', $defaultPassword, 'System Administrator']);
    }

    // Fetch all users
    $stmt = $pdo->query("SELECT id, username, full_name, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // On error, show at least the current logged-in user
    $users = [$defaultUser];
    error_log("Database error in users.php: " . $e->getMessage());
}

// Ensure $users is always an array
if (!isset($users) || !is_array($users)) {
    $users = [$defaultUser];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('users.title', 'User Management'); ?> - OTOMOTORS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e'
                        },
                        accent: {
                            50: '#fdf4ff', 100: '#fae8ff', 500: '#d946ef', 600: '#c026d3'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        * { font-family: 'Inter', -apple-system, system-ui, sans-serif; }
        .gradient-primary {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        }
        .gradient-primary:hover {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Main Content -->
    <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-slate-200/60 p-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-slate-800"><?php echo __('users.user_accounts', 'User Accounts'); ?></h2>
            <button onclick="window.openCreateUserModal()" class="px-6 py-3 gradient-primary text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <?php echo __('users.add_user', 'Add User'); ?>
            </button>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">User</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Last Login</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body" class="divide-y divide-slate-200">
                    <!-- Will be populated by JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Role Descriptions -->
        <div class="grid md:grid-cols-3 gap-4 mt-8">
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-purple-200 flex items-center justify-center">
                        <i data-lucide="shield" class="w-4 h-4 text-purple-700"></i>
                    </div>
                    <h4 class="font-bold text-purple-900">Admin</h4>
                </div>
                <p class="text-sm text-purple-700">Full system access including user management, settings, and all features</p>
            </div>
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-200 flex items-center justify-center">
                        <i data-lucide="user" class="w-4 h-4 text-blue-700"></i>
                    </div>
                    <h4 class="font-bold text-blue-900">Manager</h4>
                </div>
                <p class="text-sm text-blue-700">Access to vehicle database, customer management, and service tracking</p>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-green-200 flex items-center justify-center">
                        <i data-lucide="eye" class="w-4 h-4 text-green-700"></i>
                    </div>
                    <h4 class="font-bold text-green-900">Viewer</h4>
                </div>
                <p class="text-sm text-green-700">Read-only access to view records and reports</p>
            </div>
        </div>
    </div>
</main>

<!-- Create User Modal -->
<div id="create-user-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-slate-200">
            <h3 class="text-xl font-bold text-slate-800">Create New User</h3>
        </div>
        <form id="create-user-form" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                <input type="text" name="username" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                <input type="text" name="full_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                <select name="role" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <option value="viewer">Viewer</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            </div>
        </form>
        <div class="p-6 border-t border-slate-200 flex gap-3">
            <button onclick="closeCreateUserModal()" class="flex-1 px-4 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                Cancel
            </button>
            <button onclick="submitCreateUser()" class="flex-1 px-4 py-2 gradient-primary text-white rounded-lg hover:shadow-lg transition-all">
                Create User
            </button>
        </div>
    </div>
</div>

<script>
// Users data from PHP
const users = <?php echo json_encode($users); ?>;

// Render users table
function renderUsersTable() {
    const tbody = document.getElementById('users-table-body');
    tbody.innerHTML = users.map(user => `
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-accent-600 rounded-full flex items-center justify-center text-white font-bold">
                        ${user.full_name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="font-semibold text-slate-900">${user.full_name}</div>
                        <div class="text-sm text-slate-500">${user.email || 'No email'}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-900">${user.username}</td>
            <td class="px-6 py-4">
                <span class="px-2 py-1 text-xs font-semibold rounded-full ${
                    user.role === 'admin' ? 'bg-purple-100 text-purple-800' :
                    user.role === 'manager' ? 'bg-blue-100 text-blue-800' :
                    'bg-green-100 text-green-800'
                }">
                    ${user.role}
                </span>
            </td>
            <td class="px-6 py-4">
                <span class="px-2 py-1 text-xs font-semibold rounded-full ${
                    user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                }">
                    ${user.status}
                </span>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">
                ${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex justify-end gap-2">
                    <button onclick="editUser(${user.id})" class="p-1 text-slate-400 hover:text-primary-600 transition-colors" title="Edit">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                    </button>
                    <button onclick="deleteUser(${user.id})" class="p-1 text-slate-400 hover:text-red-600 transition-colors" title="Delete">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
    lucide.createIcons();
}

// Modal functions
function openCreateUserModal() {
    document.getElementById('create-user-modal').classList.remove('hidden');
}

function closeCreateUserModal() {
    document.getElementById('create-user-modal').classList.add('hidden');
    document.getElementById('create-user-form').reset();
}

async function submitCreateUser() {
    const form = document.getElementById('create-user-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    try {
        const response = await fetch('api.php?action=create_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('User created successfully', 'success');
            closeCreateUserModal();
            // Refresh users list
            location.reload();
        } else {
            showToast(result.message || 'Failed to create user', 'error');
        }
    } catch (error) {
        console.error('Error creating user:', error);
        showToast('Failed to create user', 'error');
    }
}

function editUser(userId) {
    // TODO: Implement edit functionality
    showToast('Edit functionality coming soon', 'info');
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    
    try {
        const response = await fetch(`api.php?action=delete_user&id=${userId}`, {
            method: 'DELETE'
        });
        const result = await response.json();
        
        if (result.success) {
            showToast('User deleted successfully', 'success');
            location.reload();
        } else {
            showToast(result.message || 'Failed to delete user', 'error');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showToast('Failed to delete user', 'error');
    }
}

// Toast notification function
function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
        warning: 'bg-yellow-500'
    };
    
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    renderUsersTable();
});
</script>

</body>
</html>