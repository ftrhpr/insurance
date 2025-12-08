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

// Database connection
require_once 'config.php';

// Default templates
$defaultTemplatesData = [
    'registered' => 'გამარჯობა {name}, თქვენი სერვისის რეგისტრაცია მოხდა. ავტომობილი: {plate}. თანხა: {amount}₾',
    'called' => 'გამარჯობა {name}, დაგიკავშირდით ჩვენი მენეჯერი. ავტომობილი: {plate}',
    'schedule' => 'გამარჯობა {name}, თქვენი სერვისის თარიღი: {date}. ავტომობილი: {plate}',
    'parts_ordered' => 'გამარჯობა {name}, თქვენი ნაწილები შეკვეთილია. ავტომობილი: {plate}',
    'parts_arrived' => 'გამარჯობა {name}, თქვენი ნაწილები მივიდა. დაადასტურეთ თქვენი ვიზიტი: {link}',
    'rescheduled' => 'გამარჯობა, კლიენტმა {name} მოითხოვა თარიღის შეცვლა. ავტომობილი: {plate}',
    'reschedule_accepted' => 'გამარჯობა {name}, თქვენი თარიღის შეცვლის მოთხოვნა მიღებულია. ახალი თარიღი: {date}',
    'completed' => 'გამარჯობა {name}, თქვენი სერვისი დასრულდა. გთხოვთ შეაფასოთ ჩვენი მომსახურება',
    'issue' => 'გამარჯობა {name}, დაფიქსირდა პრობლემა. ავტომობილი: {plate}. ჩვენ დაგიკავშირდებით.'
];

