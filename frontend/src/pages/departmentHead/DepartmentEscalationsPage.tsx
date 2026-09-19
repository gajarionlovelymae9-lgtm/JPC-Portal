import { useEffect, useState } from 'react'
import { getDepartmentEscalations, createDepartmentEscalation } from '../../services/departmentHeadService'
import type { DepartmentEscalation } from '../../services/departmentHeadService'

export default function DepartmentEscalationsPage() {
  const [escalations, setEscalations] = useState<DepartmentEscalation[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [modalOpen, setModalOpen] = useState(false)
  const [title, setTitle] = useState('')
  const [type, setType] = useState('overdue_task')
  const [reason, setReason] = useState('')
  const [priority, setPriority] = useState('medium')
  const [submitting, setSubmitting] = useState(false)

  const loadData = () => {
    setLoading(true)
    getDepartmentEscalations()
      .then(res => setEscalations(res.data))
      .catch(() => setError('Failed to load escalations.'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadData()
  }, [])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!title.trim() || !reason.trim()) return
    setSubmitting(true)
    try {
      await createDepartmentEscalation({ title, type, reason, priority })
      setModalOpen(false)
      setTitle('')
      setReason('')
      loadData()
    } catch {
      alert('Failed to submit escalation.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Administration Escalations</h1>
          <p className="text-sm text-gray-500 mt-0.5">Flag critical overdue tasks, missing reports, or target risks directly to Administration</p>
        </div>
        <button
          onClick={() => setModalOpen(true)}
          className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-xs transition-all shadow-sm"
        >
          + Raise Escalation
        </button>
      </div>

      {error && <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200">{error}</div>}

      {loading ? (
        <div className="space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />)}</div>
      ) : escalations.length === 0 ? (
        <div className="bg-white border border-gray-200 rounded-2xl p-12 text-center text-gray-500">
          <span className="text-4xl block mb-2">🛡️</span>
          <p className="font-semibold">No active escalations recorded.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4">
          {escalations.map(esc => (
            <div key={esc.id} className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm space-y-3">
              <div className="flex justify-between items-start">
                <div>
                  <div className="flex items-center gap-2">
                    <span className="text-xs px-2 py-0.5 rounded-md font-bold uppercase bg-gray-100 text-gray-700">{esc.type.replace(/_/g, ' ')}</span>
                    <span className={`text-xs px-2 py-0.5 rounded-md font-bold uppercase ${esc.priority === 'urgent' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700'}`}>
                      {esc.priority}
                    </span>
                    <span className={`text-xs px-2.5 py-0.5 rounded-full font-bold uppercase ${esc.status === 'open' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-800'}`}>
                      {esc.status}
                    </span>
                  </div>
                  <h3 className="font-bold text-gray-900 text-base mt-1.5">{esc.title}</h3>
                </div>
                <span className="text-xs text-gray-400">{new Date(esc.created_at).toLocaleDateString()}</span>
              </div>
              <p className="text-sm text-gray-700 leading-relaxed bg-gray-50 p-3 rounded-xl border border-gray-100">
                {esc.reason}
              </p>
              {esc.resolution_notes && (
                <div className="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-800">
                  <b>Resolution from {esc.resolver_name ?? 'Admin'}:</b> {esc.resolution_notes}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Modal */}
      {modalOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl p-6 max-w-lg w-full shadow-xl space-y-4">
            <h3 className="text-lg font-bold text-gray-900">Raise Issue to Administration</h3>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Title *</label>
                <input
                  type="text"
                  value={title}
                  onChange={e => setTitle(e.target.value)}
                  placeholder="e.g. Critical delay on accreditation syllabus upload"
                  className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Issue Type</label>
                  <select
                    value={type}
                    onChange={e => setType(e.target.value)}
                    className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                  >
                    <option value="overdue_task">Overdue Task</option>
                    <option value="repeated_revision">Repeated Revision</option>
                    <option value="missing_report">Missing Report</option>
                    <option value="target_risk">Target at Risk</option>
                    <option value="general">General Administrative</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Priority</label>
                  <select
                    value={priority}
                    onChange={e => setPriority(e.target.value)}
                    className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Reason & Context *</label>
                <textarea
                  value={reason}
                  onChange={e => setReason(e.target.value)}
                  rows={4}
                  placeholder="Explain why this requires administrative intervention..."
                  className="w-full border border-gray-300 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-4 py-2 border border-gray-300 rounded-xl text-xs font-semibold text-gray-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold disabled:opacity-50"
                >
                  {submitting ? 'Submitting...' : 'Submit Escalation'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}