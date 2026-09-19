import { priorityLabel, slug } from '../../utils/task'

interface TaskPriorityBadgeProps {
  priority: string
}

/** Colour-coded pill for a task priority. */
export function TaskPriorityBadge({ priority }: TaskPriorityBadgeProps) {
  return (
    <span className={`task-badge task-badge--priority task-badge--${slug(priority)}`}>
      {priorityLabel(priority)}
    </span>
  )
}
