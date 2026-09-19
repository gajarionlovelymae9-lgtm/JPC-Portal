import { apiRequest, toQueryString } from './api'

const BASE = '/department-head'

// â”€â”€â”€ Interfaces â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export interface DepartmentDashboardData {
  department: {
    id: number
    name: string
    code: string
    academic_year: string
    department_head: string
  }
  kpis: {
    total_employees: number
    active_employees: number
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
  }
  target: {
    id: number
    name: string
    target: number
    completed: number
    remaining: number
    progress_percent: number
    is_at_risk: boolean
  } | null
  overdue_alerts: Array<{
    assignment_id: number
    task_id: number
    task_title: string
    employee_name: string
    deadline: string | null
    days_overdue: number
  }>
  pending_review_alerts: Array<{
    id: number
    title: string
    task_title: string
    employee_name: string
    submitted_at: string | null
    ai_status: string
    status: string
  }>
  revision_alerts: Array<{
    id: number
    title: string
    task_title: string
    employee_name: string
    updated_at: string | null
  }>
  employee_summary: Array<{
    id: number
    employee_number: string
    name: string
    position: string
    status: string
    tasks_total: number
    tasks_completed: number
    tasks_pending: number
    tasks_overdue: number
    reports_count: number
    completion_rate: number
  }>
  ai_summary: {
    text: string
    disclaimer: string
  }
}

export interface DepartmentEmployee {
  id: number
  employee_number: string
  first_name: string
  last_name: string
  full_name: string
  position: string
  employment_status: string
  email: string | null
  account_status: string
  tasks_count: number
  completed_tasks_count: number
  reports_count: number
  completion_rate: number
}

export interface DepartmentEmployeeDetail {
  id: number
  employee_number: string
  first_name: string
  last_name: string
  full_name: string
  department_name: string | null
  position_name: string
  employment_status: string
  employment_type: string
  date_hired: string | null
  email: string | null
  account_status: string
  last_login_at: string | null
  metrics: {
    total_tasks: number
    completed_tasks: number
    pending_tasks: number
    overdue_tasks: number
    reports_submitted: number
    completion_rate: number
  }
  assignments: Array<{
    id: number
    task_id: number
    task_title: string
    priority: string
    status: string
    progress: number
    due_date: string | null
    submitted_at: string | null
  }>
  reports: Array<{
    id: number
    title: string
    task_title: string
    status: string
    submitted_at: string | null
  }>
}

export interface DepartmentTask {
  id: number
  title: string
  task_code: string
  description: string | null
  priority: 'low' | 'medium' | 'high' | 'urgent'
  status: string
  start_date: string | null
  deadline: string | null
  is_overdue: boolean
  average_progress: number
  total_assigned: number
  completed_assigned: number
  assignments: Array<{
    id: number
    employee_id: number
    employee_name: string
    employee_number?: string
    progress: number
    status: string
    submitted_at: string | null
  }>
  created_at: string | null
}

export interface DepartmentTaskDetail {
  id: number
  title: string
  task_code: string
  description: string | null
  priority: string
  status: string
  start_date: string | null
  deadline: string | null
  requirements: string | null
  instructions: string | null
  department_name: string | null
  created_by_name: string | null
  assignments: Array<{
    id: number
    employee_id: number
    employee_name: string
    employee_number?: string
    position: string
    progress: number
    status: string
    due_date: string | null
    submitted_at: string | null
    notes: string | null
  }>
}

export interface DepartmentReportItem {
  id: number
  title: string
  version: number
  status: string
  ai_status: string
  task_id: number
  task_title: string
  employee_id: number
  employee_name: string
  employee_number?: string
  submitted_at: string | null
  deadline: string | null
  is_late: boolean
  reviews_count: number
  latest_reviewer?: string | null
}

