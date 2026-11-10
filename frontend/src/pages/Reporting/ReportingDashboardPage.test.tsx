import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { AuthContext, type AuthContextValue } from '../../auth/useAuth'
import type { InstitutionReport } from '../../types/reporting'
import { ReportingDashboardPage } from './ReportingDashboardPage'

const now = new Date()
const year = now.getFullYear()
const month = now.getMonth() + 1

const options = {
  departments: [{ id: 1, name: 'College of Education' }],
  positions: [{ id: 1, name: 'Faculty Member' }],
  academic_years: [{ id: 3, name: '2026-2027', status: 'active' }],
  years: [{ value: year, label: String(year) }],
  months: [{ value: 1, label: 'January' }],
  report_statuses: [{ value: 'submitted', label: 'Submitted' }],
  task_statuses: [{ value: 'assigned', label: 'Assigned' }],
  employment_statuses: ['active', 'inactive'],
  account_statuses: ['active', 'inactive'],
}

const institution: InstitutionReport = {
  report_type: 'institution_performance',
  period: 'October 2026',
  period_key: { type: 'monthly', year, month },
  scope: 'institution',
  department_id: null,
  source: 'computed',
  calculation_version: 'v1.0',
  data_sources: ['tasks', 'reports'],
  has_data: true,
  generated_at: '2026-10-22T10:00:00Z',
  summary: {
    total_employees: 16,
    active_employees: 14,
    total_departments: 4,
    tasks_assigned: 10,
    tasks_completed: 7,
    task_completion_rate: 70,
    reports_expected: 10,
    reports_submitted: 5,
    late_reports: 2,
    late_submitted: 1,
    late_outstanding: 1,
    pending_reports: 3,
    awaiting_action: 2,
    awaiting_submission: 1,
    approval_rate: 60,
    target_achievement_rate: null,
    average_productivity: null,
    on_time_rate: 50,
    data_quality_score: null,
    data_quality_records_checked: 0,
    data_quality_issues_found: 0,
  },
}

const summary = {
  period: '2026-10',
  period_label: 'October 2026',
  period_type: 'monthly',
  narrative:
    'Executive Management Summary for October 2026: The institution recorded 7 completed tasks. The data quality score is N/A because no completed scan has evaluated the institution yet.',
  key_takeaways: ['Tasks Completed: 7 / 10 (70.0%)', 'Data Quality Health Index: N/A (not yet evaluated)'],
  has_data: true,
  generated_at: '2026-10-22T10:00:00Z',
  metrics: {
    average_productivity: null,
    tasks_completed: 7,
    tasks_assigned: 10,
    task_completion_rate: 70,
    reports_submitted: 5,
    pending_reports: 3,
    late_reports: 2,
    on_time_rate: 50,
    approval_rate: 60,
    data_quality_score: null,
  },
  change: {
    period: '2026-09',
    period_label: 'September 2026',
    average_productivity_change: 4.5,
    task_completion_change: null,
    reports_change: 1,
    late_change: -1,
    previous_period: 'September 2026',
  },
  distribution: {},
  top_performer: null,
  top_department: null,
}

const employeeRows = [
  {
    rank: 1,
    employee_id: 9,
    employee_number: 'EMP-009',
    employee_name: 'Ana Santos',
    department_id: 1,
    department_name: 'College of Education',
    total_tasks: 4,
    completed_tasks: 3,
    pending_tasks: 1,
    overdue_tasks: 0,
    on_time: 2,
    late: 1,
    completion_rate: 75,
    on_time_rate: 66.67,
    reports_submitted: 2,
    late_reports: 1,
    approval_rate: 50,
    target_achievement: 80,
    total_targets: 2,
    achieved_targets: 1,
    performance_status: 'Very Good',
    next_due_date: '2026-11-01',
  },
]

