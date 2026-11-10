import { Link } from 'react-router-dom'
import type { EmployeeScheduleEvent } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'
import { EmptyState, Panel, PanelHeader, StatusPill, formatDate } from './primitives'

/** Upcoming dated events, using the link path the backend already returns. */
export default function CalendarPreview({ events }: { events: EmployeeScheduleEvent[] }) {
  return (
    <Panel>
      <PanelHeader
        title="My Calendar"
        description="Upcoming deadlines and submissions"
        action={
          <Link
            to={PATHS.employeeCalendar}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            Open Calendar →
          </Link>
        }
      />

      {events.length === 0 ? (
        <EmptyState message="Nothing scheduled ahead." icon="🗓️" />
      ) : (
        <ul className="divide-y divide-gray-100">
          {events.map(event => (
            <li key={`${event.type}-${event.id}`} className="px-5 py-3 flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="text-xs font-medium text-gray-400 uppercase">{event.type}</p>
                <Link
                  to={event.link}
                  className="block text-sm font-medium text-gray-900 hover:text-blue-600 truncate mt-0.5"
                >
                  {event.title}
                </Link>
                <p className="text-xs text-gray-500 mt-0.5">{formatDate(event.date)}</p>
              </div>
              <div className="shrink-0 flex flex-col items-end gap-1">
                <StatusPill status={event.status} />
                {event.is_overdue ? (
                  <span className="text-xs font-semibold text-red-600">Overdue</span>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}
