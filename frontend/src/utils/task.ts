/**
 * Presentation-only helpers for task data.
 *
 * No workflow/business rules live here — status transitions are enforced by the
 * Laravel services. These functions only format labels, dates and numbers.
 */

const STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  assigned: 'Assigned',
  not_started: 'Not Started',
  accepted: 'Accepted',
  in_progress: 'In Progress',
  submitted: 'Submitted',
  under_review: 'Under Review',
  completed: 'Completed',
  approved: 'Approved',
  rejected: 'Rejected',
  revision_requested: 'Revision Requested',
  cancelled: 'Cancelled',
}

const PRIORITY_LABELS: Record<string, string> = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  urgent: 'Urgent',
}

const DECISION_LABELS: Record<string, string> = {
  approved: 'Approved',
  rejected: 'Rejected',
  needs_revision: 'Needs Revision',
}

export function statusLabel(status: string): string {
  return STATUS_LABELS[status] ?? titleCase(status)
}

export function priorityLabel(priority: string): string {
  return PRIORITY_LABELS[priority] ?? titleCase(priority)
}

export function decisionLabel(decision: string): string {
  return DECISION_LABELS[decision] ?? titleCase(decision)
}

/** CSS modifier slug, e.g. `in_progress` → `in-progress`. */
export function slug(value: string): string {
  return value.replace(/_/g, '-')
}

export function titleCase(value: string): string {
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

export function toNumber(value: number | string | null | undefined): number {
  if (value === null || value === undefined) {
    return 0
  }
  const parsed = typeof value === 'number' ? value : Number.parseFloat(value)
  return Number.isFinite(parsed) ? parsed : 0
}

export function clampPercent(value: number | string | null | undefined): number {
  return Math.min(100, Math.max(0, Math.round(toNumber(value))))
}

export function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function startOfDay(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate())
}

/**
 * Whole calendar days from today until `value` (negative when in the past).
 * Presentation-only; authorization/oversight stays with the backend.
 */
export function daysUntil(value: string | null | undefined): number | null {
  if (!value) {
    return null
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return null
  }
  const diff = startOfDay(date).getTime() - startOfDay(new Date()).getTime()
  return Math.round(diff / 86_400_000)
}

/** Human deadline label, e.g. `Due in 5 days`, `Due tomorrow`, `Overdue by 3 days`. */
export function deadlineLabel(value: string | null | undefined): string {
  const days = daysUntil(value)
  if (days === null) {
    return 'No due date'
  }
  if (days === 0) {
    return 'Due today'
  }
  if (days === 1) {
    return 'Due tomorrow'
  }
  if (days > 1) {
    return `Due in ${days} days`
  }
  const overdue = Math.abs(days)
  return `Overdue by ${overdue} day${overdue === 1 ? '' : 's'}`
}

/** Short month name (e.g. `Aug 2026`) for grouping monthly progress. */
export function monthLabel(value: string | number | Date): string {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }
  return date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }
  return date.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
