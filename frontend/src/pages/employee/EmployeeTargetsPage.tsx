import { useEffect, useState } from 'react'
import { getMyTargets } from '../../services/employeePortalService'
import type { EmployeeTarget } from '../../services/employeePortalService'

export default function EmployeeTargetsPage() {
  const [targets, setTargets] = useState<EmployeeTarget[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getMyTargets().then(r => setTargets(Array.isArray(r.data) ? r.data : [])).catch(() => setError('Unable to load targets.')).finally(() => setLoading(false))
  }, [])

  if (loading) return <div className="p-6 space-y-3">{[...Array(3)].map((_,i) => <div key={i} className="h-24 bg-gray-100 rounded-xl animate-pulse"/>)}</div>

  return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">My Targets</h1>
      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      {targets.length === 0 ? (
        <div className="text-center py-16 text-gray-500"><div className="text-4xl mb-3">🎯</div><p>No targets assigned yet.</p></div>
      ) : (
        <div className="space-y-4">
          {targets.map(t => (
            <div key={t.id} className="bg-white border border-gray-200 rounded-xl p-5">
              <div className="flex items-center justify-between mb-2">
                <div><h3 className="font-semibold text-gray-900">{t.name}</h3><p className="text-sm text-gray-500">{t.academic_year}</p></div>
                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${t.status === 'completed' ? 'bg-green-100 text-green-700' : t.status === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}`}>{t.status.replace(/_/g,' ')}</span>
              </div>
              {t.description && <p className="text-sm text-gray-600 mb-3">{t.description}</p>}
              <div className="flex items-center justify-between text-sm text-gray-500 mb-2"><span>{t.completed_tasks} / {t.total_tasks} tasks</span><span className="font-semibold text-gray-800">{t.progress_percent}%</span></div>
              <div className="w-full bg-gray-100 rounded-full h-2.5"><div className="bg-blue-600 h-2.5 rounded-full transition-all" style={{ width: `${t.progress_percent}%` }}/></div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
