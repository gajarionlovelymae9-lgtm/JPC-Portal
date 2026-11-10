import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import AdminUserManagementPage from './AdminUserManagementPage'

/**
 * The Accounts tab is the write half of People Management. These tests pin the
 * two rules that are easy to regress without noticing:
 *
 *  - a new account always carries the unique username the form collected;
 *  - an employee can hold only one login, so linking one that already has an
 *    account blocks the save and points at the existing account instead.
 */

const writes: Array<{ url: string; method: string; body: unknown }> = []

const marco = {
  id: 2,
  name: 'Marco Diaz',
  email: 'marco.diaz@jpc.local',
  username: 'marco.diaz',
  status: 'active',
  status_label: 'Active',
  can_authenticate: true,
  is_active: true,
  role: 'employee',
  role_label: 'Employee',
  employee_id: 6,
  employee_name: 'Marco Diaz',
  employee_number: 'EMP-006',
  department: 'College of Science',
  email_verified_at: '2026-01-01T00:00:00.000000Z',
  last_login_at: null,
  created_at: '2026-01-01T00:00:00.000000Z',
}

const listPayload = {
  summary: {
    total_users: 1,
    active_users: 1,
    inactive_users: 0,
    suspended_users: 0,
    unlinked_employees: 1,
    by_role: [{ role: 'employee', label: 'Employee', total: 1 }],
  },
  items: [marco],
  meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
  options: {
    roles: [
      { id: 1, value: 'admin', label: 'Administrator' },
      { id: 4, value: 'employee', label: 'Employee' },
    ],
    statuses: [
      { value: 'active', label: 'Active' },
      { value: 'inactive', label: 'Inactive' },
    ],
    unlinked_employees: [
      {
        id: 5,
        name: 'Liza Reyes',
        employee_number: 'EMP-005',
        department: 'College of Education',
      },
    ],
    employees: [
      {
        id: 5,
        name: 'Liza Reyes',
        employee_number: 'EMP-005',
        department: 'College of Education',
        account: null,
      },
      {
        id: 6,
        name: 'Marco Diaz',
        employee_number: 'EMP-006',
        department: 'College of Science',
        account: {
          id: 2,
          name: 'Marco Diaz',
          email: 'marco.diaz@jpc.local',
          username: 'marco.diaz',
          status: 'active',
        },
      },
    ],
  },
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

function stubApi() {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      const method = init?.method ?? 'GET'

      if (method !== 'GET') {
        writes.push({
          url,
          method,
          body: init?.body ? JSON.parse(String(init.body)) : undefined,
        })
        return Promise.resolve(ok(marco))
      }

      // The single-account read is what "Open existing account" calls.
      if (/\/admin\/users\/\d+$/.test(url)) return Promise.resolve(ok(marco))
      if (url.includes('/admin/users')) return Promise.resolve(ok(listPayload))
      return Promise.resolve(ok(null))
    }),
  )
}

async function openCreate(user: ReturnType<typeof userEvent.setup>) {
  await screen.findByRole('heading', { name: 'Accounts' })
  await user.click(screen.getByRole('button', { name: 'Add user' }))
  return screen.getByRole('dialog', { name: 'Add user' })
}

async function openEdit(user: ReturnType<typeof userEvent.setup>) {
  await screen.findByRole('heading', { name: 'Accounts' })
  await user.click(screen.getByRole('button', { name: 'Edit' }))
  return screen.findByRole('dialog', { name: 'Edit Marco Diaz' })
}

const PASSWORD_MIN_MESSAGE = 'Password must contain at least 8 characters.'

