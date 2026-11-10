import { apiRequest, downloadFile, toQueryString } from './api'
import type { ApiResponse } from '../types/api'

const BASE = '/admin'

/** Envelope used by every list endpoint in this service. */
export interface PagedData<T> {
  items: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface OptionItem {
  value: string
  label: string
}

export interface DepartmentOption {
  id: number
  name: string
}

export interface EmployeeOption {
  id: number
  name: string
  employee_number: string
  department: string
  /** Present on monitoring options; used to scope the create-task employee picker. */
  department_id?: number
  employment_status?: string
}

/* ------------------------------------------------------------------ */
/* Section 1 - Executive dashboard + platform status                   */
/* ------------------------------------------------------------------ */

export interface PlatformServiceState {
  key: string
  label: string
  state: string
  detail: string
}

export interface PlatformArchitectureItem {
  key: string
  label: string
  value: string
}

export interface PlatformStatusData {
  checked_at: string
  overall: string
  environment: string
  api_version: string
  services: PlatformServiceState[]
  architecture: PlatformArchitectureItem[]
  last_backup: string | null
}

export const getAdminPlatformStatus = () =>
  apiRequest<PlatformStatusData>(`${BASE}/platform-status`)

/* ------------------------------------------------------------------ */
/* Section 2 - Performance overview charts                             */
/* ------------------------------------------------------------------ */

export interface ChartSlice {
  key: string
  label: string
  value: number
  percent: number
}

export interface PerformanceOverviewData {
  year: number
  employee_progress: {
    total: number
    items: ChartSlice[]
  }
  department_performance: Array<{
    id: number
    name: string
    task_completion_pct: number
    report_coverage_pct: number
    kpi_achievement_pct: number
    ai_validation_score: number | null
    late_tasks: number
    tasks_total: number
  }>
  monthly_progress: Array<{
    month: number
    label: string
    key: string
    total: number
    completed: number
    late: number
    reports: number
    completion_rate: number
  }>
  report_status: {
    total: number
    items: ChartSlice[]
  }
  generated_at: string
}

export const getAdminPerformanceOverview = (params: Record<string, unknown> = {}) =>
  apiRequest<PerformanceOverviewData>(`${BASE}/performance-overview${toQueryString(params)}`)

/* ------------------------------------------------------------------ */
/* Section 3 - KPI targets                                              */
/* ------------------------------------------------------------------ */

export interface KpiTargetItem {
  id: number
  employee_id: number
  employee_name: string
  employee_number: string
  department_id: number
  department_name: string
  kpi: string
  description: string
  target: number
  target_unit: string | null
  /** Derived from linked completed tasks; null when no task data exists. */
  actual: number | null
  tasks_total: number
  tasks_completed: number
  /** null when the target has no linked tasks, so 0% is never implied. */
  achievement_pct: number | null
  stored_status: string
  derived_status: string
  period: string | null
  start_date: string | null
  end_date: string | null
}

export interface KpiSummary {
  total_kpis: number
  completed_targets: number
  in_progress_targets: number
  missed_targets: number
  not_started_targets: number
  avg_kpi_achievement: number
}

export interface KpiOptions {
  departments: DepartmentOption[]
  academic_years: Array<{ id: number; name: string; status: string }>
  statuses: OptionItem[]
}

export interface KpiTargetsData extends PagedData<KpiTargetItem> {
  summary: KpiSummary
  /** Filter options are embedded in the list response. */
  options: KpiOptions
}

export const getAdminKpiTargets = (params: Record<string, unknown> = {}) =>
  apiRequest<KpiTargetsData>(`${BASE}/kpi-targets${toQueryString(params)}`)

/* ------------------------------------------------------------------ */
/* Section 3b - Target adjustment review queue                         */
/* ------------------------------------------------------------------ */

export interface TargetAdjustmentProposal {
  target_value: number | null
  target_unit: string | null
  start_date: string | null
  end_date: string | null
  description: string | null
}

export interface TargetAdjustmentItem {
  id: number
  employee_target_id: number
  employee_id: number
  target_title: string | null
  employee_name: string | null
  employee_number: string | null
  department_name: string | null
  reason: string
  request_type: string | null
  proposed: TargetAdjustmentProposal
  status: 'pending' | 'approved' | 'rejected'
  decision_note: string | null
  reviewed_by: string | null
  reviewed_at: string | null
  applied_at: string | null
  original_snapshot: Record<string, unknown> | null
  requested_by: string | null
  created_at: string | null
}

export interface TargetAdjustmentSummary {
  total: number
  pending: number
  approved: number
  rejected: number
}

export interface TargetAdjustmentQueueData extends PagedData<TargetAdjustmentItem> {
  summary: TargetAdjustmentSummary
}

export interface TargetAdjustmentDetail extends TargetAdjustmentItem {
  target: {
    id: number
    title: string | null
    description: string | null
    target_value: number | null
    target_unit: string | null
    progress: number | null
    derived_status: string
  } | null
  target_detail: {
    breakdowns: Array<{ id: number; title: string; progress: number; status: string }>
    milestones: Array<{
      id: number
      title: string
      threshold_percent: number
      status: string
      achieved_at: string | null
    }>
    notes: Array<{ id: number; body: string; author: string | null; created_at: string | null }>
  } | null
}

/** The backend embeds the raw Laravel paginator under `items`. */
interface RawAdjustmentQueue {
  items: {
    data: TargetAdjustmentItem[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  summary: TargetAdjustmentSummary
}

export const getAdminTargetAdjustments = async (
  params: Record<string, unknown> = {},
): Promise<ApiResponse<TargetAdjustmentQueueData>> => {
  const response = await apiRequest<RawAdjustmentQueue | null>(
    `${BASE}/target-adjustments${toQueryString(params)}`,
  )
  const raw = response.data

  return {
    ...response,
    data: {
      items: raw?.items?.data ?? [],
      meta: {
        current_page: raw?.items?.current_page ?? 1,
        last_page: raw?.items?.last_page ?? 1,
        per_page: raw?.items?.per_page ?? 0,
        total: raw?.items?.total ?? 0,
      },
      summary: raw?.summary ?? { total: 0, pending: 0, approved: 0, rejected: 0 },
    },
  }
}

export const getAdminTargetAdjustment = (id: number | string) =>
  apiRequest<TargetAdjustmentDetail>(`${BASE}/target-adjustments/${id}`)

export const approveAdminTargetAdjustment = (id: number | string, decisionNote?: string | null) =>
  apiRequest<TargetAdjustmentItem>(`${BASE}/target-adjustments/${id}/approve`, {
    method: 'POST',
    body: { decision_note: decisionNote ?? null },
  })

export const rejectAdminTargetAdjustment = (id: number | string, decisionNote?: string | null) =>
  apiRequest<TargetAdjustmentItem>(`${BASE}/target-adjustments/${id}/reject`, {
    method: 'POST',
    body: { decision_note: decisionNote ?? null },
  })

/* ------------------------------------------------------------------ */
/* Sections 5 + 9 - Task and report monitoring                         */
/* ------------------------------------------------------------------ */

export interface MonitoredTask {
  assignment_id: number
  task_id: number
  task_name: string
  task_code: string
  assigned_by: string
  assigned_to: string
  employee_id: number
  employee_number: string
  department: string
  priority: string
  start_date: string | null
  due_date: string | null
  progress: number
  raw_status: string
  /** Normalised label: in_progress, not_started, overdue, completed... */
  status: string
  days_overdue: number
}

export interface TaskMonitoringData extends PagedData<MonitoredTask> {
  summary: Record<string, number>
}

export interface MonitoredReport {
  id: number
  title: string
  employee: string
  employee_id: number
  employee_number: string
  department: string
  task: string
  task_id: number | null
  submission_date: string | null
  submitted_at: string | null
  due_date: string | null
  status: string
  status_label: string
  is_late: boolean
  ai_status: string
  /** Comes from the seeded jpc.sample provider - not a live AI result. */
  ai_validation_score: number | null
  ai_result: string | null
  approvals_count: number
  approval_status: string
}

export interface ReportMonitoringData extends PagedData<MonitoredReport> {
  summary: Record<string, number>
}

export interface MonitoringOptions {
  departments: DepartmentOption[]
  employees: EmployeeOption[]
  task_statuses: OptionItem[]
  report_statuses: OptionItem[]
}

export const getAdminTaskMonitoring = (params: Record<string, unknown> = {}) =>
  apiRequest<TaskMonitoringData>(`${BASE}/task-monitoring${toQueryString(params)}`)

export const getAdminReportMonitoring = (params: Record<string, unknown> = {}) =>
  apiRequest<ReportMonitoringData>(`${BASE}/report-monitoring${toQueryString(params)}`)

export const getAdminMonitoringOptions = () =>
  apiRequest<MonitoringOptions>(`${BASE}/monitoring-options`)

/* ------------------------------------------------------------------ */
/* Section 11 - Institutional calendar                                  */
/* ------------------------------------------------------------------ */

export interface CalendarEvent {
  id: string
  type: string
  type_label: string
  title: string
  employee: string | null
  department: string | null
  date: string
  time: string | null
  description: string
  status: string
  link: string
}

export interface CalendarSource {
  type: string
  label: string
  supported: boolean
  source: string | null
  note?: string
}

export interface CalendarData {
  events: CalendarEvent[]
  sources: CalendarSource[]
  range: { from: string; to: string }
}

export const getAdminCalendar = (params: Record<string, unknown> = {}) =>
  apiRequest<CalendarData>(`${BASE}/calendar${toQueryString(params)}`)

/* ------------------------------------------------------------------ */
/* Sections 12 + 13 - Notifications, audit trail, governance            */
/* ------------------------------------------------------------------ */

export interface AdminNotification {
  id: number
  type: string
  title: string
  message: string
  data: Record<string, unknown> | null
  priority: string
  is_read: boolean
  read_at: string | null
  task_id: number | null
  reminder_days: number | null
  recipient: string
  recipient_email: string
  /** Additive enrichment from the People/delivery surfaces (may be null). */
  recipient_id?: number | null
  employee_number?: string | null
  department?: string | null
  delivery_channel?: string
  delivery_status?: string
  triggered_by?: string
  created_at: string
}

export interface NotificationsData extends PagedData<AdminNotification> {
  summary: Record<string, number>
}

export interface AuditTrailEntry {
  /** Prefixed id: `audit-{id}` or `activity-{id}`. */
  id: string
  source: string
  event: string
  actor: string
  actor_id: number | null
  actor_email: string | null
  entity: string
  entity_type: string
  entity_id: number | null
  /** Field-level diff from `audit_logs.old_values` / `new_values`. */
  changes: Array<{ field: string; old: string | null; new: string | null }>
  description: string
  metadata: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  created_at: string
}

export interface AuditTrailData extends PagedData<AuditTrailEntry> {
  summary: Record<string, number>
}

export interface GovernanceSummary {
  notifications: {
    total: number
    unread: number
    high_priority: number
    last_24h: number
    failed_deliveries?: number
  }
  audit_logs: { total: number; last_7_days: number }
  activity_logs: { total: number; last_7_days: number; failed_logins_7d: number }
  generated_at: string
}

export interface GovernanceOptions {
  audit_actions: OptionItem[]
  entity_types: OptionItem[]
  activity_types: OptionItem[]
  notification_types: OptionItem[]
  /** Department filter options, straight from the departments table. */
  departments?: OptionItem[]
  sources: OptionItem[]
  priorities: OptionItem[]
}

export const getAdminNotifications = (params: Record<string, unknown> = {}) =>
  apiRequest<NotificationsData>(`${BASE}/notifications${toQueryString(params)}`)

export const getAdminAuditTrail = (params: Record<string, unknown> = {}) =>
  apiRequest<AuditTrailData>(`${BASE}/audit-trail${toQueryString(params)}`)

export const getAdminGovernanceSummary = () =>
  apiRequest<GovernanceSummary>(`${BASE}/governance-summary`)

export const getAdminGovernanceOptions = () =>
  apiRequest<GovernanceOptions>(`${BASE}/governance-options`)

/* ------------------------------------------------------------------ */
/* Section 14 - AI insights                                             */
/* ------------------------------------------------------------------ */

export interface AiInsightCounts {
  total: number
  by_type: Array<{ type: string; label: string; total: number }>
}

/**
 * A row from `ai_insights`.
 *
 * `AiInsight` has no title, summary, confidence or score column, so none of
 * those are declared here. Only `type` + `content` may be rendered.
 */
export interface AiInsightItem {
  id: number
  type: string
  type_label: string
  content: string
  metadata: Record<string, unknown> | null
  employee_id: number | null
  employee: string | null
  department_id: number | null
  department: string | null
  generated_at: string | null
  scope: 'institution' | 'department' | 'employee'
  is_sample_data?: boolean
  provenance?: Record<string, unknown> | null
}

export interface AiValidationSampleOverview {
  total: number
  avg_score: number | null
  avg_confidence: number | null
  passed: number
  warning: number
  failed: number
  present: boolean
}

export interface AiValidationOverview {
  has_live_data: boolean
  total: number
  /** Already scaled to 0..100 by the backend. */
  avg_score: number | null
  avg_confidence: number | null
  passed: number
  warning: number
  failed: number
  score_scale: string
  is_sample_data: boolean
  provenance_note: string | null
  sample: AiValidationSampleOverview
}

export interface AiAnalysisRun {
  id: number
  report_id: number | null
  report: string | null
  analysis_type: string
  provider: string | null
  model: string | null
  status: string
  error_message: string | null
  started_at: string | null
  completed_at: string | null
  is_sample_data: boolean
}

export interface AiProviderStatus {
  configured_provider: string
  model: string | null
  endpoint_configured: boolean
  api_key_configured: boolean
  configured: boolean
  connected: boolean
  live: boolean
  data_source: 'live_database' | 'no_live_data'
  data_source_label: string
  last_successful_ai_request: string | null
  last_successful_analysis: string | null
  is_sample_data: boolean
  sample_data_present: boolean
  note: string | null
}

export interface AiSampleData {
  present: boolean
  validation: AiValidationSampleOverview
  analysis_runs: AiAnalysisRun[]
}

export interface AiInsightsData {
  insights: AiInsightItem[]
  /** False when `ai_insights` is empty; the UI must show the empty state. */
  available: boolean
  message: string | null
  generated: boolean
  counts: AiInsightCounts
  reports_analyzed: number
  validation: AiValidationOverview
  recent_runs: AiAnalysisRun[]
  sample_runs: AiAnalysisRun[]
  sample_data: AiSampleData
  provider_status: AiProviderStatus
  filters_applied: Record<string, unknown>
}

export interface AiInsightOptions {
  types: OptionItem[]
  departments: DepartmentOption[]
  employees: Array<{ value: number; label: string }>
  reports: Array<{ value: number; label: string }>
}

export const getAdminAiInsights = (params: Record<string, unknown> = {}) =>
  apiRequest<AiInsightsData>(`${BASE}/ai-insights${toQueryString(params)}`)

export const getAdminAiInsightOptions = () =>
  apiRequest<AiInsightOptions>(`${BASE}/ai-insight-options`)

export interface AiInsightGeneratePayload {
  type?: string
  employee_id?: number | string
  report_id?: number | string
  department_id?: number | string
  period?: string
}

export interface AiInsightGenerateResult {
  insight: AiInsightItem
  human_verification_required: boolean
  advisory_only: boolean
}

export const generateAdminAiInsight = (payload: AiInsightGeneratePayload) =>
  apiRequest<AiInsightGenerateResult>(`${BASE}/ai-insights/generate`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })

/* ------------------------------------------------------------------ */
/* Section 15 - User management                                         */
/* ------------------------------------------------------------------ */

export interface ManagedUser {
  id: number
  name: string
  email: string
  username: string | null
  status: string
  status_label: string
  can_authenticate: boolean
  is_active: boolean
  role: string
  role_label: string
  employee_id: number | null
  employee_name: string | null
  employee_number: string | null
  department: string | null
  email_verified_at: string | null
  last_login_at: string | null
  created_at: string
}

export interface UserSummary {
  total_users: number
  active_users: number
  inactive_users: number
  suspended_users: number
  unlinked_employees: number
  by_role: Array<{ role: string; label: string; total: number }>
}

export interface UserOptions {
  roles: Array<{ id: number; value: string; label: string }>
  statuses: OptionItem[]
  unlinked_employees: EmployeeOption[]
  /**
   * Every employee, including those who already hold an account. The create
   * form uses `account` to block a second login and point at the existing one.
   */
  employees?: EmployeeAccountOption[]
}

export interface EmployeeAccountOption {
  id: number
  name: string
  employee_number: string
  department: string | null
  account: {
    id: number
    name: string
    email: string
    username: string | null
    status: string | null
  } | null
}

export interface UsersData extends PagedData<ManagedUser> {
  summary: UserSummary
  /** Filter/form options are embedded in the list response. */
  options: UserOptions
}

export interface UserPayload {
  name: string
  email: string
  username: string
  password?: string
  /** Role key (`admin`, `hr`, `department_head`, `employee`), not the role id. */
  role: string
  employee_id?: number | null
  status?: string
}

export const getAdminUsers = (params: Record<string, unknown> = {}) =>
  apiRequest<UsersData>(`${BASE}/users${toQueryString(params)}`)

export const getAdminUser = (id: number) => apiRequest<ManagedUser>(`${BASE}/users/${id}`)

export const createAdminUser = (payload: UserPayload) =>
  apiRequest<ManagedUser>(`${BASE}/users`, { method: 'POST', body: payload })

export const updateAdminUser = (id: number, payload: Partial<UserPayload>) =>
  apiRequest<ManagedUser>(`${BASE}/users/${id}`, { method: 'PUT', body: payload })

export const deactivateAdminUser = (id: number, reason?: string) =>
  apiRequest<ManagedUser>(`${BASE}/users/${id}`, {
    method: 'DELETE',
    body: reason ? { reason } : undefined,
  })

export const reactivateAdminUser = (id: number) =>
  apiRequest<ManagedUser>(`${BASE}/users/${id}/reactivate`, { method: 'POST' })

/* ------------------------------------------------------------------ */
/* Section 8 - Employee progress                                        */
/* ------------------------------------------------------------------ */

export interface EmployeeProgressRow {
  employee_id: number
  name: string
  first_name: string
  last_name: string
  employee_number: string
  department_id: number | null
  department: string | null
  employment_status: string
  assigned_tasks: number
  completed_tasks: number
  pending_tasks: number
  in_progress_tasks: number
  late_tasks: number
  reports_total: number
  reports_approved: number
  reports_late: number
  reports_not_submitted: number
  kpi_pct: number | null
  /** Average of the seeded jpc.sample validation scores; null when unscored. */
  ai_score: number | null
  completion_rate: number | null
  overall_progress: number | null
  status: string
  status_label: string
}

export interface EmployeeProgressSummary {
  total_employees: number
  assigned_tasks: number
  completed_tasks: number
  pending_tasks: number
  late_tasks: number
  completion_rate: number
  late_rate: number
}

export interface EmployeeProgressDetail extends EmployeeProgressRow {
  position: string | null
  email: string | null
  tasks: Array<{
    assignment_id: number
    task_name: string
    task_code: string
    priority: string
    status: string
    progress: number
    due_date: string | null
  }>
  reports: Array<{
    id: number
    title: string
    status: string
    deadline: string | null
    submitted_at: string | null
    is_late: boolean
    ai_score: number | null
  }>
  monthly_achievements: Array<{
    id: number
    month: number
    year: number
    completion_rate: number
    on_time_rate: number
    approved_reports: number
    completed_tasks: number
    overall_score: number
    is_employee_of_month: boolean
  }>
}

export interface EmployeeProgressOptions {
  departments: DepartmentOption[]
  statuses: OptionItem[]
}

export interface EmployeeProgressData extends PagedData<EmployeeProgressRow> {
  summary: EmployeeProgressSummary
}

export const getAdminEmployeeProgress = (params: Record<string, unknown> = {}) =>
  apiRequest<EmployeeProgressData>(`${BASE}/employee-progress${toQueryString(params)}`)

export const getAdminEmployeeProgressOptions = () =>
  apiRequest<EmployeeProgressOptions>(`${BASE}/employee-progress/options`)

export const getAdminEmployeeProgressDetail = (employeeId: number) =>
  apiRequest<EmployeeProgressDetail>(`${BASE}/employee-progress/${employeeId}`)

/* ------------------------------------------------------------------ */
/* Section 12 - Achievements                                            */
/* ------------------------------------------------------------------ */

export interface AchievementRow {
  id: number
  employee_id: number
  name: string
  employee_number: string
  department: string | null
  period: string
  month: number
  year: number
  completion_rate: number
  on_time_rate: number
  approved_reports: number
  completed_tasks: number
  overall_score: number
  rank: number | null
  employee_of_the_month: boolean
}

export interface BadgeAward {
  id: number
  employee_id: number
  name: string
  employee_number: string
  department: string | null
  badge: string
  badge_code: string
  badge_icon: string | null
  badge_description: string | null
  badge_category: string | null
  academic_year: string | null
  period_start: string | null
  period_end: string | null
  awarded_at: string | null
  awarded_by: string | null
  verification_status: 'pending' | 'verified' | 'rejected'
  verified_by: string | null
  verified_at: string | null
  rejection_reason: string | null
  evidence: Record<string, unknown> | null
}

export interface BadgeDefinition {
  id: number
  name: string
  code: string
  description: string | null
  icon: string | null
  category: string | null
  criteria_type: string | null
  criteria_value: Record<string, unknown> | null
  is_active: boolean
  allow_repeats: boolean
  earned_count: number
  created_at: string | null
  updated_at: string | null
}

export interface BadgeDefinitionPayload {
  name: string
  code: string
  description?: string | null
  icon?: string | null
  category?: string | null
  criteria_type?: string | null
  criteria_value?: Record<string, unknown> | null
  is_active?: boolean
  allow_repeats?: boolean
}

export interface AwardDetail {
  id: number
  employee_id: number
  employee_name: string | null
  employee_number: string | null
  department: string | null
  badge_id: number
  badge_name: string | null
  badge_code: string | null
  badge_icon: string | null
  academic_year: string | null
  period_start: string | null
  period_end: string | null
  awarded_at: string | null
  awarded_by: string | null
  verification_status: 'pending' | 'verified' | 'rejected'
  verified_by: string | null
  verified_at: string | null
  rejection_reason: string | null
  evidence: Record<string, unknown> | null
}

export interface AchievementSummary {
  rollup_rows: number
  employees_recognised: number
  employee_of_the_month_count: number
  badge_awards: number
  distinct_badges_awarded: number
  avg_overall_score: number | null
  top_overall_score: number | null
}

export interface AchievementsData extends PagedData<AchievementRow> {
  summary: AchievementSummary
  leaders: AchievementRow[]
}

export interface BadgeAwardsData extends PagedData<BadgeAward> {}

export interface AchievementOptions {
  years: Array<{ value: number; label: string }>
  months: Array<{ value: number; label: string }>
  departments: DepartmentOption[]
  academic_years: Array<{ id: number; name: string }>
  categories: OptionItem[]
  statuses: OptionItem[]
  badges: Array<{ id: number; name: string; code: string; icon: string | null; is_active: boolean }>
}

export interface AchievementSummaryCards {
  total_active_badges: number
  total_achievements_awarded: number
  pending_reviews: number
  employees_recognized: number
}

export interface AchievementSummaries {
  cards: AchievementSummaryCards
  top_employees: Array<{
    rank: number
    employee_id: number
    name: string
    employee_number: string
    department: string | null
    total: number
  }>
  monthly_counts: Array<{ year: number; month: number; total: number }>
  department_distribution: Array<{ department: string; total: number }>
  badge_frequency: Array<{
    badge_id: number
    name: string
    icon: string | null
    category: string | null
    total: number
  }>
  academic_year_summaries: Array<{
    academic_year_id: number
    name: string
    total: number
    employees: number
  }>
}

export interface EvaluateMonthResult {
  period: string
  year: number
  month: number
  total_evaluated: number
  employee_of_the_month: string | null
}

export const getAdminAchievements = (params: Record<string, unknown> = {}) =>
  apiRequest<AchievementsData>(`${BASE}/achievements${toQueryString(params)}`)

export const getAdminBadgeAwards = (params: Record<string, unknown> = {}) =>
  apiRequest<BadgeAwardsData>(`${BASE}/achievements/badges${toQueryString(params)}`)

export const getAdminAchievementOptions = () =>
  apiRequest<AchievementOptions>(`${BASE}/achievements/options`)

export const getAdminAchievementSummaries = (params: Record<string, unknown> = {}) =>
  apiRequest<AchievementSummaries>(`${BASE}/achievements/summaries${toQueryString(params)}`)

export const getAdminBadgeDefinitions = (params: Record<string, unknown> = {}) =>
  apiRequest<PagedData<BadgeDefinition>>(`${BASE}/achievements/badge-definitions${toQueryString(params)}`)

export const createAdminBadge = (payload: BadgeDefinitionPayload) =>
  apiRequest<BadgeDefinition>(`${BASE}/achievements/badge-definitions`, { method: 'POST', body: payload })

export const updateAdminBadge = (id: number, payload: Partial<BadgeDefinitionPayload>) =>
  apiRequest<BadgeDefinition>(`${BASE}/achievements/badge-definitions/${id}`, { method: 'PUT', body: payload })

export const deleteAdminBadge = (id: number) =>
  apiRequest<{ action: 'deleted' | 'deactivated'; badge: BadgeDefinition | { id: number; code: string; deleted: boolean } }>(
    `${BASE}/achievements/badge-definitions/${id}`,
    { method: 'DELETE' },
  )

export const getAdminAwardDetail = (id: number) =>
  apiRequest<AwardDetail>(`${BASE}/achievements/awards/${id}`)

export const verifyAdminAward = (id: number) =>
  apiRequest<AwardDetail>(`${BASE}/achievements/awards/${id}/verify`, { method: 'POST' })

export const rejectAdminAward = (id: number, reason: string) =>
  apiRequest<AwardDetail>(`${BASE}/achievements/awards/${id}/reject`, { method: 'POST', body: { reason } })

export const evaluateAdminAchievements = (year: number, month: number) =>
  apiRequest<EvaluateMonthResult>(`${BASE}/achievements/evaluate`, { method: 'POST', body: { year, month } })

/* ------------------------------------------------------------------ */
/* Section 13 - Exports                                                 */
/* ------------------------------------------------------------------ */

/**
 * The admin pages reuse the existing Phase 9 export endpoints rather than
 * duplicating a second export pipeline. Each page owns the row set, so the
 * export is intentionally a separate query built from the same filters.
 *
 * `analytics.view` (employee/department/task) or `reports.view`
 * (achievements) — an admin holds every permission.
 */
export type ExportFormat = 'csv' | 'xlsx' | 'pdf'

export const ADMIN_EXPORTS = {
  employeePerformance: {
    path: '/reports/export/employee-performance',
    label: 'Employee performance',
    filename: 'Employee_Performance',
  },
  departmentPerformance: {
    path: '/reports/export/department-performance',
    label: 'Department performance',
    filename: 'Department_Performance',
  },
  taskCompletion: {
    path: '/reports/export/task-completion',
    label: 'Task completion',
    filename: 'Task_Completion',
  },
  achievements: {
    path: '/reports/export/achievements',
    label: 'Achievements',
    filename: 'Achievements',
  },
} as const satisfies Record<string, { path: string; label: string; filename: string }>

export type AdminExportKey = keyof typeof ADMIN_EXPORTS

/**
 * Trigger a real file download. Returns the saved filename so the caller can
 * confirm what was written.
 */
export async function downloadAdminExport(
  key: AdminExportKey,
  format: ExportFormat,
  params: Record<string, unknown> = {},
): Promise<string> {
  const spec = ADMIN_EXPORTS[key]
  const query = toQueryString({ ...params, format })

  await downloadFile(`${spec.path}${query}`, `${spec.filename}.${format}`)

  return `${spec.filename}.${format}`
}

/* ------------------------------------------------------------------ */
/* Planning overview (read-only GET /planning)                         */
/* ------------------------------------------------------------------ */

export interface AcademicYearItem {
  id: number
  name: string
  start_date: string
  end_date: string
  status: string
}

export interface AnnualPlanItem {
  id: number
  academic_year_id: number
  department_id: number | null
  title: string
  description: string | null
  status: string
  approved_at: string | null
  department_plans_count: number
  employee_targets_count: number
  department: { id: number; name: string } | null
  academic_year: { id: number; name: string } | null
}

export interface DepartmentPlanItem {
  id: number
  annual_plan_id: number
  department_id: number | null
  title: string
  status: string
  approved_at: string | null
  department: { id: number; name: string } | null
  annual_plan: { id: number; title: string } | null
}

export interface EmployeeTargetItem {
  id: number
  annual_plan_id: number
  employee_id: number | null
  title: string
  target_value: string
  target_unit: string
  start_date: string
  end_date: string
  status: string
  tasks_count: number
  employee_name: string | null
  employee_number: string | null
  department_name: string | null
}

export interface PlanningOverviewData {
  summary: {
    academic_years: number
    annual_plans: number
    annual_plans_by_status: Record<string, number>
    department_plans: number
    employee_targets: number
    employee_targets_by_status: Record<string, number>
  }
  academic_years: AcademicYearItem[]
  annual_plans: AnnualPlanItem[]
  department_plans: DepartmentPlanItem[]
  employee_targets: EmployeeTargetItem[]
}

export const getPlanningOverview = () => apiRequest<PlanningOverviewData>('/planning')

/* ------------------------------------------------------------------ */
/* Settings — read-only RBAC matrix (GET /admin/rbac)                  */
/* ------------------------------------------------------------------ */

export interface RbacRoleMatrix {
  label: string
  permissions: string[]
}

export interface RbacMatrixData {
  roles: Record<string, RbacRoleMatrix>
  catalog: Record<string, Record<string, string>>
}

export const getRbacMatrix = () => apiRequest<RbacMatrixData>(`${BASE}/rbac`)
