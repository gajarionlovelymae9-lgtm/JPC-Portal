# Database Seeders

This document describes every seeder in `backend/database/seeders`, how they depend on
each other, and what the development sample dataset (Phase 3.1-D data, enriched by
Phase 3.2-D for Employee Management) looks like.

## Execution

```bash
cd backend

# Run every seeder (DatabaseSeeder)
php artisan db:seed

# Run one seeder by name
php artisan db:seed --class=RoleSeeder
```

Every seeder is **idempotent**: running `php artisan db:seed` any number of times never
duplicates data. Static dictionaries use `updateOrCreate()`; the development seeders
skip themselves entirely when their rows already exist.

> **Warning:** the `Development*` seeders create fabricated users, employees, tasks,
> reports, achievements and notifications. They must **never** run against production.
> They are disabled automatically when `APP_ENV=production` and only run then if
> `SEED_DEVELOPMENT_DATA=true` is set explicitly (see
> [`config/jpc.php`](../../backend/config/jpc.php)).

## Seeder list and dependency order

`DatabaseSeeder` calls seeders in this order; earlier seeders provide rows that later
seeders reference by `firstOrFail()`.

| # | Seeder | Purpose | Kind |
| - | ------ | ------- | ---- |
| 1 | `RoleSeeder` | The four roles: Administrator, Human Resources, Department Head, Employee. | Static, idempotent |
| 2 | `PermissionSeeder` | Full permission catalog (81) and the permissions assigned per role. | Static, idempotent |
| 3 | `DepartmentSeeder` | Departments: Education (EDU), Business (CBA), Computing/ICT (CIT), Arts & Sciences (CAS), Human Resources (HR), Administration (ADM). | Static, idempotent |
| 4 | `PositionSeeder` | Standard position catalog (11). | Static, idempotent |
| 5 | `AcademicYearSeeder` | The current academic year (default `2026-2027`, configurable) plus the preceding year as `completed`. | Static, idempotent |
| 6 | `BadgeSeeder` | Achievement badge catalog (8). | Static, idempotent |
| 7 | `DevelopmentUserSeeder` | The four canonical development accounts (one per role). | Dev, guarded |
| 8 | `DevelopmentEmployeeSeeder` | 15 development employee profiles across all departments: profile data, employment type/status variety, supervisor hierarchy, a `dev-XXXX@jpc.local` account per employee, plus department head links. | Dev, guarded |
| 9 | `DevelopmentEmployeeHistorySeeder` | 13 append-only `employee_histories` records leading into the current employee state (status/type/position/department/supervisor transitions and profile corrections). | Dev, guarded |
| 10 | `DevelopmentPlanningSeeder` | One annual plan + department plan per department, 8 department targets, and 23 employee targets (5 linked to department targets). | Dev, guarded |
| 11 | `DevelopmentTaskSeeder` | 24 tasks (`TASK-2026-0001`…`0024`) with 23 assignments, covering every canonical status, planning links and a parent/child hierarchy; one task is shared by two employees. | Dev, guarded |
| 12 | `DevelopmentTaskActivitySeeder` | Append-only task progress (66), comments (6), attachment metadata (5) and reviews (7: approved/rejected/needs_revision). | Dev, guarded |
| 13 | `DevelopmentReportSeeder` | 10 reports covering approved / pending / late / rejected / revision flows, with versions, sample AI analyses, reviews and approvals. | Dev, guarded |
| 14 | `DevelopmentPerformanceSeeder` | Monthly performance records (3 months x 15 employees). | Dev, guarded |
| 15 | `DevelopmentAchievementSeeder` | Monthly top-3 achievements and selective badge awards consistent with performance. | Dev, guarded |

Full details of the planning/task development dataset are documented in
[`phase-3-3-d-seeders.md`](phase-3-3-d-seeders.md).

Phase 2 wrappers `RolePermissionSeeder` and `UserSeeder` still exist and delegate to the
above. `MiddlewareTest` and `AuthTest` seed via those wrappers.

## Guards

The `Development*` seeders check two things before inserting:

1. `config('jpc.seed_development.enabled')` — defaults to *true outside production*.
2. A table-specific "already exists" check (e.g. `Employee::count() > 0`) so repeated
   `db:seed` runs are no-ops.

Complex data (employees, plans, tasks, reports, achievements) is written inside a single
`DB::transaction()` so a failing run never leaves a half-seeded dataset.

## Development sample data

### Accounts (development only)

