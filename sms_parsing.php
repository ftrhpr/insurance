<?php
require_once 'session_config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user info from session
$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';

// Check admin access
if ($current_user_role !== 'admin') {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config.php';

try {
    $pdo = getDBConnection();

    // Create sms_parsing_templates table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_parsing_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            insurance_company VARCHAR(100) NOT NULL,
            template_pattern TEXT NOT NULL,
            field_mappings JSON NOT NULL,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Create sample templates if none exist
    $countStmt = $pdo->query("SELECT COUNT(*) FROM sms_parsing_templates");
    $templateCount = $countStmt->fetchColumn();
    
    if ($templateCount == 0) {
        $sampleTemplates = [
            [
                'name' => 'Aldagi Standard',
                'insurance_company' => 'Aldagi Insurance',
                'template_pattern' => 'მანქანის ნომერი: [PLATE] დამზღვევი: [NAME], [AMOUNT]',
                'field_mappings' => json_encode([
                    ['field' => 'plate', 'pattern' => 'მანქანის ნომერი:', 'description' => 'Plate number after Georgian text'],
                    ['field' => 'name', 'pattern' => 'დამზღვევი:', 'description' => 'Customer name after Georgian text'],
                    ['field' => 'amount', 'pattern' => ',', 'description' => 'Amount after comma']
                ])
            ],
            [
                'name' => 'Ardi Standard',
                'insurance_company' => 'Ardi Insurance',
                'template_pattern' => 'სახ. ნომ [PLATE] [AMOUNT]',
                'field_mappings' => json_encode([
                    ['field' => 'plate', 'pattern' => 'სახ. ნომ', 'description' => 'Plate number after Georgian abbreviation'],
                    ['field' => 'amount', 'pattern' => '', 'description' => 'Amount at the end']
                ])
            ],
            [
                'name' => 'Imedi L Standard',
                'insurance_company' => 'Imedi L Insurance',
                'template_pattern' => '[MAKE] ([PLATE]) [AMOUNT]',
                'field_mappings' => json_encode([
                    ['field' => 'plate', 'pattern' => '(', 'description' => 'Plate number in parentheses'],
                    ['field' => 'amount', 'pattern' => ')', 'description' => 'Amount after closing parenthesis']
                ])
            ]
        ];
        
        $insertStmt = $pdo->prepare("INSERT INTO sms_parsing_templates (name, insurance_company, template_pattern, field_mappings) VALUES (?, ?, ?, ?)");
        foreach ($sampleTemplates as $template) {
            $insertStmt->execute([
                $template['name'],
                $template['insurance_company'],
                $template['template_pattern'],
                $template['field_mappings']
            ]);
        }
    }

    // Fetch existing templates
    $stmt = $pdo->query("SELECT * FROM sms_parsing_templates ORDER BY insurance_company, name");
    $parsingTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];

            if ($action === 'save_template') {
                $name = trim($_POST['name']);
                $insurance_company = trim($_POST['insurance_company']);
                $template_pattern = trim($_POST['template_pattern']);

                // Build field mappings from form data
                $field_mappings = [];
                if (isset($_POST['field_name']) && isset($_POST['field_pattern'])) {
                    foreach ($_POST['field_name'] as $index => $field_name) {
                        if (!empty($field_name) && isset($_POST['field_pattern'][$index])) {
                            $field_mappings[] = [
                                'field' => $field_name,
                                'pattern' => trim($_POST['field_pattern'][$index]),
                                'description' => trim($_POST['field_description'][$index] ?? '')
                            ];
                        }
                    }
                }

                if (isset($_POST['template_id']) && !empty($_POST['template_id'])) {
                    // Update existing template
                    $stmt = $pdo->prepare("
                        UPDATE sms_parsing_templates
                        SET name = ?, insurance_company = ?, template_pattern = ?, field_mappings = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $insurance_company, $template_pattern, json_encode($field_mappings), $_POST['template_id']]);
                    $message = 'Template updated successfully!';
                } else {
                    // Create new template
                    $stmt = $pdo->prepare("
                        INSERT INTO sms_parsing_templates (name, insurance_company, template_pattern, field_mappings)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $insurance_company, $template_pattern, json_encode($field_mappings)]);
                    $message = 'Template created successfully!';
                }

            } elseif ($action === 'delete_template' && isset($_POST['template_id'])) {
                $stmt = $pdo->prepare("DELETE FROM sms_parsing_templates WHERE id = ?");
                $stmt->execute([$_POST['template_id']]);
                $message = 'Template deleted successfully!';
            }

            // Refresh templates list
            $stmt = $pdo->query("SELECT * FROM sms_parsing_templates ORDER BY insurance_company, name");
            $parsingTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

