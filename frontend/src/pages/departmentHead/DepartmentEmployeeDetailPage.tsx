import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getDepartmentEmployee } from '../../services/departmentHeadService'
import type { DepartmentEmployeeDetail } from '../../services/departmentHeadService'
import { PATHS } from '../../routes/paths'

export default function DepartmentEmployeeDetailPage() {
  const { employeeId } = useParams()
  const navigate = useNavigate()
  const [employee, setEmployee] = useState<DepartmentEmployeeDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [activeTab, setActiveTab] = useState<'tasks' | 'reports'>('tasks')

  useEffect(() => {
    if (!employeeId) return
    setLoading(true)
    getDepartmentEmployee(employeeId)
      .then(res => setEmployee(res.data))
      .catch(() => setError('Failed to load employee profile.'))
      .finally(() => setLoading(false))
  }, [employeeId])

  if (loading) {
    return <div className="p-6 space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />)}</div>
  }

  if (error || !employee) {
    return <div className="p-6 bg-red-50 text-red-700 rounded-xl border border-red-200">{error ?? 'Employee not found.'}</div>
  }

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      <button onClick={() => navigate(PATHS.departmentHeadEmployees)} className="text-sm font-semibold text-gray-500 hover:text-gray-900 flex items-center gap-1">
        ← Back to Faculty Directory
      </button>

      {/* Header Profile Card */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-2xl">
            {employee.first_name[0]}{employee.last_name[0]}
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">{employee.full_name}</h1>
            <p className="text-sm text-gray-500 font-mono">{employee.employee_number} · {employee.position_name}</p>
            <p className="text-xs text-gray-400 mt-0.5">{employee.department_name} · Hired: {employee.date_hired ?? '—'}</p>
          </div>
        </div>
        <div className="flex gap-2">
          <span className={`px-3 py-1 rounded-full text-xs font-bold ${employee.employment_status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'}`}>
            {employee.employment_status.toUpperCase()}
          </span>
        </div>
      </div>

      {/* Metrics Row */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        {[
          { label: 'Assigned Tasks', val: employee.metrics.total_tasks, color: 'text-blue-600' },
          { label: 'Completed', val: employee.metrics.completed_tasks, color: 'text-emerald-600' },
          { label: 'Pending', val: employee.metrics.pending_tasks, color: 'text-indigo-600' },
          { label: 'Overdue', val: employee.metrics.overdue_tasks, color: 'text-red-600' },
          { label: 'Completion Rate', val: `${employee.metrics.completion_rate}%`, color: 'text-purple-600' },
        ].map((m, i) => (
          <div key={i} className="bg-white p-4 rounded-xl border border-gray-100 shadow-sm text-center">
            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider block">{m.label}</span>
            <span className={`text-2xl font-extrabold ${m.color} mt-1 block`}>{m.val}</span>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-gray-200">
        <button
          onClick={() => setActiveTab('tasks')}
          className={`pb-3 px-4 font-semibold text-sm border-b-2 transition-colors ${activeTab === 'tasks' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
        >
          Assigned Tasks ({employee.assignments.length})
        </button>
        <button
          onClick={() => setActiveTab('reports')}
          className={`pb-3 px-4 font-semibold text-sm border-b-2 transition-colors ${activeTab === 'reports' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
        >
          Submitted Reports ({employee.reports.length})
        </button>
      </div>

      {/* Tab Content */}
      {activeTab === 'tasks' ? (
        <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
          {employee.assignments.length === 0 ? (
            <p className="p-8 text-center text-sm text-gray-500">No tasks currently assigned to this member.</p>
          ) : (
            <div className="divide-y divide-gray-100">
              {employee.assignments.map(a => (
                <div key={a.id} className="p-4 flex justify-between items-center hover:bg-gray-50/80 transition-colors">
                  <div>
                    <h4 className="font-semibold text-gray-900 text-sm">{a.task_title}</h4>
                    <p className="text-xs text-gray-500 mt-0.5">Due: {a.due_date ?? 'No deadline'} · Priority: {a.priority}</p>
                  </div>
                  <div className="flex items-center gap-4">
                    <div className="w-24 bg-gray-100 rounded-full h-2">
                      <div className="bg-blue-600 h-2 rounded-full" style={{ width: `${a.progress}%` }} />
                    </div>
                    <span className="text-xs font-bold text-gray-700 w-10 text-right">{a.progress}%</span>
                    <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${a.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'}`}>
                      {a.status}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ) : (
        <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
          {employee.reports.length === 0 ? (
            <p className="p-8 text-center text-sm text-gray-500">No reports submitted by this member.</p>
          ) : (
            <div className="divide-y divide-gray-100">
              {employee.reports.map(r => (
                <div key={r.id} className="p-4 flex justify-between items-center hover:bg-gray-50/80 transition-colors">
                  <div>
                    <h4 className="font-semibold text-gray-900 text-sm">{r.title}</h4>
                    <p className="text-xs text-gray-500 mt-0.5">Task: {r.task_title} · Submitted: {r.submitted_at ? new Date(r.submitted_at).toLocaleDateString() : '—'}</p>
                  </div>
                  <span className={`text-xs px-2.5 py-1 rounded-full font-semibold ${r.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : (r.status === 'revision_required' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800')}`}>
                    {r.status.replace(/_/g, ' ')}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )
}