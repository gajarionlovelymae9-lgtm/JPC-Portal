# JPC Portal — Reports & Analytics API (Phase 3.3-J)

Base path: `/api/v1` · Authentication: **Laravel Sanctum (SPA / cookie-based)**.

Every report endpoint re-resolves the caller's department scope **server-side** and
wraps computed data in the reports envelope:

```json
{
  "success": true,
  "message": "…",
  "data": { },
  "meta": {
    "report_type": "task-completion",
    "generated_at": "2026-09-19T09:20:00+00:00",
    "filters": { }
  }
}
```

Table endpoints additionally return `meta.pagination`:

```json
"pagination": {
  "current_page": 1,
  "last_page": 3,
  "per_page": 20,
  "total": 42
}
```

> `meta.filters` echoes the sanitized filter set (includes `limit` and `scope`
> on every report type). `page`, `per_page` and `format` are intentionally omitted.

---

## Permissions & scope model

| Role | `reports.view` | `analytics.view` |
| --- | --- | --- |
| `admin` | ✓ | ✓ |
| `hr` | ✓ | ✓ |
| `department_head` | ✓ | ✓ |
| `employee` | ✓ (own record only) | ✗ |

Access rules applied by the middleware plus the controller:

- **`/reports/me/performance`, `/reports/achievements/employees/{employee}`,
  `/reports/export/achievements`** → `reports.view`.
- **Every other report and export route** → `analytics.view` (admin / hr / department_head).
- **`department_head`** is locked to their own department —
  requesting a different `department_id` (or having no linked department)
  yields `403 Forbidden`.
- **`employee`** can only reach their own record;
  browsing any other report endpoint yields `403 Forbidden`.
  On `/reports/export/achievements` an employee is forced to their own record regardless
  of any `employee_id` parameter.

---

## Rate limits

Global `throttle:api` (`60/min`) applies to **all** `/api/*` routes; the named
limiters below stack on top. **Effective ceilings:**

| Surface | Named limiter | Effective |
| --- | --- | --- |
| Report browse endpoints | `reports` — 120/min | 60/min |
| Report exports | `reports-export` — 10/min | **10/min** |
| `me/performance` & `achievements/employees/{employee}` | none | 60/min |

Exceeding a limit returns `429 Too Many Requests`
`{ "success": false, "message": "Too many requests" }`.

---

## Shared query parameters

All GET report endpoints accept the following query parameters. Validation lives in
`App\Http\Requests\Reports\ReportRequest` (`ReportExportRequest` adds `format`).

| Parameter | Type | Default | Rules |
| --- | --- | --- | --- |
| `department_id` | int | — | nullable, exists:departments,id |
| `employee_id` | int | — | nullable, exists:employees,id |
| `academic_year_id` | int | — | nullable, exists:academic_years,id |
| `from_date` | date (Y-m-d) | — | nullable, valid date |
| `to_date` | date (Y-m-d) | — | nullable, valid date; must be ≥ `from_date` |
| `month` | int | — | nullable, 1–12 |
| `year` | int | — | nullable, 2000–2100 |
| `status` | string | — | nullable, `not_started, draft, assigned, accepted, in_progress, submitted, under_review, completed, rejected, cancelled` |
| `priority` | string | — | nullable, `low, medium, high, urgent` |
| `search` | string | — | nullable, max:100 |
| `sort` | string | `employee_name` | nullable, `employee_name, department_name, completion_rate, on_time_rate, completed_tasks, overdue_tasks, due_date` |
| `direction` | string | `asc` | nullable, `asc, desc` |
| `page` | int | `1` | nullable, min:1 |
| `per_page` | int | `20` | nullable, 1–100 |
| `limit` | int | `5` | nullable, 1–10 (top-performers only) |
| `scope` | string | `monthly` | nullable, `daily, weekly, monthly` |
| `format` | string | `csv` | nullable, `csv, xlsx, pdf` (export endpoints only) |

> **Date semantics.** With `month`/`year` or a date range, only the
> `completed_at`-derived metrics are period-constrained (`completed_tasks`,
> `on_time`, `late`, `completion_rate`, `on_time_rate`). `pending_tasks`,
> `overdue_tasks`, `submitted_tasks`, `rejected_tasks` and `cancelled_tasks`
> are always whole-history counts. `total_tasks` is period-constrained by the
> due-date filter; with only `month` set it reflects the full scope.

