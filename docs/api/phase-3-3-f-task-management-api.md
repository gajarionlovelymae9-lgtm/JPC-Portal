# JPC Portal — Planning & Task Management API (Phase 3.3-F)

REST API that exposes the Phase 3.3-E Planning & Task Management business logic
to the React.js frontend. Controllers only receive, validate, authorize, delegate
to services, and serialize — no workflow logic lives in the HTTP layer.

```
React.js → Laravel API → Form Request → Policy → Service → Eloquent → MySQL
```

- **Base path:** `/api/v1`
- **Auth:** Laravel Sanctum (`auth:sanctum`)
- **Versioning:** all endpoints are mounted under `/api/v1`
- **Out of scope:** AI/NLP, OCR/Google Vision, React UI (handled in later phases)

---

## 1. Response envelope

All responses use a consistent envelope (`App\Support\ApiResponse`).

### Single resource — `200` / `201`

```json
{
  "success": true,
  "message": "Task retrieved successfully",
  "data": { "id": 1, "task_code": "TASK-2026-0001", "title": "Monthly Report" }
}
```

### Collection — `200`

`meta` is a **sibling** of `data` and is present only for paginated collections.

```json
{
  "success": true,
  "message": "Tasks retrieved successfully",
  "data": [ { "id": 1 } ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

### Validation error — `422`

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "title": ["The title field is required."] }
}
```

### Error — `401` / `403` / `404` / `409` / `500`

```json
{ "success": false, "message": "This action is unauthorized." }
```

### No content — `204`

Empty body (used by `DELETE`).

### HTTP status codes

| Code | Meaning |
| ---- | ------- |
| `200` | Successful GET / update / action |
| `201` | Resource created |
| `204` | Successful deletion (no body) |
| `400` | Bad request |
| `401` | Unauthenticated |
| `403` | Unauthorized (policy / role) |
| `404` | Resource not found (route model binding) |
| `409` | Workflow/state conflict (domain exception) |
| `422` | Validation failed |
| `500` | Unexpected server error |

---

## 2. Authentication & authorization

- Every endpoint requires `auth:sanctum`; unauthenticated requests return `401`.
- Authorization is enforced server-side through policies registered in
  `AppServiceProvider`:
  - `TaskPolicy` — `view`, `viewAny`, `create`, `update`, `delete`, `assign`, `cancel`, `viewAnalytics`
  - `TaskAssignmentPolicy` — `view`, `viewAny`, `create`, `accept`, `start`, `submit`, `review`, `resubmit`, `progress`
  - `TaskReviewPolicy` — `review`, `approve`, `reject`, `requestRevision`
  - `EmployeePolicy` — `viewTasks` (IDOR guard for employee task lists)
- **IDOR protection:** `TaskAssignmentPolicy::view` allows only the assigned
  employee or a manager (`admin` / `department_head`); employees can never read
  another employee's assignment. `/tasks/employees/{employee}/tasks` is limited
  to managers or the employee themselves.
- **Mass assignment:** controllers only ever pass `$request->validated()` to
  services — never `$request->all()`.
- **Safe filtering/sorting:** filters use bound query-builder values; sort fields
  are checked against a whitelist before use. No raw user-controlled SQL.

### Roles referenced

| Role | Planning/Task capabilities |
| ---- | -------------------------- |
| `admin` | Full task/assignment/review + analytics |
| `department_head` | Manage department tasks, assign, review, analytics |
| `employee` | View assigned tasks, accept/start/progress/submit/resubmit own work |

---

## 3. Task endpoints

### GET `/api/v1/tasks`

Paginated, filterable, sortable task list.

