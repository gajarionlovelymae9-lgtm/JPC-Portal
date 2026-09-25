import { useCallback } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { ReportSection } from '../../components/reports/ReportSection'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingResource } from '../../hooks/useReporting'
import { getDepartmentReport, getEmployeeReport, getInstitutionReport, getManagementSummary } from '../../services/reportingService'
import { PATHS } from '../../routes/paths'
import { toNumber } from '../../utils/task'

const LINKS: Array<{ path: string; label: string; permission: string }> = [
  { path: PATHS.reportingEmployee, label: 'Employee progress report', permission: 'reports.view' },
  { path: PATHS.reportingDepartment, label: 'Department performance report', permission: 'analytics.view' },
  { path: PATHS.reportingMonthly, label: 'Monthly performance report', permission: 'analytics.view' },
  { path: PATHS.reportingAchievements, label: 'Achievement report', permission: 'analytics.view' },
  { path: PATHS.reportingLate, label: 'Late submissions report', permission: 'analytics.view' },
  { path: PATHS.reportingPending, label: 'Pending reports analytics', permission: 'analytics.view' },
  { path: PATHS.reportingTargets, label: 'Target achievement report', permission: 'analytics.view' },
  { path: PATHS.reportingDataQuality, label: 'Data quality report', permission: 'analytics.view' },
  { path: PATHS.reportingManagementSummary, label: 'Executive management summary', permission: 'analytics.view' },
  { path: PATHS.reportingAnalytics, label: 'Analytics hub', permission: 'analytics.view' },
  { path: PATHS.reportingExports, label: 'Report exports', permission: 'reports.view' },
]

/** Phase 9 — Reporting dashboard hub (spec items 7, 19, 33, 55). */
export function ReportingDashboardPage() {
  const { user } = useAuth()
  const role = user?.role ?? ''
  const permissions = user?.permissions ?? []
  const canManagement = role === 'admin' || role === 'hr'
  const isDeptHead = role === 'department_head'
  const isEmployee = role === 'employee'

  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth() + 1

  const institution = useReportingResource(
    useCallback(() => (canManagement ? getInstitutionReport() : Promise.resolve({ data: null })), [canManagement]),
    'hub-institution',
    'Unable to load the institution report.',
  )

  const managementSummary = useReportingResource(
    useCallback(() => (canManagement ? getManagementSummary(year, month) : Promise.resolve({ data: null })), [canManagement, year, month]),
    'hub-management-summary',
    'Unable to load the management summary.',
  )

  const department = useReportingResource(
    useCallback(() => (isDeptHead ? getDepartmentReport() : Promise.resolve({ data: null })), [isDeptHead]),
    'hub-department',
    'Unable to load the department report.',
  )

  const employeeSelf = useReportingResource(
    useCallback(() => (isEmployee ? getEmployeeReport() : Promise.resolve({ data: null })), [isEmployee]),
    'hub-employee-self',
    'Unable to load your progress report.',
  )

  const links = LINKS.filter((link) => permissions.includes(link.permission))

  return (
    <div className="page">
      <ReportingIntro title="Reporting center">
        Phase 9 advanced reporting, analytics and management intelligence for the JPC EPMS.
      </ReportingIntro>

      {isEmployee && employeeSelf.data ? (
        <ReportSection title={`My progress — ${employeeSelf.data.employee.name}`} hint={employeeSelf.data.period}>
          <TaskSummaryCards
            items={[
              { label: 'Task completion', value: `${toNumber(employeeSelf.data.metrics.task_completion_rate)}%`, tone: 'info' as const },
              { label: 'Report completion', value: `${toNumber(employeeSelf.data.metrics.report_completion_rate)}%`, tone: 'info' as const },
              { label: 'On-time submission', value: `${toNumber(employeeSelf.data.metrics.on_time_submission_rate)}%`, tone: 'ok' as const },
              { label: 'Approval rate', value: `${toNumber(employeeSelf.data.metrics.approval_rate)}%`, tone: 'ok' as const },
              { label: 'Productivity score', value: `${toNumber(employeeSelf.data.metrics.productivity_score)}%`, tone: 'info' as const },
              { label: 'Rating', value: employeeSelf.data.metrics.rating ?? '—' },
            ]}
          />
        </ReportSection>
      ) : null}

      {isDeptHead && department.data ? (
        <ReportSection title={`My department — ${department.data.department.name}`} hint={department.data.period}>
          <TaskSummaryCards
            items={[
              { label: 'Employees', value: department.data.metrics.employees },
              { label: 'Task completion', value: `${toNumber(department.data.metrics.task_completion_rate)}%`, tone: 'info' as const },
              { label: 'On-time rate', value: `${toNumber(department.data.metrics.on_time_rate)}%`, tone: 'ok' as const },
              { label: 'Approval rate', value: `${toNumber(department.data.metrics.approval_rate)}%`, tone: 'ok' as const },
              { label: 'Target achievement', value: `${toNumber(department.data.metrics.target_achievement_rate)}%`, tone: 'info' as const },
              { label: 'Late reports', value: department.data.metrics.late_reports, tone: 'warning' as const },
              { label: 'Rating', value: department.data.metrics.rating ?? '—' },
            ]}
          />
        </ReportSection>
      ) : null}

      {canManagement && institution.data && institution.data.has_data ? (
        <>
          <ReportSection title="Institution at a glance" hint={institution.data.period}>
            <TaskSummaryCards
              items={[
                { label: 'Employees', value: institution.data.summary.total_employees },
                { label: 'Departments', value: institution.data.summary.total_departments },
                { label: 'Task completion', value: `${toNumber(institution.data.summary.task_completion_rate)}%`, tone: 'info' as const },
                { label: 'Reports submitted', value: institution.data.summary.reports_submitted },
                { label: 'Late reports', value: institution.data.summary.late_reports, tone: 'warning' as const },
                { label: 'Pending reports', value: institution.data.summary.pending_reports, tone: 'warning' as const },
                { label: 'Approval rate', value: `${toNumber(institution.data.summary.approval_rate)}%`, tone: 'ok' as const },
                { label: 'Target achievement', value: `${toNumber(institution.data.summary.target_achievement_rate)}%`, tone: 'info' as const },
                { label: 'Avg productivity', value: `${toNumber(institution.data.summary.average_productivity)}%`, tone: 'info' as const },
                { label: 'Data quality score', value: `${toNumber(institution.data.summary.data_quality_score)}%`, tone: 'info' as const },
              ]}
            />
          </ReportSection>

          {managementSummary.data ? (
            <ReportSection title="Management summary" hint={managementSummary.data.period_label}>
              <p>{managementSummary.data.narrative}</p>
              {managementSummary.data.key_takeaways.length > 0 ? (
                <ul className="stack-list">
                  {managementSummary.data.key_takeaways.map((takeaway) => (
                    <li key={takeaway}>{takeaway}</li>
                  ))}
                </ul>
              ) : null}
            </ReportSection>
          ) : null}
        </>
      ) : null}

      <ReportSection title="Report library">
        <div className="dashboard-filters-wrap">
          <div className="task-filters">
            {links.map((link) => (
              <Link key={link.path} to={link.path} className="button button--secondary">
                {link.label}
              </Link>
            ))}
          </div>
        </div>
      </ReportSection>
    </div>
  )
}