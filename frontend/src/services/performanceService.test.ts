import { afterEach, describe, expect, it, vi } from 'vitest'
import { performanceService } from './performanceService'

/**
 * `apiRequest` already prefixes the configured API base (`/api/v1`). These
 * tests pin the single-prefix contract so a future edit cannot silently
 * recreate the double `/api/v1/api/v1/...` URL that broke the performance
 * dashboard with a 404.
 */

function mockFetch() {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: true,
    status: 200,
    headers: { get: () => null },
    json: async () => ({ success: true, message: 'ok', data: null }),
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function calledUrls(fetchMock: ReturnType<typeof vi.fn>): string[] {
  return fetchMock.mock.calls.map((call) => String(call[0]))
}

describe('performanceService', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('requests the personal performance endpoint with one /api/v1 prefix', async () => {
    const fetchMock = mockFetch()

    await performanceService.getMyPerformance(2026, 11)

    const [url] = calledUrls(fetchMock)
    expect(url).toBe('/api/v1/performance/me?year=2026&month=11')
    expect(url).not.toContain('/api/v1/api/v1')
  })

  it('bypasses the browser cache when refreshing performance data', async () => {
    const fetchMock = mockFetch()

    await performanceService.getMyPerformance(2026, 11)

    expect(fetchMock.mock.calls[0][1]).toMatchObject({ cache: 'no-store' })
  })

  it('uses single-prefix URLs for every performance and achievement endpoint', async () => {
    const fetchMock = mockFetch()

    await performanceService.getMyHistory()
    await performanceService.getPerformanceList({ year: 2026, month: 8 })
    await performanceService.getEmployeePerformance(4, 2026, 8)
    await performanceService.getRankings(2026, 8, 2)
    await performanceService.getBadgesCatalog()
    await performanceService.getMyAchievements()
    await performanceService.getMyAchievementProgress()
    await performanceService.getMonthlyAchievements(2026, 8)

    const urls = calledUrls(fetchMock)
    expect(urls).toEqual([
      '/api/v1/performance/me/history',
      '/api/v1/performance?year=2026&month=8',
      '/api/v1/performance/employees/4?year=2026&month=8',
      '/api/v1/performance/rankings?year=2026&month=8&department_id=2',
      '/api/v1/achievements',
      '/api/v1/achievements/me',
      '/api/v1/achievements/progress',
      '/api/v1/achievements/monthly?year=2026&month=8',
    ])
    expect(urls.every((url) => !url.startsWith('/api/v1/api/v1'))).toBe(true)
  })

  it('sends a single JSON-encoded recalculation body', async () => {
    const fetchMock = mockFetch()

    await performanceService.recalculatePerformance({ year: 2026, month: 8, reason: 'QA' })

    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/v1/performance/recalculate')
    expect(init.method).toBe('POST')
    expect(JSON.parse(String(init.body))).toEqual({ year: 2026, month: 8, reason: 'QA' })
  })
})
