import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getAdminReport, overrideAdminReport } from '../../services/adminPortalService'
import type { AdminReportDetail } from '../../services/adminPortalService'
import { PATHS } from '../../routes/paths'

export default function AdminReportDetailPage() {
  const { reportId } = useParams()
  const navigate = useNavigate()
  const [report, setReport] = useState<AdminReportDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [overrideStatus, setOverrideStatus] = useState<'approved' | 'revision_required' | 'rejected'>('approved')
  const [overrideReason, setOverrideReason] = useState('')
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (!reportId) return
    setLoading(true)
    getAdminReport(reportId)
      .then(res => setReport(res.data))
      .catch(() => setError('Failed to load report.'))
      .finally(() => setLoading(false))
  }, [reportId])

  const handleOverride = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!reportId || !overrideReason.trim()) {
      alert('A valid reason is required for administrative status override.')
      return
    }

    setSubmitting(true)
    try {
      await overrideAdminReport(reportId, { status: overrideStatus, reason: overrideReason })
      const updated = await getAdminReport(reportId)
      setReport(updated.data)
      setOverrideReason('')
      alert('Administrative override recorded in immutable audit log.')
    } catch {
      alert('Failed to execute override.')
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) return <div className="p-6 space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />)}</div>
  if (error || !report) return <div className="p-6 bg-red-50 text-red-700 rounded-xl border border-red-200">{error ?? 'Report not found.'}</div>

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      <button onClick={() => navigate(PATHS.adminReports)} className="text-sm font-semibold text-gray-500 hover:text-gray-900 flex items-center gap-1">
        ← Back to Reports Center
      </button>

      {/* Header */}
      <div className="bg-white border border-gray-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
        <div className="flex justify-between items-start">
          <div>
            <span className="text-xs bg-gray-100 font-mono font-bold px-2.5 py-1 rounded-lg text-gray-700">v{report.version}</span>
            <span className={`ml-2 text-xs px-2.5 py-1 rounded-lg font-bold ${report.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}`}>
              {report.status.toUpperCase()}
            </span>
            <h1 className="text-2xl font-black text-gray-900 mt-2">{report.title}</h1>
            <p className="text-xs text-gray-500 mt-1">{report.employee.name} · {report.employee.department} · {report.employee.position}</p>
          </div>
          <span className="text-xs text-gray-400">Submitted: {report.submitted_at ? new Date(report.submitted_at).toLocaleDateString() : '—'}</span>
        </div>
      </div>

      {/* Content & AI Validation Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-3">
          <h3 className="font-extrabold text-gray-900 text-base">Original Submission Content</h3>
          <div className="bg-gray-50 p-4 rounded-2xl text-xs text-gray-800 whitespace-pre-wrap leading-relaxed border border-gray-100 max-h-72 overflow-y-auto">
            {report.content ?? 'No text body.'}
          </div>
          {report.references && (
            <p className="text-xs text-gray-500 pt-2 border-t border-gray-100">
              <b>References:</b> {report.references}
            </p>
          )}
        </div>

        <div className="bg-gradient-to-br from-indigo-50/60 to-purple-50/60 border border-indigo-100 rounded-3xl p-6 shadow-sm space-y-3">
          <h3 className="font-extrabold text-indigo-950 text-base flex items-center gap-2">
            <span>🤖</span> AI Summary & Validation
          </h3>
          <p className="text-xs text-gray-800 leading-relaxed font-medium">{report.ai_summary.executive_summary}</p>
          <div className="pt-2 border-t border-indigo-100 text-xs space-y-1">
            {report.ai_validation.requirement_checks.map((c, i) => (
              <div key={i} className="flex justify-between items-center text-gray-700">
                <span>{c.requirement}</span>
                <span className="font-bold text-emerald-600">{c.status.toUpperCase()}</span>
              </div>
            ))}
          </div>
          <p className="text-[11px] text-indigo-600 italic">ℹ️ {report.ai_validation.disclaimer}</p>
        </div>
      </div>

      {/* Administrative Override Panel */}
      <div className="bg-white border border-rose-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
        <div>
          <h2 className="text-lg font-black text-gray-900 flex items-center gap-2 text-rose-950">
            <span>🛡️</span> Administrative Human Override
          </h2>
          <p className="text-xs text-gray-500">
            Authorized administrators may override review status. All overrides require an institutional justification recorded into the permanent audit trail.
          </p>
        </div>

        <form onSubmit={handleOverride} className="space-y-4">
          <div className="flex gap-3">
            {(['approved', 'revision_required', 'rejected'] as const).map(st => (
              <label key={st} className={`p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all ${overrideStatus === st ? 'bg-rose-50 border-rose-400 text-rose-900 shadow-sm' : 'border-gray-200 text-gray-600'}`}>
                <input
                  type="radio"
                  name="overrideStatus"
                  value={st}
                  checked={overrideStatus === st}
                  onChange={() => setOverrideStatus(st)}
                  className="mr-2 text-rose-600"
                />
                {st.replace(/_/g, ' ').toUpperCase()}
              </label>
            ))}
          </div>

          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
              Mandatory Override Reason *
            </label>
            <textarea
              value={overrideReason}
              onChange={e => setOverrideReason(e.target.value)}
              rows={3}
              placeholder="State the institutional justification for this override..."
              className="w-full border border-gray-300 rounded-2xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500"
              required
            />
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              disabled={submitting || !overrideReason.trim()}
              className="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs shadow-sm disabled:opacity-50"
            >
              {submitting ? 'Recording...' : 'Commit Administrative Override'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}