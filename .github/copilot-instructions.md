# OTOMOTORS Manager Portal - AI Coding Instructions

## Architecture Overview

This is a **single-page PHP application** for managing automotive insurance service cases at OTOMOTORS. The system uses:
- **Frontend**: Vanilla JavaScript with Tailwind CSS (no build step, CDN-based)
- **Backend**: PHP with PDO MySQL
- **Database**: MySQL with 4 main tables: `transfers`, `vehicles`, `customer_reviews`, `sms_templates`
- **Push Notifications**: Firebase Cloud Messaging (FCM) v1 API
- **SMS**: gosms.ge API integration

Key files:
- `index.php` - Manager dashboard (1700+ lines, all frontend logic inline)
- `api.php` - Backend API endpoints (REST-like GET/POST actions)
- `public_view.php` - Customer-facing order status page
- `config.php` - Database credentials (centralized)

## Critical Workflows

### 8-Stage Service Status Pipeline
The core business logic revolves around these statuses (in `transfers` table):
1. **New** → Initial import state (displayed separately)
2. **Processing** → Triggers welcome SMS
3. **Called** (Contacted) → Triggers schedule SMS
4. **Parts Ordered** → Triggers parts ordered SMS
5. **Parts Arrived** → Triggers confirmation SMS with `{link}` to `public_view.php`
6. **Scheduled** → Service date set, triggers schedule SMS
7. **Completed** → Triggers review request SMS (auto-sent on status change)
8. **Issue** → Exception handling

**Status change triggers automated SMS** via `saveEdit()` function (line ~1300 in `index.php`).

### Georgian Bank Statement Parsing
The system parses Georgian-language SMS messages using regex patterns in `parseBankText()`:
```javascript
/მანქანის ნომერი:\s*([A-Za-z0-9]+)\s*დამზღვევი:\s*([^,]+),\s*([\d\.]+)/i
```
Extracts: plate number, customer name, amount, and optional franchise fees.

### Customer Response Flow
1. Manager changes status to "Parts Arrived" → SMS sent with unique link
2. Customer opens `public_view.php?id={transfer_id}`
3. Customer confirms or requests reschedule (stored in `user_response` column)
4. Manager receives FCM notification via `sendFCM_V1()` function

## Database Schema

### `transfers` table (main entity)
- Core fields: `plate`, `name`, `amount`, `status`, `phone`, `franchise`
- Customer tracking: `user_response` (Pending/Confirmed/Reschedule Requested), `service_date`
- Reviews: `review_stars`, `review_comment` (inline storage, not separate table initially)
- JSON columns: `internalNotes`, `systemLogs` (activity tracking)

### `customer_reviews` table
- Separate moderation system with `status` (pending/approved/rejected)
- References order by `order_id` (VARCHAR, not FK)

### Repair Tool
Run `fix_db_all.php` to create/repair all tables and add missing columns.

## Development Patterns

### SMS Template System
All SMS messages use template placeholders:
- `{name}`, `{plate}`, `{amount}`, `{link}`, `{date}`
- Stored in `sms_templates` table with slug keys (registered, schedule, parts_arrived, completed, etc.)
- Function: `getFormattedMessage(type, data)` (line ~980 in `index.php`)

### Offline-First Design
The app checks `connection-status` div text before API calls:
```javascript
if (document.getElementById('connection-status').innerText.includes('Offline')) {
    // Update local arrays (transfers, vehicles)
} else {
    await fetchAPI('endpoint', 'POST', data);
}
```

### API Endpoint Pattern
All endpoints follow: `api.php?action={action_name}`
- Use `getJsonInput()` for POST body (not `$_POST`)
- Always return JSON via `jsonResponse()` helper
- Example: `api.php?action=update_transfer&id=123` (POST)

### Firebase Setup
- Config in both `index.php` (inline) and `firebase-messaging-sw.js`
- Uses service worker for background notifications
- Token registration stored in `manager_tokens` table
- OAuth 2.0 JWT flow for v1 API access (see `getAccessToken()` in `api.php`)

## Common Tasks

### Adding a New Status
1. Update status options in `index.php` (lines ~238, ~549)
2. Add status color mapping in `renderTable()` (~line 1180)
3. Add SMS trigger logic in `saveEdit()` (~line 1300)
4. Create/update template in SMS Templates tab

### Adding API Endpoint
```php
if ($action === 'my_action' && $method === 'POST') {
    $data = getJsonInput();
    // Process data
    jsonResponse(['status' => 'success']);
}
```

### Modal Pattern
All modals use global functions:
- `window.openEditModal(id)` / `window.closeModal()`
- Store current editing ID: `window.currentEditingId`
- Save via `window.saveEdit()` (async)

## Security Notes

- **Database credentials hardcoded** in `api.php` and `config.php` (production values present)
- **SMS API key exposed** in `api.php` line 272
- No authentication system - manager portal is open access
- CORS set to `*` (all origins allowed)

## Testing & Debugging

- `test_connection.php` - Verify database connectivity
- `test_fcm.php` - Test Firebase push notifications
- `debug_fcm.html` - Frontend FCM testing interface
- Browser console logs all API calls via `fetchAPI()` function

## File Upload Deployment
Per `QUICK_START.txt`, deployment uses FTP:
- Upload `index.php` and `api.php` after changes
- Run database migrations via `fix_db_all.php`
- Hard refresh browser cache (Ctrl+Shift+R)

## Key Conventions

- **Plate normalization**: Use `normalizePlate()` for comparisons (removes spaces/hyphens)
- **Date format**: MySQL datetime for `service_date`, converted to HTML5 datetime-local (`YYYY-MM-DDTHH:MM`)
- **Lucide icons**: Always re-init with `lucide.createIcons()` after dynamic HTML injection
- **Toast notifications**: `showToast(title, [message], type)` - types: success/error/info/urgent
- **No build tools**: All changes are direct file edits, no npm/webpack/vite
