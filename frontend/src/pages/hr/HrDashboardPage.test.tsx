import { render, screen, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { AuthContext, type AuthContextValue } from '../../auth/useAuth'
import HrDashboardPage from './HrDashboardPage'

/**
 * The page is a pure function of the six `/api/v1/hr/*` payloads, so these tests
 * stub `fetch` the same way the other page tests do and assert the three rules the
 * dashboard can silently break:
 *
 *  - a `null` rate must render as "unmeasured", never as 0%;
 *  - a badge must only appear when the API sent one;
 *  - the four headline cards and the completion panel are the only summary
 *    figures, so a number is never printed twice.
 */

const kpis = {
  employees: { total: 24, active: 21, inactive: 3, departments: 4 },
  tasks: {
    total: 18,
    completed: 12,
    pending: 5,
    overdue: 2,
    month: {
      period: '2026-09',
      label: 'September 2026',
      total: 10,
      completed: 7,
      completion_rate: 70,
    },
  },
  reports: {
    total: 9,
    submitted: 8,
    submitted_this_month: 5,
    pending: 2,
    awaiting_action: 3,
    awaiting_submission: 1,
    late: 1,
    late_submitted: 1,
    late_outstanding: 0,
    approved: 6,
    revision_required: 0,
    rejected: 0,
    month: { period: '2026-09', label: 'September 2026' },
  },
  kpi: { completion_rate: 66.67, on_time_rate: null, approval_rate: 75 },
  trends: {
    reports: [
      { period: '2026-04', label: 'Apr', total: 1, late: 0, completed: 0, approved: 1 },
      { period: '2026-05', label: 'May', total: 2, late: 1, completed: 0, approved: 1 },
    ],
    tasks: [
      { period: '2026-04', label: 'Apr', total: 4, late: 0, completed: 2, approved: 0 },
      { period: '2026-05', label: 'May', total: 3, late: 0, completed: 1, approved: 0 },
    ],
  },
}

const options = {
  departments: [{ id: 1, name: 'College of Education' }],
  positions: [{ id: 1, name: 'Faculty Member' }],
  academic_years: [],
  years: [{ value: 2026, label: '2026' }],
  months: [{ value: 1, label: 'January' }],
  report_statuses: [{ value: 'approved', label: 'Approved' }],
  task_statuses: [{ value: 'assigned', label: 'Assigned' }],
  employment_statuses: ['active', 'inactive'],
}

const employees = {
  summary: { total: 2, with_overdue: 1, with_late_reports: 0 },
  items: [
    {
      id: 1,
      employee_id: 1,
      name: 'Ana Santos',
      employee_number: 'EMP-001',
      department: 'College of Education',
      position: 'Faculty Member',
      employment_status: 'active',
      assigned_tasks: 4,
      completed_tasks: 3,
      pending_tasks: 1,
      late_tasks: 1,
      completion_rate: 75,
      submitted_reports: 2,
      approved_reports: 1,
      late_reports: 0,
      on_time_rate: null,
      current_status: 'needs_attention',
    },
    {
      id: 2,
      employee_id: 2,
      name: 'Ben Cruz',
      employee_number: 'EMP-002',
      department: null,
      position: null,
      employment_status: 'active',
      assigned_tasks: 0,
      completed_tasks: 0,
      pending_tasks: 0,
      late_tasks: 0,
      completion_rate: null,
      submitted_reports: 0,
      approved_reports: 0,
      late_reports: 0,
      on_time_rate: null,
      current_status: 'on_track',
    },
  ],
  meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 },
}

const tasks = {
  summary: { total: 1, completed: 0, pending: 1, overdue: 1 },
  items: [
    {
      id: 10,
      task_assignment_id: 10,
      task_id: 5,
      task_name: 'Compile course syllabus',
      task_code: 'TASK-005',
      employee_id: 1,
      employee_name: 'Ana Santos',
      employee_number: 'EMP-001',
      department: 'College of Education',
      start_date: '2026-09-01',
      deadline: '2026-08-20',
      progress_percentage: 40,
      status: 'overdue',
      raw_status: 'in_progress',
      is_overdue: true,
      completed_at: null,
      report_status: 'under_review',
    },
  ],
  meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
}

