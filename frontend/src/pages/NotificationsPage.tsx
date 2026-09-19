import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useNotifications } from '../hooks/useNotifications'
import { formatDateTime, titleCase } from '../utils/task'
import type { AppNotification, NotificationCategory } from '../types/notification'

type ReadFilter = 'all' | 'unread' | 'read'

const CATEGORY_LABELS: Record<string, string> = {
  deadline: 'Deadlines',
  review: 'Reviews',
  task: 'Tasks',
  system: 'System',
}

export default function NotificationsPage() {
  const navigate = useNavigate()
  const [filter, setFilter] = useState<ReadFilter>('all')
  const [category, setCategory] = useState<string>('all')
  const [page, setPage] = useState(1)

  const params = useMemo(
    () => ({
      filter: filter === 'all' ? undefined : filter,
      category: category === 'all' ? undefined : category,
      page,
      per_page: 10,
    }),
    [filter, category, page],
  )

  const { notifications, meta, loading, error, unreadCount, refresh, markRead, markAllRead, remove } =
    useNotifications(params)

  const totalPages = meta?.last_page ?? 1

  const filterTabs: Array<{ key: ReadFilter; label: string }> = [
    { key: 'all', label: 'All' },
    { key: 'unread', label: 'Unread' },
    { key: 'read', label: 'Read' },
  ]

  const categoryChips: Array<{ key: string; label: string }> = [
    { key: 'all', label: 'All types' },
    ...(['deadline', 'review', 'task', 'system'] as NotificationCategory[]).map((key) => ({
      key,
      label: CATEGORY_LABELS[key] ?? titleCase(key),
    })),
  ]

  function selectFilter(next: ReadFilter) {
    setFilter(next)
    setPage(1)
  }

  function selectCategory(next: string) {
    setCategory(next)
    setPage(1)
  }

  async function handleMarkAllRead() {
    try {
      await markAllRead()
    } catch {
      // The inline refresh below reconciles the server truth.
      refresh()
    }
  }

  async function handleRemove(notification: AppNotification) {
    try {
      await remove(notification.id)
    } catch {
      refresh()
    }
  }

  async function handleOpen(notification: AppNotification) {
    if (!notification.read_at) {
      try {
        await markRead(notification.id)
      } catch {
        // Navigation still works even if marking read failed.
      }
    }
    if (notification.action_url) {
      navigate(notification.action_url)
    }
  }

  return (
    <div className="page">
      <div className="page__intro">
        <h2>Notifications</h2>
        <p>
          Task assignments, review decisions and deadline alerts.{' '}
          {unreadCount > 0
            ? `You have ${unreadCount} unread notification${unreadCount === 1 ? '' : 's'}.`
            : 'You are all caught up.'}
        </p>
      </div>

      <div className="notification-toolbar">
        <div className="notification-toolbar__tabs" role="tablist" aria-label="Filter by status">
          {filterTabs.map(({ key, label }) => (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={filter === key}
              className={`notification-toolbar__tab${
                filter === key ? ' notification-toolbar__tab--active' : ''
              }`}
              onClick={() => selectFilter(key)}
            >
              {label}
            </button>
          ))}
        </div>

        <button
          type="button"
          className="button button--secondary button--small"
          disabled={loading || unreadCount === 0}
          onClick={() => void handleMarkAllRead()}
        >
          Mark all read
        </button>
      </div>

      <div className="notification-categories" role="group" aria-label="Filter by category">
        {categoryChips.map(({ key, label }) => (
          <button
            key={key}
            type="button"
            className={`notification-chip${
              category === key ? ' notification-chip--active' : ''
            }`}
            onClick={() => selectCategory(key)}
          >
            {label}
          </button>
        ))}
      </div>

      {error ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{error}</p>
          <button type="button" className="button button--secondary" onClick={refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {loading ? <p className="page-state">Loading notifications…</p> : null}

      {!loading && !error && notifications.length === 0 ? (
        <p className="page-state">No notifications match these filters.</p>
      ) : null}

      {!loading && !error && notifications.length > 0 ? (
        <>
          <ul className="notification-list">
            {notifications.map((notification) => (
              <li
                key={notification.id}
                className={`notification-item${
                  notification.read_at ? '' : ' notification-item--unread'
                }`}
              >
                <span
                  className={`notification-item__priority notification-item__priority--${
                    notification.priority ?? 'normal'
                  }`}
                  aria-hidden="true"
                />
                <button
                  type="button"
                  className="notification-item__main"
                  onClick={() => void handleOpen(notification)}
                >
                  <span className="notification-item__head">
                    <span className="notification-item__title">{notification.title}</span>
                    {!notification.read_at ? (
                      <span className="notification-item__unread-dot" aria-label="Unread" />
                    ) : null}
                  </span>
                  {notification.message ? (
                    <span className="notification-item__message">{notification.message}</span>
                  ) : null}
                  <span className="notification-item__meta">
                    <span className="notification-item__category">
                      {CATEGORY_LABELS[notification.category] ?? titleCase(notification.category)}
                    </span>
                    <time dateTime={notification.created_at ?? undefined}>
                      {formatDateTime(notification.created_at)}
                    </time>
                  </span>
                </button>
                <div className="notification-item__actions">
                  {!notification.read_at ? (
                    <button
                      type="button"
                      className="button button--small button--secondary"
                      onClick={() => void markRead(notification.id)}
                    >
                      Mark as read
                    </button>
                  ) : null}
                  <button
                    type="button"
                    className="button button--small button--danger-ghost"
                    onClick={() => void handleRemove(notification)}
                  >
                    Delete
                  </button>
                </div>
              </li>
            ))}
          </ul>

          {totalPages > 1 ? (
            <div className="pagination">
              <button
                type="button"
                className="button button--small button--secondary"
                disabled={page <= 1}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                Previous
              </button>
              <span className="pagination__info">
                Page {page} of {totalPages}
              </span>
              <button
                type="button"
                className="button button--small button--secondary"
                disabled={page >= totalPages}
                onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
              >
                Next
              </button>
            </div>
          ) : null}
        </>
      ) : null}
    </div>
  )
}