import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getAdminDepartments } from '../../services/adminPortalService'
import type { AdminDepartmentItem } from '../../services/adminPortalService'
import { adminDepartmentDetailPath } from '../../routes/paths'

export default function AdminDepartmentsPage() {
  const [departments, setDepartments] = useState<AdminDepartmentItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    getAdminDepartments()
      .then(res => setDepartments(res.data))
      .catch(() => setError('Failed to load departments.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Academic & Administrative Departments</h1>
        <p className="text-sm text-gray-500 mt-0.5">Institution-wide department oversight and deliverable tracking</p>
      </div>

      {error && <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200">{error}</div>}

      {loading ? (
        <div className="space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-2xl animate-pulse" />)}</div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {departments.map(d => (
            <div key={d.id} className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4 hover:shadow-md transition-shadow">
              <div className="flex justify-between items-start">
                <div>
                  <span className="text-xs bg-blue-50 text-blue-700 font-bold px-2.5 py-0.5 rounded-lg uppercase">{d.code}</span>
                  <h3 className="font-bold text-gray-900 text-lg mt-1">{d.name}</h3>
                  <p className="text-xs text-gray-500">Head: <span className="font-semibold text-gray-700">{d.head_name}</span></p>
                </div>
                <span className="text-2xl font-black text-blue-600">{d.completion_rate}%</span>
              </div>

              <div className="w-full bg-gray-100 rounded-full h-2.5">
                <div className="bg-blue-600 h-2.5 rounded-full" style={{ width: `${d.completion_rate}%` }} />
              </div>

              <div className="grid grid-cols-3 gap-2 text-center text-xs pt-2 border-t border-gray-100">
                <div><span className="text-gray-400 block">Faculty</span><b className="text-gray-800 font-bold">{d.employees_count}</b></div>
                <div><span className="text-gray-400 block">Tasks</span><b className="text-gray-800 font-bold">{d.completed_tasks}/{d.tasks_count}</b></div>
                <div><span className="text-gray-400 block">Reports</span><b className="text-gray-800 font-bold">{d.reports_count}</b></div>
              </div>

              <button
                onClick={() => navigate(adminDepartmentDetailPath(d.id))}
                className="w-full py-2 bg-gray-50 hover:bg-blue-50 text-blue-700 rounded-xl text-xs font-bold transition-colors"
              >
                View Department Drill-Down →
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}