/**
 * OTOMOTORS Manager Portal - Utility Functions
 * @version 2.0.0
 */

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function for scroll events
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// HTML escaping to prevent XSS
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

// Normalize plate number for comparison
function normalizePlate(p) {
    return p ? p.replace(/[^a-zA-Z0-9]/g, '').toUpperCase() : '';
}

// Parse number safely
function parseNumber(v) {
    if (v === null || v === undefined || v === '') return 0;
    const n = parseFloat(String(v).replace(/[^\d.-]/g, ''));
    return isNaN(n) ? 0 : n;
}

// Format currency
function formatCurrency(n, symbol = '₾') {
    return parseNumber(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + symbol;
}

// Format date for display
function formatDate(dateStr, options = {}) {
    if (!dateStr) return '';
    try {
        let normalized = dateStr;
        if (normalized.includes(' ')) {
            normalized = normalized.replace(' ', 'T');
        }
        if (normalized.length === 16) {
            normalized += ':00';
        }
        const date = new Date(normalized);
        if (isNaN(date.getTime())) return 'Invalid date';
        
        const defaultOptions = { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric',
            hour: '2-digit', 
            minute: '2-digit'
        };
        return date.toLocaleDateString('en-US', { ...defaultOptions, ...options });
    } catch (e) {
        return 'Date error';
    }
}

// Format date for HTML input (datetime-local)
function formatDateForInput(dateStr) {
    if (!dateStr) return '';
    return dateStr.replace(' ', 'T').substring(0, 16);
}

// Get hash of data for change detection
function getDataHash(data) {
    if (!data || !Array.isArray(data)) return '';
    return data.map(t => `${t.id}-${t.status}-${t.user_response || ''}`).join('|');
}

// Deep clone object
function deepClone(obj) {
    return JSON.parse(JSON.stringify(obj));
}

// Check if element is in viewport
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Lazy load images
function lazyLoadImages(container) {
    const images = container.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    });
    images.forEach(img => imageObserver.observe(img));
}

// Storage helpers with error handling
const Storage = {
    get(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            console.warn('Storage.get error:', e);
            return defaultValue;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (e) {
            console.warn('Storage.set error:', e);
            return false;
        }
    },
    remove(key) {
        try {
            localStorage.removeItem(key);
            return true;
        } catch (e) {
            return false;
        }
    }
};

// URL parameter helpers
const URLParams = {
    get(name) {
        const params = new URLSearchParams(window.location.search);
        return params.get(name);
    },
    set(name, value) {
        const params = new URLSearchParams(window.location.search);
        params.set(name, value);
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.replaceState({}, '', newUrl);
    }
};

// Export individual functions to global scope for direct access
if (typeof window !== 'undefined') {
    // Core utilities needed by other modules (toast.js, etc.)
    window.escapeHtml = escapeHtml;
    window.debounce = debounce;
    window.throttle = throttle;
    window.normalizePlate = normalizePlate;
    window.parseNumber = parseNumber;
    window.formatCurrency = formatCurrency;
    window.formatDate = formatDate;
    window.formatDateForInput = formatDateForInput;
    window.getDataHash = getDataHash;
    window.deepClone = deepClone;
    window.Storage = Storage;
    window.URLParams = URLParams;
    
    // Also export as namespaced object for organized access
    window.OtoUtils = {
        debounce,
        throttle,
        escapeHtml,
        normalizePlate,
        parseNumber,
        formatCurrency,
        formatDate,
        formatDateForInput,
        getDataHash,
        deepClone,
        isInViewport,
        lazyLoadImages,
        Storage,
        URLParams
    };
}
