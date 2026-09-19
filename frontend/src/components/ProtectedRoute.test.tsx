import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { AuthProvider } from '../auth/AuthProvider'
import { ProtectedRoute } from './ProtectedRoute'
import type { UserProfile } from '../types/auth'

const admin: UserProfile = {
  id: 1,
  name: 'JPC Administrator',
  email: 'admin@jpc.local',
  status: 'active',
  role: 'admin',
  permissions: ['settings.view'],
  last_login_at: null,
}

function json(payload: unknown, status = 200) {
  return { ok: status < 400, status, json: async () => payload }
}

function stubMe(profile: UserProfile | null) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/auth/me')) {
        return Promise.resolve(
          profile
            ? json({ success: true, message: 'Authenticated user', data: profile })
            : json({ success: false, message: 'Unauthenticated' }, 401),
        )
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

function renderGuarded(profile: UserProfile | null, permission?: string) {
  stubMe(profile)
  return render(
    <MemoryRouter initialEntries={['/guard']}>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<div>Login page</div>} />
          <Route
            path="/guard"
            element={
              <ProtectedRoute permission={permission}>
                <div>Secret content</div>
              </ProtectedRoute>
            }
          />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('ProtectedRoute', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders children for an authenticated user with the required permission', async () => {
    renderGuarded(admin, 'settings.view')

    expect(await screen.findByText('Secret content')).toBeInTheDocument()
  })

  it('renders children when the user holds the required role', async () => {
    stubMe(admin)
    render(
      <MemoryRouter initialEntries={['/guard']}>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<div>Login page</div>} />
            <Route
              path="/guard"
              element={
                <ProtectedRoute role="admin">
                  <div>Secret content</div>
                </ProtectedRoute>
              }
            />
          </Routes>
        </AuthProvider>
      </MemoryRouter>,
    )

    expect(await screen.findByText('Secret content')).toBeInTheDocument()
  })

  it('renders an access denied message when the permission is missing', async () => {
    renderGuarded(admin, 'settings.manage')

    expect(
      await screen.findByRole('heading', { name: 'Access denied' }),
    ).toBeInTheDocument()
    expect(screen.queryByText('Secret content')).not.toBeInTheDocument()
  })

  it('redirects unauthenticated users to the login page', async () => {
    renderGuarded(null)

    expect(await screen.findByText('Login page')).toBeInTheDocument()
    expect(screen.queryByText('Secret content')).not.toBeInTheDocument()
  })
})