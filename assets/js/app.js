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

// =====================================================
// BANK STATEMENT PARSING
// =====================================================

window.parseBankText = () => {
    const text = document.getElementById('import-text').value;
    if(!text) return;
    const lines = text.split(/\r?\n/);
    parsedImportData = [];
    
    // Patterns
    const regexes = [
        /Transfer from ([\w\s]+), Plate: ([\w\d]+), Amt: (\d+)/i,
        /INSURANCE PAY \| ([\w\d]+) \| ([\w\s]+) \| (\d+)/i,
        /User: ([\w\s]+) Car: ([\w\d]+) Sum: ([\w\d\.]+)/i,
        /მანქანის ნომერი:\s*([A-Za-z0-9]+)\s*დამზღვევი:\s*([^,]+),\s*([\d\.]+)/i
    ];
    
    const franchiseRegex = /\(ფრანშიზა\s*([\d\.]+)\)/i;

    lines.forEach(line => {
        for(let r of regexes) {
            const m = line.match(r);
            if(m) {
                let plate, name, amount;
                if(r.source.includes('Transfer from')) { name=m[1]; plate=m[2]; amount=m[3]; }
                else if(r.source.includes('INSURANCE')) { plate=m[1]; name=m[2]; amount=m[3]; }
                else if(r.source.includes('User:')) { name=m[1]; plate=m[2]; amount=m[3]; }
                else { plate=m[1]; name=m[2]; amount=m[3]; } 
                
                let franchise = '';
                const fMatch = line.match(franchiseRegex);
                if(fMatch) franchise = fMatch[1];

                parsedImportData.push({ 
                    plate: plate.trim(), 
                    name: name.trim(), 
                    amount: amount.trim(), 
                    franchise: franchise,
                    rawText: line 
                });
                break;
            }
        }
    });

    if(parsedImportData.length > 0) {
        document.getElementById('parsed-result').classList.remove('hidden');
        document.getElementById('parsed-placeholder').classList.add('hidden');
        document.getElementById('parsed-content').innerHTML = parsedImportData.map(i => 
            `<div class="bg-white p-3 border border-emerald-100 rounded-lg mb-2 text-xs flex justify-between items-center shadow-sm">
                <div class="flex items-center gap-2">
                    <div class="font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded">${i.plate}</div> 
                    <span class="text-slate-500">${i.name}</span>
                    ${i.franchise ? `<span class="text-orange-500 bg-orange-50 px-1.5 py-0.5 rounded ml-1">Franchise: ${i.franchise}</span>` : ''}
                </div>
                <div class="font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">${i.amount} ₾</div>
            </div>`
        ).join('');
        document.getElementById('btn-save-import').innerHTML = `<i data-lucide="save" class="w-4 h-4"></i> Save ${parsedImportData.length} Items`;
        lucide.createIcons();
    } else {
        showToast("No matches found", "error");
    }
};

