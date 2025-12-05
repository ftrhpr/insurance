// app.js - OTOMOTORS Manager Portal - Main Application Logic
// This file contains all JavaScript functionality for the manager portal

// =====================================================
// CONFIGURATION & CONSTANTS
// =====================================================

const API_URL = 'api.php';
const MANAGER_PHONE = "511144486";

// These will be set from PHP template
let USER_ROLE = '';
let CAN_EDIT = false;

// Firebase Configuration
const firebaseConfig = {
    apiKey: "AIzaSyBRvdcvgMsOiVzeUQdSMYZFQ1GKkHZUWYI",
    authDomain: "otm-portal-312a5.firebaseapp.com",
    projectId: "otm-portal-312a5",
    storageBucket: "otm-portal-312a5.firebasestorage.app",
    messagingSenderId: "917547807534",
    appId: "1:917547807534:web:9021c744b7b0f62b4e80bf"
};

// Demo mode toggle
const USE_MOCK_DATA = false;

// =====================================================
// STATE MANAGEMENT
// =====================================================

let transfers = [];
let vehicles = [];
let customerReviews = [];
let allUsers = [];
let parsedImportData = [];
let currentReviewFilter = 'all';

window.currentEditingId = null;

const currentUser = { uid: "manager", name: "Manager" };

// =====================================================
// SMS TEMPLATES
// =====================================================

const defaultTemplates = {
    'registered': "Hello {name}, payment received. Ref: {plate}. Welcome to OTOMOTORS service.",
    'called': "Hello {name}, we contacted you regarding {plate}. Service details will follow shortly.",
    'schedule': "Hello {name}, service scheduled for {date}. Ref: {plate}.",
    'parts_ordered': "Parts ordered for {plate}. We will notify you when ready.",
    'parts_arrived': "Hello {name}, your parts have arrived! Please confirm your visit here: {link}",
    'rescheduled': "Hello {name}, your service has been rescheduled to {date}. Please confirm: {link}",
    'reschedule_accepted': "Hello {name}, your reschedule request has been approved! New appointment: {date}. Ref: {plate}. - OTOMOTORS",
    'completed': "Service for {plate} is completed. Thank you for choosing OTOMOTORS! Rate your experience: {link}",
    'issue': "Hello {name}, we detected an issue with {plate}. Our team will contact you shortly."
};

let smsTemplates = defaultTemplates;

// =====================================================
// UTILITY FUNCTIONS
// =====================================================

const normalizePlate = (p) => p ? p.replace(/[^a-zA-Z0-9]/g, '').toUpperCase() : '';

function getFormattedMessage(type, data) {
    let template = smsTemplates[type] || defaultTemplates[type] || "";
    const baseUrl = window.location.href.replace(/index\.php.*/, '').replace(/\/$/, '');
    const link = `${baseUrl}/public_view.php?id=${data.id}`;

    return template
        .replace(/{name}/g, data.name || '')
        .replace(/{plate}/g, data.plate || '')
        .replace(/{amount}/g, data.amount || '')
        .replace(/{link}/g, link)
        .replace(/{date}/g, data.serviceDate ? data.serviceDate.replace('T', ' ') : '');
}

// =====================================================
// FIREBASE INITIALIZATION
// =====================================================

try {
    firebase.initializeApp(firebaseConfig);
    const messaging = firebase.messaging();
    
    messaging.onMessage((payload) => {
        console.log('Message received. ', payload);
        const { title, body } = payload.notification;
        showToast(`${title}: ${body}`, 'success');
    });
} catch (e) {
    console.log("Firebase init failed (check config):", e);
}

window.requestNotificationPermission = async () => {
    try {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            const token = await firebase.messaging().getToken({ vapidKey: 'BPmaDT11APIDJCEoLFGA7ZoUCmc2IM9wxsNPJsy4984GaZNhBEEJa1VG6C65t1oCMTtUPVSudeivYsAmINDGc-w' });
            if (token) {
                await fetchAPI('register_token', 'POST', { token });
                showToast("Notifications Enabled");
            }
        } else {
            showToast("Permission denied", "error");
        }
    } catch (error) {
        console.error('Unable to get permission', error);
    }
};

// =====================================================
// API COMMUNICATION
// =====================================================

