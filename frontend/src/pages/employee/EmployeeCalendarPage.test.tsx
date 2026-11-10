import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import EmployeeCalendarPage from './EmployeeCalendarPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

/**
 * Mirrors the real `/api/v1/employee/calendar` payload: tasks and reports owned
 * by the employee plus the announcements they are audience to. No fabricated
 * categories — the event `type`/`category` here match what the backend sends.
 */
const events = [
  {
    id: 11,
    type: 'task',
    category: 'Task',
    title: 'Submit accreditation evidence',
    description: null,
    date: '2099-01-05',
    time: null,
    status: 'in_progress',
    is_overdue: false,
    link: '/employee/tasks/7',
  },
  {
    id: 4,
    type: 'report',
    category: 'Report',
    title: 'Q3 departmental report',
    description: null,
    date: '2099-01-09',
    time: null,
    status: 'draft',
    is_overdue: true,
    link: '/employee/reports/4',
  },
  {
    id: 2,
    type: 'announcement',
    category: 'Holiday',
    title: 'Foundation Day holiday',
    description: 'Campus closed for Foundation Day.',
    date: '2099-01-20',
    time: '08:00',
    status: 'published',
    is_overdue: false,
    link: null,
  },
]

function stubApi(data: unknown = { events }, status = 200) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/employee/calendar')) {
        return Promise.resolve(
          status === 200
            ? json({ success: true, message: 'ok', data })
            : json({ success: false, message: 'failed', errors: null }, status),
        )
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

function renderPage() {
  return render(
    <MemoryRouter>
      <EmployeeCalendarPage />
    </MemoryRouter>,
  )
}

describe('EmployeeCalendarPage (Upcoming Events panel)', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the upcoming events panel from the API, nearest date first', async () => {
    stubApi()
    renderPage()

    expect(await screen.findByRole('heading', { name: 'Upcoming Events' })).toBeInTheDocument()

    // All three real events, each with its title, category chip and status pill.
    expect(screen.getByText('Submit accreditation evidence')).toBeInTheDocument()
    expect(screen.getByText('Q3 departmental report')).toBeInTheDocument()
    expect(screen.getByText('Foundation Day holiday')).toBeInTheDocument()

    expect(screen.getByText('Task')).toBeInTheDocument()
    expect(screen.getByText('Report')).toBeInTheDocument()
    expect(screen.getByText('Holiday')).toBeInTheDocument()

    // The overdue event is flagged and keeps a link to its own page.
    expect(screen.getByText('Overdue')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Open Q3 departmental report' })).toHaveAttribute(
      'href',
      '/employee/reports/4',
    )
    // Announcements have no employee-facing page, so no link is rendered.
    expect(screen.queryByRole('link', { name: 'Open Foundation Day holiday' })).toBeNull()
  })

  it('filters the list by category', async () => {
    stubApi()
    renderPage()

    await screen.findByText('Submit accreditation evidence')

    await userEvent.click(screen.getByRole('button', { name: /^Reports/ }))

    expect(screen.getByText('Q3 departmental report')).toBeInTheDocument()
    expect(screen.queryByText('Submit accreditation evidence')).toBeNull()
    expect(screen.queryByText('Foundation Day holiday')).toBeNull()
  })

  it('opens an event detail with its description and related-record link', async () => {
    stubApi()
    renderPage()

    await userEvent.click(await screen.findByText('Foundation Day holiday'))

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText('Campus closed for Foundation Day.')).toBeInTheDocument()
    // Announcements carry no internal route, so only the Close action is offered.
    expect(within(dialog).queryByRole('link')).toBeNull()
  })

  it('shows an empty state when there are no events this month', async () => {
    stubApi({ events: [] })
    renderPage()

    expect(await screen.findByText('No events this month.')).toBeInTheDocument()
  })

  it('surfaces a load error and retries', async () => {
    stubApi({ events: [] }, 500)
    renderPage()

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('The server ran into a problem. Please try again later.')

    stubApi()
    await userEvent.click(within(alert).getByRole('button', { name: /Retry/ }))

    await waitFor(() => {
      expect(screen.getByText('Submit accreditation evidence')).toBeInTheDocument()
    })
  })
})

