<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user info from session
$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';

// Database connection
require_once 'config.php';

$message = '';
$messageType = '';

try {
    $pdo = getDBConnection();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $stmt = $pdo->prepare("INSERT INTO bank_templates (name, regex_pattern, field_order, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['regex_pattern'],
                        $_POST['field_order'],
                        $_POST['description']
                    ]);
                    $message = 'Template added successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'update':
                    $stmt = $pdo->prepare("UPDATE bank_templates SET name=?, regex_pattern=?, field_order=?, description=?, active=? WHERE id=?");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['regex_pattern'],
                        $_POST['field_order'],
                        $_POST['description'],
                        isset($_POST['active']) ? 1 : 0,
                        $_POST['id']
                    ]);
                    $message = 'Template updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM bank_templates WHERE id=?");
                    $stmt->execute([$_POST['id']]);
                    $message = 'Template deleted successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'toggle':
                    $stmt = $pdo->prepare("UPDATE bank_templates SET active = 1 - active WHERE id=?");
                    $stmt->execute([$_POST['id']]);
                    $message = 'Template status updated!';
                    $messageType = 'success';
                    break;
            }
        }
    }
    
    // Fetch all templates
    $stmt = $pdo->query("SELECT * FROM bank_templates ORDER BY active DESC, name ASC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $message = 'Database error: ' . $e->getMessage();
    $messageType = 'error';
    $templates = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Statement Templates - OTOMOTORS Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-4">
                    <button onclick="window.location.href='index.php'" class="flex items-center gap-2 text-slate-600 hover:text-slate-800 transition-colors">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        Back to Dashboard
                    </button>
                    <div class="h-6 w-px bg-slate-300"></div>
                    <h1 class="text-xl font-bold text-slate-800">Bank Statement Templates</h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-600">Welcome, <?php echo htmlspecialchars($current_user_name); ?></span>
                    <a href="logout.php" class="text-sm text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Message -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Add New Template Button -->
        <div class="mb-6">
            <button onclick="openModal('add')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Add New Template
            </button>
        </div>

        <!-- Templates Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Regex Pattern</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Field Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($templates as $template): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                <?php echo htmlspecialchars($template['name']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 font-mono text-xs max-w-xs truncate" title="<?php echo htmlspecialchars($template['regex_pattern']); ?>">
                                <?php echo htmlspecialchars($template['regex_pattern']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                <?php echo htmlspecialchars($template['field_order']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 max-w-xs truncate" title="<?php echo htmlspecialchars($template['description']); ?>">
                                <?php echo htmlspecialchars($template['description']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $template['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $template['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <button onclick="openModal('edit', <?php echo $template['id']; ?>)" class="text-blue-600 hover:text-blue-900">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="toggleStatus(<?php echo $template['id']; ?>)" class="text-yellow-600 hover:text-yellow-900">
                                        <i data-lucide="power" class="w-4 h-4"></i>
                                    </button>
                                    <button onclick="deleteTemplate(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['name']); ?>')" class="text-red-600 hover:text-red-900">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 id="modal-title" class="text-xl font-bold text-slate-800">Add Template</h2>
                        <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">
                            <i data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>
                    
                    <form id="template-form" method="POST">
                        <input type="hidden" name="action" id="form-action" value="add">
                        <input type="hidden" name="id" id="template-id">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Template Name</label>
                                <input type="text" name="name" id="template-name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Regex Pattern</label>
                                <textarea name="regex_pattern" id="template-regex" required rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm" placeholder="/pattern/flags"></textarea>
                                <p class="text-xs text-slate-500 mt-1">Use capture groups () for plate, name, amount, franchise. Example: /pattern/i</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Field Order</label>
                                <input type="text" name="field_order" id="template-order" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="plate,name,amount,franchise">
                                <p class="text-xs text-slate-500 mt-1">Comma-separated list of fields in regex capture group order</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                                <textarea name="description" id="template-description" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Brief description of this template"></textarea>
                            </div>
                            
                            <div id="active-container" class="hidden">
                                <label class="flex items-center">
                                    <input type="checkbox" name="active" id="template-active" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-slate-700">Active</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" onclick="closeModal()" class="px-4 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save Template</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentTemplate = null;

        function openModal(action, id = null) {
            document.getElementById('modal').classList.remove('hidden');
            document.getElementById('form-action').value = action;
            document.getElementById('modal-title').textContent = action === 'add' ? 'Add Template' : 'Edit Template';
            
            if (action === 'edit' && id) {
                // Load template data (simplified - in real app, fetch from server)
                fetchTemplateData(id);
            } else {
                document.getElementById('template-form').reset();
                document.getElementById('template-id').value = '';
                document.getElementById('active-container').classList.add('hidden');
            }
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        function fetchTemplateData(id) {
            // Find template in current data (simplified)
            const templates = <?php echo json_encode($templates); ?>;
            const template = templates.find(t => t.id == id);
            if (template) {
                document.getElementById('template-id').value = template.id;
                document.getElementById('template-name').value = template.name;
                document.getElementById('template-regex').value = template.regex_pattern;
                document.getElementById('template-order').value = template.field_order;
                document.getElementById('template-description').value = template.description;
                document.getElementById('template-active').checked = template.active == 1;
                document.getElementById('active-container').classList.remove('hidden');
            }
        }

        function toggleStatus(id) {
            if (confirm('Toggle template status?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteTemplate(id, name) {
            if (confirm(`Delete template "${name}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>