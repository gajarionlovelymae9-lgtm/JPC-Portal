# JPC Portal — Employee Management Database Design (Phase 3.2-A)

| Item | Value |
| ---- | ----- |
| Database | `acadtrack_db` (MySQL 8 / MariaDB ≥ 10.4, XAMPP local) |
| Scope | Employee-management foundation: profile, department/position assignment, supervisor, employment status/type, employee history, targets, performance |
| Status | **IMPLEMENTED — migrations (3.2-B), Eloquent models (3.2-C), dev seeding (3.2-D) and verification (3.2-E) all done & tested.** |
| Base schema | [`docs/database/schema.sql`](schema.sql) (Phase 3.1 canonical design) |
| Related docs | [`database-design.md`](database-design.md), [`erd.md`](erd.md), [`eloquent-models.md`](eloquent-models.md), [`employee-seeding.md`](employee-seeding.md) |

---

## 1. Architecture Rule

- `users` = authentication / account identity. **Never changed by this phase.**
- `employees` = employee / business information (`1 : 1` with `users` via the existing
  unique `employees.user_id`; nullable so historical employees can exist without logins).
- `department_heads` = existing leadership history (unchanged).
- `employee_histories` = **NEW** append-only change log for profile fields.

---

## 2. Delta Analysis — Phase 3.1 schema vs Phase 3.2 requirements

| § | Requirement (Phase 3.2-A) | Existing (Phase 3.1) | Decision |
| -- | ------------------------- | -------------------- | --------- |
| 2 | Employee profile fields | `employees` has `first_name`, `middle_name`, `last_name`, `suffix`, `phone`, `date_hired`, `date_ended`, `profile_photo` | KEEP |
| 2 | `date_of_birth`, `gender`, `address` | Missing | **ADD** columns |
| 3 | `employee_number` UNIQUE NOT NULL | `employees.employee_number VARCHAR(30) UNIQUE NOT NULL` | KEEP |
| 4 | Department relationship | `employees.department_id NOT NULL FK → departments` | KEEP |
| 5 | Position relationship | `employees.position_id NULL FK → positions` | KEEP |
| 6 | Employment status (controlled values) | `employment_status VARCHAR(20) DEFAULT 'active'` | KEEP — widen documented set to 7 canonical values |
| 7 | Employment type | Missing | **ADD** `employment_type` column + index |
| 8–11, 30 | Employee history | Missing | **ADD** generic `employee_histories` table only; do NOT create per-field history tables |
| 12 | Supervisor self-FK | Missing | **ADD** `supervisor_id` self-FK + index |
| 13 | Department head | `department_heads` exists (historical leadership) | KEEP |
| 14–16 | Employee targets | `employee_targets` exists (`title` ≡ `target_name`, `target_unit` ≡ `unit`) | KEEP |
| 17–19 | Employee performance | `employee_performance_records` + `employee_progress_snapshots` exist | KEEP |
| 20–21 | Directory / search indexes | Partial | **ADD** `employment_type`, `date_hired`, `supervisor_id`, name-composite indexes |
| 22 | Profile photo path | `profile_photo VARCHAR(500)` | **RENAME** → `profile_photo_path` (storage-relative path; no binary in MySQL) |
| 23 | Contact info | `phone` present; **no** duplicate `email` in `employees` (auth email stays in `users.email`) | KEEP |
| 24 | Data privacy | Authorization is a later-phase concern (app layer) | DOCUMENT ONLY |
| 26 | Employee histories SQL | n/a | **NEW TABLE** |
| 27 | Employee targets SQL | differs from suggestion only in naming | KEEP (mapping documented) |
| 28 | Performance SQL | richer than suggestion (`academic_year_id`, `performance_status`) | KEEP |
| 29 | Score validation | `DECIMAL(5,2)` | DOCUMENT ONLY — app-enforced 0.00–100.00 (project convention: no CHECK constraints) |
| 30 | Single-history architecture | n/a | Use `employee_histories` only; no `employee_department_histories` / `_position_histories` / `_status_histories` |

**Net schema change is intentionally small:** one new table (`employee_histories`),
six new/modified columns on `employees` (4 ADD + 1 RENAME + SELF-FK), and four indexes.

---

## 3. Canonical Controlled Values

### 3.1 `employees.employment_status`

```
active | inactive | on_leave | suspended | resigned | retired | terminated
```

Single canonical lowercase set (spec §6). Stored on
`employment_status VARCHAR(20) NOT NULL DEFAULT 'active'`.
Existing dev data uses `active` only — fully compatible; no data migration needed.

### 3.2 `employees.employment_type` (NEW)

```
regular | probationary | part_time | contractual | temporary
```

Stored on `employment_type VARCHAR(20) NOT NULL DEFAULT 'regular'`.

