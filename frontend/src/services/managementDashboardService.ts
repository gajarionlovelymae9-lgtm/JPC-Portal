import { apiRequest, toQueryString } from './api'
import type { ApiResponse } from '../types/api'
import type {
  DashboardOptions,
  DashboardScopeFilters,
  DashboardSummary,
  DashboardTableParams,
  DepartmentProgressRow,
  EmployeeDetailProgress,
  EmployeeProgressRow,
  MonthlyAchievementRow,
  MonthlyProgress,
  OverdueTask,
  ReviewQueueItem,
  TargetVsActual,
} from '../types/dashboard'

const BASE = '/admin/dashboard'

/**
 * Management dashboard API surface (Admin / HR / Department Head).
 * Every call is authorised server-side by the `role:admin,hr,department_head`
 * middleware plus the TaskPolicy `viewAnalytics` gate.
 */

/** GET /admin/dashboard/options — departments & academic years for filters. */
export function getDashboardOptions(
  filters: DashboardScopeFilters = {},
): Promise<ApiResponse<DashboardOptions>> {
  return apiRequest<DashboardOptions>(`${BASE}/options${toQueryString({ ...filters })}`)
}

/** GET /admin/dashboard/summary — organisation KPI cards. */
export function getDashboardSummary(
  filters: DashboardScopeFilters = {},
): Promise<ApiResponse<DashboardSummary>> {
  return apiRequest<DashboardSummary>(`${BASE}/summary${toQueryString({ ...filters })}`)
}

/** GET /admin/dashboard/employees — paginated per-employee progress. */
export function getDashboardEmployees(
  params: DashboardTableParams = {},
): Promise<ApiResponse<EmployeeProgressRow[]>> {
  return apiRequest<EmployeeProgressRow[]>(`${BASE}/employees${toQueryString({ ...params })}`)
}

/** GET /admin/dashboard/employees/{employee}/progress — single employee detail. */
export function getEmployeeDetailProgress(
  employeeId: number | string,
  filters: DashboardScopeFilters = {},
): Promise<ApiResponse<EmployeeDetailProgress>> {
  return apiRequest<EmployeeDetailProgress>(
    `${BASE}/employees/${employeeId}/progress${toQueryString({ ...filters })}`,
  )
}

/** GET /admin/dashboard/departments — paginated per-department progress. */
export function getDashboardDepartments(
  params: DashboardTableParams = {},
): Promise<ApiResponse<DepartmentProgressRow[]>> {
  return apiRequest<DepartmentProgressRow[]>(`${BASE}/departments${toQueryString({ ...params })}`)
}

/** GET /admin/dashboard/overdue — paginated overdue task list. */
export function getDashboardOverdue(
  params: DashboardTableParams = {},
): Promise<ApiResponse<OverdueTask[]>> {
  return apiRequest<OverdueTask[]>(`${BASE}/overdue${toQueryString({ ...params })}`)
}

/** GET /admin/dashboard/review-queue — submitted/under-review assignments. */
export function getDashboardReviewQueue(
  params: DashboardTableParams = {},
): Promise<ApiResponse<ReviewQueueItem[]>> {
  return apiRequest<ReviewQueueItem[]>(`${BASE}/review-queue${toQueryString({ ...params })}`)
}

/** GET /admin/dashboard/monthly-progress — metrics for a calendar month. */
export function getDashboardMonthlyProgress(
  filters: DashboardScopeFilters = {},
): Promise<ApiResponse<MonthlyProgress>> {
  return apiRequest<MonthlyProgress>(`${BASE}/monthly-progress${toQueryString({ ...filters })}`)
}

/** GET /admin/dashboard/monthly-achievement — ranked employee leaderboard. */
export function getDashboardMonthlyAchievement(
  filters: DashboardScopeFilters = {},
): Promise<ApiResponse<MonthlyAchievementRow[]>> {
  return apiRequest<MonthlyAchievementRow[]>(
    `${BASE}/monthly-achievement${toQueryString({ ...filters })}`,
  )
}

/** GET /admin/dashboard/target-vs-actual — target attainment snapshot. */
export function getDashboardTargetVsActual(
  filters: DashboardScopeFilters = {},
): Promise<ApiResponse<TargetVsActual>> {
  return apiRequest<TargetVsActual>(`${BASE}/target-vs-actual${toQueryString({ ...filters })}`)
}