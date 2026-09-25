import { useCallback } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import { getLateReport } from '../../services/reportingService'
import { toNumber } from '../../utils/task'

/** Phase 9 — Late submission report (spec item 17, endpoint #10). */
export function LateReportsPage() {
  const { year, month, setYear, setMonth, periodLabel } = useReportingPeriod()

  const report = useReportingResource(
    useCallback(() => getLateReport({ year, month }), [year, month]),
    `late-report|${year}|${month}`,
    'Unable to load the late submission report.',
  )

  if (report.loading && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Late submissions">Reports submitted after their deadline across faculty and departments.</ReportingIntro>
        <p className="page-state">Loading late submission report…</p>
      </div>
    )
  }

  if (report.error && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Late submissions">Reports submitted after their deadline across faculty and departments.</ReportingIntro>
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
      <ReportingIntro title="Late submissions">
        Reports submitted after their deadline across faculty and departments.
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

      {data && !data.has_data ? <p className="page-state">No late submissions recorded for this period.</p> : null}

      {data?.has_data ? (
        <>
          <ReportSection title="Late submission summary" hint={data.period}>
            <TaskSummaryCards
              items={[
                { label: 'Late reports', value: data.summary.late_reports, tone: 'error' as const },
                { label: 'Total reports', value: data.summary.total_reports },
                { label: 'Late rate', value: `${toNumber(data.summary.late_submission_rate)}%`, tone: 'warning' as const },
                { label: 'Late employees', value: data.summary.late_employees, tone: 'warning' as const },
                { label: 'Late departments', value: data.summary.late_departments, tone: 'warning' as const },
              ]}
            />
          </ReportSection>

          <ReportSection title="By faculty member" hint="Ranked by late reports">
            {data.by_employee.length === 0 ? (
              <p className="page-state">No faculty with late submissions.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Faculty member</th>
                      <th scope="col">Department</th>
                      <th scope="col">Late reports</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.by_employee.map((row) => (
                      <tr key={String(row.employee_id)}>
                        <td data-label="Faculty">{row.employee_name}</td>
                        <td data-label="Department">{row.department ?? '—'}</td>
                        <td data-label="Late reports">{row.late_reports}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </ReportSection>

          <ReportSection title="Late reports detail" hint={`${data.reports.length} shown`}>
            {data.reports.length === 0 ? (
              <p className="page-state">No late report records for this period.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Title</th>
                      <th scope="col">Employee</th>
                      <th scope="col">Department</th>
                      <th scope="col">Submitted</th>
                      <th scope="col">Deadline</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.reports.map((row) => (
                      <tr key={String(row.id)}>
                        <td data-label="Title">{row.title}</td>
                        <td data-label="Employee">{row.employee}</td>
                        <td data-label="Department">{row.department ?? '—'}</td>
                        <td data-label="Submitted">{row.submitted_at ?? '—'}</td>
                        <td data-label="Deadline">{row.deadline ?? '—'}</td>
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