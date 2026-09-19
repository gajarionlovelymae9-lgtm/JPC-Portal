import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getDepartmentDashboard } from '../../services/departmentHeadService'
import type { DepartmentDashboardData } from '../../services/departmentHeadService'
import { PATHS, departmentHeadReportDetailPath, departmentHeadTaskDetailPath } from '../../routes/paths'

export default function DepartmentDashboardPage() {
  const [data, setData] = useState<DepartmentDashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    getDepartmentDashboard()
      .then(res => setData(res.data))
      .catch(() => setError('Failed to load department dashboard data.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="p-6 space-y-6">
        <div className="h-10 bg-gray-200 rounded animate-pulse w-1/3" />
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[...Array(8)].map((_, i) => (
            <div key={i} className="h-24 bg-gray-100 rounded-xl animate-pulse" />
          ))}
        </div>
      </div>
    )
  }

  if (error || !data) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
          {error ?? 'Unable to display department dashboard.'}
        </div>
      </div>
    )
  }

  const { department, kpis, target, overdue_alerts, pending_review_alerts, employee_summary, ai_summary } = data

  return (
    <div className="p-6 space-y-8 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gradient-to-r from-blue-900 to-indigo-800 p-6 rounded-2xl text-white shadow-md">
        <div>
          <div className="flex items-center gap-3">
            <span className="bg-blue-600/60 text-xs px-2.5 py-1 rounded-full font-medium tracking-wide uppercase">
              {department.code}
            </span>
            <span className="text-blue-200 text-sm">Academic Year: {department.academic_year}</span>
          </div>
          <h1 className="text-3xl font-extrabold mt-1 tracking-tight">{department.name}</h1>
          <p className="text-blue-100 text-sm mt-1">Department Head: <span className="font-semibold">{department.department_head}</span></p>
        </div>
        <div className="flex gap-3 flex-wrap">
          <Link
            to={PATHS.departmentHeadTaskCreate}
            className="px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-xl text-sm transition-all shadow-sm flex items-center gap-1.5"
          >
            <span>+</span> Assign Task
          </Link>
          <Link
            to={PATHS.departmentHeadReports}
            className="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white font-medium rounded-xl text-sm backdrop-blur transition-all border border-white/20"
          >
            Review Reports ({kpis.pending_reviews})
          </Link>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Total Employees', value: kpis.total_employees, sub: `${kpis.active_employees} Active`, color: 'text-blue-600', bg: 'bg-blue-50/50' },
          { label: 'Assigned Tasks', value: kpis.total_tasks, sub: `${kpis.completed_tasks} Completed`, color: 'text-indigo-600', bg: 'bg-indigo-50/50' },
          { label: 'Completion Rate', value: `${kpis.completion_rate}%`, sub: 'Department Average', color: 'text-emerald-600', bg: 'bg-emerald-50/50' },
          { label: 'On-Time Rate', value: `${kpis.on_time_rate}%`, sub: 'Submission punctuality', color: 'text-teal-600', bg: 'bg-teal-50/50' },
          { label: 'Pending Reviews', value: kpis.pending_reviews, sub: `${kpis.submitted_reports} Total Submitted`, color: 'text-amber-600', bg: 'bg-amber-50/50' },
          { label: 'Approved Reports', value: kpis.approved_reports, sub: `${kpis.approval_rate}% Approval Rate`, color: 'text-green-600', bg: 'bg-green-50/50' },
          { label: 'Revision Required', value: kpis.revision_required, sub: 'Needs teacher edit', color: 'text-orange-600', bg: 'bg-orange-50/50' },
          { label: 'Overdue Tasks', value: kpis.overdue_tasks, sub: 'Action required', color: 'text-red-600', bg: 'bg-red-50/50' },
        ].map((kpi, idx) => (
          <div key={idx} className={`p-5 rounded-2xl border border-gray-100 bg-white shadow-sm flex flex-col justify-between`}>
            <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">{kpi.label}</span>
            <div className="my-2">
              <span className={`text-3xl font-extrabold ${kpi.color}`}>{kpi.value}</span>
            </div>
            <span className="text-xs text-gray-500 font-medium">{kpi.sub}</span>
          </div>
        ))}
      </div>

      {/* Target & AI Summary Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Annual Target Progress */}
        {target && (
          <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
            <div>
              <div className="flex justify-between items-center mb-2">
                <h3 className="font-bold text-gray-900 text-lg">Department Annual Target</h3>
                <span className={`text-xs px-2.5 py-1 rounded-full font-semibold ${target.is_at_risk ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'}`}>
                  {target.is_at_risk ? 'At Risk' : 'On Track'}
                </span>
              </div>
              <p className="text-sm text-gray-600 mb-4">{target.name}</p>
            </div>
            <div>
              <div className="flex justify-between text-sm mb-2">
                <span className="text-gray-500 font-medium">{target.completed} of {target.target} completed</span>
                <span className="font-bold text-gray-900">{target.progress_percent}%</span>
              </div>
              <div className="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                <div
                  className={`h-full rounded-full transition-all duration-500 ${target.is_at_risk ? 'bg-gradient-to-r from-orange-500 to-red-500' : 'bg-gradient-to-r from-blue-600 to-indigo-600'}`}
                  style={{ width: `${Math.min(100, target.progress_percent)}%` }}
                />
              </div>
            </div>
          </div>
        )}

        {/* AI Department Summary */}
        <div className="lg:col-span-2 bg-gradient-to-br from-indigo-50/60 to-purple-50/60 border border-indigo-100 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center gap-2 mb-3">
              <span className="text-lg">🤖</span>
              <h3 className="font-bold text-indigo-950 text-base">AI Performance & Operational Summary</h3>
              <span className="text-xs bg-indigo-200/60 text-indigo-800 px-2 py-0.5 rounded font-medium ml-auto">Advisory</span>
            </div>
            <p className="text-gray-800 text-sm leading-relaxed mb-4">{ai_summary.text}</p>
          </div>
          <p className="text-xs text-indigo-600/80 italic border-t border-indigo-100/60 pt-3">
            ℹ️ {ai_summary.disclaimer}
          </p>
        </div>
      </div>

      {/* Alerts Row: Pending Reviews & Overdue Tasks */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Pending Reviews */}
        <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
          <div className="flex justify-between items-center mb-4">
            <h3 className="font-bold text-gray-900 text-base flex items-center gap-2">
              <span>📋</span> Reports Awaiting Review
            </h3>
            <Link to={PATHS.departmentHeadReports} className="text-xs font-semibold text-blue-600 hover:underline">
              View All
            </Link>
          </div>
          {pending_review_alerts.length === 0 ? (
            <p className="text-sm text-gray-500 py-6 text-center">No reports awaiting review.</p>
          ) : (
            <div className="divide-y divide-gray-100">
              {pending_review_alerts.map(r => (
                <div key={r.id} className="py-3 flex justify-between items-center gap-4">
                  <div className="min-w-0">
                    <p className="font-medium text-gray-900 text-sm truncate">{r.title}</p>
                    <p className="text-xs text-gray-500">{r.employee_name} · {r.task_title}</p>
                  </div>
                  <button
                    onClick={() => navigate(departmentHeadReportDetailPath(r.id))}
                    className="px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-xs font-semibold shrink-0"
                  >
                    Review
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Overdue Alerts */}
        <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
          <div className="flex justify-between items-center mb-4">
            <h3 className="font-bold text-gray-900 text-base flex items-center gap-2">
              <span className="text-red-500">⏰</span> Overdue Tasks
            </h3>
            <Link to={PATHS.departmentHeadTasks} className="text-xs font-semibold text-blue-600 hover:underline">
              View All Tasks
            </Link>
          </div>
          {overdue_alerts.length === 0 ? (
            <p className="text-sm text-gray-500 py-6 text-center text-emerald-600">✓ All tasks are on schedule.</p>
          ) : (
            <div className="divide-y divide-gray-100">
              {overdue_alerts.map(a => (
                <div key={a.assignment_id} className="py-3 flex justify-between items-center gap-4">
                  <div className="min-w-0">
                    <p className="font-medium text-gray-900 text-sm truncate">{a.task_title}</p>
                    <p className="text-xs text-gray-500">{a.employee_name}</p>
                  </div>
                  <div className="text-right shrink-0">
                    <span className="text-xs font-bold text-red-600 block">{a.days_overdue} days overdue</span>
                    <button
                      onClick={() => navigate(departmentHeadTaskDetailPath(a.task_id))}
                      className="text-xs text-blue-600 hover:underline mt-0.5 inline-block"
                    >
                      Inspect
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Department Employees Summary Table */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
        <div className="flex justify-between items-center mb-4">
          <div>
            <h3 className="font-bold text-gray-900 text-lg">Department Members Progress</h3>
            <p className="text-xs text-gray-500 mt-0.5">Faculty members and assigned task completion rates</p>
          </div>
          <Link to={PATHS.departmentHeadEmployees} className="text-xs font-semibold text-blue-600 hover:underline">
            Manage Directory →
          </Link>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-gray-50 text-gray-500 text-xs font-semibold uppercase tracking-wider border-y border-gray-100">
              <tr>
                <th className="py-3 px-4">Faculty Member</th>
                <th className="py-3 px-4">Position</th>
                <th className="py-3 px-4">Assigned Tasks</th>
                <th className="py-3 px-4">Completed</th>
                <th className="py-3 px-4">Overdue</th>
                <th className="py-3 px-4">Completion Rate</th>
                <th className="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {employee_summary.map(emp => (
                <tr key={emp.id} className="hover:bg-gray-50/80 transition-colors">
                  <td className="py-3.5 px-4 font-medium text-gray-900">
                    <div>{emp.name}</div>
                    <div className="text-xs text-gray-400 font-normal">{emp.employee_number}</div>
                  </td>
                  <td className="py-3.5 px-4 text-gray-600">{emp.position}</td>
                  <td className="py-3.5 px-4 font-semibold text-gray-700">{emp.tasks_total}</td>
                  <td className="py-3.5 px-4 text-emerald-600 font-medium">{emp.tasks_completed}</td>
                  <td className="py-3.5 px-4">
                    {emp.tasks_overdue > 0 ? (
                      <span className="text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-full">
                        {emp.tasks_overdue} Overdue
                      </span>
                    ) : (
                      <span className="text-xs text-gray-400">0</span>
                    )}
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="flex items-center gap-2">
                      <div className="w-20 bg-gray-100 rounded-full h-2">
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
                      className="text-xs text-blue-600 hover:text-blue-800 font-semibold"
                    >
                      View Profile
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}