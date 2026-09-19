# Phase 3.3-D — Planning & Task Management Seeders, Factories & Sample Data

Phase 3.3-C delivered the models for the planning and task-management domain
(see [`planning-task-models.md`](../models/planning-task-models.md)). Phase 3.3-D
makes that domain usable by seeding a realistic development dataset that walks the
full workflow:

```
Planning → Target → Task → Employee Assignment → Progress → Submission → Review → Completion
```

> All data described here is **DEVELOPMENT / TEST DATA ONLY**. It is fabricated,
> never derived from real employees, and is gated by
> `config('jpc.seed_development.enabled')` (disabled automatically when
> `APP_ENV=production`). No `Report` records, AI calls, OCR or physical files are
> created in this phase.

## How to seed

```bash
cd backend

# Run all seeders in dependency order
php artisan db:seed

# Rebuild everything from scratch (recommended for a clean dev dataset)
php artisan migrate:fresh --seed
```

Every seeder is idempotent. Re-running `php artisan db:seed` is a no-op for the
`Development*` seeders (they detect existing rows and skip) and uses
`updateOrCreate()` for dictionary/planning rows.

## Seeders

### Changed / extended in this phase

| Seeder | Change |
| ------ | ------ |
| `AcademicYearSeeder` | Also creates the immediately preceding academic year (`2025-2026`, status `completed`) so historical filtering can be tested. The prior year is derived from `config('jpc.academic_year')`. |
| `DevelopmentPlanningSeeder` | Now creates, per department, a `DepartmentPlan` and its `DepartmentTarget`s, and links the relevant `EmployeeTarget`s back to a department target via `department_target_id`. Annual plans carry real lifecycle statuses (`approved` / `draft`). |
| `DevelopmentTaskSeeder` | Fully reworked to the canonical status vocabulary, `task_code`, planning foreign keys, parent/child hierarchy, estimated hours, and overdue/late/cancelled scenarios. |

### New in this phase

| Seeder | Purpose |
| ------ | ------- |
| `DevelopmentTaskActivitySeeder` | Append-only `task_progress` history, task `comments`, attachment **metadata**, and `task_reviews` covering `approved`, `rejected` and `needs_revision`. |

### Execution order (`DatabaseSeeder`)

```
RoleSeeder → PermissionSeeder → DepartmentSeeder → PositionSeeder →
AcademicYearSeeder → BadgeSeeder →
DevelopmentUserSeeder → DevelopmentEmployeeSeeder → DevelopmentEmployeeHistorySeeder →
DevelopmentPlanningSeeder → DevelopmentTaskSeeder → DevelopmentTaskActivitySeeder →
DevelopmentReportSeeder → DevelopmentPerformanceSeeder → DevelopmentAchievementSeeder
```

`DevelopmentTaskActivitySeeder` runs after `DevelopmentTaskSeeder` (it resolves
tasks by `task_code`) and before `DevelopmentReportSeeder` / `DevelopmentAchievementSeeder`.

## Development accounts

Reused from earlier phases (no duplicates created). Identities use the `*.local`
development convention already established by `DevelopmentUserSeeder`; passwords are
hashed with `Hash::make()` and come from `config('jpc.dev_passwords')`.

| Role | Email |
| ---- | ----- |
| System Administrator | `admin@jpc.local` |
| HR Officer | `hr@jpc.local` |
| Department Head | `departmenthead@jpc.local` |
| Employee | `employee@jpc.local` |

`DevelopmentEmployeeSeeder` additionally creates one `dev-XXXX@jpc.local` account per
employee (`DEV-0001` … `DEV-0015`).

## Sample departments

Reused from `DepartmentSeeder`: Education (`EDU`), Business (`CBA`), Computing/ICT
(`CIT`), Arts & Sciences (`CAS`), Human Resources (`HR`), Administration (`ADMIN`).

## Sample employees

15 employees (`DEV-0001` … `DEV-0015`). The planning dataset focuses on five teacher
personas who each map to the progress scenario below:

| Persona | Employee | Progress |
| ------- | -------- | -------- |
| Teacher 1 | `DEV-0004` | 100% |
| Teacher 2 | `DEV-0005` | 65% |
| Teacher 3 | `DEV-0006` | 100% |
| Teacher 4 | `DEV-0007` | 80% |
| Teacher 5 | `DEV-0008` | 40% |

## Seeded planning volume

| Record | Count | Notes |
| ------ | ----- | ----- |
| Academic Years | 2 | `2026-2027` active, `2025-2026` completed |
| Annual Plans | 6 | 2 `approved`, 4 `draft` (one per department) |
| Department Plans | 6 | 2 `approved`, 4 `draft` |
| Department Targets | 8 | incl. Monthly Faculty Report Completion, Lesson Plan Submission, Student Progress Documentation, Faculty Development Activities, Academic Compliance Documentation |
| Employee Targets | 23 | 5 linked to a `department_target_id` |
| Tasks | 24 | `TASK-2026-0001` … `TASK-2026-0024` |
| Task Assignments | 23 | one task shared by two employees |
| Task Progress | 66 | append-only 0 → 25 → 50 → … history |
| Task Comments | 6 | author = admin / department head |
| Task Attachments | 5 | metadata only, `development/task-attachments/...` |
| Task Reviews | 7 | 4 approved, 1 rejected, 2 needs_revision |

## Task statuses

Every canonical status is represented (`docs/database/planning-task-management.md` §11):

