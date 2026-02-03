<?php
/**
 * Technician Work Order Print View
 * Displays case details without prices - for technicians to know what work needs to be done
 */
error_reporting(0);

// Include config
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

if ($case_id <= 0) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>Case not found</h1></body></html>');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
    $stmt->execute([$case_id]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$case) {
        http_response_code(404);
        die('<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>Case not found</h1></body></html>');
    }
    
    // Decode JSON fields safely
    $repair_parts = [];
    $repair_labor = [];
    
    if (!empty($case['repair_parts'])) {
        $decoded = json_decode($case['repair_parts'], true);
        if (is_array($decoded)) $repair_parts = $decoded;
    }
    
    if (!empty($case['repair_labor'])) {
        $decoded = json_decode($case['repair_labor'], true);
        if (is_array($decoded)) $repair_labor = $decoded;
    }
    
    // Get case images
    $case_images = [];
    if (!empty($case['case_images'])) {
        $decoded = json_decode($case['case_images'], true);
        if (is_array($decoded)) $case_images = $decoded;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Error loading case</h1></body></html>');
}

// Format date safely
$created_date = !empty($case['created_at']) ? date('d.m.Y', strtotime($case['created_at'])) : date('d.m.Y');
$service_date = !empty($case['service_date']) ? date('d.m.Y H:i', strtotime($case['service_date'])) : 'არ არის დაგეგმილი';
$due_date = !empty($case['due_date']) ? date('d.m.Y H:i', strtotime($case['due_date'])) : '-';

