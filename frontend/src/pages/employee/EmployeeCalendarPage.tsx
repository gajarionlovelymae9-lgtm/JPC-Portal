import { useEffect, useState } from 'react'
import { getMyCalendar } from '../../services/employeePortalService'
import type { EmployeeCalendarEvent } from '../../services/employeePortalService'

const TYPE_COLORS: Record<string, string> = {
  task: 'bg-blue-100 text-blue-700',
  report: 'bg-purple-100 text-purple-700',
  deadline: 'bg-red-100 text-red-700',
  review: 'bg-yellow-100 text-yellow-700',
}
const TYPE_ICONS: Record<string, string> = { task: '📋', report: '📄', deadline: '⏰', review: '🔍' }

export default function EmployeeCalendarPage() {
  const [events, setEvents] = useState<EmployeeCalendarEvent[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [filter, setFilter] = useState('all')
  const now = new Date()
  const [month, setMonth] = useState(now.getMonth() + 1)
  const [year, setYear] = useState(now.getFullYear())

  useEffect(() => {
    setLoading(true)
    getMyCalendar({ month, year })
      .then(r => setEvents(Array.isArray(r.data) ? r.data : []))
      .catch(() => setError('Unable to load calendar.'))
      .finally(() => setLoading(false))
  }, [month, year])

  const filtered = filter === 'all' ? events : events.filter(e => e.type === filter)
  const monthName = new Date(year, month - 1).toLocaleString('default', { month: 'long' })

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">My Calendar</h1>
        <div className="flex items-center gap-2">
          <button onClick={() => { if (month === 1) { setMonth(12); setYear(y => y - 1) } else setMonth(m => m - 1) }} className="px-3 py-1 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">◀</button>
          <span className="text-sm font-semibold text-gray-700 min-w-[120px] text-center">{monthName} {year}</span>
          <button onClick={() => { if (month === 12) { setMonth(1); setYear(y => y + 1) } else setMonth(m => m + 1) }} className="px-3 py-1 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">▶</button>
        </div>
      </div>
      <div className="flex gap-2 flex-wrap">
        {['all','task','report','deadline','review'].map(f => (
          <button key={f} onClick={() => setFilter(f)} className={`px-3 py-1 rounded-full text-sm font-medium border transition-colors ${filter === f ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'}`}>
            {TYPE_ICONS[f] ?? ''} {f.charAt(0).toUpperCase() + f.slice(1)}
          </button>
        ))}
      </div>
      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      {loading ? (
        <div className="space-y-3">{[...Array(5)].map((_,i) => <div key={i} className="h-14 bg-gray-100 rounded-lg animate-pulse"/>)}</div>
      ) : filtered.length === 0 ? (
        <div className="text-center py-16 text-gray-500"><div className="text-4xl mb-3">📅</div><p>No events for this period.</p></div>
      ) : (
        <div className="space-y-3">
          {filtered.sort((a,b) => new Date(a.date).getTime() - new Date(b.date).getTime()).map(ev => (
            <div key={`${ev.type}-${ev.id}`} className="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-4">
              <div className="text-2xl">{TYPE_ICONS[ev.type] ?? '📌'}</div>
              <div className="flex-1">
                <p className="font-medium text-gray-900">{ev.title}</p>
                <p className="text-sm text-gray-500">{new Date(ev.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
              </div>
              <div className="flex items-center gap-2">
                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${TYPE_COLORS[ev.type] ?? 'bg-gray-100 text-gray-600'}`}>{ev.type}</span>
                <span className="text-xs text-gray-500">{ev.status}</span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
