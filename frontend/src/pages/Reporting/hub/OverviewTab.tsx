import { useCallback } from 'react'
import {
  Badge,
  EmptyState,
  ErrorPanel,
  LoadingPanel,
  Panel,
  StatusBadge,
} from '../../../components/admin/AdminUi'
import { useReportingResource } from '../../../hooks/useReporting'
import { getManagementSummary } from '../../../services/reportingService'
import type { ManagementSummary, ReportingFilters } from '../../../types/reporting'
import { MiniStat, numOrNa, pctOrNa, type ScopedReport } from './hubShared'

interface OverviewTabProps {
  filters: ReportingFilters
  scoped: ScopedReport | null
  loading: boolean
  error: string | null
  onRetry: () => void
  canInstitution: boolean
  periodLabel: string
}

/**
 * TAB 1 — Overview: the scoped institutional/department/employee snapshot plus
 * the grounded management summary.
 */
export function OverviewTab({
  filters,
  scoped,
  loading,
  error,
  onRetry,
  canInstitution,
  periodLabel,
}: OverviewTabProps) {
  const management = useReportingResource<ManagementSummary | null>(
    useCallback(
      () =>
        canInstitution
          ? getManagementSummary(filters)
          : Promise.resolve({ data: null }),
      [canInstitution, filters],
    ),
    `hub-summary-${JSON.stringify(filters)}`,
    'Unable to load the management summary.',
  )

  if (loading && !scoped) {
    return <LoadingPanel label="Loading performance overview" rows={3} />
  }

  if (error) {
    return <ErrorPanel message={error} onRetry={onRetry} />
  }

  if (!scoped) {
    return (
      <EmptyState
        title="No overview data"
        message="No performance records are available for the selected scope and period."
      />
    )
  }

  const metrics = scoped.kind === 'institution'
    ? {
        totalTasks: scoped.data.summary.tasks_assigned,
        completed: scoped.data.summary.tasks_completed,
        pending: Math.max(0, scoped.data.summary.tasks_assigned - scoped.data.summary.tasks_completed),
        reportsSubmitted: scoped.data.summary.reports_submitted,
        late: scoped.data.summary.late_reports,
        onTime: scoped.data.summary.on_time_rate,
        employees: scoped.data.summary.total_employees,
        departments: scoped.data.summary.total_departments,
        pendingReports: scoped.data.summary.pending_reports,
        approval: scoped.data.summary.approval_rate,
        target: scoped.data.summary.target_achievement_rate,
        completion: scoped.data.summary.task_completion_rate,
        period: scoped.data.period,
      }
    : scoped.kind === 'department'
      ? {
          totalTasks: scoped.data.metrics.tasks_assigned,
          completed: scoped.data.metrics.tasks_completed,
          pending: Math.max(0, scoped.data.metrics.tasks_assigned - scoped.data.metrics.tasks_completed),
          reportsSubmitted: scoped.data.metrics.reports_submitted,
          late: scoped.data.metrics.late_reports,
          onTime: scoped.data.metrics.on_time_rate,
          employees: scoped.data.department.employee_count,
          departments: null,
          pendingReports: null,
          approval: scoped.data.metrics.approval_rate,
          target: scoped.data.metrics.target_achievement_rate,
          completion: scoped.data.metrics.task_completion_rate,
          period: scoped.data.period,
        }
      : {
          totalTasks: scoped.data.metrics.tasks_assigned,
          completed: scoped.data.metrics.tasks_completed,
          pending: Math.max(0, scoped.data.metrics.tasks_assigned - scoped.data.metrics.tasks_completed),
          reportsSubmitted: scoped.data.metrics.reports_submitted,
          late: null,
          onTime: scoped.data.metrics.on_time_submission_rate,
          employees: null,
          departments: null,
          pendingReports: null,
          approval: scoped.data.metrics.approval_rate,
          target: scoped.data.metrics.target_achievement_rate,
          completion: scoped.data.metrics.task_completion_rate,
          period: scoped.data.period,
        }

  const completionPercent = metrics.completion === null
    ? null
    : Math.max(0, Math.min(100, metrics.completion))

  const summary = management.data

  return (
    <div className="space-y-4">
      <Panel
        title="Institutional performance summary"
        description={`Key aggregates for ${periodLabel}.`}
        actions={<StatusBadge status="live" label="Live data" />}
      >
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <MiniStat label="Total tasks" value={numOrNa(metrics.totalTasks)} />
          <MiniStat label="Completed tasks" value={numOrNa(metrics.completed)} />
          <MiniStat label="Pending tasks" value={numOrNa(metrics.pending)} />
          <MiniStat label="Reports submitted" value={numOrNa(metrics.reportsSubmitted)} />
          <MiniStat
            label="Late submissions"
            value={metrics.late === null ? 'N/A' : numOrNa(metrics.late)}
            hint={scoped.kind === 'employee' ? 'Not available for the employee scope' : undefined}
          />
          <MiniStat label="On-time submission rate" value={pctOrNa(metrics.onTime)} />
          <MiniStat label="Approval rate" value={pctOrNa(metrics.approval)} />
          <MiniStat label="Target achievement" value={pctOrNa(metrics.target)} />
          {metrics.employees !== null ? (
            <MiniStat
              label={scoped.kind === 'institution' ? 'Total employees' : 'Employees'}
              value={numOrNa(metrics.employees)}
              hint={
                scoped.kind === 'institution'
                  ? `${numOrNa(scoped.data.summary.active_employees)} active · ${numOrNa(metrics.departments)} departments`
                  : undefined
              }
            />
          ) : null}
          {scoped.kind === 'institution' ? (
            <MiniStat
              label="Pending reports"
              value={numOrNa(scoped.data.summary.pending_reports)}
              hint={`${numOrNa(scoped.data.summary.awaiting_action)} awaiting action · ${numOrNa(scoped.data.summary.awaiting_submission)} to submit`}
            />
          ) : null}
        </div>

        <div className="mt-4 space-y-2">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">
              Task completion rate
            </p>
            <p className="text-sm font-black text-slate-900 tabular-nums">{pctOrNa(metrics.completion)}</p>
          </div>
          <div
            role="progressbar"
            aria-label={`Task completion rate for ${metrics.period}`}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={completionPercent === null ? undefined : Math.round(completionPercent)}
            className="h-3 w-full rounded-full bg-slate-100 overflow-hidden"
          >
            <div
              className="h-full rounded-full bg-emerald-500"
              style={{ width: `${completionPercent ?? 0}%` }}
            />
          </div>
          <p className="text-[11px] text-slate-500">
            {completionPercent === null
              ? 'No tasks were assigned in this period, so there is nothing to measure.'
              : `${numOrNa(metrics.completed)} of ${numOrNa(metrics.totalTasks)} tasks completed. Cancelled assignments are excluded.`}
          </p>
        </div>
      </Panel>

      {canInstitution ? (
        <Panel
          title="Management Summary"
          description="Deterministic narrative built from the same aggregates as the reports."
          actions={
            summary ? (
              summary.change && summary.change.average_productivity_change !== null ? (
                <Badge tone={summary.change.average_productivity_change >= 0 ? 'ok' : 'danger'}>
                  {`${summary.change.average_productivity_change > 0 ? '+' : ''}${summary.change.average_productivity_change.toFixed(1)} ppt vs ${summary.change.previous_period}`}
                </Badge>
              ) : (
                <Badge tone="neutral">N/A — no prior-period baseline</Badge>
              )
            ) : null
          }
        >
          {management.loading && !summary ? (
            <LoadingPanel label="Loading management summary" rows={2} />
          ) : management.error ? (
            <ErrorPanel message={management.error} onRetry={management.refresh} />
          ) : summary ? (
            <div className="space-y-3">
              <p className="text-sm leading-relaxed text-slate-700">{summary.narrative}</p>
              {summary.key_takeaways.length > 0 ? (
                <ul className="grid gap-1.5 sm:grid-cols-2">
                  {summary.key_takeaways.map((takeaway) => (
                    <li key={takeaway} className="flex items-start gap-2 text-xs text-slate-600">
                      <span aria-hidden="true" className="mt-1 h-1.5 w-1.5 rounded-full bg-jpc-500" />
                      <span>{takeaway}</span>
                    </li>
                  ))}
                </ul>
              ) : null}
              <div className="flex flex-wrap gap-2 text-[11px] text-slate-500">
                <Badge tone="info">{summary.period_label}</Badge>
                <Badge tone="neutral">{summary.period_type}</Badge>
                {summary.change ? (
                  <Badge tone="neutral">
                    Reports change {summary.change.reports_change >= 0 ? '+' : ''}
                    {summary.change.reports_change}
                  </Badge>
                ) : null}
              </div>
            </div>
          ) : (
            <EmptyState title="No summary available" message="Apply a different reporting period." />
          )}
        </Panel>
      ) : null}
    </div>
  )
}
