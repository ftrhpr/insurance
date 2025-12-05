<?php if (isAdmin()): ?>
<!-- User Management Modals -->
<!-- Create/Edit User Modal -->
<div id="user-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="window.closeUserModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 rounded-t-2xl flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-lg">
                        <i data-lucide="user-plus" class="w-5 h-5 text-white"></i>
                    </div>
                    <h3 id="user-modal-title" class="text-lg font-bold text-white">Add User</h3>
                </div>
                <button onclick="window.closeUserModal()" class="text-white/80 hover:text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <input type="hidden" id="user-id">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Username *</label>
                    <input id="user-username" type="text" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" placeholder="username">
                </div>
                <div id="password-field">
                    <label class="block text-xs font-bold text-slate-600 mb-2">Password *</label>
                    <input id="user-password" type="password" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" placeholder="Min 6 characters">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Full Name *</label>
                    <input id="user-fullname" type="text" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" placeholder="Full Name">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Email</label>
                    <input id="user-email" type="email" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" placeholder="user@example.com">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Role *</label>
                    <select id="user-role" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                        <option value="viewer">Viewer (Read-only)</option>
                        <option value="manager" selected>Manager (Edit cases)</option>
                        <option value="admin">Admin (Full access)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Status *</label>
                    <select id="user-status" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-3 justify-end px-6 pb-6">
                <button onclick="window.closeUserModal()" class="px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">Cancel</button>
                <button onclick="window.saveUser()" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 rounded-lg font-semibold shadow-lg transition-all">
                    <span id="user-save-btn-text">Create User</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div id="password-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="window.closePasswordModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md border border-slate-200">
            <div class="bg-gradient-to-r from-slate-700 to-slate-900 px-6 py-4 rounded-t-2xl flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-lg">
                        <i data-lucide="lock" class="w-5 h-5 text-white"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white">Change Password</h3>
                </div>
                <button onclick="window.closePasswordModal()" class="text-white/80 hover:text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <input type="hidden" id="pwd-user-id">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">New Password</label>
                    <input id="pwd-new-password" type="password" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 outline-none" placeholder="Min 6 characters">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2">Confirm Password</label>
                    <input id="pwd-confirm-password" type="password" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 outline-none" placeholder="Re-enter password">
                </div>
            </div>

            <div class="flex gap-3 justify-end px-6 pb-6">
                <button onclick="window.closePasswordModal()" class="px-4 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">Cancel</button>
                <button onclick="window.savePassword()" class="px-6 py-2.5 bg-slate-900 text-white hover:bg-slate-800 rounded-lg font-semibold shadow-lg transition-all">
                    Update Password
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
