# JPC Portal — Administrator Guide

How the **Admin** role operates the system day-to-day.

> Demo account: `admin@jpc.local` / `P@ssw0rd2026` (dev only — change on real
> deployments).

## 1. Login

1. Open the portal (local: http://localhost:5173).
2. Enter your credentials → **Sign in**.
3. The institution dashboard loads with live health status (backend, API, DB).

## 2. Employee management

- **Manage employees** — create, edit, suspend/reactivate employee records,
  employee numbers, department and position assignment, employment status.
- **Employee history** — view personnel changes over time.
- **Employee data integrity** — check for incomplete or inconsistent records
  (feeds the data-quality scan).

## 3. Department management

- **Departments** — create/rename/archive departments (soft delete preserves
  history).
- **Positions** — maintain the position catalog used by employee profiles.
- **Department heads** — assign a head to a department (current head, start/end
  dates).

## 4. Task management

- **Annual plans / tasks** — create tasks within an annual plan, set priority and
  due date, publish or cancel tasks.
- **Task assignments** — assign tasks to employees, monitor progress percentage,
  review task-level reviews/comments/attachments.

## 5. Target management

- **Annual plans** — define institutional and departmental annual plans.
- **Employee targets / department targets** — set measurable targets (title, value,
  unit, status) and track achievement against them.

## 6. Report monitoring & review

- **Reports list** — filter by status (draft, submitted, under review, revision
  required, approved, rejected, late), employee, department, period.
- **Report detail** — inspect versions, uploaded files, references, timeline, and
  AI findings.
- **Review** — open a submitted report, read the AI summary/findings (assistance
  only), then make the **human decision**:
  - Approve → recorded in `report_approvals`
  - Request revision → recorded in `report_reviews` with notes (employee edits and
    resubmits)
  - Reject → recorded with a reason
- **Export** — queue a CSV/XLSX/PDF export from the Reporting module and download
  when status = `ready` (ownership + TTL enforced).

> **AI never approves.** Approval/revision/rejection is always a human action.

## 7. Performance monitoring

- **Performance** — view employee performance records, score breakdowns, progress
  snapshots, completion rate and KPI.
- **Monthly performance / achievements** — rankings, employee-of-month, badges.

## 8. Data-quality review

- **Data-quality scans** — trigger a scan (`RunDataQualityScanJob`), review found
  issues (entity, type, severity, status).
- **Issue resolution** — mark issues in progress / resolved / dismissed; history is
  retained in `data_quality_issue_histories`.

## 9. Analytics & export

- **Analytics hub** — trends (performance/completion/submission), department and
  employee comparisons, rankings, performance distribution.
- **Report exports** — queue, monitor and download exports; view status history.

## 10. AI Chat

- **AI chat** — ask natural-language questions about your own institution's data
  (authorized tools only), e.g.:
  - *"Show all late reports this month."*
  - *"Who has the highest completion rate this month?"*
  - *"Which department has the highest performance?"*
- The chat answers via the AI tool registry, scoped to your role and institution.

## 11. Notifications

- **Notifications** — read in-app notifications; set channel preferences.
- Deadline reminders, overdue alerts, review decisions, achievements and monthly
  reports generate notifications automatically.

## 12. Audit review

- **Audit log** — review sensitive mutations (who changed what, old/new values).
- **Activity log** — broad user activity stream.

## 13. Administration extras

- **RBAC** — view role/permission catalog (`GET /api/v1/admin/rbac`); manage role
  assignments.
- **Institution dashboard options** — configure dashboard filters/options.
- **User management** — suspend accounts (login activities recorded).