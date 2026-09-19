/**
 * Types for the management dashboard API (Phase 3.3-H).
 *
 * Shapes mirror the JSON emitted by `ManagementDashboardService` and the
 * resources under `backend/app/Http/Resources/Api/V1/Dashboard`.
 */

export interface DashboardSummary {
  total_employees: number
  total_tasks: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  submitted_tasks: number
  under_review_tasks: number
  rejected_tasks: number
  cancelled_tasks: number
  on_time: number
  late: number
  completion_rate: number
  on_time_rate: number
}

export interface EmployeeProgressRow {
  employee_id: number
  employee_name: string
  assigned_tasks: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  completion_rate: number
}

export interface DepartmentProgressRow {
  department_id: number
  department_name: string
  total_tasks: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  completion_rate: number
}

export interface OverdueTask {
  task_id: number
  task_code: string
  title: string
  priority: string
  status: string
  department_id: number | null
  department_name: string | null
  due_date: string | null
  days_overdue: number | null
  assignee_names: string[]
}

export interface ReviewSummary {
  decision: string
  remarks: string | null
  reviewed_by: string | null
  reviewed_at: string | null
}

export interface ReviewQueueItem {
  assignment_id: number
  task_id: number
  task_code: string | null
  task_title: string | null
  department_id: number | null
  department_name: string | null
  employee_id: number
  employee_name: string | null
  status: string
  submitted_at: string | null
  remarks: string | null
  latest_review: ReviewSummary | null
}

export interface MonthlyProgress {
  year: number
  month: number
  total: number
  completed: number
  pending: number
  overdue: number
  on_time: number
  late: number
  submitted: number
  under_review: number
  completion_rate: number
}

export interface MonthlyAchievementRow {
  rank: number
  employee_id: number
  employee_name: string
  department_id: number | null
  department_name: string | null
  completed_tasks: number
  completion_rate: number
}

export interface TargetVsActual {
  target: number
  achieved: number
  remaining: number
  achievement_rate: number
  period: string
}

export interface EmployeeDetailProgress {
  employee_id: number
  employee_name: string
  department_id: number | null
  department_name: string | null
  summary: {
    total: number
    completed: number
    in_progress: number
    submitted: number
    under_review: number
    rejected: number
    pending: number
    overdue: number
    on_time: number
    late: number
    completion_rate: number
    on_time_rate: number
  }
  months: Array<{
    label: string
    total: number
    completed: number
    completion_rate: number
  }>
  recent_assignments: Array<{
    assignment_id: number
    task_id: number
    task_title: string | null
    task_code: string | null
    status: string
    due_date: string | null
    completed_at: string | null
    progress_percentage: number | null
  }>
}

/** Shared dashboard scope filters applied across every endpoint. */
export interface DashboardScopeFilters {
  department_id?: number | null
  academic_year_id?: number | null
  year?: number | null
  month?: number | null
  from_date?: string | null
  to_date?: string | null
}

/** Additional query params accepted by the paginated table endpoints. */
export interface DashboardTableParams extends DashboardScopeFilters {
  search?: string
  status?: string
  page?: number
  per_page?: number
}

export interface DashboardOptions {
  departments: Array<{ id: number; name: string }>
  academic_years: Array<{ id: number; name: string; status: string }>
}