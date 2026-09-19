# JPC Portal — Admin / HR / Department Head Management Dashboard (Phase 3.3-H)

React.js + TypeScript dashboard for **management analytics** (Admin, HR, and
Department Head roles) built on top of the Phase 3.3-E/F/G Planning & Task
Management domain. Follows the same rule as every other screen: the UI is a
**presentation and orchestration layer only** — counting, ranking, scoping and
authorization are all computed by the Laravel API. The frontend never recomputes
overdue status, completion rates, or department scope.

```
React component → service (fetch) → Laravel /api/v1/admin/dashboard → Policy (viewAnalytics) → Service → MySQL
```

- **Stack:** React 19, Vite 8, TypeScript, `react-router-dom` 7
- **HTTP:** native `fetch` via the shared `apiRequest` client
- **Base URL:** `VITE_API_BASE_URL` (defaults to `/api/v1`)
- **Out of scope:** AI/NLP, OCR/Google Vision, badge/achievement awarding, report
  generation — all still marked as future modules.

---

## 1. Scope & roles

| Role | Nav entry | Route | Sees |
| ---- | --------- | ----- | ---- |
| `admin` | Management Dashboard | `/admin/dashboard` | everything, filterable by department |
| `hr` | Management Dashboard | `/hr/dashboard` | everything, filterable by department |
| `department_head` | Management Dashboard | `/department-head/dashboard` | **their own department only** (server-enforced) |

Permissions come from the user's role assignment (login profile). The three
routes use `ProtectedRoute role="admin" | "hr" | "department_head"` (exact match)
plus an `analytics.view`-style NavItem `roles` guard in the sidebar, so employees
never see the section (see §5).

### Department-head isolation is server-side
`ManagementDashboardService::resolveDepartmentScope()` derives the scope from the
authenticated user's `Employee` profile (department head → their department). A
head **cannot** request another department: passing `department_id` of any other
department returns `403`, and `/options` returns only their own department in the
pickers. The frontend simply respects whatever the API returns.

---

## 2. API endpoints

All under `GET /api/v1/admin/dashboard`, routed in `routes/api.php`. Group is
`auth:sanctum` + `role:admin,hr,department_head` middleware; every method also
calls `authorize('viewAnalytics', Task::class)` (see §7). `from_date`/`to_date`
scope task counts by `due_date` (and `completed_at` for target-vs-actual).

| Endpoint | Purpose |
| -------- | ------- |
| `GET /options` | Department + academic-year pickers (dept-head limited) |
| `GET /summary` | Global KPI card data |
| `GET /employees` | Paginated employee progress table (`search`, `page`, `per_page`) |
| `GET /employees/{employee}/progress` | Single employee drill-down detail |
| `GET /departments` | Department progress rows |
| `GET /tasks` | Management task list (API only; the dashboard page omits it — see §10) |
| `GET /overdue` | Overdue task watchlist |
| `GET /review-queue` | Submitted / under-review assignments needing a decision |
| `GET /monthly-progress` | One row per month (`year`, optional `month`) |
| `GET /monthly-achievement` | Ranked employees by completed tasks |
| `GET /target-vs-actual` | Target attainment snapshot |

### Shared scope filters (sent as query params)
`department_id`, `academic_year_id`, `year`, `month`, `from_date`, `to_date`.
Static calendars: `Friendly month labels` (`2026 · February`) and calendar month
labels are derived in `src/utils/dashboard.ts` with the same math the backend
uses for grouping (1-based month from `tasks.due_date`), so KPI tiles and tables
agree on totals.

### Response envelopes
Standard `ApiResponse<T>` (`{ success, message, data, meta? }`); table endpoints
return Laravel `meta` (pagination). Errors are `ApiError` (message + status +
optional validation `errors`); network failures map to status `0` via
`src/utils/apiError.ts` — the same client used across the whole app.

---

## 3. Backend service & resources

`app/Services/Dashboard/ManagementDashboardService.php` (+ 4 resources under
`app/Http/Resources/Dashboard/`) expose read-only aggregations:

