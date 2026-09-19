import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getAdminExecutiveDashboard } from '../../services/adminPortalService'
import type { AdminExecutiveDashboardData } from '../../services/adminPortalService'
import { PATHS, adminDepartmentDetailPath } from '../../routes/paths'

export default function AdminExecutiveDashboardPage() {
  const [data, setData] = useState<AdminExecutiveDashboardData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    getAdminExecutiveDashboard()
      .then(res => setData(res.data))
      .catch(() => setError('Failed to load institution executive metrics.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="p-6 space-y-6 max-w-7xl mx-auto">
        <div className="h-12 bg-gray-200 rounded-2xl animate-pulse w-1/3" />
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {[...Array(8)].map((_, i) => (
            <div key={i} className="h-28 bg-gray-100 rounded-2xl animate-pulse" />
          ))}
        </div>
      </div>
    )
  }

  if (error || !data) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 text-red-700 p-5 rounded-2xl">
          {error ?? 'Unable to display executive dashboard.'}
        </div>
      </div>
    )
  }

  const { institution, kpis, status_distribution, department_performance, monthly_achievements, critical_alerts, ai_executive_summary } = data

  return (
    <div className="p-6 space-y-8 max-w-7xl mx-auto">
      {/* Executive Header Banner */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gradient-to-r from-slate-900 via-blue-950 to-indigo-950 p-6 sm:p-8 rounded-3xl text-white shadow-xl">
        <div>
          <div className="flex items-center gap-2.5">
            <span className="bg-blue-600/80 text-white text-xs px-3 py-1 rounded-full font-bold tracking-wider uppercase">
              Executive Management
            </span>
            <span className="text-blue-200 text-xs font-semibold">{institution.academic_year} · {institution.current_term}</span>
          </div>
          <h1 className="text-3xl sm:text-4xl font-black mt-2 tracking-tight">{institution.name}</h1>
          <p className="text-blue-200/90 text-sm mt-1">Institution-Wide Progress & Monitoring Dashboard</p>
        </div>
        <div className="flex gap-3 flex-wrap">
          <Link
            to={PATHS.adminExecutiveReport}
            className="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm transition-all shadow-md flex items-center gap-2"
          >
            <span>📄</span> Executive Report
          </Link>
          <Link
            to={PATHS.adminExports}
            className="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white font-semibold rounded-xl text-sm backdrop-blur transition-all border border-white/20"
          >
            Export Center
          </Link>
        </div>
      </div>

      {/* Main Institution Progress Bar */}
      <div className="bg-white border border-gray-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
          <div>
            <span className="text-xs font-bold text-gray-400 uppercase tracking-widest">Overall Institution Progress</span>
            <h2 className="text-2xl font-black text-gray-900 mt-0.5">Academic & Operational Deliverables</h2>
          </div>
          <div className="text-right">
            <span className="text-4xl font-black text-blue-600 tracking-tight">{kpis.institution_progress}%</span>
            <span className="text-xs text-gray-400 font-semibold block">Completed</span>
          </div>
        </div>
        <div className="w-full bg-gray-100 rounded-full h-4 overflow-hidden p-0.5 border border-gray-200">
          <div
            className="h-full rounded-full bg-gradient-to-r from-blue-600 via-indigo-600 to-emerald-500 transition-all duration-700 shadow-sm"
            style={{ width: `${kpis.institution_progress}%` }}
          />
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2 text-xs text-gray-500 font-medium border-t border-gray-100">
          <div>Total Deliverables: <b className="text-gray-900 font-bold">{kpis.total_tasks}</b></div>
          <div>Completed Deliverables: <b className="text-emerald-600 font-bold">{kpis.completed_tasks}</b></div>
          <div>On-Time Rate: <b className="text-teal-600 font-bold">{kpis.on_time_rate}%</b></div>
          <div>Report Approval Rate: <b className="text-purple-600 font-bold">{kpis.approval_rate}%</b></div>
        </div>
      </div>

      {/* Primary KPI Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          { label: 'Total Faculty & Staff', value: kpis.total_employees, sub: `${kpis.active_employees} Active Accounts`, color: 'text-blue-600', icon: '👥' },
          { label: 'Academic Departments', value: kpis.total_departments, sub: 'Operating Units', color: 'text-indigo-600', icon: '🏛️' },
          { label: 'Reports Submitted', value: kpis.submitted_reports, sub: `${kpis.approved_reports} Approved`, color: 'text-emerald-600', icon: '📝' },
          { label: 'Pending Reviews', value: kpis.pending_reviews, sub: 'Needs Dept Head Action', color: 'text-amber-600', icon: '⏳' },
          { label: 'Revision Required', value: kpis.revision_required, sub: 'Returned to Faculty', color: 'text-orange-600', icon: '🔄' },
          { label: 'Overdue Tasks', value: kpis.overdue_tasks, sub: 'Past Milestone Deadline', color: 'text-red-600', icon: '⏰' },
          { label: 'Escalated Issues', value: critical_alerts.open_escalations_count, sub: 'Department Head Flags', color: 'text-rose-600', icon: '🛡️' },
          { label: 'AI Validation Status', value: 'Active', sub: 'OCR & NLP Pipelines 100%', color: 'text-purple-600', icon: '🤖' },
        ].map((kpi, idx) => (
          <div key={idx} className="p-5 rounded-2xl border border-gray-100 bg-white shadow-sm flex flex-col justify-between">
            <div className="flex justify-between items-start">
              <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">{kpi.label}</span>
              <span className="text-lg">{kpi.icon}</span>
            </div>
            <div className="my-2">
              <span className={`text-3xl font-black ${kpi.color}`}>{kpi.value}</span>
            </div>
            <span className="text-xs text-gray-500 font-medium">{kpi.sub}</span>
          </div>
        ))}
      </div>

      {/* AI Executive Summary Box */}
      <div className="bg-gradient-to-br from-indigo-50/80 via-purple-50/60 to-blue-50/80 border border-indigo-200/80 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <span className="text-2xl">🤖</span>
            <h3 className="font-extrabold text-indigo-950 text-lg">AI Executive Operational Synthesis</h3>
          </div>
          <span className="text-xs bg-indigo-200 text-indigo-900 px-3 py-1 rounded-full font-bold">
            Executive Advisory
          </span>
        </div>
        <p className="text-sm text-gray-800 leading-relaxed font-medium">{ai_executive_summary.text}</p>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-indigo-100">
          <div className="bg-white/80 p-4 rounded-2xl border border-indigo-100/60 space-y-2">
            <h4 className="font-bold text-xs text-emerald-800 uppercase tracking-wider flex items-center gap-1.5">
              <span>✓</span> Key Accomplishments
            </h4>
            <ul className="text-xs text-gray-700 space-y-1">
              {ai_executive_summary.key_accomplishments.map((a, i) => <li key={i}>• {a}</li>)}
            </ul>
          </div>

          <div className="bg-white/80 p-4 rounded-2xl border border-indigo-100/60 space-y-2">
            <h4 className="font-bold text-xs text-amber-800 uppercase tracking-wider flex items-center gap-1.5">
              <span>⚠️</span> Areas Needing Attention
            </h4>
            <ul className="text-xs text-gray-700 space-y-1">
              {ai_executive_summary.areas_needing_attention.map((a, i) => <li key={i}>• {a}</li>)}
            </ul>
          </div>

          <div className="bg-white/80 p-4 rounded-2xl border border-indigo-100/60 space-y-2">
            <h4 className="font-bold text-xs text-blue-800 uppercase tracking-wider flex items-center gap-1.5">
              <span>💡</span> Strategic Recommendations
            </h4>
            <ul className="text-xs text-gray-700 space-y-1">
              {ai_executive_summary.recommendations.map((r, i) => <li key={i}>• {r}</li>)}
            </ul>
          </div>
        </div>

        <p className="text-xs text-indigo-600/80 italic pt-2 border-t border-indigo-100/60">
          ℹ️ {ai_executive_summary.disclaimer}
        </p>
      </div>

      {/* Distributions: Task & Report Statuses */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
          <h3 className="font-extrabold text-gray-900 text-lg">Task Status Distribution</h3>
          <div className="space-y-3">
            {[
              { label: 'Completed Deliverables', val: status_distribution.tasks.completed, total: kpis.total_tasks, color: 'bg-emerald-500' },
              { label: 'In Progress', val: status_distribution.tasks.in_progress, total: kpis.total_tasks, color: 'bg-blue-500' },
              { label: 'Pending Assignment', val: status_distribution.tasks.pending, total: kpis.total_tasks, color: 'bg-amber-400' },
              { label: 'Overdue Deliverables', val: status_distribution.tasks.overdue, total: kpis.total_tasks, color: 'bg-red-500' },
            ].map((st, i) => (
              <div key={i} className="space-y-1">
                <div className="flex justify-between text-xs font-bold text-gray-700">
                  <span>{st.label}</span>
                  <span>{st.val} ({st.total > 0 ? Math.round((st.val / st.total) * 100) : 0}%)</span>
                </div>
                <div className="w-full bg-gray-100 rounded-full h-2">
                  <div className={`h-2 rounded-full ${st.color}`} style={{ width: `${st.total > 0 ? (st.val / st.total) * 100 : 0}%` }} />
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
          <h3 className="font-extrabold text-gray-900 text-lg">Report Review Status Distribution</h3>
          <div className="space-y-3">
            {[
              { label: 'Approved Reports', val: status_distribution.reports.approved, total: kpis.submitted_reports, color: 'bg-emerald-500' },
              { label: 'Under Review', val: status_distribution.reports.under_review, total: kpis.submitted_reports, color: 'bg-blue-500' },
              { label: 'Revision Required', val: status_distribution.reports.revision_required, total: kpis.submitted_reports, color: 'bg-orange-500' },
              { label: 'Rejected', val: status_distribution.reports.rejected, total: kpis.submitted_reports, color: 'bg-red-500' },
            ].map((sr, i) => (
              <div key={i} className="space-y-1">
                <div className="flex justify-between text-xs font-bold text-gray-700">
                  <span>{sr.label}</span>
                  <span>{sr.val} ({sr.total > 0 ? Math.round((sr.val / sr.total) * 100) : 0}%)</span>
                </div>
                <div className="w-full bg-gray-100 rounded-full h-2">
                  <div className={`h-2 rounded-full ${sr.color}`} style={{ width: `${sr.total > 0 ? (sr.val / sr.total) * 100 : 0}%` }} />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Department Leaderboard & Monthly Achievers */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Department Leaderboard */}
        <div className="lg:col-span-2 bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h3 className="font-extrabold text-gray-900 text-lg">Department Completion Leaderboard</h3>
              <p className="text-xs text-gray-400 mt-0.5">Objective ranking based on verified milestone completions</p>
            </div>
            <Link to={PATHS.adminDepartments} className="text-xs font-bold text-blue-600 hover:underline">
              All Departments →
            </Link>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-wider border-y border-gray-100">
                <tr>
                  <th className="py-3 px-4">Rank</th>
                  <th className="py-3 px-4">Department</th>
                  <th className="py-3 px-4">Faculty</th>
                  <th className="py-3 px-4">Deliverables</th>
                  <th className="py-3 px-4">Completion</th>
                  <th className="py-3 px-4 text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {department_performance.map(d => (
                  <tr key={d.id} className="hover:bg-gray-50/80 transition-colors">
                    <td className="py-3.5 px-4 font-black text-gray-800">#{d.rank}</td>
                    <td className="py-3.5 px-4 font-bold text-gray-900">{d.name}</td>
                    <td className="py-3.5 px-4 text-gray-600">{d.employees_count}</td>
                    <td className="py-3.5 px-4 font-semibold text-gray-700">{d.completed_count} / {d.tasks_count}</td>
                    <td className="py-3.5 px-4">
                      <div className="flex items-center gap-2">
                        <div className="w-20 bg-gray-100 rounded-full h-2">
                          <div className="bg-blue-600 h-2 rounded-full" style={{ width: `${d.completion_rate}%` }} />
                        </div>
                        <span className="text-xs font-bold text-gray-800">{d.completion_rate}%</span>
                      </div>
                    </td>
                    <td className="py-3.5 px-4 text-right">
                      <button
                        onClick={() => navigate(adminDepartmentDetailPath(d.id))}
                        className="text-xs font-bold text-blue-600 hover:underline"
                      >
                        Inspect
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Monthly Achievers */}
        <div className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
          <div className="flex justify-between items-center">
            <h3 className="font-extrabold text-gray-900 text-lg flex items-center gap-2">
              <span>🏆</span> Monthly Achievers
            </h3>
            <span className="text-xs bg-amber-100 text-amber-800 font-bold px-2.5 py-0.5 rounded-full">Top Faculty</span>
          </div>

          <div className="space-y-3">
            {monthly_achievements.map((ma) => (
              <div key={ma.employee_id} className="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 flex items-center gap-3">
                <span className="text-2xl">{ma.icon}</span>
                <div className="min-w-0 flex-1">
                  <div className="flex justify-between items-baseline">
                    <h4 className="font-bold text-sm text-gray-900 truncate">{ma.name}</h4>
                    <span className="text-xs font-extrabold text-blue-600">{ma.completion_rate}%</span>
                  </div>
                  <p className="text-xs text-gray-500 truncate">{ma.department_name} · {ma.badge}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}