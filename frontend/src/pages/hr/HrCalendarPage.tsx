import { useCallback, useEffect, useMemo, useState } from 'react'
import { getHrCalendar, type HrCalendarEvent } from '../../services/hrCalendarService'
import { getHrOptions } from '../../services/hrService'
import { getApiErrorMessage } from '../../utils/apiError'

type ViewMode = 'month' | 'week' | 'day'

/**
 * Which visual kind an event belongs to. Announcement rows are split by
 * their category: announcements (blue), events (green) and holidays (red).
 */
type EventKind =
  | 'announcement' | 'event' | 'holiday'
  | 'task_deadline' | 'report_deadline' | 'target_period' | 'achievement'

const KIND_CONFIG: Record<EventKind, { label: string; chip: string; dot: string; icon: string }> = {
  announcement: { label: 'Announcement', chip: 'bg-blue-100 text-blue-800 border-blue-200', dot: 'bg-blue-500', icon: '📢' },
  event: { label: 'Event', chip: 'bg-emerald-100 text-emerald-800 border-emerald-200', dot: 'bg-emerald-500', icon: '🎉' },
  holiday: { label: 'Holiday', chip: 'bg-red-100 text-red-800 border-red-200', dot: 'bg-red-500', icon: '🏖️' },
  task_deadline: { label: 'Task Deadline', chip: 'bg-indigo-100 text-indigo-800 border-indigo-200', dot: 'bg-indigo-500', icon: '📋' },
  report_deadline: { label: 'Report Deadline', chip: 'bg-sky-100 text-sky-800 border-sky-200', dot: 'bg-sky-500', icon: '📄' },
  target_period: { label: 'KPI Period End', chip: 'bg-violet-100 text-violet-800 border-violet-200', dot: 'bg-violet-500', icon: '🎯' },
  achievement: { label: 'Achievement', chip: 'bg-amber-100 text-amber-800 border-amber-200', dot: 'bg-amber-500', icon: '🏆' },
}

function kindOf(event: HrCalendarEvent): EventKind {
  if (event.type === 'announcement') {
    const category = event.announcement?.category ?? ''
    if (category === 'holiday') return 'holiday'
    if (category === 'event') return 'event'
    return 'announcement'
  }
  return 'announcement'
}

