import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { generateMyReportSummary, getMyReport } from '../../services/employeePortalService'
import { reportWorkflowService } from '../../services/reportWorkflowService'
import type { EmployeeReport, ReviewHistoryItem } from '../../services/employeePortalService'
import type { ReportTimelineEvent } from '../../types/reportWorkflow'
import { PATHS, employeeReportEditPath } from '../../routes/paths'

const statusLabel = (status: string) => ({
  draft: 'Draft',
  submitted: 'Submitted',
  processing: 'Processing',
  under_review: 'Under Review',
  revision_required: 'Returned for Revision',
  resubmitted: 'Resubmitted',
  approved: 'Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
  archived: 'Archived',
}[status] ?? status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()))

const statusClass = (status: string) => ({
  draft: 'bg-gray-100 text-gray-700 border-gray-200',
  submitted: 'bg-blue-100 text-blue-700 border-blue-200',
  processing: 'bg-blue-100 text-blue-700 border-blue-200',
  under_review: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  revision_required: 'bg-orange-100 text-orange-700 border-orange-200',
  resubmitted: 'bg-blue-100 text-blue-700 border-blue-200',
  approved: 'bg-green-100 text-green-700 border-green-200',
  rejected: 'bg-red-100 text-red-700 border-red-200',
}[status] ?? 'bg-gray-100 text-gray-700 border-gray-200')

function formatDate(value?: string | null) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">{title}</h3>
      {children}
    </section>
  )
}

function SummaryList({ title, items }: { title: string; items?: string[] | null }) {
  const values = Array.isArray(items) ? items.filter(Boolean) : []
  return (
    <div>
      <h4 className="mb-2 text-sm font-semibold text-gray-900">{title}</h4>
      {values.length === 0 ? <p className="text-sm text-gray-500">Not provided in the submitted report.</p> : (
        <ul className="list-disc space-y-1 pl-5 text-sm text-gray-700">{values.map((item, i) => <li key={`${title}-${i}`}>{item}</li>)}</ul>
      )}
    </div>
  )
}

