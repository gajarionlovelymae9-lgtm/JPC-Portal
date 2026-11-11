import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import type { AdminExecutiveDashboardData } from './services/adminPortalService'
import type { EmployeeDashboard } from './services/employeePortalService'
import type { HealthResponse } from './types/api'
import type { UserProfile } from './types/auth'

const healthPayload: HealthResponse = {
  success: true,
  message: 'JPC Portal API is running',
  data: {
    application: 'ok',
    database: 'ok',
    api: 'ok',
    environment: 'local',
    api_version: 'v1',
    timestamp: '2026-09-14T00:00:00+00:00',
  },
}

const adminProfile: UserProfile = {
  id: 1,
  name: 'JPC Administrator',
  email: 'admin@jpc.local',
  status: 'active',
  role: 'admin',
  permissions: [
    'dashboard.view',
    'employees.view',
    'departments.view',
    'planning.view',
    'targets.view',
    'tasks.view',
    'reports.view',
    'analytics.view',
    'achievements.view',
    'notifications.view',
    'settings.view',
    // Granted to every admin by Permissions::forAdmin(); the new governance
    // sidebar section is gated on these.
    'users.view',
    'activity_logs.view',
  ],
  last_login_at: null,
}

const employeeProfile: UserProfile = {
  ...adminProfile,
  id: 2,
  name: 'JPC Employee',
  email: 'employee@jpc.local',
  role: 'employee',
  employee_id: 4,
  permissions: [
    'dashboard.view',
    'employees.view',
    'targets.view',
    'tasks.view',
    'reports.view',
    'achievements.view',
    'notifications.view',
  ],
}

const adminExecutiveDashboard: AdminExecutiveDashboardData = {
  institution: {
    name: 'John Paul College',
    system_name: 'JPC Portal',
    academic_year: '2026',
    academic_year_status: 'active',
    current_term: 'Term 1',
  },
  kpis: {
    total_employees: 42,
    active_employees: 40,
    total_departments: 8,
    total_tasks: 120,
    completed_tasks: 84,
    pending_tasks: 20,
    overdue_tasks: 6,
    submitted_reports: 60,
    pending_reviews: 7,
    approved_reports: 45,
    revision_required: 5,
    completion_rate: 70,
    on_time_rate: 88,
    approval_rate: 75,
    institution_progress: 72,
    in_progress_tasks: 24,
    not_started_tasks: 6,
    late_tasks: 4,
    reports_pending: 6,
    reports_approved: 45,
    reports_needs_revision: 5,
    reports_rejected: 3,
    reports_under_review: 7,
    reports_late: 4,
    avg_kpi_achievement: 68.5,
    ai_validation_score: 91.5,
    department_performance: 74,
  },
  status_distribution: {
    tasks: { completed: 84, in_progress: 24, pending: 6, overdue: 6 },
    reports: { approved: 45, under_review: 7, revision_required: 5, rejected: 3, submitted: 60 },
  },
  department_performance: [
    {
      id: 1,
      name: 'Computer Science',
      code: 'CS',
      employees_count: 12,
      tasks_count: 40,
      completed_count: 32,
      overdue_count: 2,
      reports_count: 20,
      completion_rate: 80,
      rank: 1,
    },
  ],
  monthly_achievements: [
    {
      employee_id: 1,
      name: 'Ava Reyes',
      department_name: 'Computer Science',
      position: 'Faculty',
      completed_tasks: 18,
      completion_rate: 95,
      badge: 'Top Performer',
      icon: '🏆',
    },
  ],
  critical_alerts: {
    overdue_tasks: [],
    pending_reviews_count: 7,
    revision_count: 5,
    open_escalations_count: 2,
    ocr_failed_count: 1,
  },
  ai_executive_summary: {
    available: false,
    generated: false,
    message: 'No AI insight available',
    items: [],
    total: 0,
    validation_score: 91.5,
    validation_is_sample_data: true,
    provider_status: {
      configured_provider: 'fake',
      model: 'development-sample',
      is_sample_data: true,
      live: false,
      note: 'Sample data only',
    },
  },
  operational_digest: {
    available: true,
    text: 'Institution progress is on track for Term 1.',
    key_accomplishments: ['84 deliverables completed'],
    areas_needing_attention: ['6 overdue deliverables'],
    recommendations: ['Review overdue tasks with department heads'],
    disclaimer: 'Computed from live records; not AI output.',
  },
}

