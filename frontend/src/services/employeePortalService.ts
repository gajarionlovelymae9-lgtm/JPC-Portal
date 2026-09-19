import { apiRequest, toQueryString } from './api'

const BASE = '/employee'

export interface EmployeeDashboard {
  employee: { name: string; employee_number: string; department_name: string; position: string; email: string; academic_year: string }
  kpis: { assigned_tasks: number; completed_tasks: number; pending_tasks: number; overdue_tasks: number; submitted_reports: number; approved_reports: number; revision_required: number; completion_rate: number; on_time_rate: number; approval_rate: number }
  annual_target: { year: string; target: number; completed: number; progress_percent: number; label: string } | null
  upcoming_deadlines: EmployeeTask[]
  overdue_tasks: EmployeeTask[]
  recent_reports: EmployeeReport[]
  recent_achievements: EmployeeAchievement[]
  recent_notifications: EmployeeNotification[]
  generated_at: string
}
export interface EmployeeTask {
  id: number; title: string; task_code: string; description: string
  priority: 'low' | 'medium' | 'high' | 'critical'; status: string
  deadline: string | null; assigned_date: string | null; requirements: string | null; instructions: string | null
  my_assignment: { id: number; status: string; progress: number; due_date: string | null; submitted_at: string | null; is_overdue: boolean; is_late: boolean } | null
  target: { id: number; name: string } | null
}
export interface AiRequirementCheck { requirement: string; status: 'found' | 'needs_review' | 'missing'; note: string | null }
export interface AiReferenceResult { reference: string; status: 'referenced' | 'partially_referenced' | 'not_found' | 'needs_review' }
export interface ReviewHistoryItem { reviewer: string; decision: string; comments: string | null; date: string }
export interface ReportVersion { version: number; status: string; created_at: string }
export interface EmployeeFile { id: number; original_name: string; mime_type: string; size: number; url: string; created_at: string }
export interface EmployeeReport {
  id: number; title: string; status: string; version: number; is_late: boolean
  task_id: number; task_title: string; submitted_at: string | null; approved_at: string | null; rejected_at: string | null
  deadline: string | null; submission_date: string | null; content: string | null; references: string | null
  files: EmployeeFile[]
  ocr: { status: string; extracted_text: string | null; confidence: number | null; confidence_label: string; warning: string | null } | null
  ai_summary: { executive_summary: string | null; key_points: string[]; accomplishments: string[]; issues: string[]; recommendations: string[]; disclaimer: string } | null
  ai_validation: { requirement_checks: AiRequirementCheck[]; reference_results: AiReferenceResult[]; similarity: { result: string; label: string; note: string } | null; flags: string[]; advisory_label: string; disclaimer: string } | null
  review_history: ReviewHistoryItem[]
  versions: ReportVersion[]
  version_count: number; ai_status: string | null; advisory_label: string; disclaimer: string
}
export interface EmployeeAchievement { id: number; badge_name: string; badge_description: string; badge_icon: string | null; earned_at: string; criteria: string | null }
export interface EmployeeNotification { id: number; title: string; message: string; type: string; is_read: boolean; created_at: string }
export interface EmployeeProfile { name: string; email: string; employee_number: string; department_name: string; position_name: string; employee_type: string; status: string }
export interface EmployeeCalendarEvent { id: number; title: string; type: 'task' | 'report' | 'deadline' | 'review'; date: string; status: string; url: string | null }
export interface EmployeePerformance {
  summary: { completion_rate: number; on_time_rate: number; approval_rate: number; target_achievement: number; performance_score: number }
  monthly_trend: Array<{ month: string; year: number; completion_rate: number; on_time_rate: number; target_achievement: number }>
  disclaimer: string
}
export interface EmployeeTarget { id: number; name: string; description: string | null; academic_year: string; total_tasks: number; completed_tasks: number; progress_percent: number; status: string }
export const getEmployeeDashboard = () => apiRequest<EmployeeDashboard>(BASE + '/dashboard')
export const getMyTasks = (p: Record<string,unknown> = {}) => apiRequest<EmployeeTask[]>(BASE + '/tasks' + toQueryString(p))
export const getMyTask = (id: number|string) => apiRequest<EmployeeTask>(BASE + '/tasks/' + id)
export const updateMyTaskProgress = (id: number|string, progress: number) => apiRequest<EmployeeTask>(BASE + '/tasks/' + id + '/progress', { method: 'POST', body: { progress } })
export const submitMyTask = (id: number|string, payload: { notes?: string; late_reason?: string }) => apiRequest<EmployeeTask>(BASE + '/tasks/' + id + '/submit', { method: 'POST', body: payload })
export const getMyReports = (p: Record<string,unknown> = {}) => apiRequest<EmployeeReport[]>(BASE + '/reports' + toQueryString(p))
export const getMyReport = (id: number|string) => apiRequest<EmployeeReport>(BASE + '/reports/' + id)
export const createMyReport = (payload: { task_id: number; title: string; content?: string; references?: string }) => apiRequest<EmployeeReport>(BASE + '/reports', { method: 'POST', body: payload })
export const updateMyReport = (id: number|string, payload: { title?: string; content?: string; references?: string }) => apiRequest<EmployeeReport>(BASE + '/reports/' + id, { method: 'PUT', body: payload })
export const submitMyReport = (id: number|string) => apiRequest<EmployeeReport>(BASE + '/reports/' + id + '/submit', { method: 'POST' })
export const createMyReportRevision = (id: number|string, payload: { revision_notes?: string; title?: string; content?: string }) => apiRequest<EmployeeReport>(BASE + '/reports/' + id + '/revision', { method: 'POST', body: payload })
export const getMyCalendar = (p: { month?: number; year?: number } = {}) => apiRequest<EmployeeCalendarEvent[]>(BASE + '/calendar' + toQueryString(p))
export const getMyPerformanceSummary = () => apiRequest<EmployeePerformance>(BASE + '/performance')
export const getMyTargets = () => apiRequest<EmployeeTarget[]>(BASE + '/targets')
export const getMyAchievements = () => apiRequest<EmployeeAchievement[]>(BASE + '/achievements')
export const getMyNotifications = () => apiRequest<EmployeeNotification[]>(BASE + '/notifications')
export const getMyProfile = () => apiRequest<EmployeeProfile>(BASE + '/profile')
export const updateMyProfile = (payload: { email?: string; phone?: string }) => apiRequest<EmployeeProfile>(BASE + '/profile', { method: 'PUT', body: payload })
export const changeMyPassword = (payload: { current_password: string; password: string; password_confirmation: string }) => apiRequest<null>(BASE + '/security/password', { method: 'PUT', body: payload })

