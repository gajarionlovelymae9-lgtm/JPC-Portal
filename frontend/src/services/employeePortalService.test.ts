import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from './api'
import {
  changeMyPassword,
  createMyReport,
  createMyReportRevision,
  getEmployeeDashboard,
  getMyAchievements,
  getMyCalendar,
  getMyNotifications,
  getMyPerformanceSummary,
  getMyProfile,
  getMyReport,
  getMyReports,
  getMyTargets,
  getMyTask,
  getMyTasks,
  submitMyReport,
  submitMyTask,
  updateMyProfile,
  updateMyReport,
  updateMyTaskProgress,
} from './employeePortalService'

function mockFetch(response: unknown = { success: true, message: 'ok', data: null }, status = 200) {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: status >= 200 && status < 300,
    status,
    json: async () => response,
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function callAt(fetchMock: ReturnType<typeof vi.fn>, index: number): { url: string; init: RequestInit } {
  const [url, init] = fetchMock.mock.calls[index]
  return { url: String(url), init: (init ?? {}) as RequestInit }
}

describe('employeePortalService', () => {
  beforeEach(() => {
    vi.unstubAllGlobals()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('targets every employee portal endpoint and uses the expected HTTP methods', async () => {
    const fetchMock = mockFetch()

    await getEmployeeDashboard()
    await getMyTasks({ status: 'pending' })
    await getMyTask(7)
    await updateMyTaskProgress(7, 50)
    await submitMyTask(7, { notes: 'done' })
    await getMyReports({ year: 2026 })
    await getMyReport(3)
    await createMyReport({ task_id: 1, title: 'Q3 Report' })
    await updateMyReport(3, { title: 'Q3 Report (revised)' })
    await submitMyReport(3)
    await createMyReportRevision(3, { revision_notes: 'added data' })
    await getMyCalendar({ month: 9, year: 2026 })
    await getMyPerformanceSummary()
    await getMyTargets()
    await getMyAchievements()
    await getMyNotifications()
    await getMyProfile()
    await updateMyProfile({ email: 'new@jpc.local' })
    await changeMyPassword({ current_password: 'x', password: 'y', password_confirmation: 'y' })

    const expectations: Array<[number, string, string]> = [
      [0, '/employee/dashboard', 'GET'],
      [1, '/employee/tasks?status=pending', 'GET'],
      [2, '/employee/tasks/7', 'GET'],
      [3, '/employee/tasks/7/progress', 'POST'],
      [4, '/employee/tasks/7/submit', 'POST'],
      [5, '/employee/reports?year=2026', 'GET'],
      [6, '/employee/reports/3', 'GET'],
      [7, '/employee/reports', 'POST'],
      [8, '/employee/reports/3', 'PUT'],
      [9, '/employee/reports/3/submit', 'POST'],
      [10, '/employee/reports/3/revision', 'POST'],
      [11, '/employee/calendar?month=9&year=2026', 'GET'],
      [12, '/employee/performance', 'GET'],
      [13, '/employee/targets', 'GET'],
      [14, '/employee/achievements', 'GET'],
      [15, '/employee/notifications', 'GET'],
      [16, '/employee/profile', 'GET'],
      [17, '/employee/profile', 'PUT'],
      [18, '/employee/security/password', 'PUT'],
    ]

    expect(fetchMock.mock.calls.length).toBe(expectations.length)
    expectations.forEach(([index, path, method]) => {
      const { url, init } = callAt(fetchMock, index)
      expect(url).toContain(path)
      expect(init.method ?? 'GET').toBe(method)
    })
  })

  it('serialises the report revision payload as JSON on the request body', async () => {
    const fetchMock = mockFetch()
    const payload = { revision_notes: 'added requested data', title: 'Q3 Report', content: 'body' }

    await createMyReportRevision(3, payload)

    const { init } = callAt(fetchMock, 0)
    expect(JSON.parse(String(init.body))).toEqual(payload)
  })

  it('surfaces backend error messages as ApiError', async () => {
    const fetchMock = mockFetch(
      { success: false, message: 'The password does not match.', errors: null },
      422,
    )

    const error = await changeMyPassword({
      current_password: 'wrong',
      password: 'newsecret',
      password_confirmation: 'newsecret',
    }).catch((e) => e)

    const { url } = callAt(fetchMock, 0)
    expect(url).toContain('/employee/security/password')
    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).message).toBe('The password does not match.')
  })
})