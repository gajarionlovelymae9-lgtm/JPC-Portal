# JPC Portal — Database Design (Phase 3.1-A)

| Item | Value |
| ---- | ----- |
| Database | `acadtrack_db` |
| Engine | MySQL 8 / MariaDB ≥ 10.4 (XAMPP local) |
| Storage engine | InnoDB (all tables — FK support, row-level locking, crash-safe) |
| Charset / Collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| Schema source | [`docs/database/schema.sql`](schema.sql) (canonical, executable) |
| ERD | [`docs/database/erd.md`](erd.md) |

---

## 1. Purpose

The relational foundation for the **JPC Portal**: an AI-driven employee progress
monitoring dashboard for John Paul College. The schema supports authentication/RBAC,
organization, academic-year planning, targets, tasks, reports, OCR, AI analysis,
review/approval, performance, achievements, notifications, and auditing — while
keeping human-authored data strictly separate from AI-generated analysis and
human decisions.

---

## 2. Database Groups & Table Map (36 tables)

| # | Table | Group | Purpose (1 line) |
| -- | ----- | ----- | ---------------- |
| 1 | `users` | 1 Auth/RBAC | Login accounts only (status, last_login). |
| 2 | `roles` | 1 Auth/RBAC | System roles (`admin`, `hr`, `department_head`, `employee`). |
| 3 | `permissions` | 1 Auth/RBAC | Granular permission catalog (single source of truth). |
| 4 | `role_user` | 1 Auth/RBAC | users ↔ roles (many-to-many). |
| 5 | `permission_role` | 1 Auth/RBAC | roles ↔ permissions (many-to-many). |
| 6 | `departments` | 2 Organization | Institutional departments (soft-delete). |
| 7 | `positions` | 2 Organization | Employee positions (soft-delete). |
| 8 | `employees` | 2 Organization | Employee profiles, linked to users/departments/positions (soft-delete). |
| 9 | `department_heads` | 2 Organization | Historical leadership assignments (current = 1/dept). |
| 10 | `employee_histories` | 2 Organization | Append-only profile field change history (Phase 3.2-B). |
| 10 | `employee_histories` | 2 Organization | Append-only profile field change history (Phase 3.2-B). |
| 11 | `academic_years` | 3 Planning | Planning periods (`2026-2027`). |
| 12 | `annual_plans` | 3 Planning | Department/institutional annual plans (soft-delete). |
| 13 | `employee_targets` | 3 Planning | Employee yearly targets (soft-delete). |
| 14 | `tasks` | 4 Tasks | Task records with lifecycle status (soft-delete). |
| 15 | `task_assignments` | 4 Tasks | Per-employee assignments, independent progress. |
| 16 | `task_references` | 4 Tasks | References per task (for AI validation) (soft-delete). |
| 17 | `reports` | 5 Reports | Employee report records (soft-delete). |
| 18 | `report_versions` | 5 Reports | Immutable revision history (v1, v2, …). |
| 19 | `report_files` | 5 Reports | File metadata; binaries in secure storage (soft-delete). |
| 20 | `report_references` | 5 Reports | References anchored to a submitted report. |
| 21 | `ocr_processings` | 6 OCR | OCR job tracking; extracted text ≠ original file. |
| 22 | `ai_analyses` | 7 AI | AI execution log (analysis type, provider, model, status). |
| 23 | `ai_summaries` | 7 AI | AI-generated summaries (marked as machine output). |
| 24 | `ai_validation_results` | 7 AI | Advisory AI scores + result (pass/warning/fail). |
| 25 | `ai_validation_items` | 7 AI | Granular AI findings (category, severity). |
| 26 | `ai_similarity_results` | 7 AI | Similarity flags for review (never an auto verdict). |
| 27 | `report_reviews` | 8 Review | Manual review actions (initial/revision/final). |
| 28 | `report_approvals` | 8 Review | Formal approval/rejection history (never deleted). |
| 29 | `employee_progress_snapshots` | 9 Performance | Frozen point-in-time snapshots for trends. |
| 30 | `employee_performance_records` | 9 Performance | Calculated scores per period (monthly/quarterly/yearly). |
| 31 | `badges` | 10 Achievements | Badge catalog (soft-delete). |
| 32 | `employee_badges` | 10 Achievements | Awarded badges with criteria snapshot (never deleted). |
| 33 | `monthly_achievements` | 10 Achievements | Monthly ranking / employee-of-month. |
| 34 | `notifications` | 11 Notifications | Per-user notifications. |
| 35 | `audit_logs` | 12 Auditing | Important change history (JSON diffs). |
| 36 | `activity_logs` | 12 Auditing | Broad user/system activity stream. |

