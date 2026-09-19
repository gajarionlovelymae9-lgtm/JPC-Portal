import { slug, statusLabel } from '../../utils/task'

interface TaskStatusBadgeProps {
  status: string
}

/** Colour-coded pill for a task/assignment status. */
export function TaskStatusBadge({ status }: TaskStatusBadgeProps) {
  return (
    <span className={`task-badge task-badge--${slug(status)}`}>{statusLabel(status)}</span>
  )
}
