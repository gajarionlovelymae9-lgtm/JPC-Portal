import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getMyReport, createMyReportRevision } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'

export default function EmployeeReviseReportPage() {
  const { reportId } = useParams()
  const navigate = useNavigate()
  const [originalComments, setOriginalComments] = useState<string>('')
  const [form, setForm] = useState({ title: '', content: '', references: '', revision_notes: '' })
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!reportId) return
    getMyReport(reportId).then(r => {
      const d = r.data
      setForm(f => ({ ...f, title: d.title, content: d.content ?? '', references: d.references ?? '' }))
      setOriginalComments(d.review_history?.at(-1)?.comments ?? '')
    }).catch(() => setError('Could not load report.')).finally(() => setLoading(false))
  }, [reportId])

  const submit = async () => {
    if (!reportId) return
    setSubmitting(true); setError(null)
    try { await createMyReportRevision(reportId, form); navigate(PATHS.employeeReports) }
    catch { setError('Failed to submit revision.') }
    finally { setSubmitting(false) }
  }

  if (loading) return <div className="p-6 space-y-3">{[...Array(4)].map((_,i) => <div key={i} className="h-12 bg-gray-100 rounded-lg animate-pulse"/>)}</div>

  return (
    <div className="p-6 max-w-3xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={() => navigate(PATHS.employeeReports)} className="text-gray-500 hover:text-gray-700">←</button>
        <h1 className="text-2xl font-bold text-gray-900">Submit Revision</h1>
      </div>
      {originalComments && <div className="bg-orange-50 border border-orange-200 rounded-lg p-4"><p className="font-semibold text-orange-800 text-sm mb-1">Reviewer Feedback:</p><p className="text-orange-700 text-sm">{originalComments}</p></div>}
      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        <div><label className="block text-sm font-semibold text-gray-700 mb-1">Title</label><input value={form.title} onChange={e => setForm(f => ({...f, title: e.target.value}))} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"/></div>
        <div><label className="block text-sm font-semibold text-gray-700 mb-1">Revised Content</label><textarea value={form.content} onChange={e => setForm(f => ({...f, content: e.target.value}))} rows={12} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"/></div>
        <div><label className="block text-sm font-semibold text-gray-700 mb-1">References</label><textarea value={form.references} onChange={e => setForm(f => ({...f, references: e.target.value}))} rows={3} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"/></div>
        <div><label className="block text-sm font-semibold text-gray-700 mb-1">Changes Made (optional)</label><textarea value={form.revision_notes} onChange={e => setForm(f => ({...f, revision_notes: e.target.value}))} rows={3} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none" placeholder="Describe the changes you made..."/></div>
      </div>
      <div className="flex gap-3 justify-end">
        <button onClick={() => navigate(PATHS.employeeReports)} className="px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700">Cancel</button>
        <button onClick={submit} disabled={submitting} className="px-5 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700 disabled:opacity-50">{submitting ? 'Submitting...' : 'Submit Revision'}</button>
      </div>
    </div>
  )
}
