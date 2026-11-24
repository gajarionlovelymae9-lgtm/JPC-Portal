import type { EmployeeDashboardEmployee } from '../../services/employeePortalService'
import { formatLongDate } from './primitives'

function greeting(date: Date): string {
  const hour = date.getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 18) return 'Good afternoon'
  return 'Good evening'
}

function firstName(name: string): string {
  return name.trim().split(/\s+/)[0] ?? name
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

/**
 * Dashboard header. The name, department and position all come from the
 * authenticated employee record on the server — nothing here is hard-coded.
 */
export default function EmployeeWelcome({
  employee,
  today,
  progressPercent,
}: {
  employee: EmployeeDashboardEmployee
  today: Date
  progressPercent: number | null
}) {
  const role = [employee.position, employee.department].filter(Boolean).join(' • ')

  return (
    <div className="bg-[#EEF2FF] border border-[#C7D2FE] rounded-2xl px-5 py-5 sm:px-6 sm:py-6 shadow-sm">
      <div className="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-5">
        {employee.profile_photo_url ? (
          <img
            src={employee.profile_photo_url}
            alt={employee.name}
            className="w-14 h-14 sm:w-16 sm:h-16 shrink-0 rounded-full object-cover"
          />
        ) : (
          <div
            className="w-14 h-14 sm:w-16 sm:h-16 shrink-0 rounded-full bg-[#4F46E5] text-white flex items-center justify-center text-xl sm:text-2xl font-bold"
            aria-hidden="true"
          >
            {initials(employee.name)}
          </div>
        )}

        <div className="min-w-0 flex-1">
          <h1 className="text-xl sm:text-2xl font-extrabold text-[#312E81]">
            {greeting(today)}, {firstName(employee.name)}
          </h1>
          <p className="text-sm text-[#433F70] mt-1">
            Here&apos;s your current work progress and upcoming responsibilities.
          </p>
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-xs text-[#433F70]/70">
            {role ? <span>{role}</span> : null}
            {employee.employee_no ? <span>• {employee.employee_no}</span> : null}
            <span>• {formatLongDate(today)}</span>
          </div>
        </div>

        <div className="shrink-0 sm:text-right">
          <p className="text-xs text-[#433F70]/70 uppercase tracking-wide">Overall Progress</p>
          <p className="text-2xl font-bold text-[#312E81] tabular-nums">
            {progressPercent === null ? '—' : `${Math.round(progressPercent)}%`}
          </p>
        </div>
      </div>
    </div>
  )
}
