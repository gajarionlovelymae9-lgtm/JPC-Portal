# JPC Portal — Troubleshooting

Quick diagnostic ladder. The long-form checklist lives in
[`../TROUBLESHOOTING.md`](../TROUBLESHOOTING.md); this page maps symptoms to
the Phase 13 monitoring endpoints.

## Ladder

1. **Is the app up?** `GET /api/v1/health` → `{ success: true }`.
2. **Is a component degraded?** `GET /api/v1/admin/system-health` (admin) or
   `php artisan system:check-health` from the server.
3. **Read today's errors** — `backend/storage/logs/laravel-YYYY-MM-DD.log`
   (the dashboard's "Recent Critical Errors" shows redacted criticals).
4. **Check the queue** — `php artisan queue:failed`; retry with
   `php artisan queue:retry all`.
5. **Check the scheduler** — `php artisan schedule:list` (confirms registration);
   ensure cron/`schedule:work` is actually running.

## Symptom → fix

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| 401 on every request | expired/missing session cookie | log out and back in; confirm `SANCTUM_STATEFUL_DOMAINS` |
| 403 on admin pages | RBAC (role:admin route) | confirm role via `/api/v1/auth/me`; admin routes are role-gated |
| 422 on feedback POST | invalid `category`/`verdict` | use values from `UserFeedback::CATEGORIES` / `AiFeedback::VERDICTS` |
| Reports stuck "processing" | OCR job failed | `/admin/ai/ocr` → retry; OCR failure never invalidates a report |
| AI answers degraded | provider outage | `AI_PROVIDER=fake` keeps the system usable offline |
| Export never arrives | export job failed/TTL expired | `queue:failed`; regenerate from `/reporting/exports` |
| Health says "degraded" | threshold warning (see checks map) | inspect the listed `component.check` (e.g. `ai.failure_rate`) |
| Backup check fails | no verified restore logged | follow [`backup-restore.md`](backup-restore.md) |
| Disk warning | < 500 MB free | run `reports:cleanup-exports`; clear old logs/backups |

## Reading `system_health_checks`

```sql
SELECT id, status, source, checked_at FROM system_health_checks
ORDER BY checked_at DESC LIMIT 20;
```

`checks`/`metrics` JSON hold the per-component detail at check time.

## Still stuck

- Backend: `php artisan about`, `php artisan route:list`, `php artisan migrate:status`.
- Frontend: `npm run build` locally to catch TS errors; check browser console.
- Open an incident: [`incident-response.md`](incident-response.md).
- File a bug: [`../BACKLOG.md`](../BACKLOG.md).

## Related

- Deployment checks: [`../DEPLOYMENT.md`](../DEPLOYMENT.md)
- Admin guide: [`../ADMIN_GUIDE.md`](../ADMIN_GUIDE.md)