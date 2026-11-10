import { Link } from 'react-router-dom'
import { TaskPriorityBadge } from './TaskPriorityBadge'
import { TaskProgressBar } from './TaskProgressBar'
import { TaskStatusBadge } from './TaskStatusBadge'
import { deadlineLabel, formatDate, toNumber } from '../../utils/task'
import type { ReactNode } from 'react'
import type { PaginationMeta } from '../../types/api'
import type { Task } from '../../types/task'

interface TaskTableProps {
  tasks: Task[]
  detailPath: (taskId: number) => string
  renderRowActions?: (task: Task) => ReactNode
  meta?: PaginationMeta
  onPageChange?: (page: number) => void
  loading?: boolean
  error?: string | null
  emptyMessage?: string
  onRetry?: () => void
}

/** Data table for the task management list, with loading/empty/error states. */
export function TaskTable({
  tasks,
  detailPath,
  renderRowActions,
  meta,
  onPageChange,
  loading = false,
  error = null,
  emptyMessage = 'No tasks match the current filters.',
  onRetry,
}: TaskTableProps) {
  if (loading) {
    return <p className="page-state">Loading tasks…</p>
  }

  if (error) {
    return (
      <div className="page-state page-state--denied" role="alert">
        <p>{error}</p>
        {onRetry ? (
          <button type="button" className="button button--secondary" onClick={onRetry}>
            Retry
          </button>
        ) : null}
      </div>
    )
  }

  if (tasks.length === 0) {
    return <p className="page-state">{emptyMessage}</p>
  }

  const showPagination = Boolean(meta && onPageChange && meta.last_page > 1)
  const rangeStart = meta ? (meta.current_page - 1) * meta.per_page + 1 : 0
  const rangeEnd = meta ? Math.min(meta.current_page * meta.per_page, meta.total) : 0

  return (
    <div className="task-table-wrap">
      <table className="task-table">
        <thead>
          <tr>
            <th scope="col">Task</th>
            <th scope="col">Status</th>
            <th scope="col">Priority</th>
            <th scope="col">Due</th>
            <th scope="col">Progress</th>
            {renderRowActions ? <th scope="col">Actions</th> : null}
          </tr>
        </thead>
        <tbody>
          {tasks.map((task) => {
            const assignments = task.assignments ?? []
            const percent =
              assignments.length > 0
                ? assignments.reduce(
                    (sum, assignment) => sum + toNumber(assignment.completion_percentage),
                    0,
                  ) / assignments.length
                : task.status === 'completed'
                  ? 100
                  : 0

            return (
              <tr key={task.id} className={task.is_overdue ? 'task-table__row--overdue' : undefined}>
                <td data-label="Task">
                  <Link to={detailPath(task.id)} className="task-table__title">
                    {task.title}
                  </Link>
                  <span className="task-table__code">{task.task_code}</span>
                </td>
                <td data-label="Status">
                  <TaskStatusBadge status={task.status} />
                </td>
                <td data-label="Priority">
                  <TaskPriorityBadge priority={task.priority} />
                </td>
                <td data-label="Due">
                  <span className={task.is_overdue ? 'task-table__overdue' : undefined}>
                    {formatDate(task.due_date)}
                  </span>
                  <span className="task-table__deadline">{deadlineLabel(task.due_date)}</span>
                </td>
                <td className="task-table__progress" data-label="Progress">
                  <TaskProgressBar value={toNumber(percent)} size="sm" />
                </td>
                {renderRowActions ? <td data-label="Actions">{renderRowActions(task)}</td> : null}
              </tr>
            )
          })}
        </tbody>
      </table>

      {showPagination && meta && onPageChange ? (
        <div className="task-pagination">
          <button
            type="button"
            className="button button--secondary"
            disabled={meta.current_page <= 1}
            onClick={() => onPageChange(meta.current_page - 1)}
          >
            Previous
          </button>
          <span className="task-pagination__status">
            Showing {rangeStart}–{rangeEnd} of {meta.total} · Page {meta.current_page} of{' '}
            {meta.last_page}
          </span>
          <button
            type="button"
            className="button button--secondary"
            disabled={meta.current_page >= meta.last_page}
            onClick={() => onPageChange(meta.current_page + 1)}
          >
            Next
          </button>
        </div>
      ) : null}
    </div>
  )
}
