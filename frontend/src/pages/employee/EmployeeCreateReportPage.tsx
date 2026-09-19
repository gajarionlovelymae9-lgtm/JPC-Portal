import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { createMyReport, submitMyReport, getMyTasks } from '../../services/employeePortalService'
import type { EmployeeTask } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'

export default function EmployeeCreateReportPage() {
  const navigate = useNavigate()
  const [tasks, setTasks] = useState<EmployeeTask[]>([])
  const [form, setForm] = useState({ task_id: '', title: '', content: '', references: '' })
  const [saving, setSaving] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [draftId, setDraftId] = useState<number | null>(null)
  const [confirm, setConfirm] = useState(false)

  useEffect(() => {
    getMyTasks().then(r => setTasks(Array.isArray(r.data) ? r.data : [])).catch(() => {})
  }, [])

  const handleSaveDraft = async () => {
    if (!form.task_id || !form.title) { setError('Task and title are required.'); return }
    setSaving(true); setError(null)
    try {
      const r = await createMyReport({ task_id: Number(form.task_id), title: form.title, content: form.content, references: form.references })
      setDraftId(r.data.id)
    } catch { setError('Failed to save draft.') }
    finally { setSaving(false) }
  }

  const handleSubmit = async () => {
    if (!draftId) { setError('Save draft first.'); return }
    setSubmitting(true); setError(null)
    try {
      await submitMyReport(draftId)
      navigate(PATHS.employeeReports)
    } catch { setError('Failed to submit report.') }
    finally { setSubmitting(false); setConfirm(false) }
  }

  return (
    <div className="p-6 max-w-3xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={() => navigate(PATHS.employeeReports)} className="text-gray-500 hover:text-gray-700">←</button>
        <h1 className="text-2xl font-bold text-gray-900">Create Report</h1>
      </div>

      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      {draftId && <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700">✓ Draft saved (Report #{draftId})</div>}

      <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-1">Task *</label>
          <select value={form.task_id} onChange={e => setForm(f => ({...f, task_id: e.target.value}))} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            <option value="">Select task...</option>
            {tasks.map(t => <option key={t.id} value={t.id}>{t.title}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-1">Report Title *</label>
          <input value={form.title} onChange={e => setForm(f => ({...f, title: e.target.value}))} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="Enter report title..." />
        </div>
        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-1">Report Content</label>
          <textarea value={form.content} onChange={e => setForm(f => ({...f, content: e.target.value}))} rows={10} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none" placeholder="Write your report content here..." />
        </div>
        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-1">References</label>
          <textarea value={form.references} onChange={e => setForm(f => ({...f, references: e.target.value}))} rows={3} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none" placeholder="List your references..." />
        </div>
      </div>

      <div className="flex gap-3 justify-end">
        <button onClick={handleSaveDraft} disabled={saving} className="px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
          {saving ? 'Saving...' : 'Save Draft'}
        </button>
        <button onClick={() => setConfirm(true)} disabled={!draftId || submitting} className="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
          Submit Report
        </button>
      </div>

      {confirm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-md w-full shadow-xl mx-4">
            <h3 className="text-lg font-bold text-gray-900 mb-2">Submit Report?</h3>
            <p className="text-gray-600 text-sm mb-5">Once submitted, the report will enter review. You will not be able to edit it unless a revision is requested.</p>
            <div className="flex gap-3 justify-end">
              <button onClick={() => setConfirm(false)} className="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700">Cancel</button>
              <button onClick={handleSubmit} disabled={submitting} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium disabled:opacity-50">{submitting ? 'Submitting...' : 'Yes, Submit'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
