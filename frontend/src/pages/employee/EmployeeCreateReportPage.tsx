import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { createMyReport, getMyTasks, submitMyReport } from '../../services/employeePortalService'
import type { CreateReportPayload, EmployeePortalTask } from '../../services/employeePortalService'
import { clearSummaryTransfer, getSummaryTransfer } from '../../services/employeeAiAssistantService'
import { PATHS } from '../../routes/paths'

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

const initialForm: FormState = {
  task_id: '',
  title: '',
  report_type: '',
  reporting_period: '',
  report_description: '',
  accomplishments: '',
  completed_tasks: '',
  challenges_encountered: '',
  next_plans: '',
  references: '',
}

function sectionText(content: string, labels: string[]): string {
  const normalized = content.replace(/\r\n/g, '\n')
  for (const label of labels) {
    const re = new RegExp(`(?:^|\\n)\\s*(?:#{1,6}\\s*|\\*\\*)?${label}(?:\\*\\*)?\\s*:?\\s*\\n([\\s\\S]*?)(?=\\n\\s*(?:#{1,6}\\s*|\\*\\*)?(?:What you shared|Completed tasks(?:\\s*/\\s*accomplishments)?|Accomplishments|Challenges encountered|Next plans|Next steps|Recommendations|Summary|Daily|Weekly|Monthly)(?:\\*\\*)?\\s*:|$)`, 'i')
    const m = normalized.match(re)
    if (m?.[1]) {
      const value = m[1].trim().replace(/^[-*]\s*/gm, '• ').trim()
      if (value && !/not provided yet/i.test(value)) return value
    }
  }
  return ''
}

function applySummaryToForm(content: string, title?: string | null, summaryType?: string | null): Partial<FormState> {
  const accomplishments = sectionText(content, ['Completed tasks / accomplishments', 'Accomplishments', 'Key accomplishments', 'What you shared'])
  const completed = sectionText(content, ['Completed tasks', 'Completed Tasks', 'Key activities'])
  const challenges = sectionText(content, ['Challenges encountered', 'Challenges', 'Issues'])
  const nextPlans = sectionText(content, ['Next plans', 'Next steps', 'Recommendations', 'Action items'])
  const overview = sectionText(content, ['Accomplishment / Report Draft \\(Grounded in Your Message\\)', 'Executive Summary', 'Summary', 'Overview'])

  const typeMap: Record<string, string> = {
    daily: 'Daily Report',
    weekly: 'Weekly Report',
    monthly: 'Monthly Report',
    accomplishment: 'Accomplishment Report',
    report_draft: 'Activity Report',
  }

  return {
    title: title && title.trim() ? title.trim().slice(0, 255) : '',
    report_type: summaryType && typeMap[summaryType] ? typeMap[summaryType] : '',
    report_description: overview || (content.length < 1200 ? content : content.slice(0, 1200)),
    accomplishments: accomplishments || completed || '',
    completed_tasks: completed || accomplishments || '',
    challenges_encountered: challenges || 'No challenges were reported in the imported summary.',
    next_plans: nextPlans || '',
  }
}

