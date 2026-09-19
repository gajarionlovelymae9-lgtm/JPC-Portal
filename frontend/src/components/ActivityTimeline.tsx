import { TaskTimeline } from './tasks/TaskTimeline'
import { titleCase } from '../utils/task'
import type { ActivityLogItem } from '../types/notification'
import type { TaskTimelineItem } from '../types/task'

interface ActivityTimelineProps {
  items: ActivityLogItem[]
  emptyMessage?: string
}

type Tone = NonNullable<TaskTimelineItem['tone']>

/**
 * Human-readable label for a log action, e.g. `progress.updated` →
 * `Progress Updated`.
 */
export function activityActionLabel(action: string): string {
  return titleCase(action.replace(/\./g, ' '))
}

/** Visual tone derived from the log action; unknown actions default to `info`. */
export function activityTone(action: string): Tone {
  if (action.includes('reviewed') || action.includes('approved') || action.includes('login')) {
    return 'info'
  }
  if (action.includes('cancelled') || action.includes('rejected') || action.includes('logout')) {
    return 'muted'
  }
  if (action.includes('submitted')) {
    return 'warning'
  }
  return 'info'
}

/**
 * Renders an append-only activity_logs collection as the standard timeline.
 * Presentation-only: logs are immutable and can never be edited or deleted.
 */
export function ActivityTimeline({ items, emptyMessage }: ActivityTimelineProps) {
  const timeline = items.map<TaskTimelineItem>((log) => ({
    id: log.id,
    title: activityActionLabel(log.action),
    subtitle: log.user_name ?? undefined,
    description: log.description ?? undefined,
    at: log.created_at,
    tone: activityTone(log.action),
  }))

  return <TaskTimeline items={timeline} emptyMessage={emptyMessage} />
}