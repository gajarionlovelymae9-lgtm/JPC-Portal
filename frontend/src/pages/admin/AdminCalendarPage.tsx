import { useMemo, useState } from 'react'
import { getAdminCalendar, getAdminMonitoringOptions } from '../../services/adminDashboardService'
import type { CalendarEvent, CalendarSource } from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import {
  AdminPage,
  Badge,
  Button,
  EmptyState,
  ErrorPanel,
  Field,
  FilterBar,
  LoadingPanel,
  Panel,
  Select,
  StatusBadge,
  Table,
  Td,
  TextInput,
  Th,
  toneForStatus,
} from '../../components/admin/AdminUi'
import { humanise } from '../../components/admin/AdminUi'

const EVENT_COLOURS: Record<string, string> = {
  task_deadline: '#1d4ed8',
  report_deadline: '#0ea5e9',
  target_period: '#7c3aed',
  achievement: '#16a34a',
}

/**
 * Stable references so the grouping memo below is not invalidated on every
 * render while the request is still in flight.
 */
const NO_EVENTS: CalendarEvent[] = []
const NO_SOURCES: CalendarSource[] = []

function isoDate(value: Date): string {
  return value.toISOString().slice(0, 10)
}

function startOfMonth(value: string): string {
  return `${value}-01`
}

function endOfMonth(value: string): string {
  const [year, month] = value.split('-').map(Number)
  return isoDate(new Date(year, month, 0))
}

/**
 * Admin section 11 - Institutional calendar.
 *
 * The schema has no meetings, institutional-event or holiday tables, so the
 * backend reports those categories as unsupported. The page surfaces that
 * explicitly instead of rendering an empty list that would look like
 * "nothing scheduled".
 */
export default function AdminCalendarPage() {
  const now = new Date()
  const [month, setMonth] = useState(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`)
  const [departmentId, setDepartmentId] = useState('')
  const [employeeId, setEmployeeId] = useState('')
  const [typeFilter, setTypeFilter] = useState('')

  const options = useAdminResource(() => getAdminMonitoringOptions())
  const resource = useAdminResource(
    () =>
      getAdminCalendar({
        from: startOfMonth(month),
        to: endOfMonth(month),
        department_id: departmentId,
        employee_id: employeeId,
      }),
    [month, departmentId, employeeId],
  )

  const data = resource.data
  const events = data?.events ?? NO_EVENTS
  const sources = data?.sources ?? NO_SOURCES

  const grouped = useMemo(() => {
    const map = new Map<string, typeof events>()
    for (const event of events) {
      const list = map.get(event.date) ?? []
      list.push(event)
      map.set(event.date, list)
    }
    return [...map.entries()].sort(([a], [b]) => a.localeCompare(b))
  }, [events])

  const visible = typeFilter ? events.filter((event) => event.type === typeFilter) : events

  const supportedTypes = sources.filter((source) => source.supported)
  const unsupportedTypes = sources.filter((source) => !source.supported)

  return (
    <AdminPage
      title="Institutional Calendar"
      subtitle="Deadlines, KPI period ends and achievement records in one timeline"
      actions={
        <div className="flex gap-2">
          <Button variant="secondary" onClick={() => setMonth(shiftMonth(month, -1))}>
            Previous
          </Button>
          <Button variant="secondary" onClick={() => setMonth(shiftMonth(month, 1))}>
            Next
          </Button>
        </div>
      }
    >
      <FilterBar>
        <Field label="Month">
          <TextInput type="month" value={month} onChange={setMonth} />
        </Field>
        <Field label="Department">
          <Select
            value={departmentId}
            onChange={setDepartmentId}
            options={(options.data?.departments ?? []).map((d) => ({ value: d.id, label: d.name }))}
            placeholder="All departments"
          />
        </Field>
        <Field label="Employee">
          <Select
            value={employeeId}
            onChange={setEmployeeId}
            options={(options.data?.employees ?? []).map((e) => ({ value: e.id, label: e.name }))}
            placeholder="All employees"
          />
        </Field>
        <Field label="Category">
          <Select
            value={typeFilter}
            onChange={setTypeFilter}
            options={supportedTypes.map((s) => ({ value: s.type, label: s.label }))}
            placeholder="All categories"
          />
        </Field>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading calendar" rows={2} />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load calendar.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <div className="flex flex-wrap gap-2">
            {supportedTypes.map((source) => (
              <span key={source.type} className="flex items-center gap-1.5 text-[11px] text-slate-600">
                <span
                  className="w-2.5 h-2.5 rounded-sm"
                  style={{ background: EVENT_COLOURS[source.type] ?? '#64748b' }}
                />
                {source.label}
              </span>
            ))}
          </div>

          <Panel
            title={`${month} · ${visible.length} event${visible.length === 1 ? '' : 's'}`}
            description={
              typeFilter
                ? `Filtered to ${humanise(typeFilter)}`
                : `Showing ${data.range.from} to ${data.range.to}`
            }
          >
            {visible.length === 0 ? (
              <EmptyState
                title="No events in this month"
                message="Choose a different month, or clear the category filter."
              />
            ) : (
              <div className="space-y-5">
                {grouped.map(([date, dayEvents]) => (
                  <div key={date}>
                    <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                      {new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric',
                      })}
                    </h3>
                    <ul className="space-y-2">
                      {dayEvents
                        .filter((event) => !typeFilter || event.type === typeFilter)
                        .map((event) => (
                          <li
                            key={event.id}
                            className="flex items-start gap-3 border-l-4 pl-3 py-2 bg-slate-50 rounded-r-xl"
                            style={{ borderColor: EVENT_COLOURS[event.type] ?? '#64748b' }}
                          >
                            <div className="min-w-0 flex-1">
                              <div className="flex flex-wrap items-center gap-2">
                                <p className="font-semibold text-slate-800 text-sm">{event.title}</p>
                                <Badge tone={toneForStatus(event.status)}>{humanise(event.status)}</Badge>
                              </div>
                              <p className="text-xs text-slate-500 mt-0.5">{event.description}</p>
                              <p className="text-[11px] text-slate-400 mt-0.5">
                                {event.type_label}
                                {event.employee ? ` · ${event.employee}` : ''}
                                {event.department ? ` · ${event.department}` : ''}
                              </p>
                            </div>
                          </li>
                        ))}
                    </ul>
                  </div>
                ))}
              </div>
            )}
          </Panel>

          {unsupportedTypes.length > 0 ? (
            <Panel
              title="Unavailable calendar categories"
              description="Reported honestly rather than shown as an empty list"
            >
              <Table>
                <thead>
                  <tr>
                    <Th>Category</Th>
                    <Th>Reason</Th>
                  </tr>
                </thead>
                <tbody>
                  {unsupportedTypes.map((source) => (
                    <tr key={source.type} className="border-b border-slate-100 last:border-0">
                      <Td className="font-semibold text-slate-800">
                        {source.label} <StatusBadge status="unavailable" />
                      </Td>
                      <Td className="text-slate-500 text-xs">{source.note}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            </Panel>
          ) : null}
        </div>
      )}
    </AdminPage>
  )
}

function shiftMonth(month: string, delta: number): string {
  const [year, monthIndex] = month.split('-').map(Number)
  const shifted = new Date(year, monthIndex - 1 + delta, 1)
  return `${shifted.getFullYear()}-${String(shifted.getMonth() + 1).padStart(2, '0')}`
}
