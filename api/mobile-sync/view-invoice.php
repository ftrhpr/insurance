<?php
/**
 * Public Invoice / Estimate Viewer
 * Accessible via: https://yourdomain.com/api/mobile-sync/view-invoice.php?slug=xxxxx
 * No API key required — this is a customer-facing page
 */

// Direct access is allowed for this file (public page)
define('API_ACCESS', true);

// Load config for DB connection only
require_once 'config.php';

// Override JSON content-type — this page returns HTML
header('Content-Type: text/html; charset=UTF-8');

// Get slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    renderError('ბმული არასწორია', 'გთხოვთ შეამოწმოთ ბმული და სცადოთ თავიდან.');
    exit();
}

try {
    $pdo = getDBConnection();

    // Fetch invoice by slug
    $stmt = $pdo->prepare("SELECT * FROM transfers WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        renderError('ინვოისი ვერ მოიძებნა', 'მოთხოვნილი დოკუმენტი არ არსებობს ან წაშლილია.');
        exit();
    }

    // Parse JSON fields
    $services = [];
    if (!empty($invoice['repair_labor'])) {
        $services = json_decode($invoice['repair_labor'], true) ?? [];
    }

    $parts = [];
    if (!empty($invoice['repair_parts'])) {
        $parts = json_decode($invoice['repair_parts'], true) ?? [];
    }

    $images = [];
    if (!empty($invoice['case_images'])) {
        $images = json_decode($invoice['case_images'], true) ?? [];
    }

    // Fetch payments
    $paymentsStmt = $pdo->prepare("SELECT * FROM payments WHERE transfer_id = :tid ORDER BY payment_date DESC");
    $paymentsStmt->execute([':tid' => $invoice['id']]);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPaid = array_reduce($payments, function ($sum, $p) {
        return $sum + floatval($p['amount'] ?? 0);
    }, 0);

    // Calculate totals
    $servicesSubtotal = array_reduce($services, function ($sum, $s) {
        return $sum + floatval($s['price'] ?? 0);
    }, 0);

    $partsSubtotal = array_reduce($parts, function ($sum, $p) {
        return $sum + floatval($p['total_price'] ?? $p['totalPrice'] ?? 0);
    }, 0);

    $servicesDiscount = floatval($invoice['services_discount_percent'] ?? 0);
    $partsDiscount = floatval($invoice['parts_discount_percent'] ?? 0);
    $globalDiscount = floatval($invoice['global_discount_percent'] ?? 0);
    $vatEnabled = intval($invoice['vat_enabled'] ?? 0);
    $vatRate = floatval($invoice['vat_rate'] ?? 18);

    $servicesAfterDiscount = $servicesSubtotal * (1 - $servicesDiscount / 100);
    $partsAfterDiscount = $partsSubtotal * (1 - $partsDiscount / 100);
    $subtotal = ($servicesAfterDiscount + $partsAfterDiscount) * (1 - $globalDiscount / 100);
    $vatAmount = $vatEnabled ? $subtotal * ($vatRate / 100) : 0;
    $grandTotal = $subtotal + $vatAmount;
    $balance = $grandTotal - $totalPaid;

    // Status mapping
    $statusMap = [
        'New' => ['label' => 'ახალი', 'color' => '#6366F1', 'step' => 1],
        'Processing' => ['label' => 'მუშავდება', 'color' => '#F97316', 'step' => 2],
        'Contacted' => ['label' => 'დაკავშირებული', 'color' => '#3B82F6', 'step' => 2],
        'Parts ordered' => ['label' => 'ნაწილები შეკვეთილია', 'color' => '#8B5CF6', 'step' => 3],
        'Parts Arrived' => ['label' => 'ნაწილები ჩამოვიდა', 'color' => '#06B6D4', 'step' => 3],
        'Scheduled' => ['label' => 'დაგეგმილი', 'color' => '#14B8A6', 'step' => 3],
        'In Service' => ['label' => 'სერვისშია', 'color' => '#F97316', 'step' => 4],
        'Already in service' => ['label' => 'სერვისშია', 'color' => '#F97316', 'step' => 4],
        'Completed' => ['label' => 'დასრულებული', 'color' => '#10B981', 'step' => 5],
    ];

    $repairStatusMap = [
        'მზადაა დასაწყებად' => ['label' => 'მზადაა დასაწყებად', 'icon' => '📋'],
        'იშლება' => ['label' => 'იშლება', 'icon' => '🔧'],
        'თუნუქი' => ['label' => 'თუნუქი', 'icon' => '🔨'],
        'პლასტმასის აღდგენა' => ['label' => 'პლასტმასის აღდგენა', 'icon' => '🛠️'],
        'იღებება' => ['label' => 'იღებება', 'icon' => '🎨'],
        'მუშავდება' => ['label' => 'მუშავდება', 'icon' => '⚙️'],
        'იღებება (საბოლოო)' => ['label' => 'საბოლოო შეღებვა', 'icon' => '🎨'],
        'აწყობა' => ['label' => 'აწყობა', 'icon' => '🔩'],
        'პოლირება' => ['label' => 'პოლირება', 'icon' => '✨'],
        'დაშლილი და გასული' => ['label' => 'მზადაა', 'icon' => '✅'],
    ];

    $currentStatus = $invoice['status'] ?? 'New';
    $statusInfo = $statusMap[$currentStatus] ?? ['label' => $currentStatus, 'color' => '#94A3B8', 'step' => 1];
    $currentStep = $statusInfo['step'];

    $repairStatus = $invoice['repair_status'] ?? null;
    $repairInfo = $repairStatus ? ($repairStatusMap[$repairStatus] ?? ['label' => $repairStatus, 'icon' => '🔧']) : null;

    $serviceDate = $invoice['service_date'] ?? $invoice['serviceDate'] ?? null;
    $formattedDate = $serviceDate ? date('d.m.Y', strtotime($serviceDate)) : 'N/A';

    renderInvoice($invoice, $services, $parts, $images, $payments, [
        'servicesSubtotal' => $servicesSubtotal,
        'partsSubtotal' => $partsSubtotal,
        'servicesDiscount' => $servicesDiscount,
        'partsDiscount' => $partsDiscount,
        'globalDiscount' => $globalDiscount,
        'subtotal' => $subtotal,
        'vatEnabled' => $vatEnabled,
        'vatRate' => $vatRate,
        'vatAmount' => $vatAmount,
        'grandTotal' => $grandTotal,
        'totalPaid' => $totalPaid,
        'balance' => $balance,
    ], $statusInfo, $currentStep, $repairInfo, $formattedDate);

} catch (Exception $e) {
    error_log("View invoice error: " . $e->getMessage());
    renderError('შეცდომა', 'გვერდის ჩატვირთვა ვერ მოხერხდა. გთხოვთ სცადოთ მოგვიანებით.');
}

