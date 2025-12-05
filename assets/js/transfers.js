/**
 * Transfers Management Module
 * Handles case/transfer operations, SMS parsing, table rendering
 */

// Parse Georgian bank SMS
window.parseBankSMS = function() {
    const text = document.getElementById('sms-input').value.trim();
    if (!text) return showToast('Error', 'Please paste SMS text', 'error');
    
    const patterns = [
        /მანქანის ნომერი:\s*([A-Za-z0-9]+)\s*დამზღვევი:\s*([^,]+),\s*([\d\.]+)/i,
        /plate:\s*([A-Za-z0-9]+).*?insured:\s*([^,]+),\s*([\d\.]+)/i,
        /([A-Z]{2}-\d{3}-[A-Z]{2}).*?([A-Z][a-z]+\s+[A-Z][a-z]+).*?([\d,\.]+)/
    ];
    
    let match = null;
    for (const pattern of patterns) {
        match = text.match(pattern);
        if (match) break;
    }
    
    if (!match) {
        return showToast('Parse Error', 'Could not extract data from SMS', 'error');
    }
    
    const [, plate, name, amountStr] = match;
    const amount = parseFloat(amountStr.replace(/,/g, ''));
    
    // Check for franchise
    const franchiseMatch = text.match(/ფრანშიზა[:\s]*([\d\.]+)|franchise[:\s]*([\d\.]+)/i);
    const franchise = franchiseMatch ? (franchiseMatch[1] || franchiseMatch[2]) : '';
    
    // Create new transfer
    const newTransfer = {
        plate: plate.toUpperCase(),
        name: name.trim(),
        amount: amount,
        franchise: franchise,
        status: 'New',
        phone: '',
        date: new Date().toISOString(),
        user_response: 'Pending',
        internalNotes: [],
        systemLogs: []
    };
    
    // Add to database
    fetchAPI('add_transfer', 'POST', newTransfer)
        .then(() => {
            showToast('Success', 'Transfer imported successfully', 'success');
            document.getElementById('sms-input').value = '';
            loadData();
        })
        .catch(err => {
            showToast('Error', err.message, 'error');
        });
};

