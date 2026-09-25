# JPC Portal — Database Maintenance

Routine and corrective care for the MySQL database (`acadtrack_db`).

## Health checks

`GET /api/v1/admin/system-health/database` reports:
- connection status
- database size (from `information_schema`)
- table count
- orphan scan across the 9 FK pairs the system tracks
- pending/present migrations (files vs `migrations` table)

## Migrations policy

- **Never edit or reshape an applied migration.** Add a new additive migration.
- Drift detection: health check diff = migration files vs `migrations` table.
  Fix drift with `php artisan migrate --force` (additive) — proven in
  Phase 12 (missing `report_exports`) and Phase 13 (5 new maintenance tables).
- Always review `php artisan migrate:status` before and after a change.
- Never run `migrate:fresh` on `acadtrack_db`; tests use their own
  `acadtrack_db_test` with `RefreshDatabase`.

## Routine maintenance

```sql
-- monthly: table sizes
SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS mb
FROM information_schema.tables WHERE table_schema = 'acadtrack_db' ORDER BY mb DESC;

-- prune: expired report export rows are handled by reports:cleanup-exports (TTL)

-- spot-check audit/activity growth
SELECT COUNT(*) FROM activity_logs;
```

- Keep at least one verified backup before any schema-affecting work
  (see [`backup-restore.md`](backup-restore.md)).
- Keep `jobs`, `failed_jobs`, `notification_logs`, and health history from
  growing unbounded: purge `failed_jobs` after resolution
  (`php artisan queue:flush`), review log rows per [`../DATA_PRIVACY.md`](../DATA_PRIVACY.md).

## Failed jobs hygiene

```bash
php artisan queue:failed            # inspect
php artisan queue:retry all         # after fixing a systemic cause
php artisan queue:flush             # only after capacity problems are resolved
```

## Backup & restore

Full restore-tested workflow: [`backup-restore.md`](backup-restore.md).
DB dumps → `storage/app/private/backups` (or the configured export dir);
`php artisan system:verify-backups` enforces freshness + restore proof.

## Related

- Health: [`monitoring.md`](monitoring.md)
- Troubleshooting: [`troubleshooting.md`](troubleshooting.md)
- Schema docs: [`../DATABASE.md`](../DATABASE.md)