# JPC Portal — Employee Management (Phase 3.2)

| Item | Value |
| ---- | ----- |
| Module | Employee Management Foundation |
| Phases | 3.2-A Design · 3.2-B Migrations · 3.2-C Models · 3.2-D Seeders/Factories · 3.2-E Verification |
| Status | **COMPLETE** — all phases implemented and verified (Phase 3.2-E report) |
| Stack | Laravel 12 · MySQL 8 (`acadtrack_db`) · XAMPP local |
| Related docs | [`docs/database/employee-management.md`](database/employee-management.md) (design), [`docs/database/employee-seeding.md`](database/employee-seeding.md), [`docs/database/seeders.md`](database/seeders.md) |

---

## 1. Purpose

Provides the stable employee-management foundation used by the future Task
Management, Report Management and AI Validation modules: employee profiles,
department/position assignment, supervisor hierarchy, employment status/type,
append-only profile history, employee targets and performance records.

This phase does **not** add AI, OCR, Task or Report Management — those are future
modules that build on this foundation.

## 2. Architecture

```
                    JPC PORTAL
                        │
                     USERS
                        │ 1:1 (unique employees.user_id)
                        ▼
                    EMPLOYEES        ← soft-deleted
                        │
        ┌───────────────┼────────────────┐
        │               │                │
   Department       Position (nullable) Supervisor (self-FK)
                                     └──► Subordinates
                        │
        ┌───────────────┼────────────────────┐
        │               │                    │
     History         Targets             Performance
   (append-only)  ──► AnnualPlan   ──► AcademicYear
                        │
                  FUTURE MODULES
                        │
              ┌─────────┴─────────┐
              ▼                   ▼
         TASK MANAGEMENT     REPORT MANAGEMENT
                                      │
                                      ▼
                              AI VALIDATION/OCR
```

Forward-looking (already compatible, built in Phase 3.1):

```
Employee
    ↓
TaskAssignment
    ↓
Task
```

## 3. Tables

| Table | Purpose | Important columns |
| ----- | ------- | ----------------- |
| `employees` | Employee profile + dept/position/supervisor | `employee_number` (UNIQUE), `user_id` (UNIQUE, nullable, 1:1), `department_id` (NOT NULL FK), `position_id` (NULL FK), `supervisor_id` (self-FK), `employment_status`, `employment_type`, `date_hired`, `date_ended`, `date_of_birth`, `gender`, `phone`, `address`, `profile_photo_path`, `deleted_at` (soft delete) |
| `employee_histories` | Append-only change log | `employee_id` (FK, RESTRICT), `field_name`, `old_value`, `new_value`, `effective_date`, `reason`, `changed_by` (FK users, SET NULL). Never soft-deleted. |
| `employee_targets` | Targets within annual plans (`title` ≡ `target_name`, `target_unit` ≡ `unit`) | `employee_id` (FK), `annual_plan_id` (FK), `target_value DECIMAL(12,2)`, `status` (draft/active/completed/cancelled) |
| `employee_performance_records` | Period-scoped performance scores | `employee_id` (FK), `academic_year_id` (FK), `period_type`, `period_start`, `period_end` (UNIQUE `uq_employee_performance_records_period`), five `DECIMAL(5,2)` rates, `overall_score`, `performance_status` |

Dependency tables (Phase 3.1): `users`, `departments`, `positions`,
`department_heads`, `academic_years`, `annual_plans`, `employee_progress_snapshots`,
`tasks`, `task_assignments`, `reports` (all unchanged by Phase 3.2).

### Delete behavior

- `employees` — **soft delete** (`deleted_at`); history and period records survive.
- `employee_histories`, `employee_targets`, `employee_performance_records` — hard
  delete restricted by FK (`ON DELETE RESTRICT`); protected records cannot be
  destroyed while the employee exists.
- `employee_histories.changed_by` — `ON DELETE SET NULL`.

## 4. Models

