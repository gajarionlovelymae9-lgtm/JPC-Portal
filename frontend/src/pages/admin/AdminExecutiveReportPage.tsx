import { useEffect, useState } from 'react'
import { getAdminExecutiveDashboard } from '../../services/adminPortalService'
import type { AdminExecutiveDashboardData } from '../../services/adminPortalService'

export default function AdminExecutiveReportPage() {
  const [data, setData] = useState<AdminExecutiveDashboardData | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    getAdminExecutiveDashboard()
      .then(res => setData(res.data))
      .finally(() => setLoading(false))
  }, [])

  if (loading || !data) {
    return <div className="p-8 text-center text-gray-500">Generating Executive Report...</div>
  }

  const { institution, kpis, department_performance, ai_executive_summary, operational_digest } = data

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      <div className="flex justify-between items-center print:hidden">
        <h1 className="text-2xl font-bold text-gray-900">Institutional Executive Report</h1>
        <button
          onClick={() => window.print()}
          className="px-5 py-2.5 bg-blue-600 text-white font-bold text-xs rounded-xl shadow-sm hover:bg-blue-700 flex items-center gap-2"
        >
          <span>🖨️</span> Print / Save PDF
        </button>
      </div>

      <div className="bg-white border border-gray-200 rounded-3xl p-8 sm:p-12 shadow-sm space-y-8 print:border-none print:shadow-none print:p-0">
        {/* Formal Header */}
        <div className="text-center border-b border-gray-200 pb-6 space-y-1">
          <h2 className="text-2xl font-black text-gray-900 tracking-tight">{institution.name}</h2>
          <p className="text-xs uppercase font-bold text-gray-500 tracking-widest">Office of Academic & Administrative Progress Monitoring</p>
          <p className="text-xs text-gray-400">Term: {institution.academic_year} · Date Generated: {new Date().toLocaleDateString()}</p>
        </div>

        {/* Executive Overview */}
        <div className="space-y-3">
          <h3 className="text-base font-extrabold text-gray-900 border-l-4 border-blue-600 pl-3">1. Executive Overview</h3>
          {operational_digest.available ? (
            <p className="text-sm text-gray-700 leading-relaxed">{operational_digest.text}</p>
          ) : (
            <p className="text-sm text-gray-500 italic">{ai_executive_summary.message}</p>
          )}
          {!ai_executive_summary.available ? (
            <p className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
              No AI insight is available. Provider <strong>{ai_executive_summary.provider_status.configured_provider}</strong>{' '}
              ({ai_executive_summary.provider_status.model}) — {ai_executive_summary.provider_status.note}
            </p>
          ) : null}
        </div>

        {/* Core Metrics Table */}
        <div className="space-y-3">
          <h3 className="text-base font-extrabold text-gray-900 border-l-4 border-blue-600 pl-3">2. Core Key Performance Indicators</h3>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div className="p-4 bg-gray-50 rounded-2xl border border-gray-100">
              <span className="text-xs text-gray-400 font-bold uppercase block">Progress</span>
              <span className="text-2xl font-black text-blue-600 block mt-1">{kpis.institution_progress}%</span>
            </div>
            <div className="p-4 bg-gray-50 rounded-2xl border border-gray-100">
              <span className="text-xs text-gray-400 font-bold uppercase block">Deliverables</span>
              <span className="text-2xl font-black text-gray-900 block mt-1">{kpis.completed_tasks} / {kpis.total_tasks}</span>
            </div>
            <div className="p-4 bg-gray-50 rounded-2xl border border-gray-100">
              <span className="text-xs text-gray-400 font-bold uppercase block">On-Time Rate</span>
              <span className="text-2xl font-black text-teal-600 block mt-1">{kpis.on_time_rate}%</span>
            </div>
            <div className="p-4 bg-gray-50 rounded-2xl border border-gray-100">
              <span className="text-xs text-gray-400 font-bold uppercase block">Approval Rate</span>
              <span className="text-2xl font-black text-purple-600 block mt-1">{kpis.approval_rate}%</span>
            </div>
          </div>
        </div>

        {/* Department Breakdown */}
        <div className="space-y-3">
          <h3 className="text-base font-extrabold text-gray-900 border-l-4 border-blue-600 pl-3">3. Department Performance Summary</h3>
          <table className="w-full text-xs text-left border border-gray-200">
            <thead className="bg-gray-100 font-bold text-gray-700">
              <tr>
                <th className="p-2.5 border">Rank</th>
                <th className="p-2.5 border">Department</th>
                <th className="p-2.5 border">Faculty</th>
                <th className="p-2.5 border">Completed Tasks</th>
                <th className="p-2.5 border text-right">Completion Rate</th>
              </tr>
            </thead>
            <tbody>
              {department_performance.map(d => (
                <tr key={d.id} className="border-t">
                  <td className="p-2.5 border font-bold">#{d.rank}</td>
                  <td className="p-2.5 border font-semibold">{d.name}</td>
                  <td className="p-2.5 border">{d.employees_count}</td>
                  <td className="p-2.5 border">{d.completed_count} of {d.tasks_count}</td>
                  <td className="p-2.5 border text-right font-bold text-blue-600">{d.completion_rate}%</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Strategic Guidance */}
        <div className="space-y-3">
          <h3 className="text-base font-extrabold text-gray-900 border-l-4 border-blue-600 pl-3">4. Strategic Recommendations</h3>
          {operational_digest.recommendations.length > 0 ? (
            <ul className="text-xs text-gray-700 space-y-1.5 pl-4 list-disc">
              {operational_digest.recommendations.map((r: string, idx: number) => <li key={idx}>{r}</li>)}
            </ul>
          ) : (
            <p className="text-xs text-gray-500 italic">No recommendations generated for this period.</p>
          )}
          <p className="text-[11px] text-gray-400">{operational_digest.disclaimer}</p>
        </div>
      </div>
    </div>
  )
}