import React, { useState } from 'react'

interface Props {
  isOpen: boolean
  onClose: () => void
  onResolve: (notes: string) => Promise<void>
}

export const DataQualityResolutionModal: React.FC<Props> = ({ isOpen, onClose, onResolve }) => {
  const [notes, setNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!isOpen) return null

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!notes.trim()) {
      setError('Please document the corrective action taken before resolving.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onResolve(notes)
      setNotes('')
      onClose()
    } catch (err: any) {
      setError(err?.message || 'Failed to resolve issue.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
      <div className="bg-white dark:bg-slate-800 rounded-xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-700">
        <h3 className="text-lg font-bold text-slate-900 dark:text-white mb-2">
          Resolve Data Quality Issue
        </h3>
        <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">
          Describe the corrective action performed in the primary module (e.g. updated missing profile field, corrected task dates).
        </p>

        {error && (
          <div className="mb-4 p-2.5 bg-red-50 dark:bg-red-950/50 border border-red-200 text-red-700 dark:text-red-300 text-xs rounded-lg">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Corrective Action Notes (Required)
            </label>
            <textarea
              rows={4}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="e.g. Verified employee records and corrected position ID; re-aligned task reporting dates..."
              className="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500"
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
              className="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 rounded-lg transition-colors"
            >
              {submitting ? 'Resolving...' : 'Confirm Resolution'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
