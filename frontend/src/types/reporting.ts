import type { ApiResponse } from './api'

/**
 * Phase 9 — Advanced Reporting & Analytics payloads.
 *
 * Shapes mirror the Laravel JSON emitted by ReportingService,
 * AnalyticsService and ManagementSummaryService under /api/v1/reports/* and
 * /api/v1/analytics/*.
 */

export type PeriodKey =
  | { type: 'daily'; year: number; month: number; day: number }
  | { type: 'weekly'; year: number; week: number }
  | { type: 'monthly'; year: number; month: number }
  | { type: 'quarterly'; year: number; quarter: number }
  | { type: 'yearly'; year: number }
  | { type: 'academic_year'; academic_year_id: number }
  | { type: 'custom'; from: string; to: string }

export interface ReportingEnvelope {
  report_type: string
  period: string
  period_key: PeriodKey
  has_data: boolean
  generated_at: string
}

export interface EmployeeRef {
  id: number
  name: string
  employee_number: string
  department: string | null
  position: string | null
}

export interface EmployeeReport extends ReportingEnvelope {
  source: string
  employee: EmployeeRef
  metrics: {
    tasks_assigned: number
    tasks_completed: number
    task_completion_rate: number | null
    reports_expected: number
    reports_submitted: number
    report_completion_rate: number | null
    on_time_submission_rate: number | null
    approval_rate: number | null
    target_achievement_rate: number | null
    targets_total: number
    targets_achieved: number
    productivity_score: number | null
    rating: string | null
  }
  calculation_version: string
}

export interface DepartmentReport extends ReportingEnvelope {
  source: string
  department: {
    id: number
    name: string
    employee_count: number
  }
  metrics: {
    task_completion_rate: number | null
    report_completion_rate: number | null
    on_time_rate: number | null
    approval_rate: number | null
    target_achievement_rate: number | null
    average_productivity: number | null
    employees: number
    tasks_assigned: number
    tasks_completed: number
    reports_submitted: number
    late_reports: number
    rating: string | null
  }
  calculation_version: string
}

export interface InstitutionReport extends ReportingEnvelope {
  source: string
  scope: 'institution' | 'department'
  department_id: number | null
  summary: {
    total_employees: number
    active_employees: number
    total_departments: number
    tasks_assigned: number
    tasks_completed: number
    task_completion_rate: number | null
    reports_expected: number
    reports_submitted: number
    late_reports: number
    late_submitted: number
    late_outstanding: number
    pending_reports: number
    awaiting_action: number
    awaiting_submission: number
    approval_rate: number | null
    target_achievement_rate: number | null
    average_productivity: number | null
    on_time_rate: number | null
    data_quality_score: number | null
    data_quality_records_checked: number
    data_quality_issues_found: number
  }
  calculation_version: string
  data_sources: string[]
}

export interface MonthlyHighlight {
  employee_id: number | null
  name: string
  department: string | null
  score: number | null
}

export interface DepartmentRankingRow {
  department_id: number | null
  department_name: string
  employees_evaluated: number
  average_score: number | null
}

export interface MostImproved {
  employee_id: number
  employee_name: string
  department: string | null
  delta: number
  score: number
}

export interface MonthlyReport extends ReportingEnvelope {
  scope: 'institution' | 'department'
  top_performer: MonthlyHighlight | null
  highest_completion: { employee_id: number; name: string; completion_rate: number | null } | null
  highest_target: { employee_id: number; name: string; target_rate: number | null } | null
  most_improved: MostImproved | null
  top_department: DepartmentRankingRow | null
  department_rankings: DepartmentRankingRow[]
  summary: {
    employees_evaluated: number
    average_productivity: number | null
    previous_average_productivity: number | null
    performance_change: number | null
    monthly_achievements: number
    late_reports: number
  }
  calculation_version: string
  data_sources: string[]
}

export interface AchievementRow {
  id: number
  employee_id: number
  employee_name: string
  department: string | null
  period: string
  performance_score: number | null
  completion_rate: number | null
  on_time_rate: number | null
  completed_tasks: number
  approved_reports: number
  rank: number | null
  is_employee_of_month: boolean
  badge: { name: string; code: string; icon: string | null } | null
}

export interface AchievementReport extends ReportingEnvelope {
  total: number
  total_badges: number
  achievements: AchievementRow[]
  rankings: AchievementRow[]
  data_sources: string[]
}

export interface LateReport extends ReportingEnvelope {
  summary: {
    late_reports: number
    total_reports: number
    late_submission_rate: number | null
    late_employees: number
    late_departments: number
  }
  comparison: { period: string; late_reports: number; total_reports: number; change_pct: number } | null
  by_employee: Array<{ employee_id: number; employee_name: string; department: string | null; late_reports: number }>
  by_department: Array<{ department_id: number | null; department_name: string; late_reports: number }>
  reports: Array<{
    id: number
    title: string
    employee: string
    department: string | null
    task: string | null
    status: string
    status_label: string
    submitted_at: string | null
    deadline: string | null
    days_late: number
    is_outstanding: boolean
  }>
  data_sources: string[]
}

export interface PendingReport extends ReportingEnvelope {
  summary: {
    pending_reports: number
    pending_employees: number
    pending_departments: number
    awaiting_review: number
    revision_required: number
  }
  by_employee: Array<{ employee_id: number; employee_name: string; department: string | null; pending_reports: number }>
  by_department: Array<{ department_id: number | null; department_name: string; pending_reports: number }>
  by_status: Array<{ status: string; count: number }>
  by_month: Array<{ period: string; pending_reports: number }>
  reports: Array<{
    id: number
    title: string
    employee: string
    department: string | null
    status: string
    status_label: string
    category: 'not_submitted' | 'pending_review' | 'revision_required' | 'approved' | 'closed'
    submitted_at: string | null
    deadline: string | null
    days_remaining: number | null
    days_overdue: number | null
    is_overdue: boolean
    required_action: string
  }>
  data_sources: string[]
}

