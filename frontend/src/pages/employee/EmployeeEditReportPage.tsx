import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getMyReport, updateMyReport, submitMyReport } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'

export default function EmployeeEditReportPage() {
  const { reportId } = useParams()
  const navigate = useNavigate()
  const [form, setForm] = useState({ title: '', content: '', references: '' })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)
  const [confirm, setConfirm] = useState(false)

  useEffect(() => {
    if (!reportId) return
    getMyReport(reportId).then(r => {
      setForm({ title: r.data.title, content: r.data.content ?? '', references: r.data.references ?? '' })
    }).catch(() => setError('Could not load report.')).finally(() => setLoading(false))
  }, [reportId])

  const save = async () => {
    if (!reportId) return
    setSaving(true); setError(null)
    try { await updateMyReport(reportId, form); setSaved(true) }
    catch { setError('Failed to save.') }
    finally { setSaving(false) }
  }

  const submit = async () => {
    if (!reportId) return
    setSubmitting(true)
    try { await save(); await submitMyReport(reportId); navigate(PATHS.employeeReports) }
    catch { setError('Failed to submit.') }
    finally { setSubmitting(false); setConfirm(false) }
  }

  if (loading) return <div className="p-6 space-y-3">{[...Array(4)].map((_,i) => <div key={i} className="h-12 bg-gray-100 rounded-lg animate-pulse"/>)}</div>

  return (
    <div className="p-6 max-w-3xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={() => navigate(PATHS.employeeReports)} className="text-gray-500 hover:text-gray-700">←</button>
        <h1 className="text-2xl font-bold text-gray-900">Edit Report</h1>
      </div>
      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      {saved && <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700">✓ Saved</div>}
      <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        <div><label className="block text-sm font-semibold text-gray-700 mb-1">Title *</label><input value={form.title} onChange={e => setForm(f => ({...f, title: e.target.value}))} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"/></div>
        <div><label className="block text-sm font-semibold text-gray-700 mb-1">Content</label><textarea value={form.content} onChange={e => setForm(f => ({...f, content: e.target.value}))} rows={12} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"/></div>
        <div><label className="block text-sm font-semibold text-gray-700 mb-1">References</label><textarea value={form.references} onChange={e => setForm(f => ({...f, references: e.target.value}))} rows={3} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"/></div>
      </div>
      <div className="flex gap-3 justify-end">
        <button onClick={save} disabled={saving} className="px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">{saving ? 'Saving...' : 'Save Draft'}</button>
        <button onClick={() => setConfirm(true)} className="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Submit Report</button>
      </div>
      {confirm && <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50"><div className="bg-white rounded-xl p-6 max-w-md w-full shadow-xl mx-4"><h3 className="text-lg font-bold text-gray-900 mb-2">Submit Report?</h3><p className="text-gray-600 text-sm mb-5">Once submitted, the report will enter review.</p><div className="flex gap-3 justify-end"><button onClick={() => setConfirm(false)} className="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700">Cancel</button><button onClick={submit} disabled={submitting} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium disabled:opacity-50">{submitting ? 'Submitting...' : 'Yes, Submit'}</button></div></div></div>}
    </div>
  )
}
