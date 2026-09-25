import React, { useState } from 'react'

export type ReviewActionType = 'approve' | 'request_revision' | 'reject'

interface ReportReviewActionModalProps {
  isOpen: boolean
  actionType: ReviewActionType
  onClose: () => void
  reportTitle: string
  versionNumber: number
  onConfirm: (data: { reason?: string; comments?: string }) => Promise<void>
}

export const ReportReviewActionModal: React.FC<ReportReviewActionModalProps> = ({
  isOpen,
  actionType,
  onClose,
  reportTitle,
  versionNumber,
  onConfirm,
}) => {
  const [text, setText] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!isOpen) return null

  const isApproval = actionType === 'approve'
  const isRevision = actionType === 'request_revision'

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!isApproval && !text.trim()) {
      setError('A reason / explanation is required for this action.')
      return
    }

    try {
      setSubmitting(true)
      setError(null)
      if (isApproval) {
        await onConfirm({ comments: text.trim() || undefined })
      } else {
        await onConfirm({ reason: text.trim() })
      }
      setText('')
      onClose()
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Failed to submit review action.')
    } finally {
      setSubmitting(false)
    }
  }

  const getHeaderDetails = () => {
    if (isApproval) {
      return {
        bg: 'from-emerald-600 to-emerald-700',
        icon: <span className="text-2xl">✅</span>,
        title: 'Approve Submission',
        subtitle: `Approve v${versionNumber} of "${reportTitle}". This marks the linked task as completed.`,
        btnBg: 'bg-emerald-600 hover:bg-emerald-700',
        btnLabel: 'Confirm Official Approval',
      }
    }
    if (isRevision) {
      return {
        bg: 'from-amber-600 to-amber-700',
        icon: <span className="text-2xl">🔄</span>,
        title: 'Request Revisions',
        subtitle: `Return v${versionNumber} of "${reportTitle}" to author with actionable instructions.`,
        btnBg: 'bg-amber-600 hover:bg-amber-700',
        btnLabel: 'Send Revision Request',
      }
    }
    return {
      bg: 'from-rose-600 to-rose-700',
      icon: <span className="text-2xl">❌</span>,
      title: 'Reject Submission',
      subtitle: `Formally reject "${reportTitle}". This records a terminal non-approval on the audit log.`,
      btnBg: 'bg-rose-600 hover:bg-rose-700',
      btnLabel: 'Confirm Official Rejection',
    }
  }

  const details = getHeaderDetails()

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-xl max-w-lg w-full overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-150">
        <div className={`px-6 py-4 bg-gradient-to-r ${details.bg} text-white flex items-center justify-between`}>
          <div className="flex items-center gap-3">
            {details.icon}
            <div>
              <h3 className="font-bold text-base">{details.title}</h3>
              <p className="text-xs text-white/90 line-clamp-1">{details.subtitle}</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1 rounded-lg text-white/80 hover:text-white hover:bg-white/10"
          >
            <span className="text-lg leading-none">✕</span>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {error && (
            <div className="p-3 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg text-xs flex items-center gap-2">
              <span className="text-base flex-shrink-0">⚠️</span>
              <span>{error}</span>
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
              {isApproval
                ? 'Approval Notes & Commendations (Optional)'
                : isRevision
                ? 'Required Revision Instructions *'
                : 'Official Rejection Reason *'}
            </label>
            <textarea
              value={text}
              onChange={(e) => setText(e.target.value)}
              rows={4}
              required={!isApproval}
              placeholder={
                isApproval
                  ? 'Ex: Deliverables meet departmental standards and CMO requirements...'
                  : isRevision
                  ? 'Specify required amendments, missing documentation, or sections needing work...'
                  : 'Document why this submission fails institutional criteria...'
              }
              className="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>

          <div className="pt-3 border-t border-gray-100 flex items-center justify-end gap-3">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-xs font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting}
              className={`px-4 py-2 text-xs font-medium text-white rounded-lg shadow-sm disabled:opacity-50 transition-colors ${details.btnBg}`}
            >
              {submitting ? 'Processing...' : details.btnLabel}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
