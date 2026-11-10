import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  AlarmClock,
  CalendarDays,
  CalendarX2,
  ChevronLeft,
  ChevronRight,
  ClipboardCheck,
  ClipboardList,
  Clock,
  ExternalLink,
  FileText,
  Megaphone,
  RefreshCw,
  X,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { getMyCalendar } from '../../services/employeePortalService'
import type {
  EmployeeCalendarEvent,
  EmployeeCalendarEventType,
} from '../../services/employeePortalService'
import { Panel, PanelHeader, SkeletonBlock, StatusPill } from '../../components/employee/primitives'
import { getApiErrorMessage } from '../../utils/apiError'

/* ── Category visuals ───────────────────────────────────────────────────────
 * Every event resolves to one of five design categories, each carrying the
 * official JPC category palette — a soft pastel background with a matching
 * darker text colour. The same palette drives the compact in-cell labels and
 * the event rows / detail view. Announcements are classified by their own
 * category (e.g. a "Deadline" announcement counts as a Deadline).
 * ------------------------------------------------------------------------ */

type FilterKey = 'all' | EmployeeCalendarEventType

type CategoryKey = 'deadline' | 'task' | 'report' | 'announcement' | 'review'

interface CategoryStyle {
  /** Canonical category name shown on each in-cell label. */
  label: string
  Icon: LucideIcon
  /** In-cell compact label and row/modal chip. */
  chip: string
  /** Pastel rounded-square container used by the rows and the detail view. */
  container: string
  /** Icon glyph colour. */
  icon: string
}

const CATEGORY_STYLES: Record<CategoryKey, CategoryStyle> = {
  // Deadline — soft red (#FEE2E2 / #B91C1C)
  deadline: {
    label: 'Deadline',
    Icon: AlarmClock,
    chip: 'bg-red-100 text-red-700',
    container: 'bg-red-50 border-red-100',
    icon: 'text-red-700',
  },
  // Task — soft blue (#DBEAFE / #1D4ED8)
  task: {
    label: 'Task',
    Icon: ClipboardList,
    chip: 'bg-blue-100 text-blue-700',
    container: 'bg-blue-50 border-blue-100',
    icon: 'text-blue-700',
  },
  // Report — soft green (#D1FAE5 / #047857)
  report: {
    label: 'Report',
    Icon: FileText,
    chip: 'bg-emerald-100 text-emerald-700',
    container: 'bg-emerald-50 border-emerald-100',
    icon: 'text-emerald-700',
  },
  // Announcement — soft purple (#EDE9FE / #6D28D9)
  announcement: {
    label: 'Announcement',
    Icon: Megaphone,
    chip: 'bg-violet-100 text-violet-700',
    container: 'bg-violet-50 border-violet-100',
    icon: 'text-violet-700',
  },
  // Review — soft amber (#FEF3C7 / #B45309)
  review: {
    label: 'Review',
    Icon: ClipboardCheck,
    chip: 'bg-amber-100 text-amber-700',
    container: 'bg-amber-50 border-amber-100',
    icon: 'text-amber-700',
  },
}

const CATEGORY_FALLBACK = CATEGORY_STYLES.announcement

/**
 * Resolve an API event to one of the five design categories. Tasks and reports
 * are unambiguous; announcements are classified by their own category label so
 * a "Deadline" announcement is treated as a deadline. Unknown values fall back
 * to Announcement.
 */
function categoryOf(event: EmployeeCalendarEvent): CategoryKey {
  if (event.type === 'task') return 'task'
  if (event.type === 'report') return 'report'

  const category = (event.category ?? '').trim().toLowerCase()
  if (category === 'deadline') return 'deadline'
  if (category === 'review') return 'review'
  return 'announcement'
}

function styleOf(event: EmployeeCalendarEvent): CategoryStyle {
  return CATEGORY_STYLES[categoryOf(event)] ?? CATEGORY_FALLBACK
}

/** How many event labels a single date cell previews before "+X more". */
const CELL_LABEL_LIMIT = 3

