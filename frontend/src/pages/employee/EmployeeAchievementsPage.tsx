import { useEffect, useState } from 'react'
import { getMyAchievements } from '../../services/employeePortalService'
import type { EmployeeAchievement } from '../../services/employeePortalService'

export default function EmployeeAchievementsPage() {
  const [achievements, setAchievements] = useState<EmployeeAchievement[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getMyAchievements().then(r => setAchievements(Array.isArray(r.data) ? r.data : [])).catch(() => setError('Unable to load achievements.')).finally(() => setLoading(false))
  }, [])

  if (loading) return <div className="p-6 grid grid-cols-2 md:grid-cols-3 gap-4">{[...Array(6)].map((_,i) => <div key={i} className="h-32 bg-gray-100 rounded-xl animate-pulse"/>)}</div>

  return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">My Achievements</h1>
      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      {achievements.length === 0 ? (
        <div className="text-center py-16 text-gray-500"><div className="text-4xl mb-3">🏅</div><p>No achievements yet. Keep up the great work!</p></div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {achievements.map(a => (
            <div key={a.id} className="bg-white border border-gray-200 rounded-xl p-5 text-center hover:shadow-md transition-shadow">
              <div className="text-4xl mb-3">{a.badge_icon ?? '🏆'}</div>
              <h3 className="font-bold text-gray-900 mb-1">{a.badge_name}</h3>
              <p className="text-sm text-gray-600 mb-2">{a.badge_description}</p>
              {a.criteria && <p className="text-xs text-gray-400 mb-3">{a.criteria}</p>}
              <p className="text-xs text-gray-500 mt-2">Earned: {new Date(a.earned_at).toLocaleDateString()}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
