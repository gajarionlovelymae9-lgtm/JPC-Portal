import { useCallback } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import { getAchievementReport } from '../../services/reportingService'
import { toNumber } from '../../utils/task'

/** Phase 9 — Achievement summary report (spec items 11, 15, endpoint #9). */
export function AchievementReportPage() {
  const { year, month, setYear, setMonth, periodLabel } = useReportingPeriod()

  const report = useReportingResource(
    useCallback(() => getAchievementReport({ year, month }), [year, month]),
    `achievement-report|${year}|${month}`,
    'Unable to load the achievement report.',
  )

  if (report.loading && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Achievement report">Ranked faculty culminating performance and monthly honorees.</ReportingIntro>
        <p className="page-state">Loading achievement report…</p>
      </div>
    )
  }

  if (report.error && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Achievement report">Ranked faculty culminating performance and monthly honorees.</ReportingIntro>
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
      <ReportingIntro title="Achievement report">
        Ranked faculty culminating performance and monthly honorees.
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

      {data && !data.has_data ? <p className="page-state">No achievements recorded for this period.</p> : null}

      {data?.has_data ? (
        <>
          <ReportSection title="Achievements at a glance" hint={data.period}>
            <TaskSummaryCards
              items={[
                { label: 'Total achievements', value: data.total, tone: 'info' as const },
                { label: 'Badges awarded', value: data.total_badges, tone: 'ok' as const },
              ]}
            />
          </ReportSection>

          <ReportSection title="Top ranked faculty" hint="By combined performance score">
            {data.rankings.length === 0 ? (
              <p className="page-state">No ranked faculty for this period.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Rank</th>
                      <th scope="col">Faculty member</th>
                      <th scope="col">Department</th>
                      <th scope="col">Score</th>
                      <th scope="col">Completion</th>
                      <th scope="col">On time</th>
                      <th scope="col">Badge</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.rankings.map((row) => (
                      <tr key={row.id}>
                        <td data-label="Rank">{row.rank ?? '—'}</td>
                        <td data-label="Faculty">{row.employee_name}</td>
                        <td data-label="Department">{row.department ?? '—'}</td>
                        <td data-label="Score">{toNumber(row.performance_score)}%</td>
                        <td data-label="Completion">{toNumber(row.completion_rate)}%</td>
                        <td data-label="On time">{toNumber(row.on_time_rate)}%</td>
                        <td data-label="Badge">{row.badge?.name ?? '—'}</td>
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