// Vehicle info
$vehicle_info = trim(($case['vehicle_make'] ?? '') . ' ' . ($case['vehicle_model'] ?? ''));
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>სამუშაო დავალება #<?php echo $case_id; ?> | OTOMOTORS</title>
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
            body { 
                background: white !important; 
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-size: 10px !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-break { page-break-after: always; }
            .shadow-xl, .shadow-lg, .shadow { box-shadow: none !important; }
            .rounded-2xl, .rounded-xl, .rounded-lg { border-radius: 4px !important; }
            @page {
                margin: 0.5cm;
                size: A4;
            }
            /* Compact header */
            .print-header { padding: 8px 16px !important; }
            .print-header h1 { font-size: 16px !important; }
            .print-header .text-3xl { font-size: 18px !important; }
            /* Compact info section */
            .print-info { padding: 8px 16px !important; }
            .print-info .text-2xl { font-size: 16px !important; }
            .print-info .text-lg { font-size: 12px !important; }
            .print-info .text-sm { font-size: 9px !important; }
            /* Compact work items */
            .print-content { padding: 8px 16px !important; }
            .print-content h3 { font-size: 11px !important; margin-bottom: 4px !important; padding-bottom: 2px !important; }
            .print-content .space-y-3 > * { margin-top: 4px !important; margin-bottom: 0 !important; }
            .checklist-item { padding: 6px 8px !important; }
            .checklist-item .text-base { font-size: 10px !important; }
            .checklist-item .w-6 { width: 16px !important; height: 16px !important; }
            .checklist-item .w-8 { width: 18px !important; height: 18px !important; }
            /* Compact table */
            .print-content table { font-size: 9px !important; }
            .print-content table th, .print-content table td { padding: 4px 6px !important; }
            /* Compact notes */
            .print-notes { padding: 6px 10px !important; margin-bottom: 8px !important; }
            .print-notes h4 { font-size: 10px !important; margin-bottom: 4px !important; }
            .print-notes p { font-size: 9px !important; line-height: 1.3 !important; }
            /* Due date compact */
            .print-due { padding: 4px 8px !important; }
            .print-due .text-xl { font-size: 12px !important; }
            .print-due .w-6 { width: 14px !important; height: 14px !important; }
            /* Hide photos on print to save space */
            .print-photos { display: none !important; }
            /* Compact signature */
            .print-signature { padding: 8px 16px !important; }
            .print-signature .h-16 { height: 24px !important; }
            .print-signature .grid { gap: 16px !important; }
            /* Compact footer */
            .print-footer { padding: 4px 16px !important; }
            .print-footer .text-sm { font-size: 8px !important; }
            /* Reduce margins */
            .mb-8 { margin-bottom: 8px !important; }
            .mb-6 { margin-bottom: 6px !important; }
            .mb-4 { margin-bottom: 4px !important; }
            .py-6 { padding-top: 8px !important; padding-bottom: 8px !important; }
            .px-8 { padding-left: 16px !important; padding-right: 16px !important; }
            .gap-6 { gap: 8px !important; }
            .gap-4 { gap: 6px !important; }
        }
        @media screen {
            body { background: #f3f4f6; }
        }
        .checklist-item {
            page-break-inside: avoid;
        }
    </style>
</head>
<body class="min-h-screen py-8 px-4 font-sans">
    <div class="max-w-3xl mx-auto">
        <!-- Print Header Actions -->
        <div class="no-print mb-4 flex items-center justify-between">
            <a href="edit_case.php?id=<?php echo $case_id; ?>" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                უკან დაბრუნება
            </a>
            <button onclick="window.print()" class="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                <i data-lucide="printer" class="w-4 h-4"></i>
                დაბეჭდვა
            </button>
        </div>

        <!-- Work Order Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <!-- Header -->
            <div class="print-header bg-gradient-to-r from-slate-700 to-slate-800 text-white px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">OTOMOTORS</h1>
                        <p class="text-slate-300 text-sm mt-1">სამუშაო დავალება / Work Order</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-slate-300">დავალება #</div>
                        <div class="text-3xl font-bold"><?php echo $case_id; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Vehicle & Customer Info -->
            <div class="print-info px-8 py-6 border-b border-gray-200 bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Vehicle Info - Prominent -->
                    <div class="md:col-span-2">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">ავტომობილის ინფორმაცია</h3>
                        <div class="flex items-center gap-4 flex-wrap">
                            <div class="bg-yellow-100 px-6 py-3 rounded-xl border-2 border-yellow-300">
                                <span class="font-bold text-yellow-800 text-2xl tracking-wider"><?php echo htmlspecialchars($case['plate'] ?? 'N/A'); ?></span>
                            </div>
                            <?php if (!empty($vehicle_info)): ?>
                            <div class="text-lg font-medium text-gray-700">
                                <?php echo htmlspecialchars($vehicle_info); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Customer -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">მომხმარებელი</h3>
                        <p class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($case['name'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <!-- Dates -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">თარიღები</h3>
                        <div class="space-y-1 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 w-28">შექმნის თარიღი:</span>
                                <span class="font-medium"><?php echo $created_date; ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 w-28">სერვისის თარიღი:</span>
                                <span class="font-medium"><?php echo $service_date; ?></span>
                            </div>
                            <?php if ($due_date !== '-'): ?>
                            <div class="print-due flex items-center gap-2 mt-2 p-3 bg-red-50 border-2 border-red-300 rounded-lg">
                                <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600"></i>
                                <div>
                                    <span class="text-red-600 text-sm font-medium block">ვადა / Due Date:</span>
                                    <span class="font-bold text-red-700 text-xl"><?php echo $due_date; ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Status Badge -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <span class="text-sm text-gray-500 mr-2">სტატუსი:</span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                        <?php echo htmlspecialchars($case['status'] ?? 'New'); ?>
                    </span>
                    <?php if (!empty($case['urgent']) && $case['urgent']): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-100 text-red-800 ml-2">
                        🔥 სასწრაფო
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Work Items Section -->
            <div class="print-content px-8 py-6">
                <?php if (count($repair_labor) > 0): ?>
                <!-- Services/Work to be done -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2 border-b-2 border-gray-200 pb-2">
                        <i data-lucide="wrench" class="w-5 h-5 text-blue-600"></i>
                        შესასრულებელი სამუშაოები
                        <span class="text-sm font-normal text-gray-500 ml-auto">(<?php echo count($repair_labor); ?> სამუშაო)</span>
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($repair_labor as $index => $labor): 
                            $qty = floatval($labor['quantity'] ?? $labor['hours'] ?? 1);
                            $description = $labor['description'] ?? $labor['name'] ?? 'სამუშაო';
                            $notes = $labor['notes'] ?? '';
                        ?>
                        <div class="checklist-item flex items-start gap-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <!-- Checkbox for marking completion -->
                            <div class="flex-shrink-0 mt-0.5">
                                <div class="w-6 h-6 border-2 border-gray-400 rounded bg-white flex items-center justify-center">
                                    <span class="text-xs text-gray-400 font-bold"><?php echo $index + 1; ?></span>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-800 text-base">
                                    <?php echo htmlspecialchars($description); ?>
                                </div>
                                <?php if ($qty > 1): ?>
                                <div class="text-sm text-gray-600 mt-1">
                                    რაოდენობა: <span class="font-medium"><?php echo $qty; ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($notes)): ?>
                                <div class="text-sm text-gray-500 mt-1 italic">
                                    შენიშვნა: <?php echo htmlspecialchars($notes); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <!-- Status checkbox for print -->
                            <div class="flex-shrink-0 text-center">
                                <div class="w-8 h-8 border-2 border-gray-300 rounded bg-white"></div>
                                <span class="text-xs text-gray-400 mt-1 block">✓</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (count($repair_parts) > 0): ?>
                <!-- Parts needed -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2 border-b-2 border-gray-200 pb-2">
                        <i data-lucide="package" class="w-5 h-5 text-green-600"></i>
                        საჭირო ნაწილები
                        <span class="text-sm font-normal text-gray-500 ml-auto">(<?php echo count($repair_parts); ?> ნაწილი)</span>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b-2 border-gray-200 bg-gray-50">
                                    <th class="text-left py-3 px-3 font-semibold text-gray-600 w-8">#</th>
                                    <th class="text-left py-3 px-3 font-semibold text-gray-600">ნაწილის დასახელება</th>
                                    <th class="text-center py-3 px-3 font-semibold text-gray-600 w-20">რაოდ.</th>
                                    <th class="text-center py-3 px-3 font-semibold text-gray-600 w-24">SKU/კოდი</th>
                                    <th class="text-center py-3 px-3 font-semibold text-gray-600 w-16">✓</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repair_parts as $index => $part): 
                                    $qty = floatval($part['quantity'] ?? 1);
                                    $name = $part['name'] ?? 'ნაწილი';
                                    $sku = $part['sku'] ?? $part['part_number'] ?? '';
                                    $notes = $part['notes'] ?? '';
                                    $ordered = !empty($part['ordered']);
                                ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-3 text-gray-500 font-medium"><?php echo $index + 1; ?></td>
                                    <td class="py-3 px-3">
                                        <div class="font-medium text-gray-800"><?php echo htmlspecialchars($name); ?></div>
                                        <?php if (!empty($notes)): ?>
                                        <div class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($notes); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ordered): ?>
                                        <span class="inline-flex items-center text-xs text-green-600 font-medium mt-1">
                                            <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i> შეკვეთილია
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center font-semibold text-gray-700"><?php echo $qty; ?></td>
                                    <td class="py-3 px-3 text-center text-gray-500 font-mono text-xs"><?php echo htmlspecialchars($sku) ?: '-'; ?></td>
                                    <td class="py-3 px-3 text-center">
                                        <div class="w-6 h-6 border-2 border-gray-300 rounded bg-white mx-auto"></div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (count($repair_parts) === 0 && count($repair_labor) === 0): ?>
                <div class="text-center py-12 text-gray-500">
                    <i data-lucide="clipboard-list" class="w-16 h-16 mx-auto mb-4 text-gray-300"></i>
                    <p class="text-lg font-medium">სამუშაო დეტალები ჯერ არ არის დამატებული</p>
                    <p class="text-sm mt-1">დაამატეთ სამუშაოები და ნაწილები Case Editor-ში</p>
                </div>
                <?php endif; ?>
                
                <!-- Notes Section -->
                <?php 
                $operator_comment = $case['operatorComment'] ?? '';
                // Filter out system comments
                if (strpos($operator_comment, 'Created from mobile app') === 0) {
                    $operator_comment = '';
                }
                $repair_notes = $case['repair_notes'] ?? '';
                ?>
                <?php if (!empty($repair_notes)): ?>
                <div class="print-notes mb-6 p-4 bg-blue-50 border-2 border-blue-300 rounded-lg">
                    <h4 class="font-bold text-blue-800 mb-2 flex items-center gap-2 text-lg">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                        სარემონტო შენიშვნები / Repair Notes
                    </h4>
                    <p class="text-gray-800 whitespace-pre-wrap text-base leading-relaxed"><?php echo htmlspecialchars($repair_notes); ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($operator_comment)): ?>
                <div class="print-notes mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <h4 class="font-semibold text-yellow-800 mb-2 flex items-center gap-2">
                        <i data-lucide="message-square" class="w-4 h-4"></i>
                        შენიშვნები
                    </h4>
                    <p class="text-gray-700 text-sm whitespace-pre-wrap"><?php echo htmlspecialchars($operator_comment); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (count($case_images) > 0): ?>
            <!-- Photos Section - Hidden on print to fit A4 -->
            <div class="print-photos px-8 py-6 border-t border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i data-lucide="camera" class="w-5 h-5 text-purple-600"></i>
                    ფოტოები (<?php echo count($case_images); ?>)
                </h3>
                <div class="grid grid-cols-3 md:grid-cols-4 gap-3">
                    <?php foreach ($case_images as $index => $imageUrl): ?>
                    <div class="relative aspect-square rounded-lg overflow-hidden border border-gray-200 bg-gray-100">
                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                             alt="ფოტო <?php echo $index + 1; ?>" 
                             class="w-full h-full object-cover"
                             loading="lazy">
                        <div class="absolute bottom-1 left-1 bg-black/60 text-white text-xs px-1.5 py-0.5 rounded">
                            <?php echo $index + 1; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Signature Section for Print -->
            <div class="print-signature px-8 py-6 border-t border-gray-200">
                <div class="grid grid-cols-2 gap-8">
                    <div>
                        <div class="border-b-2 border-gray-400 h-16 mb-2"></div>
                        <p class="text-sm text-gray-600">ტექნიკოსის ხელმოწერა</p>
                    </div>
                    <div>
                        <div class="border-b-2 border-gray-400 h-16 mb-2"></div>
                        <p class="text-sm text-gray-600">თარიღი</p>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="print-footer px-8 py-4 bg-gray-100 border-t border-gray-200">
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <div>
                        დაბეჭდილია: <?php echo date('d.m.Y H:i'); ?>
                    </div>
                    <div>
                        OTOMOTORS Manager System
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Print Button (bottom) -->
        <div class="no-print mt-6 text-center">
            <button onclick="window.print()" class="inline-flex items-center gap-2 px-8 py-3 bg-slate-700 hover:bg-slate-800 text-white font-medium rounded-lg transition-colors">
                <i data-lucide="printer" class="w-5 h-5"></i>
                დაბეჭდვა
            </button>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