/**
 * Minimal but complete employee dashboard payload. It exists so the app-level
 * tests can prove the whole chain (role routing -> page -> API) without
 * inventing numbers in the page itself.
 */
const employeeDashboard: EmployeeDashboard = {
  employee: {
    id: 4,
    name: 'Ana Santos',
    email: 'employee@jpc.local',
    employee_no: '2025-0004',
    department: 'College of Engineering',
    position: 'Instructor',
  },
  generated_at: '2026-09-29T08:00:00.000000Z',
  kpis: {
    total_tasks: 4,
    completed_tasks: 1,
    pending_tasks: 1,
    in_progress_tasks: 1,
    submitted_tasks: 1,
    overdue_tasks: 1,
    completion_rate: 25,
    total_reports: 2,
    submitted_reports: 1,
    approved_reports: 1,
    draft_reports: 0,
    revision_required: 0,
    late_reports: 0,
    pending_reports: 0,
    approval_rate: 100,
    report_completion_rate: 50,
    on_time_rate: 100,
  },
  progress: { completed: 1, total: 4, percent: 25 },
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
  ],
  reports: [],
  action_required: [],
  deadlines: [],
  overdue: [],
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
  annual_target: { year: 2026, target: 12, completed: 5, progress_percent: 42, label: '2026 Target' },
  targets: {
    items: [],
    summary: { total: 0, completed: 0, pending: 0 },
    annual: { year: 2026, target: 12, completed: 5, progress_percent: 42, label: '2026 Target' },
  },
  performance: {
    score: null,
    metrics: {},
    available: false,
    disclaimer: 'Performance metrics appear once reports are reviewed.',
  },
  achievements: { items: [], badges: [], monthly: [], total: 0 },
  schedule: [],
  recent_notifications: [],
  unread_notifications: 0,
  caught_up: true,
}

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

/**
 * Mirrors the live calendar payload, including the three categories the schema
 * cannot supply. If the backend ever stops reporting them as unsupported this
 * fixture must be updated deliberately, not silently.
 */
const adminCalendar = {
  events: [
    {
      id: 'task-7',
      type: 'task_deadline',
      type_label: 'Task Deadline',
      title: 'Submit Registrar accreditation evidence',
      employee: 'Ava Reyes',
      department: 'Computer Science',
      date: '2026-09-18',
      time: null,
      description: 'Assignment due date.',
      status: 'open',
      link: '/admin/task-monitoring',
    },
  ],
  sources: [
    {
      type: 'task_deadline',
      label: 'Task Deadlines',
      supported: true,
      source: 'task_assignments.due_date with fallback to tasks.due_date',
    },
    {
      type: 'meeting',
      label: 'Meetings',
      supported: false,
      source: null,
      note: 'No meetings table exists in this schema, so no meeting events can be listed.',
    },
  ],
  range: { from: '2026-09-01', to: '2026-09-30' },
}

/**
 * `ai_insights` is empty in this installation and the configured provider is the
 * `fake` driver, so the payload must be rendered as unavailable sample data.
 * Mirrors the current AdminInsightService payload shape.
 */
const adminAiValidationSample = {
  total: 2,
  avg_score: 91.5,
  avg_confidence: 88.0,
  passed: 1,
  warning: 1,
  failed: 0,
  present: true,
}

const adminAiInsights = {
  insights: [],
  available: false,
  message: 'No AI insight available',
  generated: false,
  counts: {
    total: 0,
    by_type: [{ type: 'performance', label: 'Performance', total: 0 }],
  },
  reports_analyzed: 0,
  validation: {
    has_live_data: false,
    total: 0,
    avg_score: null,
    avg_confidence: null,
    passed: 0,
    warning: 0,
    failed: 0,
    score_scale: '0..1 fraction, exposed as 0..100 here',
    is_sample_data: true,
    provenance_note:
      'Development/unverified validation rows (for example provider "jpc.sample") are stored but excluded from the live KPIs above.',
    sample: adminAiValidationSample,
  },
  recent_runs: [],
  sample_runs: [],
  sample_data: {
    present: true,
    validation: adminAiValidationSample,
    analysis_runs: [],
  },
  provider_status: {
    configured_provider: 'fake',
    model: 'development-sample',
    endpoint_configured: false,
    api_key_configured: false,
    configured: false,
    connected: false,
    live: false,
    data_source: 'no_live_data',
    data_source_label: 'No Data',
    last_successful_ai_request: null,
    last_successful_analysis: null,
    is_sample_data: true,
    sample_data_present: true,
    note: 'The configured AI provider is not a live engine.',
  },
  filters_applied: {},
}