export default function EmployeeReportDetailPage() {
  const { reportId } = useParams()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [report, setReport] = useState<EmployeeReport | null>(null)
  const [timeline, setTimeline] = useState<ReportTimelineEvent[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [tab, setTab] = useState<'overview' | 'ai' | 'feedback' | 'history'>('overview')
  const [summaryBusy, setSummaryBusy] = useState(false)
  const [summaryMessage, setSummaryMessage] = useState<string | null>(null)

  const load = async (id: string) => {
    setLoading(true); setError(null)
    try {
      const r = await getMyReport(id)
      const data = r.data as EmployeeReport
      setReport(data)
      try {
        const [t] = await Promise.allSettled([
          reportWorkflowService.getTimeline(Number(id)),
        ])
        if (t.status === 'fulfilled' && Array.isArray(t.value.data)) setTimeline(t.value.data)
        else if (Array.isArray(data.timeline)) setTimeline(data.timeline as unknown as ReportTimelineEvent[])
      } catch {
        /* optional enrichment */
      }
    } catch {
      setError('Could not load report details.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (!reportId) return
    void load(reportId)
  }, [reportId])

  useEffect(() => {
    const requested = searchParams.get('tab')
    if (requested === 'ai' || requested === 'feedback' || requested === 'history') setTab(requested)
    if (searchParams.get('print') === '1') {
      const timer = window.setTimeout(() => window.print(), 600)
      return () => window.clearTimeout(timer)
    }
  }, [searchParams, loading])

  const canGenerateSummary = useMemo(() => {
    if (!report) return false
    return report.status !== 'draft'
  }, [report])

  const onGenerateSummary = async (regenerate: boolean) => {
    if (!reportId) return
    setSummaryBusy(true); setSummaryMessage(null)
    try {
      const result = await generateMyReportSummary(reportId, regenerate)
      const payload = result.data as { ai_summary_status?: string; message?: string }
      setSummaryMessage(payload?.message || (payload?.ai_summary_status === 'generated' ? 'AI summary generated.' : `AI summary status: ${payload?.ai_summary_status ?? 'updated'}.`))
      await load(reportId)
      setTab('ai')
    } catch (e) {
      setSummaryMessage(e instanceof Error ? e.message : 'AI summary could not be generated.')
    } finally {
      setSummaryBusy(false)
    }
  }

  if (loading) return <div className="space-y-4 p-6">{Array.from({ length: 5 }).map((_, i) => <div key={i} className="h-20 animate-pulse rounded-xl bg-gray-100" />)}</div>
  if (error || !report) return <div className="p-6"><div className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">{error || 'Report not found.'}</div></div>

  const ai = report.ai_summary
  const rawFeedback = (report.reviewer_feedback && report.reviewer_feedback.length > 0 ? report.reviewer_feedback : report.review_history) ?? []
  const feedback = rawFeedback.map((item) => {
    const anyItem = item as ReviewHistoryItem & { id?: number; section?: string | null; comment?: string | null; created_at?: string | null }
    return {
      id: anyItem.id,
      reviewer: anyItem.reviewer ?? null,
      decision: anyItem.decision ?? null,
      section: anyItem.section ?? null,
      comment: anyItem.comment ?? anyItem.comments ?? null,
      date: anyItem.date ?? anyItem.created_at ?? null,
    }
  })
  const revisionHistory = ((report.revision_history && report.revision_history.length > 0 ? report.revision_history : report.versions) ?? []).map((v) => ({
    version: v.version,
    status: v.status ?? null,
    change_summary: (v as { change_summary?: string | null }).change_summary ?? null,
    submitted_at: v.submitted_at ?? null,
    created_at: v.created_at ?? null,
  }))
  const isDraft = report.status === 'draft'
  const isRevisionRequired = report.status === 'revision_required'

  return (
    <div className="mx-auto max-w-6xl space-y-6 p-6 print:p-0">
      <div className="flex flex-wrap items-start justify-between gap-4 print:hidden">
        <div>
          <button type="button" onClick={() => navigate(PATHS.employeeReports)} className="mb-2 text-sm text-gray-500 hover:text-gray-700">← Back to My Reports</button>
          <h1 className="text-2xl font-bold text-gray-900">{report.title}</h1>
          <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-500">
            <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-medium ${statusClass(report.status)}`}>{statusLabel(report.status)}</span>
            <span>Version {report.version ?? 1}</span>
            {report.is_late && <span className="rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700">Late submission</span>}
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          {isDraft && <Link to={employeeReportEditPath(report.id)} className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700">Edit Draft</Link>}
          {canGenerateSummary && (
            <>
              <button type="button" disabled={summaryBusy} onClick={() => void onGenerateSummary(false)} className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 disabled:opacity-50">{summaryBusy ? 'Working...' : 'Generate JPC AIDE Summary'}</button>
              <button type="button" disabled={summaryBusy} onClick={() => void onGenerateSummary(true)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 disabled:opacity-50">Regenerate JPC AIDE Summary</button>
            </>
          )}
          <button type="button" onClick={() => window.print()} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Download / Print Report</button>
        </div>
      </div>

      {summaryMessage && <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 print:hidden">{summaryMessage}</div>}
      {isRevisionRequired && <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 text-sm text-orange-800 print:hidden">This report was returned for revision. Review feedback, then edit/resubmit through the available revision workflow.</div>}

      <div className="grid gap-6 lg:grid-cols-[1.4fr,1fr]">
        <SectionCard title="Original Submitted Report">
          <dl className="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt className="text-gray-500">Report Type</dt><dd className="font-medium text-gray-900">{report.report_type || '—'}</dd></div>
            <div><dt className="text-gray-500">Reporting Period</dt><dd className="font-medium text-gray-900">{report.reporting_period || '—'}</dd></div>
            <div><dt className="text-gray-500">Task</dt><dd className="font-medium text-gray-900">{report.task_title || `Task #${report.task_id}`}</dd></div>
            <div><dt className="text-gray-500">Report Status</dt><dd className="font-medium text-gray-900">{statusLabel(report.status)}</dd></div>
            <div><dt className="text-gray-500">Submission Date</dt><dd className="font-medium text-gray-900">{formatDate(report.submitted_at)}</dd></div>
            <div><dt className="text-gray-500">JPC AIDE Summary Status</dt><dd className="font-medium text-gray-900">{report.ai_summary_status || 'Not generated'}</dd></div>
          </dl>
          <div className="mt-5 space-y-4">
            {([
              ['Report Description', report.report_description ?? report.content],
              ['Accomplishments', report.accomplishments],
              ['Completed Tasks', report.completed_tasks ?? report.completed_tasks_summary],
              ['Challenges Encountered', report.challenges_encountered],
              ['Next Plans', report.next_plans],
              ['References / Supporting Notes', report.references],
            ] as Array<[string, string | null | undefined]>).map(([title, value]) => (
              <div key={title}>
                <h4 className="mb-1 text-sm font-semibold text-gray-900">{title}</h4>
                <div className="whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-sm text-gray-700">{value?.trim() || 'Not provided.'}</div>
              </div>
            ))}
          </div>
          {report.files && report.files.length > 0 && (
            <div className="mt-5">
              <h4 className="mb-2 text-sm font-semibold text-gray-900">Attachments</h4>
              <ul className="space-y-2 text-sm">
                {report.files.map((file) => (
                  <li key={file.id} className="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                    <span className="text-gray-700">{file.file_name}</span>
                    {file.download_url && <a className="text-blue-600 hover:underline print:hidden" href={file.download_url} target="_blank" rel="noreferrer">Download</a>}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </SectionCard>

        <div className="space-y-6">
          <SectionCard title="Submission Information">
            <dl className="space-y-3 text-sm">
              <div className="flex justify-between gap-4"><dt className="text-gray-500">Created</dt><dd className="text-right text-gray-900">{formatDate(report.created_at)}</dd></div>
              <div className="flex justify-between gap-4"><dt className="text-gray-500">Updated</dt><dd className="text-right text-gray-900">{formatDate(report.updated_at)}</dd></div>
              <div className="flex justify-between gap-4"><dt className="text-gray-500">Submitted</dt><dd className="text-right text-gray-900">{formatDate(report.submitted_at)}</dd></div>
              <div className="flex justify-between gap-4"><dt className="text-gray-500">Approved</dt><dd className="text-right text-gray-900">{formatDate(report.approved_at)}</dd></div>
              <div className="flex justify-between gap-4"><dt className="text-gray-500">Version</dt><dd className="text-right text-gray-900">{report.version ?? 1}</dd></div>
            </dl>
          </SectionCard>

          <SectionCard title="Reviewer Feedback">
            {feedback.length === 0 ? <p className="text-sm text-gray-500">No reviewer feedback is available yet.</p> : (
              <div className="space-y-3">
                {feedback.map((item, i) => (
                  <div key={item.id ?? i} className="rounded-lg border border-gray-200 p-3 text-sm">
                    <div className="font-medium text-gray-900">{item.reviewer || 'Reviewer'} {item.decision ? `· ${item.decision}` : ''}</div>
                    {item.section && <div className="text-xs text-gray-500">Section: {item.section}</div>}
                    <p className="mt-1 whitespace-pre-wrap text-gray-700">{item.comment || 'No comment text.'}</p>
                    <div className="mt-1 text-xs text-gray-500">{formatDate(item.date)}</div>
                  </div>
                ))}
              </div>
            )}
          </SectionCard>

          <SectionCard title="Revision History">
            {revisionHistory.length === 0 ? <p className="text-sm text-gray-500">No previous revisions recorded.</p> : (
              <ul className="space-y-2 text-sm">
                {revisionHistory.map((v, i) => (
                  <li key={`${v.version ?? i}`} className="rounded-lg border border-gray-200 px-3 py-2">
                    <div className="font-medium text-gray-900">Version {v.version}</div>
                    <div className="text-gray-600">{statusLabel(String(v.status ?? 'draft'))}</div>
                    {v.change_summary && <div className="text-gray-600">{v.change_summary}</div>}
                    <div className="text-xs text-gray-500">{formatDate(v.submitted_at || v.created_at)}</div>
                  </li>
                ))}
              </ul>
            )}
          </SectionCard>
        </div>
      </div>

      <SectionCard title="AI-Generated Summary">
        <div className="mb-4 flex flex-wrap items-center gap-2 text-sm print:hidden">
          <span className="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700">Status: {report.ai_summary_status || ai?.status || 'Not generated'}</span>
          {ai?.generated_at && <span className="text-gray-500">Generated: {formatDate(ai.generated_at)}</span>}
        </div>
        {!ai || report.ai_summary_status === 'not_generated' || report.ai_summary_status === 'generating' ? (
          <div className="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600">
            {report.status === 'draft'
              ? 'AI summary is generated after the report is submitted.'
              : report.ai_summary_status === 'unavailable'
                ? 'AI summary is unavailable right now. The report submission was successful; you can try generating the summary again later.'
                : report.ai_summary_status === 'failed'
                  ? 'AI summary generation failed. You can try generating it again.'
                  : 'No JPC AIDE summary is available yet. Use “Generate JPC AIDE Summary” after submission.'}
          </div>
        ) : (
          <div className="space-y-5">
            <div>
              <h4 className="mb-2 text-sm font-semibold text-gray-900">Executive Summary</h4>
              <p className="whitespace-pre-wrap rounded-lg bg-blue-50/50 p-3 text-sm text-gray-700">{ai.executive_summary || 'Not provided in the submitted report.'}</p>
            </div>
            <div className="grid gap-5 md:grid-cols-2">
              <SummaryList title="Summary of Accomplishments" items={ai.summary_of_accomplishments ?? ai.accomplishments} />
              <SummaryList title="Completed Tasks Summary" items={ai.completed_tasks_summary ?? ai.key_points} />
              <SummaryList title="Challenges Encountered" items={ai.challenges_encountered ?? ai.issues} />
              <SummaryList title="Recommendations" items={ai.recommendations} />
            </div>
            <SummaryList title="Next Steps" items={ai.next_steps} />
            {ai.disclaimer && <p className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">{ai.disclaimer}</p>}
          </div>
        )}
      </SectionCard>

      {timeline.length > 0 && tab === 'history' && (
        <SectionCard title="Audit Timeline">
          <ul className="space-y-2 text-sm">
            {timeline.map((event, i) => (
              <li key={event.id ?? i} className="rounded-lg border border-gray-200 px-3 py-2">
                <div className="font-medium text-gray-900">{event.description || event.title || event.event_type || 'Event'}</div>
                <div className="text-xs text-gray-500">{formatDate(event.created_at)}</div>
              </li>
            ))}
          </ul>
        </SectionCard>
      )}

      <style>{`@media print { .print\\:hidden { display: none !important; } body { background: white; } }`}</style>
    </div>
  )
}