const FILTER_TABS: Array<{ key: FilterKey; label: string; Icon: LucideIcon }> = [
  { key: 'all', label: 'All', Icon: CalendarDays },
  { key: 'task', label: 'Tasks', Icon: ClipboardList },
  { key: 'report', label: 'Reports', Icon: FileText },
  { key: 'announcement', label: 'Announcements', Icon: Megaphone },
]

/** How many events the panel shows before "View All" expands it. */
const PREVIEW_LIMIT = 5

/* ── Date helpers (parsed as local dates to avoid UTC off-by-one) ─────────── */

function parseLocalDate(value: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  if (!match) return null
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
}

function formatEventDate(value: string): string {
  const date = parseLocalDate(value)
  return date
    ? date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
    : value
}

function relativeLabel(value: string, today: Date): string | null {
  const date = parseLocalDate(value)
  if (!date) return null

  const start = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  const days = Math.round((date.getTime() - start.getTime()) / 86_400_000)

  if (days === 0) return 'Today'
  if (days === 1) return 'Tomorrow'
  if (days === -1) return 'Yesterday'
  if (days < 0) return `${Math.abs(days)}d overdue`
  if (days <= 7) return `In ${days} days`
  return null
}

const humaniseStatus = (status: string) => status.replace(/_/g, ' ')

/* ── Calendar grid helpers ───────────────────────────────────────────────── */

/** Sunday-first weeks, matching the rest of the portal calendars. */
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

/** Local YYYY-MM-DD (not toISOString, which shifts by the UTC offset). */
function isoDate(value: Date): string {
  const month = String(value.getMonth() + 1).padStart(2, '0')
  const day = String(value.getDate()).padStart(2, '0')
  return `${value.getFullYear()}-${month}-${day}`
}

function isSameDay(a: Date, b: Date): boolean {
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
function buildMonthCells(year: number, month: number): Date[] {
  const firstOfMonth = new Date(year, month - 1, 1)
  const gridStart = new Date(year, month - 1, 1 - firstOfMonth.getDay())
  return Array.from(
    { length: 42 },
    (_, index) =>
      new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + index),
  )
}

/* ── Page ────────────────────────────────────────────────────────────────── */

