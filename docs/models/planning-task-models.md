# JPC Portal — Planning & Task Management Eloquent Models (Phase 3.3-C)

Scope: models, relationships, casts, `$fillable`, query scopes and model-level
helpers for the Planning & Task Management domain only. No controllers, API,
React, AI, OCR or reports work was performed in this phase.

- Models live in `backend/app/Models/` (namespace `App\Models`).
- The database schema (Phase 3.3-B migrations) is the source of truth; every
  foreign key used by a relationship physically exists.
- Factories/seeders for the new tables are deferred to Phase 3.3-D. Tests create
  records with explicit `Model::query()->create(...)` calls.

---

## 1. Model Inventory

### New models (created in 3.3-C)

| Model | Table | Soft Deletes |
| --- | --- | --- |
| `DepartmentPlan` | `department_plans` | yes |
| `DepartmentTarget` | `department_targets` | yes |
| `TaskProgress` | `task_progress` | no (append-only) |
| `TaskComment` | `task_comments` | yes |
| `TaskAttachment` | `task_attachments` | yes |
| `TaskReview` | `task_reviews` | no (append-only) |

### Updated models

| Model | Changes |
| --- | --- |
| `AnnualPlan` | `departmentPlans()` added; `creator()`→`createdBy()`, `approver()`→`approvedBy()`; scopes `draft/approved/active` |
| `EmployeeTarget` | `department_target_id` fillable; `departmentTarget()`; `tasks()` |
| `Task` | planning FKs fillable; `departmentPlan/departmentTarget/employeeTarget/parent/children/updatedBy/progress/comments/attachments/reviews`; lifecycle + priority scopes; `isOverdue()/isCompleted()`; `creator()`→`createdBy()` |
| `TaskAssignment` | `SoftDeletes`; `accepted_at`/`remarks` fillable; `progress/comments/attachments/reviews`; lifecycle + overdue scopes; `isOverdue()`; `assigner()`→`assignedBy()` |
| `Department` | `departmentPlans()`, `departmentTargets()` |
| `User` | `createdAnnualPlans/approvedAnnualPlans/createdDepartmentPlans/approvedDepartmentPlans/createdTasks/updatedTasks/assignedTasks/reviewedTasks` |
| `AcademicYear` | `employeeTargets()` corrected to `hasManyThrough(AnnualPlan)` (previously pointed at a non-existent `academic_year_id` column on `employee_targets`) |

`Employee` was already complete (`employeeTargets()`, `taskAssignments()`) and was
not changed. `TaskReference` was unchanged.

---

## 2. Relationship Matrix (Planning Domain)