window.saveParsedImport = async () => {
    const btn = document.getElementById('btn-save-import');
    btn.disabled = true; btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving...`;
    
    for(let data of parsedImportData) {
        const res = await fetchAPI('add_transfer', 'POST', data);
        if (res && res.id && data.franchise) {
            await fetchAPI(`update_transfer&id=${res.id}`, 'POST', { franchise: data.franchise });
        }
        await fetchAPI('sync_vehicle', 'POST', { plate: data.plate, ownerName: data.name });
    }
    
    if(MANAGER_PHONE) {
        const msg = `System Alert: ${parsedImportData.length} new transfer(s) added to OTOMOTORS portal.`;
        window.sendSMS(MANAGER_PHONE, msg, 'system');
    }
    
    await fetchAPI('send_broadcast', 'POST', { 
        title: 'New Transfers Imported', 
        body: `${parsedImportData.length} new cases added.` 
    });

    document.getElementById('import-text').value = '';
    document.getElementById('parsed-result').classList.add('hidden');
    document.getElementById('parsed-placeholder').classList.remove('hidden');
    loadData();
    showToast("Import Successful", "success");
    btn.disabled = false;
    btn.innerHTML = `<i data-lucide="save" class="w-4 h-4"></i> Confirm & Save`;
    lucide.createIcons();
};

window.insertSample = (t) => document.getElementById('import-text').value = t;

// =====================================================
// TRANSFERS TABLE RENDERING
// =====================================================

function renderTable() {
    const search = document.getElementById('search-input').value.toLowerCase();
    const filter = document.getElementById('status-filter').value;
    const replyFilter = document.getElementById('reply-filter').value;
    
    const newContainer = document.getElementById('new-cases-grid');
    const activeContainer = document.getElementById('table-body');
    newContainer.innerHTML = ''; activeContainer.innerHTML = '';
    
    let newCount = 0;
    let activeCount = 0;

    transfers.forEach(t => {
        // 1. Text Search Filter
        const match = (t.plate+t.name+(t.phone||'')).toLowerCase().includes(search);
        if(!match) return;

        // 2. Status Filter
        if(filter !== 'All' && t.status !== filter) return;

        // 3. Reply Filter (Logic: 'Not Responded' matches 'Pending' or null)
        if (replyFilter !== 'All') {
            if (replyFilter === 'Pending') {
                // Match "Not Responded" (Pending or empty)
                if (t.user_response && t.user_response !== 'Pending') return;
            } else {
                // Match specific reply (Confirmed / Reschedule)
                if (t.user_response !== replyFilter) return;
            }
        }

        const dateObj = new Date(t.created_at || Date.now());
        const dateStr = dateObj.toLocaleDateString('en-GB', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

        // Find linked vehicle info for display (Normalized Matching)
        const linkedVehicle = vehicles.find(v => normalizePlate(v.plate) === normalizePlate(t.plate));
        const displayPhone = t.phone || (linkedVehicle ? linkedVehicle.phone : null);

        if(t.status === 'New') {
            newCount++;
            newContainer.innerHTML += `
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
                    <div class="absolute top-0 left-0 w-1.5 h-full bg-primary-500"></div>
                    <div class="flex justify-between mb-3 pl-3">
                        <span class="bg-primary-50 text-primary-700 border border-primary-100 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> ${dateStr}</span>
                        <span class="text-xs font-mono font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded">${t.amount} ₾</span>
                    </div>
                    <div class="pl-3 mb-5">
                        <h3 class="font-bold text-lg text-slate-800">${t.plate}</h3>
                        <p class="text-xs text-slate-500 font-medium">${t.name}</p>
                        ${displayPhone ? `<div class="flex items-center gap-1.5 mt-2 text-xs text-slate-600 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100 w-fit"><i data-lucide="phone" class="w-3 h-3 text-slate-400"></i> ${displayPhone}</div>` : ''}
                        ${t.franchise ? `<p class="text-[10px] text-orange-500 mt-1">Franchise: ${t.franchise}</p>` : ''}
                    </div>
                    <div class="pl-3 text-right">
                        <button onclick="window.openEditModal(${t.id})" class="bg-white border border-slate-200 text-slate-700 text-xs font-semibold px-4 py-2 rounded-lg hover:border-primary-500 hover:text-primary-600 transition-all shadow-sm flex items-center gap-2 ml-auto group-hover:bg-primary-50">
                            Process Case <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </button>
                    </div>
                </div>`;
        } else {
            activeCount++;
            
            const statusColors = {
                'Processing': 'bg-yellow-100 text-yellow-800 border-yellow-200',
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
            
            // USER RESPONSE LOGIC
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
                    quickAcceptBtn = `<button onclick="event.stopPropagation(); window.quickAcceptReschedule(${t.id})" class="mt-1 bg-green-600 hover:bg-green-700 text-white text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 transition-all active:scale-95 shadow-sm">
                        <i data-lucide="check" class="w-3 h-3"></i> Accept
                    </button>`;
                }
                replyBadge = `<div class="flex flex-col items-start gap-1">
                    <span class="bg-orange-100 text-orange-700 border border-orange-200 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 w-fit animate-pulse">
                        <i data-lucide="clock" class="w-3 h-3"></i> Reschedule Request
                    </span>
                    ${rescheduleInfo}
                    ${quickAcceptBtn}
                </div>`;
            }

            activeContainer.innerHTML += `
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
        }
    });

    document.getElementById('new-count').innerText = `${newCount}`;
    document.getElementById('record-count').innerText = `${activeCount} active`;
    document.getElementById('new-cases-empty').classList.toggle('hidden', newCount > 0);
    document.getElementById('empty-state').classList.toggle('hidden', activeCount > 0);
    lucide.createIcons();
}

// Event listeners for filtering
document.getElementById('search-input').addEventListener('input', renderTable);
document.getElementById('status-filter').addEventListener('change', renderTable);
document.getElementById('reply-filter').addEventListener('change', renderTable);

// =====================================================
// TRANSFER MODAL MANAGEMENT
// =====================================================

