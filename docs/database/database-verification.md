# JPC Portal — Phase 3.1 Database Verification

> Phase 3.1-E — Database Foundation Finalization & Verification.
> This document records what was verified against the live `acadtrack_db`, the
> results, and the issues found and fixed during the phase.

## Database

| Item | Value |
| ---- | ----- |
| Database | `acadtrack_db` (MySQL 8, XAMPP, 127.0.0.1:3306) |
| Connection | `mysql` — Laravel boots and runs `migrate:status` (verified) |
| APP_ENV | `local` |
| APP_DEBUG | `true` (development only) |
| Test database | `acadtrack_db_test` (isolated via `phpunit.xml`) |
| Status | **READY** |

## Tables

45 tables total: **36 application tables** + 9 Laravel framework tables
(`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`,
`password_reset_tokens`, `personal_access_tokens`, `migrations`).

All tables defined in `docs/database/schema.sql` exist. No orphan or
unexpected tables found. Application tables:

- **Auth/RBAC:** `users`, `roles`, `permissions`, `role_user`, `permission_role`, `login_activities`
- **Org:** `departments`, `positions`, `employees`, `department_heads`
- **Planning:** `academic_years`, `annual_plans`, `employee_targets`
- **Tasks:** `tasks`, `task_assignments`, `task_references`
- **Reports:** `reports`, `report_versions`, `report_files`, `report_references`
- **OCR:** `ocr_processings`
- **AI:** `ai_analyses`, `ai_summaries`, `ai_validation_results`, `ai_validation_items`, `ai_similarity_results`
- **Review:** `report_reviews`, `report_approvals`
- **Performance:** `employee_progress_snapshots`, `employee_performance_records`
- **Achievements:** `badges`, `employee_badges`, `monthly_achievements`
- **Inbox / Audit:** `notifications`, `audit_logs`, `activity_logs`

## Relationships

59 foreign keys verified via `information_schema` — every relationship in the
design is present, including all AI, review, achievement, performance and
audit links.

Key verified chains:

- `users ← employees.user_id`
- `departments ← employees.department_id`, `positions ← employees.position_id`
- `annual_plans ← tasks.annual_plan_id`
- `tasks ← task_assignments.task_id`, `employees ← task_assignments.employee_id`
- `tasks ← reports.task_id`, `employees ← reports.employee_id`
- `reports ← report_versions.report_id`, `report_versions ← report_files.report_version_id`
- `report_files ← ocr_processings.report_file_id`
- `reports ← ai_analyses.report_id` → `ai_summaries` / `ai_validation_results` (→ `ai_validation_items`) / `ai_similarity_results`
- `reports ← report_reviews.report_id` / `report_approvals.report_id`
- `tasks ← task_references.task_id` → `report_references.report_id` (reference anchoring)
- `employees ← employee_performance_records.employee_id` / `employee_progress_snapshots.employee_id`
- `employees ← monthly_achievements.employee_id`, `badges ← employee_badges.badge_id`
- `users ← notifications.user_id`, `users ← audit_logs.user_id` / `activity_logs.user_id`

Every relationship returned correct records from Eloquent (see Models).

### Cascade strategy (verification result)

- **All accountability tables use `ON DELETE RESTRICT`:**
  `reports`, `report_versions`, `report_files`, `report_reviews`,
  `report_approvals`, all `ai_*` tables, `employee_performance_records`,
  `employee_progress_snapshots`, `monthly_achievements`, `audit_logs`,
  `activity_logs`, `employee_badges`, `task_assignments`, `report_references`,
  `ocr_processings`.
- **Zero `CASCADE` deletions touch protected tables** — deleting an employee,
  task, or department can never silently destroy historical accountability
  records.
- Soft deletes are used where appropriate: `departments`, `positions`,
  `employees`, `annual_plans`, `employee_targets`, `tasks`, `task_references`,
  `reports`, `report_files`, `badges`. Audit/activity/AI/review tables are
  hard-present (never deleted).

## Migrations

- 38 migrations, all **Ran** in batch 1, **0 Pending, 0 Failed**.
- Correct execution order via filename timestamps; no duplicate migrations.
- Foreign keys, indexes, unique constraints, nullable relationships,
  timestamps and soft-delete columns verified present.