| Role | Email |
| ---- | ----- |
| Admin | `admin@jpc.local` |
| HR | `hr@jpc.local` |
| Department Head | `departmenthead@jpc.local` |
| Employee | `employee@jpc.local` |

These four canonical accounts are complemented by 11 deterministic per-employee
accounts (`dev-0005@jpc.local` … `dev-0015@jpc.local`, Employee role) created by
`DevelopmentEmployeeSeeder`, so every seeded employee has a login account.

Passwords come from `SEED_ADMIN_PASSWORD`, `SEED_HR_PASSWORD`,
`SEED_DEPARTMENT_HEAD_PASSWORD`, `SEED_EMPLOYEE_PASSWORD` and fall back to the
development-only password `P@ssw0rd2026` used by the Phase 2/3 test suites. All
passwords are stored hashed via the `User` model's `'hashed'` cast.

### Dataset overview (as seeded by the current run)

| Table | Rows | Notes |
| ----- | ---- | ----- |
| users | 15 | 4 canonical role accounts + 11 per-employee `dev-XXXX` accounts |
| roles | 4 | Administrator, Human Resources, Department Head, Employee |
| permissions | 81 | Full 3.1-D catalog |
| departments | 6 | EDU, CBA, CIT, CAS, HR, ADM |
| positions | 11 | Standard position catalog |
| academic_years | 1 | `2026-2027` (configurable) |
| badges | 8 | Achievement badge catalog |
| employees | 15 | `DEV-0001` … `DEV-0015`; every employee linked to an account, one user per employee |
| department_heads | 2 | CIT (DEV-0003) and Education (DEV-0015) |
| annual_plans | 6 | One per department |
| employee_targets | 23 | draft(3) / active(17) / completed(3) |
| employee_histories | 13 | Append-only profile change log (Phase 3.2-D) |
| tasks | 16 | Include submit / monthly / project / training tasks |
| task_assignments | 17 | T1 is assigned to two employees |
| reports | 10 | approved(6) / submitted(3, one late) / rejected(1) |
| report_versions | 12 | Two reports have v2 (revision flows) |
| ai_analyses | 13 | `task_alignment` everywhere; `summarization` on 3 reports |
| ai_summaries | 3 | Sample summaries (clearly labelled sample data) |
| ai_validation_results | 10 | Scores 0.84–0.98, sample provider `jpc.sample` |
| ai_validation_items | 2 | Warning items on the rejected report |
| report_reviews | 9 | initial / revision with approved, rejected, revision_required |
| report_approvals | 6 | Approved reports only |
| employee_performance_records | 45 | 3 months (Jun–Aug 2026) x 15 employees |
| monthly_achievements | 9 | Top 3 per month; rank 1 = employee of the month |
| employee_badges | 16 | Selective awards consistent with performance |
| notifications | 9 | report_approved / report_submitted / report_rejected / badge_awarded / system |

### Notes on the sample data

- **Employee variety (Phase 3.2-D).** Employees span multiple employment statuses
  (active / on_leave / inactive), employment types (regular / probationary /
  contractual), and a supervisor hierarchy of 10 direct reports under two
  department heads. 13 `employee_histories` records lead into the current state.
  See [`docs/database/employee-seeding.md`](employee-seeding.md).
- **No `report_files` / OCR rows.** Development reports have no physical files, so no
  fake storage paths are seeded (see Phase 3.1-D Step 24).
- **AI rows are samples.** Provider is `jpc.sample`, model `development-sample`,
  status `completed`, and explanations say `DEVELOPMENT SAMPLE DATA`. No external AI
  provider is ever called from a seeder.
- **Timestamps are deterministic** and never in the future relative to the system
  "today" (2026-09-14); late submissions are allowed and marked `is_late`.
- **Deletion is not cascading by default** in the schema, so development data should be
  removed with `php artisan migrate:fresh` (full drop/re-migrate) or per-table manual
  cleanup when starting a new dev environment.

## Resetting the database

```bash
cd backend
php artisan migrate:fresh --seed   # drop all tables, re-migrate, seed
```

## Related documentation

- Schema: [`docs/database/schema.sql`](schema.sql) (authoritative vocabulary for statuses,
  types and score ranges used by the seed data).
- Design: [`docs/database/database-design.md`](database-design.md).
- Employee dataset walkthrough: [`docs/database/employee-seeding.md`](employee-seeding.md).
- Configuration: [`backend/config/jpc.php`](../../backend/config/jpc.php).
- Phase 3.3-E business rules: [`../task-management/phase-3-3-e-business-rules.md`](../task-management/phase-3-3-e-business-rules.md).