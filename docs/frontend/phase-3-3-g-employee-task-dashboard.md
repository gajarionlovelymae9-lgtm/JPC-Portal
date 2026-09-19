# JPC Portal — React.js Task Management UI & Employee Task Dashboard (Phase 3.3-G)

React.js + TypeScript frontend for the Phase 3.3-E/F Planning & Task Management
domain. The UI is a **presentation and orchestration layer only** — every workflow
rule (status transitions, assignment, review, permissions) is enforced by the
Laravel API. The frontend never talks to the database and contains no business
logic or authorization decisions.

```
React component → service (fetch) → Laravel /api/v1 → Form Request → Policy → Service → MySQL
```

- **Stack:** React 19, Vite 8, TypeScript, `react-router-dom` 7
- **HTTP:** native `fetch` via the shared `apiRequest` client (no Axios / react-query)
- **Base URL:** `VITE_API_BASE_URL` (defaults to `/api/v1`); no hard-coded hosts
- **Out of scope:** AI/NLP, OCR/Google Vision, badge/achievement awarding

---

## 1. Architecture

| Layer | Location | Responsibility |
| ----- | -------- | -------------- |
| Types | `src/types/task.ts`, `src/types/api.ts` | Mirror the 3.3-F resources exactly |
| Helpers | `src/utils/task.ts`, `src/utils/apiError.ts` | Labels/dates/clamping; error normalization |
| Services | `src/services/task*.ts` | One function per API endpoint; typed responses |
| Hooks | `src/hooks/useTasks.ts` | Fetch/loading/error state |
| Components | `src/components/tasks/*`, `src/components/common/*` | Reusable UI |
| Pages | `src/pages/TasksPage.tsx`, `src/pages/tasks/*` | Route-level screens |

The existing architecture (`src/services`, `src/hooks`, `src/components`,
`src/pages`, `src/routes`, `src/types`, `src/constants`, `src/auth`) was inspected
first and reused — nothing was duplicated and no parallel API client was added.

---

## 2. API services

All calls return the parsed envelope `ApiResponse<T>` (`{ success, message,
data, meta? }`). Non-2xx responses throw `ApiError` (message + status +
validation `errors`); network failures throw `ApiError` with status `0`.

### `taskService.ts`

| Function | Endpoint |
| -------- | -------- |
| `listTasks(params)` | `GET /tasks` |
| `getTask(id)` | `GET /tasks/{task}` |
| `createTask(payload)` | `POST /tasks` |
| `updateTask(id, payload)` | `PUT /tasks/{task}` |
| `deleteTask(id)` | `DELETE /tasks/{task}` |
| `cancelTask(id)` | `POST /tasks/{task}/cancel` |
| `getTaskSummary()` | `GET /tasks/summary` |
| `getMonthlyProgress(year, month)` | `GET /tasks/monthly-progress` |
| `getMyTasks(params)` | `GET /tasks/me` |
| `getMyTaskSummary()` | `GET /tasks/me/summary` |
| `getEmployeeTasks(employeeId, params)` | `GET /tasks/employees/{employee}/tasks` |

### `taskAssignmentService.ts`

`getAssignment`, `acceptAssignment`, `startAssignment`, `submitAssignment`,
`resubmitAssignment`, `reviewAssignment`, `updateAssignmentProgress`,
`getAssignmentProgress`, `getAssignmentReviews`, `assignEmployee`,
`listTaskAssignments` — see §7 of
`docs/api/phase-3-3-f-task-management-api.md` for the matching routes.

### `taskDashboardService.ts`

`getEmployeeProgress`, `getDepartmentProgress`, `getMonthlyAchievement`, plus
re-exports of `getMyTaskSummary` and `getMonthlyProgress` so the dashboard API can
be imported from one module.

---

## 3. Hooks & state management

| Hook | Returns | Purpose |
| ---- | ------- | ------- |
| `useTaskList(params)` | `{ tasks, meta, loading, error, refresh }` | Management list |
| `useMyTasks(params)` | `{ tasks, meta, summary, loading, error, refresh }` | Employee list + `/tasks/me/summary` |
| `useTask(id)` | `{ task, setTask, loading, error, refresh }` | Single task with assignments |

Requests run inside an async IIFE guarded by an `active` flag so unmounted
components never `setState`; `setState` is deferred past `await Promise.resolve()`
to satisfy oxlint's `set-state-in-effect` rule under React 19 StrictMode.

**Cache refresh (no optimistic updates):** every workflow mutation waits for the
API response, then updates UI state from the response and re-fetches the affected
history. After a progress update the page refetches both the assignment and its
progress history; after submit/resubmit/review the assignment + reviews are
refetched; cancel triggers `refresh()` on the task. Critical workflow operations
are never applied before the server confirms them.

