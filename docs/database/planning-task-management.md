# JPC Portal — Planning & Task Management Database Design (Phase 3.3-A)

| Item | Value |
| ---- | ----- |
| Database | `acadtrack_db` (MySQL 8 / XAMPP local) |
| Scope | Planning → targets → tasks → assignments → progress → review/monitoring |
| Status | **DESIGN ONLY (3.3-A)** — SQL artifact produced; no migrations/models yet |
| SQL file | [`../../database/sql/phase_3_3_a_planning_task_management.sql`](../../database/sql/phase_3_3_a_planning_task_management.sql) |
| Related docs | [`employee-management.md`](employee-management.md), [`database-design.md`](database-design.md), [`schema.sql`](schema.sql), [`seeders.md`](seeders.md) |

---

## 1. ERD Explanation (§67)

```
academic_years
      │ 1:N
      ▼
annual_plans   (department-scoped via department_id — doubles as the "department plan")
      │ 1:N                        department_id ──► departments
      ▼
department_targets   ◄──────────────── *department_id* ──► departments
      │                                                (targets belong to a dept plan)
      │ N:1 (nullable, via employee_targets.department_target_id)
      ▼
employee_targets     ──► employees, annual_plans
      │
      │ (optional link: tasks.employee_target_id)
      ▼
   tasks ──► annual_plans, departments (department_id = dept-plan linkage)
      │ 1:N (parent_task_id self-FK → subtasks)
      ▼
task_assignments ──► employees   UNIQUE(task_id, employee_id)
      │ 1:N
      ├────────────► task_progress ──► employees, users(updated_by)
      ├────────────► task_comments  ──► users, task_assignments(optional)
      ├────────────► task_attachments ──► users(uploaded_by)
      └────────────► task_reviews   ──► users(reviewed_by)
```

Every FK in the diagram is implemented in the SQL; the arrow direction is
"parent → child" (child holds the FK).

---

## 2. Table List

| Table | Status | Role |
| ----- | ------ | ---- |
| `academic_years` | **EXISTING — REUSED** | Period scope (name unique, one active normally) |
| `annual_plans` | **EXISTING — REUSED** | Department plan (has `department_id`); approval workflow |
| `department_targets` | **NEW** | Department-level measurable goals |
| `employee_targets` | **EXISTING — ALTER** | Individual targets; gains `department_target_id` + `created_by` |
| `tasks` | **EXISTING — ALTER** | Work items; gains `task_code`, target links, subtask self-FK, `estimated_hours`, `updated_by` |
| `task_assignments` | **EXISTING — ALTER** | Many-to-many task↔employee; gains `accepted_at`, `remarks`, `deleted_at` |
| `task_progress` | **NEW** | Append-only progress history |
| `task_comments` | **NEW** | Communication thread |
| `task_attachments` | **NEW** | File-upload metadata |
| `task_reviews` | **NEW** | Review/approval history |
| `task_references` | **EXISTING — REUSED** | Reference documents (kept distinct from attachments) |

---

## 3. Existing Tables Reused (B)

- `academic_years`, `annual_plans`, `employee_targets`, `tasks`,
  `task_assignments`, `task_references` — plus all Phase 3.1/3.2 core tables
  (`users`, `roles`, `permissions`, `departments`, `positions`, `employees`,
  `employee_histories`, `employee_performance_records`, `department_heads`, …).
- **Not created:** `department_plans` — `annual_plans.department_id` already
  provides the department-plan linkage (§68: reuse, no duplicate department
  structures).

---

## 4. New Tables (C)

### 4.1 `department_targets`
| Column | Type | Null | Notes |
| ------ | ---- | ---- | ----- |
| `id` | BIGINT UNSIGNED PK | | |
| `annual_plan_id` | BIGINT UNSIGNED FK | NOT NULL | → `annual_plans.id` (the dept plan) |
| `department_id` | BIGINT UNSIGNED FK | NOT NULL | → `departments.id` |
| `target_name` | VARCHAR(255) | NOT NULL | |
| `target_description` | TEXT | NULL | |
| `target_value` | DECIMAL(12,2) | NULL | |
| `unit` | VARCHAR(50) | NULL | |
| `start_date` / `end_date` | DATE | NULL | |
| `status` | VARCHAR(20) | NOT NULL default `draft` | `draft\|active\|completed\|cancelled` |
| `created_by` | BIGINT UNSIGNED FK | NULL | → `users.id` (usually the dept head) |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | soft delete |

