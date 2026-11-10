# JPC Portal — Security Documentation

## 1. Authentication

- **Mechanism:** Laravel Sanctum **SPA cookie authentication** (stateful sessions),
  not bearer tokens stored in browser storage.
- **Flow:** `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/login` → session cookie +
  CSRF token; `GET /api/v1/auth/me` for the profile; `POST /api/v1/auth/logout`.
- **Password:** hashed (bcrypt, `BCRYPT_ROUNDS=12` in `.env`, `4` in tests); never
  stored or logged in plain text.
- **Login hardening:** rate limiting (`login` attempts limited — see `AuthTest`), account
  status checks (suspended/inactive accounts are rejected), login activity logging
  (`login_activities` table with IP/user agent).
- **Session:** `SESSION_DRIVER=database`, `SESSION_ENCRYPT=false` (the cookie value is
  protected by `APP_KEY`), `SESSION_LIFETIME` configurable.
- **Stateful domains:** `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` list
  only the SPA origins (localhost:5173 in dev). Cross-origin cookie requests are not
  accepted.

## 2. RBAC (roles & permissions)

- **Roles:** `admin`, `hr`, `department_head`, `employee`.
- **Permissions:** a permission catalog (`permissions`) granted to roles via
  `permission_role` (`dashboard.view`, `employee.view`, `report.review`,
  `analytics.view`, `reports.export`, ...).
- **Enforcement layers:**
  1. Route middleware: `role:admin`, `permission:<key>`, throttle groups.
  2. Policies (`app/Policies/*`) for object-level checks.
  3. Service-level scoping (e.g. `ResolvesReportingScope` limits reporting/analytics
     results to the caller's own department/employee).
- **Analytics & management summary** restricted to admin/HR/department-head; employees
  have self-service-only routes.

## 3. Authorization & IDOR protection

- Every endpoint resolves the authenticated user and validates ownership/scope
  server-side (never trusts IDs sent by the client).
- In-service IDOR is explicitly tested: employee A cannot read employee B's report,
  performance, or export by changing an ID.
- File download endpoints re-check ownership/permission on every request.

## 4. File security

- Uploaded report documents are stored on the **private** `local` disk
  (`storage/app/private/private/reports` — the double `private` nesting is
  expected: uploads write under `private/reports/...` on the `local` disk
  rooted at `storage/app/private`) — **no public symlink, no public URL**.
- Download only through authenticated, authorized controllers (ownership + permission
  checked per request).
- Upload validation: mime type/extension allow-list, size limit
  (`REPORT_MAX_FILE_SIZE_KB`), stored filename is regenerated (no user-controlled path),
  checksum recorded.
- Export files live under `storage/app/private/report_exports` with a TTL and are
  cleaned by `reports:cleanup-exports`.

## 5. API security

- All responses use the standard envelope; errors never leak stack traces, SQL,
  secrets or filesystem paths (only when `APP_DEBUG=true`, which is **false** in
  production).
- Route groups are permission-gated; unauthenticated requests return 401/403.
- Throttling on sensitive groups (`throttle:reports`, `throttle:reports-export`,
  login throttle).
- Health endpoint exposes status only — never credentials/keys/paths.

## 6. XSS protection

- React escapes interpolated values by default; no `dangerouslySetInnerHTML` for
  untrusted content (report content/AI output is rendered as text).
- Backend returns JSON with correct `Content-Type`; no server-side HTML echo of user
  input.

## 7. SQL injection protection

- All queries use Eloquent/`DB` query builders with **parameter binding**; no string
  concatenation of user input into SQL.
- Foreign keys and enum values are validated before use.

## 8. CSRF strategy

- Sanctum SPA cookie auth uses the **CSRF token** (`X-XSRF-TOKEN`) obtained from
  `/sanctum/csrf-cookie`; stateful domains restrict cross-site session use.
- `SANCTUM_STATEFUL_DOMAINS`/`CORS_ALLOWED_ORIGINS` ensure cookies are only honored
  for the SPA origin.

## 9. Secret management

- `.env` is **never committed** (root and `backend/.gitignore` cover `.env`,
  `.env.local`, `.env.backup`, `.env.production`, plus credential patterns:
  `*.pem`, `*.key`, `*.p12`, `*.pfx`, `*.jks`, `*.crt`, `credentials*.json`,
  `*service-account*.json`, `auth.json`, tokens).
- `.env.example` contains **placeholders only** (no real secrets).
- AI keys (`AI_API_KEY`), Google Vision credentials (`GOOGLE_VISION_API_KEY`,
  `GOOGLE_APPLICATION_CREDENTIALS`), DB passwords, SMTP credentials live only in the
  server `.env` or a path outside the repo.
- If a secret is ever committed: rotate it immediately, remove it from history where
  appropriate, regenerate, and document — **do not merely delete the line**.

## 10. AI prompt-injection defense

- User/report text is passed to the model as **data**, never as system instructions;
  a fixed system prompt is defined server-side (`app/Services/AI/Prompts/*`).
- AI **tool calls are authorized** per-user/per-role (`AiAuthorizationService`) and
  scoped to the caller's data before execution — the model cannot request
  unauthorized records.
- AI output is validated (`AiResponseValidator`) before display; it is treated as
  assistive text, never as an action command.
- AI cannot bypass RBAC or mutate authoritative data — it reads authorized data and
  produces text/findings only.

## 11. Audit logging

- Sensitive mutations are recorded in `audit_logs` (user, action, entity, old/new
  values) via the centralized audit service.
- `activity_logs` records broad activity; `login_activities` records auth events.
- Logs do **not** contain passwords, API keys, or raw file contents.

## 12. Secure downloads

- Private files are streamed through an authenticated route (ownership/permission
  checked), with the correct filename/content-type; no signed public URLs are
  exposed for report documents.

## 13. Privacy controls

- Only information required by the system is collected/exposed.
- Role-based scoping limits what each user can see (see [`DATA_PRIVACY.md`](DATA_PRIVACY.md)).

## 14. Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`.
- Run `php artisan optimize` (config/route cache).
- HTTPS enabled; correct `APP_URL`, `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`.
- Strong DB/SMTP/AI credentials in `.env` (file permissions restricted).
- Queue worker + scheduler running; logging enabled (`LOG_CHANNEL=daily`).
- Regular backups + tested restore ([`BACKUP_AND_RESTORE.md`](BACKUP_AND_RESTORE.md)).