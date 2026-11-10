import { useCallback, useState } from 'react'
import {
  Badge,
  EmptyState,
  ErrorPanel,
  LoadingPanel,
  Panel,
  StatusBadge,
  Table,
  Td,
  Th,
  dateTime,
  humanise,
  shortDate,
} from '../../../components/admin/AdminUi'
import { useReportingResource } from '../../../hooks/useReporting'
import { getLateReport, getPendingReport } from '../../../services/reportingService'
import type { LateReport, PendingReport, ReportingFilters } from '../../../types/reporting'
import { MiniStat, numOrNa, pctOrNa } from './hubShared'

type SubmissionView = 'late' | 'pending'

const CATEGORY_LABELS: Record<string, { label: string; tone: string }> = {
  not_submitted: { label: 'Not yet submitted', tone: 'danger' },
  pending_review: { label: 'Pending review', tone: 'warn' },
  revision_required: { label: 'Returned for revision', tone: 'warn' },
  approved: { label: 'Approved', tone: 'ok' },
  closed: { label: 'Closed', tone: 'neutral' },
}

interface SubmissionMonitoringTabProps {
  filters: ReportingFilters
  periodLabel: string
}

/**
 * TAB 5 — Submission monitoring. Late and pending are two views of the same
 * report workflow; a report awaiting approval is never labelled unsubmitted.
 */
