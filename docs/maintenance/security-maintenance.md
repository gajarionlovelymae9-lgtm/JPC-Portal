# JPC Portal — Security Maintenance

How JPC Portal keeps its security posture current. Baseline policy lives in
[`../SECURITY.md`](../SECURITY.md) and [`../DATA_PRIVACY.md`](../DATA_PRIVACY.md).

## Standing posture (verified at Phase 12/13 handover)

- No tracked secrets: `secrets.php`, API keys, and production database
  passwords are placeholders / documented demo-only values.
- No `dangerouslySetInnerHTML` in the React app.
- No `exec`/`shell_exec`/`system`/`passthru`/`proc_open` in application code.
- Rows in `config` keyed from `.env`; health endpoints never echo values.
- RBAC matrix in `app/Enums/Permissions.php`; routes enforce `role:` or
  `permission:` middleware; administrators manage roles via `/admin/rbac`.

## Monitoring signals

The health dashboard exposes **redacted** counters: auth failures today,
security-event volume (failed logins + audit writes), and recent critical
errors. Spikes usually warrant investigation (see incident response).

## Patch cadence

- Run `composer outdated` and `npm audit` (or `npm outdated`) monthly (see
  [`monthly-checklist.md`](monthly-checklist.md)).
- Treat Laravel framework updates as a **release candidate**: patch in a copy,
  run the full backend + frontend suites, then deploy per
  [`release-process.md`](release-process.md).
- Do **not** upgrade Laravel 12 → 13 for this capstone release; v1.0.0 remains
  on Laravel 12 / PHP 8.2 as documented in VERSION.
- Patch **requests**, **symfony**, and other transitive deps via
  `composer update <pkg>` only after `composer test`/`php artisan test` passes
  in a scratch checkout.

## Credential hygiene

- `.env` values are never committed; regenerate `APP_KEY` on a fresh server.
- Demo accounts (admin/hr/departmenthead/employee@jpc.local) are dev-only and
  must be disabled or re-seeded before production.
- Any leaked key → rotate immediately, log a `Security` maintenance record,
  and file a backlog entry.

## Auditing

- All maintenance actions are written via `ActivityLogger` → `activity_logs`
  (activity types: `maintenance.record`, `feedback.submit`, `feedback.update`,
  `ai.feedback`).
- `audit_logs` records domain state changes; neither table may contain
  credentials or secrets.
- Admin feedback management and health access are strictly `role:admin`;
  regular users only submit feedback, never read others' entries (`/feedback/mine`
  is scoped to the caller, verified by tests).

## Performing a security touch

1. `composer audit` (backend dependencies).
2. `npm audit --omit=dev` in `frontend/`.
3. `git status` to confirm no stray/secret files before a deploy.
4. Re-run the redaction checks in `docs/completion-report-phase12.md`.
5. Record: `php artisan system:record-maintenance "Security Patch" ...`

## Related

- Incident response: [`incident-response.md`](incident-response.md)
- Release process: [`release-process.md`](release-process.md)
- Monthly checklist: [`monthly-checklist.md`](monthly-checklist.md)