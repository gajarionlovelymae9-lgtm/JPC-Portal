# JPC Portal — Planning & Task Management Migrations (Phase 3.3-B)

| Item | Value |
| ---- | ----- |
| Database | `acadtrack_db` (MySQL 8 / MariaDB 10.4 — XAMPP local) |
| Framework | Laravel 12.69.2 |
| Scope | **Migrations only** (no Models, Controllers, API, React, AI, OCR, Seeders) |
| Status | Complete — verified with `migrate`, `rollback`, `migrate:fresh`, `migrate:status` |
| Design source | [`planning-task-management.md`](planning-task-management.md) (Phase 3.3-A) |
| Related docs | [`employee-management.md`](employee-management.md), [`database-design.md`](database-design.md) |

---

## 1. Migration List

| # | File | Type | Purpose |
| - | ---- | ---- | ------- |
| 1 | `2026_09_17_000021_create_department_plans_table.php` | CREATE | Department-scoped plan under an annual plan |
| 2 | `2026_09_17_000022_create_department_targets_table.php` | CREATE | Measurable department goals |
| 3 | `2026_09_17_000023_add_department_target_id_to_employee_targets_table.php` | ALTER | Link employee targets to department targets |
| 4 | `2026_09_17_000024_add_planning_fields_to_tasks_table.php` | ALTER | Planning FKs, `task_code`, subtask, hours, audit |
| 5 | `2026_09_17_000025_add_review_fields_to_task_assignments_table.php` | ALTER | `accepted_at`, `remarks`, soft delete |
| 6 | `2026_09_17_000026_create_task_progress_table.php` | CREATE | Append-only progress history |
| 7 | `2026_09_17_000027_create_task_comments_table.php` | CREATE | Task/assignment comments |
| 8 | `2026_09_17_000028_create_task_attachments_table.php` | CREATE | File-upload metadata |
| 9 | `2026_09_17_000029_create_task_reviews_table.php` | CREATE | Review/approval audit trail |

**Migrations created:** 9 · **Tables created:** 6 (`department_plans`, `department_targets`, `task_progress`, `task_comments`, `task_attachments`, `task_reviews`) · **Tables modified:** 3 (`employee_targets`, `tasks`, `task_assignments`).

---

## 2. Migration Order

Order respects foreign-key dependencies:

```
000021 department_plans
   └─ 000022 department_targets          (FK → department_plans)
        └─ 000023 employee_targets ALTER (FK → department_targets)
             └─ 000024 tasks ALTER       (FK → department_plans, department_targets,
                                          employee_targets, tasks(self), users)
                  └─ 000025 task_assignments ALTER
                       └─ 000026 task_progress   (FK → tasks, task_assignments, employees, users)
                       └─ 000027 task_comments   (FK → tasks, task_assignments, users)
                       └─ 000028 task_attachments(FK → tasks, task_assignments, users)
                       └─ 000029 task_reviews    (FK → tasks, task_assignments, users)
```

All new files sort after the existing `2026_09_14_060001_create_employee_histories_table.php`.

---

## 3. Existing Tables Reused

No duplicate tables were created. These already existed and are used as-is:

| Table | Existing migration | Reused for |
| ----- | ------------------ | ---------- |
| `academic_years` | `..._000015_create_academic_years_table` | Period scope |
| `annual_plans` | `..._000016_create_annual_plans_table` | Annual plan (parent of `department_plans`) |
| `employee_targets` | `..._000017_create_employee_targets_table` | Individual targets (extended in place) |
| `tasks` | `..._000018_create_tasks_table` | Work items (extended in place) |
| `task_assignments` | `..._000019_create_task_assignments_table` | Task↔employee assignments (extended in place) |
| `task_references` | `..._000020_create_task_references_table` | Reference documents (untouched) |
| `departments`, `employees`, `users` | earlier phases | FK targets |

---

## 4. New Tables

### 4.1 `department_plans`
`id`, `annual_plan_id` (FK), `department_id` (FK), `title`, `description`, `status` (default `draft`), `created_by` (FK, nullable), `approved_by` (FK, nullable), `approved_at`, timestamps, soft deletes.
Indexes: `annual_plan_id`, `department_id`, `status`.

### 4.2 `department_targets`
`id`, `department_plan_id` (FK), `department_id` (FK), `target_name`, `target_description`, `target_value` `decimal(12,2)`, `unit`, `start_date`, `end_date`, `status` (default `draft`), `created_by` (FK, nullable), timestamps, soft deletes.
Indexes: `department_plan_id`, `department_id`, `status`, `created_by`.