const reports = {
  summary: { total: 1, submitted: 1, pending: 0, late: 1, approved: 0, revision_required: 0, rejected: 0, on_time_rate: null, approval_rate: 0 },
  items: [
    {
      id: 20,
      task_id: 5,
      title: 'Q3 Faculty Report',
      employee_id: 1,
      employee_name: 'Ana Santos',
      employee_number: 'EMP-001',
      department: 'College of Education',
      submission_date: '2026-08-25',
      submitted_at: '2026-08-25T10:00:00.000000Z',
      deadline: '2026-08-20',
      status: 'under_review',
      status_label: 'Under Review',
      is_late: true,
      days_late: 5,
      needs_revision: false,
    },
  ],
  meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
}

const activity = [
  {
    id: 1,
    activity_type: 'report',
    action: 'submitted',
    description: 'Ana Santos submitted Q3 Faculty Report',
    user_id: 1,
    user_name: 'Ana Santos',
    subject_type: 'Report',
    subject_id: 20,
    metadata: {},
    task_code: 'TASK-005',
    ip_address: null,
    user_agent: null,
    created_at: '2026-08-25T10:00:00.000000Z',
  },
]

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

function paged(data: unknown) {
  return json({ success: true, message: 'ok', data })
}

function stubApi(failDashboard = false) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)

      if (url.includes('/hr/dashboard/options')) return Promise.resolve(paged(options))
      if (url.includes('/hr/dashboard')) {
        return Promise.resolve(
          failDashboard
            ? json({ success: false, message: 'boom', errors: null }, 500)
            : paged(kpis),
        )
      }
      if (url.includes('/hr/employees')) return Promise.resolve(paged(employees))
      if (url.includes('/hr/monitoring')) return Promise.resolve(paged(tasks))
      if (url.includes('/hr/reports')) return Promise.resolve(paged(reports))
      if (url.includes('/activity-logs')) {
        return Promise.resolve(json({ success: true, message: 'ok', data: activity, meta: { current_page: 1, last_page: 1, per_page: 6, total: 1 } }))
      }
      if (url.includes('/health')) {
        return Promise.resolve(
          json({ success: true, message: 'ok', data: { status: 'ok', message: 'Healthy', timestamp: '2026-09-28T10:00:00Z' } }),
        )
      }

      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

function renderPage() {
  const auth: AuthContextValue = {
    user: {
      id: 1,
      name: 'JPC HR',
      email: 'hr@jpc.edu.ph',
      status: 'active',
      role: 'hr',
      employee_id: null,
      permissions: ['hr.dashboard.view'],
      last_login_at: null,
    },
    loading: false,
    error: null,
    login: vi.fn(),
    logout: vi.fn(),
    clearError: vi.fn(),
  }

  return render(
    <MemoryRouter>
      <AuthContext.Provider value={auth}>
        <HrDashboardPage />
      </AuthContext.Provider>
    </MemoryRouter>,
  )
}

/** Scope queries to one panel, since a name can appear in several tables. */
function panel(title: string): HTMLElement {
  return screen.getByRole('heading', { name: title }).closest('section') as HTMLElement
}

/** The stat card that carries the given label. */
function card(label: string): HTMLElement {
  return screen.getByText(label).closest('div')?.parentElement as HTMLElement
}

