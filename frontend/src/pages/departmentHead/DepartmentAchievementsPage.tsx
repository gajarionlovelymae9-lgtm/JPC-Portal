import { useEffect, useState } from 'react'
import { getDepartmentAchievements } from '../../services/departmentHeadService'
import type { DepartmentAchievementsData } from '../../services/departmentHeadService'

export default function DepartmentAchievementsPage() {
  const [data, setData] = useState<DepartmentAchievementsData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    getDepartmentAchievements()
      .then(res => setData(res.data))
      .catch(() => setError('Failed to load department achievements.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return <div className="p-6 space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />)}</div>
  }

  if (error || !data) {
    return <div className="p-6 bg-red-50 text-red-700 rounded-xl border border-red-200">{error ?? 'No achievements data.'}</div>
  }

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Department Achievements & Badges</h1>
        <p className="text-sm text-gray-500 mt-0.5">Recognize faculty excellence based on objective milestone criteria</p>
      </div>

      {/* Top Performers */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 className="font-bold text-gray-900 text-lg flex items-center gap-2">
          <span>🏆</span> Monthly Top Faculty Performers
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {data.top_performers.slice(0, 3).map((tp, idx) => (
            <div key={tp.employee_id} className="p-5 rounded-2xl bg-gradient-to-br from-amber-50/60 to-yellow-50/40 border border-amber-200 flex items-center gap-4">
              <div className="text-3xl">{tp.icon}</div>
              <div>
                <span className="text-[11px] font-bold uppercase tracking-wider text-amber-800">Rank #{idx + 1}</span>
                <h4 className="font-bold text-gray-900 text-sm">{tp.name}</h4>
                <p className="text-xs text-gray-500">{tp.badge} · <b>{tp.completion_rate}% Completion</b></p>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Standard Department Badges */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 className="font-bold text-gray-900 text-lg">Department Badges & Standards</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {data.badges.map((b, i) => (
            <div key={i} className="p-4 rounded-xl border border-gray-100 bg-gray-50/50 space-y-2">
              <span className="text-2xl block">{b.icon}</span>
              <h4 className="font-bold text-gray-900 text-sm">{b.title}</h4>
              <p className="text-xs text-gray-600">{b.description}</p>
              <span className="text-[11px] text-gray-400 font-medium block pt-1 border-t border-gray-200">
                Criteria: {b.criteria}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}