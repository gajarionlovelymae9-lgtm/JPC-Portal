import { Link } from 'react-router-dom'
import type {
  EmployeeOverdueReport,
  EmployeeOverdueTask,
} from '../../services/employeePortalService'
import { employeeReportDetailPath, employeeTaskDetailPath } from '../../routes/paths'
import { EmptyState, Panel, PanelHeader, formatDate } from './primitives'

/**
 * Overdue tasks and late report submissions are combined into one list because
 * they share the same meaning for the employee: work that is already behind.
 */
export default function OverdueItems({
  tasks,
  reports,
}: {
  tasks: EmployeeOverdueTask[]
  reports: EmployeeOverdueReport[]
}) {
  if (tasks.length === 0 && reports.length === 0) {
    return (
      <Panel>
        <PanelHeader title="Overdue Items" />
        <EmptyState message="Nothing overdue." hint="You're on schedule with your deadlines." icon="🎉" />
      </Panel>
    )
  }

  const total = tasks.length + reports.length

  return (
    <Panel className="border-red-200">
      <PanelHeader
        title="Overdue Items"
        description={`${total} item${total === 1 ? '' : 's'} past the deadline`}
      />
      <ul className="divide-y divide-gray-100">
        {tasks.map(task => (
          <li key={`task-${task.id}`} className="px-5 py-3 flex items-start justify-between gap-3">
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <span className="text-xs font-medium text-gray-400 uppercase">Task</span>
                <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                  {task.days_overdue}d overdue
                </span>
              </div>
              <Link
                to={employeeTaskDetailPath(task.task_id)}
                className="block text-sm font-medium text-gray-900 hover:text-blue-600 truncate mt-0.5"
              >
                {task.title}
              </Link>
              <p className="text-xs text-gray-500 mt-0.5">Due {formatDate(task.deadline)}</p>
            </div>
          </li>
        ))}

        {reports.map(report => (
          <li key={`report-${report.id}`} className="px-5 py-3 flex items-start justify-between gap-3">
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <span className="text-xs font-medium text-gray-400 uppercase">Report</span>
                <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                  Late submission
                </span>
              </div>
              <Link
                to={employeeReportDetailPath(report.id)}
                className="block text-sm font-medium text-gray-900 hover:text-blue-600 truncate mt-0.5"
              >
                {report.title}
              </Link>
              <p className="text-xs text-gray-500 mt-0.5">Due {formatDate(report.deadline)}</p>
            </div>
          </li>
        ))}
      </ul>
    </Panel>
  )
}
