# JPC Portal — Incident Response

A practical runbook for production issues. Every incident ends with a logged
maintenance record so the trail is auditable.

> v1.0.0 operational view: see the consolidated
> [`../operations/incident-response.md`](../operations/incident-response.md)
> (P0–P3 classification, security/data/AI/OCR incident procedures).

## Lifecycle

1. **Detect** — health dashboard (`/admin/system-health`), scheduled
   `system:check-health` failure, user report via `/feedback`, or alert on
   `failed_jobs`.
2. **Classify** — pick a severity:
   - **CRITICAL** — system down, data loss suspected, auth/RBAC bypass,
     exposed secrets. Stop the line.
   - **HIGH** — core flows broken (cannot submit/approve reports, queue stuck).
   - **MEDIUM** — degraded (AI/OCR failures, slow queries, one feature broken).
   - **LOW** — cosmetic / isolated, no work blocked.
3. **Contain** — stop the bleeding: retry or purge failed jobs
   (`php artisan queue:retry all` / `queue:flush`), disable a bad provider
   (set `AI_PROVIDER=fake` / `OCR_PROVIDER=fake`), or block a route in the
   reverse proxy if actively exploited.
4. **Investigate** — logs first:
   ```bash
   cd backend
   tail -n 200 storage/logs/laravel-$(date +%F).log
   php artisan queue:failed
   php artisan about
   ```
   Cross-check `system_health_checks` history and recent `activity_logs`.
5. **Recover** — apply the fix (config change, code patch, restore from
   backup if data was corrupted). Backup/restore: [`backup-restore.md`](backup-restore.md).
6. **Validate** — rerun `php artisan system:check-health`, smoke-test
   login → dashboard → report workflow, run `php artisan test` if code changed.
7. **Document** — record it:
   ```bash
   php artisan system:record-maintenance "Incident Response" \
     "CRITICAL: OCR provider outage; switched OCR_PROVIDER=fake; reports still submitted." \
     --module="OCR" --result=completed --rollback-required=false \
     --notes="Root cause: provider quota exceeded."
   ```
   For user-visible issues, update the originating feedback item to
   `RESOLVED` in `/admin/feedback`.

## Common first responses

| Symptom | First action |
| --- | --- |
| Site 500s | `tail` today's log; check `php artisan about`; restart PHP/queue worker |
| Jobs stuck | `php artisan queue:work --once`; inspect `jobs` / `failed_jobs` tables |
| AI failing | `GET /admin/system-health/ai`; flip `AI_PROVIDER=fake` (system stays usable) |
| OCR failing | `GET /admin/system-health/ocr`; OCR outage must NOT invalidate reports |
| Disk full | `reports:cleanup-exports`; prune `storage/app/private`; check export TTL |
| Suspected breach | rotate credentials, review `login_activities` failed spikes + `audit_logs` |

## Communication

- Use in-app announcements (admin) for user-visible outages.
- Update the originating feedback thread in `/admin/feedback`.

## After the incident

- File a `Bug` entry in the [`BACKLOG.md`](../BACKLOG.md) if a code fix is owed.
- If a runbook step was missing, patch the relevant `docs/maintenance/*.md`.

## Related

- Troubleshooting checklist: [`troubleshooting.md`](troubleshooting.md)
- Security: [`security-maintenance.md`](security-maintenance.md)
- Existing plan: [`../MAINTENANCE.md`](../MAINTENANCE.md)