> **Known limitation.** `sort=due_date` is whitelisted but the row fields are
> named `next_due_date`, so this sort currently degrades to the
> name-ascending tie-break. `next_due_date` itself is not sortable.

---

## Endpoint reference

### GET /reports/options

- **Auth:** `analytics.view` · **Envelope:** report
- **Purpose:** filter metadata for the manager workbench.
- **Filters honoured:** `department_id` (re-scoped for dept heads).

Response `data`:

```json
{
  "departments": [{ "id": 1, "name": "Science" }],
  "academic_years": [{ "id": 2, "name": "2025/2026", "status": "active" }],
  "statuses": ["not_started", "draft", "assigned", "accepted", "in_progress", "submitted", "under_review", "completed", "rejected", "cancelled"],
  "priorities": ["low", "medium", "high", "urgent"],
  "sortable": ["employee_name", "department_name", "completion_rate", "on_time_rate", "completed_tasks", "overdue_tasks", "due_date"],
  "scopes": ["daily", "weekly", "monthly"],
  "max_per_page": 100,
  "trend_months": 6,
  "top_performers_limit_max": 10
}
```

### GET /reports/me/performance

- **Auth:** `reports.view` · **Envelope:** report
- **Purpose:** the caller's own KPI detail.
- **Errors:** `404` `No linked employee profile for this account.` when the user
  has no linked employee record.

Response `data` — same shape as the employee **detail** below.

### GET /reports/task-completion

- **Auth:** `analytics.view` · **Envelope:** report
- **Purpose:** organisation/department KPI snapshot + 6-month chart.

```json
{
  "period": "2026-09",
  "total_tasks": 13,
  "completed_tasks": 10,
  "pending_tasks": 3,
  "overdue_tasks": 1,
  "submitted_tasks": 0,
  "rejected_tasks": 0,
  "cancelled_tasks": 0,
  "on_time": 8,
  "late": 2,
  "completion_rate": 76.92,
  "on_time_rate": 80.0,
  "period_active": true,
  "chart": {
    "labels": ["2026-04", "2026-05", "2026-06", "2026-07", "2026-08", "2026-09"],
    "completed": [4, 6, 8, 7, 9, 10],
    "pending": [1, 0, 2, 1, 1, 0],
    "completion_rates": [66.67, 85.71, 80.0, 87.5, 90.0, 100.0],
    "month_over_month_pp": 10.0
  }
}
```

### GET /reports/employees

- **Auth:** `analytics.view` · **Envelope:** table (paginated)
- **Purpose:** ranked, sortable, searchable employee KPI table.
- **Sort:** supports `sort` + `direction` (rank is a real full-scope position).

Row shape:

```json
{
  "rank": 1,
  "employee_id": 3,
  "employee_name": "Jane Doe",
  "department_id": 1,
  "department_name": "Science",
  "total_tasks": 13,
  "completed_tasks": 10,
  "pending_tasks": 3,
  "overdue_tasks": 1,
  "on_time": 8,
  "late": 2,
  "completion_rate": 76.92,
  "on_time_rate": 80.0,
  "next_due_date": "2026-09-30",
  "recent_assignments": null
}
```

### GET /reports/employees/{employee}

- **Auth:** `analytics.view` + `assertEmployeeScope` · **Envelope:** report
- **Purpose:** single-employee drill-down.

`data` extends the employee row with:

```json
"recent_assignments": [
  {
    "assignment_id": 41,
    "task_id": 7,
    "task_code": "T-2026-007",
    "task_title": "Q3 Curriculum Review",
    "department_name": "Science",
    "status": "in_progress",
    "priority": "high",
    "due_date": "2026-09-30",
    "completed_at": null,
    "progress_percentage": 60
  }
]
```

### GET /reports/departments

- **Auth:** `analytics.view` · **Envelope:** table (paginated)
- **Purpose:** ranked, sortable department KPI table.

Row shape:

