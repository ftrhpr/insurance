<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Only allow admins
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'];

try {
    $pdo = getDBConnection();
    
    // Load statuses
    $stmt = $pdo->query("SELECT * FROM `statuses` ORDER BY `type`, `sort_order` ASC");
    $allStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate by type (use array_values to reindex)
    $caseStatuses = array_values(array_filter($allStatuses, fn($s) => $s['type'] === 'case'));
    $repairStatuses = array_values(array_filter($allStatuses, fn($s) => $s['type'] === 'repair'));
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $caseStatuses = [];
    $repairStatuses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Management - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .status-item.sortable-ghost { opacity: 0.4; }
        .status-item.sortable-chosen { background: #EFF6FF; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    
    <?php include 'sidebar.php'; ?>
    
    <main class="ml-64 p-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Status Management</h1>
                <p class="text-slate-500 mt-1">Manage case and repair statuses</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Case Statuses -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i data-lucide="folder" class="w-5 h-5"></i>
                        Case Statuses
                    </h2>
                    <button onclick="openAddModal('case')" class="px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white text-sm rounded-lg transition flex items-center gap-1">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Add
                    </button>
                </div>
                <div id="case-statuses-list" class="p-4 space-y-2">
                    <?php foreach ($caseStatuses as $status): ?>
                    <div class="status-item flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200 hover:border-blue-300 transition cursor-move" data-id="<?= $status['id'] ?>" data-name="<?= htmlspecialchars($status['name'], ENT_QUOTES) ?>">
                        <i data-lucide="grip-vertical" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                        <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: <?= htmlspecialchars($status['color']) ?>"></div>
                        <span class="flex-1 font-medium text-slate-800"><?= htmlspecialchars($status['name']) ?></span>
                        <span class="text-xs px-2 py-1 rounded" style="background-color: <?= htmlspecialchars($status['bg_color']) ?>; color: <?= htmlspecialchars($status['color']) ?>">
                            <?= htmlspecialchars($status['name']) ?>
                        </span>
                        <div class="flex items-center gap-1">
                            <button onclick="toggleStatus(<?= $status['id'] ?>)" class="p-1.5 rounded hover:bg-slate-200 transition" title="<?= $status['is_active'] ? 'Active' : 'Inactive' ?>">
                                <i data-lucide="<?= $status['is_active'] ? 'eye' : 'eye-off' ?>" class="w-4 h-4 <?= $status['is_active'] ? 'text-green-600' : 'text-slate-400' ?>"></i>
                            </button>
                            <button onclick="openEditModal(<?= $status['id'] ?>)" class="p-1.5 rounded hover:bg-slate-200 transition">
                                <i data-lucide="pencil" class="w-4 h-4 text-slate-600"></i>
                            </button>
                            <button onclick="deleteStatus(<?= $status['id'] ?>)" class="p-1.5 rounded hover:bg-red-100 transition">
                                <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($caseStatuses)): ?>
                    <div class="text-center py-8 text-slate-400">
                        <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2"></i>
                        <p>No case statuses found</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Repair Statuses -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i data-lucide="wrench" class="w-5 h-5"></i>
                        Repair Statuses
                    </h2>
                    <button onclick="openAddModal('repair')" class="px-3 py-1.5 bg-white/20 hover:bg-white/30 text-white text-sm rounded-lg transition flex items-center gap-1">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Add
                    </button>
                </div>
                <div id="repair-statuses-list" class="p-4 space-y-2">
                    <?php foreach ($repairStatuses as $status): ?>
                    <div class="status-item flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200 hover:border-orange-300 transition cursor-move" data-id="<?= $status['id'] ?>" data-name="<?= htmlspecialchars($status['name'], ENT_QUOTES) ?>">
                        <i data-lucide="grip-vertical" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
                        <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: <?= htmlspecialchars($status['color']) ?>"></div>
                        <span class="flex-1 font-medium text-slate-800"><?= htmlspecialchars($status['name']) ?></span>
                        <span class="text-xs px-2 py-1 rounded" style="background-color: <?= htmlspecialchars($status['bg_color']) ?>; color: <?= htmlspecialchars($status['color']) ?>">
                            <?= htmlspecialchars($status['name']) ?>
                        </span>
                        <div class="flex items-center gap-1">
                            <button onclick="toggleStatus(<?= $status['id'] ?>)" class="p-1.5 rounded hover:bg-slate-200 transition" title="<?= $status['is_active'] ? 'Active' : 'Inactive' ?>">
                                <i data-lucide="<?= $status['is_active'] ? 'eye' : 'eye-off' ?>" class="w-4 h-4 <?= $status['is_active'] ? 'text-green-600' : 'text-slate-400' ?>"></i>
                            </button>
                            <button onclick="openEditModal(<?= $status['id'] ?>)" class="p-1.5 rounded hover:bg-slate-200 transition">
                                <i data-lucide="pencil" class="w-4 h-4 text-slate-600"></i>
                            </button>
                            <button onclick="deleteStatus(<?= $status['id'] ?>)" class="p-1.5 rounded hover:bg-red-100 transition">
                                <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($repairStatuses)): ?>
                    <div class="text-center py-8 text-slate-400">
                        <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-2"></i>
                        <p>No repair statuses found</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 id="modalTitle" class="text-lg font-bold text-slate-800">Add Status</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <form id="statusForm" onsubmit="saveStatus(event)" class="p-6 space-y-4">
                <input type="hidden" id="statusId" value="">
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                    <select id="statusType" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="case">Case Status</option>
                        <option value="repair">Repair Status</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                    <input type="text" id="statusName" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter status name">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Text Color</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="statusColor" value="#3B82F6" class="w-10 h-10 rounded cursor-pointer border border-slate-300">
                            <input type="text" id="statusColorText" value="#3B82F6" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono" onchange="document.getElementById('statusColor').value = this.value">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Background Color</label>
                        <div class="flex items-center gap-2">
                            <input type="color" id="statusBgColor" value="#DBEAFE" class="w-10 h-10 rounded cursor-pointer border border-slate-300">
                            <input type="text" id="statusBgColorText" value="#DBEAFE" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono" onchange="document.getElementById('statusBgColor').value = this.value">
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Preview</label>
                    <div class="flex items-center gap-2">
                        <span id="statusPreview" class="px-3 py-1.5 rounded-full text-sm font-medium" style="background-color: #DBEAFE; color: #3B82F6">
                            Status Name
                        </span>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="statusActive" checked class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <label for="statusActive" class="text-sm text-slate-700">Active</label>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Initialize Sortable for both lists (with null check)
        const caseList = document.getElementById('case-statuses-list');
        const repairList = document.getElementById('repair-statuses-list');
        
        if (caseList && caseList.querySelectorAll('.status-item').length > 0) {
            new Sortable(caseList, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                handle: '.status-item',
                onEnd: function(evt) {
                    saveOrder('case');
                }
            });
        }
        
        if (repairList && repairList.querySelectorAll('.status-item').length > 0) {
            new Sortable(repairList, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                handle: '.status-item',
                onEnd: function(evt) {
                    saveOrder('repair');
                }
            });
        }
        
        // Color picker sync
        document.getElementById('statusColor').addEventListener('input', function(e) {
            document.getElementById('statusColorText').value = e.target.value;
            updatePreview();
        });
        document.getElementById('statusBgColor').addEventListener('input', function(e) {
            document.getElementById('statusBgColorText').value = e.target.value;
            updatePreview();
        });
        document.getElementById('statusName').addEventListener('input', updatePreview);
        
        function updatePreview() {
            const preview = document.getElementById('statusPreview');
            const name = document.getElementById('statusName').value || 'Status Name';
            const color = document.getElementById('statusColor').value;
            const bgColor = document.getElementById('statusBgColor').value;
            preview.textContent = name;
            preview.style.color = color;
            preview.style.backgroundColor = bgColor;
        }
        
        function openAddModal(type) {
            document.getElementById('modalTitle').textContent = 'Add Status';
            document.getElementById('statusId').value = '';
            document.getElementById('statusType').value = type;
            document.getElementById('statusName').value = '';
            document.getElementById('statusColor').value = '#3B82F6';
            document.getElementById('statusColorText').value = '#3B82F6';
            document.getElementById('statusBgColor').value = '#DBEAFE';
            document.getElementById('statusBgColorText').value = '#DBEAFE';
            document.getElementById('statusActive').checked = true;
            updatePreview();
            document.getElementById('statusModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        async function openEditModal(id) {
            try {
                const response = await fetch(`api.php?action=get_status&id=${id}`);
                const result = await response.json();
                
                if (result.status === 'success') {
                    const s = result.data;
                    document.getElementById('modalTitle').textContent = 'Edit Status';
                    document.getElementById('statusId').value = s.id;
                    document.getElementById('statusType').value = s.type;
                    document.getElementById('statusName').value = s.name;
                    document.getElementById('statusColor').value = s.color;
                    document.getElementById('statusColorText').value = s.color;
                    document.getElementById('statusBgColor').value = s.bg_color;
                    document.getElementById('statusBgColorText').value = s.bg_color;
                    document.getElementById('statusActive').checked = s.is_active == 1;
                    updatePreview();
                    document.getElementById('statusModal').classList.remove('hidden');
                    lucide.createIcons();
                } else {
                    alert('Error loading status: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load status');
            }
        }
        
        function closeModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }
        
        async function saveStatus(event) {
            event.preventDefault();
            
            const data = {
                id: document.getElementById('statusId').value || 0,
                type: document.getElementById('statusType').value,
                name: document.getElementById('statusName').value,
                color: document.getElementById('statusColor').value,
                bg_color: document.getElementById('statusBgColor').value,
                is_active: document.getElementById('statusActive').checked ? 1 : 0
            };
            
            try {
                const response = await fetch('api.php?action=save_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                if (result.status === 'success') {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to save status');
            }
        }
        
        async function deleteStatus(id) {
            // Get the name from the data attribute on the status item
            const statusItem = document.querySelector(`.status-item[data-id="${id}"]`);
            const name = statusItem ? statusItem.dataset.name : 'this status';
            
            if (!confirm(`Are you sure you want to delete "${name}"?`)) return;
            
            try {
                const response = await fetch('api.php?action=delete_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const result = await response.json();
                
                if (result.status === 'success') {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to delete status');
            }
        }
        
        async function toggleStatus(id) {
            try {
                const response = await fetch('api.php?action=toggle_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const result = await response.json();
                
                if (result.status === 'success') {
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to toggle status');
            }
        }
        
        async function saveOrder(type) {
            const list = document.getElementById(type + '-statuses-list');
            const items = list.querySelectorAll('.status-item');
            const orders = [];
            
            items.forEach((item, index) => {
                orders.push({
                    id: parseInt(item.dataset.id),
                    sort_order: index + 1
                });
            });
            
            try {
                const response = await fetch('api.php?action=reorder_statuses', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ orders: orders })
                });
                const result = await response.json();
                
                if (result.status !== 'success') {
                    console.error('Failed to save order:', result.message);
                }
            } catch (error) {
                console.error('Error saving order:', error);
            }
        }
    </script>
</body>
</html>