// Render transfers table
window.renderTable = function() {
    const activeBody = document.getElementById('active-transfers-body');
    const newBody = document.getElementById('new-transfers-body');
    
    if (!activeBody || !newBody) return;
    
    // Split transfers
    const newTransfers = transfers.filter(t => t.status === 'New');
    const activeTransfers = transfers.filter(t => t.status !== 'New');
    
    // Update stats
    const statNew = document.getElementById('stat-new');
    const statProcessing = document.getElementById('stat-processing');
    const statScheduled = document.getElementById('stat-scheduled');
    const statCompleted = document.getElementById('stat-completed');
    const badge = document.getElementById('new-count-badge');
    
    if (statNew) statNew.textContent = newTransfers.length;
    if (statProcessing) statProcessing.textContent = activeTransfers.filter(t => ['Processing', 'Called', 'Parts Ordered'].includes(t.status)).length;
    if (statScheduled) statScheduled.textContent = activeTransfers.filter(t => t.status === 'Scheduled').length;
    if (statCompleted) statCompleted.textContent = activeTransfers.filter(t => t.status === 'Completed').length;
    if (badge) badge.textContent = newTransfers.length;
    
    // Render new transfers
    if (newTransfers.length === 0) {
        newBody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-blue-400">No new cases</td></tr>`;
    } else {
        newBody.innerHTML = newTransfers.map(t => {
            const dateStr = new Date(t.date).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            return `
                <tr class="hover:bg-blue-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="bg-white border-2 border-blue-300 text-slate-800 font-mono font-bold px-2.5 py-1.5 rounded-lg text-sm shadow-sm">${t.plate}</div>
                            <div>
                                <div class="font-semibold text-sm text-blue-900">${t.name}</div>
                                ${t.franchise ? `<div class="text-xs text-orange-600">Franchise: ${t.franchise}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 font-mono font-bold text-blue-900">${t.amount} ₾</td>
                    <td class="px-6 py-4 text-sm text-blue-700">${dateStr}</td>
                    <td class="px-6 py-4 text-right">
                        ${CAN_EDIT ? `<button onclick="window.openEditModal(${t.id})" class="text-blue-600 hover:text-blue-800 p-2 hover:bg-blue-100 rounded-lg transition-all"><i data-lucide="arrow-right" class="w-4 h-4"></i></button>` : ''}
                    </td>
                </tr>
            `;
        }).join('');
    }
    
    // Render active transfers
    if (activeTransfers.length === 0) {
        activeBody.innerHTML = `<tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No active cases</td></tr>`;
    } else {
        activeBody.innerHTML = activeTransfers.map(t => {
            const dateStr = new Date(t.date).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            
            const statusColors = {
                'Processing': 'bg-blue-100 text-blue-800 border-blue-200',
                'Called': 'bg-purple-100 text-purple-800 border-purple-200',
                'Parts Ordered': 'bg-indigo-100 text-indigo-800 border-indigo-200',
                'Parts Arrived': 'bg-teal-100 text-teal-800 border-teal-200',
                'Scheduled': 'bg-orange-100 text-orange-800 border-orange-200',
                'Completed': 'bg-emerald-100 text-emerald-800 border-emerald-200',
                'Issue': 'bg-red-100 text-red-800 border-red-200'
            };
            const badgeClass = statusColors[t.status] || 'bg-slate-100 text-slate-600 border-slate-200';
            
            const hasPhone = t.phone ? 
                `<span class="flex items-center gap-1.5 text-slate-600 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100 w-fit"><i data-lucide="phone" class="w-3 h-3 text-slate-400"></i> ${t.phone}</span>` : 
                `<span class="text-red-400 text-xs flex items-center gap-1"><i data-lucide="alert-circle" class="w-3 h-3"></i> Missing</span>`;
            
            // User response
            let replyBadge = `<span class="bg-slate-100 text-slate-500 border border-slate-200 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 w-fit"><i data-lucide="help-circle" class="w-3 h-3"></i> Not Responded</span>`;
            
            if (t.user_response === 'Confirmed') {
                replyBadge = `<span class="bg-green-100 text-green-700 border border-green-200 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 w-fit"><i data-lucide="check" class="w-3 h-3"></i> Confirmed</span>`;
            } else if (t.user_response === 'Reschedule Requested') {
                let rescheduleInfo = '';
                let quickAcceptBtn = '';
                if (t.rescheduleDate) {
                    const reqDate = new Date(t.rescheduleDate.replace(' ', 'T'));
                    const dateStr = reqDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                    rescheduleInfo = `<div class="text-[9px] text-orange-600 mt-0.5 flex items-center gap-1"><i data-lucide="calendar" class="w-2.5 h-2.5"></i> ${dateStr}</div>`;
                    if (CAN_EDIT) {
                        quickAcceptBtn = `<button onclick="event.stopPropagation(); window.quickAcceptReschedule(${t.id})" class="mt-1 bg-green-600 hover:bg-green-700 text-white text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 transition-all active:scale-95 shadow-sm">
                            <i data-lucide="check" class="w-3 h-3"></i> Accept
                        </button>`;
                    }
                }
                replyBadge = `<div class="flex flex-col items-start gap-1">
                    <span class="bg-orange-100 text-orange-700 border border-orange-200 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 w-fit animate-pulse">
                        <i data-lucide="clock" class="w-3 h-3"></i> Reschedule Request
                    </span>
                    ${rescheduleInfo}
                    ${quickAcceptBtn}
                </div>`;
            }

            return `
                <tr class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors group">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="bg-white border border-slate-200 text-slate-800 font-mono font-bold px-2.5 py-1.5 rounded-lg text-sm shadow-sm">${t.plate}</div>
                            <div>
                                <div class="font-semibold text-sm text-slate-800">${t.name}</div>
                                <div class="text-xs text-slate-400 font-mono">${t.amount} ₾</div>
                                <div class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5"><i data-lucide="clock" class="w-3 h-3"></i> ${dateStr}</div>
                                ${t.franchise ? `<div class="text-[10px] text-orange-500 mt-0.5">Franchise: ${t.franchise}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-[10px] uppercase tracking-wider font-bold border ${badgeClass}">${t.status}</span></td>
                    <td class="px-6 py-4 text-sm">${hasPhone}</td>
                    <td class="px-6 py-4">${replyBadge}</td>
                    <td class="px-6 py-4 text-right">
                        ${CAN_EDIT ? 
                            `<button onclick="window.openEditModal(${t.id})" class="text-slate-400 hover:text-primary-600 p-2 hover:bg-primary-50 rounded-lg transition-all"><i data-lucide="settings-2" class="w-4 h-4"></i></button>` :
                            `<button onclick="window.viewCase(${t.id})" class="text-slate-400 hover:text-blue-600 p-2 hover:bg-blue-50 rounded-lg transition-all" title="View Only"><i data-lucide="eye" class="w-4 h-4"></i></button>`
                        }
                    </td>
                </tr>`;
        }).join('');
    }
    
    initLucide();
};

// Open edit modal
window.openEditModal = function(id) {
    const t = transfers.find(i => i.id == id);
    if (!t) return;
    
    window.currentEditingId = id;
    
    const modalPlate = document.getElementById('modal-plate');
    const modalName = document.getElementById('modal-customer-name');
    const modalAmount = document.getElementById('modal-amount');
    const modalDate = document.getElementById('modal-date');
    const inputStatus = document.getElementById('input-status');
    const inputPhone = document.getElementById('input-phone');
    const inputFranchise = document.getElementById('input-franchise');
    const inputNotes = document.getElementById('input-notes');
    
    if (modalPlate) modalPlate.textContent = t.plate;
    if (modalName) modalName.textContent = t.name;
    if (modalAmount) modalAmount.textContent = t.amount + ' ₾';
    if (modalDate) modalDate.textContent = new Date(t.date).toLocaleString();
    if (inputStatus) inputStatus.value = t.status;
    if (inputPhone) inputPhone.value = t.phone || '';
    if (inputFranchise) inputFranchise.value = t.franchise || '';
    if (inputNotes) inputNotes.value = (t.internalNotes || []).join('\n');
    
    const serviceDate = t.serviceDate ? t.serviceDate.replace(' ', 'T').slice(0, 16) : '';
    const inputServiceDate = document.getElementById('input-service-date');
    if (inputServiceDate) inputServiceDate.value = serviceDate;
    
    // User response
    const responseSection = document.getElementById('modal-response-section');
    if (responseSection) {
        if (t.user_response && t.user_response !== 'Pending') {
            responseSection.classList.remove('hidden');
            const modalUserResponse = document.getElementById('modal-user-response');
            if (modalUserResponse) modalUserResponse.textContent = t.user_response;
        } else {
            responseSection.classList.add('hidden');
        }
    }
    
    // Reviews
    const reviewSection = document.getElementById('modal-reviews-section');
    if (reviewSection) {
        if (t.review_stars) {
            reviewSection.classList.remove('hidden');
            const modalReviewStars = document.getElementById('modal-review-stars');
            const modalReviewRating = document.getElementById('modal-review-rating');
            const modalReviewComment = document.getElementById('modal-review-comment');
            if (modalReviewStars) modalReviewStars.innerHTML = '★'.repeat(t.review_stars) + '☆'.repeat(5 - t.review_stars);
            if (modalReviewRating) modalReviewRating.textContent = `${t.review_stars}/5`;
            if (modalReviewComment) modalReviewComment.textContent = t.review_comment || 'No comment';
        } else {
            reviewSection.classList.add('hidden');
        }
    }
    
    // Reschedule
    const rescheduleSection = document.getElementById('modal-reschedule-section');
    if (rescheduleSection) {
        if (t.user_response === 'Reschedule Requested' && (t.rescheduleDate || t.rescheduleComment)) {
            rescheduleSection.classList.remove('hidden');
            if (t.rescheduleDate) {
                const requestedDate = new Date(t.rescheduleDate.replace(' ', 'T'));
                const modalRescheduleDate = document.getElementById('modal-reschedule-date');
                if (modalRescheduleDate) modalRescheduleDate.innerText = requestedDate.toLocaleString();
            }
            const modalRescheduleComment = document.getElementById('modal-reschedule-comment');
            if (modalRescheduleComment) modalRescheduleComment.innerText = t.rescheduleComment || 'No comment';
        } else {
            rescheduleSection.classList.add('hidden');
        }
    }
    
    const editModal = document.getElementById('edit-modal');
    if (editModal) editModal.classList.remove('hidden');
    initLucide();
};

window.closeModal = () => { 
    document.getElementById('edit-modal').classList.add('hidden'); 
    window.currentEditingId = null; 
};

window.viewCase = function(id) {
    window.openEditModal(id);
    if (!CAN_EDIT) {
        const modal = document.getElementById('edit-modal');
        modal.querySelectorAll('input, select, textarea, button[onclick*="save"]').forEach(el => {
            el.disabled = true;
        });
        const saveBtn = modal.querySelector('button[onclick*="saveEdit"]');
        if (saveBtn) {
            saveBtn.textContent = 'Close';
            saveBtn.onclick = window.closeModal;
        }
    }
};

window.saveEdit = async () => {
    if (!CAN_EDIT) {
        showToast('Permission Denied', 'You do not have permission to edit cases', 'error');
        return;
    }
    
    const t = transfers.find(i => i.id == window.currentEditingId);
    const status = document.getElementById('input-status').value;
    const phone = document.getElementById('input-phone').value;
    const serviceDate = document.getElementById('input-service-date').value;
    
    if (status === 'Parts Arrived' && !serviceDate) {
        return showToast("Scheduling Required", "Please select a service date to save 'Parts Arrived' status.", "error");
    }

    const updates = {
        status,
        phone,
        serviceDate: serviceDate || null,
        franchise: document.getElementById('input-franchise').value,
        internalNotes: document.getElementById('input-notes').value.split('\n').filter(n => n.trim()),
    };

    try {
        await fetchAPI(`update_transfer&id=${window.currentEditingId}`, 'POST', updates);
        showToast('Success', 'Transfer updated successfully', 'success');
        window.closeModal();
        loadData();
    } catch (err) {
        showToast('Error', err.message, 'error');
    }
};

// Reschedule functions
window.acceptReschedule = async function() {
    const t = transfers.find(i => i.id == window.currentEditingId);
    if (!t || !t.rescheduleDate) return;
    
    if (!confirm(`Accept reschedule request for ${t.rescheduleDate}?`)) return;
    
    try {
        await fetchAPI(`accept_reschedule&id=${window.currentEditingId}`, 'POST', {});
        showToast('Success', 'Reschedule accepted and SMS sent', 'success');
        window.closeModal();
        loadData();
    } catch (err) {
        showToast('Error', err.message, 'error');
    }
};

window.declineReschedule = async function() {
    if (!confirm('Decline this reschedule request?')) return;
    
    try {
        await fetchAPI(`decline_reschedule&id=${window.currentEditingId}`, 'POST', {});
        showToast('Success', 'Reschedule declined', 'success');
        window.closeModal();
        loadData();
    } catch (err) {
        showToast('Error', err.message, 'error');
    }
};

window.quickAcceptReschedule = async function(id) {
    const t = transfers.find(i => i.id == id);
    if (!t) return;
    
    if (!confirm(`Quick accept reschedule for ${t.name}?`)) return;
    
    try {
        await fetchAPI(`accept_reschedule&id=${id}`, 'POST', {});
        showToast('Success', 'Reschedule accepted', 'success');
        loadData();
    } catch (err) {
        showToast('Error', err.message, 'error');
    }
};

// SMS sending
window.sendSMS = async function(phone, message, type) {
    try {
        await fetchAPI('send_sms', 'POST', { phone, message, type });
        showToast('SMS Sent', 'Message delivered successfully', 'success');
    } catch (err) {
        showToast('Error', 'Failed to send SMS', 'error');
    }
};