try {
    $pdo = getDBConnection();
    
    // Fetch SMS templates
    $stmt = $pdo->query("SELECT * FROM sms_templates");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $templatesData = [];
    foreach ($templates as $tpl) {
        $templatesData[$tpl['slug']] = $tpl['content'];
    }
    
    // Merge with defaults (use database values if exist, otherwise use defaults)
    $templatesData = array_merge($defaultTemplatesData, $templatesData);
    
} catch (PDOException $e) {
    $templatesData = $defaultTemplatesData;
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
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

<?php include 'header.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Main Content -->
    <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-slate-200/60 p-8">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Templates Section -->
            <div class="lg:col-span-2 space-y-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-slate-800">Manage SMS Templates</h2>
                    <?php if ($current_user_role === 'admin' || $current_user_role === 'manager'): ?>
                    <button onclick="window.saveAllTemplates()" class="px-6 py-3 gradient-primary text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Save All Templates
                    </button>
                    <?php else: ?>
                    <div class="text-sm text-slate-500 italic">View only - editing disabled</div>
                    <?php endif; ?>
                </div>

                <!-- Template Cards -->
                <div class="space-y-4">
                    <!-- Welcome SMS -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <i data-lucide="user-check" class="w-4 h-4 text-emerald-600"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">Welcome SMS</h3>
                            <span class="text-xs px-2 py-1 bg-emerald-100 text-emerald-700 rounded-full ml-auto">Processing</span>
                        </div>
                        <textarea id="tpl-registered" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" <?php echo ($current_user_role !== 'admin' && $current_user_role !== 'manager') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($templatesData['registered'] ?? ''); ?></textarea>
                    </div>

                    <!-- Customer Contacted -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i data-lucide="phone-call" class="w-4 h-4 text-blue-600"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">Customer Contacted</h3>
                            <span class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded-full ml-auto">Called</span>
                        </div>
                        <textarea id="tpl-called" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" <?php echo ($current_user_role !== 'admin' && $current_user_role !== 'manager') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($templatesData['called'] ?? ''); ?></textarea>
                    </div>

                    <!-- Service Scheduled -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                                <i data-lucide="calendar-check" class="w-4 h-4 text-indigo-600"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">Service Scheduled</h3>
                            <span class="text-xs px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full ml-auto">Scheduled</span>
                        </div>
                        <textarea id="tpl-schedule" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" <?php echo ($current_user_role !== 'admin' && $current_user_role !== 'manager') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($templatesData['schedule'] ?? ''); ?></textarea>
                    </div>

                    <!-- Parts Ordered -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                                <i data-lucide="package" class="w-4 h-4 text-amber-600"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">Parts Ordered</h3>
                            <span class="text-xs px-2 py-1 bg-amber-100 text-amber-700 rounded-full ml-auto">Parts Ordered</span>
                        </div>
                        <textarea id="tpl-parts_ordered" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" <?php echo ($current_user_role !== 'admin' && $current_user_role !== 'manager') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($templatesData['parts_ordered'] ?? ''); ?></textarea>
                    </div>

                    <!-- Parts Arrived -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                                <i data-lucide="box" class="w-4 h-4 text-purple-600"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">Parts Arrived</h3>
                            <span class="text-xs px-2 py-1 bg-purple-100 text-purple-700 rounded-full ml-auto">Parts Arrived</span>
                        </div>
                        <textarea id="tpl-parts_arrived" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" <?php echo ($current_user_role !== 'admin' && $current_user_role !== 'manager') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($templatesData['parts_arrived'] ?? ''); ?></textarea>
                    </div>

                    <!-- Reschedule Request -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                                <i data-lucide="clock" class="w-4 h-4 text-orange-600"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">Reschedule Request (Customer)</h3>
                            <span class="text-xs px-2 py-1 bg-orange-100 text-orange-700 rounded-full ml-auto">Customer Action</span>
                        </div>
                        <textarea id="tpl-rescheduled" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" <?php echo ($current_user_role !== 'admin' && $current_user_role !== 'manager') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($templatesData['rescheduled'] ?? ''); ?></textarea>
                    </div>

                    <!-- Reschedule Accepted -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-cyan-100 flex items-center justify-center">
                                <i data-lucide="calendar-check" class="w-4 h-4 text-cyan-600"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">Reschedule Accepted (Manager)</h3>
                            <span class="text-xs px-2 py-1 bg-cyan-100 text-cyan-700 rounded-full ml-auto">Manager Action</span>
                        </div>
                        <textarea id="tpl-reschedule_accepted" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" <?php echo ($current_user_role !== 'admin' && $current_user_role !== 'manager') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($templatesData['reschedule_accepted'] ?? ''); ?></textarea>
                    </div>

                    <!-- Service Completed -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                <i data-lucide="check-circle" class="w-4 h-4 text-green-600"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">Service Completed</h3>
                            <span class="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-full ml-auto">Completed</span>
                        </div>
                        <textarea id="tpl-completed" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" <?php echo ($current_user_role !== 'admin' && $current_user_role !== 'manager') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($templatesData['completed'] ?? ''); ?></textarea>
                    </div>

                    <!-- Issue Reported -->
                    <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                <i data-lucide="alert-circle" class="w-4 h-4 text-red-600"></i>
                            </div>
                            <h3 class="font-bold text-slate-800">Issue Reported</h3>
                            <span class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded-full ml-auto">Issue</span>
                        </div>
                        <textarea id="tpl-issue" rows="3" class="w-full p-3 border border-slate-200 rounded-lg text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none" <?php echo ($current_user_role !== 'admin' && $current_user_role !== 'manager') ? 'readonly' : ''; ?>><?php echo htmlspecialchars($templatesData['issue'] ?? ''); ?></textarea>
                    </div>
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
        const CAN_EDIT = USER_ROLE === 'admin' || USER_ROLE === 'manager';
        
        let smsTemplates = <?php echo json_encode($templatesData); ?>;

        // Default templates if database is empty
        const defaultTemplates = {
            registered: 'გამარჯობა {name}, თქვენი სერვისის რეგისტრაცია მოხდა. ავტომობილი: {plate}. თანხა: {amount}₾',
            called: 'გამარჯობა {name}, დაგიკავშირდით ჩვენი მენეჯერი. ავტომობილი: {plate}',
            schedule: 'გამარჯობა {name}, თქვენი სერვისის თარიღი: {date}. ავტომობილი: {plate}',
            parts_ordered: 'გამარჯობა {name}, თქვენი ნაწილები შეკვეთილია. ავტომობილი: {plate}',
            parts_arrived: 'გამარჯობა {name}, თქვენი ნაწილები მივიდა. დაადასტურეთ თქვენი ვიზიტი: {link}',
            rescheduled: 'გამარჯობა, კლიენტმა {name} მოითხოვა თარიღის შეცვლა. ავტომობილი: {plate}',
            reschedule_accepted: 'გამარჯობა {name}, თქვენი თარიღის შეცვლის მოთხოვნა მიღებულია. ახალი თარიღი: {date}',
            completed: 'გამარჯობა {name}, თქვენი სერვისი დასრულდა. გთხოვთ შეაფასოთ ჩვენი მომსახურება',
            issue: 'გამარჯობა {name}, დაფიქსირდა პრობლემა. ავტომობილი: {plate}. ჩვენ დაგიკავშირდებით.'
        };

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
                headers: { 'Content-Type': 'application/json' }
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
                smsTemplates.registered = getVal('tpl-registered');
                smsTemplates.called = getVal('tpl-called');
                smsTemplates.schedule = getVal('tpl-schedule');
                smsTemplates.parts_ordered = getVal('tpl-parts_ordered');
                smsTemplates.parts_arrived = getVal('tpl-parts_arrived');
                smsTemplates.rescheduled = getVal('tpl-rescheduled');
                smsTemplates.reschedule_accepted = getVal('tpl-reschedule_accepted');
                smsTemplates.completed = getVal('tpl-completed');
                smsTemplates.issue = getVal('tpl-issue');

                await fetchAPI('save_templates', 'POST', smsTemplates);
                showToast('Success', 'All templates saved successfully', 'success');
            } catch (err) {
                console.error('Error saving templates:', err);
                showToast('Error', err.message || 'Failed to save templates', 'error');
            }
        };

        function getFormattedMessage(type, data) {
            let template = smsTemplates[type] || defaultTemplates[type] || '';
            
            template = template.replace(/{name}/g, data.name || '');
            template = template.replace(/{plate}/g, data.plate || '');
            template = template.replace(/{amount}/g, data.amount || '');
            template = template.replace(/{date}/g, data.date || '');
            template = template.replace(/{link}/g, data.link || '');
            
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
