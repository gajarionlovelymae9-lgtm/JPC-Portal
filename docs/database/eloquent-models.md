# JPC Portal — Eloquent Models (Phase 3.1-C; updated Phase 3.2-C)

> **Planning & Task Management superseded:** the Phase 3.3-C work
> (`DepartmentPlan`, `DepartmentTarget`, `TaskProgress`, `TaskComment`,
> `TaskAttachment`, `TaskReview`, plus the planning relationships/scopes added to
> `AnnualPlan`, `EmployeeTarget`, `Task`, `TaskAssignment`, `Department` and `User`)
> is documented in [`../models/planning-task-models.md`](../models/planning-task-models.md).
> Where the two differ, the 3.3-C document wins.


All models live in `backend/app/Models/` with namespace `App\Models`.

They map 1:1 to the tables created in Phase 3.1-B and validated against the actual
migrations — the database schema is the source of truth. No models exist for
framework infrastructure tables (`migrations`, `password_reset_tokens`, `sessions`,
`cache*`, `jobs*`, `personal_access_tokens`).

---

## 1. Model Overview

| Model | Table | Soft Deletes |
| --- | --- | --- |
| `User` | `users` | — |
| `Role` | `roles` | — |
| `Permission` | `permissions` | — |
| `Department` | `departments` | yes |
| `Position` | `positions` | yes |
| `Employee` | `employees` | yes |
| `DepartmentHead` | `department_heads` | — |
| `AcademicYear` | `academic_years` | — |
| `AnnualPlan` | `annual_plans` | yes |
| `EmployeeTarget` | `employee_targets` | yes |
| `EmployeeHistory` | `employee_histories` | — |
| `Task` | `tasks` | yes |
| `TaskAssignment` | `task_assignments` | — |
| `TaskReference` | `task_references` | yes |
| `Report` | `reports` | yes |
| `ReportVersion` | `report_versions` | — |
| `ReportFile` | `report_files` | yes |
| `ReportReference` | `report_references` | — |
| `OcrProcessing` | `ocr_processings` | — |
| `AiAnalysis` | `ai_analyses` | — |
| `AiSummary` | `ai_summaries` | — |
| `AiValidationResult` | `ai_validation_results` | — |
| `AiValidationItem` | `ai_validation_items` | — |
| `AiSimilarityResult` | `ai_similarity_results` | — |
| `ReportReview` | `report_reviews` | — |
| `ReportApproval` | `report_approvals` | — |
| `EmployeeProgressSnapshot` | `employee_progress_snapshots` | — |
| `EmployeePerformanceRecord` | `employee_performance_records` | — |
| `Badge` | `badges` | yes |
| `EmployeeBadge` | `employee_badges` | — |
| `MonthlyAchievement` | `monthly_achievements` | — |
| `Notification` | `notifications` | — |
| `AuditLog` | `audit_logs` | — |
| `ActivityLog` | `activity_logs` | — |

`LoginActivity` (`login_activities`, Phase 2) also exists and is preserved unchanged.

---

## 2. Relationship Matrix