const adminPlatformStatus = {
  checked_at: '2026-09-26T09:00:00+00:00',
  overall: 'ok',
  environment: 'local',
  api_version: 'v1',
  services: [
    { key: 'api', label: 'REST API', state: 'connected', detail: 'Version v1' },
    { key: 'database', label: 'Database', state: 'connected', detail: 'acadtrack_db' },
  ],
  architecture: [
    { key: 'frontend', label: 'Frontend', value: 'React + Vite SPA (TypeScript)' },
    { key: 'backend', label: 'Backend', value: 'Laravel 12.0.0 (PHP 8.4.0)' },
  ],
  last_backup: null,
}

function stubApi(profile: UserProfile | null) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/api/v1/auth/me')) {
        return Promise.resolve(
          profile
            ? json({ success: true, message: 'Authenticated user', data: profile })
            : json({ success: false, message: 'Unauthenticated' }, 401),
        )
      }
      if (url.includes('/api/v1/auth/logout')) {
        return Promise.resolve(json({ success: true, message: 'Logout successful', data: null }))
      }
      if (url.includes('/api/v1/employee/dashboard')) {
        return Promise.resolve(
          json({ success: true, message: 'Employee dashboard loaded', data: employeeDashboard }),
        )
      }
      if (url.includes('/api/v1/health')) {
        return Promise.resolve(json(healthPayload))
      }
      if (url.includes('/api/v1/admin/executive-dashboard')) {
        return Promise.resolve(
          json({ success: true, message: 'Executive dashboard loaded', data: adminExecutiveDashboard }),
        )
      }
      if (url.includes('/api/v1/admin/platform-status')) {
        return Promise.resolve(
          json({ success: true, message: 'Platform status loaded', data: adminPlatformStatus }),
        )
      }
      if (url.includes('/api/v1/admin/calendar')) {
        return Promise.resolve(json({ success: true, message: 'Calendar loaded', data: adminCalendar }))
      }
      if (url.includes('/api/v1/admin/ai-insight-options')) {
        return Promise.resolve(
          json({
            success: true,
            message: 'Options loaded',
            data: { types: [], departments: [{ id: 1, name: 'Computer Science' }] },
          }),
        )
      }
      if (url.includes('/api/v1/admin/ai-insights')) {
        return Promise.resolve(json({ success: true, message: 'AI insights loaded', data: adminAiInsights }))
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

function renderApp(initialEntry = '/dashboard', profile: UserProfile | null = adminProfile) {
  stubApi(profile)
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <App />
    </MemoryRouter>,
  )
}

/**
 * The persistent Header also renders an `<h1>` with the navigation label, so
 * page-level heading queries must be scoped to the routed content region.
 */
async function findPageHeading(name: string | RegExp) {
  const main = await screen.findByRole('main')
  return within(main).findByRole('heading', { level: 1, name })
}

