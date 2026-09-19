# Employee Management — Development Seeding (Phase 3.2-D)

This document describes the development (sample) dataset for the Employee
Management module: how it is seeded, the accounts, the departments/positions,
the supervisor hierarchy, the employment variety, and the performance/target
scenarios the sample data is designed to demonstrate.

All data is **fake**. No real employee names, emails, phones, addresses, or
credentials are stored anywhere. Seeders run only in non-production
environments (or explicitly with `SEED_DEVELOPMENT_DATA=true`) and are
**idempotent** — re-running `php artisan db:seed` never duplicates rows.

## How to seed / reset

```bash
cd backend

php artisan migrate:fresh --seed   # drop all tables, re-migrate, seed everything
php artisan db:seed                # safe re-run (development seeders self-skip)
```

## Seeder ownership

| Seeder | Provides |
| ------ | -------- |
| `DevelopmentUserSeeder` | The four canonical role accounts (admin, hr, department_head, employee). |
| `DevelopmentEmployeeSeeder` | 15 employees (profile data, employment variety, supervisor hierarchy) and the per-employee `dev-XXXX@jpc.local` accounts. |
| `DevelopmentEmployeeHistorySeeder` | 13 append-only profile-change records (`employee_histories`). |
| `DevelopmentPlanningSeeder` | 6 annual plans and 23 employee targets. |
| `DevelopmentPerformanceSeeder` | 45 monthly performance records (Jun–Aug 2026). |

## Development accounts

15 accounts in total. The four canonical role accounts are:

| Role | Email |
| ---- | ----- |
| Admin | `admin@jpc.local` |
| HR | `hr@jpc.local` |
| Department Head | `departmenthead@jpc.local` |
| Employee | `employee@jpc.local` |

The remaining 11 employees each get a deterministic account with the Employee
role, e.g. `dev-0005@jpc.local` … `dev-0015@jpc.local` (lower-cased employee
number). Every employee row therefore has a linked `user_id` — see the
"one User per Employee" goal in the module design.

Passwords come from `SEED_ADMIN_PASSWORD`, `SEED_HR_PASSWORD`,
`SEED_DEPARTMENT_HEAD_PASSWORD`, `SEED_EMPLOYEE_PASSWORD` and fall back to the
development-only password `P@ssw0rd2026`. They are stored hashed by the `User`
model's `'hashed'` cast and are documented in
[`backend/config/jpc.php`](../../backend/config/jpc.php).

## Departments & positions

Six departments from `DepartmentSeeder`:

| Code | Name |
| ---- | ---- |
| EDU | College of Education |
| CBA | College of Business Administration |
| CIT | College of Information Technology |
| CAS | College of Arts and Sciences |
| HR | Human Resources |
| ADMIN | Administration |

All 11 catalog positions from `PositionSeeder` are represented across the 15
employees (`SYS_ADMIN`, `HR_MANAGER`, `DEPT_HEAD`, `TEACHER`, `INSTRUCTOR`,
`PROFESSOR`, `IT_STAFF`, `HR_OFFICER`, `ADMIN_STAFF`, …).

## Supervisor hierarchy

`employees.supervisor_id` links each employee to their line manager. Two
department heads manage their department members; two senior faculty supervise
peers in headless departments; HR and Administration report into the Admin
account. (No circular references; the top of each chain has `NULL`.)

| Supervisor (employee) | Role | Reports to them |
| --------------------- | ---- | --------------- |
| DEV-0001 Administrator | Top of chain | DEV-0002 (HR Manager), DEV-0014 (Admin Staff) |
| DEV-0003 Dept Head (CIT) | Top of chain | DEV-0009 (Instructor), DEV-0010 (IT Staff) |
| DEV-0008 Professor (CBA) | Top of chain | DEV-0007 (Instructor) |
| DEV-0012 Professor (CAS) | Top of chain | DEV-0011 (Instructor) |
| DEV-0015 Dept Head (EDU) | Top of chain | DEV-0004, DEV-0005, DEV-0006 (Teachers) |

10 of 15 employees report to a supervisor. The two current department heads
are DEV-0003 (CIT) and DEV-0015 (EDU) via `department_heads`.

## Employment variety

Canonical vocabulary (see `docs/database/schema.sql`):

- `employment_status`: **active** (13), **inactive** (1), **on_leave** (1)
  — full canonical set: `active | inactive | on_leave | suspended | resigned |
  retired | terminated`.
- `employment_type`: **regular** (9), **probationary** (5), **contractual** (1)
  — full canonical set: `regular | probationary | part_time | contractual |
  temporary`.

Profile data (gender, `date_of_birth`, phone, address) is populated on every
employee; `date_of_birth` is also cast as a date on the model.

## Employee history records

`DevelopmentEmployeeHistorySeeder` writes 13 append-only `employee_histories`
records that lead into the current seeded state, e.g.:

- employment transitions: probation → regular (DEV-0006), regular → contractual
  (DEV-0010), active → on_leave (DEV-0009), active → inactive (DEV-0014, with
  `date_ended` set)
- position changes: IT_STAFF → DEPT_HEAD (DEV-0003), HR_ASSISTANT → HR_OFFICER
  (DEV-0013)
- department transfer: CAS → CBA (DEV-0008)
- supervisor assignments (DEV-0004, DEV-0007)
- profile corrections (date_of_birth, phone, address, gender)

Every record carries `effective_date` and `changed_by` (admin or HR account);
`employee_histories.changed_by` is ON DELETE SET NULL so history survives
account deletion.

## Targets & performance scenarios

**Employee targets** (`employee_targets`): 23 targets across the 6 annual
plans, 2+ for most employees, with status variety `active` (17), `completed`
(3), `draft` (3). Values/units are realistic (reports, documents, percent,
prototypes, observations, albums).

**Performance records** (`employee_performance_records`): 45 records covering
June–August 2026 (3 months × 15 employees), with three scenarios:

| Scenario | Employees | Current `performance_status` split |
| -------- | --------- | --------------------------------- |
| High performers | DEV-0004, DEV-0013, DEV-0015 | improving (9) |
| Average / consistent | Most employees | consistent (30) |
| Needs improvement | DEV-0001, DEV-0009 | at_risk (6) |

`overall_score` is derived with the documented 40/30/30 weighting
(completion/on-time/quality) and is stored guarded on the model. Achievements
and badges (`DevelopmentAchievementSeeder`) are consistent with these records.

## Related documentation

- Schema vocabulary & ERD: [`docs/database/schema.sql`](schema.sql), [`docs/database/erd.md`](erd.md)
- Seeder overview & counts: [`docs/database/seeders.md`](seeders.md)
- Eloquent relationships/accessors for these tables: [`docs/database/eloquent-models.md`](eloquent-models.md)
- Employee Management design & delta: [`docs/database/employee-management.md`](employee-management.md)