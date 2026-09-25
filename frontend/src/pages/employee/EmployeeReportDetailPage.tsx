import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getMyReport } from '../../services/employeePortalService'
import { reportWorkflowService } from '../../services/reportWorkflowService'
import type { EmployeeReport, AiRequirementCheck } from '../../services/employeePortalService'
import type { ReportTimelineEvent, ReportReviewCommentItem, ReportVersionSnapshot } from '../../types/reportWorkflow'
import { ReportTimelineView } from '../../components/reports/ReportTimelineView'
import { ReviewerCommentSection } from '../../components/reports/ReviewerCommentSection'
import { RevisionEditorModal } from '../../components/reports/RevisionEditorModal'
import { VersionComparisonModal } from '../../components/reports/VersionComparisonModal'
import { PATHS } from '../../routes/paths'

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-700 border-gray-200',
  DRAFT: 'bg-gray-100 text-gray-700 border-gray-200',
  submitted: 'bg-blue-100 text-blue-700 border-blue-200',
  SUBMITTED: 'bg-blue-100 text-blue-700 border-blue-200',
  processing: 'bg-purple-100 text-purple-700 border-purple-200',
  under_review: 'bg-amber-100 text-amber-700 border-amber-200',
  UNDER_REVIEW: 'bg-amber-100 text-amber-700 border-amber-200',
  revision_required: 'bg-orange-100 text-orange-700 border-orange-200',
  REVISION_REQUIRED: 'bg-orange-100 text-orange-700 border-orange-200',
  resubmitted: 'bg-indigo-100 text-indigo-700 border-indigo-200',
  approved: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  APPROVED: 'bg-emerald-100 text-emerald-700 border-emerald-200',
  rejected: 'bg-rose-100 text-rose-700 border-rose-200',
  REJECTED: 'bg-rose-100 text-rose-700 border-rose-200',
}

function CheckIcon({ status }: { status: string }) {
  if (status === 'found') return <span className="text-green-600 font-bold">✓</span>
  if (status === 'missing') return <span className="text-red-500 font-bold">✗</span>
  return <span className="text-yellow-500 font-bold">⚠</span>
}

