# JPC Portal — Backup & Restore Verification

Backups are only useful if they restore. This guide documents the process and
how the system tracks it.

> Detailed dump/restore steps (XAMPP `mysqldump`, verification database) are in
> [`../BACKUP_AND_RESTORE.md`](../BACKUP_AND_RESTORE.md). This page covers the
> Phase 13 verification workflow that surrounds them.

## Principles

1. **Never claim a backup is valid unless a restore was performed and logged.**
2. Backups are recorded as maintenance logs with type `Database Backup`.
3. A verified restore is recorded as type `Database Restore`.
4. `php artisan system:verify-backups` exits non-zero when:
   - no backup maintenance log exists,
   - the newest backup failed or is older than the window (`--days`, default 7),
   - no successful restore is logged after that backup, or
   - no `.sql` dump file exists under `storage/app/private/backups`.

## Recording a backup (manual, current workflow)

```bash
cd backend

# 1. Dump the database (example for XAMPP)
mysqldump -u root acadtrack_db > ../backups/acadtrack_db_$(date +%F).sql

# 2. Record it — audited, stamped with the current VERSION
php artisan system:record-maintenance \
  "Database Backup" \
  "Nightly mysqldump of acadtrack_db before Phase 13 deploy." \
  --module="Database" \
  --result=completed \
  --performed-by=admin@jpc.local

# 3. Weekly: verify freshness + restore proof
php artisan system:verify-backups --days=7
```

## Proving the backup restores

1. Restore the newest dump into a scratch database (e.g. `acadtrack_db_verify`).
2. Compare row counts of core tables (see BACKUP_AND_RESTORE.md §5).
3. Record the verified restore:

```bash
php artisan system:record-maintenance \
  "Database Restore" \
  "Restored nightly dump into acadtrack_db_verify; table counts matched." \
  --module="Database" \
  --result=completed \
  --performed-by=admin@jpc.local
```

Until step 3 is done, `system:verify-backups` correctly reports the backup as
**UNVERIFIED** — this is intentional and must not be bypassed.

## Backing up files

The private disk holds report files and exports:

- `backend/storage/app/private/reports/` — report uploads (OCR inputs)
- `backend/storage/app/private/report_exports/` — generated export files

Copy the whole `storage/app/private` directory alongside the DB dump. See
BACKUP_AND_RESTORE.md §4.

## Retention

Keep at least 7 daily dumps + the newest monthly dump, per the retention policy
in [`../DATA_PRIVACY.md`](../DATA_PRIVACY.md).

## Related

- Maintenance log UI: `/admin/maintenance` (admin)
- Monthly checklist: [`monthly-checklist.md`](monthly-checklist.md)
- Database maintenance: [`database-maintenance.md`](database-maintenance.md)