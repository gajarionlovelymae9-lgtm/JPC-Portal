import type { ReactNode } from 'react'

/**
 * Shared presentational primitives for the employee workspace.
 *
 * Everything here is data-driven and presentational — no API calls, so the
 * sections stay easy to reason about and to test in isolation.
 */

export function Panel({
  children,
  className = '',
}: {
  children: ReactNode
  className?: string
}) {
  return (
    <section
      className={`bg-white border border-gray-200 rounded-xl ${className}`}
    >
      {children}
    </section>
  )
}

export function PanelHeader({
  title,
  action,
  description,
}: {
  title: string
  action?: ReactNode
  description?: string
}) {
  return (
    <div className="flex items-start justify-between gap-3 px-5 pt-4 pb-3 border-b border-gray-100">
      <div className="min-w-0">
        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">
          {title}
        </h2>
        {description ? (
          <p className="text-xs text-gray-500 mt-0.5">{description}</p>
        ) : null}
      </div>
      {action ? <div className="shrink-0">{action}</div> : null}
    </div>
  )
}

export function EmptyState({
  message,
  hint,
  icon = '📭',
}: {
  message: string
  hint?: string
  icon?: string
}) {
  return (
    <div className="px-5 py-8 text-center">
      <div className="text-2xl mb-2" aria-hidden="true">
        {icon}
      </div>
      <p className="text-sm text-gray-600 font-medium">{message}</p>
      {hint ? <p className="text-xs text-gray-400 mt-1">{hint}</p> : null}
    </div>
  )
}

export function SkeletonBlock({ className = 'h-20' }: { className?: string }) {
  return (
    <div
      className={`bg-gray-100 rounded-lg animate-pulse ${className}`}
      aria-hidden="true"
    />
  )
}

export function SkeletonCards({ count = 4 }: { count?: number }) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" aria-hidden="true">
      {Array.from({ length: count }, (_, i) => (
        <SkeletonBlock key={i} className="h-24" />
      ))}
    </div>
  )
}

/**
 * Progress bar. `percent === null` means "nothing recorded yet" and renders a
 * neutral bar with an em dash instead of a misleading empty percentage.
 */
export function ProgressBar({
  percent,
  tone = 'blue',
  className = '',
}: {
  percent: number | null
  tone?: 'blue' | 'green' | 'amber' | 'red'
  className?: string
}) {
  const clamped = percent === null ? 0 : Math.max(0, Math.min(100, percent))
  const fill = {
    blue: 'bg-info',
    green: 'bg-ok',
    amber: 'bg-warning',
    red: 'bg-error',
  }[tone]

  return (
    <div className={`flex items-center gap-2 ${className}`}>
      <div
        className="flex-1 bg-gray-100 rounded-full h-2"
        role="progressbar"
        aria-valuenow={percent ?? undefined}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label="Progress"
      >
        <div
          className={`${fill} h-2 rounded-full transition-all`}
          style={{ width: `${clamped}%` }}
        />
      </div>
      <span className="text-xs font-semibold text-gray-700 w-11 text-right tabular-nums">
        {percent === null ? '—' : `${Math.round(percent)}%`}
      </span>
    </div>
  )
}

const STATUS_TONES: Record<string, string> = {
  completed: 'bg-green-100 text-green-700',
  approved: 'bg-green-100 text-green-700',
  achieved: 'bg-green-100 text-green-700',
  in_progress: 'bg-blue-100 text-blue-700',
  submitted: 'bg-blue-100 text-blue-700',
  processing: 'bg-blue-100 text-blue-700',
  resubmitted: 'bg-blue-100 text-blue-700',
  under_review: 'bg-yellow-100 text-yellow-700',
  assigned: 'bg-gray-100 text-gray-700',
  accepted: 'bg-gray-100 text-gray-700',
  draft: 'bg-gray-100 text-gray-700',
  revision_required: 'bg-orange-100 text-orange-700',
  rejected: 'bg-red-100 text-red-700',
  cancelled: 'bg-gray-100 text-gray-500',
  archived: 'bg-gray-100 text-gray-500',
  high: 'bg-red-100 text-red-700',
  medium: 'bg-amber-100 text-amber-700',
  low: 'bg-gray-100 text-gray-600',
}

export function StatusPill({ status }: { status: string }) {
  const tone = STATUS_TONES[status] ?? 'bg-gray-100 text-gray-600'
  return (
    <span
      className={`inline-block px-2 py-0.5 rounded-full text-xs font-semibold capitalize whitespace-nowrap ${tone}`}
    >
      {status.replace(/_/g, ' ')}
    </span>
  )
}

/**
 * Urgency is communicated with restrained colour. Overdue is the only state
 * that earns red; "due today" uses amber, everything else stays neutral.
 */
export function UrgencyPill({ urgency, days }: { urgency: string; days?: number | null }) {
  if (urgency === 'overdue') {
    return (
      <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
        {days !== undefined && days !== null ? `${Math.abs(days)}d overdue` : 'Overdue'}
      </span>
    )
  }

  if (urgency === 'today') {
    return (
      <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
        Due today
      </span>
    )
  }

  if (urgency === 'tomorrow') {
    return (
      <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">
        Due tomorrow
      </span>
    )
  }

  if (urgency === 'waiting') {
    return (
      <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
        In review
      </span>
    )
  }

  if (urgency === 'done') {
    return (
      <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
        Done
      </span>
    )
  }

  if (urgency === 'none' || days === null || days === undefined) {
    return null
  }

  return (
    <span className="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
      {days}d left
    </span>
  )
}

export function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

export function formatLongDate(value: Date): string {
  return value.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
}

/** Renders a percentage or an em dash when the backend reports no data. */
export function formatPercent(percent: number | null | undefined): string {
  return percent === null || percent === undefined ? '—' : `${Math.round(percent)}%`
}