### 4.3 `task_progress` (no soft delete — append-only)
`id`, `task_id` (FK), `task_assignment_id` (FK), `employee_id` (FK), `previous_percentage` `decimal(5,2)` default 0, `new_percentage` `decimal(5,2)` default 0, `status`, `comment`, `updated_by` (FK, nullable), timestamps.
Indexes: `task_id`, `task_assignment_id`, `employee_id`, `created_at`.

### 4.4 `task_comments`
`id`, `task_id` (FK), `task_assignment_id` (FK, nullable), `user_id` (FK), `comment`, timestamps, soft deletes.
Indexes: `task_id`, `task_assignment_id`, `user_id`.

### 4.5 `task_attachments`
`id`, `task_id` (FK), `task_assignment_id` (FK, nullable), `uploaded_by` (FK), `file_name`, `file_path`, `mime_type`, `file_size` `unsignedBigInteger`, timestamps, soft deletes. (No binary stored in MySQL.)
Indexes: `task_id`, `task_assignment_id`, `uploaded_by`.

### 4.6 `task_reviews` (no soft delete — audit trail)
`id`, `task_id` (FK), `task_assignment_id` (FK), `reviewed_by` (FK), `decision`, `remarks`, `reviewed_at`, timestamps.
Indexes: `task_id`, `task_assignment_id`, `reviewed_by`, `decision`.

---

## 5. Modified Tables

### 5.1 `employee_targets` (+1 column)
- `department_target_id` nullable FK → `department_targets` **nullOnDelete**. Index added. Nothing else changed; reversible.

### 5.2 `tasks` (+7 columns)
- `task_code` `varchar(50)` **nullable, UNIQUE** — backfilled for pre-existing rows with `TASK-<YEAR>-<LPAD id,4,0>`.
- `department_plan_id` nullable FK → `department_plans` nullOnDelete.
- `department_target_id` nullable FK → `department_targets` nullOnDelete.
- `employee_target_id` nullable FK → `employee_targets` nullOnDelete.
- `parent_task_id` nullable self-FK → `tasks` nullOnDelete (subtasks).
- `estimated_hours` `decimal(8,2)` nullable.
- `updated_by` nullable FK → `users` restrictOnDelete.
- The pre-existing `department_id`, `annual_plan_id`, `priority`, `status`, dates and other columns are left untouched.

### 5.3 `task_assignments` (+3 columns)
- `accepted_at` nullable timestamp.
- `remarks` nullable text.
- `deleted_at` (soft delete).
- Pre-existing `progress_percentage` (≈ spec "completion_percentage") and `unique(task_id, employee_id)` are kept.

---

## 6. Foreign Keys

All business/history FKs use **`ON DELETE RESTRICT, ON UPDATE CASCADE`**; optional planning links use **`ON DELETE SET NULL, ON UPDATE CASCADE`**.

| Table | Column | → References | On delete |
| ----- | ------ | ------------ | --------- |
| `department_plans` | `annual_plan_id` | `annual_plans.id` | RESTRICT |
| `department_plans` | `department_id` | `departments.id` | RESTRICT |
| `department_plans` | `created_by`, `approved_by` | `users.id` | RESTRICT |
| `department_targets` | `department_plan_id` | `department_plans.id` | RESTRICT |
| `department_targets` | `department_id` | `departments.id` | RESTRICT |
| `department_targets` | `created_by` | `users.id` | RESTRICT |
| `employee_targets` | `department_target_id` | `department_targets.id` | SET NULL |
| `tasks` | `department_plan_id` | `department_plans.id` | SET NULL |
| `tasks` | `department_target_id` | `department_targets.id` | SET NULL |
| `tasks` | `employee_target_id` | `employee_targets.id` | SET NULL |
| `tasks` | `parent_task_id` | `tasks.id` | SET NULL |
| `tasks` | `updated_by` | `users.id` | RESTRICT |
| `task_progress` | `task_id`, `task_assignment_id`, `employee_id` | `tasks`, `task_assignments`, `employees` | RESTRICT |
| `task_progress` | `updated_by` | `users.id` | RESTRICT |
| `task_comments` | `task_id` | `tasks.id` | RESTRICT |
| `task_comments` | `task_assignment_id` | `task_assignments.id` | SET NULL |
| `task_comments` | `user_id` | `users.id` | RESTRICT |
| `task_attachments` | `task_id` | `tasks.id` | RESTRICT |
| `task_attachments` | `task_assignment_id` | `task_assignments.id` | SET NULL |
| `task_attachments` | `uploaded_by` | `users.id` | RESTRICT |
| `task_reviews` | `task_id`, `task_assignment_id`, `reviewed_by` | `tasks`, `task_assignments`, `users` | RESTRICT |