---

## 3. Naming Conventions

- `snake_case` everywhere: tables (plural nouns), columns, indexes, foreign keys.
- Primary key: `id` (`BIGINT UNSIGNED AUTO_INCREMENT`).
- Foreign keys: `<entity>_id`; constraint names `{table}_{column}_foreign`
  (Laravel-compatible so Phase 3.1-B migrations match this file).
- Unique keys: `uq_{table}_{cols}`. Indexes: `idx_{table}_{cols}`.
- Boolean columns: `TINYINT(1)` (also used as flag-style columns like `is_late`,
  `is_current`, `is_active`).
- Dates: `DATE` for calendar dates; `TIMESTAMP`/`DATETIME` for instants.
- All tables carry `created_at` / `updated_at` (`TIMESTAMP NULL`) for Laravel.

---

## 4. Important Relationships

**Auth/RBAC**
- `users` ← `role_user` → `roles`; `roles` ← `permission_role` → `permissions`.
- A user may hold many roles; a role holds many permissions. Permissions are the
  granular gate used by middleware.

**Organization**
- `departments` 1—N `employees` (Rule 1: an employee belongs to a department —
  `department_id NOT NULL`).
- `positions` 1—N `employees`.
- `users` 1—0..1 `employees` (`employee.user_id UNIQUE`, nullable for historical
  employees without logins; Rule 2).
- `departments` 1—N `department_heads` (1—N `employees`) with a generated stored
  column enforcing at most one **current** head per department.

**Planning**
- `academic_years` 1—N `annual_plans` 1—N `tasks` and 1—N `employee_targets`.
- `employees` 1—N `employee_targets`.

**Tasks**
- `tasks` 1—N `task_assignments` (Rule 3: many employees per task; Rule 4: each
  assignment carries its own `progress_percentage`, `status`, `completed_at`).
- `tasks` 1—N `task_references`.

**Reports**
- `tasks` 1—N `reports`, `employees` 1—N `reports` (Rule 5).
- `reports` 1—N `report_versions` 1—N `report_files` (Rules 6 & 7).
- `report_files` 1—N `ocr_processings` (Rule 8).
- `reports` 1—N `report_references` (anchors to `task_references`).

**AI**
- `reports` 1—N `ai_analyses` 1—N `ai_summaries` and 1—N `ai_validation_results`
  1—N `ai_validation_items`.
- `reports` 1—N `ai_similarity_results` (self-referential: `compared_report_id`
  also → `reports.id`).

**Review & Approval**
- `reports` 1—N `report_reviews` (1—N `users`).
- `reports` 1—N `report_approvals` (1—N `users`). Never deleted (Rule 11).

**Performance & Achievements**
- `employees` & `academic_years` → snapshots, performance records, employee_badges,
  monthly_achievements. `badges` 1—N `employee_badges`.

**Notifications, Audit, Activity**
- `users` 1—N `notifications`, `audit_logs`, `activity_logs`.
- `audit_logs.entity_type/entity_id` and `activity_logs.subject_type/subject_id` are
  polymorphic references with NO FK (by design — see §8).

---

## 5. Foreign Key / Delete Behaviour Policy

Rule: **RESTRICT by default** to protect academic and audit history; **CASCADE only
on pure link tables** and throwaway user data.

| Behaviour | Applied to | Rationale |
| --------- | ---------- | --------- |
| `ON DELETE CASCADE` | `role_user`, `permission_role` | Pure links; meaningless without parent. |
| `ON DELETE CASCADE` | `notifications` | User deletion removes their inbox. |
| `ON DELETE RESTRICT` | Everything else | Preserves reports, versions, reviews, approvals, audits, achievements, assignments, progress. |

**Note on soft deletes:** soft-deleted rows are never physically removed, so FK
constraints remain satisfied when records are "deleted" through the API.

`ON UPDATE CASCADE` is applied uniformly.

---

## 6. Unique Constraints