describe('AdminUserManagementPage', () => {
  beforeEach(() => {
    writes.length = 0
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  const FORM_TIMEOUT = 20_000

  it('creates an account with the username and linked employee the form collected', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminUserManagementPage embedded />)

    const dialog = await openCreate(user)

    await user.type(within(dialog).getByLabelText(/Full name/), 'Liza Reyes')
    await user.type(within(dialog).getByLabelText(/Email/), 'liza.reyes@jpc.local')
    await user.type(within(dialog).getByLabelText(/Username/), 'liza.reyes')
    await user.type(within(dialog).getByLabelText(/Password/), 'password123')

    await user.click(within(dialog).getByLabelText(/Link employee record/))
    await user.click(await within(dialog).findByRole('option', { name: /Liza Reyes/ }))

    await user.click(within(dialog).getByRole('button', { name: 'Create user' }))

    await waitFor(() => expect(writes).toHaveLength(1))

    expect(writes[0].method).toBe('POST')
    expect(writes[0].url).toContain('/admin/users')
    expect(writes[0].body).toMatchObject({
      name: 'Liza Reyes',
      email: 'liza.reyes@jpc.local',
      username: 'liza.reyes',
      role: 'employee',
      status: 'active',
      employee_id: 5,
    })
  }, FORM_TIMEOUT)

  it('blocks a second login for an employee and opens the existing account', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminUserManagementPage embedded />)

    const dialog = await openCreate(user)

    await user.click(within(dialog).getByLabelText(/Link employee record/))
    await user.click(await within(dialog).findByRole('option', { name: /Marco Diaz/ }))

    expect(
      within(dialog).getByText('This employee already has a login account.'),
    ).toBeInTheDocument()
    expect(within(dialog).getByText(/marco\.diaz@jpc\.local/)).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Create user' })).toBeDisabled()
    expect(writes).toHaveLength(0)

    await user.click(within(dialog).getByRole('button', { name: 'Open existing account' }))

    const editDialog = await screen.findByRole('dialog', { name: 'Edit Marco Diaz' })
    expect(within(editDialog).getByLabelText(/Username/)).toHaveValue('marco.diaz')
    expect(within(editDialog).getByLabelText(/Full name/)).toHaveValue('Marco Diaz')
  }, FORM_TIMEOUT)

  it('rejects 5 and 7 character passwords, warns on save, and accepts 8 characters', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminUserManagementPage embedded />)

    const dialog = await openEdit(user)
    const password = within(dialog).getByLabelText(/New password/)
    const save = within(dialog).getByRole('button', { name: 'Save changes' })

    await user.type(password, '12345')
    expect(within(dialog).getByText(PASSWORD_MIN_MESSAGE)).toBeInTheDocument()
    expect(save).toHaveAttribute('aria-disabled', 'true')

    // Attempting to save an invalid password must not write anything and must
    // surface the warning toast.
    await user.click(save)
    await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent(PASSWORD_MIN_MESSAGE))
    expect(writes).toHaveLength(0)

    await user.clear(password)
    await user.type(password, '1234567')
    expect(within(dialog).getByText(PASSWORD_MIN_MESSAGE)).toBeInTheDocument()
    expect(save).toHaveAttribute('aria-disabled', 'true')

    // The warning clears immediately once the value reaches 8 characters.
    await user.clear(password)
    await user.type(password, '12345678')
    expect(within(dialog).queryByText(PASSWORD_MIN_MESSAGE)).not.toBeInTheDocument()
    expect(save).not.toHaveAttribute('aria-disabled', 'true')
    expect(screen.queryByRole('status')).not.toBeInTheDocument()

    await user.click(save)
    await waitFor(() => expect(writes).toHaveLength(1))
    expect(writes[0].method).toBe('PUT')
    expect(writes[0].body).toMatchObject({ password: '12345678' })
  }, FORM_TIMEOUT)

  it('accepts a password longer than 8 characters and toggles visibility', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminUserManagementPage embedded />)

    const dialog = await openEdit(user)
    const password = within(dialog).getByLabelText(/New password/)

    expect(password).toHaveAttribute('type', 'password')

    await user.click(within(dialog).getByRole('button', { name: 'Show password' }))
    expect(password).toHaveAttribute('type', 'text')
    expect(within(dialog).getByRole('button', { name: 'Hide password' })).toBeInTheDocument()

    await user.type(password, 'a-much-longer-password')
    expect(within(dialog).queryByText(PASSWORD_MIN_MESSAGE)).not.toBeInTheDocument()

    await user.click(within(dialog).getByRole('button', { name: 'Hide password' }))
    expect(password).toHaveAttribute('type', 'password')

    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))
    await waitFor(() => expect(writes).toHaveLength(1))
    expect(writes[0].body).toMatchObject({ password: 'a-much-longer-password' })
  }, FORM_TIMEOUT)

  it('treats a blank password as valid when editing and preserves the existing one', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminUserManagementPage embedded />)

    const dialog = await openEdit(user)
    const password = within(dialog).getByLabelText(/New password/)

    expect(password).toHaveValue('')
    expect(within(dialog).queryByText(PASSWORD_MIN_MESSAGE)).not.toBeInTheDocument()

    const save = within(dialog).getByRole('button', { name: 'Save changes' })
    expect(save).not.toHaveAttribute('aria-disabled', 'true')

    await user.click(save)
    await waitFor(() => expect(writes).toHaveLength(1))

    // No password key at all: the backend keeps the stored hash.
    expect(writes[0].body).not.toHaveProperty('password')
  }, FORM_TIMEOUT)
})
