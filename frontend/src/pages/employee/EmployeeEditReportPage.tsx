import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { getMyReport, getMyTasks, submitMyReport, updateMyReport } from '../../services/employeePortalService'
import type { EmployeePortalTask, EmployeeReport } from '../../services/employeePortalService'
import { PATHS, employeeReportDetailPath } from '../../routes/paths'

const REPORT_TYPES = ['Weekly Report', 'Monthly Report', 'Quarterly Report', 'Project Report', 'Activity Report', 'Accomplishment Report', 'Other']

type FormState = {
  task_id: string
  title: string
  report_type: string
  reporting_period: string
  report_description: string
  accomplishments: string
  completed_tasks: string
  challenges_encountered: string
  next_plans: string
  references: string
}

const toForm = (report: EmployeeReport): FormState => ({
  task_id: report.task_id ? String(report.task_id) : '',
  title: report.title ?? '',
  report_type: report.report_type ?? '',
  reporting_period: report.reporting_period ?? '',
  report_description: report.report_description ?? report.content ?? '',
  accomplishments: report.accomplishments ?? '',
  completed_tasks: report.completed_tasks ?? report.completed_tasks_summary ?? '',
  challenges_encountered: report.challenges_encountered ?? '',
  next_plans: report.next_plans ?? '',
  references: report.references ?? '',
})

