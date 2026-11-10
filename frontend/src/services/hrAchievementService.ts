import { apiRequest } from './api'

export interface HrAchievementRecord {
  id: number
  employee_id: number | null
  employee_name: string | null
  employee_number: string | null
  department: string | null
  position: string | null
  badge_id: number | null
  achievement_title: string | null
  badge_name: string | null
  badge_code: string | null
  category: string | null
  badge_category: string | null
  tier: string | null
  badge_tier: string | null
  achievement_date: string | null
  awarded_at: string | null
  verification_status: 'pending' | 'verified' | 'rejected'
  verified_at: string | null
  verified_by: string | null
  /** Department Head recommendation metadata (null for auto awards). */
  recommended_by: string | null
  recommended_at: string | null
  recommendation_details: string | null
  rejection_reason: string | null
  reason: string | null
  period_start: string | null
  period_end: string | null
  academic_year: string | null
  evidence: Record<string, unknown> | null
}

export interface HrAchievementKpiCards {
  total_recognitions: number
  verified: number
  pending_verification: number
  this_month: number
}

export interface HrAchievementOption {
  value: string | number
  label: string
  code?: string
  category?: string | null
  tier?: string | null
  is_active?: boolean
}

export interface HrAchievementOptions {
  departments: HrAchievementOption[]
  academic_years: HrAchievementOption[]
  badges: HrAchievementOption[]
  categories: HrAchievementOption[]
  tiers: HrAchievementOption[]
  years: HrAchievementOption[]
  months: HrAchievementOption[]
  statuses: HrAchievementOption[]
}

export interface HrAchievementReport {
  period: { label: string; year: number; month: number | null }
  totals: { total: number; verified: number; pending: number; rejected: number }
  by_category: Array<{ category: string; total: number }>
  by_tier: Array<{ tier: string; total: number }>
  employees: Array<{
    employee_id: number | null
    employee_number: string | null
    name: string | null
    department: string | null
    total: number
    verified: number
    pending: number
    rejected: number
    awards: Array<{
      id: number
      badge: string | null
      category: string | null
      tier: string | null
      awarded_at: string | null
      verification_status: string
    }>
  }>
}

export interface HrAchievementDetail extends HrAchievementRecord {
  employee_email?: string | null
  badge_description?: string | null
  criteria_snapshot?: Record<string, unknown> | null
  history?: Array<{
    id: number
    activity_type: string
    description: string
    actor: string | null
    metadata: Record<string, unknown> | null
    created_at: string | null
  }>
  created_at?: string | null
  updated_at?: string | null
}

export interface HrAchievementListResponse {
  items: HrAchievementRecord[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
  cards: HrAchievementKpiCards
}

export interface HrAchievementFilters {
  employee_id?: string | number
  department_id?: string | number
  badge_id?: string | number
  academic_year_id?: string | number
  category?: string
  tier?: string
  status?: string
  verification_status?: string
  year?: string | number
  month?: string | number
  search?: string
  page?: number
  per_page?: number
  format?: 'csv' | 'xlsx' | 'pdf'
}

function buildQuery(filters?: HrAchievementFilters): string {
  if (!filters) return ''
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    params.append(key, String(value))
  })
  const query = params.toString()
  return query ? `?${query}` : ''
}

export async function fetchHrAchievements(filters?: HrAchievementFilters) {
  return apiRequest<HrAchievementListResponse>(`/hr/achievement-badges${buildQuery(filters)}`)
}

export async function fetchHrAchievementOptions() {
  return apiRequest<HrAchievementOptions>('/hr/achievement-badges/options')
}

export async function fetchHrAchievementDetail(id: number) {
  return apiRequest<HrAchievementDetail>(`/hr/achievement-badges/${id}`)
}

export async function verifyHrAchievement(id: number) {
  return apiRequest<HrAchievementDetail>(`/hr/achievement-badges/${id}/verify`, { method: 'POST' })
}

export async function rejectHrAchievement(id: number, reason: string) {
  return apiRequest<HrAchievementDetail>(`/hr/achievement-badges/${id}/reject`, {
    method: 'POST',
    body: JSON.stringify({ reason }),
  })
}

export async function fetchHrAchievementReports(filters?: HrAchievementFilters) {
  return apiRequest<HrAchievementReport>(`/hr/achievement-badges/reports${buildQuery(filters)}`)
}

export async function exportHrAchievements(filters?: HrAchievementFilters) {
  const query = buildQuery({ ...filters, format: filters?.format ?? 'csv' })
  const response = await fetch(`${import.meta.env.VITE_API_BASE_URL ?? '/api/v1'}/hr/achievement-badges/export${query}`, {
    method: 'GET',
    credentials: 'include',
  })
  if (!response.ok) {
    throw new Error('Failed to export HR achievements')
  }
  const disposition = response.headers.get('Content-Disposition') ?? ''
  const match = disposition.match(/filename="([^"]+)"/)
  const filename = match?.[1] ?? `HR_Achievements.${filters?.format ?? 'csv'}`
  const blob = await response.blob()
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
