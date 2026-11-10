import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import PlanningPage from './PlanningPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const SUCCESS = {
  success: true,
  message: 'Planning overview loaded successfully.',
  data: {
    summary: {
      academic_years: 2,
      annual_plans: 3,
      annual_plans_by_status: { approved: 3 },
      department_plans: 4,
      employee_targets: 5,
      employee_targets_by_status: { active: 5 },
    },
    academic_years: [
      {
        id: 1,
        name: '2026-2027',
        start_date: '2026-06-01T00:00:00.000000Z',
        end_date: '2027-05-31T00:00:00.000000Z',
        status: 'active',
      },
    ],
    annual_plans: [
      {
        id: 10,
        academic_year_id: 1,
        department_id: 7,
        title: 'Faculty Performance and Academic Reporting Plan',
        description: 'Development plan for teacher education faculty.',
        status: 'approved',
        approved_at: '2026-06-01T00:00:00.000000Z',
        department_plans_count: 1,
        employee_targets_count: 1,
        department: { id: 7, name: 'Teacher Education' },
        academic_year: { id: 1, name: '2026-2027' },
      },
    ],
    department_plans: [
      {
        id: 20,
        annual_plan_id: 10,
        department_id: 7,
        title: 'Departmental Reporting and Compliance Plan',
        status: 'approved',
        approved_at: '2026-06-02T00:00:00.000000Z',
        department: { id: 7, name: 'Teacher Education' },
        annual_plan: { id: 10, title: 'Faculty Performance and Academic Reporting Plan' },
      },
    ],
    employee_targets: [
      {
        id: 30,
        annual_plan_id: 10,
        employee_id: 3,
        title: 'Submit quarterly progress reports',
        target_value: '12.00',
        target_unit: 'reports',
        start_date: '2026-06-01T00:00:00.000000Z',
        end_date: '2027-05-31T00:00:00.000000Z',
        status: 'active',
        tasks_count: 1,
        employee_name: 'Maria Santos',
        employee_number: 'EMP-001',
        department_name: 'Teacher Education',
      },
    ],
  },
}

function stubApi() {
  const fetchMock = vi.fn((input: RequestInfo | URL) => {
    const url = String(input)
    if (url.includes('/planning')) {
      return Promise.resolve(json(SUCCESS))
    }
    return Promise.resolve(json({ success: false, message: 'Not found.' }, 404))
  })

  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function panelFor(title: string): HTMLElement {
  return screen.getByRole('heading', { level: 2, name: title }).closest('section') as HTMLElement
}

describe('PlanningPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the planning chain from the single overview endpoint', async () => {
    stubApi()
    render(<PlanningPage />)

    await screen.findByRole('heading', { level: 2, name: 'Annual plans' })

    expect(screen.getByRole('heading', { level: 1, name: 'Planning' })).toBeInTheDocument()

    // Summary cards.
    expect(screen.getByText('5')).toBeInTheDocument() // employee targets
    const yearsPanel = panelFor('Academic years')
    expect(within(yearsPanel).getByText('2026-2027')).toBeInTheDocument()

    const plansPanel = panelFor('Annual plans')
    expect(within(plansPanel).getByText('Faculty Performance and Academic Reporting Plan')).toBeInTheDocument()
    expect(within(plansPanel).getByText('Teacher Education')).toBeInTheDocument()

    const deptPanel = panelFor('Department plans')
    expect(within(deptPanel).getByText('Departmental Reporting and Compliance Plan')).toBeInTheDocument()

    const targetsPanel = panelFor('Employee targets')
    expect(within(targetsPanel).getByText('Submit quarterly progress reports')).toBeInTheDocument()
    expect(within(targetsPanel).getByText('Maria Santos')).toBeInTheDocument()
    expect(within(targetsPanel).getByText('EMP-001')).toBeInTheDocument()
  })

  it('exposes no mutation controls — the screen is read-only', async () => {
    stubApi()
    render(<PlanningPage />)

    await screen.findByRole('heading', { level: 2, name: 'Employee targets' })

    expect(screen.queryAllByRole('button')).toHaveLength(0)
  })

  it('shows the error panel with a working retry', async () => {
    let attempts = 0
    const fetchMock = vi.fn(() => {
      attempts += 1
      if (attempts === 1) {
        return Promise.resolve(json({ success: false, message: 'Server exploded.' }, 500))
      }
      return Promise.resolve(json(SUCCESS))
    })
    vi.stubGlobal('fetch', fetchMock)

    const user = userEvent.setup()
    render(<PlanningPage />)

    const panel = await screen.findByText('Something went wrong')
    expect(panel).toBeInTheDocument()
    expect(screen.getByText('Server exploded.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Try again' }))

    await waitFor(() => {
      expect(fetchMock.mock.calls.length).toBe(2)
    })
    expect(await screen.findByRole('heading', { level: 2, name: 'Annual plans' })).toBeInTheDocument()
  })
})
