import { useEffect, useState } from 'react'
import { getMyNotifications } from '../../services/employeePortalService'
import type { EmployeeNotification } from '../../services/employeePortalService'

const TYPE_ICONS: Record<string, string> = { task: '📋', report: '📄', approval: '✅', revision: '🔄', achievement: '🏆', deadline: '⏰' }

export default function EmployeeNotificationsPage() {
  const [notifications, setNotifications] = useState<EmployeeNotification[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getMyNotifications()
      .then(r => setNotifications(Array.isArray(r.data?.notifications) ? r.data.notifications : []))
      .catch(() => setError('Unable to load notifications. Please try again.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Notifications</h1>
      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      {loading ? (
        <div className="space-y-3">{[...Array(5)].map((_,i) => <div key={i} className="h-16 bg-gray-100 rounded-lg animate-pulse"/>)}</div>
      ) : notifications.length === 0 ? (
        <div className="text-center py-16 text-gray-500"><div className="text-4xl mb-3">🔔</div><p>No notifications yet.</p></div>
      ) : (
        <div className="space-y-3">
          {notifications.map(n => (
            <div key={n.id} className={`bg-white border rounded-xl p-4 flex items-start gap-4 transition-colors ${!n.is_read ? 'border-blue-200 bg-blue-50/30' : 'border-gray-200'}`}>
<div className="text-2xl">{TYPE_ICONS[n.type ?? ''] ?? '🔔'}</div>
              <div className="flex-1">
                <div className="flex items-center justify-between">
                  <p className={`font-medium ${!n.is_read ? 'text-gray-900' : 'text-gray-700'}`}>{n.title}</p>
                  {!n.is_read && <span className="w-2 h-2 bg-blue-600 rounded-full"/>}
                </div>
                <p className="text-sm text-gray-600 mt-0.5">{n.message}</p>
                <p className="text-xs text-gray-400 mt-1">
                  {n.created_at ? new Date(n.created_at).toLocaleString() : ''}
                </p>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
