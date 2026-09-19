import { useEffect, useState } from 'react'
import { getAdminEscalations, resolveAdminEscalation } from '../../services/adminPortalService'
import type { AdminEscalationItem } from '../../services/adminPortalService'

export default function AdminEscalationsPage() {
  const [escalations, setEscalations] = useState<AdminEscalationItem[]>([])
  const [loading, setLoading] = useState(true)
  const [selectedEsc, setSelectedEsc] = useState<AdminEscalationItem | null>(null)
  const [resolutionStatus, setResolutionStatus] = useState<'resolved' | 'under_review' | 'closed'>('resolved')
  const [resolutionNotes, setResolutionNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const loadData = () => {
    setLoading(true)
    getAdminEscalations()
      .then(res => setEscalations(res.data))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadData()
  }, [])

  const handleResolve = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedEsc || !resolutionNotes.trim()) return

    setSubmitting(true)
    try {
      await resolveAdminEscalation(selectedEsc.id, {
        status: resolutionStatus,
        resolution_notes: resolutionNotes,
      })
      setSelectedEsc(null)
      setResolutionNotes('')
      loadData()
    } catch {
      alert('Failed to resolve escalation.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Department Escalations Resolution</h1>
        <p className="text-sm text-gray-500 mt-0.5">Review and resolve operational roadblocks, overdue deliverables, and risks flagged by Department Heads</p>
      </div>

      {loading ? (
        <div className="space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />)}</div>
      ) : escalations.length === 0 ? (
        <div className="bg-white border border-gray-200 rounded-3xl p-12 text-center text-gray-500">
          <span className="text-4xl block mb-2">🛡️</span>
          <p className="font-semibold">No active escalations.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4">
          {escalations.map(esc => (
            <div key={esc.id} className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-3">
              <div className="flex justify-between items-start">
                <div>
                  <div className="flex items-center gap-2">
                    <span className="text-xs bg-gray-100 font-bold px-2 py-0.5 rounded text-gray-700 uppercase">{esc.type.replace(/_/g, ' ')}</span>
                    <span className={`text-xs px-2.5 py-0.5 rounded-full font-bold uppercase ${esc.status === 'open' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'}`}>
                      {esc.status}
                    </span>
                  </div>
                  <h3 className="font-black text-gray-900 text-base mt-1.5">{esc.title}</h3>
                  <p className="text-xs text-gray-500">From: <b>{esc.department_name}</b> ({esc.created_by_name})</p>
                </div>
                {esc.status === 'open' && (
                  <button
                    onClick={() => { setSelectedEsc(esc); setResolutionNotes(esc.resolution_notes ?? ''); }}
                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm"
                  >
                    Action / Resolve
                  </button>
                )}
              </div>

              <div className="bg-gray-50 p-4 rounded-2xl text-xs text-gray-800 border border-gray-100 leading-relaxed">
                {esc.reason}
              </div>

              {esc.resolution_notes && (
                <div className="bg-emerald-50 p-4 rounded-2xl text-xs text-emerald-900 border border-emerald-200">
                  <b>Administrative Resolution:</b> {esc.resolution_notes}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Resolution Modal */}
      {selectedEsc && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-4">
            <h3 className="text-lg font-bold text-gray-900">Resolve Escalation: {selectedEsc.title}</h3>
            <form onSubmit={handleResolve} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Status Transition</label>
                <select
                  value={resolutionStatus}
                  onChange={e => setResolutionStatus(e.target.value as typeof resolutionStatus)}
                  className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm bg-white"
                >
                  <option value="resolved">Mark as Resolved</option>
                  <option value="under_review">Mark Under Institutional Review</option>
                  <option value="closed">Close Escalation</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Administrative Notes & Action Taken *</label>
                <textarea
                  value={resolutionNotes}
                  onChange={e => setResolutionNotes(e.target.value)}
                  rows={4}
                  placeholder="Detail the resolution provided..."
                  className="w-full border border-gray-300 rounded-xl p-3 text-sm"
                  required
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setSelectedEsc(null)}
                  className="px-4 py-2 border border-gray-300 text-gray-700 rounded-xl text-xs font-bold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold"
                >
                  {submitting ? 'Saving...' : 'Confirm Resolution'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}