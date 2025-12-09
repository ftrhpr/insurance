<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();

$statusFilter = $_GET['status'] ?? 'To Collect';
$vendorFilter = $_GET['vendor'] ?? '';
$plateFilter = $_GET['plate'] ?? '';

// Detect JSON support
$use_json = false;
try {
    $test = $pdo->query("SELECT JSON_EXTRACT('[{\"vendor\":\"x\"}]', '$[0].vendor')")->fetchColumn();
    if ($test !== false) $use_json = true;
} catch (Exception $ex) { $use_json = false; }

$where = $use_json ? "WHERE JSON_LENGTH(parts) > 0" : "WHERE parts IS NOT NULL AND parts <> '[]'";
$params = [];

if ($statusFilter) {
    if ($use_json) {
        // match any part with given status
        $where .= " AND JSON_CONTAINS(parts, JSON_QUOTE(?), '$[*].status')";
        $params[] = $statusFilter;
    } else {
        $where .= " AND parts LIKE ?";
        $params[] = '%' . $statusFilter . '%';
    }
}
if ($vendorFilter) {
    if ($use_json) {
        $where .= " AND (JSON_CONTAINS(parts, JSON_QUOTE(?), '$[*].vendor') OR parts LIKE ? )";
        $params[] = $vendorFilter;
        $params[] = '%' . $vendorFilter . '%';
    } else {
        $where .= " AND parts LIKE ?";
        $params[] = '%' . $vendorFilter . '%';
    }
}
if ($plateFilter) {
    $where .= " AND plate LIKE ?";
    $params[] = '%' . $plateFilter . '%';
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// total matching transfers
$countSql = "SELECT COUNT(*) FROM transfers $where";
$countStmt = $pdo->prepare($countSql);
$bi = 1; foreach ($params as $p) { $countStmt->bindValue($bi++, $p); }
$countStmt->execute();
$totalTransfers = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalTransfers / $perPage));

