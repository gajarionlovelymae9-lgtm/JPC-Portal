import { TaskSummaryCards } from '../tasks/TaskSummaryCards'
import { summaryCards } from '../../utils/dashboard'
import type { DashboardSummary } from '../../types/dashboard'

interface KpiGridProps {
  summary: DashboardSummary | null
}

/** Organisation-wide KPI cards at the top of the management dashboard. */
export function KpiGrid({ summary }: KpiGridProps) {
  if (!summary) {
    return null
  }
  return <TaskSummaryCards items={summaryCards(summary)} />
}