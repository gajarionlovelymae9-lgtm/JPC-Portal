import { useEffect, useState } from 'react'
import { getDepartmentPerformance } from '../../services/departmentHeadService'
import type { DepartmentPerformanceData } from '../../services/departmentHeadService'

export default function DepartmentPerformancePage() {
  const [data, setData] = useState<DepartmentPerformanceData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    getDepartmentPerformance()
      .then(res => setData(res.data))
      .catch(() => setError('Failed to load performance metrics.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return <div className="p-6 space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />)}</div>
  }

  if (error || !data) {
    return <div className="p-6 bg-red-50 text-red-700 rounded-xl border border-red-200">{error ?? 'No data.'}</div>
  }

  const { summary, employee_comparison, monthly_trends, ai_summary } = data

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Department Performance & Analytics</h1>
        <p className="text-sm text-gray-500 mt-0.5">Aggregate performance metrics and intra-department faculty comparisons</p>
      </div>

      {/* Aggregate Score Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Completion Rate', val: `${summary.completion_rate}%`, color: 'text-blue-600', sub: 'Assigned deliverable completion' },
          { label: 'On-Time Submission Rate', val: `${summary.on_time_rate}%`, color: 'text-emerald-600', sub: 'Submissions before deadline' },
          { label: 'Report Approval Rate', val: `${summary.approval_rate}%`, color: 'text-purple-600', sub: 'First-pass approval success' },
          { label: 'Target Achievement', val: `${summary.target_achievement}%`, color: 'text-indigo-600', sub: 'Annual goal progress' },
        ].map((s, idx) => (
          <div key={idx} className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider block">{s.label}</span>
            <span className={`text-3xl font-extrabold ${s.color} mt-1 block`}>{s.val}</span>
            <span className="text-xs text-gray-500 mt-1 block">{s.sub}</span>
          </div>
        ))}
      </div>

      {/* AI Summary Banner */}
      <div className="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-2xl p-5">
        <div className="flex items-center gap-2 mb-1.5">
          <span className="text-base">🤖</span>
          <h3 className="font-bold text-blue-950 text-sm">AI Performance Observation</h3>
        </div>
        <p className="text-sm text-blue-900 leading-relaxed">{ai_summary.text}</p>
        <p className="text-[11px] text-blue-600/80 italic mt-2">ℹ️ {ai_summary.disclaimer}</p>
      </div>

      {/* Intra-Department Employee Comparison */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <div>
          <h3 className="font-bold text-gray-900 text-lg">Intra-Department Faculty Comparison</h3>
          <p className="text-xs text-gray-500">Benchmark task completion and punctuality across department members</p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-gray-50 text-gray-500 text-xs font-semibold uppercase tracking-wider border-y border-gray-100">
              <tr>
                <th className="py-3 px-4">Faculty Member</th>
                <th className="py-3 px-4">Position</th>
                <th className="py-3 px-4">Total Tasks</th>
                <th className="py-3 px-4">Completed</th>
                <th className="py-3 px-4">Completion Rate</th>
                <th className="py-3 px-4">On-Time Rate</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {employee_comparison.map(emp => (
                <tr key={emp.id} className="hover:bg-gray-50/80">
                  <td className="py-3 px-4 font-semibold text-gray-900">
                    <div>{emp.name}</div>
                    <div className="text-xs text-gray-400 font-mono font-normal">{emp.employee_number}</div>
                  </td>
                  <td className="py-3 px-4 text-gray-600">{emp.position}</td>
                  <td className="py-3 px-4 font-semibold text-gray-800">{emp.tasks_count}</td>
                  <td className="py-3 px-4 text-emerald-600 font-medium">{emp.completed_count}</td>
                  <td className="py-3 px-4">
                    <div className="flex items-center gap-2">
                      <div className="w-24 bg-gray-100 rounded-full h-2">
                        <div className="bg-blue-600 h-2 rounded-full" style={{ width: `${emp.completion_rate}%` }} />
                      </div>
                      <span className="text-xs font-bold text-gray-700">{emp.completion_rate}%</span>
                    </div>
                  </td>
                  <td className="py-3 px-4">
                    <div className="flex items-center gap-2">
                      <div className="w-24 bg-gray-100 rounded-full h-2">
                        <div className="bg-emerald-600 h-2 rounded-full" style={{ width: `${emp.on_time_rate}%` }} />
                      </div>
                      <span className="text-xs font-bold text-gray-700">{emp.on_time_rate}%</span>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Monthly Trend Table */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 className="font-bold text-gray-900 text-lg">Monthly Performance Trends</h3>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {monthly_trends.map(t => (
            <div key={t.month} className="p-4 bg-gray-50 rounded-xl border border-gray-100 text-center space-y-1">
              <span className="font-bold text-sm text-gray-800 block">{t.month} 2026</span>
              <div className="text-xs text-gray-600">Completion: <b className="text-blue-600">{t.completion_rate}%</b></div>
              <div className="text-xs text-gray-600">On-Time: <b className="text-emerald-600">{t.on_time_rate}%</b></div>
              <div className="text-xs text-gray-600">Approval: <b className="text-purple-600">{t.approval_rate}%</b></div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}