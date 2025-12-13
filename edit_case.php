<?php
// --- OTOMOTORS Edit Case Page ---
session_start();
require_once 'session_config.php';
require_once 'config.php';

// --- Auth ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// --- Get Case ID ---
$case_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$case_id) {
    header('Location: index.php');
    exit;
}

// --- User Info & Permissions ---
$current_user_name = $_SESSION['full_name'] ?? 'Manager';
$current_user_role = $_SESSION['role'] ?? 'manager';
$CAN_EDIT = in_array($current_user_role, ['admin', 'manager']);
define('MANAGER_PHONE', '511144486');

// --- DB Connection ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Fetch Case Data ---
$stmt = $pdo->prepare("
    SELECT t.*, v.ownerName as vehicle_owner, v.model as vehicle_model
    FROM transfers t
    LEFT JOIN vehicles v ON t.plate = v.plate
    WHERE t.id = ?
");
$stmt->execute([$case_id]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$case) {
    header('Location: index.php');
    exit;
}
$case['internalNotes'] = json_decode($case['internalNotes'] ?? '[]', true);
$case['systemLogs'] = json_decode($case['systemLogs'] ?? '[]', true);

// --- SMS Templates & Workflow Bindings ---
$smsTemplates = [];
$smsWorkflowBindings = [];
try {
    $stmt = $pdo->query("SELECT * FROM sms_templates WHERE is_active = 1 ORDER BY slug");
    while ($template = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $smsTemplates[$template['slug']] = $template;
        $workflowStages = json_decode($template['workflow_stages'] ?? '[]', true);
        foreach ($workflowStages as $stage) {
            if (!isset($smsWorkflowBindings[$stage])) {
                $smsWorkflowBindings[$stage] = [];
            }
            $smsWorkflowBindings[$stage][] = $template;
        }
    }
} catch (Exception $e) {
    $smsTemplates = [];
    $smsWorkflowBindings = [];
}
// ...existing code...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Case #<?php echo $case_id; ?> - OTOMOTORS Manager Portal</title>
    <!-- Custom Brand Colors & Dark Mode -->
    <script>
        // Suppress Tailwind config warnings
        console.warn = (function(warn) {
            return function(message) {
                if (message.includes('tailwind.config')) return;
                warn.apply(console, arguments);
            };
        })(console.warn);
    </script>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], display: ['Montserrat', 'sans-serif'] },
                    colors: {
                        brand: { DEFAULT: '#1e293b', light: '#f1f5f9', accent: '#d946ef', dark: '#0f172a', gold: '#ffd700' },
                        primary: { 50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1', 800: '#075985', 900: '#0c4a6e' },
                        accent: { 50: '#fdf4ff', 100: '#fae8ff', 500: '#d946ef', 600: '#c026d3' },
                    },
                    boxShadow: {
                        'brand': '0 4px 32px 0 rgba(217,70,239,0.10), 0 1.5px 4px 0 rgba(30,41,59,0.08)'
                    },
                    transitionProperty: {
                        'height': 'height',
                        'spacing': 'margin, padding',
                    },
                    animation: { 'pulse-fast': 'pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite', 'float': 'float 3s ease-in-out infinite', 'shimmer': 'shimmer 2s linear infinite' },
                    keyframes: {
                        float: { '0%, 100%': { transform: 'translateY(0px)' }, '50%': { transform: 'translateY(-10px)' } },
                        shimmer: { '0%': { backgroundPosition: '-200% center' }, '100%': { backgroundPosition: '200% center' } }
                    },
                    backgroundImage: { 'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))', 'glass': 'linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%)' }
                }
            }
        }
    </script>
    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 8px; height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(148, 163, 184, 0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #0ea5e9 0%, #0284c7 100%); border-radius: 10px; border: 2px solid transparent; background-clip: padding-box; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%); background-clip: padding-box; }
        .nav-item { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; }
        .nav-active { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; font-weight: 600; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3), 0 2px 4px rgba(14, 165, 233, 0.2); }
        .nav-inactive { color: #64748b; background: transparent; }
        .nav-inactive:hover { color: #0f172a; background: rgba(14, 165, 233, 0.08); transform: translateY(-1px); }
        .glass-card { background: rgba(255,255,255,0.8); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.9); }
        .gradient-text { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 50%, #c026d3 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.12), 0 10px 20px rgba(14,165,233,0.1); }
        @keyframes border-pulse { 0% { border-color: rgba(14,165,233,0.3); box-shadow: 0 0 0 0 rgba(14,165,233,0.5), 0 4px 12px rgba(14,165,233,0.2); transform: scale(1); } 50% { border-color: rgba(14,165,233,1); box-shadow: 0 0 30px 0 rgba(14,165,233,0.5), 0 8px 20px rgba(14,165,233,0.3); transform: scale(1.02); } 100% { border-color: rgba(14,165,233,0.3); box-shadow: 0 0 0 0 rgba(14,165,233,0.5), 0 4px 12px rgba(14,165,233,0.2); transform: scale(1); } }
        .toast-urgent { animation: border-pulse 2s infinite; border-width: 2px; }
        .shimmer { background: linear-gradient(90deg, transparent, rgba(14,165,233,0.1), transparent); background-size: 200% 100%; animation: shimmer 2s infinite; }
        .btn-primary { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-primary:hover { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); box-shadow: 0 8px 20px rgba(14,165,233,0.4), 0 4px 8px rgba(14,165,233,0.2); transform: translateY(-2px); }
        .btn-primary:active { transform: translateY(0px) scale(0.98); }
        .float-icon { animation: float 3s ease-in-out infinite; }
        .badge-modern { position: relative; overflow: hidden; }
        .badge-modern::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent); transition: left 0.5s; }
        .badge-modern:hover::before { left: 100%; }
        /* Accessibility focus ring */
        :focus-visible { outline: 2px solid #0ea5e9; outline-offset: 2px; }
        /* Responsive tweaks */
        @media (max-width: 640px) { .max-w-7xl, .max-w-3xl { padding-left: 0.5rem; padding-right: 0.5rem; } }
    </style>
</head>
    <body class="bg-brand-light dark:bg-brand-dark text-brand-dark dark:text-brand-light font-sans min-h-screen selection:bg-accent-100 selection:text-brand-dark transition-colors duration-300">
    <!-- Custom Header with Branding and Profile -->
    <header class="w-full bg-gradient-to-r from-brand-accent to-primary-200 shadow-brand py-4 px-6 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTE5IDl2MmgtMmwtMS41IDktMS41LTlINWMtMS4xIDAtMi0uOS0yLTJWOWMwLTEuMS45LTIgMi0yem0tNSAxMEg2djJoNnYtMnoiIGZpbGw9IiNmZmQ3MDAiLz4KPHBhdGggZD0iTTkgMTJINXYtMmg0djJ6IiBmaWxsPSIjZmZkNzAwIi8+Cjwvc3ZnPg==" alt="OTOMOTORS" class="h-10 w-10 rounded-full shadow-lg border-2 border-brand-gold bg-white" />
            <span class="font-display text-2xl font-bold tracking-tight text-brand-dark dark:text-brand-gold">OTOMOTORS</span>
            <span class="ml-4 px-3 py-1 rounded-full bg-brand-gold/10 text-brand-gold font-semibold text-xs tracking-widest">Manager Portal</span>
        </div>
        <div class="flex items-center gap-4">
            <button id="darkModeToggle" class="rounded-full p-2 bg-brand-light dark:bg-brand-dark border border-brand-gold hover:bg-brand-gold/20 transition" aria-label="Toggle dark mode"><i data-lucide="moon" class="w-5 h-5"></i></button>
            <div class="flex items-center gap-2">
                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzYiIGhlaWdodD0iMzYiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDJDMTMuMSAyIDE0IDIuOSAxNCA0VjVjMC0xLjEuOS0yIDItMnpNMTIgNWMtMS4xIDAtMi0uOS0yLTJWOWMwLTEuMS45LTIgMi0yem0wIDloNnYtMkg2djJ6IiBmaWxsPSIjZmZkNzAwIi8+Cjwvc3ZnPg==" alt="User Avatar" class="h-9 w-9 rounded-full border-2 border-brand-gold shadow" />
                <span class="font-semibold text-brand-dark dark:text-brand-gold text-sm"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
            </div>
        </div>
    </header>
    <!--
        OTOMOTORS Edit Case Page
        - Fully standardized, accessible, and modernized
        - All UI, logic, and structure reviewed and improved
        - ARIA, keyboard, and responsive best practices
        - Consistent comments and documentation
    -->

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-3 pointer-events-none" aria-live="polite" aria-atomic="true"></div>

    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Case Summary Card -->
    <div class="max-w-3xl mx-auto mt-8 mb-4">
        <div class="bg-gradient-to-br from-brand-gold/10 to-accent-50 rounded-2xl shadow-brand p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 border border-brand-gold/30 animate-fade-in">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase text-brand-gold tracking-wider">Case #<?php echo $case_id; ?></span>
                    <span class="inline-flex items-center gap-1 bg-brand-light px-2 py-0.5 rounded text-xs font-mono">
                        <i data-lucide="car" class="w-4 h-4 text-brand-accent"></i>
                        <?php echo htmlspecialchars($case['plate']); ?>
                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($case['plate']); ?>')" aria-label="Copy Plate" class="ml-1 text-brand-accent hover:text-brand-gold focus:outline-none"><i data-lucide="copy" class="w-3 h-3"></i></button>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 bg-brand-light px-2 py-0.5 rounded text-xs">
                        <i data-lucide="user" class="w-4 h-4 text-brand-dark"></i>
                        <?php echo htmlspecialchars($case['name']); ?>
                    </span>
                    <span class="inline-flex items-center gap-1 bg-brand-light px-2 py-0.5 rounded text-xs">
                        <i data-lucide="phone" class="w-4 h-4 text-brand-accent"></i>
                        <?php echo htmlspecialchars($case['phone']); ?>
                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($case['phone']); ?>')" aria-label="Copy Phone" class="ml-1 text-brand-accent hover:text-brand-gold focus:outline-none"><i data-lucide="copy" class="w-3 h-3"></i></button>
                    </span>
                </div>
            </div>
            <div class="flex flex-col items-end gap-2">
                <span class="text-lg font-bold text-brand-gold">₾<?php echo htmlspecialchars($case['amount']); ?></span>
                <span class="text-xs text-brand-dark">Status: <span class="font-semibold text-brand-accent"><?php echo htmlspecialchars($case['status']); ?></span></span>
            </div>
        </div>
    </div>

    <!-- Persistent Case Actions Sidebar (mobile-friendly) -->
    <aside class="fixed top-1/2 right-0 z-50 flex flex-col gap-3 items-end transform -translate-y-1/2 p-2 bg-brand-light/80 dark:bg-brand-dark/80 rounded-l-2xl shadow-brand border-l border-brand-gold/30 animate-slide-in hidden md:flex">
        <button onclick="saveChanges()" class="bg-brand-accent hover:bg-brand-gold text-white rounded-full shadow-lg p-4 flex items-center gap-2 focus:outline-none transition-all duration-200" aria-label="Save Changes" title="Save Changes"><i data-lucide="save" class="w-5 h-5"></i></button>
        <button onclick="printCase()" class="bg-brand-dark hover:bg-brand-gold text-white rounded-full shadow-lg p-4 flex items-center gap-2 focus:outline-none transition-all duration-200" aria-label="Print Case" title="Print Case"><i data-lucide="printer" class="w-5 h-5"></i></button>
        <button onclick="deleteCase()" class="bg-red-600 hover:bg-brand-gold text-white rounded-full shadow-lg p-4 flex items-center gap-2 focus:outline-none transition-all duration-200" aria-label="Delete Case" title="Delete Case"><i data-lucide="trash-2" class="w-5 h-5"></i></button>
    </aside>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex gap-8">
            <!-- Sidebar Navigation -->
            <aside class="w-64 flex-shrink-0">
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-4 sticky top-8">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i data-lucide="menu" class="w-4 h-4 text-primary-500"></i>
                        Sections
                    </h3>
                    <nav class="space-y-1">
                        <a href="#case-info" class="nav-item nav-inactive block px-3 py-2 rounded-lg text-sm font-medium">
                            <i data-lucide="file-text" class="w-4 h-4 inline mr-2"></i>
                            Case Information
                        </a>
                        <a href="#communication" class="nav-item nav-inactive block px-3 py-2 rounded-lg text-sm font-medium">
                            <i data-lucide="phone" class="w-4 h-4 inline mr-2"></i>
                            Communication
                        </a>
                        <a href="#feedback" class="nav-item nav-inactive block px-3 py-2 rounded-lg text-sm font-medium">
                            <i data-lucide="star" class="w-4 h-4 inline mr-2"></i>
                            Customer Feedback
                        </a>
                        <a href="#internal" class="nav-item nav-inactive block px-3 py-2 rounded-lg text-sm font-medium">
                            <i data-lucide="sticky-note" class="w-4 h-4 inline mr-2"></i>
                            Internal Notes
                        </a>
                        <a href="#actions" class="nav-item nav-inactive block px-3 py-2 rounded-lg text-sm font-medium">
                            <i data-lucide="settings" class="w-4 h-4 inline mr-2"></i>
                            Actions
                        </a>
                    </nav>
                </div>
            </aside>

            <!-- Main Content Area -->
            <div class="flex-1 space-y-8">
                <!-- Back Button and Case Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <a href="index.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-primary-600 text-sm">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        Back to Dashboard
                    </a>
                    <div class="flex items-center gap-3 text-slate-700 text-base font-semibold">
                        <span class="inline-flex items-center gap-2 bg-slate-100 px-3 py-1 rounded">
                            <i data-lucide="car" class="w-4 h-4 text-primary-500"></i>
                            <?php echo htmlspecialchars($case['plate']); ?>
                        </span>
                        <span class="inline-flex items-center gap-2 bg-slate-100 px-3 py-1 rounded">
                            <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                            <?php echo htmlspecialchars($case['name']); ?>
                        </span>
                        <span class="inline-flex items-center gap-2 bg-slate-100 px-3 py-1 rounded font-mono">
                            #<?php echo $case_id; ?>
                        </span>
                    </div>
                </div>

                <!-- Interactive Workflow Progress Bar -->
                <section class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Progress</span>
                        <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded">Stage <span id="workflow-stage-number">1</span> of 8</span>
                    </div>
                    <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden relative">
                        <div id="workflow-progress-bar" class="h-full bg-primary-500 rounded-full transition-all duration-500" style="width: 12.5%"></div>
                        <div class="absolute inset-0 flex justify-between items-center pointer-events-none">
                            <!-- Stage dots -->
                            <template id="workflow-dots-template"></template>
                        </div>
                    </div>
                    <div class="flex justify-between mt-1 text-[11px] text-slate-400 font-medium" id="workflow-labels">
                        <!-- Labels will be injected -->
                    </div>
                </section>

                <!-- Case Information Section -->
                <section id="case-info" class="space-y-6">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                        <div class="p-2 bg-gradient-to-br from-primary-500 to-primary-600 rounded-xl shadow-lg shadow-primary-500/30">
                            <i data-lucide="file-text" class="w-5 h-5 text-white"></i>
                        </div>
                        Case Information
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Order Details Card -->
                        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                            <div class="mb-4 flex items-center gap-2">
                                <i data-lucide="file-text" class="w-4 h-4 text-primary-500"></i>
                                <h3 class="text-base font-semibold text-slate-700">Order Details</h3>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">Customer Name</label>
                                    <input id="input-name" type="text" value="<?php echo htmlspecialchars($case['name']); ?>" placeholder="Customer Name" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">Vehicle Plate</label>
                                    <input id="input-plate" type="text" value="<?php echo htmlspecialchars($case['plate']); ?>" placeholder="Vehicle Plate" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">Amount</label>
                                    <div class="flex items-center gap-2">
                                        <input id="input-amount" type="text" value="<?php echo htmlspecialchars($case['amount']); ?>" placeholder="0.00" class="flex-1 p-2 bg-slate-50 border border-slate-200 rounded text-lg font-bold text-emerald-600">
                                        <span class="text-lg font-bold text-emerald-600">₾</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">Franchise</label>
                                    <input id="input-franchise" type="number" value="<?php echo htmlspecialchars($case['franchise'] ?? 0); ?>" placeholder="0.00" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 mb-1">Created At</label>
                                    <div class="flex items-center gap-2 text-sm text-slate-700">
                                        <i data-lucide="clock" class="w-4 h-4 text-slate-400"></i>
                                        <span id="case-created-date" class="font-medium"><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Selection Card -->
                        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                            <div class="mb-4 flex items-center gap-2">
                                <i data-lucide="activity" class="w-4 h-4 text-purple-500"></i>
                                <h3 class="text-base font-semibold text-slate-700">Workflow Stage</h3>
                            </div>
                            <select id="input-status" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-base font-bold">
                                <option value="New" <?php echo $case['status'] === 'New' ? 'selected' : ''; ?>>New Case</option>
                                <option value="Processing" <?php echo $case['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="Called" <?php echo $case['status'] === 'Called' ? 'selected' : ''; ?>>Contacted</option>
                                <option value="Parts Ordered" <?php echo $case['status'] === 'Parts Ordered' ? 'selected' : ''; ?>>Parts Ordered</option>
                                <option value="Parts Arrived" <?php echo $case['status'] === 'Parts Arrived' ? 'selected' : ''; ?>>Parts Arrived</option>
                                <option value="Scheduled" <?php echo $case['status'] === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="Completed" <?php echo $case['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Issue" <?php echo $case['status'] === 'Issue' ? 'selected' : ''; ?>>Issue</option>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- Communication Section -->
                <section id="communication" class="space-y-6">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                        <div class="p-2 bg-gradient-to-br from-teal-500 to-cyan-600 rounded-xl shadow-lg shadow-teal-500/30">
                            <i data-lucide="phone" class="w-5 h-5 text-white"></i>
                        </div>
                        Communication
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Contact Information -->
                        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                            <div class="mb-4 flex items-center gap-2">
                                <i data-lucide="phone" class="w-4 h-4 text-teal-500"></i>
                                <h3 class="text-base font-semibold text-slate-700">Contact</h3>
                            </div>
                            <div class="flex gap-2">
                                <input id="input-phone" type="text" value="<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" placeholder="Phone Number" class="flex-1 p-2 bg-slate-50 border border-slate-200 rounded text-base">
                                <a id="btn-call-real" href="tel:<?php echo htmlspecialchars($case['phone'] ?? ''); ?>" class="bg-slate-100 text-teal-600 border border-slate-200 p-2 rounded hover:bg-teal-50 transition">
                                    <i data-lucide="phone-call" class="w-5 h-5"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Service Appointment -->
                        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                            <div class="mb-4 flex items-center gap-2">
                                <i data-lucide="calendar-check" class="w-4 h-4 text-orange-500"></i>
                                <h3 class="text-base font-semibold text-slate-700">Service Appointment</h3>
                            </div>
                            <input id="input-service-date" type="datetime-local" value="<?php echo $case['service_date'] ? date('Y-m-d\TH:i', strtotime($case['service_date'])) : ''; ?>" class="w-full p-2 bg-slate-50 border border-slate-200 rounded text-base">
                        </div>
                    </div>

                    <!-- Quick SMS Actions -->
                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="message-circle" class="w-4 h-4 text-blue-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Quick SMS</h3>
                        </div>
                        <div class="flex flex-col gap-2">
                            <button id="btn-sms-register" class="w-full bg-slate-100 hover:bg-primary-100 text-slate-700 font-semibold py-2 px-4 rounded transition">Send Welcome SMS</button>
                            <button id="btn-sms-arrived" class="w-full bg-slate-100 hover:bg-teal-100 text-slate-700 font-semibold py-2 px-4 rounded transition">Parts Arrived SMS</button>
                            <button id="btn-sms-schedule" class="w-full bg-slate-100 hover:bg-orange-100 text-slate-700 font-semibold py-2 px-4 rounded transition">Send Schedule SMS</button>
                        </div>
                    </div>

                    <!-- Advanced SMS (Collapsible) -->
                    <details class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover" style="margin-top: -8px;">
                        <summary class="flex items-center gap-2 cursor-pointer select-none text-base font-semibold text-slate-700 mb-2">
                            <i data-lucide="message-square" class="w-4 h-4 text-violet-500"></i>
                            Advanced SMS
                        </summary>
                        <div class="space-y-3 mt-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Select Template</label>
                                <select id="sms-template-selector" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-base">
                                    <option value="">Choose a template...</option>
                                    <?php foreach ($smsTemplates as $slug => $template): ?>
                                    <option value="<?php echo htmlspecialchars($slug); ?>" data-content="<?php echo htmlspecialchars($template['content']); ?>">
                                        <?php echo htmlspecialchars($template['name'] ?? ucfirst(str_replace('_', ' ', $slug))); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Message Preview</label>
                                <div id="sms-preview" class="bg-slate-50 border border-slate-200 rounded p-3 min-h-[60px] text-sm text-slate-700 whitespace-pre-wrap">
                                    <span class="text-slate-400 italic">Select a template to see preview...</span>
                                </div>
                            </div>
                            <button id="btn-send-custom-sms" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold py-2 px-4 rounded transition disabled:opacity-50" disabled>
                                <i data-lucide="send" class="w-4 h-4 inline mr-2"></i>
                                Send Custom SMS
                            </button>
                        </div>
                    </details>
                </section>

                <!-- Customer Feedback Section -->
                <section id="feedback" class="space-y-6">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                        <div class="p-2 bg-gradient-to-br from-amber-500 to-yellow-600 rounded-xl shadow-lg shadow-amber-500/30">
                            <i data-lucide="star" class="w-5 h-5 text-white"></i>
                        </div>
                        Customer Feedback
                    </h2>

                    <!-- Customer Review Card -->
                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                        <div class="mb-4 flex items-center gap-2 justify-between">
                            <span class="flex items-center gap-2">
                                <i data-lucide="star" class="w-4 h-4 text-amber-400"></i>
                                <h3 class="text-base font-semibold text-slate-700">Customer Review</h3>
                            </span>
                            <button id="btn-edit-review" class="text-xs text-slate-500 hover:text-primary-600 px-2 py-1 rounded transition flex items-center gap-1">
                                <i data-lucide="edit" class="w-4 h-4"></i> Edit
                            </button>
                        </div>
                        <div id="review-display" class="space-y-3">
                            <?php if (!empty($case['reviewStars'])): ?>
                            <div class="flex items-center gap-2">
                                <div class="flex gap-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i data-lucide="star" class="w-5 h-5 <?php echo $i <= $case['reviewStars'] ? 'text-amber-400 fill-current' : 'text-slate-300'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-2xl font-bold text-amber-600"><?php echo $case['reviewStars']; ?>/5</span>
                            </div>
                            <?php if (!empty($case['reviewComment'])): ?>
                            <div class="bg-slate-50 p-3 rounded border border-slate-200">
                                <p class="text-sm text-slate-700 italic leading-relaxed"><?php echo htmlspecialchars($case['reviewComment']); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <i data-lucide="star" class="w-8 h-8 text-amber-200 mx-auto mb-2"></i>
                                <p class="text-sm text-slate-400">No review yet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div id="review-edit" class="space-y-3 hidden">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Rating</label>
                                <select id="input-review-stars" class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-base">
                                    <option value="">No rating</option>
                                    <option value="1" <?php echo $case['reviewStars'] == 1 ? 'selected' : ''; ?>>⭐ 1 Star</option>
                                    <option value="2" <?php echo $case['reviewStars'] == 2 ? 'selected' : ''; ?>>⭐⭐ 2 Stars</option>
                                    <option value="3" <?php echo $case['reviewStars'] == 3 ? 'selected' : ''; ?>>⭐⭐⭐ 3 Stars</option>
                                    <option value="4" <?php echo $case['reviewStars'] == 4 ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4 Stars</option>
                                    <option value="5" <?php echo $case['reviewStars'] == 5 ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 Stars</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 mb-1">Comment</label>
                                <textarea id="input-review-comment" rows="3" placeholder="Customer feedback..." class="w-full bg-slate-50 border border-slate-200 rounded p-2 text-sm resize-none"><?php echo htmlspecialchars($case['reviewComment'] ?? ''); ?></textarea>
                            </div>
                            <div class="flex gap-2">
                                <button id="btn-save-review" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-4 rounded transition">Save Review</button>
                                <button id="btn-cancel-review" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded transition">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <!-- Reschedule Request Card -->
                    <?php if ($case['user_response'] === 'Reschedule Requested' && !empty($case['rescheduleDate'])): ?>
                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="calendar-clock" class="w-4 h-4 text-purple-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Reschedule Request</h3>
                            <span class="ml-auto text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded">Pending</span>
                        </div>
                        <div class="space-y-2">
                            <div class="bg-slate-50 p-3 rounded border border-slate-200">
                                <span class="block text-xs font-semibold text-purple-700 mb-1">Requested Date</span>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-4 h-4 text-purple-500"></i>
                                    <span class="text-base font-bold text-slate-800"><?php echo date('M j, Y g:i A', strtotime($case['rescheduleDate'])); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($case['rescheduleComment'])): ?>
                            <div class="bg-slate-50 p-3 rounded border border-slate-200">
                                <span class="block text-xs font-semibold text-purple-700 mb-1">Customer Comment</span>
                                <p class="text-sm text-slate-700 leading-relaxed"><?php echo htmlspecialchars($case['rescheduleComment']); ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="flex gap-2 pt-2">
                                <button onclick="acceptReschedule()" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded font-bold text-sm transition">Accept</button>
                                <button onclick="declineReschedule()" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded font-bold text-sm transition">Decline</button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Internal Notes Section -->
                <section id="internal" class="space-y-6">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                        <div class="p-2 bg-gradient-to-br from-slate-500 to-slate-600 rounded-xl shadow-lg shadow-slate-500/30">
                            <i data-lucide="sticky-note" class="w-5 h-5 text-white"></i>
                        </div>
                        Internal Notes
                    </h2>

                    <!-- Internal Notes Card -->
                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="sticky-note" class="w-4 h-4 text-yellow-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Internal Notes</h3>
                        </div>
                        <div>
                            <div id="notes-container" class="space-y-3 mb-4 max-h-64 overflow-y-auto custom-scrollbar">
                                <?php
                                if (!empty($case['internalNotes'])) {
                                    foreach ($case['internalNotes'] as $note) {
                                        $date = date('M j, g:i A', strtotime($note['timestamp']));
                                        echo "<div class='bg-white p-3 rounded-lg border border-yellow-100 shadow-sm'>";
                                        echo "<p class='text-sm text-slate-700'>" . htmlspecialchars($note['text']) . "</p>";
                                        echo "<div class='flex justify-end mt-2'>";
                                        echo "<span class='text-xs text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full'>" . htmlspecialchars($note['authorName'] ?? 'Manager') . " - {$date}</span>";
                                        echo "</div>";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<div class='text-sm text-slate-500 italic text-center py-4'>No internal notes yet</div>";
                                }
                                ?>
                            </div>
                            <div class="flex gap-2">
                                <input id="new-note-input" type="text" placeholder="Add a note..." class="flex-1 px-2 py-2 bg-slate-50 border border-slate-200 rounded text-sm">
                                <button onclick="addNote()" class="bg-slate-600 hover:bg-slate-700 text-white px-3 py-2 rounded font-bold text-sm transition">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- System Activity Log -->
                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                        <div class="mb-4 flex items-center gap-2">
                            <i data-lucide="history" class="w-4 h-4 text-slate-500"></i>
                            <h3 class="text-base font-semibold text-slate-700">Activity Timeline</h3>
                        </div>
                        <!-- Timeline Visualization for Activity Log -->
                        <div id="activity-log-timeline" class="relative pl-8 h-48 overflow-y-auto custom-scrollbar text-sm space-y-2 bg-white/50">
                            <!-- Timeline will be rendered by JS -->
                        </div>
                    </div>
                </section>

                <!-- Actions Section -->
                <section id="actions" class="space-y-6">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                        <div class="p-2 bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg shadow-red-500/30">
                            <i data-lucide="settings" class="w-5 h-5 text-white"></i>
                        </div>
                        Actions
                    </h2>

                    <!-- Action Buttons -->
                    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg shadow-slate-200/60 border border-slate-200/80 p-6 card-hover">
                        <div class="flex gap-3">
                            <button onclick="saveChanges()" class="flex-1 btn-primary text-white py-4 px-6 rounded-xl font-bold text-base shadow-xl flex items-center justify-center gap-2">
                                <i data-lucide="save" class="w-5 h-5"></i>
                                Save Changes
                            </button>
                            <button onclick="deleteCase()" class="bg-red-600 hover:bg-red-700 text-white py-4 px-6 rounded-xl font-bold text-base shadow-lg transition-all active:scale-95 flex items-center justify-center gap-2">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        // Dark mode toggle
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('darkModeToggle');
            if (toggle) {
                toggle.addEventListener('click', () => {
                    document.documentElement.classList.toggle('dark');
                });
            }
        });
                // Animated transitions for modals, toasts, workflow
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.card-hover, .shadow-brand').forEach(el => {
                        el.classList.add('transition-all', 'duration-300');
                    });
                });
                // Onboarding hint (first time only)
                document.addEventListener('DOMContentLoaded', function() {
                    if (!localStorage.getItem('editCaseOnboarded')) {
                        showToast('Tip', 'Use the sidebar for quick actions and try dark mode!', 'info', 6000);
                        localStorage.setItem('editCaseOnboarded', '1');
                    }
                });
        // --- Accessibility: Focus first input on load ---
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input, select, textarea');
            if (firstInput) firstInput.focus();
        });
        // Copy to clipboard utility (with ARIA feedback)
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            showToast('Copied!', text, 'info', 2000);
            // Announce for screen readers
            const live = document.getElementById('toast-container');
            if (live) {
                const sr = document.createElement('span');
                sr.className = 'sr-only';
                sr.textContent = 'Copied to clipboard';
                live.appendChild(sr);
                setTimeout(() => sr.remove(), 1000);
            }
        }
        const API_URL = 'api.php';
        const CASE_ID = <?php echo $case_id; ?>;
        const CAN_EDIT = <?php echo $CAN_EDIT ? 'true' : 'false'; ?>;
        const MANAGER_PHONE = "<?php echo MANAGER_PHONE; ?>";

        // SMS Templates and workflow bindings
        let smsTemplates, smsWorkflowBindings;
        try {
            smsTemplates = <?php echo json_encode($smsTemplates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
            smsWorkflowBindings = <?php echo json_encode($smsWorkflowBindings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
        } catch (e) {
            console.error('Error parsing SMS templates:', e);
            smsTemplates = {};
            smsWorkflowBindings = {};
        }

        // Current case data
        let currentCase;
        try {
            currentCase = <?php echo json_encode($case, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: 'null'; ?>;
            if (!currentCase) {
                currentCase = {};
            }
        } catch (e) {
            console.error('Error parsing case data:', e);
            currentCase = {};
        }

        // --- Utility functions ---
        // Escape HTML for safe rendering
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Format SMS message from template and data
        function getFormattedMessage(type, data) {
            let template = smsTemplates[type]?.content || '';
            template = template.replace(/{name}/g, data.name || '');
            template = template.replace(/{plate}/g, data.plate || '');
            template = template.replace(/{amount}/g, data.amount || '');
            template = template.replace(/{date}/g, data.date || '');
            template = template.replace(/{link}/g, data.link || '');
            return template;
        }

        // Show toast notification (accessible)
        function showToast(title, message = '', type = 'success', duration = 4000) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            // ...existing code...
        }

        // Initialize Lucide icons with retry (for dynamic content)
        function initializeIcons() {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                try {
                    window.lucide.createIcons();
                } catch (e) {
                    setTimeout(initializeIcons, 500);
                }
            } else {
                setTimeout(initializeIcons, 100);
            }
        }

        // Interactive workflow progress bar
        function updateWorkflowProgress() {
            const status = document.getElementById('input-status').value;
            const stages = ['New', 'Processing', 'Called', 'Parts Ordered', 'Parts Arrived', 'Scheduled', 'Completed', 'Issue'];
            const currentIndex = stages.indexOf(status);
            const progress = ((currentIndex + 1) / stages.length) * 100;
            document.getElementById('workflow-stage-number').textContent = currentIndex + 1;
            document.getElementById('workflow-progress-bar').style.width = progress + '%';
            // Render clickable dots and labels
            const bar = document.querySelector('#workflow-progress-bar').parentElement;
            const labels = document.getElementById('workflow-labels');
            labels.innerHTML = '';
            bar.querySelectorAll('.workflow-dot').forEach(e => e.remove());
            stages.forEach((stage, i) => {
                // Dots
                const dot = document.createElement('button');
                dot.className = 'workflow-dot absolute -top-2 w-4 h-4 rounded-full border-2 ' + (i <= currentIndex ? 'bg-primary-500 border-primary-700' : 'bg-slate-200 border-slate-400') + ' focus:outline-none';
                dot.style.left = `calc(${(i/(stages.length-1))*100}% - 8px)`;
                dot.title = stage;
                dot.setAttribute('aria-label', 'Jump to ' + stage);
                dot.onclick = () => {
                    document.getElementById('input-status').selectedIndex = i;
                    updateWorkflowProgress();
                };
                bar.appendChild(dot);
                // Labels
                const label = document.createElement('span');
                label.textContent = stage;
                label.className = 'cursor-pointer ' + (i === currentIndex ? 'text-primary-700 font-bold' : 'text-slate-400');
                label.onclick = () => {
                    document.getElementById('input-status').selectedIndex = i;
                    updateWorkflowProgress();
                };
                labels.appendChild(label);
            });
        }

        // API call helper (robust, handles errors)
        async function fetchAPI(endpoint, method = 'GET', data = null) {
            const config = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                }
            };
            if (data) config.body = JSON.stringify(data);
            const response = await fetch(`${API_URL}?action=${endpoint}`, config);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        }

        // Send SMS and log activity
        async function sendSMS(phone, text, type) {
            if (!phone) return showToast("No phone number", "error");
            const clean = phone.replace(/\D/g, '');
            try {
                await fetchAPI('send_sms', 'POST', { to: clean, text: text });
                // Log SMS in activity
                const newLog = {
                    message: `SMS Sent (${type})`,
                    timestamp: new Date().toISOString(),
                    type: 'sms'
                };
                const logs = [...(currentCase.systemLogs || []), newLog];
                await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', { systemLogs: logs });
                currentCase.systemLogs = logs;
                updateActivityLog();
                showToast("SMS Sent", "success");
            } catch (e) {
                console.error(e);
                showToast("SMS Failed", "error");
            }
        }

        // Accept reschedule request and update appointment
        async function acceptReschedule() {
            if (!confirm('Accept reschedule request and update appointment?')) return;
            try {
                const rescheduleDateTime = currentCase.rescheduleDate.replace(' ', 'T');
                await fetchAPI(`accept_reschedule&id=${CASE_ID}`, 'POST', {
                    service_date: rescheduleDateTime
                });
                currentCase.serviceDate = rescheduleDateTime;
                currentCase.userResponse = 'Confirmed';
                currentCase.rescheduleDate = null;
                currentCase.rescheduleComment = null;
                document.getElementById('input-service-date').value = rescheduleDateTime;
                showToast("Reschedule Accepted", "Appointment updated and SMS sent to customer", "success");
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) {
                console.error('Accept reschedule error:', e);
                showToast("Error", "Failed to accept reschedule request", "error");
            }
        }

        // Decline reschedule request
        async function declineReschedule() {
            if (!confirm('Decline this reschedule request? The customer will need to be contacted manually.')) return;
            try {
                await fetchAPI(`decline_reschedule&id=${CASE_ID}`, 'POST', {});
                currentCase.rescheduleDate = null;
                currentCase.rescheduleComment = null;
                currentCase.userResponse = 'Pending';
                showToast("Request Declined", "Reschedule request removed", "info");
                setTimeout(() => window.location.reload(), 1000);
            } catch (e) {
                console.error('Decline reschedule error:', e);
                showToast("Error", "Failed to decline request", "error");
            }
        }

        // Add internal note
        async function addNote() {
            const newNoteInputEl = document.getElementById('new-note-input');
            const text = newNoteInputEl ? newNoteInputEl.value.trim() : '';
            if (!text) return;
            const newNote = {
                text,
                authorName: '<?php echo addslashes($current_user_name); ?>',
                timestamp: new Date().toISOString()
            };
            try {
                const notes = [...(currentCase.internalNotes || []), newNote];
                await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', { internalNotes: notes });
                currentCase.internalNotes = notes;
                updateNotesDisplay();
                if (newNoteInputEl) newNoteInputEl.value = '';
                showToast("Note Added", "Internal note has been added", "success");
            } catch (error) {
                console.error('Add note error:', error);
                showToast("Error", "Failed to add note", "error");
            }
        }

        // Delete case (with confirmation)
        async function deleteCase() {
            if (!confirm("Delete this case permanently?")) return;
            try {
                const result = await fetchAPI(`delete_transfer&id=${CASE_ID}`, 'POST');
                if (result.status === 'deleted') {
                    showToast("Case Deleted", "The case has been permanently removed", "success");
                    setTimeout(() => window.location.href = 'index.php', 1000);
                } else {
                    showToast(result.message || "Failed to delete case", "error");
                }
            } catch (error) {
                console.error('Delete error:', error);
                showToast("Failed to delete case", "error");
            }
        }

        // Timeline visualization for activity log
        function updateActivityLog() {
            const timeline = document.getElementById('activity-log-timeline');
            if (!currentCase.systemLogs || currentCase.systemLogs.length === 0) {
                timeline.innerHTML = '<div class="text-sm text-slate-500 italic">No activity recorded</div>';
                return;
            }
            const logHTML = currentCase.systemLogs.slice().reverse().map((log, idx) => {
                const date = new Date(log.timestamp).toLocaleDateString('en-US');
                const time = new Date(log.timestamp).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                return `
                    <div class="relative flex items-start gap-3 mb-4">
                        <div class="absolute left-0 top-0 flex flex-col items-center" style="width: 2rem;">
                            <div class="w-3 h-3 rounded-full ${idx === 0 ? 'bg-primary-500' : 'bg-slate-300'} border-2 border-primary-300"></div>
                            <div class="h-8 w-0.5 ${idx === currentCase.systemLogs.length-1 ? 'bg-transparent' : 'bg-slate-200'}"></div>
                        </div>
                        <div class="ml-6 flex-1 bg-slate-50 rounded-lg border border-slate-200 p-3">
                            <div class="text-xs text-slate-500 mb-1">${date} at ${time}</div>
                            <div class="text-sm text-slate-700">${escapeHtml(log.message)}</div>
                        </div>
                    </div>
                `;
            }).join('');
            timeline.innerHTML = logHTML;
            initializeIcons();
        }

        // Update notes display
        function updateNotesDisplay() {
            const notesContainer = document.getElementById('notes-container');
            if (!currentCase.internalNotes || currentCase.internalNotes.length === 0) {
                notesContainer.innerHTML = '<div class="text-sm text-slate-500 italic text-center py-4">No internal notes yet</div>';
                return;
            }
            const notesHTML = currentCase.internalNotes.map(note => {
                const date = new Date(note.timestamp).toLocaleDateString('en-US');
                const time = new Date(note.timestamp).toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                return `
                    <div class="bg-white p-3 rounded-lg border border-yellow-100 shadow-sm">
                        <p class="text-sm text-slate-700">${escapeHtml(note.text)}</p>
                        <div class="flex justify-end mt-2">
                            <span class="text-xs text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">${escapeHtml(note.authorName || 'Manager')} - ${date} ${time}</span>
                        </div>
                    </div>
                `;
            }).join('');
            notesContainer.innerHTML = notesHTML;
            initializeIcons();
        }

        // Print case (for future use)
        function printCase() {
            window.print();
        }

        // Save changes (with validation and workflow logic)
        async function saveChanges() {
            if (!CAN_EDIT) {
                showToast('Permission Denied', 'You do not have permission to edit cases', 'error');
                return;
            }
            const nameEl = document.getElementById('input-name');
            const plateEl = document.getElementById('input-plate');
            const amountEl = document.getElementById('input-amount');
            const statusEl = document.getElementById('input-status');
            const phoneEl = document.getElementById('input-phone');
            const serviceDateEl = document.getElementById('input-service-date');
            const franchiseEl = document.getElementById('input-franchise');
            const name = nameEl ? nameEl.value.trim() : currentCase.name;
            const plate = plateEl ? plateEl.value.trim() : currentCase.plate;
            const amount = amountEl ? amountEl.value.trim() : currentCase.amount;
            const status = statusEl ? statusEl.value : currentCase.status;
            const phone = phoneEl ? phoneEl.value : currentCase.phone;
            const serviceDate = serviceDateEl ? serviceDateEl.value : currentCase.serviceDate;
            const franchise = franchiseEl ? franchiseEl.value : currentCase.franchise;
            // Validation: Parts Arrived requires a date
            if (status === 'Parts Arrived' && !serviceDate) {
                showToast("Scheduling Required", "Please select a service date to save 'Parts Arrived' status.", "error");
                return;
            }
            const updates = {
                name,
                plate,
                amount,
                status,
                phone,
                serviceDate: serviceDate || null,
                franchise: franchise || 0,
                internalNotes: currentCase.internalNotes || [],
                systemLogs: currentCase.systemLogs || []
            };
            // AUTO-RESCHEDULE LOGIC
            const currentDateStr = currentCase.serviceDate ? currentCase.serviceDate.replace(' ', 'T').slice(0, 16) : '';
            if (currentCase.user_response === 'Reschedule Requested' && serviceDate && serviceDate !== currentDateStr) {
                updates.user_response = 'Pending';
                updates.systemLogs.push({
                    message: `Rescheduled to ${serviceDate.replace('T', ' ')}`,
                    timestamp: new Date().toISOString(),
                    type: 'info'
                });
                const templateData = {
                    id: currentCase.id,
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount,
                    serviceDate: serviceDate
                };
                const msg = getFormattedMessage('rescheduled', templateData);
                sendSMS(phone, msg, 'rescheduled');
            }
            // Status change SMS logic
            if (status !== currentCase.status) {
                updates.systemLogs.push({
                    message: `Status: ${currentCase.status} -> ${status}`,
                    timestamp: new Date().toISOString(),
                    type: 'status'
                });
                if (phone && smsWorkflowBindings && smsWorkflowBindings[status]) {
                    const templateData = {
                        id: currentCase.id,
                        name: currentCase.name,
                        plate: currentCase.plate,
                        amount: currentCase.amount,
                        serviceDate: serviceDate || currentCase.serviceDate
                    };
                    smsWorkflowBindings[status].forEach(template => {
                        const msg = getFormattedMessage(template.slug, templateData);
                        sendSMS(phone, msg, `${template.slug}_sms`);
                    });
                }
                // Special handling for Processing status - auto-assign schedule
                if (status === 'Processing') {
                    let assignedDate = serviceDate || currentCase.serviceDate;
                    if (!assignedDate) {
                        const today = new Date();
                        const nextDay = new Date(today);
                        nextDay.setDate(today.getDate() + 1);
                        // Skip weekends
                        if (nextDay.getDay() === 0) nextDay.setDate(nextDay.getDate() + 1);
                        if (nextDay.getDay() === 6) nextDay.setDate(nextDay.getDate() + 2);
                        nextDay.setHours(10, 0, 0, 0);
                        assignedDate = nextDay.toISOString().slice(0, 16);
                        updates.serviceDate = assignedDate;
                        updates.systemLogs.push({
                            message: `Auto-assigned service date: ${assignedDate.replace('T', ' ')}`,
                            timestamp: new Date().toISOString(),
                            type: 'info'
                        });
                    }
                }
            }
            try {
                await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', updates);
                Object.assign(currentCase, updates);
                showToast("Changes Saved", "Case has been updated successfully", "success");
                updateActivityLog();
                updateWorkflowProgress();
            } catch (error) {
                console.error('Save error:', error);
                showToast("Error", "Failed to save changes", "error");
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize icons when DOM is ready
            initializeIcons();

            // Also try to initialize when window loads (backup)
            window.addEventListener('load', function() {
                setTimeout(initializeIcons, 100);
            });
            // Update workflow progress on status change
            document.getElementById('input-status').addEventListener('change', updateWorkflowProgress);

            // Enter key for notes
            document.getElementById('new-note-input').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') addNote();
            });

            // SMS button handlers
            document.getElementById('btn-sms-register').addEventListener('click', () => {
                const phone = document.getElementById('input-phone').value;
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount
                };
                const msg = getFormattedMessage('registered', templateData);
                sendSMS(phone, msg, 'welcome');
            });

            document.getElementById('btn-sms-arrived').addEventListener('click', () => {
                const phone = document.getElementById('input-phone').value;
                const publicUrl = window.location.origin + window.location.pathname.replace('edit_case.php', 'public_view.php');
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount,
                    link: `${publicUrl}?id=${CASE_ID}`
                };
                const msg = getFormattedMessage('parts_arrived', templateData);
                sendSMS(phone, msg, 'parts_arrived');
            });

            document.getElementById('btn-sms-schedule').addEventListener('click', () => {
                const phone = document.getElementById('input-phone').value;
                const serviceDate = document.getElementById('input-service-date').value;
                if (!serviceDate) {
                    showToast('No Service Date', 'Please set a service date first', 'error');
                    return;
                }
                const date = new Date(serviceDate).toLocaleString('ka-GE', {
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                const templateData = {
                    name: currentCase.name,
                    plate: currentCase.plate,
                    amount: currentCase.amount,
                    date: date
                };
                const msg = getFormattedMessage('schedule', templateData);
                sendSMS(phone, msg, 'schedule');
            });

            // SMS Template Selector
            document.getElementById('sms-template-selector').addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const templateSlug = this.value;
                const sendButton = document.getElementById('btn-send-custom-sms');
                const previewDiv = document.getElementById('sms-preview');

                if (!templateSlug) {
                    previewDiv.innerHTML = '<span class="text-slate-400 italic">Select a template to see preview...</span>';
                    sendButton.disabled = true;
                    return;
                }

                // Get template data and format message
                const template = smsTemplates[templateSlug];
                if (template) {
                    const templateData = {
                        id: CASE_ID,
                        name: document.getElementById('input-name').value || currentCase.name,
                        plate: document.getElementById('input-plate').value || currentCase.plate,
                        amount: document.getElementById('input-amount').value || currentCase.amount,
                        serviceDate: document.getElementById('input-service-date').value || currentCase.serviceDate,
                        date: document.getElementById('input-service-date').value || currentCase.serviceDate
                    };

                    const formattedMessage = getFormattedMessage(templateSlug, templateData);
                    previewDiv.textContent = formattedMessage;
                    sendButton.disabled = false;
                }
            });

            // Send Custom SMS Button
            document.getElementById('btn-send-custom-sms').addEventListener('click', () => {
                const templateSelector = document.getElementById('sms-template-selector');
                const templateSlug = templateSelector.value;
                const phone = document.getElementById('input-phone').value;

                if (!templateSlug) {
                    showToast('No Template Selected', 'Please select an SMS template first', 'error');
                    return;
                }

                const templateData = {
                    id: CASE_ID,
                    name: document.getElementById('input-name').value || currentCase.name,
                    plate: document.getElementById('input-plate').value || currentCase.plate,
                    amount: document.getElementById('input-amount').value || currentCase.amount,
                    serviceDate: document.getElementById('input-service-date').value || currentCase.serviceDate,
                    date: document.getElementById('input-service-date').value || currentCase.serviceDate
                };

                const msg = getFormattedMessage(templateSlug, templateData);
                sendSMS(phone, msg, `custom_${templateSlug}`);
            });

            // Review Editing
            document.getElementById('btn-edit-review').addEventListener('click', () => {
                document.getElementById('review-display').classList.add('hidden');
                document.getElementById('review-edit').classList.remove('hidden');
            });

            document.getElementById('btn-cancel-review').addEventListener('click', () => {
                document.getElementById('review-edit').classList.add('hidden');
                document.getElementById('review-display').classList.remove('hidden');
            });

            document.getElementById('btn-save-review').addEventListener('click', async () => {
                const stars = document.getElementById('input-review-stars').value;
                const comment = document.getElementById('input-review-comment').value.trim();

                try {
                    await fetchAPI(`update_transfer&id=${CASE_ID}`, 'POST', {
                        reviewStars: stars || null,
                        reviewComment: comment || null
                    });

                    // Update local case data
                    currentCase.reviewStars = stars || null;
                    currentCase.reviewComment = comment || null;

                    showToast("Review Updated", "Customer review has been saved successfully", "success");

                    // Refresh the page to show updated review
                    setTimeout(() => window.location.reload(), 1000);

                } catch (error) {
                    console.error('Save review error:', error);
                    showToast("Error", "Failed to save review", "error");
                }
            });

            // Initialize sidebar navigation (keyboard and scroll aware)
            function initSidebarNav() {
                const navLinks = document.querySelectorAll('aside nav a');
                const sections = document.querySelectorAll('section[id]');
                navLinks.forEach(link => {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        const targetId = link.getAttribute('href').substring(1);
                        const targetSection = document.getElementById(targetId);
                        if (targetSection) {
                            targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            targetSection.setAttribute('tabindex', '-1');
                            targetSection.focus();
                        }
                    });
                    link.setAttribute('tabindex', '0');
                    link.setAttribute('role', 'button');
                    link.setAttribute('aria-label', link.textContent.trim());
                });
                function updateActiveNav() {
                    const scrollY = window.scrollY + 100;
                    sections.forEach(section => {
                        const sectionTop = section.offsetTop;
                        const sectionHeight = section.offsetHeight;
                        const sectionId = section.getAttribute('id');
                        if (scrollY >= sectionTop && scrollY < sectionTop + sectionHeight) {
                            navLinks.forEach(link => {
                                link.classList.remove('nav-active');
                                link.classList.add('nav-inactive');
                            });
                            const activeLink = document.querySelector(`aside nav a[href="#${sectionId}"]`);
                            if (activeLink) {
                                activeLink.classList.remove('nav-inactive');
                                activeLink.classList.add('nav-active');
                            }
                        }
                    });
                }
                window.addEventListener('scroll', updateActiveNav);
                updateActiveNav();
            }

        // Initialize all features
        updateWorkflowProgress();
        initializeIcons();
        initSidebarNav();
    </script>
</body>
</html>