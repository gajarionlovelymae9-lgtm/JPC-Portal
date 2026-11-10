import { apiRequest } from './api'

/**
 * HR institutional calendar.
 *
 * Events come from real records only (task/report deadlines, KPI period
 * ends, achievements and published announcements — announcements, events
 * and holidays are announcement rows with different categories).
 */

export interface HrCalendarAnnouncement {
  id: number
  title: string
  message: string
  category: string | null
  priority: string | null
  audience_type: string | null
  audience_id: string | null
  start_date: string | null
  end_date: string | null
  published_at: string | null
  created_by: string | null
}

export interface HrCalendarEvent {
  id: string
  type: string
  type_label: string
  title: string
  employee: string | null
  department: string | null
  date: string
  time: string | null
  description: string
  status: string
  link: string | null
  /** Present on announcement-sourced events (announcements / events / holidays). */
  announcement?: HrCalendarAnnouncement
}

export interface HrCalendarSource {
  type: string
  label: string
  supported: boolean
  source?: string | null
  note?: string | null
}

export interface HrCalendarData {
  events: HrCalendarEvent[]
  sources: HrCalendarSource[]
  range: { from: string; to: string }
}

export interface HrCalendarFilters {
  from: string
  to: string
  department_id?: number | string
}

function toQueryString(params: Record<string, unknown>): string {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      search.append(key, String(value))
    }
  }
  const query = search.toString()
  return query ? `?${query}` : ''
}

export function getHrCalendar(filters: HrCalendarFilters): Promise<{ data: HrCalendarData }> {
  return apiRequest<HrCalendarData>(`/hr/calendar${toQueryString({ ...filters })}`)
}
