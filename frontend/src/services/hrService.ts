import { apiRequest, toQueryString } from './api'

/**
 * HR portal API client.
 *
 * Separate from `adminDashboardService` on purpose: `/admin/dashboard/*` is
 * shared with HR and department heads, so reusing it would make two
 * definitions of the same institution-wide numbers. The HR dashboard reads its
 * own `/hr/*` contract, which is gated on `hr.dashboard.view`.
 *
 * Honesty contract mirrored from the backend: a rate is `number | null`, never
 * a defaulted `0`, and badges only ever exist where a real grant row does.
 */

const BASE = '/hr'

/** Envelope used by every HR list endpoint. */
export interface HrPagedData<T> {
  summary: Record<string, number>
  items: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

/* ------------------------------------------------------------------ */
/* KPI header block                                                    */
/* ------------------------------------------------------------------ */

export interface HrTrendPoint {
  period: string
  label: string
  total: number
  late: number
  completed: number
  approved: number
}

export interface HrMonthLabel {
  /** `YYYY-MM` */
  period: string
  /** `October 2026` */
  label: string
}

export interface HrDashboardData {
  employees: {
    total: number
    active: number
    inactive: number
    departments: number
  }
  tasks: {
    total: number
    completed: number
    pending: number
    overdue: number
    /** The month the Task Completion Rate card describes. */
    month: HrMonthLabel & {
      total: number
      completed: number
      /** null when nothing was assigned that month — never a fake 0. */
      completion_rate: number | null
    }
  }
  reports: {
    total: number
    submitted: number
    /** Reports handed in during the current calendar month. */
    submitted_this_month: number
    pending: number
    /** Every report that still needs somebody to act (the Pending Reports card). */
    awaiting_action: number
    /** The part of it that waits for the employee to submit or revise. */
    awaiting_submission: number
    late: number
    /** Late reports that were handed in. */
    late_submitted: number
    /** Late reports nobody handed in yet. */
    late_outstanding: number
    approved: number
    revision_required: number
    rejected: number
    /** The month the monthly report figures belong to. */
    month: HrMonthLabel
  }
  kpi: {
    /** null when there is nothing to divide by — never a fake 0. */
    completion_rate: number | null
    on_time_rate: number | null
    approval_rate: number | null
  }
  trends: {
    reports: HrTrendPoint[]
    tasks: HrTrendPoint[]
  }
}

export const getHrDashboard = () => apiRequest<HrDashboardData>(`${BASE}/dashboard`)

/* ------------------------------------------------------------------ */
/* Filter options                                                      */
/* ------------------------------------------------------------------ */

export interface HrOption {
  value: string | number
  label: string
}

export interface HrNamedOption {
  id: number
  name: string
}

export interface HrAcademicYearOption extends HrNamedOption {
  status: string
}

export interface HrOptions {
  departments: HrNamedOption[]
  positions: HrNamedOption[]
  academic_years: HrAcademicYearOption[]
  years: HrOption[]
  months: HrOption[]
  report_statuses: HrOption[]
  task_statuses: HrOption[]
  employment_statuses: string[]
  /** `users.status` values, i.e. the login account rather than the engagement. */
  account_statuses: string[]
}

export const getHrOptions = () => apiRequest<HrOptions>(`${BASE}/dashboard/options`)

/* ------------------------------------------------------------------ */
/* Employee progress overview                                          */
/* ------------------------------------------------------------------ */

export interface HrEmployeeRow {
  id: number
  employee_id: number
  name: string
  employee_number: string
  /** Lives on the linked account, not on `employees`, so it can be null. */
  email: string | null
  has_account: boolean
  account_status: string | null
  date_hired: string | null
  department: string | null
  position: string | null
  status: string
  assigned_tasks: number
  completed_tasks: number
  pending_tasks: number
  late_tasks: number
  submitted_reports: number
  approved_reports: number
  late_reports: number
  completion_rate: number | null
  on_time_rate: number | null
  approval_rate: number | null
  /** Derived from the real late counts: on_track, needs_attention, inactive. */
  current_status: string
}

export interface HrEmployeeSummary {
  total: number
  with_overdue: number
  with_late_reports: number
}

export type HrEmployeeData = HrPagedData<HrEmployeeRow> & {
  summary: HrEmployeeSummary
}

export interface HrEmployeeFilters {
  department_id?: string
  position_id?: string
  status?: string
  account_status?: string
  search?: string
  from_date?: string
  to_date?: string
  sort?: string
  page?: number
  per_page?: number
}

export const getHrEmployees = (params: HrEmployeeFilters = {}) =>
  apiRequest<HrEmployeeData>(`${BASE}/employees${toQueryString({ ...params })}`)

/* ------------------------------------------------------------------ */
/* Employee management (create / edit / account status)                */
/* ------------------------------------------------------------------ */

/** One employee in full, grouped the way the profile screen shows them. */
export interface HrEmployeeProfile {
  id: number
  personal: {
    employee_number: string
    first_name: string
    middle_name: string | null
    last_name: string
    suffix: string | null
    full_name: string
    email: string | null
    username: string | null
    phone: string | null
    address: string | null
    date_of_birth: string | null
    profile_photo_path: string | null
  }
  employment: {
    employment_status: string
    employment_type: string | null
    date_hired: string | null
    date_ended: string | null
    account_status: string | null
    role: string | null
    has_account: boolean
    last_login_at: string | null
  }
  organisation: {
    department_id: number
    department: string | null
    position_id: number | null
    position: string | null
    supervisor_id: number | null
    supervisor: string | null
  }
  tasks: {
    total: number
    completed: number
    pending: number
    late: number
  }
  reports: {
    total: number
    submitted: number
    pending: number
    approved: number
    rejected: number
    revision_required: number
    late: number
  }
  performance: {
    /** null when the denominator is zero — never a defaulted 0. */
    completion_rate: number | null
    on_time_submission_rate: number | null
    approval_rate: number | null
    late_tasks: number
    late_reports: number
    status: string
  }
  validation_alerts: HrValidationAlert[]
  history: HrEmployeeHistoryEntry[]
}

export interface HrValidationAlert {
  id: number
  issue_type: string | null
  severity: string | null
  status: string | null
  /** Still needs a human decision. */
  is_open: boolean
  title: string | null
  description: string | null
  /**
   * Free-form JSON column, so it can hold the offending value in any shape
   * depending on the rule that raised the alert.
   */
  detected_value: Record<string, unknown> | string | number | boolean | null
  reviewed_at: string | null
  created_at: string | null
}

export interface HrEmployeeHistoryEntry {
  /** `employee_histories` for field changes, `activity_logs` for the intent. */
  source: 'employee_histories' | 'activity_logs'
  id: number
  event: string
  description: string | null
  actor: string | null
  occurred_at: string | null
}

export const getHrEmployeeProfile = (employeeId: number) =>
  apiRequest<HrEmployeeProfile>(`${BASE}/employees/${employeeId}`)

/** Fields shared by the create and edit forms; all optional for a partial edit. */
export interface HrEmployeeInput {
  employee_number?: string
  first_name?: string
  middle_name?: string | null
  last_name?: string
  suffix?: string | null

