import { slug, statusLabel } from '../../utils/task'

interface TaskStatusBadgeProps {
  status: string
  /** Latest review decision, used to distinguish revision from a hard rejection. */
  decision?: string | null
}

/** Colour-coded pill for a task/assignment status. */
export function TaskStatusBadge({ status, decision }: TaskStatusBadgeProps) {
  const isRevision = status === 'rejected' && decision === 'needs_revision'
  const label = isRevision ? 'Revision Requested' : statusLabel(status)
  const modifier = isRevision ? 'revision-requested' : slug(status)

  return <span className={`task-badge task-badge--${modifier}`}>{label}</span>
}