Indexes: `annual_plan_id`, `department_id`, `status`, `created_by`.

### 4.2 `task_progress`
| Column | Type | Null | Notes |
| ------ | ---- | ---- | ----- |
| `id` | BIGINT UNSIGNED PK | | |
| `task_id` | BIGINT UNSIGNED FK | NOT NULL | → `tasks.id` (RESTRICT) |
| `task_assignment_id` | BIGINT UNSIGNED FK | NOT NULL | → `task_assignments.id` (RESTRICT) |
| `employee_id` | BIGINT UNSIGNED FK | NOT NULL | → `employees.id` (RESTRICT) |
| `previous_percentage` | DECIMAL(5,2) | NOT NULL default 0.00 | |
| `new_percentage` | DECIMAL(5,2) | NOT NULL | 0.00–100.00 |
| `status` | VARCHAR(30) | NULL | assignment-status snapshot at that point |
| `comment` | TEXT | NULL | |
| `updated_by` | BIGINT UNSIGNED FK | NULL | → `users.id` |
| `created_at` / `updated_at` | TIMESTAMP | | |

Indexes: `task_id`, `task_assignment_id`, `employee_id`, `created_at`.
**Append-only audit** — never soft/hard deleted (§48).

### 4.3 `task_comments`
| Column | Type | Null | Notes |
| ------ | ---- | ---- | ----- |
| `id` | BIGINT UNSIGNED PK | | |
| `task_id` | BIGINT UNSIGNED FK | NOT NULL | → `tasks.id` |
| `task_assignment_id` | BIGINT UNSIGNED FK | NULL | optional, → `task_assignments.id` |
| `user_id` | BIGINT UNSIGNED FK | NOT NULL | → `users.id` |
| `comment` | TEXT | NOT NULL | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | soft delete |

Indexes: `task_id`, `task_assignment_id`, `user_id`.

### 4.4 `task_attachments`
| Column | Type | Null | Notes |
| ------ | ---- | ---- | ----- |
| `id` | BIGINT UNSIGNED PK | | |
| `task_id` | BIGINT UNSIGNED FK | NOT NULL | → `tasks.id` |
| `task_assignment_id` | BIGINT UNSIGNED FK | NULL | optional |
| `uploaded_by` | BIGINT UNSIGNED FK | NOT NULL | → `users.id` |
| `file_name` | VARCHAR(255) | NOT NULL | |
| `file_path` | VARCHAR(500) | NOT NULL | storage-relative path; no binary in MySQL |
| `mime_type` | VARCHAR(100) | NULL | |
| `file_size` | BIGINT UNSIGNED | NULL | bytes |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | soft delete |

Indexes: `task_id`, `task_assignment_id`, `uploaded_by`.

### 4.5 `task_reviews`
| Column | Type | Null | Notes |
| ------ | ---- | ---- | ----- |
| `id` | BIGINT UNSIGNED PK | | |
| `task_id` | BIGINT UNSIGNED FK | NOT NULL | → `tasks.id` |
| `task_assignment_id` | BIGINT UNSIGNED FK | NOT NULL | → `task_assignments.id` |
| `reviewed_by` | BIGINT UNSIGNED FK | NOT NULL | → `users.id` |
| `decision` | VARCHAR(20) | NOT NULL | `approved\|rejected\|needs_revision` |
| `remarks` | TEXT | NULL | |
| `reviewed_at` | TIMESTAMP | NULL | |
| `created_at` / `updated_at` | TIMESTAMP | | |

Indexes: `task_id`, `task_assignment_id`, `reviewed_by`, `decision`.
**Audit trail — never soft-deleted** (§48).

---

## 5. Modified Existing Tables (D)

