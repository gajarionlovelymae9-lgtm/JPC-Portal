import { useCallback } from 'react'
import {
  Badge,
  EmptyState,
  ErrorPanel,
  LoadingPanel,
  Panel,
  Table,
  Td,
  Th,
} from '../../../components/admin/AdminUi'
import { BarChart } from '../../../components/reports/BarChart'
import { TopPerformersPanel } from '../../../components/reports/TopPerformersPanel'
import { useReportingResource } from '../../../hooks/useReporting'
import { useReportResource } from '../../../hooks/useReports'
import { getMonthlyReport, getPerformanceTrend } from '../../../services/reportingService'
import { getTaskCompletionReport } from '../../../services/reportService'
import type {
  MonthlyReport,
  PerformanceTrendPoint,
  ReportingFilters,
} from '../../../types/reporting'
import {
  MiniStat,
  numOrNa,
  pctOrNa,
  monthlyYearMonth,
  toReportFilters,
  type ScopedReport,
} from './hubShared'

interface MonthlyPerformanceTabProps {
  filters: ReportingFilters
  scoped: ScopedReport | null
  periodLabel: string
}

/**
 * TAB 4 — Monthly performance: highlights, departmental rankings, true
 * month-over-month movement and the six-month productivity trend.
 */
export function MonthlyPerformanceTab({ filters, scoped, periodLabel }: MonthlyPerformanceTabProps) {
  const { year, month } = monthlyYearMonth(filters)

  const monthly = useReportingResource<MonthlyReport>(
    useCallback(() => getMonthlyReport(year, month), [year, month]),
    `hub-monthly-${year}-${month}`,
    'Unable to load the monthly performance report.',
  )

  const trend = useReportingResource<PerformanceTrendPoint[]>(
    useCallback(() => getPerformanceTrend(6, filters), [filters]),
    `hub-monthly-trend-${JSON.stringify(filters)}`,
    'Unable to load the productivity trend.',
  )

  // Lifted from the legacy Reports page: the manager task-completion chart
  // (completed vs pending by month) and the top-performers leaderboard share
  // the same /reports endpoints, so the KPI maths stays identical everywhere.
  const reportFilters = toReportFilters(filters)

  const taskCompletion = useReportResource(
    useCallback(() => getTaskCompletionReport(reportFilters), [reportFilters]),
    `hub-task-completion-chart|${JSON.stringify(reportFilters)}`,
    'Unable to load the task completion chart.',
  )

  const data = monthly.data
  const trendPoints = trend.data ?? []

  const scopedTotals = scoped
    ? scoped.kind === 'institution'
      ? {
          assigned: scoped.data.summary.tasks_assigned,
          completed: scoped.data.summary.tasks_completed,
          submitted: scoped.data.summary.reports_submitted,
          completion: scoped.data.summary.task_completion_rate,
        }
      : scoped.kind === 'department'
        ? {
            assigned: scoped.data.metrics.tasks_assigned,
            completed: scoped.data.metrics.tasks_completed,
            submitted: scoped.data.metrics.reports_submitted,
            completion: scoped.data.metrics.task_completion_rate,
          }
        : {
            assigned: scoped.data.metrics.tasks_assigned,
            completed: scoped.data.metrics.tasks_completed,
            submitted: scoped.data.metrics.reports_submitted,
            completion: scoped.data.metrics.task_completion_rate,
          }
    : null

  if (monthly.loading && !data) {
    return <LoadingPanel label="Loading monthly performance" rows={3} />
  }

  if (monthly.error) {
    return <ErrorPanel message={monthly.error} onRetry={monthly.refresh} />
  }

  return (
    <div className="space-y-4">
      <Panel
        title="Monthly performance summary"
        description={`Reported month: ${periodLabel}.`}
        actions={<Badge tone="info">{`${year}-${String(month).padStart(2, '0')}`}</Badge>}
      >
        {!data || !data.has_data ? (
          <EmptyState
            title="No monthly performance data"
            message="No performance snapshots were recorded for this month, so rankings are not shown."
          />
        ) : (
          <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
            <MiniStat label="Employees evaluated" value={numOrNa(data.summary.employees_evaluated)} />
            <MiniStat label="Average productivity" value={pctOrNa(data.summary.average_productivity)} />
            <MiniStat
              label="Month-over-month"
              value={
                data.summary.performance_change === null
                  ? 'N/A'
                  : `${data.summary.performance_change > 0 ? '+' : ''}${data.summary.performance_change} pp`
              }
              hint="Change against the prior month"
            />
            <MiniStat label="Monthly achievements" value={numOrNa(data.summary.monthly_achievements)} />
            <MiniStat label="Late reports" value={numOrNa(data.summary.late_reports)} />
          </div>
        )}

        {scopedTotals ? (
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
            <MiniStat label="Tasks assigned" value={numOrNa(scopedTotals.assigned)} />
            <MiniStat label="Tasks completed" value={numOrNa(scopedTotals.completed)} />
            <MiniStat label="Task completion" value={pctOrNa(scopedTotals.completion)} />
            <MiniStat label="Reports submitted" value={numOrNa(scopedTotals.submitted)} />
          </div>
        ) : null}
      </Panel>

      {data?.has_data ? (
        <>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Panel title="Top-performing employee" description="Highest recorded monthly score.">
              {data.top_performer ? (
                <div className="space-y-1">
                  <p className="text-lg font-black text-slate-900">{data.top_performer.name}</p>
                  <p className="text-xs text-slate-500">{data.top_performer.department ?? 'No department'}</p>
                  <p className="text-sm font-bold text-emerald-600 tabular-nums">
                    {pctOrNa(data.top_performer.score)}
                  </p>
                </div>
              ) : (
                <EmptyState title="No top performer" message="Not enough scored records for this month." />
              )}
            </Panel>

            <Panel title="Most improved" description="Largest month-over-month gain.">
              {data.most_improved ? (
                <div className="space-y-1">
                  <p className="text-lg font-black text-slate-900">{data.most_improved.employee_name}</p>
                  <p className="text-xs text-slate-500">{data.most_improved.department ?? 'No department'}</p>
                  <p className="text-sm font-bold text-emerald-600 tabular-nums">
                    {`+${data.most_improved.delta} pp`}
                  </p>
                </div>
              ) : (
                <EmptyState title="No movement recorded" message="No prior-month baseline is available." />
              )}
            </Panel>
          </div>

          <Panel
            title="Department rankings"
            description="Average recorded productivity per department for the selected month."
            actions={<Badge tone="neutral">{`${data.department_rankings.length} evaluated`}</Badge>}
          >
            {data.department_rankings.length === 0 ? (
              <EmptyState
                title="No department rankings"
                message="Performance data is not sufficient to rank departments for this month."
              />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Rank</Th>
                    <Th>Department</Th>
                    <Th className="text-right">Employees evaluated</Th>
                    <Th className="text-right">Average score</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.department_rankings.map((row, index) => (
                    <tr key={row.department_id ?? row.department_name} className="border-b border-slate-100 last:border-0">
                      <Td className="text-slate-500 tabular-nums">{index + 1}</Td>
                      <Td className="font-semibold text-slate-800">{row.department_name}</Td>
                      <Td className="text-right tabular-nums">{numOrNa(row.employees_evaluated)}</Td>
                      <Td className="text-right tabular-nums">{pctOrNa(row.average_score)}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </Panel>
        </>
      ) : null}

      <Panel
        title="Productivity trend"
        description="Six most recent recorded months."
      >
        {trend.loading && !trend.data ? (
          <LoadingPanel label="Loading productivity trend" rows={2} />
        ) : trend.error ? (
          <ErrorPanel message={trend.error} onRetry={trend.refresh} />
        ) : trendPoints.length === 0 ? (
          <EmptyState
            title="No productivity trend"
            message="No scored performance snapshots exist for the trend window."
          />
        ) : (
          <div className="flex items-end gap-3 overflow-x-auto pb-1">
            {trendPoints.map((point) => {
              const value = point.productivity_score
              const height = value === null ? 4 : Math.max(6, Math.min(100, value))
              return (
                <div key={`${point.year}-${point.month}`} className="min-w-[64px] text-center">
                  <div className="h-28 flex items-end justify-center">
                    <div
                      className="w-8 rounded-t-lg bg-jpc-500/80"
                      style={{ height: `${height}%` }}
                      title={`${point.period}: ${pctOrNa(value)}`}
                    />
                  </div>
                  <p className="mt-1 text-[10px] font-bold text-slate-600">{point.period}</p>
                  <p className="text-[11px] font-black text-slate-900 tabular-nums">{pctOrNa(value)}</p>
                </div>
              )
            })}
          </div>
        )}
      </Panel>

      <Panel
        title="Completed versus pending tasks by month"
        description="Month-over-month task throughput from the task completion report. The highlighted figure is the completion-rate change against the prior month."
        actions={
          taskCompletion.data?.chart.month_over_month_pp !== undefined ? (
            <Badge
              tone={taskCompletion.data.chart.month_over_month_pp >= 0 ? 'ok' : 'danger'}
            >
              {`${taskCompletion.data.chart.month_over_month_pp > 0 ? '+' : ''}${taskCompletion.data.chart.month_over_month_pp} pp vs prior month`}
            </Badge>
          ) : null
        }
      >
        {taskCompletion.loading && !taskCompletion.data ? (
          <LoadingPanel label="Loading task completion chart" rows={2} />
        ) : taskCompletion.error ? (
          <ErrorPanel message={taskCompletion.error} onRetry={taskCompletion.refresh} />
        ) : taskCompletion.data ? (
          <BarChart
            title="Completed versus pending tasks by month"
            labels={taskCompletion.data.chart.labels}
            completed={taskCompletion.data.chart.completed}
            pending={taskCompletion.data.chart.pending}
            rates={taskCompletion.data.chart.completion_rates}
            monthOverMonthPp={taskCompletion.data.chart.month_over_month_pp}
          />
        ) : (
          <EmptyState
            title="No task chart data"
            message="No task records exist for the selected period."
          />
        )}
      </Panel>

      <div className="dashboard-grid">
        <TopPerformersPanel scope={reportFilters} refreshVersion={0} />
      </div>
    </div>
  )
}
