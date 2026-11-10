import { useCallback } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import { getManagementSummary } from '../../services/reportingService'
import { toNumber } from '../../utils/task'

/** Phase 9 — Executive management summary (spec item 20, endpoint #15). */
export function ManagementSummaryPage() {
  const { year, month, setYear, setMonth, periodLabel } = useReportingPeriod()

  const report = useReportingResource(
    useCallback(() => getManagementSummary({ year, month }), [year, month]),
    `mgmt-summary|${year}|${month}`,
    'Unable to load the management summary.',
  )

  if (report.loading && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Executive management summary">Grounded institutional narrative plus monthly performance metrics.</ReportingIntro>
        <p className="page-state">Loading management summary…</p>
      </div>
    )
  }

  if (report.error && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Executive management summary">Grounded institutional narrative plus monthly performance metrics.</ReportingIntro>
        <div className="page-state page-state--denied" role="alert">
          <p>{report.error}</p>
          <button type="button" className="button button--secondary" onClick={report.refresh}>Retry</button>
        </div>
      </div>
    )
  }

  const data = report.data

  return (
    <div className="page">
      <ReportingIntro title="Executive management summary">
        Grounded institutional narrative plus monthly performance metrics.
      </ReportingIntro>

      <div className="dashboard-filters-wrap">
        <div className="task-filters">
          <div className="task-filters__field task-filters__field--grow">
            <span className="task-filters__label">Period</span>
            <span>{periodLabel}</span>
          </div>
          <PeriodFilter year={year} month={month} onYearChange={setYear} onMonthChange={setMonth} />
        </div>
      </div>

      {data ? (
        <>
          <ReportSection title={data.period_label} hint="Deterministic, data-grounded narrative">
            <p>{data.narrative}</p>
            {data.key_takeaways.length > 0 ? (
              <ul className="stack-list">
                {data.key_takeaways.map((takeaway) => (
                  <li key={takeaway}>{takeaway}</li>
                ))}
              </ul>
            ) : null}
          </ReportSection>

          {data.has_data && data.metrics ? (
            <>
              <ReportSection title="Monthly metrics">
                <TaskSummaryCards
                  items={[
                    { label: 'Avg productivity', value: `${toNumber(data.metrics.average_productivity)}%`, tone: 'info' as const },
                    { label: 'Tasks completed', value: data.metrics.tasks_completed, tone: 'ok' as const },
                    { label: 'Tasks assigned', value: data.metrics.tasks_assigned },
                    { label: 'Task completion', value: `${toNumber(data.metrics.task_completion_rate)}%`, tone: 'info' as const },
                    { label: 'Reports submitted', value: data.metrics.reports_submitted },
                    { label: 'Late reports', value: data.metrics.late_reports, tone: 'warning' as const },
                    { label: 'Data quality score', value: data.metrics.data_quality_score === null ? 'N/A' : `${toNumber(data.metrics.data_quality_score)}%`, tone: 'info' as const },
                  ]}
                />
              </ReportSection>

              {data.change ? (
                <ReportSection title={`Change vs ${data.change.previous_period ?? 'previous period'}`}>
                  <TaskSummaryCards
                    items={[
                      { label: 'Productivity change', value: `${data.change.average_productivity_change === null ? '—' : (data.change.average_productivity_change >= 0 ? '+' : '') + data.change.average_productivity_change}pp`, tone: 'ok' as const },
                      { label: 'Reports change', value: `${data.change.reports_change >= 0 ? '+' : ''}${data.change.reports_change}` },
                      { label: 'Late change', value: `${data.change.late_change >= 0 ? '+' : ''}${data.change.late_change}`, tone: 'warning' as const },
                    ]}
                  />
                </ReportSection>
              ) : null}

              {data.distribution ? (
                <ReportSection title="Performance distribution" hint={`${Object.values(data.distribution).reduce((sum, count) => sum + count, 0)} faculty evaluated`}>
                  <TaskSummaryCards
                    items={Object.entries(data.distribution).map(([band, count]) => ({
                      label: band,
                      value: count,
                    }))}
                  />
                </ReportSection>
              ) : null}
            </>
          ) : null}

          <div className="dashboard-grid">
            {data.top_performer ? (
              <ReportSection title="Top performer">
                <p>
                  {data.top_performer.name} ({data.top_performer.department ?? '—'}) —{' '}
                  <strong>{toNumber(data.top_performer.score)}%</strong>
                </p>
              </ReportSection>
            ) : null}
            {data.top_department ? (
              <ReportSection title="Top department">
                <p>
                  {data.top_department.department_name} —{' '}
                  <strong>{toNumber(data.top_department.average_score)}%</strong>
                </p>
              </ReportSection>
            ) : null}
          </div>
        </>
      ) : null}
    </div>
  )
}