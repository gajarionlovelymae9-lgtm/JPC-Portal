import { apiRequest, toQueryString } from './api'
import type { ApiResponse } from '../types/api'
import type {
  DepartmentProgressRow,
  EmployeeProgressRow,
  MonthlyAchievementRow,
} from '../types/task'

/**
 * Dashboard surface re-exports employee-scoped helpers that live in
 * `taskService` so callers can import the full dashboard API from one module.
 */
export { getMonthlyProgress, getMyTaskSummary } from './taskService'

/** GET /employees/progress — per-employee aggregates (manager/admin only). */
export function getEmployeeProgress(): Promise<ApiResponse<EmployeeProgressRow[]>> {
  return apiRequest<EmployeeProgressRow[]>('/employees/progress')
}

/** GET /departments/progress — per-department aggregates (manager/admin only). */
export function getDepartmentProgress(): Promise<ApiResponse<DepartmentProgressRow[]>> {
  return apiRequest<DepartmentProgressRow[]>('/departments/progress')
}

/** GET /employees/monthly-achievement?year=&month= */
export function getMonthlyAchievement(
  year: number,
  month: number,
): Promise<ApiResponse<MonthlyAchievementRow[]>> {
  return apiRequest<MonthlyAchievementRow[]>(
    `/employees/monthly-achievement${toQueryString({ year, month })}`,
  )
}
