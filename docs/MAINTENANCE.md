# JPC Portal — Maintenance Plan

Recommended ongoing maintenance activities for a healthy JPC Portal deployment.

## Daily

- [ ] Check application errors — `backend/storage/logs/laravel-*.log` for new
      errors/warnings; backend health via `GET /api/v1/health`.
- [ ] Check failed queues — `php artisan queue:failed` (backlog; retry or purge).
- [ ] Check critical notifications — review `notification_logs` for failed high-
      priority deliveries (deadline reminders, review decisions).

## Weekly

- [ ] Review logs — frontend/backend, AI/OCR failures, throttling hits.
- [ ] Review storage — private `storage/app/private` growth (reports, exports);
      confirm `reports:cleanup-exports` removes expired exports.
- [ ] Review failed AI/OCR jobs — retry or investigate recurring failures.
- [ ] Verify backups exist and are non-empty (see [`BACKUP_AND_RESTORE.md`](BACKUP_AND_RESTORE.md)).

## Monthly

- [ ] Verify backup restoration — restore the latest backup into
      `acadtrack_db_verify` and compare table counts (see BACKUP_AND_RESTORE.md §5).
- [ ] Review performance — slow queries/pages; check analytics query time; review
      `report_exports` volume and rating endpoints.
- [ ] Review database growth — disk usage of `acadtrack_db`; prune obsolete data by
      policy.
- [ ] Review audit logs — spot-check sensitive actions (employee edits, approvals,
      export activity) for anomalies.
- [ ] Review data-quality trends — resolved vs open issues across months.

## Before major changes

- [ ] Back up the database and private files (BACKUP_AND_RESTORE.md).
- [ ] Run the full test suite: `php artisan test` (+ frontend `npm test`, `npm run
      lint`, `npm run build`).
- [ ] Review the migration (`php artisan migrate:status` before/after); never edit
      an applied migration.
- [ ] Test on staging first (`APP_ENV=staging`), where possible.
- [ ] Document changes in `docs/` (handover, deployment, changelog).

## Upgrade / patch workflow

1. `composer update` (backend) / `npm update` (frontend) on a copy.
2. Run tests; fix regressions.
3. Backup → deploy → `php artisan migrate --force` → `php artisan optimize` →
   restart worker → verify health + smoke login.
4. Frontend: `npm run build` and swap the static build.

## Monitoring suggestions

- Health: poll `GET /api/v1/health` every minute.
- Queue depth: `SELECT COUNT(*) FROM jobs;` / check `failed_jobs` count.
- Scheduler: confirm the last `schedule:run` executed (Laravel log or a sentinel).
- Error log tailing + retention (daily log channel already rotates).
- DB size: information_schema stats monthly.

## Incident response

1. Logs first (`storage/logs`, queue failed jobs, mail logs).
2. Check services: PHP `php artisan about`, MySQL status in XAMPP, queue worker.
3. See [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md) for the checklist.
4. For data loss → follow the Restore Procedure (BACKUP_AND_RESTORE.md) into a
   recovery DB first.