import { Link } from 'react-router-dom'
import type { EmployeeDashboardKpis } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'
import { formatPercent } from './primitives'

type Tone = 'neutral' | 'info' | 'success' | 'warning' | 'danger'

interface Card {
  key: string
  label: string
  value: string
  caption?: string
  tone: Tone
  to?: string
}

const TONE_CLASS: Record<Tone, { value: string; icon: string }> = {
  neutral: { value: 'text-gray-900', icon: 'bg-gray-100 text-gray-600' },
  info: { value: 'text-blue-700', icon: 'bg-blue-100 text-blue-700' },
  success: { value: 'text-green-700', icon: 'bg-green-100 text-green-700' },
  warning: { value: 'text-amber-700', icon: 'bg-amber-100 text-amber-700' },
  danger: { value: 'text-red-700', icon: 'bg-red-100 text-red-700' },
}

const ICONS: Record<string, string> = {
  assigned: '📋',
  completed: '✅',
  pending: '⏳',
  reports: '📄',
  on_time: '⏱️',
  progress: '📈',
  overdue: '⚠️',
}

function buildCards(kpis: EmployeeDashboardKpis): Card[] {
  return [
    {
      key: 'assigned',
      label: 'Assigned Tasks',
      value: String(kpis.total_tasks),
      caption: kpis.in_progress_tasks > 0 ? `${kpis.in_progress_tasks} in progress` : undefined,
      tone: 'info',
      to: PATHS.employeeTasks,
    },
    {
      key: 'completed',
      label: 'Completed Tasks',
      value: String(kpis.completed_tasks),
      tone: 'success',
      to: PATHS.employeeTasks,
    },
    {
      key: 'pending',
      label: 'Pending Tasks',
      value: String(kpis.pending_tasks + kpis.in_progress_tasks),
      caption: kpis.submitted_tasks > 0 ? `${kpis.submitted_tasks} awaiting review` : undefined,
      tone: 'warning',
      to: PATHS.employeeTasks,
    },
    {
      key: 'reports',
      label: 'Reports Submitted',
      value: String(kpis.submitted_reports),
      caption: kpis.draft_reports > 0 ? `${kpis.draft_reports} draft pending` : undefined,
      tone: 'neutral',
      to: PATHS.employeeReports,
    },
    {
      key: 'on_time',
      label: 'On-Time Submission',
      value: formatPercent(kpis.on_time_rate),
      tone: kpis.on_time_rate !== null && kpis.on_time_rate < 80 ? 'warning' : 'info',
    },
    {
      key: 'progress',
      label: 'Overall Progress',
      value: formatPercent(kpis.completion_rate),
      caption:
        kpis.total_tasks === 0
          ? 'No tasks assigned yet'
          : `${kpis.completed_tasks} of ${kpis.total_tasks} tasks`,
      tone: 'info',
    },
  ]
}

/**
 * Responsive KPI grid. Null rates render as an em dash — a percentage is never
 * invented for a metric the backend could not compute.
 */
export default function EmployeeSummaryCards({ kpis }: { kpis: EmployeeDashboardKpis }) {
  const cards = buildCards(kpis)

  return (
    <div
      className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4"
      aria-label="Key performance indicators"
    >
      {cards.map(card => {
        const tone = TONE_CLASS[card.tone]
        const body = (
          <>
            <div className="flex items-center justify-between gap-2">
              <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                {card.label}
              </p>
              <span
                className={`w-7 h-7 shrink-0 rounded-lg flex items-center justify-center text-sm ${tone.icon}`}
                aria-hidden="true"
              >
                {ICONS[card.key]}
              </span>
            </div>
            <p className={`text-2xl font-bold mt-2 tabular-nums ${tone.value}`}>
              {card.value}
            </p>
            {card.caption ? (
              <p className="text-xs text-gray-500 mt-0.5">{card.caption}</p>
            ) : null}
          </>
        )

        return card.to ? (
          <Link
            key={card.key}
            to={card.to}
            className="block bg-white border border-gray-200 rounded-xl p-4 hover:border-blue-300 hover:shadow-sm transition-colors"
          >
            {body}
          </Link>
        ) : (
          <div key={card.key} className="bg-white border border-gray-200 rounded-xl p-4">
            {body}
          </div>
        )
      })}
    </div>
  )
}
