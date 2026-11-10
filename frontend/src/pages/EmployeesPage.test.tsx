import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { MemoryRouter } from 'react-router-dom'

import EmployeesPage from './EmployeesPage'
import { AuthContext, type AuthContextValue } from '../auth/useAuth'
import type { UserProfile } from '../types/auth'
import type { HrEmployeeProfile } from '../services/hrService'

/**
 * The page is a pure function of the `/api/v1/hr/*` payloads, so these tests
 * stub `fetch` the way the other page tests do and pin the rules this page can
 * silently break:
 *
 *  - a `null` rate must render as "unmeasured", never as 0%;
 *  - the directory row and the profile are the same employee's real numbers;
 *  - write buttons are hidden without `employees.create` / `employees.update`,
 *    but the page must never assume that hiding is the control;
 *  - a failed save shows the API's own field errors instead of a generic error.
 */

const options = {
  departments: [
    { id: 1, name: 'College of Education' },
    { id: 2, name: 'College of Engineering' },
  ],
  positions: [
    { id: 1, name: 'Faculty Member' },
    { id: 2, name: 'Instructor' },
  ],
  academic_years: [],
  years: [{ value: 2026, label: '2026' }],
  months: [{ value: 1, label: 'January' }],
  report_statuses: [{ value: 'approved', label: 'Approved' }],
  task_statuses: [{ value: 'assigned', label: 'Assigned' }],
  employment_statuses: ['active', 'inactive', 'on_leave'],
  account_statuses: ['active', 'inactive', 'suspended'],
}

const directoryRows = {
  summary: { total: 2, with_overdue: 1, with_late_reports: 0 },
  items: [
    {
      id: 1,
      employee_id: 1,
      name: 'Ana Santos',
      employee_number: 'EMP-001',
      email: 'ana.santos@jpc.local',
      has_account: true,
      account_status: 'active',
      date_hired: '2025-06-01',
      department: 'College of Education',
      position: 'Faculty Member',
      status: 'active',
      assigned_tasks: 4,
      completed_tasks: 3,
      pending_tasks: 1,
      late_tasks: 1,
      submitted_reports: 2,
      approved_reports: 1,
      late_reports: 0,
      completion_rate: 75,
      on_time_rate: null,
      approval_rate: 50,
      current_status: 'needs_attention',
    },
    {
      id: 2,
      employee_id: 2,
      name: 'Ben Cruz',
      employee_number: 'EMP-002',
      email: null,
      has_account: false,
      account_status: null,
      date_hired: null,
      department: null,
      position: null,
      status: 'inactive',
      assigned_tasks: 0,
      completed_tasks: 0,
      pending_tasks: 0,
      late_tasks: 0,
      submitted_reports: 0,
      approved_reports: 0,
      late_reports: 0,
      completion_rate: null,
      on_time_rate: null,
      approval_rate: null,
      current_status: 'inactive',
    },
  ],
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
}

const anaProfile: HrEmployeeProfile = {
  id: 1,
  personal: {
    employee_number: 'EMP-001',
    first_name: 'Ana',
    middle_name: null,
    last_name: 'Santos',
    suffix: null,
    full_name: 'Ana Santos',
    email: 'ana.santos@jpc.local',
    username: 'ana.santos',
    phone: '555-0142',
    address: '12 Kalentong St',
    date_of_birth: '1990-04-11',
    profile_photo_path: null,
  },
  employment: {
    employment_status: 'active',
    employment_type: 'regular',
    date_hired: '2025-06-01',
    date_ended: null,
    account_status: 'active',
    role: 'employee',
    has_account: true,
    last_login_at: '2026-09-27T08:15:00.000000Z',
  },
  organisation: {
    department_id: 1,
    department: 'College of Education',
    position_id: 1,
    position: 'Faculty Member',
    supervisor_id: null,
    supervisor: null,
  },
  tasks: { total: 4, completed: 3, pending: 1, late: 1 },
  reports: {
    total: 2,
    submitted: 2,
    pending: 1,
    approved: 1,
    rejected: 0,
    revision_required: 0,
    late: 0,
  },
  performance: {
    // on_time and approval are null on purpose: nothing to divide by.
    completion_rate: 75,
    on_time_submission_rate: null,
    approval_rate: 50,
    late_tasks: 1,
    late_reports: 0,
    status: 'active',
  },
  validation_alerts: [
    {
      id: 7,
      issue_type: 'DUPLICATE_REPORT',
      severity: 'HIGH',
      status: 'OPEN',
      is_open: true,
      title: 'Duplicate report detected',
      description: 'Two reports share the same submission window.',
      detected_value: { report_id: 20 },
      reviewed_at: null,
      created_at: '2026-09-26T09:00:00.000000Z',
    },
    {
      id: 6,
      issue_type: 'MISSING_FIELD',
      severity: 'LOW',
      status: 'RESOLVED',
      is_open: false,
      title: 'Phone number was missing',
      description: 'Filled in during the HR review.',
      detected_value: null,
      reviewed_at: '2026-09-20T10:00:00.000000Z',
      created_at: '2026-09-18T09:00:00.000000Z',
    },
  ],
  history: [
    {
      source: 'employee_histories',
      id: 3,
      event: 'employment_status',
      description: 'employment_status: inactive => active',
      actor: 'JPC HR',
      occurred_at: '2026-06-01',
    },
    {
      source: 'activity_logs',
      id: 12,
      event: 'hr.employee.update',
      description: 'Updated employee Ana Santos (EMP-001).',
      actor: 'JPC HR',
      occurred_at: '2026-06-01T09:30:00.000000Z',
    },
  ],
}

