<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// User info
$current_user_name = $_SESSION['full_name'] ?? 'User';
$current_user_role = $_SESSION['role'] ?? 'viewer';

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Collectors - OTOMOTORS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>* { font-family: 'Inter', -apple-system, system-ui, sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

<?php include 'header.php'; ?>

<main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white/80 backdrop-blur-xl rounded-2xl shadow-xl border border-slate-200/60 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Collectors</h1>
                <p class="text-sm text-slate-500">Manage collection partners who pick up parts</p>
            </div>
            <div>
                <?php if ($current_user_role === 'admin' || $current_user_role === 'manager'): ?>
                <button id="btn-add-collector" class="px-4 py-2 bg-emerald-600 text-white rounded-lg shadow">Add Collector</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex items-center gap-3 mb-4">
            <input id="collectors-search" placeholder="Search collectors by name or phone" class="p-2 border rounded flex-1" />
            <select id="collectors-perpage" class="p-2 border rounded">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table id="collectors-table" class="w-full text-sm border-collapse">
                <thead class="text-left text-xs text-slate-500 border-b"><tr><th class="py-2">Name</th><th class="py-2">Phone</th><th class="py-2">Notes</th><th class="py-2 text-right">Actions</th></tr></thead>
                <tbody class="bg-white" id="collectors-tbody">
                    <tr><td colspan="4" class="py-6 text-center text-slate-400">Loading collectors...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between mt-4">
            <div id="collectors-paging-info" class="text-sm text-slate-500">&nbsp;</div>
            <div id="collectors-pagination" class="flex items-center gap-2"></div>
        </div>

        <!-- Editor drawer -->
        <div id="collector-editor" class="hidden mt-6 bg-slate-50 p-4 border rounded-lg">
            <h3 class="font-bold mb-2">Collector</h3>
            <div class="grid grid-cols-1 gap-2">
                <input id="collector-name" placeholder="Full name" class="p-2 border rounded" />
                <input id="collector-phone" placeholder="Phone" class="p-2 border rounded" />
                <textarea id="collector-notes" rows="4" placeholder="Notes" class="p-2 border rounded"></textarea>
                <div id="collector-error" class="text-red-600 text-sm hidden"></div>
            </div>
            <div class="flex gap-2 justify-end mt-3">
                <button id="collector-cancel" class="px-3 py-2 border rounded">Cancel</button>
                <button id="collector-save" class="px-3 py-2 bg-amber-600 text-white rounded">Save</button>
            </div>
        </div>

    </div>
</main>

<script>
    const CAN_MANAGE = <?php echo ($current_user_role === 'admin' || $current_user_role === 'manager') ? 'true' : 'false'; ?>;

    // Simple helper: fetch API wrapper
    async function fetchAPI(action, method='GET', body=null) {
        const opts = { method: method };
        if (body) { opts.headers = { 'Content-Type': 'application/json' }; opts.body = JSON.stringify(body); }
        const res = await fetch(`api.php?action=${action}`, opts);
        return res.json();
    }

    // Client-side list, search and pagination
    let collectorsList = [];
    let collectorsPage = 1;
    let collectorsPerPage = parseInt(document.getElementById('collectors-perpage').value || '10');

    async function loadCollectors() {
        const tbody = document.getElementById('collectors-tbody');
        tbody.innerHTML = '<tr><td colspan="4" class="py-6 text-center text-slate-400">Loading collectors...</td></tr>';
        try {
            const list = await fetchAPI('get_collectors');
            collectorsList = Array.isArray(list) ? list : [];
            collectorsPage = 1;
            renderCollectorsPage();
        } catch (err) {
            console.error('Failed to load collectors', err);
            tbody.innerHTML = '<tr><td colspan="4" class="py-6 text-center text-red-500">Failed to load</td></tr>';
        }
    }

    function renderCollectorsPage() {
        const tbody = document.getElementById('collectors-tbody');
        const search = document.getElementById('collectors-search').value.toLowerCase().trim();
        collectorsPerPage = parseInt(document.getElementById('collectors-perpage').value || '10');

        // filter
        const filtered = collectorsList.filter(c => {
            if (!search) return true;
            const hay = `${c.name} ${c.phone || ''} ${c.notes || ''}`.toLowerCase();
            return hay.includes(search);
        });

        const total = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / collectorsPerPage));
        if (collectorsPage > totalPages) collectorsPage = totalPages;

        const start = (collectorsPage - 1) * collectorsPerPage;
        const pageItems = filtered.slice(start, start + collectorsPerPage);

        if (pageItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="py-6 text-center text-slate-400">No collectors found</td></tr>';
        } else {
            tbody.innerHTML = '';
            pageItems.forEach(c => {
                const tr = document.createElement('tr');
                const phoneText = c.phone ? formatPhone(c.phone) : '';
                tr.innerHTML = `<td class="py-3">${escapeHtml(c.name)}</td><td class="py-3">${escapeHtml(phoneText)}</td><td class="py-3">${escapeHtml(c.notes||'')}</td><td class="py-3 text-right">${CAN_MANAGE ? `<button data-id="${c.id}" class="edit-btn px-2 py-1 text-xs bg-white border rounded mr-2">Edit</button><button data-id="${c.id}" class="delete-btn px-2 py-1 text-xs bg-red-600 text-white rounded">Delete</button>` : ''}</td>`;
                tbody.appendChild(tr);
            });
            // Wire buttons
            document.querySelectorAll('.edit-btn').forEach(b=>b.addEventListener('click', e=>openEditor(e.currentTarget.getAttribute('data-id'))));
            document.querySelectorAll('.delete-btn').forEach(b=>b.addEventListener('click', async e=>{
                const id = e.currentTarget.getAttribute('data-id');
                if (!confirm('Delete this collector?')) return;
                try {
                    await fetchAPI(`delete_collector&id=${id}`, 'POST', {});
                    await loadCollectors();
                    showToast('Deleted', 'Collector removed');
                } catch (err) { console.error(err); showToast('Error', 'Delete failed'); }
            }));
        }

        // pagination controls
        const pageInfo = document.getElementById('collectors-paging-info');
        const pagination = document.getElementById('collectors-pagination');
        pageInfo.innerText = `Showing ${Math.min(start+1, total)}-${Math.min(start + pageItems.length, total)} of ${total}`;
        pagination.innerHTML = '';
        const prev = document.createElement('button'); prev.className='px-3 py-1 border rounded'; prev.innerText='Prev'; prev.disabled = collectorsPage<=1; prev.addEventListener('click', ()=>{ collectorsPage--; renderCollectorsPage(); });
        const next = document.createElement('button'); next.className='px-3 py-1 border rounded'; next.innerText='Next'; next.disabled = collectorsPage>=totalPages; next.addEventListener('click', ()=>{ collectorsPage++; renderCollectorsPage(); });
        pagination.appendChild(prev);
        // page numbers
        const maxPagesToShow = 5; let startPage = Math.max(1, collectorsPage - 2); let endPage = Math.min(totalPages, startPage + maxPagesToShow -1);
        if (endPage - startPage < maxPagesToShow -1) startPage = Math.max(1, endPage - maxPagesToShow +1);
        for (let p=startPage;p<=endPage;p++){
            const b = document.createElement('button'); b.className='px-2 py-1 border rounded ' + (p===collectorsPage? 'bg-slate-800 text-white':'' ); b.innerText = p; b.addEventListener('click', ()=>{ collectorsPage = p; renderCollectorsPage(); }); pagination.appendChild(b);
        }
        pagination.appendChild(next);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const d = document.createElement('div'); d.textContent = text; return d.innerHTML;
    }

    // Format phone for display (same rules as index)
    function formatPhone(p) {
        if (!p) return '';
        const plus = p.startsWith('+') ? '+' : '';
        const digits = p.replace(/\D/g, '');
        if (digits.length > 9) {
            const cc = digits.slice(0, digits.length - 9);
            const rest = digits.slice(digits.length - 9);
            return `${plus}${cc} ${rest.slice(0,3)} ${rest.slice(3,6)} ${rest.slice(6)}`;
        }
        return plus + digits.replace(/(\d{3})(?=\d)/g, '$1 ').trim();
    }

    // Editor functions
    function openEditor(id) {
        if (!CAN_MANAGE) { alert('Permission denied'); return; }
        const editor = document.getElementById('collector-editor');
        const name = document.getElementById('collector-name');
        const phone = document.getElementById('collector-phone');
        const notes = document.getElementById('collector-notes');
        editor.setAttribute('data-edit-id', id || '');
        if (!id) {
            name.value=''; phone.value=''; notes.value=''; editor.classList.remove('hidden'); return;
        }
        // load item
        fetchAPI('get_collectors').then(list=>{
            const it = list.find(x=>String(x.id)===String(id));
            if (!it) { showInlineError('Collector not found'); return; }
            name.value = it.name || '';
            phone.value = it.phone || '';
            notes.value = it.notes || '';
            document.getElementById('collector-error').classList.add('hidden');
            editor.classList.remove('hidden');
        }).catch(err=>{ console.error(err); showInlineError('Failed to load collector'); });
    }

    document.getElementById('btn-add-collector')?.addEventListener('click', ()=>openEditor(''));
    document.getElementById('collector-cancel').addEventListener('click', ()=>{
        document.getElementById('collector-editor').classList.add('hidden');
    });
    document.getElementById('collector-save').addEventListener('click', async ()=>{
        clearInlineError();
        const editor = document.getElementById('collector-editor');
        const id = editor.getAttribute('data-edit-id');
        const name = document.getElementById('collector-name').value.trim();
        const phone = document.getElementById('collector-phone').value.trim();
        const notes = document.getElementById('collector-notes').value.trim();
        if (!name) return showInlineError('Name is required');
        // Basic client-side phone validation
        if (phone) {
            const digits = phone.replace(/\D/g, '');
            if (digits.length < 6) return showInlineError('Phone must contain at least 6 digits');
        }
        const payload = { name, phone, notes };
        try {
            if (id) {
                const res = await fetchAPI(`update_collector&id=${id}`, 'POST', payload);
                if (res?.status === 'error') return showInlineError(res.message || 'Failed to update');
                showToast('Saved', 'Collector updated');
            } else {
                const res = await fetchAPI('create_collector', 'POST', payload);
                if (res?.status === 'error') return showInlineError(res.message || 'Failed to create');
                showToast('Created', 'Collector added');
            }
            document.getElementById('collector-editor').classList.add('hidden');
            await loadCollectors();
        } catch (err) { console.error(err); showInlineError('Save failed'); }
    });

    function showInlineError(msg) {
        const el = document.getElementById('collector-error');
        if (!el) return alert(msg);
        el.innerText = msg;
        el.classList.remove('hidden');
    }
    function clearInlineError() {
        const el = document.getElementById('collector-error');
        if (!el) return; el.innerText = ''; el.classList.add('hidden');
    }

    // Simple toast
    function showToast(title, text) {
        const t = document.createElement('div');
        t.className = 'fixed right-4 bottom-4 bg-slate-900 text-white px-4 py-2 rounded shadow-lg';
        t.innerHTML = `<strong class="block">${title}</strong><span class="block text-sm">${text}</span>`;
        document.body.appendChild(t);
        setTimeout(()=>{ t.remove(); }, 3000);
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', ()=>{ loadCollectors(); });
    // Wire search and per-page
    document.getElementById('collectors-search').addEventListener('input', ()=>{ collectorsPage = 1; renderCollectorsPage(); });
    document.getElementById('collectors-perpage').addEventListener('change', ()=>{ collectorsPage = 1; renderCollectorsPage(); });
</script>

</body>
</html>
