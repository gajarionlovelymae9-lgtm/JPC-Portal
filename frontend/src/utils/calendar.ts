/**
 * Shared month-grid helpers for the portal calendars. These are free of any
 * view logic so both the full My Calendar page and the dashboard's compact
 * calendar render the same 42-cell grid for a given month.
 */

/** Sunday-first weeks, matching the rest of the portal calendars. */
export const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

/** Parse a YYYY-MM-DD string as a *local* date to avoid a UTC off-by-one. */
export function parseLocalDate(value: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  if (!match) return null
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
}

/** Local YYYY-MM-DD (not toISOString, which shifts by the UTC offset). */
export function isoDate(value: Date): string {
  const month = String(value.getMonth() + 1).padStart(2, '0')
  const day = String(value.getDate()).padStart(2, '0')
  return `${value.getFullYear()}-${month}-${day}`
}

export function isSameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  )
}

/**
 * Six fixed rows (42 cells) starting on the Sunday on or before the first of
 * the month — the grid therefore always covers the whole month and the leading
 * / trailing days that pad it. Days are built with local arithmetic so DST and
 * leap years (including 29-day Februaries) resolve correctly.
 */
export function buildMonthCells(year: number, month: number): Date[] {
  const firstOfMonth = new Date(year, month - 1, 1)
  const gridStart = new Date(year, month - 1, 1 - firstOfMonth.getDay())
  return Array.from(
    { length: 42 },
    (_, index) =>
      new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + index),
  )
}

/** Format a YYYY-MM-DD value as a long, human-readable date. */
export function formatEventDate(value: string): string {
  const date = parseLocalDate(value)
  return date
    ? date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
    : value
}
