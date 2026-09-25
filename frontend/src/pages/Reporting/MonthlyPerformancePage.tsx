import { useCallback } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import { getMonthlyReport } from '../../services/reportingService'
import { toNumber } from '../../utils/task'

/** Phase 9 — Monthly performance report (spec items 10, 16, endpoint #6). */
export function MonthlyPerformancePage() {
  const { year, month, setYear, setMonth, periodLabel } = useReportingPeriod()

  const report = useReportingResource(
    useCallback(() => getMonthlyReport(year, month), [year, month]),
    `monthly-report|${year}|${month}`,
    'Unable to load the monthly performance report.',
  )

  if (report.loading && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Monthly performance report">Top performers, departmental rankings and month-over-month movement.</ReportingIntro>
        <p className="page-state">Loading monthly report…</p>
      </div>
    )
  }

  if (report.error && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Monthly performance report">Top performers, departmental rankings and month-over-month movement.</ReportingIntro>
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
      <ReportingIntro title="Monthly performance report">
        Top performers, departmental rankings and month-over-month movement.
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

      {data && !data.has_data ? <p className="page-state">No performance data recorded for this month.</p> : null}

      {data?.has_data ? (
        <>
          <ReportSection title="Monthly highlights" hint={periodLabel}>
            <TaskSummaryCards
              items={[
                { label: 'Employees evaluated', value: data.summary.employees_evaluated },
                { label: 'Avg productivity', value: `${toNumber(data.summary.average_productivity)}%`, tone: 'info' as const },
                { label: 'Performance change', value: `${data.summary.performance_change === null ? '—' : `${data.summary.performance_change >= 0 ? '+' : ''}${data.summary.performance_change}pp`}`, tone: 'ok' as const },
                { label: 'Monthly achievements', value: data.summary.monthly_achievements, tone: 'info' as const },
                { label: 'Late reports', value: data.summary.late_reports, tone: 'warning' as const },
              ]}
            />
          </ReportSection>

          <div className="dashboard-grid">
            <ReportSection title="Top performer">
              {data.top_performer ? (
                <p>
                  {data.top_performer.name} ({data.top_performer.department ?? '—'}) —{' '}
                  <strong>{toNumber(data.top_performer.score)}%</strong>
                </p>
              ) : (
                <p className="page-state">No top performer recorded.</p>
              )}
            </ReportSection>
            <ReportSection title="Most improved">
              {data.most_improved ? (
                <p>
                  {data.most_improved.employee_name} ({data.most_improved.department ?? '—'}) —{' '}
                  <strong>+{data.most_improved.delta}pp</strong>
                </p>
              ) : (
                <p className="page-state">No movement recorded.</p>
              )}
            </ReportSection>
          </div>

          <ReportSection title="Department rankings" hint={`${data.department_rankings.length} departments evaluated`}>
            {data.department_rankings.length === 0 ? (
              <p className="page-state">No department rankings for this period.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Department</th>
                      <th scope="col">Employees evaluated</th>
                      <th scope="col">Average score</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.department_rankings.map((row) => (
                      <tr key={String(row.department_id)}>
                        <td data-label="Department">{row.department_name}</td>
                        <td data-label="Evaluated">{row.employees_evaluated}</td>
                        <td data-label="Avg score">{toNumber(row.average_score)}%</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </ReportSection>
        </>
      ) : null}
    </div>
  )
}