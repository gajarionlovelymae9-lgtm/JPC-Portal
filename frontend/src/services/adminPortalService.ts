import { apiRequest, toQueryString } from './api'
import type {
  DepartmentEscalationDetail,
  DepartmentEscalationListResult,
  EscalationSummary,
} from './departmentHeadService'

const BASE = '/admin'

export interface AdminExecutiveDashboardData {
  institution: {
    name: string
    system_name: string
    academic_year: string
    academic_year_status: string
    current_term: string
  }
  kpis: {
    total_employees: number
    active_employees: number
    total_departments: number
    total_tasks: number
    completed_tasks: number
    pending_tasks: number
    overdue_tasks: number
    submitted_reports: number
    pending_reviews: number
    approved_reports: number
    revision_required: number
    completion_rate: number
    on_time_rate: number
    approval_rate: number
    institution_progress: number
    in_progress_tasks: number
    not_started_tasks: number
    late_tasks: number
    reports_pending: number
    reports_approved: number
    reports_needs_revision: number
    reports_rejected: number
    reports_under_review: number
    reports_late: number
    avg_kpi_achievement: number
    ai_validation_score: number
    department_performance: number
  }
  status_distribution: {
    tasks: {
      completed: number
      in_progress: number
      pending: number
      overdue: number
    }
    reports: {
      approved: number
      under_review: number
      revision_required: number
      rejected: number
      submitted: number
    }
  }
  department_performance: Array<{
    id: number
    name: string
    code: string
    employees_count: number
    tasks_count: number
    completed_count: number
    overdue_count: number
    reports_count: number
    completion_rate: number
    rank: number
  }>
  monthly_achievements: Array<{
    employee_id: number
    name: string
    department_name: string
    position: string
    completed_tasks: number
    completion_rate: number
    badge: string
    icon: string
  }>
  critical_alerts: {
    overdue_tasks: Array<{
      assignment_id: number
      task_title: string
      employee_name: string
      department_name: string
      days_overdue: number
    }>
    pending_reviews_count: number
    revision_count: number
    open_escalations_count: number
    ocr_failed_count: number
  }
  ai_executive_summary: AdminAiExecutiveSummary
  operational_digest: AdminOperationalDigest
}

/**
 * AI provenance for the executive header.
 *
 * `available` is false whenever there is no real insight row, and
 * `provider_status.live` is false while the configured provider is the
 * `fake` driver. The UI must render the empty state instead of implying
 * that generated analysis exists.
 */
export interface AdminAiExecutiveSummary {
  available: boolean
  generated: boolean
  message: string
  items: AdminAiInsightItem[]
  total: number
  validation_score: number | null
  validation_is_sample_data: boolean
  provider_status: {
    configured_provider: string
    model: string
    is_sample_data: boolean
    live: boolean
    note: string
  }
}

export interface AdminAiInsightItem {
  id: number
  type: string
  title: string
  summary: string
  confidence: number | null
  score: number | null
  employee_id: number | null
  employee_name: string | null
  department_name: string | null
  is_sample_data: boolean
  created_at: string
}

/**
 * Deterministic arithmetic digest. This is NOT AI output and must never be
 * labelled as such in the UI.
 */
export interface AdminOperationalDigest {
  available: boolean
  text: string
  key_accomplishments: string[]
  areas_needing_attention: string[]
  recommendations: string[]
  disclaimer: string
}

export interface AdminDepartmentItem {
  id: number
  name: string
  code: string
  head_name: string
  employees_count: number
  active_employees: number
  tasks_count: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  reports_count: number
  completion_rate: number
  on_time_rate: number
}

export interface AdminDepartmentDetail {
  id: number
  name: string
  code: string
  description: string | null
  head: {
    name: string
    position: string
  }
  metrics: {
    total_employees: number
    active_employees: number
    total_tasks: number
    completed_tasks: number
    pending_tasks: number
    reports_count: number
    completion_rate: number
  }
  target: {
    name: string
    target: number
    completed: number
  } | null
  employees: Array<{
    id: number
    name: string
    employee_number: string
    position: string
    status: string
  }>
}

export interface AdminReportItem {
  id: number
  title: string
  version: number
  status: string
  ai_status: string
  task_title: string
  employee_name: string
  employee_number: string
  department_name: string
  submitted_at: string | null
  deadline: string | null
  is_late: boolean
}

