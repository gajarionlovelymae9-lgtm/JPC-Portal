import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import AdminPeopleDepartmentsPage from './AdminPeopleDepartmentsPage'
import * as service from '../../services/peopleManagementService'
import { ApiError } from '../../services/api'

vi.mock('../../services/peopleManagementService', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../services/peopleManagementService')>()
  return {
    ...actual,
    listDepartments: vi.fn(),
    getDepartmentSummary: vi.fn(),
    getDepartmentOptions: vi.fn(),
    getEligibleDepartmentHeads: vi.fn(),
    createDepartment: vi.fn(),
    updateDepartment: vi.fn(),
    setDepartmentStatus: vi.fn(),
    assignDepartmentHead: vi.fn(),
    assignDepartmentEmployees: vi.fn(),
    getDepartment: vi.fn(),
  }
})

const SUMMARY = { total: 0, active: 0, inactive: 0 }

const OPTIONS: service.DepartmentOptions = {
  head_role: 'department_head',
  head_role_users: 0,
  departments: [],
  statuses: [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
  ],
  employees: [],
}

function openCreateDialog() {
  return screen.getByRole('button', { name: /add department/i })
}

const DEPARTMENT: service.Department = {
  id: 3,
  name: 'College of Information Technology',
  code: 'CIT',
  description: null,
  status: 'active',
  head_employee_id: null,
  head_name: 'Unassigned',
}

const ELIGIBLE: service.EligibleDepartmentHeads = {
  department: { id: 3, name: DEPARTMENT.name, code: 'CIT', status: 'active' },
  head_role: 'department_head',
  current_head_employee_id: null,
  employees: [
    {
      id: 3,
      name: 'JPC Department Head',
      employee_number: 'DEV-0003',
      position: 'Dean',
      department_id: 3,
      department: DEPARTMENT.name,
      account_status: 'active',
    },
    {
      id: 10,
      name: 'Ian Torres',
      employee_number: 'DEV-0010',
      position: 'Faculty',
      department_id: 3,
      department: DEPARTMENT.name,
      account_status: 'active',
    },
  ],
  diagnostics: {
    total_employees: 2,
    active_employees: 2,
    without_account: 0,
    inactive_account: 0,
    without_head_role: 0,
    eligible: 2,
  },
}

function openAssignHeadDialog() {
  return screen.getByRole('button', { name: /assign head/i })
}

function baseMocks() {
  vi.mocked(service.listDepartments).mockResolvedValue([DEPARTMENT])
  vi.mocked(service.getDepartmentSummary).mockResolvedValue({ total: 1, active: 1, inactive: 0 })
  vi.mocked(service.getDepartmentOptions).mockResolvedValue(OPTIONS)
  vi.mocked(service.getEligibleDepartmentHeads).mockResolvedValue(ELIGIBLE)
}

