# JPC Portal — Release Process

How changes become releases in the JPC Portal project (v1.0.0 baseline, Phase 13).

## Versioning

- A single `VERSION` file at the repository root holds the current version:
  ```
  JPC Portal v1.0.0
  Release date: 2026-09-23
  ...
  ```
- Maintenance records stamp `version` from `VERSION` automatically when not
  supplied (see `MaintenanceLogService::currentVersion()`).
- Releases are documented in the root [`CHANGELOG.md`](../../CHANGELOG.md)
  using the categories: **Added / Changed / Fixed / Security / Deprecated /
  Removed**.

## Release checklist

1. **Change** — implement + tests in a branch/copy. Never work on production.
2. **Test (backend)** — `cd backend; php artisan test` (full suite passes).
3. **Test (frontend)** — `cd frontend; npm test`; `npm run lint`; `npm run build`.
4. **Type/Case audit** — the frontend requires exact-case import paths
   (`pages/admin/Admin*.tsx`). Mixed-case paths on Windows silently collide;
   keep all new pages in the flat `pages/admin/` convention.
5. **Backup** — record `Database Backup` and verify:
   `php artisan system:verify-backups` (see [`backup-restore.md`](backup-restore.md)).
6. **Deploy** —
   ```
   git pull / sync
   cd backend
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force        # additive only, never fresh
   php artisan config:cache && php artisan route:cache
   php artisan queue:restart
   cd ../frontend && npm ci && npm run build
   ```
7. **Verify** — `php artisan migrate:status` (no pending surprises),
   `php artisan system:check-health`, smoke test login → dashboard → report.
8. **Document** — record the maintenance log and update `CHANGELOG.md`,
   `docs/` handover docs, and `VERSION`.
9. **Backlog** — close completed items in [`../BACKLOG.md`](../BACKLOG.md) with
   their resolution.

## Maintenance types recorded

`maintenance_logs.maintenance_type` — free-form but keep consistent labels:
`Database Backup`, `Database Restore`, `Security Patch`, `Incident Response`,
`File Storage`, `AI Maintenance`, `OCR Maintenance`, `Performance`, `Release`.

## Rules

- Do not upgrade Laravel 12 → 13 for this release; v1.0.0 pins Laravel 12 / PHP 8.2.
- A change that touches HTTP/API must not break the health endpoints or
  `role:admin` gating (covered by `SystemMaintenanceApiTest`).
- Every production-impacting change must pass the backend and frontend suites
  before the maintenance log is marked `completed`.

## Related

- Change log: [`../../CHANGELOG.md`](../../CHANGELOG.md)
- Monthly checklist: [`monthly-checklist.md`](monthly-checklist.md)
- Deployment: [`../DEPLOYMENT.md`](../DEPLOYMENT.md)