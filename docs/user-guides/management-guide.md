# JPC Portal — Management User Guide

Authorized management workflows for the **HR**, **Department Head**, and
**Admin** roles.

> Development demo accounts (dev only — change on real installations):
> HR `hr@jpc.local`, Department Head `departmenthead@jpc.local`, Admin
> `admin@jpc.local` / `P@ssw0rd2026`.

> **Key principle:** AI **assists** the reviewer — it summarizes, flags possible
> issues and highlights similarity/validation indicators. **AI never approves,
> rejects, or accuses.** The human reviewer makes every consequential decision.

---

## Part A — Shared management workflows

### A1. Login and dashboards

- Admin: institution dashboard with live health status (backend / API / DB
  health indicators).
- HR: employee-centric overview; manage people and departments below.
- Department Head: department dashboard scoped to your department — completion
  rate, late reports, pending reviews, employees and their progress.

### A2. Employee monitoring

- View employees, their targets, tasks, progress and report history (scoped by
  role: HR at institution level, Department Head for their department, Admin
  everywhere).
- Drill into performance records, score breakdowns, progress snapshots, KPIs.
- Spot anomalies (low completion, late reports, at-risk targets) and act.

### A3. Report review (HR, Department Head, Admin — where the permission allows)

1. Open the report list filtered by status, employee, department, period.
2. Open a report: read versions, uploaded files, references, timeline and the
   AI summary/findings.
3. Cross-check AI findings against the actual document (**indicators, not
   proof**).
4. Leave reviewer comments (`report_review_comments`).
5. Make the human decision:
   - **Approve** → recorded in `report_approvals`;
   - **Revision required** → employee is notified, status
     `revision_required`, edits and resubmits;
   - **Reject** → reject with a reason (`report_reviews`).

### A4. Performance & achievements

- **Performance**: scores, score breakdowns, completion rate, progress
  snapshots, KPI. Compute is by the system — do not hand-edit.
- **Monthly performance / achievements**: rankings, employee of the month,
  badges. Same human-review principle applies to any decision influenced by
  scores.

### A5. Analytics & exports

- **Analytics hub**: trends (performance/completion/submission), department and
  employee comparisons, rankings, performance distribution — filtered to your
  scope.
- **Report exports**: queue CSV / XLSX / PDF from the Reporting module; download
  when status `ready`; exports are owner-scoped and TTL-cleaned by
  `reports:cleanup-exports`.

### A6. Data quality

- Run a **data-quality scan** (also automated weekly, Mondays 06:30).
- Review issues (entity, type, severity, status); assign / resolve / dismiss.
- History is retained in `data_quality_issue_histories`. Use findings to clean
  employee/department data.

### A7. AI Chat

- **AI Chat** answers natural-language questions about institution data through
  authorized, role-scoped tools:
  - "Show all late reports this month."
  - "Who has the highest completion rate this month?"
  - "Which department has the highest performance?"
- Tool calls and conversations are logged; answers must be verified against
  actual data before acting.

### A8. Audit trail

- **Audit log** — sensitive mutations (who, what, old/new values).
- **Activity log** — broad user activity stream.
- Review when investigating an incident or unexpected change. Keep the audit
  trail intact — deletions are not silent.

### A9. Notifications

- Send **announcements** to employees/departments (HR).
- Review `notification_logs` for failed deliveries (deadline reminders, review
  decisions) and retry/investigate as needed.

### A10. When AI or OCR fails

- Confirm the worker is running: `php artisan queue:work`.
- Check provider config: `AI_PROVIDER` / `OCR_PROVIDER` (default `fake` works
  offline; real providers need credentials in `backend/.env`).
- Retry failed jobs: `php artisan queue:retry all`; list:
  `php artisan queue:failed`.
- Logs: `backend/storage/logs/laravel-*.log`. The app never crashes on
  provider failure — the report stays reviewable.

**Never expose or paste API keys into the frontend, logs, or tickets.**

---

## Part B — HR workflows

### B1. Employee management

- Create / edit / suspend / reactivate employees (employee number, profile
  fields, employment status).
- Manage **department** and **position** assignment.
- View **employee history** (personnel change trail).

### B2. Employee data validation

- Records feed the data-quality scan (missing fields, duplicates, invalid
  department/position links) — resolve issues via the data-quality module.

### B3. Departments & positions

- View/maintain departments (name, code, status) and the position catalog;
  assign department heads (start/end dates, current head flag).

### B4. User lifecycle

- Create accounts for new staff and assign the correct role (employee /
  department head / HR).
- Reset passwords; suspend accounts for offboarding (login blocked, history
  retained).
- Cross-check `login_activities` for suspicious activity.

---

## Part C — Department Head workflows

### C1. Department dashboard

- Completion rate, late reports, pending reviews, employees' progress.
- Recent submissions awaiting your decision.

### C2. Department tasks and targets

- View (and where permitted create/manage) tasks for your department; assign
  to employees, monitor progress percentages and due dates.
- View department targets and employees' targets; track achievement.

### C3. Department reporting & reviews

- Review submitted reports for your department (§A3).
- Generate department reports and queue exports when permitted.
- Analytics filtered to your department.

---

## Part D — Admin workflows

### D1. Institution configuration

- **Departments**: create/rename/archive (soft delete preserves history).
- **Positions**: maintain the catalog.
- **Department heads**: assign heads (current head, start/end dates).
- **Annual plans / targets**: institutional and departmental annual plans,
  employee/department targets.

### D2. System administration

- **User/RBAC**: view the role/permission catalog (`GET /api/v1/admin/rbac`);
  manage role assignments; suspend accounts.
- **Maintenance log**: record/audit each maintenance action
  (`/admin/maintenance` or `system:record-maintenance`).
- **System health**: `/admin/system-health` — application, database, queue,
  storage, AI, OCR, notifications; failed-job counters; redacted critical
  errors.
- **Feedback**: review `/admin/feedback` entries (bug / incorrect info / UI /
  AI / OCR / feature request).

### D3. Backup & operations

- Follow [`BACKUP_AND_RESTORE.md`](../BACKUP_AND_RESTORE.md): daily backups,
  monthly restore test, off-machine storage.
- Run `php artisan system:verify-backups` to confirm backup freshness.
- Ensure the queue worker and scheduler are running; see
  [`MAINTENANCE.md`](../MAINTENANCE.md) and [`ADMIN_HANDOVER.md`](../ADMIN_HANDOVER.md).

---

## Cross-role checklist (before acting on AI output)

- [ ] Read the actual document, not just the summary.
- [ ] Verify similarity/validation indicators against the document.
- [ ] Comment clearly when requesting revision or rejecting.
- [ ] Record approvals/rejections with a reason where the workflow asks.
- [ ] Never let AI output override a human decision or the audit trail.

See [`ADMIN_HANDOVER.md`](../ADMIN_HANDOVER.md) and
[`DEVELOPER_HANDOVER.md`](../DEVELOPER_HANDOVER.md) for handover-level detail.