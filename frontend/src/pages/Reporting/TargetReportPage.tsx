import { useCallback } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import { getTargetReport } from '../../services/reportingService'
import { toNumber } from '../../utils/task'

/** Phase 9 — Target achievement report (spec item 18, endpoint #13). */
export function TargetReportPage() {
  const { year, month, setYear, setMonth, periodLabel } = useReportingPeriod()

  const report = useReportingResource(
    useCallback(() => getTargetReport({ year, month }), [year, month]),
    `target-report|${year}|${month}`,
    'Unable to load the target achievement report.',
  )

  if (report.loading && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Target achievement">Department and faculty attainment against quarterly and annual targets.</ReportingIntro>
        <p className="page-state">Loading target achievement report…</p>
      </div>
    )
  }

  if (report.error && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Target achievement">Department and faculty attainment against quarterly and annual targets.</ReportingIntro>
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
      <ReportingIntro title="Target achievement">
        Department and faculty attainment against quarterly and annual targets.
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

      {data && !data.has_data ? <p className="page-state">No targets recorded for this period.</p> : null}

      {data?.has_data ? (
        <>
          <ReportSection title="Target summary" hint={data.period}>
            <TaskSummaryCards
              items={[
                { label: 'Total targets', value: data.total_targets },
                { label: 'Achieved', value: data.achieved_targets, tone: 'ok' as const },
                { label: 'Partially achieved', value: data.partially_achieved, tone: 'warning' as const },
                { label: 'Unachieved', value: data.unachieved, tone: 'error' as const },
                { label: 'In progress', value: data.in_progress, tone: 'info' as const },
                { label: 'Achievement rate', value: `${toNumber(data.achievement_rate)}%`, tone: 'info' as const },
              ]}
            />
          </ReportSection>

          <ReportSection title="By department" hint="Target attainment per department">
            {data.by_department.length === 0 ? (
              <p className="page-state">No department target data for this period.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Department</th>
                      <th scope="col">Total targets</th>
                      <th scope="col">Achieved</th>
                      <th scope="col">Achievement rate</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.by_department.map((row) => (
                      <tr key={String(row.department_id)}>
                        <td data-label="Department">{row.department_name}</td>
                        <td data-label="Total">{row.total_targets}</td>
                        <td data-label="Achieved">{row.achieved}</td>
                        <td data-label="Rate">{toNumber(row.achievement_rate)}%</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </ReportSection>

          <ReportSection title="Target detail" hint={`${data.targets.length} targets`}>
            {data.targets.length === 0 ? (
              <p className="page-state">No individual targets for this period.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Title</th>
                      <th scope="col">Status</th>
                      <th scope="col">Value</th>
                      <th scope="col">Employee</th>
                      <th scope="col">Department</th>
                      <th scope="col">Start</th>
                      <th scope="col">End</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.targets.map((row) => (
                      <tr key={String(row.id)}>
                        <td data-label="Title">{row.title}</td>
                        <td data-label="Status">{row.status.replaceAll('_', ' ')}</td>
                        <td data-label="Value">{toNumber(row.target_value)} {row.target_unit ?? ''}</td>
                        <td data-label="Employee">{row.employee}</td>
                        <td data-label="Department">{row.department ?? '—'}</td>
                        <td data-label="Start">{row.start_date ?? '—'}</td>
                        <td data-label="End">{row.end_date ?? '—'}</td>
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