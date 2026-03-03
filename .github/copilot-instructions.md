# OTOMOTORS Manager Portal - AI Coding Instructions

## Architecture Overview

Multi-page PHP application for automotive insurance case management. No build tools — all files are edited directly.

**Stack**: PHP 7+/PDO MySQL backend, Vanilla JS + Tailwind CSS (local with CDN fallback) frontend, Firebase FCM v1 for push notifications, gosms.ge for SMS.

**Production domain**: `https://portal.otoexpress.ge`

**Key Files**:
- [index.php](../index.php) — Manager dashboard (~5.7k lines, inline JS/PHP)
- [api.php](../api.php) — All REST endpoints (~3k lines, 80+ actions)
- [workflow.php](../workflow.php) — Kanban repair board (admin/manager only)
- [technician_dashboard.php](../technician_dashboard.php) — Technician work view
- [edit_case.php](../edit_case.php) — Full-page case editor (~5.4k lines)
- [analytics.php](../analytics.php) — Analytics dashboard (~1.5k lines)
- [vehicles.php](../vehicles.php) — Customer/Vehicle database (~1.3k lines)
- [offers.php](../offers.php) — Offer/voucher management (~1.3k lines)
- [api/mobile-sync/](../api/mobile-sync/) — Mobile app sync endpoints (separate API key auth)
- [config.php](../config.php) — Centralized DB credentials & API keys (includes RO App integration)

**Shared Components**:
- [sidebar.php](../sidebar.php) — Sidebar nav with role-based menu, language selector, connection indicator
- [header.php](../header.php) — Header component with responsive desktop/mobile nav
- [session_config.php](../session_config.php) — Secure session config (httponly, SameSite, 2hr timeout, fingerprint anti-hijacking)
- [language.php](../language.php) — Translation system with DB-backed caching

**JS Modules** ([js/](../js/)):
- [js/api.js](../js/api.js) — `fetchAPI()`, CSRF token header, connection monitoring
- [js/toast.js](../js/toast.js) — `showToast()`, `showConfirm()`, `showLoading()`/`hideLoading()`
- [js/utils.js](../js/utils.js) — `debounce()`, `throttle()`, `escapeHtml()`, `normalizePlate()`, `parseNumber()`, `formatCurrency()`, `formatDate()`, `Storage`, `URLParams`, etc. Exported as `window.OtoUtils`

**CSS**: [assets/custom.css](../assets/custom.css) — Glass morphism, status badge colors, print styles, animations.

**Fonts**: BPG Arial/BPG Arial Caps (Georgian) via [fonts/include_fonts.php](../fonts/include_fonts.php) with Inter fallback.

## Two-Track Case System

### Case Status Pipeline (Customer Journey)
Statuses stored in `statuses` table, loaded dynamically. Status changes trigger SMS via `saveEdit()`:
- New → Processing → Called → Parts Ordered → Parts Arrived → Scheduled → Already in service → Completed
- "Completed" auto-sends review request SMS

### Repair Workflow Stages (Technician Journey)
8-stage kanban tracked in `repair_stage` column:
`backlog` → `disassembly` → `body_work` → `processing_for_painting` → `preparing_for_painting` → `painting` → `assembling` → `done`

JSON columns track work: `repair_assignments` (tech assignments), `stage_timers` (time tracking), `stage_statuses` (per-stage completion).

**Note**: Stage definitions are repeated in multiple files (workflow.php, technician_dashboard.php, api.php) rather than centralized. Dynamic stages also loaded from `workflow_stages` table.

## User Roles & Access

Permission hierarchy in api.php: `viewer (1) < technician (2) < manager (3) < admin (4)`. Plus `operator` role (non-hierarchical, voucher redemption only).

