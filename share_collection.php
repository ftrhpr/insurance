<?php
require_once 'session_config.php';
if (empty($_GET['id'])) {
    header('Location: parts_collection.php');
    exit;
}
$collection_id = $_GET['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Parts Collection - OTOMOTORS Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col items-center justify-center">
    <div class="glass-card max-w-md w-full mx-auto mt-10 p-8 rounded-2xl shadow-xl border border-white/20 flex flex-col items-center">
        <h1 class="text-2xl font-bold gradient-text mb-2 text-center">Share Parts Collection</h1>
        <div class="text-gray-700 text-center mb-4">Share this link or QR code with a manager to start the parts collection process.</div>
        <input type="text" readonly id="shareLink" value="<?php echo htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/edit_collection.php?id=' . urlencode($collection_id)); ?>" class="w-full text-xs bg-gray-100 rounded px-2 py-1 border border-gray-200 mb-4 cursor-pointer" onclick="this.select()" title="Shareable link">
        <div id="qrCodeContainer" class="mb-4"></div>
        <button onclick="printQrCode()" class="px-4 py-2 rounded bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 mb-4">Print QR Code</button>
        <div id="partsListContainer" class="w-full mt-2">
            <div class="font-semibold text-gray-800 mb-2 text-center">Parts List</div>
            <div id="partsListLoading" class="text-xs text-gray-500 text-center">Loading parts list...</div>
            <div id="partsList"></div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            var url = document.getElementById('shareLink').value;
            new QRCode(document.getElementById('qrCodeContainer'), { text: url, width: 180, height: 180 });
            // Fetch and render parts list
            fetch('api.php?action=get_parts_collection&id=<?php echo urlencode($collection_id); ?>')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('partsListLoading').style.display = 'none';
                    if (!data.success || !data.collection) {
                        document.getElementById('partsList').innerHTML = '<div class="text-xs text-red-500 text-center">Could not load parts list.</div>';
                        return;
                    }
                    let parts = [];
                    try {
                        parts = JSON.parse(data.collection.parts_list);
                    } catch (e) {}
                    if (!parts.length) {
                        document.getElementById('partsList').innerHTML = '<div class="text-xs text-gray-400 text-center">No parts found.</div>';
                        return;
                    }
                    let html = '<table class="w-full text-xs border rounded overflow-hidden"><thead><tr class="bg-gray-100 text-gray-700"><th class="p-2">Name</th><th class="p-2">Qty</th><th class="p-2">Price</th></tr></thead><tbody>';
                    parts.filter(p => p.type === 'part').forEach(part => {
                        html += `<tr class="border-b"><td class="p-2">${part.name}</td><td class="p-2 text-center">${part.quantity}</td><td class="p-2 text-right">₾${parseFloat(part.price).toFixed(2)}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    document.getElementById('partsList').innerHTML = html;
                });
        });
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