---

## 4. Components

`src/components/tasks/`

| Component | Notes |
| --------- | ----- |
| `TaskStatusBadge` | Status label + `task-badge--{slug}` (centralized mapping) |
| `TaskPriorityBadge` | Priority label + `task-badge--priority` / `--{priority}` |
| `TaskProgressBar` | `role="progressbar"`, `aria-valuenow`, clamped 0–100, text %, `size` |
| `TaskCard` | Title, badges, assignment progress, due date + deadline |
| `TaskFilters` | Status, priority, due-from, due-to, search, sort, direction, reset |
| `TaskTable` | Task/Status/Priority/Due/Progress(/Actions); loading/empty/error states; pagination |
| `TaskTimeline` | Merged lifecycle/progress/review feed with tone dots |
| `TaskProgressHistory` | Date / Previous % / New % / Comment / Updated by table |
| `TaskSummaryCards` | Stat tiles (label/value/tone) |
| `TaskBreakdownChart` | Dependency-free horizontal bar chart |
| `TaskMonthlyProgress` | Monthly completion bars |

`src/components/common/`

| Component | Notes |
| --------- | ----- |
| `ConfirmDialog` | Accessible modal (`role="dialog"`, `aria-modal`, Escape/backdrop, focus) |

Status/priority colours are centralized as CSS classes in `src/index.css` under
the "Task management (Phase 3.3-G)" section — components never inline arbitrary
colours.

---

## 5. Pages & routes

| Route | Page | Permission |
| ----- | ---- | ---------- |
| `/tasks` | `TasksPage` | `tasks.view` |
| `/tasks/:taskId` | `TaskDetailsPage` | `tasks.view` |
| `/employee/dashboard` | `EmployeeDashboardPage` | `tasks.view` |
| `/employee/tasks` | `MyTasksPage` | `tasks.view` |
| `/employee/tasks/:taskId` | `TaskDetailsPage` | `tasks.view` |
| `/employee/progress` | `TaskProgressPage` | `tasks.view` |

All new routes are wrapped in the existing `ProtectedRoute`; the surrounding
`AppLayout` still requires authentication, so an unauthenticated visit to
`/employee/*` redirects to `/login`.

- **TasksPage** — management table: filters, due-date range, sorting, backend
  pagination (`Showing X–Y of Z`), and View/Continue row actions.
- **MyTasksPage** — summary tiles + card grid + filters/pagination.
- **EmployeeDashboardPage** — KPI tiles (Total / Completed / Pending / Overdue /
  Completion rate / On-time rate), a task-breakdown chart, plus "Needs attention"
  (overdue) and "Upcoming" sections.
- **TaskProgressPage** — completion-rate tiles, monthly progress bars, and
  "Open work" / "Completed & cancelled" groups.
- **TaskDetailsPage** — see §6.

Employee pages guard on `user.employee_id`; accounts without a linked employee
profile (e.g. pure admins) get an explanatory empty state.

---

## 6. Task workflow UI (`TaskDetailsPage`)

### Information shown
Task code, title, description, priority, status, start date, due date
(+ deadline countdown), estimated hours, planning references (annual plan,
department plan, targets), each assignment (employee, assigned date, status,
progress, due), overall progress, latest review result, progress history and the
activity timeline.

### Status vs assignment status (§31)
The page renders both `Task status` and `Your assignment` badges side by side, so
a task can be "In Progress" while the selected employee's assignment is
"Completed" without misreporting either.

### Owner actions (state-gated)
`Accept` (assigned), `Start work` (assigned/accepted), `Submit for review`
(accepted/in_progress), `Resubmit` (rejected), plus an "Update progress" form
(percentage slider + number input + comment). Completed/cancelled assignments hide
all editing controls.

### Manager actions
`Review submission` (approved / rejected / needs_revision + required remarks)
when the assignment is submitted/under_review, and `Cancel task` for
draft/assigned tasks.

### Confirmations (§46)
`Submit`, `Resubmit` and `Cancel` go through `ConfirmDialog`
("Are you sure you want to submit this task for review?") — never `window.confirm`.

### Submission remarks (§27)
An optional remarks textarea is shown for submittable/resubmittable assignments
and sent as `remarks` on submit.

### Rejection / review result (§28, §73)
The latest review is surfaced as a banner: `✓ Approved` or
`✕ Rejected` / `✕ Revision Required`, including the reviewer's remarks
("Reason for Revision").

