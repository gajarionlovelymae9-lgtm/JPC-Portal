# JPC Portal â€” Capstone Defense Question & Answer Guide

| | |
| --- | --- |
| **Project** | JPC Portal â€” AI-Driven Employee Progress Monitoring Dashboard Using NLP-Based Algorithm for John Paul College |
| **Version** | v1.0.0 |
| **Purpose** | Prepare the presenters with technically accurate, honest answers for the defense. Every claim below is verifiable in the codebase or tests. |

> Rule for the defense: if a capability does not exist, say so directly and name the
> existing alternative. Never claim features the system does not have.

## A. System & purpose (general)

**Q1. What problem does this system solve?**
JPC leadership has no centralized way to monitor employee progress through annual
plans, targets, tasks, reports, validation, and achievements. Paper/document-based
submissions are hard to track, late reports are hard to surface, review takes time,
and evidence plus AI analysis are not in one place. This system centralizes the whole
workflow, surfaces late/incomplete submissions, automates OCR and NLP-based AI
analysis as assistance, computes performance/KPIs, and reports/audits everything.

**Q2. Who uses it?**
Four roles: Admin (institution), HR (employee data/validation/quality/reporting),
Department Head (department tasks/targets/reports and manual decisions), and Employee
(self-service on own records). Access is enforced by permissions + scope.

**Q3. What are the major modules?**
Identity & RBAC, planning (academic year â†’ annual plan â†’ targets), tasks, reports
with workflow, OCR, AI (summary/validation/similarity insights), performance/KPIs,
achievements/badges, data quality scans, reporting/analytics/exports, AI Chat,
notifications, audit + activity logs.

## B. Architecture & tech stack

**Q4. Describe the architecture.**
React 19 SPA (Vite + TypeScript) talking to a Laravel 12 REST API at `/api/v1`.
Sanctum cookie-based authentication; RBAC via `App\Enums\Permissions` catalog +
policies and service-level scoping; MySQL 8 (`acadtrack_db`); databases queues and a
scheduler for reminders and monthly report generation; server-side OCR/AI; private
file storage; Laravel Resources for API shape.

**Q5. Why this design (SPA + API)?**
Separation of concerns: the API is reusable and testable independently; the React SPA
gives a responsive dashboard experience with role-driven navigation; server-side
processing keeps OCR/AI credentials and business rules off the client.

**Q6. How is data stored/organized?**
~90 migrations covering identity/RBAC, planning, tasks, reports, OCR/AI, chat,
performance, achievements, data quality, exports, notifications, messages, audit.
See `docs/DATABASE.md` and ERD.

**Q7. How do the frontend and backend stay consistent?**
Shared DTO conventions (`ApiResponse<T>`, resource shapes), typed services in
`frontend/src/services`, and parallel test suites (Vitest frontend, PHPUnit backend).

## C. The NLP-based algorithm (signature requirement)

**Q8. Where is the "NLP-based algorithm"? Show it.**
The system treats **report content as natural language** and processes it server-side
in `backend/app/Services/AI`:
- **Summary:** extracts and condenses the natural-language report text via the AI
  engine (default deterministic `fake` engine; optional OpenAI-compatible provider
  `AI_PROVIDER=openai`, `AI_ENDPOINT`), trimming and structuring it.
- **Validation:** matches the text against configured requirements/expectations and
  produces structured findings (present/absent/partial) â€” natural-language
  comparison, not keyword-only.
- **Similarity indicators:** compares the report against reference documents and
  computes an indicator score (an *indicator*, not proof of plagiarism).
- **AI Chat:** natural-language questions are matched to an authorized tool registry
  (categorized questions â†’ tool calls) and answered from the database.
- **Data-quality:** natural-language fields (titles, descriptions, comments) are
  scanned for consistency issues.

**Q9. Does the model learn?**
No. It is a deterministic, rule-driven NLP pipeline by default (fully offline,
reproducible), with an optional pluggable OpenAI-compatible provider. There is no
training loop, model persistence, or learned bias; outcomes are explainable.

**Q10. Why not a trained ML model?**
A trained model is a dependency (data, quota, fees, internet) and is harder to audit
in an academic setting. The rule/NLP-driven approach is deterministic, free, works
offline, and is fully explainable â€” the capstone requirement is a working algorithm,
which this satisfies. Provider-based enhancement is already pluggable.

## D. OCR

**Q11. How does OCR work?**
On attachment, a queued job extracts text server-side. `OCR_PROVIDER=google_vision`
uses the Google Vision REST API against the uploaded file; `OCR_PROVIDER=fake` is the
offline default engine that returns deterministic extracted text. Results are stored
with the report and surfaced in the UI with confidence/status.

**Q12. What if OCR fails?**
The job records failure and the report still works â€” analysis simply shows the upload
status; failures are logged and re-runnable, never crashing the flow.

## E. AI governance & human control

**Q13. Does AI make decisions?**
No. **AI never approves, rejects, or evaluates.** It produces summaries, validation
findings/insights and similarity indicators. A human (Department Head/Admin) reads
the content, adds reviewer comments, and makes the approve/revise/reject decision.
See `docs/AI_GOVERNANCE.md`.