// ─── Render Functions ──────────────────────────────────────

function formatGEL($amount) {
    return number_format(floatval($amount), 2, '.', ',') . ' ₾';
}

function renderError($title, $message) {
    ?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OtoMotors</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F0F4F8; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-card { background: #fff; border-radius: 20px; padding: 40px; text-align: center; max-width: 400px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .error-icon { font-size: 48px; margin-bottom: 16px; }
        .error-title { font-size: 20px; font-weight: 700; color: #1E293B; margin-bottom: 8px; }
        .error-msg { font-size: 14px; color: #64748B; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">🔍</div>
        <div class="error-title"><?= htmlspecialchars($title) ?></div>
        <div class="error-msg"><?= htmlspecialchars($message) ?></div>
    </div>
</body>
</html>
    <?php
}

function renderInvoice($invoice, $services, $parts, $images, $payments, $totals, $statusInfo, $currentStep, $repairInfo, $formattedDate) {
    $plate = htmlspecialchars($invoice['plate'] ?? 'N/A');
    $customerName = htmlspecialchars($invoice['name'] ?? 'N/A');
    $vehicleMake = htmlspecialchars($invoice['vehicle_make'] ?? '');
    $vehicleModel = htmlspecialchars($invoice['vehicle_model'] ?? '');
    $vehicle = trim("$vehicleMake $vehicleModel") ?: 'N/A';
    $mechanic = htmlspecialchars($invoice['assigned_mechanic'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $plate ?> — OtoMotors</title>
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1E40AF;
            --success: #10B981;
            --warning: #F59E0B;
            --error: #EF4444;
            --orange: #F97316;
            --bg: #F0F4F8;
            --card: #FFFFFF;
            --text: #1E293B;
            --text-secondary: #64748B;
            --text-tertiary: #94A3B8;
            --border: #E2E8F0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
        .container { max-width: 600px; margin: 0 auto; padding: 0 0 40px; }

        /* Header */
        .header { background: linear-gradient(135deg, var(--primary-dark), var(--primary), #3B82F6); padding: 32px 24px 28px; color: #fff; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .brand { font-size: 14px; font-weight: 600; opacity: 0.8; letter-spacing: 1px; text-transform: uppercase; }
        .invoice-id { font-size: 12px; opacity: 0.6; }
        .plate-number { font-size: 32px; font-weight: 800; letter-spacing: 1px; margin-bottom: 4px; }
        .vehicle-name { font-size: 15px; opacity: 0.8; }
        .customer-name { font-size: 14px; opacity: 0.65; margin-top: 2px; }
        .header-date { font-size: 12px; opacity: 0.5; margin-top: 8px; }

        /* Status Badge */
        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; }

        /* Status Tracker */
        .tracker { background: var(--card); margin: -16px 16px 0; border-radius: 16px; padding: 20px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); position: relative; z-index: 1; }
        .tracker-title { font-size: 14px; font-weight: 700; color: var(--text-secondary); margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; }
        .tracker-steps { display: flex; align-items: center; justify-content: space-between; position: relative; }
        .tracker-line { position: absolute; top: 16px; left: 24px; right: 24px; height: 3px; background: var(--border); border-radius: 2px; }
        .tracker-line-fill { position: absolute; top: 16px; left: 24px; height: 3px; border-radius: 2px; transition: width 0.5s ease; }
        .tracker-step { display: flex; flex-direction: column; align-items: center; gap: 8px; position: relative; z-index: 1; }
        .step-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; border: 3px solid var(--border); background: #fff; color: var(--text-tertiary); transition: all 0.3s; }
        .step-dot.active { border-color: var(--primary); background: var(--primary); color: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,0.15); }
        .step-dot.done { border-color: var(--success); background: var(--success); color: #fff; }
        .step-label { font-size: 10px; color: var(--text-tertiary); font-weight: 500; text-align: center; max-width: 64px; }
        .step-label.active { color: var(--primary); font-weight: 700; }
        .step-label.done { color: var(--success); }

        /* Repair status */
        .repair-status { background: var(--card); margin: 12px 16px 0; border-radius: 14px; padding: 16px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 12px; }
        .repair-icon { font-size: 24px; }
        .repair-label-text { font-size: 13px; color: var(--text-secondary); }
        .repair-value { font-size: 15px; font-weight: 700; color: var(--text); }

        /* Sections */
        .section { background: var(--card); margin: 12px 16px 0; border-radius: 14px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .section-title { font-size: 14px; font-weight: 700; color: var(--text-secondary); margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; }
        .section-title .emoji { font-size: 16px; }

        /* Services Table */
        .service-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #F1F5F9; }
        .service-row:last-child { border-bottom: none; }
        .service-name { font-size: 14px; font-weight: 500; color: var(--text); flex: 1; }
        .service-qty { font-size: 12px; color: var(--text-tertiary); margin: 0 12px; }
        .service-price { font-size: 14px; font-weight: 700; color: var(--orange); white-space: nowrap; }

        /* Parts Table */
        .part-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #F1F5F9; }
        .part-row:last-child { border-bottom: none; }
        .part-name { font-size: 14px; font-weight: 500; color: var(--text); flex: 1; }
        .part-qty { font-size: 12px; color: var(--text-tertiary); margin: 0 12px; }
        .part-price { font-size: 14px; font-weight: 700; color: var(--primary); white-space: nowrap; }

        /* Price Summary */
        .price-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; }
        .price-label { font-size: 14px; color: var(--text-secondary); }
        .price-value { font-size: 14px; font-weight: 600; color: var(--text); }
        .price-discount { color: var(--success); }
        .price-divider { border-top: 2px solid var(--border); margin: 8px 0; }
        .price-total { font-size: 18px; font-weight: 800; }
        .price-total .price-value { font-size: 20px; color: var(--primary); }

        /* Balance */
        .balance-section { background: linear-gradient(135deg, #F0FDF4, #ECFDF5); border: 1px solid #BBF7D0; }
        .balance-unpaid { background: linear-gradient(135deg, #FEF2F2, #FFF1F2); border: 1px solid #FECACA; }
        .balance-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; }
        .balance-label { font-size: 14px; color: var(--text-secondary); }
        .balance-value { font-size: 14px; font-weight: 700; }
        .balance-total { font-size: 20px; font-weight: 800; }
        .balance-paid { color: var(--success); }
        .balance-remaining { color: var(--error); }
        .balance-zero { color: var(--success); }

        /* Photos */
        .photo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .photo-item { aspect-ratio: 1; border-radius: 10px; overflow: hidden; cursor: pointer; position: relative; }
        .photo-item img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
        .photo-item:hover img { transform: scale(1.05); }
        .photo-more { background: rgba(0,0,0,0.5); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; position: absolute; inset: 0; }

        /* Payments */
        .payment-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #F1F5F9; }
        .payment-row:last-child { border-bottom: none; }
        .payment-info { }
        .payment-method { font-size: 13px; font-weight: 600; color: var(--text); }
        .payment-date { font-size: 12px; color: var(--text-tertiary); margin-top: 2px; }
        .payment-amount { font-size: 15px; font-weight: 700; color: var(--success); }

        /* Mechanic */
        .mechanic-badge { display: inline-flex; align-items: center; gap: 6px; background: #EEF2FF; padding: 8px 14px; border-radius: 10px; margin-top: 4px; }
        .mechanic-badge span { font-size: 13px; font-weight: 600; color: #6366F1; }

        /* Footer */
        .footer { text-align: center; margin-top: 24px; padding: 20px 16px; }
        .footer-brand { font-size: 16px; font-weight: 800; color: var(--primary); letter-spacing: 0.5px; }
        .footer-sub { font-size: 12px; color: var(--text-tertiary); margin-top: 4px; }

        /* Lightbox */
        .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 100; align-items: center; justify-content: center; cursor: pointer; }
        .lightbox.active { display: flex; }
        .lightbox img { max-width: 95%; max-height: 90vh; border-radius: 8px; object-fit: contain; }

        /* Animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .section, .tracker, .repair-status { animation: fadeInUp 0.4s ease-out backwards; }
        .section:nth-child(2) { animation-delay: 0.05s; }
        .section:nth-child(3) { animation-delay: 0.1s; }
        .section:nth-child(4) { animation-delay: 0.15s; }
        .section:nth-child(5) { animation-delay: 0.2s; }

        @media (max-width: 400px) {
            .plate-number { font-size: 26px; }
            .header { padding: 24px 16px 22px; }
            .step-label { font-size: 9px; max-width: 52px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div>
                    <div class="brand">OtoMotors</div>
                    <div class="invoice-id">#<?= htmlspecialchars($invoice['id']) ?></div>
                </div>
                <div class="status-badge" style="background: <?= $statusInfo['color'] ?>22; color: <?= $statusInfo['color'] ?>;">
                    <?= $statusInfo['label'] ?>
                </div>
            </div>
            <div class="plate-number"><?= $plate ?></div>
            <div class="vehicle-name"><?= htmlspecialchars($vehicle) ?></div>
            <div class="customer-name">👤 <?= $customerName ?></div>
            <div class="header-date">📅 <?= $formattedDate ?></div>
        </div>

        <!-- Status Tracker -->
        <div class="tracker">
            <div class="tracker-title">სტატუსის ტრეკერი</div>
            <div class="tracker-steps">
                <div class="tracker-line"></div>
                <?php
                $fillPercent = max(0, min(100, (($currentStep - 1) / 4) * 100));
                $fillWidth = 'calc(' . $fillPercent . '% - 0px)';
                ?>
                <div class="tracker-line-fill" style="width: <?= $fillWidth ?>; background: <?= $statusInfo['color'] ?>;"></div>
                <?php
                $steps = [
                    ['label' => 'ახალი', 'step' => 1],
                    ['label' => 'პროცესი', 'step' => 2],
                    ['label' => 'მომზადება', 'step' => 3],
                    ['label' => 'სერვისი', 'step' => 4],
                    ['label' => 'მზადაა', 'step' => 5],
                ];
                foreach ($steps as $s):
                    $isDone = $s['step'] < $currentStep;
                    $isActive = $s['step'] === $currentStep;
                    $dotClass = $isDone ? 'done' : ($isActive ? 'active' : '');
                    $labelClass = $isDone ? 'done' : ($isActive ? 'active' : '');
                ?>
                <div class="tracker-step">
                    <div class="step-dot <?= $dotClass ?>">
                        <?= $isDone ? '✓' : $s['step'] ?>
                    </div>
                    <div class="step-label <?= $labelClass ?>"><?= $s['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Repair Status -->
        <?php if ($repairInfo): ?>
        <div class="repair-status">
            <div class="repair-icon"><?= $repairInfo['icon'] ?></div>
            <div>
                <div class="repair-label-text">რემონტის ეტაპი</div>
                <div class="repair-value"><?= htmlspecialchars($repairInfo['label']) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mechanic -->
        <?php if ($mechanic): ?>
        <div class="section" style="padding: 14px 20px;">
            <div style="font-size: 13px; color: var(--text-secondary);">მექანიკოსი</div>
            <div class="mechanic-badge"><span>🔧 <?= htmlspecialchars($mechanic) ?></span></div>
        </div>
        <?php endif; ?>

        <!-- Services -->
        <?php if (!empty($services)): ?>
        <div class="section">
            <div class="section-title"><span class="emoji">📋</span> სერვისები</div>
            <?php foreach ($services as $s):
                $name = htmlspecialchars($s['name'] ?? $s['description'] ?? 'N/A');
                $hours = floatval($s['hours'] ?? 1);
                $price = floatval($s['price'] ?? 0);
                $discount = floatval($s['discount_percent'] ?? 0);
            ?>
            <div class="service-row">
                <div class="service-name"><?= $name ?></div>
                <?php if ($hours > 1): ?><div class="service-qty">x<?= $hours ?></div><?php endif; ?>
                <div class="service-price">
                    <?= formatGEL($price) ?>
                    <?php if ($discount > 0): ?>
                        <span style="font-size:11px; color:var(--success); margin-left:4px;">-<?= $discount ?>%</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Parts -->
        <?php if (!empty($parts)): ?>
        <div class="section">
            <div class="section-title"><span class="emoji">🔧</span> ნაწილები</div>
            <?php foreach ($parts as $p):
                $name = htmlspecialchars($p['name'] ?? $p['name_en'] ?? 'N/A');
                $qty = intval($p['quantity'] ?? 1);
                $total = floatval($p['total_price'] ?? $p['totalPrice'] ?? 0);
            ?>
            <div class="part-row">
                <div class="part-name"><?= $name ?></div>
                <?php if ($qty > 1): ?><div class="part-qty">x<?= $qty ?></div><?php endif; ?>
                <div class="part-price"><?= formatGEL($total) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Price Summary -->
        <div class="section">
            <div class="section-title"><span class="emoji">💰</span> ღირებულება</div>

            <?php if ($totals['servicesSubtotal'] > 0): ?>
            <div class="price-row">
                <div class="price-label">სერვისები</div>
                <div class="price-value"><?= formatGEL($totals['servicesSubtotal']) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($totals['servicesDiscount'] > 0): ?>
            <div class="price-row">
                <div class="price-label price-discount">↳ ფასდაკლება (<?= $totals['servicesDiscount'] ?>%)</div>
                <div class="price-value price-discount">-<?= formatGEL($totals['servicesSubtotal'] * $totals['servicesDiscount'] / 100) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($totals['partsSubtotal'] > 0): ?>
            <div class="price-row">
                <div class="price-label">ნაწილები</div>
                <div class="price-value"><?= formatGEL($totals['partsSubtotal']) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($totals['partsDiscount'] > 0): ?>
            <div class="price-row">
                <div class="price-label price-discount">↳ ფასდაკლება (<?= $totals['partsDiscount'] ?>%)</div>
                <div class="price-value price-discount">-<?= formatGEL($totals['partsSubtotal'] * $totals['partsDiscount'] / 100) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($totals['globalDiscount'] > 0): ?>
            <div class="price-row">
                <div class="price-label price-discount">საერთო ფასდაკლება (<?= $totals['globalDiscount'] ?>%)</div>
                <div class="price-value price-discount">-<?= $totals['globalDiscount'] ?>%</div>
            </div>
            <?php endif; ?>

            <?php if ($totals['vatEnabled']): ?>
            <div class="price-row">
                <div class="price-label">დღგ (<?= $totals['vatRate'] ?>%)</div>
                <div class="price-value"><?= formatGEL($totals['vatAmount']) ?></div>
            </div>
            <?php endif; ?>

            <div class="price-divider"></div>
            <div class="price-row price-total">
                <div class="price-label" style="font-weight:800; color:var(--text);">ჯამი</div>
                <div class="price-value"><?= formatGEL($totals['grandTotal']) ?></div>
            </div>
        </div>

        <!-- Balance / Payments Summary -->
        <div class="section <?= $totals['balance'] > 1 ? 'balance-unpaid' : 'balance-section' ?>" style="margin-top: 12px;">
            <div class="balance-row">
                <div class="balance-label">ჯამი</div>
                <div class="balance-value"><?= formatGEL($totals['grandTotal']) ?></div>
            </div>
            <div class="balance-row">
                <div class="balance-label">გადახდილი</div>
                <div class="balance-value balance-paid"><?= formatGEL($totals['totalPaid']) ?></div>
            </div>
            <div style="border-top: 2px solid <?= $totals['balance'] > 1 ? '#FECACA' : '#BBF7D0' ?>; margin: 6px 0;"></div>
            <div class="balance-row">
                <div class="balance-label" style="font-weight:700;">ნაშთი</div>
                <div class="balance-total <?= $totals['balance'] > 1 ? 'balance-remaining' : 'balance-zero' ?>">
                    <?= $totals['balance'] > 1 ? formatGEL($totals['balance']) : '✅ გადახდილია' ?>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="section">
            <div class="section-title"><span class="emoji">💳</span> გადახდები</div>
            <?php foreach ($payments as $p):
                $method = htmlspecialchars($p['payment_method'] ?? $p['method'] ?? 'Cash');
                $amount = floatval($p['amount'] ?? 0);
                $date = $p['payment_date'] ?? $p['created_at'] ?? '';
                $fmtDate = $date ? date('d.m.Y', strtotime($date)) : '';
                $methodLabel = $method === 'Cash' ? 'ნაღდი' : ($method === 'Transfer' ? 'გადარიცხვა' : $method);
            ?>
            <div class="payment-row">
                <div class="payment-info">
                    <div class="payment-method"><?= $methodLabel ?></div>
                    <div class="payment-date"><?= $fmtDate ?></div>
                </div>
                <div class="payment-amount">+<?= formatGEL($amount) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Photos -->
        <?php if (!empty($images)): ?>
        <div class="section">
            <div class="section-title"><span class="emoji">📸</span> ფოტოები</div>
            <div class="photo-grid">
                <?php
                $maxShow = 6;
                $total = count($images);
                foreach (array_slice($images, 0, $maxShow) as $i => $url):
                    $isLast = ($i === $maxShow - 1 && $total > $maxShow);
                ?>
                <div class="photo-item" onclick="openLightbox('<?= htmlspecialchars($url, ENT_QUOTES) ?>')">
                    <img src="<?= htmlspecialchars($url) ?>" alt="Photo <?= $i + 1 ?>" loading="lazy" />
                    <?php if ($isLast): ?>
                    <div class="photo-more">+<?= $total - $maxShow ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-brand">OtoMotors</div>
            <div class="footer-sub">ავტო სერვისი · თბილისი</div>
        </div>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <img id="lightbox-img" src="" alt="Photo" />
    </div>

    <script>
        function openLightbox(url) {
            document.getElementById('lightbox-img').src = url;
            document.getElementById('lightbox').classList.add('active');
        }
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLightbox();
        });
    </script>
</body>
</html>
    <?php
}
?>
