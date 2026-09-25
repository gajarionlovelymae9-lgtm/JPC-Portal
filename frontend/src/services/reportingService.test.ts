import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  getAchievementReport,
  getDataQualityReport,
  getDepartmentReport,
  getEmployeeReport,
  getInstitutionReport,
  getLateReport,
  getManagementSummary,
  getMonthlyReport,
  getPendingReport,
  getPerformanceDistribution,
  getPerformanceTrend,
  getRankings,
  getTargetReport,
} from './reportingService'

function mockFetch() {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({ success: true, message: 'ok', data: null }),
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

describe('reportingService', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('targets the Phase 9 report endpoints under /reports', async () => {
    const fetchMock = mockFetch()

    await getEmployeeReport({ year: 2026, month: 9 })
    await getDepartmentReport({ year: 2026, month: 9, department_id: 2 })
    await getInstitutionReport({ year: 2026, month: 9 })
    await getMonthlyReport(2026, 9)
    await getAchievementReport({ year: 2026, month: 9 })
    await getLateReport({ year: 2026, month: 9 })
    await getPendingReport({ year: 2026, month: 9 })
    await getTargetReport({ year: 2026, month: 9 })
    await getDataQualityReport({ year: 2026, month: 9 })
    await getManagementSummary(2026, 9)

    const urls = fetchMock.mock.calls.map((call) => String(call[0]))
    expect(urls[0]).toContain('/reports/employee?')
    expect(urls[0]).toContain('year=2026')
    expect(urls[1]).toContain('/reports/department?')
    expect(urls[1]).toContain('department_id=2')
    expect(urls[2]).toContain('/reports/institution?')
    expect(urls[3]).toContain('/reports/monthly?year=2026&month=9')
    expect(urls[4]).toContain('/reports/achievements?')
    expect(urls[5]).toContain('/reports/late?')
    expect(urls[6]).toContain('/reports/pending?')
    expect(urls[7]).toContain('/reports/targets?')
    expect(urls[8]).toContain('/reports/data-quality?')
    expect(urls[9]).toContain('/reports/management-summary?year=2026&month=9')
  })

  it('targets the Phase 9 analytics endpoints under /analytics', async () => {
    const fetchMock = mockFetch()

    await getPerformanceTrend(6, { year: 2026 })
    await getRankings({ year: 2026 }, 'completion_rate')
    await getPerformanceDistribution({ year: 2026, month: 9 })

    const urls = fetchMock.mock.calls.map((call) => String(call[0]))
    expect(urls[0]).toContain('/analytics/performance-trend?')
    expect(urls[0]).toContain('months=6')
    expect(urls[1]).toContain('/analytics/rankings?')
    expect(urls[1]).toContain('metric=completion_rate')
    expect(urls[2]).toContain('/analytics/performance-distribution?')
    expect(urls[2]).toContain('month=9')
  })

  it('drops empty filter values from the query string', async () => {
    const fetchMock = mockFetch()

    await getLateReport({ year: 2026, month: undefined })

    const url = String(fetchMock.mock.calls[0][0])
    expect(url).toContain('year=2026')
    expect(url).not.toContain('month')
  })
})