export interface AdminReportDetail {
  id: number
  title: string
  version: number
  status: string
  content: string | null
  references: string | null
  is_late: boolean
  submitted_at: string | null
  employee: {
    id: number
    name: string
    employee_number: string
    department: string
    position: string
  }
  task: {
    id: number
    title: string
    description: string | null
    deadline: string | null
  }
  files: Array<{
    id: number
    name: string
    size: number
    url: string
  }>
  ocr: {
    status: string
    extracted_text: string | null
    confidence: number
  }
  ai_summary: {
    executive_summary: string
    key_points: string[]
    disclaimer: string
  }
  ai_validation: {
    requirement_checks: Array<{
      requirement: string
      status: string
      note: string | null
    }>
    disclaimer: string
  }
  reviews: Array<{
    id: number
    reviewer_name: string
    decision: string
    comments: string | null
    created_at: string
  }>
}

export interface AdminOcrItem {
  id: number
  document_name: string
  report_title: string
  status: string
  confidence: number
  processed_at: string | null
}

// ─── API Methods ────────────────────────────────────────────────────────────

export const getAdminExecutiveDashboard = () =>
  apiRequest<AdminExecutiveDashboardData>(`${BASE}/executive-dashboard`)

export const getAdminDepartments = (params: Record<string, unknown> = {}) =>
  apiRequest<AdminDepartmentItem[]>(`${BASE}/departments${toQueryString(params)}`)

export const getAdminDepartment = (departmentId: number | string) =>
  apiRequest<AdminDepartmentDetail>(`${BASE}/departments/${departmentId}`)

export const getAdminReports = (params: Record<string, unknown> = {}) =>
  apiRequest<AdminReportItem[]>(`${BASE}/reports${toQueryString(params)}`)

export const getAdminReport = (reportId: number | string) =>
  apiRequest<AdminReportDetail>(`${BASE}/reports/${reportId}`)

export const overrideAdminReport = (reportId: number | string, payload: {
  status: 'approved' | 'revision_required' | 'rejected'
  reason: string
}) => apiRequest<{ id: number; status: string; reason: string }>(`${BASE}/reports/${reportId}/override`, {
  method: 'POST',
  body: payload,
})

export const getAdminOcrList = () =>
  apiRequest<AdminOcrItem[]>(`${BASE}/ai/ocr`)

export const retryAdminAiJob = (jobId: number | string) =>
  apiRequest<{ job_id: number; status: string }>(`${BASE}/ai/retry/${jobId}`, {
    method: 'POST',
  })

// ─── Escalations (review workflow) ──────────────────────────────────────────

export interface AdminEscalationOptions {
  administrators: Array<{ id: number; name: string }>
  statuses: Array<{ value: string; label: string; is_terminal: boolean }>
}

export const getAdminEscalations = (params: Record<string, unknown> = {}) =>
  apiRequest<DepartmentEscalationListResult>(`${BASE}/escalations${toQueryString(params)}`)

export const getAdminEscalationSummary = () =>
  apiRequest<EscalationSummary>(`${BASE}/escalations/summary`)

export const getAdminEscalationOptions = () =>
  apiRequest<AdminEscalationOptions>(`${BASE}/escalations/options`)

export const getAdminEscalation = (escalationId: number | string) =>
  apiRequest<DepartmentEscalationDetail>(`${BASE}/escalations/${escalationId}`)

export const assignAdminEscalation = (
  escalationId: number | string,
  payload: { assigned_to: number; note?: string },
) => apiRequest<DepartmentEscalationDetail>(`${BASE}/escalations/${escalationId}/assign`, {
  method: 'POST',
  body: payload,
})

export const updateAdminEscalationStatus = (
  escalationId: number | string,
  payload: { status: string; resolution_notes?: string; internal_note?: string },
) => apiRequest<DepartmentEscalationDetail>(`${BASE}/escalations/${escalationId}/status`, {
  method: 'POST',
  body: payload,
})

export const addAdminEscalationNote = (
  escalationId: number | string,
  payload: { note: string; is_internal?: boolean },
) => apiRequest<DepartmentEscalationDetail>(`${BASE}/escalations/${escalationId}/notes`, {
  method: 'POST',
  body: payload,
})
