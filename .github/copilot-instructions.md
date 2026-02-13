# OTOMOTORS Manager Portal - AI Coding Instructions

## Architecture Overview

Single-page PHP application for automotive insurance case management. No build tools - all files are edited directly.

**Stack**: PHP 7+/PDO MySQL backend, Vanilla JS + Tailwind CSS (CDN) frontend, Firebase FCM for push, gosms.ge for SMS.

**Key Files**:
- [index.php](../index.php) - Manager dashboard (~5k lines, inline JS/PHP)
- [api.php](../api.php) - All REST endpoints (~3k lines)
- [workflow.php](../workflow.php) - Kanban repair board (admin/manager only)
- [technician_dashboard.php](../technician_dashboard.php) - Technician work view
- [api/mobile-sync/](../api/mobile-sync/) - Mobile app sync endpoints
- [config.php](../config.php) - Centralized DB credentials & API keys

## Two-Track Case System

### Case Status Pipeline (Customer Journey)
Statuses stored in `statuses` table, loaded dynamically. Status changes trigger SMS via `saveEdit()`:
- New → Processing → Called → Parts Ordered → Parts Arrived → Scheduled → Already in service → Completed
- "Completed" auto-sends review request SMS

### Repair Workflow Stages (Technician Journey)  
8-stage kanban tracked in `repair_stage` column:
`backlog` → `disassembly` → `body_work` → `processing_for_painting` → `preparing_for_painting` → `painting` → `assembling` → `done`

JSON columns track work: `repair_assignments` (tech assignments), `stage_timers` (time tracking), `stage_statuses` (per-stage completion).

## User Roles & Access

| Role | Access |
|------|--------|
| `admin` | Full access: users, translations, workflow, statuses, SMS parsing |
| `manager` | Dashboard, cases, parts, reviews, templates |
| `technician` | Technician dashboard only (auto-redirected from index.php) |
| `viewer` | Read-only dashboard access |

Role-based navigation in [sidebar.php](../sidebar.php). Auth check: `$_SESSION['user_id']`, `$_SESSION['role']`.

## API Pattern

All endpoints: `api.php?action={action_name}` with GET/POST methods.

```php
// Adding new endpoint in api.php:
if ($action === 'my_action' && $method === 'POST') {
    if (!checkPermission('manager')) jsonResponse(['error' => 'Forbidden']);
    $data = getJsonInput();  // Use this, NOT $_POST
    // ... process
    jsonResponse(['status' => 'success']);
}
```

Public endpoints (no auth): `login`, `get_order_status`, `submit_review`, `get_public_transfer`, `user_respond`

## Frontend Patterns

**JavaScript API calls** via `fetchAPI()` in [js/api.js](../js/api.js):
```javascript
await fetchAPI('update_transfer', 'POST', { id: 123, status: 'Completed' });
```

**Global modal functions**: `window.openEditModal(id)`, `window.closeModal()`, `window.saveEdit()`

**Toast notifications**: `showToast(title, message, 'success'|'error'|'info'|'urgent')`

**Icons**: After injecting HTML, call `lucide.createIcons()` to render Lucide icons.

## Translation System

Multilanguage via [language.php](../language.php) with `__($key, $default)`:
```php
echo __('dashboard.title', 'OTOMOTORS Manager Portal');
```
Languages: `en` (default), `ka` (Georgian), `ru` (Russian). Admin manages via `translations.php`.

## SMS Template Placeholders

Templates in `sms_templates` table use: `{name}`, `{plate}`, `{amount}`, `{link}`, `{date}`

SMS parsing for Georgian bank statements uses configurable regex in `sms_parsing_templates` table.

## Database Migrations

Schema changes via SQL files (`add_*.sql`). Run migrations:
1. Execute SQL in phpMyAdmin, OR
2. Run `fix_db_all.php` which aggregates common fixes

## Deployment

FTP upload workflow (no CI/CD):
1. Edit files locally
2. Upload via FTP (`Ctrl+Shift+P` → "FTP-Simple: Upload")  
3. Hard refresh browser (`Ctrl+Shift+R`)

## Testing/Debug Files

- `test_connection.php` - DB connectivity
- `test_fcm.php` - Firebase push
- `debug_*.php` files - Various debugging helpers
- Add `?debug=1` to workflow.php for admin error details

## Critical Conventions

- **Plate normalization**: Use `normalizePlate()` before comparisons (strips spaces/hyphens)
- **JSON columns**: `system_logs`, `repair_assignments`, `stage_timers`, `stage_statuses` - always decode/encode
- **No FK constraints**: Tables use VARCHAR IDs, not foreign keys
- **CORS**: Set to `*` (all origins) - security consideration
