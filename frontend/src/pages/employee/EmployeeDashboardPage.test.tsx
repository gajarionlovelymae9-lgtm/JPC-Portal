import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { AuthProvider } from '../../auth/AuthProvider'
import type { EmployeeDashboard } from '../../services/employeePortalService'
import EmployeeDashboardPage from './EmployeeDashboardPage'

/**
 * The dashboard is a pure function of GET /employee/dashboard, so these tests
 * stub `fetch` (including /auth/me for the real AuthProvider) and assert the
 * rules the page can silently break:
 *
 *  - a `null` rate must render as "unmeasured", never as 0%;
 *  - a section must only show a badge/achievement the API actually sent;
 *  - a brand-new employee gets meaningful empty states, not a broken grid.
 */

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const employee = {
  id: 4,
  name: 'Ana Santos',
  email: 'ana.santos@jpc.edu.ph',
  employee_no: '2025-0004',
  department: 'College of Engineering',
  position: 'Instructor',
}

const populated: EmployeeDashboard = {
  employee,
  generated_at: '2026-09-29T08:00:00.000000Z',
  kpis: {
    total_tasks: 6,
    completed_tasks: 3,
    pending_tasks: 1,
    in_progress_tasks: 2,
    submitted_tasks: 0,
    overdue_tasks: 1,
    completion_rate: 50,
    total_reports: 3,
    submitted_reports: 1,
    approved_reports: 2,
    draft_reports: 0,
    revision_required: 0,
    late_reports: 0,
    pending_reports: 1,
    approval_rate: 67,
    report_completion_rate: 25,
    on_time_rate: 100,
  },
  progress: { completed: 3, total: 6, percent: 50 },
  tasks: [
    {
      assignment_id: 10,
      task_id: 30,
      title: 'Compile First Quarter Targets',
      description: null,
      priority: 'high',
      status: 'in_progress',
      progress: 40,
      deadline: '2026-09-30T00:00:00.000000Z',
      deadline_date: '2026-09-30',
      assigned_at: '2026-09-01T00:00:00.000000Z',
      is_overdue: false,
      urgency: 'soon',
    },
    {
      assignment_id: 11,
      task_id: 31,
      title: 'Submit Annual Report',
      description: null,
      priority: 'medium',
      status: 'assigned',
      progress: 0,
      deadline: '2026-09-20T00:00:00.000000Z',
      deadline_date: '2026-09-20',
      assigned_at: '2026-09-01T00:00:00.000000Z',
      is_overdue: true,
      urgency: 'overdue',
    },
  ],
  reports: [
    {
      id: 55,
      title: 'Q3 Performance Report',
      task_id: 30,
      task_title: 'Compile First Quarter Targets',
      status: 'approved',
      review_status: null,
      submitted_at: '2026-09-02T00:00:00.000000Z',
      deadline: '2026-09-10T00:00:00.000000Z',
      is_late: false,
      needs_action: false,
      action: null,
      urgency: 'done',
    },
  ],
  action_required: [
    {
      kind: 'report',
      id: 56,
      title: 'Second Quarter Report',
      reason: 'Dean requested revisions before approval.',
      action: 'revise',
      action_label: 'Revise',
      severity: 'high',
    },
  ],
  deadlines: [
    {
      type: 'task',
      id: 10,
      task_id: 30,
      title: 'Compile First Quarter Targets',
      deadline: '2026-09-30T00:00:00.000000Z',
      deadline_date: '2026-09-30',
      days_remaining: 1,
      status: 'in_progress',
      urgency: 'tomorrow',
    },
  ],
  overdue: [
    {
      type: 'task',
      id: 11,
      task_id: 31,
      title: 'Submit Annual Report',
      deadline: '2026-09-20T00:00:00.000000Z',
      days_overdue: 9,
      status: 'assigned',
      urgency: 'overdue',
    },
  ],
  overdue_reports: [],
  monthly_progress: {
    year: 2026,
    month: 9,
    label: 'September 2026',
    tasks_assigned: 2,
    tasks_completed: 1,
    reports_submitted: 1,
    reports_approved: 1,
    late_submissions: 0,
    on_time_submissions: 1,
    completion_rate: 50,
  },
  annual_target: { year: 2026, target: 10, completed: 4, progress_percent: 40, label: '2026 Annual Targets' },
  targets: {
    items: [
      {
        id: 7,
        title: 'Complete 10 research outputs',
        description: 'Complete 10 research outputs',
        metric: 'Research outputs',
        target_value: 10,
        actual_value: 4,
        target_unit: 'Research outputs',
        progress: 40,
        status: 'in_progress',
        derived_status: 'in_progress',
        progress_source: 'tasks',
        start_date: '2026-01-01',
        end_date: '2026-12-31',
        breakdowns_count: 0,
        breakdowns_completed: 0,
        milestones_count: 0,
        milestones_achieved: 0,
        tasks_total: 2,
        tasks_completed: 1,
        notes_count: 0,
        pending_adjustments: 0,
      },
    ],
    summary: { total: 1, completed: 0, pending: 1 },
    annual: { year: 2026, target: 10, completed: 4, progress_percent: 40, label: '2026 Annual Targets' },
  },
  performance: {
    score: null,
    metrics: {
      task_completion: { label: 'Task Completion', value: 50, source: 'system' },
      on_time_submission: { label: 'On-Time Submission', value: 100, source: 'system' },
    },
    available: true,
    disclaimer: 'Metrics are based on your submitted work and are recalculated daily.',
  },
  achievements: {
    items: [
      {
        id: 3,
        name: 'On-Time Streak',
        description: 'Submitted 5 reports on time in a row.',
        icon: '🔥',
        criteria: '5 consecutive on-time reports',
        earned_at: '2026-09-20T00:00:00.000000Z',
      },
    ],
    badges: [
      {
        id: 3,
        name: 'On-Time Streak',
        description: 'Submitted 5 reports on time in a row.',
        icon: '🔥',
        criteria: '5 consecutive on-time reports',
        earned_at: '2026-09-20T00:00:00.000000Z',
      },
    ],
    monthly: [],
    total: 1,
  },
  schedule: [
    {
      id: 10,
      type: 'task',
      title: 'Compile First Quarter Targets',
      date: '2026-09-30',
      status: 'in_progress',
      is_overdue: false,
      link: '/employee/tasks/30',
    },
  ],
  recent_notifications: [
    {
      id: 90,
      type: 'approval',
      title: 'Report approved',
      message: 'Your Q3 Performance Report was approved.',
      is_read: false,
      read_at: null,
      created_at: '2026-09-02T00:00:00.000000Z',
    },
  ],
  unread_notifications: 1,
  caught_up: false,
}

