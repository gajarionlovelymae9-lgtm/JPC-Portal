import { Link } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { PATHS } from '../../routes/paths'
import { Panel, PanelHeader } from './primitives'

interface QuickAction {
  to: string
  label: string
  description: string
  icon: string
  permission: string
}

/**
 * Quick actions are filtered by the authenticated user's permissions so an
 * employee without `reports.view` never sees a link that would bounce to a
 * blocked route.
 */
const ACTIONS: QuickAction[] = [
  {
    to: PATHS.employeeTasks,
    label: 'My Tasks',
    description: 'View and update your assignments',
    icon: '📋',
    permission: 'tasks.view',
  },
  {
    to: PATHS.employeeReportCreate,
    label: 'Create Report',
    description: 'Submit a report for a task',
    icon: '📝',
    permission: 'reports.view',
  },
  {
    to: PATHS.employeeReports,
    label: 'My Reports',
    description: 'Track status and revisions',
    icon: '📄',
    permission: 'reports.view',
  },
  {
    to: PATHS.employeeCalendar,
    label: 'Calendar',
    description: 'See your deadline schedule',
    icon: '🗓️',
    permission: 'reports.view',
  },
  {
    to: PATHS.employeeAchievements,
    label: 'Achievements',
    description: 'Review badges you have earned',
    icon: '🏆',
    permission: 'reports.view',
  },
  {
    to: PATHS.employeeProfile,
    label: 'My Profile',
    description: 'Update your personal information',
    icon: '👤',
    permission: 'reports.view',
  },
]

export default function QuickActions() {
  const { user } = useAuth()
  const permissions = user?.permissions ?? []
  const actions = ACTIONS.filter(action => permissions.includes(action.permission))

  if (actions.length === 0) {
    return null
  }

  return (
    <Panel>
      <PanelHeader title="Quick Actions" />
      <div className="p-4 grid grid-cols-2 lg:grid-cols-3 gap-3">
        {actions.map(action => (
          <Link
            key={action.to}
            to={action.to}
            className="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50/40 transition-colors"
          >
            <span className="text-lg shrink-0" aria-hidden="true">
              {action.icon}
            </span>
            <span className="min-w-0">
              <span className="block text-sm font-semibold text-gray-900">{action.label}</span>
              <span className="block text-xs text-gray-500 truncate">{action.description}</span>
            </span>
          </Link>
        ))}
      </div>
    </Panel>
  )
}
