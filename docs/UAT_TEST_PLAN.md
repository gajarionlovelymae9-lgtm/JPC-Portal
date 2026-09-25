# JPC Portal — User Acceptance Test (UAT) Plan

| | |
| --- | --- |
| **Project** | JPC Portal — An AI-Driven Employee Progress Monitoring Dashboard Using Natural Language Processing-Based Algorithm for John Paul College |
| **Version** | v1.0.0 |
| **Phase** | 12 — Final Academic Validation, Capstone Demonstration, UAT & System Acceptance |
| **Intended testers** | Admin, HR, Department Head, Employee (demonstration accounts), advisers/dean |

## 1. Purpose

Verify, against the **actual running system**, that every functional module works,
role boundaries are enforced, AI and OCR assist the workflow, and the system is
suitable for capstone demonstration and final acceptance.

> **Absolute rule:** every UAT result is recorded as **PASS / FAIL / BLOCKED** with
> the actual observed behavior. No result is assumed to pass without exercising it.

## 2. Test environment

| Item | Value |
| --- | --- |
| Backend URL | http://localhost:8000/api/v1 |
| Frontend URL | http://localhost:5173 (dev) / served build (prod) |
| Database | `acadtrack_db` (dev), `acadtrack_db_test` (automated) |
| Stack | React 19 + Vite, Laravel 12, PHP 8.2, MySQL 8/MariaDB (XAMPP) |
| Demo accounts | See README (dev-only; fictional data) |
| Services needed | `php artisan serve`, `php artisan queue:work`, scheduler (optional) |

## 3. UAT test case format

```
Test Case ID:   UAT-<MODULE>-<NNN>
Feature:        <module / screen>
Role:           <Admin | HR | Department Head | Employee>
Precondition:   <required state>
Steps:          1. ... 2. ... 3. ...
Expected Result: <observable success>
Actual Result:   <recorded by tester>
Status:          PASS / FAIL / BLOCKED
Tester:          <name / role>
Date:            <YYYY-MM-DD>
Remarks:         <notes>
```

## 4. Role-scope matrix (verified against `app/Enums/Permissions.php`)

The matrix below is derived from the **actual** permission catalog, not a
hypothetical table. Scoping columns: **All** = whole institution, **Dept** =
department head's own department, **Own** = the caller's own records, **View** =
read-only, **—** = no access (route returns 403 / UI hides entry).

| Function | Admin | HR | Department Head | Employee |
| --- | --- | --- | --- | --- |
| Manage Users (create/update/delete) | All | — (users.view read-only) | — | — |
| View Roles / Permissions (RBAC catalog) | All | — | — | — |
| Manage Employees (CRUD) | All | Create/Update + View | View (Dept) | Own profile only |
| Manage Departments / Positions | All CRUD | View | View | View |
| Manage Tasks (create/assign/complete) | All | View | All (Dept) | Assigned tasks only (complete own) |
| Manage Targets / Plans | All | View | Create/Update (Dept) | View (Own targets) |
| Submit / Create Reports | All (workflow) | — | — | Own |
| Review Reports | All | View only | Dept | Own view only |
| Approve / Reject Reports | All | — | Dept | — |
| View Report Files | All | Yes (authorized) | Dept | Own only |
| Run / View OCR | All | View | Run + View (Dept) | View (Own) |
| Run AI Analysis / View Summary, Validation, Similarity | All | View | Run + View (Dept) | View (Own) |
| Institution Analytics | All | Authorized | — (analytics scoped to Dept) | — |
| Department Analytics | All | Authorized | Yes (Dept) | — |
| Own Performance / KPIs | All | Authorized | Yes | Own |
| Achievements / Badges | All | View | View + Award | View (Own) |
| Data Quality (scan/issues) | All | Review issues | View (Dept) | — |
| Reporting & Exports | All | Authorized reports | Dept reports | Generated exports owned by user |
| AI Chat | All (all tools) | Authorized tools | Authorized tools | Limited tools (own scope) |
| Audit Logs (`audit_logs`) | All | — | — | — |
| Activity Logs (`activity_logs`) | All | View | View | — |

> Notes: Employees are scoped to their own records server-side (IDOR protection);
> HR has `analytics.view` and `activity_logs.view` but **not** `audit_logs.view` nor
> any report review/approve permission; Department Head has full department-scoped
> task/report workflow + analytics, but no `audit_logs.view`. Admin is the only role
> with `audit_logs.view`.

## 5. UAT Scenarios — Admin

### UAT-ADM-001 Login
- Precondition: server + frontend running; admin account active.
- Steps: 1) Open portal. 2) Enter admin credentials. 3) Sign in.
- Expected: lands on institution dashboard; health indicators OK.
- Status: ______  Tester: ______  Date: ______  Remarks: ______