| Role | Access |
|------|--------|
| `admin` | Full access: users, translations, workflow, statuses, SMS parsing, analytics, offers |
| `manager` | Dashboard, cases, workflow, analytics, parts, reviews, templates, offers, nachrebi report, redeem |
| `technician` | Technician dashboard only (auto-redirected from index.php), vehicles, nachrebi report |
| `viewer` | Read-only dashboard, vehicles, reviews, offers |
| `operator` | Voucher redemption page only |

Role-based navigation in [sidebar.php](../sidebar.php). Auth check: `$_SESSION['user_id']`, `$_SESSION['role']`.

## All Application Pages

### Authenticated Pages
| Page | Purpose | Access |
|------|---------|--------|
| [index.php](../index.php) | Manager dashboard, case CRUD, status board | All except technician |
| [edit_case.php](../edit_case.php) | Full-page case editor (`?id=`) | admin/manager edit, viewer read-only |
| [workflow.php](../workflow.php) | Kanban repair board, `?debug=1` for errors | admin, manager |
| [technician_dashboard.php](../technician_dashboard.php) | Technician work view, `?json` for AJAX | technician |
| [analytics.php](../analytics.php) | Revenue/performance metrics, `?range=`/`?from=`/`?to=` | admin, manager |
| [vehicles.php](../vehicles.php) | Customer/Vehicle database, service history | All authenticated |
| [calendar.php](../calendar.php) | Due date calendar view | All authenticated |
| [offers.php](../offers.php) | Offer/voucher CRUD, bulk SMS, view analytics | All except technician |
| [reviews.php](../reviews.php) | Customer review management | All authenticated |
| [templates.php](../templates.php) | SMS template management, workflow bindings | All except technician |
| [parts_collection.php](../parts_collection.php) | Parts collection management | All authenticated |
| [create_collection.php](../create_collection.php) | Create parts collection form | All authenticated |
| [share_collection.php](../share_collection.php) | Shareable parts collection view | All authenticated |
| [nachrebi_report.php](../nachrebi_report.php) | Parts quantity report, filter by month/tech | technician, admin, manager |
| [users.php](../users.php) | User CRUD, auto-creates table | admin only |
| [statuses.php](../statuses.php) | Status management, SortableJS drag-reorder | admin only |
| [translations.php](../translations.php) | Translation management UI | admin only |
| [sms_parsing.php](../sms_parsing.php) | SMS parsing regex config, auto-creates table | admin only |
| [redeem.php](../redeem.php) | Voucher redemption (Georgian UI) | admin, manager, operator |

### Public Pages (No Auth)
| Page | Purpose |
|------|---------|
| [login.php](../login.php) | Login with file-based IP rate limiting (5 attempts, 15-min lockout) |
| [public_view.php](../public_view.php) | Customer-facing service status, star rating widget |
| [public_invoice.php](../public_invoice.php) | Shareable invoice by `?id=` or `?slug=` |
| [redeem_offer.php](../redeem_offer.php) | Public offer redemption (confetti animations) |
| [reviews_form.html](../reviews_form.html) | Standalone review form |
| [print_technician.php](../print_technician.php) | Technician work order print (no prices) |

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

