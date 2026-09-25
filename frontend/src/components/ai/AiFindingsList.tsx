import { useState } from 'react'
import type { AiFindingData } from '../../services/aiReportService'

interface Props {
  findings: AiFindingData[]
  loading?: boolean
  canReview?: boolean
  onReviewFinding?: (findingId: number, action: 'accept' | 'dismiss' | 'modify', notes?: string) => Promise<void>
}

export function AiFindingsList({ findings, loading, canReview, onReviewFinding }: Props) {
  const [actingId, setActingId] = useState<number | null>(null)
  const [modalFinding, setModalFinding] = useState<AiFindingData | null>(null)
  const [modalAction, setModalAction] = useState<'accept' | 'dismiss' | 'modify'>('accept')
  const [notes, setNotes] = useState('')

  if (loading) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm animate-pulse space-y-4">
        <div className="h-6 bg-gray-200 rounded w-1/3"></div>
        <div className="h-24 bg-gray-100 rounded-2xl"></div>
        <div className="h-24 bg-gray-100 rounded-2xl"></div>
      </div>
    )
  }

  const openActionModal = (finding: AiFindingData, action: 'accept' | 'dismiss' | 'modify') => {
    setModalFinding(finding)
    setModalAction(action)
    setNotes(finding.reviewer_notes ?? '')
  }

  const handleConfirmAction = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!modalFinding || !onReviewFinding) return

    setActingId(modalFinding.id)
    try {
      await onReviewFinding(modalFinding.id, modalAction, notes)
      setModalFinding(null)
    } finally {
      setActingId(null)
    }
  }

  return (
    <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 md:p-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
            ⚠️
          </div>
          <div>
            <h3 className="font-extrabold text-base md:text-lg text-gray-900">
              Structured AI Findings
            </h3>
            <span className="text-xs text-gray-400 font-medium">
              Grounded Observations for Human Verification
            </span>
          </div>
        </div>

        <span className="text-xs font-bold text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">
          {findings.length} findings identified
        </span>
      </div>

      {findings.length === 0 ? (
        <div className="p-8 text-center bg-gray-50/50 rounded-2xl space-y-2">
          <span className="text-3xl">✨</span>
          <h4 className="font-bold text-sm text-gray-900">No Critical Findings</h4>
          <p className="text-xs text-gray-400">
            No compliance or quality issues were flagged by the AI engine.
          </p>
        </div>
      ) : (
        <div className="space-y-4">
          {findings.map((f) => {
            const isCrit = f.severity === 'critical'
            const isHigh = f.severity === 'high'
            const isDismissed = f.status === 'dismissed'
            const isConfirmed = f.status === 'confirmed'

            const borderClass = isDismissed
              ? 'border-gray-100 bg-gray-50/40 opacity-70'
              : isCrit
              ? 'border-red-200 bg-red-50/30'
              : isHigh
              ? 'border-amber-200 bg-amber-50/30'
              : 'border-gray-100 bg-white'

            return (
              <div
                key={f.id}
                className={`p-5 rounded-2xl border transition-all space-y-3 ${borderClass}`}
              >
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span
                      className={`text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full ${
                        isCrit
                          ? 'bg-red-100 text-red-700'
                          : isHigh
                          ? 'bg-amber-100 text-amber-700'
                          : 'bg-blue-100 text-blue-700'
                      }`}
                    >
                      {f.severity}
                    </span>

                    <span className="text-[10px] uppercase font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                      {f.finding_type.replace('_', ' ')}
                    </span>

                    <span
                      className={`text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full ${
                        isConfirmed
                          ? 'bg-emerald-100 text-emerald-800'
                          : isDismissed
                          ? 'bg-gray-200 text-gray-600'
                          : 'bg-blue-100 text-blue-800'
                      }`}
                    >
                      Status: {f.status}
                    </span>
                  </div>

                  {f.confidence_score && (
                    <span className="text-[11px] font-bold text-gray-400 font-mono">
                      Confidence: {(f.confidence_score * 100).toFixed(0)}%
                    </span>
                  )}
                </div>

                <div>
                  <h4 className="font-extrabold text-sm text-gray-900 mb-1">{f.title}</h4>
                  <p className="text-xs text-gray-600 leading-relaxed">{f.description}</p>
                </div>

                {f.evidence && (
                  <div className="p-3 bg-gray-50 rounded-xl border border-gray-100 font-mono text-[11px] text-gray-800">
                    <strong className="block text-gray-400 font-sans text-[10px] uppercase mb-1">
                      Evidence:
                    </strong>
                    {f.evidence}
                  </div>
                )}

                {/* Reviewer Footnote */}
                {f.reviewer && (
                  <div className="pt-2 border-t border-gray-100 text-[11px] text-gray-500 flex items-center justify-between">
                    <span>
                      Reviewed by: <strong>{f.reviewer.name}</strong> ({f.reviewer_action})
                    </span>
                    {f.reviewer_notes && <em>"{f.reviewer_notes}"</em>}
                  </div>
                )}

                {/* Reviewer Action Buttons */}
                {canReview && onReviewFinding && (
                  <div className="flex items-center justify-end gap-2 pt-2">
                    <button
                      onClick={() => openActionModal(f, 'accept')}
                      disabled={actingId === f.id}
                      className="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold text-xs rounded-xl transition-colors"
                    >
                      ✓ Confirm Finding
                    </button>
                    <button
                      onClick={() => openActionModal(f, 'dismiss')}
                      disabled={actingId === f.id}
                      className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-xl transition-colors"
                    >
                      ✕ Dismiss
                    </button>
                    <button
                      onClick={() => openActionModal(f, 'modify')}
                      disabled={actingId === f.id}
                      className="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-800 font-bold text-xs rounded-xl transition-colors"
                    >
                      ✏️ Modify Note
                    </button>
                  </div>
                )}
              </div>
            )
          })}
        </div>
      )}

      {/* Review Action Modal */}
      {modalFinding && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-xs p-4">
          <div className="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-md w-full p-6 space-y-4 animate-scale-in">
            <h3 className="font-extrabold text-base text-gray-900">
              {modalAction === 'accept'
                ? 'Confirm AI Finding'
                : modalAction === 'dismiss'
                ? 'Dismiss AI Finding'
                : 'Modify Finding Review Note'}
            </h3>
            <p className="text-xs text-gray-500">{modalFinding.title}</p>

            <form onSubmit={handleConfirmAction} className="space-y-3">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Reviewer Notes / Reason
                </label>
                <textarea
                  rows={3}
                  placeholder="Provide context for this decision..."
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setModalFinding(null)}
                  className="px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={actingId === modalFinding.id}
                  className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs"
                >
                  Save Decision
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}

export default AiFindingsList
