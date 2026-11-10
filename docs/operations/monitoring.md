# JPC Portal — Operations: Monitoring

Operational monitoring reference for JPC Portal v1.0.0 (Phase 16). This is the
**summary view** of what is monitored, where the signals live, and how to read
them. Detailed deep-dives live under [`../maintenance/`](../maintenance/)
(reused — no duplicate systems were built).

## 1. Health model

Every component reports one of these operational states. A status is only ever
reported as `Healthy` when the check actually ran and passed — the system never
reports healthy without checking.

| Operational status | Meaning | System source |
| --- | --- | --- |
| `HEALTHY` | Check ran and passed (status `ok`) | component `status: ok` |
| `WARNING` | Degraded but serving (status `degraded`) | threshold crossed (see §3) |
| `DEGRADED` | One or more components in warning/error but system usable | aggregate `status: degraded` |
| `CRITICAL` | Component check failed (`error`) or system-down condition | aggregate `status: error` (label `Error`) |
| `OFFLINE` | No response / provider not reachable | component check failure (e.g. DB connection error) |
| `UNKNOWN` | State cannot be determined (check itself threw) | health service error path |

Aggregation logic (existing `SystemHealthService::check()`):

- one or more `error` → aggregate `degraded`
- only `degraded` components → aggregate `degraded`
- all `ok` → aggregate `ok`

> Mapping note: the backend internally uses `ok` / `degraded` / `error`; the
> dashboard labels them `Healthy` / `Degraded` / `Error` (`SystemMonitoringService::label`).

## 2. Where monitoring lives (reused, not recreated)

| Area | Endpoint / command | Backing service |
| --- | --- | --- |
| Public health | `GET /api/v1/health` | `HealthController` (app/db/api, no secrets) |
| Full health (admin) | `GET /api/v1/admin/system-health` | `SystemHealthService` + `SystemMonitoringService` |
| Dashboard strip | `GET /api/v1/admin/system-health/dashboard` | `SystemMonitoringService::dashboard()` |
| Database | `GET /api/v1/admin/system-health/database` | `DatabaseHealthService` |
| Queue | `GET /api/v1/admin/system-health/queue` | `QueueHealthService` |
| Storage | `GET /api/v1/admin/system-health/storage` | `StorageHealthService` |
| AI | `GET /api/v1/admin/system-health/ai` | `AiHealthService` |
| OCR | `GET /api/v1/admin/system-health/ocr` | `OcrHealthService` |
| History | `GET /api/v1/admin/system-health/history` | `SystemMonitoringService::history()` |
| Admin maintenance log | `GET/POST /api/v1/admin/maintenance/logs` | `MaintenanceLogService` |
| Admin notification metrics | `GET /api/v1/admin/notifications/metrics` | `AdminNotificationController` |
| CLI health run | `php artisan system:check-health --source=manual` | `SystemHealthCheckCommand` |
| CLI backup verify | `php artisan system:verify-backups --days=7` | `VerifyBackupsCommand` |
| CLI maintenance record | `php artisan system:record-maintenance …` | `RecordMaintenanceCommand` |
| Admin UI | `/admin/system-health`, `/admin/maintenance`, `/admin/ai`, `/admin/ai/ocr` | frontend pages |

All operations endpoints are behind `role:admin` (401 unauthenticated / 403
non-admin — verified by `SystemMaintenanceApiTest`). The public health endpoint
returns aggregate statuses only.

## 3. Thresholds (existing)

| Component | Warning condition | Source |
| --- | --- | --- |
| Application | > 20 failed logins today; > 50 log exceptions today; > 10 failed notifications today | `ApplicationHealthService` |
| Database | any orphan records found; any pending migrations | `DatabaseHealthService` |
| Queue | failed jobs today > 0; pending backlog > 100 | `QueueHealthService` |
| Storage | free space < 500 MB; private/exports disk unreachable | `StorageHealthService` |
| AI | failure rate > 20% | `AiHealthService` |
| OCR | failure rate > 20%; any low-confidence (below min_confidence) | `OcrHealthService` |

## 4. Scheduled monitoring (existing scheduler in `routes/console.php`)

| Schedule | Task | Purpose |
| --- | --- | --- |
| Daily 05:00 | `system:check-health` | full scan, persists `system_health_checks` |
| Daily 05:30 | `system:verify-backups` | backup freshness + restore-tested verdict |
| Daily 03:15 | `reports:cleanup-exports` | export TTL cleanup |
| Daily 07:00 / 07:30 | `tasks:send-deadline-reminders` / `tasks:send-overdue-notifications` | notifications |
| Weekly Mon 06:30 | `RunDataQualityScanJob('full',…)` | full data-quality scan |
| Monthly 1st 06:00 | `reports:generate-monthly` | monthly management report |

Scheduler enabled by cron (`php artisan schedule:run` every minute) in
production, or `php artisan schedule:work` locally.

## 5. Application & API health

- `GET /api/v1/health` → `application: ok`, `database: ok/error`, `api: ok`.
- Login failures, exceptions and failed notifications tracked via
  `ApplicationHealthService` (counts only — no log contents exposed).

## 6. Database health

- Connectivity (`SELECT 1`), database size, table count, pending migrations,
  orphan-record scan (reports/versions/files/ocr/ai/tasks/feedback/maintenance).
- Read-only checks; run on demand or via the daily scheduled health scan.
- Migrations drift is a `warning` when a migration file is not yet applied.

## 7. Queue health & failed jobs

Queue monitoring uses the existing Laravel **database** queue
(`jobs` + `failed_jobs`).

