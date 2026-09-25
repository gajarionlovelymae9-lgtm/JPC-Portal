import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import type { AdminExecutiveDashboardData } from './services/adminPortalService'
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
  ],
  last_login_at: null,
}

const employeeProfile: UserProfile = {
  ...adminProfile,
  id: 2,
  name: 'JPC Employee',
  email: 'employee@jpc.local',
  role: 'employee',
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
    text: 'Institution progress is on track for Term 1.',
    key_accomplishments: ['84 deliverables completed'],
    areas_needing_attention: ['6 overdue deliverables'],
    recommendations: ['Review overdue tasks with department heads'],
    disclaimer: 'AI-generated summary; verify before decisions.',
  },
}

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
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
      if (url.includes('/api/v1/health')) {
        return Promise.resolve(json(healthPayload))
      }
      if (url.includes('/api/v1/admin/executive-dashboard')) {
        return Promise.resolve(
          json({ success: true, message: 'Executive dashboard loaded', data: adminExecutiveDashboard }),
        )
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

describe('App', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the application shell for an authenticated admin', async () => {
    renderApp()

    expect(await screen.findByText('Database: Connected')).toBeInTheDocument()
    expect(screen.getByText('JPC Portal')).toBeInTheDocument()
    expect(screen.getByRole('navigation', { name: /main navigation/i })).toBeInTheDocument()
    expect(screen.getByText('Welcome to JPC Portal')).toBeInTheDocument()
  })

  it('renders sidebar navigation links for an admin', async () => {
    renderApp()

    await screen.findByText('Database: Connected')

    const nav = screen.getByRole('navigation', { name: /main navigation/i })
    expect(nav).toHaveTextContent('Executive Dashboard')
    expect(nav).toHaveTextContent('Executive Report')
    expect(nav).toHaveTextContent('Dept Comparison')
    expect(nav).toHaveTextContent('Employees')
    expect(nav).toHaveTextContent('Tasks')
    expect(nav).toHaveTextContent('Institution Reports')
    expect(nav).toHaveTextContent('Reports')
    expect(nav).toHaveTextContent('Analytics')
    expect(nav).toHaveTextContent('Achievements')
    expect(nav).toHaveTextContent('Messages')
    expect(nav).toHaveTextContent('Announcements')
    expect(nav).toHaveTextContent('Notifications')
    expect(nav).toHaveTextContent('AI & OCR Monitor')
    expect(nav).toHaveTextContent('Settings')

    expect(screen.queryByRole('link', { name: 'Departments' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Planning' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Targets' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'My Tasks' })).not.toBeInTheDocument()
  })

  it('hides sidebar links the employee cannot access', async () => {
    renderApp('/dashboard', employeeProfile)

    await screen.findByText('Database: Connected')

    const nav = screen.getByRole('navigation', { name: /main navigation/i })
    expect(nav).toHaveTextContent('Dashboard')
    expect(nav).toHaveTextContent('Tasks')
    expect(screen.queryByRole('link', { name: 'Planning' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Analytics' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Settings' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Departments' })).not.toBeInTheDocument()
  })

  it('redirects the root path to the dashboard', async () => {
    renderApp('/')

    expect(await screen.findByText('Welcome to JPC Portal')).toBeInTheDocument()
  })

  it('renders placeholder pages for future modules', async () => {
    const first = renderApp('/employees')

    expect(
      await first.findByText(
        'Employee management will be implemented in a future phase.',
      ),
    ).toBeInTheDocument()
    expect(first.getByText('Coming soon')).toBeInTheDocument()

    first.unmount()

    const second = renderApp('/reports')
    expect(
      await second.findByText(
        'Task completion, per-employee and per-department performance, attainment and top performers across the organisation.',
      ),
    ).toBeInTheDocument()
    expect(
      second.getByRole('heading', { level: 2, name: 'Reports' }),
    ).toBeInTheDocument()
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

    await screen.findByText('Welcome to JPC Portal')

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

  it('displays the backend health status from the API', async () => {
    renderApp()

    expect(await screen.findByText('Backend: Connected')).toBeInTheDocument()
    expect(await screen.findByText('Database: Connected')).toBeInTheDocument()
    expect(await screen.findByText('API: Connected')).toBeInTheDocument()
  })

  it('renders the executive dashboard for an admin', async () => {
    renderApp('/admin/dashboard')

    expect(
      await screen.findByRole('heading', { level: 1, name: 'John Paul College' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Executive Management')).toBeInTheDocument()
    expect(screen.getByText('AI Executive Operational Synthesis')).toBeInTheDocument()
  })

  it('blocks employees from the management dashboard', async () => {
    renderApp('/admin/dashboard', employeeProfile)

    expect(await screen.findByRole('heading', { name: 'Access denied' })).toBeInTheDocument()
  })
})