  /** Supplying an email without a password creates an unusable login. */
  email?: string | null
  /** Required whenever an account (email) is supplied; unique per account. */
  username?: string | null
  password?: string | null
  account_status?: string | null

  department_id?: number | null
  position_id?: number | null
  supervisor_id?: number | null

  employment_status?: string | null
  employment_type?: string | null
  date_hired?: string | null
  date_of_birth?: string | null
  phone?: string | null
  address?: string | null

  /** Recorded on every changed field of an edit, for the audit trail. */
  reason?: string | null
}

export const createHrEmployee = (payload: HrEmployeeInput) =>
  apiRequest<HrEmployeeProfile>(`${BASE}/employees`, { method: 'POST', body: payload })

export const updateHrEmployee = (employeeId: number, payload: HrEmployeeInput) =>
  apiRequest<HrEmployeeProfile>(`${BASE}/employees/${employeeId}`, {
    method: 'PATCH',
    body: payload,
  })

/* ------------------------------------------------------------------ */
/* Duplicate-name advisory check                                       */
/* ------------------------------------------------------------------ */

export interface HrDuplicateNameMatch {
  id: number
  employee_number: string
  full_name: string
  department: string | null
  email: string | null
  username: string | null
}

export interface HrDuplicateNameResult {
  normalized_name: string
  matches: HrDuplicateNameMatch[]
}

/**
 * Advisory only: a matching name is a warning the operator may override,
 * never a hard block. The backend normalises case and whitespace.
 */
export const checkHrEmployeeDuplicateName = (params: {
  first_name: string
  middle_name?: string | null
  last_name: string
  exclude_id?: number
}) => apiRequest<HrDuplicateNameResult>(`${BASE}/employees/duplicate-name${toQueryString({ ...params })}`)

/**
 * Activating/deactivating a login never deletes the account, so the employee
 * record and its audit trail survive.
 */
export const setHrEmployeeAccountStatus = (employeeId: number, accountStatus: string) =>
  apiRequest<HrEmployeeProfile>(`${BASE}/employees/${employeeId}/account-status`, {
    method: 'POST',
    body: { account_status: accountStatus },
  })

/* ------------------------------------------------------------------ */
/* Task progress monitoring                                            */
/* ------------------------------------------------------------------ */

export interface HrTaskRow {
  id: number
  task_assignment_id: number
  task_id: number
  task_name: string | null
  task_code: string | null
  employee_id: number
  employee_name: string | null
  employee_number: string | null
  department: string | null
  start_date: string | null
  /** Assignment due date, falling back to the parent task's due date. */
  deadline: string | null
  progress_percentage: number | null
  /** Normalised label: an overdue assignment reports "overdue". */
  status: string
  /** The untouched `task_assignments.status`. */
  raw_status: string
  is_overdue: boolean
  completed_at: string | null
  report_status: string | null
}

export interface HrTaskSummary {
  total: number
  completed: number
  pending: number
  overdue: number
}

export type HrTaskData = HrPagedData<HrTaskRow> & {
  summary: HrTaskSummary
}

export interface HrTaskFilters {
  department_id?: string
  employee_id?: string
  status?: string
  overdue?: string
  search?: string
  from_date?: string
  to_date?: string
  page?: number
  per_page?: number
}

export const getHrTaskMonitoring = (params: HrTaskFilters = {}) =>
  apiRequest<HrTaskData>(`${BASE}/monitoring${toQueryString({ ...params })}`)

/* ------------------------------------------------------------------ */
/* Report monitoring                                                   */
/* ------------------------------------------------------------------ */

export interface HrReportRow {
  id: number
  report_id: number
  title: string
  task_name: string | null
  task_code: string | null
  employee_id: number
  employee_name: string | null
  employee_number: string | null
  department: string | null
  submission_date: string | null
  submitted_at: string | null
  deadline: string | null
  status: string
  /** Resolved from the ReportStatus enum, never a raw string in the UI. */
  status_label: string
  is_late: boolean
  days_late: number
  needs_revision: boolean
}

export interface HrReportSummary {
  total: number
  submitted: number
  pending: number
  late: number
  approved: number
  revision_required: number
  on_time_rate: number | null
  approval_rate: number | null
}

export type HrReportData = HrPagedData<HrReportRow> & {
  summary: HrReportSummary
}

export interface HrReportFilters {
  department_id?: string
  employee_id?: string
  status?: string
  /** `1` narrows the table to the Late Reports alert list. */
  late?: string
  search?: string
  from_date?: string
  to_date?: string
  page?: number
  per_page?: number
}

export const getHrReports = (params: HrReportFilters = {}) =>
  apiRequest<HrReportData>(`${BASE}/reports${toQueryString({ ...params })}`)

/* ------------------------------------------------------------------ */
/* Monthly achievement                                                 */
/* ------------------------------------------------------------------ */

export interface HrBadgeGrant {
  id: number
  name: string
  code: string
  icon: string | null
  description: string | null
  criteria_type: string | null
  criteria_value: Record<string, unknown> | null
  period_start: string | null
  period_end: string | null
  awarded_at: string | null
}

export interface HrAchievementRow {
  id: number
  employee_id: number
  name: string
  employee_number: string
  department: string | null
  /** `YYYY-MM`. */
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
  /** Empty when the employee holds no grant — never a default badge. */
  badges: HrBadgeGrant[]
}

export type HrAchievementData = HrPagedData<HrAchievementRow>

export interface HrAchievementFilters {
  department_id?: string
  year?: string
  month?: string
  search?: string
  page?: number
  per_page?: number
}

export const getHrAchievements = (params: HrAchievementFilters = {}) =>
  apiRequest<HrAchievementData>(`${BASE}/achievements${toQueryString({ ...params })}`)
