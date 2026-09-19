import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getMyReports } from '../../services/employeePortalService'
import type { EmployeeReport } from '../../services/employeePortalService'
import { PATHS, employeeReportDetailPath } from '../../routes/paths'

const STATUS_COLORS: Record<string, string> = {
  DRAFT: 'bg-gray-100 text-gray-700', SUBMITTED: 'bg-blue-100 text-blue-700',
  UNDER_REVIEW: 'bg-yellow-100 text-yellow-700', REVISION_REQUIRED: 'bg-orange-100 text-orange-700',
  APPROVED: 'bg-green-100 text-green-700', REJECTED: 'bg-red-100 text-red-700',
}

export default function EmployeeReportsPage() {
  const [reports, setReports] = useState<EmployeeReport[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [statusFilter, setStatusFilter] = useState('all')
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    const params = statusFilter !== 'all' ? { status: statusFilter } : {}
    getMyReports(params)
      .then(r => setReports(Array.isArray(r.data) ? r.data : []))
      .catch(() => setError('Unable to load your reports. Please try again.'))
      .finally(() => setLoading(false))
  }, [statusFilter])

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">My Reports</h1>
        <Link to={PATHS.employeeReportCreate} className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">+ Create Report</Link>
      </div>
      <div className="flex gap-2 flex-wrap">
        {['all','DRAFT','SUBMITTED','UNDER_REVIEW','REVISION_REQUIRED','APPROVED','REJECTED'].map(s => (
          <button key={s} onClick={() => setStatusFilter(s)} className={`px-3 py-1 rounded-full text-sm font-medium border transition-colors ${statusFilter === s ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'}`}>
            {s === 'all' ? 'All' : s.replace(/_/g, ' ')}
          </button>
        ))}
      </div>
      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      {loading ? (
        <div className="space-y-3">{[...Array(4)].map((_,i) => <div key={i} className="h-16 bg-gray-100 rounded-lg animate-pulse" />)}</div>
      ) : reports.length === 0 ? (
        <div className="text-center py-16 text-gray-500"><div className="text-4xl mb-3">📄</div><p className="font-medium">No reports found.</p><p className="text-sm mt-1">Create your first report to get started.</p></div>
      ) : (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>{['Report','Task','Submitted','Status','AI Status','Actions'].map(h => <th key={h} className="px-4 py-3 text-left font-semibold text-gray-600">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {reports.map(report => (
                <tr key={report.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3"><div className="font-medium text-gray-900">{report.title}</div>{report.is_late && <span className="text-xs text-red-600 font-medium">LATE</span>}</td>
                  <td className="px-4 py-3 text-gray-600">{report.task_title}</td>
                  <td className="px-4 py-3 text-gray-500">{report.submitted_at ? new Date(report.submitted_at).toLocaleDateString() : '—'}</td>
                  <td className="px-4 py-3"><span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${STATUS_COLORS[report.status] ?? 'bg-gray-100 text-gray-600'}`}>{report.status.replace(/_/g,' ')}</span></td>
                  <td className="px-4 py-3 text-gray-500 text-xs">{report.ai_status ?? '—'}</td>
                  <td className="px-4 py-3 space-x-2">
                    <button onClick={() => navigate(employeeReportDetailPath(report.id))} className="text-blue-600 hover:underline text-xs font-medium">View</button>
                    {report.status === 'DRAFT' && <button onClick={() => navigate(`/employee/reports/${report.id}/edit`)} className="text-gray-600 hover:underline text-xs font-medium">Edit</button>}
                    {report.status === 'REVISION_REQUIRED' && <button onClick={() => navigate(`/employee/reports/${report.id}/revise`)} className="text-orange-600 hover:underline text-xs font-medium">Revise</button>}
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
