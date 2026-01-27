<?php
session_start();
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$current_user_name = $_SESSION['full_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_language(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('settings.statuses.title', 'Status Management'); ?> - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.378.0/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <?php if (file_exists(__DIR__ . '/fonts/include_fonts.php')) include __DIR__ . '/fonts/include_fonts.php'; ?>
    <style>
        .sortable-ghost { opacity: 0.4; background: #c8ebfb; }
        .sortable-chosen { cursor: grabbing; }
    </style>
</head>
<body class="bg-slate-100">
    <div id="toast-container" class="fixed top-6 right-6 z-[100] space-y-3"></div>

    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 ml-64 py-10 px-8" x-data="statusManager()">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800"><?php echo __('settings.statuses.title', 'Status Management'); ?></h1>
                        <p class="text-slate-600 mt-1"><?php echo __('settings.statuses.description', 'Manage case statuses and repair statuses'); ?></p>
                    </div>
                    <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mb-6">
                <div class="flex gap-2 border-b border-slate-200">
                    <button @click="activeTab = 'case'" 
                            :class="activeTab === 'case' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                            class="px-6 py-3 rounded-t-lg font-medium transition-colors">
                        <i data-lucide="layers" class="w-4 h-4 inline mr-2"></i>
                        Case Statuses
                    </button>
                    <button @click="activeTab = 'repair'" 
                            :class="activeTab === 'repair' ? 'bg-amber-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'"
                            class="px-6 py-3 rounded-t-lg font-medium transition-colors">
                        <i data-lucide="wrench" class="w-4 h-4 inline mr-2"></i>
                        Repair Statuses
                    </button>
                </div>
            </div>

            <!-- Case Statuses Tab -->
            <div x-show="activeTab === 'case'" x-transition>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Case Workflow Statuses</h2>
                            <p class="text-sm text-slate-600 mt-1">Define the stages a case goes through from creation to completion</p>
                        </div>
                        <button @click="openModal('case', null)" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Add Status
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <div id="case-statuses-list" class="space-y-3">
                            <template x-for="status in caseStatuses" :key="status.id">
                                <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors cursor-move" :data-id="status.id">
                                    <div class="cursor-grab text-slate-400 hover:text-slate-600">
                                        <i data-lucide="grip-vertical" class="w-5 h-5"></i>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <span :class="`inline-flex items-center justify-center w-10 h-10 rounded-lg bg-${status.color}-100 text-${status.color}-600`">
                                            <i :data-lucide="status.icon" class="w-5 h-5"></i>
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-slate-800" x-text="status.name"></span>
                                            <span x-show="status.is_default" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">Default</span>
                                            <span x-show="status.is_final" class="px-2 py-0.5 bg-purple-100 text-purple-700 text-xs rounded-full">Final</span>
                                            <span x-show="!status.is_active" class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full">Inactive</span>
                                        </div>
                                        <div class="text-sm text-slate-500 mt-0.5">
                                            <span x-text="status.name_ka" class="mr-3"></span>
                                            <span class="text-slate-400">|</span>
                                            <span x-text="status.name_en" class="ml-3"></span>
                                        </div>
                                        <div class="text-xs text-slate-400 mt-1">
                                            Slug: <code class="bg-slate-200 px-1 rounded" x-text="status.slug"></code>
                                            <span x-show="status.triggers_sms" class="ml-3">
                                                SMS: <code class="bg-amber-100 text-amber-700 px-1 rounded" x-text="status.triggers_sms"></code>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button @click="openModal('case', status)" class="p-2 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </button>
                                        <button @click="deleteStatus('case', status.id)" class="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="caseStatuses.length === 0" class="text-center py-12 text-slate-500">
                            <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                            <p>No case statuses found. Add one to get started.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Repair Statuses Tab -->
            <div x-show="activeTab === 'repair'" x-transition>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Repair Statuses</h2>
                            <p class="text-sm text-slate-600 mt-1">Define the repair workflow stages for tracking repair progress</p>
                        </div>
                        <button @click="openModal('repair', null)" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Add Status
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <div id="repair-statuses-list" class="space-y-3">
                            <template x-for="status in repairStatuses" :key="status.id">
                                <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-lg border border-slate-200 hover:border-slate-300 transition-colors cursor-move" :data-id="status.id">
                                    <div class="cursor-grab text-slate-400 hover:text-slate-600">
                                        <i data-lucide="grip-vertical" class="w-5 h-5"></i>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <span :class="`inline-flex items-center justify-center w-10 h-10 rounded-lg bg-${status.color}-100 text-${status.color}-600`">
                                            <i :data-lucide="status.icon" class="w-5 h-5"></i>
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-slate-800" x-text="status.name"></span>
                                            <span x-show="status.is_default" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">Default</span>
                                            <span x-show="!status.is_active" class="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full">Inactive</span>
                                        </div>
                                        <div class="text-sm text-slate-500 mt-0.5">
                                            <span x-text="status.name_ka" class="mr-3"></span>
                                            <span class="text-slate-400">|</span>
                                            <span x-text="status.name_en" class="ml-3"></span>
                                        </div>
                                        <div class="text-xs text-slate-400 mt-1">
                                            Slug: <code class="bg-slate-200 px-1 rounded" x-text="status.slug"></code>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button @click="openModal('repair', status)" class="p-2 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors">
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </button>
                                        <button @click="deleteStatus('repair', status.id)" class="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div x-show="repairStatuses.length === 0" class="text-center py-12 text-slate-500">
                            <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                            <p>No repair statuses found. Add one to get started.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Modal -->
            <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="closeModal()">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="p-6 border-b border-slate-200">
                        <h3 class="text-xl font-bold text-slate-800" x-text="editingStatus ? 'Edit Status' : 'Add New Status'"></h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Name (Primary)</label>
                                <input x-model="formData.name" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Status name">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
                                <input x-model="formData.slug" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="auto-generated">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Name (Georgian)</label>
                                <input x-model="formData.name_ka" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="ქართული სახელი">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Name (English)</label>
                                <input x-model="formData.name_en" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="English name">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Color</label>
                                <select x-model="formData.color" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="slate">Slate</option>
                                    <option value="gray">Gray</option>
                                    <option value="red">Red</option>
                                    <option value="orange">Orange</option>
                                    <option value="amber">Amber</option>
                                    <option value="yellow">Yellow</option>
                                    <option value="lime">Lime</option>
                                    <option value="green">Green</option>
                                    <option value="emerald">Emerald</option>
                                    <option value="teal">Teal</option>
                                    <option value="cyan">Cyan</option>
                                    <option value="sky">Sky</option>
                                    <option value="blue">Blue</option>
                                    <option value="indigo">Indigo</option>
                                    <option value="violet">Violet</option>
                                    <option value="purple">Purple</option>
                                    <option value="fuchsia">Fuchsia</option>
                                    <option value="pink">Pink</option>
                                    <option value="rose">Rose</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Icon (Lucide)</label>
                                <input x-model="formData.icon" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="circle">
                                <p class="text-xs text-slate-500 mt-1">
                                    <a href="https://lucide.dev/icons/" target="_blank" class="text-blue-600 hover:underline">Browse icons →</a>
                                </p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Sort Order</label>
                                <input x-model="formData.sort_order" type="number" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="0">
                            </div>
                            <div x-show="modalType === 'case'">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Triggers SMS Template</label>
                                <input x-model="formData.triggers_sms" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="template_slug">
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-4 pt-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input x-model="formData.is_active" type="checkbox" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                                <span class="text-sm text-slate-700">Active</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input x-model="formData.is_default" type="checkbox" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                                <span class="text-sm text-slate-700">Default for new cases</span>
                            </label>
                            <label x-show="modalType === 'case'" class="inline-flex items-center gap-2 cursor-pointer">
                                <input x-model="formData.is_final" type="checkbox" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                                <span class="text-sm text-slate-700">Final status</span>
                            </label>
                        </div>
                    </div>
                    <div class="p-6 border-t border-slate-200 flex justify-end gap-3">
                        <button @click="closeModal()" class="px-4 py-2 text-slate-700 hover:bg-slate-100 rounded-lg transition-colors">Cancel</button>
                        <button @click="saveStatus()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                            <span x-text="editingStatus ? 'Update' : 'Create'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function statusManager() {
            return {
                activeTab: 'case',
                caseStatuses: [],
                repairStatuses: [],
                showModal: false,
                modalType: 'case',
                editingStatus: null,
                formData: {
                    name: '',
                    name_ka: '',
                    name_en: '',
                    slug: '',
                    color: 'slate',
                    icon: 'circle',
                    sort_order: 0,
                    is_active: true,
                    is_default: false,
                    is_final: false,
                    triggers_sms: ''
                },

                async init() {
                    await this.loadStatuses();
                    this.$nextTick(() => {
                        lucide.createIcons();
                        this.initSortable();
                    });
                },

                async loadStatuses() {
                    try {
                        const [caseRes, repairRes] = await Promise.all([
                            fetch('api.php?action=get_case_statuses'),
                            fetch('api.php?action=get_repair_statuses')
                        ]);
                        
                        const caseData = await caseRes.json();
                        const repairData = await repairRes.json();
                        
                        if (caseData.success) this.caseStatuses = caseData.statuses;
                        if (repairData.success) this.repairStatuses = repairData.statuses;
                        
                        this.$nextTick(() => lucide.createIcons());
                    } catch (e) {
                        console.error('Failed to load statuses:', e);
                        showToast('Error', 'Failed to load statuses', 'error');
                    }
                },

                initSortable() {
                    const caseList = document.getElementById('case-statuses-list');
                    const repairList = document.getElementById('repair-statuses-list');
                    
                    if (caseList) {
                        new Sortable(caseList, {
                            animation: 150,
                            ghostClass: 'sortable-ghost',
                            chosenClass: 'sortable-chosen',
                            handle: '.cursor-grab',
                            onEnd: (evt) => this.handleReorder('case', evt)
                        });
                    }
                    
                    if (repairList) {
                        new Sortable(repairList, {
                            animation: 150,
                            ghostClass: 'sortable-ghost',
                            chosenClass: 'sortable-chosen',
                            handle: '.cursor-grab',
                            onEnd: (evt) => this.handleReorder('repair', evt)
                        });
                    }
                },

                async handleReorder(type, evt) {
                    const list = type === 'case' ? this.caseStatuses : this.repairStatuses;
                    const items = evt.to.querySelectorAll('[data-id]');
                    const order = Array.from(items).map((el, idx) => ({
                        id: parseInt(el.dataset.id),
                        sort_order: idx
                    }));
                    
                    try {
                        const res = await fetch('api.php?action=reorder_statuses', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ type, order })
                        });
                        const data = await res.json();
                        if (data.success) {
                            showToast('Saved', 'Order updated', 'success');
                            await this.loadStatuses();
                        }
                    } catch (e) {
                        console.error('Reorder failed:', e);
                    }
                },

                openModal(type, status) {
                    this.modalType = type;
                    this.editingStatus = status;
                    
                    if (status) {
                        this.formData = {
                            name: status.name || '',
                            name_ka: status.name_ka || '',
                            name_en: status.name_en || '',
                            slug: status.slug || '',
                            color: status.color || 'slate',
                            icon: status.icon || 'circle',
                            sort_order: status.sort_order || 0,
                            is_active: Boolean(parseInt(status.is_active)),
                            is_default: Boolean(parseInt(status.is_default)),
                            is_final: Boolean(parseInt(status.is_final || 0)),
                            triggers_sms: status.triggers_sms || ''
                        };
                    } else {
                        this.formData = {
                            name: '',
                            name_ka: '',
                            name_en: '',
                            slug: '',
                            color: type === 'case' ? 'blue' : 'amber',
                            icon: 'circle',
                            sort_order: type === 'case' ? this.caseStatuses.length : this.repairStatuses.length,
                            is_active: true,
                            is_default: false,
                            is_final: false,
                            triggers_sms: ''
                        };
                    }
                    
                    this.showModal = true;
                },

                closeModal() {
                    this.showModal = false;
                    this.editingStatus = null;
                },

                async saveStatus() {
                    if (!this.formData.name.trim()) {
                        showToast('Error', 'Status name is required', 'error');
                        return;
                    }
                    
                    const action = this.editingStatus 
                        ? `update_${this.modalType}_status&id=${this.editingStatus.id}`
                        : `create_${this.modalType}_status`;
                    
                    try {
                        const res = await fetch(`api.php?action=${action}`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                ...this.formData,
                                is_active: this.formData.is_active ? 1 : 0,
                                is_default: this.formData.is_default ? 1 : 0,
                                is_final: this.formData.is_final ? 1 : 0
                            })
                        });
                        
                        const data = await res.json();
                        
                        if (data.success) {
                            showToast('Success', this.editingStatus ? 'Status updated' : 'Status created', 'success');
                            this.closeModal();
                            await this.loadStatuses();
                            this.$nextTick(() => lucide.createIcons());
                        } else {
                            showToast('Error', data.error || 'Failed to save status', 'error');
                        }
                    } catch (e) {
                        console.error('Save failed:', e);
                        showToast('Error', 'Failed to save status', 'error');
                    }
                },

                async deleteStatus(type, id) {
                    if (!confirm('Are you sure you want to delete this status?')) return;
                    
                    try {
                        const res = await fetch(`api.php?action=delete_${type}_status&id=${id}`, {
                            method: 'POST'
                        });
                        const data = await res.json();
                        
                        if (data.success) {
                            showToast('Deleted', 'Status deleted', 'success');
                            await this.loadStatuses();
                        } else {
                            showToast('Error', data.error || 'Failed to delete status', 'error');
                        }
                    } catch (e) {
                        console.error('Delete failed:', e);
                        showToast('Error', 'Failed to delete status', 'error');
                    }
                }
            };
        }

        function showToast(title, message, type = 'info') {
            const container = document.getElementById('toast-container');
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                info: 'bg-blue-500'
            };
            
            const toast = document.createElement('div');
            toast.className = `${colors[type] || colors.info} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-x-full`;
            toast.innerHTML = `
                <div>
                    <div class="font-medium">${title}</div>
                    <div class="text-sm opacity-90">${message}</div>
                </div>
            `;
            
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-full'));
            
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
