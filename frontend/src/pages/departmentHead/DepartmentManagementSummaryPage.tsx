import { useEffect, useState } from 'react'
import { getDepartmentDashboard } from '../../services/departmentHeadService'
import type { DepartmentDashboardData } from '../../services/departmentHeadService'

export default function DepartmentManagementSummaryPage() {
  const [data, setData] = useState<DepartmentDashboardData | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    getDepartmentDashboard()
      .then(res => setData(res.data))
      .finally(() => setLoading(false))
  }, [])

  if (loading || !data) {
    return <div className="p-6 space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-24 bg-gray-100 rounded-2xl animate-pulse" />)}</div>
  }

  const { department, kpis, target, ai_summary } = data

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      <div className="border-b border-gray-200 pb-4">
        <h1 className="text-2xl font-bold text-gray-900">Executive Management Summary</h1>
        <p className="text-sm text-gray-500 mt-0.5">{department.name} · Academic Year {department.academic_year}</p>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-semibold block uppercase">Completion</span>
          <span className="text-3xl font-extrabold text-blue-600 mt-1 block">{kpis.completion_rate}%</span>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-semibold block uppercase">On-Time</span>
          <span className="text-3xl font-extrabold text-emerald-600 mt-1 block">{kpis.on_time_rate}%</span>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-semibold block uppercase">Approval Rate</span>
          <span className="text-3xl font-extrabold text-purple-600 mt-1 block">{kpis.approval_rate}%</span>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-semibold block uppercase">Target Goal</span>
          <span className="text-3xl font-extrabold text-indigo-600 mt-1 block">{target?.progress_percent ?? 100}%</span>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-3">
        <h3 className="font-bold text-gray-900 text-base">Key Operational Status</h3>
        <p className="text-sm text-gray-700 leading-relaxed">{ai_summary.text}</p>
        <p className="text-xs text-gray-400 italic">Approved for Administration & Dean progress tracking.</p>
      </div>
    </div>
  )
}