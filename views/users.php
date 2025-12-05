<?php if (isAdmin()): ?>
<!-- USERS VIEW -->
<div id="view-users" class="hidden space-y-6 animate-in fade-in duration-300">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">User Management</h2>
            <p class="text-sm text-slate-500 mt-1">Manage system users and permissions</p>
        </div>
        <button onclick="window.openCreateUserModal()" class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-indigo-700 transition-all shadow-lg hover:shadow-xl">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            Add User
        </button>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Last Login</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="users-table-body" class="divide-y divide-slate-100">
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                            <i data-lucide="loader" class="w-8 h-8 mx-auto mb-2 animate-spin"></i>
                            <p>Loading users...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Role Descriptions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 border border-purple-200">
            <div class="flex items-center gap-2 mb-2">
                <i data-lucide="shield" class="w-5 h-5 text-purple-600"></i>
                <h4 class="font-bold text-purple-900">Admin</h4>
            </div>
            <p class="text-sm text-purple-700">Full system access, can manage users and all settings</p>
        </div>
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
            <div class="flex items-center gap-2 mb-2">
                <i data-lucide="briefcase" class="w-5 h-5 text-blue-600"></i>
                <h4 class="font-bold text-blue-900">Manager</h4>
            </div>
            <p class="text-sm text-blue-700">Can edit cases, send SMS, manage appointments</p>
        </div>
        <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg p-4 border border-slate-200">
            <div class="flex items-center gap-2 mb-2">
                <i data-lucide="eye" class="w-5 h-5 text-slate-600"></i>
                <h4 class="font-bold text-slate-900">Viewer</h4>
            </div>
            <p class="text-sm text-slate-700">Read-only access to cases and reports</p>
        </div>
    </div>
</div>
<?php endif; ?>
