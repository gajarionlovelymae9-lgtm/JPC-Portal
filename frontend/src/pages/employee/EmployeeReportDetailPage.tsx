import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getMyReport } from '../../services/employeePortalService'
import type { EmployeeReport, AiRequirementCheck, AiReferenceResult } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'

const STATUS_COLORS: Record<string,string> = {
  DRAFT:'bg-gray-100 text-gray-700', SUBMITTED:'bg-blue-100 text-blue-700',
  UNDER_REVIEW:'bg-yellow-100 text-yellow-700', REVISION_REQUIRED:'bg-orange-100 text-orange-700',
  APPROVED:'bg-green-100 text-green-700', REJECTED:'bg-red-100 text-red-700',
}

function CheckIcon({ status }: { status: string }) {
  if (status === 'found') return <span className="text-green-600">✓</span>
  if (status === 'missing') return <span className="text-red-500">✗</span>
  return <span className="text-yellow-500">⚠</span>
}

export default function EmployeeReportDetailPage() {
  const { reportId } = useParams()
  const navigate = useNavigate()
  const [report, setReport] = useState<EmployeeReport | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [activeTab, setActiveTab] = useState<'overview'|'ocr'|'ai_summary'|'ai_validation'|'review'>('overview')

  useEffect(() => {
    if (!reportId) return
    setLoading(true)
    getMyReport(reportId)
      .then(r => setReport(r.data))
      .catch(() => setError('Unable to load report.'))
      .finally(() => setLoading(false))
  }, [reportId])

  if (loading) return <div className="p-6 space-y-3">{[...Array(6)].map((_,i) => <div key={i} className="h-10 bg-gray-100 rounded-lg animate-pulse"/>)}</div>
  if (error || !report) return <div className="p-6 bg-red-50 text-red-700 rounded-lg">{error ?? 'Report not found.'}</div>

  const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'ocr', label: 'OCR Result' },
    { key: 'ai_summary', label: 'AI Summary' },
    { key: 'ai_validation', label: 'AI Validation' },
    { key: 'review', label: 'Review History' },
  ] as const

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={() => navigate(PATHS.employeeReports)} className="text-gray-500 hover:text-gray-700 text-xl">←</button>
        <div className="flex-1">
          <h1 className="text-2xl font-bold text-gray-900">{report.title}</h1>
          <p className="text-sm text-gray-500 mt-1">{report.task_title} · Version {report.version} {report.is_late && <span className="text-red-600 font-semibold ml-2">LATE</span>}</p>
        </div>
        <span className={`px-3 py-1 rounded-full text-sm font-semibold ${STATUS_COLORS[report.status] ?? 'bg-gray-100 text-gray-600'}`}>{report.status.replace(/_/g,' ')}</span>
      </div>

      {report.status === 'REVISION_REQUIRED' && (
        <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 flex items-center justify-between">
          <div><p className="font-semibold text-orange-800">Revision Required</p><p className="text-orange-700 text-sm mt-1">{report.review_history.at(-1)?.comments ?? 'Please review the feedback and submit a revision.'}</p></div>
          <button onClick={() => navigate(`/employee/reports/${report.id}/revise`)} className="px-4 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700">Create Revision</button>
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-1 border-b border-gray-200">
        {tabs.map(tab => (
          <button key={tab.key} onClick={() => setActiveTab(tab.key)} className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${activeTab === tab.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>{tab.label}</button>
        ))}
      </div>

      {/* Overview */}
      {activeTab === 'overview' && (
        <div className="space-y-4">
          <div className="bg-white border border-gray-200 rounded-xl p-5 grid grid-cols-2 gap-4 text-sm">
            <div><span className="text-gray-500">Submitted</span><p className="font-medium">{report.submitted_at ? new Date(report.submitted_at).toLocaleString() : '—'}</p></div>
            <div><span className="text-gray-500">Deadline</span><p className="font-medium">{report.deadline ?? '—'}</p></div>
            <div><span className="text-gray-500">Approved</span><p className="font-medium">{report.approved_at ? new Date(report.approved_at).toLocaleString() : '—'}</p></div>
            <div><span className="text-gray-500">Versions</span><p className="font-medium">{report.version_count}</p></div>
          </div>
          {report.content && <div className="bg-white border border-gray-200 rounded-xl p-5"><h3 className="font-semibold text-gray-800 mb-3">Report Content</h3><div className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{report.content}</div></div>}
          {report.references && <div className="bg-white border border-gray-200 rounded-xl p-5"><h3 className="font-semibold text-gray-800 mb-3">References</h3><div className="text-sm text-gray-600 whitespace-pre-wrap">{report.references}</div></div>}
        </div>
      )}

      {/* OCR */}
      {activeTab === 'ocr' && (
        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
          {!report.ocr ? <p className="text-gray-500 text-sm">No OCR data available.</p> : (
            <>
              <div className="flex items-center gap-3">
                <span className="text-sm font-semibold text-gray-700">Status:</span>
                <span className="px-2 py-0.5 bg-gray-100 rounded text-xs font-medium">{report.ocr.status}</span>
                <span className="text-sm font-semibold text-gray-700 ml-4">Confidence:</span>
                <span className="text-sm">{report.ocr.confidence_label}{report.ocr.confidence !== null ? ` (${report.ocr.confidence}%)` : ''}</span>
              </div>
              {report.ocr.warning && <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-yellow-800 text-sm">⚠ {report.ocr.warning}</div>}
              {report.ocr.extracted_text ? (
                <div><h3 className="font-semibold text-gray-800 mb-2 text-sm">Extracted Text</h3><div className="bg-gray-50 rounded-lg p-4 text-sm font-mono whitespace-pre-wrap text-gray-700 max-h-96 overflow-y-auto">{report.ocr.extracted_text}</div></div>
              ) : <p className="text-gray-500 text-sm">No extracted text yet.</p>}
            </>
          )}
        </div>
      )}

      {/* AI Summary */}
      {activeTab === 'ai_summary' && (
        <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
          {!report.ai_summary ? <p className="text-gray-500 text-sm">AI summary not yet generated.</p> : (
            <>
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-blue-800 text-xs">ℹ {report.ai_summary.disclaimer}</div>
              {report.ai_summary.executive_summary && <div><h3 className="font-semibold text-gray-800 mb-2">Executive Summary</h3><p className="text-sm text-gray-700">{report.ai_summary.executive_summary}</p></div>}
              {report.ai_summary.key_points.length > 0 && <div><h3 className="font-semibold text-gray-800 mb-2">Key Points</h3><ul className="list-disc list-inside space-y-1">{report.ai_summary.key_points.map((p,i) => <li key={i} className="text-sm text-gray-700">{p}</li>)}</ul></div>}
              {report.ai_summary.accomplishments.length > 0 && <div><h3 className="font-semibold text-gray-800 mb-2">Major Accomplishments</h3><ul className="list-disc list-inside space-y-1">{report.ai_summary.accomplishments.map((a,i) => <li key={i} className="text-sm text-gray-700">{a}</li>)}</ul></div>}
              {report.ai_summary.issues.length > 0 && <div><h3 className="font-semibold text-gray-800 mb-2">Issues</h3><ul className="list-disc list-inside space-y-1">{report.ai_summary.issues.map((iss,i) => <li key={i} className="text-sm text-gray-700">{iss}</li>)}</ul></div>}
            </>
          )}
        </div>
      )}

      {/* AI Validation */}
      {activeTab === 'ai_validation' && (
        <div className="space-y-4">
          {!report.ai_validation ? <div className="bg-white border border-gray-200 rounded-xl p-5 text-gray-500 text-sm">AI validation not yet available.</div> : (
            <>
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-blue-800 text-xs">ℹ {report.advisory_label} — {report.disclaimer}</div>
              <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                <h3 className="font-semibold text-gray-800">Requirement Checks</h3>
                {report.ai_validation.requirement_checks.map((c: AiRequirementCheck, i: number) => (
                  <div key={i} className="flex items-start gap-3 text-sm">
                    <CheckIcon status={c.status} />
                    <div><p className="text-gray-800">{c.requirement}</p>{c.note && <p className="text-gray-500 text-xs mt-0.5">{c.note}</p>}</div>
                  </div>
                ))}
              </div>
              {report.ai_validation.reference_results.length > 0 && (
                <div className="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                  <h3 className="font-semibold text-gray-800">Reference Results</h3>
                  {report.ai_validation.reference_results.map((r: AiReferenceResult, i: number) => (
                    <div key={i} className="flex items-center gap-3 text-sm"><span className={`px-2 py-0.5 rounded text-xs font-medium ${r.status === 'referenced' ? 'bg-green-100 text-green-700' : r.status === 'not_found' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'}`}>{r.status.replace(/_/g,' ')}</span><span className="text-gray-700">{r.reference}</span></div>
                  ))}
                </div>
              )}
              {report.ai_validation.similarity && (
                <div className="bg-white border border-gray-200 rounded-xl p-5"><h3 className="font-semibold text-gray-800 mb-2">Similarity Check</h3><p className="text-sm text-gray-700">{report.ai_validation.similarity.label}: {report.ai_validation.similarity.result}</p><p className="text-xs text-gray-500 mt-1">{report.ai_validation.similarity.note}</p></div>
              )}
            </>
          )}
        </div>
      )}

      {/* Review History */}
      {activeTab === 'review' && (
        <div className="space-y-3">
          {report.review_history.length === 0 ? <div className="bg-white border border-gray-200 rounded-xl p-5 text-gray-500 text-sm">No review history yet.</div> :
            report.review_history.map((h, i) => (
              <div key={i} className="bg-white border border-gray-200 rounded-xl p-5">
                <div className="flex items-center justify-between mb-2">
                  <span className="font-semibold text-gray-800">{h.reviewer}</span>
                  <span className="text-xs text-gray-500">{new Date(h.date).toLocaleString()}</span>
                </div>
                <span className={`px-2 py-0.5 rounded text-xs font-semibold ${h.decision === 'approved' ? 'bg-green-100 text-green-700' : h.decision === 'revision_required' ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700'}`}>{h.decision.replace(/_/g,' ')}</span>
                {h.comments && <p className="text-sm text-gray-700 mt-3">{h.comments}</p>}
              </div>
            ))
          }
        </div>
      )}
    </div>
  )
}