### 3.3 `employees.gender` (NEW)

```
female | male | prefer_not_to_say
```

`VARCHAR(30) NULL` — optional, privacy-sensitive.

### 3.4 `employee_histories.field_name` (NEW table)

Controlled at the application layer:

```
department | position | employment_status | employment_type | supervisor |
date_hired | date_ended | date_of_birth | gender | phone | address | profile_photo_path
```

### 3.5 `employee_performance_records.period_type`

```
monthly | quarterly | yearly
```

(Existing — followed the project convention; spec calls these monthly/quarterly/semester/annual. Field is `VARCHAR(20)` and app-validated, so adding `semester` later is a data change, not an `ALTER TABLE`.)

### 3.6 Score validation (§29)

All rate columns are `DECIMAL(5,2)` with valid domain **0.00–100.00**. Per the project
convention established in Phase 3.1 (`database-design.md` §8.1 — VARCHAR + app
validation instead of rigid DB constraints), bounds are enforced by application
validation in the services that compute/record scores. No `CHECK` constraints are
introduced (keeps schema portable across MySQL/MariaDB and matches existing strategy).
The verification phase may add `CHECK` later only if the user explicitly requires it.

---

## 4. MySQL 8 SQL — Target DDL

> Design reference for the Phase 3.2-B migration. `schema.sql` will be updated to match
> only **after** the migrations run against `acadtrack_db`. Apply nothing manually.

### 4.1 `employees` — target shape (full CREATE for reference)

```sql
-- ============================================================================
-- 08a employees (Phase 3.2 target — Phase 3.1 shape + 6 columns + 4 indexes)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `employees` (
  `id`                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`           BIGINT UNSIGNED  NULL
                      COMMENT 'Nullable: historical employees may have no login. UNIQUE: 1:1 with users.',
  `employee_number`   VARCHAR(30)      NOT NULL,
  `first_name`        VARCHAR(100)     NOT NULL,
  `middle_name`       VARCHAR(100)     NULL DEFAULT NULL,
  `last_name`         VARCHAR(100)     NOT NULL,
  `suffix`            VARCHAR(20)      NULL DEFAULT NULL,
  `position_id`       BIGINT UNSIGNED  NULL,
  `department_id`     BIGINT UNSIGNED  NOT NULL,
  `employment_status` VARCHAR(20)      NOT NULL DEFAULT 'active'
                      COMMENT 'active | inactive | on_leave | suspended | resigned | retired | terminated',
  `employment_type`   VARCHAR(20)      NOT NULL DEFAULT 'regular'
                      COMMENT 'regular | probationary | part_time | contractual | temporary',
  `supervisor_id`     BIGINT UNSIGNED  NULL
                      COMMENT 'Self-referencing line-manager (not department-head role).',
  `date_of_birth`     DATE             NULL DEFAULT NULL,
  `gender`            VARCHAR(30)      NULL DEFAULT NULL
                      COMMENT 'female | male | prefer_not_to_say',
  `date_hired`        DATE             NULL DEFAULT NULL,
  `date_ended`        DATE             NULL DEFAULT NULL,
  `phone`             VARCHAR(30)      NULL DEFAULT NULL,
  `address`           TEXT             NULL,
  `profile_photo_path`VARCHAR(500)     NULL DEFAULT NULL
                      COMMENT 'Storage-relative path under storage/app/public/employees/. No binary in MySQL.',
  `created_at`        TIMESTAMP        NULL DEFAULT NULL,
  `updated_at`        TIMESTAMP        NULL DEFAULT NULL,
  `deleted_at`        TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employees_user_id`         (`user_id`),
  UNIQUE KEY `uq_employees_employee_number` (`employee_number`),
  KEY `idx_employees_department_id`     (`department_id`),
  KEY `idx_employees_position_id`       (`position_id`),
  KEY `idx_employees_employment_status` (`employment_status`),
  KEY `idx_employees_employment_type`   (`employment_type`),
  KEY `idx_employees_supervisor_id`     (`supervisor_id`),
  KEY `idx_employees_date_hired`        (`date_hired`),
  KEY `idx_employees_name`              (`last_name`,`first_name`),
  CONSTRAINT `employees_user_id_foreign`       FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`)       ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employees_position_id_foreign`   FOREIGN KEY (`position_id`)   REFERENCES `positions` (`id`)   ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employees_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employees_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `employees` (`id`)   ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Employee profiles. One user-account per employee (unique). Supervisor is a self-FK. Soft-deleted.';
```

> Note: the live table keeps the existing `idx_employees_last_name` from Phase 3.1
> (not the Phase 3.1-A `idx_employees_last_name` name change). Migration 3.2-B adds
> `idx_employees_name` for directory ordering without removing the single-column index.

