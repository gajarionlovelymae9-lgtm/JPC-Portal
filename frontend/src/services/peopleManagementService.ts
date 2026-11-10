/**
 * People Management API Service
 *
 * Covers the department management surface + people summary used by the
 * consolidated People Management module (admin only). Employee endpoints live
 * in hrService.ts and user-account endpoints in adminDashboardService.ts;
 * this file mirrors the backend AdminDepartmentManagementService contract.
 *
 * There is deliberately NO deleteDepartment here: departments are deactivated,
 * never hard-deleted, so historical employees, tasks and reports keep resolving.
 */

import { apiRequest } from './api'
import type { ApiResponse } from '../types/api'

// ─── Types ────────────────────────────────────────────────────────────────────

export interface Department {
  id: number
  name: string
  code: string | null
  description: string | null
  status: 'active' | 'inactive'
  created_at?: string
  // enriched by AdminDepartmentManagementService::listDepartments
  head_employee_id?: number | null
  head_name?: string
  head_employee_number?: string
  employees_count?: number
  active_employees?: number
  tasks_count?: number
  completed_tasks?: number
  pending_tasks?: number
  overdue_tasks?: number
  reports_count?: number
  completion_rate?: number
  on_time_rate?: number
}

export interface DepartmentDetail {
  id: number
  name: string
  code: string | null
  description: string | null
  status: 'active' | 'inactive'
  created_at: string | null
  head: {
    employee_id: number | null
    name: string
    position?: string | null
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
  employees: {
    id: number
    name: string
    employee_number: string
    position: string | null
    position_id: number | null
    status: string
    employment_type?: string
    date_hired?: string | null
    account_status?: string | null
  }[]
}

export interface DepartmentSummary {
  total: number
  active: number
  inactive: number
}

/** Shape returned by GET /admin/people/summary. */
export interface PeopleSummary {
  employees: {
    total: number
    active: number
    inactive: number
    with_late_reports: number
  }
  departments: DepartmentSummary
  accounts: {
    total: number
    active: number
    inactive: number
    suspended: number
    unlinked_employee_accounts: number
    accounts_without_employee: number
  }
}

/** Shape returned by GET /admin/departments/options. */
export interface DepartmentOptions {
  head_role: string
  head_role_users: number
  departments: Array<{ id: number; name: string; code: string | null; status: string }>
  statuses: OptionItem[]
  employees: Array<{
    id: number
    name: string
    employee_number: string
    department_id: number | null
    department: string | null
    employment_status: string
    has_account: boolean
    account_status?: string | null
    role: string | null
    is_head_role: boolean
  }>
}

/** One employee eligible to be assigned as a department head. */
export interface EligibleDepartmentHead {
  id: number
  name: string
  employee_number: string
  position: string | null
  department_id: number
  department: string
  account_status?: string | null
}

/** Why an employee was not eligible, used to explain an empty picker. */
export interface DepartmentHeadDiagnostics {
  total_employees: number
  active_employees: number
  without_account: number
  inactive_account: number
  without_head_role: number
  eligible: number
}

/** Shape returned by GET /admin/departments/{department}/eligible-heads. */
export interface EligibleDepartmentHeads {
  department: { id: number; name: string; code: string | null; status: string }
  head_role: string
  current_head_employee_id: number | null
  employees: EligibleDepartmentHead[]
  diagnostics: DepartmentHeadDiagnostics
}

export interface OptionItem {
  value: string
  label: string
}

// ─── Department API Calls ─────────────────────────────────────────────────────

// `apiRequest` already prepends `/api/v1`, so paths here must not repeat it.
const BASE = '/admin/departments'

export async function listDepartments(filters?: Record<string, string>): Promise<Department[]> {
  const params = filters ? '?' + new URLSearchParams(filters).toString() : ''
  const res = await apiRequest(`${BASE}${params}`)
  return res.data as Department[]
}

export async function getDepartment(id: number): Promise<DepartmentDetail> {
  const res = await apiRequest(`${BASE}/${id}`)
  return res.data as DepartmentDetail
}

/** Summary-card counts, always from live queries (never the visible list). */
export async function getDepartmentSummary(): Promise<DepartmentSummary> {
  const res = await apiRequest(`${BASE}/summary`)
  return res.data as DepartmentSummary
}

/** Options for the create/edit/assignment forms. */
export async function getDepartmentOptions(): Promise<DepartmentOptions> {
  const res = await apiRequest(`${BASE}/options`)
  return res.data as DepartmentOptions
}

/**
 * Employees eligible to head a specific department — resolved server-side from
 * real records, including account status and both supported role stores.
 */
export async function getEligibleDepartmentHeads(departmentId: number): Promise<EligibleDepartmentHeads> {
  const res = await apiRequest(`${BASE}/${departmentId}/eligible-heads`)
  return res.data as EligibleDepartmentHeads
}

export async function createDepartment(data: {
  name: string
  code?: string
  description?: string
  status?: string
}): Promise<DepartmentDetail> {
  // Pass the object as-is: `apiRequest` owns JSON serialization. Stringifying
  // here would double-encode the body, and Laravel would receive a JSON string
  // instead of an object (so `name` would look missing -> "name required").
  const res = await apiRequest(BASE, {
    method: 'POST',
    body: data,
  })
  return res.data as DepartmentDetail
}

export async function updateDepartment(
  id: number,
  data: {
    name?: string
    code?: string | null
    description?: string | null
    status?: string
  },
): Promise<DepartmentDetail> {
  const res = await apiRequest(`${BASE}/${id}`, {
    method: 'PUT',
    body: data,
  })
  return res.data as DepartmentDetail
}

/** Activate or deactivate a department. Deactivation is the only removal path. */
export async function setDepartmentStatus(id: number, status: 'active' | 'inactive'): Promise<DepartmentDetail> {
  const res = await apiRequest(`${BASE}/${id}/status`, {
    method: 'POST',
    body: { status },
  })
  return res.data as DepartmentDetail
}

/**
 * Assign a department head. The employee must belong to the department, be
 * actively employed, and their account must already hold the Department Head
 * role — the server rejects anything else, never granting the role implicitly.
 */
export async function assignDepartmentHead(departmentId: number, employeeId: number): Promise<DepartmentDetail> {
  const res = await apiRequest(`${BASE}/${departmentId}/head`, {
    method: 'POST',
    body: { employee_id: employeeId },
  })
  return res.data as DepartmentDetail
}

/** Transfer employees into a department (history is preserved). */
export async function assignDepartmentEmployees(
  departmentId: number,
  employeeIds: number[],
): Promise<{ department: DepartmentDetail; transferred: number }> {
  const res = await apiRequest(`${BASE}/${departmentId}/employees`, {
    method: 'POST',
    body: { employee_ids: employeeIds },
  })
  return res.data as { department: DepartmentDetail; transferred: number }
}

// ─── Roles & Access ───────────────────────────────────────────────────────────

export interface RolesCatalogue {
  roles: Array<{
    id: number
    name: string
    label: string
    description: string | null
    /** The Administrator role is read-only — its permission set is the invariant. */
    is_locked: boolean
    users_count: number
    permissions: string[]
  }>
  permissions: Array<{
    group: string
    label: string
    permissions: Array<{ name: string; label: string; elevated: boolean }>
  }>
  total_permissions: number
  /** Permissions reserved for the Administrator role; never assignable elsewhere. */
  elevated_permissions: string[]
}

/** Full role + permission catalogue for the Roles & Access tab. */
export async function getRolesAccess(): Promise<RolesCatalogue> {
  const res = await apiRequest('/admin/roles')
  return res.data as RolesCatalogue
}

/**
 * Replace a role's permission set. The server blocks the Administrator role
 * and every elevated permission in the deny-list, and the UI mirrors both
 * guards so they cannot be triggered accidentally.
 */
export async function updateRolePermissions(
  roleId: number,
  permissions: string[],
): Promise<RolesCatalogue> {
  const res = await apiRequest(`/admin/roles/${roleId}/permissions`, {
    method: 'PUT',
    // apiRequest serialises plain objects; passing a pre-stringified body
    // would be double-encoded and rejected by Laravel's `array` rule.
    body: { permissions },
  })
  return res.data as RolesCatalogue
}

// ─── People Module Summary ────────────────────────────────────────────────────

/** Combined People Management header summary (employees + departments + accounts). */
export function getPeopleSummary(): Promise<ApiResponse<PeopleSummary>> {
  return apiRequest<PeopleSummary>('/admin/people/summary')
}