**Helper functions**: `checkPermission($role)` (hierarchy-based), `jsonResponse($data)`, `getJsonInput()` (reads php://input then $_POST), `getCurrentUserId()`.

**CSRF**: POST requests require `X-CSRF-Token` header. Token injected via `window.OtoConfig.CSRF_TOKEN`. Public endpoints exempted.

**Public endpoints** (no auth): `get_public_transfer`, `user_respond`, `submit_review`, `get_public_offer`, `redeem_offer`, `track_offer_view`, `save_completion_signature`

### API Endpoint Categories (~82 total)

- **Cases**: `get_transfers`, `get_transfer`, `add_transfer`, `update_transfer`, `delete_transfer`, `get_transfers_for_parts`, `get_backlog`
- **Case Versions**: `get_case_versions`, `create_case_version`, `update_case_version`, `set_active_version`, `delete_case_version`
- **Case Images**: `upload_case_image`, `delete_case_image`
- **Scheduling**: `accept_reschedule`, `decline_reschedule`, `confirm_appointment`, `bulk_schedule_new`, `resend_schedule_sms`
- **Repair Workflow**: `update_repair_stage`, `assign_technician`, `update_urgent`, `move_to_next_stage`, `finish_stage`
- **Payments**: `create_payment`, `get_payments`, `delete_payment`
- **SMS**: `send_sms`, `get_sms_templates`, `save_templates`, `get_parsing_templates`, `get_workflow_stages`
- **Users**: `get_users`, `create_user`, `update_user`, `change_password`, `delete_user`, `get_current_user`, `get_managers`, `get_technicians`
- **Statuses**: `get_statuses`, `get_status`, `save_status`, `delete_status`, `reorder_statuses`, `toggle_status`
- **Reviews**: `get_reviews`, `update_review_status`
- **Parts Collections**: `get_parts_collections`, `create_parts_collection`, `update_parts_collection`, `delete_parts_collection`
- **Vehicles**: `sync_vehicle`, `delete_vehicle`
- **Consumables**: `get_consumables_costs`, `save_consumables_cost`, `delete_consumables_cost`
- **Offers**: `get_offers`, `create_offer`, `update_offer`, `toggle_offer_status`, `delete_offer`, `send_offer_sms`, `get_customers_for_bulk_sms`, `bulk_send_offer_sms`, `get_offer_views`, `get_offers_for_phone`, `get_offer_redemptions`, `admin_redeem_offer`
- **Translations**: `save_translation`, `export_translations`, `set_language`
- **Misc**: `get_item_suggestions`, `parse_invoice_pdf`

## Mobile Sync API

Separate REST API in [api/mobile-sync/](../api/mobile-sync/) for React Native mobile app.

- Own [config.php](../api/mobile-sync/config.php) with separate DB credentials and `verifyAPIKey()` auth
- CORS set to `*` (all origins)
- Timezone: `Asia/Tbilisi`
- Endpoints: Invoice CRUD, payments, statuses, mechanics list, slug generation

## Frontend Patterns

**JavaScript API calls** via `fetchAPI()` in [js/api.js](../js/api.js):
```javascript
await fetchAPI('update_transfer', 'POST', { id: 123, status: 'Completed' });
```

**Global config**: `window.OtoConfig = { CSRF_TOKEN, API_URL, USE_MOCK_DATA }` (injected by PHP)

**Global modal functions**: `window.openEditModal(id)`, `window.closeModal()`, `window.saveEdit()`

**Toast notifications**: `showToast(title, message, 'success'|'error'|'info'|'urgent')`

**Confirmation dialogs**: `showConfirm(title, message, onConfirm, onCancel)`

**Loading overlay**: `showLoading(message)` / `hideLoading()` (reference-counted)

**Icons**: After injecting HTML, call `lucide.createIcons()` to render Lucide icons.

**QR Codes**: QRCode.js library available in index.php.

## Translation System

Multilanguage via [language.php](../language.php) with `__($key, $default)`:
```php
echo __('dashboard.title', 'OTOMOTORS Manager Portal');
```
Languages: `en` (default), `ka` (Georgian), `ru` (Russian). DB-backed with caching. Admin manages via `translations.php`.

Note: [login.php](../login.php) has its own standalone `__()` function (doesn't use language.php).

## SMS System

### Templates
Templates in `sms_templates` table use placeholders: `{name}`, `{plate}`, `{amount}`, `{link}`, `{date}`

### SMS Sending
Via gosms.ge API using `file_get_contents()`. Phone numbers cleaned to `995XXXXXXXXX` format.

### SMS Parsing
Georgian bank statement parsing uses configurable regex in `sms_parsing_templates` table (admin configurable via [sms_parsing.php](../sms_parsing.php)).

## Push Notifications

Firebase FCM v1 HTTP API via `sendFCM_V1($pdo, $keyFile, $title, $body)`. Uses JWT service account auth ([service-account.json](../service-account.json)). Sends to all tokens in `manager_tokens` table.

## Database

### Key Tables
| Table | Purpose |
|-------|---------|
| `transfers` | Main cases (plate, name, phone, status, status_id, amount, franchise, due_date, slug, vehicle_make/model, repair_stage, repair_assignments JSON, stage_timers JSON, stage_statuses JSON, system_logs JSON, nachrebi_qty, urgent, completion_signature, signature_date, case_images, etc.) |
| `statuses` | Dynamic statuses (name, type, color, bg_color, sort_order, is_active) |
| `users` | User accounts (username, password, full_name, email, role, status) |
| `vehicles` | Vehicle database |
| `customer_reviews` | Review data (rating, timestamps) |
| `sms_templates` | SMS templates (slug, content, workflow_stages, is_active) |
| `sms_parsing_templates` | SMS parsing regex (name, insurance_company, template_pattern, field_mappings JSON) |
| `workflow_stages` | Workflow stage definitions (stage_name, description, stage_order) |
| `parts_collections` | Parts collections (linked to transfers) |
| `translations` | i18n strings (key, language_code, text) |
| `manager_tokens` | FCM push tokens |
| `item_suggestions` | Autocomplete suggestions (name, type, usage_count) |
| `consumables_costs` | Consumable cost records |
| `offers` | Offer/voucher definitions |
| `offer_redemptions` | Redemption records |
| `offer_views` | Offer view analytics |
| `payments` | Payment records per case |

### Migrations
Schema changes via SQL files (`add_*.sql`). Run migrations:
1. Execute SQL in phpMyAdmin, OR
2. Run `fix_db_all.php` which aggregates common fixes
3. Some pages auto-create tables/columns if missing (defensive migration pattern in calendar.php, sms_parsing.php, users.php)

## Security

- **CSRF Protection**: Token in session, sent as `X-CSRF-Token` header on POST requests
- **Session Security**: httponly cookies, HTTPS-only, strict mode, SameSite=Lax, 2hr timeout, 30min ID regeneration, browser fingerprint anti-hijacking
- **Login Rate Limiting**: File-based IP rate limiting (5 attempts, 15-min lockout)
- **CORS**: Main api.php restricted to `https://portal.otoexpress.ge`; mobile-sync API uses `*`
- **No FK constraints**: Tables use VARCHAR IDs, not foreign keys

## Deployment

FTP upload workflow (no CI/CD):
1. Edit files locally
2. Upload via FTP (`Ctrl+Shift+P` → "FTP-Simple: Upload")
3. Hard refresh browser (`Ctrl+Shift+R`)

## Testing/Debug Files

- `test_connection.php` — DB connectivity
- `test_fcm.php` — Firebase push
- `test_sms.php` / `test_sms_templates.php` — SMS testing
- `test_translations.php` — Translation testing
- `debug_*.php` files — Various debugging helpers
- Add `?debug=1` to workflow.php for admin error details

## Critical Conventions

- **Plate normalization**: Use `normalizePlate()` (JS) or equivalent before comparisons (strips non-alphanumeric, uppercases)
- **JSON columns**: `system_logs`, `repair_assignments`, `stage_timers`, `stage_statuses` — always JSON decode/encode
- **Dual column naming**: `system_logs` vs `systemLogs` both exist in transfers table (inconsistent, handle both)
- **Config duplication**: [api/mobile-sync/config.php](../api/mobile-sync/config.php) has separate DB credentials from [config.php](../config.php)
- **Currency**: Georgian Lari (₾), use `formatCurrency()` in JS
- **Timezone**: `Asia/Tbilisi` (set in mobile-sync config)
- **HTML escaping**: Use `escapeHtml()` from js/utils.js for XSS prevention
- **RO App Integration**: `RO_APP_API_URL` and `RO_APP_API_TOKEN` in config for RepairOrder app API