describe('HrDashboardPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('greets the signed-in user and shows the current date', async () => {
    stubApi()
    renderPage()

    expect(await screen.findByText(/Good day, JPC HR!/)).toBeInTheDocument()
    expect(screen.getByText(/Here.s what.s happening with your team today\./)).toBeInTheDocument()
  })

  it('renders the four headline cards from the API payload', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    const employees = card('Total Employees')
    expect(employees.textContent).toContain('24')
    expect(employees.textContent).toContain('21 active')

    const submitted = card('Reports Submitted')
    expect(submitted.textContent).toContain('5')
    expect(submitted.textContent).toContain('September 2026')

    const pending = card('Pending Reports')
    expect(pending.textContent).toContain('3')
    expect(pending.textContent).toContain('1 still to be submitted')

    const late = card('Late Reports')
    expect(late.textContent).toContain('1')
    expect(late.textContent).toContain('not submitted yet')
  })

  it('shows the task completion rate as one wide card with a progress bar', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    const card = panel('Task Completion Rate')
    expect(within(card).getByText('70.0%')).toBeInTheDocument()
    expect(within(card).getByText(/7 of 10 tasks completed/)).toBeInTheDocument()

    const bar = within(card).getByRole('progressbar')
    expect(bar.getAttribute('aria-valuenow')).toBe('70')
  })

  it('does not repeat a figure in a second card', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    // The cards that used to sit above the tables are gone: their numbers are
    // either in the headline cards or in the tables themselves.
    expect(screen.queryByText('Active employees')).not.toBeInTheDocument()
    expect(screen.queryByText('Departments')).not.toBeInTheDocument()
    expect(screen.queryByText('Assigned tasks')).not.toBeInTheDocument()
    expect(screen.queryByText('Overdue tasks')).not.toBeInTheDocument()
    expect(screen.queryByText('On-time submission rate')).not.toBeInTheDocument()
    expect(screen.queryByText('Report approval rate')).not.toBeInTheDocument()
    expect(screen.queryByText('Reports pending review')).not.toBeInTheDocument()
    expect(screen.queryByText('With overdue tasks')).not.toBeInTheDocument()
    expect(screen.queryByText('With late reports')).not.toBeInTheDocument()
  })

  it('renders every monitoring section from the API payloads', async () => {
    stubApi()
    renderPage()

    expect(await screen.findByText('Total Employees')).toBeInTheDocument()

    expect(screen.getByText('Employee progress overview')).toBeInTheDocument()
    expect(screen.getByText('Report monitoring')).toBeInTheDocument()
    expect(screen.getByText('Task progress')).toBeInTheDocument()
    expect(screen.getByText('Analytics & insights')).toBeInTheDocument()
    expect(screen.getByText('Recent activity')).toBeInTheDocument()
    expect(screen.getByText('System status')).toBeInTheDocument()

    // "Ana Santos"/"Ben Cruz" appear in both the employee and task tables.
    expect((await screen.findAllByText('Ana Santos')).length).toBeGreaterThan(0)
    expect(screen.getAllByText('Ben Cruz').length).toBeGreaterThan(0)
    expect(screen.getByText('Compile course syllabus')).toBeInTheDocument()
    // A report name can appear in both the monitoring table and the late panel.
    expect(within(panel('Report monitoring')).getByText('Q3 Faculty Report')).toBeInTheDocument()
    expect(screen.getByText('Ana Santos submitted Q3 Faculty Report')).toBeInTheDocument()
  })

  it('renders a null rate as unmeasured instead of 0%', async () => {
    stubApi()
    renderPage()

    // Ben has no tasks, so his completion rate must not read as 0%.
    await screen.findByText('Total Employees')
    const ben = within(panel('Employee progress overview')).getByText('Ben Cruz').closest('tr')
    expect(ben?.textContent).toContain('-')
    expect(ben?.textContent).not.toContain('0%')
  })

  it('withholds achievement reporting from HR', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    expect(screen.queryByText('Monthly employee achievement')).not.toBeInTheDocument()
    expect(screen.queryByText('Top achievement scores')).not.toBeInTheDocument()
    expect(screen.queryByText('Achievement Report')).not.toBeInTheDocument()
    expect(screen.queryByText('Achievements')).not.toBeInTheDocument()
  })

  it('surfaces an overdue assignment and a late report from the real flags', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Total Employees')

    const task = within(panel('Task progress')).getByText('Compile course syllabus').closest('tr')
    expect(task?.textContent).toContain('Overdue')
    expect(task?.textContent).toContain('Report under_review')

    const report = within(panel('Report monitoring')).getByText('Q3 Faculty Report').closest('tr')
    expect(report?.textContent).toContain('5d late')

    // The Late Reports panel repeats the same row under a "Days late" column.
    const late = panel('Late reports')
    expect(within(late).getByText('Days late')).toBeInTheDocument()
    expect(within(late).getByText('5 days late')).toBeInTheDocument()
  })

  it('shows an error state with a retry when the KPI call fails', async () => {
    stubApi(true)
    renderPage()

    expect(await screen.findByText('Something went wrong')).toBeInTheDocument()
    expect(screen.getByText('boom')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument()
  })
})