const employeeDetail = {
  ...employeeRows[0],
  recent_assignments: [
    {
      assignment_id: 44,
      task_id: 7,
      task_code: 'TASK-007',
      task_title: 'Prepare module evaluation',
      department_id: 1,
      department_name: 'College of Education',
      status: 'completed',
      priority: 'high',
      due_date: '2026-10-15',
      completed_at: '2026-10-14T08:00:00Z',
      progress_percentage: 100,
    },
  ],
}

const departmentRows = [
  {
    rank: 1,
    department_id: 1,
    department_name: 'College of Education',
    department_code: 'COE',
    employee_count: 4,
    total_tasks: 10,
    completed_tasks: 8,
    pending_tasks: 2,
    overdue_tasks: 0,
    on_time: 7,
    late: 1,
    completion_rate: 80,
    on_time_rate: 87.5,
    reports_submitted: 6,
    late_reports: 1,
    approval_rate: 66.67,
    on_time_submission_rate: 83.33,
    target_achievement: 90,
    total_targets: 5,
    achieved_targets: 4,
    next_due_date: '2026-11-02',
  },
]

const monthly = {
  report_type: 'monthly_performance',
  period: 'October 2026',
  period_key: { type: 'monthly', year, month },
  has_data: true,
  generated_at: '2026-10-22T10:00:00Z',
  scope: 'institution',
  top_performer: { employee_id: 9, name: 'Ana Santos', department: 'College of Education', score: 88 },
  highest_completion: { employee_id: 9, name: 'Ana Santos', completion_rate: 75 },
  highest_target: { employee_id: 9, name: 'Ana Santos', target_rate: 80 },
  most_improved: {
    employee_id: 9,
    employee_name: 'Ana Santos',
    department: 'College of Education',
    delta: 6,
    score: 88,
  },
  top_department: {
    department_id: 1,
    department_name: 'College of Education',
    employees_evaluated: 4,
    average_score: 88,
  },
  department_rankings: [
    {
      department_id: 1,
      department_name: 'College of Education',
      employees_evaluated: 4,
      average_score: 88,
    },
  ],
  summary: {
    employees_evaluated: 4,
    average_productivity: 88,
    previous_average_productivity: 82,
    performance_change: 6,
    monthly_achievements: 3,
    late_reports: 1,
  },
  calculation_version: 'v1.0',
  data_sources: ['monthly_performance_snapshots'],
}

const trend = [
  {
    period: 'October 2026',
    year,
    month,
    productivity_score: 88,
    completion_rate: 70,
    records_count: 4,
  },
]

const late = {
  report_type: 'late_reports',
  period: 'October 2026',
  period_key: { type: 'monthly', year, month },
  has_data: true,
  generated_at: '2026-10-22T10:00:00Z',
  summary: {
    late_reports: 2,
    total_reports: 5,
    late_submission_rate: 40,
    late_employees: 1,
    late_departments: 1,
  },
  comparison: null,
  by_employee: [
    {
      employee_id: 9,
      employee_name: 'Ana Santos',
      department: 'College of Education',
      late_reports: 2,
    },
  ],
  by_department: [{ department_id: 1, department_name: 'College of Education', late_reports: 2 }],
  reports: [
    {
      id: 10,
      title: 'Monthly accomplishment report',
      employee: 'Ana Santos',
      department: 'College of Education',
      task: 'TASK-007',
      status: 'submitted',
      status_label: 'Submitted',
      submitted_at: '2026-10-10T08:00:00Z',
      deadline: '2026-10-05',
      days_late: 5,
      is_outstanding: false,
    },
    {
      id: 11,
      title: 'Quarterly narrative report',
      employee: 'Ana Santos',
      department: 'College of Education',
      task: null,
      status: 'draft',
      status_label: 'Draft',
      submitted_at: null,
      deadline: '2026-10-01',
      days_late: 0,
      is_outstanding: true,
    },
  ],
  data_sources: ['reports', 'tasks'],
}

