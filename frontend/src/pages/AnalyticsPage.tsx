import { useCallback, useState } from 'react'
import { ReportFilters } from '../components/reports/ReportFilters'
import { ReportSection } from '../components/reports/ReportSection'
import { BarChart } from '../components/reports/BarChart'
import { TargetVsActualCard } from '../components/dashboard/TargetVsActualCard'
import { ExportButtons } from '../components/reports/ExportButtons'
import { TaskSummaryCards } from '../components/tasks/TaskSummaryCards'
import { useReportExport, useReportResource } from '../hooks/useReports'
import {
  getProgressTrend,
  getReportOptions,
  getTargetVsActual,
  getTaskCompletionReport,
} from '../services/reportService'
import { toNumber } from '../utils/task'
import type { ReportFilters as ReportFilterParams } from '../types/report'

function trendCards(trend: { total: number[]; completed: number[]; pending: number[]; overdue: number[]; completion_rates: number[]; month_over_month_pp: number }) {
  const latest = trend.total.length - 1
  const current = Math.max(latest, 0)

  return [
    { label: 'Total (latest)', value: trend.total[current] ?? 0 },
    { label: 'Completed (latest)', value: trend.completed[current] ?? 0, tone: 'ok' as const },
    { label: 'Pending (latest)', value: trend.pending[current] ?? 0, tone: 'warning' as const },
    { label: 'Overdue (latest)', value: trend.overdue[current] ?? 0, tone: 'error' as const },
    { label: 'Rate (latest)', value: `${toNumber(trend.completion_rates[current])}%`, tone: 'info' as const },
    {
      label: 'Month-over-month',
      value: `${trend.month_over_month_pp > 0 ? '+' : ''}${trend.month_over_month_pp}pp`,
      tone: trend.month_over_month_pp >= 0 ? 'ok' as const : 'error' as const,
    },
  ]
}

export default function AnalyticsPage() {
  const [filters, setFilters] = useState<ReportFilterParams>({})
  const [refreshVersion, setRefreshVersion] = useState(0)

  const options = useReportResource(
    useCallback(() => getReportOptions(), []),
    `analytics-options|${refreshVersion}`,
    'Unable to load analytics options.',
  )

  const trend = useReportResource(
    useCallback(() => getProgressTrend(filters), [filters]),
    `trend|${refreshVersion}|${JSON.stringify(filters)}`,
    'Unable to load the progress trend.',
  )

  const taskCompletion = useReportResource(
    useCallback(() => getTaskCompletionReport(filters), [filters]),
    `task-completion-chart|${refreshVersion}|${JSON.stringify(filters)}`,
    'Unable to load the task completion chart.',
  )

  const targetVsActual = useReportResource(
    useCallback(() => getTargetVsActual(filters), [filters]),
    `target-vs-actual|${refreshVersion}|${JSON.stringify(filters)}`,
    'Unable to load target vs actual.',
  )

  const { exporting, exportError, handleExport } = useReportExport()

  const handleFilters = (next: ReportFilterParams) => setFilters(next)
  const bumpRefresh = () => setRefreshVersion((version) => version + 1)

  return (
    <div className="page">
      <div className="page__intro">
        <h2>Analytics</h2>
        <p>
          Month-over-month progress, completion rates and attainment across your
          reporting scope.
        </p>
      </div>

      {exportError ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{exportError}</p>
        </div>
      ) : null}

      <ReportFilters
        value={filters}
        options={options.data}
        optionsLoading={options.loading}
        onChange={handleFilters}
        onRefresh={bumpRefresh}
        refreshing={trend.loading && trend.data !== null}
        lastUpdated={trend.updatedAt}
      />

      {trend.loading && !trend.data ? (
        <p className="page-state">Loading analytics…</p>
      ) : null}

      {trend.error && !trend.data ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{trend.error}</p>
          <button type="button" className="button button--secondary" onClick={trend.refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {trend.data ? (
        <>
          <ReportSection
            title="Progress trend"
            hint={trend.data.period}
            action={
              <ExportButtons
                report="task-completion"
                filters={filters}
                onExport={handleExport}
                disabled={exporting}
              />
            }
          >
            <TaskSummaryCards items={trendCards(trend.data)} />
            <BarChart
              title="Completed versus pending assignments by month"
              labels={trend.data.labels}
              completed={trend.data.completed}
              pending={trend.data.pending}
              rates={trend.data.completion_rates}
              monthOverMonthPp={trend.data.month_over_month_pp}
            />
          </ReportSection>
        </>
      ) : null}

      {taskCompletion.data ? (
        <ReportSection
          title="Task completion by month"
          hint={taskCompletion.data.period}
          action={
            <ExportButtons
              report="task-completion"
              filters={filters}
              onExport={handleExport}
              disabled={exporting}
            />
          }
        >
          <TaskSummaryCards
            items={[
              { label: 'Total', value: taskCompletion.data.total_tasks },
              { label: 'Completed', value: taskCompletion.data.completed_tasks, tone: 'ok' as const },
              { label: 'Pending', value: taskCompletion.data.pending_tasks, tone: 'warning' as const },
              { label: 'Overdue', value: taskCompletion.data.overdue_tasks, tone: 'error' as const },
              { label: 'On time', value: taskCompletion.data.on_time, tone: 'ok' as const },
              { label: 'Late', value: taskCompletion.data.late, tone: 'warning' as const },
            ]}
          />
          <BarChart
            title="Completed versus pending tasks by month"
            labels={taskCompletion.data.chart.labels}
            completed={taskCompletion.data.chart.completed}
            pending={taskCompletion.data.chart.pending}
            rates={taskCompletion.data.chart.completion_rates}
            monthOverMonthPp={taskCompletion.data.chart.month_over_month_pp}
          />
        </ReportSection>
      ) : null}

      <div className="dashboard-grid">
        <TargetVsActualCard data={targetVsActual.data} />
      </div>
    </div>
  )
}