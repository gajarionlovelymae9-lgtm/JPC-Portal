import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getMyReports } from '../../services/employeePortalService'
import type { EmployeeReportListItem, PaginationMeta } from '../../services/employeePortalService'
import { PATHS, employeeReportDetailPath, employeeReportEditPath } from '../../routes/paths'

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'draft', label: 'Draft' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'under_review', label: 'Under Review' },
  { value: 'revision_required', label: 'Returned for Revision' },
  { value: 'approved', label: 'Approved' },
  { value: 'resubmitted', label: 'Resubmitted' },
  { value: 'rejected', label: 'Rejected' },
]

const AI_STATUS_OPTIONS = [
  { value: '', label: 'All AI statuses' },
  { value: 'not_generated', label: 'Not generated' },
  { value: 'generating', label: 'Generating' },
  { value: 'generated', label: 'Generated' },
  { value: 'unavailable', label: 'Unavailable' },
  { value: 'failed', label: 'Failed' },
]

function formatDate(value?: string | null) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

function reportStatusLabel(status: string) {
  const map: Record<string, string> = {
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
  }
  return map[status] ?? status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function StatusBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-blue-100 text-blue-700',
    processing: 'bg-blue-100 text-blue-700',
    under_review: 'bg-yellow-100 text-yellow-800',
    revision_required: 'bg-orange-100 text-orange-700',
    resubmitted: 'bg-blue-100 text-blue-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-500',
    archived: 'bg-gray-100 text-gray-500',
  }
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${styles[status] ?? 'bg-gray-100 text-gray-700'}`}>
      {reportStatusLabel(status)}
    </span>
  )
}

function AiStatusBadge({ status }: { status?: string | null }) {
  const value = status || 'not_generated'
  const styles: Record<string, string> = {
    not_generated: 'bg-gray-100 text-gray-600',
    generating: 'bg-blue-100 text-blue-700',
    generated: 'bg-green-100 text-green-700',
    unavailable: 'bg-amber-100 text-amber-700',
    failed: 'bg-red-100 text-red-700',
  }
  const labels: Record<string, string> = {
    not_generated: 'Not generated',
    generating: 'Generating',
    generated: 'Generated',
    unavailable: 'Unavailable',
    failed: 'Failed',
  }
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${styles[value] ?? 'bg-gray-100 text-gray-600'}`}>
      {labels[value] ?? value.replace(/_/g, ' ')}
    </span>
  )
}