- **summary** — total employees/tasks, completed/pending/overdue, submitted,
  under-review, rejected, cancelled, on-time/late, completion rate, on-time rate.
- **employees** — per employee: assigned/completed/pending/overdue + completion
  rate; paginated and searchable by name/work email.
- **employeeProgress** — single employee totals + their assignment list.
- **departments** — per department: employee count, assigned, completed,
  completion rate.
- **overdue** — tasks where `is_overdue` (server flag, never recomputed in UI).
- **reviewQueue** — assignments with status `submitted` or `under_review`, joined
  to the latest `TaskReview` for the decision banner.
- **monthlyProgress** — grouped by `due_date` month: totals per status + on-time
  counts.
- **monthlyAchievement** — ranked by completed tasks, with rank + completion rate.
- **targetVsActual** — `target` = `employee_targets` rows in scope, `achieved` =
  targets having ≥ 1 completed task, `remaining`, `achievement_rate`, `period`
  (academic year name, else `Monthly` / `All time`).
- **options** — departments + academic years for filter pickers; department heads
  receive only their own department.

All queries eager-load and use aggregate sub-selects so the dashboard stays
`N+1`-free (mirroring the existing `EmployeeModelTest::common_dashboard_queries`
guard).

---

## 4. Frontend architecture

| Layer | Location | Responsibility |
| ----- | -------- | -------------- |
| Types | `src/types/dashboard.ts` | Mirror of the dashboard resources |
| Helpers | `src/utils/dashboard.ts` | Month labels, KPI card spec |
| Services | `src/services/managementDashboardService.ts` | One function per endpoint |
| Hooks | `src/hooks/useManagementDashboard.ts` | `useDashboardResource`, `useDashboardTable` |
| Components | `src/components/dashboard/*` | 13 presentational components |
| Page | `src/pages/dashboard/ManagementDashboardPage.tsx` | Shared page keyed by role |

### `useDashboardResource`
Generic fetch hook returning `{ data, meta, loading, error, refresh, updatedAt }`.
Requests are guarded by an `active` flag so unmounted components never `setState`,
and `updatedAt` is captured inside the async `.then` (no `setState` in an effect),
keeping oxlint's `react-hooks` rules clean.

### `useDashboardTable`
Paginated table state (page + search). Scope changes reset both by **remounting**
the table via `key={filterKey}` at the parent level rather than an effect, so
search/page never bleed across departments.

### Components (`src/components/dashboard/`)
| Component | Notes |
| --------- | ----- |
| `DashboardFilters` | Department + academic-year + month + date-range + Reset; options loaded from `/options` |
| `KpiGrid` | Label/value/tone stat tiles from `/summary` |
| `TargetVsActualCard` | Target / achieved / remaining / rate + period |
| `MonthlyProgressCard` | Per-month status counts + `TaskBreakdownChart` |
| `MonthlyAchievementPanel` | Ranked leaderboard (gold/silver/bronze rails) |
| `EmployeeProgressTable` | Search + pagination + `TaskProgressBar`; row click → detail panel |
| `EmployeeDetailPanel` | Drill-down employee totals + assignment list |
| `DepartmentProgressTable` | Department rows with completion bars |
| `OverdueTaskTable` | Overdue watchlist referencing the API's `is_overdue` flag |
| `ReviewQueueTable` | Pending submissions with latest review result banner |
| `TablePagination` / `TableSearchBar` | Reusable controls |

Reuses the shared `.task-badge--*`, `.task-table`, and `.task-progress` CSS
classes plus dashboard-specific helpers appended to `src/index.css`
(`.dashboard-toolbar`, `.dashboard-grid`, `.dashboard-panel__head`,
`.achievement-rank--1/2/3`, `.task-table__select`, …).

---

## 5. Wiring changes

- `src/routes/paths.ts` — added `adminDashboard`, `hrDashboard`,
  `departmentHeadDashboard` + `managementDashboardPath(role)`.
