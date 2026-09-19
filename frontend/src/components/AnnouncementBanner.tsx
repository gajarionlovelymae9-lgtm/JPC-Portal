import { useState, useEffect } from 'react'
import { getActiveAnnouncements, type AnnouncementItem } from '../services/notificationCenterService'

export function AnnouncementBanner() {
  const [announcements, setAnnouncements] = useState<AnnouncementItem[]>([])
  const [dismissed, setDismissed] = useState<number[]>([])

  useEffect(() => {
    try {
      const stored = localStorage.getItem('jpc_dismissed_announcements')
      if (stored) {
        setDismissed(JSON.parse(stored))
      }
    } catch {
      // ignore
    }

    getActiveAnnouncements()
      .then((res: { data?: AnnouncementItem[] } | AnnouncementItem[]) => {
        const list = Array.isArray(res) ? res : res.data ?? []
        setAnnouncements(list)
      })
      .catch(() => {
        setAnnouncements([])
      })
  }, [])

  const handleDismiss = (id: number) => {
    const next = [...dismissed, id]
    setDismissed(next)
    try {
      localStorage.setItem('jpc_dismissed_announcements', JSON.stringify(next))
    } catch {
      // ignore
    }
  }

  const visible = announcements.filter((a) => !dismissed.includes(a.id))

  if (visible.length === 0) return null

  return (
    <div className="space-y-2 mb-6">
      {visible.map((item) => {
        const isCritical = item.priority === 'critical'
        const isHigh = item.priority === 'high'

        const bgClass = isCritical
          ? 'bg-red-50 border-red-200 text-red-900'
          : isHigh
          ? 'bg-amber-50 border-amber-200 text-amber-900'
          : 'bg-blue-50 border-blue-200 text-blue-900'

        const badgeClass = isCritical
          ? 'bg-red-200 text-red-800'
          : isHigh
          ? 'bg-amber-200 text-amber-800'
          : 'bg-blue-200 text-blue-800'

        return (
          <div
            key={item.id}
            className={`p-4 rounded-2xl border flex items-start justify-between gap-4 shadow-sm transition-all ${bgClass}`}
            role="alert"
          >
            <div className="flex items-start gap-3 min-w-0 flex-1">
              <span className="text-xl flex-shrink-0 mt-0.5">📢</span>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2 mb-1 flex-wrap">
                  <h4 className="font-bold text-sm">{item.title}</h4>
                  <span className={`text-[10px] uppercase tracking-wider font-extrabold px-2 py-0.5 rounded-full ${badgeClass}`}>
                    {item.priority}
                  </span>
                </div>
                <p className="text-xs whitespace-pre-line opacity-90">{item.message}</p>
              </div>
            </div>

            <button
              onClick={() => handleDismiss(item.id)}
              className="text-gray-400 hover:text-gray-700 font-bold p-1 text-sm rounded-lg transition-colors flex-shrink-0"
              title="Dismiss announcement"
            >
              ✕
            </button>
          </div>
        )
      })}
    </div>
  )
}

export default AnnouncementBanner