**Q14. How do you prevent AI from leaking or misbehaving?**
AI Chat only calls an **authorized tool registry**, each tool checks the caller's
permissions and scope, and replies are assembled server-side from database results â€”
never raw model output with credentials. Answers are scoped to what the role may see,
and out-of-scope questions are refused. See `docs/SECURITY.md`.

**Q15. How is AI output validated?**
Indicators are bounded; validation items are tied to configured requirements; outputs
are stored with the version they processed. Human review is the required control in
the workflow.

## F. RBAC & security

**Q16. How is access control enforced?**
Two layers: (1) route policies/`can:` middleware using the permission catalog in
`App\Enums\Permissions` (Admin all; HR view-level employee/report/task + analytics;
Department Head department-scoped workflow; Employee own records); (2) **service-level
scoping** so a user with permission still cannot reach another employee's or another
department's records (IDOR protection) â€” e.g. `ResolvesReportingScope`.

**Q17. How did you prevent IDOR (guessing IDs)?**
Every query that loads by model is scope-filtered in the service layer (team/department
or self), so even a valid ID outside the caller's scope returns 404/403. This is
covered by tests.

**Q18. How are secrets handled?**
Everything sensitive (app key, DB credentials, OCR/AI provider keys if configured)
lives in `.env`/server env, which is git-ignored (`.gitignore` hardened against
`*.pem/*.key/auth.json/credentials*`). `git grep` scan is clean; only placeholder
`.env.example` is committed. Dev demo passwords are fictional and overridable.

**Q19. What about XSS/CSRF/SQL injection?**
React escapes by default (no `dangerouslySetInnerHTML` in app code); Sanctum CSRF
token flow protects state-changing requests; all queries use parameterized
query builder/ORM calls; private file storage is served through authorized download
endpoints only.

## G. Performance, reporting, achievements

**Q20. How is performance computed?**
Deterministically from report statuses and target/task data â€” completion rate, on-time
vs late, score breakdowns and progress snapshots are computed from the database for
the selected period, then presented in the performance section and analytics.
Indicators are stable/recomputable, not random.

**Q21. How are achievements awarded?**
Badges/ranks and "employee of the month" are computed from configured criteria and
monthly performance signals by the achievement service; badges are awarded manually
or per configured rules, tracked in the audit trail.

**Q22. What does the management dashboard show?**
Summary KPIs, completion rate, late/pending reports, department comparisons, trends,
rankings, distribution, data-quality status, and top achievers â€” see the 10 prepared
management questions in the presentation flow Part 7.

**Q23. How does reporting/export work?**
Reports are generated server-side (per role scope) and exports are **queued** to
CSV/XLSX/PDF, marked `ready`, **owner-scoped** (a different user cannot download a
foreign export), and expire by TTL. Contains are verified during UAT, not just file
existence.

## H. Notifications & scheduling

**Q24. How do deadline reminders work?**
`routes/console.php` schedules `tasks:send-deadline-reminders` (daily 07:00),
`tasks:send-overdue-notifications` (daily 07:30), `reports:generate-monthly`
(1st of month 06:00), `reports:cleanup-exports` (daily 03:15). In app delivery works
in-app and optionally via mail; email failures are logged.

## I. Testing & quality

**Q25. How is the system tested?**
Backend: **406 tests / 1880 assertions** on an isolated `acadtrack_db_test` (never the
real DB). Frontend: **87 Vitest tests**, oxlint (0 errors), `tsc -b`, production
build. A full regression ran at the end of the project and again during UAT.

**Q26. What were the hardest bugs/lessons?**
(Truthful examples from the project, choose the ones that actually occurred.)
- Laravel picks `.env.testing` when `APP_ENV=testing` even if `.env` has the real key â€”
  stripped `APP_KEY` broke login in a test run; resolved by removing `.env.testing`
  and isolating the test DB entirely via `phpunit.xml`.
- `ReportingService` caching closures silently dropped captured variables â€” fixed by
  explicitly capturing `$period`/`$filters` inside the closure.
- Generalizing the AI-generation workflow left unusable `$name` stubs (e.g.
  `app/Models/$name`) â€” cleaned up; verified nothing referenced them.
- XAMPP ships `mysql.exe` broken on this machine (missing
  `caching_sha2_password.dll`) â€” DB maintenance/validation is performed through
  PHP/PDO instead.

**Q27. What is the state of known limitations?**
Honest list: real AI/OCR depend on provider availability/quota (offline `fake` engine
by default, so the demo is self-contained); OCR accuracy depends on document quality;
similarity is an indicator, not proof; the frontend ships as one ~846 kB chunk
(code-splitting is a future target); email is optional. None blocks core acceptance.

## J. Closing statement template

"JPC Portal automates the employee progress loop â€” plan, target, task, report,
review, achievement â€” with OCR and an NLP-based, explainable AI that assists but
never decides. It is role-secure, audited, fully tested (406 backend + 87 frontend),
and delivered as documentation plus working code with fictional demo data. We
present it as a tested, ready-to-demonstrate academic system with clearly disclosed
limitations and a clear path for future enhancement."

## K. What you must NOT claim at defense

- AI trains itself or learns from data. (It does not.)
- The similarity score is proof of plagiarism. (It is an indicator.)
- Real production deployment happened. (Deployment docs exist; live production
  deployment with real credentials/HTTPS is on the go-live checklist, not done.)
- Credentials shown are real. (Demo accounts are fictional dev-only.)