describe('EmployeeCalendarPage (monthly calendar grid)', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  /** Accessible name of a grid day: "Thursday, November 5, 2026". */
  const dateLabel = (date: Date) =>
    date.toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })

  const monthHeading = (date: Date) =>
    `${date.toLocaleString('default', { month: 'long' })} ${date.getFullYear()}`

  const gridCells = () =>
    screen.getAllByRole('button', { name: /day, [A-Z][a-z]+ \d{1,2}, \d{4}/ })

  const isLeapYear = (year: number) =>
    (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0

  it('renders the seven weekday headers and a full 42-cell month grid', async () => {
    stubApi({ events: [] })
    renderPage()
    await screen.findByText('No events this month.')

    for (const weekday of ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']) {
      expect(screen.getByText(weekday)).toBeInTheDocument()
    }

    expect(gridCells()).toHaveLength(42)
  })

  it('aligns the grid to the correct weekday and covers the whole selected month', async () => {
    stubApi({ events: [] })
    renderPage()
    await screen.findByText('No events this month.')

    const today = new Date()
    const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
    const lastOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0)
    const gridStart = new Date(today.getFullYear(), today.getMonth(), 1 - firstOfMonth.getDay())

    // The grid opens on the Sunday on or before the 1st.
    expect(gridCells()[0]).toHaveAccessibleName(dateLabel(gridStart))
    // Both ends of the month are present.
    expect(screen.getByRole('button', { name: dateLabel(firstOfMonth) })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: dateLabel(lastOfMonth) })).toBeInTheDocument()
    // Today is highlighted (selected by default).
    expect(screen.getByRole('button', { name: dateLabel(today) })).toHaveAttribute('aria-pressed', 'true')
  })

  it('navigates to the previous/next month and back with Today', async () => {
    stubApi({ events: [] })
    renderPage()
    await screen.findByText('No events this month.')

    const today = new Date()
    expect(screen.getByText(monthHeading(today))).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Next month' }))
    expect(
      screen.getByText(monthHeading(new Date(today.getFullYear(), today.getMonth() + 1, 1))),
    ).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Previous month' }))
    expect(screen.getByText(monthHeading(today))).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Previous month' }))
    expect(
      screen.getByText(monthHeading(new Date(today.getFullYear(), today.getMonth() - 1, 1))),
    ).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Today' }))
    expect(screen.getByText(monthHeading(today))).toBeInTheDocument()
  })

  it('selects a clicked date and shows that day’s real events', async () => {
    const today = new Date()
    // Pick a day that is guaranteed to be a different day of the same month.
    const day = today.getDate() === 15 ? 16 : 15
    const iso = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    const target = new Date(today.getFullYear(), today.getMonth(), day)

    stubApi({
      events: [
        {
          id: 99,
          type: 'task',
          category: 'Task',
          title: 'Mid-month checkpoint',
          description: null,
          date: iso,
          time: null,
          status: 'in_progress',
          is_overdue: false,
          link: '/employee/tasks/9',
        },
      ],
    })
    renderPage()

    // The real event surfaces as an indicator on exactly the correct day cell.
    const cell = await screen.findByRole('button', { name: `${dateLabel(target)}, 1 event` })
    expect(cell).toHaveAttribute('aria-pressed', 'false')

    // Today carries no events, so its detail strip is empty…
    expect(screen.getByText('Nothing scheduled on this day.')).toBeInTheDocument()

    await userEvent.click(cell)

    // …and selecting the event's day selects it and reveals the record.
    expect(cell).toHaveAttribute('aria-pressed', 'true')
    expect(screen.getByRole('button', { name: dateLabel(today) })).toHaveAttribute('aria-pressed', 'false')
    // The record now surfaces three times: the in-cell label, the selected-day
    // strip and the Upcoming Events panel.
    expect(screen.getAllByText('Mid-month checkpoint')).toHaveLength(3)
  })

  it('handles a leap-year February with 29 days', async () => {
    stubApi({ events: [] })
    renderPage()
    await screen.findByText('No events this month.')

    const today = new Date()
    let leapYear = today.getFullYear()
    if (!(isLeapYear(leapYear) && today.getMonth() <= 1)) {
      do {
        leapYear += 1
      } while (!isLeapYear(leapYear))
    }
    const monthsAhead = (leapYear - today.getFullYear()) * 12 + (1 - today.getMonth())

    for (let step = 0; step < monthsAhead; step += 1) {
      fireEvent.click(screen.getByRole('button', { name: 'Next month' }))
    }
    // Let the month refetches settle so no state updates land outside act().
    await act(async () => {
      await Promise.resolve()
    })

    expect(screen.getByText(monthHeading(new Date(leapYear, 1, 1)))).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: dateLabel(new Date(leapYear, 1, 29)) }),
    ).toBeInTheDocument()
  }, 15000)
})

