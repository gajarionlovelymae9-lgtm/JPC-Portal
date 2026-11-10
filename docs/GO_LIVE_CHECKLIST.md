# JPC Portal — Final Go-Live Checklist

Use this checklist before declaring the system production-ready.

## Application

- [ ] Backend working — `GET /api/v1/health` returns `success: true`
- [ ] Frontend working — SPA loads at the production URL
- [ ] Database working — migrations applied; `php artisan migrate:status` all `Ran`
- [ ] Authentication working — login/logout/me for all roles
- [ ] RBAC working — each role sees only its permitted menu/data (verified in demo)
- [ ] `SEED_DEVELOPMENT_DATA=false` in production `.env` (defaults to `true` —
      see [`INSTALLATION.md`](INSTALLATION.md) §4.4 / [`DEPLOYMENT.md`](DEPLOYMENT.md) §6)

## Core features

- [ ] **Employees** — create/edit/suspend; history recorded
- [ ] **Departments** — maintain + head assignment
- [ ] **Targets** — annual plans, department/employee targets
- [ ] **Tasks** — tasks, assignments, progress, references, comments, review
- [ ] **Reports** — draft/submit/versions/timeline workflow
- [ ] **OCR** — document upload + text extraction (fake engine or Google Vision)
- [ ] **AI** — summary, validation, reference/similarity indicators, insights
- [ ] **Review** — human examine + comment + decide
- [ ] **Approval** — approve/revision/reject recorded with approver & timestamp
- [ ] **Performance** — records, score breakdowns, progress, KPIs
- [ ] **Achievements** — badges + monthly achievements
- [ ] **Data quality** — scans, issues, resolution history
- [ ] **Analytics** — trends, comparisons, rankings, distribution
- [ ] **Reporting** — all report payloads incl. management summary
- [ ] **AI Chat** — authorized tool answers
- [ ] **Notifications** — in-app + (optional) mail with preferences/logs
- [ ] **Audit trail** — audit + activity logs written

## Security

- [ ] Secrets protected — no `.env`/keys/credentials in git (audit with
      `git ls-files | grep -Ei 'env$|pem|key|cred'`)
- [ ] `.env` file protected (git-ignored; restricted file permissions)
- [ ] Private files protected — no public URL; authorized download only
- [ ] API endpoints authorized — unauthenticated/unauthorized return 401/403
- [ ] IDOR tested — cross-user/cross-dept access blocked in tests
- [ ] Upload security tested — mime/size limits, regenerated names
- [ ] `APP_DEBUG=false`, `APP_ENV=production` in production `.env`
- [ ] Debug error messages never expose stack/SQL/secrets
- [ ] AI/OCR credentials server-side only

## Operations

- [ ] Backup procedure documented + automated schedule configured
- [ ] Restore procedure documented + tested (recovery DB, monthly verification)
- [ ] Queue procedure — `queue:work` running (persistent in prod)
- [ ] Scheduler procedure — `schedule:run` via cron (or `schedule:work` dev);
      `php artisan schedule:list` shows all **7** tasks (deadline reminders,
      overdue notifications, monthly report, export cleanup, health check,
      backup verification, weekly data-quality scan)
- [ ] Logging enabled — `LOG_CHANNEL=daily` (or stack), log retention policy
- [ ] Monitoring — health polling, queue depth, failed jobs alerting
- [ ] Troubleshooting documentation available
- [ ] `php artisan optimize` run after final config on the prod host

## Documentation (all present under `docs/`)

- [ ] Admin Guide — `ADMIN_GUIDE.md`
- [ ] HR Guide — `HR_GUIDE.md`
- [ ] Department Head Guide — `DEPARTMENT_HEAD_GUIDE.md`
- [ ] Employee Guide — `EMPLOYEE_GUIDE.md`
- [ ] Employee User Guide — `user-guides/employee-guide.md`
- [ ] Management User Guide — `user-guides/management-guide.md`
- [ ] Developer Guide — `DEVELOPER_HANDOVER.md`
- [ ] Admin/Operations Handover — `ADMIN_HANDOVER.md`
- [ ] Installation Guide (new-machine) — `INSTALLATION.md`
- [ ] Release Notes & Versions — `RELEASE_NOTES.md` + `VERSION_MANAGEMENT.md`
- [ ] Maintenance Log — `maintenance-log.md`
- [ ] Deployment Guide — `DEPLOYMENT.md` (+ `XAMPP_SETUP.md`)
- [ ] Security Guide — `SECURITY.md`
- [ ] AI Governance — `AI_GOVERNANCE.md`
- [ ] Database Documentation — `DATABASE.md` / `docs/database/erd.md`
- [ ] Backup & Restore — `BACKUP_AND_RESTORE.md`
- [ ] Maintenance — `MAINTENANCE.md`
- [ ] Final Demo Script — `FINAL_DEMO_SCRIPT.md`
- [ ] Architecture — `FINAL_ARCHITECTURE.md`
- [ ] README — full project README with install/run/testing/accounts

## Final acceptance gates

- [ ] `php artisan test` -> **406 passed, 1880 assertions**
- [ ] `npm test` -> **87 passed**
- [ ] `npm run lint` → 0 errors (58 pre-existing warnings acceptable)
- [ ] `npm run build` → build succeeds (chunk-size warning acceptable, tracked)
- [ ] Demo script executes end-to-end on synthetic data
- [ ] No real employee personal information in seed/demo data
- [ ] `php artisan system:check-health --source=manual` → ok

## After go-live

- [ ] First backup taken and verified off-machine
- [ ] Queue worker + scheduler confirmed running after a reboot
- [ ] Monitoring alert email/notification configured (or documented manual check)