window.openEditModal = (id) => {
    const t = transfers.find(i => i.id == id);
    if(!t) return;
    window.currentEditingId = id;
    
    // Auto-fill phone from registry if missing in transfer
    const linkedVehicle = vehicles.find(v => normalizePlate(v.plate) === normalizePlate(t.plate));
    const phoneToFill = t.phone || (linkedVehicle ? linkedVehicle.phone : '');

    document.getElementById('modal-title-ref').innerText = t.plate;
    document.getElementById('modal-title-name').innerText = t.name;
    document.getElementById('input-phone').value = phoneToFill;
    document.getElementById('input-service-date').value = t.serviceDate ? t.serviceDate.replace(' ', 'T') : ''; 
    document.getElementById('input-franchise').value = t.franchise || '';
    document.getElementById('input-status').value = t.status;
    
    document.getElementById('btn-call-real').href = t.phone ? `tel:${t.phone}` : '#';
    document.getElementById('btn-sms-register').onclick = () => {
        const templateData = { 
            id: t.id,
            name: t.name, 
            plate: t.plate, 
            amount: t.amount, 
            serviceDate: document.getElementById('input-service-date').value 
        };
        const msg = getFormattedMessage('registered', templateData);
        window.sendSMS(document.getElementById('input-phone').value, msg, 'registered');
    };

    document.getElementById('btn-sms-arrived').onclick = () => {
        const date = document.getElementById('input-service-date').value;
        if (!date) return showToast("Time Required", "Please set an Appointment date for Parts Arrived SMS", "error");
        
        const templateData = { 
            id: t.id,
            name: t.name, 
            plate: t.plate, 
            amount: t.amount, 
            serviceDate: date 
        };
        const msg = getFormattedMessage('parts_arrived', templateData);
        window.sendSMS(document.getElementById('input-phone').value, msg, 'parts_arrived');
    };

    document.getElementById('btn-sms-schedule').onclick = () => {
        const date = document.getElementById('input-service-date').value;
        if (!date) return showToast("Please set an Appointment date first", "error");
        const templateData = { 
            id: t.id,
            name: t.name, 
            plate: t.plate, 
            amount: t.amount, 
            serviceDate: date 
        };
        const msg = getFormattedMessage('schedule', templateData);
        window.sendSMS(document.getElementById('input-phone').value, msg, 'schedule');
    };

    const logHTML = (t.systemLogs || []).map(l => `
        <div class="mb-2 last:mb-0 pl-3 border-l-2 border-slate-200 text-slate-600">
            <div class="text-[10px] text-slate-400 uppercase tracking-wider mb-0.5">${l.timestamp.split('T')[0]}</div>
            ${l.message}
        </div>`).join('');
    document.getElementById('activity-log-container').innerHTML = logHTML || '<div class="text-center py-4"><span class="italic text-slate-300 text-xs">No system activity recorded</span></div>';
    
    const noteHTML = (t.internalNotes || []).map(n => `
        <div class="bg-white p-3 rounded-lg border border-yellow-100 shadow-sm mb-3">
            <p class="text-sm text-slate-700">${n.text}</p>
            <div class="flex justify-end mt-2"><span class="text-[10px] text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">${n.authorName}</span></div>
        </div>`).join('');
    document.getElementById('notes-list').innerHTML = noteHTML || '<div class="h-full flex items-center justify-center text-slate-400 text-xs italic">No team notes yet</div>';

    // Display customer review if exists
    const reviewSection = document.getElementById('modal-review-section');
    if (t.reviewStars && t.reviewStars > 0) {
        reviewSection.classList.remove('hidden');
        document.getElementById('modal-review-rating').innerText = t.reviewStars;
        
        const starsHTML = Array(5).fill(0).map((_, i) => 
            `<i data-lucide="star" class="w-5 h-5 ${i < t.reviewStars ? 'text-yellow-400 fill-yellow-400' : 'text-gray-300'}"></i>`
        ).join('');
        document.getElementById('modal-review-stars').innerHTML = starsHTML;
        
        const comment = t.reviewComment || 'No comment provided';
        document.getElementById('modal-review-comment').innerText = comment;
    } else {
        reviewSection.classList.add('hidden');
    }

    // Display reschedule request if exists
    const rescheduleSection = document.getElementById('modal-reschedule-section');
    if (t.userResponse === 'Reschedule Requested' && (t.rescheduleDate || t.rescheduleComment)) {
        rescheduleSection.classList.remove('hidden');
        
        if (t.rescheduleDate) {
            const requestedDate = new Date(t.rescheduleDate.replace(' ', 'T'));
            document.getElementById('modal-reschedule-date').innerText = requestedDate.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        } else {
            document.getElementById('modal-reschedule-date').innerText = 'Not specified';
        }
        
        const rescheduleComment = t.rescheduleComment || 'No additional comments';
        document.getElementById('modal-reschedule-comment').innerText = rescheduleComment;
    } else {
        rescheduleSection.classList.add('hidden');
    }

    document.getElementById('edit-modal').classList.remove('hidden');
    lucide.createIcons();
};

window.closeModal = () => { 
    document.getElementById('edit-modal').classList.add('hidden'); 
    window.currentEditingId = null; 
};

