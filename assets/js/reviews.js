/**
 * Reviews Management Module
 * Handles customer review moderation
 */

window.loadReviews = async function() {
    try {
        const data = await fetchAPI('get_reviews', 'GET');
        reviews = data.reviews || [];
        const avgRating = data.average_rating || 0;
        
        renderReviewsTable();
        
        // Update average rating display
        const avgElement = document.getElementById('avg-rating');
        if (avgElement) {
            avgElement.textContent = avgRating.toFixed(1);
        }
    } catch (err) {
        console.error('Error loading reviews:', err);
        showToast('Error', 'Failed to load reviews', 'error');
    }
}

function renderReviewsTable() {
    const tbody = document.getElementById('reviews-table-body');
    if (!tbody) return;
    
    if (reviews.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                    <i data-lucide="star" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                    <p>No reviews yet</p>
                </td>
            </tr>
        `;
        initLucide();
        return;
    }
    
    tbody.innerHTML = reviews.map(r => {
        const statusColors = {
            pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
            approved: 'bg-green-100 text-green-800 border-green-200',
            rejected: 'bg-red-100 text-red-800 border-red-200'
        };
        
        const statusBadge = statusColors[r.status] || statusColors.pending;
        const stars = '★'.repeat(r.stars) + '☆'.repeat(5 - r.stars);
        const date = new Date(r.created_at).toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
        
        return `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-semibold text-slate-800">${r.customer_name || 'Anonymous'}</div>
                    <div class="text-xs text-slate-500">Order: ${r.order_id}</div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-1 text-yellow-500 text-lg">
                        ${stars}
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5">${r.stars}/5</div>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-slate-700 max-w-md">${r.comment || 'No comment'}</p>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${statusBadge}">
                        ${r.status.charAt(0).toUpperCase() + r.status.slice(1)}
                    </span>
                    <div class="text-xs text-slate-400 mt-1">${date}</div>
                </td>
                <td class="px-6 py-4 text-right">
                    ${CAN_EDIT ? `
                        <div class="flex items-center justify-end gap-2">
                            ${r.status !== 'approved' ? `
                                <button onclick="window.updateReviewStatus(${r.id}, 'approved')" class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Approve">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                </button>
                            ` : ''}
                            ${r.status !== 'rejected' ? `
                                <button onclick="window.updateReviewStatus(${r.id}, 'rejected')" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Reject">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            ` : ''}
                            ${r.status !== 'pending' ? `
                                <button onclick="window.updateReviewStatus(${r.id}, 'pending')" class="p-2 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors" title="Mark Pending">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                </button>
                            ` : ''}
                        </div>
                    ` : `
                        <span class="text-xs text-slate-400">View Only</span>
                    `}
                </td>
            </tr>
        `;
    }).join('');
    
    initLucide();
}

window.updateReviewStatus = async function(id, status) {
    try {
        await fetchAPI(`update_review_status&id=${id}`, 'POST', { status });
        showToast('Success', `Review ${status}`, 'success');
        loadReviews();
    } catch (err) {
        showToast('Error', err.message, 'error');
    }
};

window.filterReviews = function(status) {
    const buttons = document.querySelectorAll('[data-filter]');
    buttons.forEach(btn => {
        if (btn.dataset.filter === status) {
            btn.classList.add('bg-slate-900', 'text-white');
            btn.classList.remove('text-slate-600', 'hover:bg-slate-50');
        } else {
            btn.classList.remove('bg-slate-900', 'text-white');
            btn.classList.add('text-slate-600', 'hover:bg-slate-50');
        }
    });
    
    const filteredReviews = status === 'all' ? 
        reviews : 
        reviews.filter(r => r.status === status);
    
    renderFilteredReviews(filteredReviews);
};

function renderFilteredReviews(filteredReviews) {
    const tbody = document.getElementById('reviews-table-body');
    if (!tbody) return;
    
    // Temporarily replace reviews array for rendering
    const originalReviews = reviews;
    reviews = filteredReviews;
    renderReviewsTable();
    reviews = originalReviews;
}