export default function EmployeeReportDetailPage() {
  const { reportId } = useParams()
  const navigate = useNavigate()
  const [report, setReport] = useState<EmployeeReport | null>(null)
  const [timeline, setTimeline] = useState<ReportTimelineEvent[]>([])
  const [comments, setComments] = useState<ReportReviewCommentItem[]>([])
  const [versions, setVersions] = useState<ReportVersionSnapshot[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [activeTab, setActiveTab] = useState<'overview' | 'ocr' | 'ai_summary' | 'ai_validation' | 'comments' | 'versions' | 'timeline'>('overview')

  // Modals
  const [revisionModalOpen, setRevisionModalOpen] = useState(false)
  const [comparisonModalOpen, setComparisonModalOpen] = useState(false)

  const numReportId = Number(reportId)

  useEffect(() => {
    if (!reportId) return
    loadReportData()
  }, [reportId])

  const loadReportData = async () => {
    setLoading(true)
    try {
      const [repRes, timeRes, comRes, verRes] = await Promise.allSettled([
        getMyReport(reportId!),
        reportWorkflowService.getTimeline(numReportId),
        reportWorkflowService.getComments(numReportId),
        reportWorkflowService.getVersions(numReportId),
      ])

      if (repRes.status === 'fulfilled' && repRes.value.data) {
        setReport(repRes.value.data)
      } else {
        setError('Unable to load report.')
      }

      if (timeRes.status === 'fulfilled' && timeRes.value.data) {
        setTimeline(timeRes.value.data)
      }

      if (comRes.status === 'fulfilled' && comRes.value.data) {
        setComments(comRes.value.data)
      }

      if (verRes.status === 'fulfilled' && verRes.value.data) {
        setVersions(verRes.value.data)
      }
    } finally {
      setLoading(false)
    }
  }

  const handleResolveComment = async (commentId: number) => {
    const res = await reportWorkflowService.resolveComment(numReportId, commentId)
    if (res.success && res.data) {
      setComments((prev) => prev.map((c) => (c.id === commentId ? res.data : c)))
    }
  }

  const handleSubmitRevision = async (formData: FormData) => {
    await reportWorkflowService.submitRevision(numReportId, formData)
    await loadReportData()
  }

  if (loading) {
    return (
      <div className="p-6 space-y-3 max-w-5xl mx-auto">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="h-12 bg-gray-100 rounded-xl animate-pulse" />
        ))}
      </div>
    )
  }

  if (error || !report) {
    return (
      <div className="p-6 max-w-5xl mx-auto">
        <div className="p-6 bg-red-50 text-red-700 rounded-xl border border-red-200">
          {error ?? 'Report not found.'}
        </div>
      </div>
    )
  }

  const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'ai_summary', label: 'AI Summary' },
    { key: 'ai_validation', label: 'AI Validation' },
    { key: 'ocr', label: 'OCR Extraction' },
    { key: 'comments', label: `Feedback (${comments.length})` },
    { key: 'versions', label: `Versions (${versions.length || report.version_count})` },
    { key: 'timeline', label: `Audit Timeline (${timeline.length})` },
  ] as const

  const isRevisionRequired =
    report.status === 'REVISION_REQUIRED' || report.status === 'revision_required'

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      {/* Top Breadcrumb & Actions */}
      <div className="flex items-center justify-between">
        <button
          onClick={() => navigate(PATHS.employeeReports)}
          className="inline-flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors"
        >
          <span>←</span>
          Back to My Reports
        </button>

        {versions.length > 1 && (
          <button
            onClick={() => setComparisonModalOpen(true)}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors"
          >
            <span>⇄</span>
            Compare Versions ({versions.length})
          </button>
        )}
      </div>

      {/* Header Info */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
          <div className="flex-1 space-y-1">
            <div className="flex items-center gap-2 flex-wrap">
              <span
                className={`px-3 py-1 rounded-full text-xs font-bold border ${
                  STATUS_COLORS[report.status] ?? 'bg-gray-100 text-gray-700'
                }`}
              >
                {report.status.replace(/_/g, ' ').toUpperCase()}
              </span>
              <span className="font-mono text-xs font-bold bg-indigo-50 border border-indigo-200 text-indigo-700 px-2.5 py-0.5 rounded-md">
                Version {report.version}.0
              </span>
              {report.is_late && (
                <span className="text-xs px-2.5 py-0.5 rounded-md font-bold bg-rose-50 border border-rose-200 text-rose-700">
                  LATE SUBMISSION
                </span>
              )}
            </div>

            <h1 className="text-2xl font-black text-gray-900 tracking-tight">{report.title}</h1>
            <p className="text-xs text-gray-500">
              Task: <strong className="text-gray-800">{report.task_title}</strong> • Deadline:{' '}
              {report.deadline ?? '—'}
            </p>
          </div>

          {isRevisionRequired && (
            <button
              onClick={() => setRevisionModalOpen(true)}
              className="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all"
            >
              <span>🔄</span>
              Submit Revision (v{report.version + 1})
            </button>
          )}
        </div>

        {/* Revision Required Alert Banner */}
        {isRevisionRequired && (
          <div className="mt-4 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-start justify-between gap-4">
            <div className="space-y-1">
              <p className="font-bold text-xs text-amber-900 uppercase tracking-wider">
                Action Required: Reviewer Requested Revisions
              </p>
              <p className="text-xs text-amber-800">
                {report.review_history?.at(-1)?.comments ||
                  'Please address reviewer feedback and submit a revised version (v' +
                    (report.version + 1) +
                    ').'}
              </p>
            </div>
            <button
              onClick={() => setRevisionModalOpen(true)}
              className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold flex-shrink-0"
            >
              Revise Now
            </button>
          </div>
        )}
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-gray-200 bg-white px-4 rounded-t-xl overflow-x-auto">
        {tabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-3 text-xs font-bold border-b-2 whitespace-nowrap transition-colors ${
              activeTab === tab.key
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-800'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Tab Panels */}
      {activeTab === 'overview' && (
        <div className="space-y-4">
          <div className="bg-white border border-gray-200 rounded-2xl p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div>
              <span className="text-gray-400 block font-medium">Submitted Date</span>
              <p className="font-bold text-gray-800 mt-0.5">
                {report.submitted_at ? new Date(report.submitted_at).toLocaleString() : '—'}
              </p>
            </div>
            <div>
              <span className="text-gray-400 block font-medium">Task Deadline</span>
              <p className="font-bold text-gray-800 mt-0.5">{report.deadline ?? '—'}</p>
            </div>
            <div>
              <span className="text-gray-400 block font-medium">Approval Status</span>
              <p className="font-bold text-gray-800 mt-0.5">
                {report.approved_at ? new Date(report.approved_at).toLocaleString() : 'Pending / Review'}
              </p>
            </div>
            <div>
              <span className="text-gray-400 block font-medium">Total Versions</span>
              <p className="font-bold text-gray-800 mt-0.5">{report.version_count} version(s)</p>
            </div>
          </div>

          {report.content && (
            <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
              <h3 className="font-bold text-gray-900 text-sm mb-3 uppercase tracking-wider">
                Submitted Report Content
              </h3>
              <div className="text-sm font-mono text-gray-800 whitespace-pre-wrap leading-relaxed bg-gray-50 p-5 rounded-xl border border-gray-100">
                {report.content}
              </div>
            </div>
          )}

          {report.references && (
            <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
              <h3 className="font-bold text-gray-900 text-sm mb-2 uppercase tracking-wider">
                References & Institutional Citations
              </h3>
              <div className="text-xs text-gray-700 whitespace-pre-wrap bg-gray-50 p-4 rounded-xl border border-gray-100">
                {report.references}
              </div>
            </div>
          )}
        </div>
      )}

      {activeTab === 'ai_summary' && (
        <div className="bg-white border border-gray-200 rounded-2xl p-6 space-y-4 shadow-sm">
          {!report.ai_summary ? (
            <p className="text-gray-500 text-sm">AI summary not yet generated.</p>
          ) : (
            <>
              <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-900 text-xs flex items-center gap-2">
                <span className="text-amber-600 flex-shrink-0">🛡️</span>
                <span>{report.ai_summary.disclaimer}</span>
              </div>
              {report.ai_summary.executive_summary && (
                <div>
                  <h3 className="font-bold text-gray-900 text-sm mb-2">Executive Summary</h3>
                  <p className="text-sm text-gray-700 bg-gray-50 p-4 rounded-xl border border-gray-100 leading-relaxed">
                    {report.ai_summary.executive_summary}
                  </p>
                </div>
              )}
              {report.ai_summary.key_points && report.ai_summary.key_points.length > 0 && (
                <div>
                  <h3 className="font-bold text-gray-900 text-sm mb-2">Key Accomplishments</h3>
                  <ul className="list-disc list-inside space-y-1 text-sm text-gray-700 bg-gray-50 p-4 rounded-xl border border-gray-100">
                    {report.ai_summary.key_points.map((p, i) => (
                      <li key={i}>{p}</li>
                    ))}
                  </ul>
                </div>
              )}
            </>
          )}
        </div>
      )}

      {activeTab === 'ai_validation' && (
        <div className="space-y-4">
          {!report.ai_validation ? (
            <div className="bg-white border border-gray-200 rounded-2xl p-6 text-gray-500 text-sm">
              AI validation not yet available.
            </div>
          ) : (
            <>
              <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-900 text-xs flex items-center gap-2">
                <span className="text-amber-600 flex-shrink-0">🛡️</span>
                <span>
                  {report.advisory_label} — {report.disclaimer}
                </span>
              </div>
              <div className="bg-white border border-gray-200 rounded-2xl p-6 space-y-3 shadow-sm">
                <h3 className="font-bold text-gray-900 text-sm uppercase tracking-wider">
                  Requirement Alignment
                </h3>
                {report.ai_validation.requirement_checks?.map((c: AiRequirementCheck, i: number) => (
                  <div
                    key={i}
                    className="flex items-start gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100 text-sm"
                  >
                    <CheckIcon status={c.status} />
                    <div className="flex-1">
                      <p className="text-gray-800 font-medium">{c.requirement}</p>
                      {c.note && <p className="text-gray-500 text-xs mt-0.5">{c.note}</p>}
                    </div>
                  </div>
                ))}
              </div>
            </>
          )}
        </div>
      )}

      {activeTab === 'ocr' && (
        <div className="bg-white border border-gray-200 rounded-2xl p-6 space-y-4 shadow-sm">
          {!report.ocr ? (
            <p className="text-gray-500 text-sm">No OCR data available.</p>
          ) : (
            <>
              <div className="flex items-center gap-3">
                <span className="text-xs font-bold text-gray-700 uppercase">Confidence:</span>
                <span className="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-md text-xs font-bold">
                  {report.ocr.confidence_label} ({report.ocr.confidence}%)
                </span>
              </div>
              {report.ocr.extracted_text && (
                <div>
                  <h3 className="font-bold text-gray-900 text-xs uppercase tracking-wider mb-2">
                    Extracted Text
                  </h3>
                  <div className="bg-gray-50 rounded-xl p-4 text-xs font-mono whitespace-pre-wrap text-gray-700 max-h-96 overflow-y-auto border border-gray-200">
                    {report.ocr.extracted_text}
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      )}

      {activeTab === 'comments' && (
        <ReviewerCommentSection
          comments={comments}
          canAddComment={false}
          canResolveComment={true}
          onResolveComment={handleResolveComment}
        />
      )}

      {activeTab === 'versions' && (
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 className="font-bold text-gray-900 text-sm uppercase tracking-wider">
              Immutable Version Snapshots
            </h3>
            {versions.length > 1 && (
              <button
                onClick={() => setComparisonModalOpen(true)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors"
              >
                <span>⇄</span>
                Compare Versions
              </button>
            )}
          </div>

          <div className="space-y-3">
            {versions.map((v) => (
              <div
                key={v.id}
                className="p-4 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-between"
              >
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-bold font-mono text-xs">
                      v{v.version_number}.0
                    </span>
                    <span className="text-xs font-bold text-gray-900">{v.title}</span>
                  </div>
                  {v.change_summary && (
                    <p className="text-xs text-gray-600">
                      <strong>Changes:</strong> {v.change_summary}
                    </p>
                  )}
                </div>
                <span className="text-xs text-gray-400">
                  {v.submitted_at ? new Date(v.submitted_at).toLocaleDateString() : 'Draft'}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {activeTab === 'timeline' && (
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
          <h3 className="font-bold text-gray-900 text-sm uppercase tracking-wider mb-6 pb-3 border-b border-gray-100">
            Report Lifecycle Audit Trail
          </h3>
          <ReportTimelineView timeline={timeline} />
        </div>
      )}

      {/* Revision Editor Modal */}
      {revisionModalOpen && (
        <RevisionEditorModal
          isOpen={revisionModalOpen}
          onClose={() => setRevisionModalOpen(false)}
          currentVersion={report.version}
          initialTitle={report.title}
          initialContent={report.content || ''}
          initialReferences={report.references || ''}
          onSubmitRevision={handleSubmitRevision}
        />
      )}

      {/* Version Comparison Modal */}
      {comparisonModalOpen && (
        <VersionComparisonModal
          isOpen={comparisonModalOpen}
          onClose={() => setComparisonModalOpen(false)}
          reportId={numReportId}
          availableVersions={versions}
        />
      )}
    </div>
  )
}