window.viewCase = function(id) {
    window.openEditModal(id);
    // Disable all form inputs for viewers
    if (!CAN_EDIT) {
        const modal = document.getElementById('edit-modal');
        modal.querySelectorAll('input, select, textarea, button[onclick*="save"]').forEach(el => {
            el.disabled = true;
        });
        // Change save button to close
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
    
    // VALIDATION: Parts Arrived requires a date
    if (status === 'Parts Arrived' && !serviceDate) {
        return showToast("Scheduling Required", "Please select a service date to save 'Parts Arrived' status.", "error");
    }

    const updates = {
        status,
        phone,
        serviceDate: serviceDate || null,
        franchise: document.getElementById('input-franchise').value,
        internalNotes: t.internalNotes || [],
        systemLogs: t.systemLogs || []
    };

    // AUTO-RESCHEDULE LOGIC
    const currentDateStr = t.serviceDate ? t.serviceDate.replace(' ', 'T').slice(0, 16) : '';
    if (t.user_response === 'Reschedule Requested' && serviceDate && serviceDate !== currentDateStr) {
        updates.user_response = 'Pending';
        updates.systemLogs.push({ message: `Rescheduled to ${serviceDate.replace('T', ' ')}`, timestamp: new Date().toISOString(), type: 'info' });
        const templateData = { id: t.id, name: t.name, plate: t.plate, amount: t.amount, serviceDate: serviceDate };
        const msg = getFormattedMessage('rescheduled', templateData);
        window.sendSMS(phone, msg, 'rescheduled');
    }

    // AUTOMATED SMS ON STATUS CHANGE
    if(status !== t.status) {
        updates.systemLogs.push({ message: `Status: ${t.status} -> ${status}`, timestamp: new Date().toISOString(), type: 'status' });
        
        if (phone) {
            const templateData = { 
                id: t.id, 
                name: t.name, 
                plate: t.plate, 
                amount: t.amount, 
                serviceDate: serviceDate || t.serviceDate
            };

            // 1. Processing -> Welcome SMS
            if (status === 'Processing') {
                const msg = getFormattedMessage('registered', templateData);
                window.sendSMS(phone, msg, 'welcome_sms');
            }
            
            // 2. Scheduled -> Service Schedule SMS
            else if (status === 'Scheduled') {
                if(!serviceDate) showToast("Note", "Status set to Scheduled without a date.", "info");
                const msg = getFormattedMessage('schedule', templateData);
                window.sendSMS(phone, msg, 'schedule_sms');
            }

            // 3. Contacted -> Called SMS
            else if (status === 'Called') {
                const msg = getFormattedMessage('called', templateData);
                window.sendSMS(phone, msg, 'contacted_sms');
            }

            // 4. Parts Ordered -> Parts Ordered SMS
            else if (status === 'Parts Ordered') {
                const msg = getFormattedMessage('parts_ordered', templateData);
                window.sendSMS(phone, msg, 'parts_ordered_sms');
            }

            // 5. Parts Arrived -> Parts Arrived SMS
            else if (status === 'Parts Arrived') {
                const msg = getFormattedMessage('parts_arrived', templateData);
                window.sendSMS(phone, msg, 'parts_arrived_sms');
            }

            // 6. Completed -> Completed SMS with review link
            else if (status === 'Completed') {
                const msg = getFormattedMessage('completed', templateData);
                window.sendSMS(phone, msg, 'completed_sms');
            }

            // 7. Issue -> Issue SMS
            else if (status === 'Issue') {
                const msg = getFormattedMessage('issue', templateData);
                window.sendSMS(phone, msg, 'issue_sms');
            }
        }
    }

    if(phone) {
        if (document.getElementById('connection-status').innerText.includes('Offline')) {
            const v = vehicles.find(v => v.plate === t.plate);
            if(v) v.phone = phone;
        } else {
            await fetchAPI('sync_vehicle', 'POST', { plate: t.plate, phone: phone });
        }
    }

    if (document.getElementById('connection-status').innerText.includes('Offline')) {
        Object.assign(t, updates);
    } else {
        await fetchAPI(`update_transfer&id=${window.currentEditingId}`, 'POST', updates);
    }
    
    loadData();
    showToast("Changes Saved", "success");
};

window.addNote = async () => {
    const text = document.getElementById('new-note-input').value;
    if(!text) return;
    const t = transfers.find(i => i.id == window.currentEditingId);
    const newNote = { text, authorName: 'Manager', timestamp: new Date().toISOString() };
    
    if (document.getElementById('connection-status').innerText.includes('Offline')) {
        if(!t.internalNotes) t.internalNotes = [];
        t.internalNotes.push(newNote);
    } else {
        const notes = [...(t.internalNotes || []), newNote];
        await fetchAPI(`update_transfer&id=${window.currentEditingId}`, 'POST', { internalNotes: notes });
        t.internalNotes = notes;
    }
    
    document.getElementById('new-note-input').value = '';
    
    // Re-render notes
    const noteHTML = (t.internalNotes || []).map(n => `
        <div class="bg-white p-3 rounded-lg border border-yellow-100 shadow-sm mb-3 animate-in slide-in-from-bottom-2 fade-in">
            <p class="text-sm text-slate-700">${n.text}</p>
            <div class="flex justify-end mt-2"><span class="text-[10px] text-slate-400 bg-slate-50 px-2 py-0.5 rounded-full">${n.authorName}</span></div>
        </div>`).join('');
    document.getElementById('notes-list').innerHTML = noteHTML;
};

window.deleteRecord = async (id) => {
    if(!id) {
        showToast("Error: No record ID", "error");
        return;
    }
    if(confirm("Delete this case permanently?")) {
        if (document.getElementById('connection-status').innerText.includes('Offline')) {
            transfers = transfers.filter(t => t.id !== id);
        } else {
            await fetchAPI(`delete_transfer&id=${id}`, 'POST');
        }
        window.closeModal();
        loadData(); 
        showToast("Deleted", "error");
    }
};

// Event listener for note input
document.getElementById('new-note-input').addEventListener('keypress', (e) => { 
    if(e.key === 'Enter') window.addNote(); 
});

// =====================================================
// VEHICLE MANAGEMENT
// =====================================================

function renderVehicleTable() {
    const term = document.getElementById('vehicle-search').value.toLowerCase();
    const rows = vehicles.filter(v => (v.plate+v.ownerName).toLowerCase().includes(term));
    
    const html = rows.map(v => {
        // Get service history for this plate
        const serviceHistory = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(v.plate));
        const historyCount = serviceHistory.length;
        const lastService = serviceHistory.length > 0 ? serviceHistory[serviceHistory.length - 1] : null;
        
        let historyBadge = '';
        if (historyCount > 0) {
            historyBadge = `<span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2 py-1 rounded-lg text-xs font-semibold">
                <i data-lucide="file-text" class="w-3 h-3"></i> ${historyCount} service${historyCount > 1 ? 's' : ''}
            </span>`;
            if (lastService) {
                const statusColors = {
                    'New': 'bg-blue-50 text-blue-600',
                    'Processing': 'bg-yellow-50 text-yellow-600',
                    'Completed': 'bg-green-50 text-green-600',
                    'Scheduled': 'bg-orange-50 text-orange-600'
                };
                const colorClass = statusColors[lastService.status] || 'bg-slate-50 text-slate-600';
                historyBadge += ` <span class="ml-1 text-[10px] ${colorClass} px-1.5 py-0.5 rounded">${lastService.status}</span>`;
            }
        } else {
            historyBadge = '<span class="text-slate-300 text-xs italic">No history</span>';
        }
        
        return `
        <tr class="border-b border-slate-50 hover:bg-slate-50/50 group transition-colors">
            <td class="px-6 py-4 font-mono font-bold text-slate-800">${v.plate}</td>
            <td class="px-6 py-4 text-slate-600">${v.ownerName || '-'}</td>
            <td class="px-6 py-4 text-sm text-slate-500">${v.phone || '-'}</td>
            <td class="px-6 py-4 text-sm text-slate-500">${v.model || ''}</td>
            <td class="px-6 py-4">${historyBadge}</td>
            <td class="px-6 py-4 text-right">
                <button onclick="window.editVehicle(${v.id})" class="text-primary-600 hover:bg-primary-50 p-2 rounded-lg transition-all"><i data-lucide="edit-2" class="w-4 h-4"></i></button>
                <button onclick="window.deleteVehicle(${v.id})" class="text-red-400 hover:text-red-600 hover:bg-red-50 p-2 rounded-lg transition-all"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
            </td>
        </tr>`;
    }).join('');
    
    document.getElementById('vehicle-table-body').innerHTML = html;
    document.getElementById('vehicle-empty').classList.toggle('hidden', rows.length > 0);
    lucide.createIcons();
}

