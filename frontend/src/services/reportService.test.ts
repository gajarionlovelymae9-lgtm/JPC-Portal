import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from './api'
import {
  downloadReportExport,
  getDepartmentPerformance,
  getEmployeeAchievements,
  getEmployeePerformance,
  getMonthlyAchievements,
  getMyPerformance,
  getProgressTrend,
  getReportOptions,
  getTaskCompletionReport,
  getTopPerformers,
} from './reportService'

function mockFetch() {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({ success: true, message: 'ok', data: null }),
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function calledUrl(fetchMock: ReturnType<typeof vi.fn>): string {
  return String(fetchMock.mock.calls[0][0])
}

describe('reportService', () => {
  beforeEach(() => {
    vi.unstubAllGlobals()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('targets the report endpoints under /reports', async () => {
    const fetchMock = mockFetch()

    await getReportOptions()
    await getMyPerformance()
    await getTaskCompletionReport()
    await getEmployeePerformance()
    await getDepartmentPerformance()
    await getMonthlyAchievements()
    await getEmployeeAchievements(4)
    await getProgressTrend()
    await getTopPerformers()

    const urls = fetchMock.mock.calls.map((call) => String(call[0]))
    expect(urls[0]).toContain('/reports/options')
    expect(urls[1]).toContain('/reports/me/performance')
    expect(urls[2]).toContain('/reports/task-completion')
    expect(urls[3]).toContain('/reports/employees')
    expect(urls[4]).toContain('/reports/departments')
    expect(urls[5]).toContain('/reports/achievements/monthly')
    expect(urls[6]).toContain('/reports/achievements/employees/4')
    expect(urls[7]).toContain('/reports/trend/monthly-completions')
    expect(urls[8]).toContain('/reports/top-performers')
  })

  it('serialises filters onto the query string and omits empty values', async () => {
    const fetchMock = mockFetch()

    await getEmployeePerformance({
      department_id: 3,
      month: 9,
      year: 2026,
      sort: 'completion_rate',
      direction: 'desc',
    })
    const url = calledUrl(fetchMock)
    expect(url).toContain('department_id=3')
    expect(url).toContain('month=9')
    expect(url).toContain('year=2026')
    expect(url).toContain('sort=completion_rate')
    expect(url).toContain('direction=desc')

    await getMyPerformance({})
    expect(String(fetchMock.mock.calls[1][0]).endsWith('/reports/me/performance')).toBe(true)
  })
})

describe('downloadReportExport', () => {
  beforeEach(() => {
    vi.unstubAllGlobals()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('requests the export with the chosen format and triggers a file download', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: new Headers({ 'Content-Disposition': 'attachment; filename="JPC_Achievements.csv"' }),
      blob: async () => new Blob(['a,b'], { type: 'text/csv' }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)

    await downloadReportExport('achievements', 'csv', { scope: 'monthly' })

    const url = String(fetchMock.mock.calls[0][0])
    expect(url).toContain('/reports/export/achievements?')
    expect(url).toContain('format=csv')
    expect(url).toContain('scope=monthly')
    expect(click).toHaveBeenCalled()

    click.mockRestore()
  })

  it('surfaces rate-limit responses as an ApiError', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 429,
      json: async () => ({ success: false, message: 'Too many export requests. Try again later.', errors: null }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const error = await downloadReportExport('task-completion', 'xlsx').catch((e) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(429)
    expect((error as ApiError).message).toBe('Too many export requests. Try again later.')
  })
})