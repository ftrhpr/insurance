<?php
require_once 'session_config.php';
require_once 'config.php';

// Initialize PDO
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

if (empty($_GET['id'])) {
    header('Location: parts_collection.php');
    exit;
}
$collection_id = intval($_GET['id']);

// Fetch collection data
try {
    $stmt = $pdo->prepare("SELECT pc.*, t.plate as transfer_plate, t.name as transfer_name FROM parts_collections pc LEFT JOIN transfers t ON pc.transfer_id = t.id WHERE pc.id = ?");
    $stmt->execute([$collection_id]);
    $collection = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$collection) {
        die('Collection not found.');
    }
    $parts_list = json_decode($collection['parts_list'], true) ?: [];
} catch (Exception $e) {
    die('Error loading collection: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parts Collection - <?php echo htmlspecialchars($collection['transfer_plate'] . ' - ' . $collection['transfer_name']); ?> - OTOMOTORS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.9);
        }
        .gradient-text {
            background: linear-gradient(135deg, #0284c7 0%, #c026d3 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #0ea5e9 0%, #d946ef 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3), 0 4px 6px -2px rgba(217, 70, 239, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="glass-card rounded-2xl shadow-xl p-6 mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold gradient-text">Parts Collection</h1>
                    <p class="text-gray-700"><?php echo htmlspecialchars($collection['transfer_plate'] . ' - ' . $collection['transfer_name']); ?></p>
                    <p class="text-sm text-gray-600">ID: #<?php echo $collection['id']; ?> | Status: <?php echo ucfirst($collection['status']); ?></p>
                </div>
                <a href="edit_collection.php?id=<?php echo $collection['id']; ?>" class="btn-gradient px-6 py-3 text-white rounded-lg shadow-md hover:shadow-lg transition-all">
                    <i data-lucide="play" class="w-5 h-5 mr-2 inline"></i>
                    Start Collection Process
                </a>
            </div>
        </div>

        <!-- Parts List -->
        <div class="glass-card rounded-2xl shadow-xl p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center justify-between">
                <div class="flex items-center">
                    <i data-lucide="package" class="w-6 h-6 mr-2 text-purple-600"></i>
                    Parts to Collect
                </div>
                <button onclick="toggleLabors()" id="toggleLaborsBtn" class="px-3 py-1 text-sm rounded bg-gray-200 text-gray-700 hover:bg-gray-300">
                    <i data-lucide="eye" class="w-4 h-4 mr-1 inline"></i>
                    Show Services
                </button>
            </h2>
            <?php
            $parts = array_filter($parts_list, fn($item) => $item['type'] === 'part');
            $labors = array_filter($parts_list, fn($item) => $item['type'] === 'labor');
            ?>
            <?php if (empty($parts)): ?>
                <p class="text-gray-600">No parts listed for this collection.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($parts as $item): ?>
                        <div class="flex items-center justify-between p-4 bg-white/50 rounded-lg border border-gray-200">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="package" class="w-4 h-4 text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['name']); ?></p>
                                    <p class="text-sm text-gray-600">Qty: <?php echo $item['quantity']; ?> | Price: ₾<?php echo number_format($item['price'], 2); ?></p>
                                </div>
                            </div>
                            <?php if (isset($item['collected'])): ?>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-600">Collected:</span>
                                    <input type="checkbox" <?php echo $item['collected'] ? 'checked' : ''; ?> disabled class="w-5 h-5 text-green-600">
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Labors Section (Hidden by default) -->
            <div id="laborsSection" class="mt-6 hidden">
                <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center">
                    <i data-lucide="wrench" class="w-5 h-5 mr-2 text-sky-600"></i>
                    Services
                </h3>
                <?php if (empty($labors)): ?>
                    <p class="text-gray-600">No services listed for this collection.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($labors as $item): ?>
                            <div class="flex items-center justify-between p-4 bg-white/50 rounded-lg border border-gray-200">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-sky-100 rounded-full flex items-center justify-center">
                                        <i data-lucide="wrench" class="w-4 h-4 text-sky-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['name']); ?></p>
                                        <p class="text-sm text-gray-600">Qty: <?php echo $item['quantity']; ?> | Price: ₾<?php echo number_format($item['price'], 2); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Share Section -->
        <div class="glass-card rounded-2xl shadow-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                <i data-lucide="share-2" class="w-6 h-6 mr-2 text-blue-600"></i>
                Share This Collection
            </h2>
            <div class="flex flex-col sm:flex-row items-center gap-4">
                <input type="text" readonly value="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/share_collection.php?id=' . urlencode($collection_id)); ?>" class="flex-1 text-sm bg-gray-100 rounded px-3 py-2 border border-gray-200 cursor-pointer" onclick="this.select()" title="Shareable link">
                <button onclick="copyToClipboard(this.previousElementSibling.value)" class="px-4 py-2 rounded bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">Copy Link</button>
                <button onclick="showQrModal()" class="px-4 py-2 rounded bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700">Show QR Code</button>
            </div>
        </div>
    </div>

    <!-- QR Modal -->
    <div id="qrModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
        <div class="bg-white rounded-2xl shadow-2xl p-8 flex flex-col items-center relative min-w-[320px]">
            <button onclick="closeQrModal()" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            <div id="qrCodeContainer" class="mb-4"></div>
            <button onclick="printQrCode()" class="mt-2 px-4 py-2 rounded bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Print QR Code</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
        });

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Link copied to clipboard!');
            });
        }

        function toggleLabors() {
            const section = document.getElementById('laborsSection');
            const btn = document.getElementById('toggleLaborsBtn');
            if (section.classList.contains('hidden')) {
                section.classList.remove('hidden');
                btn.innerHTML = '<i data-lucide="eye-off" class="w-4 h-4 mr-1 inline"></i>Hide Services';
            } else {
                section.classList.add('hidden');
                btn.innerHTML = '<i data-lucide="eye" class="w-4 h-4 mr-1 inline"></i>Show Services';
            }
            lucide.createIcons();
        }

        function showQrModal() {
            const modal = document.getElementById('qrModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                const qrDiv = document.getElementById('qrCodeContainer');
                qrDiv.innerHTML = '';
                var url = '<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/share_collection.php?id=' . urlencode($collection_id)); ?>';
                new QRCode(qrDiv, { text: url, width: 180, height: 180 });
            }, 100);
        }

        function closeQrModal() {
            document.getElementById('qrModal').classList.add('hidden');
        }

        function printQrCode() {
            const qrDiv = document.getElementById('qrCodeContainer');
            const win = window.open('', '', 'width=400,height=500');
            win.document.write('<html><head><title>Print QR Code</title></head><body style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;"><div>' + qrDiv.innerHTML + '</div></body></html>');
            win.document.close();
            win.focus();
            setTimeout(() => { win.print(); win.close(); }, 500);
        }
    </script>
</body>
</html>
