import { apiRequest, toQueryString } from './api'

const BASE = '/employee'

/**
 * Employee portal API client.
 *
 * These types mirror `App\Services\Employee\EmployeePortalService` exactly.
 * Percentages are `number | null` — a null means "no data to measure yet",
 * which the UI renders as an em dash rather than a misleading 0%.
 */

export type Urgency = 'overdue' | 'today' | 'tomorrow' | 'soon' | 'later' | 'waiting' | 'done' | 'none'

export type ReportAction = 'edit' | 'revise' | null

export interface EmployeeDashboardEmployee {
  id: number
  name: string
  email: string | null
  employee_no: string | null
  department: string | null
  position: string | null
}

export interface EmployeeDashboardKpis {
  total_tasks: number
  completed_tasks: number
  pending_tasks: number
  in_progress_tasks: number
  submitted_tasks: number
  overdue_tasks: number
  completion_rate: number | null
  total_reports: number
  submitted_reports: number
  approved_reports: number
  draft_reports: number
  revision_required: number
  late_reports: number
  pending_reports: number
  approval_rate: number | null
  report_completion_rate: number | null
  on_time_rate: number | null
}

export interface EmployeeDashboardTask {
  assignment_id: number
  task_id: number
  title: string
  description: string | null
  priority: string | null
  status: string
  latest_review_decision?: string | null
  progress: number
  deadline: string | null
  deadline_date: string | null
  assigned_at: string | null
  is_overdue: boolean
  urgency: Urgency
}

export interface EmployeeDashboardReport {
  id: number
  title: string
  task_id: number
  task_title: string | null
  status: string
  review_status: string | null
  submitted_at: string | null
  deadline: string | null
  is_late: boolean
  needs_action: boolean
  action: ReportAction
  urgency: Urgency
}

export interface EmployeeDeadline {
  type: 'task' | 'report'
  id: number
  task_id?: number
  title: string
  deadline: string | null
  deadline_date: string | null
  days_remaining: number | null
  status: string
  urgency: Urgency
}

export interface EmployeeOverdueTask {
  type: 'task'
  id: number
  task_id: number
  title: string
  deadline: string | null
  days_overdue: number
  status: string
  urgency: 'overdue'
}

export interface EmployeeOverdueReport {
  type: 'report'
  id: number
  title: string
  deadline: string | null
  status: string
  urgency: 'overdue'
  action: ReportAction
}

export interface EmployeeActionRequired {
  kind: 'task' | 'report'
  id: number
  title: string
  reason: string
  action: 'view' | ReportAction
  action_label: string
  severity: 'high' | 'medium' | 'low'
}

export interface EmployeeMonthlyProgress {
  year: number
  month: number
  label: string
  tasks_assigned: number
  tasks_completed: number
  reports_submitted: number
  reports_approved: number
  late_submissions: number
  on_time_submissions: number
  completion_rate: number | null
}

export interface EmployeeAnnualTarget {
  year: number
  target: number
  completed: number
  progress_percent: number | null
  label: string
}

export type EmployeeTargetProgressSource = 'breakdowns' | 'tasks' | 'none'
export type EmployeeTargetDerivedStatus = 'completed' | 'in_progress' | 'missed' | 'not_started'
export type EmployeeTargetBreakdownStatus = 'pending' | 'in_progress' | 'completed'
export type EmployeeTargetMilestoneStatus = 'pending' | 'achieved'
export type EmployeeTargetAdjustmentStatus = 'pending' | 'approved' | 'rejected'
export type EmployeeTargetAdjustmentType =
  | 'target_value'
  | 'deadline'
  | 'period'
  | 'description'
  | 'other'

