# KRYSTAL ATTENDANCE SYSTEM — PROJECT MASTER PLAN v3.0
### PRODUCTION-GRADE SINGLE SOURCE OF TRUTH
> Every future AI agent and developer MUST read this entire document before touching any code.

---

## SECTION 1 — ARCHITECTURE OVERVIEW

### Technology Stack
| Layer | Technology | Notes |
|---|---|---|
| Backend | PHP 8.x | Procedural, security-first helpers |
| Database | MySQL/MariaDB + PDO | 100% Prepared Statements |
| Frontend | Vanilla HTML5/CSS3/JS (ES6+) | No frameworks |
| API | Internal JSON REST | CSRF + Session protected |
| Hosting | Hostinger/cPanel Shared | Plan all features around this constraint |

### Directory Sensitivity Map
| Path | Access | Risk Level |
|---|---|---|
| `/api/` | Public (HTTP) | CRITICAL — must always require login + CSRF |
| `/includes/` | Blocked via `.htaccess` | CRITICAL — never expose directly |
| `/logs/` | Blocked via `.htaccess` | HIGH — contains internal error data |
| `/css/`,`/js/`,`/assets/` | Public | LOW |
| `setup.php` | Blocked via `.htaccess` + lockfile | HIGH |
| `migrate_audit.php` | Blocked via `.htaccess` + lockfile | HIGH |
| `PROJECT_MASTER_PLAN.md` | Readable via HTTP | MEDIUM — add to `.htaccess` deny list |

**ACTION NEEDED:** `PROJECT_MASTER_PLAN.md` should be added to the `.htaccess` `FilesMatch` deny list in production.

---

## SECTION 2 — DATABASE SCHEMA (FULL)

### Database Charset Standard
- All tables and DB connections MUST use:
  - `utf8mb4`
  - `utf8mb4_unicode_ci`
- PDO connection must execute:
  `SET NAMES utf8mb4`
- Never use legacy utf8 (3-byte) encoding because it breaks full Unicode support.

### MySQL SQL Mode
Production MySQL sessions should enable:
- `STRICT_TRANS_TABLES`
- `ERROR_FOR_DIVISION_BY_ZERO`
- `NO_ENGINE_SUBSTITUTION`
This prevents silent invalid inserts and inconsistent DB behavior.

### Table: `users`
```
id INT AUTO_INCREMENT PK
username VARCHAR(100) UNIQUE NOT NULL
password VARCHAR(255) NOT NULL          -- bcrypt only
role ENUM('shift','admin') NOT NULL
shift VARCHAR(20) NOT NULL              -- 'morning','afternoon','night','all'
created_at TIMESTAMP
```

### Table: `attendance_records`
```
id INT AUTO_INCREMENT PK
shift VARCHAR(20) NOT NULL
attendance_date DATE NOT NULL
created_by INT DEFAULT NULL
created_at TIMESTAMP
INDEX: idx_shift(shift), idx_date(attendance_date)
```

### Table: `attendance_employees`
```
id INT AUTO_INCREMENT PK
attendance_record_id INT NOT NULL       -- FK → attendance_records.id CASCADE DELETE
employee_name VARCHAR(150) NOT NULL
employee_id VARCHAR(50) NOT NULL
post VARCHAR(50) NOT NULL
status VARCHAR(20) NOT NULL DEFAULT 'present'
created_at TIMESTAMP
```

### Table: `audit_log`
```
id BIGINT UNSIGNED AUTO_INCREMENT PK
user_id INT, username VARCHAR(100), role VARCHAR(20), shift VARCHAR(20)
action_type VARCHAR(80) NOT NULL
target_type VARCHAR(50), target_id INT
details_json TEXT
ip_address VARCHAR(45), user_agent VARCHAR(255)
created_at TIMESTAMP
INDEX: idx_action, idx_user_id, idx_username, idx_shift, idx_created_at
```

### Database Integrity Constraints (UNIQUE KEY Strategy)
DB-level constraints are the **final safety layer** beyond frontend and API validation. If API validation is bypassed, the DB must still reject invalid data.

| Table | Constraint Name | Columns | Purpose |
|---|---|---|---|
| `attendance_records` | `unique_shift_date` | `(shift, attendance_date)` | Prevent duplicate records for same shift+day |
| `attendance_employees` | `unique_employee_per_record` | `(attendance_record_id, employee_id)` | Prevent duplicate staff ID within one record |
| `users` | `unique_username` | `(username)` | Already enforced; document explicitly |

- **Implementation note:** `attendance_records` currently handles duplicate shift+date via application-level overwrite logic. The DB UNIQUE constraint must be evaluated before adding to avoid conflicts with the overwrite flow. Add constraint only alongside a controlled migration.
- **Never remove** the API-level duplicate ID check even after DB constraints are added — defense in depth.

### Timezone Standard
- **Standard:** `Asia/Kolkata` (IST, UTC+5:30) for all date/time operations.
- PHP must set: `date_default_timezone_set('Asia/Kolkata')` — add to `includes/config.php`.
- MySQL session must set: `SET time_zone = '+05:30'` — add to PDO connection init.
- **Never** rely on browser-reported timezone for attendance dates, salary calculations, or report generation.
- All `created_at` and `attendance_date` values in the DB must be interpreted as IST.

### Soft Delete Strategy (Future)
- **Current behavior:** Hard delete (`DELETE FROM attendance_records WHERE id=?`). Child rows cascade-deleted via FK.
- **Recommendation for Phase 3B+:** Introduce soft delete columns: `deleted_at TIMESTAMP NULL DEFAULT NULL`, `deleted_by INT NULL`.
- All read queries add `WHERE deleted_at IS NULL`. Delete API sets `deleted_at = NOW()` and `deleted_by = user_id` instead of `DELETE`.
- **Benefits:** Legal/audit recovery, accidental-delete recovery, salary calculation safety (finalized months still have data).
- **Do NOT implement** until Phase 3B. Current production environment is low-risk and hard delete is simpler.