### Timeline (§22, §74)
Built from the selected assignment: `Assigned`, `Accepted`, every progress update,
every review decision (`Approved`/`Rejected`/`Needs Revision`), and `Completed`.
An explicit "Started" node is not shown because the API exposes no `started_at`.

### Deadline & overdue (§20, §21)
`deadlineLabel()` renders `Due today`, `Due tomorrow`, `Due in N days`,
`Overdue by N days`. The `OVERDUE` warning is driven solely by the API's
`is_overdue` flag — the frontend never recomputes overdue status.

---

## 7. Error handling

`src/utils/apiError.ts` centralizes all error presentation:

- `getApiErrorMessage(error, fallback)` maps statuses to friendly text —
  `0` network, `401` session, `403` "You do not have permission to perform this
  action.", `404` "Task not found.", `422` keeps the backend validation message,
  `500` server error — and otherwise falls back to the backend message/fallback.
- `getApiFieldErrors(error)` extracts Laravel's `errors` bag for field-level
  messages, shown inline under the progress percentage and review/submission
  remarks fields.

Every data page handles **loading** (text or skeleton), **empty** ("No tasks
assigned yet."), **error** with a **Retry** button, and **not-found** states.
`ConfirmDialog` prevents duplicate submissions by disabling its buttons while a
request is in flight.

---

## 8. Responsiveness & accessibility

- Responsive grids (`auto-fill`/`auto-fit`) and a mobile breakpoint that converts
  `.task-table` rows into labelled cards (`data-label` via `td::before`).
- Semantic HTML: `<table>`/`<th scope>`, `<form>`, `<label>`, `<time>`, `<ol>`,
  `<ul>`, `<article>`, `<section>`.
- `role="progressbar"` with `aria-valuenow/min/max` plus visible text percentage
  (never colour-only); `role="alert"` for errors; `role="status"` for review
  results; `role="dialog"` + `aria-modal` for confirmations; visible focus styles
  from the shared design system.

---

## 9. Backend change

`App\Http\Resources\UserProfileResource` now includes
`employee_id => $user->employee?->id` (nullable) so the UI can identify the
authenticated user's own assignment. The field is additive; the frontend type
`UserProfile.employee_id` is optional.

---

## 10. Known limitations

- **No create/assign UI.** There is no reference-data endpoint to populate
  employee/department pickers, so the create/assign service functions are ready
  but not exposed. Documented intentionally.
- **"Assigned By" column** is omitted — `TaskAssignmentResource` does not expose
  the assigner; only `employee_id` + embedded `employee` are returned.
- **On-time submission rate** is not an API field; the dashboard derives it from
  the employee's own completed assignments (`completed_at <= due_date`) and shows
  an explanatory note until the first completed task.
- **Monthly progress** on `/employee/progress` is derived by grouping the
  employee's own tasks by due-date month; `/tasks/monthly-progress` is
  manager/admin-only (`TaskPolicy@viewAnalytics`).
- **Monthly achievement / rank** (`/employees/monthly-achievement`) is also
  manager/admin-only, so no rank is shown to employees — no badge results are
  invented.
- **Toasts:** the project has no toast system, so workflow results use inline
  `task-feedback` banners ("Task accepted.", "Progress updated.", "Assignment
  submitted for review.", "Assignment resubmitted.").
- **CSRF/cookie origin.** `.env` sets an absolute `VITE_API_BASE_URL`; the Vite
  dev proxy maps `/api` and `/sanctum` to `127.0.0.1:8000`. For a same-origin
  cookie flow in production prefer `/api/v1`.

---

## 11. Verification

- `npm run lint` — **0 warnings, 0 errors** (oxlint, 70 files).
- `npm run build` — `tsc -b && vite build` succeeds.
- `npm run test` — **12 files / 56 tests pass**:
  - `services/taskServices.test.ts` — query-string building, empty-param omission,
    summary/my-tasks endpoints, assignment action paths.
  - `utils/apiError.test.ts` — status mapping (403/404/422/0) + error bags.
  - `utils/task.test.ts` — deadline labels, clamping, label mapping.
  - `components/common/ConfirmDialog.test.tsx` — dialog semantics, confirm,
    Escape/backdrop cancel.
  - `components/tasks/TaskIndicators.test.tsx`, `TaskTable.test.tsx`,
    `TaskTimeline.test.tsx` — component behaviour.
  - Existing `App`, `ProtectedRoute`, `LoginPage`, `SystemStatus`, `health` tests.
- Backend suite remains unaffected (`php artisan test`): the only failure is a
  pre-existing flaky Faker `positions_name_unique` collision that passes on
  isolated re-run.