window.openVehicleModal = () => {
    document.getElementById('veh-id').value = '';
    document.getElementById('veh-plate').value = '';
    document.getElementById('veh-owner').value = '';
    document.getElementById('veh-phone').value = '';
    document.getElementById('veh-model').value = '';
    document.getElementById('vehicle-modal').classList.remove('hidden');
};

window.closeVehicleModal = () => document.getElementById('vehicle-modal').classList.add('hidden');

window.editVehicle = (id) => {
    const v = vehicles.find(i => i.id == id);
    document.getElementById('veh-id').value = id;
    document.getElementById('veh-plate').value = v.plate;
    document.getElementById('veh-owner').value = v.ownerName;
    document.getElementById('veh-phone').value = v.phone;
    document.getElementById('veh-model').value = v.model;
    
    // Show service history
    const serviceHistory = transfers.filter(t => normalizePlate(t.plate) === normalizePlate(v.plate));
    const historySection = document.getElementById('veh-history-section');
    
    if (serviceHistory.length > 0) {
        historySection.classList.remove('hidden');
        const historyHTML = serviceHistory.map(s => {
            const date = s.serviceDate ? new Date(s.serviceDate.replace(' ', 'T')).toLocaleDateString() : 'Not scheduled';
            const statusColors = {
                'New': 'bg-blue-100 text-blue-700',
                'Processing': 'bg-yellow-100 text-yellow-700',
                'Called': 'bg-purple-100 text-purple-700',
                'Parts Ordered': 'bg-indigo-100 text-indigo-700',
                'Parts Arrived': 'bg-teal-100 text-teal-700',
                'Scheduled': 'bg-orange-100 text-orange-700',
                'Completed': 'bg-green-100 text-green-700',
                'Issue': 'bg-red-100 text-red-700'
            };
            const statusClass = statusColors[s.status] || 'bg-slate-100 text-slate-700';
            return `
                <div class="bg-white p-3 rounded-lg border border-slate-200 hover:border-indigo-300 transition-all cursor-pointer" onclick="window.openEditModal(${s.id})">
                    <div class="flex justify-between items-start mb-1">
                        <span class="font-semibold text-slate-700">${s.name}</span>
                        <span class="text-[10px] ${statusClass} px-2 py-0.5 rounded-full font-bold">${s.status}</span>
                    </div>
                    <div class="text-[10px] text-slate-400 flex items-center gap-3">
                        <span><i data-lucide="calendar" class="w-3 h-3 inline"></i> ${date}</span>
                        <span><i data-lucide="coins" class="w-3 h-3 inline"></i> ${s.amount || 0} GEL</span>
                    </div>
                </div>
            `;
        }).join('');
        document.getElementById('veh-history-list').innerHTML = historyHTML;
    } else {
        historySection.classList.add('hidden');
    }
    
    document.getElementById('vehicle-modal').classList.remove('hidden');
    lucide.createIcons();
};

window.saveVehicle = async () => {
    const id = document.getElementById('veh-id').value;
    const data = {
        plate: document.getElementById('veh-plate').value,
        ownerName: document.getElementById('veh-owner').value,
        phone: document.getElementById('veh-phone').value,
        model: document.getElementById('veh-model').value
    };
    
    if (document.getElementById('connection-status').innerText.includes('Offline')) {
        if(id) {
            const idx = vehicles.findIndex(v => v.id == id);
            if(idx > -1) vehicles[idx] = { ...vehicles[idx], ...data };
        } else {
            vehicles.push({ id: Math.floor(Math.random()*10000), ...data });
        }
    } else {
        await fetchAPI(`save_vehicle${id ? '&id='+id : ''}`, 'POST', data);
    }
    window.closeVehicleModal();
    loadData();
    showToast("Vehicle Saved", "success");
};

window.deleteVehicle = async (id) => {
    if(confirm("Delete vehicle?")) {
        if (document.getElementById('connection-status').innerText.includes('Offline')) {
            vehicles = vehicles.filter(v => v.id != id);
        } else {
            await fetchAPI(`delete_vehicle&id=${id}`, 'POST');
        }
        loadData();
        showToast("Vehicle Deleted", "success");
    }
};

// Event listener for vehicle search
document.getElementById('vehicle-search').addEventListener('input', renderVehicleTable);

// =====================================================
// SMS SENDING
// =====================================================