export default function EmployeeCalendarPage() {
  const today = useMemo(() => new Date(), [])
  const [events, setEvents] = useState<EmployeeCalendarEvent[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [filter, setFilter] = useState<FilterKey>('all')
  const [expanded, setExpanded] = useState(false)
  const [selected, setSelected] = useState<EmployeeCalendarEvent | null>(null)
  const [month, setMonth] = useState(today.getMonth() + 1)
  const [year, setYear] = useState(today.getFullYear())
  /** The day whose events the calendar detail strip shows. Defaults to today. */
  const [selectedDate, setSelectedDate] = useState<Date | null>(today)
  /** When set, the "+X more" dialog lists every event for that day. */
  const [dayModalDate, setDayModalDate] = useState<Date | null>(null)

  const load = useCallback(() => {
    setLoading(true)
    setError(null)
    getMyCalendar({ month, year })
      .then(response => setEvents(Array.isArray(response.data?.events) ? response.data.events : []))
      .catch(cause => {
        setEvents([])
        setError(getApiErrorMessage(cause, 'Unable to load calendar. Please try again.'))
      })
      .finally(() => setLoading(false))
  }, [month, year])

  useEffect(() => {
    void load()
  }, [load])

  const counts = useMemo(
    () => ({
      all: events.length,
      task: events.filter(event => event.type === 'task').length,
      report: events.filter(event => event.type === 'report').length,
      announcement: events.filter(event => event.type === 'announcement').length,
    }),
    [events],
  )

  // The backend already returns events sorted by nearest date.
  const filtered = useMemo(
    () => (filter === 'all' ? events : events.filter(event => event.type === filter)),
    [events, filter],
  )

  const visible = expanded ? filtered : filtered.slice(0, PREVIEW_LIMIT)
  const canExpand = filtered.length > PREVIEW_LIMIT
  const monthName = new Date(year, month - 1).toLocaleString('default', { month: 'long' })

  // The 42 cells of the visible month grid (leading/trailing days included).
  const monthCells = useMemo(() => buildMonthCells(year, month), [month, year])

  // Group the real API events by day so each cell can show its own indicators.
  const eventsByDate = useMemo(() => {
    const map = new Map<string, EmployeeCalendarEvent[]>()
    for (const event of events) {
      const key = event.date.slice(0, 10)
      if (!key) continue
      const list = map.get(key)
      if (list) list.push(event)
      else map.set(key, [event])
    }
    return map
  }, [events])

  const selectedKey = selectedDate ? isoDate(selectedDate) : null
  const selectedDayEvents = useMemo(
    () => (selectedKey ? (eventsByDate.get(selectedKey) ?? []) : []),
    [eventsByDate, selectedKey],
  )

  const dayModalEvents = useMemo(
    () => (dayModalDate ? (eventsByDate.get(isoDate(dayModalDate)) ?? []) : []),
    [eventsByDate, dayModalDate],
  )

  const goMonth = (delta: -1 | 1) => {
    setSelected(null)
    setExpanded(false)
    setSelectedDate(null)

    let nextMonth = month + delta
    let nextYear = year
    if (nextMonth < 1) {
      nextMonth = 12
      nextYear -= 1
    } else if (nextMonth > 12) {
      nextMonth = 1
      nextYear += 1
    }

    setMonth(nextMonth)
    setYear(nextYear)
  }

  /** Jump back to the current month and re-select today. */
  const goToday = () => {
    setSelected(null)
    setExpanded(false)
    setMonth(today.getMonth() + 1)
    setYear(today.getFullYear())
    setSelectedDate(today)
  }

  /**
   * Selecting a grid day highlights it and — when the day belongs to an
   * adjacent month — moves the calendar to that month so its events load.
   */
  const selectDate = (date: Date) => {
    setSelected(null)
    setExpanded(false)
    setSelectedDate(date)

    if (date.getMonth() !== month - 1 || date.getFullYear() !== year) {
      setMonth(date.getMonth() + 1)
      setYear(date.getFullYear())
    }
  }

  /** Selecting an event label highlights its day and opens the event details. */
  const openEventDetails = (event: EmployeeCalendarEvent) => {
    const date = parseLocalDate(event.date)
    if (date) setSelectedDate(date)
    setSelected(event)
  }

  return (
    <div className="p-6 space-y-6 max-w-5xl mx-auto">
      {/* Header + month navigation (existing calendar control, preserved) */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">My Calendar</h1>
          <p className="mt-0.5 text-sm text-gray-500">
            Your deadlines, tasks and institutional announcements in one place.
          </p>
        </div>

        <div className="flex items-center gap-2 self-start">
          <div className="flex items-center gap-2 rounded-xl border border-line bg-white p-1">
            <button
              type="button"
              onClick={() => goMonth(-1)}
              aria-label="Previous month"
              className="grid h-8 w-8 place-items-center rounded-lg text-jpc-600 hover:bg-mint-200"
            >
              <ChevronLeft size={18} />
            </button>
            <span aria-live="polite" className="min-w-[132px] text-center text-sm font-semibold text-slate-800">
              {monthName} {year}
            </span>
            <button
              type="button"
              onClick={() => goMonth(1)}
              aria-label="Next month"
              className="grid h-8 w-8 place-items-center rounded-lg text-jpc-600 hover:bg-mint-200"
            >
              <ChevronRight size={18} />
            </button>
          </div>
          <button
            type="button"
            onClick={goToday}
            className="rounded-xl border border-line bg-white px-3 py-2 text-sm font-semibold text-jpc-700 hover:bg-mint-200"
          >
            Today
          </button>
        </div>
      </div>

      {/* Category filter */}
      <div className="flex flex-wrap gap-2">
        {FILTER_TABS.map(tab => {
          const active = filter === tab.key
          return (
            <button
              key={tab.key}
              type="button"
              aria-pressed={active}
              onClick={() => {
                setFilter(tab.key)
                setExpanded(false)
              }}
              className={`inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors ${
                active
                  ? 'border-blue-600 bg-blue-600 text-white'
                  : 'border-gray-200 bg-white text-gray-600 hover:border-blue-300 hover:text-blue-700'
              }`}
            >
              <tab.Icon size={15} />
              {tab.label}
              <span
                className={`rounded-full px-1.5 text-xs font-semibold tabular-nums ${
                  active ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'
                }`}
              >
                {counts[tab.key]}
              </span>
            </button>
          )
        })}
      </div>

      {/* Monthly calendar grid */}
      <Panel className="overflow-hidden">
        <div className="grid grid-cols-7 border-b border-line bg-mint-200">
          {WEEKDAYS.map(day => (
            <div
              key={day}
              className="py-2.5 text-center text-[11px] font-semibold uppercase tracking-wider text-jpc-700"
            >
              {day}
            </div>
          ))}
        </div>

        <div className="grid grid-cols-7">
          {monthCells.map(cell => {
            const inMonth = cell.getMonth() === month - 1 && cell.getFullYear() === year
            const isToday = isSameDay(cell, today)
            const isSelected = selectedDate ? isSameDay(cell, selectedDate) : false
            const dayEvents = eventsByDate.get(isoDate(cell)) ?? []
            const dateLabel = cell.toLocaleDateString('en-US', {
              weekday: 'long',
              year: 'numeric',
              month: 'long',
              day: 'numeric',
            })

            const hiddenCount = Math.max(0, dayEvents.length - CELL_LABEL_LIMIT)

            return (
              <div
                key={isoDate(cell)}
                className={`relative flex min-h-[76px] flex-col gap-1 overflow-hidden border-b border-r border-line p-1.5 text-left transition-colors sm:min-h-[116px] [&:nth-child(7n)]:border-r-0 [&:nth-last-child(-n+7)]:border-b-0 ${
                  inMonth ? 'bg-white' : 'bg-mint-50'
                } ${isSelected ? 'ring-2 ring-inset ring-jpc-600' : ''}`}
              >
                <button
                  type="button"
                  onClick={() => selectDate(cell)}
                  aria-pressed={isSelected}
                  aria-label={
                    dayEvents.length > 0
                      ? `${dateLabel}, ${dayEvents.length} event${dayEvents.length === 1 ? '' : 's'}`
                      : dateLabel
                  }
                  className={`grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-semibold tabular-nums transition-colors hover:bg-mint-200 ${
                    isToday ? 'bg-jpc-600 text-white' : inMonth ? 'text-slate-700' : 'text-slate-400'
                  }`}
                >
                  {cell.getDate()}
                </button>

                {dayEvents.length > 0 ? (
                  <div className="flex w-full min-w-0 flex-col gap-0.5">
                    {dayEvents.slice(0, CELL_LABEL_LIMIT).map(event => {
                      const style = styleOf(event)
                      return (
                        <button
                          key={`${event.type}-${event.id}`}
                          type="button"
                          onClick={() => openEventDetails(event)}
                          title={`${style.label}: ${event.title}`}
                          className={`flex w-full min-w-0 items-center gap-1 rounded px-1 py-0.5 text-left text-[10px] font-semibold leading-tight ${style.chip}`}
                        >
                          <span className="shrink-0">{style.label}</span>{' '}
                          <span className="truncate font-medium">{event.title}</span>
                        </button>
                      )
                    })}
                    {hiddenCount > 0 ? (
                      <button
                        type="button"
                        onClick={() => setDayModalDate(cell)}
                        className="w-full rounded px-1 py-0.5 text-left text-[10px] font-semibold text-slate-500 transition-colors hover:bg-mint-200 hover:text-jpc-700"
                      >
                        +{hiddenCount} more
                      </button>
                    ) : null}
                  </div>
                ) : null}
              </div>
            )
          })}
        </div>

        {/* Selected-day detail strip */}
        <div className="border-t border-line">
          <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <h2 className="text-sm font-semibold text-slate-900">
              {selectedDate
                ? selectedDate.toLocaleDateString('en-US', {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                  })
                : 'Select a date'}
              {selectedDate && isSameDay(selectedDate, today) ? (
                <span className="ml-2 rounded-full bg-mint-200 px-2 py-0.5 text-[11px] font-semibold text-jpc-700">
                  Today
                </span>
              ) : null}
            </h2>
            {selectedDate ? (
              <span className="text-xs text-slate-500">
                {selectedDayEvents.length === 0
                  ? 'No events'
                  : `${selectedDayEvents.length} event${selectedDayEvents.length === 1 ? '' : 's'}`}
              </span>
            ) : null}
          </div>

          {!selectedDate ? (
            <p className="px-4 pb-4 text-xs text-slate-400">
              Pick a date to see its scheduled tasks, reports and announcements.
            </p>
          ) : selectedDayEvents.length === 0 ? (
            <p className="px-4 pb-4 text-xs text-slate-400">Nothing scheduled on this day.</p>
          ) : (
            <ul className="divide-y divide-line border-t border-line">
              {selectedDayEvents.map(event => (
                <EventRow
                  key={`${event.type}-${event.id}`}
                  event={event}
                  today={today}
                  onOpen={() => setSelected(event)}
                />
              ))}
            </ul>
          )}
        </div>
      </Panel>

      {/* Upcoming Events panel */}
      <Panel>
        <PanelHeader
          title="Upcoming Events"
          description={`Nearest date first · ${monthName} ${year}`}
          action={
            canExpand ? (
              <button
                type="button"
                onClick={() => setExpanded(value => !value)}
                className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
              >
                {expanded ? 'Show less' : `View All (${filtered.length})`}
              </button>
            ) : null
          }
        />

        {error ? (
          <div role="alert" className="m-4 space-y-2 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p>{error}</p>
            <button
              type="button"
              onClick={load}
              className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold hover:bg-rose-100"
            >
              <RefreshCw size={13} /> Retry
            </button>
          </div>
        ) : loading ? (
          <div className="space-y-3 p-4" aria-hidden="true">
            {Array.from({ length: 4 }, (_, index) => (
              <SkeletonBlock key={index} className="h-16" />
            ))}
          </div>
        ) : filtered.length === 0 ? (
          <div className="px-5 py-12 text-center">
            <span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl border border-gray-100 bg-gray-50 text-gray-400">
              <CalendarX2 size={22} />
            </span>
            <p className="mt-3 text-sm font-medium text-gray-600">
              {events.length === 0 ? 'No events this month.' : 'No events match this category.'}
            </p>
            <p className="mt-1 text-xs text-gray-400">
              Tasks, reports and announcements you are assigned will appear here.
            </p>
          </div>
        ) : (
          <ul className="divide-y divide-gray-100">
            {visible.map(event => (
              <EventRow
                key={`${event.type}-${event.id}`}
                event={event}
                today={today}
                onOpen={() => setSelected(event)}
              />
            ))}
          </ul>
        )}
      </Panel>

      {dayModalDate ? (
        <DayEventsModal
          date={dayModalDate}
          events={dayModalEvents}
          today={today}
          onClose={() => setDayModalDate(null)}
          onOpenEvent={event => {
            setDayModalDate(null)
            setSelected(event)
          }}
        />
      ) : null}

      {selected ? <EventDetailModal event={selected} onClose={() => setSelected(null)} /> : null}
    </div>
  )
}

/* ── Event row ───────────────────────────────────────────────────────────── */

function EventRow({
  event,
  today,
  onOpen,
}: {
  event: EmployeeCalendarEvent
  today: Date
  onOpen: () => void
}) {
  const style = styleOf(event)
  const { Icon } = style
  const relative = relativeLabel(event.date, today)

  return (
    <li
      className={`group flex items-start gap-3 px-4 py-3.5 sm:gap-4 sm:px-5 ${
        event.is_overdue ? 'border-l-2 border-l-rose-400 bg-rose-50/30' : ''
      }`}
    >
      <span
        aria-hidden="true"
        className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl border ${style.container}`}
      >
        <Icon size={20} strokeWidth={1.75} className={style.icon} />
      </span>

      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-1.5">
          <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold ${style.chip}`}>
            {event.category}
          </span>
          {event.is_overdue ? (
            <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
              <AlarmClock size={11} /> Overdue
            </span>
          ) : null}
        </div>

        <button
          type="button"
          onClick={onOpen}
          title={event.title}
          className="mt-1 block max-w-full truncate text-left text-sm font-semibold text-gray-900 hover:text-blue-700"
        >
          {event.title}
        </button>

        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
          <span className="inline-flex items-center gap-1">
            <CalendarDays size={13} /> {formatEventDate(event.date)}
          </span>
          {event.time ? (
            <span className="inline-flex items-center gap-1">
              <Clock size={13} /> {event.time}
            </span>
          ) : null}
          {relative ? <span className="font-medium text-gray-400">{relative}</span> : null}
        </div>
      </div>

      <div className="flex shrink-0 flex-col items-end gap-2">
        <StatusPill status={event.status} />
        {event.link ? (
          <Link
            to={event.link}
            aria-label={`Open ${event.title}`}
            className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:underline"
          >
            <span className="hidden sm:inline">Open</span>
            <ExternalLink size={13} />
          </Link>
        ) : null}
      </div>
    </li>
  )
}

/* ── Day events ("+X more") ──────────────────────────────────────────────── */

/**
 * Lists every event on a single day — opened by the "+X more" button inside a
 * date cell that has more events than the cell previews. Selecting a row opens
 * that event's full details.
 */
function DayEventsModal({
  date,
  events,
  today,
  onClose,
  onOpenEvent,
}: {
  date: Date
  events: EmployeeCalendarEvent[]
  today: Date
  onClose: () => void
  onOpenEvent: (event: EmployeeCalendarEvent) => void
}) {
  useEffect(() => {
    const onKeyDown = (keyEvent: KeyboardEvent) => {
      if (keyEvent.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [onClose])

  const dateLabel = date.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  })

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={`Events on ${dateLabel}`}
      onClick={onClose}
      className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 backdrop-blur-sm sm:items-center sm:p-4"
    >
      <div
        onClick={clickEvent => clickEvent.stopPropagation()}
        className="max-h-[85vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl"
      >
        <div className="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
          <div className="min-w-0">
            <h2 className="text-sm font-semibold text-slate-900">{dateLabel}</h2>
            <p className="mt-0.5 text-xs text-slate-500">
              {events.length} event{events.length === 1 ? '' : 's'}
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700"
          >
            <X size={16} />
          </button>
        </div>

        <ul className="divide-y divide-line">
          {events.map(event => {
            const style = styleOf(event)
            const { Icon } = style
            const relative = relativeLabel(event.date, today)

            return (
              <li key={`${event.type}-${event.id}`}>
                <button
                  type="button"
                  onClick={() => onOpenEvent(event)}
                  className="flex w-full items-start gap-3 px-5 py-3.5 text-left transition-colors hover:bg-mint-100"
                >
                  <span
                    aria-hidden="true"
                    className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl border ${style.container}`}
                  >
                    <Icon size={20} strokeWidth={1.75} className={style.icon} />
                  </span>

                  <span className="min-w-0 flex-1">
                    <span
                      className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${style.chip}`}
                    >
                      {event.category}
                    </span>
                    <span className="mt-1 block truncate text-sm font-semibold text-gray-900">
                      {event.title}
                    </span>
                    <span className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                      {event.time ? (
                        <span className="inline-flex items-center gap-1">
                          <Clock size={13} /> {event.time}
                        </span>
                      ) : null}
                      {relative ? <span className="font-medium text-gray-400">{relative}</span> : null}
                    </span>
                  </span>

                  <StatusPill status={event.status} />
                </button>
              </li>
            )
          })}
        </ul>
      </div>
    </div>
  )
}

