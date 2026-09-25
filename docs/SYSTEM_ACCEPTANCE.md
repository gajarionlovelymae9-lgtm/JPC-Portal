# JPC Portal â€” System Acceptance

| | |
| --- | --- |
| **Project** | JPC Portal â€” An AI-Driven Employee Progress Monitoring Dashboard Using Natural Language Processing-Based Algorithm for John Paul College |
| **Version Under Test** | v1.0.0 |
| **Testing Date** | 2026-09-23 |
| **Acceptance Type** | Capstone final acceptance + UAT |

> **Do not auto-mark ACCEPTED.** The sections below record the *actual* validation
> performed during Phase 12 plus the results measured at that time. The final
> recommendation is the developer's snapshot; the institutional sign-off (adviser/
> dean + tester signatures) is completed manually and **must** be filled before the
> project is considered formally accepted.

## 1. Scope of acceptance

Authentication & RBAC, employee/department/position/target/task management, report
workflow (draft â†’ submitted â†’ review â†’ approved/revision/rejected), OCR, AI
(summary/validation/similarity/findings/chat), performance/KPIs, achievements,
data-quality, reporting, analytics, exports, notifications, audit trail.

## 2. Environment used for validation

- Backend: Laravel 12, PHP 8.2, MySQL 8 (XAMPP), `acadtrack_db` (dev, MySQL).
- Frontend: React 19, Vite, TypeScript; Vitest; oxlint; `tsc -b`; production build.
- Validation commands (run 2026-09-23) are recorded in Â§4â€“Â§6 and are reproducible.

## 3. Result per area (Phase 12 snapshot)

| Area | Status | Evidence |
| --- | --- | --- |
| Authentication (login/logout, Sanctum) | **PASS** | Auth tests pass; login demonstrated in plan |
| RBAC / role isolation | **PASS (automated)** | Permission catalog verified; role matrix in UAT plan Â§4 mirrors `app/Enums/Permissions.php`; authorization tests pass |
| Employees / departments / positions | **PASS** | CRUD covered by tests + UAT scenarios |
| Tasks / targets / plans | **PASS** | Workflow tests pass |
| Report workflow | **PASS** | State-transition tests pass (draftâ†’â€¦â†’approved/rejected) |
| OCR | **PASS** | Engine tests pass (fake offline default) |
| AI summary/validation/similarity/findings | **PASS** | AI service tests pass; outputs assistive only |
| AI Chat (+ security refusal) | **PASS** | Tool registry + scope tests pass |
| Performance / KPIs | **PASS** | Performance tests pass |
| Achievements / badges | **PASS** | Tests pass; badge seeding verified |
| Data quality | **PASS** | Scans/issues/history tests pass |
| Reporting / analytics | **PASS** | 5 reporting/Analytics test files pass (42 tests) |
| Exports (CSV/XLSX/PDF) | **PASS (schema fixed in this phase)** | Export pipeline tests pass; dev DB missing `report_exports` migration was applied (see Â§6 finding DB-01) |
| Notifications | **PASS** | Preference + delivery tests pass |
| Audit trail | **PASS** | Audit logging tests pass |
| Security negatives | **PASS** | See Â§5 |

> "PASS" above means **automated regression passed** at the Phase 12 snapshot and the
> manual UAT matrix in `docs/UAT_TEST_PLAN.md` is prepared for institutional testers.
> Manual UAT execution by the tester/team is tracked in `docs/UAT_TEST_PLAN.md` Â§14.

## 4. Automated test results (2026-09-23, final regression)

| Suite | Command | Result |
| --- | --- | --- |
| Backend | `php artisan test` | **406 passed, 1880 assertions** (isolated `acadtrack_db_test`) |
| Frontend | `npm test -- --run` | **87 passed** (18 files) |
| Frontend lint | `npm run lint` | **0 errors** (54 pre-existing warnings, accepted) |
| Frontend types | `npx tsc -b` | Clean |
| Production build | `npm run build` | PASS (single 846.22 kB chunk; known warning, code-split planned) |

## 5. Security validation (Phase 12 snapshot)

| Check | Result |
| --- | --- |
| No tracked `.env`, `.pem`, `.key`, credentials files | PASS (git ls-files clean) |
| `git grep` for secrets (API keys, DB/MAIL passwords, demo creds in source) | PASS â€” only placeholders/`.env.example`/documented dev demo defaults; no real values committed |
| No `dangerouslySetInnerHTML` in frontend | PASS |
| No `exec()`/`shell_exec`/`system()`/`passthru`/`proc_open` in app code | PASS |
| RBAC + service-level IDOR protection (reports/exports/performance scoping) | PASS â€” covered by automated tests |
| Sanitized file upload contracts (type/size) | PASS â€” validation tests |
| CSRF via Sanctum, React XSS defaults, parameterized queries | PASS â€” by design + tests |

## 6. Database validation (Phase 12 snapshot)

| Check | Result |
| --- | --- |
| Migrations applied vs files | **Fixed this phase**: 72/73 on dev DB â†’ applied pending `create_report_exports_table` â†’ **73/73, 0 pending** |
| Foreign-key constraints scanned (read-only via PDO) | 128 constraints checked, **0 orphans** |
| Seed data sanity | Roles: admin 1, hr 1, department_head 1, employee 12; task/report status distributions valid |
| `report_exports` table | Exists after migration (test DB always had it). **Finding DB-01 (fixed):** dev DB was missing this additive migration; applied on 2026-09-23. No data loss (migration only creates the empty table). |

## 7. Bugs found & disposition (final triage)

| ID | Severity | Description | Status |
| --- | --- | --- | --- |
| DB-01 | Medium | Dev DB missing `report_exports` (migration pending) â†’ exports would fail on dev | **Fixed** â€” migration applied |
| â€” | Low | Frontend bundle warning (~846 kB) | Accepted limitation (code-split planned) |
| â€” | Low | oc lint 54 warnings (no errors) | Accepted (rule tuning) |

No unresolved Critical or High defect remains. The full regression re-ran after the
DB fix and is green (Â§4).

## 8. Final status (developer snapshot â€” requires sign-off below)

**Recommended status: ACCEPTED WITH LIMITATIONS** â€” subject to the institutional
UAT sheet in `docs/UAT_TEST_PLAN.md` Â§14 and sign-off.

Basis:
- Automated regression fully green (406 backend + 87 frontend; types, lint, build pass).
- DB schema completed and orphan-free; security sweep clean.
- Documented limitations are transparent and non-blocking: offline `fake` AI/OCR by
  default (provider-dependent otherwise), similarity is an indicator not proof, single
  frontend chunk, mail optional.

## 9. Sign-off (to be completed by authorized personnel)

| Role | Name | Status (ACCEPTED / ACCEPTED WITH LIMITATIONS / NOT ACCEPTED) | Signature | Date |
| --- | --- | --- | --- | --- |
| Team Lead / Developer | | | | |
| Adviser | | | | |
| Dean / Evaluator | | | | |
| UAT Tester(s) | | | | |

## 10. References

- UAT matrix & scenarios: `docs/UAT_TEST_PLAN.md`
- Demo flow: `docs/CAPSTONE_PRESENTATION_FLOW.md`, `docs/FINAL_DEMO_SCRIPT.md`
- Defense Q&A: `docs/CAPSTONE_DEFENSE_QA.md`
- Security: `docs/SECURITY.md` Â· AI governance: `docs/AI_GOVERNANCE.md`
- Architecture/DB/deploy: `docs/FINAL_ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/DEPLOYMENT.md`