export interface TargetReport extends ReportingEnvelope {
  total_targets: number
  achieved_targets: number
  partially_achieved: number
  unachieved: number
  in_progress: number
  achievement_rate: number | null
  total_target_value: number
  total_actual: number
  achievement_percentage: number | null
  by_department: Array<{
    department_id: number | null
    department_name: string
    total_targets: number
    achieved: number
    achievement_rate: number | null
    total_target_value: number
    total_actual: number
    achievement_percentage: number | null
  }>
  targets: Array<{
    id: number
    title: string
    status: string
    status_label: string
    target_value: number
    target_unit: string | null
    actual_value: number
    achievement_percentage: number | null
    remaining_target: number
    period_type: 'monthly' | 'quarterly' | 'annual'
    employee: string
    department: string | null
    start_date: string | null
    end_date: string | null
  }>
  data_sources: string[]
}

export interface DataQualityReport extends ReportingEnvelope {
  summary: {
    data_quality_score: number | null
    evaluated: boolean
    records_checked: number
    issues_found: number
    latest_scan_at: string | null
    total_issues: number
    open_issues: number
    critical: number
    high: number
    medium: number
    low: number
    resolved: number
    dismissed: number
  }
  by_category: Array<{ label: string; count: number }>
  by_department: Array<{ label: string; count: number }>
  trends: Array<{ period: string; score: number | null }>
  recent_scans: Array<{
    id: number
    status: string
    records_checked: number
    issues_found: number
    created_at: string | null
  }>
  data_sources: string[]
}

export interface ManagementSummary {
  period: string
  period_label: string
  period_type: string
  narrative: string
  key_takeaways: string[]
  has_data: boolean
  generated_at: string
  metrics?: {
    average_productivity: number | null
    tasks_completed: number
    tasks_assigned: number
    task_completion_rate: number | null
    reports_submitted: number
    pending_reports: number
    late_reports: number
    on_time_rate: number | null
    approval_rate: number | null
    data_quality_score: number | null
  }
  change?: {
    period: string | null
    period_label: string
    average_productivity_change: number | null
    task_completion_change: number | null
    reports_change: number
    late_change: number
    previous_period: string
  } | null
  distribution?: Record<string, number>
  top_performer?: MonthlyHighlight | null
  top_department?: DepartmentRankingRow | null
}

export interface PerformanceTrendPoint {
  period: string
  year: number
  month: number
  productivity_score: number | null
  completion_rate: number | null
  records_count: number
}

export interface CompletionTrendPoint {
  period: string
  year: number
  month: number
  completion_rate: number | null
  records_count: number
}

export interface SubmissionTrendPoint {
  period: string
  year: number
  month: number
  total_reports: number
  late_reports: number
  late_rate: number | null
}

export interface DepartmentComparisonRow {
  id: number
  name: string
  productivity: number | null
  completion_rate: number | null
  on_time_rate: number | null
  approval_rate: number | null
  target_achievement: number | null
  employees_evaluated: number
}

export interface EmployeeComparisonRow {
  id: number
  name: string
  department: string | null
  productivity: number | null
  completion_rate: number | null
  on_time_rate: number | null
  approval_rate: number | null
  target_achievement: number | null
}

export interface PeriodComparison {
  period_a: {
    period: string
    _key: string
    productivity: number | null
    completion_rate: number | null
    on_time_rate: number | null
    employees_evaluated: number
  }
  period_b: {
    period: string
    _key: string
    productivity: number | null
    completion_rate: number | null
    on_time_rate: number | null
    employees_evaluated: number
  }
  delta: {
    productivity_diff: number | null
    completion_rate_diff: number | null
    on_time_rate_diff: number | null
  }
}

export interface RankingRow {
  rank: number
  employee_id: number
  name: string
  department: string | null
  metric: string
  score: number | null
  completion_rate: number | null
  on_time_rate: number | null
  target_achievement: number | null
  rating: string | null
}

export interface PerformanceDistribution {
  total_evaluated: number
  distribution: Record<string, number>
}

export type ComparisonType = 'employee' | 'department' | 'period'

export type ComparisonResult =
  | DepartmentComparisonRow[]
  | EmployeeComparisonRow[]
  | PeriodComparison

export interface ReportExport {
  id: number
  report_type: string
  format: string
  status: string
  status_label: string
  filters: Record<string, unknown> | null
  file_name: string | null
  file_size: number | null
  row_count: number | null
  error_message: string | null
  error_line: string | null
  requested_at: string | null
  completed_at: string | null
  expires_at: string | null
}

export type ReportingResponse<T> = ApiResponse<T>

export interface ReportingFilters {
  period_type?: string
  year?: number
  month?: number
  quarter?: number
  start_date?: string
  end_date?: string
  department_id?: number
  academic_year_id?: number
  employee_id?: number
  metric?: string
  limit?: number
}

export type ReportExportTypeValue =
  | 'employee_progress'
  | 'department_performance'
  | 'institution_performance'
  | 'monthly_performance'
  | 'achievement_summary'
  | 'late_reports'
  | 'pending_reports'
  | 'targets_summary'
  | 'data_quality'