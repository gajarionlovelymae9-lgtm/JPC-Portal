export interface TaskSummaryCardItem {
  label: string
  value: number | string
  tone?: 'default' | 'ok' | 'warning' | 'error' | 'info'
}

interface TaskSummaryCardsProps {
  items: TaskSummaryCardItem[]
}

/** Responsive grid of headline task counters. */
export function TaskSummaryCards({ items }: TaskSummaryCardsProps) {
  return (
    <div className="task-stats">
      {items.map((item) => (
        <div
          key={item.label}
          className={`task-stat task-stat--${item.tone ?? 'default'}`}
        >
          <span className="task-stat__value">{item.value}</span>
          <span className="task-stat__label">{item.label}</span>
        </div>
      ))}
    </div>
  )
}
