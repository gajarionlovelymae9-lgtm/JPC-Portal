import React, { useState } from 'react'

interface Props {
  isOpen: boolean
  title: string
  actionLabel: string
  isDismiss?: boolean
  onClose: () => void
  onSubmit: (notes: string) => Promise<void>
}

export const DataQualityReviewModal: React.FC<Props> = ({
  isOpen,
  title,
  actionLabel,
  isDismiss = false,
  onClose,
  onSubmit,
}) => {
  const [notes, setNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!isOpen) return null

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (isDismiss && !notes.trim()) {
      setError('A dismissal explanation reason is required.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmit(notes)
      setNotes('')
      onClose()
    } catch (err: any) {
      setError(err?.message || 'Failed to submit review action.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
      <div className="bg-white dark:bg-slate-800 rounded-xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-700">
        <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">{title}</h3>
        <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
          All reviewer actions are logged in the immutable audit history.
        </p>

        {error && (
          <div className="mb-4 p-2.5 bg-red-50 dark:bg-red-950/50 border border-red-200 text-red-700 dark:text-red-300 text-xs rounded-lg">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              {isDismiss ? 'Dismissal Reason / Explanation (Required)' : 'Review Notes (Optional)'}
            </label>
            <textarea
              rows={3}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder={isDismiss ? 'Explain why this finding is a false positive...' : 'Add notes regarding this finding...'}
              className="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div className="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg transition-colors"
            >
              {submitting ? 'Submitting...' : actionLabel}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