window.sendSMS = async (phone, text, type) => {
    if(!phone) return showToast("No phone number", "error");
    const clean = phone.replace(/\D/g, '');
    try {
        const result = await fetchAPI('send_sms', 'POST', { to: clean, text: text });
        
        if(window.currentEditingId) {
            const t = transfers.find(i => i.id == window.currentEditingId);
            const newLog = { message: `SMS Sent (${type})`, timestamp: new Date().toISOString(), type: 'sms' };
            
            if (document.getElementById('connection-status').innerText.includes('Offline')) {
                if(!t.systemLogs) t.systemLogs = [];
                t.systemLogs.push(newLog);
            } else {
                const logs = [...(t.systemLogs || []), newLog];
                await fetchAPI(`update_transfer&id=${window.currentEditingId}`, 'POST', { systemLogs: logs });
            }
            // Refresh Logs
            const logsToRender = document.getElementById('connection-status').innerText.includes('Offline') ? t.systemLogs : [...(t.systemLogs || []), newLog];
            const logHTML = logsToRender.map(l => `<div class="mb-2 last:mb-0 pl-3 border-l-2 border-slate-200 text-slate-600"><div class="text-[10px] text-slate-400 uppercase tracking-wider mb-0.5">${l.timestamp.split('T')[0]}</div>${l.message}</div>`).join('');
            document.getElementById('activity-log-container').innerHTML = logHTML;
        }
        showToast("SMS Sent", "success");
    } catch(e) { console.error(e); showToast("SMS Failed", "error"); }
};

// =====================================================
// RESCHEDULE HANDLING
// =====================================================

window.quickAcceptReschedule = async (id) => {
    const t = transfers.find(i => i.id == id);
    if (!t || !t.rescheduleDate) return;

    const reqDate = new Date(t.rescheduleDate.replace(' ', 'T'));
    const dateStr = reqDate.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    
    if (!confirm(`Accept reschedule request for ${t.name} (${t.plate})?\n\nNew appointment: ${dateStr}\n\nCustomer will receive SMS confirmation.`)) {
        return;
    }

    try {
        showToast("Processing...", "Accepting reschedule request", "info");
        
        const rescheduleDateTime = t.rescheduleDate.replace(' ', 'T');
        await fetchAPI(`accept_reschedule&id=${id}`, 'POST', {
            service_date: rescheduleDateTime
        });

        t.serviceDate = rescheduleDateTime;
        t.userResponse = 'Confirmed';
        t.rescheduleDate = null;
        t.rescheduleComment = null;
        
        showToast("Reschedule Accepted", `Appointment updated and SMS sent to ${t.name}`, "success");
        loadData();
    } catch(e) {
        console.error('Quick accept reschedule error:', e);
        showToast("Error", "Failed to accept reschedule request", "error");
    }
};

window.acceptReschedule = async () => {
    const t = transfers.find(i => i.id == window.currentEditingId);
    if (!t || !t.rescheduleDate) return;

    if (!confirm(`Accept reschedule request and update appointment to ${new Date(t.rescheduleDate.replace(' ', 'T')).toLocaleString()}?`)) {
        return;
    }

    try {
        const rescheduleDateTime = t.rescheduleDate.replace(' ', 'T');
        document.getElementById('input-service-date').value = rescheduleDateTime;
        
        await fetchAPI(`accept_reschedule&id=${window.currentEditingId}`, 'POST', {
            service_date: rescheduleDateTime
        });

        t.serviceDate = rescheduleDateTime;
        t.userResponse = 'Confirmed';
        
        showToast("Reschedule Accepted", "Appointment updated and SMS sent to customer", "success");
        window.closeModal();
        loadData();
    } catch(e) {
        console.error('Accept reschedule error:', e);
        showToast("Error", "Failed to accept reschedule request", "error");
    }
};

window.declineReschedule = async () => {
    if (!confirm('Decline this reschedule request? The customer will need to be contacted manually.')) {
        return;
    }

    try {
        await fetchAPI(`decline_reschedule&id=${window.currentEditingId}`, 'POST', {});
        
        const t = transfers.find(i => i.id == window.currentEditingId);
        if (t) {
            t.rescheduleDate = null;
            t.rescheduleComment = null;
            t.userResponse = 'Pending';
        }
        
        showToast("Request Declined", "Reschedule request removed", "info");
        window.closeModal();
        loadData();
    } catch(e) {
        console.error('Decline reschedule error:', e);
        showToast("Error", "Failed to decline request", "error");
    }
};

// =====================================================
// REVIEWS SYSTEM
// =====================================================

async function loadReviews() {
    try {
        console.log('Loading reviews...');
        const data = await fetchAPI('get_reviews');
        console.log('Reviews data received:', data);
        
        if (data && data.reviews) {
            customerReviews = data.reviews;
            console.log('Number of reviews:', customerReviews.length);
            
            document.getElementById('avg-rating').textContent = data.average_rating || '0.0';
            document.getElementById('total-reviews').textContent = data.total || 0;
            
            const pendingCount = customerReviews.filter(r => r.status === 'pending').length;
            document.getElementById('pending-count').textContent = pendingCount;
            
            renderReviews();
        } else {
            console.warn('No reviews data in response:', data);
            renderReviews();
        }
    } catch(e) {
        console.error('Load reviews error:', e);
        showToast('Failed to load reviews', 'error');
    }
}