- `src/constants/navigation.ts` — `NavigationItem.roles?: string[]`; three
  "Management Dashboard" entries live in a new **Leadership** sidebar section.
- `src/components/Sidebar.tsx` — filters items by permission **and** optional
  `roles` array before rendering.
- `src/App.tsx` — three lazy routes wrapped in `ProtectedRoute` with the exact
  role; the shell `AppLayout` still requires authentication, so unauthenticated
  visits redirect to `/login`.
- `src/components/Header.tsx` — title auto-resolves from NAVIGATION_ITEMS (no
  per-route title map for the new routes).

---

## 6. Drill-down behavior

Clicking a row in `EmployeeProgressTable` opens `EmployeeDetailPanel`, which
fetches `/employees/{id}/progress` on demand with the same scope filters. The
panel shows totals (assigned/completed/pending/overdue + completion rate) and the
employee's assignment list with per-assignment status and progress.

---

## 7. Backend change

`TaskPolicy::viewAnalytics` now admits `hr` in addition to `admin` and
`department_head`, so every management dashboard endpoint is reachable by HR
without weakening other task permissions. No schema change was required for 3.3-H
(read-only aggregations over existing tables).

---

## 8. Error & loading states

Every panel handles `loading` (text/skeleton placeholder), `empty`
("No employees match the current filters." / "No completed work yet for the
selected period."), and `error` with a **Retry** button. `getApiErrorMessage`
keeps messaging consistent (403 → "You do not have permission…", 404 → "Task not
found."); field errors are only relevant to plural-form endpoints and are shown
when present.

---

## 9. Responsiveness & accessibility

- Responsive grids (`auto-fill`/`auto-fit`); `.task-table` mobile label cards via
  `data-label`/`td::before` are reused for the dashboard tables.
- `role="progressbar"` with `aria-valuenow/min/max` + visible percent text;
  `role="alert"` for errors; focus styles come from the shared design system.
- No chart/export library is used — bars are plain divs with width percentages, so
  there is no new dependency surface.

---

## 10. Known limitations

- **`GET /admin/dashboard/tasks` exists but the dashboard page omits it** — the
  full task list is already first-class at `/tasks`, so the management dashboard
  deliberately ships KPIs, rankings and watchlists instead. `getManagementTasks`
  is intentionally **not** exposed by
  `managementDashboardService.ts`.
- **No create/assign/review actions from the dashboard** — it is read-only by
  design; workflow actions stay in `TaskDetailsPage`.
- **Toasts:** the project has no toast system; inline banners and panel states are
  used everywhere.
- **Dept-head isolation is enforced per request**; the UI does not attempt to
  mirror the policy (it just passes the filters the user picks and labels the
  department picker with what `/options` returns).

---

## 11. Verification

Backend (`php artisan test`, full suite):
- **220 tests passed / 1089 assertions**, including
  `Tests\Feature\Api\Dashboard\ManagementDashboardTest` (20 tests): role gating,
  HR access, department-head scoping + 403s, options restrictions, summary math,
  pagination/search, overdue, review queue, monthly filters, ranking, target vs
  actual, employee detail.

Frontend:
- `npm run lint` — **0 warnings, 0 errors** (oxlint, 91 files).
- `npm run build` — `tsc -b && vite build` succeeds (344 kB JS).
- `npm run test` — **16 files / 79 tests pass**, including new:
  - `services/managementDashboardService.test.ts` — endpoint targets + query
    strings (scope filters, pagination, employee detail path).
  - `utils/dashboard.test.ts` — friendly/calendar month labels, summary KPI spec.
  - `components/dashboard/panels.test.tsx` — KpiGrid, TargetVsActualCard,
    MonthlyProgressCard, MonthlyAchievementPanel (loading/empty/error/ranked).
  - `components/dashboard/EmployeeProgressTable.test.tsx` — loading, rows,
    progressbar value, empty, pagination (service mocked).
  - `App.test.tsx` — admin reaches the dashboard at `/admin/dashboard`;
    employees get the Access-denied page.