describe('App', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the application shell for an authenticated admin', async () => {
    renderApp()

    expect(await findPageHeading('John Paul College · Executive Dashboard')).toBeInTheDocument()
    // The brand shows in the sidebar, and in the header on the narrow screens
    // where the sidebar is a drawer.
    expect(screen.getAllByText('JPC Portal').length).toBeGreaterThan(0)
    expect(screen.getByRole('navigation', { name: /main navigation/i })).toBeInTheDocument()
  })

  it('renders sidebar navigation links for an admin', async () => {
    renderApp()

    await findPageHeading('John Paul College · Executive Dashboard')

    const nav = screen.getByRole('navigation', { name: /main navigation/i })

    // The admin portal groups its links behind collapsible headers: on load
    // only the group owning the active route opens, everything else stays
    // folded until its header is clicked.
    expect(within(nav).getByRole('button', { name: 'Dashboard' })).toHaveAttribute(
      'aria-expanded',
      'true',
    )
    expect(within(nav).queryByRole('link', { name: 'KPI Targets' })).not.toBeInTheDocument()

    const user = userEvent.setup()
    // Two passes: the first opens the top-level groups, the second opens the
    // sub-groups (Exports, Feedback) revealed by the first pass.
    for (let pass = 0; pass < 2; pass += 1) {
      const collapsed = within(nav)
        .getAllByRole('button')
        .filter((toggle) => toggle.getAttribute('aria-expanded') === 'false')
      for (const toggle of collapsed) {
        await user.click(toggle)
      }
    }

    expect(within(nav).getByRole('button', { name: 'Dashboard' })).toHaveAttribute(
      'aria-expanded',
      'true',
    )

    expect(nav).toHaveTextContent('Executive Dashboard')
    expect(nav).toHaveTextContent('Performance Overview')
    expect(nav).toHaveTextContent('Executive Report')
    expect(nav).toHaveTextContent('Dept Comparison')
    expect(nav).toHaveTextContent('Employee Progress')
    expect(nav).toHaveTextContent('KPI Targets')
    expect(nav).toHaveTextContent('Task Monitoring')
    expect(nav).toHaveTextContent('Report Monitoring')
    expect(nav).toHaveTextContent('Institutional Calendar')
    expect(nav).toHaveTextContent('People Management')
    expect(nav).toHaveTextContent('Audit Trail')
    expect(nav).toHaveTextContent('Platform Status')
    expect(nav).toHaveTextContent('Tasks')
    expect(nav).toHaveTextContent('Institution Reports')
    expect(nav).toHaveTextContent('Reports')
    expect(nav).toHaveTextContent('Analytics')
    expect(nav).toHaveTextContent('Achievement & Badges')
    expect(nav).toHaveTextContent('Messages')
    expect(nav).toHaveTextContent('Announcements')
    expect(nav).toHaveTextContent('Notifications')
    expect(nav).toHaveTextContent('AI & OCR Monitor')
    expect(nav).toHaveTextContent('Settings')

    // The legacy /achievements, /activity-logs and /notifications entries used
    // to render beside their admin replacements, producing duplicate menus and
    // two identically-labelled "Notifications" links in one section.
    expect(screen.queryByRole('link', { name: 'Achievements' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Activity Logs' })).not.toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Notifications' })).toHaveLength(1)

    expect(screen.queryByRole('link', { name: 'Departments' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Planning' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Targets' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'My Tasks' })).not.toBeInTheDocument()
  })

  it('shows only employee portal links to an employee', async () => {
    renderApp('/dashboard', employeeProfile)

    await screen.findByRole('heading', { level: 1, name: 'My Dashboard' })

    const nav = screen.getByRole('navigation', { name: /main navigation/i })
    expect(nav).toHaveTextContent('My Dashboard')
    expect(nav).toHaveTextContent('My Tasks')
    expect(nav).toHaveTextContent('My Reports')
    expect(nav).toHaveTextContent('My Calendar')
    expect(nav).toHaveTextContent('My Achievements')
    expect(nav).toHaveTextContent('My Profile')
    expect(nav).toHaveTextContent('Change Password')

    // Management and institution-wide surfaces must stay hidden from employees.
    expect(screen.queryByRole('link', { name: 'Planning' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Analytics' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Settings' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Departments' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Employees' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'HR Dashboard' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Admin Dashboard' })).not.toBeInTheDocument()
  })

  it('redirects the root path to the dashboard of the signed-in role', async () => {
    const admin = renderApp('/')
    expect(await findPageHeading('John Paul College · Executive Dashboard')).toBeInTheDocument()
    admin.unmount()

    renderApp('/', employeeProfile)
    expect(await screen.findByRole('heading', { level: 1, name: 'My Dashboard' })).toBeInTheDocument()
  })

  it('sends an employee away from the legacy foundation page path', async () => {
    // Regression guard for the reported bug: an authenticated employee typed
    // into (or was sent to) /dashboard and saw the JPC Portal welcome page.
    renderApp('/dashboard', employeeProfile)

    expect(await screen.findByRole('heading', { level: 1, name: 'My Dashboard' })).toBeInTheDocument()
    expect(screen.queryByText('Welcome to JPC Portal')).not.toBeInTheDocument()
    expect(screen.queryByText('Platform architecture')).not.toBeInTheDocument()

    // The real dashboard renders the payload returned by the API.
    expect(await screen.findByText('Assigned Tasks')).toBeInTheDocument()
    expect(screen.getByText('Compile First Quarter Targets')).toBeInTheDocument()
  })

  it('keeps the platform page for a role this build does not know', async () => {
    // Only unrecognised roles may still see the foundation page; it must not
    // redirect to itself, so the fallback stays non-looping.
    const auditor: UserProfile = { ...employeeProfile, role: 'auditor' }

    renderApp('/dashboard', auditor)

    expect(await screen.findByText('Welcome to JPC Portal')).toBeInTheDocument()
  })

  it('redirects the legacy /reports route to the unified Reports & Analytics hub', async () => {
    // `/reports` and `/reporting` were merged into one Reports & Analytics
    // module; the legacy URL now resolves to the same unified page.
    renderApp('/reports')

    expect(await findPageHeading('Reports & Analytics')).toBeInTheDocument()
  })

  it('shows an access denied message when the user lacks permissions', async () => {
    renderApp('/settings', employeeProfile)

    expect(
      await screen.findByRole('heading', { name: 'Access denied' }),
    ).toBeInTheDocument()
  })

  it('redirects unauthenticated users to the login page', async () => {
    renderApp('/dashboard', null)

    expect(
      await screen.findByRole('heading', { name: 'Sign in' }),
    ).toBeInTheDocument()
  })

  it('signs the user out and returns to login', async () => {
    const user = userEvent.setup()
    renderApp()

    await findPageHeading('John Paul College · Executive Dashboard')

    await user.click(screen.getByRole('button', { name: 'Sign out' }))

    expect(
      await screen.findByRole('heading', { name: 'Sign in' }),
    ).toBeInTheDocument()
  })

  it('renders a not-found page for unknown routes', async () => {
    renderApp('/unknown-route')

    expect(
      await screen.findByRole('heading', { name: 'Page not found' }),
    ).toBeInTheDocument()
  })

  it('asks the employee dashboard API for the signed-in employee only', async () => {
    // The dashboard is a pure function of the session: no employee id is sent
    // by the client, so one employee can never see another employee's records.
    const urls: string[] = []

    vi.stubGlobal(
      'fetch',
      vi.fn((input: RequestInfo | URL) => {
        const url = String(input)
        urls.push(url)

        if (url.includes('/api/v1/auth/me')) {
          return Promise.resolve(
            json({ success: true, message: 'Authenticated user', data: employeeProfile }),
          )
        }
        if (url.includes('/api/v1/employee/dashboard')) {
          return Promise.resolve(
            json({ success: true, message: 'Employee dashboard loaded', data: employeeDashboard }),
          )
        }
        return Promise.resolve(json({ success: true, message: 'ok', data: null }))
      }),
    )

    render(
      <MemoryRouter initialEntries={['/employee/dashboard']}>
        <App />
      </MemoryRouter>,
    )

    expect(await screen.findByText('Compile First Quarter Targets')).toBeInTheDocument()

    const dashboardCall = urls.find((url) => url.includes('/api/v1/employee/dashboard'))
    expect(dashboardCall).toBeDefined()
    expect(dashboardCall).not.toContain('employee_id=')
  })

  it('renders the executive dashboard for an admin', async () => {
    renderApp('/admin/dashboard')

    expect(
      await findPageHeading('John Paul College · Executive Dashboard'),
    ).toBeInTheDocument()
    expect(screen.getByText('2026 (active) · Term 1')).toBeInTheDocument()

    // Every headline number comes from the API payload, not from the component.
    expect(screen.getByText('Total Employees')).toBeInTheDocument()
    expect(screen.getByText('42')).toBeInTheDocument()
    expect(screen.getByText('40 active')).toBeInTheDocument()
    expect(screen.getByText('Task Completion')).toBeInTheDocument()
    expect(screen.getByText('On-Time Rate')).toBeInTheDocument()
    expect(screen.getByText('Overdue Tasks')).toBeInTheDocument()
    expect(screen.getByText('Pending Reviews')).toBeInTheDocument()
    expect(screen.getByText('Reports Submitted')).toBeInTheDocument()
  })

  it('separates the operational digest from AI output on the executive dashboard', async () => {
    renderApp('/admin/dashboard')

    await findPageHeading('John Paul College · Executive Dashboard')

    // The digest is deterministic and must be labelled as not-AI.
    expect(screen.getByText('Operational Digest')).toBeInTheDocument()
    expect(screen.getByText('Computed from live records — not AI output')).toBeInTheDocument()
    expect(screen.getByText('Key accomplishments')).toBeInTheDocument()
    expect(screen.getByText('84 deliverables completed')).toBeInTheDocument()
    expect(screen.getByText('Needs attention')).toBeInTheDocument()
    expect(screen.getByText('Computed from live records; not AI output.')).toBeInTheDocument()

    // AI Executive Summary card removed from Admin Dashboard; no AI insight card expected.
  })

  it('renders every new admin section from the API', async () => {
    const sections: Array<[string, string | RegExp]> = [
      ['/admin/performance', 'Performance Overview'],
      ['/admin/kpi-targets', 'KPI Targets'],
      ['/admin/employee-progress', 'Employee Progress'],
      ['/admin/task-monitoring', 'Task Monitoring'],
      ['/admin/report-monitoring', 'Report Monitoring'],
      ['/admin/calendar', 'Institutional Calendar'],
      ['/admin/achievements', 'Achievement & Badges'],
      ['/admin/notification-centre', /^Notifications$/],
      ['/admin/audit-trail', 'Audit Trail'],
      ['/admin/people', 'People Management'],
      ['/admin/users', 'People Management'],
      ['/admin/ai-insights', /JPC AIDE.*AI Insights|AI Insights/],
      ['/admin/platform-status', 'Platform Status'],
    ]

    for (const [path, heading] of sections) {
      const { unmount } = renderApp(path)
      expect(await findPageHeading(heading), path).toBeInTheDocument()
      unmount()
    }
  })

  it('reports calendar categories the schema cannot provide', async () => {
    renderApp('/admin/calendar')

    await findPageHeading('Institutional Calendar')

    // No meetings/institutional-event/holiday tables exist, so the backend marks
    // them unsupported and the page must say so instead of rendering a blank list.
    await waitFor(() => {
      expect(screen.getByText('Unavailable calendar categories')).toBeInTheDocument()
    })
    expect(
      screen.getByText('No meetings table exists in this schema, so no meeting events can be listed.'),
    ).toBeInTheDocument()
  })

  it('shows the honest AI empty state on the AI insights page', async () => {
    renderApp('/admin/ai-insights')

    await findPageHeading('JPC AIDE - AI Insights')

    // The fixture has an empty `ai_insights` table and sample validation rows.
    await waitFor(() => {
      expect(screen.getByText('No AI insight available')).toBeInTheDocument()
    })
    // Live KPIs must stay at zero even though sample rows exist.
    expect(screen.getAllByText('0').length).toBeGreaterThan(0)
    // Sample data is labelled and the provider is honestly reported as not connected.
    expect(screen.getByText('Development Sample Data Notice')).toBeInTheDocument()
    expect(screen.getByText('Present (excluded)')).toBeInTheDocument()
    expect(screen.getByText('Not Connected')).toBeInTheDocument()
  })

  it('blocks employees from the management dashboard', async () => {
    renderApp('/admin/dashboard', employeeProfile)

    expect(await screen.findByRole('heading', { name: 'Access denied' })).toBeInTheDocument()
  })

  it('downloads a real export file from the export endpoint', async () => {
    // The export endpoints stream a file rather than the JSON envelope, so they
    // need their own response shape plus the object-URL plumbing jsdom lacks.
    const urls: string[] = []
    const createObjectURL = vi.fn(() => 'blob:jpc-export')
    const revokeObjectURL = vi.fn()

    vi.stubGlobal('URL', { ...URL, createObjectURL, revokeObjectURL })
    vi.stubGlobal(
      'fetch',
      vi.fn((input: RequestInfo | URL) => {
        const url = String(input)
        urls.push(url)

        if (url.includes('/api/v1/auth/me')) {
          return Promise.resolve(
            json({ success: true, message: 'Authenticated user', data: adminProfile }),
          )
        }
        if (url.includes('/reports/export/employee-performance')) {
          return Promise.resolve({
            ok: true,
            status: 200,
            headers: {
              get: (name: string) =>
                name.toLowerCase() === 'content-disposition'
                  ? 'attachment; filename="Employee_Performance_2026-09-26.csv"'
                  : null,
            },
            blob: async () => new Blob(['rank,employee_name\n1,Ada\n'], {
              type: 'text/csv; charset=UTF-8',
            }),
            json: async () => ({}),
          })
        }

        return Promise.resolve(json({ success: true, message: 'ok', data: null }))
      }),
    )

    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})

    render(
      <MemoryRouter initialEntries={['/admin/employee-progress']}>
        <App />
      </MemoryRouter>,
    )

    const exportButton = await screen.findByRole('button', { name: /export employee performance/i })
    await userEvent.click(exportButton)

    await waitFor(() => {
      expect(urls.some((url) => url.includes('/api/v1/reports/export/employee-performance?format=csv'))).toBe(true)
    })

    expect(createObjectURL).toHaveBeenCalled()
    expect(click).toHaveBeenCalled()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:jpc-export')

    click.mockRestore()
  })
})