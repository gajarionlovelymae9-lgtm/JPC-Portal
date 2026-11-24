import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { CalendarDays, ExternalLink } from 'lucide-react'
import { getMyCalendar } from '../../services/employeePortalService'
import type {
  EmployeeCalendarEvent,
  EmployeeCalendarEventType,
} from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'
import { Panel, PanelHeader, SkeletonBlock } from './primitives'
import { WEEKDAYS, buildMonthCells, isoDate, isSameDay } from '../../utils/calendar'

/**
 * Dense, read-only month grid for the dashboard. The full My Calendar page owns
 * month navigation, filters and detail modals; this widget only answers "which
 * days this month have something scheduled?", so it stays compact and links out
 * to the full page for anything deeper.
 */

/** A distinct dot colour per event type, matching the full calendar palette. */
const DOT_TONES: Record<EmployeeCalendarEventType, string> = {
  task: 'bg-blue-500',
  report: 'bg-emerald-500',
  announcement: 'bg-violet-500',
}

/** How many dots a single day renders before collapsing into a "+N" count. */
const DOT_LIMIT = 3

/** How many of the selected day's events the detail strip lists. */
const DETAIL_LIMIT = 4

export default function DashboardCalendar() {
  const today = useMemo(() => new Date(), [])
  const [events, setEvents] = useState<EmployeeCalendarEvent[]>([])
  const [loading, setLoading] = useState(true)
  const [selectedDate, setSelectedDate] = useState<Date>(today)

  const month = today.getMonth() + 1
  const year = today.getFullYear()

  useEffect(() => {
    let active = true
    setLoading(true)

    getMyCalendar({ month, year })
      .then(response => {
        if (!active) return
        setEvents(Array.isArray(response.data?.events) ? response.data.events : [])
      })
      .catch(() => {
        // A calendar fetch must never take the dashboard down — degrade to an
        // empty grid rather than surfacing an error for one widget.
        if (active) setEvents([])
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [month, year])

  const monthCells = useMemo(() => buildMonthCells(year, month), [month, year])

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

  const selectedEvents = eventsByDate.get(isoDate(selectedDate)) ?? []
  const monthName = today.toLocaleString('default', { month: 'long' })

  return (
    <Panel>
      <PanelHeader
        title="My Calendar"
        description={`${monthName} ${year} at a glance`}
        action={
          <Link
            to={PATHS.employeeCalendar}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            Open Calendar →
          </Link>
        }
      />

      {loading ? (
        <div className="p-4">
          <SkeletonBlock className="h-64" />
        </div>
      ) : (
        <>
          <div className="grid grid-cols-7 border-b border-gray-100 bg-gray-50">
            {WEEKDAYS.map(day => (
              <div
                key={day}
                className="py-2 text-center text-[10px] font-semibold uppercase tracking-wider text-gray-400"
              >
                {day}
              </div>
            ))}
          </div>

          <div className="grid grid-cols-7">
            {monthCells.map(cell => {
              const inMonth = cell.getMonth() === month - 1 && cell.getFullYear() === year
              const isToday = isSameDay(cell, today)
              const isSelected = isSameDay(cell, selectedDate)
              const dayEvents = eventsByDate.get(isoDate(cell)) ?? []
              const hidden = Math.max(0, dayEvents.length - DOT_LIMIT)
              const dateLabel = cell.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric',
              })

              return (
                <button
                  key={isoDate(cell)}
                  type="button"
                  onClick={() => setSelectedDate(cell)}
                  aria-pressed={isSelected}
                  aria-label={
                    dayEvents.length > 0
                      ? `${dateLabel}, ${dayEvents.length} event${dayEvents.length === 1 ? '' : 's'}`
                      : dateLabel
                  }
                  className={`flex min-h-[44px] flex-col items-center gap-1 border-b border-r border-gray-100 py-1.5 transition-colors sm:min-h-[58px] [&:nth-child(7n)]:border-r-0 ${
                    isSelected ? 'bg-blue-50' : 'hover:bg-gray-50'
                  }`}
                >
                  <span
                    className={`grid h-6 w-6 place-items-center rounded-full text-xs font-semibold tabular-nums ${
                      isToday
                        ? 'bg-blue-600 text-white'
                        : inMonth
                          ? 'text-gray-700'
                          : 'text-gray-300'
                    }`}
                  >
                    {cell.getDate()}
                  </span>
                  <span className="flex h-1.5 items-center gap-0.5" aria-hidden="true">
                    {dayEvents.slice(0, DOT_LIMIT).map(event => (
                      <span
                        key={`${event.type}-${event.id}`}
                        className={`h-1.5 w-1.5 rounded-full ${DOT_TONES[event.type] ?? 'bg-gray-400'}`}
                      />
                    ))}
                    {hidden > 0 ? (
                      <span className="text-[9px] font-semibold leading-none text-gray-400">
                        +{hidden}
                      </span>
                    ) : null}
                  </span>
                </button>
              )
            })}
          </div>

          <div className="border-t border-gray-100 px-4 py-3">
            <p className="mb-2 flex items-center gap-2 text-xs font-semibold text-gray-500">
              <CalendarDays size={13} />
              {selectedDate.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'short',
                day: 'numeric',
              })}
              {isSameDay(selectedDate, today) ? (
                <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700">
                  Today
                </span>
              ) : null}
            </p>

            {selectedEvents.length === 0 ? (
              <p className="text-xs text-gray-400">Nothing scheduled on this day.</p>
            ) : (
              <ul className="space-y-1.5">
                {selectedEvents.slice(0, DETAIL_LIMIT).map(event => (
                  <li key={`${event.type}-${event.id}`}>
                    <Link
                      to={event.link ?? PATHS.employeeCalendar}
                      className="flex items-center gap-2 text-sm text-gray-700 hover:text-blue-600"
                    >
                      <span
                        aria-hidden="true"
                        className={`h-2 w-2 shrink-0 rounded-full ${DOT_TONES[event.type] ?? 'bg-gray-400'}`}
                      />
                      <span className="truncate">{event.title}</span>
                      <ExternalLink size={12} className="shrink-0 text-gray-300" />
                    </Link>
                  </li>
                ))}
                {selectedEvents.length > DETAIL_LIMIT ? (
                  <li className="text-xs text-gray-400">
                    +{selectedEvents.length - DETAIL_LIMIT} more
                  </li>
                ) : null}
              </ul>
            )}
          </div>
        </>
      )}
    </Panel>
  )
}
