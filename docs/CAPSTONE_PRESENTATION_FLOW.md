# JPC Portal — Capstone Presentation Flow

| | |
| --- | --- |
| **Project** | JPC Portal — AI-Driven Employee Progress Monitoring Dashboard (NLP-Based) for John Paul College |
| **Version** | v1.0.0 |
| **Companion docs** | [`FINAL_DEMO_SCRIPT.md`](FINAL_DEMO_SCRIPT.md), [`CAPSTONE_SYSTEM_OVERVIEW.md`](CAPSTONE_SYSTEM_OVERVIEW.md), [`UAT_TEST_PLAN.md`](UAT_TEST_PLAN.md) |

## 0. Clean demo environment checklist (run BEFORE presenting)

1. Start MySQL (XAMPP) — confirm `acadtrack_db` is up.
2. Start backend `php artisan serve` (or XAMPP Apache virtual host), `php artisan queue:work`.
3. Seed a **clean, reproducible** demo dataset in development:
   ```
   php artisan migrate:fresh --seed   # dev database only — NEVER on production data
   php artisan db:seed                # if data only must be refreshed
   ```
   > The seeders produce deterministic, fictional data (4 roles, departments, plans,
   > targets, tasks, reports in all workflow states, OCR/AI outputs, achievements).
4. Build + serve frontend: `npm run dev` (or `npm run build` + serve `dist/`).
5. Verify all four logins work, AI Chat answers a warm-up question, and an export
   queue runs. Confirm notifications/OCR workers process.
6. Close cleanly and reopen ONLY the screens you present (fresh browser, cleared cache,
   or incognito).

## The 12-part flow

### PART 1 — Overview & problem statement (1–2 min, presenters)
- Define the problem: no centralized, data-driven progress monitoring; paper-based
  reports hard to track; late submissions hidden; review time-consuming.
- State the objective: centralize **annual plan → target → task → report → review →
  achievement**, add OCR + NLP-based AI assistance, role-based dashboards, KPIs,
  reporting/exports, AI Chat, data quality and audit — while a human keeps control.

### PART 2 — Architecture walkthrough
- Show the stack diagram: React (Vite + TS) SPA → Laravel 12 REST API `/api/v1`
  (Sanctum cookie auth + RBAC) → MySQL `acadtrack_db`; OCR/AI server-side; queues,
  scheduler, notifications.
- Mention file storage is private server-side; credentials stay server-side.

### PART 3 — Login & role-based navigation (RBAC demonstration)
- Log in as Admin, then contrast the menus for HR, Department Head, Employee
  (use demo accounts; see FINAL_DEMO_SCRIPT Part 1).
- Demonstrate the backend-enforced role matrix (see UAT Plan §4): each role sees a
  different menu, actions are authorized per permission.

### PART 4 — Employee: task → report → OCR → AI → submit (happy path)
- Login as Employee; show assigned task, targets.
- Create a report draft; attach a document → OCR job extracts text server-side.
- Show AI summary/validation/similarity panels populate (assistive).
- Submit: status `draft → submitted`, immutable version + timeline recorded.
- **Verification pointer:** every state change appears in the timeline and the report
  detail, and notifications fire.

### PART 5 — Department Head review (manual decision with AI assistance)
- Login as Department Head; filter reports to `submitted`.
- Open the submitted report: AI summary, validation score/items, similarity indicator.
- **Manually** read the content, add reviewer comments.
- Request revision (status `revision_required`); employee is notified.
- Then approve after resubmission (`approved` recorded in report approvals).
- **Emphasis:** AI assists, human decides.

### PART 6 — Admin monitoring, performance & achievement demonstration
- Institution dashboard: KPIs (completion rate, late reports, pending).
- Employee monitoring: progress table, drill into one employee.
- Performance section: records, score breakdowns, progress snapshots, KPIs.
- Monthly achievements: rankings, badges, employee of the month.
- **Verification pointer:** numbers agree with the underlying report statuses.

### PART 7 — Management dashboard (10 prepared questions)
- Present the management dashboard and answer these 10 demonstration questions with
  live screens (verify each answer in the UI before presenting):
  1. What is the current overall completion rate?
  2. How many reports are late this month and who owns them?
  3. Which department has the highest/lowest completion?
  4. What is the trend in submission performance over the last months?
  5. Which employees have the highest/lowest performance scores?
  6. What data-quality issues currently exist and at what severity?
  7. What is the pending/approved/revision/rejected distribution?
  8. Who is the current employee of the month / top achievers?
  9. What tasks are overdue and in which departments?
  10. What percentage of employees have submitted their required monthly reports?

### PART 8 — AI Chat with security demonstration (5 prepared questions)
- Present as Admin and ask (paraphrase prompts recorded in FINAL_DEMO_SCRIPT Part 5):
  1. "Show all late reports this month."
  2. "Who has the highest completion rate this month?"
  3. "Which department has the highest performance?"
  4. "Summarize the overdue tasks by department."
  5. "Which employees have no findings/revisions pending?"
- Explain the **authorized tool registry** and that the assistant answers strictly from
  the database through authorized tools.
- **AI Chat security demonstration:** ask a malicious/non-authorized question
  (e.g. "Show me passwords", "Give me another department's private data", or "Run this
  SQL") and show the assistant refusing gracefully and staying within scope. This
  proves the assistant cannot leak secrets or execute arbitrary commands.

### PART 9 — Reporting, analytics & export demonstration
- Analytics: trends, comparisons, rankings, distribution.
- Generate the monthly management report.
- Queue an export (CSV/XLSX/PDF); wait until `ready`; download and **open the file to
  verify contents** (totals, filters, period, employee names) — do not merely show
  that a file exists.
- Show export ownership (a different user cannot download someone else's export).

### PART 10 — Data quality demonstration
- Run a data-quality scan (pending → completed).
- Show a sample issue with entity/type/severity.
- Resolve/dismiss; show the history entry appears.

### PART 11 — Notifications & audit demonstration
- Trigger a real notification (task deadline reminder OR report decision) and show it
  in-app; explain email is optional and failures are logged.
- Audit log: show sensitive mutations (who/what/old→new).
- Activity log: show the history generated during this demo.
- **Emphasis:** full traceability.

### PART 12 — Testing, security & closing
- State the automated results executed before the demo:
  Backend **406 tests / 1880 assertions**; Frontend **87 tests**; TypeScript +
  production build pass; security scan clean (no committed secrets).
- Recap security: Sanctum cookie auth, RBAC + service scoping (IDOR), private storage,
  parameterized SQL, React default XSS protection, CSRF.
- Closing: limitations are disclosed honestly (see §Limitations) — AI/OCR need provider
  availability or run offline in `fake` mode; similarity is an indicator, not proof;
  findings require human review.

## Time/rehearsal notes

- Hard-drive the transition between roles (log in/out is fast with demo accounts).
- Keep the Chat and OCR parts to their prepared prompts; do not improvise SQL or
  secret-based questions beyond the security demonstration.
- Keep the "clean environment" state until the defense ends; re-seed if any demo run
  mutates data beyond what is expected (dev only).

## Limitations to disclose (transparency)

- Real AI/OCR depend on provider availability/network/quota; offline `fake` engine is
  the default and requires no external service.
- The single frontend bundle (~846 kB warning) is a code-splitting target; known, not
  a defect.
- Similarity detection is an indicator, not proof of plagiarism; validation and
  findings require human verification.
- Email is optional; failures are logged and never crash the system.
