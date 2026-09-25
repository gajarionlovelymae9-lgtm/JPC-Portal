import { useCallback } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import { getPendingReport } from '../../services/reportingService'

/** Phase 9 — Pending reports analytics (spec item 17, endpoint #11). */
export function PendingReportsPage() {
  const { year, month, setYear, setMonth, periodLabel } = useReportingPeriod()

  const report = useReportingResource(
    useCallback(() => getPendingReport({ year, month }), [year, month]),
    `pending-report|${year}|${month}`,
    'Unable to load pending reports analytics.',
  )

  if (report.loading && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Pending reports">Awaiting-review workload by faculty, department and status.</ReportingIntro>
        <p className="page-state">Loading pending reports analytics…</p>
      </div>
    )
  }

  if (report.error && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Pending reports">Awaiting-review workload by faculty, department and status.</ReportingIntro>
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
      <ReportingIntro title="Pending reports">
        Awaiting-review workload by faculty, department and status.
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

      {data && !data.has_data ? <p className="page-state">No pending reports for this period.</p> : null}

      {data?.has_data ? (
        <>
          <ReportSection title="Pending report summary" hint={data.period}>
            <TaskSummaryCards
              items={[
                { label: 'Pending reports', value: data.summary.pending_reports, tone: 'warning' as const },
                { label: 'Employees with pending', value: data.summary.pending_employees },
                { label: 'Departments affected', value: data.summary.pending_departments, tone: 'info' as const },
              ]}
            />
          </ReportSection>

          <div className="dashboard-grid">
            <ReportSection title="By status">
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Status</th>
                      <th scope="col">Count</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.by_status.map((row) => (
                      <tr key={row.status}>
                        <td data-label="Status">{row.status.replaceAll('_', ' ')}</td>
                        <td data-label="Count">{row.count}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </ReportSection>
            <ReportSection title="By month">
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Month</th>
                      <th scope="col">Pending</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.by_month.map((row) => (
                      <tr key={row.period}>
                        <td data-label="Month">{row.period}</td>
                        <td data-label="Pending">{row.pending_reports}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </ReportSection>
          </div>

          <ReportSection title="By faculty member" hint={`${data.by_employee.length} faculty with pending work`}>
            {data.by_employee.length === 0 ? (
              <p className="page-state">No faculty with pending reports.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Faculty member</th>
                      <th scope="col">Department</th>
                      <th scope="col">Pending reports</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.by_employee.map((row) => (
                      <tr key={String(row.employee_id)}>
                        <td data-label="Faculty">{row.employee_name}</td>
                        <td data-label="Department">{row.department ?? '—'}</td>
                        <td data-label="Pending">{row.pending_reports}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </ReportSection>

          <ReportSection title="Pending report detail" hint={`${data.reports.length} shown`}>
            {data.reports.length === 0 ? (
              <p className="page-state">No pending report records for this period.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Title</th>
                      <th scope="col">Employee</th>
                      <th scope="col">Department</th>
                      <th scope="col">Status</th>
                      <th scope="col">Submitted</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.reports.map((row) => (
                      <tr key={String(row.id)}>
                        <td data-label="Title">{row.title}</td>
                        <td data-label="Employee">{row.employee}</td>
                        <td data-label="Department">{row.department ?? '—'}</td>
                        <td data-label="Status">{row.status.replaceAll('_', ' ')}</td>
                        <td data-label="Submitted">{row.submitted_at ?? '—'}</td>
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