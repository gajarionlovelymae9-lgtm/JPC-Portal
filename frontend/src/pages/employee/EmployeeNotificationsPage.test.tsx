import { render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import EmployeeNotificationsPage from './EmployeeNotificationsPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const notifications = [
  {
    id: 1,
    title: 'Report approved',
    message: 'Your Q3 report was approved.',
    type: 'approval',
    is_read: false,
    created_at: '2026-09-01T08:00:00.000000Z',
  },
  {
    id: 2,
    title: 'New task assigned',
    message: 'You have a new task: compile targets.',
    type: 'task',
    is_read: true,
    created_at: '2026-08-30T08:00:00.000000Z',
  },
]

function stubApi(status = 200) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/employee/notifications')) {
        return Promise.resolve(
          status === 200
            ? json({ success: true, message: 'ok', data: { notifications, total: notifications.length } })
            : json({ success: false, message: 'failed', errors: null }, status),
        )
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

describe('EmployeeNotificationsPage (GCS §38 notifications)', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the notification list with read/unread styling signals', async () => {
    stubApi()
    render(<EmployeeNotificationsPage />)

    expect(await screen.findByRole('heading', { name: 'Notifications' })).toBeInTheDocument()
    expect(screen.getByText('Report approved')).toBeInTheDocument()
    expect(screen.getByText('Your Q3 report was approved.')).toBeInTheDocument()
    expect(screen.getByText('New task assigned')).toBeInTheDocument()
    // unread badge dot (blue rounded-full span) is rendered for the unread notification
    await waitFor(() => {
      expect(
        document.querySelectorAll('span.w-2.h-2.rounded-full').length,
      ).toBeGreaterThan(0)
    })
  })

  it('renders an empty state when there are no notifications', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(json({ success: true, message: 'ok', data: { notifications: [], total: 0 } })),
    )
    render(<EmployeeNotificationsPage />)

    expect(await screen.findByText('No notifications yet.')).toBeInTheDocument()
  })

  it('surfaces a load error when the notifications request fails', async () => {
    stubApi(500)
    render(<EmployeeNotificationsPage />)

    expect(await screen.findByText('Unable to load notifications. Please try again.')).toBeInTheDocument()
  })
})