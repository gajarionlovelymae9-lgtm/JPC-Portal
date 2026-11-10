import { useCallback } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import { getDataQualityReport } from '../../services/reportingService'
import { toNumber } from '../../utils/task'
import { toCategoryRows } from './hub/hubShared'

/** Phase 9 — Data quality report (spec items 19, 55, endpoint #14). */
export function DataQualityReportPage() {
  const { year, month, setYear, setMonth, periodLabel } = useReportingPeriod()

  const report = useReportingResource(
    useCallback(() => getDataQualityReport({ year, month }), [year, month]),
    `data-quality-report|${year}|${month}`,
    'Unable to load the data quality report.',
  )

  if (report.loading && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Data quality report">Integrity score, issue severity trends and scan health.</ReportingIntro>
        <p className="page-state">Loading data quality report…</p>
      </div>
    )
  }

  if (report.error && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Data quality report">Integrity score, issue severity trends and scan health.</ReportingIntro>
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
      <ReportingIntro title="Data quality report">
        Integrity score, issue severity trends and scan health.
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

      {data && !data.has_data ? <p className="page-state">No data quality data for this period.</p> : null}

      {data?.has_data ? (
        <>
          <ReportSection title="Data quality summary" hint={data.period}>
            <TaskSummaryCards
              items={[
                { label: 'Quality score', value: `${toNumber(data.summary.data_quality_score)}%`, tone: 'info' as const },
                { label: 'Total issues', value: data.summary.total_issues },
                { label: 'Open issues', value: data.summary.open_issues, tone: 'warning' as const },
                { label: 'Critical', value: data.summary.critical, tone: 'error' as const },
                { label: 'High', value: data.summary.high, tone: 'warning' as const },
                { label: 'Medium', value: data.summary.medium },
                { label: 'Low', value: data.summary.low },
                { label: 'Resolved', value: data.summary.resolved, tone: 'ok' as const },
                { label: 'Dismissed', value: data.summary.dismissed, tone: 'info' as const },
              ]}
            />
          </ReportSection>

          <div className="dashboard-grid">
            <ReportSection title="By category">
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Category</th>
                      <th scope="col">Count</th>
                    </tr>
                  </thead>
                  <tbody>
                    {toCategoryRows(data.by_category).map((row) => (
                      <tr key={row.label}>
                        <td data-label="Category">{row.label}</td>
                        <td data-label="Count">{row.count}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </ReportSection>
            <ReportSection title="By department">
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Department</th>
                      <th scope="col">Count</th>
                    </tr>
                  </thead>
                  <tbody>
                    {toCategoryRows(data.by_department).map((row) => (
                      <tr key={row.label}>
                        <td data-label="Department">{row.label}</td>
                        <td data-label="Count">{row.count}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </ReportSection>
          </div>

          <ReportSection title="Recent scans" hint={`${data.recent_scans.length} most recent`}>
            {data.recent_scans.length === 0 ? (
              <p className="page-state">No scans recorded for this period.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Scan</th>
                      <th scope="col">Status</th>
                      <th scope="col">Started</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.recent_scans.map((row) => (
                      <tr key={String(row.id)}>
                        <td data-label="Scan">#{row.id}</td>
                        <td data-label="Status">{row.status.replaceAll('_', ' ')}</td>
                        <td data-label="Started">{row.created_at ?? '—'}</td>
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