| Table | Constraint | Enforces |
| ----- | ---------- | -------- |
| `users` | `uq_users_email` | No duplicate login emails |
| `roles` | `uq_roles_name` | Unique role name |
| `permissions` | `uq_permissions_name` | Unique permission name |
| `role_user` | `uq_role_user (role_id, user_id)` | No duplicate role assignments |
| `permission_role` | `uq_permission_role (permission_id, role_id)` | No duplicate permission assignments |
| `departments` | `uq_departments_name`, `uq_departments_code` | Unique name/code |
| `positions` | `uq_positions_name`, `uq_positions_code` | Unique name/code |
| `employees` | `uq_employees_employee_number`, `uq_employees_user_id` | No duplicate employee numbers; one account per employee |
| `department_heads` | `uq_department_heads_current` (generated) | One current head per department |
| `academic_years` | `uq_academic_years_name` | Unique year label |
| `task_assignments` | `uq_task_assignments (task_id, employee_id)` | No duplicate task assignments |
| `report_versions` | `uq_report_versions (report_id, version_number)` | No duplicate version numbers per report |
| `ai_similarity_results` | `uq_ai_similarity_results (report_id, compared_report_id)` | No duplicate comparison pairs |
| `employee_performance_records` | `uq_employee_performance_records_period` | One record per employee-period |
| `badges` | `uq_badges_code`, `uq_badges_name` | Unique badge code/name |
| `employee_badges` | `uq_employee_badges_award` | No duplicate badge awards |
| `monthly_achievements` | `uq_monthly_achievements_period` | One record per employee-month |

---

## 7. Index Strategy

Every index has a reason (authentication, filtering, dashboards, reports, tasks,
status/date lookups). Indexes were **not** added to every column — status/priority
low-cardinality columns are indexed only when they are genuine filter keys.

| Table | Index | Reason |
| ----- | ----- | ------ |
| `users` | `email` (UNIQUE), `status` | Login lookup; account-status filtering |
| `employees` | `employee_number` (UQ), `user_id` (UQ), `department_id`, `position_id`, `employment_status`, `last_name` | Lookup by number/account; org + name filters |
| `departments` / `positions` | `status` | Active/inactive listing |
| `department_heads` | `department_id`, `employee_id`, `is_current` | History + current queries |
| `academic_years` | `status`, `start_date` | Active-year lookup; calendar browse |
| `annual_plans` | `academic_year_id`, `department_id`, `status`, `created_by`, `approved_by` | Plan browsing and ownership |
| `employee_targets` | `annual_plan_id`, `employee_id`, `status`, `(employee_id, status)` | Target dashboards |
| `tasks` | `annual_plan_id`, `department_id`, `created_by`, `status`, `due_date`, `priority`, `(department_id, status)` | Task filtering, dashboards, overdue queries |
| `task_assignments` | `(task_id, employee_id)` UQ, `employee_id`, `status`, `assigned_by`, `due_date` | Assignee workload; per-employee queues |
| `task_references` | `task_id`, `reference_type` | Reference lookup |
| `reports` | `task_id`, `employee_id`, `status`, `submitted_at`, `is_late`, `deadline`, `(employee_id, status)` | Report queues, timeliness, dashboards |
| `report_versions` | `(report_id, version_number)` UQ, `submitted_by` | Version history |
| `report_files` | `report_id`, `report_version_id`, `uploaded_by` | File listing |
| `report_references` | `report_id`, `task_reference_id` | AI reference alignment |
| `ocr_processings` | `report_file_id`, `status` | Job queue |
| `ai_analyses` | `report_id`, `analysis_type`, `status` | Analysis history/filtering |
| `ai_summaries` | `report_id`, `ai_analysis_id` | Summary lookup |
| `ai_validation_results` | `report_id`, `ai_analysis_id`, `result` | Results + audit |
| `ai_validation_items` | `ai_validation_result_id`, `category` | Drill-down findings |
| `ai_similarity_results` | `(report_id, compared_report_id)` UQ, `compared_report_id` | Pair lookups |
| `report_reviews` | `report_id`, `reviewer_id`, `review_type` | Review trail |
| `report_approvals` | `report_id`, `approver_id` | Approval trail |
| `employee_progress_snapshots` | `employee_id`, `academic_year_id`, `snapshot_date`, `(employee_id, snapshot_date)` | Trend queries |
| `employee_performance_records` | `(employee, year, period…)` UQ, `academic_year_id`, `period_start` | Period scoring |
| `badges` | `code` (UQ), `name` (UQ), `is_active` | Catalog |
| `employee_badges` | `badge_id`, `academic_year_id`, `awarded_by`, award UQ | Award history |
| `monthly_achievements` | period UQ, `(academic_year_id, month, year)`, `is_employee_of_month` | Monthly rankings |
| `notifications` | `(user_id, created_at)`, `type`, `read_at` | Inbox queries |
| `audit_logs` | `(entity_type, entity_id)`, `action`, `created_at`, `(user_id, created_at)` | Audit lookups |
| `activity_logs` | `(user_id, created_at)`, `activity_type`, `(subject_type, subject_id)` | Activity feed |