function emptyDashboard(): EmployeeDashboard {
  return {
    ...populated,
    kpis: {
      ...populated.kpis,
      total_tasks: 0,
      completed_tasks: 0,
      completion_rate: null,
      on_time_rate: null,
    },
    progress: { completed: 0, total: 0, percent: null },
    tasks: [],
    reports: [],
    action_required: [],
    deadlines: [],
    overdue: [],
    overdue_reports: [],
    monthly_progress: { ...populated.monthly_progress, tasks_assigned: 0, tasks_completed: 0, completion_rate: null },
    targets: { items: [], summary: { total: 0, completed: 0, pending: 0 }, annual: populated.annual_target },
    performance: { score: null, metrics: {}, available: false, disclaimer: '' },
    achievements: { items: [], badges: [], monthly: [], total: 0 },
    schedule: [],
    recent_notifications: [],
    unread_notifications: 0,
    caught_up: true,
  }
}

interface StubOptions {
  dashboard?: EmployeeDashboard
  failDashboard?: boolean
  employeeId?: number | null
  permissions?: string[]
}

function stubApi({
  dashboard = populated,
  failDashboard = false,
  employeeId = 4,
  permissions = ['tasks.view', 'reports.view'],
}: StubOptions = {}) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)

      if (url.includes('/auth/me')) {
        return Promise.resolve(
          json({
            success: true,
            message: 'ok',
            data: {
              id: 9,
              name: 'Ana Santos',
              email: 'ana.santos@jpc.edu.ph',
              status: 'active',
              role: 'employee',
              employee_id: employeeId,
              permissions,
              last_login_at: null,
            },
          }),
        )
      }

      if (url.includes('/employee/dashboard')) {
        return Promise.resolve(
          failDashboard
            ? json({ success: false, message: 'Dashboard unavailable', errors: null }, 500)
            : json({ success: true, message: 'ok', data: dashboard }),
        )
      }

      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