export function SubmissionMonitoringTab({ filters, periodLabel }: SubmissionMonitoringTabProps) {
  const [view, setView] = useState<SubmissionView>('late')

  const late = useReportingResource<LateReport | null>(
    useCallback(
      () => (view === 'late' ? getLateReport(filters) : Promise.resolve({ data: null })),
      [filters, view],
    ),
    `hub-late-${JSON.stringify(filters)}-${view}`,
    'Unable to load the late submissions report.',
  )

  const pending = useReportingResource<PendingReport | null>(
    useCallback(
      () => (view === 'pending' ? getPendingReport(filters) : Promise.resolve({ data: null })),
      [filters, view],
    ),
    `hub-pending-${JSON.stringify(filters)}-${view}`,
    'Unable to load the pending reports report.',
  )

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Submission monitoring views">
        <button
          type="button"
          role="tab"
          aria-selected={view === 'late'}
          onClick={() => setView('late')}
          className={`px-4 py-2 rounded-lg text-sm font-bold border transition ${
            view === 'late'
              ? 'bg-jpc-600 text-white border-jpc-600'
              : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
          }`}
        >
          Late submissions
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={view === 'pending'}
          onClick={() => setView('pending')}
          className={`px-4 py-2 rounded-lg text-sm font-bold border transition ${
            view === 'pending'
              ? 'bg-jpc-600 text-white border-jpc-600'
              : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
          }`}
        >
          Pending reports
        </button>
        <Badge tone="info">{periodLabel}</Badge>
      </div>

      {view === 'late' ? (
        <Panel
          title="Late submissions"
          description="Reports submitted after the effective deadline, plus overdue reports that have not been submitted."
          actions={
            late.data ? (
              <Badge tone={late.data.summary.late_reports > 0 ? 'danger' : 'ok'}>
                {`${numOrNa(late.data.summary.late_reports)} late`}
              </Badge>
            ) : null
          }
        >
          {late.loading && !late.data ? (
            <LoadingPanel label="Loading late submissions" rows={3} />
          ) : late.error ? (
            <ErrorPanel message={late.error} onRetry={late.refresh} />
          ) : !late.data || !late.data.has_data ? (
            <EmptyState
              title="No late submissions"
              message="No report in this period was submitted after its deadline or is overdue."
            />
          ) : (
            <div className="space-y-3">
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <MiniStat label="Late reports" value={numOrNa(late.data.summary.late_reports)} />
                <MiniStat label="Total reports" value={numOrNa(late.data.summary.total_reports)} />
                <MiniStat label="Late submission rate" value={pctOrNa(late.data.summary.late_submission_rate)} />
                <MiniStat
                  label="Late employees"
                  value={numOrNa(late.data.summary.late_employees)}
                  hint={`${numOrNa(late.data.summary.late_departments)} departments affected`}
                />
              </div>

              <Table>
                <thead>
                  <tr>
                    <Th>Employee</Th>
                    <Th>Department</Th>
                    <Th>Report title</Th>
                    <Th>Deadline</Th>
                    <Th>Submitted</Th>
                    <Th className="text-right">Days late</Th>
                    <Th>Status</Th>
                  </tr>
                </thead>
                <tbody>
                  {late.data.reports.map((row) => (
                    <tr key={row.id} className="border-b border-slate-100 last:border-0">
                      <Td className="font-semibold text-slate-800">{row.employee}</Td>
                      <Td className="text-xs text-slate-600">{row.department ?? '—'}</Td>
                      <Td>
                        <p className="text-slate-800">{row.title}</p>
                        {row.task ? <p className="text-[11px] text-slate-400">{row.task}</p> : null}
                      </Td>
                      <Td className="text-xs text-slate-500">{shortDate(row.deadline)}</Td>
                      <Td className="text-xs text-slate-500">
                        {row.submitted_at ? dateTime(row.submitted_at) : 'Not submitted'}
                      </Td>
                      <Td className="text-right tabular-nums">
                        {row.is_outstanding ? (
                          <Badge tone="danger">Outstanding</Badge>
                        ) : (
                          numOrNa(row.days_late)
                        )}
                      </Td>
                      <Td>
                        <StatusBadge
                          status={row.is_outstanding ? 'overdue' : row.status}
                          label={row.is_outstanding ? 'Overdue — not submitted' : row.status_label}
                        />
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
              <p className="text-[11px] text-slate-500">
                Lateness uses the same rule as the Reporting Hub KPI: the report's own deadline, falling back to the
                linked task due date. Reports never submitted are shown separately as outstanding.
              </p>
            </div>
          )}
        </Panel>
      ) : (
        <Panel
          title="Pending reports"
          description="Every report awaiting the next workflow action, grouped by its real workflow status."
          actions={
            pending.data ? (
              <Badge tone="neutral">{`${numOrNa(pending.data.summary.pending_reports)} pending`}</Badge>
            ) : null
          }
        >
          {pending.loading && !pending.data ? (
            <LoadingPanel label="Loading pending reports" rows={3} />
          ) : pending.error ? (
            <ErrorPanel message={pending.error} onRetry={pending.refresh} />
          ) : !pending.data || !pending.data.has_data ? (
            <EmptyState
              title="No pending reports"
              message="No report is awaiting a review, revision or approval action in this period."
            />
          ) : (
            <div className="space-y-3">
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <MiniStat label="Pending reports" value={numOrNa(pending.data.summary.pending_reports)} />
                <MiniStat
                  label="Awaiting review"
                  value={numOrNa(pending.data.summary.awaiting_review)}
                  hint="Submitted or under review — not unsubmitted"
                />
                <MiniStat
                  label="Returned for revision"
                  value={numOrNa(pending.data.summary.revision_required)}
                />
                <MiniStat
                  label="Employees"
                  value={numOrNa(pending.data.summary.pending_employees)}
                  hint={`${numOrNa(pending.data.summary.pending_departments)} departments`}
                />
              </div>

              <Table>
                <thead>
                  <tr>
                    <Th>Employee</Th>
                    <Th>Department</Th>
                    <Th>Report title</Th>
                    <Th>Deadline</Th>
                    <Th>Status</Th>
                    <Th>Days remaining / overdue</Th>
                    <Th>Required action</Th>
                  </tr>
                </thead>
                <tbody>
                  {pending.data.reports.map((row) => {
                    const category = CATEGORY_LABELS[row.category] ?? CATEGORY_LABELS.closed
                    return (
                      <tr key={row.id} className="border-b border-slate-100 last:border-0">
                        <Td className="font-semibold text-slate-800">{row.employee}</Td>
                        <Td className="text-xs text-slate-600">{row.department ?? '—'}</Td>
                        <Td className="text-slate-800">{row.title}</Td>
                        <Td className="text-xs text-slate-500">{shortDate(row.deadline)}</Td>
                        <Td>
                          <div className="space-y-1">
                            <Badge tone={category.tone}>{category.label}</Badge>
                            <p className="text-[10px] text-slate-400">{row.status_label}</p>
                          </div>
                        </Td>
                        <Td className="text-xs">
                          {row.is_overdue ? (
                            <span className="font-bold text-red-600">
                              {row.days_overdue === null ? 'Overdue' : `${row.days_overdue} days overdue`}
                            </span>
                          ) : row.days_remaining === null ? (
                            '—'
                          ) : (
                            <span className="text-slate-600">{row.days_remaining} days remaining</span>
                          )}
                        </Td>
                        <Td className="text-xs text-slate-600">{humanise(row.required_action)}</Td>
                      </tr>
                    )
                  })}
                </tbody>
              </Table>
              <p className="text-[11px] text-slate-500">
                A report that is submitted or awaiting review stays in this list as pending review — it is never
                classified as unsubmitted. Only draft reports count as not yet submitted.
              </p>
            </div>
          )}
        </Panel>
      )}
    </div>
  )
}