### Database Indexing Strategy
- **Current indexes are sufficient** for the current scale (hundreds of records).
- **Future analytics risk:** When `attendance_employees` exceeds 50,000 rows, add `INDEX(employee_id)` and `INDEX(status)`.
- **Audit log growth:** At 500+ entries/day, add a composite `INDEX(created_at, action_type)` for dashboard queries.
- **Monthly/yearly filter queries** (Phase 3C) will need `INDEX(YEAR(attendance_date), MONTH(attendance_date))` — plan as a functional index in MySQL 8+ or use a `year_month` computed column.
- **Pagination is already implemented** in `audit-log.php` (50 rows/page). All future list views MUST use pagination — never `SELECT *` without `LIMIT`.
- **Maximum safe pattern:** Never load more than 500 rows per API response. Use cursor-based or offset pagination.

---

## SECTION 3 — COMPLETE DATA FLOW ARCHITECTURE

### 3.1 Login Flow
1. Browser loads `index.php` → PHP calls `getCsrfToken()` → token embedded in JS variable `CSRF_TOKEN`.
2. User submits form → `app.js initLogin()` POSTs JSON to `api/login.php` with `X-CSRF-TOKEN` header.
3. `api/login.php`: `requireCsrf()` → `loginUser()` → `password_verify()` → `session_regenerate_id(true)` → sets `$_SESSION` → `generateCsrfToken()` → `auditLog('LOGIN_SUCCESS')`.
4. Response: `{success:true, csrf_token:"new_token"}` → JS updates `CSRF_TOKEN` → redirect to `dashboard.php`.
5. **Failure path:** `auditLog('LOGIN_FAILURE')` → `{success:false, error:"..."}` → UI shows error.

### 3.2 Session Validation Flow
- Every protected PHP page calls `requireLogin()` at the top.
- `requireLogin()` → `isLoggedIn()` → checks `$_SESSION['user_id']` AND `$_SESSION['shift']`.
- If session is missing: HTML pages redirect to `index.php`. AJAX requests (detect via `HTTP_X_REQUESTED_WITH`) return `401 JSON`.
- **Session name:** `KRYSTAL_SESSID` (custom, harder to guess than default `PHPSESSID`).
- **Cookie flags:** `HttpOnly: true`, `SameSite: Strict`, `Secure: false` (must be `true` in production/HTTPS).

### 3.3 CSRF Lifecycle
- Token created at first `session_start()` via `generateCsrfToken()` using `bin2hex(random_bytes(32))`.
- Token stored in `$_SESSION['csrf_token']`.
- Token is embedded into every page via `<-php echo json_encode(getCsrfToken()); ->`.
- Every POST/DELETE AJAX call sends `X-CSRF-TOKEN: {token}` header.
- `requireCsrf()` uses `hash_equals()` for timing-safe comparison.
- Token is **regenerated** on login. It is **NOT rotated** per-request (stateless per-session design).
- CSRF token expires with session expiry.
- csrf_token must be unset during logout.
- **Future Phase 3A+:** rotate token on password/security-sensitive actions.
- **Future hardening:** Rotate token on sensitive operations (password change, etc.) in Phase 3A.

### 3.4 Attendance Save Lifecycle (New Record)
1. User adds employees to in-memory `attendanceData[]` array in JS.
2. Clicks "Final Save" → JS validates locally (duplicate ID, name length, etc.).
3. `apiCall('api/attendance.php', 'POST', payload)` with CSRF header.
4. API: `requireLogin()` → `requireCsrf()` → JSON input decode → field validation → shift access check.
5. `$db->beginTransaction()` → check for existing date+shift record → INSERT `attendance_records` → INSERT all `attendance_employees` → `$db->commit()`.
6. `auditLog('ATTENDANCE_ADD', {...})` called ONLY after commit.
7. JS: clears `localStorage.removeItem('previewData')` → redirect to `dashboard.php`.

### 3.5 Attendance Edit Lifecycle
1. Dashboard "Edit" button → redirect to `add-attendance.php?edit={id}`.
2. PHP loads record from DB (with shift access check) → injects `EDIT_DATA` JSON into page.
3. JS: checks `localStorage['previewData']` — if `editId` matches stored `editId`, use localStorage state (preserves "Back" from preview). Otherwise use server-provided `EDIT_DATA`.
4. Save: POST with `editId` in payload → API `beginTransaction()` → `UPDATE attendance_records` → `DELETE attendance_employees WHERE attendance_record_id=-` → re-INSERT all employees → `commit()`.

### 3.6 Preview Persistence Lifecycle (HIGHEST RISK FLOW)
1. "Preview" button clicked → `localStorage.setItem('previewData', JSON.stringify({date, shift, employees, editId}))`.
2. Redirect to `preview.php?edit={id}&shift={shift}` (or no params for new).
3. `preview.php`: server-side only reads `editId` and `shift` from URL for Back button URL construction. **Never reads localStorage.**
4. `app.js initPreview()`: reads `localStorage['previewData']` → renders all sections client-side.
5. "Back" button → navigates to `add-attendance.php?edit={id}` (NO `?new=1`).
6. `add-attendance.php` on load: reads `localStorage['previewData']` → if `p.editId == editRecordId` (or both null for new) → restores employee list.
7. **Critical rule:** `?new=1` flag CLEARS localStorage. It must ONLY appear when starting a genuinely fresh session from `select-shift.php` or dashboard "Add New" link.

### 3.7 Delete Lifecycle
1. Dashboard delete button → `confirm()` dialog → `apiCall('DELETE', {id})`.
2. API: `requireLogin()` → `requireCsrf()` → fetch record → shift access check → `DELETE FROM attendance_records WHERE id=-`.
3. MySQL `ON DELETE CASCADE` automatically deletes all child `attendance_employees` rows.
4. `auditLog('ATTENDANCE_DELETE', {record_date, record_shift})`.
5. JS reloads dashboard records.

### 3.8 Admin Shift Selection Lifecycle
1. Admin clicks "Add New Attendance" from dashboard → redirected to `select-shift.php`.
2. `select-shift.php`: enforces `role === 'admin'`. Non-admins are redirected to `add-attendance.php?new=1`.
3. Admin clicks a shift card → redirects to `add-attendance.php?new=1&shift=morning` (etc.).
4. `add-attendance.php`: `?new=1` clears localStorage. `$_GET['shift']` sets `$activeShift` (validated against whitelist). `auditLog('SHIFT_SELECTED')` fired only when `?new=1` is present.