function renderPage() {
  return render(
    <MemoryRouter>
      <AuthProvider>
        <EmployeeDashboardPage />
      </AuthProvider>
    </MemoryRouter>,
  )
}

function section(title: string): HTMLElement {
  return screen.getByRole('heading', { name: title }).closest('section') as HTMLElement
}

describe('EmployeeDashboardPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('greets the authenticated employee and renders every section heading', async () => {
    stubApi()
    renderPage()

    expect(await screen.findByRole('heading', { name: /Good (morning|afternoon|evening), Ana/ })).toBeInTheDocument()

    for (const title of [
      'My Tasks',
      'My Reports',
      'Upcoming Deadlines',
      'Monthly Progress',
      'My Targets',
      'Performance',
      'Recent Achievements',
      'My Calendar',
      'Notifications',
    ]) {
      expect(await screen.findByRole('heading', { name: title })).toBeInTheDocument()
    }
  })

  it('shows the KPI values returned by the API', async () => {
    stubApi()
    renderPage()

    const kpis = await screen.findByLabelText('Key performance indicators')

    expect(within(kpis).getByText('Assigned Tasks')).toBeInTheDocument()
    expect(within(kpis).getByText('On-Time Submission')).toBeInTheDocument()
    expect(within(kpis).getByText('100%')).toBeInTheDocument()
    expect(within(kpis).getByText('3 of 6 tasks')).toBeInTheDocument()
  })

  it('renders the action-required item returned by the API', async () => {
    stubApi()
    renderPage()

    const panel = await waitFor(() => section('Action Required'))
    expect(
      within(panel).getByText('Second Quarter Report'),
    ).toBeInTheDocument()
    expect(within(panel).getByRole('button', { name: 'Revise' })).toBeInTheDocument()
  })

  it('never invents a percentage when the backend has no data', async () => {
    stubApi({ dashboard: emptyDashboard() })
    renderPage()

    await screen.findByRole('heading', { name: 'My Tasks' })
    expect(screen.queryByText('0%')).not.toBeInTheDocument()
    expect(screen.getAllByText('—').length).toBeGreaterThan(0)
  })

  it('shows empty states and a caught-up confirmation for a new employee', async () => {
    stubApi({ dashboard: emptyDashboard() })
    renderPage()

    expect(await screen.findByText("You're all caught up!")).toBeInTheDocument()
    expect(screen.getByText('No assigned tasks yet.')).toBeInTheDocument()
    expect(screen.getByText('No reports yet.')).toBeInTheDocument()
    expect(screen.getByText('No achievements yet.')).toBeInTheDocument()
  })

  it('surfaces a load error and recovers when retry succeeds', async () => {
    const fetchMock = vi.fn((input: RequestInfo | URL) => {
      const url = String(input)

      if (url.includes('/auth/me')) {
        return Promise.resolve(
          json({
            success: true,
            message: 'ok',
            data: {
              id: 9,
              name: 'Ana Santos',
              email: 'ana.santos@jpc.edu.ph',
              status: 'active',
              role: 'employee',
              employee_id: 4,
              permissions: ['tasks.view', 'reports.view'],
              last_login_at: null,
            },
          }),
        )
      }

      if (url.includes('/employee/dashboard')) {
        return Promise.resolve(
          fetchMock.mock.calls.filter(call => String(call[0]).includes('/employee/dashboard')).length === 1
            ? json({ success: false, message: 'Dashboard unavailable', errors: null }, 500)
            : json({ success: true, message: 'ok', data: populated }),
        )
      }

      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    })

    vi.stubGlobal('fetch', fetchMock)
    const user = userEvent.setup()
    renderPage()

    expect(await screen.findByText('Unable to load your dashboard')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await screen.findByRole('heading', { name: 'My Tasks' })).toBeInTheDocument()
  })

  it('explains that no employee profile is linked instead of rendering an empty grid', async () => {
    stubApi({ employeeId: null })
    renderPage()

    expect(
      await screen.findByText('No employee profile is linked to this account'),
    ).toBeInTheDocument()
  })
})