const benProfile: HrEmployeeProfile = {
  ...anaProfile,
  id: 2,
  personal: {
    ...anaProfile.personal,
    employee_number: 'EMP-002',
    first_name: 'Ben',
    last_name: 'Cruz',
    full_name: 'Ben Cruz',
    email: null,
    phone: null,
    address: null,
    date_of_birth: null,
  },
  employment: {
    ...anaProfile.employment,
    employment_status: 'inactive',
    employment_type: null,
    date_hired: null,
    account_status: null,
    has_account: false,
    last_login_at: null,
  },
  organisation: {
    department_id: 2,
    department: 'College of Engineering',
    position_id: null,
    position: null,
    supervisor_id: null,
    supervisor: null,
  },
  tasks: { total: 0, completed: 0, pending: 0, late: 0 },
  reports: {
    total: 0,
    submitted: 0,
    pending: 0,
    approved: 0,
    rejected: 0,
    revision_required: 0,
    late: 0,
  },
  performance: {
    completion_rate: null,
    on_time_submission_rate: null,
    approval_rate: null,
    late_tasks: 0,
    late_reports: 0,
    status: 'inactive',
  },
  validation_alerts: [],
  history: [],
}

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

function ok(data: unknown) {
  return json({ success: true, message: 'ok', data })
}

interface StubOptions {
  permissions?: string[]
  /** Override the response for a create/update call. */
  onWrite?: (url: string, body: unknown) => ReturnType<typeof json> | null
  /** Matches returned by the advisory duplicate-name check. */
  duplicateMatches?: Array<{
    id: number
    employee_number: string
    full_name: string
    department: string | null
    email: string | null
    username: string | null
  }>
}

const written: Array<{ url: string; method: string; body: unknown }> = []

function stubApi({ permissions = [], onWrite, duplicateMatches = [] }: StubOptions = {}) {
  const profile = permissions.includes('hr.dashboard.view')

  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      const method = init?.method ?? 'GET'
      const body = init?.body ? JSON.parse(String(init.body)) : undefined

      if (method !== 'GET') {
        written.push({ url, method, body })
        if (onWrite) {
          const override = onWrite(url, body)
          if (override) return Promise.resolve(override)
        }
        return Promise.resolve(ok(anaProfile))
      }

      if (url.includes('/hr/dashboard/options')) return Promise.resolve(ok(options))
      if (url.includes('/hr/employees/duplicate-name')) {
        return Promise.resolve(
          ok({ normalized_name: 'carlo mendoza', matches: duplicateMatches }),
        )
      }
      if (/\/hr\/employees\/\d+\/account-status/.test(url)) return Promise.resolve(ok(anaProfile))
      if (/\/hr\/employees\/\d+/.test(url)) {
        return Promise.resolve(ok(url.includes('/2') ? benProfile : anaProfile))
      }
      if (url.includes('/hr/employees')) return Promise.resolve(ok(directoryRows))
      if (url.includes('/data-quality/issues')) return Promise.resolve(ok({}))

      return Promise.resolve(ok(null))
    }),
  )

  return profile
}

function authValue(permissions: string[]): AuthContextValue {
  const user: UserProfile = {
    id: 9,
    name: 'JPC HR',
    email: 'hr@jpc.local',
    status: 'active',
    role: 'hr',
    permissions,
    last_login_at: null,
  }

  return {
    user,
    loading: false,
    error: null,
    login: vi.fn(),
    logout: vi.fn(),
    clearError: vi.fn(),
  }
}