| Status | Count | Example |
| ------ | ----- | ------- |
| `draft` | 1 | TASK-2026-0014 Prepare Teaching Load Compliance Report |
| `assigned` | 2 | TASK-2026-0002 Prepare Faculty Report, TASK-2026-0012 Prepare Academic Event Documentation |
| `in_progress` | 9 | TASK-2026-0001 Prepare Monthly Faculty Report (flagship) |
| `submitted` | 2 | TASK-2026-0010 Curriculum Development Report, TASK-2026-0018 Monthly Grade Encoding Summary |
| `under_review` | 1 | TASK-2026-0017 Submit OBE Course Syllabus |
| `completed` | 7 | TASK-2026-0005 Faculty Accomplishment Report, TASK-2026-0022 Academic Compliance Report (late) |
| `rejected` | 1 | TASK-2026-0008 Submit Business Faculty Survey Report |
| `cancelled` | 1 | TASK-2026-0023 Prepare Quarterly Procurement Audit |

Assignment statuses (`assigned`, `accepted`, `in_progress`, `submitted`, `completed`,
`rejected`, `cancelled`) are all represented as well — see
`TaskAssignment::selectRaw(...)` in the seeder tests.

## Task assignments

### Multiple employees on one task (required)

`TASK-2026-0001 — Prepare Monthly Faculty Report`

| Employee | Progress | Assignment status |
| -------- | -------- | ----------------- |
| `DEV-0004` (Teacher 1) | 100% | `completed` (2026-06-25) |
| `DEV-0005` (Teacher 2) | 65% | `in_progress` |

Task overall status: `in_progress`. This satisfies the project requirement that one
task can be assigned to multiple employees.

### Parent / child hierarchy

`TASK-2026-0024 — Prepare Monthly Faculty Documentation` (parent) has three children:

| Code | Child |
| ---- | ----- |
| `TASK-2026-0002` | Prepare Faculty Report |
| `TASK-2026-0003` | Prepare Attendance Summary |
| `TASK-2026-0004` | Prepare Supporting Documents |

## Progress scenarios

- Progress records are generated in 25-point steps from `0` to the assignment's current
  percentage, giving an append-only audit trail (Teacher 1: 0/25/50/75/100; Teacher 2:
  0/25/50/65).
- **Completed** tasks carry `completed_at`.
- **Overdue**: ≥ 2 tasks past `due_date` that are not completed/cancelled
  (e.g. TASK-2026-0007, TASK-2026-0020 at 80%, TASK-2026-0021 at 40%).
- **Late submission**: `TASK-2026-0022 — Submit Academic Compliance Report` was due
  `2026-08-15` but completed `2026-08-17`.
- **On-time**: completed tasks whose `completed_at` is on/before `due_date`.
- **Monthly spread**: task activity and completions occur across June, July and August
  2026 so monthly reports, completion and achievement rankings can be derived later.

## Review scenarios

| Task | Decision | Remarks |
| ---- | -------- | ------- |
| TASK-2026-0005 | `approved` | Documentation is complete and consistent with the required output. |
| TASK-2026-0008 | `rejected` | Documentation is incomplete. Please revise and resubmit. |
| TASK-2026-0010 | `needs_revision` | Additional supporting document required. |
| TASK-2026-0017 | `needs_revision` | Additional supporting document required. |
| TASK-2026-0006 / 0013 / 0022 | `approved` | Various |

Reviews are authored by `admin@jpc.local` or `departmenthead@jpc.local` only (never
random employees), matching the RBAC model.

## Factories

New factories (all under `backend/database/factories`):

| Factory | States |
| ------- | ------ |
| `TaskFactory` | `draft`, `assigned`, `inProgress`, `submitted`, `underReview`, `completed`, `rejected`, `cancelled`, `overdue` |
| `TaskAssignmentFactory` | `assigned`, `inProgress`, `submitted`, `completed`, `rejected`, `overdue` |
| `TaskProgressFactory` | `completed` |
| `TaskCommentFactory` | — |
| `TaskAttachmentFactory` | — |
| `TaskReviewFactory` | `approved`, `rejected`, `needsRevision` |
| `DepartmentPlanFactory` | `approved`, `active` |
| `DepartmentTargetFactory` | `completed` |
| `TaskReferenceFactory` | — |

Relationships are resolved automatically (no manual ID injection): a
`TaskAssignmentFactory` creates its `Task` and `Employee`; `TaskProgressFactory` and
`TaskReviewFactory` derive `task_id` / `employee_id` from the created assignment;
`DepartmentPlanFactory` reuses the parent `AnnualPlan`'s department.

## Tests

- `tests/Feature/Planning/PlanningSeederTest.php` — dataset shape, full status coverage,
  multi-assignment, hierarchy, overdue/late scenarios, activity data and idempotency.
- `tests/Feature/Planning/PlanningFactoryTest.php` — factory states and relationships.

Run the phase checks:

```bash
cd backend
php vendor/bin/pint
php artisan test tests/Feature/Planning
```

## Verification snippets

```php
use App\Models\Task;

// Multi-assignment
Task::where('task_code', 'TASK-2026-0001')->first()->assignments()->count(); // 2

// Parent / child
Task::where('task_code', 'TASK-2026-0024')->first()->children()->count();     // 3

// Overdue
Task::overdue()->count();                                                      // 7

// Dashboard-ready counts
Task::count();            // 24
Task::completed()->count(); // 7
Task::pending()->count();   // 15
Task::underReview()->count(); // 1
```

## Reset the development database

```bash
cd backend
php artisan migrate:fresh --seed
```

This drops and recreates every table in `acadtrack_db` and re-seeds. It is safe to run
repeatedly — the seeders are idempotent and no duplicate-key errors occur.
