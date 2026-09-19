import { useCallback, useEffect, useState } from 'react'
import {
  deleteNotification,
  getUnreadNotificationCount,
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '../services/notificationService'
import { getApiErrorMessage } from '../utils/apiError'
import type { PaginationMeta } from '../types/api'
import type { AppNotification, NotificationFilter } from '../types/notification'

/** How often the header bell re-checks the unread counter. */
const POLL_INTERVAL_MS = 60_000

function readCount(value: unknown): number {
  if (typeof value !== 'object' || value === null) {
    return 0
  }
  const count = (value as { count?: unknown }).count
  return typeof count === 'number' && Number.isFinite(count) ? count : 0
}

export interface NotificationsState {
  notifications: AppNotification[]
  meta: PaginationMeta | null
  loading: boolean
  error: string | null
  unreadCount: number
  refresh: () => void
  markRead: (id: number) => Promise<void>
  markAllRead: () => Promise<void>
  remove: (id: number) => Promise<void>
}

/**
 * The signed-in user's notification inbox plus the unread counter.
 *
 * The counter is polled every minute so the header badge catches new deadline
 * reminders without reloading the page; list actions update local state
 * immediately and then are reconciled on the next refresh.
 */
export function useNotifications(params: NotificationFilter = {}): NotificationsState {
  const queryKey = JSON.stringify(params)
  const [notifications, setNotifications] = useState<AppNotification[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [unreadCount, setUnreadCount] = useState(0)
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    const requestParams = JSON.parse(queryKey) as NotificationFilter
    let active = true

    void (async () => {
      await Promise.resolve()
      if (!active) {
        return
      }
      setLoading(true)
      setError(null)

      try {
        const [list, count] = await Promise.all([
          listNotifications(requestParams),
          getUnreadNotificationCount(),
        ])
        if (active) {
          setNotifications(list.data)
          setMeta(list.meta ?? null)
          setUnreadCount(readCount(count.data))
        }
      } catch (error_: unknown) {
        if (active) {
          setError(getApiErrorMessage(error_, 'Unable to load notifications.'))
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    })()

    return () => {
      active = false
    }
  }, [queryKey, reloadKey])

  useEffect(() => {
    let active = true

    const timer = window.setInterval(() => {
      void (async () => {
        if (!active) {
          return
        }
        try {
          const count = await getUnreadNotificationCount()
          if (active) {
            setUnreadCount(readCount(count.data))
          }
        } catch {
          // Keep the last known counter on transient failures.
        }
      })()
    }, POLL_INTERVAL_MS)

    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [])

  const refresh = useCallback(() => setReloadKey((key) => key + 1), [])

  async function markRead(id: number): Promise<void> {
    await markNotificationRead(id)
    setNotifications((items) =>
      items.map((notification) =>
        notification.id === id
          ? { ...notification, read_at: new Date().toISOString() }
          : notification,
      ),
    )
    setUnreadCount((count) => Math.max(0, count - 1))
  }

  async function markAllRead(): Promise<void> {
    await markAllNotificationsRead()
    setNotifications((items) =>
      items.map((notification) => ({
        ...notification,
        read_at: notification.read_at ?? new Date().toISOString(),
      })),
    )
    setUnreadCount(0)
  }

  async function remove(id: number): Promise<void> {
    await deleteNotification(id)
    setNotifications((items) => items.filter((notification) => notification.id !== id))
  }

  return { notifications, meta, loading, error, unreadCount, refresh, markRead, markAllRead, remove }
}