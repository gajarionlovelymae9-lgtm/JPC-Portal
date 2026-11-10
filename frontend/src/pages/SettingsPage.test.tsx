import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import SettingsPage from './SettingsPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const SUCCESS = {
  success: true,
  message: 'Role-based access control summary',
  data: {
    roles: {
      admin: {
        label: 'Administrator',
        permissions: ['planning.view', 'planning.create', 'settings.view'],
      },
      hr: {
        label: 'HR',
        permissions: ['planning.view', 'settings.view'],
      },
      department_head: {
        label: 'Department Head',
        permissions: ['planning.view'],
      },
      employee: {
        label: 'Employee',
        permissions: [],
      },
    },
    catalog: {
      planning: {
        'planning.view': 'View Planning',
        'planning.create': 'Create Planning',
      },
      settings: {
        'settings.view': 'View Settings',
      },
    },
  },
}

function stubApi() {
  const fetchMock = vi.fn((input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('/admin/rbac')) {
      return Promise.resolve(json(SUCCESS))
    }
    return Promise.resolve(json({ success: false, message: 'Not found.' }, 404))
  })

  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function matrixPanel(): HTMLElement {
  return screen
    .getByRole('heading', { level: 2, name: 'Role → permission matrix' })
    .closest('section') as HTMLElement
}

describe('SettingsPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the role-to-permission matrix from the RBAC endpoint', async () => {
    stubApi()
    render(<SettingsPage />)

    await screen.findByRole('heading', { level: 2, name: 'Role → permission matrix' })

    expect(screen.getByRole('heading', { level: 1, name: 'Settings' })).toBeInTheDocument()

    // Column headers come from the API's role labels.
    const panel = matrixPanel()
    expect(within(panel).getByRole('columnheader', { name: 'Administrator' })).toBeInTheDocument()
    expect(within(panel).getByRole('columnheader', { name: 'Department Head' })).toBeInTheDocument()

    // A permission row carries its label, its raw name and its grants.
    const row = within(panel).getByText('View Planning').closest('tr') as HTMLElement
    expect(within(row).getByText('planning.view')).toBeInTheDocument()
    expect(within(row).getAllByText('✓')).toHaveLength(3) // admin, hr, department head
    expect(within(row).getAllByText('—')).toHaveLength(1) // employee

    const settingsRow = within(panel).getByText('View Settings').closest('tr') as HTMLElement
    expect(within(settingsRow).getAllByText('✓')).toHaveLength(2) // admin, hr
  })

  it('shows summary counts for roles, permissions and groups', async () => {
    stubApi()
    render(<SettingsPage />)

    await screen.findByRole('heading', { level: 2, name: 'Role → permission matrix' })

    expect(screen.getByText('4')).toBeInTheDocument() // roles
    expect(screen.getByText('3')).toBeInTheDocument() // permissions
    expect(screen.getByText('2')).toBeInTheDocument() // groups
  })

  it('exposes no mutation controls — the matrix is read-only', async () => {
    stubApi()
    render(<SettingsPage />)

    await screen.findByRole('heading', { level: 2, name: 'Role → permission matrix' })

    expect(screen.queryAllByRole('button')).toHaveLength(0)
  })

  it('shows the error panel with a working retry', async () => {
    let attempts = 0
    const fetchMock = vi.fn(() => {
      attempts += 1
      if (attempts === 1) {
        return Promise.resolve(json({ success: false, message: 'RBAC unavailable.' }, 503))
      }
      return Promise.resolve(json(SUCCESS))
    })
    vi.stubGlobal('fetch', fetchMock)

    const user = userEvent.setup()
    render(<SettingsPage />)

    expect(await screen.findByText('Something went wrong')).toBeInTheDocument()
    expect(screen.getByText('RBAC unavailable.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Try again' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.length).toBe(2)
    })
    expect(
      await screen.findByRole('heading', { level: 2, name: 'Role → permission matrix' }),
    ).toBeInTheDocument()
  })
})
