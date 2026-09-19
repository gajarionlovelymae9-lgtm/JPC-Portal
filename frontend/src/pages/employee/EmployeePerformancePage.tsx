import { useEffect, useState } from 'react'
import { getMyPerformanceSummary } from '../../services/employeePortalService'
import type { EmployeePerformance } from '../../services/employeePortalService'

function StatCard({ label, value, color }: { label: string; value: string; color: string }) {
  return (
    <div className="bg-white border border-gray-200 rounded-xl p-5 text-center">
      <div className={`text-3xl font-bold ${color}`}>{value}</div>
      <div className="text-sm text-gray-500 mt-1">{label}</div>
    </div>
  )
}

export default function EmployeePerformancePage() {
  const [data, setData] = useState<EmployeePerformance | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getMyPerformanceSummary()
      .then(r => setData(r.data))
      .catch(() => setError('Unable to load performance data.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <div className="p-6 space-y-3">{[...Array(5)].map((_,i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse"/>)}</div>
  if (error || !data) return <div className="p-6 bg-red-50 text-red-700 rounded-lg">{error}</div>

  const s = data.summary
  return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">My Performance</h1>
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-blue-800 text-xs">ℹ {data.disclaimer}</div>
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        <StatCard label="Completion Rate" value={`${s.completion_rate}%`} color="text-blue-600"/>
        <StatCard label="On-Time Rate" value={`${s.on_time_rate}%`} color="text-green-600"/>
        <StatCard label="Approval Rate" value={`${s.approval_rate}%`} color="text-purple-600"/>
        <StatCard label="Target Achievement" value={`${s.target_achievement}%`} color="text-orange-600"/>
        <StatCard label="Performance Score" value={`${s.performance_score}`} color="text-indigo-600"/>
      </div>
      {data.monthly_trend.length > 0 && (
        <div className="bg-white border border-gray-200 rounded-xl p-5">
          <h2 className="font-semibold text-gray-800 mb-4">Monthly Trend</h2>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead><tr className="text-left text-gray-500 border-b">{['Month','Completion','On-Time','Target Achievement'].map(h=><th key={h} className="pb-2 pr-4">{h}</th>)}</tr></thead>
              <tbody className="divide-y divide-gray-50">
                {data.monthly_trend.map((m,i) => (
                  <tr key={i}>
                    <td className="py-2 pr-4 text-gray-700">{m.month} {m.year}</td>
                    <td className="py-2 pr-4"><span className="font-medium text-blue-600">{m.completion_rate}%</span></td>
                    <td className="py-2 pr-4"><span className="font-medium text-green-600">{m.on_time_rate}%</span></td>
                    <td className="py-2"><span className="font-medium text-orange-600">{m.target_achievement}%</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}