FK columns rely on the explicitly declared indexes above (the DB reuses them instead
of auto-creating unnamed ones).

---

## 8. Design Decisions

1. **VARCHAR instead of MySQL ENUM.**
   All status/priority/type columns use `VARCHAR` with a documented allowed set
   (e.g. `users.status`: active/inactive/suspended). Rationale: Laravel enum casting
   already enforces the set at the app layer; future additions do not require
   `ALTER TABLE` of the column type; avoids DB-vendor quirks. The allowed values are
   listed in `schema.sql` column comments.

2. **JSON used only where structured-flexible data is genuinely required:**
   `notifications.data`, `audit_logs.old_values/new_values`,
   `activity_logs.metadata`, `employee_badges.criteria_snapshot`,
   `badges.criteria_value`, `ai_summaries.*_items`. Relational fields are used
   wherever that is the correct model.

3. **`users.role_id` reconciliation (Phase 2 → 3).** Phase 2 used a single
   `users.role_id` column plus a `permissions` table with `slug`/`module`. The
   canonical Phase 3 model uses the `role_user` junction (many-to-many) and
   `permissions(name, display_name, description)`. Phase 3.1-B will migrate the
   existing 4 users/roles (admin, hr, department_head, employee) into `role_user` and
   drop `users.role_id`. **No application code changes are made in 3.1-A.**

4. **Derived/low-cost fields are stored deliberately:** `reports.is_late` and
   `reports.version` (current version) are maintained by the application for cheap
   reporting, while historical truth lives in `report_versions`.

5. **Percentages as `DECIMAL(5,2)` (0.00–100.00);** single-value scores as
   `DECIMAL(5,4)` (0.0000–1.0000). Readable and unambiguous.

6. **Single active academic year** is an application-level rule (spec permits
   exceptions). No DB constraint enforces it.

7. **One current department head per department** is enforced with a stored
   generated column `current_department_id = IF(is_current, department_id, NULL)`
   plus a UNIQUE index (MySQL 8 and MariaDB 10.2+ both support this). Historical
   rows keep `is_current = 0` → `NULL`, unconstrained.

8. **Polymorphic log references (`audit_logs`, `activity_logs`)** have no FK by
   design; they can reference any entity. Integrity is application-enforced and the
   `entity_type` records the model class.

9. **File storage:** binaries are never written to MySQL. `report_files` stores
   metadata + a SHA-256 `checksum`; the file lives on a disk/object storage disk.

10. **AI separation (3-tier model):**
    - Human-authored content: `reports`, `report_versions`, `report_files`.
    - AI-generated output: `ocr_processings`, `ai_summaries`, `ai_validation_results`,
      `ai_validation_items`, `ai_similarity_results` — always tied to an
      `ai_analyses` execution record (provider, model, input hash, timestamps).
    - Human decisions: `report_reviews`, `report_approvals`, `report.status`.
    AI `result` (`pass|warning|fail`) and similarity `status` are **advisory flags**;
    final approval lives only in `report_approvals`/`reports.status` (Rules 9 & 10).

11. **Do not redesign `login_activities`/`sanctum` tables here.** The Phase 2
    `login_activities` table and Laravel’s `personal_access_tokens` (if used) remain
    outside this schema; they are reconciled during the migration phase.

---

## 9. Soft-Delete Strategy

Soft deletes (`deleted_at`) apply to master/work records where "delete" means
"hide from active workflows":

`departments`, `positions`, `employees`, `annual_plans`, `employee_targets`,
`tasks`, `task_references`, `reports`, `report_versions`, `report_files`, `badges`.

Hard deletes are **prohibited** for historical/audit rows:

`report_versions` (already immutable), `report_reviews`, `report_approvals`,
`ai_*` records, `employee_progress_snapshots`, `employee_performance_records`,
`employee_badges`, `monthly_achievements`, `audit_logs`, `activity_logs`,
`login_activities`. These tables, plus snapshots and approval history, intentionally
have **no `deleted_at`** column.