| Model | Relationship | Target | Foreign key |
| --- | --- | --- | --- |
| AnnualPlan | belongsTo | AcademicYear | `academic_year_id` |
| AnnualPlan | belongsTo | Department | `department_id` |
| AnnualPlan | belongsTo | User (`createdBy`) | `created_by` |
| AnnualPlan | belongsTo | User (`approvedBy`) | `approved_by` |
| AnnualPlan | hasMany | DepartmentPlan, EmployeeTarget, Task | |
| DepartmentPlan | belongsTo | AnnualPlan | `annual_plan_id` |
| DepartmentPlan | belongsTo | Department | `department_id` |
| DepartmentPlan | belongsTo | User (`createdBy`, `approvedBy`) | `created_by`, `approved_by` |
| DepartmentPlan | hasMany | DepartmentTarget, Task | |
| DepartmentTarget | belongsTo | DepartmentPlan, Department, User (`createdBy`) | `department_plan_id`, `department_id`, `created_by` |
| DepartmentTarget | hasMany | EmployeeTarget, Task | |
| EmployeeTarget | belongsTo | AnnualPlan, DepartmentTarget, Employee | `annual_plan_id`, `department_target_id`, `employee_id` |
| EmployeeTarget | hasMany | Task | `employee_target_id` |
| Task | belongsTo | AnnualPlan, DepartmentPlan, DepartmentTarget, EmployeeTarget, Department, User (`createdBy`, `updatedBy`) | nullable planning links |
| Task | belongsTo | Task (`parent`) | `parent_task_id` (self-FK) |
| Task | hasMany | Task (`children`) | `parent_task_id` |
| Task | hasMany | TaskAssignment, TaskProgress, TaskComment, TaskAttachment, TaskReview, TaskReference, Report | |
| TaskAssignment | belongsTo | Task, Employee, User (`assignedBy`) | `task_id`, `employee_id`, `assigned_by` |
| TaskAssignment | hasMany | TaskProgress, TaskComment, TaskAttachment, TaskReview | |
| TaskProgress | belongsTo | Task, TaskAssignment (`assignment`), Employee, User (`updatedBy`) | `task_id`, `task_assignment_id`, `employee_id`, `updated_by` |
| TaskComment | belongsTo | Task, TaskAssignment (`assignment`), User | `task_id`, `task_assignment_id`, `user_id` |
| TaskAttachment | belongsTo | Task, TaskAssignment (`assignment`), User (`uploadedBy`) | `task_id`, `task_assignment_id`, `uploaded_by` |
| TaskReview | belongsTo | Task, TaskAssignment (`assignment`), User (`reviewedBy`) | `task_id`, `task_assignment_id`, `reviewed_by` |
| User | hasMany | AnnualPlan (created/approved), DepartmentPlan (created/approved), Task (created/updated), TaskAssignment (assigned), TaskReview (reviewed) | |
| Department | hasMany | DepartmentPlan, DepartmentTarget, Task | |
| Employee | hasMany | EmployeeTarget, TaskAssignment | |
| AcademicYear | hasManyThrough | EmployeeTarget | via `annual_plans` |

---

## 3. Query Scopes

| Model | Scope | Behavior |
| --- | --- | --- |
| AnnualPlan | `draft()` / `approved()` / `active()` | `status` filter |
| DepartmentPlan | `draft()` / `active()` / `approved()` | `status` filter |
| DepartmentTarget | `draft()` / `active()` / `completed()` | `status` filter |
| EmployeeTarget | `active()` / `completed()` | `status` filter |
| Task | `draft/assigned/inProgress/submitted/underReview/completed/rejected/cancelled` | lifecycle `status` |
| Task | `highPriority()` / `urgent()` | `priority` filter |
| Task | `pending()` | `status = not_started` |
| Task | `overdue()` | `due_date < now()`, `completed_at IS NULL`, status not completed/cancelled |
| Task | `byDepartment($id)` | `department_id` filter |
| TaskAssignment | `assigned/accepted/inProgress/submitted/completed/rejected/cancelled` | lifecycle `status` |
| TaskAssignment | `pending()` | `status IN (not_started, in_progress)` |
| TaskAssignment | `overdue()` | same semantics as Task |
| TaskAssignment | `forEmployee($id)` | `employee_id` filter |
| TaskReview | `approved()` / `rejected()` / `needsRevision()` | `decision` filter |

`scopeOverdue()` keeps the index-friendly `where('due_date', '<', ...)` form (no
per-row `whereDate()`), consistent with the Phase 3.2 convention.

---

## 4. Model Helpers

| Model | Helper | Behavior |
| --- | --- | --- |
| Task | `isOverdue(): bool` | `due_date` past **and** `completed_at === null` **and** status not completed/cancelled |
| Task | `isCompleted(): bool` | `status === 'completed'` |
| TaskAssignment | `isOverdue(): bool` | same rules as Task |

Helpers are pure attribute checks — they never issue queries.

---

## 5. Casts

- Dates (`date`): `DepartmentTarget.start_date/end_date`, `TaskAssignment.start_date/due_date`.
- Datetimes (`datetime`): `AnnualPlan.approved_at`, `DepartmentPlan.approved_at`,
  `Task.start_date/due_date/completed_at`, `TaskAssignment.assigned_at/accepted_at/completed_at`,
  `TaskReview.reviewed_at`.