const pending = {
  report_type: 'pending_reports',
  period: 'October 2026',
  period_key: { type: 'monthly', year, month },
  has_data: true,
  generated_at: '2026-10-22T10:00:00Z',
  summary: {
    pending_reports: 3,
    pending_employees: 2,
    pending_departments: 1,
    awaiting_review: 1,
    revision_required: 1,
  },
  by_employee: [],
  by_department: [],
  by_status: [],
  by_month: [],
  reports: [
    {
      id: 12,
      title: 'Awaiting approval report',
      employee: 'Ana Santos',
      department: 'College of Education',
      status: 'submitted',
      status_label: 'Submitted',
      category: 'pending_review',
      submitted_at: '2026-10-12T08:00:00Z',
      deadline: '2026-10-15',
      days_remaining: 2,
      days_overdue: null,
      is_overdue: false,
      required_action: 'review',
    },
    {
      id: 13,
      title: 'Returned for revision report',
      employee: 'Ben Cruz',
      department: 'College of Education',
      status: 'revision_required',
      status_label: 'Revision required',
      category: 'revision_required',
      submitted_at: '2026-10-11T08:00:00Z',
      deadline: '2026-10-18',
      days_remaining: null,
      days_overdue: 1,
      is_overdue: true,
      required_action: 'revise',
    },
    {
      id: 14,
      title: 'Never submitted report',
      employee: 'Ben Cruz',
      department: 'College of Education',
      status: 'draft',
      status_label: 'Draft',
      category: 'not_submitted',
      submitted_at: null,
      deadline: '2026-10-02',
      days_remaining: null,
      days_overdue: 4,
      is_overdue: true,
      required_action: 'submit',
    },
  ],
  data_sources: ['reports', 'tasks'],
}

const targets = {
  report_type: 'target_achievement',
  period: 'October 2026',
  period_key: { type: 'monthly', year, month },
  has_data: true,
  generated_at: '2026-10-22T10:00:00Z',
  total_targets: 1,
  achieved_targets: 0,
  partially_achieved: 0,
  unachieved: 0,
  in_progress: 1,
  achievement_rate: 0,
  total_target_value: 150,
  total_actual: 120,
  achievement_percentage: 80,
  by_department: [
    {
      department_id: 1,
      department_name: 'College of Education',
      total_targets: 1,
      achieved: 0,
      achievement_rate: 0,
      total_target_value: 150,
      total_actual: 120,
      achievement_percentage: 80,
    },
  ],
  targets: [
    {
      id: 21,
      title: 'Enrolment target',
      status: 'in_progress',
      status_label: 'In progress',
      target_value: 150,
      target_unit: 'students',
      actual_value: 120,
      achievement_percentage: 80,
      remaining_target: 30,
      period_type: 'monthly',
      employee: 'Ana Santos',
      department: 'College of Education',
      start_date: '2026-10-01',
      end_date: '2026-10-31',
    },
  ],
  data_sources: ['employee_targets', 'tasks'],
}

const dataQuality = {
  report_type: 'data_quality',
  period: 'October 2026',
  period_key: { type: 'monthly', year, month },
  has_data: false,
  generated_at: '2026-10-22T10:00:00Z',
  summary: {
    data_quality_score: null,
    evaluated: false,
    records_checked: 0,
    issues_found: 0,
    latest_scan_at: null,
    total_issues: 0,
    open_issues: 0,
    critical: 0,
    high: 0,
    medium: 0,
    low: 0,
    resolved: 0,
    dismissed: 0,
  },
  by_category: [],
  by_department: [],
  trends: [],
  recent_scans: [],
  data_sources: ['data_quality_issues', 'data_quality_scans'],
}