export default function EmployeeCreateReportPage() {
  const navigate = useNavigate()
  const [tasks, setTasks] = useState<EmployeePortalTask[]>([])
  const [form, setForm] = useState<FormState>(initialForm)
  const [attachments, setAttachments] = useState<File[]>([])
  const [saving, setSaving] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [draftId, setDraftId] = useState<number | null>(null)
  const [confirm, setConfirm] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<keyof FormState, string>>>({})
  const [importedSummaryTitle, setImportedSummaryTitle] = useState<string | null>(null)

  useEffect(() => {
    getMyTasks({ per_page: 100 })
      .then((r) => setTasks(Array.isArray(r.data?.tasks) ? r.data.tasks : []))
      .catch(() => {})
  }, [])

  useEffect(() => {
    const transfer = getSummaryTransfer()
    if (!transfer?.content) return
    const mapped = applySummaryToForm(transfer.content, transfer.title, transfer.summaryType)
    setForm((current) => {
      const mostlyEmpty = !current.title && !current.report_description && !current.accomplishments && !current.completed_tasks
      if (!mostlyEmpty) return current
      return {
        ...current,
        ...Object.fromEntries(Object.entries(mapped).filter(([, value]) => String(value ?? '').trim() !== '')),
      } as FormState
    })
    setImportedSummaryTitle(transfer.title || 'JPC AIDE summary')
    clearSummaryTransfer()
  }, [])

  const setField = (key: keyof FormState, value: string) => {
    setForm((f) => ({ ...f, [key]: value }))
    setFieldErrors((e) => ({ ...e, [key]: undefined }))
  }

  const payload = (): CreateReportPayload => ({
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

  const validate = (_forSubmit: boolean = false) => {
    const next: Partial<Record<keyof FormState, string>> = {}
    if (!form.task_id) next.task_id = 'Select the task this report is for.'
    if (!form.title.trim()) next.title = 'Report title is required.'
    setFieldErrors(next)
    return Object.keys(next).length === 0
  }

  const handleSaveDraft = async () => {
    if (!validate(false)) { setError('Task and title are required.'); return }
    setSaving(true); setError(null)
    try {
      const r = await createMyReport(payload())
      const report = (r.data as { report?: { id?: number } })?.report
      setDraftId(report?.id ?? null)
      setAttachments([])
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to save draft.')
    } finally {
      setSaving(false)
    }
  }

  const handleSubmit = async () => {
    if (!validate(true)) { setError('Please complete all required fields before submitting.'); setConfirm(false); return }
    setSubmitting(true); setError(null)
    try {
      if (draftId) {
        await submitMyReport(draftId, payload())
      } else {
        const created = await createMyReport(payload())
        const report = (created.data as { report?: { id?: number } })?.report
        if (!report?.id) throw new Error('Draft could not be created.')
        await submitMyReport(report.id)
      }
      navigate(PATHS.employeeReports)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to submit report.')
    } finally {
      setSubmitting(false); setConfirm(false)
    }
  }

  const inputClass = (key: keyof FormState) =>
    `w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 ${fieldErrors[key] ? 'border-red-300 focus:ring-red-100' : 'border-gray-300 focus:ring-blue-100 focus:border-blue-500'}`

  return (
    <div className="mx-auto max-w-4xl space-y-6 p-6">
      <div className="flex items-center gap-3">
        <button type="button" onClick={() => navigate(PATHS.employeeReports)} className="text-gray-500 hover:text-gray-700">←</button>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Create Report</h1>
          <p className="text-sm text-gray-500">Save a draft anytime, or complete all required fields to submit.</p>
        </div>
      </div>

      {importedSummaryTitle && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <strong>JPC AIDE summary applied:</strong> {importedSummaryTitle}. Review and edit the imported content before saving or submitting. This report will not be submitted automatically.
            </div>
            <button type="button" onClick={() => navigate(PATHS.employeeAiAssistant)} className="font-medium text-blue-700 underline">Back to JPC AIDE</button>
          </div>
        </div>
      )}
      {error && <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>}
      {draftId && <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">Draft saved (Report #{draftId}). You can continue editing and submit when ready.</div>}

      <div className="space-y-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div className="grid gap-5 md:grid-cols-2">
          <div>
            <label className="mb-1 block text-sm font-semibold text-gray-700">Related Task *</label>
            <select value={form.task_id} onChange={(e) => setField('task_id', e.target.value)} className={inputClass('task_id')}>
              <option value="">Select task...</option>
              {tasks.map((t) => <option key={t.id} value={t.id}>{t.title ?? `Task #${t.id}`}</option>)}
            </select>
            {fieldErrors.task_id && <p className="mt-1 text-xs text-red-600">{fieldErrors.task_id}</p>}
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold text-gray-700">Report Type *</label>
            <input value={form.report_type} onChange={(e) => setField('report_type', e.target.value)} className={inputClass('report_type')} placeholder="e.g. Weekly Report" />
            {fieldErrors.report_type && <p className="mt-1 text-xs text-red-600">{fieldErrors.report_type}</p>}
          </div>
        </div>

        <div className="grid gap-5 md:grid-cols-2">
          <div>
            <label className="mb-1 block text-sm font-semibold text-gray-700">Report Title *</label>
            <input value={form.title} onChange={(e) => setField('title', e.target.value)} className={inputClass('title')} placeholder="Enter report title..." />
            {fieldErrors.title && <p className="mt-1 text-xs text-red-600">{fieldErrors.title}</p>}
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold text-gray-700">Reporting Period *</label>
            <input value={form.reporting_period} onChange={(e) => setField('reporting_period', e.target.value)} className={inputClass('reporting_period')} placeholder="e.g. Nov 1–7, 2026" />
            {fieldErrors.reporting_period && <p className="mt-1 text-xs text-red-600">{fieldErrors.reporting_period}</p>}
          </div>
        </div>

        {([
          ['report_description', 'Report Description *', 5, 'Summarize the work covered by this report.'],
          ['accomplishments', 'Accomplishments *', 4, 'List accomplishments achieved during the reporting period.'],
          ['completed_tasks', 'Completed Tasks *', 4, 'List tasks completed, with outcomes where relevant.'],
          ['challenges_encountered', 'Challenges Encountered *', 4, 'Describe blockers, risks, or issues encountered.'],
          ['next_plans', 'Next Plans *', 4, 'Describe planned actions for the next period.'],
          ['references', 'References / Supporting Notes', 3, 'Optional references, links, or notes.'],
        ] as Array<[keyof FormState, string, number, string]>).map(([key, label, rows, placeholder]) => (
          <div key={key}>
            <label className="mb-1 block text-sm font-semibold text-gray-700">{label}</label>
            <textarea value={form[key]} onChange={(e) => setField(key, e.target.value)} rows={rows} className={`${inputClass(key)} resize-y`} placeholder={placeholder} />
            {fieldErrors[key] && <p className="mt-1 text-xs text-red-600">{fieldErrors[key]}</p>}
          </div>
        ))}

        <div>
          <label className="mb-1 block text-sm font-semibold text-gray-700">Optional File Attachments</label>
          <input
            type="file"
            multiple
            accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg"
            onChange={(e) => setAttachments(Array.from(e.target.files ?? []))}
            className="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700"
          />
          <p className="mt-1 text-xs text-gray-500">Allowed: PDF, DOC/DOCX, TXT, PNG, JPG/JPEG. Max 10 MB each, up to 10 files.</p>
          {attachments.length > 0 && <ul className="mt-2 space-y-1 text-sm text-gray-700">{attachments.map((file) => <li key={`${file.name}-${file.size}`}>• {file.name}</li>)}</ul>}
        </div>
      </div>

      <div className="flex flex-wrap justify-end gap-3">
        <button type="button" onClick={handleSaveDraft} disabled={saving || submitting} className="rounded-lg border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
          {saving ? 'Saving...' : 'Save Draft'}
        </button>
        <button type="button" onClick={() => setConfirm(true)} disabled={saving || submitting} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
          Submit Report
        </button>
      </div>

      {confirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900">Submit Report?</h3>
            <p className="mt-2 text-sm text-gray-600">After submission, the report enters the review workflow and an AI summary will be generated automatically from the submitted content. Draft editing is only available through the revision workflow when requested.</p>
            <div className="mt-5 flex justify-end gap-3">
              <button type="button" onClick={() => setConfirm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">Cancel</button>
              <button type="button" onClick={handleSubmit} disabled={submitting} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">{submitting ? 'Submitting...' : 'Yes, Submit'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}