export default function EmployeeReportsPage() {
  const navigate = useNavigate()
  const [reports, setReports] = useState<EmployeeReportListItem[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [aiSummaryStatus, setAiSummaryStatus] = useState('')
  const [reportType, setReportType] = useState('')
  const [page, setPage] = useState(1)

  const load = async () => {
    setLoading(true)
    setError(null)
    try {
      const response = await getMyReports({
        page,
        per_page: 10,
        search: search || undefined,
        status: status || undefined,
        ai_summary_status: aiSummaryStatus || undefined,
        report_type: reportType || undefined,
      })
      const data = response.data as unknown as { reports?: EmployeeReportListItem[]; meta?: PaginationMeta }
      setReports(Array.isArray(data?.reports) ? data.reports : [])
      setMeta(data?.meta ?? null)
    } catch {
      setError('Could not load your reports.')
      setReports([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, status, aiSummaryStatus])

  const reportTypes = useMemo(() => {
    const values = Array.from(new Set(reports.map((r) => r.report_type).filter(Boolean))) as string[]
    return values.sort()
  }, [reports])

  const onSearch = (e: React.FormEvent) => {
    e.preventDefault()
    setPage(1)
    void load()
  }

  return (
    <div className="space-y-6 p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">My Reports</h1>
          <p className="mt-1 text-sm text-gray-500">Create reports, submit for review, and track AI summary status in one place.</p>
        </div>
        <Link to={PATHS.employeeReportCreate} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
          + Create Report
        </Link>
      </div>

      <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form onSubmit={onSearch} className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search title, type, or period"
            className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
          <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            {STATUS_OPTIONS.map((option) => <option key={option.value || 'all'} value={option.value}>{option.label}</option>)}
          </select>
          <select value={aiSummaryStatus} onChange={(e) => { setAiSummaryStatus(e.target.value); setPage(1) }} className="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            {AI_STATUS_OPTIONS.map((option) => <option key={option.value || 'all-ai'} value={option.value}>{option.label}</option>)}
          </select>
          <input
            list="report-types"
            value={reportType}
            onChange={(e) => setReportType(e.target.value)}
            placeholder="Filter by report type"
            className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
          <datalist id="report-types">
            {reportTypes.map((type) => <option key={type} value={type} />)}
          </datalist>
          <div className="flex gap-2">
            <button type="submit" className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Search</button>
            <button type="button" onClick={() => { setSearch(''); setStatus(''); setAiSummaryStatus(''); setReportType(''); setPage(1); setTimeout(() => void load(), 0) }} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</button>
          </div>
        </form>
      </div>

      {error && <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>}

      <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div className="border-b border-gray-100 px-5 py-4">
          <h2 className="text-lg font-semibold text-gray-900">Report History</h2>
        </div>
        {loading ? (
          <div className="space-y-3 p-5">{Array.from({ length: 5 }).map((_, i) => <div key={i} className="h-12 animate-pulse rounded-lg bg-gray-100" />)}</div>
        ) : reports.length === 0 ? (
          <div className="p-12 text-center text-gray-500">
            <p className="font-medium">No reports found.</p>
            <p className="mt-1 text-sm">Create your first report or adjust the filters.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-100 text-sm">
              <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                  <th className="px-4 py-3">Report Title</th>
                  <th className="px-4 py-3">Report Type</th>
                  <th className="px-4 py-3">Reporting Period</th>
                  <th className="px-4 py-3">Submission Date</th>
                  <th className="px-4 py-3">Report Status</th>
                  <th className="px-4 py-3">AI Summary Status</th>
                  <th className="px-4 py-3">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 bg-white">
                {reports.map((report) => {
                  const isDraft = report.status === 'draft'
                  const canEdit = isDraft || report.status === 'revision_required'
                  const hasFeedback = report.status === 'revision_required' || report.status === 'rejected' || report.status === 'approved'
                  return (
                    <tr key={report.id} className="hover:bg-gray-50/70">
                      <td className="px-4 py-3">
                        <div className="font-medium text-gray-900">{report.title}</div>
                        <div className="text-xs text-gray-500">{report.task_title ? `Task: ${report.task_title}` : `Report #${report.id}`}</div>
                      </td>
                      <td className="px-4 py-3 text-gray-700">{report.report_type || '—'}</td>
                      <td className="px-4 py-3 text-gray-700">{report.reporting_period || '—'}</td>
                      <td className="px-4 py-3 text-gray-700">{formatDate(report.submitted_at)}</td>
                      <td className="px-4 py-3"><StatusBadge status={report.status} /></td>
                      <td className="px-4 py-3"><AiStatusBadge status={report.ai_summary_status} /></td>
                      <td className="px-4 py-3">
                        <div className="flex flex-wrap gap-2">
                          <Link to={employeeReportDetailPath(report.id)} className="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">View Report</Link>
                          <Link to={`${employeeReportDetailPath(report.id)}?tab=ai`} className="rounded-md border border-blue-200 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-50">View AI Summary</Link>
                          {canEdit && <Link to={employeeReportEditPath(report.id)} className="rounded-md border border-amber-200 px-2.5 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50">Edit Draft</Link>}
                          <button type="button" onClick={() => navigate(`${employeeReportDetailPath(report.id)}?print=1`)} className="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">Print</button>
                          {hasFeedback && <Link to={`${employeeReportDetailPath(report.id)}?tab=feedback`} className="rounded-md border border-purple-200 px-2.5 py-1 text-xs font-medium text-purple-700 hover:bg-purple-50">View Feedback</Link>}
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-gray-100 px-5 py-4 text-sm text-gray-600">
            <span>Page {meta.current_page} of {meta.last_page} · {meta.total} reports</span>
            <div className="flex gap-2">
              <button type="button" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))} className="rounded-lg border border-gray-300 px-3 py-1.5 disabled:opacity-50">Previous</button>
              <button type="button" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)} className="rounded-lg border border-gray-300 px-3 py-1.5 disabled:opacity-50">Next</button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}