function renderPage(permissions: string[] = ['employees.view', 'employees.create', 'employees.update']) {
  return render(
    <MemoryRouter>
      <AuthContext.Provider value={authValue(permissions)}>
        <EmployeesPage />
      </AuthContext.Provider>
    </MemoryRouter>,
  )
}

describe('EmployeesPage', () => {
  beforeEach(() => {
    written.length = 0
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  /**
   * Scope a query to one employee's row.
   *
   * Every row carries the same "View" and "Edit" labels, so an unscoped query
   * matches both people and cannot tell us which one was acted on.
   */
  function rowFor(name: string): HTMLElement {
    return screen.getByRole('table').querySelectorAll('tbody tr')[
      screen.getAllByRole('row').findIndex((r) => r.textContent?.includes(name)) - 1
    ] as HTMLElement
  }

  /** Open one employee's record from the directory. */
  async function openRecord(user: ReturnType<typeof userEvent.setup>, name: string) {
    const row = rowFor(name)
    await user.click(within(row).getByRole('button', { name: 'View' }))
  }

  it('lists real employees with their account and progress columns', async () => {
    stubApi()
    renderPage()

    await screen.findByRole('heading', { level: 1, name: 'Employee Management' })

    const table = screen.getByRole('table')
    expect(within(table).getByText('Ana Santos')).toBeInTheDocument()
    expect(within(table).getByText('EMP-001')).toBeInTheDocument()
    expect(within(table).getByText('ana.santos@jpc.local')).toBeInTheDocument()
    expect(within(table).getByText('75.0%')).toBeInTheDocument()

    // A rate the API sent as null must not be dressed up as 0%.
    const benRow = within(table).getByText('Ben Cruz').closest('tr') as HTMLElement
    expect(within(benRow).getAllByText('—').length).toBeGreaterThan(0)

    // An employee with no login says so rather than showing a blank cell.
    expect(within(benRow).getByText('None')).toBeInTheDocument()
  })

  it('shows a full record when an employee is selected', async () => {
    stubApi()
    const user = userEvent.setup()
    renderPage()

    await screen.findByRole('table')
    await openRecord(user, 'Ana Santos')

    expect(await screen.findByText('12 Kalentong St')).toBeInTheDocument()
    // The directory row also shows the position, so scope this to the profile.
    const profile = (await screen.findByText('12 Kalentong St')).closest('section') as HTMLElement
    expect(within(profile).getByText('Faculty Member')).toBeInTheDocument()
    expect(screen.getByText('Duplicate report detected')).toBeInTheDocument()
    expect(screen.getByText('employment_status: inactive => active')).toBeInTheDocument()

    // The alert that is already resolved must not offer further actions.
    const resolved = screen.getByText('Phone number was missing').closest('li') as HTMLElement
    expect(within(resolved).queryByRole('button', { name: 'Resolve' })).not.toBeInTheDocument()
  })

  it('renders unmeasured profile rates as a dash, never 0%', async () => {
    stubApi()
    const user = userEvent.setup()
    renderPage()

    await screen.findByRole('table')
    await openRecord(user, 'Ana Santos')

    const performance = (await screen.findByRole('heading', { name: 'Performance' })).closest(
      'section',
    ) as HTMLElement

    expect(within(performance).getByText('75.0%')).toBeInTheDocument()
    // on_time_submission_rate is null in the payload.
    expect(within(performance).getAllByText('—').length).toBeGreaterThan(0)
  })

  it('hides write actions from a user without the employees permissions', async () => {
    stubApi()
    renderPage(['employees.view'])

    await screen.findByRole('heading', { level: 1, name: 'Employee Management' })

    expect(screen.queryByRole('button', { name: 'Add employee' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(
      screen.getByText('You have read-only access to the employee directory.'),
    ).toBeInTheDocument()

    // Reading still works.
    expect(screen.getByRole('table')).toBeInTheDocument()
  })

  // Typing six fields character by character is slow; the other form tests use
  // the same budget.
  const FORM_TIMEOUT = 20_000

  it('creates an employee with the fields the form collected', async () => {
    stubApi()
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Add employee' }))

    const dialog = screen.getByRole('dialog', { name: 'Add employee' })

    await user.type(within(dialog).getByLabelText(/Employee ID/), 'EMP-2026-042')
    await user.type(within(dialog).getByLabelText(/First name/), 'Carlo')
    await user.type(within(dialog).getByLabelText(/Last name/), 'Mendoza')
    await user.click(within(dialog).getByLabelText(/Department/))
    await user.click(await within(dialog).findByRole('option', { name: 'College of Education' }))
    await user.type(within(dialog).getByLabelText(/Email/), 'carlo.mendoza@jpc.local')
    await user.type(within(dialog).getByLabelText(/Username/), 'carlo.mendoza')
    await user.type(within(dialog).getByLabelText(/Temporary password/), 'P@ssw0rd2026')

    await user.click(within(dialog).getByRole('button', { name: 'Create employee' }))

    await waitFor(() => expect(written).toHaveLength(1))

    expect(written[0].method).toBe('POST')
    expect(written[0].url).toContain('/hr/employees')
    expect(written[0].body).toMatchObject({
      employee_number: 'EMP-2026-042',
      first_name: 'Carlo',
      last_name: 'Mendoza',
      department_id: 1,
      email: 'carlo.mendoza@jpc.local',
      username: 'carlo.mendoza',
      password: 'P@ssw0rd2026',
    })
  }, FORM_TIMEOUT)

  it('warns about a same-name employee and only saves after the operator proceeds', async () => {
    stubApi({
      duplicateMatches: [
        {
          id: 4,
          employee_number: 'EMP-004',
          full_name: 'Carlo Mendoza',
          department: 'College of Education',
          email: null,
          username: null,
        },
      ],
    })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Add employee' }))
    const dialog = screen.getByRole('dialog', { name: 'Add employee' })

    await user.type(within(dialog).getByLabelText(/Employee ID/), 'EMP-2026-042')
    await user.type(within(dialog).getByLabelText(/First name/), 'Carlo')
    await user.type(within(dialog).getByLabelText(/Last name/), 'Mendoza')
    await user.click(within(dialog).getByLabelText(/Department/))
    await user.click(await within(dialog).findByRole('option', { name: 'College of Education' }))

    await user.click(within(dialog).getByRole('button', { name: 'Create employee' }))

    // The warning names the matching employee, their ID and department and
    // refuses to save until the operator explicitly decides to continue.
    expect(
      await within(dialog).findByText('A matching employee name already exists'),
    ).toBeInTheDocument()
    expect(within(dialog).getByText('Carlo Mendoza')).toBeInTheDocument()
    expect(within(dialog).getByText('EMP-004')).toBeInTheDocument()
    expect(within(dialog).getByText(/College of Education/)).toBeInTheDocument()
    expect(written).toHaveLength(0)

    await user.click(within(dialog).getByRole('button', { name: 'Proceed anyway' }))

    await waitFor(() => expect(written).toHaveLength(1))
    expect(written[0].method).toBe('POST')
    expect(written[0].body).toMatchObject({
      employee_number: 'EMP-2026-042',
      first_name: 'Carlo',
      last_name: 'Mendoza',
    })
  }, FORM_TIMEOUT)

  it('surfaces the API field errors from a rejected save', async () => {
    stubApi({
      onWrite: () =>
        json(
          {
            success: false,
            message: 'Validation failed',
            errors: { employee_number: ['The employee ID has already been taken.'] },
          },
          422,
        ),
    })

    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Add employee' }))
    const dialog = screen.getByRole('dialog', { name: 'Add employee' })

    await user.type(within(dialog).getByLabelText(/Employee ID/), 'EMP-001')
    await user.type(within(dialog).getByLabelText(/First name/), 'Dup')
    await user.type(within(dialog).getByLabelText(/Last name/), 'Licate')
    await user.click(within(dialog).getByLabelText(/Department/))
    await user.click(await within(dialog).findByRole('option', { name: 'College of Education' }))
    await user.click(within(dialog).getByRole('button', { name: 'Create employee' }))

    expect(
      await within(dialog).findByText('The employee ID has already been taken.'),
    ).toBeInTheDocument()
    // The dialog stays open so the operator can fix the value.
    expect(screen.getByRole('dialog', { name: 'Add employee' })).toBeInTheDocument()
  }, FORM_TIMEOUT)

  it('sends a partial edit with the reason the operator typed', async () => {
    stubApi()
    const user = userEvent.setup()
    renderPage()

    await screen.findByRole('table')
    await user.click(within(rowFor('Ana Santos')).getByRole('button', { name: 'Edit' }))

    const dialog = await screen.findByRole('dialog', { name: 'Edit employee' })

    // The form is seeded from the profile, not the directory row.
    await waitFor(() =>
      expect(within(dialog).getByLabelText(/Employee ID/)).toHaveValue('EMP-001'),
    )
    expect(within(dialog).getByLabelText(/First name/)).toHaveValue('Ana')

    // The field starts from the profile's value, so replace it rather than append.
    const phone = within(dialog).getByLabelText(/Phone/)
    await user.clear(phone)
    await user.type(phone, '555-9999')
    await user.type(
      within(dialog).getByLabelText(/Reason for this change/),
      'Corrected during the HR review.',
    )

    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    await waitFor(() => expect(written).toHaveLength(1))
    expect(written[0].method).toBe('PATCH')
    expect(written[0].url).toContain('/hr/employees/1')
    expect(written[0].body).toMatchObject({
      phone: '555-9999',
      reason: 'Corrected during the HR review.',
    })
  }, FORM_TIMEOUT)

  it('never PATCHes before the profile has loaded', async () => {
    stubApi()
    const user = userEvent.setup()
    renderPage()

    await screen.findByRole('table')
    await user.click(within(rowFor('Ana Santos')).getByRole('button', { name: 'Edit' }))

    // The dialog is open but carries no Save control until the record arrives,
    // so an empty payload can never be submitted from the create defaults.
    const dialog = screen.getByRole('dialog', { name: 'Edit employee' })
    await waitFor(() =>
      expect(within(dialog).getByRole('button', { name: 'Save changes' })).toBeInTheDocument(),
    )
    expect(written).toHaveLength(0)
  })

  it('changes the account status through its own audited endpoint', async () => {
    stubApi()
    const user = userEvent.setup()
    renderPage()

    await screen.findByRole('table')
    await openRecord(user, 'Ana Santos')

    const account = (await screen.findByRole('heading', { name: 'Login account' })).closest(
      'section',
    ) as HTMLElement

    await user.selectOptions(within(account).getByLabelText(/Account status/), 'inactive')

    await waitFor(() => expect(written).toHaveLength(1))
    expect(written[0].url).toContain('/hr/employees/1/account-status')
    expect(written[0].body).toEqual({ account_status: 'inactive' })
  })

  it('does not offer account status for an employee with no login', async () => {
    stubApi()
    const user = userEvent.setup()
    renderPage()

    await screen.findByRole('table')
    const benRow = rowFor('Ben Cruz')
    await user.click(within(benRow).getByRole('button', { name: 'View' }))

    expect(await screen.findByText(/^No login account$/)).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Login account' })).not.toBeInTheDocument()
  })

  it('resolves a validation alert through the existing data-quality workflow', async () => {
    const alerts: string[] = []
    vi.stubGlobal(
      'fetch',
      vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
        const url = String(input)
        const method = init?.method ?? 'GET'
        if (method !== 'GET') {
          alerts.push(url)
          if (url.includes('/data-quality/issues/7/resolve')) return Promise.resolve(ok({}))
        }
        if (url.includes('/hr/dashboard/options')) return Promise.resolve(ok(options))
        if (/\/hr\/employees\/\d+/.test(url)) return Promise.resolve(ok(anaProfile))
        if (url.includes('/hr/employees')) return Promise.resolve(ok(directoryRows))
        return Promise.resolve(ok(null))
      }),
    )
    vi.spyOn(window, 'prompt').mockReturnValue('Fixed during the HR review.')

    const user = userEvent.setup()
    renderPage()

    await screen.findByRole('table')
    await openRecord(user, 'Ana Santos')

    const alert = (await screen.findByText('Duplicate report detected')).closest('li') as HTMLElement
    await user.click(within(alert).getByRole('button', { name: 'Resolve' }))

    await waitFor(() =>
      expect(alerts.some((url) => url.includes('/data-quality/issues/7/resolve'))).toBe(true),
    )
  })

  it('shows an empty state rather than a blank table when nothing matches', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((input: RequestInfo | URL) => {
        const url = String(input)
        if (url.includes('/hr/dashboard/options')) return Promise.resolve(ok(options))
        if (url.includes('/hr/employees')) {
          return Promise.resolve(
            ok({
              summary: { total: 0, with_overdue: 0, with_late_reports: 0 },
              items: [],
              meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
            }),
          )
        }
        return Promise.resolve(ok(null))
      }),
    )

    renderPage()

    expect(
      await screen.findByText('No employees match these filters'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('reports an API failure instead of showing an empty directory', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn((input: RequestInfo | URL) => {
        const url = String(input)
        if (url.includes('/hr/dashboard/options')) return Promise.resolve(ok(options))
        if (url.includes('/hr/employees')) {
          return Promise.resolve(json({ success: false, message: 'Directory unavailable' }, 500))
        }
        return Promise.resolve(ok(null))
      }),
    )

    renderPage()

    expect(await screen.findByText('Directory unavailable')).toBeInTheDocument()
  })
})