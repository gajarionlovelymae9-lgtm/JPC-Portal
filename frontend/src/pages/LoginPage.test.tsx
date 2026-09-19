import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
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

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

function stubApi(meStatus: number, loginStatus: number) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/auth/me')) {
        return Promise.resolve(
          meStatus === 200
            ? json({ success: true, message: 'Authenticated user', data: profile })
            : json({ success: false, message: 'Unauthenticated' }, meStatus),
        )
      }
      if (url.includes('/auth/login')) {
        if (loginStatus === 200) {
          return Promise.resolve(json({ success: true, message: 'Login successful', data: profile }))
        }
        return Promise.resolve(
          json(
            { success: false, message: 'These credentials do not match our records.' },
            loginStatus,
          ),
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

describe('LoginPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the sign-in form', async () => {
    stubApi(401, 200)
    renderLogin()

    expect(await screen.findByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toBeInTheDocument()
    expect(screen.getByLabelText('Password')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument()
  })

  it('submits credentials and signs the user in', async () => {
    stubApi(401, 200)
    const user = userEvent.setup()
    renderLogin()

    await screen.findByRole('heading', { name: 'Sign in' })
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
    await user.type(screen.getByLabelText('Email'), 'admin@jpc.local')
    await user.type(screen.getByLabelText('Password'), 'wrong')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(
      await screen.findByText('These credentials do not match our records.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
  })

  it('redirects already-authenticated visitors to the dashboard', async () => {
    stubApi(200, 200)
    renderLogin()

    await waitFor(() => {
      expect(screen.queryByRole('heading', { name: 'Sign in' })).not.toBeInTheDocument()
    })
  })
})