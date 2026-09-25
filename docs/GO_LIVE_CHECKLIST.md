# JPC Portal â€” Final Go-Live Checklist

Use this checklist before declaring the system production-ready.

## Application

- [ ] Backend working â€” `GET /api/v1/health` returns `success: true`
- [ ] Frontend working â€” SPA loads at the production URL
- [ ] Database working â€” migrations applied; `php artisan migrate:status` all `Ran`
- [ ] Authentication working â€” login/logout/me for all roles
- [ ] RBAC working â€” each role sees only its permitted menu/data (verified in demo)

## Core features

- [ ] **Employees** â€” create/edit/suspend; history recorded
- [ ] **Departments** â€” maintain + head assignment
- [ ] **Targets** â€” annual plans, department/employee targets
- [ ] **Tasks** â€” tasks, assignments, progress, references, comments, review
- [ ] **Reports** â€” draft/submit/versions/timeline workflow
- [ ] **OCR** â€” document upload + text extraction (fake engine or Google Vision)
- [ ] **AI** â€” summary, validation, reference/similarity indicators, insights
- [ ] **Review** â€” human examine + comment + decide
- [ ] **Approval** â€” approve/revision/reject recorded with approver & timestamp
- [ ] **Performance** â€” records, score breakdowns, progress, KPIs
- [ ] **Achievements** â€” badges + monthly achievements
- [ ] **Data quality** â€” scans, issues, resolution history
- [ ] **Analytics** â€” trends, comparisons, rankings, distribution
- [ ] **Reporting** â€” all report payloads incl. management summary
- [ ] **AI Chat** â€” authorized tool answers
- [ ] **Notifications** â€” in-app + (optional) mail with preferences/logs
- [ ] **Audit trail** â€” audit + activity logs written

## Security

- [ ] Secrets protected â€” no `.env`/keys/credentials in git (audit with
      `git ls-files | grep -Ei 'env$|pem|key|cred'`)
- [ ] `.env` file protected (git-ignored; restricted file permissions)
- [ ] Private files protected â€” no public URL; authorized download only
- [ ] API endpoints authorized â€” unauthenticated/unauthorized return 401/403
- [ ] IDOR tested â€” cross-user/cross-dept access blocked in tests
- [ ] Upload security tested â€” mime/size limits, regenerated names
- [ ] `APP_DEBUG=false`, `APP_ENV=production` in production `.env`
- [ ] Debug error messages never expose stack/SQL/secrets
- [ ] AI/OCR credentials server-side only

## Operations

- [ ] Backup procedure documented + automated schedule configured
- [ ] Restore procedure documented + tested (recovery DB, monthly verification)
- [ ] Queue procedure â€” `queue:work` running (persistent in prod)
- [ ] Scheduler procedure â€” `schedule:run` via cron (or `schedule:work` dev)
- [ ] Logging enabled â€” `LOG_CHANNEL=daily` (or stack), log retention policy
- [ ] Monitoring â€” health polling, queue depth, failed jobs alerting
- [ ] Troubleshooting documentation available
- [ ] `php artisan optimize` run after final config on the prod host

## Documentation (all present under `docs/`)

- [ ] Admin Guide â€” `ADMIN_GUIDE.md`
- [ ] HR Guide â€” `HR_GUIDE.md`
- [ ] Department Head Guide â€” `DEPARTMENT_HEAD_GUIDE.md`
- [ ] Employee Guide â€” `EMPLOYEE_GUIDE.md`
- [ ] Developer Guide â€” `DEVELOPER_HANDOVER.md`
- [ ] Admin/Operations Handover â€” `ADMIN_HANDOVER.md`
- [ ] Deployment Guide â€” `DEPLOYMENT.md` (+ `XAMPP_SETUP.md`)
- [ ] Security Guide â€” `SECURITY.md`
- [ ] AI Governance â€” `AI_GOVERNANCE.md`
- [ ] Database Documentation â€” `DATABASE.md` / `docs/database/erd.md`
- [ ] Backup & Restore â€” `BACKUP_AND_RESTORE.md`
- [ ] Maintenance â€” `MAINTENANCE.md`
- [ ] Final Demo Script â€” `FINAL_DEMO_SCRIPT.md`
- [ ] Architecture â€” `FINAL_ARCHITECTURE.md`
- [ ] README â€” full project README with install/run/testing/accounts

## Final acceptance gates

- [ ] `php artisan test` -> **406 passed, 1880 assertions**
- [ ] `npm test` -> **87 passed**
- [ ] `npm run lint` â†’ 0 errors
- [ ] `npm run build` â†’ build succeeds (chunk-size warning acceptable, tracked)
- [ ] Demo script executes end-to-end on synthetic data
- [ ] No real employee personal information in seed/demo data

## After go-live

- [ ] First backup taken and verified off-machine
- [ ] Queue worker + scheduler confirmed running after a reboot
- [ ] Monitoring alert email/notification configured (or documented manual check)
