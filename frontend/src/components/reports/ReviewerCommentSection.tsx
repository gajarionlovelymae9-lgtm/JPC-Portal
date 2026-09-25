import React, { useState } from 'react'
import type { ReportReviewCommentItem } from '../../types/reportWorkflow'

interface ReviewerCommentSectionProps {
  comments: ReportReviewCommentItem[]
  canAddComment?: boolean
  canResolveComment?: boolean
  onAddComment?: (sectionKey: string, comment: string) => Promise<void>
  onResolveComment?: (commentId: number) => Promise<void>
}

export const ReviewerCommentSection: React.FC<ReviewerCommentSectionProps> = ({
  comments,
  canAddComment = false,
  canResolveComment = false,
  onAddComment,
  onResolveComment,
}) => {
  const [sectionKey, setSectionKey] = useState('general')
  const [newComment, setNewComment] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [resolvingId, setResolvingId] = useState<number | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!newComment.trim() || !onAddComment) return

    try {
      setSubmitting(true)
      await onAddComment(sectionKey, newComment)
      setNewComment('')
    } finally {
      setSubmitting(false)
    }
  }

  const handleResolve = async (commentId: number) => {
    if (!onResolveComment) return
    try {
      setResolvingId(commentId)
      await onResolveComment(commentId)
    } finally {
      setResolvingId(null)
    }
  }

  const sections = [
    { value: 'general', label: 'General / Overall' },
    { value: 'title', label: 'Title & Metadata' },
    { value: 'executive_summary', label: 'Executive Summary' },
    { value: 'content', label: 'Main Content / Deliverables' },
    { value: 'references', label: 'References & Citations' },
    { value: 'formatting', label: 'Formatting / Evidence' },
  ]

  return (
    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <div className="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
        <div className="flex items-center gap-2">
          <span className="text-xl">💬</span>
          <h3 className="text-base font-semibold text-gray-900">Review Feedback & Inline Comments</h3>
        </div>
        <span className="text-xs px-2.5 py-1 rounded-full font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
          {comments.filter(c => c.status === 'open').length} Open / {comments.length} Total
        </span>
      </div>

      {/* Comment List */}
      {comments.length === 0 ? (
        <div className="text-center py-8 text-gray-500 bg-gray-50 rounded-lg border border-dashed border-gray-200">
          <div className="text-2xl mb-2">💬</div>
          <p className="text-sm">No review comments on this report yet.</p>
        </div>
      ) : (
        <div className="space-y-4 mb-6">
          {comments.map((comment) => (
            <div
              key={comment.id}
              className={`p-4 rounded-lg border transition-all ${
                comment.status === 'resolved'
                  ? 'bg-gray-50 border-gray-200 opacity-80'
                  : 'bg-white border-amber-200 shadow-sm'
              }`}
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-2 flex-wrap mb-1">
                    <span className="font-semibold text-sm text-gray-900">
                      {comment.reviewer?.name || 'Reviewer'}
                    </span>
                    <span className="flex items-center gap-1 text-[11px] px-2 py-0.5 rounded font-medium bg-gray-100 text-gray-700">
                      🏷️ {sections.find(s => s.value === comment.section_key)?.label || comment.section_key}
                    </span>
                    <span
                      className={`text-[11px] font-semibold px-2 py-0.5 rounded-full ${
                        comment.status === 'resolved'
                          ? 'bg-emerald-100 text-emerald-800'
                          : 'bg-amber-100 text-amber-800'
                      }`}
                    >
                      {comment.status === 'resolved' ? 'Resolved' : 'Action Required'}
                    </span>
                    <span className="text-xs text-gray-400">
                      {new Date(comment.created_at).toLocaleDateString()}
                    </span>
                  </div>

                  <p className="text-sm text-gray-800 whitespace-pre-wrap mt-2">{comment.comment}</p>

                  {comment.status === 'resolved' && comment.resolved_at && (
                    <div className="mt-2 text-xs text-emerald-700 flex items-center gap-1">
                      <span>✓</span>
                      Resolved by {comment.resolver?.name || 'Author'} on{' '}
                      {new Date(comment.resolved_at).toLocaleDateString()}
                    </div>
                  )}
                </div>

                {comment.status === 'open' && canResolveComment && (
                  <button
                    type="button"
                    onClick={() => handleResolve(comment.id)}
                    disabled={resolvingId === comment.id}
                    className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-md font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 transition-colors disabled:opacity-50"
                  >
                    <span>✓</span>
                    {resolvingId === comment.id ? 'Resolving...' : 'Mark Resolved'}
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Add Comment Form for Reviewers */}
      {canAddComment && onAddComment && (
        <form onSubmit={handleSubmit} className="mt-6 pt-4 border-t border-gray-200">
          <h4 className="text-sm font-semibold text-gray-900 mb-2">Add Review Feedback</h4>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
            <div className="md:col-span-1">
              <label className="block text-xs font-medium text-gray-700 mb-1">Target Section</label>
              <select
                value={sectionKey}
                onChange={(e) => setSectionKey(e.target.value)}
                className="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                {sections.map((s) => (
                  <option key={s.value} value={s.value}>
                    {s.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="md:col-span-3">
              <label className="block text-xs font-medium text-gray-700 mb-1">Reviewer Note / Revision Requirement</label>
              <div className="relative">
                <textarea
                  value={newComment}
                  onChange={(e) => setNewComment(e.target.value)}
                  placeholder="Specify feedback, corrections needed, or missing details..."
                  rows={3}
                  required
                  className="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pr-24"
                />
                <button
                  type="submit"
                  disabled={submitting || !newComment.trim()}
                  className="absolute bottom-3 right-3 inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg shadow-sm disabled:opacity-50 transition-colors"
                >
                  {submitting ? 'Posting...' : 'Post Note'}
                </button>
              </div>
            </div>
          </div>
        </form>
      )}
    </div>
  )
}