### 5.1 `employee_targets` (ALTER)
| Added column | Type | FK | Purpose |
| ------------ | ---- | -- | ------- |
| `department_target_id` | BIGINT UNSIGNED NULL | → `department_targets.id` (RESTRICT) | optional contribution link (§10) |
| `created_by` | BIGINT UNSIGNED NULL | → `users.id` (RESTRICT) | audit (§56) |

### 5.2 `tasks` (ALTER)
| Added column | Type | FK | Purpose |
| ------------ | ---- | -- | ------- |
| `task_code` | VARCHAR(50) **UNIQUE NOT NULL** | — | human-readable id (§12), backfilled then NOT NULL |
| `department_target_id` | BIGINT UNSIGNED NULL | → `department_targets.id` | optional origin link (§43) |
| `employee_target_id` | BIGINT UNSIGNED NULL | → `employee_targets.id` | optional origin link (§43) |
| `parent_task_id` | BIGINT UNSIGNED NULL | self-FK → `tasks.id` | subtasks (§19) |
| `estimated_hours` | DECIMAL(8,2) NULL | — | effort, supports `2.50` (§18) |
| `updated_by` | BIGINT UNSIGNED NULL | → `users.id` | audit (§56) |

> Not all task-origin columns are required (nullable) so a task may link to a
> plan, a department target, an employee target, or just stand alone (§43–44).

### 5.3 `task_assignments` (ALTER)
| Added column | Type | Purpose |
| ------------ | ---- | ------- |
| `accepted_at` | TIMESTAMP NULL | when the employee accepted |
| `remarks` | TEXT NULL | free-text note |
| `deleted_at` | TIMESTAMP NULL | soft delete protecting the history chain (§49) |

> `progress_percentage DECIMAL(5,2) default 0` already exists and implements the
> spec §25 `completion_percentage` (0.00–100.00) — kept, no rename to avoid
> breaking existing code/seeders.

---

## 6. Primary Keys (E)

