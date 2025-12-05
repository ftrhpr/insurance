/**
 * Core Application Module
 * Handles main app initialization and common utilities
 */

// Note: USER_ROLE, USER_NAME, CAN_EDIT are injected by index-modular.php
const API_URL = 'api.php';
const MANAGER_PHONE = "511144486";

// Global state
let transfers = [];
let vehicles = [];
let reviews = [];
const currentUser = { uid: "manager", name: "Manager" };
let isOnline = true;
let connectionCheckInterval = null;

// Helper functions
window.normalizePlate = (p) => p ? p.replace(/[^a-zA-Z0-9]/g, '').toUpperCase() : '';

// Connection Status Management
function updateConnectionStatus(online) {
    isOnline = online;
    const statusEl = document.getElementById('connection-status');
    if (!statusEl) return;
    
    if (online) {
        statusEl.className = 'flex items-center gap-2 text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1.5 rounded-full shadow-sm';
        statusEl.innerHTML = '<span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span><span>Server Connected</span>';
    } else {
        statusEl.className = 'flex items-center gap-2 text-xs font-medium bg-red-50 text-red-700 border border-red-100 px-3 py-1.5 rounded-full shadow-sm';
        statusEl.innerHTML = '<span class="w-2 h-2 bg-red-500 rounded-full"></span><span>Connection Lost</span>';
        showToast('Connection Lost', 'Attempting to reconnect...', 'error');
    }
}

// Monitor connection status
function startConnectionMonitoring() {
    // Check online status
    window.addEventListener('online', () => {
        updateConnectionStatus(true);
        showToast('Back Online', 'Connection restored', 'success');
        loadData(); // Reload data
    });
    
    window.addEventListener('offline', () => {
        updateConnectionStatus(false);
    });
    
    // Periodic health check
    if (connectionCheckInterval) clearInterval(connectionCheckInterval);
    connectionCheckInterval = setInterval(async () => {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 3000);
            
            await fetch('api.php?action=health_check', {
                method: 'GET',
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            if (!isOnline) {
                updateConnectionStatus(true);
                showToast('Connection Restored', 'Server is reachable', 'success');
            }
        } catch (err) {
            if (isOnline) {
                updateConnectionStatus(false);
            }
        }
    }, 30000); // Check every 30 seconds
}

window.updateConnectionStatus = updateConnectionStatus;

// API Helper with retry logic
async function fetchAPI(action, method = 'GET', body = null, retries = 2) {
    const opts = { 
        method,
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin'
    };
    if (body) opts.body = JSON.stringify(body);
    
    for (let attempt = 0; attempt <= retries; attempt++) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
            
            const res = await fetch(`${API_URL}?action=${action}`, {
                ...opts,
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (!res.ok) {
                let errorData = {};
                const resClone = res.clone(); // Clone only when needed
                try {
                    errorData = await resClone.json();
                } catch (e) {
                    // If JSON parsing fails, read text from original response
                    const errorText = await res.text();
                    errorData = { error: errorText || `HTTP ${res.status}` };
                }
                
                // Retry on server errors (503)
                if (res.status === 503 && errorData.retry && attempt < retries) {
                    await new Promise(resolve => setTimeout(resolve, 1000 * (attempt + 1)));
                    continue;
                }
                
                throw new Error(errorData.message || errorData.error || `HTTP ${res.status}`);
            }
            
            updateConnectionStatus(true);
            
            // Parse JSON response
            try {
                return await res.json();
            } catch (jsonError) {
                console.error('JSON parse error:', jsonError);
                throw new Error('Invalid response format from server');
            }
            
        } catch (err) {
            console.error(`API Error [${action}] (attempt ${attempt + 1}):`, err);
            
            // Handle specific error types
            if (err.name === 'AbortError') {
                updateConnectionStatus(false);
                if (attempt < retries) {
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    continue;
                }
                throw new Error('Request timeout. Please check your connection.');
            }
            
            if (err.message.includes('Unauthorized') || err.message.includes('401')) {
                window.location.href = 'login.php';
                return;
            }
            
            if (err.message.includes('Failed to fetch') || err.message.includes('NetworkError')) {
                updateConnectionStatus(false);
                if (attempt < retries) {
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    continue;
                }
                throw new Error('Network error. Please check your internet connection.');
            }
            
            if (attempt === retries) {
                throw err;
            }
        }
    }
}