```json
{
  "rank": 1,
  "department_id": 1,
  "department_name": "Science",
  "department_code": "SCI",
  "employee_count": 4,
  "total_tasks": 13,
  "completed_tasks": 10,
  "pending_tasks": 3,
  "overdue_tasks": 1,
  "on_time": 8,
  "late": 2,
  "completion_rate": 76.92,
  "on_time_rate": 80.0,
  "next_due_date": "2026-09-30",
  "recent_tasks": null
}
```

### GET /reports/departments/{department}

- **Auth:** `analytics.view` + department scope · **Envelope:** report
- **Purpose:** single-department drill-down.

`data` extends the department row with:

```json
"recent_tasks": [
  {
    "task_id": 7,
    "task_code": "T-2026-007",
    "title": "Q3 Curriculum Review",
    "status": "in_progress",
    "priority": "high",
    "due_date": "2026-09-30",
    "completed_at": null,
    "assignee_names": ["Jane Doe"]
  }
]
```

### GET /reports/achievements/monthly

- **Auth:** `analytics.view` · **Envelope:** report
- **Purpose:** ranked monthly leaderboard (weighted 60/40 completion/on-time).

```json
{
  "period": "2026-09",
  "year": 2026,
  "month": 9,
  "rankings": [
    {
      "rank": 1,
      "is_employee_of_month": true,
      "employee_id": 3,
      "employee_name": "Jane Doe",
      "department_id": 1,
      "department_name": "Science",
      "total_tasks": 13,
      "completed_tasks": 10,
      "completion_rate": 100.0,
      "on_time_rate": 100.0,
      "overall_score": 100.0
    }
  ]
}
```

### GET /reports/achievements/employees/{employee}

- **Auth:** `reports.view` + `assertEmployeeScope` · **Envelope:** report
- **Purpose:** personal achievement series (6 months).

```json
{
  "employee_id": 3,
  "employee_name": "Jane Doe",
  "department_id": 1,
  "department_name": "Science",
  "period": "2026-09",
  "current_rank": 1,
  "is_employee_of_month": true,
  "series": [
    {
      "label": "2026-04",
      "total_tasks": 5,
      "completed_tasks": 3,
      "completion_rate": 60.0,
      "on_time_rate": 66.67,
      "overall_score": 62.67
    }
  ],
  "badges": [{ "id": 1, "code": "EOM", "name": "Employee of the Month", "awarded_at": "2026-09-01T00:00:00+00:00" }]
}
```

### GET /reports/target-vs-actual

- **Auth:** `analytics.view` · **Envelope:** report
- **Purpose:** target attainment snapshot (`academic_year_id` switches the period
  label to the academic-year name).

```json
{
  "period": "2025/2026",
  "year": 2026,
  "month": 9,
  "target": 12,
  "achieved": 10,
  "remaining": 2,
  "achievement_rate": 83.33
}
```

### GET /reports/trend/monthly-completions

- **Auth:** `analytics.view` · **Envelope:** report
- **Purpose:** month-over-month trend feed (6 months).

```json
{
  "period": "2026-09",
  "labels": ["2026-04", "2026-05", "2026-06", "2026-07", "2026-08", "2026-09"],
  "total": [5, 6, 8, 7, 9, 13],
  "completed": [3, 5, 6, 6, 8, 10],
  "pending": [2, 1, 2, 1, 1, 3],
  "overdue": [1, 0, 1, 1, 0, 1],
  "completion_rates": [60.0, 83.33, 75.0, 85.71, 88.89, 76.92],
  "month_over_month_pp": -11.97
}
```

### GET /reports/top-performers

- **Auth:** `analytics.view` · **Envelope:** report
- **Purpose:** leaderboard for `daily` / `weekly` / `monthly` scope
  (`period` value varies by scope — date, range label or Y-m).

```json
{
  "scope": "monthly",
  "period": "2026-09",
  "limit": 5,
  "performers": [
    {
      "rank": 1,
      "employee_id": 3,
      "employee_name": "Jane Doe",
      "department_id": 1,
      "department_name": "Science",
      "completed_tasks": 10,
      "completion_rate": 76.92
    }
  ]
}
```

---

## Exports

