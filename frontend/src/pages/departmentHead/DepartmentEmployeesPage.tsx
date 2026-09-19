import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getDepartmentEmployees } from '../../services/departmentHeadService'
import type { DepartmentEmployee } from '../../services/departmentHeadService'

export default function DepartmentEmployeesPage() {
  const [employees, setEmployees] = useState<DepartmentEmployee[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    getDepartmentEmployees({ search, status: statusFilter })
      .then(res => setEmployees(res.data))
      .catch(() => setError('Failed to load department employees.'))
      .finally(() => setLoading(false))
  }, [search, statusFilter])

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Department Faculty & Staff</h1>
          <p className="text-sm text-gray-500 mt-0.5">Faculty members assigned to your department</p>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="flex flex-col sm:flex-row gap-3 bg-white p-4 rounded-2xl border border-gray-200 shadow-sm">
        <input
          type="text"
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Search by name or employee ID..."
          className="flex-1 border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <select
          value={statusFilter}
          onChange={e => setStatusFilter(e.target.value)}
          className="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
        >
          <option value="all">All Employment Statuses</option>
          <option value="active">Active</option>
          <option value="on_leave">On Leave</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      {error && <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200">{error}</div>}

      {loading ? (
        <div className="space-y-3">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-16 bg-gray-100 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : employees.length === 0 ? (
        <div className="bg-white border border-gray-200 rounded-2xl p-12 text-center text-gray-500">
          <span className="text-4xl block mb-2">👥</span>
          <p className="font-semibold">No faculty members found.</p>
        </div>
      ) : (
        <div className="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
          <table className="w-full text-sm text-left">
            <thead className="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider border-b border-gray-200">
              <tr>
                <th className="py-3 px-4">Employee ID</th>
                <th className="py-3 px-4">Name</th>
                <th className="py-3 px-4">Position</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4">Tasks</th>
                <th className="py-3 px-4">Reports</th>
                <th className="py-3 px-4">Completion Rate</th>
                <th className="py-3 px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {employees.map(emp => (
                <tr key={emp.id} className="hover:bg-gray-50/80 transition-colors">
                  <td className="py-3.5 px-4 font-mono text-xs text-gray-500 font-semibold">{emp.employee_number}</td>
                  <td className="py-3.5 px-4 font-medium text-gray-900">{emp.full_name}</td>
                  <td className="py-3.5 px-4 text-gray-600">{emp.position}</td>
                  <td className="py-3.5 px-4">
                    <span className={`text-xs px-2.5 py-0.5 rounded-full font-semibold ${emp.employment_status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'}`}>
                      {emp.employment_status.replace(/_/g, ' ')}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-gray-700 font-medium">{emp.tasks_count}</td>
                  <td className="py-3.5 px-4 text-gray-700 font-medium">{emp.reports_count}</td>
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-2">
                      <div className="w-16 bg-gray-100 rounded-full h-2">
                        <div
                          className="bg-blue-600 h-2 rounded-full"
                          style={{ width: `${emp.completion_rate}%` }}
                        />
                      </div>
                      <span className="text-xs font-bold text-gray-700">{emp.completion_rate}%</span>
                    </div>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <button
                      onClick={() => navigate(`/department-head/employees/${emp.id}`)}
                      className="px-3 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold"
                    >
                      View Details
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}