async function fetchAPI(action, method = 'GET', body = null) {
    const opts = { method };
    if (body) opts.body = JSON.stringify(body);
    
    if (USE_MOCK_DATA) {
        return getMockData(action, body);
    }

    try {
        const res = await fetch(`${API_URL}?action=${action}`, opts);
        
        if (!res.ok) {
            let errorText = res.statusText;
            try {
                const errorJson = await res.json();
                if (errorJson.error) errorText = errorJson.error;
            } catch (parseErr) {
                const text = await res.text();
                if(text) errorText = text.substring(0, 100);
            }
            throw new Error(`Server Error (${res.status}): ${errorText}`);
        }

        const data = await res.json();
        
        const statusEl = document.getElementById('connection-status');
        if(statusEl) statusEl.innerHTML = `<span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span> SQL Connected`;
        
        return data;
    } catch (e) {
        console.warn("Server unavailable:", e);
        const statusEl = document.getElementById('connection-status');
        if(statusEl) statusEl.innerHTML = `<span class="w-2 h-2 bg-red-500 rounded-full"></span> Connection Failed`;
        
        showToast("Connection Error", e.message, "error");
        throw e;
    }
}

function getMockData(action, body) {
    const statusEl = document.getElementById('connection-status');
    if(statusEl) statusEl.innerHTML = `<span class="w-2 h-2 bg-yellow-500 rounded-full"></span> Demo Mode`;

    return new Promise(resolve => {
        setTimeout(() => {
            if (action === 'get_transfers') resolve(transfers.length ? transfers : []);
            else if (action === 'get_vehicles') resolve(vehicles.length ? vehicles : []);
            else if (action === 'add_transfer') {
                const newId = Math.floor(Math.random()*10000);
                resolve({ id: newId, status: 'success' });
            }
            else if (action === 'save_vehicle') resolve({ status: 'success' });
            else resolve({ status: 'mock_success' });
        }, 100);
    });
}

// =====================================================
// DATA LOADING
// =====================================================

async function loadData() {
    try {
        const newTransfers = await fetchAPI('get_transfers');
        const newVehicles = await fetchAPI('get_vehicles');
        
        if(Array.isArray(newTransfers)) transfers = newTransfers;
        if(Array.isArray(newVehicles)) vehicles = newVehicles;

        renderTable();
        renderVehicleTable();
    } catch(e) {
        // Squelch load errors
    }

    document.getElementById('loading-screen').classList.add('opacity-0', 'pointer-events-none');
    setTimeout(() => {
        document.getElementById('loading-screen').classList.add('hidden');
        document.getElementById('app-content').classList.remove('hidden');
    }, 500);
}

setInterval(loadData, 10000);

// =====================================================
// UI COMPONENTS
// =====================================================

function showToast(title, message = '', type = 'success', duration = 4000) {
    const container = document.getElementById('toast-container');
    
    if (typeof type === 'number') { duration = type; type = 'success'; }
    if (!message && !type) { type = 'success'; }
    else if (['success', 'error', 'info', 'urgent'].includes(message)) { type = message; message = ''; }
    
    const toast = document.createElement('div');
    
    const colors = {
        success: { bg: 'bg-white', border: 'border-emerald-100', iconBg: 'bg-emerald-50', iconColor: 'text-emerald-600', icon: 'check-circle-2' },
        error: { bg: 'bg-white', border: 'border-red-100', iconBg: 'bg-red-50', iconColor: 'text-red-600', icon: 'alert-circle' },
        info: { bg: 'bg-white', border: 'border-blue-100', iconBg: 'bg-blue-50', iconColor: 'text-blue-600', icon: 'info' },
        urgent: { bg: 'bg-white', border: 'border-indigo-200 toast-urgent', iconBg: 'bg-indigo-50', iconColor: 'text-indigo-600', icon: 'bell-ring' }
    };
    
    const style = colors[type] || colors.info;

    toast.className = `pointer-events-auto w-80 ${style.bg} border ${style.border} shadow-xl shadow-slate-200/60 rounded-xl p-4 flex items-start gap-3 transform transition-all duration-500 translate-y-10 opacity-0`;
    
    toast.innerHTML = `
        <div class="${style.iconBg} p-2.5 rounded-full shrink-0">
            <i data-lucide="${style.icon}" class="w-5 h-5 ${style.iconColor}"></i>
        </div>
        <div class="flex-1 pt-1">
            <h4 class="text-sm font-bold text-slate-800 leading-none mb-1">${title}</h4>
            ${message ? `<p class="text-xs text-slate-500 leading-relaxed">${message}</p>` : ''}
        </div>
        <button onclick="this.parentElement.remove()" class="text-slate-300 hover:text-slate-500 transition-colors -mt-1 -mr-1 p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    `;

    container.appendChild(toast);
    if(window.lucide) lucide.createIcons();

    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-10', 'opacity-0');
    });

    if (duration > 0 && type !== 'urgent') {
        setTimeout(() => {
            toast.classList.add('translate-y-4', 'opacity-0');
            setTimeout(() => toast.remove(), 500);
        }, duration);
    }
}