describe('AdminPeopleDepartmentsPage — assign department head', () => {
  beforeEach(() => {
    baseMocks()
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  it('loads eligible heads dynamically and shows name, employee ID, and position', async () => {
    const user = userEvent.setup()
    render(<AdminPeopleDepartmentsPage embedded />)

    await user.click(await waitFor(() => openAssignHeadDialog()))

    const dialog = await screen.findByRole('dialog', { name: /assign department head/i })

    await waitFor(() => {
      expect(service.getEligibleDepartmentHeads).toHaveBeenCalledWith(3)
    })

    expect(
      await within(dialog).findByRole('option', {
        name: /JPC Department Head · #DEV-0003 · Dean/,
      }),
    ).toBeInTheDocument()
    expect(
      within(dialog).getByRole('option', { name: /Ian Torres · #DEV-0010 · Faculty/ }),
    ).toBeInTheDocument()
  })

  it('shows a loading indicator while eligible heads are retrieved', async () => {
    const user = userEvent.setup()
    vi.mocked(service.getEligibleDepartmentHeads).mockReturnValue(new Promise(() => {}))

    render(<AdminPeopleDepartmentsPage embedded />)
    await user.click(await waitFor(() => openAssignHeadDialog()))

    expect(await screen.findByText('Loading eligible employees')).toBeInTheDocument()
  })

  it('shows an API error with a working retry instead of an empty dropdown', async () => {
    const user = userEvent.setup()
    vi.mocked(service.getEligibleDepartmentHeads)
      .mockRejectedValueOnce(new ApiError('Eligible lookup failed', 400))
      .mockResolvedValueOnce(ELIGIBLE)

    render(<AdminPeopleDepartmentsPage embedded />)
    await user.click(await waitFor(() => openAssignHeadDialog()))

    expect(await screen.findByText('Eligible lookup failed')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /try again/i }))

    expect(
      await screen.findByRole('option', { name: /JPC Department Head · #DEV-0003 · Dean/ }),
    ).toBeInTheDocument()
  })

  it('explains why no employee is eligible using diagnostic counts', async () => {
    const user = userEvent.setup()
    vi.mocked(service.getEligibleDepartmentHeads).mockResolvedValue({
      ...ELIGIBLE,
      employees: [],
      diagnostics: {
        total_employees: 3,
        active_employees: 3,
        without_account: 1,
        inactive_account: 1,
        without_head_role: 1,
        eligible: 0,
      },
    })

    render(<AdminPeopleDepartmentsPage embedded />)
    await user.click(await waitFor(() => openAssignHeadDialog()))

    expect(await screen.findByText('No eligible employees found')).toBeInTheDocument()
    expect(screen.getByText(/1 active employee\(s\) have no linked login account/i)).toBeInTheDocument()
    expect(screen.getByText(/1 active employee\(s\) have an inactive account/i)).toBeInTheDocument()
    expect(screen.getByText(/1 active employee\(s\) do not hold the Department Head role/i)).toBeInTheDocument()
    expect(screen.getByText(/User Management → Accounts/i)).toBeInTheDocument()
  })

  it('filters eligible employees by name, employee ID, or position', async () => {
    const user = userEvent.setup()
    render(<AdminPeopleDepartmentsPage embedded />)
    await user.click(await waitFor(() => openAssignHeadDialog()))

    const dialog = await screen.findByRole('dialog', { name: /assign department head/i })
    const search = within(dialog).getByPlaceholderText(/search by name, employee id, or position/i)

    await user.type(search, 'Ian')

    await waitFor(() => {
      expect(
        within(dialog).queryByRole('option', { name: /JPC Department Head/ }),
      ).not.toBeInTheDocument()
    })
    expect(within(dialog).getByRole('option', { name: /Ian Torres/ })).toBeInTheDocument()
  })

  it('assigns the selected head and refreshes the departments table', async () => {
    const user = userEvent.setup()
    vi.mocked(service.assignDepartmentHead).mockResolvedValue({} as service.DepartmentDetail)

    render(<AdminPeopleDepartmentsPage embedded />)
    await user.click(await waitFor(() => openAssignHeadDialog()))

    const dialog = await screen.findByRole('dialog', { name: /assign department head/i })
    const select = within(dialog).getByRole('combobox', { name: /select employee/i })

    await user.selectOptions(select, '3')
    await user.click(within(dialog).getByRole('button', { name: 'Assign Head' }))

    await waitFor(() => {
      expect(service.assignDepartmentHead).toHaveBeenCalledWith(3, 3)
    })
    await waitFor(() => {
      expect(service.listDepartments).toHaveBeenCalledTimes(2)
    })
  })
})

describe('AdminPeopleDepartmentsPage — department form validation', () => {
  beforeEach(() => {
    vi.mocked(service.listDepartments).mockResolvedValue([])
    vi.mocked(service.getDepartmentSummary).mockResolvedValue(SUMMARY)
    vi.mocked(service.getDepartmentOptions).mockResolvedValue(OPTIONS)
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  it('submits a full academic program name and a short code', async () => {
    const user = userEvent.setup()
    vi.mocked(service.createDepartment).mockResolvedValue({} as service.DepartmentDetail)

    render(<AdminPeopleDepartmentsPage embedded />)

    await user.click(await waitFor(() => openCreateDialog()))

    const dialog = screen.getByRole('dialog', { name: 'Add New Department' })
    const nameInput = within(dialog).getByRole('textbox', { name: /department name/i })
    const codeInput = within(dialog).getByRole('textbox', { name: /department code/i })

    expect(nameInput).toHaveAttribute('maxlength', '255')

    await user.type(nameInput, 'Bachelor of Science in Civil Engineering')
    await user.type(codeInput, 'bsce')
    await user.click(within(dialog).getByRole('button', { name: 'Create Department' }))

    await waitFor(() => {
      expect(service.createDepartment).toHaveBeenCalledWith({
        name: 'Bachelor of Science in Civil Engineering',
        code: 'BSCE',
        description: undefined,
        status: 'active',
      })
    })

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })
  })

  it('allows punctuation in the name and leaves the optional code unset', async () => {
    const user = userEvent.setup()
    vi.mocked(service.createDepartment).mockResolvedValue({} as service.DepartmentDetail)
    const name = "College of Arts & Sciences (O'Brien Hall) - Main Campus"

    render(<AdminPeopleDepartmentsPage embedded />)

    await user.click(await waitFor(() => openCreateDialog()))
    const dialog = screen.getByRole('dialog', { name: 'Add New Department' })

    await user.type(within(dialog).getByRole('textbox', { name: /department name/i }), name)
    await user.click(within(dialog).getByRole('button', { name: 'Create Department' }))

    await waitFor(() => {
      expect(service.createDepartment).toHaveBeenCalledWith({
        name,
        code: undefined,
        description: undefined,
        status: 'active',
      })
    })
  })

  it('shows the Laravel duplicate-name error next to the field and keeps the form open', async () => {
    const user = userEvent.setup()
    vi.mocked(service.createDepartment).mockRejectedValue(
      new ApiError('Validation failed', 422, {
        name: ['A department with this name already exists.'],
      }),
    )

    render(<AdminPeopleDepartmentsPage embedded />)

    await user.click(await waitFor(() => openCreateDialog()))
    const dialog = screen.getByRole('dialog', { name: 'Add New Department' })
    const nameInput = within(dialog).getByRole('textbox', { name: /department name/i })

    await user.type(nameInput, 'Finance')
    await user.click(within(dialog).getByRole('button', { name: 'Create Department' }))

    expect(await screen.findByText('A department with this name already exists.')).toBeInTheDocument()
    expect(nameInput).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByRole('dialog', { name: 'Add New Department' })).toBeInTheDocument()
  })

  it('shows the Laravel duplicate-code error next to the code field', async () => {
    const user = userEvent.setup()
    vi.mocked(service.createDepartment).mockRejectedValue(
      new ApiError('Validation failed', 422, {
        code: ['A department with this code already exists.'],
      }),
    )

    render(<AdminPeopleDepartmentsPage embedded />)

    await user.click(await waitFor(() => openCreateDialog()))
    const dialog = screen.getByRole('dialog', { name: 'Add New Department' })
    const codeInput = within(dialog).getByRole('textbox', { name: /department code/i })

    await user.type(within(dialog).getByRole('textbox', { name: /department name/i }), 'Computer Engineering')
    await user.type(codeInput, 'BSCE')
    await user.click(within(dialog).getByRole('button', { name: 'Create Department' }))

    expect(await screen.findByText('A department with this code already exists.')).toBeInTheDocument()
    expect(codeInput).toHaveAttribute('aria-invalid', 'true')
  })
})
