<?php
/**
 * Public Invoice View
 * Shareable link for customers to view their invoice details
 */
error_reporting(0); // Suppress errors on production

// Include config - try multiple paths
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config.php';
}
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Configuration error</h1></body></html>');
}

// Get database connection
try {
    if (function_exists('getDBConnection')) {
        $pdo = getDBConnection();
    } elseif (!isset($pdo)) {
        // Fallback - create connection directly
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
} catch (Exception $e) {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Database connection error</h1></body></html>');
}

$case_id = intval($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');

if (($case_id <= 0 && empty($slug)) || (!empty($slug) && (strlen($slug) < 3 || strlen($slug) > 100))) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>Invoice not found</h1></body></html>');
}

try {
    if (!empty($slug)) {
        // Look up by slug
        $stmt = $pdo->prepare("SELECT * FROM transfers WHERE slug = ?");
        $stmt->execute([$slug]);
    } else {
        // Look up by ID (legacy support)
        $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
        $stmt->execute([$case_id]);
    }
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$case) {
        http_response_code(404);
        die('<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>Invoice not found</h1></body></html>');
    }
    
    // Set case_id from database if not provided via GET
    $case_id = $case['id'];
    
    // ── Load Case Versions ──
    $all_versions = [];
    $active_version = null;
    $selected_version = null;
    $version_param = isset($_GET['version']) ? intval($_GET['version']) : 0;

    try {
        // Check if case_versions table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'case_versions'");
        if ($tableCheck->rowCount() > 0) {
            $vstmt = $pdo->prepare("SELECT * FROM case_versions WHERE transfer_id = ? ORDER BY is_active DESC, created_at DESC");
            $vstmt->execute([$case_id]);
            $all_versions = $vstmt->fetchAll(PDO::FETCH_ASSOC);

            // Find active version
            foreach ($all_versions as $v) {
                if ($v['is_active']) { $active_version = $v; break; }
            }

            // Determine which version to display
            if ($version_param > 0) {
                foreach ($all_versions as $v) {
                    if ($v['id'] == $version_param) { $selected_version = $v; break; }
                }
                // Fall back to active version if the param doesn't match any version
                if (!$selected_version && $active_version) {
                    $selected_version = $active_version;
                }
            } elseif ($active_version) {
                $selected_version = $active_version;
            }
        }
    } catch (Exception $e) {
        // Silently ignore - table may not exist yet
    }

    // Decode JSON fields - use version data if available, otherwise case data
    $repair_parts = [];
    $repair_labor = [];
    $using_version = false;
    $version_name = '';

    if ($selected_version) {
        $using_version = true;
        $version_name = $selected_version['version_name'] ?? '';
        if (!empty($selected_version['repair_parts'])) {
            $decoded = json_decode($selected_version['repair_parts'], true);
            if (is_array($decoded)) $repair_parts = $decoded;
        }
        if (!empty($selected_version['repair_labor'])) {
            $decoded = json_decode($selected_version['repair_labor'], true);
            if (is_array($decoded)) $repair_labor = $decoded;
        }
    } else {
        if (!empty($case['repair_parts'])) {
            $decoded = json_decode($case['repair_parts'], true);
            if (is_array($decoded)) $repair_parts = $decoded;
        }
        if (!empty($case['repair_labor'])) {
            $decoded = json_decode($case['repair_labor'], true);
            if (is_array($decoded)) $repair_labor = $decoded;
        }
    }

    // Get discount percentages - from version or case
    if ($selected_version) {
        $parts_discount_pct = floatval($selected_version['parts_discount_percent'] ?? 0);
        $services_discount_pct = floatval($selected_version['services_discount_percent'] ?? 0);
        $global_discount_pct = floatval($selected_version['global_discount_percent'] ?? 0);
    } else {
        $parts_discount_pct = isset($case['parts_discount_percent']) ? floatval($case['parts_discount_percent']) : 0;
        $services_discount_pct = isset($case['services_discount_percent']) ? floatval($case['services_discount_percent']) : 0;
        $global_discount_pct = isset($case['global_discount_percent']) ? floatval($case['global_discount_percent']) : 0;
    }
    
    // Get case images
    $case_images = [];
    if (!empty($case['case_images'])) {
        $decoded = json_decode($case['case_images'], true);
        if (is_array($decoded)) $case_images = $decoded;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Error loading invoice</h1></body></html>');
}

// Calculate totals with discounts
$parts_subtotal = 0;
foreach ($repair_parts as $part) {
    $qty = floatval($part['quantity'] ?? 1);
    $price = floatval($part['unit_price'] ?? 0);
    $item_discount = floatval($part['discount_percent'] ?? 0);
    $item_total = $qty * $price * (1 - $item_discount / 100);
    $parts_subtotal += $item_total;
}

$services_subtotal = 0;
foreach ($repair_labor as $labor) {
    $qty = floatval($labor['quantity'] ?? $labor['hours'] ?? 1);
    $rate = floatval($labor['unit_rate'] ?? $labor['hourly_rate'] ?? 0);
    $item_discount = floatval($labor['discount_percent'] ?? 0);
    $item_total = $qty * $rate * (1 - $item_discount / 100);
    $services_subtotal += $item_total;
}

$subtotal = $parts_subtotal + $services_subtotal;

// Apply category discounts
$parts_discount_amount = $parts_subtotal * ($parts_discount_pct / 100);
$services_discount_amount = $services_subtotal * ($services_discount_pct / 100);
$after_category_discounts = $subtotal - $parts_discount_amount - $services_discount_amount;

// Apply global discount
$global_discount_amount = $after_category_discounts * ($global_discount_pct / 100);
$total_discount = $parts_discount_amount + $services_discount_amount + $global_discount_amount;
$grand_total = $after_category_discounts - $global_discount_amount;

// Calculate VAT if enabled (18% of grand total)
$vat_enabled = $selected_version ? (bool)($selected_version['vat_enabled'] ?? false) : (isset($case['vat_enabled']) ? (bool)$case['vat_enabled'] : false);
$vat_amount = $vat_enabled ? $grand_total * 0.18 : 0;
$final_total = $grand_total + $vat_amount;

// Format date safely
$invoice_date = !empty($case['created_at']) ? date('d.m.Y', strtotime($case['created_at'])) : date('d.m.Y');
$service_date = !empty($case['service_date']) ? date('d.m.Y H:i', strtotime($case['service_date'])) : 'Not scheduled';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ინვოისი #<?php echo $case_id; ?> | OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php 
    $fontsPath = __DIR__ . '/fonts/include_fonts.php';
    if (file_exists($fontsPath)) {
        include $fontsPath;
    }
    ?>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['BPG Arial', 'Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-break { page-break-after: always; }
        }
        /* Signature canvas touch handling */
        #canvas-wrapper { touch-action: none; }
        #signature-canvas { touch-action: none; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen py-8 px-4 font-sans">
    <div class="max-w-3xl mx-auto">
        <!-- Invoice Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">OTOMOTORS</h1>
                        <p class="text-blue-100 text-sm mt-1">ავტომობილის სერვისი</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-blue-100">ინვოისი</div>
                        <div class="text-2xl font-bold">#<?php echo $case_id; ?></div>
                        <?php if ($using_version): ?>
                        <div class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 bg-white/20 rounded-full text-xs text-white/90">
                            <i data-lucide="layers" class="w-3 h-3"></i>
                            <?php echo htmlspecialchars($version_name); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (count($all_versions) > 0): ?>
            <!-- Version Selector Bar -->
            <div class="px-8 py-4 bg-gradient-to-r from-violet-50 to-purple-50 border-b border-violet-200/60 no-print">
                <div class="flex items-center gap-2 mb-2">
                    <i data-lucide="layers" class="w-4 h-4 text-violet-600"></i>
                    <span class="text-sm font-semibold text-violet-800">ფასის ვარიანტები</span>
                    <span class="text-xs text-violet-500">(<?php echo count($all_versions); ?>)</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php
                    // Build base URL
                    $base_params = [];
                    if (!empty($slug)) $base_params['slug'] = $slug;
                    else $base_params['id'] = $case_id;

                    foreach ($all_versions as $v):
                        $v_params = $base_params;
                        $v_params['version'] = $v['id'];
                        $v_url = '?' . http_build_query($v_params);
                        $is_selected = $selected_version && $selected_version['id'] == $v['id'];
                        $is_active_v = $v['is_active'];

                        // Compute quick total for this version
                        $v_parts = json_decode($v['repair_parts'] ?? '[]', true) ?: [];
                        $v_labor = json_decode($v['repair_labor'] ?? '[]', true) ?: [];
                        $v_pt = 0; foreach ($v_parts as $p) { $v_pt += (floatval($p['quantity']??1)) * (floatval($p['unit_price']??0)) * (1 - (floatval($p['discount_percent']??0))/100); }
                        $v_lt = 0; foreach ($v_labor as $l) { $v_lt += (floatval($l['quantity']??$l['hours']??1)) * (floatval($l['unit_rate']??$l['hourly_rate']??0)) * (1 - (floatval($l['discount_percent']??0))/100); }
                        $v_pd = floatval($v['parts_discount_percent']??0); $v_sd = floatval($v['services_discount_percent']??0); $v_gd = floatval($v['global_discount_percent']??0);
                        $v_after = ($v_pt*(1-$v_pd/100)) + ($v_lt*(1-$v_sd/100));
                        $v_grand = $v_after * (1-$v_gd/100);
                        if ($v['vat_enabled']) $v_grand *= 1.18;
                    ?>
                    <a href="<?php echo htmlspecialchars($v_url); ?>"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium transition-all border-2 <?php echo $is_selected
                            ? 'bg-violet-600 text-white border-violet-600 shadow-lg shadow-violet-200'
                            : 'bg-white text-slate-700 border-slate-200 hover:border-violet-300 hover:bg-violet-50'; ?>">
                        <?php if ($is_active_v): ?>
                        <span class="w-2 h-2 rounded-full <?php echo $is_selected ? 'bg-white' : 'bg-violet-500'; ?> shrink-0"></span>
                        <?php endif; ?>
                        <span class="truncate max-w-[120px]"><?php echo htmlspecialchars($v['version_name']); ?></span>
                        <span class="<?php echo $is_selected ? 'text-violet-200' : 'text-slate-400'; ?> font-bold">₾<?php echo number_format($v_grand, 2); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Customer & Vehicle Info -->
            <div class="px-8 py-6 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">მომხმარებელი</h3>
                        <p class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($case['name'] ?? 'N/A'); ?></p>
                        <?php if (!empty($case['phone'])): ?>
                        <p class="text-gray-600 flex items-center gap-2 mt-1">
                            <i data-lucide="phone" class="w-4 h-4"></i>
                            <?php echo htmlspecialchars($case['phone']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">ავტომობილი</h3>
                        <div class="flex items-center gap-3">
                            <div class="bg-blue-100 px-4 py-2 rounded-lg border-2 border-blue-200">
                                <span class="font-bold text-blue-800 text-lg"><?php echo htmlspecialchars($case['plate'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                        <?php if (!empty($case['vehicle_make']) || !empty($case['vehicle_model'])): ?>
                        <p class="text-gray-600 mt-2">
                            <?php echo htmlspecialchars(trim(($case['vehicle_make'] ?? '') . ' ' . ($case['vehicle_model'] ?? ''))); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-100">
                    <div>
                        <span class="text-sm text-gray-500">ინვოისის თარიღი:</span>
                        <span class="font-medium text-gray-800 ml-2"><?php echo $invoice_date; ?></span>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">სერვისის თარიღი:</span>
                        <span class="font-medium text-gray-800 ml-2"><?php echo $service_date; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <div class="px-8 py-6">
                <?php if (count($repair_parts) > 0): ?>
                <!-- Parts Section -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3 flex items-center gap-2">
                        <i data-lucide="package" class="w-4 h-4"></i>
                        ნაწილები
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b-2 border-gray-200">
                                    <th class="text-left py-2 font-semibold text-gray-600">აღწერა</th>
                                    <th class="text-center py-2 font-semibold text-gray-600 w-20">რაოდ.</th>
                                    <th class="text-right py-2 font-semibold text-gray-600 w-24">ფასი</th>
                                    <th class="text-right py-2 font-semibold text-gray-600 w-24">ჯამი</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repair_parts as $part): 
                                    $qty = floatval($part['quantity'] ?? 1);
                                    $price = floatval($part['unit_price'] ?? 0);
                                    $item_discount = floatval($part['discount_percent'] ?? 0);
                                    $item_total = $qty * $price;
                                    $discounted_total = $item_total * (1 - $item_discount / 100);
                                ?>
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 text-gray-800">
                                        <?php echo htmlspecialchars($part['name'] ?? 'Unnamed Part'); ?>
                                        <?php if ($item_discount > 0): ?>
                                        <span class="ml-2 text-xs text-red-500 font-medium">-<?php echo $item_discount; ?>%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-center text-gray-600"><?php echo $qty; ?></td>
                                    <td class="py-3 text-right text-gray-600">₾<?php echo number_format($price, 2); ?></td>
                                    <td class="py-3 text-right font-medium text-gray-800">
                                        <?php if ($item_discount > 0): ?>
                                        <span class="line-through text-gray-400 text-xs mr-1">₾<?php echo number_format($item_total, 2); ?></span>
                                        <?php endif; ?>
                                        ₾<?php echo number_format($discounted_total, 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (count($repair_labor) > 0): ?>
                <!-- Services Section -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3 flex items-center gap-2">
                        <i data-lucide="wrench" class="w-4 h-4"></i>
                        მომსახურება
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b-2 border-gray-200">
                                    <th class="text-left py-2 font-semibold text-gray-600">აღწერა</th>
                                    <th class="text-center py-2 font-semibold text-gray-600 w-20">რაოდ.</th>
                                    <th class="text-right py-2 font-semibold text-gray-600 w-24">ფასი</th>
                                    <th class="text-right py-2 font-semibold text-gray-600 w-24">ჯამი</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repair_labor as $labor): 
                                    $qty = floatval($labor['quantity'] ?? $labor['hours'] ?? 1);
                                    $rate = floatval($labor['unit_rate'] ?? $labor['hourly_rate'] ?? 0);
                                    $item_discount = floatval($labor['discount_percent'] ?? 0);
                                    $item_total = $qty * $rate;
                                    $discounted_total = $item_total * (1 - $item_discount / 100);
                                ?>
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 text-gray-800">
                                        <?php echo htmlspecialchars($labor['description'] ?? 'Service'); ?>
                                        <?php if ($item_discount > 0): ?>
                                        <span class="ml-2 text-xs text-red-500 font-medium">-<?php echo $item_discount; ?>%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-center text-gray-600"><?php echo $qty; ?></td>
                                    <td class="py-3 text-right text-gray-600">₾<?php echo number_format($rate, 2); ?></td>
                                    <td class="py-3 text-right font-medium text-gray-800">
                                        <?php if ($item_discount > 0): ?>
                                        <span class="line-through text-gray-400 text-xs mr-1">₾<?php echo number_format($item_total, 2); ?></span>
                                        <?php endif; ?>
                                        ₾<?php echo number_format($discounted_total, 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (count($repair_parts) === 0 && count($repair_labor) === 0): ?>
                <div class="text-center py-8 text-gray-500">
                    <i data-lucide="file-text" class="w-12 h-12 mx-auto mb-3 text-gray-300"></i>
                    <p>ინვოისის დეტალები ჯერ არ არის დამატებული</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Totals Section -->
            <div class="px-8 py-6 bg-gray-50 border-t border-gray-200 overflow-hidden">
                <div class="w-full">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                        <!-- Left: Bank panel (visible only when VAT enabled) -->
                        <div class="md:col-span-2 min-w-0">
                            <?php if ($vat_enabled && $vat_amount > 0): ?>
                            <div class="rounded-xl bg-white p-6 border border-gray-200 shadow-sm h-full min-w-0 overflow-hidden">
                                <h4 class="text-sm font-semibold text-gray-600 mb-2">ბანკის მონაცემები</h4>
                                <p class="text-xs text-gray-500">გთხოვთ, გადახდისას გადაამოწმოთ და გამოიყენოთ ქვემოთ მოცემული IBAN-ები</p>
                                <div class="mt-4 grid gap-3">
                                    <div class="flex items-center justify-between bg-gray-50 border border-gray-100 rounded-lg px-4 py-3">
                                        <div>
                                            <div class="text-sm text-gray-500">საქართველოს ბანკი</div>
                                            <div class="font-mono font-semibold text-gray-800 break-words">GE94BG0000000100727119</div>
                                        </div>
                                        <button class="text-sm text-indigo-600 font-medium ml-4 shrink-0 flex items-center gap-1" onclick="(function(btn){ if (!navigator.clipboard) return; navigator.clipboard.writeText('GE94BG0000000100727119').then(function(){ btn.querySelector('span').innerText='კოპირებულია'; btn.querySelector('i').setAttribute('data-lucide', 'check'); btn.classList.add('text-green-600'); btn.classList.remove('text-indigo-600'); lucide.createIcons(); setTimeout(function(){ btn.querySelector('span').innerText='კოპირება'; btn.querySelector('i').setAttribute('data-lucide', 'copy'); btn.classList.remove('text-green-600'); btn.classList.add('text-indigo-600'); lucide.createIcons(); }, 1500); }).catch(function(){}); })(this)">
                                            <span></span>
                                            <i data-lucide="copy" class="w-4 h-4"></i>
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-between bg-gray-50 border border-gray-100 rounded-lg px-4 py-3">
                                        <div>
                                            <div class="text-sm text-gray-500">თიბისი ბანკი</div>
                                            <div class="font-mono font-semibold text-gray-800 break-words">GE64TB7669336080100009</div>
                                        </div>
                                        <button class="text-sm text-indigo-600 font-medium ml-4 shrink-0 flex items-center gap-1" onclick="(function(btn){ if (!navigator.clipboard) return; navigator.clipboard.writeText('GE64TB7669336080100009').then(function(){ btn.querySelector('span').innerText='კოპირებულია'; btn.querySelector('i').setAttribute('data-lucide', 'check'); btn.classList.add('text-green-600'); btn.classList.remove('text-indigo-600'); lucide.createIcons(); setTimeout(function(){ btn.querySelector('span').innerText='კოპირება'; btn.querySelector('i').setAttribute('data-lucide', 'copy'); btn.classList.remove('text-green-600'); btn.classList.add('text-indigo-600'); lucide.createIcons(); }, 1500); }).catch(function(){}); })(this)">
                                            <span></span>
                                            <i data-lucide="copy" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right: Summary card -->
                        <div class="md:col-span-1 min-w-0">
                            <div class="rounded-xl bg-white shadow p-4 min-w-0 overflow-hidden">
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-500 uppercase">საფასურის მიმოხილვა</h3>
                                        <p class="text-xs text-gray-400">ფასების მოკლე მიმოხილვა</p>
                                    </div>
                                    <button id="toggle-invoice-details" onclick="(function(btn){const el=document.getElementById('invoice-details'); el.classList.toggle('hidden'); const isHidden = el.classList.contains('hidden'); btn.querySelector('span').innerText = isHidden ? '' : ''; const icon = btn.querySelector('i'); icon.setAttribute('data-lucide', isHidden ? 'chevron-down' : 'chevron-up'); lucide.createIcons();})(this)" class="text-sm text-indigo-600 hover:underline no-print shrink-0 ml-2 flex items-center gap-1">
                                        <span></span>
                                        <i data-lucide="chevron-up" class="w-4 h-4"></i>
                                    </button>
                                </div>

                                <div id="invoice-details" class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">ნაწილები</span>
                                        <span class="font-medium">₾<?php echo number_format($parts_subtotal, 2); ?></span>
                                    </div>

                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">მომსახურება</span>
                                        <span class="font-medium">₾<?php echo number_format($services_subtotal, 2); ?></span>
                                    </div>

                                    <div class="flex justify-between text-sm pt-2 border-t border-gray-100">
                                        <span class="text-gray-600">ჯამი</span>
                                        <span class="font-medium">₾<?php echo number_format($subtotal, 2); ?></span>
                                    </div>

                                    <?php if ($total_discount > 0): ?>
                                    <div class="pt-2">
                                        <?php if ($parts_discount_pct > 0): ?>
                                        <div class="flex justify-between text-sm text-red-600">
                                            <span>ნაწილების ფასდაკლება (<?php echo $parts_discount_pct; ?>%)</span>
                                            <span>-₾<?php echo number_format($parts_discount_amount, 2); ?></span>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($services_discount_pct > 0): ?>
                                        <div class="flex justify-between text-sm text-red-600">
                                            <span>მომსახურების ფასდაკლება (<?php echo $services_discount_pct; ?>%)</span>
                                            <span>-₾<?php echo number_format($services_discount_amount, 2); ?></span>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($global_discount_pct > 0): ?>
                                        <div class="flex justify-between text-sm text-red-600">
                                            <span>საერთო ფასდაკლება (<?php echo $global_discount_pct; ?>%)</span>
                                            <span>-₾<?php echo number_format($global_discount_amount, 2); ?></span>
                                        </div>
                                        <?php endif; ?>

                                        <div class="flex justify-between text-sm font-medium text-red-600 pt-1">
                                            <span>დაზოგილი თანხა</span>
                                            <span>-₾<?php echo number_format($total_discount, 2); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-4 border-t pt-3">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="text-sm text-gray-500">ჯამი (დღგ-ის გარეშე)</div>
                                            <div class="text-lg font-semibold text-gray-800">₾<?php echo number_format($grand_total, 2); ?></div>
                                        </div>
                                        <?php if ($vat_enabled && $vat_amount > 0): ?>
                                        <div class="text-right ml-4">
                                            <div class="text-sm text-orange-700">დღგ (18%)</div>
                                            <div class="text-lg font-semibold text-orange-600">₾<?php echo number_format($vat_amount, 2); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-3 rounded-xl bg-orange-50 p-4 border border-orange-200/50">
                                        <div class="flex flex-wrap items-end justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium text-gray-700 truncate">საბოლოო გადასახდელი</div>
                                                <div class="text-lg font-bold text-gray-900"><?php echo $vat_enabled && $vat_amount > 0 ? 'დღგ-ით' : ''; ?></div>
                                            </div>
                                            <div class="text-3xl font-extrabold text-orange-600 text-right shrink-0">₾<?php echo number_format($final_total, 2); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (count($case_images) > 0): ?>
            <!-- Photos Section -->
            <div class="px-8 py-6 border-t border-gray-200">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4 flex items-center gap-2">
                    <i data-lucide="camera" class="w-4 h-4"></i>
                    ფოტოები (<?php echo count($case_images); ?>)
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <?php foreach ($case_images as $index => $imageUrl): ?>
                    <div class="relative aspect-square rounded-xl overflow-hidden border border-gray-200 bg-gray-100 cursor-pointer group" onclick="openImageModal(<?php echo $index; ?>)">
                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                             alt="ფოტო <?php echo $index + 1; ?>" 
                             class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                             loading="lazy"
                             onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-400\'><i data-lucide=\'image-off\' class=\'w-8 h-8\'></i></div>';">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                            <div class="absolute bottom-2 left-2 text-white text-xs font-medium">
                                ფოტო <?php echo $index + 1; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="px-8 py-4 bg-white border-t border-gray-200 no-print">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-center sm:text-left">
                        <p class="text-sm text-gray-500">შეკითხვების შემთხვევაში დაგვიკავშირდით:</p>
                        <a href="tel:+995511144486" class="text-blue-600 font-semibold flex items-center gap-2 justify-center sm:justify-start mt-1">
                            <i data-lucide="phone" class="w-4 h-4"></i>
                            +995 511 144 486
                        </a>
                    </div>
                    <button onclick="window.print()" class="flex items-center gap-2 px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        დაბეჭდვა
                    </button>
                </div>
            </div>

            <?php if (strtolower($case['status'] ?? '') === 'completed' || intval($case['status_id'] ?? 0) === 8): ?>
            <!-- Completion Signature Section -->
            <div class="px-8 py-6 border-t border-gray-200" id="signature-section">
                <?php if (!empty($case['completion_signature'])): ?>
                <!-- Already Signed - Display saved signature -->
                <div class="text-center">
                    <div class="flex items-center justify-center gap-2 mb-4">
                        <div class="bg-green-100 p-2 rounded-full">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                        </div>
                        <h3 class="text-lg font-bold text-green-700">სერვისი დადასტურებულია</h3>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">მომხმარებელმა ციფრულად დაადასტურა სამუშაოს დასრულება</p>
                    <div class="bg-gray-50 rounded-xl border-2 border-gray-200 p-4 inline-block">
                        <img src="<?php echo htmlspecialchars($case['completion_signature']); ?>" alt="Customer Signature" class="max-w-[400px] max-h-[200px] mx-auto">
                    </div>
                    <?php if (!empty($case['signature_date'])): ?>
                    <p class="text-xs text-gray-400 mt-3">
                        <i data-lucide="calendar" class="w-3 h-3 inline-block mr-1"></i>
                        ხელმოწერის თარიღი: <?php echo date('d.m.Y H:i', strtotime($case['signature_date'])); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- Signature Pad - Not yet signed -->
                <div id="signature-pad-container">
                    <div class="flex items-center justify-center gap-2 mb-4">
                        <div class="bg-blue-100 p-2 rounded-full">
                            <i data-lucide="pen-tool" class="w-5 h-5 text-blue-600"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-800">სამუშაოს დასრულების დადასტურება</h3>
                    </div>
                    <p class="text-sm text-gray-500 text-center mb-4">გთხოვთ, ხელი მოაწეროთ ქვემოთ სერვისის დასრულების დასადასტურებლად</p>
                    
                    <div class="relative bg-white rounded-xl border-2 border-dashed border-gray-300 mx-auto max-w-[500px] touch-none" id="canvas-wrapper">
                        <canvas id="signature-canvas" class="w-full rounded-xl cursor-crosshair" style="height: 200px;"></canvas>
                        <div id="signature-placeholder" class="absolute inset-0 flex items-center justify-center pointer-events-none text-gray-300">
                            <div class="text-center">
                                <i data-lucide="pen-tool" class="w-8 h-8 mx-auto mb-2"></i>
                                <p class="text-sm">ხელი მოაწერეთ აქ</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-center gap-3 mt-4 no-print">
                        <button onclick="clearSignature()" class="flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium rounded-lg transition-colors text-sm">
                            <i data-lucide="eraser" class="w-4 h-4"></i>
                            გასუფთავება
                        </button>
                        <button onclick="saveSignature()" id="btn-save-signature" class="flex items-center gap-2 px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-colors text-sm shadow-lg shadow-green-600/30 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i data-lucide="check" class="w-4 h-4"></i>
                            ხელმოწერის შენახვა
                        </button>
                    </div>
                </div>
                
                <!-- Success state (shown after saving) -->
                <div id="signature-success" class="hidden text-center py-6">
                    <div class="bg-green-100 p-4 rounded-full inline-block mb-4">
                        <i data-lucide="check-circle" class="w-10 h-10 text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-bold text-green-700 mb-2">ხელმოწერა წარმატებით შეინახა!</h3>
                    <p class="text-sm text-gray-500">მადლობა სერვისის დადასტურებისთვის</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Powered By -->
        <div class="text-center mt-6 text-gray-400 text-sm no-print">
            <p>Powered by OTOMOTORS Manager</p>
        </div>
    </div>
    
    <?php if (count($case_images) > 0): ?>
    <!-- Image Modal -->
    <div id="image-modal" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4 no-print" onclick="closeImageModal()">
        <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white/80 hover:text-white p-2">
            <i data-lucide="x" class="w-8 h-8"></i>
        </button>
        <button onclick="event.stopPropagation(); prevImage()" class="absolute left-4 top-1/2 -translate-y-1/2 text-white/80 hover:text-white p-2 bg-black/30 rounded-full">
            <i data-lucide="chevron-left" class="w-8 h-8"></i>
        </button>
        <button onclick="event.stopPropagation(); nextImage()" class="absolute right-4 top-1/2 -translate-y-1/2 text-white/80 hover:text-white p-2 bg-black/30 rounded-full">
            <i data-lucide="chevron-right" class="w-8 h-8"></i>
        </button>
        <img id="modal-image" src="" alt="Full size photo" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl" onclick="event.stopPropagation()">
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 text-white/80 text-sm">
            <span id="image-counter">1 / <?php echo count($case_images); ?></span>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        lucide.createIcons();
        
        <?php if ((strtolower($case['status'] ?? '') === 'completed' || intval($case['status_id'] ?? 0) === 8) && empty($case['completion_signature'])): ?>
        // ── Signature Pad Logic ──
        (function() {
            const canvas = document.getElementById('signature-canvas');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            const wrapper = document.getElementById('canvas-wrapper');
            const placeholder = document.getElementById('signature-placeholder');
            const saveBtn = document.getElementById('btn-save-signature');
            let isDrawing = false;
            let hasDrawn = false;
            let lastX = 0;
            let lastY = 0;
            
            // Set actual canvas resolution to match display size
            function resizeCanvas() {
                const rect = wrapper.getBoundingClientRect();
                const dpr = window.devicePixelRatio || 1;
                canvas.width = rect.width * dpr;
                canvas.height = 200 * dpr;
                canvas.style.width = rect.width + 'px';
                canvas.style.height = '200px';
                ctx.scale(dpr, dpr);
                ctx.strokeStyle = '#1e293b';
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }
            
            resizeCanvas();
            window.addEventListener('resize', function() {
                if (!hasDrawn) resizeCanvas();
            });
            
            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                if (e.touches && e.touches.length > 0) {
                    return {
                        x: e.touches[0].clientX - rect.left,
                        y: e.touches[0].clientY - rect.top
                    };
                }
                return {
                    x: e.clientX - rect.left,
                    y: e.clientY - rect.top
                };
            }
            
            function startDrawing(e) {
                e.preventDefault();
                isDrawing = true;
                const pos = getPos(e);
                lastX = pos.x;
                lastY = pos.y;
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
            }
            
            function draw(e) {
                if (!isDrawing) return;
                e.preventDefault();
                const pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
                lastX = pos.x;
                lastY = pos.y;
                
                if (!hasDrawn) {
                    hasDrawn = true;
                    if (placeholder) placeholder.style.display = 'none';
                    if (saveBtn) saveBtn.disabled = false;
                    wrapper.classList.remove('border-dashed', 'border-gray-300');
                    wrapper.classList.add('border-solid', 'border-blue-400');
                }
            }
            
            function stopDrawing(e) {
                if (e) e.preventDefault();
                isDrawing = false;
                ctx.beginPath();
            }
            
            // Mouse events
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseleave', stopDrawing);
            
            // Touch events
            canvas.addEventListener('touchstart', startDrawing, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', stopDrawing, { passive: false });
            canvas.addEventListener('touchcancel', stopDrawing, { passive: false });
            
            // Clear function
            window.clearSignature = function() {
                const dpr = window.devicePixelRatio || 1;
                ctx.clearRect(0, 0, canvas.width / dpr, canvas.height / dpr);
                hasDrawn = false;
                if (placeholder) placeholder.style.display = 'flex';
                if (saveBtn) saveBtn.disabled = true;
                wrapper.classList.add('border-dashed', 'border-gray-300');
                wrapper.classList.remove('border-solid', 'border-blue-400');
            };
            
            // Save function
            window.saveSignature = async function() {
                if (!hasDrawn) return;
                
                const slug = '<?php echo htmlspecialchars($case['slug'] ?? '', ENT_QUOTES); ?>';
                if (!slug) {
                    alert('ხელმოწერის შენახვა ვერ ხერხდება. გთხოვთ, სცადოთ მოგვიანებით.');
                    return;
                }
                
                // Get signature as base64 PNG
                const signatureData = canvas.toDataURL('image/png');
                
                // Disable button and show loading
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> იტვირთება...';
                }
                
                try {
                    const response = await fetch('api.php?action=save_completion_signature', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ slug: slug, signature: signatureData })
                    });
                    
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        // Show success state
                        const padContainer = document.getElementById('signature-pad-container');
                        const successEl = document.getElementById('signature-success');
                        if (padContainer) padContainer.style.display = 'none';
                        if (successEl) {
                            successEl.classList.remove('hidden');
                            lucide.createIcons();
                        }
                    } else {
                        alert(result.error || 'ხელმოწერის შენახვა ვერ მოხერხდა. გთხოვთ, სცადოთ მოგვიანებით.');
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> ხელმოწერის შენახვა';
                            lucide.createIcons();
                        }
                    }
                } catch (err) {
                    console.error('Signature save error:', err);
                    alert('ქსელის შეცდომა. გთხოვთ, სცადოთ მოგვიანებით.');
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> ხელმოწერის შენახვა';
                        lucide.createIcons();
                    }
                }
            };
        })();
        <?php endif; ?>
        
        <?php if (count($case_images) > 0): ?>
        const caseImages = <?php echo json_encode($case_images); ?>;
        let currentImageIndex = 0;
        
        function openImageModal(index) {
            currentImageIndex = index;
            updateModalImage();
            document.getElementById('image-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeImageModal() {
            document.getElementById('image-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        function updateModalImage() {
            document.getElementById('modal-image').src = caseImages[currentImageIndex];
            document.getElementById('image-counter').textContent = (currentImageIndex + 1) + ' / ' + caseImages.length;
        }
        
        function prevImage() {
            currentImageIndex = (currentImageIndex - 1 + caseImages.length) % caseImages.length;
            updateModalImage();
        }
        
        function nextImage() {
            currentImageIndex = (currentImageIndex + 1) % caseImages.length;
            updateModalImage();
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('image-modal').classList.contains('hidden')) return;
            if (e.key === 'Escape') closeImageModal();
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        });
        <?php endif; ?>
    </script>
</body>
</html>