### 3.9 CSV Export Lifecycle
- **Entirely client-side.** No server request made.
- JS builds CSV string from `attendanceData[]` → `Blob` → `URL.createObjectURL()` → `<a download>` click.
- Available from both `add-attendance.php` and `preview.php`.
- **Risk:** No server-side validation. Export reflects in-memory state only, not necessarily what is saved in DB.

### 3.10 PDF/Print Lifecycle
1. "Download PDF" button → JS saves `localStorage['previewData']` → opens `preview.php` in new tab.
2. New tab `preview.php` reads localStorage → renders → JS fires `window.print()` after 600ms timeout.
3. Browser print dialog handles PDF generation.
4. **Risk:** The 600ms timeout is fragile if the page is slow. On slow connections, printing may trigger before render completes.

### 3.11 Audit Log View Lifecycle
- `audit-log.php`: `requireLogin()` + role check (`role !== 'admin'` → redirect).
- Paginated query (50/page) with filters: date, action_type, username, shift.
- Filter inputs validated before query (date via `validateDate()`, shift via whitelist).
- All output escaped via `esc()` (= `htmlspecialchars`).

---

## SECTION 4 — SESSION ARCHITECTURE (PHASE 3A DESIGN)

### Current State
- Sessions use `KRYSTAL_SESSID` cookie, `HttpOnly`, `SameSite: Strict`.
- No idle timeout exists. No absolute timeout exists.
- `session_regenerate_id(true)` called only on login.

### Phase 3A-1 Target Design
- **Idle Timeout:** Store `$_SESSION['last_activity']` timestamp. On every `requireLogin()`, check if `time() - last_activity > 1800` (30 min). If expired, call `logoutUser()` and redirect.
- **Absolute Timeout:** Store `$_SESSION['login_time']`. If `time() - login_time > 43200` (12 hours), force logout regardless of activity.
- **AJAX Expiry Handling:** All `apiCall()` responses that return `401` must trigger `window.location.href = 'index.php-expired=1'`.
- **Secure Flag:** In production (APP_ENV=production), set `'secure' => true` in `session_set_cookie_params()`.
- **Multi-tab Behavior:** All tabs share one PHP session. Logout in one tab will cause all other tabs' next API call to return 401. The frontend must handle this gracefully.
- **Session Fixation:** Already handled via `session_regenerate_id(true)` on login. Do not remove this.
- **Files to modify in 3A-1:** ONLY `includes/auth.php` (`startSecureSession`, `requireLogin`) and `js/app.js` (401 handler in `apiCall()`).

### Sensitive Functions (DO NOT MODIFY without full test)
| Function | File | Risk |
|---|---|---|
| `startSecureSession()` | `includes/auth.php` | CRITICAL |
| `loginUser()` | `includes/auth.php` | CRITICAL |
| `logoutUser()` | `includes/auth.php` | CRITICAL |
| `requireCsrf()` | `includes/auth.php` | CRITICAL |
| `generateCsrfToken()` | `includes/auth.php` | HIGH |

---

## SECTION 5 — API ARCHITECTURE STANDARDS

### Request Standards
- Method: `GET` (read), `POST` (create/update), `DELETE` (delete).
- Body: JSON-encoded (`Content-Type: application/json`).
- Auth header: `X-CSRF-TOKEN: {token}` on all POST/DELETE.

### Response Standards
- Always `Content-Type: application/json`.
- Success: `{"success": true, ...data}` with HTTP 200.
- Error: `{"success": false, "error": "User-safe message"}` with appropriate HTTP code.

### HTTP Status Code Standards
| Code | Use Case |
|---|---|
| 200 | Success |
| 400 | Validation failure (bad input) |
| 401 | Not authenticated |
| 403 | Authenticated but not authorized (CSRF fail, wrong shift) |
| 404 | Record not found |
| 405 | Wrong HTTP method |
| 500 | Server/DB error |

### Mandatory Checklist for Every New API Endpoint
- [ ] `requireLogin()` called first
- [ ] `requireMethod([...])` called
- [ ] `requireCsrf()` called for POST/DELETE
- [ ] All inputs validated before DB interaction
- [ ] Shift access check (`user['role'] !== 'admin' && shift !== user['shift']`)
- [ ] `beginTransaction()` for any multi-table write
- [ ] `auditLog()` called AFTER successful commit
- [ ] Errors caught in `try/catch`, logged via `logException()`, response via `jsonError()`

### NEVER Rules for APIs
- NEVER return raw PHP error messages.
- NEVER return stack traces.
- NEVER return DB credentials in any error response.
- NEVER skip `requireCsrf()` on mutation endpoints.
- NEVER skip transaction wrapping for multi-table writes.
- NEVER log passwords, tokens, or session IDs.

### Error Trace Strategy
- Every critical exception in `try/catch` blocks MUST generate a unique error reference ID: `$errorId = 'ERR-' . strtoupper(bin2hex(random_bytes(4)))`.
- The error ID is returned in the JSON error response: `{"success": false, "error": "An error occurred. Ref: ERR-XXXX"}`.
- The full exception trace (class, message, file, line) is stored ONLY in `logs/krystal_YYYY-MM-DD.log` via `logException()`.
- Users NEVER see stack traces, file paths, or DB error messages.
- Error IDs allow admins to cross-reference user-reported errors with internal logs without exposing system internals.
- **Implementation target:** Phase 3A-2 (config hardening pass). Add helper `logExceptionWithRef($msg, $e, $context)` to `logger.php`.

### API Versioning Strategy
- **Current:** All API endpoints live at `/api/` (e.g., `api/attendance.php`, `api/login.php`).
- **Future trigger:** Before any mobile app, external system, or React/Vue frontend integration, migrate to `/api/v1/` structure.
- **Strategy:** Create `/api/v1/` directory. Existing `/api/` endpoints remain untouched for backward compatibility. New endpoints go into `/api/v1/` only.
- **Versioning rule:** A version is deprecated only after all consumers have migrated. Never delete an API version without explicit confirmation.
- **Do NOT implement** v1 versioning now. Plan the directory structure and add to Phase 3F checklist.