window.switchView = (v) => {
    document.getElementById('view-dashboard').classList.toggle('hidden', v !== 'dashboard');
    document.getElementById('view-vehicles').classList.toggle('hidden', v !== 'vehicles');
    document.getElementById('view-reviews').classList.toggle('hidden', v !== 'reviews');
    document.getElementById('view-templates').classList.toggle('hidden', v !== 'templates');
    
    const usersView = document.getElementById('view-users');
    if (usersView) {
        usersView.classList.toggle('hidden', v !== 'users');
    }
    
    const activeClass = "nav-active px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 bg-slate-900 text-white shadow-sm";
    const inactiveClass = "nav-inactive px-4 py-1.5 rounded-md text-sm transition-all flex items-center gap-2 text-slate-500 hover:text-slate-900 hover:bg-white";

    document.getElementById('nav-dashboard').className = v === 'dashboard' ? activeClass : inactiveClass;
    document.getElementById('nav-vehicles').className = v === 'vehicles' ? activeClass : inactiveClass;
    document.getElementById('nav-reviews').className = v === 'reviews' ? activeClass : inactiveClass;
    document.getElementById('nav-templates').className = v === 'templates' ? activeClass : inactiveClass;
    
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

// =====================================================
// TEMPLATE MANAGEMENT
// =====================================================

async function saveAllTemplates() {
    try {
        const getVal = (id) => {
            const el = document.getElementById(id);
            return el ? el.value : '';
        };

        smsTemplates.registered = getVal('tpl-registered');
        smsTemplates.called = getVal('tpl-called');
        smsTemplates.schedule = getVal('tpl-schedule');
        smsTemplates.parts_ordered = getVal('tpl-parts_ordered');
        smsTemplates.parts_arrived = getVal('tpl-parts_arrived');
        smsTemplates.rescheduled = getVal('tpl-rescheduled');
        smsTemplates.reschedule_accepted = getVal('tpl-reschedule_accepted');
        smsTemplates.completed = getVal('tpl-completed');
        smsTemplates.issue = getVal('tpl-issue');
        
        await fetchAPI('save_templates', 'POST', smsTemplates);
        showToast("Templates Saved to Database", "success");
    } catch (e) {
        console.error("Save error:", e);
        showToast("Error saving templates", "error");
    }
}

window.saveAllTemplates = saveAllTemplates;

async function loadTemplatesToUI() {
    try {
        const serverTemplates = await fetchAPI('get_templates');
        smsTemplates = { ...defaultTemplates, ...serverTemplates };
        
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if(el) el.value = val || '';
        };

        setVal('tpl-registered', smsTemplates.registered);
        setVal('tpl-called', smsTemplates.called);
        setVal('tpl-schedule', smsTemplates.schedule);
        setVal('tpl-parts_ordered', smsTemplates.parts_ordered);
        setVal('tpl-parts_arrived', smsTemplates.parts_arrived);
        setVal('tpl-rescheduled', smsTemplates.rescheduled);
        setVal('tpl-reschedule_accepted', smsTemplates.reschedule_accepted);
        setVal('tpl-completed', smsTemplates.completed);
        setVal('tpl-issue', smsTemplates.issue);
    } catch (e) {
        console.error("UI Load Error", e);
    }
}

// =====================================================
// DOM READY
// =====================================================

document.addEventListener('DOMContentLoaded', () => {
    if ('Notification' in window && Notification.permission === 'default') {
        const prompt = document.getElementById('notification-prompt');
        if(prompt) setTimeout(() => prompt.classList.remove('hidden'), 2000);
    }
    loadTemplatesToUI();
});

// =====================================================
// INITIALIZATION
// =====================================================

// Set user role from PHP (will be injected)
function initializeUserRole(role) {
    USER_ROLE = role;
    CAN_EDIT = USER_ROLE === 'admin' || USER_ROLE === 'manager';
}

// Export for PHP template
window.initializeUserRole = initializeUserRole;

loadData();
if(window.lucide) lucide.createIcons();