| Model | Relationship | Target | Notes |
| --- | --- | --- | --- |
| User | hasOne | Employee | 1:1 via `user_id` |
| User | belongsToMany | Role | pivot `role_user` (`withTimestamps`) |
| User | hasMany | Notification | custom inbox override (see §5) |
| User | hasMany | LoginActivity | Phase 2 |
| User | belongsTo | Role | `role_id` — Phase 2 compat, kept until 3.1-D migration |
| Role | belongsToMany | User | pivot `role_user` |
| Role | belongsToMany | Permission | pivot `permission_role` |
| Permission | belongsToMany | Role | pivot `permission_role` |
| Department | hasMany | Employee | |
| Department | hasMany | DepartmentHead | head history |
| Department | hasMany | AnnualPlan | |
| Department | hasMany | Task | |
| Position | hasMany | Employee | |
| Employee | belongsTo | User | `user_id` |
| Employee | belongsTo | Department | |
| Employee | belongsTo | Position | |
| Employee | belongsTo | Employee (`supervisor`, `supervisor_id`) | self-FK (3.2-C) |
| Employee | hasMany | Employee (`subordinates`, `supervisor_id`) | self-FK inverse (3.2-C) |
| Employee | hasMany | EmployeeHistory | append-only field history (3.2-C) |
| Employee | hasMany | DepartmentHead | head history |
| Employee | hasMany | EmployeeTarget | yearly targets |
| Employee | hasMany | TaskAssignment | |
| Employee | hasMany | Report | |
| Employee | hasMany | EmployeeProgressSnapshot | |
| Employee | hasMany | EmployeePerformanceRecord | |
| Employee | hasMany | EmployeeBadge | |
| Employee | hasManyThrough | Badge | via `employee_badges` |
| Employee | hasMany | MonthlyAchievement | |
| EmployeeHistory | belongsTo | Employee | (3.2-C) |
| EmployeeHistory | belongsTo | User (`changedBy`, `changed_by`) | nullable, SET NULL (3.2-C) |
| DepartmentHead | belongsTo | Department | |
| DepartmentHead | belongsTo | Employee | |
| AcademicYear | hasMany | AnnualPlan, EmployeeTarget, EmployeeProgressSnapshot, EmployeePerformanceRecord, EmployeeBadge, MonthlyAchievement | |
| AnnualPlan | belongsTo | AcademicYear | |
| AnnualPlan | belongsTo | Department | |
| AnnualPlan | belongsTo | User (`creator`, `created_by`) | |
| AnnualPlan | belongsTo | User (`approver`, `approved_by`) | |
| AnnualPlan | hasMany | EmployeeTarget, Task | |
| EmployeeTarget | belongsTo | AnnualPlan | |
| EmployeeTarget | belongsTo | Employee | |
| Task | belongsTo | AnnualPlan | nullable |
| Task | belongsTo | Department | nullable |
| Task | belongsTo | User (`creator`, `created_by`) | |
| Task | hasMany | TaskAssignment | task → many employees |
| Task | hasMany | TaskReference | |
| Task | hasMany | Report | |
| TaskAssignment | belongsTo | Task | |
| TaskAssignment | belongsTo | Employee | |
| TaskAssignment | belongsTo | User (`assigner`, `assigned_by`) | |
| TaskReference | belongsTo | Task | |
| TaskReference | hasMany | ReportReference | used by AI reference validation |
| Report | belongsTo | Task | |
| Report | belongsTo | Employee | explicit submitter |
| Report | hasMany | ReportVersion, ReportFile, ReportReference | |
| Report | hasMany | AiAnalysis, AiSummary, AiValidationResult, AiSimilarityResult | AI layer (advisory) |
| Report | hasMany | ReportReview, ReportApproval | human layer |
| ReportVersion | belongsTo | Report | |
| ReportVersion | belongsTo | User (`submitter`, `submitted_by`) | |
| ReportVersion | hasMany | ReportFile | |
| ReportFile | belongsTo | Report, ReportVersion, User (`uploader`, `uploaded_by`) | |
| ReportFile | hasMany | OcrProcessing | |
| ReportReference | belongsTo | Report, TaskReference | |
| OcrProcessing | belongsTo | ReportFile | |
| AiAnalysis | belongsTo | Report | |
| AiAnalysis | hasMany | AiSummary, AiValidationResult, AiSimilarityResult | |
| AiSummary | belongsTo | Report, AiAnalysis | |
| AiValidationResult | belongsTo | Report, AiAnalysis | |
| AiValidationResult | hasMany | AiValidationItem | |
| AiValidationItem | belongsTo | AiValidationResult | |
| AiSimilarityResult | belongsTo | Report (`report_id`) | |
| AiSimilarityResult | belongsTo | Report (`compared_report_id`) | |
| ReportReview | belongsTo | Report, User (`reviewer`, `reviewer_id`) | |
| ReportApproval | belongsTo | Report, User (`approver`, `approver_id`) | |
| EmployeeProgressSnapshot | belongsTo | Employee, AcademicYear | |
| EmployeePerformanceRecord | belongsTo | Employee, AcademicYear | |
| Badge | hasMany | EmployeeBadge | |
| Badge | belongsToMany | Employee | pivot `employee_badges` (3.2-C inverse) |
| EmployeeBadge | belongsTo | Employee, Badge, AcademicYear, User (`awarder`, `awarded_by`) | |
| MonthlyAchievement | belongsTo | Employee, AcademicYear | |
| Notification | belongsTo | User | custom inbox |
| AuditLog | belongsTo | User | |
| AuditLog | morphTo | entity | `entity_type` / `entity_id` |
| ActivityLog | belongsTo | User | |
| ActivityLog | morphTo | subject | `subject_type` / `subject_id` |