---

## SECTION 6 — ROLE & ACCESS CONTROL MATRIX

| Feature / Route | Shift User | Admin |
|---|---|---|
| `index.php` (Login) | ✅ | ✅ |
| `dashboard.php` | ✅ Own shift | ✅ All shifts |
| `add-attendance.php` | ✅ Own shift only | ✅ Any shift |
| `select-shift.php` | ❌ Redirected | ✅ |
| `preview.php` | ✅ | ✅ |
| `audit-log.php` | ❌ Redirected | ✅ |
| `api/attendance.php GET` | Own shift only | All shifts |
| `api/attendance.php POST` | Own shift only | All shifts |
| `api/attendance.php DELETE` | Own shift record only | Any record |
| `api/login.php` | ✅ | ✅ |
| `api/session.php` | ✅ | ✅ |

### Future Role Expansion Rules
- Add new roles to `users.role` ENUM ONLY after adding access checks in `auth.php`.
- Never add a new role without updating this matrix and all `requireLogin()` gating logic.
- Future roles (HR, Auditor, Payroll Manager) must be additive — they must not break existing `shift`/`admin` logic.
- Route-level permission should be checked in PHP (server-side). Frontend hiding of links is cosmetic only.

---

## SECTION 7 — CONCURRENCY & MULTI-ADMIN SAFETY

### Current Risk
- If two admins open the same attendance record for editing simultaneously, the last save wins with no warning. The first admin's changes are silently overwritten.

### Planned Solution (Phase 3B or later)
- Add `updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` to `attendance_records`.
- On edit load: store `original_updated_at` in the form/localStorage.
- On save: add `WHERE id=- AND updated_at=-` to the UPDATE query. If 0 rows affected, the record was modified by another user → return a `409 Conflict` error.
- Frontend displays a user-friendly "This record was modified by another user. Please reload." message.
- **Do NOT implement** until Phase 3B unless explicitly requested. The current single-admin production environment has low risk.


---

## SECTION 8 - SECURITY HARDENING ROADMAP
### Production Logging Restrictions
Production logs must NEVER contain:
- passwords
- session IDs
- CSRF tokens
- DB credentials
- Authorization headers


### Completed
- [x] CSRF protection (token-based, `hash_equals`)
- [x] SQL injection prevention (100% prepared statements)
- [x] XSS prevention (`esc()` / `htmlspecialchars` on all output)
- [x] Password hashing (bcrypt via `password_hash`)
- [x] Session hardening (`HttpOnly`, `SameSite:Strict`, custom session name)
- [x] Path protection (`.htaccess` on `/includes/`, `/logs/`)
- [x] Production error suppression (no stack traces to browser)
- [x] Audit logging (all sensitive actions tracked)
- [x] Setup/migration scripts locked (lock files + key params)

### Planned - Phase 3A
- [ ] Idle + absolute session timeout
- [ ] `Secure` cookie flag (requires HTTPS on Hostinger)
- [ ] AJAX 401 handler - graceful redirect
- [ ] `APP_ENV=production` mode toggle verification

### Planned - Future Phases
- [ ] Rate limiting on `api/login.php` (max 5 attempts/15min/IP, stored in DB or APCu)
- [ ] Account lockout after N failed login attempts (add `failed_attempts`, `locked_until` to `users` table)
- [ ] Security headers: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin`
- [ ] Content Security Policy (CSP) header - must be planned carefully to not break Font Awesome CDN
- [ ] Password complexity policy (min 8 chars, mixed case) - for new password change feature
- [ ] IP logging already done in audit log. Flag suspicious patterns (future monitoring module)
- [ ] File upload protection: If any file upload is ever added, enforce MIME type check + store outside webroot

### Future Password Reset Architecture
When a password reset feature is added, it MUST follow these rules:
- Generate a cryptographically secure token: `bin2hex(random_bytes(32))`.
- Store **only the hashed token** in the DB: `password_hash($token, PASSWORD_DEFAULT)`. Never store plain token.
- Token must expire after 15–30 minutes (`expires_at TIMESTAMP`).
- Token is **single-use**: mark as `used=1` immediately upon consumption, before resetting the password.
- Token table: `password_resets(id, user_id, token_hash, expires_at, used, created_at)`.
- Reset links sent via email only (SMTP). Never display tokens in the UI after generation.
- Audit log event: `PASSWORD_RESET_REQUEST` and `PASSWORD_RESET_SUCCESS`.

---

## SECTION 9 - PRODUCTION DEPLOYMENT ARCHITECTURE (HOSTINGER/CPANEL)

### Folder Strategy
```
public_html/
+-- krystal/            - Application root
-   +-- .htaccess       - Deny direct access to sensitive files
-   +-- api/
-   +-- includes/       - .htaccess: Deny from all
-   +-- logs/           - .htaccess: Deny from all
-   +-- css/, js/, assets/
-   +-- *.php (pages)
```

### .htaccess Hardening (Production Additions Needed)
Current `.htaccess` denies: `setup.php`, `migrate_audit.php`, `_dbcheck.php`, `*.log`, `*.bak`, `*.lock`.
**Must also add:** `PROJECT_MASTER_PLAN.md`, `composer.json` (if added), `*.md` files.

```apache
<FilesMatch "\.(md|json|lock|log|bak|env)$">
    Order deny,allow
    Deny from all
</FilesMatch>
```

### Environment Configuration Strategy
- **Current:** `APP_ENV` constant defined directly in `includes/config.php`.
- **Phase 3A-2:** Introduce a `config.local.php` pattern - `config.php` loads `config.local.php` if it exists. `config.local.php` is in `.gitignore`. Production values live there.
- **Future:** Migrate to `.env` file outside `public_html/` (e.g. one directory up on Hostinger). Use `$_ENV` or `getenv()`. Never commit credentials.
- **Never** expose `APP_ENV`, `DB_PASS`, or any credential in a publicly accessible file.
- **Security Rule:** If .env is ever temporarily placed inside webroot during migration/testing, .htaccess protection MUST still deny access.

### SSL/HTTPS Enforcement
- Hostinger provides free SSL. Enforce via `.htaccess`:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```
- Once HTTPS is enforced, update `session_set_cookie_params` to set `'secure' => true`.

