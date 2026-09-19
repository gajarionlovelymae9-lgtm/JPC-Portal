export interface MonthlyProgressItem {
  label: string
  completed: number
  total: number
}

interface TaskMonthlyProgressProps {
  items: MonthlyProgressItem[]
  emptyMessage?: string
}

function rate(item: MonthlyProgressItem): number {
  return item.total > 0 ? Math.round((item.completed / item.total) * 100) : 0
}

/** Monthly completion bars derived from the employee's own tasks. */
export function TaskMonthlyProgress({
  items,
  emptyMessage = 'No monthly activity to show yet.',
}: TaskMonthlyProgressProps) {
  if (items.length === 0) {
    return <p className="page-state">{emptyMessage}</p>
  }

  return (
    <ul className="task-monthly" aria-label="Monthly progress">
      {items.map((item) => {
        const percent = rate(item)
        return (
          <li key={item.label} className="task-monthly__row">
            <span className="task-monthly__label">{item.label}</span>
            <span className="task-chart__track">
              <span className="task-chart__fill task-chart__fill--ok" style={{ width: `${percent}%` }} />
            </span>
            <span className="task-monthly__value">
              {percent}% <span className="task-monthly__detail">({item.completed}/{item.total})</span>
            </span>
          </li>
        )
      })}
    </ul>
  )
}
