import { formatDateTime } from '../../utils/task'
import type { TaskTimelineItem } from '../../types/task'

interface TaskTimelineProps {
  items: TaskTimelineItem[]
  emptyMessage?: string
}

/** Chronological list of progress updates or review decisions. */
export function TaskTimeline({
  items,
  emptyMessage = 'No activity recorded yet.',
}: TaskTimelineProps) {
  if (items.length === 0) {
    return <p className="task-timeline__empty">{emptyMessage}</p>
  }

  return (
    <ol className="task-timeline">
      {items.map((item) => (
        <li key={item.id} className="task-timeline__item">
          <span
            className={`task-timeline__dot task-timeline__dot--${item.tone ?? 'info'}`}
            aria-hidden="true"
          />
          <div className="task-timeline__body">
            <div className="task-timeline__head">
              <span className="task-timeline__title">{item.title}</span>
              {item.at ? (
                <time className="task-timeline__time">{formatDateTime(item.at)}</time>
              ) : null}
            </div>
            {item.subtitle ? (
              <span className="task-timeline__subtitle">{item.subtitle}</span>
            ) : null}
            {item.description ? (
              <p className="task-timeline__description">{item.description}</p>
            ) : null}
          </div>
        </li>
      ))}
    </ol>
  )
}
