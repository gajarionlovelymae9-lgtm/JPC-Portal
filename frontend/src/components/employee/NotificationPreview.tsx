import { Link } from 'react-router-dom'
import type { EmployeeNotification } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'
import { EmptyState, Panel, PanelHeader, formatDate } from './primitives'

/** Compact notification preview. The header badge count stays authoritative. */
export default function NotificationPreview({
  notifications,
  unreadCount,
}: {
  notifications: EmployeeNotification[]
  unreadCount: number
}) {
  return (
    <Panel>
      <PanelHeader
        title="Notifications"
        action={
          <Link
            to={PATHS.employeeNotifications}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            View All →
          </Link>
        }
      />

      {notifications.length === 0 ? (
        <EmptyState message="No notifications yet." icon="🔔" />
      ) : (
        <>
          {unreadCount > notifications.length ? (
            <p className="px-5 pt-3 text-xs text-gray-500">
              {unreadCount} unread in total.
            </p>
          ) : null}
          <ul className="divide-y divide-gray-100">
            {notifications.map(notification => (
              <li key={notification.id} className="px-5 py-3 flex items-start gap-3">
                <span
                  className={`w-2 h-2 rounded-full shrink-0 mt-1.5 ${
                    notification.is_read ? 'bg-gray-200' : 'bg-blue-600'
                  }`}
                  aria-hidden="true"
                />
                <div className="min-w-0">
                  <p
                    className={`text-sm truncate ${
                      notification.is_read ? 'text-gray-600' : 'font-semibold text-gray-900'
                    }`}
                  >
                    {notification.title}
                  </p>
                  {notification.message ? (
                    <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{notification.message}</p>
                  ) : null}
                  {notification.created_at ? (
                    <p className="text-xs text-gray-400 mt-1">
                      {formatDate(notification.created_at)}
                    </p>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        </>
      )}
    </Panel>
  )
}
