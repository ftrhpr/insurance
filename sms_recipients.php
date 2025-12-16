<?php
$current_user_role = $_SESSION['role'] ?? 'viewer';
if ($current_user_role !== 'admin' && $current_user_role !== 'manager') {
    http_response_code(403);
    echo "<h2>Access Denied</h2><p>You do not have permission to view this page.</p>";
    exit;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMS Recipients - OTOMOTORS</title>
    <?php include 'header.php'; ?>
</head>
<body class="bg-slate-50">
<main class="max-w-5xl mx-auto p-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold">SMS Recipients</h1>
            <button onclick="openRecipientModal()" class="px-4 py-2 rounded bg-blue-600 text-white">Add Recipient</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="recipients-table">
                <thead>
                    <tr class="text-left text-xs text-slate-500 uppercase">
                        <th class="p-2">Name</th>
                        <th class="p-2">Phone</th>
                        <th class="p-2">Type</th>
                        <th class="p-2">Description</th>
                        <th class="p-2">Actions</th>
                    </tr>
                </thead>
                <tbody id="recipients-body" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal -->
<div id="recipient-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4" id="recipient-modal-title">Add Recipient</h3>
        <div class="space-y-3">
            <input id="recipient-id" type="hidden">
            <div>
                <label class="text-sm font-medium">Name</label>
                <input id="recipient-name" class="w-full p-2 border rounded" type="text">
            </div>
            <div>
                <label class="text-sm font-medium">Phone</label>
                <input id="recipient-phone" class="w-full p-2 border rounded" type="text">
            </div>
            <div>
                <label class="text-sm font-medium">Type</label>
                <select id="recipient-type" class="w-full p-2 border rounded">
                    <option value="system">System</option>
                    <option value="parts">Parts</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Description</label>
                <textarea id="recipient-description" class="w-full p-2 border rounded" rows="3"></textarea>
            </div>
            <div class="flex gap-2 justify-end">
                <button onclick="closeRecipientModal()" class="px-4 py-2 rounded border">Cancel</button>
                <button onclick="saveRecipient()" class="px-4 py-2 rounded bg-blue-600 text-white">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
async function fetchAPI(action, method = 'GET', body = null) {
    try {
        const opts = { method, headers: {} };
        if (method === 'POST') {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body || {});
        }
        const res = await fetch(`api.php?action=${action}`, opts);
        return await res.json();
    } catch (e) {
        console.error('API error', e);
        return null;
    }
}

async function loadRecipients() {
    const data = await fetchAPI('get_sms_recipients');
    const body = document.getElementById('recipients-body');
    body.innerHTML = '';
    if (!data || !data.recipients) return;
    data.recipients.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="p-2">${escapeHtml(r.name)}</td>
            <td class="p-2">${escapeHtml(r.phone)}</td>
            <td class="p-2">${escapeHtml(r.type || '')}</td>
            <td class="p-2">${escapeHtml(r.description || '')}</td>
            <td class="p-2"><button onclick="editRecipient(${r.id})" class="mr-2 text-blue-600">Edit</button><button onclick="deleteRecipient(${r.id})" class="text-red-600">Delete</button></td>
        `;
        body.appendChild(tr);
    });
}

function escapeHtml(s) { return String(s || '').replace(/[&<>\"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]); }

function openRecipientModal() {
    document.getElementById('recipient-id').value = '';
    document.getElementById('recipient-name').value = '';
    document.getElementById('recipient-phone').value = '';
    document.getElementById('recipient-type').value = 'system';
    document.getElementById('recipient-description').value = '';
    document.getElementById('recipient-modal-title').textContent = 'Add Recipient';
    document.getElementById('recipient-modal').classList.remove('hidden');
    document.getElementById('recipient-modal').classList.add('flex');
}

function closeRecipientModal() {
    document.getElementById('recipient-modal').classList.remove('flex');
    document.getElementById('recipient-modal').classList.add('hidden');
}

async function saveRecipient() {
    const id = document.getElementById('recipient-id').value || null;
    const name = document.getElementById('recipient-name').value.trim();
    const phone = document.getElementById('recipient-phone').value.trim();
    const type = document.getElementById('recipient-type').value;
    const description = document.getElementById('recipient-description').value.trim();
    if (!name || !phone) return alert('Name and phone required');
    const res = await fetchAPI('save_sms_recipient', 'POST', { id, name, phone, type, description });
    if (res && res.status === 'success') {
        closeRecipientModal();
        loadRecipients();
    } else {
        alert('Save failed');
    }
}

async function editRecipient(id) {
    const data = await fetchAPI('get_sms_recipients');
    const rec = (data && data.recipients || []).find(r => Number(r.id) === Number(id));
    if (!rec) return alert('Recipient not found');
    document.getElementById('recipient-id').value = rec.id;
    document.getElementById('recipient-name').value = rec.name;
    document.getElementById('recipient-phone').value = rec.phone;
    document.getElementById('recipient-type').value = rec.type || 'system';
    document.getElementById('recipient-description').value = rec.description || '';
    document.getElementById('recipient-modal-title').textContent = 'Edit Recipient';
    document.getElementById('recipient-modal').classList.remove('hidden');
    document.getElementById('recipient-modal').classList.add('flex');
}

async function deleteRecipient(id) {
    if (!confirm('Delete this recipient?')) return;
    const res = await fetchAPI(`delete_sms_recipient&id=${id}`, 'POST', {});
    if (res && res.status === 'success') loadRecipients();
    else alert('Delete failed');
}

loadRecipients();
</script>
</body>
</html>