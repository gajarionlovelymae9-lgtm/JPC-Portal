export interface TaskBreakdownItem {
  label: string
  value: number
  tone?: 'default' | 'ok' | 'warning' | 'error' | 'info'
}

interface TaskBreakdownChartProps {
  items: TaskBreakdownItem[]
  title?: string
}

/**
 * Dependency-free horizontal bar chart for task status distribution.
 * Values come straight from the API — only the bar widths are derived.
 */
export function TaskBreakdownChart({ items, title }: TaskBreakdownChartProps) {
  const max = Math.max(1, ...items.map((item) => item.value))

  return (
    <div className="task-chart" role="group" aria-label={title ?? 'Task breakdown'}>
      {title ? <h4 className="task-chart__title">{title}</h4> : null}
      <ul className="task-chart__list">
        {items.map((item) => {
          const width = Math.round((item.value / max) * 100)
          return (
            <li key={item.label} className="task-chart__row">
              <span className="task-chart__label">{item.label}</span>
              <span className="task-chart__track">
                <span
                  className={`task-chart__fill task-chart__fill--${item.tone ?? 'default'}`}
                  style={{ width: `${width}%` }}
                />
              </span>
              <span className="task-chart__value">{item.value}</span>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