$sql = "SELECT id, plate, name, phone, status as transfer_status, parts FROM transfers $where ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$bind = 1; foreach ($params as $p) { $stmt->bindValue($bind++, $p); }
$stmt->bindValue($bind++, (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue($bind++, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$list = [];
foreach ($rows as $r) {
    $parts = json_decode($r['parts'], true) ?: [];
    foreach ($parts as $idx => $p) {
        $list[] = [
            'transfer_id' => $r['id'],
            'plate' => $r['plate'],
            'transfer_status' => $r['transfer_status'],
            'part_index' => $idx,
            'part_name' => $p['name'] ?? '',
            'qty' => $p['qty'] ?? '',
            'vendor' => $p['vendor'] ?? '',
            'part_status' => $p['status'] ?? '',
        ];
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Parts Collection - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
</head>
<body class="bg-slate-50 text-slate-800 font-sans">
<?php include 'header.php'; ?>
<main class="max-w-6xl mx-auto p-6">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Parts Collection</h1>
        <div class="text-sm text-slate-600">Quickly update part statuses</div>
    </div>

    <form method="get" class="mb-4 flex gap-2 items-end">
        <div>
            <label class="block text-xs">Status</label>
            <select name="status" class="p-2 border rounded">
                <option value="To Collect" <?php if ($statusFilter==='To Collect') echo 'selected'; ?>>To Collect</option>
                <option value="Collected" <?php if ($statusFilter==='Collected') echo 'selected'; ?>>Collected</option>
                <option value="In Transit" <?php if ($statusFilter==='In Transit') echo 'selected'; ?>>In Transit</option>
                <option value="Delivered" <?php if ($statusFilter==='Delivered') echo 'selected'; ?>>Delivered</option>
                <option value="Cancelled" <?php if ($statusFilter==='Cancelled') echo 'selected'; ?>>Cancelled</option>
            </select>
        </div>
        <div>
            <label class="block text-xs">Vendor</label>
            <input name="vendor" type="text" class="p-2 border rounded" value="<?php echo htmlspecialchars($vendorFilter); ?>" />
        </div>
        <div>
            <label class="block text-xs">Plate</label>
            <input name="plate" type="text" class="p-2 border rounded" value="<?php echo htmlspecialchars($plateFilter); ?>" />
        </div>
        <div>
            <button type="submit" class="px-3 py-2 bg-primary-500 text-white rounded">Filter</button>
        </div>
    </form>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="p-2 text-left">Transfer</th>
                    <th class="p-2 text-left">Plate</th>
                    <th class="p-2 text-left">Part</th>
                    <th class="p-2 text-left">Qty</th>
                    <th class="p-2 text-left">Vendor</th>
                    <th class="p-2 text-left">Status</th>
                    <th class="p-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $row): ?>
                <tr class="border-b">
                    <td class="p-2"><a href="index.php?edit=<?php echo $row['transfer_id']; ?>" class="text-primary-600"><?php echo $row['transfer_id']; ?></a></td>
                    <td class="p-2"><?php echo htmlspecialchars($row['plate']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($row['part_name']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($row['qty']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($row['vendor']); ?></td>
                    <td class="p-2">
                        <select data-transfer="<?php echo $row['transfer_id']; ?>" data-index="<?php echo $row['part_index']; ?>" class="part-status p-1 border rounded">
                            <option <?php if($row['part_status']==='To Collect') echo 'selected'; ?>>To Collect</option>
                            <option <?php if($row['part_status']==='Collected') echo 'selected'; ?>>Collected</option>
                            <option <?php if($row['part_status']==='In Transit') echo 'selected'; ?>>In Transit</option>
                            <option <?php if($row['part_status']==='Delivered') echo 'selected'; ?>>Delivered</option>
                            <option <?php if($row['part_status']==='Cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                    </td>
                    <td class="p-2">
                        <button class="btn-save-part px-3 py-1 bg-amber-600 text-white rounded" data-transfer="<?php echo $row['transfer_id']; ?>" data-index="<?php echo $row['part_index']; ?>">Save</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-sm text-slate-600">Showing <?php echo count($list); ?> parts (page <?php echo $page; ?> of <?php echo $totalPages; ?> — <?php echo $totalTransfers; ?> transfers)</div>

    <div class="mt-3 flex gap-2">
        <?php if ($page > 1): ?>
            <a class="px-3 py-1 bg-white border rounded" href="parts_collection.php?page=<?php echo $page-1; ?>&status=<?php echo urlencode($statusFilter); ?>&vendor=<?php echo urlencode($vendorFilter); ?>&plate=<?php echo urlencode($plateFilter); ?>">Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a class="px-3 py-1 bg-white border rounded" href="parts_collection.php?page=<?php echo $page+1; ?>&status=<?php echo urlencode($statusFilter); ?>&vendor=<?php echo urlencode($vendorFilter); ?>&plate=<?php echo urlencode($plateFilter); ?>">Next</a>
        <?php endif; ?>
    </div>

</main>

<script>
document.addEventListener('click', async (e) => {
    if (e.target && e.target.classList.contains('btn-save-part')) {
        const transfer = e.target.getAttribute('data-transfer');
        const index = Number(e.target.getAttribute('data-index'));
        const select = document.querySelector('select.part-status[data-transfer="'+transfer+'"][data-index="'+index+'"]');
        if (!select) return showToast('Error', 'Status selector not found', 'error');
        const status = select.value;
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        try {
            const res = await fetch('api.php?action=update_part', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ id: transfer, index: index, updates: { status: status } })
            });
            const j = await res.json();
            if (j && j.status === 'updated') {
                showToast('Success', 'Part updated', 'success');
            } else {
                showToast('Error', 'Update failed: ' + (j.error || j.message || 'unknown'), 'error');
            }
        } catch (err) { showToast('Error', 'Request failed: ' + err.message, 'error'); }
    }
});
</script>
<script>
// Minimal showToast fallback (uses existing showToast if present)
if (typeof window.showToast !== 'function') {
    window.showToast = function(title, message = '', type = 'info') {
        const containerId = 'parts-toast-container';
        let container = document.getElementById(containerId);
        if (!container) {
            container = document.createElement('div');
            container.id = containerId;
            container.style.position = 'fixed';
            container.style.right = '20px';
            container.style.top = '20px';
            container.style.zIndex = 99999;
            document.body.appendChild(container);
        }
        const el = document.createElement('div');
        el.style.background = (type==='success')? '#16a34a' : (type==='error'? '#ef4444' : '#0ea5e9');
        el.style.color = 'white';
        el.style.padding = '10px 14px';
        el.style.marginTop = '8px';
        el.style.borderRadius = '8px';
        el.style.boxShadow = '0 6px 18px rgba(2,6,23,0.12)';
        el.innerHTML = `<strong style="display:block;font-weight:600;margin-bottom:4px">${title}</strong><div style="font-size:13px">${message}</div>`;
        container.appendChild(el);
        setTimeout(() => { el.style.transition = 'opacity 0.4s'; el.style.opacity = '0'; setTimeout(()=>el.remove(), 500); }, 3500);
    };
}
</script>
</body>
</html>