All tables use surrogate `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
(consistent with every existing Phase 3.1/3.2 table).

---

## 7. Foreign Keys (F)

Project convention: **`ON DELETE RESTRICT, ON UPDATE CASCADE`** everywhere (§48).

| Table | Column | References |
| ----- | ------ | ---------- |
| `department_targets` | `annual_plan_id` | `annual_plans.id` |
| `department_targets` | `department_id` | `departments.id` |
| `department_targets` | `created_by` | `users.id` |
| `employee_targets` | `department_target_id` | `department_targets.id` (new) |
| `employee_targets` | `created_by` | `users.id` (new) |
| `tasks` | `department_target_id` | `department_targets.id` (new) |
| `tasks` | `employee_target_id` | `employee_targets.id` (new) |
| `tasks` | `parent_task_id` | `tasks.id` self-FK (new) |
| `tasks` | `updated_by` | `users.id` (new) |
| `task_assignments` | `task_id` | `tasks.id` (existing) |
| `task_assignments` | `employee_id` | `employees.id` (existing) |
| `task_assignments` | `assigned_by` | `users.id` (existing) |
| `task_progress` | `task_id` | `tasks.id` |
| `task_progress` | `task_assignment_id` | `task_assignments.id` |
| `task_progress` | `employee_id` | `employees.id` |
| `task_progress` | `updated_by` | `users.id` |
| `task_comments` | `task_id` | `tasks.id` |
| `task_comments` | `task_assignment_id` | `task_assignments.id` |
| `task_comments` | `user_id` | `users.id` |
| `task_attachments` | `task_id` | `tasks.id` |
| `task_attachments` | `task_assignment_id` | `task_assignments.id` |
| `task_attachments` | `uploaded_by` | `users.id` |
| `task_reviews` | `task_id` | `tasks.id` |
| `task_reviews` | `task_assignment_id` | `task_assignments.id` |
| `task_reviews` | `reviewed_by` | `users.id` |

---

## 8. Indexes (G)

- `tasks`: existing (`annual_plan_id`, `department_id`, `created_by`, `status`,
  `due_date`, `priority`) + new `task_code` UNIQUE, `department_target_id`,
  `employee_target_id`, `parent_task_id`, `updated_by`.
- `task_assignments`: existing `task_id` (via UNIQUE), `employee_id`, `status`,
  `assigned_by`, `due_date` + new `accepted_at`.
- `task_progress`: `task_id`, `task_assignment_id`, `employee_id`, `created_at`.
- `task_comments`: `task_id`, `task_assignment_id`, `user_id`.
- `task_attachments`: `task_id`, `task_assignment_id`, `uploaded_by`.
- `task_reviews`: `task_id`, `task_assignment_id`, `reviewed_by`, `decision`.
- `department_targets`: `annual_plan_id`, `department_id`, `status`, `created_by`.
- `employee_targets`: existing + new `department_target_id`, `created_by`.

---

## 9. Unique Constraints (H)

| Table | Constraint | Purpose |
| ----- | ---------- | ------- |
| `academic_years` | `name` UNIQUE (existing) | no duplicate year names |
| `tasks` | `task_code` UNIQUE (new) | one human-readable code per task |
| `task_assignments` | UNIQUE(`task_id`, `employee_id`) (existing) | no double assignment (§26/§65) |

---

## 10. Delete Strategy (I)

- **FKs: RESTRICT (delete) + CASCADE (update)** — prevents destroying history.
- `task_progress` / `task_reviews`: **never soft-deleted** (audit integrity, §48).
- `tasks`, `task_assignments`, `task_comments`, `task_attachments`,
  `department_targets`, `employee_targets`, `annual_plans`: **soft delete**
  (`deleted_at`) — historical progress never vanishen accidentally.
- `task_assignments.deleted_at` protects the chain `tasks → assignments →
  progress` even if an assignment is archived.

---

## 11. Status Values (J)

| Field | Values | Notes |
| ----- | ------ | ----- |
| `academic_years.status` | `draft \| active \| closed` | existing; normally one active (§2) |
| `annual_plans.status` | `draft \| submitted \| under_review \| approved \| rejected \| completed \| archived` | §3 |
| `department_targets.status` | `draft \| active \| completed \| cancelled` | aligns with employee_targets |
| `employee_targets.status` | `draft \| active \| completed \| cancelled` | existing |
| `tasks.status` | `draft \| assigned \| in_progress \| submitted \| under_review \| completed \| rejected \| cancelled` | `overdue` is **derived** (due_date < now AND not completed/cancelled), §16/§36 |
| `tasks.priority` | `low \| medium \| high \| urgent` | §15 |
| `task_assignments.status` | `assigned \| accepted \| in_progress \| submitted \| completed \| rejected \| cancelled` | per-assignment, §23 |
| `task_reviews.decision` | `approved \| rejected \| needs_revision` | §33 |

All `VARCHAR`, no ENUM (per project convention and spec §50).

---

## 12. Task Lifecycle (K)

```
draft → assigned ──────────────────────────────► cancelled (any point)
                    │
                    ▼
                in_progress
                    │
                    ▼
                submitted
                    │
          ┌─────────┴──────────┐
      under_review        (re-open: rejected → in_progress)
          │
    ┌─────┼─────┐
approved  rejected  needs_revision
    │        └──────────► in_progress → submitted …
    ▼
 completed
```

- Task-level status is the **aggregate**, each employee has an **independent
  assignment status** (§23): Task 1 can be `in_progress` while Teacher 1 is
  `completed` (100%) and Teacher 2 is `in_progress` (65%).
- `completed_at` is set only on terminal completion; application validation
  enforces logical transitions (§17).

---

## 13. Assignment Lifecycle

```
assigned → accepted → in_progress → submitted → under_review → completed
                └── rejected ─────────────────► in_progress (revision)
