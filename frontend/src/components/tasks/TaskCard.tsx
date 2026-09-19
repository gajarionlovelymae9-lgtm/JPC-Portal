import { Link } from 'react-router-dom'
import { TaskPriorityBadge } from './TaskPriorityBadge'
import { TaskProgressBar } from './TaskProgressBar'
import { TaskStatusBadge } from './TaskStatusBadge'
import { formatDate } from '../../utils/task'
import type { Task, TaskAssignment } from '../../types/task'

interface TaskCardProps {
  task: Task
  /** When provided, the card shows the individual assignment progress/due date. */
  assignment?: TaskAssignment
  to: string
}

export function TaskCard({ task, assignment, to }: TaskCardProps) {
  const dueDate = assignment?.due_date ?? task.due_date
  const overdue = assignment?.is_overdue ?? task.is_overdue

  return (
    <article className={`task-card${overdue ? ' task-card--overdue' : ''}`}>
      <header className="task-card__header">
        <Link to={to} className="task-card__title">
          {task.title}
        </Link>
        <div className="task-card__badges">
          <TaskStatusBadge status={task.status} />
          <TaskPriorityBadge priority={task.priority} />
        </div>
      </header>

      <p className="task-card__code">{task.task_code}</p>

      {task.description ? <p className="task-card__description">{task.description}</p> : null}

      {assignment ? (
        <TaskProgressBar value={assignment.completion_percentage} label="Progress" />
      ) : null}

      <footer className="task-card__footer">
        <span className={`task-card__due${overdue ? ' task-card__due--overdue' : ''}`}>
          Due {formatDate(dueDate)}
          {overdue ? ' · Overdue' : ''}
        </span>
        <Link to={to} className="task-card__link">
          View details
        </Link>
      </footer>
    </article>
  )
}
