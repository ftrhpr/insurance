/**
 * SMS Templates Management Module
 * Handles SMS template customization and persistence
 */

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

let smsTemplates = {};

async function loadTemplates() {
    try {
        const data = await fetchAPI('get_templates', 'GET');
        // API returns object directly, not nested in templates
        smsTemplates = data || {};
        
        // Merge with defaults for any missing templates
        Object.keys(defaultTemplates).forEach(key => {
            if (!smsTemplates[key]) {
                smsTemplates[key] = defaultTemplates[key];
            }
        });
        
        loadTemplatesToUI();
    } catch (err) {
        console.error('Error loading templates:', err);
        smsTemplates = { ...defaultTemplates };
        loadTemplatesToUI();
    }
}

function loadTemplatesToUI() {
    const getVal = id => document.getElementById(id)?.value || '';
    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = val || '';
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
}

window.saveAllTemplates = async function() {
    const getVal = id => document.getElementById(id)?.value || '';
    
    smsTemplates = {
        registered: getVal('tpl-registered'),
        called: getVal('tpl-called'),
        schedule: getVal('tpl-schedule'),
        parts_ordered: getVal('tpl-parts_ordered'),
        parts_arrived: getVal('tpl-parts_arrived'),
        rescheduled: getVal('tpl-rescheduled'),
        reschedule_accepted: getVal('tpl-reschedule_accepted'),
        completed: getVal('tpl-completed'),
        issue: getVal('tpl-issue')
    };
    
    try {
        await fetchAPI('save_templates', 'POST', smsTemplates);
        showToast('Success', 'Templates saved successfully', 'success');
    } catch (err) {
        showToast('Error', 'Failed to save templates', 'error');
    }
};

window.resetTemplate = function(key) {
    if (!confirm('Reset this template to default?')) return;
    
    const el = document.getElementById(`tpl-${key}`);
    if (el && defaultTemplates[key]) {
        el.value = defaultTemplates[key];
        showToast('Reset', 'Template reset to default', 'info');
    }
};

window.getFormattedMessage = function(type, data) {
    let template = smsTemplates[type] || defaultTemplates[type] || '';
    
    // Replace placeholders
    template = template.replace(/{name}/g, data.name || '');
    template = template.replace(/{plate}/g, data.plate || '');
    template = template.replace(/{amount}/g, data.amount || '');
    template = template.replace(/{date}/g, data.date || '');
    template = template.replace(/{link}/g, data.link || '');
    
    return template;
};

// Export for use in other modules
window.getFormattedMessage = getFormattedMessage;

// Initialize templates on load
window.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('view-templates')) {
        loadTemplates();
    }
});
