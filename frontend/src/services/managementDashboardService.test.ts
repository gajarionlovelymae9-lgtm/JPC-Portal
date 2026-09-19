import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  getDashboardDepartments,
  getDashboardEmployees,
  getDashboardMonthlyAchievement,
  getDashboardMonthlyProgress,
  getDashboardOptions,
  getDashboardOverdue,
  getDashboardReviewQueue,
  getDashboardSummary,
  getDashboardTargetVsActual,
  getEmployeeDetailProgress,
} from './managementDashboardService'

function mockFetch() {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({ success: true, message: 'ok', data: [] }),
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function calledUrl(fetchMock: ReturnType<typeof vi.fn>): string {
  return String(fetchMock.mock.calls[0][0])
}

describe('managementDashboardService', () => {
  beforeEach(() => {
    vi.unstubAllGlobals()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('targets the dashboard endpoints without a query when no filters are set', async () => {
    const fetchMock = mockFetch()

    await getDashboardOptions()
    await getDashboardSummary()
    await getDashboardEmployees()

    const urls = fetchMock.mock.calls.map((call) => String(call[0]))
    expect(urls[0]).toContain('/admin/dashboard/options')
    expect(urls[1]).toContain('/admin/dashboard/summary')
    expect(urls[2]).toContain('/admin/dashboard/employees')
  })

  it('builds the summary query string from scope filters', async () => {
    const fetchMock = mockFetch()

    await getDashboardSummary({
      department_id: 3,
      academic_year_id: 4,
      year: 2026,
      month: 2,
      from_date: '2026-01-01',
      to_date: '2026-12-31',
    })

    const url = calledUrl(fetchMock)
    expect(url).toContain('/summary?')
    expect(url).toContain('department_id=3')
    expect(url).toContain('academic_year_id=4')
    expect(url).toContain('year=2026')
    expect(url).toContain('month=2')
    expect(url).toContain('from_date=2026-01-01')
    expect(url).toContain('to_date=2026-12-31')
  })

  it('builds paginated employee/table query strings', async () => {
    const fetchMock = mockFetch()

    await getDashboardEmployees({ search: 'Smith', page: 2, per_page: 10 })

    const url = calledUrl(fetchMock)
    expect(url).toContain('/employees?')
    expect(url).toContain('search=Smith')
    expect(url).toContain('page=2')
    expect(url).toContain('per_page=10')
  })

  it('targets the employee detail endpoint with a path id', async () => {
    const fetchMock = mockFetch()

    await getEmployeeDetailProgress(7, { year: 2026 })

    const url = calledUrl(fetchMock)
    expect(url).toContain('/employees/7/progress')
    expect(url).toContain('year=2026')
  })

  it('targets the remaining management endpoints', async () => {
    const fetchMock = mockFetch()

    await getDashboardDepartments()
    await getDashboardOverdue()
    await getDashboardReviewQueue()
    await getDashboardMonthlyProgress()
    await getDashboardMonthlyAchievement()
    await getDashboardTargetVsActual()

    const urls = fetchMock.mock.calls.map((call) => String(call[0]))
    expect(urls[0]).toContain('/admin/dashboard/departments')
    expect(urls[1]).toContain('/admin/dashboard/overdue')
    expect(urls[2]).toContain('/admin/dashboard/review-queue')
    expect(urls[3]).toContain('/admin/dashboard/monthly-progress')
    expect(urls[4]).toContain('/admin/dashboard/monthly-achievement')
    expect(urls[5]).toContain('/admin/dashboard/target-vs-actual')
  })
})