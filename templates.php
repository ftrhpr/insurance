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

// Default templates
$defaultTemplatesData = [
    'registered' => 'გამარჯობა {name}, თქვენი სერვისის რეგისტრაცია მოხდა. ავტომობილი: {plate}. თანხა: {amount}₾',
    'called' => 'გამარჯობა {name}, დაგიკავშირდით ჩვენი მენეჯერი. ავტომობილი: {plate}',
    'contacted' => 'გამარჯობა {name}, თქვენ დაგიკავშირდით. ავტომობილი: {plate}. მალე მოგაწვდით დეტალურ ინფორმაციას.',
    'schedule' => 'გამარჯობა {name}, თქვენი სერვისის თარიღი: {date}. ავტომობილი: {plate}. დაადასტურეთ ან გადაავადეთ: {link}',
    'parts_ordered' => 'გამარჯობა {name}, თქვენი ნაწილები შეკვეთილია. ავტომობილი: {plate}',
    'parts_arrived' => 'გამარჯობა {name}, თქვენი ნაწილები მივიდა. დაადასტურეთ თქვენი ვიზიტი: {link}',
    'rescheduled' => 'გამარჯობა, კლიენტმა {name} მოითხოვა თარიღის შეცვლა. ავტომობილი: {plate}',
    'reschedule_accepted' => 'გამარჯობა {name}, თქვენი თარიღის შეცვლის მოთხოვნა მიღებულია. ახალი თარიღი: {date}',
    'completed' => 'გამარჯობა {name}, თქვენი სერვისი დასრულდა. გთხოვთ შეაფასოთ ჩვენი მომსახურება',
    'issue' => 'გამარჯობა {name}, დაფიქსირდა პრობლემა. ავტომობილი: {plate}. ჩვენ დაგიკავშირდებით.',
    'system' => 'სისტემური შეტყობინება: {count} ახალი განაცხადი დაემატა OTOMOTORS პორტალში.'
];

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch SMS templates with workflow bindings
    $stmt = $pdo->query("SELECT slug, content, workflow_stages, is_active FROM sms_templates ORDER BY slug");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch workflow stages
    $stmt = $pdo->query("SELECT stage_name, description FROM workflow_stages WHERE is_active = 1 ORDER BY stage_order");
    $workflowStages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $templatesData = [];
    $workflowBindings = [];
    foreach ($templates as $tpl) {
        $templatesData[$tpl['slug']] = [
            'content' => $tpl['content'],
            'workflow_stages' => json_decode($tpl['workflow_stages'] ?? '[]', true),
            'is_active' => $tpl['is_active']
        ];
        $workflowBindings[$tpl['slug']] = $templatesData[$tpl['slug']]['workflow_stages'];
    }
    
} catch (PDOException $e) {
    $templatesData = [];
    $workflowStages = [];
    $workflowBindings = [];
    error_log("Database error in templates.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Templates - OTOMOTORS</title>
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
    <div class="flex">
        <?php include 'sidebar.php'; ?>
        <main class="flex-1 ml-64 p-8">

    <!-- Main Content -->
    <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-slate-200/60 p-8">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Templates Section -->
            <div class="lg:col-span-2 space-y-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-slate-800">Manage SMS Templates</h2>
                    <?php if ($current_user_role === 'admin' || $current_user_role === 'manager' || $current_user_role === 'viewer'): ?>
                    <button onclick="window.saveAllTemplates()" class="px-6 py-3 gradient-primary text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save All Templates
                    </button>
                    <?php else: ?>
                    <div class="text-sm text-slate-500 italic">View only - editing disabled</div>
                    <?php endif; ?>
                </div>

                <!-- Template Cards -->
                <div class="space-y-4" id="templates-container">
                    <?php
                    // Define template metadata for icons and colors
                    $templateMeta = [
                        'registered' => ['icon' => 'user-check', 'color' => 'emerald', 'label' => 'Welcome SMS'],
                        'called' => ['icon' => 'phone-call', 'color' => 'blue', 'label' => 'Customer Contacted'],
                        'contacted' => ['icon' => 'phone', 'color' => 'cyan', 'label' => 'Contacted Notification'],
                        'schedule' => ['icon' => 'calendar-check', 'color' => 'indigo', 'label' => 'Service Scheduled'],
                        'parts_ordered' => ['icon' => 'package', 'color' => 'amber', 'label' => 'Parts Ordered'],
                        'parts_arrived' => ['icon' => 'box', 'color' => 'purple', 'label' => 'Parts Arrived'],
                        'rescheduled' => ['icon' => 'clock', 'color' => 'orange', 'label' => 'Reschedule Request'],
                        'reschedule_accepted' => ['icon' => 'calendar-check', 'color' => 'cyan', 'label' => 'Reschedule Accepted'],
                        'completed' => ['icon' => 'check-circle', 'color' => 'green', 'label' => 'Service Completed'],
                        'issue' => ['icon' => 'alert-circle', 'color' => 'red', 'label' => 'Issue Reported'],
                        'system' => ['icon' => 'bell', 'color' => 'gray', 'label' => 'System Alert']
                    ];

                    // Sort templates by a predefined order
                    $templateOrder = ['registered', 'called', 'contacted', 'schedule', 'parts_ordered', 'parts_arrived', 'rescheduled', 'reschedule_accepted', 'completed', 'issue', 'system'];
                    $sortedTemplates = [];
                    foreach ($templateOrder as $slug) {
                        if (isset($templatesData[$slug])) {
                            $sortedTemplates[$slug] = $templatesData[$slug];
                        }
                    }
                    // Add any additional templates not in the predefined order
                    foreach ($templatesData as $slug => $data) {
                        if (!isset($sortedTemplates[$slug])) {
                            $sortedTemplates[$slug] = $data;
                        }
                    }

                    foreach ($sortedTemplates as $slug => $template):
                        $meta = $templateMeta[$slug] ?? ['icon' => 'message-square', 'color' => 'slate', 'label' => ucfirst(str_replace('_', ' ', $slug))];
                        $isEditable = ($current_user_role === 'admin' || $current_user_role === 'manager' || $current_user_role === 'viewer');
                    ?>
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow template-card" data-slug="<?php echo $slug; ?>">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-<?php echo $meta['color']; ?>-100 flex items-center justify-center">
                                    <i data-lucide="<?php echo $meta['icon']; ?>" class="w-4 h-4 text-<?php echo $meta['color']; ?>-600"></i>
                                </div>
                                <h3 class="font-bold text-slate-800"><?php echo $meta['label']; ?></h3>
                                <?php if (isset($templateMeta[$slug])): ?>
                                <span class="text-xs px-2 py-1 bg-<?php echo $meta['color']; ?>-100 text-<?php echo $meta['color']; ?>-700 rounded-full"><?php echo ucfirst(str_replace('_', ' ', $slug)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="flex items-center gap-1 text-xs">
                                    <input type="checkbox" id="active-<?php echo $slug; ?>" <?php echo ($template['is_active'] ?? true) ? 'checked' : ''; ?> class="w-3 h-3 template-active">
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Workflow Stages:</label>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach ($workflowStages as $stage): ?>
                                <label class="flex items-center gap-1 text-xs bg-slate-100 px-2 py-1 rounded">
                                    <input type="checkbox"
                                           name="stages-<?php echo $slug; ?>[]"
                                           value="<?php echo $stage['stage_name']; ?>"
                                           <?php echo in_array($stage['stage_name'], $template['workflow_stages'] ?? []) ? 'checked' : ''; ?>
                                           class="w-3 h-3 stage-checkbox">
                                    <?php echo $stage['stage_name']; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <textarea id="tpl-<?php echo $slug; ?>" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none template-content" <?php echo !$isEditable ? 'readonly' : ''; ?>><?php echo htmlspecialchars($template['content'] ?? ''); ?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-gradient-to-br from-slate-50 to-slate-100 border border-slate-200 rounded-xl p-6 sticky top-6">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <i data-lucide="info" class="w-5 h-5"></i>
                        Template Variables
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="bg-white rounded-lg p-3 border border-slate-200">
                            <code class="text-blue-600 font-mono font-bold">{name}</code>
                            <p class="text-xs text-slate-600 mt-1">Customer's full name</p>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-slate-200">
                            <code class="text-blue-600 font-mono font-bold">{plate}</code>
                            <p class="text-xs text-slate-600 mt-1">Vehicle plate number</p>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-slate-200">
                            <code class="text-blue-600 font-mono font-bold">{amount}</code>
                            <p class="text-xs text-slate-600 mt-1">Service amount</p>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-slate-200">
                            <code class="text-blue-600 font-mono font-bold">{date}</code>
                            <p class="text-xs text-slate-600 mt-1">Service date</p>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-slate-200">
                            <code class="text-blue-600 font-mono font-bold">{link}</code>
                            <p class="text-xs text-slate-600 mt-1">Customer confirmation link</p>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-slate-200">
                            <code class="text-blue-600 font-mono font-bold">{count}</code>
                            <p class="text-xs text-slate-600 mt-1">Count/number (for system alerts)</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-xs text-blue-800">
                            <i data-lucide="lightbulb" class="w-4 h-4 inline mb-1"></i>
                            <strong>Tip:</strong> Use these placeholders in your templates. They will be automatically replaced with actual customer data when SMS is sent.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        const API_URL = 'api.php';
        const USER_ROLE = '<?php echo $current_user_role; ?>';
        const CAN_EDIT = USER_ROLE === 'admin' || USER_ROLE === 'manager' || USER_ROLE === 'viewer';
        
        let smsTemplates = <?php echo json_encode($templatesData); ?>;
        let workflowStages = <?php echo json_encode($workflowStages); ?>;

        // Utility Functions
        function getVal(id) {
            const el = document.getElementById(id);
            return el ? el.value.trim() : '';
        }

        function showToast(title, message = '', type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const icons = {
                success: 'check-circle',
                error: 'x-circle',
                info: 'info',
                urgent: 'alert-triangle'
            };

            const colors = {
                success: 'from-emerald-500 to-green-600',
                error: 'from-red-500 to-rose-600',
                info: 'from-blue-500 to-indigo-600',
                urgent: 'from-amber-500 to-orange-600'
            };

            const toast = document.createElement('div');
            toast.className = `transform transition-all duration-300 translate-x-0 opacity-100`;
            toast.innerHTML = `
                <div class="bg-gradient-to-r ${colors[type]} text-white px-6 py-4 rounded-xl shadow-2xl min-w-[300px] max-w-md">
                    <div class="flex items-start gap-3">
                        <i data-lucide="${icons[type]}" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                        <div class="flex-1">
                            <div class="font-bold text-sm">${title}</div>
                            ${message ? `<div class="text-xs mt-1 opacity-90">${message}</div>` : ''}
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(toast);
            lucide.createIcons();

            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';
        
        async function fetchAPI(action, method = 'GET', data = null) {
            const url = `${API_URL}?action=${action}`;
            const options = {
                method,
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include'  // Include cookies for session authentication
            };
            
            // Add CSRF token for POST requests
            if (method === 'POST' && CSRF_TOKEN) {
                options.headers['X-CSRF-Token'] = CSRF_TOKEN;
            }
            
            if (data && method === 'POST') {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            return response.json();
        }

        // Template Management Functions
        window.saveAllTemplates = async function() {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to edit templates', 'error');
                return;
            }

            try {
                const smsTemplates = {};

                // Collect data from all template cards dynamically
                const templateCards = document.querySelectorAll('.template-card');
                
                templateCards.forEach(card => {
                    const slug = card.dataset.slug;
                    const content = getVal(`tpl-${slug}`);
                    const isActive = document.getElementById(`active-${slug}`)?.checked ?? true;
                    
                    // Collect selected workflow stages
                    const stageCheckboxes = document.querySelectorAll(`input[name="stages-${slug}[]"]:checked`);
                    const workflowStages = Array.from(stageCheckboxes).map(cb => cb.value);
                    
                    smsTemplates[slug] = {
                        content: content,
                        workflow_stages: workflowStages,
                        is_active: isActive
                    };
                });

                await fetchAPI('save_templates', 'POST', smsTemplates);
                showToast('Success', 'All templates saved successfully', 'success');
                
                // Refresh data if on a page with processing queue
                if (typeof loadData === 'function') {
                    loadData();
                }
            } catch (err) {
                console.error('Error saving templates:', err);
                showToast('Error', err.message || 'Failed to save templates', 'error');
            }
        };

        function getFormattedMessage(type, data) {
            let template = smsTemplates[type]?.content || '';
            
            template = template.replace(/{name}/g, data.name || '');
            template = template.replace(/{plate}/g, data.plate || '');
            template = template.replace(/{amount}/g, data.amount || '');
            template = template.replace(/{date}/g, data.date || '');
            template = template.replace(/{link}/g, data.link || '');
            template = template.replace(/{count}/g, data.count || '');
            
            return template;
        }

        // Initialize Lucide icons
        if (window.lucide) {
            lucide.createIcons();
        }
    </script>
</main>
</body>
</html>
</html>
