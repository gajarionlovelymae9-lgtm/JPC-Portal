import { ApiError, apiRequest, API_BASE_URL, toQueryString } from './api'
import type {
  DepartmentPerformanceDetail,
  DepartmentPerformanceRow,
  EmployeeAchievements,
  EmployeePerformanceDetail,
  EmployeePerformanceRow,
  ExportFormat,
  MonthlyAchievementReport,
  ProgressTrendReport,
  ReportFilters,
  ReportOptions,
  ReportResponse,
  TargetVsActual,
  TaskCompletionReport,
  TopPerformersReport,
} from '../types/report'

const BASE = '/reports'

/**
 * Report-shaped request helper: the shared `apiRequest` types `meta` as
 * pagination metadata, while report endpoints carry `meta.filters` (and an
 * optional nested `pagination`). The envelope is otherwise identical, so the
 * result is narrowed to the report contract without changing transport.
 */
async function reportRequest<T>(path: string): Promise<ReportResponse<T>> {
  return apiRequest<T>(path) as Promise<ReportResponse<T>>
}

const REPORT_EXPORTS = {
  'task-completion': `${BASE}/export/task-completion`,
  'employee-performance': `${BASE}/export/employee-performance`,
  'department-performance': `${BASE}/export/department-performance`,
  achievements: `${BASE}/export/achievements`,
} as const

export type ReportExportType = keyof typeof REPORT_EXPORTS

/**
 * Reports & Analytics API surface (Phase 3.3-J).
 *
 * Manager endpoints are server-side scoped; employees are locked to their own
 * record (`/reports/me/performance` and forced self-import on exports).
 */

/** GET /reports/options — manager filter metadata. */
export function getReportOptions(filters: ReportFilters = {}): Promise<ReportResponse<ReportOptions>> {
  return reportRequest<ReportOptions>(`${BASE}/options${toQueryString({ ...filters })}`)
}

/** GET /reports/me/performance — the caller's own KPI detail. */
export function getMyPerformance(filters: ReportFilters = {}): Promise<ReportResponse<EmployeePerformanceDetail>> {
  return reportRequest<EmployeePerformanceDetail>(
    `${BASE}/me/performance${toQueryString({ ...filters })}`,
  )
}

/** GET /reports/task-completion — organisation/department KPI snapshot + chart. */
export function getTaskCompletionReport(filters: ReportFilters = {}): Promise<ReportResponse<TaskCompletionReport>> {
  return reportRequest<TaskCompletionReport>(`${BASE}/task-completion${toQueryString({ ...filters })}`)
}

/** GET /reports/employees — ranked, sortable, paginated employee KPIs. */
export function getEmployeePerformance(filters: ReportFilters = {}): Promise<ReportResponse<EmployeePerformanceRow[]>> {
  return reportRequest<EmployeePerformanceRow[]>(`${BASE}/employees${toQueryString({ ...filters })}`)
}

/** GET /reports/employees/{employee} — single-employee drill-down. */
export function getEmployeePerformanceDetail(
  employeeId: number | string,
  filters: ReportFilters = {},
): Promise<ReportResponse<EmployeePerformanceDetail>> {
  return reportRequest<EmployeePerformanceDetail>(
    `${BASE}/employees/${employeeId}${toQueryString({ ...filters })}`,
  )
}

/** GET /reports/departments — ranked, sortable, paginated department KPIs. */
export function getDepartmentPerformance(filters: ReportFilters = {}): Promise<ReportResponse<DepartmentPerformanceRow[]>> {
  return reportRequest<DepartmentPerformanceRow[]>(
    `${BASE}/departments${toQueryString({ ...filters })}`,
  )
}

/** GET /reports/departments/{department} — single-department drill-down. */
export function getDepartmentPerformanceDetail(
  departmentId: number | string,
  filters: ReportFilters = {},
): Promise<ReportResponse<DepartmentPerformanceDetail>> {
  return reportRequest<DepartmentPerformanceDetail>(
    `${BASE}/departments/${departmentId}${toQueryString({ ...filters })}`,
  )
}

/** GET /reports/achievements/monthly — ranked monthly leaderboard. */
export function getMonthlyAchievements(filters: ReportFilters = {}): Promise<ReportResponse<MonthlyAchievementReport>> {
  return reportRequest<MonthlyAchievementReport>(
    `${BASE}/achievements/monthly${toQueryString({ ...filters })}`,
  )
}

/** GET /reports/achievements/employees/{employee} — personal achievement series. */
export function getEmployeeAchievements(
  employeeId: number | string,
  filters: ReportFilters = {},
): Promise<ReportResponse<EmployeeAchievements>> {
  return reportRequest<EmployeeAchievements>(
    `${BASE}/achievements/employees/${employeeId}${toQueryString({ ...filters })}`,
  )
}

/** GET /reports/trend/monthly-completions — month-over-month trend feed. */
export function getProgressTrend(filters: ReportFilters = {}): Promise<ReportResponse<ProgressTrendReport>> {
  return reportRequest<ProgressTrendReport>(`${BASE}/trend/monthly-completions${toQueryString({ ...filters })}`)
}

/** GET /reports/top-performers — leaderboard for daily/weekly/monthly scope. */
export function getTopPerformers(filters: ReportFilters = {}): Promise<ReportResponse<TopPerformersReport>> {
  return reportRequest<TopPerformersReport>(`${BASE}/top-performers${toQueryString({ ...filters })}`)
}

/** GET /reports/target-vs-actual — target attainment snapshot. */
export function getTargetVsActual(filters: ReportFilters = {}): Promise<ReportResponse<TargetVsActual>> {
  return reportRequest<TargetVsActual>(`${BASE}/target-vs-actual${toQueryString({ ...filters })}`)
}

/**
 * Stream a report export (CSV / XLSX / PDF) and save it as a file download.
 * The export routes are rate-limited (`reports:export`), so 429 surfaces as an
 * ApiError for the caller to present.
 */
export async function downloadReportExport(
  report: ReportExportType,
  format: ExportFormat,
  filters: ReportFilters = {},
): Promise<void> {
  const query = toQueryString({ ...filters, format })

  const response = await fetch(`${API_BASE_URL}${REPORT_EXPORTS[report]}${query}`, {
    method: 'GET',
    headers: { Accept: 'text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/pdf,application/octet-stream' },
    credentials: 'include',
  })

  if (!response.ok) {
    const payload = await response.json().catch(() => null)
    const message =
      payload && typeof payload.message === 'string'
        ? payload.message
        : `Export failed with status ${response.status}`
    throw new ApiError(message, response.status, payload?.errors)
  }

  let filename = `JPC_Report.${format}`
  const disposition = response.headers.get('Content-Disposition')
  const match = disposition?.match(/filename="?([^";]+)"?/)
  if (match) {
    filename = match[1]
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}