### 4.2 `employees` — delta statements (what Phase 3.2-B will run)

```sql
-- Add new columns / indexes (Laravel Blueprint equivalents will be used in 3.2-B)
ALTER TABLE `employees`
  ADD `employment_type`   VARCHAR(20) NOT NULL DEFAULT 'regular',
  ADD `date_of_birth`     DATE NULL DEFAULT NULL,
  ADD `gender`            VARCHAR(30) NULL DEFAULT NULL,
  ADD `address`           TEXT NULL,
  ADD `supervisor_id`     BIGINT UNSIGNED NULL,
  ADD KEY `idx_employees_employment_type` (`employment_type`),
  ADD KEY `idx_employees_supervisor_id`   (`supervisor_id`),
  ADD KEY `idx_employees_date_hired`      (`date_hired`),
  ADD KEY `idx_employees_name`            (`last_name`,`first_name`),
  ADD CONSTRAINT `employees_supervisor_id_foreign`
      FOREIGN KEY (`supervisor_id`) REFERENCES `employees` (`id`)
      ON DELETE RESTRICT ON UPDATE CASCADE;

-- Rename photo column (storage path semantics)
ALTER TABLE `employees`
  RENAME COLUMN `profile_photo` TO `profile_photo_path`;
```

### 4.3 `employee_histories` — NEW table

```sql
-- ============================================================================
-- 08b employee_histories — append-only change log for employee profile fields.
--     Generic: field_name + old/new value captures department, position,
--     employment_status, employment_type, supervisor, phone, address, etc.
--     NOT soft-deletable (history must survive). RESTRICT on delete.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `employee_histories` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id`   BIGINT UNSIGNED NOT NULL,
  `field_name`    VARCHAR(100)    NOT NULL
                  COMMENT 'department | position | employment_status | employment_type | supervisor | date_hired | date_ended | date_of_birth | gender | phone | address | profile_photo_path',
  `old_value`     TEXT            NULL,
  `new_value`     TEXT            NULL,
  `effective_date`DATE            NOT NULL,
  `reason`        TEXT            NULL,
  `changed_by`    BIGINT UNSIGNED NULL
                  COMMENT 'users.id of the actor (HR/admin); NULL for bulk/system events. ON DELETE SET NULL keeps the record if the user account is removed.',
  `created_at`    TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_histories_employee_id`     (`employee_id`),
  KEY `idx_employee_histories_field_name`      (`field_name`),
  KEY `idx_employee_histories_effective_date`  (`effective_date`),
  KEY `idx_employee_histories_employee_field`  (`employee_id`,`field_name`),
  CONSTRAINT `employee_histories_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_histories_changed_by_foreign`  FOREIGN KEY (`changed_by`)  REFERENCES `users`      (`id`) ON DELETE SET NULL  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Append-only employee attribute change history. Never soft-deleted; RESTRICT protects records.';
```

### 4.4 `employee_targets` — unchanged, spec mapping (§27)

The existing table satisfies spec §14–16. Column mapping: `title` ≡ `target_name`,
`description` ≡ `target_description`, `target_unit` ≡ `unit`, `status` =
`draft | active | completed | cancelled`. `target_value DECIMAL(12,2)` nullable.

### 4.5 `employee_performance_records` — unchanged, spec mapping (§28)

Existing table already provides all five rates + `overall_score`, `quality_score`,
`period_type`, `period_start/end`, `academic_year_id`, and a **UNIQUE per
employee-period** (`uq_employee_performance_records_period`), plus
`performance_status` (improving/consistent/declining/at_risk). **No schema change.**

### 4.6 `department_heads` — unchanged (§13)

Department-head leadership remains in the existing historical table
(`is_current` + generated-column uniqueness). An employee becoming department head
does not require a new user account.

---

## 5. Indexing Strategy (§20–21, §31)

| Index (employees) | Purpose |
| ----------------- | ------- |
| `uq_employees_employee_number` | Unique lookup by employee number |
| `uq_employees_user_id` | 1:1 account link |
| `idx_employees_department_id` | Department filter (directory) |
| `idx_employees_position_id` | Position filter (directory) |
| `idx_employees_employment_status` | Status filter (directory) |
| `idx_employees_employment_type` (NEW) | Type filter (HR: regular/part-time/contractual) |
| `idx_employees_supervisor_id` (NEW) | Subordinate list of a supervisor |
| `idx_employees_date_hired` (NEW) | Date-hired filter/sort (HR) |
| `idx_employees_name` (NEW, `last_name, first_name`) | Name search / directory ordering |

No index is added to `phone`, `address`, `gender`, `date_of_birth`
(not directory filters; `date_of_birth` is privacy-sensitive, rare in queries).

