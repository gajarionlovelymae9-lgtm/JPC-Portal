export type PerformanceRatingLabel =
  | 'Excellent'
  | 'Very Good'
  | 'Good'
  | 'Needs Improvement'
  | 'Unsatisfactory'

export interface PerformancePeriod {
  id: number
  name: string
  period_type: 'monthly' | 'quarterly' | 'yearly'
  year: number
  month: number | null
  quarter: number | null
  start_date: string
  end_date: string
  status: 'open' | 'closed' | 'finalized'
}

export interface PerformanceScoreBreakdownItem {
  metric_key: string
  metric_label: string
  raw_value: number
  weight: number
  weighted_value: number
}

export interface BadgeItem {
  id: number
  code: string
  name: string
  description?: string
  icon?: string
  awarded_at?: string
  criteria_snapshot?: Record<string, any>
}

export interface MonthlyAchievementHistoryItem {
  id: number
  month: number
  year: number
  completion_rate: number
  on_time_rate: number
  approved_reports: number
  completed_tasks: number
  overall_score: number
  rank: number | null
  is_employee_of_month: boolean
}

export interface PerformanceHistoryPoint {
  period_label: string
  year: number
  month: number
  score: number
  rating: string
}

export interface PerformanceKpis {
  task_completion_rate: number
  report_completion_rate: number
  on_time_submission_rate: number
  approval_rate: number
  target_achievement_rate: number
}

export interface EmployeePerformanceData {
  period: PerformancePeriod
  employee: {
    id: number
    name: string
    department?: string
    position?: string
  }
  kpis: PerformanceKpis
  productivity_score: number
  rating: PerformanceRatingLabel
  rating_color: 'emerald' | 'blue' | 'amber' | 'orange' | 'rose'
  calculation_version: string
  breakdowns: PerformanceScoreBreakdownItem[]
  metadata?: {
    total_tasks?: number
    completed_tasks?: number
    expected_reports?: number
    submitted_reports?: number
    on_time_reports?: number
    late_reports?: number
    reviewed_reports?: number
    approved_reports?: number
    rejected_reports?: number
    achieved_target_val?: number
    total_target_val?: number
  }
  badges: BadgeItem[]
  history: PerformanceHistoryPoint[]
  /** False when the period has no tasks, reports, or targets to evaluate. */
  has_activity?: boolean
}

export interface MonthlyRankItem {
  rank: number
  employee_id: number
  employee_name: string
  department_name: string
  productivity_score: number
  rating: string
  completion_rate: number
  on_time_rate: number
  approval_rate: number
  target_achievement_rate: number
  is_employee_of_month: boolean
}

export interface PerformanceInsight {
  is_advisory: boolean
  disclaimer: string
  summary: string
  trend_direction: 'improving' | 'declining' | 'stable'
  score_delta: number
  anomalies: string[]
  recommendations: string[]
}