// View Switching
window.switchView = (v) => {
    const dashboard = document.getElementById('view-dashboard');
    const vehicles = document.getElementById('view-vehicles');
    const reviewsView = document.getElementById('view-reviews');
    const templates = document.getElementById('view-templates');
    
    if (dashboard) dashboard.classList.toggle('hidden', v !== 'dashboard');
    if (vehicles) vehicles.classList.toggle('hidden', v !== 'vehicles');
    if (reviewsView) reviewsView.classList.toggle('hidden', v !== 'reviews');
    if (templates) templates.classList.toggle('hidden', v !== 'templates');
    
    const usersView = document.getElementById('view-users');
    if (usersView) {
        usersView.classList.toggle('hidden', v !== 'users');
    }
    
    const activeClass = "nav-active px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 bg-slate-900 text-white shadow-sm";
    const inactiveClass = "nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 text-slate-500 hover:text-slate-900 hover:bg-white";

    const navDashboard = document.getElementById('nav-dashboard');
    const navVehicles = document.getElementById('nav-vehicles');
    const navReviews = document.getElementById('nav-reviews');
    const navTemplates = document.getElementById('nav-templates');
    
    if (navDashboard) navDashboard.className = v === 'dashboard' ? activeClass : inactiveClass;
    if (navVehicles) navVehicles.className = v === 'vehicles' ? activeClass : inactiveClass;
    if (navReviews) navReviews.className = v === 'reviews' ? activeClass : inactiveClass;
    if (navTemplates) navTemplates.className = v === 'templates' ? activeClass : inactiveClass;
    
    const navUsers = document.getElementById('nav-users');
    if (navUsers) {
        navUsers.className = v === 'users' ? activeClass : inactiveClass;
    }

    if (v === 'reviews') {
        loadReviews();
    }
    if (v === 'users') {
        loadUsers();
    }
};

// Toast Notifications
function showToast(title, message = '', type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) {
        console.error('Toast container not found');
        return;
    }
    const id = Date.now();
    
    const colors = {
        success: 'bg-emerald-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
        urgent: 'bg-orange-500'
    };
    
    const icons = {
        success: 'check-circle',
        error: 'alert-circle',
        info: 'info',
        urgent: 'alert-triangle'
    };
    
    const toast = document.createElement('div');
    toast.id = `toast-${id}`;
    toast.className = `${colors[type]} text-white px-5 py-3 rounded-xl shadow-2xl flex items-center gap-3 pointer-events-auto min-w-[300px] animate-in slide-in-from-right-10 fade-in duration-300`;
    toast.innerHTML = `
        <i data-lucide="${icons[type]}" class="w-5 h-5 shrink-0"></i>
        <div class="flex-1">
            <div class="font-bold text-sm">${title}</div>
            ${message ? `<div class="text-xs opacity-90 mt-0.5">${message}</div>` : ''}
        </div>
        <button onclick="document.getElementById('toast-${id}').remove()" class="hover:bg-white/20 p-1 rounded transition-colors">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    `;
    
    container.appendChild(toast);
    initLucide();
    
    setTimeout(() => {
        toast.classList.add('animate-out', 'slide-out-to-right-10', 'fade-out');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Load initial data
window.loadData = async function() {
    try {
        const data = await fetchAPI('get_transfers', 'GET');
        transfers = data.transfers || [];
        renderTable();
        loadVehicles();
    } catch (err) {
        console.error('Failed to load data:', err);
        showToast('Error', 'Failed to load transfers', 'error');
    }
};

// User Menu Toggle (used in header)
window.toggleUserMenu = function() {
    const dropdown = document.getElementById('user-dropdown');
    dropdown.classList.toggle('hidden');
};

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const container = document.getElementById('user-menu-container');
    const dropdown = document.getElementById('user-dropdown');
    if (container && !container.contains(e.target) && dropdown) {
        dropdown.classList.add('hidden');
    }
});

// Safe lucide initialization helper
window.initLucide = function() {
    if (window.lucide && typeof lucide.createIcons === 'function') {
        try {
            lucide.createIcons();
        } catch (e) {
            console.error('Lucide icon initialization error:', e);
        }
    }
};

// Initialize
window.addEventListener('DOMContentLoaded', function() {
    startConnectionMonitoring();
    loadData();
    initLucide();
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (connectionCheckInterval) {
        clearInterval(connectionCheckInterval);
    }
});
