/**
 * Presentation-only helpers for the management dashboard.
 * No business rules — only formatting of numbers, months and percents.
 */

import type { TaskSummaryCardItem } from '../components/tasks/TaskSummaryCards'
import type { DashboardSummary } from '../types/dashboard'

/** Convert a `YYYY-MM` label (as served by the employee detail API) to e.g. `April 2026`. */
export function friendlyMonthLabel(value: string): string {
  const [year, month] = value.split('-').map(Number)
  if (!Number.isFinite(year) || !Number.isFinite(month)) {
    return value
  }
  const date = new Date(year, month - 1, 1)
  if (Number.isNaN(date.getTime())) {
    return value
  }
  return date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
}

/** Calendar month label for a `year`/`month` pair, e.g. `(2026, 4)` → `April 2026`. */
export function calendarMonthLabel(year: number, month: number): string {
  const date = new Date(year, month - 1, 1)
  if (Number.isNaN(date.getTime())) {
    return `${year}`
  }
  return date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
}

/** Map the management summary payload onto the shared KPI card grid. */
export function summaryCards(summary: DashboardSummary): TaskSummaryCardItem[] {
  return [
    { label: 'Total tasks', value: summary.total_tasks, tone: 'default' },
    { label: 'Completion rate', value: `${summary.completion_rate}%`, tone: 'ok' },
    { label: 'On-time rate', value: `${summary.on_time_rate}%`, tone: 'info' },
    { label: 'Overdue', value: summary.overdue_tasks, tone: 'warning' },
    { label: 'Pending', value: summary.pending_tasks, tone: 'default' },
    { label: 'Submitted', value: summary.submitted_tasks, tone: 'default' },
    { label: 'Under review', value: summary.under_review_tasks, tone: 'info' },
    { label: 'Rejected', value: summary.rejected_tasks, tone: 'error' },
    { label: 'Cancelled', value: summary.cancelled_tasks, tone: 'default' },
  ]
}