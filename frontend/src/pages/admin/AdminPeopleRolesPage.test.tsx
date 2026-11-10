import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'

import AdminPeopleRolesPage from './AdminPeopleRolesPage'
import * as service from '../../services/peopleManagementService'
import { ApiError } from '../../services/api'
import type { RolesCatalogue } from '../../services/peopleManagementService'

const CATALOGUE: RolesCatalogue = {
  roles: [
    {
      id: 1,
      name: 'admin',
      label: 'Administrator',
      description: 'Full system access',
      is_locked: true,
      users_count: 2,
      permissions: [
        'employees.create',
        'employees.view',
        'reports.view',
        'settings.manage',
        'settings.view',
      ],
    },
    {
      id: 2,
      name: 'hr',
      label: 'HR',
      description: 'Employee administration',
      is_locked: false,
      users_count: 1,
      permissions: ['employees.view', 'reports.view'],
    },
    {
      id: 3,
      name: 'department_head',
      label: 'Department Head',
      description: null,
      is_locked: false,
      users_count: 3,
      permissions: ['employees.view'],
    },
    {
      id: 4,
      name: 'employee',
      label: 'Employee',
      description: 'Self-service access',
      is_locked: false,
      users_count: 10,
      permissions: [],
    },
  ],
  permissions: [
    {
      group: 'employees',
      label: 'Employees',
      permissions: [
        { name: 'employees.view', label: 'View Employees', elevated: false },
        { name: 'employees.create', label: 'Create Employees', elevated: false },
      ],
    },
    {
      group: 'reports',
      label: 'Reports',
      permissions: [
        { name: 'reports.view', label: 'View Reports', elevated: false },
        { name: 'reports.approve', label: 'Approve Reports', elevated: false },
      ],
    },
    {
      group: 'settings',
      label: 'Settings',
      permissions: [
        { name: 'settings.view', label: 'View Settings', elevated: false },
        { name: 'settings.manage', label: 'Manage Settings', elevated: true },
      ],
    },
  ],
  total_permissions: 6,
  elevated_permissions: ['settings.manage'],
}

vi.mock('../../services/peopleManagementService', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../services/peopleManagementService')>()
  return {
    ...actual,
    getRolesAccess: vi.fn(),
    updateRolePermissions: vi.fn(),
  }
})