- **Auth:** required. **Policy:** `TaskPolicy@viewAny` (`tasks.view`)
- **Query parameters**

  | Param | Type | Notes |
  | ----- | ---- | ----- |
  | `status` | string | exact status |
  | `priority` | string | exact priority |
  | `department_id` | int | exact |
  | `employee_id` | int | tasks having an assignment to the employee |
  | `annual_plan_id` | int | exact |
  | `department_plan_id` | int | exact |
  | `department_target_id` | int | exact |
  | `employee_target_id` | int | exact |
  | `due_date_from` | date | `due_date >=` |
  | `due_date_to` | date | `due_date <=` |
  | `search` | string | matches `task_code`, `title`, `description` |
  | `sort` | string | one of `created_at`, `updated_at`, `due_date`, `priority`, `status`, `title` (default `created_at`) |
  | `direction` | string | `asc` (default) or `desc` |
  | `page` / `per_page` | int | default 15, maximum 100 |

- **200** — paginated `TaskResource` collection with `meta`.

### POST `/api/v1/tasks`

- **Auth:** required. **Policy:** `TaskPolicy@create` (`tasks.create`)
- **Form request:** `StoreTaskRequest` → `TaskService::createTask($data, $user)`

  | Field | Rules |
  | ----- | ----- |
  | `title` | required, string, max 255 |
  | `description` | nullable, string |
  | `priority` | required, string |
  | `department_id` | required, exists `departments,id` |
  | `due_date` | required, date |
  | `start_date` | nullable, date |
  | `estimated_hours` | nullable, numeric, min 0 |
  | `annual_plan_id` | nullable, exists `annual_plans,id` |

- **201** — `TaskResource`. A unique `task_code` is generated by the service.

### GET `/api/v1/tasks/{task}`

- **Auth:** required. **Policy:** `TaskPolicy@view`
- Eager-loads `annualPlan`, `departmentPlan`, `departmentTarget`, `employeeTarget`,
  `assignments`, `progress`, `reviews`.
- **200** — `TaskResource` (includes nested `assignments`).

### PUT / PATCH `/api/v1/tasks/{task}`

- **Auth:** required. **Policy:** `TaskPolicy@update`
- **Form request:** `UpdateTaskRequest` → `TaskService::updateTask($task, $data, $user)`
- Fields may be sent individually (`sometimes`). Illegal lifecycle transitions
  raise a domain exception → `409`.
- **200** — `TaskResource`.

### DELETE `/api/v1/tasks/{task}`

- **Auth:** required. **Policy:** `TaskPolicy@delete`
- Soft delete (model uses `SoftDeletes`); no permanent deletion.
- **204** — no body.

### POST `/api/v1/tasks/{task}/cancel`

- **Auth:** required. **Policy:** `TaskPolicy@update` → `TaskService::cancelTask($task, $user)`
- Only `draft` / `assigned` tasks can be cancelled; otherwise `409`.
- **200** — `TaskResource` with `status = "cancelled"`.

### GET `/api/v1/tasks/{task}/assignments`

- **Auth:** required. **Policy:** `TaskPolicy@viewAny`
- **200** — paginated `TaskAssignmentResource` collection.

### POST `/api/v1/tasks/{task}/assignments`

- **Auth:** required. **Policies:** `TaskPolicy@assign`, `TaskAssignmentPolicy@create`
- **Form request:** `AssignTaskRequest` → `TaskAssignmentService::assignEmployee()`

  | Field | Rules |
  | ----- | ----- |
  | `employee_id` | required, exists `employees,id` |
  | `due_date` | required, date |
  | `remarks` | nullable, string |

- Only active employees; task must be `draft` / `assigned`.
- **201** — `TaskAssignmentResource`.

---

## 4. Assignment endpoints

### GET `/api/v1/task-assignments/{assignment}`

- **Auth:** required. **Policy:** `TaskAssignmentPolicy@view` (owner or manager)
- **200** — `TaskAssignmentResource` with `employee` and `task`.

### POST `/api/v1/task-assignments/{assignment}/accept`

- **Auth:** required. **Policy:** `TaskAssignmentPolicy@accept` (owner)
- `assigned` → `accepted`.
- **200** — `TaskAssignmentResource`.

