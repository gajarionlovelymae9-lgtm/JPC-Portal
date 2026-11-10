# JPC Portal — Maintenance Log

The maintenance log records every maintenance action performed on the system
for auditability and verification.

## How records are captured

The system has a **primary, audited mechanism**: the `maintenance_logs` table,
written through:

- **Admin UI** — `/admin/maintenance` (roles with `maintenance.manage`
  permission, e.g. Admin); recorded via `MaintenanceLogService`.
- **CLI** — `php artisan system:record-maintenance <type> <description>`
  (see §"CLI reference" below).

Both paths record the **author** (authenticated user for the UI; the operator
name via `--performed-by=` for the CLI), a maintenance type, module, result,
rollback status, and timestamp. Auditors should query `maintenance_logs` for the
authoritative history.

This file intentionally contains **no fabricated historical entries**. It
documents the format and the procedure so every real action follows the same
template.

## Record format (table columns)

| Field | Meaning | Example |
| --- | --- | --- |
| `id` | auto-increment | `42` |
| `maintenance_type` | the kind of action | `Database Backup` |
| `description` | what was done | `Full logical dump of acadtrack_db moved off-machine` |
| `affected_module` | affected module | `Database` |
| `performed_by` | operator identity (user id or email) | `admin@jpc.local` |
| `result` | `completed` / `failed` / `pending` / `rolled_back` | `completed` |
| `rollback_required` | whether rollback is needed | `0` |
| `notes` | free-form detail (redacted of secrets) | `dump verified via restore test` |
| `performed_at` / timestamps | when performed | 2026-09-25 06:00 |

## Human-readable log (recommended format)

For a plain-text companion log or monthly review, use the same schema per entry:

```text
Date:
Version:
Issue:
Affected Module:
Action:
Result:
Developer:
```

Example (actual operations as they occur):

```text
Date: 2026-09-25
Version: v1.0.0
Issue: Storage path double-nesting verified during Phase 15 packaging
Affected Module: Storage / Reporting
Action: Documented real on-disk layout storage/app/private/private/reports
Result: completed — backup procedure updated
Developer: (operator name)
```

## Logged activity by default (scheduled, no manual entry needed)

These scheduled commands **already write** into the maintenance/monitoring
tables (see [`DEPLOYMENT.md`](DEPLOYMENT.md) §9):

- `system:check-health` daily 05:00 → `system_health_checks`
- `system:verify-backups` daily 05:30 → backup-freshness verdict

Manual actions that **must** be logged by the operator:

- Database backups and restores (type `Database Backup` / `Database Restore`)
- Security patches and dependency upgrades (type `Security Patch`)
- Incident responses (type `Incident Response`)
- AI / OCR provider changes (types `AI Maintenance` / `OCR Maintenance`)
- Performance work (type `Performance`)
- Releases (type `Release`)
- Any file-storage operation (type `File Storage`)

## CLI reference

```bash
cd backend
php artisan system:record-maintenance "Database Backup" "Daily dump taken and moved off-machine" --module=Database --result=completed --performed-by=admin@jpc.local
php artisan system:record-maintenance "Database Restore" "Monthly restore test into acadtrack_db_verify" --module=Database --result=completed --performed-by=admin@jpc.local
php artisan system:record-maintenance "Security Patch" "Patched composer dependency CVE-YYYY-NNNN" --module=Security --result=completed --performed-by=admin@jpc.local
```

> `--performed-by` must be an existing user id or email (the command resolves it
> and fails if not found). Always pass it for auditability. The stored column is
> `affected_module` — the `--module=` option populates it.

For auditability, always run CLI entries with `--performed-by=<existing user email or id>`.

## Rules

- **Never fabricate maintenance records.** Record only actions that actually
  occurred (and only mark `completed` when the action was verified).
- Never record secrets (API keys, passwords, credential paths) in `notes` or
  `description`.
- A backup is only "verified" after a successful restore test (see
  [`BACKUP_AND_RESTORE.md`](BACKUP_AND_RESTORE.md) §4 and
  [`maintenance/backup-restore.md`](maintenance/backup-restore.md)).
- Store the log with the backups so a post-disaster review can reconstruct what
  happened.

See also: [`maintenance/monthly-checklist.md`](maintenance/monthly-checklist.md)
(monthly review includes maintenance-log spot-checks) and
[`MAINTENANCE.md`](MAINTENANCE.md).