## Models

- 34 Eloquent models — every model maps to its expected table (default
  snake_case pluralisation or explicit `$table`).
- Casts correct (`date`, `datetime`, `decimal`, `boolean`, `array`, `hashed`,
  enum casts). `User.$hidden` protects `password` / `remember_token`.
- **Relationship errors: 0**. Verified live: `User::with('roles')`,
  `Employee::with('department','position','performanceRecords',
  'monthlyAchievements','badges')`, `Task::with('assignments.employee',
  'reports')`, `Report::with('employee','task','versions','aiAnalyses',
  'aiSummaries','aiValidationResults','reviews','approvals')`.
- `Employee::badges()` is a `belongsToMany` over `employee_badges`
  (fixed during 3.1-D verification; pivot has timestamps).

## Seeders

- 12 active seeders + 2 Phase-2 wrapper seeders (`RolePermissionSeeder`,
  `UserSeeder`) that delegate.
- `db:seed` is repeatable — repeated runs report 6 dev seeders as
  "skipped": roles, permissions, departments, positions, badges and academic
  year use `updateOrCreate`; employee/plan/task/report/performance/achievement
  seeders guard on existing rows. **Duplicate problems: 0.**
- Clean re-install `migrate:fresh --seed` completes and the application boots.

### Development data (as seeded)

| Metric | Count |
| ------ | ----- |
| Users | 4 (one per role) |
| Employees | 15 |
| Departments | 6 |
| Positions | 11 |
| Academic years | 1 |
| Tasks | 16 |
| Task assignments | 17 |
| Reports | 10 (approved 6 / submitted 3 / rejected 1) |
| Report versions | 12 |
| Reviews / Approvals | 9 / 6 |
| AI analyses | 13 |
| AI validation results | 10 (+2 warning items) |
| AI summaries | 3 |
| Performance records | 45 |
| Monthly achievements | 9 (top-3 × 3 months, rank 1 = employee of the month) |
| Badges / Employee badges | 8 / 16 |
| Notifications | 9 |

Report statuses present: **completed/approved, pending (submitted), late,
rejected, revision** — every future dashboard widget has data.

Task statuses present: **completed, in progress, due/pending submitted,
overdue**. One task ("Submit Monthly Lesson Plan") has two assignees.
`Employee::taskAssignments()` returns 5 for DEV-0004, including completed,
overdue and pending — the employee-progress requirement.

## Security

| Check | Result |
| ----- | ------ |
| Password hashing | `users.password` are bcrypt hashes; no plaintext/short values; seeded dev accounts all hashed via `hashed` cast |
| `.env` protection | `.env` matches `backend/.gitignore` and root `.gitignore`; `git check-ignore` confirms |
| `.env.example` | `DB_PASSWORD` and `SEED_*_PASSWORD` are empty placeholders; no real credentials |
| No real data | Repository scan found no API keys, Google credentials, real phones or personal emails (only test/docs fixtures) |
| Mass assignment | Privileged fields no longer mass-assignable (see below) |
| Audit history protection | `audit_logs`, `activity_logs` are `$guarded = ['*']` write-only models; user-scope audit permissions are not granted to `employee` |
| Employee restrictions | verified `employee` cannot hold `users.delete`, `roles.delete`, `permissions.manage`, `audit_logs.view` |

### Mass-assignment hardening (issue found & fixed in this phase)

Verification (STEP 29) found privileged fields were mass-assignable. They were
removed from `$fillable` (moved to `$guarded`), and the development seeders
were updated to write them explicitly via `forceFill`:

| Model | Guarded fields |
| ----- | -------------- |
| `Report` | `status`, `is_late`, `approved_at`, `rejected_at` |
| `ReportReview` | `decision`, `reviewed_at` |
| `ReportApproval` | `decision`, `approved_at` |
| `EmployeePerformanceRecord` | `overall_score`, `performance_status` |
| `MonthlyAchievement` | `completion_rate`, `on_time_rate`, `approved_reports`, `completed_tasks`, `overall_score`, `rank`, `is_employee_of_month` |
| `EmployeeBadge` | `awarded_at`, `awarded_by` |
| `AiAnalysis` | `status`, `input_hash`, `started_at`, `completed_at`, `error_message` |
| `AiSummary` | `summary`, `key_accomplishments`, `key_findings`, `issues`, `recommendations`, `action_items`, `generated_at` |
| `AiValidationResult` | all scores, `result`, `explanation` |
| `AiValidationItem` | `severity`, `finding`, `evidence`, `recommendation` |
| `AiSimilarityResult` | `similarity_score`, `status`, `explanation` |
| `OcrProcessing` | `status`, `raw_text`, `confidence_score`, `started_at`, `completed_at`, `error_message` |
| `AuditLog`, `ActivityLog` | all (`$guarded = ['*']`) |

After the change the database was rebuilt (`migrate:fresh --seed`) and all
counts/statuses/relationships re-verified; the backend test suite still passes
(30 tests / 110 assertions).

## Verification results (commands run)

| Step | Command | Result |
| ---- | ------- | ------ |
| Connection | `php artisan about` | Database driver `mysql`, env `local` |
| Migrations | `php artisan migrate:status` | 38 Ran / 0 Pending / 0 Failed |
| Tables | `SHOW TABLES` / `information_schema` | 36 app + 9 framework tables |
| Structures | `SHOW CREATE TABLE` equivalents (information_schema) | PK/FK/index/unique/nullable/types/timestamps correct |
| FKs | `information_schema` query | 59 FK constraints, cascade strategy safe |
| Uniques | `information_schema.STATISTICS` | 23 unique indexes, all required `uq_*` present |
| Models | file audit + Eloquent queries | 34 models, tables/casts/soft deletes match |
| Relationships | `with(...)` graph tests | 0 errors |
| Roles/permissions | role/permission queries | correct per role; employee block verified |
| Seeders | `db:seed` × 2 | repeatable, 0 duplicates |
| Clean install | `migrate:fresh --seed` | success |
| Caches | `config:cache`... clears | config/cache/route/view cleared, app boots |
| Code quality | `vendor/bin/pint --test`, `php artisan test` | Pint passed; 30 tests / 110 assertions passed |

## Phase 3.1 status classification

| Area | Status |
| ---- | ------ |
| Migrations | READY |
| Models | READY |
| Seeders | READY |
| Relationships | READY |
| RBAC database | READY |
| Employee database | READY |
| Task database | READY |
| Report database | READY |
| OCR architecture (schema only) | READY (integration NOT STARTED) |
| AI architecture (schema + samples) | READY (engine NOT STARTED) |
| Review/approval structure | READY |
| Performance/Achievement/Badge | READY |
| Notification/Audit | READY |
| React dashboard | NOT STARTED |
| APIs / controllers | NOT STARTED |

## Known limitations

- **No `report_files` / `ocr_processings` rows.** The tables exist with the
  full `processing / completed / failed` and `raw_text / confidence_score`
  structure, but development reports have no physical files so no rows are
  seeded (SEEDING decision from Phase 3.1-D, Step 24). Google Vision is a
  later-phase integration.
- **AI values are static sample data** (provider `jpc.sample`), labelled
  `DEVELOPMENT SAMPLE DATA`. No external provider is ever called from a
  seeder; real AI execution comes in a later phase.
- **`task_references` / `report_references` are empty** (architecture ready,
  exercised by later report submission flow).
- **`employee_progress_snapshots` is empty** (computed materialisation in a
  later phase).
- **`audit_logs` / `activity_logs` are empty** — models and permissions are
  ready; the audit writer service is a later-phase concern.
- Development credentials are intentionally shared fixtures and must never be
  used in production. All password values flow from `SEED_*_PASSWORD`
  environment variables.

## Related documentation

- [`database-design.md`](database-design.md) — design & rules
- [`schema.sql`](schema.sql) — authoritative DDL
- [`eloquent-models.md`](eloquent-models.md) — model map
- [`seeders.md`](seeders.md) — seeder catalog & dataset
- [`erd.md`](erd.md) — entity-relationship diagrams