// Get template for editing
$editTemplate = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    foreach ($parsingTemplates as $template) {
        if ($template['id'] == $_GET['edit']) {
            $editTemplate = $template;
            $editTemplate['field_mappings'] = json_decode($editTemplate['field_mappings'], true);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Parsing Templates - OTOMOTORS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .template-card {
            transition: all 0.2s;
        }
        .template-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .field-mapping {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-800 mb-2">SMS Parsing Templates</h1>
        <p class="text-slate-600">Configure how SMS messages from insurance companies are parsed and mapped to database fields.</p>
        
        <!-- Info Box -->
        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start gap-3">
                <i data-lucide="info" class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0"></i>
                <div class="text-sm text-blue-800">
                    <p class="font-medium mb-1">How SMS Parsing Works:</p>
                    <ul class="list-disc list-inside space-y-1 text-blue-700">
                        <li>Each template defines how to extract data from SMS messages from a specific insurance company</li>
                        <li>Field mappings use patterns to locate data in the SMS text (e.g., "მანქანის ნომერი:" for plate numbers)</li>
                        <li>The system automatically matches incoming SMS against these templates</li>
                        <li>Sample templates for Aldagi, Ardi, and Imedi L are created automatically</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo strpos($message, 'Error') === 0 ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-8">
        <!-- Templates List -->
        <div class="lg:col-span-2">
            <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-slate-200/60 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Parsing Templates</h2>
                    <button onclick="showCreateForm()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Add Template
                    </button>
                </div>

                <?php if (empty($parsingTemplates)): ?>
                    <div class="text-center py-12">
                        <i data-lucide="file-text" class="w-16 h-16 text-slate-300 mx-auto mb-4"></i>
                        <h3 class="text-lg font-medium text-slate-600 mb-2">No parsing templates yet</h3>
                        <p class="text-slate-500 mb-4">Create your first SMS parsing template to get started.</p>
                        <button onclick="showCreateForm()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Create First Template
                        </button>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($parsingTemplates as $template): ?>
                            <div class="template-card bg-white rounded-xl border border-slate-200 p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-slate-800"><?php echo htmlspecialchars($template['name']); ?></h3>
                                        <p class="text-sm text-slate-600"><?php echo htmlspecialchars($template['insurance_company']); ?></p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="editTemplate(<?php echo $template['id']; ?>)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </button>
                                        <button onclick="deleteTemplate(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['name']); ?>')" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="text-sm text-slate-600 mb-2">
                                    <strong>Pattern:</strong> <?php echo htmlspecialchars(substr($template['template_pattern'], 0, 100)); ?><?php echo strlen($template['template_pattern']) > 100 ? '...' : ''; ?>
                                </div>
                                <div class="text-xs text-slate-500">
                                    Updated: <?php echo date('M j, Y H:i', strtotime($template['updated_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Template Form -->
        <div class="lg:col-span-1">
            <div id="template-form" class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-slate-200/60 p-6 <?php echo $editTemplate ? '' : 'hidden'; ?>">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800" id="form-title">Create Template</h2>
                    <button onclick="hideForm()" class="p-2 text-slate-400 hover:text-slate-600 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form id="parsing-template-form" method="POST">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="template_id" id="template_id" value="">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Template Name</label>
                            <input type="text" name="name" id="template_name" required
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g., Aldagi Standard">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Insurance Company</label>
                            <input type="text" name="insurance_company" id="insurance_company" required
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g., Aldagi Insurance">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Template Pattern</label>
                            <textarea name="template_pattern" id="template_pattern" required rows="3"
                                      class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Describe the SMS format pattern"></textarea>
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-sm font-medium text-slate-700">Field Mappings</label>
                                <button type="button" onclick="addFieldMapping()" class="text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    Add Field
                                </button>
                            </div>
                            <div id="field-mappings" class="space-y-2">
                                <!-- Field mappings will be added here -->
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                                Save Template
                            </button>
                            <button type="button" onclick="hideForm()" class="px-4 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
// Template management functions
let fieldMappingCount = 0;

function showCreateForm() {
    document.getElementById('form-title').textContent = 'Create Template';
    document.getElementById('template_id').value = '';
    document.getElementById('parsing-template-form').reset();
    document.getElementById('field-mappings').innerHTML = '';
    fieldMappingCount = 0;
    document.getElementById('template-form').classList.remove('hidden');
}

function hideForm() {
    document.getElementById('template-form').classList.add('hidden');
}

function editTemplate(id) {
    // Redirect to edit mode
    window.location.href = `sms_parsing.php?edit=${id}`;
}

function deleteTemplate(id, name) {
    if (confirm(`Are you sure you want to delete the template "${name}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_template">
            <input type="hidden" name="template_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function addFieldMapping(field = '', pattern = '', description = '') {
    const container = document.getElementById('field-mappings');
    const id = fieldMappingCount++;

    const fieldHtml = `
        <div class="field-mapping" id="field-${id}">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-slate-700">Field ${id + 1}</span>
                <button type="button" onclick="removeFieldMapping(${id})" class="text-red-500 hover:text-red-700">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 gap-2">
                <input type="text" name="field_name[]" value="${field}" placeholder="Field name (e.g., plate, name, amount)"
                       class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <input type="text" name="field_pattern[]" value="${pattern}" placeholder="Pattern/keyword (e.g., plate:, amount:)"
                       class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <input type="text" name="field_description[]" value="${description}" placeholder="Description (optional)"
                       class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', fieldHtml);
    lucide.createIcons();
}

function removeFieldMapping(id) {
    const element = document.getElementById(`field-${id}`);
    if (element) {
        element.remove();
    }
}

// Initialize form with existing data
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($editTemplate): ?>
        document.getElementById('form-title').textContent = 'Edit Template';
        document.getElementById('template_id').value = '<?php echo $editTemplate['id']; ?>';
        document.getElementById('template_name').value = '<?php echo htmlspecialchars($editTemplate['name']); ?>';
        document.getElementById('insurance_company').value = '<?php echo htmlspecialchars($editTemplate['insurance_company']); ?>';
        document.getElementById('template_pattern').value = '<?php echo htmlspecialchars($editTemplate['template_pattern']); ?>';

        <?php if (!empty($editTemplate['field_mappings'])): ?>
            <?php foreach ($editTemplate['field_mappings'] as $mapping): ?>
                addFieldMapping(
                    '<?php echo htmlspecialchars($mapping['field']); ?>',
                    '<?php echo htmlspecialchars($mapping['pattern']); ?>',
                    '<?php echo htmlspecialchars($mapping['description'] ?? ''); ?>'
                );
            <?php endforeach; ?>
        <?php endif; ?>

        document.getElementById('template-form').classList.remove('hidden');
    <?php endif; ?>

    // Initialize Lucide icons
    lucide.createIcons();
});
</script>

</body>
</html>