### Log Rotation (Hostinger Shared Hosting)
- `logger.php` already auto-rotates log files at 5MB (`LOG_MAX_BYTES`).
- On Hostinger, cron jobs can be configured. Schedule a monthly cleanup of `.log.bak` files.
- Never store more than 30 days of raw logs on shared hosting due to disk quota limits.

### Backup Strategy
- **Database:** Use Hostinger's built-in daily backup or a scheduled `mysqldump` via cron. Frequency: daily. Retention: 7 days minimum.
- **Files:** Download full `public_html/krystal/` weekly via FTP/SFTP.
- **Before every deployment:** Take a manual DB snapshot and file backup.
- **Restore test:** Verify backup restore works in a staging environment before relying on it.

### File Permission Policy
| Target | Permission | Notes |
|---|---|---|
| PHP files | `644` | Owner read/write, group/other read |
| Directories | `755` | Owner full, group/other read+execute |
| `logs/` | `750` | Owner full, group read, no other access |
| `includes/` | `755` | Protected by `.htaccess`, not permission alone |
| **NEVER** | `777` | Grants world-write — never acceptable on shared hosting |

- On Hostinger/cPanel, set permissions via File Manager or `chmod` in SSH.
- The `logs/` directory must be writable by the PHP process (web server user). `750` with correct group ownership achieves this.

### Environment Separation Rules
| Environment | Purpose | DB | Errors | Deployment |
|---|---|---|---|---|
| **Local** | Development (XAMPP) | Local DB | Visible | Direct file edit |
| **Staging** | Pre-release testing | Copy of production DB | Logged only | Deploy and test first |
| **Production** | Live system (Hostinger) | Production DB | Hidden | Deploy only after staging pass |

- **Rule:** Never deploy code directly to production without first verifying it on staging.
- **Rule:** All DB migrations must be tested on staging before running on production.
- **Rule:** `APP_ENV = 'development'` must NEVER be set on the production server.
- **Staging environment:** Can be a subdirectory (`staging.domain.com`) or a separate Hostinger subdomain.

### Maintenance / Deployment Mode
- Before any migration or risky deployment, enable maintenance mode:
  1. Rename `index.php` to `index.php.bak` temporarily, replace with a static maintenance page.
  2. This blocks all new user sessions while existing sessions complete their requests.
- Take a full DB backup (`mysqldump`) **before** any schema migration.
- For non-breaking file-only deployments (CSS, JS), maintenance mode is optional but recommended.
- Re-enable by restoring `index.php`. Verify login works before announcing availability.
- Future: implement a `MAINTENANCE_MODE` constant in `config.php` that shows a friendly page if `true`.

### Deployment Checklist (Every Release)
1. [ ] Take full DB backup before deploying
2. [ ] Enable maintenance mode (for schema changes)
3. [ ] Run migration on staging first; verify result
4. [ ] Set `APP_ENV = 'production'` in config
5. [ ] Verify `display_errors = Off` in php.ini or `.htaccess`
6. [ ] Verify HTTPS redirect is active
7. [ ] Verify `logs/` directory is writable by PHP (permission 750)
8. [ ] Verify `includes/` and `logs/` are inaccessible from browser
9. [ ] Test login - add attendance - preview - save - delete flow
10. [ ] Check browser console for JS errors
11. [ ] Check `logs/krystal_YYYY-MM-DD.log` for PHP errors after deployment
12. [ ] Disable maintenance mode; confirm login from a fresh browser session

---

## SECTION 10 - AUDIT LOG RETENTION & SCALABILITY

### Growth Estimate
- Average: ~10-20 audit events/day in normal operation.
- At 20 events/day - ~7,300 rows/year. Well within MySQL capability for 5+ years.
- The `audit_log` table uses `BIGINT UNSIGNED` PK - supports 18 quintillion rows.

### Retention Policy
| Event Type | Retention | Can Archive- |
|---|---|---|
| LOGIN_SUCCESS | 90 days | Yes |
| LOGIN_FAILURE | 1 year | No - security evidence |
| ATTENDANCE_ADD | Forever | No - business record |
| ATTENDANCE_EDIT | Forever | No - business record |
| ATTENDANCE_DELETE | Forever | No - business record |
| SHIFT_SELECTED | 30 days | Yes |
| LOGOUT | 30 days | Yes |

### Archive Strategy
- Phase 3F: Create an `audit_log_archive` table with identical schema.
- Monthly cron: `INSERT INTO audit_log_archive SELECT * FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) AND action_type IN ('LOGIN_SUCCESS','LOGOUT','SHIFT_SELECTED')`.
- Then `DELETE FROM audit_log WHERE id IN (SELECT id FROM audit_log_archive WHERE ...)`.
- **NEVER delete** `ATTENDANCE_ADD`, `ATTENDANCE_EDIT`, `ATTENDANCE_DELETE` records.

---

## SECTION 11 - EMPLOYEE MASTER MIGRATION STRATEGY (PHASE 3B)

### Current Architecture Problem
Attendance records store employee data (`name`, `id`, `post`) as raw strings in `attendance_employees`. There is no centralized employee registry. Name/ID corrections require manually updating historical records.