| Model | Namespace | Notes |
| ----- | --------- | ----- |
| `Employee` | `App\Models\Employee` | `SoftDeletes`, self-FK `supervisor()/subordinates()`, `user()`, `department()`, `position()`, `histories()`, `employeeTargets()`, `performanceRecords()`, `full_name` accessor, `active()/inactive()/byDepartment()/byPosition()/ofEmploymentType()` scopes |
| `EmployeeHistory` | `App\Models\Employee` | `employee()`, `changedBy()`; append-only (no soft delete) |
| `EmployeeTarget` | `App\Models\Employee` | `employee()`, `annualPlan()` |
| `EmployeePerformanceRecord` | `App\Models\Employee` | `employee()`, `academicYear()`; `monthly()/quarterly()/yearly()/atRisk()` scopes; verdict fields guarded |

No AI, OCR, NLP or external-API logic lives in any Employee model (§44).

## 5. Relationships

Forward and reverse:

- `User 1:1 Employee` — `$user->employee`, `$employee->user`
- `Employee N:1 Department` — `$employee->department`, `$department->employees`
- `Employee N:1 Position` — `$employee->position`, `$position->employees`
- `Employee self Nth → Mth Supervisor/Subordinates` — `$employee->supervisor`, `$employee->subordinates`
- `Employee 1:N EmployeeHistory` — `$employee->histories`
- `Employee 1:N EmployeeTarget` — `$employee->employeeTargets`, `AnnualPlan 1:N EmployeeTarget`
- `Employee 1:N EmployeePerformanceRecord` — `$employee->performanceRecords`, `AcademicYear 1:N EmployeePerformanceRecord`

## 6. Roles

Existing RBAC; no new authorization mechanism was created:

- `admin` — full access
- `hr` — employee management
- `department_head` — department-scoped access
- `employee` — own profile access

## 7. Employee hierarchy

- `DepartmentHead` lives in the existing `department_heads` history table
  (`is_current` + generated-column uniqueness, one current head per department).
- Supervisor is a **self-referencing FK** on `employees.supervisor_id` (line
  manager), distinct from the department-head role.
- Dev data: 15 employees, 13 have a supervisor, 2 current department heads. The
  documented cross-department exception is `DEV-0002` (HR) → `DEV-0001` (Admin).

## 8. Seed data

DEVELOPMENT-ONLY data, gated by `config('jpc.seed_development.enabled')`.

| Data | Count |
| ---- | ----- |
| Users | 15 |
| Employees | 15 |
| Departments | 6 |
| Positions | 11 |
| Employee histories | 13 |
| Employee targets | 23 |
| Performance records | 45 |
| Department heads (current) | 2 |

Development accounts (passwords hashed, dev-only fallback `P@ssw0rd2026`):

- `admin@jpc.local` — admin
- `hr@jpc.local` — hr
- `departmenthead@jpc.local` — department_head
- `employee@jpc.local` — employee
- `dev-0005@jpc.local` … `dev-0015@jpc.local` — one account per seeded employee

All accounts use the `@jpc.local` development domain. **DEVELOPMENT ONLY — never use
seeded credentials in production.**

## 9. Testing

```
php artisan test               # 62 tests / 326 assertions — all pass
vendor\bin\pint --test         # code style passes
```

Test files:

- `tests/Feature/Employee/EmployeeModelTest.php`
- `tests/Feature/Employee/EmployeeRelationshipTest.php`
- `tests/Feature/Employee/EmployeeDataIntegrityTest.php`
- `tests/Feature/Employee/EmployeeSeederTest.php`
- `tests/Feature/Database/EmployeeFactoryTest.php`
- `tests/Feature/Database/DevelopmentEmployeeDatasetTest.php`

## 10. Development commands

```
php artisan migrate:status     # verify installed migrations
php artisan migrate            # apply pending migrations
php artisan migrate:fresh --seed  # clean rebuild + dev seed (LOCAL ONLY)
php artisan db:seed            # seed (idempotent — safe to re-run)
php artisan test               # full test suite
vendor\bin\pint                # apply code style
```