Soft deletion is a UI/UX mechanism — it must never replace `audit_logs` /
`report_approvals` / `report_reviews` which are the true historical records.

---

## 10. Historical Data Strategy

- **Report history:** every submission is a new `report_versions` row; `reports.version`
  points at the latest. Originals are never overwritten.
- **Leadership history:** `department_heads` keeps every assignment period.
- **Performance history:** snapshots are frozen; recalculations update their own
  performance-record row instead of rewriting history.
- **Approvals/reviews/audits:** append-only.
- **Timestamps:** standard `created_at`/`updated_at`, plus domain timestamps
  (`submitted_at`, `approved_at`, `completed_at`, `reviewed_at`, `awarded_at`) and
  period columns (`period_start`, `period_end`, `academic_year_id`) for range queries.

---

## 11. Security Considerations

Prevent these integrity failures:
- Duplicate employee numbers → `uq_employees_employee_number`
- Duplicate user emails → `uq_users_email`
- Duplicate role assignments → `uq_role_user`
- Duplicate permission assignments → `uq_permission_role`
- Duplicate task assignments → `uq_task_assignments`
- Duplicate report versions → `uq_report_versions`
- Invalid foreign keys → all FKs `RESTRICT`/`CASCADE` as documented in §5

Do **not** store in normal tables: plain-text passwords (bcrypt hash in `users.password`),
API keys, AI/OCR provider secrets (environment variables only). Passwords are never
serialized by any API resource.

`password`, `remember_token` are the only auth secrets; `employees` stores only
profile data (no TIN/national-ID/salary).

---

## 12. Validation Performed

The full `schema.sql` was executed against an isolated scratch database on the local
MySQL-compatible server, then inspected via `information_schema`:

- 35 tables created in dependency order (no FK ordering errors).
- All 35 tables use `InnoDB` + `utf8mb4_unicode_ci`.
- All foreign keys reference existing tables/PK columns (no invalid references).
- No duplicate index names or duplicate foreign key names.
- Unique constraints present for every required uniqueness rule (§6).
- Generated-column UNIQUE (`department_heads`) accepted by the server.

See the Phase 3.1-A report for exact counts (tables, foreign keys, indexes).

---

## 13. Phase 3.1-B — Laravel 12 Migration Notes & Deviations

All 35 tables now exist via Laravel migrations
(`backend/database/migrations/2026_09_14_000010_*` to `2026_09_14_000040_*`).
Phase 1/2 migrations were **preserved unchanged**. Verified against the live
`acadtrack_db`: all 35 target tables present (45 total incl. Laravel framework
tables), 59 foreign keys, 23 unique constraints, `department_heads` generated
column present, soft deletes + timestamps as designed.

**Deviations and implementation notes (Phase 3.1-B):**

1. **Existing column names kept (no rename).** Phase 2 tables already existed with
   `roles.label` (≈ `display_name`) and `permissions.label` + `permissions.group`
   (≈ `display_name` + grouping). No technical conflict; renamed columns would have
   broken Phase 2 seeders/middleware. Models in 3.1-C will map to current names.
2. **`users.role_id` retained.** Phase 2 uses a single `roles` FK (`ON DELETE SET
   NULL`). `role_user` is created empty now; the data backfill and eventual
   retirement of `role_id` is part of 3.1-C/D.
3. **`login_activities.event`** (login/failed/logout) kept over the 3.1-A
   `status` naming. The migration system uses Laravel's own naming; no rename done.
4. **`department_heads.current_department_id`** generated column was added with
   `DB::statement()` inside the migration because Laravel's Blueprint has no
   portable builder API for MySQL/MariaDB `GENERATED ALWAYS AS ... STORED`
   columns. A `DB::statement()` with the `IF(is_current, department_id, NULL)`
   expression and a manual unique index recreates the 3.1-A constraint exactly.
5. **`monthly_achievements` unique index** has an explicit name
   (`uq_monthly_achievements_period`) — the Laravel-generated
   `…_employee_id_academic_year_id_month_year_unique` exceeded MySQL's
   64-character identifier limit (fixed after first migrate attempt; the
   partially-created table was dropped and recreated).
