import { apiRequest, toQueryString } from './api'

const ADMIN_BASE = '/admin'

// ---------------------------------------------------------------- types

export interface HealthComponent {
  status: 'ok' | 'degraded' | 'error'
  checks: Record<string, string>
  metrics: Record<string, number | string>
}

export interface SystemHealthData {
  status: 'ok' | 'degraded'
  checked_at: string
  environment: string
  components: {
    application: HealthComponent
    database: HealthComponent
    queue: HealthComponent
    storage: HealthComponent
    ai: HealthComponent
    ocr: HealthComponent
  }
  last_backup: { performed_at: string; result: string; verified: boolean } | null
}

export interface SystemHealthDashboard {
  system_status: Record<string, string>
  counters: Record<string, number>
  recent_critical_errors: string[]
  checked_at: string
  status: string
}

export interface HealthHistoryEntry {
  id: number
  status: string
  source: string
  checks: Record<string, string>
  checked_at: string
}

export interface MaintenanceLogItem {
  id: number
  performed_at: string
  version: string | null
  performed_by: number | null
  maintenance_type: string
  affected_module: string | null
  description: string
  result: 'completed' | 'failed' | 'pending' | 'rolled_back'
  rollback_required: boolean
  notes: string | null
  performer?: { id: number; name: string; email: string } | null
}

export interface UserFeedbackItem {
  id: number
  user_id: number
  category: string
  subject: string
  description: string
  priority: 'low' | 'medium' | 'high' | 'critical'
  status: 'OPEN' | 'UNDER_REVIEW' | 'IN_PROGRESS' | 'RESOLVED' | 'REJECTED' | 'CLOSED'
  assigned_to: number | null
  resolution: string | null
  resolved_at: string | null
  created_at: string
  user?: { id: number; name: string; email: string }
  assignee?: { id: number; name: string } | null
}

export interface AiFeedbackItem {
  id: number
  user_id: number | null
  ai_type: string
  ai_record_id: number | null
  category: string
  verdict: string
  comment: string | null
  created_at: string
}

export interface Paginated<T> {
  data: T[]
  total: number
  current_page: number
  last_page: number
}

// ------------------------------------------------------------ system health

export const getSystemHealth = () =>
  apiRequest<SystemHealthData>(`${ADMIN_BASE}/system-health`)

export const getSystemHealthDashboard = () =>
  apiRequest<SystemHealthDashboard>(`${ADMIN_BASE}/system-health/dashboard`)

export const getSystemHealthHistory = () =>
  apiRequest<HealthHistoryEntry[]>(`${ADMIN_BASE}/system-health/history`)

// ------------------------------------------------------------- maintenance

export const getMaintenanceLogs = (params: Record<string, unknown> = {}) =>
  apiRequest<Paginated<MaintenanceLogItem>>(`${ADMIN_BASE}/maintenance/logs${toQueryString(params)}`)

export const recordMaintenanceLog = (payload: {
  maintenance_type: string
  description: string
  affected_module?: string
  result?: string
  rollback_required?: boolean
  notes?: string
}) =>
  apiRequest<MaintenanceLogItem>(`${ADMIN_BASE}/maintenance/logs`, {
    method: 'POST',
    body: payload,
  })

// ------------------------------------------------------ feedback (admin + user)

export const getFeedbackList = (params: Record<string, unknown> = {}) =>
  apiRequest<Paginated<UserFeedbackItem>>(`${ADMIN_BASE}/feedback${toQueryString(params)}`)

export const getAiFeedbackList = () =>
  apiRequest<AiFeedbackItem[]>(`${ADMIN_BASE}/feedback/ai`)

export const updateFeedback = (feedbackId: number | string, payload: {
  status?: string
  priority?: string
  assigned_to?: number | null
  resolution?: string
}) =>
  apiRequest<UserFeedbackItem>(`${ADMIN_BASE}/feedback/${feedbackId}`, {
    method: 'PATCH',
    body: payload,
  })

export const submitFeedback = (payload: {
  category: string
  subject: string
  description: string
  priority?: string
}) =>
  apiRequest<UserFeedbackItem>('/feedback', { method: 'POST', body: payload })

export const getMyFeedback = () =>
  apiRequest<UserFeedbackItem[]>('/feedback/mine')

export const submitAiFeedback = (payload: {
  ai_type: string
  ai_record_id?: number | null
  category?: string
  verdict: string
  comment?: string
}) =>
  apiRequest<AiFeedbackItem>('/feedback/ai', { method: 'POST', body: payload })