window.filterReviews = (filter) => {
    currentReviewFilter = filter;
    
    ['all', 'pending', 'approved', 'rejected'].forEach(f => {
        const btn = document.getElementById(`filter-${f}`);
        if (f === filter) {
            btn.className = 'flex-1 px-4 py-2 rounded-lg text-sm font-semibold bg-slate-900 text-white transition-all';
        } else {
            btn.className = 'flex-1 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all';
        }
    });
    
    renderReviews();
};

function renderReviews() {
    const container = document.getElementById('reviews-grid');
    const emptyState = document.getElementById('reviews-empty');
    
    console.log('Rendering reviews, total:', customerReviews.length, 'filter:', currentReviewFilter);
    
    let filteredReviews = customerReviews || [];
    if (currentReviewFilter !== 'all') {
        filteredReviews = filteredReviews.filter(r => r.status === currentReviewFilter);
    }
    
    console.log('Filtered reviews:', filteredReviews.length);
    
    if (filteredReviews.length === 0) {
        container.innerHTML = '';
        emptyState.classList.remove('hidden');
        return;
    }
    
    emptyState.classList.add('hidden');
    
    const html = filteredReviews.map(review => {
        const stars = '★'.repeat(review.rating) + '☆'.repeat(5 - review.rating);
        const statusColors = {
            pending: 'bg-yellow-50 border-yellow-200 text-yellow-700',
            approved: 'bg-green-50 border-green-200 text-green-700',
            rejected: 'bg-red-50 border-red-200 text-red-700'
        };
        const statusColor = statusColors[review.status] || statusColors.pending;
        
        const date = new Date(review.created_at).toLocaleDateString('en-GB', { 
            month: 'short', day: 'numeric', year: 'numeric' 
        });
        
        return `
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1">
                        <h3 class="font-bold text-slate-800">${review.customer_name || 'Anonymous'}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">${date}</p>
                    </div>
                    <span class="text-2xl text-yellow-400">${stars}</span>
                </div>
                
                <p class="text-sm text-slate-600 leading-relaxed mb-4 line-clamp-3">${review.comment || ''}</p>
                
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <span class="text-[10px] font-mono font-bold text-slate-400">Order #${review.order_id}</span>
                    <span class="px-2 py-1 rounded-full text-[10px] font-bold border ${statusColor} uppercase">
                        ${review.status}
                    </span>
                </div>
                
                ${review.status === 'pending' ? `
                    <div class="flex gap-2 mt-3">
                        <button onclick="window.approveReview(${review.id})" class="flex-1 bg-green-500 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-green-600 active:scale-95 transition-all flex items-center justify-center gap-1">
                            <i data-lucide="check" class="w-4 h-4"></i> Approve
                        </button>
                        <button onclick="window.rejectReview(${review.id})" class="flex-1 bg-red-500 text-white px-3 py-2 rounded-lg text-sm font-semibold hover:bg-red-600 active:scale-95 transition-all flex items-center justify-center gap-1">
                            <i data-lucide="x" class="w-4 h-4"></i> Reject
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
    
    container.innerHTML = html;
    if (window.lucide) lucide.createIcons();
}

window.approveReview = async (id) => {
    try {
        await fetchAPI(`update_review_status&id=${id}`, 'POST', { status: 'approved' });
        showToast('Review Approved', 'success');
        loadReviews();
    } catch(e) {
        showToast('Failed to approve review', 'error');
    }
};

window.rejectReview = async (id) => {
    if (confirm('Reject this review permanently?')) {
        try {
            await fetchAPI(`update_review_status&id=${id}`, 'POST', { status: 'rejected' });
            showToast('Review Rejected', 'error');
            loadReviews();
        } catch(e) {
            showToast('Failed to reject review', 'error');
        }
    }
};

// =====================================================
// USER MANAGEMENT
// =====================================================

window.toggleUserMenu = function() {
    const dropdown = document.getElementById('user-dropdown');
    dropdown.classList.toggle('hidden');
    if (!dropdown.classList.contains('hidden')) {
        lucide.createIcons();
    }
};

document.addEventListener('click', function(e) {
    const container = document.getElementById('user-menu-container');
    const dropdown = document.getElementById('user-dropdown');
    if (container && dropdown && !container.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

async function loadUsers() {
    try {
        const data = await fetchAPI('get_users', 'GET');
        allUsers = data.users || [];
        renderUsersTable();
    } catch (err) {
        console.error('Error loading users:', err);
        showToast('Error', 'Failed to load users', 'error');
    }
}

function renderUsersTable() {
    const tbody = document.getElementById('users-table-body');
    if (!tbody) return;
    
    if (allUsers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                    <i data-lucide="users" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                    <p>No users found</p>
                </td>
            </tr>
        `;
        lucide.createIcons();
        return;
    }
    
    tbody.innerHTML = allUsers.map(user => {
        const roleColors = {
            admin: 'bg-purple-100 text-purple-800 border-purple-200',
            manager: 'bg-blue-100 text-blue-800 border-blue-200',
            viewer: 'bg-slate-100 text-slate-800 border-slate-200'
        };
        
        const statusColors = {
            active: 'bg-emerald-100 text-emerald-800 border-emerald-200',
            inactive: 'bg-red-100 text-red-800 border-red-200'
        };
        
        const lastLogin = user.last_login ? new Date(user.last_login).toLocaleString() : 'Never';
        
        return `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold">
                            ${user.full_name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div class="font-semibold text-slate-800">${user.full_name}</div>
                            ${user.email ? `<div class="text-xs text-slate-500">${user.email}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="font-mono text-sm text-slate-700">${user.username}</span>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${roleColors[user.role]}">
                        ${user.role.charAt(0).toUpperCase() + user.role.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border ${statusColors[user.status]}">
                        ${user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-600">${lastLogin}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick="window.openEditUserModal(${user.id})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit User">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                        <button onclick="window.openChangeUserPasswordModal(${user.id})" class="p-2 text-slate-600 hover:bg-slate-50 rounded-lg transition-colors" title="Change Password">
                            <i data-lucide="key" class="w-4 h-4"></i>
                        </button>
                        <button onclick="window.deleteUser(${user.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete User">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    lucide.createIcons();
}

window.openCreateUserModal = function() {
    document.getElementById('user-modal-title').textContent = 'Add User';
    document.getElementById('user-save-btn-text').textContent = 'Create User';
    document.getElementById('user-id').value = '';
    document.getElementById('user-username').value = '';
    document.getElementById('user-username').disabled = false;
    document.getElementById('user-password').value = '';
    document.getElementById('password-field').style.display = 'block';
    document.getElementById('user-fullname').value = '';
    document.getElementById('user-email').value = '';
    document.getElementById('user-role').value = 'manager';
    document.getElementById('user-status').value = 'active';
    document.getElementById('user-modal').classList.remove('hidden');
    lucide.createIcons();
};

window.openEditUserModal = function(userId) {
    const user = allUsers.find(u => u.id === userId);
    if (!user) return;
    
    document.getElementById('user-modal-title').textContent = 'Edit User';
    document.getElementById('user-save-btn-text').textContent = 'Update User';
    document.getElementById('user-id').value = user.id;
    document.getElementById('user-username').value = user.username;
    document.getElementById('user-username').disabled = true;
    document.getElementById('password-field').style.display = 'none';
    document.getElementById('user-fullname').value = user.full_name;
    document.getElementById('user-email').value = user.email || '';
    document.getElementById('user-role').value = user.role;
    document.getElementById('user-status').value = user.status;
    document.getElementById('user-modal').classList.remove('hidden');
    lucide.createIcons();
};

window.closeUserModal = function() {
    document.getElementById('user-modal').classList.add('hidden');
};

window.saveUser = async function() {
    const userId = document.getElementById('user-id').value;
    const username = document.getElementById('user-username').value.trim();
    const password = document.getElementById('user-password').value;
    const fullName = document.getElementById('user-fullname').value.trim();
    const email = document.getElementById('user-email').value.trim();
    const role = document.getElementById('user-role').value;
    const status = document.getElementById('user-status').value;
    
    if (!username || !fullName) {
        showToast('Validation Error', 'Username and full name are required', 'error');
        return;
    }
    
    if (!userId && (!password || password.length < 6)) {
        showToast('Validation Error', 'Password must be at least 6 characters', 'error');
        return;
    }
    
    const data = { full_name: fullName, email, role, status };
    if (!userId) {
        data.username = username;
        data.password = password;
    }
    
    try {
        const action = userId ? `update_user&id=${userId}` : 'create_user';
        await fetchAPI(action, 'POST', data);
        showToast('Success', userId ? 'User updated successfully' : 'User created successfully', 'success');
        window.closeUserModal();
        loadUsers();
    } catch (err) {
        console.error('Error saving user:', err);
        showToast('Error', err.message || 'Failed to save user', 'error');
    }
};

window.openChangePasswordModal = function() {
    document.getElementById('pwd-user-id').value = '';
    document.getElementById('pwd-new-password').value = '';
    document.getElementById('pwd-confirm-password').value = '';
    document.getElementById('password-modal').classList.remove('hidden');
    lucide.createIcons();
};

window.openChangeUserPasswordModal = function(userId) {
    document.getElementById('pwd-user-id').value = userId;
    document.getElementById('pwd-new-password').value = '';
    document.getElementById('pwd-confirm-password').value = '';
    document.getElementById('password-modal').classList.remove('hidden');
    lucide.createIcons();
};

window.closePasswordModal = function() {
    document.getElementById('password-modal').classList.add('hidden');
};

window.savePassword = async function() {
    const userId = document.getElementById('pwd-user-id').value;
    const newPassword = document.getElementById('pwd-new-password').value;
    const confirmPassword = document.getElementById('pwd-confirm-password').value;
    
    if (!newPassword || newPassword.length < 6) {
        showToast('Validation Error', 'Password must be at least 6 characters', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showToast('Validation Error', 'Passwords do not match', 'error');
        return;
    }
    
    try {
        const action = userId ? `change_password&id=${userId}` : 'change_password';
        await fetchAPI(action, 'POST', { password: newPassword });
        showToast('Success', 'Password changed successfully', 'success');
        window.closePasswordModal();
    } catch (err) {
        console.error('Error changing password:', err);
        showToast('Error', err.message || 'Failed to change password', 'error');
    }
};

window.deleteUser = async function(userId) {
    const user = allUsers.find(u => u.id === userId);
    if (!user) return;
    
    if (!confirm(`Are you sure you want to delete user "${user.username}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        await fetchAPI(`delete_user&id=${userId}`, 'POST');
        showToast('Success', 'User deleted successfully', 'success');
        loadUsers();
    } catch (err) {
        console.error('Error deleting user:', err);
        showToast('Error', err.message || 'Failed to delete user', 'error');
    }
};

loadData();
if(window.lucide) lucide.createIcons();
