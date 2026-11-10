import { Link } from 'react-router-dom'
import type { EmployeeDashboardReport } from '../../services/employeePortalService'
import { PATHS, employeeReportDetailPath, employeeReportEditPath } from '../../routes/paths'
import { EmptyState, Panel, PanelHeader, StatusPill, UrgencyPill, formatDate } from './primitives'

const ACTION_LABEL: Record<string, string> = {
  edit: 'Submit',
  revise: 'Revise',
  view: 'View',
  none: '',
}

function reportActionLink(report: EmployeeDashboardReport): { to: string; label: string } | null {
  if (report.needs_action && report.action === 'revise') {
    return { to: `/employee/reports/${report.id}/revise`, label: ACTION_LABEL.revise }
  }
  if (report.needs_action && report.action === 'edit') {
    return { to: employeeReportEditPath(report.id), label: ACTION_LABEL.edit }
  }
  return null
}

/** Recent reports for the authenticated employee. */
export default function ReportOverview({ reports }: { reports: EmployeeDashboardReport[] }) {
  return (
    <Panel>
      <PanelHeader
        title="My Reports"
        description="Latest submissions and their review status"
        action={
          <Link
            to={PATHS.employeeReports}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            View All Reports →
          </Link>
        }
      />

      {reports.length === 0 ? (
        <EmptyState message="No reports yet." hint="Reports you create for your tasks will appear here." icon="📄" />
      ) : (
        <ul className="divide-y divide-gray-100">
          {reports.map(report => {
            const action = reportActionLink(report)

            return (
              <li key={report.id} className="px-5 py-3 flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <Link
                    to={employeeReportDetailPath(report.id)}
                    className="text-sm font-semibold text-gray-900 hover:text-blue-600"
                  >
                    {report.title}
                  </Link>
                  {report.task_title ? (
                    <p className="text-xs text-gray-500 mt-0.5 truncate">{report.task_title}</p>
                  ) : null}
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-gray-500">
                    <StatusPill status={report.status} />
                    <UrgencyPill urgency={report.urgency} />
                    {report.submitted_at ? (
                      <span>Submitted {formatDate(report.submitted_at)}</span>
                    ) : report.deadline ? (
                      <span>Due {formatDate(report.deadline)}</span>
                    ) : null}
                  </div>
                </div>
                {action ? (
                  <Link
                    to={action.to}
                    className="shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
                  >
                    {action.label}
                  </Link>
                ) : null}
              </li>
            )
          })}
        </ul>
      )}
    </Panel>
  )
}