describe('AdminPeopleRolesPage', () => {
  afterEach(() => {
    vi.clearAllMocks()
  })

  it('shows a compact role overview with counts instead of permission chips', async () => {
    vi.mocked(service.getRolesAccess).mockResolvedValueOnce(CATALOGUE)

    render(<AdminPeopleRolesPage />)

    expect(await screen.findByRole('heading', { name: 'Administrator' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'HR' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Department Head' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Employee' })).toBeInTheDocument()

    expect(screen.getByText('2 users')).toBeInTheDocument()
    expect(screen.getByText('10 users')).toBeInTheDocument()
    expect(screen.getByText('5 permissions assigned')).toBeInTheDocument()
    expect(screen.getByText('2 permissions assigned')).toBeInTheDocument()
    expect(screen.getByText('1 permission assigned')).toBeInTheDocument()
    expect(screen.getByText('0 permissions assigned')).toBeInTheDocument()

    // The locked Administrator row exposes no edit action.
    expect(screen.getAllByRole('button', { name: 'Edit permissions' })).toHaveLength(3)
    expect(screen.getByText('Administrator only')).toBeInTheDocument()

    // Overview must not dump raw permission codes.
    expect(screen.queryByText('employees.view')).not.toBeInTheDocument()
  })

  it('retries after a failure and clears the stale error on success', async () => {
    vi.mocked(service.getRolesAccess)
      .mockRejectedValueOnce(new ApiError('Not found', 404))
      .mockResolvedValueOnce(CATALOGUE)

    const user = userEvent.setup()
    render(<AdminPeopleRolesPage />)

    expect(await screen.findByText('Not found')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /try again/i }))

    expect(await screen.findByRole('heading', { name: 'Administrator' })).toBeInTheDocument()
    await waitFor(() => {
      expect(screen.queryByText('Not found')).not.toBeInTheDocument()
    })
  })

  it('opens the grouped editor with readable labels, codes and Administrator-only guards', async () => {
    vi.mocked(service.getRolesAccess).mockResolvedValueOnce(CATALOGUE)

    const user = userEvent.setup()
    render(<AdminPeopleRolesPage />)

    const editButtons = await screen.findAllByRole('button', { name: 'Edit permissions' })
    await user.click(editButtons[0])

    const dialog = screen.getByRole('dialog', { name: /Edit permissions — HR/ })

    expect(within(dialog).getByText('View Employees')).toBeInTheDocument()
    expect(within(dialog).getByText('employees.view')).toBeInTheDocument()
    expect(within(dialog).getByText('reports.view')).toBeInTheDocument()

    const restricted = within(dialog).getByRole('checkbox', {
      name: 'Manage Settings (settings.manage)',
    })
    expect(restricted).toBeDisabled()
    expect(within(dialog).getByText('Admin only')).toBeInTheDocument()
  })

  it('saves the existing permission set after Select all and Clear per module', async () => {
    vi.mocked(service.getRolesAccess).mockResolvedValue(CATALOGUE)
    vi.mocked(service.updateRolePermissions).mockResolvedValue(CATALOGUE)

    const user = userEvent.setup()
    render(<AdminPeopleRolesPage />)

    const editButtons = await screen.findAllByRole('button', { name: 'Edit permissions' })

    // Select all in Employees adds the missing employees.create permission.
    await user.click(editButtons[0])
    let dialog = screen.getByRole('dialog', { name: /Edit permissions — HR/ })
    await user.click(
      within(dialog).getByRole('button', { name: 'Select all Employees permissions' }),
    )
    await user.click(within(dialog).getByRole('button', { name: 'Save permissions' }))

    await waitFor(() => {
      expect(service.updateRolePermissions).toHaveBeenCalledWith(2, [
        'employees.create',
        'employees.view',
        'reports.view',
      ])
    })

    // Clear removes only the Employees selections, leaving Reports intact.
    vi.mocked(service.updateRolePermissions).mockClear()
    await user.click(screen.getAllByRole('button', { name: 'Edit permissions' })[0])
    dialog = screen.getByRole('dialog', { name: /Edit permissions — HR/ })
    await user.click(within(dialog).getByRole('button', { name: 'Clear all Employees permissions' }))
    await user.click(within(dialog).getByRole('button', { name: 'Save permissions' }))

    await waitFor(() => {
      expect(service.updateRolePermissions).toHaveBeenCalledWith(2, ['reports.view'])
    })
  })

  it('filters the editor and keeps Administrator-only permissions disabled', async () => {
    vi.mocked(service.getRolesAccess).mockResolvedValueOnce(CATALOGUE)

    const user = userEvent.setup()
    render(<AdminPeopleRolesPage />)

    const editButtons = await screen.findAllByRole('button', { name: 'Edit permissions' })
    await user.click(editButtons[0])

    const dialog = screen.getByRole('dialog', { name: /Edit permissions — HR/ })
    await user.type(
      within(dialog).getByPlaceholderText('Search permissions by name or code'),
      'approve',
    )

    expect(within(dialog).getByText('Approve Reports')).toBeInTheDocument()
    expect(within(dialog).queryByText('View Employees')).not.toBeInTheDocument()
    expect(within(dialog).queryByText('Manage Settings')).not.toBeInTheDocument()

    await user.click(
      within(dialog).getByRole('button', { name: 'Select all Reports permissions' }),
    )
    await user.click(within(dialog).getByRole('button', { name: 'Save permissions' }))

    await waitFor(() => {
      expect(service.updateRolePermissions).toHaveBeenCalledWith(2, [
        'employees.view',
        'reports.approve',
        'reports.view',
      ])
    })
  })

  it('persists the refreshed catalogue after saving', async () => {
    vi.mocked(service.getRolesAccess).mockResolvedValueOnce(CATALOGUE)
    vi.mocked(service.updateRolePermissions).mockResolvedValueOnce({
      ...CATALOGUE,
      roles: CATALOGUE.roles.map((role) =>
        role.id === 2
          ? {
              ...role,
              permissions: ['employees.create', 'employees.view', 'reports.view'],
            }
          : role,
      ),
    })

    const user = userEvent.setup()
    render(<AdminPeopleRolesPage />)

    const editButtons = await screen.findAllByRole('button', { name: 'Edit permissions' })
    await user.click(editButtons[0])

    const dialog = screen.getByRole('dialog', { name: /Edit permissions — HR/ })
    await user.click(
      within(dialog).getByRole('button', { name: 'Select all Employees permissions' }),
    )
    await user.click(within(dialog).getByRole('button', { name: 'Save permissions' }))

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })
    expect(await screen.findByText('3 permissions assigned')).toBeInTheDocument()
  })

  it('searches the collapsible permission catalog by code and module', async () => {
    vi.mocked(service.getRolesAccess).mockResolvedValueOnce(CATALOGUE)

    const user = userEvent.setup()
    render(<AdminPeopleRolesPage />)

    await screen.findByRole('heading', { name: 'Administrator' })

    // Catalog starts collapsed: module names are visible, entries are not.
    expect(screen.queryByText('View Employees')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Employees/ }))
    expect(screen.getByText('View Employees')).toBeInTheDocument()
    expect(screen.getByText('employees.view')).toBeInTheDocument()

    const search = screen.getByPlaceholderText('Search permissions by name, module or code')
    await user.type(search, 'approve')

    expect(screen.getByText('Approve Reports')).toBeInTheDocument()
    expect(screen.getByText('reports.approve')).toBeInTheDocument()
    expect(screen.queryByText('View Employees')).not.toBeInTheDocument()

    await user.clear(search)
    await user.type(search, 'settings')

    expect(screen.getByText('View Settings')).toBeInTheDocument()
    expect(screen.getByText('Manage Settings')).toBeInTheDocument()
    expect(screen.queryByText('Approve Reports')).not.toBeInTheDocument()
  })
})