const exports = [
  {
    id: 1,
    report_type: 'institution_performance',
    format: 'xlsx',
    status: 'COMPLETED',
    status_label: 'Completed',
    // The library renders the export's period label from these filters
    // ("October 2026" below); the test pins October so it is not dependent
    // on the current calendar month when run.
    filters: { year: 2026, month: 10 },
    file_name: 'institution-performance.xlsx',
    file_size: 2048,
    row_count: 12,
    error_message: null,
    error_line: null,
    requested_at: '2026-10-22T09:00:00Z',
    completed_at: '2026-10-22T09:01:00Z',
    expires_at: '2026-10-29T09:01:00Z',
  },
  {
    id: 2,
    report_type: 'data_quality',
    format: 'csv',
    status: 'QUEUED',
    status_label: 'Queued',
    filters: { academic_year_id: 3 },
    file_name: null,
    file_size: null,
    row_count: null,
    error_message: null,
    error_line: null,
    requested_at: '2026-10-22T09:30:00Z',
    completed_at: null,
    expires_at: null,
  },
]

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

function ok(data: unknown) {
  return Promise.resolve(json({ success: true, message: 'ok', data }))
}

function stubApi({
  summaryChange = summary.change,
  institutionError = false,
  institutionOverride = institution,
  employeeRowsOverride = employeeRows,
  departmentRowsOverride = departmentRows,
}: {
  summaryChange?: typeof summary.change | null
  institutionError?: boolean
  institutionOverride?: typeof institution
  employeeRowsOverride?: typeof employeeRows
  departmentRowsOverride?: typeof departmentRows
} = {}) {
  const fetchMock = vi.fn((input: RequestInfo | URL) => {
    const url = String(input)

    if (url.includes('/reports/management-summary')) return ok({ ...summary, change: summaryChange })
    if (url.includes('/reports/data-quality')) return ok(dataQuality)
    if (url.includes('/reports/exports')) return ok(exports)
    if (url.includes('/reports/institution')) {
      return institutionError
        ? Promise.resolve(json({ success: false, message: 'boom', errors: null }, 500))
        : ok(institutionOverride)
    }
    if (url.includes('/reports/employees/9')) return ok(employeeDetail)
    if (url.includes('/reports/employees')) return ok(employeeRowsOverride)
    if (url.includes('/reports/departments')) return ok(departmentRowsOverride)
    if (url.includes('/reports/department?')) return ok(departmentReport)
    if (url.includes('/reports/employee?')) return ok(employeeReport)
    if (url.includes('/reports/monthly')) return ok(monthly)
    if (url.includes('/reports/late')) return ok(late)
    if (url.includes('/reports/pending')) return ok(pending)
    if (url.includes('/reports/targets')) return ok(targets)
    if (url.includes('/analytics/performance-trend')) return ok(trend)
    if (url.includes('/hr/dashboard/options')) return ok(options)

    return ok(null)
  })

  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

const departmentReport = {
  report_type: 'department_performance',
  period: 'October 2026',
  period_key: { type: 'monthly', year, month },
  has_data: true,
  generated_at: '2026-10-22T10:00:00Z',
  source: 'computed',
  calculation_version: 'v1.0',
  department: { id: 1, name: 'College of Education', employee_count: 4 },
  metrics: {
    task_completion_rate: 80,
    report_completion_rate: 60,
    on_time_rate: 83.33,
    approval_rate: 66.67,
    target_achievement_rate: 90,
    average_productivity: 88,
    employees: 4,
    tasks_assigned: 10,
    tasks_completed: 8,
    reports_submitted: 6,
    late_reports: 1,
    rating: 'Very Good',
  },
}

const employeeReport = {
  report_type: 'employee_progress',
  period: 'October 2026',
  period_key: { type: 'monthly', year, month },
  has_data: true,
  generated_at: '2026-10-22T10:00:00Z',
  source: 'computed',
  calculation_version: 'v1.0',
  employee: {
    id: 9,
    name: 'Ana Santos',
    employee_number: 'EMP-009',
    department: 'College of Education',
    position: 'Faculty Member',
  },
  metrics: {
    tasks_assigned: 4,
    tasks_completed: 3,
    task_completion_rate: 75,
    reports_expected: 2,
    reports_submitted: 2,
    report_completion_rate: 100,
    on_time_submission_rate: null,
    approval_rate: 50,
    target_achievement_rate: null,
    targets_total: 0,
    targets_achieved: 0,
    productivity_score: 88,
    rating: 'Very Good',
  },
}

function authValue(role: string, permissions: string[]): AuthContextValue {
  return {
    user: {
      id: 1,
      name: role === 'employee' ? 'Ana Santos' : 'JPC Admin',
      email: 'user@jpc.edu.ph',
      status: 'active',
      role,
      employee_id: role === 'employee' ? 9 : null,
      permissions,
      last_login_at: null,
    },
    loading: false,
    error: null,
    login: vi.fn(),
    logout: vi.fn(),
    clearError: vi.fn(),
  }
}

function renderPage(
  role = 'admin',
  permissions = ['reports.view', 'analytics.view', 'hr.dashboard.view'],
) {
  return render(
    <MemoryRouter>
      <AuthContext.Provider value={authValue(role, permissions)}>
        <ReportingDashboardPage />
      </AuthContext.Provider>
    </MemoryRouter>,
  )
}

function panel(title: string): HTMLElement {
  return screen.getByRole('heading', { name: title }).closest('section') as HTMLElement
}

function card(label: string): HTMLElement {
  return screen.getByText(label).closest('div')?.parentElement as HTMLElement
}

async function openTab(name: string) {
  fireEvent.click(screen.getByRole('tab', { name }))
}

describe('ReportingDashboardPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the hub title and the six consolidated KPIs from the API', async () => {
    stubApi()
    renderPage()

    expect(await screen.findByText('Total Employees')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Reports & Analytics' })).toBeInTheDocument()
    expect(
      screen.getByText('One workspace for performance, submissions, targets and data quality'),
    ).toBeInTheDocument()

    expect(card('Total Employees').textContent).toContain('16')
    expect(card('Total Employees').textContent).toContain('14 active')
    expect(card('Task Completion Rate').textContent).toContain('70.0%')
    expect(card('Reports Submitted').textContent).toContain('5')
    expect(card('Late Reports').textContent).toContain('2')
    expect(card('Pending Reports').textContent).toContain('3')
    expect(card('Approval Rate').textContent).toContain('60.0%')
  })

  it('exposes every consolidated reporting category as a tab', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    for (const name of [
      'Overview',
      'Employee Performance',
      'Department Performance',
      'Monthly Performance',
      'Submission Monitoring',
      'Target Achievement',
      'Data Quality',
    ]) {
      expect(screen.getByRole('tab', { name })).toBeInTheDocument()
    }
  })

  it('keeps a zero denominator as N/A instead of zero percent', async () => {
    stubApi({
      institutionOverride: {
        ...institution,
        summary: {
          ...institution.summary,
          tasks_assigned: 0,
          tasks_completed: 0,
          task_completion_rate: null,
        },
      },
    })
    renderPage()

    await screen.findByText('Total Employees')

    expect(card('Task Completion Rate').textContent).toContain('N/A')
    expect(card('Task Completion Rate').textContent).not.toContain('0.0%')
    // Task completion, approval and target achievement all lack a denominator,
    // so the management panel shows N/A for each rather than a fake zero.
    expect(
      within(panel('Institutional performance summary')).getAllByText('N/A').length,
    ).toBeGreaterThan(0)
  })

  it('renders the management summary with the real period change', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    const management = panel('Management Summary')
    expect(
      within(management).getByText(/Executive Management Summary for October 2026/),
    ).toBeInTheDocument()
    expect(within(management).getByText('+4.5 ppt vs September 2026')).toBeInTheDocument()
    expect(within(management).getByText('Tasks Completed: 7 / 10 (70.0%)')).toBeInTheDocument()
  })

  it('states N/A when there is no prior-period baseline', async () => {
    stubApi({ summaryChange: null })
    renderPage()

    await screen.findByText('Total Employees')

    expect(
      within(panel('Management Summary')).getByText('N/A — no prior-period baseline'),
    ).toBeInTheDocument()
  })

  it('renders the employee performance table with the enriched report and target columns', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')
    await openTab('Employee Performance')

    const row = (await screen.findByText('Ana Santos')).closest('tr') as HTMLElement
    expect(within(row).getByText('EMP-009')).toBeInTheDocument()
    expect(within(row).getByText('2')).toBeInTheDocument()
    expect(within(row).getByText('80.0%')).toBeInTheDocument()
    expect(within(row).getByText('Very Good')).toBeInTheDocument()

    fireEvent.click(within(row).getByRole('button', { name: 'View' }))

    expect(await screen.findByText('Prepare module evaluation')).toBeInTheDocument()
  })

  it('shows N/A in employee and department rows when a denominator is zero', async () => {
    stubApi({
      employeeRowsOverride: [
        {
          ...employeeRows[0],
          total_tasks: 0,
          completed_tasks: 0,
          completion_rate: 0,
          total_targets: 0,
          achieved_targets: 0,
          target_achievement: 0,
        },
      ],
      departmentRowsOverride: [
        {
          ...departmentRows[0],
          total_tasks: 0,
          completed_tasks: 0,
          completion_rate: 0,
          reports_submitted: 0,
          approval_rate: 0,
          on_time_submission_rate: 0,
          total_targets: 0,
          achieved_targets: 0,
          target_achievement: 0,
        },
      ],
    })
    renderPage()

    await screen.findByText('Total Employees')

    await openTab('Employee Performance')
    const employeeRow = (await screen.findByText('Ana Santos')).closest('tr') as HTMLElement
    // Completion and target achievement both lack a denominator.
    expect(within(employeeRow).getAllByText('N/A').length).toBeGreaterThanOrEqual(2)
    expect(within(employeeRow).queryByText('0.0%')).not.toBeInTheDocument()

    await openTab('Department Performance')
    const departmentRow = (await screen.findByText('College of Education')).closest('tr') as HTMLElement
    // Completion, on-time submission, approval and target achievement.
    expect(within(departmentRow).getAllByText('N/A').length).toBeGreaterThanOrEqual(4)
    expect(within(departmentRow).queryByText('0.0%')).not.toBeInTheDocument()
  })

  it('renders department performance and target achievement from the shared endpoints', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    await openTab('Department Performance')
    expect(await screen.findByText('College of Education')).toBeInTheDocument()
    expect(screen.getByText('83.3%')).toBeInTheDocument()

    await openTab('Target Achievement')
    expect(await screen.findByRole('heading', { name: 'Target achievement summary' })).toBeInTheDocument()
    const targetRow = (await screen.findByText('Enrolment target')).closest('tr') as HTMLElement
    expect(within(targetRow).getByText('80.0%')).toBeInTheDocument()
    expect(within(targetRow).getByText('30')).toBeInTheDocument()
    expect(within(targetRow).getByText('In progress')).toBeInTheDocument()
  })

  it('distinguishes late, awaiting review, revision and unsubmitted reports', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')
    await openTab('Submission Monitoring')

    expect(await screen.findByRole('heading', { name: 'Late submissions' })).toBeInTheDocument()

    const lateRow = (await screen.findByText('Monthly accomplishment report')).closest('tr') as HTMLElement
    expect(within(lateRow).getByText('5')).toBeInTheDocument()
    expect(within(lateRow).getByText('Submitted')).toBeInTheDocument()

    const outstandingRow = screen.getByText('Quarterly narrative report').closest('tr') as HTMLElement
    expect(within(outstandingRow).getByText('Overdue — not submitted')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('tab', { name: 'Pending reports' }))

    expect(await screen.findByRole('heading', { name: 'Pending reports' })).toBeInTheDocument()

    const awaitingRow = (await screen.findByText('Awaiting approval report')).closest('tr') as HTMLElement
    expect(within(awaitingRow).getByText('Pending review')).toBeInTheDocument()
    expect(within(awaitingRow).queryByText('Not yet submitted')).not.toBeInTheDocument()

    const unsubmittedRow = screen.getByText('Never submitted report').closest('tr') as HTMLElement
    expect(within(unsubmittedRow).getByText('Not yet submitted')).toBeInTheDocument()

    const revisionRow = screen.getByText('Returned for revision report').closest('tr') as HTMLElement
    expect(within(revisionRow).getByText('Returned for revision')).toBeInTheDocument()
  })

  it('shows data quality as N/A until a completed scan has evaluated the institution', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')
    await openTab('Data Quality')

    expect(await screen.findByRole('heading', { name: 'Data quality score' })).toBeInTheDocument()
    expect(screen.getByText('Not evaluated')).toBeInTheDocument()
    expect(screen.getByText(/No completed scan has evaluated the institution yet/)).toBeInTheDocument()
  })

  it('applies scope, department and academic-year filters to the requests', async () => {
    const fetchMock = stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    fireEvent.change(screen.getByLabelText('Scope'), { target: { value: 'department' } })
    fireEvent.change(screen.getByLabelText('Department'), { target: { value: '1' } })
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    const departmentCalls = () =>
      fetchMock.mock.calls
        .map((call) => String(call[0]))
        .filter((url) => url.includes('/reports/department?'))

    await waitFor(() =>
      expect(departmentCalls().some((url) => url.includes('department_id=1'))).toBe(true),
    )
    expect(departmentCalls().some((url) => url.includes(`month=${month}`))).toBe(true)

    fireEvent.change(screen.getByLabelText('Academic year'), { target: { value: '3' } })

    expect(screen.queryByLabelText('Period')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() =>
      expect(departmentCalls().some((url) => url.includes('academic_year_id=3'))).toBe(true),
    )
    expect(screen.getAllByText('2026-2027').length).toBeGreaterThan(0)
  })

  it('only enables download for completed exports and reveals details on view', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    const library = panel('Report Library')
    expect(within(library).getByText('Institution Performance')).toBeInTheDocument()
    expect(within(library).getByText('Data Quality')).toBeInTheDocument()
    expect(within(library).getByText('October 2026')).toBeInTheDocument()
    expect(within(library).getByText('2026-2027')).toBeInTheDocument()

    expect(within(library).getAllByRole('button', { name: 'Download' })).toHaveLength(1)

    const queuedRow = within(library).getByText('Data Quality').closest('tr') as HTMLElement
    fireEvent.click(within(queuedRow).getByRole('button', { name: 'View' }))

    expect(within(library).getByText('Expires')).toBeInTheDocument()
  })

  it('shows the employee self-service KPIs without management or data-quality tabs', async () => {
    stubApi()
    renderPage('employee', ['reports.view'])

    expect(await screen.findByText('Total Employees')).toBeInTheDocument()
    expect(card('Task Completion Rate').textContent).toContain('75.0%')
    expect(card('Reports Submitted').textContent).toContain('2')
    expect(card('Late Reports').textContent).toContain('N/A')

    expect(screen.queryByRole('tab', { name: 'Data Quality' })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Employee Performance' })).not.toBeInTheDocument()
    expect(screen.queryByText('Management Summary')).not.toBeInTheDocument()
  })

  it('shows an error state when the institution report fails', async () => {
    stubApi({ institutionError: true })
    renderPage()

    expect(await screen.findAllByText('Something went wrong')).not.toHaveLength(0)
    expect(
      screen.getAllByText('The server ran into a problem. Please try again later.').length,
    ).toBeGreaterThan(0)
  })
})