describe('EmployeeCalendarPage (in-cell event labels)', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  const dateLabel = (date: Date) =>
    date.toLocaleDateString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })

  /** Local YYYY-MM-DD for a day in the month currently shown by the calendar. */
  const isoThisMonth = (day: number) => {
    const today = new Date()
    return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  }

  /** Builds a calendar event with the shared defaults, mirroring the API shape. */
  const event = (over: Record<string, unknown>) => ({
    description: null,
    time: null,
    status: 'in_progress',
    is_overdue: false,
    link: null,
    ...over,
  })

  it('shows the category and title of every event inside its date cell', async () => {
    const day = 12
    const iso = isoThisMonth(day)
    const today = new Date()
    const target = new Date(today.getFullYear(), today.getMonth(), day)

    stubApi({
      events: [
        event({ id: 1, type: 'task', category: 'Task', title: 'Label A', date: iso }),
        event({
          id: 2,
          type: 'announcement',
          category: 'Deadline',
          title: 'Label B',
          date: iso,
          status: 'published',
        }),
      ],
    })
    renderPage()

    // The day cell announces its two events and lists both as labels.
    const cell = await screen.findByRole('button', { name: `${dateLabel(target)}, 2 events` })
    const cellBox = cell.parentElement as HTMLElement

    expect(within(cellBox).getByText('Task')).toBeInTheDocument()
    expect(within(cellBox).getByText('Label A')).toBeInTheDocument()
    expect(within(cellBox).getByText('Deadline')).toBeInTheDocument()
    expect(within(cellBox).getByText('Label B')).toBeInTheDocument()

    // Each label carries its official category colour.
    expect(screen.getByTitle('Task: Label A')).toHaveClass('bg-blue-100', 'text-blue-700')
    expect(screen.getByTitle('Deadline: Label B')).toHaveClass('bg-red-100', 'text-red-700')
  })

  it('collapses extra events into "+X more" and opens the full day list', async () => {
    const day = 12
    const iso = isoThisMonth(day)

    stubApi({
      events: Array.from({ length: 5 }, (_, index) =>
        event({
          id: 100 + index,
          type: 'task',
          category: 'Task',
          title: `Bulk event ${index + 1}`,
          date: iso,
        }),
      ),
    })
    renderPage()

    // Only the first three events are previewed; the rest collapse.
    expect(await screen.findByTitle('Task: Bulk event 1')).toBeInTheDocument()
    expect(screen.getByTitle('Task: Bulk event 3')).toBeInTheDocument()
    expect(screen.queryByTitle('Task: Bulk event 4')).toBeNull()

    await userEvent.click(screen.getByRole('button', { name: '+2 more' }))

    const dialog = await screen.findByRole('dialog', { name: /Events on/ })
    expect(within(dialog).getAllByText(/^Bulk event \d$/)).toHaveLength(5)
  })

  it('opens an event’s details from its in-cell label', async () => {
    const day = 12
    const iso = isoThisMonth(day)

    stubApi({
      events: [event({ id: 1, type: 'task', category: 'Task', title: 'Label A', date: iso })],
    })
    renderPage()

    await userEvent.click(await screen.findByTitle('Task: Label A'))

    const dialog = await screen.findByRole('dialog', { name: 'Label A' })
    // Tasks are scheduled by their deadline, so the field reads Deadline.
    expect(within(dialog).getByText('Deadline')).toBeInTheDocument()
  })
})
