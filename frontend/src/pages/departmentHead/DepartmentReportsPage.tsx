import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getDepartmentReports } from '../../services/departmentHeadService'
import type { DepartmentReportItem } from '../../services/departmentHeadService'
import { departmentHeadReportDetailPath } from '../../routes/paths'

export default function DepartmentReportsPage() {
  const [reports, setReports] = useState<DepartmentReportItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [statusFilter, setStatusFilter] = useState('all')
  const [search, setSearch] = useState('')
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    getDepartmentReports({ status: statusFilter, search })
      .then(res => setReports(res.data))
      .catch(() => setError('Failed to load department reports.'))
      .finally(() => setLoading(false))
  }, [statusFilter, search])

  const pendingCount = reports.filter(r => r.status === 'submitted' || r.status === 'under_review').length

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Faculty Report Submissions</h1>
          <p className="text-sm text-gray-500 mt-0.5">Review, validate AI analysis, and approve/request revisions on submitted work</p>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex flex-col sm:flex-row gap-3 bg-white p-4 rounded-2xl border border-gray-200 shadow-sm">
        <input
          type="text"
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Search by report title or faculty name..."
          className="flex-1 border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <div className="flex gap-1.5 flex-wrap">
          {[
            { key: 'all', label: 'All Submissions' },
            { key: 'submitted', label: 'Pending Review' },
            { key: 'approved', label: 'Approved' },
            { key: 'revision_required', label: 'Revision Required' },
            { key: 'rejected', label: 'Rejected' },
          ].map(tab => (
            <button
              key={tab.key}
              onClick={() => setStatusFilter(tab.key)}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-colors ${statusFilter === tab.key ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
            >
              {tab.label} {tab.key === 'submitted' && pendingCount > 0 && `(${pendingCount})`}
            </button>
          ))}
        </div>
      </div>

      {error && <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200">{error}</div>}

      {loading ? (
        <div className="space-y-3">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-16 bg-gray-100 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : reports.length === 0 ? (
        <div className="bg-white border border-gray-200 rounded-2xl p-12 text-center text-gray-500">
          <span className="text-4xl block mb-2">📄</span>
          <p className="font-semibold">No reports found matching criteria.</p>
        </div>
      ) : (
        <div className="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
          <table className="w-full text-sm text-left">
            <thead className="bg-gray-50 text-gray-600 text-xs font-semibold uppercase tracking-wider border-b border-gray-200">
              <tr>
                <th className="py-3 px-4">Report Title</th>
                <th className="py-3 px-4">Faculty Member</th>
                <th className="py-3 px-4">Task</th>
                <th className="py-3 px-4">Submitted</th>
                <th className="py-3 px-4">AI Check</th>
                <th className="py-3 px-4">Review Status</th>
                <th className="py-3 px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {reports.map(r => (
                <tr key={r.id} className="hover:bg-gray-50/80 transition-colors">
                  <td className="py-3.5 px-4 font-medium text-gray-900">
                    <div className="flex items-center gap-2">
                      <span>{r.title}</span>
                      <span className="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded font-mono">v{r.version}</span>
                      {r.is_late && <span className="text-[10px] bg-red-100 text-red-700 px-1.5 py-0.5 rounded font-bold">LATE</span>}
                    </div>
                  </td>
                  <td className="py-3.5 px-4">
                    <div className="font-medium text-gray-900">{r.employee_name}</div>
                    <div className="text-xs text-gray-400 font-mono">{r.employee_number}</div>
                  </td>
                  <td className="py-3.5 px-4 text-gray-600 font-medium">{r.task_title}</td>
                  <td className="py-3.5 px-4 text-gray-500 text-xs">
                    {r.submitted_at ? new Date(r.submitted_at).toLocaleDateString() : '—'}
                  </td>
                  <td className="py-3.5 px-4">
                    <span className="text-xs px-2 py-0.5 rounded-md font-medium bg-purple-50 text-purple-700 border border-purple-100 flex items-center gap-1 w-max">
                      <span>🤖</span> {r.ai_status}
                    </span>
                  </td>
                  <td className="py-3.5 px-4">
                    <span className={`text-xs px-2.5 py-1 rounded-full font-bold ${r.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : (r.status === 'revision_required' ? 'bg-orange-100 text-orange-800' : (r.status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'))}`}>
                      {r.status.replace(/_/g, ' ').toUpperCase()}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <button
                      onClick={() => navigate(departmentHeadReportDetailPath(r.id))}
                      className="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl text-xs font-bold transition-colors"
                    >
                      {r.status === 'submitted' || r.status === 'under_review' ? 'Review →' : 'View →'}
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