### Phase 3B Target Schema
```sql
CREATE TABLE employees (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(50) NOT NULL UNIQUE,  -- immutable, maps to old employee_id
    full_name     VARCHAR(150) NOT NULL,
    post          VARCHAR(50) NOT NULL,
    shift         VARCHAR(20) NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    joined_date   DATE DEFAULT NULL,
    notes         TEXT DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Backward Compatibility Rules (CRITICAL)
- **DO NOT** add a foreign key from `attendance_employees.employee_id` to `employees.employee_code` in Phase 3B.
- Old attendance records must remain standalone. They are a historical snapshot, not a live join.
- The `employee_code` in the `employees` table matches the existing `employee_id` strings - enabling optional lookup without enforcing referential integrity on old data.
- Employee deactivation (`status='inactive'`) must NOT delete or alter any historical attendance records.
- If an employee is deactivated, their name should still appear correctly in all historical previews and reports.

### Migration Rollout Plan
1. Create `employees` table (safe - additive only).
2. Populate from distinct `(employee_id, employee_name, post)` values in `attendance_employees` - data-migration script, run once.
3. Update `add-attendance.php` to show an auto-suggest/search field backed by `employees` table.
4. Auto-fill name and post when employee_code is entered (AJAX lookup).
5. Still allow manual entry for unknown employees (backward compatible).
6. Old records remain untouched. No FK enforcement on `attendance_employees`.

---

## SECTION 12 - SALARY ENGINE SAFETY ARCHITECTURE (PHASE 3D)

### Core Principles
- Salary data is FINANCIAL. It must be treated as immutable once finalized.
- Salary calculations must be based on a SNAPSHOT of attendance data, not a live join.

### Planned Schema Concepts
```
salary_months       - month lock table (year, month, shift, locked_at, locked_by)
salary_records      - per-employee calculation snapshot
salary_adjustments  - manual overrides (overtime, deductions)
```

### Rules
- A `salary_month` row with `locked=true` means NO recalculation is allowed.
- Locked months require admin re-opening (audited action).
- Salary calculations must read from a snapshot, never from live `attendance_employees` for finalized months.
- A payslip once generated must be re-generatable from the snapshot, even if the employee is deleted.
- Overtime rules and deduction rules must be stored as configuration rows, not hardcoded.
- Salary edit permission must be limited to `admin` role only - never `shift` role.

---

## SECTION 13 - FRONTEND STATE MANAGEMENT ARCHITECTURE
### localStorage Security Rule
- localStorage is convenience-only frontend state.
- Server-side validation remains the final authority.
- Never trust localStorage data without backend validation.


### localStorage Governance
| Key | Purpose | Set By | Cleared By |
|---|---|---|---|
| `previewData` | Employee list + date + shift + editId | `add-attendance.php` JS (preview/PDF buttons) | Final Save success, `?new=1` page load |

### Frontend JavaScript Safety Rules
- **Approved mutable globals:** `attendanceData[]` (in-memory employee list), `CSRF_TOKEN`, `SESSION_USER`, `ACTIVE_SHIFT`, `EDIT_DATA`. These are intentionally global — injected by PHP at page load and controlled within their respective `init*()` functions.
- **No uncontrolled globals:** Do not declare ad-hoc global variables in future JS additions. Any new state must be scoped within the relevant `init*()` function or a named namespace object.
- **Future JS modules:** When the codebase grows beyond `app.js`, use a namespaced module pattern: `window.Krystal = window.Krystal || {}; Krystal.SomeModule = (function() { ... })();`. Do not adopt ES modules (`import`/`export`) until a build step (webpack/vite) is introduced, as shared hosting does not support bundlers.
- **Event binding rule:** Never bind DOM events outside of `DOMContentLoaded` or outside the correct `init*()` function. Global listeners cause unintended behavior across pages.
- **Validation mirror rule:** Frontend constants (`VALID_POSTS`, `VALID_STATUSES`, `VALID_SHIFTS`, `MAX_NAME_LENGTH`, `MAX_ID_LENGTH`) MUST always match backend constants in `includes/functions.php`. If backend constants change, update `app.js` in the same commit.

### Invalidation Rules
1. `localStorage.removeItem('previewData')` MUST be called after successful POST to API.
2. `?new=1` in URL triggers `localStorage.removeItem('previewData')` on page load - this flag means "truly new session, discard all cache".
3. `?new=1` must ONLY come from: `dashboard.php` "Add New" link (shift user), or `select-shift.php` shift card links. NEVER from the Back button on `preview.php`.

### Cache Matching Logic (CRITICAL - DO NOT CHANGE)
In `add-attendance.php` JS:
```javascript
// Cache is valid only if:
// - New session: neither editRecordId NOR p.editId is set
// - Edit session: editRecordId matches p.editId exactly
if ((!editRecordId && !p.editId) || (editRecordId && p.editId == editRecordId))
```
This logic prevents stale cache from one editing session polluting another.

### What Previously Broke (Known Bug History)
- **Bug:** Using `?new=1` in the Back button URL from `preview.php` caused the employee list to be cleared when going back. Fixed by removing `?new=1` from the back URL in `preview.php`.
- **Bug:** Admin PDF download was losing the `editId` in localStorage, causing the cache match to fail on return. Fixed by explicitly storing `editId` in the `downloadPdfBtn` handler.
- **Rule:** Never add `?new=1` to any URL that is reachable via the "Back" navigation path.

---

## SECTION 14 - PERFORMANCE & MEMORY SAFETY RULES

### Current Limits
- Dashboard loads all records (no pagination). Safe while records < 1,000.
- `api/attendance.php GET` returns all matching records with full employee arrays. This could become slow with large datasets.

### Future Pagination Rules
- When attendance records exceed 200 per shift per year, implement server-side pagination on the dashboard API.
- Dashboard must never load more than 100 records per page.
- `attendance_employees` query for analytics must use `COUNT()` aggregates, not full row fetches.

### Export Size Limits
- CSV export is client-side from JS array - safe for up to 500 employees per record.
- PDF/Print is client-side rendered HTML - browsers handle up to ~300 table rows comfortably before print layout breaks.
- Future: If employee count per record exceeds 200, warn the user that the PDF may have multiple pages.

### Browser Rendering
- The preview page renders all 5 post-category sections unconditionally. Empty sections show "No data available". This is intentional and must not be changed.
- `renderTable()` in `app.js` re-renders the entire tbody on every change. Safe for up to 200 rows. For larger datasets, switch to virtual rendering.

---

## SECTION 15 - DISASTER RECOVERY & ROLLBACK STRATEGY

### Database Corruption Recovery
1. Stop application (rename `index.php` temporarily, redirect all traffic to maintenance page).
2. Restore from most recent `mysqldump` backup.
3. Verify `attendance_records` and `attendance_employees` row counts match expected.
4. Verify `audit_log` is intact.
5. Re-enable application.

### Code Rollback
- Keep all deployments in Git. Tag every stable release (`git tag v1.0-stable`).
- On rollback: `git checkout v1.0-stable` - upload files - verify DB schema compatibility.
- Schema changes MUST be backward compatible. Never drop columns in a rollback-sensitive phase.

### Emergency Admin Access
- If the only admin user is locked out: Use `setup.php` (temporarily unlock by removing `.setup_lock`) to re-run user seeding. Re-lock immediately after.
- Alternatively: Direct DB access via Hostinger phpMyAdmin - `UPDATE users SET password = - WHERE username = 'admin@krystal'` with a new `password_hash`.

### Migration Rollback Rules
- Every DB migration must have a documented rollback SQL.
- Phase 3B rollback: `DROP TABLE IF EXISTS employees` - safe, no FK enforced on existing tables.
- Phase 3A rollback: Session changes are PHP-only - revert `auth.php` to previous version.

---

## SECTION 16 - IMPLEMENTATION DEPENDENCY TREE

```
Phase 3A-1 (Session Security)
  +-- Prerequisites: None. Modify auth.php + app.js only.
  +-- Unlocks: Safer foundation for all future phases.
  +-- Risk: HIGH for auth.php. Test ALL login/logout/AJAX flows.

