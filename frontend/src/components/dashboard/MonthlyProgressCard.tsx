import { TaskBreakdownChart } from '../tasks/TaskBreakdownChart'
import type { TaskBreakdownItem } from '../tasks/TaskBreakdownChart'
import { calendarMonthLabel } from '../../utils/dashboard'
import type { MonthlyProgress } from '../../types/dashboard'

interface MonthlyProgressCardProps {
  data: MonthlyProgress | null
}

/** Progress metrics for the selected calendar month, with a status breakdown. */
export function MonthlyProgressCard({ data }: MonthlyProgressCardProps) {
  if (!data) {
    return null
  }

  const items: TaskBreakdownItem[] = [
    { label: 'Completed on time', value: data.on_time, tone: 'ok' },
    { label: 'Completed late', value: data.late, tone: 'warning' },
    { label: 'Pending', value: data.pending, tone: 'default' },
    { label: 'Submitted', value: data.submitted, tone: 'default' },
    { label: 'Under review', value: data.under_review, tone: 'info' },
    { label: 'Overdue', value: data.overdue, tone: 'error' },
  ]

  return (
    <section className="task-section" aria-label="Monthly progress">
      <div className="dashboard-panel__head">
        <h3>Monthly progress</h3>
        <span className="dashboard-panel__hint">{calendarMonthLabel(data.year, data.month)}</span>
      </div>

      <div className="task-stats">
        <div className="task-stat">
          <span className="task-stat__value">{data.total}</span>
          <span className="task-stat__label">Tasks created</span>
        </div>
        <div className="task-stat task-stat--ok">
          <span className="task-stat__value">{data.completed}</span>
          <span className="task-stat__label">Completed</span>
        </div>
        <div className="task-stat task-stat--info">
          <span className="task-stat__value">{data.completion_rate}%</span>
          <span className="task-stat__label">Completion</span>
        </div>
        <div className="task-stat task-stat--warning">
          <span className="task-stat__value">{data.overdue}</span>
          <span className="task-stat__label">Overdue</span>
        </div>
      </div>

      <TaskBreakdownChart items={items} title="Status breakdown" />
    </section>
  )
}