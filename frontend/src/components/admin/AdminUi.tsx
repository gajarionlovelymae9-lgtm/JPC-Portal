import { useCallback, useEffect, useState, type ReactNode } from 'react'

import { ApiError } from '../../services/api'
import {
  ADMIN_EXPORTS,
  downloadAdminExport,
  type AdminExportKey,
  type ExportFormat,
} from '../../services/adminDashboardService'

/**
 * Shared presentational primitives for the JPC admin dashboard.
 *
 * These deliberately avoid any hardcoded numbers: every value is passed in by
 * the calling page, which sources it from the API.
 */

/* ------------------------------------------------------------------ */
/* Layout                                                              */
/* ------------------------------------------------------------------ */

export function AdminPage({
  title,
  subtitle,
  actions,
  children,
}: {
  /** Omitted when the page is embedded as a tab inside another page, which
   * already renders its own module header. */
  title?: string
  subtitle?: string
  actions?: ReactNode
  children: ReactNode
}) {
  return (
    <div className="p-4 sm:p-6 space-y-6 max-w-content mx-auto animate-fade-in">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        {title ? (
          <div>
            <h1 className="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">{title}</h1>
            {subtitle ? <p className="text-sm text-slate-500 mt-1">{subtitle}</p> : null}
          </div>
        ) : (
          <span className="sr-only">People Management</span>
        )}
        {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
      </div>
      {children}
    </div>
  )
}

export function Panel({
  title,
  description,
  actions,
  children,
  className = '',
}: {
  title?: string
  description?: string
  actions?: ReactNode
  children: ReactNode
  className?: string
}) {
  return (
    <section className={`bg-white rounded-2xl border border-slate-200 shadow-jpc ${className}`}>
      {title || actions ? (
        <header className="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 pb-3">
          <div>
            {title ? <h2 className="text-base font-bold text-slate-900">{title}</h2> : null}
            {description ? <p className="text-xs text-slate-500 mt-0.5">{description}</p> : null}
          </div>
          {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </header>
      ) : null}
      <div className="px-5 pb-5">{children}</div>
    </section>
  )
}

/* ------------------------------------------------------------------ */
/* Statistic cards                                                     */
/* ------------------------------------------------------------------ */

export type StatTone = 'neutral' | 'ok' | 'warn' | 'danger' | 'info'

const TONE_CLASSES: Record<StatTone, string> = {
  neutral: 'text-slate-900',
  ok: 'text-ok',
  warn: 'text-warning',
  danger: 'text-error',
  info: 'text-info',
}

export function StatCard({
  label,
  value,
  hint,
  tone = 'neutral',
  icon,
}: {
  label: string
  value: string | number
  hint?: string
  tone?: StatTone
  icon?: ReactNode
}) {
  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-jpc p-4 sm:p-5">
      <div className="flex items-start justify-between gap-2">
        <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{label}</p>
        {icon ? <span className="text-slate-300">{icon}</span> : null}
      </div>
      <p className={`text-2xl sm:text-3xl font-black mt-2 tabular-nums ${TONE_CLASSES[tone]}`}>
        {value}
      </p>
      {hint ? <p className="text-[11px] text-slate-500 mt-1 leading-snug">{hint}</p> : null}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Badges and notes                                                    */
/* ------------------------------------------------------------------ */

const BADGE_TONES: Record<string, string> = {
  ok: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  warn: 'bg-amber-50 text-amber-700 ring-amber-200',
  danger: 'bg-red-50 text-red-700 ring-red-200',
  info: 'bg-sky-50 text-sky-700 ring-sky-200',
  neutral: 'bg-slate-100 text-slate-700 ring-slate-200',
  purple: 'bg-violet-50 text-violet-700 ring-violet-200',
}

export function Badge({
  children,
  tone = 'neutral',
}: {
  children: ReactNode
  tone?: keyof typeof BADGE_TONES | string
}) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1 ring-inset ${
        BADGE_TONES[tone] ?? BADGE_TONES.neutral
      }`}
    >
      {children}
    </span>
  )
}

/**
 * Maps a domain status string to a badge tone so the same status always looks
 * the same across every admin page.
 */
export function toneForStatus(status: string | null | undefined): string {
  const value = (status ?? '').toLowerCase()
  if (['completed', 'approved', 'active', 'ok', 'published', 'on_track', 'employee_of_the_month'].includes(value)) {
    return 'ok'
  }
  if (['overdue', 'late', 'missed', 'at_risk', 'rejected', 'suspended', 'error', 'down', 'critical'].includes(value)) {
    return 'danger'
  }
  if (['in_progress', 'pending', 'under_review', 'submitted', 'revision_required', 'degraded', 'warning', 'needs_attention'].includes(value)) {
    return 'warn'
  }
  if (['not_started', 'assigned', 'accepted', 'inactive', 'draft', 'queued'].includes(value)) {
    return 'neutral'
  }
  if (['info', 'recorded', 'open'].includes(value)) {
    return 'info'
  }
  return 'neutral'
}

export function StatusBadge({ status, label }: { status: string | null | undefined; label?: string | null }) {
  return <Badge tone={toneForStatus(status)}>{label ?? humanise(status)}</Badge>
}

export function humanise(value: string | null | undefined): string {
  if (!value) return '—'
  return value
    .replace(/[_.]+/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

/**
 * Inline provenance warning. Used wherever a value originates from the seeded
 * `jpc.sample` AI provider so the UI can never imply live AI analysis.
 */
export function SampleDataNotice({ title, children }: { title?: string; children?: ReactNode }) {
  return (
    <div className="flex items-start gap-2 text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
      <span aria-hidden="true" className="font-black">!</span>
      <div className="min-w-0">
        {title ? <p className="font-semibold text-amber-900">{title}</p> : null}
        <p className={title ? 'mt-0.5' : undefined}>
          {children ?? 'This figure comes from seeded development sample data, not a live AI provider.'}
        </p>
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* States                                                              */
/* ------------------------------------------------------------------ */

export function LoadingPanel({ label = 'Loading', rows = 4 }: { label?: string; rows?: number }) {
  return (
    <div className="space-y-4" role="status" aria-live="polite" aria-busy="true">
      <span className="sr-only">{label}</span>
      <div className="h-9 w-1/3 rounded-lg bg-slate-100 animate-pulse" />
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {Array.from({ length: rows }).map((_, i) => (
          <div key={i} className="h-24 rounded-2xl bg-slate-100 animate-pulse" />
        ))}
      </div>
      <div className="h-64 rounded-2xl bg-slate-100 animate-pulse" />
    </div>
  )
}

export function ErrorPanel({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="bg-red-50 border border-red-200 text-red-800 rounded-2xl p-5 space-y-3">
      <p className="font-bold">Something went wrong</p>
      <p className="text-sm">{message}</p>
      {onRetry ? (
        <button
          type="button"
          onClick={onRetry}
          className="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-bold hover:bg-red-700"
        >
          Try again
        </button>
      ) : null}
    </div>
  )
}

export function EmptyState({ title, message }: { title: string; message?: string }) {
  return (
    <div className="text-center py-10 px-4">
      <p className="text-sm font-bold text-slate-700">{title}</p>
      {message ? <p className="text-xs text-slate-500 mt-1 max-w-md mx-auto">{message}</p> : null}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Form controls                                                       */
/* ------------------------------------------------------------------ */

export function FilterBar({ children }: { children: ReactNode }) {
  return (
    <div className="flex flex-wrap items-end gap-3 bg-white rounded-2xl border border-slate-200 shadow-jpc p-4">
      {children}
    </div>
  )
}

export function Field({
  label,
  children,
  className = '',
  required = false,
  hint,
}: {
  label: string
  children: ReactNode
  className?: string
  /** Renders a required marker next to the label. */
  required?: boolean
  /** Optional helper text rendered under the control. */
  hint?: string
}) {
  return (
    <label className={`flex flex-col gap-1 ${className}`}>
      <span className="text-[11px] font-bold uppercase tracking-wider text-slate-500">
        {label}
        {required ? <span className="ml-0.5 text-red-500">*</span> : null}
      </span>
      {children}
      {hint ? <span className="text-[11px] text-slate-400">{hint}</span> : null}
    </label>
  )
}

const controlClass =
  'text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-jpc-500 focus:border-jpc-500'

export function Select({
  value,
  onChange,
  options,
  placeholder,
  className = '',
}: {
  value: string
  onChange: (value: string) => void
  options: Array<{ value: string | number; label: string }>
  placeholder?: string
  className?: string
}) {
  return (
    <select
      className={`${controlClass} ${className}`}
      value={value}
      onChange={(e) => onChange(e.target.value)}
    >
      {placeholder ? <option value="">{placeholder}</option> : null}
      {options.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  )
}

export function TextInput({
  value,
  onChange,
  placeholder,
  type = 'text',
  className = '',
}: {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  type?: string
  className?: string
}) {
  return (
    <input
      type={type}
      className={`${controlClass} ${className}`}
      value={value}
      placeholder={placeholder}
      onChange={(e) => onChange(e.target.value)}
    />
  )
}

export function Button({
  children,
  onClick,
  variant = 'primary',
  type = 'button',
  disabled = false,
  ariaDisabled = false,
  className = '',
  title,
}: {
  children: ReactNode
  onClick?: () => void
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  type?: 'button' | 'submit'
  disabled?: boolean
  /**
   * Marks the button unavailable without suppressing the click event. Used by
   * forms that must stay visually disabled but still explain why an attempted
   * save was rejected.
   */
  ariaDisabled?: boolean
  className?: string
  title?: string
}) {
  const variants: Record<string, string> = {
    primary: 'bg-jpc-600 text-white hover:bg-jpc-700',
    secondary: 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50',
    danger: 'bg-red-600 text-white hover:bg-red-700',
    ghost: 'text-jpc-700 hover:bg-jpc-50',
  }
  return (
    <button
      type={type}
      onClick={onClick}
      disabled={disabled}
      aria-disabled={ariaDisabled || undefined}
      title={title}
      className={`px-4 py-2 rounded-lg text-sm font-bold transition disabled:opacity-50 disabled:cursor-not-allowed ${ariaDisabled ? 'opacity-50 cursor-not-allowed' : ''} ${variants[variant]} ${className}`}
    >
      {children}
    </button>
  )
}

/* ------------------------------------------------------------------ */
/* Table + pagination                                                  */
/* ------------------------------------------------------------------ */

export function Table({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div className="overflow-x-auto -mx-5 px-5">
      <table className={`w-full text-sm border-collapse ${className}`}>{children}</table>
    </div>
  )
}

export function Th({ children, className = '' }: { children?: ReactNode; className?: string }) {
  return (
    <th
      className={`text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 py-2.5 pr-3 whitespace-nowrap ${className}`}
    >
      {children}
    </th>
  )
}

export function Td({ children, className = '' }: { children?: ReactNode; className?: string }) {
  return <td className={`py-2.5 pr-3 text-slate-700 align-top ${className}`}>{children}</td>
}

export function Pagination({
  meta,
  onPageChange,
}: {
  meta: { current_page: number; last_page: number; total: number; per_page: number }
  onPageChange: (page: number) => void
}) {
  if (meta.last_page <= 1) {
    return (
      <p className="text-xs text-slate-500 pt-3">
        {meta.total} record{meta.total === 1 ? '' : 's'}
      </p>
    )
  }
  return (
    <div className="flex items-center justify-between pt-4 text-xs text-slate-500">
      <span>
        Page {meta.current_page} of {meta.last_page} · {meta.total} records
      </span>
      <div className="flex gap-2">
        <Button
          variant="secondary"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          Previous
        </Button>
        <Button
          variant="secondary"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Next
        </Button>
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Formatting helpers                                                  */
/* ------------------------------------------------------------------ */

/** Formats a percentage, keeping "no data" visibly distinct from zero. */
export function pct(value: number | null | undefined, digits = 1): string {
  if (value === null || value === undefined) return '—'
  return `${Number(value).toFixed(digits)}%`
}

export function num(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—'
  return Number(value).toLocaleString()
}

export function shortDate(value: string | null | undefined): string {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value
  return parsed.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

export function dateTime(value: string | null | undefined): string {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value
  return parsed.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Accessible dialog used for admin create/edit forms.
 *
 * Rendered inline (no portal) so it inherits the admin layout's stacking
 * context, and closed by clicking the backdrop, the close button or Escape.
 */
export function Modal({
  open,
  title,
  onClose,
  children,
  footer,
}: {
  open: boolean
  title: string
  onClose: () => void
  children: ReactNode
  footer?: ReactNode
}) {
  useEffect(() => {
    if (!open) return
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
      <div
        className="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden="true"
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-label={title}
        className="relative w-full sm:max-w-xl max-h-[90vh] flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl"
      >
        <div className="flex items-center justify-between gap-4 px-5 py-4 border-b border-slate-200">
          <h2 className="text-base font-bold text-slate-900">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close dialog"
            className="w-8 h-8 grid place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
          >
            <svg viewBox="0 0 20 20" fill="none" className="w-4 h-4" aria-hidden="true">
              <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
            </svg>
          </button>
        </div>
        <div className="px-5 py-4 overflow-y-auto">{children}</div>
        {footer ? (
          <div className="flex items-center justify-end gap-2 px-5 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
            {footer}
          </div>
        ) : null}
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Export                                                              */
/* ------------------------------------------------------------------ */

const EXPORT_FORMATS: ExportFormat[] = ['csv', 'xlsx', 'pdf']

/**
 * Real export action for the admin tables.
 *
 * Downloads a streamed file from the existing Phase 9 export endpoints, using
 * the page's active filters so the file matches what is on screen. Surfaces API
 * errors (permission, throttle, validation) instead of failing silently.
 */
export function ExportButton({
  exportKey,
  filters = {},
  formats = EXPORT_FORMATS,
}: {
  exportKey: AdminExportKey
  filters?: Record<string, unknown>
  formats?: ExportFormat[]
}) {
  const [format, setFormat] = useState<ExportFormat>(formats[0] ?? 'csv')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const run = useCallback(async () => {
    setBusy(true)
    setError(null)
    try {
      await downloadAdminExport(exportKey, format, filters)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'Export failed')
    } finally {
      setBusy(false)
    }
  }, [exportKey, format, filters])

  const spec = ADMIN_EXPORTS[exportKey]

  return (
    <div className="flex items-center gap-2">
      <label className="sr-only" htmlFor={`${exportKey}-export-format`}>
        {spec.label} export format
      </label>
      <select
        id={`${exportKey}-export-format`}
        value={format}
        disabled={busy}
        onChange={(event) => setFormat(event.target.value as ExportFormat)}
        className="px-3 py-2 rounded-lg text-sm font-semibold text-slate-700 bg-white border border-slate-300 disabled:opacity-50"
      >
        {formats.map((option) => (
          <option key={option} value={option}>
            {option.toUpperCase()}
          </option>
        ))}
      </select>
      <Button variant="secondary" onClick={run} disabled={busy}>
        {busy ? 'Preparing…' : `Export ${spec.label}`}
      </Button>
      {error ? (
        <span role="alert" className="text-sm font-semibold text-red-600">
          {error}
        </span>
      ) : null}
    </div>
  )
}
