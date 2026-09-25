import React, { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { reportWorkflowService } from '../../services/reportWorkflowService'
import { getAiValidation, getAiSummary } from '../../services/aiReportService'
import type {
  ReportWorkflowDetail,
  ReportVersionSnapshot,
  ReportTimelineEvent,
  ReportReviewCommentItem,
  ReportWorkflowStatus,
} from '../../types/reportWorkflow'
import { ReportTimelineView } from '../../components/reports/ReportTimelineView'
import { ReviewerCommentSection } from '../../components/reports/ReviewerCommentSection'
import { VersionComparisonModal } from '../../components/reports/VersionComparisonModal'
import { ReportReviewActionModal, type ReviewActionType } from '../../components/reports/ReportReviewActionModal'


const STATUS_BADGES: Record<ReportWorkflowStatus, { bg: string; text: string; label: string }> = {
  draft: { bg: 'bg-gray-100 border-gray-200', text: 'text-gray-700', label: 'Draft' },
  submitted: { bg: 'bg-blue-50 border-blue-200', text: 'text-blue-700', label: 'Submitted' },
  processing: { bg: 'bg-purple-50 border-purple-200', text: 'text-purple-700', label: 'Processing (AI/OCR)' },
  under_review: { bg: 'bg-amber-50 border-amber-200', text: 'text-amber-700', label: 'Under Review' },
  revision_required: { bg: 'bg-orange-50 border-orange-200', text: 'text-orange-700', label: 'Revision Required' },
  resubmitted: { bg: 'bg-indigo-50 border-indigo-200', text: 'text-indigo-700', label: 'Resubmitted' },
  approved: { bg: 'bg-emerald-50 border-emerald-200', text: 'text-emerald-700', label: 'Approved' },
  rejected: { bg: 'bg-rose-50 border-rose-200', text: 'text-rose-700', label: 'Rejected' },
  cancelled: { bg: 'bg-slate-100 border-slate-300', text: 'text-slate-600', label: 'Cancelled' },
  archived: { bg: 'bg-zinc-100 border-zinc-300', text: 'text-zinc-600', label: 'Archived' },
}

export const ReportReviewWorkspacePage: React.FC = () => {
  const { reportId } = useParams<{ reportId: string }>()
  const navigate = useNavigate()

  const [report, setReport] = useState<ReportWorkflowDetail | null>(null)
  const [versions, setVersions] = useState<ReportVersionSnapshot[]>([])
  const [timeline, setTimeline] = useState<ReportTimelineEvent[]>([])
  const [comments, setComments] = useState<ReportReviewCommentItem[]>([])
  const [aiSummary, setAiSummary] = useState<any>(null)
  const [aiValidation, setAiValidation] = useState<any>(null)

  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState<'content' | 'ai_advisory' | 'comments' | 'versions' | 'timeline'>('content')

  // Modals state
  const [comparisonOpen, setComparisonOpen] = useState(false)
  const [reviewActionType, setReviewActionType] = useState<ReviewActionType | null>(null)

  const id = Number(reportId)

  useEffect(() => {
    if (id) {
      loadAllData()
    }
  }, [id])

  const loadAllData = async () => {
    try {
      setLoading(true)
      // Load versions & timeline & comments
      const [vRes, tRes, cRes] = await Promise.all([
        reportWorkflowService.getVersions(id),
        reportWorkflowService.getTimeline(id),
        reportWorkflowService.getComments(id),
      ])

      if (vRes.success && vRes.data) {
        setVersions(vRes.data)
        if (vRes.data.length > 0) {
          const latest = vRes.data[0]
          setReport({
            id: latest.report_id,
            task_id: latest.metadata?.task_id || 1,
            employee_id: latest.submitted_by || 1,
            title: latest.title || 'Report Submission',
            content: latest.content || '',
            references: latest.references || '',
            version: latest.version_number,
            status: (latest.status as ReportWorkflowStatus) || 'under_review',
            is_late: Boolean(latest.metadata?.is_late),
            submitted_at: latest.submitted_at || null,
            approved_at: null,
            rejected_at: null,
            created_at: latest.created_at,
            updated_at: latest.created_at,
            files: latest.files || [],
            employee: latest.submitter
              ? {
                  id: latest.submitter.id,
                  first_name: latest.submitter.name,
                  last_name: '',
                  employee_number: 'EMP-PRO',
                }
              : undefined,
          })
        }
      }

      if (tRes.success && tRes.data) {
        setTimeline(tRes.data)
      }

      if (cRes.success && cRes.data) {
        setComments(cRes.data)
      }

      // Load AI advisory if available
      try {
        const [sumRes, valRes] = await Promise.allSettled([
          getAiSummary(id),
          getAiValidation(id),
        ])
        if (sumRes.status === 'fulfilled' && sumRes.value.data) setAiSummary(sumRes.value.data)
        if (valRes.status === 'fulfilled' && valRes.value.data) setAiValidation(valRes.value.data)
      } catch (e) {
        // AI data is advisory/optional
      }
    } finally {
      setLoading(false)
    }
  }

  const handleAddComment = async (sectionKey: string, commentText: string) => {
    const res = await reportWorkflowService.addComment(id, {
      section_key: sectionKey,
      comment: commentText,
      version_number: report?.version,
    })
    if (res.success && res.data) {
      setComments((prev) => [...prev, res.data])
    }
  }

  const handleResolveComment = async (commentId: number) => {
    const res = await reportWorkflowService.resolveComment(id, commentId)
    if (res.success && res.data) {
      setComments((prev) => prev.map((c) => (c.id === commentId ? res.data : c)))
    }
  }

  const handleReviewAction = async (data: { reason?: string; comments?: string }) => {
    if (!reviewActionType) return

    if (reviewActionType === 'approve') {
      await reportWorkflowService.approveReport(id, data.comments)
    } else if (reviewActionType === 'request_revision') {
      await reportWorkflowService.requestRevision(id, {
        reason: data.reason || 'Revisions required by department head.',
      })
    } else if (reviewActionType === 'reject') {
      await reportWorkflowService.rejectReport(id, data.reason || 'Submission does not meet requirements.')
    }

    setReviewActionType(null)
    await loadAllData()
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="text-center space-y-3">
          <div className="w-10 h-10 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto" />
          <p className="text-sm text-gray-500 font-medium">Loading report review workspace...</p>
        </div>
      </div>
    )
  }

  if (!report) {
    return (
      <div className="p-8 text-center bg-white rounded-xl border border-gray-200 max-w-lg mx-auto mt-12">
        <span className="text-5xl mb-3 block">⚠️</span>
        <h3 className="text-lg font-bold text-gray-900">Report Not Found</h3>
        <p className="text-sm text-gray-500 mt-1 mb-4">The requested report could not be loaded.</p>
        <button
          onClick={() => navigate(-1)}
          className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"
        >
          Go Back
        </button>
      </div>
    )
  }

  const badge = STATUS_BADGES[report.status] || STATUS_BADGES.under_review
  const canReview = ['submitted', 'processing', 'under_review', 'resubmitted'].includes(report.status)

  return (
    <div className="max-w-7xl mx-auto space-y-6 pb-12">
      {/* Top Breadcrumb & Navigation */}
      <div className="flex items-center justify-between">
        <button
          onClick={() => navigate(-1)}
          className="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
        >
          <span>←</span>
          Back to Reports List
        </button>

        {versions.length > 1 && (
          <button
            onClick={() => setComparisonOpen(true)}
            className="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-semibold shadow-sm transition-colors"
          >
            <span>⇄</span>
            Compare Versions ({versions.length} versions)
          </button>
        )}
      </div>

      {/* Main Workspace Header Banner */}
      <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div className="space-y-2 flex-1">
            <div className="flex items-center gap-3 flex-wrap">
              <span className={`px-3 py-1 rounded-full text-xs font-bold border ${badge.bg} ${badge.text}`}>
                {badge.label}
              </span>
              <span className="px-2.5 py-0.5 rounded-md bg-indigo-50 border border-indigo-200 text-indigo-700 text-xs font-bold font-mono">
                Version {report.version}.0
              </span>
              {report.is_late && (
                <span className="px-2.5 py-0.5 rounded-md bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold">
                  LATE SUBMISSION
                </span>
              )}
            </div>

            <h1 className="text-2xl font-black text-gray-900 tracking-tight">{report.title}</h1>

            <div className="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-gray-500">
              <span>Author: <strong className="text-gray-800">{report.employee?.first_name} {report.employee?.last_name}</strong></span>
              <span>•</span>
              <span>Submitted: <strong className="text-gray-800">{report.submitted_at ? new Date(report.submitted_at).toLocaleString() : 'Draft'}</strong></span>
              {report.task && (
                <>
                  <span>•</span>
                  <span>Task: <strong className="text-indigo-600">{report.task.title}</strong></span>
                </>
              )}
            </div>
          </div>

          {/* Action Buttons for Reviewer */}
          {canReview && (
            <div className="flex flex-wrap items-center gap-3 flex-shrink-0">
              <button
                type="button"
                onClick={() => setReviewActionType('approve')}
                className="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all"
              >
                <span>✅</span>
                Approve Report
              </button>
              <button
                type="button"
                onClick={() => setReviewActionType('request_revision')}
                className="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl shadow-sm transition-all"
              >
                <span>🔄</span>
                Request Revisions
              </button>
              <button
                type="button"
                onClick={() => setReviewActionType('reject')}
                className="inline-flex items-center gap-2 px-3 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold rounded-xl transition-all"
              >
                <span>❌</span>
                Reject
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Tabs Navigation */}
      <div className="flex items-center gap-2 border-b border-gray-200 bg-white px-6 rounded-t-xl">
        <button
          onClick={() => setActiveTab('content')}
          className={`py-3.5 px-4 text-xs font-bold border-b-2 flex items-center gap-2 transition-colors ${
            activeTab === 'content'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-gray-500 hover:text-gray-800'
          }`}
        >
          <span>📄</span>
          Report Document &amp; Evidence
        </button>

        <button
          onClick={() => setActiveTab('ai_advisory')}
          className={`py-3.5 px-4 text-xs font-bold border-b-2 flex items-center gap-2 transition-colors ${
            activeTab === 'ai_advisory'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-gray-500 hover:text-gray-800'
          }`}
        >
          <span>✨</span>
          AI Advisory &amp; Validation
          <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-medium">
            Advisory
          </span>
        </button>

        <button
          onClick={() => setActiveTab('comments')}
          className={`py-3.5 px-4 text-xs font-bold border-b-2 flex items-center gap-2 transition-colors ${
            activeTab === 'comments'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-gray-500 hover:text-gray-800'
          }`}
        >
          <span>💬</span>
          Review Comments ({comments.length})
        </button>

        <button
          onClick={() => setActiveTab('versions')}
          className={`py-3.5 px-4 text-xs font-bold border-b-2 flex items-center gap-2 transition-colors ${
            activeTab === 'versions'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-gray-500 hover:text-gray-800'
          }`}
        >
          <span>🔄</span>
          Version History ({versions.length})
        </button>

        <button
          onClick={() => setActiveTab('timeline')}
          className={`py-3.5 px-4 text-xs font-bold border-b-2 flex items-center gap-2 transition-colors ${
            activeTab === 'timeline'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-gray-500 hover:text-gray-800'
          }`}
        >
          <span>🕐</span>
          Audit Timeline ({timeline.length})
        </button>
      </div>

      {/* Tab Panels */}
      {activeTab === 'content' && (
        <div className="space-y-6">
          {/* Main Document Content */}
          <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-100">
              Submitted Report Content
            </h3>
            <div className="prose max-w-none text-sm font-mono text-gray-800 whitespace-pre-wrap leading-relaxed bg-gray-50 p-6 rounded-xl border border-gray-200">
              {report.content || <span className="text-gray-400 italic">No direct text provided.</span>}
            </div>

            {report.references && (
              <div className="mt-6 pt-4 border-t border-gray-100">
                <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                  Institutional References & Anchors
                </h4>
                <div className="p-4 bg-indigo-50/50 rounded-xl border border-indigo-100 text-xs text-indigo-950 whitespace-pre-wrap">
                  {report.references}
                </div>
              </div>
            )}

            {/* Attached Files */}
            {report.files && report.files.length > 0 && (
              <div className="mt-6 pt-4 border-t border-gray-100">
                <h4 className="text-xs font-bold text-gray-700 uppercase tracking-wider mb-3">
                  Attached Evidentiary Files
                </h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {report.files.map((file) => (
                    <div
                      key={file.id}
                      className="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-200 hover:border-indigo-300 transition-colors"
                    >
                      <div className="flex items-center gap-2 overflow-hidden">
                        <span className="text-indigo-500 flex-shrink-0">📎</span>
                        <span className="text-xs font-medium text-gray-800 truncate">{file.file_name}</span>
                      </div>
                      <span className="text-[11px] text-gray-400 font-mono flex-shrink-0">
                        {Math.round(file.file_size / 1024)} KB
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {activeTab === 'ai_advisory' && (
        <div className="space-y-6">
          {/* Advisory Notice Banner */}
          <div className="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start gap-3">
            <span className="text-amber-600 flex-shrink-0 mt-0.5 text-lg">🛡️</span>
            <div className="text-xs space-y-1">
              <p className="font-bold">AI-Assisted Document Validation (Strictly Advisory)</p>
              <p className="text-amber-800">
                All summaries, requirement matches, and similarity indicators are generated algorithmically to assist human review. Final validation and authorization remain strictly the responsibility of authorized reviewers.
              </p>
            </div>
          </div>

          {/* AI Executive Summary */}
          {aiSummary && (
            <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
              <div className="flex items-center gap-2">
                <span className="text-indigo-600 text-lg">✨</span>
                <h3 className="text-base font-bold text-gray-900">Grounded NLP Executive Summary</h3>
              </div>
              <p className="text-sm text-gray-700 leading-relaxed bg-gray-50 p-4 rounded-xl border border-gray-100">
                {aiSummary.summary_text || aiSummary.executive_summary || 'Summary compiled from submission.'}
              </p>
            </div>
          )}

          {/* AI Validation Checks */}
          {aiValidation && (
            <div className="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
              <h3 className="text-base font-bold text-gray-900">Task Requirement Alignment Checks</h3>
              <div className="space-y-2">
                {aiValidation.items?.map((item: any, idx: number) => (
                  <div key={idx} className="p-3 rounded-lg border border-gray-100 bg-gray-50 flex items-start justify-between gap-3">
                    <div className="space-y-0.5">
                      <p className="text-xs font-semibold text-gray-800">{item.requirement_text || item.label || 'Requirement'}</p>
                      {item.evidence && <p className="text-[11px] text-gray-500">Evidence: {item.evidence}</p>}
                    </div>
                    <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">
                      {item.status || 'Verified'}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}

      {activeTab === 'comments' && (
        <ReviewerCommentSection
          comments={comments}
          canAddComment={true}
          canResolveComment={true}
          onAddComment={handleAddComment}
          onResolveComment={handleResolveComment}
        />
      )}

      {activeTab === 'versions' && (
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
          <div className="flex items-center justify-between pb-4 border-b border-gray-100">
            <h3 className="text-base font-bold text-gray-900">Immutable Version Snapshots</h3>
            {versions.length > 1 && (
              <button
                onClick={() => setComparisonOpen(true)}
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
                className="p-4 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-between hover:bg-white hover:border-indigo-200 transition-all"
              >
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-bold font-mono text-xs">
                      v{v.version_number}.0
                    </span>
                    <span className="text-xs font-bold text-gray-900">{v.title}</span>
                    <span className="text-xs text-gray-500">
                      by {v.submitter?.name || 'Author'}
                    </span>
                  </div>
                  {v.change_summary && (
                    <p className="text-xs text-gray-600">
                      <strong>Revision Changes:</strong> {v.change_summary}
                    </p>
                  )}
                  {v.notes && <p className="text-xs text-gray-500 italic">{v.notes}</p>}
                </div>

                <div className="text-right text-xs text-gray-400">
                  {v.submitted_at ? new Date(v.submitted_at).toLocaleString() : 'Draft'}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {activeTab === 'timeline' && (
        <div className="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
          <h3 className="text-base font-bold text-gray-900 mb-6 pb-4 border-b border-gray-100">
            Immutable Audit Trail & Lifecycle Events
          </h3>
          <ReportTimelineView timeline={timeline} />
        </div>
      )}

      {/* Version Comparison Modal */}
      {comparisonOpen && (
        <VersionComparisonModal
          isOpen={comparisonOpen}
          onClose={() => setComparisonOpen(false)}
          reportId={id}
          availableVersions={versions}
        />
      )}

      {/* Review Action Modal (Approve, Request Revision, Reject) */}
      {reviewActionType && (
        <ReportReviewActionModal
          isOpen={Boolean(reviewActionType)}
          actionType={reviewActionType}
          onClose={() => setReviewActionType(null)}
          reportTitle={report.title}
          versionNumber={report.version}
          onConfirm={handleReviewAction}
        />
      )}
    </div>
  )
}