### POST `/api/v1/task-assignments/{assignment}/start`

- **Auth:** required. **Policy:** `TaskAssignmentPolicy@start` (owner)
- `assigned` / `accepted` → `in_progress`.
- **200** — `TaskAssignmentResource`.

### POST `/api/v1/task-assignments/{assignment}/progress`

- **Auth:** required. **Policy:** `TaskAssignmentPolicy@progress` (owner)
- **Form request:** `UpdateTaskProgressRequest` → `TaskProgressService::updateProgress()`

  | Field | Rules |
  | ----- | ----- |
  | `percentage` | required, numeric, min 0, max 100 |
  | `comment` | nullable, string, max 5000 |

- **200** — `TaskProgressResource`.
- **422** for out-of-range percentages.

### GET `/api/v1/task-assignments/{assignment}/progress`

- **Auth:** required. **Policy:** `TaskAssignmentPolicy@view`
- **200** — `TaskProgressResource` collection (progress history).

### POST `/api/v1/task-assignments/{assignment}/submit`

- **Auth:** required. **Policy:** `TaskAssignmentPolicy@submit` (owner)
- **Form request:** `SubmitTaskRequest` (`remarks` nullable) → `submitAssignment()`
- `in_progress` / `accepted` → `submitted`.
- **200** — `TaskAssignmentResource`.

### POST `/api/v1/task-assignments/{assignment}/review`

- **Auth:** required. **Policy:** `TaskAssignmentPolicy@review` (manager) →
  `TaskReviewService`
- **Form request:** `ReviewTaskRequest`

  | Field | Rules |
  | ----- | ----- |
  | `decision` | required, in `approved,rejected,needs_revision` |
  | `remarks` | required, string, max 5000 |

- `approved` → `approve()`, `rejected` → `reject()`, `needs_revision` → `requestRevision()`.
- Self-approval is blocked by the service.
- **200** — `TaskReviewResource`.

### GET `/api/v1/task-assignments/{assignment}/reviews`

- **Auth:** required. **Policy:** `TaskAssignmentPolicy@view`
- **200** — `TaskReviewResource` collection.

### POST `/api/v1/task-assignments/{assignment}/resubmit`

- **Auth:** required. **Policy:** `TaskAssignmentPolicy@resubmit` (owner)
- `rejected` → `submitted` via `TaskAssignmentService::resubmitAssignment()`.
- **200** — `TaskAssignmentResource`.

---

## 5. "My work" and analytics endpoints

### GET `/api/v1/tasks/me` (alias `GET /api/v1/me/tasks`)

- **Auth:** required. Returns tasks assigned to the authenticated employee.
- Non-employees receive `403 { success: false, message: "No employee profile found." }`.
- **200** — paginated `TaskResource` collection.

### GET `/api/v1/tasks/me/summary` (alias `GET /api/v1/me/tasks/summary`)

- **Auth:** required. Employee-scoped counts.
- **200** — `{ total, completed, in_progress, submitted, under_review, rejected, overdue }`.

### GET `/api/v1/tasks/{task}` — "summary" and "monthly" are static routes

> Declared **before** the `{task}` wildcard so `summary`, `monthly-progress`,
> `me`, and `employees/{employee}/tasks` are never swallowed by model binding.

### GET `/api/v1/tasks/summary`

- **Auth:** required. **Policy:** `TaskPolicy@viewAnalytics` (admin / department_head).
- **200** — `{ total_tasks, completed_tasks, pending_tasks, overdue_tasks,
  submitted_tasks, under_review_tasks, rejected_tasks, cancelled_tasks }`.
- `pending` = open statuses (`draft`, `assigned`, `in_progress`, `submitted`, `under_review`).

### GET `/api/v1/tasks/monthly-progress?year=YYYY&month=M`

- **Auth:** required. **Policy:** `TaskPolicy@viewAnalytics`.
- **200** — `{ total, completed, pending, overdue, on_time, late, submitted, under_review }`.