`cascadeOnDelete()` is **never** used on task/history tables — work history cannot disappear accidentally.

---

## 7. Indexes

Indexes exist for every column listed in the phase spec. FK columns are auto-indexed by InnoDB under the FK constraint name (`*_foreign`), so **no duplicate indexes** were declared. Explicit indexes added where no FK exists:

- `department_plans`: `annual_plan_id`, `department_id`, `status`.
- `department_targets`: `department_plan_id`, `department_id`, `status`, `created_by`.
- `tasks`: `task_code` (unique), `department_plan_id`, `department_target_id`, `employee_target_id`, `parent_task_id`, `updated_by`; existing `annual_plan_id`, `department_id`, `status`, `priority`, `due_date`, `created_by`.
- `task_assignments`: `accepted_at` (via column), existing `task_id`/`employee_id`/`status`/`due_date`/`assigned_by`.
- `task_progress`: `task_id`, `task_assignment_id`, `employee_id`, `created_at`.
- `task_comments`: `task_id`, `task_assignment_id`, `user_id`.
- `task_attachments`: `task_id`, `task_assignment_id`, `uploaded_by`.
- `task_reviews`: `task_id`, `task_assignment_id`, `reviewed_by`, `decision`.

**Unique constraints:** `tasks.task_code`, `task_assignments(task_id, employee_id)` (existing), `academic_years.name` (existing).

---

## 8. Delete Strategy

- **Soft deletes** (`deleted_at`): `department_plans`, `department_targets`, `tasks`, `task_assignments`, `task_comments`, `task_attachments` (plus existing `annual_plans`, `employee_targets`).
- **No soft deletes** (audit integrity, append-only): `task_progress`, `task_reviews`.
- **RESTRICT** prevents deleting tasks assigned to employees, tasks with progress/reviews, and users referenced by audit columns.
- **SET NULL** only for optional planning links, so reorganising plans never destroys tasks or history.

---

## 9. Rollback Procedure

```powershell
php artisan migrate            # apply the 9 migrations
php artisan migrate:rollback   # reverses the whole batch in dependency-safe reverse order
php artisan migrate            # re-apply
```

Each `down()` drops added columns/indexes/FKs for ALTER migrations and uses `Schema::dropIfExists()` for new tables. Rollback order automatically drops child tables (`task_reviews`, `task_attachments`, `task_comments`, `task_progress`) before parents.

To reset development data:

```powershell
php artisan migrate:fresh --seed
```

---

## 10. Testing Procedure

1. `php artisan migrate` → all 9 new migrations DONE.
2. `php artisan migrate:rollback` → all 9 reversed cleanly.
3. `php artisan migrate` → re-applied.
4. `php artisan migrate:fresh --seed` → 47 migrations run, seeders complete.
5. `php artisan migrate:status` → all migrations `Ran`.
6. Integrity scenarios (verified, all PASS):
   - Academic year `2026-2027` present; annual plan (EDU) present.
   - Create department plan → department target → link employee target.
   - Create task `TASK-2026-0001` with plan/target FKs and `estimated_hours` 8.5.
   - Create subtask with `parent_task_id`.
   - Assign one task to two employees → PASS.
   - Re-insert same `(task_id, employee_id)` → rejected by UNIQUE.
   - Progress `0→25→50→75→100` stored as 4 rows.
   - Reviews `needs_revision` then `approved` both retained.
   - Hard-delete task with history → blocked by RESTRICT.
   - Hard-delete user referenced by `created_by` → blocked by RESTRICT.
7. `php artisan test` → **62 passed (326 assertions)** — no regressions.

---

## 11. Notes / Deviations

- **`department_plans` physically created.** Phase 3.3-A proposed reusing `annual_plans.department_id`; Phase 3.3-B explicitly requires a separate `department_plans` table (see the phase architecture diagram), so it is created and `department_targets`/`tasks` link to it.
- **`task_code` is nullable-unique in the DB** for this phase. The existing `DevelopmentTaskSeeder` inserts tasks without a code and must keep working without seeder changes (out of scope in 3.3-B). Existing rows are backfilled (`TASK-YYYY-NNNN`); generation + NOT NULL enforcement belongs to Phase 3.3-C models/seeders.
- **`progress_percentage` is reused** as the spec's "completion_percentage" (Phase 3.3-A decision) — no duplicate column was added.
- Existing `tasks.start_date`/`due_date` remain `date` (existing column semantics preserved); `estimated_hours` and `completed_at` meet the new requirements.
- No Models, Controllers, API, React, AI, OCR, Reports, or Seeders were created or modified.