export interface EmployeeTargetItem {
  id: number
  title: string | null
  description: string | null
  /** Legacy alias of `target_unit`, kept for the dashboard widget. */
  metric: string | null
  target_value: number | null
  /** Derived actual: completed sub-targets (breakdowns) or linked completed tasks. */
  actual_value: number
  target_unit: string | null
  progress: number | null
  status: 'achieved' | 'in_progress'
  /** Richer status derived from progress and the deadline. */
  derived_status: EmployeeTargetDerivedStatus
  progress_source: EmployeeTargetProgressSource
  start_date: string | null
  end_date: string | null
  breakdowns_count: number
  breakdowns_completed: number
  milestones_count: number
  milestones_achieved: number
  tasks_total: number
  tasks_completed: number
  notes_count: number
  pending_adjustments: number
}

export interface EmployeeTargetBreakdown {
  id: number
  employee_target_id: number
  title: string
  description: string | null
  target_value: number | null
  target_unit: string | null
  progress: number
  status: EmployeeTargetBreakdownStatus
  completed_at: string | null
  sort_order: number
}

export interface EmployeeTargetMilestone {
  id: number
  employee_target_id: number
  title: string
  description: string | null
  threshold_percent: number
  status: EmployeeTargetMilestoneStatus
  achieved_at: string | null
  sort_order: number
}

export interface EmployeeTargetNote {
  id: number
  employee_target_id: number
  employee_id: number
  author: string | null
  body: string
  created_at: string | null
  updated_at: string | null
}

export interface EmployeeTargetAdjustment {
  id: number
  employee_target_id: number
  employee_id: number
  target_title: string | null
  employee_name: string | null
  employee_number: string | null
  department_name: string | null
  reason: string
  request_type: EmployeeTargetAdjustmentType | null
  proposed: {
    target_value: number | null
    target_unit: string | null
    start_date: string | null
    end_date: string | null
    description: string | null
  }
  status: EmployeeTargetAdjustmentStatus
  decision_note: string | null
  reviewed_by: string | null
  reviewed_at: string | null
  applied_at: string | null
  original_snapshot: Record<string, unknown> | null
  requested_by: string | null
  created_at: string | null
}

export interface EmployeeTargetDetail {
  target: EmployeeTargetItem & {
    employee_name?: string | null
    employee_number?: string | null
    department_name?: string | null
  }
  breakdowns: EmployeeTargetBreakdown[]
  milestones: EmployeeTargetMilestone[]
  notes: EmployeeTargetNote[]
  adjustments: EmployeeTargetAdjustment[]
}

export interface EmployeeTargetSuggestion {
  key: string
  target_id: number
  target_title: string | null
  title: string
  detail: string
  severity: 'low' | 'medium' | 'high'
  evidence: Record<string, number | string>
}

export interface EmployeeTargetSuggestionsPayload {
  available: boolean
  targets_analyzed: number
  suggestions: EmployeeTargetSuggestion[]
  generated_at: string
  disclaimer: string
  no_data_reason: string | null
}

export interface EmployeeTargetSummary {
  total: number
  completed: number
  pending: number
}

export interface EmployeeTargetsPayload {
  items: EmployeeTargetItem[]
  summary: EmployeeTargetSummary
  annual: EmployeeAnnualTarget
}

export interface EmployeePerformanceMetric {
  label: string
  value: number | null
  source: 'ai' | 'system'
}

export interface EmployeePerformancePayload {
  score: unknown
  metrics: Record<string, EmployeePerformanceMetric>
  available: boolean
  disclaimer: string
}

export interface EmployeeAchievementItem {
  id: number
  name: string
  description: string | null
  icon: string | null
  criteria: string | null
  earned_at: string | null
}

export interface EmployeeMonthlyAchievement {
  year: number | null
  month: number | null
  month_name: string | null
  completion: string | number | null
  on_time: string | number | null
  rank: number | null
  badge: string | null
  achievement: string | null
}

export interface EmployeeAchievementsPayload {
  items: EmployeeAchievementItem[]
  badges: EmployeeAchievementItem[]
  monthly: EmployeeMonthlyAchievement[]
  total: number
}