All exports stream a file (`Content-Disposition` attachment) and audit a
`report.export` activity-log entry. Filenames follow
`JPC_{Label}_Report_{Y-m-d}.{format}`. CSV output starts with a UTF-8 BOM.

| Endpoint | Auth | Label |
| --- | --- | --- |
| `/reports/export/task-completion?format=` | `analytics.view` | `Task_Completion` (metric/value rows) |
| `/reports/export/employee-performance?format=` | `analytics.view` | `Employee_Performance` |
| `/reports/export/department-performance?format=` | `analytics.view` | `Department_Performance` |
| `/reports/export/achievements?format=` | `reports.view` | `Achievements` |

- `format` ∈ `csv, xlsx, pdf` (default `csv`).
- Exports cap row count at `config('reports.export_rows_max')` (5000).
- `/reports/export/achievements`: an `employee_id` parameter exports that
  employee's personal series; **employees** are always forced to their own record
  (`403` if they have no linked employee). Without `employee_id`, the ranked
  leaderboard is exported in scope.

---

## Validation rules (summary)

Applies to all report endpoints (see the full table under *Shared query
parameters* above). The `to_date` rule additionally fails when it precedes
`from_date`:

```json
{
  "message": "Validation failed",
  "errors": {
    "to_date": ["The \"to\" date must be on or after the \"from\" date."]
  }
}
```

---

## Error responses

| Status | Body |
| --- | --- |
| 401 | `{ "success": false, "message": "Unauthenticated" }` |
| 403 | `{ "success": false, "message": "Forbidden" }` (permission, department scope, employee scope) |
| 404 | `{ "success": false, "message": "Resource not found" }` (route/model binding) |
| 404 | `{ "success": false, "message": "No linked employee profile for this account." }` (`/me/performance`) |
| 422 | `{ "success": false, "message": "Validation failed", "errors": { field: [messages] } }` |
| 429 | `{ "success": false, "message": "Too many requests" }` (export throttle: 10/min) |

---

## Implementation notes

- Services live in `app/Services/Reports/`
  (`TaskCompletionReportService`, `EmployeePerformanceReportService`,
  `DepartmentPerformanceReportService`, `AchievementReportService`,
  `ProgressTrendService`, `TargetVsActualReportService`) and share the
  `ReportFilterBag` value object.
- `AchievementReportService::monthly()` and the target/trend aggregates are
  cached for `config('reports.cache_ttl')` (60 s) under a filter signature.
- Monthly achievements are mirrored into the `monthly_achievements` table so
  the dashboard and history stay consistent. Because `MonthlyAchievement` guards
  the verdict columns (`completion_rate`, `on_time_rate`, `overall_score`, `rank`,
  `is_employee_of_month`, …), the mirror uses `firstOrNew` + `forceFill`.
- Config lives in `config/reports.php`: `default_per_page` (20),
  `max_per_page` (100), `trend_months` (6), `trend_months_max` (12),
  `top_performers_limit` (5), `top_performers_limit_max` (10),
  `export_rows_max` (5000), `cache_ttl` (60).
- The resource classes serialize pre-computed service arrays; detail endpoints
  return the raw service shapes (`recent_assignments`, `recent_tasks`, `series`,
  `badges` are `null` placeholders on the paginated table rows only).
- Frontend contract types live in `frontend/src/types/report.ts`, the service
  layer in `frontend/src/services/reportService.ts`, and re-usable hooks in
  `frontend/src/hooks/useReports.ts`.

## Example requests

```bash
# Manager KPI snapshot, all departments, October 2026
curl -b cookies.txt "http://localhost:8000/api/v1/reports/task-completion?year=2026&month=10"

# Paginated employee table, Science dept, sorted by completion rate
curl -b cookies.txt "http://localhost:8000/api/v1/reports/employees?department_id=1&sort=completion_rate&direction=desc&per_page=20"

# Personal performance for an employee
curl -b cookies.txt "http://localhost:8000/api/v1/reports/me/performance"

# Export the department report as XLSX
curl -b cookies.txt -OJ "http://localhost:8000/api/v1/reports/export/department-performance?format=xlsx"

# Monthly leaderboard, daily scope, top 3
curl -b cookies.txt "http://localhost:8000/api/v1/reports/top-performers?scope=daily&limit=3"
```