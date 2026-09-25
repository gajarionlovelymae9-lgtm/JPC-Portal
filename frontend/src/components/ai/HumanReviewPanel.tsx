import { useState } from 'react'

interface Props {
  currentStatus: string
  onSubmitDecision: (decision: 'validated' | 'approved' | 'revision_required' | 'rejected', notes: string) => Promise<void>
}

export function HumanReviewPanel({ currentStatus, onSubmitDecision }: Props) {
  const [decision, setDecision] = useState<'validated' | 'revision_required' | 'rejected' | null>(null)
  const [notes, setNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!decision) return

    if ((decision === 'revision_required' || decision === 'rejected') && !notes.trim()) {
      setError('Please provide detailed explanatory comments for revision or rejection.')
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await onSubmitDecision(decision, notes.trim())
      setDecision(null)
      setNotes('')
    } catch (err: unknown) {
      setError((err as Error).message || 'Failed to submit review decision.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 md:p-8 space-y-6">
      <div className="flex items-center gap-3 border-b border-gray-100 pb-5">
        <div className="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
          ✍️
        </div>
        <div>
          <h3 className="font-extrabold text-base md:text-lg text-gray-900">
            Final Human Review & Decision
          </h3>
          <p className="text-xs text-gray-400 font-medium">
            AI recommendations are advisory. Authorized reviewers make the final institutional validation.
          </p>
        </div>
      </div>

      <div className="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 bg-gray-50/70 rounded-2xl border border-gray-100">
        <div>
          <span className="text-xs font-bold text-gray-400 uppercase tracking-wider block">
            Current Report Status
          </span>
          <span className="text-base font-extrabold text-gray-900 capitalize">
            {currentStatus.replace('_', ' ')}
          </span>
        </div>

        <div className="flex items-center gap-2 flex-wrap">
          <button
            onClick={() => { setDecision('validated'); setError(null) }}
            className="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition-all"
          >
            ✓ Validate & Approve
          </button>
          <button
            onClick={() => { setDecision('revision_required'); setError(null) }}
            className="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-xs transition-all"
          >
            🔄 Request Revision
          </button>
          <button
            onClick={() => { setDecision('rejected'); setError(null) }}
            className="px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-xs transition-all"
          >
            ✕ Reject Report
          </button>
        </div>
      </div>

      {/* Decision Modal */}
      {decision && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-xs p-4">
          <div className="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-lg w-full p-6 space-y-4 animate-scale-in">
            <div className="flex items-center justify-between border-b border-gray-100 pb-3">
              <h3 className="font-extrabold text-base text-gray-900 capitalize">
                {decision === 'validated'
                  ? 'Confirm Report Approval'
                  : decision === 'revision_required'
                  ? 'Request Report Revision'
                  : 'Confirm Report Rejection'}
              </h3>
              <button onClick={() => setDecision(null)} className="text-gray-400 hover:text-gray-700 font-bold">
                ✕
              </button>
            </div>

            {error && (
              <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl font-medium">
                {error}
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Reviewer Justification / Feedback Note {decision !== 'validated' && <span className="text-red-500">*</span>}
                </label>
                <textarea
                  rows={4}
                  placeholder="Enter comments for the employee regarding this review decision..."
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500"
                  required={decision !== 'validated'}
                />
              </div>

              <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button
                  type="button"
                  onClick={() => setDecision(null)}
                  className="px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className={`px-6 py-2 text-white font-bold text-xs rounded-xl shadow-xs disabled:opacity-50 ${
                    decision === 'validated'
                      ? 'bg-emerald-600 hover:bg-emerald-700'
                      : decision === 'revision_required'
                      ? 'bg-amber-600 hover:bg-amber-700'
                      : 'bg-red-600 hover:bg-red-700'
                  }`}
                >
                  {submitting ? 'Submitting...' : 'Submit Official Decision'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}

export default HumanReviewPanel