export interface EmployeeScheduleEvent {
  id: number
  type: 'task' | 'report'
  title: string
  date: string
  status: string
  is_overdue: boolean
  link: string
}

export interface EmployeeNotification {
  id: number
  type: string | null
  title: string
  message: string | null
  is_read: boolean
  read_at: string | null
  created_at: string | null
}

export interface EmployeeDashboard {
  employee: EmployeeDashboardEmployee
  generated_at: string
  kpis: EmployeeDashboardKpis
  progress: { completed: number; total: number; percent: number | null }
  tasks: EmployeeDashboardTask[]
  reports: EmployeeDashboardReport[]
  action_required: EmployeeActionRequired[]
  deadlines: EmployeeDeadline[]
  overdue: EmployeeOverdueTask[]
  overdue_reports: EmployeeOverdueReport[]
  monthly_progress: EmployeeMonthlyProgress
  annual_target: EmployeeAnnualTarget
  targets: EmployeeTargetsPayload
  performance: EmployeePerformancePayload
  achievements: EmployeeAchievementsPayload
  schedule: EmployeeScheduleEvent[]
  recent_notifications: EmployeeNotification[]
  unread_notifications: number
  caught_up: boolean
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface EmployeePortalTask {
  id: number
  task_id: number
  title: string | null
  description: string | null
  priority: string | null
  assigned_date: string | null
  deadline: string | null
  progress: number
  status: string
  latest_review_decision?: string | null
  is_overdue: boolean
  is_late: boolean
  submitted_at: string | null
  completed_at?: string | null
  requirements?: string | null
  instructions?: string | null
  target?: unknown
  notes?: string | null
  late_reason?: string | null
  reviews?: Array<{
    reviewer: string | null
    decision: string | null
    comments: string | null
    reviewed_at: string | null
  }>
}

export interface EmployeeTaskListPayload {
  tasks: EmployeePortalTask[]
  meta: PaginationMeta
  summary: {
    total_tasks: number
    completed_tasks: number
    pending_tasks: number
    in_progress_tasks: number
    submitted_tasks: number
    overdue_tasks: number
    completion_rate: number | null
  }
}

export interface AiRequirementCheck {
  requirement: string
  status: 'found' | 'needs_review' | 'missing'
  note: string | null
}

export interface AiReferenceResult {
  reference: string
  status: 'referenced' | 'partially_referenced' | 'not_found' | 'needs_review'
}

export interface ReviewHistoryItem {
  reviewer: string | null
  decision: string | null
  comments: string | null
  date: string | null
}

export interface ReportVersion {
  version: number
  status: string | null
  submitted_at: string | null
  created_at: string | null
  is_current: boolean
}

export interface EmployeeFile {
  id: number
  file_name: string
  file_size: number | null
  mime_type: string | null
  uploaded_at: string | null
  download_url?: string | null
}

export interface EmployeeReportListItem {
  id: number
  title: string
  report_type?: string | null
  reporting_period?: string | null
  task_id: number
  task_title: string | null
  submitted_at: string | null
  created_at?: string | null
  updated_at: string | null
  status: string
  version: number
  is_late: boolean
  ai_status: string | null
  ai_summary_status?: string | null
  ai_summary_generated_at?: string | null
  review_status: string | null
  is_revision: boolean
  advisory_label: string
  disclaimer: string
}

export interface EmployeeReportAiSummary {
  status?: string | null
  executive_summary?: string | null
  summary_of_accomplishments?: string[]
  completed_tasks_summary?: string[]
  challenges_encountered?: string[]
  recommendations?: string[]
  next_steps?: string[]
  key_points?: string[]
  accomplishments?: string[]
  issues?: string[]
  generated_at?: string | null
  disclaimer?: string
  message?: string | null
}

export interface EmployeeReportFeedbackItem {
  id?: number
  reviewer?: string | null
  section?: string | null
  comment?: string | null
  decision?: string | null
  comments?: string | null
  date?: string | null
  is_resolved?: boolean
  created_at?: string | null
}

export interface EmployeeReportTimelineItem {
  id?: number
  event_type?: string | null
  description?: string | null
  actor?: string | null
  created_at?: string | null
  metadata?: unknown
}

export interface EmployeeReport extends EmployeeReportListItem {
  approved_at?: string | null
  rejected_at?: string | null
  deadline?: string | null
  submission_date?: string | null
  report_description?: string | null
  content?: string | null
  accomplishments?: string | null
  completed_tasks?: string | null
  completed_tasks_summary?: string | null
  challenges_encountered?: string | null
  next_plans?: string | null
  references?: string | null
  files?: EmployeeFile[]
  ocr?: {
    status: string
    extracted_text: string | null
    confidence: number | null
    confidence_label: string | null
    warning: string | null
  } | null
  ai_summary?: EmployeeReportAiSummary | null
  ai_validation?: {
    requirement_checks: AiRequirementCheck[]
    reference_results: AiReferenceResult[]
    flags: string[]
    advisory_label: string
    disclaimer: string
  } | null
  review_history?: ReviewHistoryItem[]
  reviewer_feedback?: EmployeeReportFeedbackItem[]
  comments?: EmployeeReportFeedbackItem[]
  timeline?: EmployeeReportTimelineItem[]
  revision_history?: (ReportVersion & { change_summary?: string | null })[]
  versions?: ReportVersion[]
  version_count?: number
}

export interface EmployeeReportListPayload {
  reports: EmployeeReportListItem[]
  meta: PaginationMeta
}

export type EmployeeCalendarEventType = 'task' | 'report' | 'announcement'

export interface EmployeeCalendarEvent {
  id: number
  type: EmployeeCalendarEventType
  /** Display label for the event category (Task, Report, Holiday, Event…). */
  category: string
  title: string
  description: string | null
  date: string
  time: string | null
  status: string
  is_overdue: boolean
  /** Explicit deadline, when the source record exposes one separately. */
  deadline?: string | null
  /** Person the record is assigned to, when the API provides it. */
  assigned_person?: string | null
  /** Internal route to the related record; null when there is no page to open. */
  link: string | null
}

export interface EmployeeCalendarPayload {
  events: EmployeeCalendarEvent[]
}

export interface EmployeePerformanceSummary {
  performance: unknown
  completion_rate: number | null
  on_time_rate: number | null
  approval_rate: number | null
  disclaimer: string
}

export interface EmployeeNotificationsPayload {
  notifications: EmployeeNotification[]
  total: number
}

export interface EmployeeProfile {
  name: string
  email: string
  employee_no: string | null
  department: string | null
  position: string | null
  employee_type: string | null
  status: string | null
}

/** Backwards-compatible aliases used by the existing employee pages. */
export type EmployeeTarget = EmployeeTargetItem
export type EmployeeAchievement = EmployeeAchievementItem

export interface CreateReportPayload {
  task_id: number
  title: string
  report_type?: string
  reporting_period?: string
  report_description?: string
  content?: string
  accomplishments?: string
  completed_tasks?: string
  challenges_encountered?: string
  next_plans?: string
  references?: string
  attachments?: File[]
}

export interface UpdateReportPayload {
  task_id?: number
  title?: string
  report_type?: string
  reporting_period?: string
  report_description?: string
  content?: string
  accomplishments?: string
  completed_tasks?: string
  challenges_encountered?: string
  next_plans?: string
  references?: string
  attachments?: File[]
}

export interface CreateRevisionPayload {
  revision_notes?: string
  title?: string
  content?: string
  report_type?: string
  reporting_period?: string
  accomplishments?: string
  completed_tasks?: string
  challenges_encountered?: string
  next_plans?: string
}

export interface ReportSummaryResult {
  report_id: number
  ai_summary_status: string
  ai_summary_generated_at?: string | null
  summary?: EmployeeReportAiSummary | null
  regenerated?: boolean
  message?: string | null
}

export const getEmployeeDashboard = (p: { year?: number; month?: number } = {}) =>
  apiRequest<EmployeeDashboard>(BASE + '/dashboard' + toQueryString(p))

export const getMyTasks = (p: Record<string, unknown> = {}) =>
  apiRequest<EmployeeTaskListPayload>(BASE + '/tasks' + toQueryString(p))

export const getMyTask = (id: number | string) =>
  apiRequest<EmployeePortalTask>(BASE + '/tasks/' + id)

export const updateMyTaskProgress = (id: number | string, progress: number) =>
  apiRequest<EmployeePortalTask>(BASE + '/tasks/' + id + '/progress', {
    method: 'POST',
    body: { progress },
  })

export const submitMyTask = (
  id: number | string,
  payload: { notes?: string; late_reason?: string },
) =>
  apiRequest<EmployeePortalTask>(BASE + '/tasks/' + id + '/submit', {
    method: 'POST',
    body: payload,
  })

const reportBody = (payload: Record<string, unknown>): FormData | Record<string, unknown> => {
  const files = Array.isArray(payload.attachments) ? (payload.attachments as File[]) : []
  if (files.length === 0) {
    const { attachments: _attachments, ...rest } = payload
    return rest
  }
  const form = new FormData()
  for (const [key, value] of Object.entries(payload)) {
    if (key === 'attachments') continue
    if (value === undefined || value === null) continue
    form.append(key, String(value))
  }
  files.forEach((file) => form.append('attachments[]', file))
  return form
}

export const getMyReports = (p: Record<string, unknown> = {}) =>
  apiRequest<EmployeeReportListPayload>(BASE + '/reports' + toQueryString(p))

export const getMyReport = (id: number | string) =>
  apiRequest<EmployeeReport>(BASE + '/reports/' + id)

export const createMyReport = (payload: CreateReportPayload) =>
  apiRequest<{ report: EmployeeReport }>(BASE + '/reports', {
    method: 'POST',
    body: reportBody(payload as unknown as Record<string, unknown>),
  })

export const updateMyReport = (id: number | string, payload: UpdateReportPayload) =>
  apiRequest<{ report: EmployeeReport }>(BASE + '/reports/' + id, {
    method: 'PUT',
    body: reportBody(payload as unknown as Record<string, unknown>),
  })

export const submitMyReport = (id: number | string, payload: UpdateReportPayload = {}) =>
  apiRequest<{ report: EmployeeReport; ai_summary?: ReportSummaryResult }>(BASE + '/reports/' + id + '/submit', {
    method: 'POST',
    body: Object.keys(payload).length ? reportBody(payload as unknown as Record<string, unknown>) : undefined,
  })

export const generateMyReportSummary = (id: number | string, regenerate = false) =>
  apiRequest<ReportSummaryResult>(BASE + '/reports/' + id + '/ai-summary', {
    method: 'POST',
    body: { regenerate },
  })

export const createMyReportRevision = (id: number | string, payload: CreateRevisionPayload) =>
  apiRequest<{ report: EmployeeReport }>(BASE + '/reports/' + id + '/revision', {
    method: 'POST',
    body: payload,
  })

export const downloadMyReportFile = (reportId: number | string, fileId: number | string) =>
  `${import.meta.env.VITE_API_BASE_URL ?? '/api/v1'}${BASE}/reports/${reportId}/files/${fileId}`

export const getMyCalendar = (p: { month?: number; year?: number } = {}) =>
  apiRequest<EmployeeCalendarPayload>(BASE + '/calendar' + toQueryString(p))

export const getMyPerformanceSummary = () =>
  apiRequest<EmployeePerformanceSummary>(BASE + '/performance')

export const getMyTargets = () => apiRequest<EmployeeTargetsPayload>(BASE + '/targets')

export const getMyTargetSuggestions = () =>
  apiRequest<EmployeeTargetSuggestionsPayload>(BASE + '/targets/suggestions')

export const getMyTarget = (targetId: number | string) =>
  apiRequest<EmployeeTargetDetail>(BASE + '/targets/' + targetId)

/* ---------------------- Target breakdown (sub-targets) ------------------- */

export interface BreakdownPayload {
  title: string
  description?: string | null
  target_value?: number | null
  target_unit?: string | null
  progress?: number
  sort_order?: number
}

export const createMyTargetBreakdown = (targetId: number | string, payload: BreakdownPayload) =>
  apiRequest<EmployeeTargetBreakdown>(`${BASE}/targets/${targetId}/breakdowns`, {
    method: 'POST',
    body: payload,
  })

export const updateMyTargetBreakdown = (
  targetId: number | string,
  breakdownId: number | string,
  payload: Partial<BreakdownPayload>,
) =>
  apiRequest<EmployeeTargetBreakdown>(`${BASE}/targets/${targetId}/breakdowns/${breakdownId}`, {
    method: 'PUT',
    body: payload,
  })

export const deleteMyTargetBreakdown = (targetId: number | string, breakdownId: number | string) =>
  apiRequest<null>(`${BASE}/targets/${targetId}/breakdowns/${breakdownId}`, { method: 'DELETE' })

/* --------------------------- Target milestones -------------------------- */

export interface MilestonePayload {
  title: string
  description?: string | null
  threshold_percent: number
  sort_order?: number
}

export const createMyTargetMilestone = (targetId: number | string, payload: MilestonePayload) =>
  apiRequest<EmployeeTargetMilestone>(`${BASE}/targets/${targetId}/milestones`, {
    method: 'POST',
    body: payload,
  })

export const updateMyTargetMilestone = (
  targetId: number | string,
  milestoneId: number | string,
  payload: Partial<MilestonePayload>,
) =>
  apiRequest<EmployeeTargetMilestone>(`${BASE}/targets/${targetId}/milestones/${milestoneId}`, {
    method: 'PUT',
    body: payload,
  })

export const deleteMyTargetMilestone = (targetId: number | string, milestoneId: number | string) =>
  apiRequest<null>(`${BASE}/targets/${targetId}/milestones/${milestoneId}`, { method: 'DELETE' })

/* ----------------------- Target adjustment requests --------------------- */

export interface AdjustmentRequestPayload {
  reason: string
  request_type?: EmployeeTargetAdjustmentType
  proposed_target_value?: number | null
  proposed_target_unit?: string | null
  proposed_start_date?: string | null
  proposed_end_date?: string | null
  proposed_description?: string | null
}

export const getMyTargetAdjustments = (targetId: number | string) =>
  apiRequest<EmployeeTargetAdjustment[]>(`${BASE}/targets/${targetId}/adjustments`)

export const createMyTargetAdjustment = (
  targetId: number | string,
  payload: AdjustmentRequestPayload,
) =>
  apiRequest<EmployeeTargetAdjustment>(`${BASE}/targets/${targetId}/adjustments`, {
    method: 'POST',
    body: payload,
  })

/* --------------------- Target achievement notes ------------------------- */

export const getMyTargetNotes = (targetId: number | string) =>
  apiRequest<EmployeeTargetNote[]>(`${BASE}/targets/${targetId}/notes`)

export const createMyTargetNote = (targetId: number | string, body: string) =>
  apiRequest<EmployeeTargetNote>(`${BASE}/targets/${targetId}/notes`, {
    method: 'POST',
    body: { body },
  })

export const updateMyTargetNote = (
  targetId: number | string,
  noteId: number | string,
  body: string,
) =>
  apiRequest<EmployeeTargetNote>(`${BASE}/targets/${targetId}/notes/${noteId}`, {
    method: 'PUT',
    body: { body },
  })

export const deleteMyTargetNote = (targetId: number | string, noteId: number | string) =>
  apiRequest<null>(`${BASE}/targets/${targetId}/notes/${noteId}`, { method: 'DELETE' })

export const getMyAchievements = () =>
  apiRequest<EmployeeAchievementsPayload>(BASE + '/achievements')

export const getMyNotifications = () =>
  apiRequest<EmployeeNotificationsPayload>(BASE + '/notifications')

export const getMyProfile = () => apiRequest<EmployeeProfile>(BASE + '/profile')

export const updateMyProfile = (payload: { email?: string }) =>
  apiRequest<EmployeeProfile>(BASE + '/profile', { method: 'PUT', body: payload })

export const changeMyPassword = (payload: {
  current_password: string
  password: string
  password_confirmation: string
}) => apiRequest<null>(BASE + '/security/password', { method: 'PUT', body: payload })

/**
 * AI Insights — advisory analytics derived only from the signed-in employee's
 * own records. Mirrors `App\Services\Employee\EmployeeAIInsightsService`.
 *
 * Every `value` here is `number | null`. A null means "not measurable from real
 * data" and is rendered as "No data", never as 0%, so the UI can never imply a
 * measurement that was not made.
 */

export interface EmployeeAiInsightsEmployee {
  name: string | null
  department: string | null
  position: string | null
}

export interface EmployeeAiInsightsPeriod {
  year: number
  month: number
  label: string
  start: string
  end: string
}

export interface EmployeeAiScore {
  value: number | null
  label: string | null
  color: string | null
  basis: string | null
  metrics_used: number
  metrics_excluded: number
}

export interface EmployeeAiMetric {
  key: string
  label: string
  value: number | null
  unit: string
  weight: number
  available: boolean
  basis: string
  unavailable_reason: string | null
}

export interface EmployeeAiAlert {
  key: string
  severity: 'low' | 'medium' | 'high'
  title: string
  detail: string
  evidence: Record<string, number | string>
}

export interface EmployeeAiRecommendation {
  key: string
  title: string
  detail: string
  evidence: Record<string, number | string>
}

export interface EmployeeAiTrendPoint {
  period_label: string
  year: number
  month: number
  score: number
  rating: string
}

export interface EmployeeAiProductivityPoint {
  period_label: string
  year: number
  month: number
  rate: number
}

export interface EmployeeAiForecast {
  value: number | null
  confidence: 'low' | 'medium' | 'high' | 'insufficient_data'
  method: string | null
  based_on_months: number
  required_months: number
  reason: string
  needed: string | null
  direction?: string
  average_score?: number
}

export interface EmployeeAiTransparencyMetric {
  key: string
  label: string
  available: boolean
  configured_weight: number
  raw_value: number | null
  applied_weight: number | null
  contribution: number | null
  excluded_reason: string | null
}

export interface EmployeeAiScoreTransparency {
  formula: string
  weights_source: string
  calculation_version: string | null
  basis: string | null
  metrics: EmployeeAiTransparencyMetric[]
  metrics_used: number
  metrics_excluded: number
  available: boolean
}

export interface EmployeeAiInsights {
  employee: EmployeeAiInsightsEmployee
  period: EmployeeAiInsightsPeriod
  generated_at: string
  available: boolean
  no_data_reason: string | null
  score: EmployeeAiScore
  metrics: EmployeeAiMetric[]
  performance_trend: EmployeeAiTrendPoint[]
  productivity_trend: EmployeeAiProductivityPoint[]
  alerts: EmployeeAiAlert[]
  summary: string | null
  recommendations: EmployeeAiRecommendation[]
  forecast: EmployeeAiForecast
  score_transparency: EmployeeAiScoreTransparency
  disclaimer: string
}

export const getMyAiInsights = (p: { year?: number; month?: number } = {}) =>
  apiRequest<EmployeeAiInsights>(BASE + '/ai-insights' + toQueryString(p))
