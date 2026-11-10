# JPC Portal — Operations: Backup & Recovery

Authoritative backup-and-recovery runbook for JPC Portal v1.0.0 (Phase 16).
Complements the pre-existing [`../BACKUP_AND_RESTORE.md`](../BACKUP_AND_RESTORE.md)
and [`../maintenance/backup-restore.md`](../maintenance/backup-restore.md).

> **Truth-in-reporting rule:** This document only reports what has actually been
> verified. A backup is only "restore-tested" when a real restore procedure has
> completed against it. As of this writing, **no production restore test has
> been performed** — see §5. Do not claim a backup is "valid" merely because the
> file exists.

## 1. Real backup locations (verified paths)

| Asset | Real path |
| --- | --- |
| Database dumps (mysqldump backups) | `backend/storage/app/private/backups/` |
| Document/OCR files | `backend/storage/app/private/private/reports` (double `private` nesting) |
| Generated exports | `backend/storage/app/private/report_exports` (TTL-cleanup via `reports:cleanup-exports` 03:15) |
| Logs | `backend/storage/logs/laravel-*.log` (daily channel) |

> Paths were corrected in Phase 15 — the older docs said `private/reports`;
> the real nesting is `private/private/reports`.

## 2. Backup verification chain (§8)

The `system:verify-backups --days=7` command implements this strict chain.
A backup is considered **verified** only when every link holds:

1. **BACKUP CREATED** — a `completed` maintenance-log record with type
   `Database Backup` exists within the window.
2. **EXISTS** — a dump file is present at
   `storage/app/private/backups/` for that record.
3. **SIZE** — the dump file has non-trivial size (not 0 bytes).
4. **INTEGRITY** — dump contents can be interpreted (mysqldump header present).
5. **TEST RESTORE** — a `completed` maintenance-log record of type
   `Database Restore` exists (i.e., that backup was actually restored before).
6. **VALIDATION** — post-restore health checks reported ok.

**Current status (not fabricated):** no Database Restore record exists, so
`system:verify-backups` returns `FAILURE` ("Backup is not verify-able: restore
record missing") — that is the honest outcome. See §5 for what to do next.

## 3. Creating a backup

Use the existing procedure (`maintenance` type `Database Backup`, result
`completed`, performed-by your user email/id), then record it:

```bash
php artisan system:record-maintenance "Database Backup" "Scheduled daily backup" \
  --module="Database" --result=completed --performed-by="admin@jpc.local"
```

Manual DB dump (local, always outside the app layer guards nothing — dumps are
authorized maintenance):

```bash
mysqldump -u root acadtrack_db > storage/app/private/backups/acadtrack_YYYY-MM-DD.sql
```

(In the local XAMPP environment `mysql.exe` may be unusable — missing
`caching_sha2_password.dll` — so prefer `php artisan`/PDO-based imports and the
existing backup command path.)

## 4. Restore procedure

**Do not restore unless a data-change was documented** (data-guard rule) and
the affected scope is understood. Prefer restoring only affected rows/tables
(§19 in [incident-response](incident-response.md)) over a blind full restore.

Full-restore steps:

1. Stop the app writes (maintenance mode is acceptable).
2. Confirm the target dump in §1 exists and is the right dated one.
3. Import it (pass the actual dump file, not a placeholder):
   `mysql -u root -p acadtrack_db < backups/<file>.sql`
   (or use PDO/`php artisan`-based import if the local `mysql.exe` is broken).
4. Re-enable the app.
5. **Record the restore honestly**: `system:record-maintenance "Database
   Restore" "Restored from <file>" --module="Database" --result=completed
   --performed-by="admin@jpc.local"` — this record is what makes the backup
   verify-able (§2.5).
6. Validate: `php artisan system:check-health --source=manual`, spot-check key
   tables, run `system:verify-backups` to confirm the chain now holds.

## 5. Gaps (honest, not hidden)

- **Restore-tested backups: NONE yet.** Every backup that will exist from this
  point forward must be restore-tested once to establish the chain (§2.5-6).
- **Cloud/offsite backup:** not configured — backups are on the same host.
- **Logs and file backups:** covered operably (paths above), not yet
  restore-tested either.
- The first restore test should be done in a **staging/test** copy of the app,
  not directly over production data, to validate procedure without risk.

## 6. Related

- [`monitoring.md`](monitoring.md) — scheduled verify task (05:30).
- [`incident-response.md`](incident-response.md) — data-incident procedure.
- [`../BACKUP_AND_RESTORE.md`](../BACKUP_AND_RESTORE.md),
  [`../maintenance/backup-restore.md`](../maintenance/backup-restore.md),
  [`../DEPLOYMENT.md`](../DEPLOYMENT.md).