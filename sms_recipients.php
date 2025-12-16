<?php
require_once 'session_config.php';
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$current_user_role = $_SESSION['role'] ?? 'viewer';
if ($current_user_role !== 'admin' && $current_user_role !== 'manager') {
    http_response_code(403);
    echo "<h2>Access Denied</h2><p>You do not have permission to view this page.</p>";
    exit;
}

// Try to load existing recipients server-side so page displays them immediately
$initialRecipients = [];
try {
    require_once 'config.php';
    $pdo = getDBConnection();
    $tableExists = $pdo->query("SHOW TABLES LIKE 'sms_recipients'")->rowCount() > 0;
    if ($tableExists) {
        $stmt = $pdo->prepare("SELECT id, name, phone, type, description, workflow_stages, template_slug, enabled FROM sms_recipients ORDER BY name");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['workflow_stages'] = json_decode($r['workflow_stages'] ?? '[]', true) ?: [];
            $r['enabled'] = (int)($r['enabled'] ?? 0);
        }
        $initialRecipients = $rows;
    }
} catch (Exception $e) {
    error_log('sms_recipients server load error: ' . $e->getMessage());
}
$initialRecipientsJson = json_encode($initialRecipients, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMS Recipients - OTOMOTORS</title>
    <!-- Fonts & Styles -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/fonts/include_fonts.php'; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Small adjustments for recipients table */
        .table-actions button { margin-left: 0.5rem; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">
    <div class="flex">
        <?php include 'sidebar.php'; ?>
        <main class="flex-1 ml-64 p-8">
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">SMS Recipients</h2>
                        <p class="text-slate-500 text-sm">Configure additional phone recipients for automated workflow notifications.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <input id="recipient-search" placeholder="Search name, phone, stage or template" class="px-3 py-2 rounded-lg border w-80 text-sm" />
                        <select id="filter-type" class="px-3 py-2 rounded-lg border text-sm">
                            <option value="">All types</option>
                            <option value="system">System</option>
                            <option value="parts">Parts</option>
                            <option value="other">Other</option>
                        </select>
                        <button onclick="openRecipientModal()" class="px-4 py-2 rounded bg-blue-600 text-white">Add Recipient</button>

                        <!-- CSV Import -->
                        <label class="flex items-center gap-2 px-3 py-2 rounded bg-white border cursor-pointer ml-2">
                            <i data-lucide="upload" class="w-4 h-4 text-slate-600"></i>
                            <span class="text-sm text-slate-700">Import CSV</span>
                            <input id="import-file" type="file" accept=".csv" class="hidden" />
                        </label>
                        <button onclick="startImport()" id="btn-import" class="px-3 py-2 rounded bg-amber-500 text-white">Import</button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-4">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="recipients-table">
                            <thead>
                                <tr class="text-left text-xs text-slate-500 uppercase">
                                    <th class="p-2">Name</th>
                                    <th class="p-2">Phone</th>
                                    <th class="p-2">Type</th>
                                    <th class="p-2">Stages</th>
                                    <th class="p-2">Template</th>
                                    <th class="p-2">Enabled</th>
                                    <th class="p-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="recipients-body" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

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
                <label class="text-sm font-medium">Workflow Stages</label>
                <div id="workflow-stages" class="grid grid-cols-2 gap-2 text-sm text-slate-700">
                    <label><input type="checkbox" value="New"> New</label>
                    <label><input type="checkbox" value="Processing"> Processing</label>
                    <label><input type="checkbox" value="Called"> Called</label>
                    <label><input type="checkbox" value="Parts Ordered"> Parts Ordered</label>
                    <label><input type="checkbox" value="Parts Arrived"> Parts Arrived</label>
                    <label><input type="checkbox" value="Scheduled"> Scheduled</label>
                    <label><input type="checkbox" value="Completed"> Completed</label>
                    <label><input type="checkbox" value="Issue"> Issue</label>
                </div>
            </div>
            <div>
                <label class="text-sm font-medium">Template (Optional)</label>
                <select id="recipient-template" class="w-full p-2 border rounded">
                    <option value="">-- None --</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Description</label>
                <textarea id="recipient-description" class="w-full p-2 border rounded" rows="3"></textarea>
            </div>
            <div>
                <label class="inline-flex items-center gap-2"><input id="recipient-enabled" type="checkbox" checked> <span class="text-sm">Enabled</span></label>
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

// Use server-provided recipients when available to show existing entries immediately
let recipientsCache = (typeof initialRecipients !== 'undefined' && Array.isArray(initialRecipients)) ? initialRecipients : [];

function renderRecipients() {
    const body = document.getElementById('recipients-body');
    body.innerHTML = '';
    const q = (document.getElementById('recipient-search')?.value || '').toLowerCase();
    const typeFilter = (document.getElementById('filter-type')?.value || '').toLowerCase();

    const list = recipientsCache.filter(r => {
        if (typeFilter && (r.type || '').toLowerCase() !== typeFilter) return false;
        if (!q) return true;
        const stages = (r.workflow_stages || []).join(' ').toLowerCase();
        return (r.name || '').toLowerCase().includes(q) || (r.phone || '').toLowerCase().includes(q) || stages.includes(q) || (r.template_slug || '').toLowerCase().includes(q);
    });

    list.forEach(r => {
        const tr = document.createElement('tr');
        const stages = (r.workflow_stages || []).join(', ');
        tr.innerHTML = `
            <td class="p-2">${escapeHtml(r.name)}</td>
            <td class="p-2">${escapeHtml(r.phone)}</td>
            <td class="p-2">${escapeHtml(r.type || '')}</td>
            <td class="p-2">${escapeHtml(stages)}</td>
            <td class="p-2">${escapeHtml(r.template_slug || '')}</td>
            <td class="p-2">${r.enabled ? '<span class="text-emerald-600 font-semibold">Yes</span>' : '<span class="text-slate-400">No</span>'}</td>
            <td class="p-2 table-actions"><button onclick="editRecipient(${r.id})" class="px-2 py-1 rounded bg-white border text-blue-600 text-sm">Edit</button><button onclick="deleteRecipient(${r.id})" class="px-2 py-1 rounded bg-white border text-red-600 text-sm">Delete</button></td>
        `;
        body.appendChild(tr);
    });
}

async function loadRecipients() {
    const data = await fetchAPI('get_sms_recipients');
    if (!data || !data.recipients) {
        recipientsCache = [];
        renderRecipients();
        return;
    }
    recipientsCache = data.recipients;
    renderRecipients();
}

function escapeHtml(s) { return String(s || '').replace(/[&<>\"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]); }

async function openRecipientModal() {
    document.getElementById('recipient-id').value = '';
    document.getElementById('recipient-name').value = '';
    document.getElementById('recipient-phone').value = '';
    document.getElementById('recipient-type').value = 'system';
    document.getElementById('recipient-description').value = '';
    document.getElementById('recipient-template').value = '';
    document.getElementById('recipient-enabled').checked = true;
    document.getElementById('recipient-modal-title').textContent = 'Add Recipient';

    // Clear workflow checkboxes
    document.querySelectorAll('#workflow-stages input[type=checkbox]').forEach(cb => cb.checked = false);

    // Load templates for selector
    const tdata = await fetchAPI('get_sms_templates');
    const tpl = document.getElementById('recipient-template');
    tpl.innerHTML = '<option value="">-- None --</option>';
    if (tdata && Array.isArray(tdata)) {
        tdata.forEach(t => {
            const opt = document.createElement('option'); opt.value = t.slug; opt.textContent = t.slug; tpl.appendChild(opt);
        });
    } else if (tdata && tdata.templates) {
        tdata.templates.forEach(t => {
            const opt = document.createElement('option'); opt.value = t.slug; opt.textContent = t.slug; tpl.appendChild(opt);
        });
    }

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
    const enabled = document.getElementById('recipient-enabled').checked ? 1 : 0;
    const template_slug = document.getElementById('recipient-template').value || '';
    const workflow = Array.from(document.querySelectorAll('#workflow-stages input[type=checkbox]:checked')).map(cb => cb.value);
    if (!name || !phone) return alert('Name and phone required');
    const res = await fetchAPI('save_sms_recipient', 'POST', { id, name, phone, type, description, workflow_stages: workflow, template_slug, enabled });
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
    document.getElementById('recipient-template').value = rec.template_slug || '';
    document.getElementById('recipient-enabled').checked = !!rec.enabled;

    // Set workflow checkboxes
    document.querySelectorAll('#workflow-stages input[type=checkbox]').forEach(cb => {
        cb.checked = (rec.workflow_stages || []).includes(cb.value);
    });

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

async function startImport() {
    const fileInput = document.getElementById('import-file');
    const file = fileInput.files && fileInput.files[0];
    if (!file) return alert('Please choose a CSV file to import');

    const ok = confirm('Importing will add or update recipients by phone. Continue?');
    if (!ok) return;

    const btn = document.getElementById('btn-import');
    btn.disabled = true; btn.textContent = 'Importing...';

    const fd = new FormData();
    fd.append('file', file);

    try {
        const res = await fetch(`api.php?action=import_sms_recipients`, { method: 'POST', body: fd });
        const j = await res.json();
        if (j && j.status === 'success') {
            alert(`Imported: ${j.imported}, Updated: ${j.updated}\nErrors: ${j.errors.length}`);
            loadRecipients();
        } else {
            alert('Import failed: ' + (j.message || j.error || JSON.stringify(j)));
        }
    } catch (e) {
        console.error('Import error', e);
        alert('Import failed');
    }

    btn.disabled = false; btn.textContent = 'Import';
}

// Expose initialRecipients from server and initialize UI
window.initialRecipients = <?php echo $initialRecipientsJson ?: '[]'; ?> || [];
// Load recipients and templates on page load
loadRecipients();
(async function loadTemplatesOnStart(){
    const tdata = await fetchAPI('get_sms_templates');
    const tpl = document.getElementById('recipient-template');
    if (!tpl) return;
    tpl.innerHTML = '<option value="">-- None --</option>';
    if (tdata && Array.isArray(tdata)) {
        tdata.forEach(t => tpl.appendChild(Object.assign(document.createElement('option'), { value: t.slug, textContent: t.slug })));
    } else if (tdata && tdata.templates) {
        tdata.templates.forEach(t => tpl.appendChild(Object.assign(document.createElement('option'), { value: t.slug, textContent: t.slug })));
    }
})();

// Search & filters
document.getElementById('recipient-search')?.addEventListener('input', () => renderRecipients());
document.getElementById('filter-type')?.addEventListener('change', () => renderRecipients());
</script>
</body>
</html>