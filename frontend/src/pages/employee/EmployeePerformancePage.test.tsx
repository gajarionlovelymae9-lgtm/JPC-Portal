import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { EmployeePerformanceData } from '../../types/performance'
import EmployeePerformancePage from './EmployeePerformancePage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => null },
    json: async () => payload,
  }
}

const MONTHS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
]

function makeData(
  year = 2026,
  month = 11,
  overrides: Partial<EmployeePerformanceData> = {},
): EmployeePerformanceData {
  return {
    period: {
      id: 9,
      name: `${MONTHS[month - 1]} ${year}`,
      period_type: 'monthly',
      year,
      month,
      quarter: Math.ceil(month / 3),
      start_date: `${year}-${String(month).padStart(2, '0')}-01T00:00:00.000000Z`,
      end_date: `${year}-${String(month).padStart(2, '0')}-28T00:00:00.000000Z`,
      status: 'open',
    },
    employee: {
      id: 4,
      name: 'JPC Employee',
      department: 'College of Education',
      position: 'Staff',
    },
    kpis: {
      task_completion_rate: 83.33,
      report_completion_rate: 100,
      on_time_submission_rate: 100,
      approval_rate: 50,
      target_achievement_rate: 25,
    },
    productivity_score: 75.5,
    rating: 'Good',
    rating_color: 'amber',
    calculation_version: 'v1.0',
    breakdowns: [
      {
        metric_key: 'task_completion',
        metric_label: 'Task Completion',
        raw_value: 83.33,
        weight: 30,
        weighted_value: 25,
      },
      {
        metric_key: 'report_completion',
        metric_label: 'Report Completion',
        raw_value: 100,
        weight: 20,
        weighted_value: 20,
      },
    ],
    metadata: {
      total_tasks: 6,
      completed_tasks: 5,
      expected_reports: 1,
      submitted_reports: 1,
      on_time_reports: 1,
      late_reports: 0,
      reviewed_reports: 2,
      approved_reports: 1,
      rejected_reports: 1,
      achieved_target_val: 25,
      total_target_val: 100,
    },
    has_activity: true,
    badges: [
      {
        id: 1,
        code: 'TOP_PERFORMER',
        name: 'Top Performer',
        description: 'Achieved an overall performance score of at least 95.',
        awarded_at: '2026-07-31T17:00:00.000000Z',
      },
    ],
    history: [
      { period_label: 'Jul 2026', year: 2026, month: 7, score: 70, rating: 'Good' },
      { period_label: 'Nov 2026', year, month, score: 75.5, rating: 'Good' },
    ],
    ...overrides,
  }
}

function stubApi(
  respond: (url: URL, call: number) => ReturnType<typeof json> | Promise<ReturnType<typeof json>>,
) {
  const fetchMock = vi.fn((input: RequestInfo | URL) => {
    const url = new URL(String(input), 'http://localhost')
    return Promise.resolve(respond(url, fetchMock.mock.calls.length))
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function requestPeriod(fetchMock: ReturnType<typeof vi.fn>, index = 0) {
  const url = new URL(String(fetchMock.mock.calls[index][0]), 'http://localhost')
  return {
    year: Number(url.searchParams.get('year')),
    month: Number(url.searchParams.get('month')),
  }
}

describe('EmployeePerformancePage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('loads and renders the authenticated employee performance payload', async () => {
    stubApi((url) => {
      const year = Number(url.searchParams.get('year'))
      const month = Number(url.searchParams.get('month'))
      return json({ success: true, message: 'ok', data: makeData(year, month) })
    })

    render(<EmployeePerformancePage />)

    expect(screen.getByText('Computing verified performance data...')).toBeInTheDocument()

    expect((await screen.findAllByText('75.5%')).length).toBeGreaterThan(0)
    expect(screen.getAllByText('Good').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Task Completion').length).toBeGreaterThan(0)
    expect(screen.getByText('5 of 6 tasks completed')).toBeInTheDocument()
    expect(screen.getByText('1 on-time (0 late)')).toBeInTheDocument()
    expect(screen.getByText('Top Performer')).toBeInTheDocument()

    const now = new Date()
    expect(
      screen.getByText(`${MONTHS[now.getMonth()]} ${now.getFullYear()}`),
    ).toBeInTheDocument()
  })

  it('filters the request and the rendered period by month and year', async () => {
    const fetchMock = stubApi((url) => {
      const year = Number(url.searchParams.get('year'))
      const month = Number(url.searchParams.get('month'))
      return json({ success: true, message: 'ok', data: makeData(year, month) })
    })

    const user = userEvent.setup()
    render(<EmployeePerformancePage />)

    await screen.findAllByText('Task Completion')

    const currentYear = new Date().getFullYear()
    const previousYear = currentYear - 1
    const [monthSelect, yearSelect] = screen.getAllByRole('combobox')

    await user.selectOptions(monthSelect, '1')
    await user.selectOptions(yearSelect, String(previousYear))

    await waitFor(() => {
      expect(requestPeriod(fetchMock, fetchMock.mock.calls.length - 1)).toEqual({
        year: previousYear,
        month: 1,
      })
    })
    expect(await screen.findByText(`January ${previousYear}`)).toBeInTheDocument()
  })

  it('reloads real data when Refresh is clicked', async () => {
    let call = 0
    const fetchMock = stubApi((url) => {
      call += 1
      const year = Number(url.searchParams.get('year'))
      const month = Number(url.searchParams.get('month'))
      return json({
        success: true,
        message: 'ok',
        data: makeData(year, month, { productivity_score: call === 1 ? 40 : 80 }),
      })
    })

    const user = userEvent.setup()
    render(<EmployeePerformancePage />)

    expect((await screen.findAllByText('40.0%')).length).toBeGreaterThan(0)

    await user.click(screen.getByRole('button', { name: /Refresh/ }))

    expect((await screen.findAllByText('80.0%')).length).toBeGreaterThan(0)
    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(requestPeriod(fetchMock, 0)).toEqual(requestPeriod(fetchMock, 1))
  })

  it('shows an empty state instead of fabricating a score when the period has no activity', async () => {
    stubApi(() => json({ success: true, message: 'ok', data: makeData(2026, 11, { has_activity: false }) }))

    render(<EmployeePerformancePage />)

    expect(await screen.findByText('No performance activity for this period')).toBeInTheDocument()
    expect(screen.queryByText('Official Productivity Score')).not.toBeInTheDocument()
  })

  it('surfaces the real API error message instead of a generic connection error', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => undefined)
    stubApi(() => json({ success: false, message: 'No associated employee record found.' }, 404))

    render(<EmployeePerformancePage />)

    expect(await screen.findByText(/No associated employee record found/)).toBeInTheDocument()
    expect(screen.queryByText(/Unable to connect to the performance service/)).not.toBeInTheDocument()
  })

  it('reports a connection failure only when the network request itself fails', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => undefined)
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')))

    render(<EmployeePerformancePage />)

    expect(
      await screen.findByText(/Unable to connect to the performance service/),
    ).toBeInTheDocument()
  })
})