`employee_histories`: `employee_id` (drill-down), `effective_date` (period reporting),
`field_name` (type filtering), `(employee_id, field_name)` (trace a specific field).

---

## 6. Relationship Diagram (§32)

```
                 ┌──────────────┐
                 │    users     │
                 └──────┬───────┘
                        │ 1:1 (existing employees.user_id UNIQUE)
                 ┌──────▼───────┐
                 │  employees   │◄──────── self-FK supervisor_id (RESTRICT)
                 └──────┬───────┘
          ┌─────────────┼─────────────┬───────────────┐
          ▼             ▼             ▼               ▼
    departments   positions    department_heads   employee_histories (NEW)
                                              └ changed_by → users
          │
          ├────────► employee_targets ──────► annual_plans ──► academic_years
          ├────────► employee_performance_records ──► academic_years
          ├────────► employee_progress_snapshots ──► academic_years
          ├────────► monthly_achievements / employee_badges ──► badges
          ├────────► tasks ──► task_assignments
          └────────► reports
```

All new FKs follow the Phase 3.1 policy: **`ON DELETE RESTRICT, ON UPDATE CASCADE`**
(protects history). `employee_histories` is append-only and NOT soft-deletable.

---

## 7. Data Privacy (§24)

- `employees` carries personal data → later-phase authorization: employee (own
  profile), HR (employee management), department head (department-scoped), admin (all).
- `gender` / `date_of_birth` / `address` are nullable and optional in forms.
- No salary, TIN/SSS, or national ID is stored (existing security decision, kept).
- Auth email lives only in `users.email`; `employees` has no email column.

---

## 8. Historical Data Strategy (kept for §8–11)

- Department/position/status/type/supervisor changes are recorded **before** the
  live column changes, so a March report still resolves the March department
  (old value is captured in `employee_histories`).
- `employee_histories` joins `audit_logs` as the accountability trail for HR
  operations; `audit_logs` keeps the system-level JSON diffs, `employee_histories`
  keeps the semantically named field history for reporting.
- Reports from a past date use the current + historical values; performance over a
  period uses `employee_performance_records` (period-scoped), not the live profile.

---

## 9. Acceptance Criteria — Phase 3.2-A Review

- [x] Existing `users` table preserved (no changes)
- [x] Employee profile architecture defined (target DDL §4.1)
- [x] Employee number unique (`uq_employees_employee_number`)
- [x] Department relationship defined (`employees.department_id` FK)
- [x] Position relationship defined (`employees.position_id` FK)
- [x] Supervisor relationship defined (self-FK `employees.supervisor_id`, NEW)
- [x] Employment status defined (7 canonical values, §3.1)
- [x] Employment type defined (`employment_type`, NEW, §3.2)
- [x] Employee history defined (`employee_histories`, NEW, §4.3)
- [x] Employee target relationship defined (`employee_targets`, mapping §4.4)
- [x] Performance relationship defined (`employee_performance_records`, §4.5)
- [x] Proper indexes defined (§5)
- [x] Foreign keys defined (RESTRICT/CASCADE policy, §6)
- [x] Historical data protected (`employee_histories` append-only; RESTRICT)
- [x] No unnecessary duplicate tables (no per-field history tables, no supervisor table)
- [x] MySQL 8 compatible (InnoDB, utf8mb4, no vendor-only constructs)
- [x] Laravel 12 compatible (Blueprint equivalents in §4.2 can express every delta)

**Completed:** verification report (3.2-E).

---

## 10. Notes / Known Limitations

- This is a **design document** that was implemented in Phase 3.2-B (migrations),
  Phase 3.2-C (models) and Phase 3.2-D (seeders/dev-data enrichment).
- Migrations executed cleanly against `acadtrack_db`; rollback tested; fresh
  install tested; all 62 backend tests pass (326 assertions); Pint passes.
- `changed_by` FK uses `ON DELETE SET NULL` (supersedes the 3.2-A draft RESTRICT):
  historical records survive removal of the actor's user account.
- Models: `EmployeeHistory` added; `Employee` gained `supervisor()/subordinates()/
  histories()`, `full_name` accessor, `inactive()/ofEmploymentType()` scopes, and
  the Phase 3.2 columns in `$fillable`; `Badge` gained `employees()` inverse.
- Nine factory files now exist: `Employee`, `Department`, `Position` (3.2-D), `User`
  (pre-existing), `AcademicYear`, `AnnualPlan`, `DepartmentHead`, `EmployeeTarget`,
  `EmployeePerformanceRecord` (3.2-E). EmployeeChain factories resolve dangling
  factory references on all employee-linked models.
- Score bounds (0.00–100.00) are app-enforced per project convention; no CHECK added.