---

## 3. Query Scopes

| Model | Scope | Behavior |
| --- | --- | --- |
| Department / Position | `active()` | `status = 'active'` |
| Employee | `active()` | `employment_status = 'active'` |
| Employee | `inactive()` | `employment_status != 'active'` (3.2-C) |
| Employee | `byDepartment($id)` / `byPosition($id)` | FK filter |
| Employee | `ofEmploymentType($type)` | `employment_type` filter (3.2-C) |
| AcademicYear | `active()` / `closed()` | `status` filter |
| EmployeeTarget | `active()` / `completed()` | `status` filter |
| Task | `pending()` / `inProgress()` / `completed()` | lifecycle `status` filter |
| Task | `overdue()` | `due_date < today`, `completed_at IS NULL`, not completed/cancelled — index-friendly (`due_date` / `status` indexed), **no** per-row expression |
| Task | `byDepartment($id)` | `department_id` filter |
| TaskAssignment | `completed()` / `pending()` / `overdue()` | same semantics as Task |
| TaskAssignment | `forEmployee($id)` | `employee_id` filter |
| Report | `draft()` / `submitted()` / `pendingReview()` / `pending()` / `approved()` / `rejected()` | lifecycle `status` filter |
| Report | `late()` | `is_late = true` (authoritative flag) |
| OcrProcessing / AiAnalysis | `completed()` / `failed()` / `processing()` | `status` filter |
| AiValidationResult | `passed()` / `warning()` / `failed()` | `result = pass|warning|fail` (advisory) |
| AiValidationItem | `critical()` / `warning()` | `severity` filter (advisory) |
| EmployeePerformanceRecord | `monthly()` / `quarterly()` / `yearly()` / `atRisk()` | `period_type` / `performance_status = at_risk` |
| Badge | `active()` | `is_active = true` |
| DepartmentHead | `current()` | `is_current = true` |
| MonthlyAchievement | `forMonth($m, $y)` / `employeeOfMonth()` / `topRanked()` | month/year filter, EOM flag, rank ordering |
| Notification | `unread()` / `read()` | `read_at` NULL / not NULL |

---

## 4. Security

- **Hidden from serialization** — `User::$hidden = ['password', 'remember_token']`
  stays in place; `ReportFile` hides internal storage details
  (`stored_filename`, `storage_disk`, `file_path`, `checksum`).
- **Mass assignment** — every model declares an explicit `$fillable` whitelist.
  Privileged value columns (approval/review decisions, report status verdicts,
  performance/achievement scores and ranks, badge award grantors, AI results,
  OCR raw text, audit records) are **not** mass-assignable — they live in
  `$guarded` and may only be written explicitly via `forceFill` by internal
  service/seeder code (hardened in Phase 3.1-E; see
  [`database-verification.md`](database-verification.md)). `AuditLog` and
  `ActivityLog` are fully `$guarded = ['*']` write-only models.
- **Approval history** (`report_approvals`) is treated as immutable history —
  normal flows append rows, never modify them.
- **AI is advisory** — `AiValidationResult` / `AiValidationItem` /
  `AiSimilarityResult` never mutate `reports.status`. Only human
  `ReportReview` / `ReportApproval` drive final status, enforced by services later.
- User auth credentials remain only on `users`; `Employee` carries no password data.

## 5. Casts & Deviations

