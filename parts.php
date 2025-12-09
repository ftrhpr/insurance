<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();

$statusFilter = $_GET['status'] ?? 'All';
$vendorFilter = $_GET['vendor'] ?? '';
$export = isset($_GET['export']) && $_GET['export'] == '1';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;

// Detect whether the DB supports JSON functions (MySQL 5.7+/MariaDB 10.2+)
$use_json = false;
try {
    $test = $pdo->query("SELECT JSON_EXTRACT('[{\"vendor\":\"x\"}]', '$[0].vendor')")->fetchColumn();
    if ($test !== false) $use_json = true;
} catch (Exception $ex) {
    $use_json = false;
}

// Build WHERE clause depending on JSON support
$where = $use_json ? "WHERE JSON_LENGTH(parts) > 0" : "WHERE parts IS NOT NULL AND parts <> '[]'";
$params = [];
if ($statusFilter && $statusFilter !== 'All') {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}
if ($vendorFilter) {
    if ($use_json) {
        $where .= " AND (JSON_CONTAINS(parts, JSON_QUOTE(?), '$[*].vendor') OR parts LIKE ?)";
        $params[] = $vendorFilter;
        $params[] = '%' . $vendorFilter . '%';
    } else {
        $where .= " AND parts LIKE ?";
        $params[] = '%' . $vendorFilter . '%';
    }
}

// If export requested, fetch all matching rows (no pagination)
if ($export) {
    $exportSql = "SELECT id, plate, name, phone, status, service_date, parts FROM transfers $where ORDER BY id DESC";
    $exportStmt = $pdo->prepare($exportSql);
    $bindIndex = 1;
    foreach ($params as $p) { $exportStmt->bindValue($bindIndex++, $p); }
    $exportStmt->execute();
    $allRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=parts_export_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['transfer_id','plate','name','phone','transfer_status','service_date','part_name','qty','vendor','part_status']);
    foreach ($allRows as $r) {
        $parts = json_decode($r['parts'], true) ?: [];
        if (empty($parts)) continue;
        foreach ($parts as $p) {
            fputcsv($out, [
                $r['id'],
                $r['plate'],
                $r['name'],
                $r['phone'],
                $r['status'],
                $r['service_date'],
                $p['name'] ?? '',
                $p['qty'] ?? '',
                $p['vendor'] ?? '',
                $p['status'] ?? '',
            ]);
        }
    }
    fclose($out);
    exit();
}

// Paginated query when not exporting
$offset = ($page - 1) * $perPage;
$sql = "SELECT SQL_CALC_FOUND_ROWS id, plate, name, phone, status, service_date, parts FROM transfers $where ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
// bind params then offset/limit
$bindIndex = 1;
foreach ($params as $p) { $stmt->bindValue($bindIndex++, $p); }
$stmt->bindValue($bindIndex++, (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue($bindIndex++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// get total
$totalStmt = $pdo->query('SELECT FOUND_ROWS()');
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=parts_export_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['transfer_id','plate','name','phone','transfer_status','service_date','part_name','qty','vendor','part_status']);
    foreach ($rows as $r) {
        $parts = json_decode($r['parts'], true) ?: [];
        if (empty($parts)) continue;
        foreach ($parts as $p) {
            fputcsv($out, [
                $r['id'],
                $r['plate'],
                $r['name'],
                $r['phone'],
                $r['status'],
                $r['service_date'],
                $p['name'] ?? '',
                $p['qty'] ?? '',
                $p['vendor'] ?? '',
                $p['status'] ?? '',
            ]);
        }
    }
    fclose($out);
    exit();
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Parts Admin - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans">
<?php include 'header.php'; ?>
<main class="max-w-6xl mx-auto p-6">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Parts Admin</h1>
        <div class="flex gap-2">
            <a href="parts.php?export=1&status=<?php echo urlencode($statusFilter); ?>&vendor=<?php echo urlencode($vendorFilter); ?>" class="px-3 py-2 bg-amber-600 text-white rounded">Export CSV</a>
        </div>
    </div>

    <form method="get" class="mb-4 flex gap-2 items-end">
        <div>
            <label class="block text-xs">Status</label>
            <select name="status" class="p-2 border rounded">
                <option value="All">All</option>
                <option value="Processing" <?php if ($statusFilter==='Processing') echo 'selected'; ?>>Processing</option>
                <option value="Parts Ordered" <?php if ($statusFilter==='Parts Ordered') echo 'selected'; ?>>Parts Ordered</option>
                <option value="Parts Arrived" <?php if ($statusFilter==='Parts Arrived') echo 'selected'; ?>>Parts Arrived</option>
                <option value="Scheduled" <?php if ($statusFilter==='Scheduled') echo 'selected'; ?>>Scheduled</option>
                <option value="Completed" <?php if ($statusFilter==='Completed') echo 'selected'; ?>>Completed</option>
            </select>
        </div>
        <div>
            <label class="block text-xs">Vendor Contains</label>
            <input name="vendor" type="text" class="p-2 border rounded" value="<?php echo htmlspecialchars($vendorFilter); ?>" />
        </div>
        <div>
            <button type="submit" class="px-3 py-2 bg-primary-500 text-white rounded">Filter</button>
        </div>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="p-2 text-left">ID</th>
                    <th class="p-2 text-left">Plate</th>
                    <th class="p-2 text-left">Name</th>
                    <th class="p-2 text-left">Phone</th>
                    <th class="p-2 text-left">Transfer Status</th>
                    <th class="p-2 text-left">Parts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $parts = json_decode($r['parts'], true) ?: [];
                ?>
                <tr class="border-b">
                    <td class="p-2 align-top"><a href="index.php?edit=<?php echo $r['id']; ?>" class="text-primary-600 font-medium"><?php echo $r['id']; ?></a></td>
                    <td class="p-2 align-top"><?php echo htmlspecialchars($r['plate']); ?></td>
                    <td class="p-2 align-top"><?php echo htmlspecialchars($r['name']); ?></td>
                    <td class="p-2 align-top"><?php echo htmlspecialchars($r['phone']); ?></td>
                    <td class="p-2 align-top"><?php echo htmlspecialchars($r['status']); ?></td>
                    <td class="p-2 align-top">
                        <?php foreach ($parts as $p): ?>
                            <div class="mb-2 p-2 bg-slate-50 rounded">
                                <div class="text-sm font-semibold"><?php echo htmlspecialchars($p['name'] ?? ''); ?> <span class="text-xs text-slate-400">x<?php echo htmlspecialchars($p['qty'] ?? ''); ?></span></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($p['vendor'] ?? ''); ?> • <strong><?php echo htmlspecialchars($p['status'] ?? ''); ?></strong></div>
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-4 flex items-center justify-between">
        <div class="text-sm text-slate-600">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $total; ?> results)</div>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
                <a class="px-3 py-1 bg-white border rounded" href="parts.php?page=<?php echo $page-1; ?>&status=<?php echo urlencode($statusFilter); ?>&vendor=<?php echo urlencode($vendorFilter); ?>&export=<?php echo $export?1:0; ?>">Prev</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a class="px-3 py-1 bg-white border rounded" href="parts.php?page=<?php echo $page+1; ?>&status=<?php echo urlencode($statusFilter); ?>&vendor=<?php echo urlencode($vendorFilter); ?>&export=<?php echo $export?1:0; ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
