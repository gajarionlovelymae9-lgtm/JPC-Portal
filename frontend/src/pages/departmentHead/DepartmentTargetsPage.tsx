import { useEffect, useState } from 'react'
import { getDepartmentTargets } from '../../services/departmentHeadService'
import type { DepartmentTarget } from '../../services/departmentHeadService'

export default function DepartmentTargetsPage() {
  const [targets, setTargets] = useState<DepartmentTarget[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    getDepartmentTargets()
      .then(res => setTargets(res.data))
      .catch(() => setError('Failed to load department targets.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Department Targets & Planning</h1>
        <p className="text-sm text-gray-500 mt-0.5">Annual and quarterly department milestones linked to actual task and report metrics</p>
      </div>

      {error && <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200">{error}</div>}

      {loading ? (
        <div className="space-y-4">
          {[...Array(3)].map((_, i) => (
            <div key={i} className="h-28 bg-gray-100 rounded-2xl animate-pulse" />
          ))}
        </div>
      ) : targets.length === 0 ? (
        <div className="bg-white border border-gray-200 rounded-2xl p-12 text-center text-gray-500">
          <span className="text-4xl block mb-2">🎯</span>
          <p className="font-semibold">No targets configured for this academic term.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4">
          {targets.map(t => (
            <div key={t.id} className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
              <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="font-bold text-gray-900 text-lg">{t.name}</h3>
                    <span className={`text-xs px-2.5 py-0.5 rounded-full font-bold ${t.is_at_risk ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'}`}>
                      {t.status}
                    </span>
                  </div>
                  <p className="text-xs text-gray-400 mt-0.5">Academic Year: {t.academic_year} · Target Deadline: {t.deadline}</p>
                </div>
                <div className="text-right">
                  <span className="text-2xl font-extrabold text-blue-600">{t.progress_percent}%</span>
                </div>
              </div>

              {t.description && (
                <p className="text-sm text-gray-600 leading-relaxed">{t.description}</p>
              )}

              <div>
                <div className="flex justify-between text-xs text-gray-500 mb-1.5 font-medium">
                  <span>Completed: <b>{t.completed}</b> / {t.target} deliverables</span>
                  <span>Remaining: <b>{t.remaining}</b></span>
                </div>
                <div className="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                  <div
                    className={`h-full rounded-full transition-all duration-500 ${t.is_at_risk ? 'bg-gradient-to-r from-orange-500 to-red-500' : 'bg-gradient-to-r from-blue-600 to-indigo-600'}`}
                    style={{ width: `${Math.min(100, t.progress_percent)}%` }}
                  />
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}