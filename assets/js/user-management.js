/**
 * User Management Module
 * Handles all user CRUD operations
 */

let allUsers = [];

window.loadUsers = async function() {
    try {
        const data = await fetchAPI('get_users', 'GET');
        allUsers = data.users || [];
        renderUsersTable();
    } catch (err) {
        console.error('Error loading users:', err);
        showToast('Error', 'Failed to load users', 'error');
    }
}

function renderUsersTable() {
    const tbody = document.getElementById('users-table-body');
    if (!tbody) return;
    
    if (allUsers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                    <i data-lucide="users" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                    <p>No users found</p>
                </td>
            </tr>
        `;
        initLucide();
        return;
    }
    
    tbody.innerHTML = allUsers.map(user => {
        const roleColors = {
            admin: 'bg-purple-100 text-purple-800 border-purple-200',
            manager: 'bg-blue-100 text-blue-800 border-blue-200',
            viewer: 'bg-slate-100 text-slate-800 border-slate-200'
        };
        
        const statusColors = {
            active: 'bg-emerald-100 text-emerald-800 border-emerald-200',
            inactive: 'bg-red-100 text-red-800 border-red-200'
        };
        
        const lastLogin = user.last_login ? new Date(user.last_login).toLocaleString() : 'Never';
        
        return `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold">
                            ${user.full_name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div class="font-semibold text-slate-800">${user.full_name}</div>
                            ${user.email ? `<div class="text-xs text-slate-500">${user.email}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="font-mono text-sm text-slate-700">${user.username}</span>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${roleColors[user.role]}">
                        ${user.role.charAt(0).toUpperCase() + user.role.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${statusColors[user.status]}">
                        ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">${lastLogin}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick="window.openEditUserModal(${user.id})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit User">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                        <button onclick="window.openChangeUserPasswordModal(${user.id})" class="p-2 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors" title="Change Password">
                            <i data-lucide="key" class="w-4 h-4"></i>
                        </button>
                        <button onclick="window.deleteUser(${user.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete User">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    initLucide();
}

window.openAddUserModal = function() {
    document.getElementById('user-modal-title').textContent = 'Add User';
    document.getElementById('user-save-btn-text').textContent = 'Create User';
    document.getElementById('user-id').value = '';
    document.getElementById('user-username').value = '';
    document.getElementById('user-username').disabled = false;
    document.getElementById('user-password').value = '';
    document.getElementById('password-field').style.display = 'block';
    document.getElementById('user-fullname').value = '';
    document.getElementById('user-email').value = '';
    document.getElementById('user-role').value = 'manager';
    document.getElementById('user-status').value = 'active';
    document.getElementById('user-modal').classList.remove('hidden');
    initLucide();
};

window.openEditUserModal = function(userId) {
    const user = allUsers.find(u => u.id === userId);
    if (!user) return;
    
    document.getElementById('user-modal-title').textContent = 'Edit User';
    document.getElementById('user-save-btn-text').textContent = 'Update User';
    document.getElementById('user-id').value = user.id;
    document.getElementById('user-username').value = user.username;
    document.getElementById('user-username').disabled = true;
    document.getElementById('password-field').style.display = 'none';
    document.getElementById('user-fullname').value = user.full_name;
    document.getElementById('user-email').value = user.email || '';
    document.getElementById('user-role').value = user.role;
    document.getElementById('user-status').value = user.status;
    document.getElementById('user-modal').classList.remove('hidden');
    initLucide();
};

window.closeUserModal = function() {
    document.getElementById('user-modal').classList.add('hidden');
};

window.saveUser = async function() {
    const userId = document.getElementById('user-id').value;
    const username = document.getElementById('user-username').value.trim();
    const password = document.getElementById('user-password').value;
    const fullName = document.getElementById('user-fullname').value.trim();
    const email = document.getElementById('user-email').value.trim();
    const role = document.getElementById('user-role').value;
    const status = document.getElementById('user-status').value;
    
    if (!username || !fullName) {
        showToast('Validation Error', 'Username and full name are required', 'error');
        return;
    }
    
    if (!userId && (!password || password.length < 6)) {
        showToast('Validation Error', 'Password must be at least 6 characters', 'error');
        return;
    }
    
    const data = { full_name: fullName, email, role, status };
    if (!userId) {
        data.username = username;
        data.password = password;
    }
    
    try {
        const action = userId ? `update_user&id=${userId}` : 'create_user';
        await fetchAPI(action, 'POST', data);
        showToast('Success', userId ? 'User updated successfully' : 'User created successfully', 'success');
        window.closeUserModal();
        loadUsers();
    } catch (err) {
        console.error('Error saving user:', err);
        showToast('Error', err.message || 'Failed to save user', 'error');
    }
};

window.openChangePasswordModal = function() {
    document.getElementById('pwd-user-id').value = '';
    document.getElementById('pwd-new-password').value = '';
    document.getElementById('pwd-confirm-password').value = '';
    document.getElementById('password-modal').classList.remove('hidden');
    initLucide();
};

window.openChangeUserPasswordModal = function(userId) {
    document.getElementById('pwd-user-id').value = userId;
    document.getElementById('pwd-new-password').value = '';
    document.getElementById('pwd-confirm-password').value = '';
    document.getElementById('password-modal').classList.remove('hidden');
    initLucide();
};

window.closePasswordModal = function() {
    document.getElementById('password-modal').classList.add('hidden');
};

window.savePassword = async function() {
    const userId = document.getElementById('pwd-user-id').value;
    const newPassword = document.getElementById('pwd-new-password').value;
    const confirmPassword = document.getElementById('pwd-confirm-password').value;
    
    if (!newPassword || newPassword.length < 6) {
        showToast('Validation Error', 'Password must be at least 6 characters', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showToast('Validation Error', 'Passwords do not match', 'error');
        return;
    }
    
    try {
        const action = userId ? `change_password&id=${userId}` : 'change_password';
        await fetchAPI(action, 'POST', { password: newPassword });
        showToast('Success', 'Password changed successfully', 'success');
        window.closePasswordModal();
    } catch (err) {
        console.error('Error changing password:', err);
        showToast('Error', err.message || 'Failed to change password', 'error');
    }
};

window.deleteUser = async function(userId) {
    const user = allUsers.find(u => u.id === userId);
    if (!user) return;
    
    if (!confirm(`Are you sure you want to delete user "${user.username}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        await fetchAPI(`delete_user&id=${userId}`, 'POST');
        showToast('Success', 'User deleted successfully', 'success');
        loadUsers();
    } catch (err) {
        console.error('Error deleting user:', err);
        showToast('Error', err.message || 'Failed to delete user', 'error');
    }
};
