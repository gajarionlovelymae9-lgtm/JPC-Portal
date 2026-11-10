import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'

import AdminPeoplePage from './AdminPeoplePage'

const summaryPayload = {
  employees: { total: 42, active: 40, inactive: 2, with_late_reports: 1 },
  departments: { total: 6, active: 6, inactive: 0, with_head: 5, without_head: 1 },
  accounts: {
    total: 48,
    active: 45,
    inactive: 3,
    suspended: 1,
    unlinked_employee_accounts: 2,
    accounts_without_employee: 1,
  },
}

vi.mock('../../services/peopleManagementService', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../services/peopleManagementService')>()
  return {
    ...actual,
    getPeopleSummary: vi.fn(() => Promise.resolve({ success: true, message: 'ok', data: summaryPayload })),
  }
})

// Each tab body is an already-tested page component; stub them so the test
// focuses on the unified header, tab bar and URL mirroring.
vi.mock('../EmployeesPage', () => ({ default: () => <div>EMPLOYEES CONTENT</div> }))
vi.mock('./AdminPeopleDepartmentsPage', () => ({ default: () => <div>DEPARTMENTS CONTENT</div> }))
vi.mock('./AdminUserManagementPage', () => ({ default: () => <div>ACCOUNTS CONTENT</div> }))

function renderPeople(initialEntry: string) {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <AdminPeoplePage />
    </MemoryRouter>,
  )
}

describe('AdminPeoplePage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders the unified header, people summary strip and the employees tab by default', async () => {
    renderPeople('/admin/people')

    expect(screen.getByRole('heading', { name: 'People Management' })).toBeInTheDocument()
    expect(await screen.findByText('42')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Employees', selected: true })).toBeInTheDocument()
    expect(screen.getByText('EMPLOYEES CONTENT')).toBeInTheDocument()
  })

  it('switches tabs and mirrors the tab in the URL', async () => {
    const user = userEvent.setup()
    renderPeople('/admin/people')

    await user.click(screen.getByRole('tab', { name: 'Departments' }))
    expect(screen.getByText('DEPARTMENTS CONTENT')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Departments', selected: true })).toBeInTheDocument()

    await user.click(screen.getByRole('tab', { name: 'Accounts' }))
    expect(screen.getByText('ACCOUNTS CONTENT')).toBeInTheDocument()
  })

  it('deep-links to a tab through ?tab=', () => {
    renderPeople('/admin/people?tab=accounts')

    expect(screen.getByText('ACCOUNTS CONTENT')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Accounts', selected: true })).toBeInTheDocument()
  })
})
