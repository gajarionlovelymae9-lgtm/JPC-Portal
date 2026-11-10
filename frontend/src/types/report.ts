import type { ApiResponse, PaginationMeta } from './api'

/**
 * Types for the Reports & Analytics API (Phase 3.3-J).
 *
 * Shapes mirror the JSON emitted by `backend/app/Services/Reports/*` and the
 * envelope built in `ReportController::reportResponse()` / `tableResponse()`.
 */

export type ReportScope = 'daily' | 'weekly' | 'monthly'
export type ExportFormat = 'csv' | 'xlsx' | 'pdf'

/** Query params accepted by the report endpoints (validated server-side). */
export interface ReportFilters {
  department_id?: number | null
  employee_id?: number | null
  academic_year_id?: number | null
  month?: number | null
  year?: number | null
  from_date?: string | null
  to_date?: string | null
  status?: string | null
  priority?: string | null
  search?: string
  sort?: string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
  scope?: ReportScope
  limit?: number
}

/** Echoed, sanitized filter set returned inside `meta.filters`. */
export interface ReportFiltersMeta {
  department_id: number | null
  employee_id: number | null
  academic_year_id: number | null
  from_date: string | null
  to_date: string | null
  month: number | null
  year: number | null
  status: string | null
  priority: string | null
  search: string | null
  sort: string
  direction: 'asc' | 'desc'
  limit: number | null
  scope: ReportScope
}

export interface ReportMeta {
  report_type: string
  generated_at: string
  filters: ReportFiltersMeta
  pagination?: PaginationMeta
}

export type ReportResponse<T> = Omit<ApiResponse<T>, 'meta'> & { meta?: ReportMeta }

export interface ReportOptionValue {
  id: number
  name: string
}

export interface ReportOptionYear extends ReportOptionValue {
  status: string
}

export interface ReportOptions {
  departments: ReportOptionValue[]
  academic_years: ReportOptionYear[]
  statuses: string[]
  priorities: string[]
  sortable: string[]
  scopes: ReportScope[]
  max_per_page: number
  trend_months: number
  top_performers_limit_max: number
}

/** Month-over-month chart feed shared by several reports. */
export interface CompletionChart {
  labels: string[]
  completed: number[]
  pending: number[]
  completion_rates: number[]
  month_over_month_pp: number
}

export interface TaskCompletionReport {
  period: string
  total_tasks: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  submitted_tasks: number
  rejected_tasks: number
  cancelled_tasks: number
  on_time: number
  late: number
  completion_rate: number
  on_time_rate: number
  period_active: boolean
  chart: CompletionChart
}

export interface EmployeePerformanceRow {
  rank: number | null
  employee_id: number | null
  employee_number: string | null
  employee_name: string | null
  department_id: number | null
  department_name: string | null
  total_tasks: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  on_time: number
  late: number
  completion_rate: number
  on_time_rate: number
  reports_submitted: number
  late_reports: number
  approval_rate: number | null
  target_achievement: number | null
  total_targets: number
  achieved_targets: number
  performance_status: string | null
  next_due_date: string | null
}

export interface RecentAssignment {
  assignment_id: number
  task_id: number
  task_code: string | null
  task_title: string | null
  department_id: number | null
  department_name: string | null
  status: string
  priority: string | null
  due_date: string | null
  completed_at: string | null
  progress_percentage: number | null
}

export interface EmployeePerformanceDetail extends EmployeePerformanceRow {
  recent_assignments: RecentAssignment[]
}

export interface DepartmentPerformanceRow {
  rank: number | null
  department_id: number | null
  department_name: string | null
  department_code: string | null
  employee_count: number
  total_tasks: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  on_time: number
  late: number
  completion_rate: number
  on_time_rate: number
  reports_submitted: number
  late_reports: number
  approval_rate: number | null
  on_time_submission_rate: number | null
  target_achievement: number | null
  total_targets: number
  achieved_targets: number
  next_due_date: string | null
}

export interface RecentTask {
  task_id: number
  task_code: string | null
  title: string | null
  status: string
  priority: string | null
  due_date: string | null
  completed_at: string | null
  assignee_names: string[]
}

export interface DepartmentPerformanceDetail extends DepartmentPerformanceRow {
  recent_tasks: RecentTask[]
}

export interface AchievementRow {
  rank: number | null
  employee_id: number | null
  employee_name: string | null
  department_id: number | null
  department_name: string | null
  total_tasks: number
  completed_tasks: number
  completion_rate: number
  on_time_rate: number
  overall_score: number
  is_employee_of_month: boolean
}

export interface MonthlyAchievementReport {
  period: string
  year: number
  month: number
  rankings: AchievementRow[]
}

export interface AchievementSeriesMonth {
  label: string
  total_tasks: number
  completed_tasks: number
  completion_rate: number
  on_time_rate: number
  overall_score: number
}

export interface AchievementBadge {
  id: number | null
  code: string | null
  name: string | null
  awarded_at: string | null
}

export interface EmployeeAchievements {
  employee_id: number
  employee_name: string | null
  department_id: number | null
  department_name: string | null
  period: string
  current_rank: number | null
  is_employee_of_month: boolean
  series: AchievementSeriesMonth[]
  badges: AchievementBadge[]
}

export interface ProgressTrendReport {
  period: string
  labels: string[]
  total: number[]
  completed: number[]
  pending: number[]
  overdue: number[]
  completion_rates: number[]
  month_over_month_pp: number
}

export interface TopPerformer {
  rank: number
  employee_id: number
  employee_name: string | null
  department_id: number | null
  department_name: string | null
  completed_tasks: number
  completion_rate: number
}

export interface TopPerformersReport {
  scope: ReportScope
  period: string
  limit: number
  performers: TopPerformer[]
}

export interface TargetVsActual {
  period: string
  year: number
  month: number
  target: number
  achieved: number
  remaining: number
  achievement_rate: number
}