- Decimals (`decimal:2`): `DepartmentTarget.target_value`, `EmployeeTarget.target_value`,
  `Task.estimated_hours/target_value`, `TaskAssignment.progress_percentage`,
  `TaskProgress.previous_percentage/new_percentage`.
- Integer: `TaskAttachment.file_size`.

`Task.start_date` / `Task.due_date` remain DB `date` columns but are cast as
`datetime` per the Phase 3.3 spec, so comparisons and Carbon helpers behave
uniformly.

---

## 6. Security / Mass Assignment

- Every new model declares an explicit `$fillable` whitelist; `id`, `created_at`,
  `updated_at` and `deleted_at` are never fillable.
- Privileged lifecycle columns (`status`, `decision`, `approved_by`, `reviewed_by`)
  are fillable only for the models where a workflow writes them; approval/review
  outcomes are still expected to be driven by services, not mass-assigned payloads.
- No accessor performs a query; no business logic (`approve()`, `reject()`,
  `submit()`) or AI/report method exists on these models — those arrive in later
  phases.
- Soft deletes are applied **only** where the schema has `deleted_at`
  (`AnnualPlan`, `DepartmentPlan`, `DepartmentTarget`, `Task`, `TaskAssignment`,
  `TaskComment`, `TaskAttachment`). `TaskProgress` and `TaskReview` stay
  append-only.

---

## 7. Deviations & Decisions

1. **`department_plans` is a real table** (Phase 3.3-B decision). This model
   supersedes the earlier 3.3-A idea of reusing `annual_plans.department_id`.
2. **`progress_percentage` retained** on `TaskAssignment` as the physical column
   name for the spec's "completion_percentage"; no rename was introduced.
3. **`task_code` is nullable-unique**; generation remains in application/seeder
   code (Phase 3.3-D). No model-level boot hook generates it.
4. **`AcademicYear::employeeTargets()` fixed** to `hasManyThrough(AnnualPlan)` —
   the previous `hasMany(EmployeeTarget)` referenced a non-existent
   `employee_targets.academic_year_id` column and would throw at runtime. Caught
   by the new model tests.
5. **`isOverdue()` includes the `completed_at IS NULL` guard** so it stays
   consistent with `scopeOverdue()`.
6. **Renames for spec alignment** (`createdBy`, `approvedBy`, `assignedBy`) were
   verified safe — grep confirmed no external callers of the old names.

---

## 8. Test Coverage

New suite: `backend/tests/Feature/Planning/` (72 tests, 286 assertions).

| File | Focus |
| --- | --- |
| `PlanningTestCase.php` | Shared fixture builder (no new-table factories) |
| `AcademicYearTest.php` | `hasManyThrough` targets, casts, scopes |
| `AnnualPlanTest.php` | belongsTo/hasMany graph, casts, soft delete, mass assignment |
| `DepartmentPlanTest.php` | plan graph, creator/approver, scopes, soft delete |
| `DepartmentTargetTest.php` | target graph, casts, scopes, soft delete |
| `EmployeeTargetTest.php` | employee/plan/target links, `tasks()` |
| `TaskTest.php` | full relation set, self-FK, lifecycle/priority/overdue scopes, helpers, N+1, soft delete |
| `TaskAssignmentTest.php` | relations, new review fields, lifecycle/overdue scopes, soft delete |
| `TaskCollaborationTest.php` | progress/comment/attachment/review models and append-only audit tables |
| `TaskRelationshipTest.php` | end-to-end chain + Department/User/Employee integration |

Full suite after 3.3-C: **134 tests / 612 assertions passing**. Pint passes.

---

## 9. Verification

A tinker verification (run against `acadtrack_db_test`, inside a rolled-back
transaction) exercised the complete chain:
`AcademicYear → AnnualPlan → DepartmentPlan → DepartmentTarget → EmployeeTarget →
Task → TaskAssignment → {TaskProgress, TaskComment, TaskAttachment, TaskReview}`,
plus `User` authorship relations, `Department` children, `Employee` assignments and
the `AcademicYear → EmployeeTarget` through-relation. **All 20 checks passed.**
