import { useCallback, useState } from 'react'
import { useAuth } from '../../auth/useAuth'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import { getEmployeeReport } from '../../services/reportingService'
import { getEmployeePerformance, getReportOptions } from '../../services/reportService'
import { toNumber } from '../../utils/task'

/**
 * Phase 9 — Employee progress report (spec item 12, endpoint #4).
 * Employees (reports.view) see their own record — the backend forces the
 * scope. Managers (analytics.view) pick a department then a faculty member.
 */
export function EmployeeReportPage() {
  const { user } = useAuth()
  const isEmployee = user?.role === 'employee'
  const isManager = !isEmployee

  const { year, month, setYear, setMonth } = useReportingPeriod()
  const [departmentId, setDepartmentId] = useState<number | undefined>(undefined)
  const [employeeId, setEmployeeId] = useState<number | undefined>(undefined)

  const options = useReportingResource(
    useCallback(() => getReportOptions(), []),
    'employee-report-options',
    'Unable to load report options.',
  )

  const employees = useReportingResource(
    // Server caps per_page at 100; 200 used to 422 the dropdown load.
    useCallback(() => getEmployeePerformance({ department_id: departmentId, per_page: 100, page: 1 }), [departmentId]),
    `employee-report-employees|${String(departmentId)}`,
    'Unable to load faculty members.',
  )

  const effectiveEmployeeId = isEmployee ? user?.employee_id ?? 0 : (employeeId ?? 0)

  const report = useReportingResource(
    useCallback(
      () => getEmployeeReport({ year, month, employee_id: effectiveEmployeeId }),
      [year, month, effectiveEmployeeId],
    ),
    `employee-report|${year}|${month}|${effectiveEmployeeId}`,
    'Unable to load the employee progress report.',
  )

  if (isManager && effectiveEmployeeId <= 0) {
    return (
      <div className="page">
        <ReportingIntro title="Employee progress report">
          Individual task, report and target attainment across the period.
        </ReportingIntro>
<div className="dashboard-filters-wrap">
          <div className="task-filters">
            <PeriodFilter year={year} month={month} onYearChange={setYear} onMonthChange={setMonth} />
            <label className="task-filters__field">
              <span className="task-filters__label">Department</span>
              <select
                aria-label="Department"
                value={departmentId ?? ''}
                onChange={(event) => {
                  setDepartmentId(event.target.value ? Number(event.target.value) : undefined)
                  setEmployeeId(undefined)
                }}
              >
                <option value="">All departments</option>
                {(options.data?.departments ?? []).map((dept) => (
                  <option key={dept.id} value={dept.id}>{dept.name}</option>
                ))}
              </select>
            </label>
            <label className="task-filters__field">
              <span className="task-filters__label">Employee</span>
              <select
                aria-label="Employee"
                value={employeeId ?? ''}
                disabled={employees.loading}
                onChange={(event) => setEmployeeId(event.target.value ? Number(event.target.value) : undefined)}
              >
                <option value="">Select a faculty member…</option>
                {(employees.data ?? []).map((row) => (
                  <option key={String(row.employee_id)} value={String(row.employee_id ?? '')}>
                    {row.employee_name ?? `Employee #${row.employee_id}`}
                  </option>
                ))}
              </select>
            </label>
          </div>
        </div>
        <p className="page-state">Select a department and a faculty member to view their progress report.</p>
      </div>
    )
  }

  if (report.loading && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Employee progress report">Individual task, report and target attainment across the period.</ReportingIntro>
        <p className="page-state">Loading employee report…</p>
      </div>
    )
  }

  if (report.error && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Employee progress report">Individual task, report and target attainment across the period.</ReportingIntro>
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
      <ReportingIntro title="Employee progress report">
        Individual task, report and target attainment across the period.
      </ReportingIntro>

      <div className="dashboard-filters-wrap">
        <div className="task-filters">
          <PeriodFilter year={year} month={month} onYearChange={setYear} onMonthChange={setMonth} />
          {isManager ? (
            <>
              <label className="task-filters__field">
                <span className="task-filters__label">Department</span>
                <select
                  aria-label="Department"
                  value={departmentId ?? ''}
                  onChange={(event) => {
                    setDepartmentId(event.target.value ? Number(event.target.value) : undefined)
                    setEmployeeId(undefined)
                  }}
                >
                  <option value="">All departments</option>
                  {(options.data?.departments ?? []).map((dept) => (
                    <option key={dept.id} value={dept.id}>{dept.name}</option>
                  ))}
                </select>
              </label>
              <label className="task-filters__field">
                <span className="task-filters__label">Employee</span>
                <select
                  aria-label="Employee"
                  value={employeeId ?? ''}
                  disabled={employees.loading}
                  onChange={(event) => setEmployeeId(event.target.value ? Number(event.target.value) : undefined)}
                >
                  <option value="">Select a faculty member…</option>
                  {(employees.data ?? []).map((row) => (
                    <option key={String(row.employee_id)} value={String(row.employee_id ?? '')}>
                      {row.employee_name ?? `Employee #${row.employee_id}`}
                    </option>
                  ))}
                </select>
              </label>
            </>
          ) : null}
        </div>
      </div>

      {data && !data.has_data ? (
        <p className="page-state">No data matches this period.</p>
      ) : null}

        {data?.has_data ? (
          <ReportSection title={data.employee.name} hint={data.period}>
            <TaskSummaryCards
              items={[
                { label: 'Tasks assigned', value: data.metrics.tasks_assigned },
                { label: 'Tasks completed', value: data.metrics.tasks_completed, tone: 'ok' as const },
                { label: 'Task completion', value: `${toNumber(data.metrics.task_completion_rate)}%`, tone: 'info' as const },
                { label: 'Reports submitted', value: data.metrics.reports_submitted, tone: 'info' as const },
                { label: 'Report completion', value: `${toNumber(data.metrics.report_completion_rate)}%`, tone: 'info' as const },
                { label: 'On-time submission', value: `${toNumber(data.metrics.on_time_submission_rate)}%`, tone: 'ok' as const },
                { label: 'Approval rate', value: `${toNumber(data.metrics.approval_rate)}%`, tone: 'ok' as const },
                { label: 'Targets achieved', value: `${data.metrics.targets_achieved}/${data.metrics.targets_total}`, tone: 'info' as const },
                { label: 'Productivity score', value: `${toNumber(data.metrics.productivity_score)}%`, tone: 'info' as const },
                { label: 'Rating', value: data.metrics.rating ?? '—' },
              ]}
            />
          </ReportSection>
        ) : null}
    </div>
  )
}