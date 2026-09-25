import { useCallback, useState } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import { getDepartmentReport } from '../../services/reportingService'
import { getReportOptions } from '../../services/reportService'
import { toNumber } from '../../utils/task'

/**
 * Phase 9 — Department performance report (spec items 8-9, endpoint #5).
 * Department heads are server-scoped to their own department; admin/hr pick a
 * department from the report options.
 */
export function DepartmentReportPage() {
  const { year, month, setYear, setMonth } = useReportingPeriod()
  const [departmentId, setDepartmentId] = useState<number | undefined>(undefined)

  const options = useReportingResource(
    useCallback(() => getReportOptions(), []),
    'department-report-options',
    'Unable to load report options.',
  )

  const report = useReportingResource(
    useCallback(() => getDepartmentReport({ year, month, department_id: departmentId }), [year, month, departmentId]),
    `department-report|${year}|${month}|${String(departmentId)}`,
    'Unable to load the department performance report.',
  )

  if (report.loading && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Department performance report">Task, report and target attainment rolled up per department.</ReportingIntro>
        <p className="page-state">Loading department report…</p>
      </div>
    )
  }

  if (report.error && !report.data) {
    return (
      <div className="page">
        <ReportingIntro title="Department performance report">Task, report and target attainment rolled up per department.</ReportingIntro>
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
      <ReportingIntro title="Department performance report">
        Task, report and target attainment rolled up per department.
      </ReportingIntro>

      <div className="dashboard-filters-wrap">
        <div className="task-filters">
          <PeriodFilter year={year} month={month} onYearChange={setYear} onMonthChange={setMonth} />
          <label className="task-filters__field">
            <span className="task-filters__label">Department</span>
            <select
              aria-label="Department"
              value={departmentId ?? ''}
              onChange={(event) => setDepartmentId(event.target.value ? Number(event.target.value) : undefined)}
            >
              <option value="">Current department (or select)</option>
              {(options.data?.departments ?? []).map((dept) => (
                <option key={dept.id} value={dept.id}>{dept.name}</option>
              ))}
            </select>
          </label>
        </div>
      </div>

      {data && !data.has_data ? <p className="page-state">No data matches this period.</p> : null}

      {data?.has_data ? (
        <>
          <ReportSection title={data.department.name} hint={data.period}>
            <TaskSummaryCards
              items={[
                { label: 'Employees', value: data.metrics.employees },
                { label: 'Tasks assigned', value: data.metrics.tasks_assigned },
                { label: 'Tasks completed', value: data.metrics.tasks_completed, tone: 'ok' as const },
                { label: 'Task completion', value: `${toNumber(data.metrics.task_completion_rate)}%`, tone: 'info' as const },
                { label: 'Reports submitted', value: data.metrics.reports_submitted, tone: 'info' as const },
                { label: 'Late reports', value: data.metrics.late_reports, tone: 'warning' as const },
                { label: 'On-time rate', value: `${toNumber(data.metrics.on_time_rate)}%`, tone: 'ok' as const },
                { label: 'Approval rate', value: `${toNumber(data.metrics.approval_rate)}%`, tone: 'ok' as const },
                { label: 'Target achievement', value: `${toNumber(data.metrics.target_achievement_rate)}%`, tone: 'info' as const },
                { label: 'Avg productivity', value: `${toNumber(data.metrics.average_productivity)}%`, tone: 'info' as const },
                { label: 'Rating', value: data.metrics.rating ?? '—' },
              ]}
            />
          </ReportSection>
        </>
      ) : null}
    </div>
  )
}