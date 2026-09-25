# JPC Portal — Monthly Maintenance Checklist

A single sheet to run against the JPC Portal on the first working day of each
month. Uses the available monitoring instead of guessing.

## Health & monitoring

- [ ] `php artisan system:check-health` → status `ok` (any `degraded` warnable
      item is reviewed).
- [ ] Review `system_health_checks` history in `/admin/system-health` for trends.
- [ ] Review failed jobs: `php artisan queue:failed` — retry or purge; then
      `php artisan queue:flush` if resolved.
- [ ] Review today's critical errors in the dashboard; check logs for anything
      redacted that needs a ticket.

## Backup & restore

- [ ] `php artisan system:verify-backups --days=7` → passes (fresh + verified
      restore on record). If not, follow [`backup-restore.md`](backup-restore.md).
- [ ] Perform one full restore test this month into `acadtrack_db_verify`, then
      record `Database Restore` — proves the dump actually works.

## Database

- [ ] `GET /api/v1/admin/system-health/database` → connection ok, orphans 0,
      no pending migrations.
- [ ] Review table sizes (information_schema) — prune per policy
      ([`database-maintenance.md`](database-maintenance.md)).
- [ ] Review data-quality scan resolutions since last month.

## Storage & files

- [ ] Health shows storage `ok` (free space above the 500 MB warning).
- [ ] `reports:cleanup-exports` ran; stale export files are gone.

## AI / OCR

- [ ] `/admin/system-health/ai` and `/admin/system-health/ocr` are `ok`
      (failure rate below 20%); failure counts equal provider reality.
- [ ] Review `ai_feedback` verdicts (Correct vs Incorrect share); add a new
      `ai_prompt_versions` row + backlog entry if quality regressed.
- [ ] Retry or document any residual failed AI/OCR jobs.

## Security

- [ ] `composer audit` and `npm audit --omit=dev` show no known criticals.
  (Backend is Laravel 12/PHP 8.2 pinned — no framework-13 upgrade.)
- [ ] Spot-check `activity_logs` for `maintenance.*` entries this month; confirm
      no secrets in `*.log`.
- [ ] Confirm auth-failure count is normal; investigate spikes
      (`login_activities.event='failed'`).

## Feedback & backlog

- [ ] Review `/admin/feedback` — move OPEN items to UNDER_REVIEW/IN_PROGRESS;
      assign; close RESOLVED/REJECTED with resolutions.
- [ ] Sweep [`../BACKLOG.md`](../BACKLOG.md) — new items from this month's
      incidents and feedback; update resolutions.

## Release hygiene

- [ ] If code changed this month: full backend + frontend suites, build,
      CHANGELOG updated, `VERSION` consistent, maintenance log recorded.

## Record the check

```bash
cd backend
php artisan system:record-maintenance \
  "Monthly Maintenance" \
  "Monthly checklist completed; all items checked. See docs/maintenance/monthly-checklist.md." \
  --module="System" --result=completed --performed-by=admin@jpc.local
```