Phase 3A-2 (Centralized Config / APP_ENV)
  +-- Prerequisites: Phase 3A-1 complete and stable.
  +-- Unlocks: Production-safe deployment.
  +-- Risk: MEDIUM. Only touches config.php and .htaccess.

Phase 3B (Employee Master)
  +-- Prerequisites: Phase 3A complete. DB backup taken.
  +-- Unlocks: Phase 3C and 3D.
  +-- Risk: MEDIUM. Additive schema only. No FK on old tables.
  +-- Hidden dependency: employee_code must match existing employee_id values.

Phase 3C (History & Filtering)
  +-- Prerequisites: Phase 3B. Composite indexes added.
  +-- Risk: LOW-MEDIUM. Read-only analytics. No write path changes.

Phase 3D (Salary Engine)
  +-- Prerequisites: Phase 3B (employee profiles), Phase 3C (attendance history).
  +-- Risk: VERY HIGH. Financial data. Must have immutable snapshots.
  +-- Never implement without dedicated testing phase.

Phase 3E (Reports & Analytics)
  +-- Prerequisites: Phase 3C + Phase 3D.
  +-- Risk: LOW. Mostly read-only dashboard additions.

Phase 3F (Backup & Deployment Hardening)
  +-- Prerequisites: All above phases stable.
  +-- Risk: LOW. Infrastructure only.
