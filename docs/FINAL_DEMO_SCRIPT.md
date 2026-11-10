# JPC Portal — Final Demonstration Script

A scripted walkthrough of the completed system for an academic/capstone
presentation. Uses the seeded fictional demo dataset (dev seeders).

> Prerequisites: backend + frontend running (`XAMPP_SETUP.md`), demo data seeded
> (`php artisan db:seed`), queue worker started (`php artisan queue:work`).

## PART 1 — LOGIN

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@jpc.local` | `P@ssw0rd2026` |
| HR | `hr@jpc.local` | `P@ssw0rd2026` |
| Department Head | `departmenthead@jpc.local` | `P@ssw0rd2026` |
| Employee | `employee@jpc.local` | `P@ssw0rd2026` |

1. Show the login screen (SPA, Sanctum cookie auth).
2. Log in as **Employee** (start with the producer side).
3. Log out; log in as **Admin** → show RBAC-driven sidebar (different menu per role).
   Repeat quickly for HR and Department Head to contrast menus.
4. Emphasize every role sees a different dashboard/navigation.

## PART 2 — EMPLOYEE

Log in as `employee@jpc.local`.

1. **Assigned task** — open My Tasks; show priority, due date, progress.
2. **Target** — open Targets; show target value/unit/status.
3. **Report creation** — New report, enter title + content, attach a document.
4. **Document upload + OCR** — upload a file; show the OCR job processing
   (status change) and the extracted text appearing.
5. **AI summary** — after processing, show the generated summary and findings
   (fake provider completes offline).
6. **Report submission** — click Submit; status changes `draft` → `submitted`;
   a new immutable version is recorded; timeline shows the event.

## PART 3 — DEPARTMENT HEAD

Log in as `departmenthead@jpc.local`.

1. **Pending reports** — open the report list filtered to `submitted`.
2. **AI findings** — open the submitted report; show summary, validation score,
   similarity indicator.
3. **Manual review** — read the actual content; enter reviewer comments.
4. **Revision** — request revision with notes → employee notified, status
   `revision_required`.
5. (Optional) show the employee resubmit, then **approve** → `approved` recorded in
   report approvals.
6. **Emphasize:** AI assists; the head makes the decision.

## PART 4 — ADMIN

Log in as `admin@jpc.local`.

1. **Institution dashboard** — summary KPIs.
2. **Employee monitoring** — employee list + progress table; drill into one employee.
3. **Completion rate / late reports** — dashboards/lists showing completion rate,
   `is_late` reports, deadlines.
4. **KPI / productivity** — performance section: records, score breakdowns, progress
   snapshots, completion rate.
5. **Monthly achievements** — rankings, badges, employee of the month.

## PART 5 — AI CHAT

In AI Chat (as admin), ask in order:

1. *"Show all late reports this month."*
2. *"Who has the highest completion rate this month?"*
3. *"Which department has the highest performance?"*

Show each tool-backed answer (tool registry, scoped/authorized). If a question is
outside the authorized tools, the assistant should say so gracefully.

## PART 6 — REPORTING

1. **Analytics** — trends (performance/completion/submission), comparisons
   (departments/employees/periods), rankings, distribution.
2. **Monthly report** — generate the monthly management report.
3. **Export** — queue a CSV/XLSX/PDF export; wait for `ready`; download and open the
   file. (Owner-only; expires by TTL.)

## PART 7 — DATA QUALITY

1. **Data-quality** — run a scan (shows pending → completed).
2. Show a sample issue (entity, type, severity).
3. **Resolve** — mark it resolved/dismissed; show the history entry.

## PART 8 — AUDIT

1. **Audit log** — show recent sensitive actions (who/what/old→new).
2. **Activity log** — show the action history generated during this demo.

## Closing

Summarize: React SPA → Laravel API → MySQL; OCR + AI assist (offline fake provider by
default); every approval/decision made by a human; security (RBAC, IDOR, private
storage); test suite (406 backend + 87 frontend) executed before demo.

## Demo quick-reference

| Topic | Where | Evidence to show |
| --- | --- | --- |
| OCR | Report detail | extracted text + confidence |
| AI summary | Report detail | summary panel |
| Validation | Report detail | score + items |
| Similarity | Report detail | indicator score |
| Review | Department head reports | review comment + decision |
| Approval | Report approvals | approver + timestamp |
| Performance | Admin/performance | scores + breakdowns |
| Achievements | Achievements | badges + rank |
| AI chat | AI Chat | tool answer + tool call log |
| Exports | Reporting/exports | queued → ready → downloaded file |
| Data quality | Data Quality | scan + issue + resolution |
| Audit | Audit logs | mutations + activity |
