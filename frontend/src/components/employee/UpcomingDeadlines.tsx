import { Link } from 'react-router-dom'
import type { EmployeeDeadline } from '../../services/employeePortalService'
import { PATHS, employeeTaskDetailPath } from '../../routes/paths'
import { EmptyState, Panel, PanelHeader, UrgencyPill, formatDate } from './primitives'

const TYPE_LABEL: Record<string, string> = {
  task: 'Task',
  report: 'Report',
}

function deadlinePath(item: EmployeeDeadline): string {
  if (item.type === 'task') {
    return employeeTaskDetailPath(item.task_id ?? item.id)
  }
  return `/employee/reports/${item.id}`
}

/** Task and report deadlines still ahead, closest first. */
export default function UpcomingDeadlines({ deadlines }: { deadlines: EmployeeDeadline[] }) {
  return (
    <Panel>
      <PanelHeader
        title="Upcoming Deadlines"
        action={
          <Link
            to={PATHS.employeeCalendar}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            View Calendar →
          </Link>
        }
      />

      {deadlines.length === 0 ? (
        <EmptyState message="No upcoming deadlines." icon="🗓️" />
      ) : (
        <ul className="divide-y divide-gray-100">
          {deadlines.map(item => (
            <li key={`${item.type}-${item.id}`} className="px-5 py-3 flex items-center justify-between gap-3">
              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-xs font-medium text-gray-400 uppercase">
                    {TYPE_LABEL[item.type] ?? item.type}
                  </span>
                  <UrgencyPill urgency={item.urgency} days={item.days_remaining} />
                </div>
                <Link
                  to={deadlinePath(item)}
                  className="block text-sm font-medium text-gray-900 hover:text-blue-600 truncate mt-0.5"
                >
                  {item.title}
                </Link>
                <p className="text-xs text-gray-500 mt-0.5">Due {formatDate(item.deadline)}</p>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}
