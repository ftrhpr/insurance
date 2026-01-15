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

if ($case_id <= 0) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>Invoice not found</h1></body></html>');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
    $stmt->execute([$case_id]);
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$case) {
        http_response_code(404);
        die('<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>Invoice not found</h1></body></html>');
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
    
    // Get discount percentages - handle missing columns gracefully
    $parts_discount_pct = isset($case['parts_discount_percent']) ? floatval($case['parts_discount_percent']) : 0;
    $services_discount_pct = isset($case['services_discount_percent']) ? floatval($case['services_discount_percent']) : 0;
    $global_discount_pct = isset($case['global_discount_percent']) ? floatval($case['global_discount_percent']) : 0;
    
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
                    </div>
                </div>
            </div>
            
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
                
                <?php if (count($case_images) > 0): ?>
                <!-- Photos Section -->
                <div class="mt-6 pt-6 border-t border-gray-200">
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
            </div>
            
            <!-- Totals Section -->
            <div class="px-8 py-6 bg-gray-50 border-t border-gray-200">
                <div class="max-w-xs ml-auto space-y-2">
                    <!-- Parts Subtotal -->
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">ნაწილები:</span>
                        <span class="font-medium">₾<?php echo number_format($parts_subtotal, 2); ?></span>
                    </div>
                    
                    <!-- Services Subtotal -->
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">მომსახურება:</span>
                        <span class="font-medium">₾<?php echo number_format($services_subtotal, 2); ?></span>
                    </div>
                    
                    <!-- Subtotal -->
                    <div class="flex justify-between text-sm pt-2 border-t border-gray-200">
                        <span class="text-gray-600">ჯამი:</span>
                        <span class="font-medium">₾<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <?php if ($total_discount > 0): ?>
                    <!-- Discounts -->
                    <?php if ($parts_discount_pct > 0): ?>
                    <div class="flex justify-between text-sm text-red-600">
                        <span>ნაწილების ფასდაკლება (<?php echo $parts_discount_pct; ?>%):</span>
                        <span>-₾<?php echo number_format($parts_discount_amount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($services_discount_pct > 0): ?>
                    <div class="flex justify-between text-sm text-red-600">
                        <span>მომსახურების ფასდაკლება (<?php echo $services_discount_pct; ?>%):</span>
                        <span>-₾<?php echo number_format($services_discount_amount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($global_discount_pct > 0): ?>
                    <div class="flex justify-between text-sm text-red-600">
                        <span>საერთო ფასდაკლება (<?php echo $global_discount_pct; ?>%):</span>
                        <span>-₾<?php echo number_format($global_discount_amount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Total Savings -->
                    <div class="flex justify-between text-sm font-medium text-red-600 pt-1">
                        <span>დაზოგილი თანხა:</span>
                        <span>-₾<?php echo number_format($total_discount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Grand Total -->
                    <div class="flex justify-between pt-3 border-t-2 border-gray-300">
                        <span class="text-lg font-bold text-gray-800">გადასახდელი:</span>
                        <span class="text-2xl font-bold text-indigo-600">₾<?php echo number_format($grand_total, 2); ?></span>
                    </div>
                </div>
            </div>
            
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