6. **Test isolation fix (important).** `phpunit.xml` previously left the sqlite
   `:memory:` lines commented, so `RefreshDatabase` fatally ran `migrate:fresh`
   against the **development** database, wiping seeded data on every `php artisan
   test`. Now set to `<env name="DB_DATABASE" value="acadtrack_db_test"/>` (a
   dedicated, auto-migrated MySQL test database). The development database is no
   longer touched by the test suite. This was discovered during 3.1-B when
   `users`/`roles`/`permissions` returned 0 after a test run; data was reseeded.
7. **Soft deletes & nullable timestamps** match 3.1-A exactly (10 soft-deletable
   tables; `report_versions`/reviews/approvals/AI log tables deliberately have no
   `deleted_at`).
8. **FK delete rules** match 3.1-A: `RESTRICT` for all historical/domain
   relationships, `CASCADE` only for `role_user`, `permission_role`, and
   `notifications(user_id)`.

---

## 14. Phase 3.2-B — Employee Management Migration Notes & Deviations

Phase 3.2-A design was implemented with two Laravel 12 migrations
(`2026_09_14_060000_*`, `2026_09_14_060001_*`) that sort after all Phase 3.1
migrations. No Phase 3.1 migration was modified; the existing tables remain the
source of truth. `schema.sql` has been updated to the Phase 3.2 target.

**Changes applied:**

1. **`employees` (ALTER only, migration `060000`).** Additive + one rename:
   - ADD `employment_type VARCHAR(20) NOT NULL DEFAULT 'regular'`
     (`regular | probationary | part_time | contractual | temporary`).
   - ADD `date_of_birth DATE NULL`, `gender VARCHAR(30) NULL`
     (`female | male | prefer_not_to_say`), `address TEXT NULL`.
   - ADD `supervisor_id` self-referencing FK (`ON DELETE RESTRICT,
     ON UPDATE CASCADE`) — consistent with the project's RESTRICT-by-default
     historical-data policy; employees are soft-deleted, so a soft-deleted
     supervisor still resolves correctly for subordinates.
   - RENAME `profile_photo` → `profile_photo_path` (storage-path semantics;
     column was unused by code/seeders, so the rename is safe).
   - ADD indexes `idx_employees_employment_type`, `idx_employees_supervisor_id`,
     `idx_employees_date_hired`, `idx_employees_name (last_name, first_name)`.
   - `rollback` reverts every change (columns, indexes, FK, rename).

2. **`employee_histories` (CREATE, migration `060001`).** Append-only change log
   (`field_name`, `old_value`, `new_value`, `effective_date`, `reason`,
   `changed_by`). No `deleted_at` — history must survive.
   - `employee_id` → `employees(id)` ON DELETE RESTRICT.
   - `changed_by` → `users(id)` ON DELETE **SET NULL** (deviation from the
     3.2-A draft which used RESTRICT): phase requirement — if the acting user
     account is removed, the historical record must remain.

**Kept unchanged (no duplicates created):** `users`, `departments`, `positions`,
`annual_plans`, `employee_targets`, `employee_performance_records`,
`department_heads`. `employee_targets` uses `title`/`target_unit` as the
`target_name`/`unit` mapping; `employee_performance_records` already had
`scores + academic_year_id + period unique constraint`.

**Explicitly retained Phase 3.1 semantics (not forced to match spec literally):**
`employees.user_id` stays NULLABLE-UNIQUE (historical employees may have no
login) and `position_id` stays NULLABLE (position may be unassigned);
`employee_number` stays `VARCHAR(30)` (its values fit). These are source-of-truth
decisions — changing them would have been gratuitous churn.

---

## 15. Known Issues / Notes

- Schema validation passed with zero creation errors (Phase 3.1-A report).
- `acadtrack_db` already exists (Phase 1/2); the Phase 3.1-B migration suite was used
  instead of re-running `schema.sql` over the existing database.
- MariaDB `JSON` is an alias of `LONGTEXT` (values validated as JSON by the client);
  functionally identical for this design.
- **`role_user` is currently empty** because Phase 2 authentication still reads
  `users.role_id`. 3.1-C/D will backfill `role_user`, switch the app to the
  many-to-many model, then drop `users.role_id`.
- `login_activities` (Phase 2) and Sanctum `personal_access_tokens`/`sessions`
  tables are framework infrastructure and sit outside the Phase 3 domain schema.
- Test suite now targets the isolated `acadtrack_db_test` database (MySQL) —
  see §13.6.