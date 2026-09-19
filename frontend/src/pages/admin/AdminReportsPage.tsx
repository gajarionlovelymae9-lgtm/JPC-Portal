import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getAdminReports } from '../../services/adminPortalService'
import type { AdminReportItem } from '../../services/adminPortalService'
import { adminReportDetailPath } from '../../routes/paths'

export default function AdminReportsPage() {
  const [reports, setReports] = useState<AdminReportItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    getAdminReports({ search, status: statusFilter })
      .then(res => setReports(res.data))
      .catch(() => setError('Failed to load reports.'))
      .finally(() => setLoading(false))
  }, [search, statusFilter])

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Institution Report Monitoring Center</h1>
        <p className="text-sm text-gray-500 mt-0.5">Comprehensive view of all department submissions, verification status, and review approvals</p>
      </div>

      <div className="flex flex-col sm:flex-row gap-3 bg-white p-4 rounded-2xl border border-gray-200 shadow-sm">
        <input
          type="text"
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Search by title, faculty, or department..."
          className="flex-1 border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <select
          value={statusFilter}
          onChange={e => setStatusFilter(e.target.value)}
          className="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
        >
          <option value="all">All Statuses</option>
          <option value="submitted">Submitted</option>
          <option value="under_review">Under Review</option>
          <option value="approved">Approved</option>
          <option value="revision_required">Revision Required</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      {error && <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200">{error}</div>}

      {loading ? (
        <div className="space-y-3">{[...Array(5)].map((_, i) => <div key={i} className="h-16 bg-gray-100 rounded-xl animate-pulse" />)}</div>
      ) : reports.length === 0 ? (
        <div className="bg-white border border-gray-200 rounded-2xl p-12 text-center text-gray-500">
          <span className="text-4xl block mb-2">📄</span>
          <p className="font-semibold">No reports found.</p>
        </div>
      ) : (
        <div className="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
          <table className="w-full text-sm text-left">
            <thead className="bg-gray-50 text-gray-600 text-xs font-bold uppercase tracking-wider border-b border-gray-200">
              <tr>
                <th className="py-3 px-4">Report</th>
                <th className="py-3 px-4">Faculty Member</th>
                <th className="py-3 px-4">Department</th>
                <th className="py-3 px-4">Task</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {reports.map(r => (
                <tr key={r.id} className="hover:bg-gray-50/80">
                  <td className="py-3.5 px-4 font-semibold text-gray-900">
                    <div className="flex items-center gap-2">
                      <span>{r.title}</span>
                      <span className="text-[10px] bg-gray-100 px-1.5 py-0.5 rounded font-mono">v{r.version}</span>
                    </div>
                  </td>
                  <td className="py-3.5 px-4">{r.employee_name}</td>
                  <td className="py-3.5 px-4 font-medium text-gray-600">{r.department_name}</td>
                  <td className="py-3.5 px-4 text-gray-600 text-xs font-mono">{r.task_title}</td>
                  <td className="py-3.5 px-4">
                    <span className={`text-xs px-2.5 py-1 rounded-full font-bold ${r.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : (r.status === 'revision_required' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800')}`}>
                      {r.status.replace(/_/g, ' ').toUpperCase()}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <button
                      onClick={() => navigate(adminReportDetailPath(r.id))}
                      className="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-xl text-xs"
                    >
                      Inspect →
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