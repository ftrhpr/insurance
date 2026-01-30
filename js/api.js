/**
 * OTOMOTORS Manager Portal - API Core Functions
 * @version 2.0.1
 * 
 * NOTE: This file provides ONLY the fetchAPI function and connection monitoring.
 * Data management (transfers, vehicles, loadData, getMockData) remains in index.php
 * because it requires PHP context (statuses, templates, etc.)
 */

// Configuration - Set by PHP in index.php
window.OtoConfig = window.OtoConfig || {
    CSRF_TOKEN: '',
    API_URL: 'api.php',
    USE_MOCK_DATA: false
};

// Connection state (use underscore to avoid conflicts)
let _apiIsOnline = navigator.onLine;

// Connection status monitoring
window.addEventListener('online', () => {
    _apiIsOnline = true;
    updateConnectionStatus(true);
});

window.addEventListener('offline', () => {
    _apiIsOnline = false;
    updateConnectionStatus(false);
});

function updateConnectionStatus(online) {
    const statusEl = document.getElementById('connection-status');
    if (statusEl) {
        if (online) {
            statusEl.innerHTML = '<span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span> SQL Connected';
        } else {
            statusEl.innerHTML = '<span class="w-2 h-2 bg-red-500 rounded-full"></span> Connection Failed';
        }
    }
}

/**
 * Main API function - makes HTTP requests to api.php
 * @param {string} action - The API action to call
 * @param {string} method - HTTP method (GET or POST)
 * @param {object} body - Request body for POST requests
 * @returns {Promise<any>} - API response data
 */
async function fetchAPI(action, method = 'GET', body = null) {
    const { CSRF_TOKEN, API_URL } = window.OtoConfig;
    
    const url = `${API_URL}?action=${action}`;
    const options = {
        method,
        headers: { 'Content-Type': 'application/json' },
    };
    
    // Add CSRF token for POST requests
    if (method === 'POST' && CSRF_TOKEN) {
        options.headers['X-CSRF-Token'] = CSRF_TOKEN;
    }
    
    if (body && method !== 'GET') {
        options.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(url, options);
        
        // Update connection status
        updateConnectionStatus(true);
        
        if (!response.ok) {
            const errorText = await response.text();
            let errorMessage;
            try {
                const errorJson = JSON.parse(errorText);
                errorMessage = errorJson.message || errorJson.error || errorText;
            } catch {
                errorMessage = errorText;
            }
            throw new Error(`Server Error (${response.status}): ${errorMessage}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
            updateConnectionStatus(false);
        }
        console.error('API Error:', action, error);
        
        // Show error toast if available
        if (typeof showToast === 'function') {
            showToast("Connection Error", error.message, "error");
        }
        
        throw error;
    }
}

/**
 * Check if browser is online
 * @returns {boolean}
 */
function apiIsOnline() {
    return _apiIsOnline;
}

// Export for global access (fetchAPI is the only function needed from this file)
// loadData, getMockData, etc. are defined in index.php with PHP context
window.fetchAPI = fetchAPI;
window.updateConnectionStatus = updateConnectionStatus;
window.apiIsOnline = apiIsOnline;
