import { apiRequest, toQueryString } from './api'

const BASE = '/admin'

export interface AdminExecutiveDashboardData {
  institution: {
    name: string
    system_name: string
    academic_year: string
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
  ai_executive_summary: {
    text: string
    key_accomplishments: string[]
    areas_needing_attention: string[]
    recommendations: string[]
    disclaimer: string
  }
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

export interface AdminAiStatusData {
  health: {
    status: string
    success_rate: number
    average_time_seconds: number
    total_pipelines: number
  }
  jobs: {
    queued: number
    processing: number
    completed: number
    failed: number
  }
  pipelines: Array<{
    name: string
    status: string
    success_rate: number
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

export interface AdminEscalationItem {
  id: number
  title: string
  department_name: string
  type: string
  reason: string
  priority: string
  status: string
  resolution_notes: string | null
  created_by_name: string
  resolver_name: string | null
  created_at: string
  resolved_at: string | null
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

export const getAdminAiStatus = () =>
  apiRequest<AdminAiStatusData>(`${BASE}/ai/status`)

export const getAdminOcrList = () =>
  apiRequest<AdminOcrItem[]>(`${BASE}/ai/ocr`)

export const retryAdminAiJob = (jobId: number | string) =>
  apiRequest<{ job_id: number; status: string }>(`${BASE}/ai/retry/${jobId}`, {
    method: 'POST',
  })

export const getAdminEscalations = (params: Record<string, unknown> = {}) =>
  apiRequest<AdminEscalationItem[]>(`${BASE}/escalations${toQueryString(params)}`)

export const resolveAdminEscalation = (escalationId: number | string, payload: {
  status: 'under_review' | 'resolved' | 'closed'
  resolution_notes: string
}) => apiRequest<{ id: number; status: string }>(`${BASE}/escalations/${escalationId}/resolve`, {
  method: 'POST',
  body: payload,
})