function isoDate(value: Date): string {
  // Local date arithmetic (not UTC) avoids off-by-one days.
  return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`
}

function startOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

function endOfMonth(date: Date): Date {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0)
}

/** Sunday-start weeks, matching typical school calendars. */
function startOfWeek(date: Date): Date {
  const day = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  day.setDate(day.getDate() - day.getDay())
  return day
}

function addDays(date: Date, days: number): Date {
  const next = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  next.setDate(next.getDate() + days)
  return next
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

export function HrCalendarPage() {
  const today = useMemo(() => new Date(), [])
  const [view, setView] = useState<ViewMode>('month')
  const [cursor, setCursor] = useState<Date>(() => startOfMonth(new Date()))
  const [events, setEvents] = useState<HrCalendarEvent[]>([])
  const [selected, setSelected] = useState<HrCalendarEvent | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [kindFilter, setKindFilter] = useState<'all' | EventKind>('all')
  const [search, setSearch] = useState('')
  const [departments, setDepartments] = useState<Array<{ value: string; label: string }>>([])
  const [departmentId, setDepartmentId] = useState('')

  // API window the current view needs: month view fetches the whole month;
  // week and day fetch only the visible span.
  const range = useMemo(() => {
    if (view === 'week') {
      const start = startOfWeek(cursor)
      return { from: isoDate(start), to: isoDate(addDays(start, 6)) }
    }
    if (view === 'day') {
      return { from: isoDate(cursor), to: isoDate(cursor) }
    }
    return { from: isoDate(startOfMonth(cursor)), to: isoDate(endOfMonth(cursor)) }
  }, [view, cursor])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const response = await getHrCalendar({
        from: range.from,
        to: range.to,
        department_id: departmentId || undefined,
      })
      setEvents(Array.isArray(response.data?.events) ? response.data.events : [])
    } catch (cause) {
      setError(getApiErrorMessage(cause, 'Unable to load the calendar.'))
    } finally {
      setLoading(false)
    }
  }, [range.from, range.to, departmentId])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    getHrOptions()
      .then(res => setDepartments(
        (res.data?.departments ?? [])
          .map((department: { id?: number | string; name?: string }) => ({
            value: String(department.id ?? ''),
            label: String(department.name ?? ''),
          }))
          .filter(department => department.value !== '' && department.label),
      ))
      .catch(() => null)
  }, [])

  const filtered = useMemo(() => {
    return events.filter(event => {
      const kind = event.type === 'announcement' ? kindOf(event) : otherKind(event)
      if (kindFilter !== 'all' && kind !== kindFilter) return false
      const haystack = `${event.title} ${event.description} ${event.employee ?? ''} ${event.department ?? ''}`
      if (search && !haystack.toLowerCase().includes(search.toLowerCase())) return false
      return true
    })
  }, [events, kindFilter, search])

  const byDate = useMemo(() => {
    const map = new Map<string, HrCalendarEvent[]>()
    for (const event of filtered) {
      const list = map.get(event.date) ?? []
      list.push(event)
      map.set(event.date, list)
    }
    for (const list of map.values()) {
      list.sort((a, b) => (a.time ?? '').localeCompare(b.time ?? ''))
    }
    return map
  }, [filtered])

  // Fixed 6 x 7 grid keeps the month cell heights stable across months.
  const monthCells = useMemo(() => {
    const first = startOfMonth(cursor)
    const gridStart = startOfWeek(first)
    return Array.from({ length: 42 }, (_, index) => addDays(gridStart, index))
  }, [cursor])

  const weekCells = useMemo(() => {
    const start = startOfWeek(cursor)
    return Array.from({ length: 7 }, (_, index) => addDays(start, index))
  }, [cursor])

  const dayCells = useMemo(() => [cursor], [cursor])

  const step = (direction: -1 | 1) => {
    setCursor(previous => {
      if (view === 'month') return new Date(previous.getFullYear(), previous.getMonth() + direction, 1)
      if (view === 'week') return addDays(previous, 7 * direction)
      return addDays(previous, direction)
    })
  }

  const title = useMemo(() => {
    if (view === 'month') {
      return cursor.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
    }
    if (view === 'week') {
      const start = startOfWeek(cursor)
      const end = addDays(start, 6)
      return `${start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}`
    }
    return cursor.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
  }, [view, cursor])

  return (
    <div className="p-4 sm:p-6 space-y-5 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Calendar</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Announcements, holidays, events, deadlines and KPI periods in one timeline
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <div className="flex gap-1 rounded-xl bg-slate-100 dark:bg-slate-800 p-1" role="tablist" aria-label="Calendar view">
            {(['month', 'week', 'day'] as ViewMode[]).map(mode => (
              <button
                key={mode}
                type="button"
                aria-pressed={view === mode}
                onClick={() => {
                  setView(mode)
                  setCursor(new Date(cursor.getFullYear(), cursor.getMonth(), 1))
                }}
                className={`px-3 py-1.5 rounded-lg text-xs font-bold capitalize ${view === mode ? 'bg-white dark:bg-slate-700 shadow text-blue-700 dark:text-blue-300' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800'}`}
              >
                {mode}
              </button>
            ))}
          </div>
          <button type="button" onClick={() => step(-1)} className="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700">
            ← Previous
          </button>
          <button
            type="button"
            onClick={() => setCursor(startOfMonth(new Date()))}
            className="rounded-lg bg-blue-600 px-3 py-2 text-sm font-bold text-white hover:bg-blue-700"
          >
            Today
          </button>
          <button type="button" onClick={() => step(1)} className="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700">
            Next →
          </button>
        </div>
      </div>

      {/* Colour legend */}
      <div className="flex flex-wrap gap-x-4 gap-y-1.5">
        {(Object.keys(KIND_CONFIG) as EventKind[]).map(kind => (
          <span key={kind} className="flex items-center gap-1.5 text-[11px] text-slate-600 dark:text-slate-300">
            <span className={`w-2.5 h-2.5 rounded-sm ${KIND_CONFIG[kind].dot}`} />
            {KIND_CONFIG[kind].label}
          </span>
        ))}
      </div>

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-3">
        <input
          type="search"
          aria-label="Search events"
          value={search}
          onChange={event => setSearch(event.target.value)}
          placeholder="Search events…"
          className="w-full sm:flex-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3.5 py-2.5 text-sm text-slate-800 dark:text-slate-100 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
        />
        <select
          aria-label="Filter by type"
          value={kindFilter}
          onChange={event => setKindFilter(event.target.value as 'all' | EventKind)}
          className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm text-slate-700 dark:text-slate-200"
        >
          <option value="all">All types</option>
          {(Object.keys(KIND_CONFIG) as EventKind[]).map(kind => (
            <option key={kind} value={kind}>{KIND_CONFIG[kind].label}</option>
          ))}
        </select>
        {departments.length > 0 ? (
          <select
            aria-label="Filter by department"
            value={departmentId}
            onChange={event => setDepartmentId(event.target.value)}
            className="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm text-slate-700 dark:text-slate-200"
          >
            <option value="">All departments</option>
            {departments.map(department => (
              <option key={department.value} value={department.value}>{department.label}</option>
            ))}
          </select>
        ) : null}
      </div>

      {loading ? (
        <div className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-10 text-center">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent" />
          <p className="text-sm text-slate-500 mt-3">Loading calendar…</p>
        </div>
      ) : error ? (
        <div className="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm space-y-2" role="alert">
          <p>{error}</p>
          <button type="button" onClick={() => void load()} className="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-bold hover:bg-red-100">
            Retry
          </button>
        </div>
      ) : (
        <>
          <div className="text-sm font-bold text-slate-900 dark:text-white">{title}</div>

          {view === 'month' ? (
            <div className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
              <div className="grid grid-cols-7 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-700">
                {WEEKDAYS.map(day => (
                  <div key={day} className="py-2">{day}</div>
                ))}
              </div>
              <div className="grid grid-cols-7">
                {monthCells.map(cell => {
                  const inMonth = cell.getMonth() === cursor.getMonth()
                  const isToday = isoDate(cell) === isoDate(today)
                  const dayEvents = byDate.get(isoDate(cell)) ?? []
                  return (
                    <div
                      key={isoDate(cell)}
                      className={`min-h-[96px] border-b border-r border-slate-100 dark:border-slate-700/60 p-1.5 ${inMonth ? 'bg-white dark:bg-slate-800' : 'bg-slate-50 dark:bg-slate-900/40'}`}
                    >
                      <div className="flex items-center gap-1 mb-1">
                        <span className={`text-xs font-bold ${inMonth ? 'text-slate-700 dark:text-slate-200' : 'text-slate-300'}`}>
                          {cell.getDate()}
                        </span>
                        {isToday ? <span className="w-1.5 h-1.5 rounded-full bg-blue-600" aria-label="Today" /> : null}
                      </div>
                      {dayEvents.length > 0 ? (
                        <div className="space-y-1">
                          {dayEvents.slice(0, 2).map(event => (
                            <EventChip key={event.id} event={event} onClick={() => {
                              setView('day')
                              setCursor(cell)
                              setSelected(event)
                            }} compact />
                          ))}
                          {dayEvents.length > 2 ? (
                            <button
                              type="button"
                              onClick={() => {
                                setView('day')
                                setCursor(cell)
                              }}
                              className="text-[10px] font-semibold text-blue-600 hover:underline"
                            >
                              +{dayEvents.length - 2} more
                            </button>
                          ) : null}
                        </div>
                      ) : null}
                    </div>
                  )
                })}
              </div>
            </div>
          ) : (
            <div className={`grid gap-3 ${view === 'week' ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4' : 'grid-cols-1'}`}>
              {(view === 'week' ? weekCells : dayCells).map(cell => {
                const dayEvents = byDate.get(isoDate(cell)) ?? []
                const isToday = isoDate(cell) === isoDate(today)
                return (
                  <div key={isoDate(cell)} className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                    <h3 className={`text-xs font-bold uppercase tracking-wider mb-2 ${isToday ? 'text-blue-700 dark:text-blue-300' : 'text-slate-500 dark:text-slate-400'}`}>
                      {cell.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' })}
                      {isToday ? ' · Today' : ''}
                    </h3>
                    {dayEvents.length === 0 ? (
                      <p className="text-xs text-slate-400 py-2">No events.</p>
                    ) : (
                      <ul className="space-y-2">
                        {dayEvents.map(event => (
                          <li key={event.id}>
                            <EventChip event={event} onClick={() => setSelected(event)} />
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                )
              })}
            </div>
          )}

          {filtered.length === 0 ? (
            <div className="text-center py-10 text-sm text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl">
              No events in this {view}. Try another period or clear the filters.
            </div>
          ) : null}
        </>
      )}

      {selected ? <EventDetail event={selected} onClose={() => setSelected(null)} /> : null}
    </div>
  )
}

/** Non-announcement event kinds keep their operational type. */
function otherKind(event: HrCalendarEvent): 'task_deadline' | 'report_deadline' | 'target_period' | 'achievement' {
  if (event.type === 'task_deadline' || event.type === 'report_deadline' ||
      event.type === 'target_period' || event.type === 'achievement') {
    return event.type
  }
  return 'task_deadline'
}

/* ── Event chip ──────────────────────────────────────────────────────────── */

function EventChip({ event, onClick, compact = false }: {
  event: HrCalendarEvent
  onClick: () => void
  compact?: boolean
}) {
  const kind = event.type === 'announcement' ? kindOf(event) : otherKind(event)
  const config = KIND_CONFIG[kind]
  return (
    <button
      type="button"
      onClick={onClick}
      className={`w-full text-left rounded-lg border px-2 py-1 text-[11px] font-semibold ${config.chip} hover:brightness-95 transition`}
    >
      <span className="flex items-center gap-1 min-w-0">
        <span aria-hidden>{config.icon}</span>
        {!compact && event.time ? <span className="text-[10px] text-slate-500">{event.time}</span> : null}
        <span className="truncate">{event.title}</span>
      </span>
    </button>
  )
}

/* ── Detail modal ────────────────────────────────────────────────────────── */

function EventDetail({ event, onClose }: { event: HrCalendarEvent; onClose: () => void }) {
  const kind = event.type === 'announcement' ? kindOf(event) : otherKind(event)
  const detail = event.announcement
  const humanise = (value: string | null | undefined) =>
    value ? value.replace(/[_-]+/g, ' ').replace(/\b\w/g, character => character.toUpperCase()) : null

  const audienceLabel = detail
    ? detail.audience_type === 'all'
      ? 'All staff'
      : detail.audience_type === 'role'
        ? `Role: ${humanise(detail.audience_id) ?? detail.audience_id ?? '—'}`
        : detail.audience_type === 'department'
          ? 'Specific department'
          : 'Specific person'
    : null

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50"
      role="dialog"
      aria-modal="true"
      aria-label={event.title}
      onClick={onClose}
    >
      <div
        className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl max-h-[85vh] w-full max-w-lg overflow-y-auto p-5 space-y-4"
        onClick={clickEvent => clickEvent.stopPropagation()}
      >
        <div className="flex items-start justify-between gap-3">
          <span className={`text-[11px] font-bold px-2.5 py-1 rounded-full border ${KIND_CONFIG[kind].chip}`}>
            {KIND_CONFIG[kind].icon} {KIND_CONFIG[kind].label}
          </span>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            className="rounded-lg p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 text-lg leading-none"
          >
            ✕
          </button>
        </div>

        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white break-words">{event.title}</h2>
          <p className="text-sm text-slate-700 dark:text-slate-300 mt-2 whitespace-pre-line break-words">
            {detail?.message ?? event.description}
          </p>
        </div>

        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-xs">
          <div>
            <dt className="font-bold uppercase tracking-wider text-slate-500">Date</dt>
            <dd className="text-slate-700 dark:text-slate-200">{event.date}{event.time ? ` · ${event.time}` : ''}</dd>
          </div>
          {detail?.end_date && detail.end_date.slice(0, 10) !== event.date ? (
            <div>
              <dt className="font-bold uppercase tracking-wider text-slate-500">Ends</dt>
              <dd className="text-slate-700 dark:text-slate-200">{detail.end_date.slice(0, 10)}</dd>
            </div>
          ) : null}
          {detail?.category ? (
            <div>
              <dt className="font-bold uppercase tracking-wider text-slate-500">Category</dt>
              <dd className="text-slate-700 dark:text-slate-200 capitalize">{detail.category}</dd>
            </div>
          ) : null}
          {audienceLabel ? (
            <div>
              <dt className="font-bold uppercase tracking-wider text-slate-500">Audience</dt>
              <dd className="text-slate-700 dark:text-slate-200">{audienceLabel}</dd>
            </div>
          ) : null}
          {event.department ? (
            <div>
              <dt className="font-bold uppercase tracking-wider text-slate-500">Department</dt>
              <dd className="text-slate-700 dark:text-slate-200 break-words">{event.department}</dd>
            </div>
          ) : null}
          {event.employee ? (
            <div>
              <dt className="font-bold uppercase tracking-wider text-slate-500">Employee</dt>
              <dd className="text-slate-700 dark:text-slate-200 break-words">{event.employee}</dd>
            </div>
          ) : null}
          {detail?.priority ? (
            <div>
              <dt className="font-bold uppercase tracking-wider text-slate-500">Priority</dt>
              <dd className="text-slate-700 dark:text-slate-200 capitalize">{detail.priority}</dd>
            </div>
          ) : null}
          {detail?.created_by ? (
            <div>
              <dt className="font-bold uppercase tracking-wider text-slate-500">Published by</dt>
              <dd className="text-slate-700 dark:text-slate-200 break-words">{detail.created_by}</dd>
            </div>
          ) : null}
          <div>
            <dt className="font-bold uppercase tracking-wider text-slate-500">Status</dt>
            <dd className="text-slate-700 dark:text-slate-200">{humanise(event.status) ?? event.status}</dd>
          </div>
        </dl>

        {event.link ? (
          <a
            href={event.link}
            className="inline-block text-sm font-bold text-blue-600 hover:underline"
            onClick={() => onClose()}
          >
            Open related record
          </a>
        ) : null}

        <button
          type="button"
          onClick={onClose}
          className="w-full rounded-xl bg-slate-100 dark:bg-slate-700 px-4 py-2.5 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600"
        >
          Back to calendar
        </button>
      </div>
    </div>
  )
}