- Dates (`date`) on: `Employee.date_of_birth/date_hired/date_ended`, `DepartmentHead.start_date/end_date`,
  `AcademicYear`, `EmployeeTarget`, `EmployeeHistory.effective_date`, `Task.start_date/due_date`, `TaskAssignment`,
  `Report.submission_date/deadline`, `EmployeeProgressSnapshot.snapshot_date`,
  `EmployeePerformanceRecord.period_*`, `EmployeeBadge.period_start/period_end`.
- Datetimes (`datetime`) on: `User.last_login_at/email_verified_at`, `AnnualPlan.approved_at`,
  `Task/TaskAssignment.completed_at`, `TaskAssignment.assigned_at`, `Report.submitted_at/
  approved_at/rejected_at`, `ReportVersion.submitted_at`, `OcrProcessing`
  `started_at/completed_at`, `AiAnalysis` `started_at/completed_at`, `AiSummary.generated_at`,
  `ReportReview.reviewed_at`, `ReportApproval.approved_at`, `EmployeeBadge.awarded_at`,
  `Notification.read_at`.
- Booleans (`boolean`): `DepartmentHead.is_current`, `Report.is_late`, `Badge.is_active`,
  `MonthlyAchievement.is_employee_of_month`.
- JSON (`array`): `LoginActivity.meta`, `Badge.criteria_value`, `AiSummary.*` JSON fields,
  `EmployeeBadge.criteria_snapshot`, `Notification.data`, `AuditLog.old_values/new_values`,
  `ActivityLog.metadata` — cast **only** because the columns are JSON.
  The design doc (3.1-A) intentionally keeps `AiSummary` prose fields (`summary`,
  `key_accomplishments`, etc.) uncast where columns are `LONGTEXT`.
- Decimals (`decimal:N,2` / `decimal:4`) on monetary/target columns and all
  rate/score columns, matching column precision `5,2`/`5,4`/`12,2`.
- **`User::notifications()`** deliberately overrides the `Notifiable` trait's
  morphMany (Phase 3.1-B deviation): the `notifications` table is a custom
  user-facing inbox (`user_id`, `type`, `title`, `message`, `data`, `read_at`),
  not Laravel's database-notification schema.
- **`Report::aiSimilarityResults()`** is `hasMany(AiSimilarityResult::class, 'report_id')`
  because the similarity table has two FKs to `reports`.
- `scopeOverdue()` uses plain `where('due_date', '<', ...)` instead of
  `whereDate()` so the `due_date` index is usable on dashboards.
- **Decimal cast syntax fixed (3.2-C):** `EmployeeTarget.target_value` and
  `Task.target_value` previously used `'decimal:12,2'` (invalid — Laravel's `decimal`
  cast only accepts a single scale arg), which threw at runtime when the attribute
  was read. Corrected to `'decimal:2'` (matches `DECIMAL(12,2)` columns).
- **`Employee::full_name`** is an `Attribute` accessor — computed from
  `first/middle/last/suffix`, empty parts skipped (no trailing whitespace). Not a
  database column, and not appended to array/JSON serialization automatically.

## 6. Purpose & Design Rules Preserved

- Task → many employees: `Task -> assignments()` (`TaskAssignment`) → `Employee`.
  No `Task::employee()` shortcut was created.
- A `Report` explicitly knows `task()` and `employee()`; it never infers the
  submitter from task assignments (needed for per-employee progress/achievements).
- AI results are fully separated from human review; `Report` exposes both trees.
- No accessors run queries; no performance math lives in models (later: Services,
  scheduled jobs, analytics layer).
- **Employee-management relationships (3.2-C):** `Employee.supervisor()/subordinates()`
  self-FK; `Employee.histories()` → append-only `EmployeeHistory`; inverse
  `Badge.employees()`. No automatic eager loading (`$with`) is set on `Employee`.
- **Naming:** the Employee→targets relationship is `employeeTargets()` (Phase 3.1-C),
  not `targets()`; `EmployeeTarget` uses `title`/`target_unit` (schema columns) as the
  `target_name`/`unit` spec mapping. No duplicate `targets()` alias was added.
- `EmployeeHistory` has **no SoftDeletes** (table has no `deleted_at`) — history is
  append-only by design.