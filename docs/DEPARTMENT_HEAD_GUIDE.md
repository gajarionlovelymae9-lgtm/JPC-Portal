# JPC Portal — Department Head Guide

How the **Department Head** role manages their department's tasks, reports and
performance.

> **Consolidated guide:** the management workflows for Admin, HR and Department
> Heads are now covered together in
> [`user-guides/management-guide.md`](user-guides/management-guide.md). This
> page keeps the department-head quick reference below and is maintained
> alongside it.

> Demo account: `departmenthead@jpc.local` / `P@ssw0rd2026` (dev only).

> **Key principle:** AI **assists** the reviewer — it summarizes, flags possible
> issues, and highlights similarity/validation indicators. **AI never approves,
> rejects, or accuses.** The human reviewer makes every consequential decision.

## 1. Login

Sign in with your department-head credentials. Your **department dashboard** loads
scoped to your department.

## 2. Department dashboard

- Department completion rate, late reports, pending reviews.
- Employees in your department and their current progress.
- Recent submissions awaiting your decision.

## 3. Assigned employees

- View the employees in your department, their targets, tasks and progress.
- Drill into an employee's performance and report history.

## 4. Tasks

- View (and where permitted, create/manage) tasks for your department.
- Assign tasks to employees, monitor progress percentage and due dates.

## 5. Targets

- View department targets and your employees' targets; track achievement against
  them.

## 6. Submitted reports

- Open the report list filtered to your department; statuses include
  `submitted`, `under_review`, `revision_required`, `approved`, `rejected`,
  and late submissions (`is_late`).

## 7. AI summaries

Open a report to see the AI-generated **summary** — key accomplishments,
recommendations, validation score, and similarity indicators. This condenses the
document so you can review faster.

## 8. AI findings

Review the AI **findings** (possible issues, missing requirements, similarity
indicators). Treat each as a **suggestion to verify**, not a conclusion —
similarity detection is an **indicator, not proof of plagiarism**.

## 9. Manual review

1. Read the report content, versions, files, references and timeline.
2. Cross-check AI findings against the actual document.
3. Leave reviewer comments (recorded in `report_review_comments`).
4. Make the human decision:
   - **Approve** — the report is accepted (recorded in `report_approvals`).
   - **Revision required** — send it back with clear notes; the employee edits and
     resubmits.
   - **Reject** — reject with a reason (recorded in `report_reviews`).

## 10. Revision workflow

- When you request a revision, the employee is notified and the status becomes
  `revision_required`.
- On resubmission the status becomes `resubmitted` and returns to your queue; a new
  report version is created.

## 11. Performance

- View your department's and employees' **performance** records, score breakdowns,
  progress snapshots, completion rate and KPIs.
- **Monthly performance / achievements** — rankings and employee-of-month for your
  department.

## 12. Department analytics

- **Analytics** — completion/submission trends, comparisons, rankings and
  performance distribution filtered to your department.
- **Reports & exports** — generate department reports and queue exports
  (CSV/XLSX/PDF) when permitted.

> Human review remains required for every approval, rejection and revision.