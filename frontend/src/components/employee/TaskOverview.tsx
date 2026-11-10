import { Link } from 'react-router-dom'
import type { EmployeeDashboardTask } from '../../services/employeePortalService'
import { PATHS, employeeTaskDetailPath } from '../../routes/paths'
import {
  EmptyState,
  Panel,
  PanelHeader,
  ProgressBar,
  StatusPill,
  UrgencyPill,
  formatDate,
} from './primitives'

const PRIORITY_TONE: Record<string, string> = {
  low: 'text-gray-500',
  medium: 'text-blue-600',
  high: 'text-amber-600',
  critical: 'text-red-600',
}

/** Recent tasks for the authenticated employee, newest activity first. */
export default function TaskOverview({ tasks }: { tasks: EmployeeDashboardTask[] }) {
  return (
    <Panel>
      <PanelHeader
        title="My Tasks"
        description="Your most recently updated assignments"
        action={
          <Link
            to={PATHS.employeeTasks}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            View All Tasks →
          </Link>
        }
      />

      {tasks.length === 0 ? (
        <EmptyState message="No assigned tasks yet." hint="Tasks assigned to you will appear here." icon="📋" />
      ) : (
        <ul className="divide-y divide-gray-100">
          {tasks.map(task => (
            <li key={task.assignment_id} className="px-5 py-3 hover:bg-gray-50 transition-colors">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <Link
                    to={employeeTaskDetailPath(task.task_id)}
                    className="text-sm font-semibold text-gray-900 hover:text-blue-600"
                  >
                    {task.title}
                  </Link>
                  {task.description ? (
                    <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">
                      {task.description.replace(/<[^>]*>/g, '')}
                    </p>
                  ) : null}
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-gray-500">
                    <StatusPill status={task.status} />
                    <UrgencyPill urgency={task.urgency} />
                    {task.priority ? (
                      <span className={`capitalize ${PRIORITY_TONE[task.priority] ?? 'text-gray-500'}`}>
                        {task.priority}
                      </span>
                    ) : null}
                    <span>Due {formatDate(task.deadline)}</span>
                    {task.assigned_at ? (
                      <span className="hidden sm:inline">Assigned {formatDate(task.assigned_at)}</span>
                    ) : null}
                  </div>
                </div>
                <div className="shrink-0 w-28">
                  <ProgressBar
                    percent={task.status === 'completed' ? 100 : task.progress}
                    tone={task.is_overdue ? 'red' : task.status === 'completed' ? 'green' : 'blue'}
                  />
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}