### GET `/api/v1/tasks/employees/{employee}/tasks`

- **Auth:** required. **Policy:** `EmployeePolicy@viewTasks` (manager or self).
- **200** — paginated `TaskResource` collection. Employees requesting another
  employee's list receive `403` (IDOR protection).

### GET `/api/v1/employees/progress`

- **Auth:** required. **Policy:** `TaskPolicy@viewAnalytics`.
- Aggregated with `withCount` (no N+1). **200** — list of
  `{ employee_id, employee_name, assigned_tasks, completed_tasks, pending_tasks, overdue_tasks, completion_rate }`.

### GET `/api/v1/departments/progress`

- **Auth:** required. **Policy:** `TaskPolicy@viewAnalytics`.
- **200** — list of `{ department_id, department_name, total_tasks, completed_tasks, pending_tasks, overdue_tasks, completion_rate }`.

### GET `/api/v1/employees/monthly-achievement?year=YYYY&month=M`

- **Auth:** required. **Policy:** `TaskPolicy@viewAnalytics`.
- Ranked by `completion_rate DESC`, then `completed_tasks DESC`, then `employee_name ASC`.
- **200** — list of `{ rank, employee_id, employee_name, completed_tasks, completion_rate }`.
  No badge/achievement records are created in this phase.

`completion_rate = total > 0 ? round(completed / total * 100, 2) : 0.0`
(division-by-zero guarded).

---

## 6. Resource shapes

### TaskResource

`id, task_code, title, description, priority, status, start_date, due_date,
completed_at, estimated_hours, is_overdue, annual_plan, department_plan,
department_target, employee_target, assignments`

### TaskAssignmentResource

`id, task_id, employee_id, employee, status, assigned_at, accepted_at, due_date,
completed_at, completion_percentage, remarks, is_overdue`

### TaskProgressResource

`id, task_id, task_assignment_id, employee_id, previous_percentage,
new_percentage, status, comment, created_at`

### TaskReviewResource

`id, task_id, task_assignment_id, reviewed_by, decision, remarks, reviewed_at`

Dates are serialized as ISO-8601 strings.

---

## 7. Error handling

Domain exceptions from the 3.3-E service layer are rendered as consistent JSON:

| Exception | HTTP |
| --------- | ---- |
| `InvalidTaskStatusTransitionException` | `409` |
| `TaskNotAssignableException` | `409` |
| `InvalidProgressException` | `422` |
| `InvalidTaskReviewException` | `409` / `403` |
| `ModelNotFoundException` (route binding) | `404` |
| `AuthorizationException` | `403` |
| `ValidationException` | `422` |

---

## 8. Notes & guarantees

- Services from Phase 3.3-E are reused unchanged (`TaskService`,
  `TaskAssignmentService`, `TaskProgressService`, `TaskReviewService`).
- Form requests from Phase 3.3-E are reused (`StoreTaskRequest`,
  `UpdateTaskRequest`, `AssignTaskRequest`, `UpdateTaskProgressRequest`,
  `SubmitTaskRequest`, `ReviewTaskRequest`).
- Collections use standard Laravel resource collections
  (`ModelResource::collection()`) and the `ApiResponse::collection()` helper;
  controllers never hand-build divergent JSON.
- Eager loading (`with`, `withCount`) is used to avoid N+1 queries.
- API versioning is centralized in `routes/api.php` under `Route::prefix('v1')`.

### Known deviations from the phase brief

- The brief's collection example shows `data` and `meta` as siblings; this is
  implemented exactly via `ApiResponse::collection()`.
- `php artisan test` covers 37 feature tests for this module (auth, CRUD,
  filtering, search, sorting, pagination, assignment, progress, submit,
  resubmit, review, summary, monthly, employee/department progress,
  monthly achievement, `401`, `403`, `404`, `422`, and IDOR).