### UAT-ADM-002 Dashboard metrics
- Steps: view KPI cards (completion rate, late reports, pending reports).
- Expected: numbers render and match the database (not hard-coded).

### UAT-ADM-003 Manage employees
- Steps: create an employee → edit it → suspend it → view history.
- Expected: create/edit/suspend succeed; history records changes; suspended user can't log in.

### UAT-ADM-004 Manage departments & positions
- Steps: create department, assign head; maintain a position.
- Expected: catalog updates persist and appear in employee assignment.

### UAT-ADM-005 Manage tasks
- Steps: create task → assign to employee → update progress → complete.
- Expected: assignment notification created; progress tracked; status transitions validated.

### UAT-ADM-006 Manage targets
- Steps: maintain annual plan → department target → employee target.
- Expected: target records persist with status and values.

### UAT-ADM-007 View reports
- Steps: open report list; filter by status/department/employee/late.
- Expected: filters applied; each report shows versions, files, timeline, reviews.

### UAT-ADM-008 Review & decide a report
- Steps: open a `submitted` report → read AI summary + findings → approve / request revision / reject with comment.
- Expected: decision recorded (`report_reviews`/`report_approvals`); timeline updated; employee notified.

### UAT-ADM-009 Review performance
- Steps: open performance section for an employee/period.
- Expected: records, score breakdowns, progress snapshots, KPIs display calculated values.

### UAT-ADM-010 Review achievements
- Steps: open achievements; view badges and monthly achievements.
- Expected: only configured-awarded badges/ranks displayed.

### UAT-ADM-011 Review data-quality issues
- Steps: run a scan → examine issues → mark resolved/dismissed.
- Expected: issues with entity/type/severity; resolution history recorded.

### UAT-ADM-012 Generate reports
- Steps: generate employee / department / institution / monthly / late / achievements / management summary report.
- Expected: payloads reflect database values.

### UAT-ADM-013 Export reports
- Steps: queue export (CSV/XLSX/PDF) → wait ready → download → open file.
- Expected: file contains correct filters, totals, employee/performance data, period.

### UAT-ADM-014 AI Chat
- Steps: ask "Show all late reports this month." then "Which department has the highest performance?"
- Expected: answers grounded in database; tool calls authorized.

### UAT-ADM-015 Review audit trail
- Steps: perform an action (e.g. edit employee), open audit log.
- Expected: the mutation is recorded (user, action, old/new), plus activity log entry.

## 6. UAT Scenarios — HR

### UAT-HR-001 Login
- Expected: HR dashboard loads; menu shows only HR-authorized items.

### UAT-HR-002 View authorized employee information
- Steps: open employees; open one profile.
- Expected: read access; no create of users; no roles/permissions management.

### UAT-HR-003 Monitor employee progress
- Steps: view employee progress snapshots / target completion.
- Expected: data shown per authorization.

### UAT-HR-004 View authorized performance
- Expected: performance records display read-only (no manage).

### UAT-HR-005 Review data-quality issues
- Expected: scan history + issues visible; resolution allowed per permissions.

### UAT-HR-006 Generate authorized reports
- Expected: HR can access authorized reports/analytics; exports owner-scoped.

### UAT-HR-007 Negative checks
- Try to approve/reject a report and to open audit logs.
- Expected: 403 / hidden options (HR has no `reports.review/approve`, no `audit_logs.view`).

## 7. UAT Scenarios — Department Head

### UAT-DH-001 Login & department dashboard
- Expected: department-scoped dashboard (own department only).

### UAT-DH-002 View assigned employees
- Expected: only own-department employees visible.

### UAT-DH-003/004 Tasks & targets
- Expected: create/update/assign tasks within department; department targets editable.

### UAT-DH-005 Review submitted reports
- Expected: own-department submitted/under-review reports listed.

### UAT-DH-006 View AI summary
- Expected: summary shown for a report in department.

### UAT-DH-007 View AI findings
- Expected: validation/similarity indicators shown as assistance.

### UAT-DH-008 Request revision
- Expected: report set to `revision_required` with comment; employee notified.

### UAT-DH-009 Approve report
- Expected: report `approved`; approval + approver + timestamp recorded; employee notified.

### UAT-DH-010 Department performance
- Expected: department performance records/rankings display.

### UAT-DH-011 Achievements
- Expected: view department achievements; award badges per permission.

### UAT-DH-012 Negative checks
- Try to open another department's report/employee or audit logs.
- Expected: 403 / filtered out (IDOR protected; no audit_logs permission).

## 8. UAT Scenarios — Employee

### UAT-EMP-001 Login
- Expected: employee dashboard; only own records menu.

