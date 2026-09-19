import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { getUnreadCount, getNotifications, markAsRead, markAllAsRead, type NotificationItem } from '../services/notificationCenterService'

const TYPE_ICONS: Record<string, string> = {
  task_assigned: '📋', task_updated: '✏️', task_deadline: '⏰', task_due_today: '📅',
  task_overdue: '🔴', task_completed: '✅', report_submitted: '📄', report_approved: '✅',
  revision_required: '🔄', report_rejected: '❌', ocr_completed: '📸', ocr_failed: '⚠️',
  ai_validation_completed: '🤖', ai_processing_failed: '🤖', target_at_risk: '🎯',
  achievement_earned: '🏆', badge_earned: '🏅', escalation_created: '🚨',
  announcement_published: '📢', system: '⚙️',
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  return `${days}d ago`
}

export function NotificationBell() {
  const [count, setCount] = useState(0)
  const [open, setOpen] = useState(false)
  const [items, setItems] = useState<NotificationItem[]>([])
  const [loading, setLoading] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const navigate = useNavigate()

  const fetchCount = async () => {
    try {
      const res = await getUnreadCount()
      const dataAny = res as unknown
      const countVal = (dataAny as { count?: number })?.count ?? (dataAny as { data?: { count?: number } })?.data?.count ?? 0
      setCount(countVal)
    } catch { /* silent */ }
  }

  useEffect(() => {
    fetchCount()
    const timer = setInterval(fetchCount, 30000)
    return () => clearInterval(timer)
  }, [])

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const toggleDropdown = async () => {
    if (!open) {
      setLoading(true)
      try {
        const res = await getNotifications({ per_page: 5 })
        const dataAny = res as unknown
        const list = Array.isArray(dataAny)
          ? dataAny
          : (dataAny as { data?: NotificationItem[] })?.data ?? []
        setItems(list as NotificationItem[])
      } catch { setItems([]) }
      setLoading(false)
    }
    setOpen(!open)
  }

  const handleClick = async (item: NotificationItem) => {
    if (!item.read_at) {
      try { await markAsRead(item.id); fetchCount() } catch { /* silent */ }
    }
    setOpen(false)
    const data = item.data as Record<string, unknown> | null
    if (data?.action === 'view_task' && data.record_id) navigate(`/employee/tasks/${data.record_id}`)
    else if (data?.action === 'view_report' && data.record_id) navigate(`/employee/reports/${data.record_id}`)
    else navigate('/notifications')
  }

  const handleMarkAll = async () => {
    try { await markAllAsRead(); setCount(0); setItems(items.map(i => ({ ...i, read_at: new Date().toISOString() }))) } catch { /* silent */ }
  }

  return (
    <div ref={ref} className="relative">
      <button onClick={toggleDropdown} className="relative p-2 text-gray-600 hover:text-gray-900 transition-colors" aria-label="Notifications">
        <span className="text-xl">🔔</span>
        {count > 0 && (
          <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full shadow-sm">
            {count > 99 ? '99+' : count}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full mt-2 w-96 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 overflow-hidden">
          <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50/50">
            <h3 className="font-bold text-sm text-gray-900">Notifications</h3>
            {count > 0 && (
              <button onClick={handleMarkAll} className="text-xs text-blue-600 hover:text-blue-800 font-semibold">Mark all read</button>
            )}
          </div>

          <div className="max-h-80 overflow-y-auto">
            {loading ? (
              <div className="p-6 text-center text-gray-400 text-sm">Loading...</div>
            ) : items.length === 0 ? (
              <div className="p-6 text-center">
                <p className="text-2xl mb-1">🎉</p>
                <p className="text-sm text-gray-500">You're all caught up!</p>
              </div>
            ) : (
              items.map((item) => (
                <button key={item.id} onClick={() => handleClick(item)}
                  className={`w-full text-left px-4 py-3 border-b border-gray-50 hover:bg-blue-50/50 transition-colors flex gap-3 items-start ${!item.read_at ? 'bg-blue-50/30' : ''}`}>
                  <span className="text-lg mt-0.5">{TYPE_ICONS[item.type] ?? '📌'}</span>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      {!item.read_at && <span className="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0" />}
                      <p className="text-sm font-semibold text-gray-900 truncate">{item.title}</p>
                    </div>
                    <p className="text-xs text-gray-500 truncate">{item.message}</p>
                    <p className="text-[10px] text-gray-400 mt-0.5">{timeAgo(item.created_at)}</p>
                  </div>
                  {(item.priority === 'high' || item.priority === 'critical') && (
                    <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 ${item.priority === 'critical' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}>
                      {item.priority}
                    </span>
                  )}
                </button>
              ))
            )}
          </div>

          <button onClick={() => { navigate('/notifications'); setOpen(false) }}
            className="w-full py-2.5 text-sm font-semibold text-blue-600 hover:bg-blue-50 border-t border-gray-100 transition-colors">
            View All Notifications
          </button>
        </div>
      )}
    </div>
  )
}

export default NotificationBell