```

- Per-assignment `due_date` allows individual deadlines (§24).
- Assignment is flagged overdue dynamically when `due_date < NOW()` and status
  NOT IN (`completed`, `cancelled`) — computed by the service layer (§36).

---

## 14. Progress Lifecycle

- One `task_progress` row per update: `previous_percentage → new_percentage`
  plus a status snapshot and optional comment (§63 example: 0→25→50→75→100).
- Supports dashboard queries: total / completed / pending / in progress /
  overdue per employee, department, or institution-wide (§37–§42).

---

## 15. Review Lifecycle

```
submitted → under_review
   │
   ├── approved        → assignment status = completed
   ├── rejected        → assignment status = rejected → in_progress (redo)
   └── needs_revision  → assignment status = rejected → in_progress (redo)
```
Each review is a `task_reviews` row (decision, remarks, reviewer, timestamp).
Authorization (Admin / Department Head reviewer) is a later-phase concern (§34).

---

## 16. Business Scenarios (§60–§64)

### Scenario 1 — multi-assign, independent progress
`TASK-2026-0001 "Prepare Monthly Faculty Report"` (Annual Plan:
Faculty Performance Improvement Plan; Department: College of Education;
Department Target: Complete faculty documentation). Assigned to Teacher 1 + Teacher 2.
- `task_assignments` row per teacher; Teacher 1 `completed` 100.00%, Teacher 2
  `in_progress` 65.00% → `tasks.status = in_progress`.

### Scenario 2 — review approval
`TASK-2026-0002` assigned to Teacher 3 → `submitted` → `task_reviews`
`decision = approved` → assignment `completed`.

### Scenario 3 — overdue without stored flag
`TASK-2026-0003` due `2026-08-10`, status `in_progress`, today > due →
service computes `is_overdue = true` (no permanent status stored).

### Scenario 4 / 5 — progress + revision history
`task_progress` records the 0→25→50→75→100 journey; `task_reviews` records
`needs_revision → rejected → in_progress → submitted → approved`.

---

## 17. Verification Checklist (§70)

- [x] Existing database inspected (migrations 0015–0020 read; no duplicate tables)
- [x] No duplicate tables (department_plans intentionally skipped — reused
      `annual_plans.department_id`)
- [x] Academic year architecture verified (`academic_years` reused)
- [x] Annual plan architecture verified (`annual_plans` reused, approval fields ok)
- [x] Department plans designed (mapped to `annual_plans`)
- [x] Department targets designed (`department_targets` NEW)
- [x] Existing employee targets integrated (ALTER: `department_target_id`, `created_by`)
- [x] Tasks designed (`tasks` ALTER: `task_code`, links, self-FK, hours, `updated_by`)
- [x] Multiple employee assignments supported (`task_assignments`, UNIQUE pair)
- [x] Task progress history supported (`task_progress` NEW)
- [x] Task reviews supported (`task_reviews` NEW)
- [x] Optional task comments supported (`task_comments` NEW)
- [x] Optional task attachments supported (`task_attachments` NEW)
- [x] Parent/subtask structure supported (`tasks.parent_task_id`)
- [x] Deadlines supported (`tasks.due_date`, `task_assignments.due_date`)
- [x] Completion percentage supported (`task_assignments.progress_percentage`)
- [x] Task status lifecycle supported (documented values, §12)
- [x] Assignment status lifecycle supported (documented values, §13)
- [x] Foreign keys defined (RESTRICT/CASCADE, §7)
- [x] Indexes defined (§8)
- [x] Unique constraints defined (§9)
- [x] Historical data protected (§10)
- [x] MySQL 8 compatible (InnoDB, utf8mb4, no ENUM)
- [x] SQL file created
- [x] ERD documentation created (§1)
- [x] Business scenarios validated (§16)

---

## 18. Notes

- This phase produced **design + SQL only**. No migrations, models, controllers,
  API or React were created.
- The single deviation from the literal spec table list is the omission of
  `department_plans` (redundant with `annual_plans.department_id`). If the
  reviewer requires a physically separate table, it can be added in Phase 3.3-B
  as a thin parent of `department_targets` without changing the rest.
- `task_code` backfill uses `TASK-<YEAR>-<LPAD id,4,0>` for pre-existing rows,
  then enforces `NOT NULL`.
- `task_assignments.progress_percentage` is kept (=== spec
  "completion_percentage") to avoid breaking existing code/seeders.