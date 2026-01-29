<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'language.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Only allow technicians and admins
$allowed_roles = ['technician', 'admin', 'manager'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header('Location: index.php');
    exit();
}

$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'];

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get filter parameters
$selected_month = $_GET['month'] ?? '';  // Empty by default to show all
$selected_technician = $_GET['technician'] ?? '';

// Build query for completed cases with nachrebi_qty
$query = "SELECT 
    name as customer_name,
    plate,
    nachrebi_qty,
    amount,
    franchise,
    status,
    service_date,
    created_at,
    assigned_mechanic,
    id
FROM transfers 
WHERE nachrebi_qty > 0 AND status = 'Completed'";

$params = [];

// Technician filter - technicians only see their own, admins can filter by technician
if ($current_user_role === 'technician') {
    $query .= " AND assigned_mechanic = ?";
    $params[] = $current_user_name;
} elseif ($selected_technician) {
    $query .= " AND assigned_mechanic = ?";
    $params[] = $selected_technician;
}

// Month filter
if ($selected_month) {
    $query .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $params[] = $selected_month;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_nachrebi = array_sum(array_column($records, 'nachrebi_qty'));
$total_amount = $total_nachrebi * 77; // Calculate amount as nachrebi_qty × 77

// Get available months for filter dropdown
$months_query = "SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month 
    FROM transfers 
    WHERE nachrebi_qty > 0 AND status = 'Completed'";

// Filter months by technician if applicable
if ($current_user_role === 'technician') {
    $months_query .= " AND assigned_mechanic = ?";
    $months_stmt = $pdo->prepare($months_query . " ORDER BY month DESC");
    $months_stmt->execute([$current_user_name]);
} else {
    $months_stmt = $pdo->query($months_query . " ORDER BY month DESC");
}

$available_months = $months_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get list of technicians for admin/manager filter
$technicians = [];
$all_technicians = [];
if ($current_user_role !== 'technician') {
    // Get technicians from filter (those who have completed cases)
    $tech_stmt = $pdo->query("SELECT DISTINCT assigned_mechanic FROM transfers WHERE assigned_mechanic IS NOT NULL AND assigned_mechanic != '' AND nachrebi_qty > 0 AND status = 'Completed' ORDER BY assigned_mechanic");
    $technicians = $tech_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all technicians from users table for assignment dropdown
    $all_tech_stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'technician' AND status = 'active' ORDER BY full_name");
    $all_technicians = $all_tech_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get summary by technician (for admin/manager view)
$technician_summary = [];
$consumables_costs = [];
if ($current_user_role !== 'technician') {
    $summary_query = "SELECT 
        assigned_mechanic,
        COUNT(*) as total_cases,
        SUM(nachrebi_qty) as total_nachrebi
    FROM transfers 
    WHERE nachrebi_qty > 0 AND status = 'Completed' AND assigned_mechanic IS NOT NULL AND assigned_mechanic != ''";
    
    if ($selected_month) {
        $summary_query .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
        $summary_stmt = $pdo->prepare($summary_query . " GROUP BY assigned_mechanic ORDER BY total_nachrebi DESC");
        $summary_stmt->execute([$selected_month]);
    } else {
        $summary_stmt = $pdo->query($summary_query . " GROUP BY assigned_mechanic ORDER BY total_nachrebi DESC");
    }
    $technician_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load consumables costs for the selected month (with error handling if table doesn't exist)
    try {
        if ($selected_month) {
            $cost_stmt = $pdo->prepare("SELECT * FROM `consumables_costs` WHERE `year_month` = ?");
            $cost_stmt->execute([$selected_month]);
        } else {
            $cost_stmt = $pdo->query("SELECT * FROM `consumables_costs` ORDER BY `year_month` DESC");
        }
        $consumables_data = $cost_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Index by technician name for easy lookup
        foreach ($consumables_data as $cc) {
            $consumables_costs[$cc['technician_name']] = $cc;
        }
    } catch (Exception $e) {
        // Table might not exist yet - ignore the error
        $consumables_costs = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ნაჭრების რაოდენობა - რეპორტი</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-border { border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body class="bg-slate-50">
    
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-slate-600 hover:text-slate-900">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">ნაჭრების რაოდენობა - რეპორტი</h1>
                        <?php if ($current_user_role === 'technician'): ?>
                            <p class="text-sm text-slate-500 mt-1">ტექნიკოსი: <?= htmlspecialchars($current_user_name) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-sm text-slate-600"><?= htmlspecialchars($current_user_name) ?></span>
                    <span class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700"><?= htmlspecialchars($current_user_role) ?></span>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6 no-print">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                
                <!-- Month Filter -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>
                        თვე
                    </label>
                    <select name="month" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">ყველა თვე</option>
                        <?php foreach ($available_months as $month): ?>
                            <option value="<?= $month ?>" <?= $month === $selected_month ? 'selected' : '' ?>>
                                <?= date('F Y', strtotime($month . '-01')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Technician Filter (Admin/Manager only) -->
                <?php if ($current_user_role !== 'technician'): ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        <i data-lucide="user" class="w-4 h-4 inline mr-1"></i>
                        ტექნიკოსი
                    </label>
                    <select name="technician" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">ყველა ტექნიკოსი</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?= htmlspecialchars($tech) ?>" <?= $tech === $selected_technician ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tech) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="flex items-end space-x-2 <?= $current_user_role !== 'technician' ? '' : 'md:col-span-2' ?>">
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center">
                        <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                        ფილტრი
                    </button>
                    <a href="nachrebi_report.php" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 transition">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </a>
                    <button type="button" onclick="window.print()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Technician Summary (Admin/Manager only) -->
        <?php if ($current_user_role !== 'technician' && count($technician_summary) > 0): ?>
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-slate-800 flex items-center">
                    <i data-lucide="users" class="w-5 h-5 mr-2 text-blue-600"></i>
                    ტექნიკოსების შეჯამება
                    <?php if ($selected_month): ?>
                        <span class="text-sm font-normal text-slate-500 ml-2">(<?= date('F Y', strtotime($selected_month . '-01')) ?>)</span>
                    <?php endif; ?>
                </h2>
                <?php if ($selected_month): ?>
                <button onclick="openConsumablesModal()" class="px-3 py-1.5 bg-orange-500 text-white text-sm rounded-lg hover:bg-orange-600 transition flex items-center gap-1">
                    <i data-lucide="package-plus" class="w-4 h-4"></i>
                    სახარჯი მასალები
                </button>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($technician_summary as $tech): 
                    $tech_name = $tech['assigned_mechanic'];
                    $nachrebi_amount = $tech['total_nachrebi'] * 77;
                    $consumable_cost = isset($consumables_costs[$tech_name]) ? floatval($consumables_costs[$tech_name]['cost']) : 0;
                    $net_amount = $nachrebi_amount - $consumable_cost;
                ?>
                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200 hover:border-blue-300 transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($tech_name) ?></span>
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full"><?= $tech['total_cases'] ?> შეკვეთა</span>
                    </div>
                    <div class="text-2xl font-bold text-emerald-600 mb-2">
                        <?= number_format($tech['total_nachrebi'], 2) ?> <span class="text-sm font-normal text-slate-500">ნაჭერი</span>
                    </div>
                    <div class="text-sm text-slate-600 border-t border-slate-200 pt-2 mt-2 space-y-1">
                        <div class="flex justify-between">
                            <span>ნაჭრები (×77):</span>
                            <span class="font-medium">₾<?= number_format($nachrebi_amount, 2) ?></span>
                        </div>
                        <?php if ($selected_month): ?>
                        <div class="flex justify-between text-orange-600">
                            <span>სახარჯი მასალა:</span>
                            <span class="font-medium">- ₾<?= number_format($consumable_cost, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-blue-700 font-bold border-t border-slate-200 pt-1">
                            <span>სულ:</span>
                            <span>₾<?= number_format($net_amount, 2) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">დასრულებული შეკვეთები</p>
                        <p class="text-3xl font-bold text-slate-900"><?= count($records) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">სულ ნაჭრები</p>
                        <p class="text-3xl font-bold text-emerald-600"><?= number_format($total_nachrebi, 2) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="package" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">სულ თანხა</p>
                        <p class="text-3xl font-bold text-blue-600">₾<?= number_format($total_amount, 2) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="dollar-sign" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden print-border">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">მომხმარებელი</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">მანქანის ნომერი</th>
                            <?php if ($current_user_role !== 'technician'): ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">ტექნიკოსი</th>
                            <?php endif; ?>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">ნაჭრების რაოდ.</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">თანხა</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase tracking-wider">თარიღი</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (count($records) > 0): ?>
                            <?php foreach ($records as $record): ?>
                                <tr class="hover:bg-slate-50 transition" id="row-<?= $record['id'] ?>">
                                    <td class="px-4 py-3 text-sm font-medium text-slate-900">#<?= $record['id'] ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-900"><?= htmlspecialchars($record['customer_name']) ?></td>
                                    <td class="px-4 py-3 text-sm font-mono text-slate-900"><?= htmlspecialchars($record['plate']) ?></td>
                                    <?php if ($current_user_role !== 'technician'): ?>
                                    <td class="px-4 py-3 text-sm text-slate-700">
                                        <select 
                                            class="technician-select text-xs border border-slate-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white min-w-[140px]"
                                            data-case-id="<?= $record['id'] ?>"
                                            onchange="assignTechnician(this)"
                                        >
                                            <option value="">-- არჩევა --</option>
                                            <?php foreach ($all_technicians as $tech): ?>
                                                <option value="<?= htmlspecialchars($tech['full_name']) ?>" <?= ($record['assigned_mechanic'] === $tech['full_name']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($tech['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="save-indicator hidden text-green-600 ml-2">
                                            <i data-lucide="check" class="w-4 h-4 inline"></i>
                                        </span>
                                    </td>
                                    <?php endif; ?>
                                    <td class="px-4 py-3 text-sm font-bold text-emerald-600"><?= number_format($record['nachrebi_qty'], 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-900">₾<?= number_format($record['nachrebi_qty'] * 77, 2) ?></td>
                                    <td class="px-4 py-3 text-sm text-slate-600">
                                        <?= date('d/m/Y', strtotime($record['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $current_user_role !== 'technician' ? '7' : '6' ?>" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-slate-400">
                                        <i data-lucide="inbox" class="w-12 h-12 mb-3"></i>
                                        <p class="text-sm">დასრულებული შეკვეთები არ მოიძებნა</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Assign technician function
        async function assignTechnician(selectElement) {
            const caseId = selectElement.dataset.caseId;
            const technicianName = selectElement.value;
            const row = document.getElementById('row-' + caseId);
            const indicator = row.querySelector('.save-indicator');
            
            // Disable select during save
            selectElement.disabled = true;
            selectElement.classList.add('opacity-50');
            
            try {
                const response = await fetch('api.php?action=update_transfer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: caseId,
                        assigned_mechanic: technicianName || null
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    // Show success indicator
                    indicator.classList.remove('hidden');
                    setTimeout(() => {
                        indicator.classList.add('hidden');
                    }, 2000);
                    
                    // Flash row green
                    row.classList.add('bg-green-50');
                    setTimeout(() => {
                        row.classList.remove('bg-green-50');
                    }, 1000);
                } else {
                    alert('შეცდომა: ' + (result.error || 'ვერ მოხერხდა შენახვა'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('შეცდომა: ვერ მოხერხდა შენახვა');
            } finally {
                selectElement.disabled = false;
                selectElement.classList.remove('opacity-50');
                lucide.createIcons();
            }
        }

        // Consumables Cost Management
        const selectedMonth = '<?= $selected_month ?>';
        const allTechnicians = <?= json_encode(array_column($all_technicians, 'full_name')) ?>;
        let consumablesCosts = <?= json_encode($consumables_costs) ?>;

        function openConsumablesModal() {
            document.getElementById('consumablesModal').classList.remove('hidden');
            renderConsumablesTable();
        }

        function closeConsumablesModal() {
            document.getElementById('consumablesModal').classList.add('hidden');
        }

        function renderConsumablesTable() {
            const tbody = document.getElementById('consumablesTableBody');
            tbody.innerHTML = '';
            
            allTechnicians.forEach(tech => {
                const existing = consumablesCosts[tech] || { cost: 0, notes: '' };
                const row = document.createElement('tr');
                row.className = 'border-b border-slate-100';
                row.innerHTML = `
                    <td class="px-4 py-3 text-sm font-medium text-slate-900">${tech}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center">
                            <span class="text-slate-500 mr-1">₾</span>
                            <input type="number" step="0.01" min="0" 
                                class="consumable-cost-input w-28 px-2 py-1 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                data-technician="${tech}"
                                value="${parseFloat(existing.cost || 0).toFixed(2)}"
                                placeholder="0.00">
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <input type="text" 
                            class="consumable-notes-input w-full px-2 py-1 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            data-technician="${tech}"
                            value="${existing.notes || ''}"
                            placeholder="შენიშვნა...">
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="saveConsumableCost('${tech}')" 
                            class="px-3 py-1 bg-emerald-500 text-white text-xs rounded hover:bg-emerald-600 transition">
                            შენახვა
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        async function saveConsumableCost(technicianName) {
            const costInput = document.querySelector(`.consumable-cost-input[data-technician="${technicianName}"]`);
            const notesInput = document.querySelector(`.consumable-notes-input[data-technician="${technicianName}"]`);
            
            const cost = Math.round((parseFloat(costInput.value) || 0) * 100) / 100; // Round to 2 decimal places
            const notes = notesInput.value || '';
            
            try {
                const response = await fetch('api.php?action=save_consumables_cost', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        technician_name: technicianName,
                        year_month: selectedMonth,
                        cost: cost,
                        notes: notes
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    // Update local data
                    consumablesCosts[technicianName] = { cost: cost, notes: notes };
                    
                    // Flash success
                    costInput.classList.add('bg-green-100');
                    notesInput.classList.add('bg-green-100');
                    setTimeout(() => {
                        costInput.classList.remove('bg-green-100');
                        notesInput.classList.remove('bg-green-100');
                    }, 1000);
                    
                    alert('შენახულია!');
                } else {
                    alert('შეცდომა: ' + (result.message || 'ვერ მოხერხდა შენახვა'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('შეცდომა: ვერ მოხერხდა შენახვა');
            }
        }

        async function saveAllConsumables() {
            const costInputs = document.querySelectorAll('.consumable-cost-input');
            let saved = 0;
            
            for (const costInput of costInputs) {
                const tech = costInput.dataset.technician;
                const notesInput = document.querySelector(`.consumable-notes-input[data-technician="${tech}"]`);
                const cost = Math.round((parseFloat(costInput.value) || 0) * 100) / 100; // Round to 2 decimal places
                const notes = notesInput.value || '';
                
                try {
                    const response = await fetch('api.php?action=save_consumables_cost', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            technician_name: tech,
                            year_month: selectedMonth,
                            cost: cost,
                            notes: notes
                        })
                    });
                    
                    const result = await response.json();
                    if (result.status === 'success') {
                        consumablesCosts[tech] = { cost: cost, notes: notes };
                        saved++;
                    }
                } catch (error) {
                    console.error('Error saving for ' + tech + ':', error);
                }
            }
            
            alert(`შენახულია ${saved} ჩანაწერი!`);
            closeConsumablesModal();
            window.location.reload();
        }
    </script>

    <!-- Consumables Cost Modal -->
    <?php if ($current_user_role !== 'technician'): ?>
    <div id="consumablesModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center no-print">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-hidden">
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i data-lucide="package" class="w-5 h-5"></i>
                    სახარჯი მასალების ხარჯი
                    <?php if ($selected_month): ?>
                        <span class="text-sm font-normal opacity-90">(<?= date('F Y', strtotime($selected_month . '-01')) ?>)</span>
                    <?php endif; ?>
                </h3>
                <button onclick="closeConsumablesModal()" class="text-white hover:text-orange-200 transition">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <?php if (!$selected_month): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <p class="text-yellow-800 text-sm">
                        <i data-lucide="alert-triangle" class="w-4 h-4 inline mr-1"></i>
                        სახარჯი მასალების დასამატებლად გთხოვთ აირჩიოთ კონკრეტული თვე ფილტრში.
                    </p>
                </div>
                <?php else: ?>
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase">ტექნიკოსი</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase">ხარჯი</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase">შენიშვნა</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-slate-600 uppercase"></th>
                        </tr>
                    </thead>
                    <tbody id="consumablesTableBody">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php if ($selected_month): ?>
            <div class="bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t">
                <button onclick="closeConsumablesModal()" class="px-4 py-2 text-slate-700 bg-slate-200 rounded-lg hover:bg-slate-300 transition">
                    გაუქმება
                </button>
                <button onclick="saveAllConsumables()" class="px-4 py-2 text-white bg-orange-500 rounded-lg hover:bg-orange-600 transition flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    ყველას შენახვა
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
