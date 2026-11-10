import type { ReactNode } from 'react'
import type { HrOptions } from '../../../services/hrService'
import type { ReportFilters, ReportResponse } from '../../../types/report'
import type {
  DepartmentReport,
  EmployeeReport,
  InstitutionReport,
  ReportingFilters,
} from '../../../types/reporting'

/** The seven consolidated report categories of the Reporting Hub. */
export type HubTabKey =
  | 'overview'
  | 'employees'
  | 'departments'
  | 'monthly'
  | 'submissions'
  | 'targets'
  | 'data-quality'

/** Props shared by every tab component. */
export interface HubTabProps {
  filters: ReportingFilters
  /** Admin / HR: institution-wide data is allowed. */
  canInstitution: boolean
  /** Admin / HR / department head: department aggregates are allowed. */
  canDepartment: boolean
  options: HrOptions | null
  periodLabel: string
}

export type ScopedReport =
  | { kind: 'institution'; data: InstitutionReport }
  | { kind: 'department'; data: DepartmentReport }
  | { kind: 'employee'; data: EmployeeReport }

/** Percentage that keeps a zero denominator visibly distinct from zero. */
export function pctOrNa(value: number | null | undefined, digits = 1): string {
  if (value === null || value === undefined || Number.isNaN(Number(value))) {
    return 'N/A'
  }
  return `${Number(value).toFixed(digits)}%`
}

/**
 * Percentage for a rate whose denominator is known to the caller.
 *
 * The table report services return 0.0 when a denominator is empty (see
 * ComputesReportMetrics), which would read as a real zero score. The hub
 * overrides that here so "no tasks" is N/A, never 0.0%.
 */
export function pctOf(
  value: number | null | undefined,
  denominator: number | null | undefined,
  digits = 1,
): string {
  if (denominator === null || denominator === undefined || denominator <= 0) {
    return 'N/A'
  }
  return pctOrNa(value, digits)
}

export function numOrNa(value: number | null | undefined): string {
  if (value === null || value === undefined || Number.isNaN(Number(value))) {
    return 'N/A'
  }
  return Number(value).toLocaleString()
}

/**
 * Normalise a category breakdown payload into a list of {label, count} rows.
 *
 * Endpoints produced before the reporting contract fix returned either a
 * list or a keyed map (`entity_type => count`); a malformed payload must
 * never crash the tab with `… .map is not a function`, so anything that is
 * not a usable list or map degrades to an empty one.
 */
export function toCategoryRows(value: unknown): Array<{ label: string; count: number }> {
  if (!Array.isArray(value)) {
    if (value === null || value === undefined || typeof value !== 'object') {
      return []
    }
    // Keyed map: the keys carry the labels, the values the counts.
    return Object.entries(value as Record<string, unknown>)
      .filter(([, count]) => typeof count === 'number')
      .map(([label, count]) => ({ label, count: Number(count) }))
  }

  return value
    .filter((row): row is { label?: unknown; count?: unknown } =>
      row !== null && typeof row === 'object')
    .map(row => ({
      label: typeof row.label === 'string' ? row.label : String(row.label ?? ''),
      count: typeof row.count === 'number' ? row.count : 0,
    }))
    .filter(row => row.label !== '')
}

export interface PeriodRange {
  year: number | null
  month: number | null
  from_date: string | null
  to_date: string | null
}

/**
 * Normalises the hub's period selection into a concrete date range.
 *
 * Phase 9 endpoints accept `period_type`, while the Phase 3.3-J
 * employee/department tables only understand `year`, `month`, `from_date` and
 * `to_date` — this is the single place both families are reconciled.
 */
