import { apiRequest, toQueryString } from './api'

/** Envelope helpers live in the caller; this service returns raw API data. */

/* ------------------------------------------------------------------ */
/* Types (shapes mirror the backend decorated rows exactly)             */
/* ------------------------------------------------------------------ */

export interface HrTargetOption {
  value: string | number
  label: string
}

export interface HrTargetEmployeeOption {
  id: number
  name: string
  employee_number: string | null
  department_id: number | null
  department_name: string | null
  status: string | null
}

export interface HrTargetPlanOption {
  id: number
  title: string | null
  status: string | null
  academic_year: string | null
}

export interface HrTargetOptions {
  departments: Array<{ id: number; name: string }>
  academic_years: Array<{ id: number; name: string; status: string | null }>
  statuses: Array<{ value: string; label: string }>
  stored_statuses: Array<{ value: string; label: string }>
  employees: HrTargetEmployeeOption[]
  annual_plans: HrTargetPlanOption[]
}

export interface HrTargetItem {
  id: number
  annual_plan_id: number
  employee_id: number
  employee_name: string | null
  employee_number: string | null
  department_id: number | null
  department_name: string | null
  kpi: string
  description: string | null
  target: number | null
  target_unit: string | null
  actual: number | null
  tasks_total: number
  tasks_completed: number
  achievement_pct: number | null
  stored_status: string
  derived_status: string
  period: string
  start_date: string | null
  end_date: string | null
}

export interface HrTargetSummary {
  total_kpis: number
  completed_targets: number
  in_progress_targets: number
  missed_targets: number
  not_started_targets: number
  avg_kpi_achievement: number
}

export interface HrTargetMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface HrTargetFilters {
  search?: string
  department_id?: string
  employee_id?: string
  academic_year_id?: string
  status?: string
  page?: number
  per_page?: number
}

export interface HrTargetPayload {
  annual_plan_id: number | ''
  employee_id: number | ''
  title: string
  description: string
  target_value: string
  target_unit: string
  start_date: string
  end_date: string
  status: string
}

interface HrTargetListData {
  summary: HrTargetSummary
  items: HrTargetItem[]
  meta: HrTargetMeta
  options: HrTargetOptions
}

interface Envelope<T> {
  success: boolean
  message?: string
  data: T
}

const BASE = '/hr/targets'

/* ------------------------------------------------------------------ */
/* API calls                                                            */
/* ------------------------------------------------------------------ */

export async function fetchHrTargets(
  filters: HrTargetFilters = {},
): Promise<Envelope<HrTargetListData>> {
  return apiRequest<Envelope<HrTargetListData>['data']>(
    `${BASE}${toQueryString({ ...filters })}`,
  )
}

export async function fetchHrTargetDetail(id: number): Promise<HrTargetItem> {
  const response = await apiRequest<HrTargetItem>(`${BASE}/${id}`)
  return response.data
}

export async function createHrTarget(
  payload: Partial<HrTargetPayload>,
): Promise<HrTargetItem> {
  const response = await apiRequest<HrTargetItem>(BASE, {
    method: 'POST',
    body: clean(payload),
  })
  return response.data
}

export async function updateHrTarget(
  id: number,
  payload: Partial<HrTargetPayload>,
): Promise<HrTargetItem> {
  const response = await apiRequest<HrTargetItem>(`${BASE}/${id}`, {
    method: 'PUT',
    body: clean(payload),
  })
  return response.data
}

export async function deleteHrTarget(id: number): Promise<void> {
  await apiRequest(`${BASE}/${id}`, { method: 'DELETE' })
}

/** Strip empty strings / blank options so Laravel's nullable rules apply. */
function clean(payload: Partial<HrTargetPayload>): Record<string, unknown> {
  const body: Record<string, unknown> = {}
  Object.entries(payload).forEach(([key, value]) => {
    if (value === '') return
    body[key] = value
  })
  return body
}