### UAT-EMP-002 View assigned tasks
- Expected: own assignments with progress/priority/due date.

### UAT-EMP-003 View targets
- Expected: own targets with values/status.

### UAT-EMP-004 Create report
- Expected: draft created; still editable.

### UAT-EMP-005 Upload report
- Steps: attach a document (valid type/size).
- Expected: file stored privately; OCR job triggered (text extraction).

### UAT-EMP-006 Review AI summary
- Expected: summary/validation/similarity panels populate (assistive).

### UAT-EMP-007 Submit report
- Expected: status → `submitted`; immutable version; reviewer notification.

### UAT-EMP-008 View status / notifications
- Expected: report detail shows timeline; notifications received.

### UAT-EMP-009 Respond to revision
- Expected: after reviewer revision request, report shows `revision_required` + comments.

### UAT-EMP-010 Resubmit
- Expected: new version; status → `resubmitted`.

### UAT-EMP-011 View performance
- Expected: own performance records/breakdowns/KPI display.

### UAT-EMP-012 View achievements
- Expected: own badges/monthly achievements display.

### UAT-EMP-013 Negative checks
- Try to open another employee's report/performance or analytics page.
- Expected: 403 / hidden (service-scoped; no `analytics.view`).

## 9. Happy-path end-to-end (Employee → Reviewer)

```
LOGIN → TASK → REPORT(draft) → UPLOAD → OCR → AI(summary/validation/similarity)
→ SUBMIT → REVIEW (DH) → AI findings → MANUAL decision → REVISION → RESUBMIT
→ APPROVE → PERFORMANCE/ACHIEVEMENTS recorded → dashboard reflects it.
```
Record each state transition and the final status; this is the primary acceptance
flow (UAT-E2E-001).

## 10. Negative-path test cases (must be rejected safely)

| ID | Action | Expected rejection |
| --- | --- | --- |
| UAT-NEG-001 | Invalid login (wrong password) | 401, no data leak, rate-limited after attempts |
| UAT-NEG-002 | Employee accesses another employee's report/performance by ID | 403 / filtered (IDOR) |
| UAT-NEG-003 | Unauthorized export (non-owner / wrong role) | 403; export ownership enforced |
| UAT-NEG-004 | Invalid file upload (wrong mime/extension) | 422; file rejected |
| UAT-NEG-005 | Oversized file | 413/422; rejected per `REPORT_MAX_FILE_SIZE_KB` |
| UAT-NEG-006 | Report missing required fields | 422 validation |
| UAT-NEG-007 | Report/submission with invalid status transition (e.g. approve from `draft`) | 422; workflow service blocks |
| UAT-NEG-008 | Duplicate submission attempt | Blocked / idempotent |
| UAT-NEG-009 | Unauthorized approval (employee approves own report) | 403 |
| UAT-NEG-010 | Cross-department access by Department Head | 403/filtered |
| UAT-NEG-011 | Accessing a non-existent resource | 404, safe message |
| UAT-NEG-012 | AI Chat asking for secrets/other-department data | Refused; no secrets exposed; no SQL execution |

## 11. Role isolation verification

Execute the matrix in §4 by:
1. Logging in as each role and capturing the **rendered menu** (expected visible vs hidden).
2. Issuing the same HTTP request under two different roles and asserting the response
   differs by permission (200 vs 403) for: employee create, task assign, report
   approve, analytics access, audit log access, export.
3. Recording the results in the acceptance sheet (§14).

## 12. Data & reproducibility

- All demo data is **fictional**; no real personal information.
- UAT may re-seed the development database (`php artisan db:seed`) only in a
  development environment — never on production-like data.
- Every FAIL is documented with reproduction steps and logged before fix.

## 13. UAT closure criteria

- 100% of Critical and High scenarios PASS (or are fixed and re-tested).
- No unresolved Critical/High defect blocking core workflows.
- E2E happy path passes end-to-end.
- Role matrix shows the expected isolation behavior on sampled checks.

## 14. Acceptance sheet

| Area | Status (PASS/FAIL) | Notes |
| --- | --- | --- |
| Authentication | ______ | |
| RBAC / role isolation | ______ | |
| Employee / department / tasks / targets | ______ | |
| Report workflow (draft→approve) | ______ | |
| OCR | ______ | |
| AI summary/validation/similarity/findings | ______ | |
| Performance / achievements | ______ | |
| Data quality | ______ | |
| Reporting / exports | ______ | |
| AI Chat (+ security refusal) | ______ | |
| Notifications | ______ | |
| Audit trail | ______ | |
| Security negatives | ______ | |

Upload completed sheets to the final acceptance document
([`SYSTEM_ACCEPTANCE.md`](SYSTEM_ACCEPTANCE.md)).