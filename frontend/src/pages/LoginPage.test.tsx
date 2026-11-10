import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { AuthProvider } from '../auth/AuthProvider'
import LoginPage from '../pages/LoginPage'
import type { UserProfile } from '../types/auth'

const profile: UserProfile = {
  id: 1,
  name: 'JPC Administrator',
  email: 'admin@jpc.local',
  status: 'active',
  role: 'admin',
  permissions: ['dashboard.view', 'settings.view'],
  last_login_at: null,
}

const employeeProfile: UserProfile = {
  id: 2,
  name: 'Ana Santos',
  email: 'employee@jpc.local',
  status: 'active',
  role: 'employee',
  employee_id: 4,
  permissions: ['dashboard.view', 'tasks.view', 'reports.view'],
  last_login_at: null,
}

const departmentHeadProfile: UserProfile = {
  ...profile,
  id: 3,
  name: 'Ben Reyes',
  email: 'depthead@jpc.local',
  role: 'department_head',
}

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

function stubApi(
  meStatus: number,
  loginStatus: number,
  sessionProfile: UserProfile = profile,
  loginMessage = 'These credentials do not match our records.',
) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/auth/me')) {
        return Promise.resolve(
          meStatus === 200
            ? json({ success: true, message: 'Authenticated user', data: sessionProfile })
            : json({ success: false, message: 'Unauthenticated' }, meStatus),
        )
      }
      if (url.includes('/auth/login')) {
        if (loginStatus === 200) {
          return Promise.resolve(
            json({ success: true, message: 'Login successful', data: sessionProfile }),
          )
        }
        return Promise.resolve(
          json({ success: false, message: loginMessage }, loginStatus),
        )
      }
      if (url.includes('/sanctum/csrf-cookie')) {
        return Promise.resolve(json({}, 204))
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

function renderLogin() {
  return render(
    <MemoryRouter>
      <AuthProvider>
        <LoginPage />
      </AuthProvider>
    </MemoryRouter>,
  )
}

function PathnameProbe() {
  const { pathname } = useLocation()
  return <p>landed on {pathname}</p>
}

/**
 * Renders the real login page with a catch-all route so the post-login
 * destination is observable: an employee must never be sent to /dashboard.
 */
function renderLoginWithRoutes() {
  return render(
    <MemoryRouter initialEntries={['/login']}>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="*" element={<PathnameProbe />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

async function signIn(
  user: ReturnType<typeof userEvent.setup>,
  opts?: Partial<{ role: string; email: string; password: string }>,
) {
  const role = opts?.role ?? 'admin'
  const email = opts?.email ?? 'someone@jpc.local'
  const password = opts?.password ?? 'P@ssw0rd2026'

  await user.selectOptions(screen.getByLabelText('Role'), role)
  await user.type(screen.getByLabelText('Email'), email)
  await user.type(screen.getByLabelText('Password'), password)
  await user.click(screen.getByRole('button', { name: 'Sign in' }))
}

describe('LoginPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the sign-in form', async () => {
    stubApi(401, 200)
    renderLogin()

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
    expect(screen.getByLabelText('Role')).toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toBeInTheDocument()
    expect(screen.getByLabelText('Password')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument()
    expect(screen.getByText('Employee Progress Monitoring System')).toBeInTheDocument()
  })

  it('submits credentials and signs the user in', async () => {
    stubApi(401, 200)
    const user = userEvent.setup()
    renderLogin()

    await screen.findByRole('heading', { name: 'Sign in' })
    await user.selectOptions(screen.getByLabelText('Role'), 'admin')
    await user.type(screen.getByLabelText('Email'), 'admin@jpc.local')
    await user.type(screen.getByLabelText('Password'), 'P@ssw0rd2026')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    await waitFor(() => {
      expect(screen.queryByRole('heading', { name: 'Sign in' })).not.toBeInTheDocument()
    })
  })

  it('surfaces the backend error message on failed credentials', async () => {
    stubApi(401, 401)
    const user = userEvent.setup()
    renderLogin()

    await screen.findByRole('heading', { name: 'Sign in' })
    await user.selectOptions(screen.getByLabelText('Role'), 'admin')
    await user.type(screen.getByLabelText('Email'), 'admin@jpc.local')
    await user.type(screen.getByLabelText('Password'), 'wrong')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(
      await screen.findByText('These credentials do not match our records.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
  })

  it('redirects already-authenticated visitors to the dashboard of their role', async () => {
    stubApi(200, 200)
    renderLoginWithRoutes()

    expect(await screen.findByText('landed on /admin/dashboard')).toBeInTheDocument()
  })

  it('sends an employee who signs in to the employee dashboard', async () => {
    stubApi(401, 200, employeeProfile)
    const user = userEvent.setup()
    renderLoginWithRoutes()

    await screen.findByRole('heading', { name: 'Sign in' })
    await signIn(user, { role: 'employee' })

    // Regression guard: employees used to land on the generic /dashboard page.
    expect(await screen.findByText('landed on /employee/dashboard')).toBeInTheDocument()
  })

  it('keeps a department head on the department head dashboard', async () => {
    stubApi(401, 200, departmentHeadProfile)
    const user = userEvent.setup()
    renderLoginWithRoutes()

    await screen.findByRole('heading', { name: 'Sign in' })
    await signIn(user, { role: 'department_head' })

    expect(
      await screen.findByText('landed on /department-head/dashboard'),
    ).toBeInTheDocument()
  })

  it('offers every system role and preselects none', async () => {
    stubApi(401, 200)
    renderLogin()

    const roleSelect = (await screen.findByLabelText('Role')) as HTMLSelectElement
    const options = Array.from(roleSelect.options).map((option) => option.text)

    expect(options).toEqual([
      'Select your role',
      'Admin',
      'HR',
      'Department Head',
      'Employee',
    ])
    expect(roleSelect.value).toBe('')
  })

  it('does not offer the Dean role', async () => {
    stubApi(401, 200)
    renderLogin()

    const roleSelect = (await screen.findByLabelText('Role')) as HTMLSelectElement
    const values = Array.from(roleSelect.options).map((option) => option.value)

    expect(values).not.toContain('dean')
    expect(Array.from(roleSelect.options).map((option) => option.text)).not.toContain('Dean')
  })

  it('requires a role before it contacts the API', async () => {
    stubApi(401, 200)
    const user = userEvent.setup()
    renderLogin()

    await screen.findByRole('heading', { name: 'Sign in' })
    await user.type(screen.getByLabelText('Email'), 'admin@jpc.local')
    await user.type(screen.getByLabelText('Password'), 'P@ssw0rd2026')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Please select your role.')).toBeInTheDocument()

    const calls = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls.map((call) =>
      String(call[0]),
    )
    expect(calls.some((url) => url.includes('/auth/login'))).toBe(false)
  })

  it('sends the selected role with the credentials', async () => {
    stubApi(401, 200)
    const user = userEvent.setup()
    renderLogin()

    await screen.findByRole('heading', { name: 'Sign in' })
    await signIn(user, { role: 'hr', email: 'hr@jpc.local', password: 'P@ssw0rd2026' })

    await waitFor(() => {
      const calls = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls
      const loginCall = calls.find((call) => String(call[0]).includes('/auth/login'))
      expect(loginCall).toBeDefined()
      const init = (loginCall as [RequestInfo | URL, RequestInit])[1]
      const body = JSON.parse(String(init.body))
      expect(body).toMatchObject({
        role: 'hr',
        email: 'hr@jpc.local',
        password: 'P@ssw0rd2026',
      })
    })
  })

  it('surfaces the backend role-mismatch message', async () => {
    stubApi(
      401,
      401,
      profile,
      'The selected role does not match this account.',
    )
    const user = userEvent.setup()
    renderLogin()

    await screen.findByRole('heading', { name: 'Sign in' })
    await signIn(user, { role: 'employee', email: 'admin@jpc.local' })

    expect(
      await screen.findByText('The selected role does not match this account.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
  })

  it('toggles password visibility without clearing the value', async () => {
    stubApi(401, 200)
    const user = userEvent.setup()
    renderLogin()

    await screen.findByRole('heading', { name: 'Sign in' })
    const password = screen.getByLabelText('Password')
    await user.type(password, 'secret1234')

    expect(password).toHaveAttribute('type', 'password')

    await user.click(screen.getByRole('button', { name: 'Show password' }))
    expect(screen.getByLabelText('Password')).toHaveAttribute('type', 'text')
    expect(screen.getByLabelText('Password')).toHaveValue('secret1234')

    await user.click(screen.getByRole('button', { name: 'Hide password' }))
    expect(screen.getByLabelText('Password')).toHaveAttribute('type', 'password')
  })

  it('reports a missing email and password before submitting', async () => {
    stubApi(401, 200)
    const user = userEvent.setup()
    renderLogin()

    await screen.findByRole('heading', { name: 'Sign in' })
    await user.selectOptions(screen.getByLabelText('Role'), 'admin')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('The email field is required.')).toBeInTheDocument()
    expect(screen.getByText('The password field is required.')).toBeInTheDocument()
  })
})