export function periodRange(filters: ReportingFilters): PeriodRange {
  const year = typeof filters.year === 'number' ? filters.year : null
  const month = typeof filters.month === 'number' ? filters.month : null

  if (filters.start_date && filters.end_date) {
    return { year, month, from_date: filters.start_date, to_date: filters.end_date }
  }

  if (year !== null && filters.period_type === 'quarterly' && filters.quarter) {
    const startMonth = (filters.quarter - 1) * 3 + 1
    const endMonth = startMonth + 2
    return {
      year,
      month: null,
      from_date: `${year}-${String(startMonth).padStart(2, '0')}-01`,
      to_date: lastDayOfMonth(year, endMonth),
    }
  }

  if (year !== null && filters.period_type === 'yearly') {
    return { year, month: null, from_date: `${year}-01-01`, to_date: `${year}-12-31` }
  }

  if (year !== null && month !== null) {
    return {
      year,
      month,
      from_date: `${year}-${String(month).padStart(2, '0')}-01`,
      to_date: lastDayOfMonth(year, month),
    }
  }

  return { year, month, from_date: null, to_date: null }
}

function lastDayOfMonth(year: number, month: number): string {
  const day = new Date(year, month, 0).getDate()
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

/** The year/month pair a monthly report should render. */
export function monthlyYearMonth(filters: ReportingFilters): { year: number; month: number } {
  const range = periodRange(filters)
  const now = new Date()

  if (range.year !== null && range.month !== null) {
    return { year: range.year, month: range.month }
  }

  if (range.to_date) {
    const parsed = new Date(range.to_date)
    if (!Number.isNaN(parsed.getTime())) {
      return { year: parsed.getFullYear(), month: parsed.getMonth() + 1 }
    }
  }

  if (range.year !== null) {
    return { year: range.year, month: range.year === now.getFullYear() ? now.getMonth() + 1 : 12 }
  }

  return { year: now.getFullYear(), month: now.getMonth() + 1 }
}

/** Convert hub filters to the Phase 3.3-J table report query shape. */
export function toReportFilters(
  filters: ReportingFilters,
  extra: ReportFilters = {},
): ReportFilters {
  const range = periodRange(filters)

  return {
    department_id: filters.department_id ?? null,
    academic_year_id: filters.academic_year_id ?? null,
    employee_id: filters.employee_id ?? null,
    year: range.year,
    month: range.month,
    from_date: range.from_date,
    to_date: range.to_date,
    page: 1,
    // The table endpoints cap per_page at 100 (ReportRequest validation) —
    // sending 200 made every hub tab 422 with "The per page field must not
    // be greater than 100."
    per_page: 100,
    ...extra,
  }
}

/**
 * Load every page of a paginated table report (below `maxPages` to keep the
 * rate-limiters sane) and return it merged into what looks like a single
 * response. Client-side search/filter in the hub tabs operates on the full
 * dataset this way, instead of silently truncating at one page of 100.
 *
 * The envelope is the report envelope (`meta.pagination.last_page`), so the
 * merged result stays type-compatible with `useReportingResource`.
 */
export async function fetchAllReportPages<T>(
  query: (filters: ReportFilters) => Promise<ReportResponse<T[]>>,
  filters: ReportingFilters,
  extra: ReportFilters = {},
  maxPages = 20,
): Promise<ReportResponse<T[]>> {
  const base = toReportFilters(filters, extra)
  const first = await query({ ...base, page: 1 })
  const rows: T[] = [...(first.data ?? [])]
  const lastPage = first.meta?.pagination?.last_page ?? 1

  for (let page = 2; page <= Math.min(lastPage, maxPages); page += 1) {
    const next = await query({ ...base, page })
    rows.push(...(next.data ?? []))
  }

  return { ...first, data: rows }
}

export function KpiIcon({ children }: { children: ReactNode }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.7}
      strokeLinecap="round"
      strokeLinejoin="round"
      className="h-6 w-6"
      aria-hidden="true"
      focusable="false"
    >
      {children}
    </svg>
  )
}

export function MiniStat({ label, value, hint }: { label: string; value: ReactNode; hint?: string }) {
  return (
    <div className="rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5">
      <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">{label}</p>
      <p className="text-lg font-black text-slate-900 tabular-nums mt-0.5">{value}</p>
      {hint ? <p className="text-[10px] text-slate-500 mt-0.5 leading-snug">{hint}</p> : null}
    </div>
  )
}
