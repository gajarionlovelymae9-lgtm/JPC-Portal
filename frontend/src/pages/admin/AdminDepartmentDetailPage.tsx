import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getAdminDepartment } from '../../services/adminPortalService'
import type { AdminDepartmentDetail } from '../../services/adminPortalService'
import { PATHS } from '../../routes/paths'

export default function AdminDepartmentDetailPage() {
  const { departmentId } = useParams()
  const navigate = useNavigate()
  const [dept, setDept] = useState<AdminDepartmentDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!departmentId) return
    setLoading(true)
    getAdminDepartment(departmentId)
      .then(res => setDept(res.data))
      .catch(() => setError('Failed to load department details.'))
      .finally(() => setLoading(false))
  }, [departmentId])

  if (loading) {
    return <div className="p-6 space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-2xl animate-pulse" />)}</div>
  }

  if (error || !dept) {
    return <div className="p-6 bg-red-50 text-red-700 rounded-xl border border-red-200">{error ?? 'Department not found.'}</div>
  }

  return (
    <div className="p-6 max-w-6xl mx-auto space-y-6">
      <button onClick={() => navigate(PATHS.adminDepartments)} className="text-sm font-semibold text-gray-500 hover:text-gray-900 flex items-center gap-1">
        ← Back to Departments
      </button>

      {/* Header */}
      <div className="bg-white border border-gray-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
        <div className="flex justify-between items-start">
          <div>
            <span className="text-xs bg-blue-100 text-blue-800 font-bold px-3 py-1 rounded-full uppercase">{dept.code}</span>
            <h1 className="text-2xl sm:text-3xl font-black text-gray-900 mt-2">{dept.name}</h1>
            <p className="text-sm text-gray-500 mt-0.5">Head: <span className="font-bold text-gray-800">{dept.head.name}</span> ({dept.head.position})</p>
          </div>
          <span className="text-3xl font-black text-blue-600">{dept.metrics.completion_rate}%</span>
        </div>
      </div>

      {/* Metrics */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-bold uppercase block">Faculty Members</span>
          <span className="text-2xl font-black text-gray-900 block mt-1">{dept.metrics.total_employees}</span>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-bold uppercase block">Deliverables</span>
          <span className="text-2xl font-black text-blue-600 block mt-1">{dept.metrics.completed_tasks}/{dept.metrics.total_tasks}</span>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-bold uppercase block">Submitted Reports</span>
          <span className="text-2xl font-black text-emerald-600 block mt-1">{dept.metrics.reports_count}</span>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-bold uppercase block">Active Accounts</span>
          <span className="text-2xl font-black text-purple-600 block mt-1">{dept.metrics.active_employees}</span>
        </div>
      </div>

      {/* Faculty Table */}
      <div className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
        <h3 className="font-extrabold text-gray-900 text-lg">Assigned Faculty Members</h3>
        <div className="divide-y divide-gray-100">
          {dept.employees.map(e => (
            <div key={e.id} className="py-3.5 flex justify-between items-center">
              <div>
                <h4 className="font-bold text-sm text-gray-900">{e.name}</h4>
                <p className="text-xs text-gray-400 font-mono">{e.employee_number} · {e.position}</p>
              </div>
              <span className="text-xs px-2.5 py-0.5 rounded-full font-bold bg-emerald-100 text-emerald-800 uppercase">
                {e.status}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}