- Pending backlog, failed today /this week / total, retries available.
- Failed jobs are classified by type (ocr / ai / notification / export / other).

### Failed-job procedure (§10)

For each failure record, capture (do not expose private employee data beyond
the minimum needed):

| Field | How to get it |
| --- | --- |
| Job class / id | `php artisan queue:failed` |
| Date & time | `failed_jobs.failed_at` |
| Employee/Report context | `failed_jobs.payload` (internal only) or the originating report/notification table |
| Error | payload `error` / `exception` (logs) |
| Retry count | `jobs.attempts` or the job's `tries`/backoff config |
| Status | `failed_jobs` row state |
| Resolution | logged in the maintenance log / feedback thread |

Workflow:

1. `php artisan queue:failed` — list.
2. Investigate before retrying. **Do not repeatedly retry a permanently invalid
   job** without diagnosing root cause.
3. Fix root cause (e.g. provider creds, file missing, invalid payload on one
   report) then `php artisan queue:retry all` (or `queue:retry <id>`).
4. Purge with `php artisan queue:flush` only when certain the job is
   permanently invalid (document why in the maintenance log).
5. Record the action in the maintenance log:
   `php artisan system:record-maintenance "Incident Response" "<job> failed" --module=Queue --result=<result> --performed-by=<email>`.

> Long-lived workers must be **restarted** after a deploy to pick up new code:
> stop the `queue:work` process and start it again (systemd/supervisor restart).

## 8. AI monitoring

Existing `AiHealthService` reads `ai_analyses` (never duplicated records):
requests, success, failure, pending, timeouts, provider/model, average duration,
failure-rate percent. A failing provider → `degraded`; the rest of the system
keeps working. Sensitive content (prompts, report contents, keys) is never
logged or exposed — only aggregate counts.

## 9. OCR monitoring

Existing `OcrHealthService` reads `ocr_processings`: queued / processing /
completed / failed, low-confidence count, failure rate, average duration.
OCR failure never invalidates a report; only the OCR indicator degrades.

## 10. Notification monitoring

- Failed deliveries today → application component warning.
- Admin metrics: `GET /api/v1/admin/notifications/metrics`.
- Delivery log: `GET /api/v1/admin/notifications/logs`; per-log retry
  `POST /api/v1/admin/notifications/{notificationLog}/retry`.
- Notification content is never exposed in public logs; only statuses/counts.

## 11. Storage monitoring

Existing `StorageHealthService`: private disk reachability, exports disk
writability, free space (< 500 MB warning), export backlog bytes. Real paths:
`storage/app/private/private/reports` (double `private` nesting — see
[`../BACKUP_AND_RESTORE.md`](../BACKUP_AND_RESTORE.md)),
`storage/app/private/report_exports`.

Cleanup follows the retention policy — never auto-delete employee files just
because they seem unused.

## 12. Log monitoring (§15)

Location: `backend/storage/logs/laravel-*.log` (daily channel).

Look for, and investigate before dismissing:

- repeated exceptions (`ERROR`/`CRITICAL`/`EMERGENCY`)
- authentication failures (`login_activities.event = 'failed'` spikes)
- authorization failures (403 audit)
- database failures (connection errors)
- queue failures (`failed_jobs`)
- AI failures (`ai_analyses.status = 'failed'`)
- OCR failures (`ocr_processings.status = 'failed'`)
- filesystem failures (storage errors)

Health dashboard surfaces the last 5 `CRITICAL`/`EMERGENCY` lines with keys
redacted. **Do not delete logs to hide errors** — retain per retention policy.

## 13. Performance monitoring (§22)

Existing metrics: database size/time, AI average duration, OCR average
duration, export backlog, storage free space. Add per-request timing where
useful (logs / profiler). Investigate **sustained** degradation — do not
optimize from a single slow request.

## 14. Health-check security (§33)

- Public `GET /api/v1/health` → aggregate statuses only; never credentials,
  DB names beyond config default, keys, paths, employee data, or report
  contents.
- Detailed health/queue/storage/AI/OCR/metrics → `role:admin` only.
- Dashboard shows only counts + redacted error strings.

## 15. Alerts (§35) — where supported

| Alert | Source to watch | Expected action |
| --- | --- | --- |
| Critical system failure | log `CRITICAL`/`EMERGENCY`, dashboard recent errors | incident response (§29) |
| Database unavailable | `health/database` error, `jobs`/`failed_jobs` cascade | P0 response |
| Queue failure | `failed_jobs` growth, backlog > 100 | investigate + retry |
| Large failed-job backlog | `failed_jobs` count / `metrics.failed_by_type` | triage by type |
| AI provider failure | `health/ai` failure-rate > 20% | flip to `fake` if needed |
| OCR provider failure | `health/ocr` failure-rate > 20% | keep workflow usable; fix provider |
| Backup failure | `system:verify-backups` non-zero | backup/recovery procedure |
| Storage problem | `health/storage` free-space warning / unreachable | prune TTL exports, expand disk |
| Security incident | login-activity spikes, 403 audit bumps | security incident procedure |

Avoid alert spam: escalate only on sustained/exceeding conditions, alert on
thresholds once per window, prefer aggregates over per-job alerts.

## Related

- [`incident-response.md`](incident-response.md) — response runbooks.
- [`backup-recovery.md`](backup-recovery.md) — backup & restore.
- [`maintenance.md`](maintenance.md) — routine maintenance schedule.
- [`../maintenance/monitoring.md`](../maintenance/monitoring.md) and the
  per-area runbooks under [`../maintenance/`](../maintenance/) for deep dives.