export interface DepartmentReportDetail {
  id: number
  title: string
  version: number
  status: string
  content: string | null
  references: string | null
  is_late: boolean
  submitted_at: string | null
  approved_at: string | null
  employee: {
    id: number
    name: string
    employee_number: string
    position: string
  }
  task: {
    id: number
    title: string
    description: string | null
    deadline: string | null
    requirements: string | null
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
    confidence_label: string
    warning: string | null
  }
  ai_summary: {
    executive_summary: string
    key_points: string[]
    accomplishments: string[]
    issues: string[]
    disclaimer: string
  }
  ai_validation: {
    requirement_checks: Array<{
      requirement: string
      status: string
      note: string | null
    }>
    reference_results: Array<{
      reference: string
      status: string
    }>
    similarity: {
      result: string
      label: string
      note: string
    }
    flags: string[]
    advisory_label: string
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

export interface DepartmentTarget {
  id: number
  name: string
  description: string | null
  academic_year: string
  target: number
  completed: number
  remaining: number
  progress_percent: number
  is_at_risk: boolean
  status: string
  deadline: string
}

export interface DepartmentPerformanceData {
  department_name: string
  summary: {
    completion_rate: number
    on_time_rate: number
    approval_rate: number
    target_achievement: number
  }
  employee_comparison: Array<{
    id: number
    name: string
    employee_number: string
    position: string
    tasks_count: number
    completed_count: number
    completion_rate: number
    on_time_rate: number
  }>
  monthly_trends: Array<{
    month: string
    completion_rate: number
    on_time_rate: number
    approval_rate: number
  }>
  ai_summary: {
    text: string
    disclaimer: string
  }
}

export interface DepartmentAchievementsData {
  top_performers: Array<{
    employee_id: number
    name: string
    position: string
    completed_tasks: number
    completion_rate: number
    badge: string
    icon: string
  }>
  badges: Array<{
    title: string
    description: string
    icon: string
    criteria: string
  }>
}

export interface DepartmentEscalation {
  id: number
  title: string
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

// â”€â”€â”€ API Methods â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

export const getDepartmentDashboard = (params: Record<string, unknown> = {}) =>
  apiRequest<DepartmentDashboardData>(`${BASE}/dashboard${toQueryString(params)}`)

export const getDepartmentEmployees = (params: Record<string, unknown> = {}) =>
  apiRequest<DepartmentEmployee[]>(`${BASE}/employees${toQueryString(params)}`)

export const getDepartmentEmployee = (employeeId: number | string) =>
  apiRequest<DepartmentEmployeeDetail>(`${BASE}/employees/${employeeId}`)

export const getDepartmentTasks = (params: Record<string, unknown> = {}) =>
  apiRequest<DepartmentTask[]>(`${BASE}/tasks${toQueryString(params)}`)

export const createDepartmentTask = (payload: {
  title: string
  description?: string
  priority?: string
  start_date?: string
  deadline?: string
  requirements?: string
  instructions?: string
  assigned_employee_ids?: number[]
  department_id?: number
}) => apiRequest<{ id: number; title: string; status: string }>(`${BASE}/tasks`, {
  method: 'POST',
  body: payload,
})

export const getDepartmentTask = (taskId: number | string) =>
  apiRequest<DepartmentTaskDetail>(`${BASE}/tasks/${taskId}`)

export const updateDepartmentTask = (taskId: number | string, payload: Record<string, unknown>) =>
  apiRequest<{ id: number; title: string; status: string }>(`${BASE}/tasks/${taskId}`, {
    method: 'PATCH',
    body: payload,
  })

export const assignDepartmentTaskEmployees = (taskId: number | string, employeeIds: number[]) =>
  apiRequest<{ assigned_count: number }>(`${BASE}/tasks/${taskId}/assign`, {
    method: 'POST',
    body: { employee_ids: employeeIds },
  })

export const cancelDepartmentTask = (taskId: number | string, reason: string) =>
  apiRequest<{ id: number; status: string; reason: string }>(`${BASE}/tasks/${taskId}/cancel`, {
    method: 'POST',
    body: { reason },
  })

export const getDepartmentReports = (params: Record<string, unknown> = {}) =>
  apiRequest<DepartmentReportItem[]>(`${BASE}/reports${toQueryString(params)}`)

export const getDepartmentReport = (reportId: number | string) =>
  apiRequest<DepartmentReportDetail>(`${BASE}/reports/${reportId}`)

export const reviewDepartmentReport = (reportId: number | string, payload: {
  decision: 'approved' | 'revision_required' | 'rejected' | 'approve' | 'revision' | 'reject'
  comments?: string
}) => apiRequest<{ id: number; status: string; decision: string }>(`${BASE}/reports/${reportId}/review`, {
  method: 'POST',
  body: payload,
})

export const getDepartmentTargets = (params: Record<string, unknown> = {}) =>
  apiRequest<DepartmentTarget[]>(`${BASE}/targets${toQueryString(params)}`)

export const getDepartmentPerformance = (params: Record<string, unknown> = {}) =>
  apiRequest<DepartmentPerformanceData>(`${BASE}/performance${toQueryString(params)}`)

export const getDepartmentAchievements = (params: Record<string, unknown> = {}) =>
  apiRequest<DepartmentAchievementsData>(`${BASE}/achievements${toQueryString(params)}`)

export const getDepartmentEscalations = (params: Record<string, unknown> = {}) =>
  apiRequest<DepartmentEscalation[]>(`${BASE}/escalations${toQueryString(params)}`)

export const createDepartmentEscalation = (payload: {
  title: string
  type?: string
  reason: string
  priority?: string
  department_id?: number
}) => apiRequest<{ id: number; title: string; status: string }>(`${BASE}/escalations`, {
  method: 'POST',
  body: payload,
})