export default function EmployeeEditReportPage() {
  const { reportId } = useParams()
  const navigate = useNavigate()
  const [tasks, setTasks] = useState<EmployeePortalTask[]>([])
  const [report, setReport] = useState<EmployeeReport | null>(null)
  const [form, setForm] = useState<FormState | null>(null)
  const [attachments, setAttachments] = useState<File[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)
  const [confirm, setConfirm] = useState(false)

  useEffect(() => {
    getMyTasks({ per_page: 100 }).then((r) => setTasks(Array.isArray(r.data?.tasks) ? r.data.tasks : [])).catch(() => {})
  }, [])

  useEffect(() => {
    if (!reportId) return
    setLoading(true)
    getMyReport(reportId)
      .then((r) => {
        const data = r.data as EmployeeReport
        setReport(data)
        setForm(toForm(data))
      })
      .catch(() => setError('Could not load report.'))
      .finally(() => setLoading(false))
  }, [reportId])

  if (loading || !form) {
    return <div className="space-y-3 p-6">{Array.from({ length: 6 }).map((_, i) => <div key={i} className="h-12 animate-pulse rounded-lg bg-gray-100" />)}</div>
  }

  const isDraft = report?.status === 'draft'
  if (!isDraft) {
    return (
      <div className="mx-auto max-w-3xl p-6">
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-6">
          <h2 className="text-lg font-semibold text-amber-900">This report cannot be edited</h2>
          <p className="mt-2 text-sm text-amber-800">Only draft reports can be edited directly. Approved reports cannot be modified. If revision is required, create a revision from the report details page.</p>
          <button type="button" onClick={() => navigate(reportId ? employeeReportDetailPath(reportId) : PATHS.employeeReports)} className="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">View Report</button>
        </div>
      </div>
    )
  }

  const setField = (key: keyof FormState, value: string) => {
    setForm((f) => (f ? { ...f, [key]: value } : f))
    setSaved(false)
  }

  const payload = () => ({
    task_id: Number(form.task_id),
    title: form.title.trim(),
    report_type: form.report_type.trim(),
    reporting_period: form.reporting_period.trim(),
    report_description: form.report_description,
    accomplishments: form.accomplishments,
    completed_tasks: form.completed_tasks,
    challenges_encountered: form.challenges_encountered,
    next_plans: form.next_plans,
    references: form.references,
    attachments,
  })

  const save = async () => {
    if (!reportId) return
    setSaving(true); setError(null); setSaved(false)
    try {
      await updateMyReport(reportId, payload())
      setAttachments([])
      setSaved(true)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to save draft.')
    } finally {
      setSaving(false)
    }
  }

  const submit = async () => {
    if (!reportId) return
    setSubmitting(true); setError(null)
    try {
      await submitMyReport(reportId, payload())
      navigate(PATHS.employeeReports)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to submit report. Make sure all required fields are completed.')
    } finally {
      setSubmitting(false); setConfirm(false)
    }
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      <div className="flex items-center gap-3">
        <button type="button" onClick={() => navigate(PATHS.employeeReports)} className="text-gray-500 hover:text-gray-700">←</button>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Edit Draft Report</h1>
          <p className="text-sm text-gray-500">Only draft reports can be edited. Save your progress or submit when complete.</p>
        </div>
      </div>

      {error && <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>}
      {saved && <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">Draft saved.</div>}

      <div className="space-y-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div className="grid gap-5 md:grid-cols-2">
          <div>
            <label className="mb-1 block text-sm font-semibold text-gray-700">Related Task *</label>
            <select value={form.task_id} onChange={(e) => setField('task_id', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
              <option value="">Select task...</option>
              {tasks.map((t) => <option key={t.id} value={t.task_id ?? t.id}>{t.title ?? `Task #${t.task_id ?? t.id}`}</option>)}
            </select>
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold text-gray-700">Report Type *</label>
            <input list="edit-report-type-options" value={form.report_type} onChange={(e) => setField('report_type', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            <datalist id="edit-report-type-options">{REPORT_TYPES.map((type) => <option key={type} value={type} />)}</datalist>
          </div>
        </div>
        <div className="grid gap-5 md:grid-cols-2">
          <div>
            <label className="mb-1 block text-sm font-semibold text-gray-700">Report Title *</label>
            <input value={form.title} onChange={(e) => setField('title', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold text-gray-700">Reporting Period *</label>
            <input value={form.reporting_period} onChange={(e) => setField('reporting_period', e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
          </div>
        </div>

        {([
          ['report_description', 'Report Description *', 5],
          ['accomplishments', 'Accomplishments *', 4],
          ['completed_tasks', 'Completed Tasks *', 4],
          ['challenges_encountered', 'Challenges Encountered *', 4],
          ['next_plans', 'Next Plans *', 4],
          ['references', 'References / Supporting Notes', 3],
        ] as Array<[keyof FormState, string, number]>).map(([key, label, rows]) => (
          <div key={key}>
            <label className="mb-1 block text-sm font-semibold text-gray-700">{label}</label>
            <textarea value={form[key]} onChange={(e) => setField(key, e.target.value)} rows={rows} className="w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
          </div>
        ))}

        {report?.files && report.files.length > 0 && (
          <div>
            <h3 className="mb-2 text-sm font-semibold text-gray-700">Existing Attachments</h3>
            <ul className="space-y-1 text-sm text-gray-700">{report.files.map((file) => <li key={file.id}>• {file.file_name}</li>)}</ul>
          </div>
        )}

        <div>
          <label className="mb-1 block text-sm font-semibold text-gray-700">Add More Attachments</label>
          <input type="file" multiple accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" onChange={(e) => setAttachments(Array.from(e.target.files ?? []))} className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700" />
          {attachments.length > 0 && <ul className="mt-2 space-y-1 text-sm text-gray-700">{attachments.map((file) => <li key={`${file.name}-${file.size}`}>• {file.name}</li>)}</ul>}
        </div>
      </div>

      <div className="flex flex-wrap justify-end gap-3">
        <button type="button" onClick={save} disabled={saving || submitting} className="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">{saving ? 'Saving...' : 'Save Draft'}</button>
        <button type="button" onClick={() => setConfirm(true)} disabled={saving || submitting} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Submit Report</button>
      </div>

      {confirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900">Submit report for review?</h3>
            <p className="mt-2 text-sm text-gray-600">The current draft will be saved and submitted. An AI summary will be generated automatically from the submitted content.</p>
            <div className="mt-5 flex justify-end gap-3">
              <button type="button" onClick={() => setConfirm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">Cancel</button>
              <button type="button" onClick={submit} disabled={submitting} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{submitting ? 'Submitting...' : 'Submit Report'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}