/* ── Event detail ────────────────────────────────────────────────────────── */

function EventDetailModal({ event, onClose }: { event: EmployeeCalendarEvent; onClose: () => void }) {
  const style = styleOf(event)
  const { Icon } = style
  const isDeadlineEvent = event.type === 'task' || event.type === 'report'

  useEffect(() => {
    const onKeyDown = (keyEvent: KeyboardEvent) => {
      if (keyEvent.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [onClose])

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={event.title}
      onClick={onClose}
      className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 backdrop-blur-sm sm:items-center sm:p-4"
    >
      <div
        onClick={clickEvent => clickEvent.stopPropagation()}
        className="max-h-[85vh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl sm:max-w-lg sm:rounded-2xl"
      >
        <div className="flex items-start justify-between gap-3">
          <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold ${style.chip}`}>
            <Icon size={13} /> {event.category}
          </span>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close details"
            className="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700"
          >
            <X size={16} />
          </button>
        </div>

        <div className="mt-3 flex items-start gap-3">
          <span
            aria-hidden="true"
            className={`grid h-11 w-11 shrink-0 place-items-center rounded-2xl border ${style.container}`}
          >
            <Icon size={20} strokeWidth={1.75} className={style.icon} />
          </span>
          <div className="min-w-0">
            <h2 className="break-words text-lg font-bold text-gray-900">{event.title}</h2>
            <div className="mt-1.5 flex flex-wrap items-center gap-2">
              <StatusPill status={event.status} />
              {event.is_overdue ? (
                <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                  <AlarmClock size={11} /> Overdue
                </span>
              ) : null}
            </div>
          </div>
        </div>

        {event.description ? (
          <p className="mt-4 whitespace-pre-line break-words text-sm text-gray-700">{event.description}</p>
        ) : null}

        <dl className="mt-4 grid grid-cols-1 gap-x-4 gap-y-3 rounded-xl border border-gray-200 p-3 text-xs sm:grid-cols-2">
          <div>
            {/* Tasks and reports are scheduled by their deadline; everything
                else (announcements) simply happens on a date. */}
            <dt className="font-semibold uppercase tracking-wider text-gray-400">
              {isDeadlineEvent ? 'Deadline' : 'Date'}
            </dt>
            <dd className="mt-0.5 text-gray-700">
              {formatEventDate(event.deadline ?? event.date)}
            </dd>
          </div>
          {event.time ? (
            <div>
              <dt className="font-semibold uppercase tracking-wider text-gray-400">Time</dt>
              <dd className="mt-0.5 text-gray-700">{event.time}</dd>
            </div>
          ) : null}
          <div>
            <dt className="font-semibold uppercase tracking-wider text-gray-400">Category</dt>
            <dd className="mt-0.5 text-gray-700">{event.category}</dd>
          </div>
          {event.assigned_person ? (
            <div>
              <dt className="font-semibold uppercase tracking-wider text-gray-400">Assigned to</dt>
              <dd className="mt-0.5 text-gray-700">{event.assigned_person}</dd>
            </div>
          ) : null}
          <div>
            <dt className="font-semibold uppercase tracking-wider text-gray-400">Status</dt>
            <dd className="mt-0.5 capitalize text-gray-700">{humaniseStatus(event.status)}</dd>
          </div>
        </dl>

        <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
          {event.link ? (
            <Link
              to={event.link}
              onClick={onClose}
              className="inline-flex items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
              Open related record <ExternalLink size={14} />
            </Link>
          ) : null}
          <button
            type="button"
            onClick={onClose}
            className="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  )
}
