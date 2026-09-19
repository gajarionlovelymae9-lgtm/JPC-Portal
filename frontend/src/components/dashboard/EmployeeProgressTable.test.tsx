import { render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { EmployeeProgressTable } from './EmployeeProgressTable'
import { getDashboardEmployees } from '../../services/managementDashboardService'

vi.mock('../../services/managementDashboardService', () => ({
  getDashboardEmployees: vi.fn(),
}))

const mockedGetEmployees = vi.mocked(getDashboardEmployees)

const row = {
  employee_id: 3,
  employee_name: 'Maria Santos',
  assigned_tasks: 6,
  completed_tasks: 4,
  pending_tasks: 1,
  overdue_tasks: 1,
  completion_rate: 66.67,
}

const envelope = (rows: typeof row[], meta: { current_page: number; last_page: number; per_page: number; total: number }) => ({
  success: true,
  message: 'ok',
  data: rows,
  meta,
})

describe('EmployeeProgressTable', () => {
  afterEach(() => {
    vi.clearAllMocks()
  })

  it('shows a loading message while the request is in flight', () => {
    mockedGetEmployees.mockImplementation(() => new Promise(() => {}))

    render(<EmployeeProgressTable scope={{}} refreshVersion={0} selectedEmployeeId={null} onSelect={vi.fn()} />)

    expect(screen.getByText('Loading employee progress…')).toBeInTheDocument()
  })

  it('renders employee rows once loaded', async () => {
    mockedGetEmployees.mockResolvedValue(envelope([row], { current_page: 1, last_page: 1, per_page: 10, total: 1 }))

    render(<EmployeeProgressTable scope={{}} refreshVersion={0} selectedEmployeeId={null} onSelect={vi.fn()} />)

    expect(await screen.findByText('Maria Santos')).toBeInTheDocument()
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '67')
  })

  it('shows an empty state when no employees match', async () => {
    mockedGetEmployees.mockResolvedValue(envelope([], { current_page: 1, last_page: 1, per_page: 10, total: 0 }))

    render(<EmployeeProgressTable scope={{}} refreshVersion={0} selectedEmployeeId={null} onSelect={vi.fn()} />)

    expect(
      await screen.findByText('No employees match the current filters.'),
    ).toBeInTheDocument()
  })

  it('shows pagination when more than one page exists', async () => {
    mockedGetEmployees.mockResolvedValue(envelope([row], { current_page: 1, last_page: 2, per_page: 10, total: 15 }))

    render(<EmployeeProgressTable scope={{}} refreshVersion={0} selectedEmployeeId={null} onSelect={vi.fn()} />)

    expect(await screen.findByText(/Page 1 of 2/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Next' })).toBeInTheDocument()
  })
})