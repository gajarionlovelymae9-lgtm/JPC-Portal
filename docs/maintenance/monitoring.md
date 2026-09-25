# JPC Portal — Maintenance Monitoring Guide

How to monitor a running JPC Portal deployment (Phase 13).

## What the system monitors

| Area | Where | Command / Endpoint |
| --- | --- | --- |
| Application | logs, auth failures, notifications | `GET /api/v1/admin/system-health` (admin) |
| Database | connection, size, orphan checks, migrations | `GET /api/v1/admin/system-health/database` |
| Queue | pending jobs, failed jobs (today/week/total, by type) | `GET /api/v1/admin/system-health/queue` |
| Storage | private disk reachability, free space, export files | `GET /api/v1/admin/system-health/storage` |
| AI provider | success/failure, avg duration, failure-rate threshold (20%) | `GET /api/v1/admin/system-health/ai` |
| OCR provider | queued/processing/completed/failed, low-confidence count | `GET /api/v1/admin/system-health/ocr` |
| Notifications | failed deliveries today | shown under Application status |
| Last backup | most recent `Database Backup` maintenance log | shown on the dashboard |

All health responses are aggregate counts and statuses only. Credentials,
API keys, prompts, and report contents are never returned or logged.

## Health dashboard (admin UI)

- Route: `/admin/system-health` (admin role only).
- Shows the component status strip, counters (failed jobs, API errors,
  AI/OCR failures, security events, auth failures, storage warnings),
  recent critical errors (redacted), and check history.
- Deep-dive AI/OCR job monitoring lives in `/admin/ai` and `/admin/ai/ocr`
  (existing Admin AI Monitoring pages — reused, not duplicated).

## CLI monitoring

```bash
cd backend

# Run a full health check now and persist a system_health_checks row
php artisan system:check-health --source=manual

# Verify a fresh, restore-tested backup exists
php artisan system:verify-backups --days=7

# Queue depth and failures
php artisan queue:failed
php artisan queue:monitor

# Application overview (version, cache, DB driver)
php artisan about
```

## Scheduled monitoring (automatic)

Registered in `backend/routes/console.php`:

| Schedule | Task |
| --- | --- |
| Daily 05:00 | `system:check-health` (records `system_health_checks`) |
| Daily 05:30 | `system:verify-backups` (fails loudly if backup is stale/unverified) |
| Weekly Mon 06:30 | `RunDataQualityScanJob` (full data-quality scan) |
| Daily 07:00 / 07:30 | existing deadline / overdue notification senders |
| Daily 03:15 | `reports:cleanup-exports` (export TTL cleanup) |
| Monthly 1st 06:00 | `reports:generate-monthly` |

> Scheduler requires a cron entry on Linux:
> `* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1`
> On the XAMPP/Windows dev setup, run `php artisan schedule:work` manually.

## Reading health status

- `ok` / `Healthy` — checks passed.
- `degraded` / `Degraded` — warnings crossed (e.g. failure rate > 20%,
  free disk < 500 MB, orphan rows found, > 50 exceptions today).
- `error` / `Error` — a component check itself failed; investigate immediately.

A degraded system still serves users: OCR/AI outages never block report
submission, and reports are never marked invalid because OCR is unavailable.

## Related

- Full maintenance plan: [`../MAINTENANCE.md`](../MAINTENANCE.md)
- Health history: `GET /api/v1/admin/system-health/history`
- Backup/restore: [`backup-restore.md`](backup-restore.md)
- Incident response: [`incident-response.md`](incident-response.md)