```

---

## SECTION 17 - REGRESSION TESTING PROTOCOL (MANDATORY AFTER EVERY CHANGE)

Run ALL of the following after every code change before marking any task complete:

### Authentication Tests
- [ ] Login with valid credentials (shift user) - lands on dashboard, not select-shift.
- [ ] Login with valid credentials (admin) - lands on dashboard.
- [ ] Login with wrong password - shows error, no redirect.
- [ ] Access `dashboard.php` directly without login - redirects to `index.php`.
- [ ] Access `api/attendance.php` without session - returns `{success:false, error:"Unauthorized"}` with 401.

### Shift Isolation Tests
- [ ] Login as Morning shift user - can only see morning records on dashboard.
- [ ] Login as Morning shift user - try `api/attendance.php?shift=afternoon` - must return only morning records.
- [ ] Login as Morning shift user - try to save attendance with `shift:"afternoon"` - must return 403.
- [ ] Login as Morning shift user - try `add-attendance.php?edit={id_of_afternoon_record}` - must show no data or redirect.

### CRUD Flow Tests
- [ ] Add new attendance record (5 employees) - save - appears on dashboard.
- [ ] Edit existing record - change 2 employee statuses - save - dashboard shows updated data.
- [ ] Delete record - confirm dialog - record disappears from dashboard.
- [ ] Duplicate Staff ID - attempt to add same ID twice - must show error, not save.

### Preview Persistence Tests (CRITICAL)
- [ ] Add 5 employees - click Preview - verify all 5 appear on preview page.
- [ ] Click Back from preview - verify all 5 employees still in list on `add-attendance.php`.
- [ ] Add 3 employees - click Download PDF - new tab opens - prints - return to original tab - employees still in list.
- [ ] Click "Add New Attendance" from dashboard - verify `localStorage['previewData']` is cleared.
- [ ] Edit record, go to preview, click Back - verify edit state is preserved (correct `editId` match).

### Audit Log Tests
- [ ] Login - check `audit_log` table for `LOGIN_SUCCESS` row.
- [ ] Save attendance - check for `ATTENDANCE_ADD` row with correct `target_id`.
- [ ] Delete record - check for `ATTENDANCE_DELETE` row with correct date and shift.
- [ ] Login failure - check for `LOGIN_FAILURE` row with attempted username.

### Export Tests
- [ ] CSV export from `add-attendance.php` - file downloads, opens correctly in Excel.
- [ ] CSV export from `preview.php` - correct data, correct filename.

### Security Tests
- [ ] POST to `api/attendance.php` without `X-CSRF-TOKEN` header - must return 403.
- [ ] POST to `api/attendance.php` with expired/wrong token - must return 403.
- [ ] Access `includes/config.php` directly - must return 403.
- [ ] Access `logs/krystal_*.log` directly - must return 403.

---

## SECTION 18 - HIGH-RISK "NEVER BREAK" SYSTEMS (EXACT DETAIL)

### 1. Shift Isolation - `includes/functions.php::getFilteredRecords()`
**Why it's dangerous:** A single misplaced condition in the WHERE clause can expose all shifts' data to a shift user.
**Rule:** The `if ($user['role'] !== 'admin')` block must ALWAYS add `WHERE shift = -`. Adding new filter parameters must be done inside the existing conditions array, not by replacing it.
**Never do:** `$sql = 'SELECT * FROM attendance_records WHERE ...' . $extraWhere` (string concatenation bypasses the guard).

### 2. Preview Persistence - `js/app.js` Lines 281-294 (localStorage cache read)
**Why it's dangerous:** The cache-match condition `(!editRecordId && !p.editId) || (editRecordId && p.editId == editRecordId)` is exact. Changing the variable names, the comparison operator, or the logic will cause either: (a) stale cache poisoning new sessions, or (b) cache never loading, breaking the Back button.
**Rule:** Never refactor this block without re-running the full Preview Persistence test suite.

### 3. Preview Back URL - `preview.php` Lines 44-56
**Why it's dangerous:** The Back URL must NOT contain `?new=1`. Adding this flag would clear localStorage when the user navigates back, destroying their unsaved work.
**Rule:** The back URL for edit sessions is `add-attendance.php?edit={id}`. The back URL for new sessions is `add-attendance.php` (or with `?shift=...` for admin). Never append `?new=1`.

### 4. Transaction Integrity - `api/attendance.php` POST handler
**Why it's dangerous:** The save operation touches two tables. If `commit()` is not called, or if the `INSERT attendance_employees` loop is moved outside the transaction, data can become inconsistent.
**Rule:** The `beginTransaction()` - operations - `commit()` block must remain atomic. The `auditLog()` must remain AFTER `commit()`.

### 5. CSRF Token - `includes/auth.php::requireCsrf()`
**Why it's dangerous:** Removing or weakening this check opens all mutation endpoints to cross-site request forgery.
**Rule:** `requireCsrf()` must be the FIRST check after `requireLogin()` in every POST/DELETE handler. Never replace `hash_equals()` with `==`.

### 6. Audit Fail-Safe - `includes/audit.php`
**Why it's dangerous:** The entire `auditLog()` function is wrapped in `try/catch(Throwable)`. If audit logging fails, it logs a warning but does NOT block the main operation. This is intentional (fail-safe).
**Rule:** Never move `auditLog()` INSIDE the main transaction. It must remain outside and after `commit()`. Moving it inside would mean a failed audit write rolls back the user's actual data save - unacceptable.

### 7. Delete Cascade - `attendance_employees` FK
**Why it's dangerous:** The `ON DELETE CASCADE` on `attendance_employees.attendance_record_id` means deleting a parent record automatically deletes all children. This is correct and intentional.
**Rule:** Never change this to `ON DELETE SET NULL` or `ON DELETE RESTRICT`. If the cascade is changed, orphaned employee rows will accumulate and corrupt analytics.

---

## SECTION 19 - AI AGENT EXECUTION PROTOCOL (STRICT RULES)

### Before Every Task
1. **Read this entire document.**
2. **Identify which sections are affected** by the requested change.
3. **Identify all regression risks** based on Section 18.
4. **Confirm scope** - implement ONLY what was requested. Nothing extra.

### Safe Implementation Workflow
```
1. Analyze - identify affected files and functions
2. Plan - write out the change before coding
3. Implement - change ONE system at a time
4. Verify - check for PHP errors (check logs/)
5. Regression test - run Section 17 checklist for affected areas
6. Report - summarize what changed and what was tested
```

### Safe Rollback Workflow
```
1. Identify the last stable Git commit/tag
2. Revert the specific files changed
3. Verify DB schema was not changed (or reverse the migration)
4. Confirm application works before closing the task
```

### NEVER Rules for AI Agents
- **NEVER** implement Phase 3B and Phase 3A simultaneously.
- **NEVER** modify `auth.php` without re-testing all authentication flows.
- **NEVER** modify the localStorage cache-match logic without running the full Preview test suite.
- **NEVER** add `?new=1` to any URL reachable from preview.php Back navigation.
- **NEVER** remove `requireCsrf()` from any POST/DELETE endpoint.
- **NEVER** move `auditLog()` inside a database transaction.
- **NEVER** use string concatenation to build SQL WHERE clauses.
- **NEVER** return raw PHP errors, stack traces, or DB credentials to the browser.
- **NEVER** deploy a phase without checking the browser console for JS errors.
- **NEVER** skip testing with both admin and shift users.
- **ALWAYS** test the preview - back - edit - save full loop after any persistence change.
- **ALWAYS** check that shift isolation still works after any change to attendance queries.
- **ALWAYS** verify the audit_log table has correct entries after any CRUD operation.

---

## SECTION 20 - CURRENT PROJECT STATUS & READINESS

### Completed Phases
| Phase | Status | Notes |
|---|---|---|
| Core DB Setup | ✅ Complete | users, attendance_records, attendance_employees |
| Auth System | ✅ Complete | Login, logout, session, CSRF |
| Shift Isolation | ✅ Complete | Role-based data filtering |
| CRUD Engine | ✅ Complete | Add, edit, delete with transactions |
| Preview System | ✅ Complete | localStorage persistence, back-button safe |
| PDF/Print | ✅ Complete | Client-side print via preview.php |
| CSV Export | ✅ Complete | Client-side Blob download |
| Audit Trail | ✅ Complete | All sensitive actions logged |
| Error Handling | ✅ Complete | Production-safe, no stack traces |
| Audit Log Viewer | ✅ Complete | Admin-only, paginated, filterable |
| Security Hardening | ✅ Complete | CSRF, prepared statements, .htaccess |

### Architecture Maturity Score: 8.5/10
- Strong foundation. Security-first. Audit-complete. Transaction-safe.
- Missing: session timeouts, config externalization, employee master, salary engine.
- Production-ready for current scope. Ready to begin Phase 3A.

### Recommended Next Steps (In Order)
1. **Phase 3A-1:** Implement idle + absolute session timeout. Modify only `includes/auth.php` and `js/app.js`.
2. **Phase 3A-2:** Externalize config. Introduce `config.local.php` pattern. Add `.md` files to `.htaccess` deny list.
3. **Phase 3A-3:** Access guard cleanup. Add security headers to `.htaccess`.
4. **Then seek user approval before Phase 3B.**
