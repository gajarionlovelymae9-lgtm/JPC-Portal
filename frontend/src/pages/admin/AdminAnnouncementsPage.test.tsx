import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AdminAnnouncementsPage from './AdminAnnouncementsPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const BASE = {
  audience_type: 'all',
  audience_id: null,
  start_date: null,
  end_date: null,
  created_at: '2026-10-01T00:00:00Z',
}

const DRAFT = {
  id: 1,
  title: 'Draft notice',
  message: 'A draft body.',
  priority: 'normal',
  category: 'general',
  status: 'draft',
  published_at: null,
  ...BASE,
}

const SCHEDULED = {
  id: 2,
  title: 'Scheduled notice',
  message: 'Scheduled body.',
  priority: 'high',
  category: 'academic',
  status: 'scheduled',
  published_at: '2099-01-01T00:00:00Z',
  ...BASE,
}

const PUBLISHED = {
  id: 3,
  title: 'Published notice',
  message: 'Published body.',
  priority: 'critical',
  category: 'event',
  status: 'published',
  published_at: '2026-10-01T00:00:00Z',
  ...BASE,
}

function stubApi() {
  const fetchMock = vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)

    if (url.includes('/admin/announcements')) {
      const method = init?.method ?? 'GET'
      if (method !== 'GET') {
        return Promise.resolve(json({ success: true, message: 'ok', data: { id: 99 } }))
      }
      return Promise.resolve(
        json({ success: true, message: 'Announcements retrieved', data: { data: [DRAFT, SCHEDULED, PUBLISHED] } }),
      )
    }

    return Promise.resolve(json({ success: true, message: 'ok', data: null }))
  })

  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function callsTo(fetchMock: ReturnType<typeof vi.fn>, method: string, path: string) {
  return fetchMock.mock.calls.filter(
    ([input, init]) => String(input).includes(path) && (init as RequestInit | undefined)?.method === method,
  )
}

describe('AdminAnnouncementsPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('lists announcements with their category and status, and per-status actions', async () => {
    stubApi()
    render(<AdminAnnouncementsPage />)

    expect(await screen.findByText('Draft notice')).toBeInTheDocument()
    expect(screen.getByText('Scheduled notice')).toBeInTheDocument()
    expect(screen.getByText('Published notice')).toBeInTheDocument()

    const draftCard = screen.getByText('Draft notice').closest('div.rounded-3xl') as HTMLElement
    const scheduledCard = screen.getByText('Scheduled notice').closest('div.rounded-3xl') as HTMLElement
    const publishedCard = screen.getByText('Published notice').closest('div.rounded-3xl') as HTMLElement

    // The category badge is surfaced on every card (scoped: the filter
    // options repeat the same words).
    expect(within(draftCard).getByText('general')).toBeInTheDocument()
    expect(within(scheduledCard).getByText('academic')).toBeInTheDocument()
    expect(within(publishedCard).getByText('event')).toBeInTheDocument()

    // Drafts offer publish + schedule; published rows offer archive instead.
    expect(within(draftCard).getByRole('button', { name: /Publish/ })).toBeInTheDocument()
    expect(within(draftCard).getByRole('button', { name: /Schedule/ })).toBeInTheDocument()

    expect(within(publishedCard).getByRole('button', { name: /Archive/ })).toBeInTheDocument()
    expect(within(publishedCard).queryByRole('button', { name: /Publish/ })).not.toBeInTheDocument()

    expect(within(scheduledCard).getByRole('button', { name: /Unschedule/ })).toBeInTheDocument()
  })

  it('sends the chosen category when creating an announcement', async () => {
    const fetchMock = stubApi()
    const user = userEvent.setup()
    render(<AdminAnnouncementsPage />)

    await user.click(await screen.findByRole('button', { name: /Create Announcement/ }))

    await user.type(screen.getByPlaceholderText(/Mid-Year Faculty/), 'Enrollment week')
    await user.type(screen.getByPlaceholderText(/Announcement body text/), 'Enrollment opens Monday.')
    await user.selectOptions(screen.getByLabelText('Category'), 'academic')

    await user.click(screen.getByRole('button', { name: 'Create Draft' }))

    await waitFor(() => {
      expect(callsTo(fetchMock, 'POST', '/admin/announcements').length).toBeGreaterThan(0)
    })

    const call = callsTo(fetchMock, 'POST', '/admin/announcements')[0]
    const body = JSON.parse((call[1] as RequestInit).body as string)
    expect(body.category).toBe('academic')
    expect(body.title).toBe('Enrollment week')
  })

  it('filters the list by category', async () => {
    const fetchMock = stubApi()
    const user = userEvent.setup()
    render(<AdminAnnouncementsPage />)

    await screen.findByText('Draft notice')

    await user.selectOptions(screen.getByRole('combobox'), 'policy')

    await waitFor(() => {
      const listCalls = fetchMock.mock.calls.filter(
        ([input, init]) =>
          String(input).includes('/admin/announcements') &&
          ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET' &&
          String(input).includes('category=policy'),
      )
      expect(listCalls.length).toBeGreaterThan(0)
    })
  })

  it('schedules a draft with a future publication time', async () => {
    vi.spyOn(window, 'prompt').mockReturnValue('2099-06-01T09:00')
    const fetchMock = stubApi()
    const user = userEvent.setup()
    render(<AdminAnnouncementsPage />)

    const draftCard = (await screen.findByText('Draft notice')).closest('div.rounded-3xl') as HTMLElement
    await user.click(within(draftCard).getByRole('button', { name: /Schedule/ }))

    await waitFor(() => {
      expect(callsTo(fetchMock, 'POST', '/admin/announcements/1/schedule').length).toBe(1)
    })

    const call = callsTo(fetchMock, 'POST', '/admin/announcements/1/schedule')[0]
    const body = JSON.parse((call[1] as RequestInit).body as string)
    expect(typeof body.published_at).toBe('string')
    expect(new Date(body.published_at).getTime()).toBeGreaterThan(Date.now())
  })

  it('refuses to schedule without a valid future time and never calls the API', async () => {
    vi.spyOn(window, 'prompt').mockReturnValue('not-a-date')
    vi.spyOn(window, 'alert').mockImplementation(() => {})
    const fetchMock = stubApi()
    const user = userEvent.setup()
    render(<AdminAnnouncementsPage />)

    const draftCard = (await screen.findByText('Draft notice')).closest('div.rounded-3xl') as HTMLElement
    await user.click(within(draftCard).getByRole('button', { name: /Schedule/ }))

    expect(callsTo(fetchMock, 'POST', '/admin/announcements/1/schedule')).toHaveLength(0)
  })

  it('unschedules a scheduled announcement after confirmation', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const fetchMock = stubApi()
    const user = userEvent.setup()
    render(<AdminAnnouncementsPage />)

    const scheduledCard = (await screen.findByText('Scheduled notice')).closest('div.rounded-3xl') as HTMLElement
    await user.click(within(scheduledCard).getByRole('button', { name: /Unschedule/ }))

    await waitFor(() => {
      expect(callsTo(fetchMock, 'DELETE', '/admin/announcements/2/schedule').length).toBe(1)
    })
  })

  it('archives a published announcement after confirmation', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const fetchMock = stubApi()
    const user = userEvent.setup()
    render(<AdminAnnouncementsPage />)

    const publishedCard = (await screen.findByText('Published notice')).closest('div.rounded-3xl') as HTMLElement
    await user.click(within(publishedCard).getByRole('button', { name: /Archive/ }))

    await waitFor(() => {
      expect(callsTo(fetchMock, 'POST', '/admin/announcements/3/archive').length).toBe(1)
    })
  })
})
