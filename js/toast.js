/**
 * OTOMOTORS Manager Portal - Toast Notifications
 * @version 2.0.0
 */

// Toast notification system
function showToast(title, message = '', type = 'success', duration = 4000) {
    const container = document.getElementById('toast-container');
    if (!container) {
        console.warn('Toast container not found');
        return;
    }
    
    // Handle legacy calls where message might be the type
    if (typeof type === 'number') { 
        duration = type; 
        type = 'success'; 
    }
    if (!message && !type) { 
        type = 'success'; 
    } else if (['success', 'error', 'info', 'urgent'].includes(message)) { 
        type = message; 
        message = ''; 
    }
    
    // Create toast element
    const toast = document.createElement('div');
    
    const colors = {
        success: { 
            bg: 'bg-white/95 backdrop-blur-xl', 
            border: 'border-emerald-200/60', 
            iconBg: 'bg-gradient-to-br from-emerald-50 to-teal-50', 
            iconColor: 'text-emerald-600', 
            icon: 'check-circle-2',
            shadow: 'shadow-emerald-500/20' 
        },
        error: { 
            bg: 'bg-white/95 backdrop-blur-xl', 
            border: 'border-red-200/60', 
            iconBg: 'bg-gradient-to-br from-red-50 to-orange-50', 
            iconColor: 'text-red-600', 
            icon: 'alert-circle',
            shadow: 'shadow-red-500/20' 
        },
        info: { 
            bg: 'bg-white/95 backdrop-blur-xl', 
            border: 'border-primary-200/60', 
            iconBg: 'bg-gradient-to-br from-primary-50 to-accent-50', 
            iconColor: 'text-primary-600', 
            icon: 'info',
            shadow: 'shadow-primary-500/20' 
        },
        urgent: { 
            bg: 'bg-white/95 backdrop-blur-xl toast-urgent', 
            border: 'border-primary-300', 
            iconBg: 'bg-gradient-to-br from-primary-100 to-accent-100', 
            iconColor: 'text-primary-700', 
            icon: 'bell-ring',
            shadow: 'shadow-primary-500/30' 
        }
    };
    
    const style = colors[type] || colors.info;

    toast.className = `pointer-events-auto w-80 ${style.bg} border-2 ${style.border} shadow-2xl ${style.shadow} rounded-2xl p-4 flex items-start gap-3 transform transition-all duration-500 translate-y-10 opacity-0`;
    
    toast.innerHTML = `
        <div class="${style.iconBg} p-3 rounded-xl shrink-0 shadow-inner">
            <i data-lucide="${style.icon}" class="w-5 h-5 ${style.iconColor}"></i>
        </div>
        <div class="flex-1 pt-1">
            <h4 class="text-sm font-bold text-slate-900 leading-none mb-1.5">${escapeHtml(title)}</h4>
            ${message ? `<p class="text-xs text-slate-600 leading-relaxed font-medium">${message}</p>` : ''}
        </div>
        <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-slate-600 transition-colors -mt-1 -mr-1 p-1.5 hover:bg-slate-100 rounded-lg">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    `;

    container.appendChild(toast);
    
    // Initialize Lucide icons
    if (window.lucide) lucide.createIcons();

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-10', 'opacity-0');
    });

    // Auto remove after duration (but keep urgent toasts visible)
    if (duration > 0 && type !== 'urgent') {
        setTimeout(() => {
            toast.classList.add('translate-y-10', 'opacity-0');
            setTimeout(() => toast.remove(), 500);
        }, duration);
    }

    return toast;
}

// Confirmation dialog
function showConfirm(title, message, onConfirm, onCancel = null) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[9999] p-4';
    modal.id = 'confirm-modal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="bg-amber-100 p-3 rounded-xl">
                        <i data-lucide="alert-triangle" class="w-6 h-6 text-amber-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">${escapeHtml(title)}</h3>
                </div>
                <p class="text-slate-600 mb-6">${escapeHtml(message)}</p>
                <div class="flex gap-3 justify-end">
                    <button id="confirm-cancel" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl font-medium transition-colors">
                        Cancel
                    </button>
                    <button id="confirm-ok" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-medium transition-colors">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    if (window.lucide) lucide.createIcons();
    
    const cleanup = () => modal.remove();
    
    document.getElementById('confirm-cancel').onclick = () => {
        cleanup();
        if (onCancel) onCancel();
    };
    
    document.getElementById('confirm-ok').onclick = () => {
        cleanup();
        if (onConfirm) onConfirm();
    };
    
    modal.onclick = (e) => {
        if (e.target === modal) {
            cleanup();
            if (onCancel) onCancel();
        }
    };
}

// Loading indicator
let loadingCount = 0;
function showLoading(message = 'Loading...') {
    loadingCount++;
    let loader = document.getElementById('global-loader');
    
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.className = 'fixed inset-0 bg-black/30 flex items-center justify-center z-[9998]';
        loader.innerHTML = `
            <div class="bg-white rounded-2xl shadow-2xl p-6 flex items-center gap-4">
                <div class="w-8 h-8 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
                <span class="text-slate-700 font-medium" id="loader-message">${escapeHtml(message)}</span>
            </div>
        `;
        document.body.appendChild(loader);
    } else {
        document.getElementById('loader-message').textContent = message;
        loader.classList.remove('hidden');
    }
}

function hideLoading() {
    loadingCount = Math.max(0, loadingCount - 1);
    if (loadingCount === 0) {
        const loader = document.getElementById('global-loader');
        if (loader) loader.classList.add('hidden');
    }
}

// Export for global access
window.showToast = showToast;
window.showConfirm = showConfirm;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
