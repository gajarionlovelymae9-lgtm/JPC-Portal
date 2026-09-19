import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getDepartmentReport, reviewDepartmentReport } from '../../services/departmentHeadService'
import type { DepartmentReportDetail } from '../../services/departmentHeadService'
import { PATHS } from '../../routes/paths'

export default function DepartmentReportDetailPage() {
  const { reportId } = useParams()
  const navigate = useNavigate()
  const [report, setReport] = useState<DepartmentReportDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [decision, setDecision] = useState<'approved' | 'revision_required' | 'rejected'>('approved')
  const [comments, setComments] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [activeTab, setActiveTab] = useState<'original' | 'ai' | 'ocr' | 'history'>('original')

  useEffect(() => {
    if (!reportId) return
    setLoading(true)
    getDepartmentReport(reportId)
      .then(res => setReport(res.data))
      .catch(() => setError('Failed to load report review details.'))
      .finally(() => setLoading(false))
  }, [reportId])

  const handleReview = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!reportId) return
    if (decision !== 'approved' && !comments.trim()) {
      alert('Comments are required when requesting revisions or rejecting a report.')
      return
    }

    setSubmitting(true)
    try {
      await reviewDepartmentReport(reportId, { decision, comments })
      // Reload
      const updated = await getDepartmentReport(reportId)
      setReport(updated.data)
      setComments('')
      alert(`Report decision '${decision.replace(/_/g, ' ')}' successfully recorded.`)
    } catch {
      alert('Failed to submit review decision.')
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return <div className="p-6 space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />)}</div>
  }

  if (error || !report) {
    return <div className="p-6 bg-red-50 text-red-700 rounded-xl border border-red-200">{error ?? 'Report not found.'}</div>
  }

  return (
    <div className="p-6 max-w-6xl mx-auto space-y-6">
      <button onClick={() => navigate(PATHS.departmentHeadReports)} className="text-sm font-semibold text-gray-500 hover:text-gray-900 flex items-center gap-1">
        ← Back to Reports Queue
      </button>

      {/* Header Info */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
          <div className="flex items-center gap-2">
            <span className="font-mono text-xs text-gray-500 font-bold bg-gray-100 px-2.5 py-1 rounded-lg">Version {report.version}</span>
            <span className={`text-xs px-2.5 py-1 rounded-lg font-bold ${report.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : (report.status === 'revision_required' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800')}`}>
              {report.status.replace(/_/g, ' ').toUpperCase()}
            </span>
            {report.is_late && <span className="text-xs px-2.5 py-1 rounded-lg font-bold bg-red-100 text-red-700">SUBMITTED LATE</span>}
          </div>
          <span className="text-xs text-gray-400">
            Submitted: {report.submitted_at ? new Date(report.submitted_at).toLocaleString() : '—'}
          </span>
        </div>

        <h1 className="text-2xl font-bold text-gray-900">{report.title}</h1>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-3 border-t border-gray-100 text-xs">
          <div><span className="text-gray-400 block">Faculty Submitter</span><span className="font-semibold text-gray-800">{report.employee.name} ({report.employee.position})</span></div>
          <div><span className="text-gray-400 block">Task Assignment</span><span className="font-semibold text-gray-800">{report.task.title}</span></div>
          <div><span className="text-gray-400 block">Task Deadline</span><span className="font-semibold text-gray-800">{report.task.deadline ?? 'None'}</span></div>
        </div>
      </div>

      {/* Navigation Tabs for Content */}
      <div className="flex gap-2 border-b border-gray-200">
        {[
          { key: 'original', label: '📄 Original Report & Files' },
          { key: 'ai', label: '🤖 AI Summary & Validation' },
          { key: 'ocr', label: '🔍 OCR Extracted Text' },
          { key: 'history', label: `📜 Review History (${report.reviews.length})` },
        ].map(t => (
          <button
            key={t.key}
            onClick={() => setActiveTab(t.key as typeof activeTab)}
            className={`pb-3 px-4 font-semibold text-sm border-b-2 transition-colors ${activeTab === t.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Tab Panels */}
      {activeTab === 'original' && (
        <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
          <div>
            <h3 className="font-bold text-gray-900 text-base mb-2">Report Content</h3>
            <div className="bg-gray-50 p-4 rounded-xl text-sm text-gray-800 whitespace-pre-wrap leading-relaxed border border-gray-100">
              {report.content ?? 'No body text provided.'}
            </div>
          </div>
          {report.references && (
            <div>
              <h3 className="font-bold text-gray-900 text-sm mb-1">References & Citations</h3>
              <p className="text-xs text-gray-600 whitespace-pre-wrap">{report.references}</p>
            </div>
          )}
          {report.files.length > 0 && (
            <div>
              <h3 className="font-bold text-gray-900 text-sm mb-2">Attached Files</h3>
              <div className="flex gap-2 flex-wrap">
                {report.files.map(f => (
                  <a
                    key={f.id}
                    href={f.url}
                    target="_blank"
                    rel="noreferrer"
                    className="flex items-center gap-2 bg-gray-50 border border-gray-200 px-3 py-2 rounded-xl text-xs font-semibold text-blue-600 hover:bg-blue-50"
                  >
                    <span>📎</span> {f.name}
                  </a>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {activeTab === 'ai' && (
        <div className="space-y-6">
          {/* Executive Summary */}
          <div className="bg-gradient-to-br from-indigo-50/70 to-blue-50/70 border border-indigo-100 rounded-2xl p-6 shadow-sm space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="font-bold text-indigo-950 text-base flex items-center gap-2">
                <span>🤖</span> AI Executive Summary
              </h3>
              <span className="text-xs bg-indigo-200/60 text-indigo-800 px-2.5 py-0.5 rounded-full font-medium">Advisory</span>
            </div>
            <p className="text-sm text-gray-800 leading-relaxed">{report.ai_summary.executive_summary}</p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-indigo-100">
              <div>
                <h4 className="font-bold text-xs text-indigo-900 uppercase tracking-wider mb-1">Key Points</h4>
                <ul className="list-disc list-inside text-xs text-gray-700 space-y-1">
                  {report.ai_summary.key_points.map((p, idx) => <li key={idx}>{p}</li>)}
                </ul>
              </div>
              <div>
                <h4 className="font-bold text-xs text-indigo-900 uppercase tracking-wider mb-1">Major Accomplishments</h4>
                <ul className="list-disc list-inside text-xs text-gray-700 space-y-1">
                  {report.ai_summary.accomplishments.map((a, idx) => <li key={idx}>{a}</li>)}
                </ul>
              </div>
            </div>
            <p className="text-[11px] text-indigo-600 italic pt-2">ℹ️ {report.ai_summary.disclaimer}</p>
          </div>

          {/* Validation Checks */}
          <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
            <h3 className="font-bold text-gray-900 text-base flex items-center gap-2">
              <span>✅</span> AI Requirement Adherence & Reference Checks
            </h3>
            <div className="divide-y divide-gray-100">
              {report.ai_validation.requirement_checks.map((chk, idx) => (
                <div key={idx} className="py-3 flex justify-between items-center gap-4">
                  <div>
                    <p className="text-sm font-semibold text-gray-800">{chk.requirement}</p>
                    {chk.note && <p className="text-xs text-gray-500 mt-0.5">{chk.note}</p>}
                  </div>
                  <span className={`text-xs px-2.5 py-1 rounded-full font-bold shrink-0 ${chk.status === 'found' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>
                    {chk.status.toUpperCase()}
                  </span>
                </div>
              ))}
            </div>

            <div className="p-4 bg-gray-50 rounded-xl border border-gray-100">
              <h4 className="font-bold text-xs text-gray-700 mb-1">{report.ai_validation.similarity.label}</h4>
              <p className="text-xs font-semibold text-gray-900">{report.ai_validation.similarity.result}</p>
              <p className="text-xs text-gray-500 mt-0.5">{report.ai_validation.similarity.note}</p>
            </div>
            <p className="text-[11px] text-gray-400 italic">ℹ️ {report.ai_validation.disclaimer}</p>
          </div>
        </div>
      )}

      {activeTab === 'ocr' && (
        <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-3">
          <div className="flex justify-between items-center">
            <h3 className="font-bold text-gray-900 text-base">OCR Extracted Text</h3>
            <span className="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg">
              {report.ocr.confidence_label} ({report.ocr.confidence}%)
            </span>
          </div>
          {report.ocr.warning && (
            <div className="p-3 bg-amber-50 text-amber-800 rounded-xl text-xs font-semibold border border-amber-200">
              ⚠️ {report.ocr.warning}
            </div>
          )}
          <div className="bg-gray-50 p-4 rounded-xl font-mono text-xs text-gray-700 whitespace-pre-wrap max-h-96 overflow-y-auto border border-gray-200">
            {report.ocr.extracted_text ?? 'No OCR text extracted.'}
          </div>
        </div>
      )}

      {activeTab === 'history' && (
        <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
          <h3 className="font-bold text-gray-900 text-base">Review & Decision History</h3>
          {report.reviews.length === 0 ? (
            <p className="text-sm text-gray-500 py-4">No reviews recorded yet for this report.</p>
          ) : (
            <div className="divide-y divide-gray-100">
              {report.reviews.map(rv => (
                <div key={rv.id} className="py-3 space-y-1">
                  <div className="flex justify-between items-center">
                    <span className="font-bold text-sm text-gray-800">{rv.reviewer_name}</span>
                    <span className="text-xs text-gray-400">{new Date(rv.created_at).toLocaleString()}</span>
                  </div>
                  <span className={`text-xs px-2 py-0.5 rounded-md font-bold inline-block ${rv.decision === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-orange-100 text-orange-800'}`}>
                    {rv.decision.toUpperCase()}
                  </span>
                  {rv.comments && <p className="text-xs text-gray-600 mt-1">"{rv.comments}"</p>}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Human Review Panel */}
      <div className="bg-white border border-blue-200 rounded-2xl p-6 shadow-sm space-y-4">
        <div>
          <h2 className="text-lg font-bold text-gray-900 flex items-center gap-2">
            <span>✍️</span> Department Head Review Decision
          </h2>
          <p className="text-xs text-gray-500">
            AI validation is only advisory. Final approval or revision instructions remain the authorized responsibility of the Department Head.
          </p>
        </div>

        <form onSubmit={handleReview} className="space-y-4">
          <div className="flex gap-4 flex-wrap">
            <label className={`flex items-center gap-2 p-3 rounded-xl border cursor-pointer font-bold text-xs transition-all ${decision === 'approved' ? 'bg-emerald-50 border-emerald-400 text-emerald-900 shadow-sm' : 'border-gray-200 text-gray-600'}`}>
              <input
                type="radio"
                name="decision"
                value="approved"
                checked={decision === 'approved'}
                onChange={() => setDecision('approved')}
                className="text-emerald-600"
              />
              ✓ Approve Report
            </label>

            <label className={`flex items-center gap-2 p-3 rounded-xl border cursor-pointer font-bold text-xs transition-all ${decision === 'revision_required' ? 'bg-orange-50 border-orange-400 text-orange-900 shadow-sm' : 'border-gray-200 text-gray-600'}`}>
              <input
                type="radio"
                name="decision"
                value="revision_required"
                checked={decision === 'revision_required'}
                onChange={() => setDecision('revision_required')}
                className="text-orange-600"
              />
              🔄 Request Revision
            </label>

            <label className={`flex items-center gap-2 p-3 rounded-xl border cursor-pointer font-bold text-xs transition-all ${decision === 'rejected' ? 'bg-red-50 border-red-400 text-red-900 shadow-sm' : 'border-gray-200 text-gray-600'}`}>
              <input
                type="radio"
                name="decision"
                value="rejected"
                checked={decision === 'rejected'}
                onChange={() => setDecision('rejected')}
                className="text-red-600"
              />
              ✕ Reject Submission
            </label>
          </div>

          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
              Review Comments & Guidance {decision !== 'approved' && <span className="text-red-500">*</span>}
            </label>
            <textarea
              value={comments}
              onChange={e => setComments(e.target.value)}
              rows={3}
              placeholder={decision === 'approved' ? 'Optional positive feedback...' : 'Specify the exact items requiring revision or reason for rejection...'}
              className="w-full border border-gray-300 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <button
              type="submit"
              disabled={submitting}
              className={`px-6 py-2.5 rounded-xl text-sm font-bold text-white shadow-sm disabled:opacity-50 transition-all ${decision === 'approved' ? 'bg-emerald-600 hover:bg-emerald-700' : (decision === 'revision_required' ? 'bg-orange-600 hover:bg-orange-700' : 'bg-red-600 hover:bg-red-700')}`}
            >
              {submitting ? 'Recording...' : `Submit Decision (${decision.replace(/_/g, ' ')})`}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}