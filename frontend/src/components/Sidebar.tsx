import { NavLink } from 'react-router-dom'
import { NAVIGATION_ITEMS } from '../constants/navigation'
import { useAuth } from '../auth/useAuth'

interface SidebarProps {
  open: boolean
  onClose: () => void
}

export function Sidebar({ open, onClose }: SidebarProps) {
  const { user } = useAuth()
  const permissions = user?.permissions ?? []

  const can = (permission: string) => permissions.includes(permission)
  const item = (key: string) => NAVIGATION_ITEMS.find((entry) => entry.key === key)

  const visible = (entry: { permission: string; roles?: string[] }) =>
    can(entry.permission) && (!entry.roles || entry.roles.includes(user?.role ?? ''))

  const isAdmin = user?.role === 'admin'
  const isDeptHead = user?.role === 'department_head'

  const sections: Array<{ label: string; keys: string[] }> = isAdmin ? [
    { label: 'Executive Overview', keys: ['admin-dashboard', 'admin-exec-report'] },
    { label: 'Institution Oversight', keys: ['admin-departments', 'employees', 'tasks'] },
    { label: 'Reports & Quality', keys: ['admin-reports-center', 'reports', 'analytics', 'achievements'] },
    { label: 'Advanced Reporting', keys: ['reporting-hub', 'reporting-employee', 'reporting-department', 'reporting-monthly', 'reporting-achievements', 'reporting-late', 'reporting-pending', 'reporting-targets', 'reporting-data-quality', 'reporting-management-summary', 'reporting-analytics', 'reporting-exports'] },
    { label: 'Communication & Alerts', keys: ['messages', 'admin-announcements', 'admin-notif-monitor', 'notifications', 'notif-preferences'] },
    { label: 'AI & Operations', keys: ['admin-ai-monitor', 'admin-escalations', 'admin-exports', 'activity-logs'] },
    { label: 'System', keys: ['admin-system-health', 'admin-maintenance', 'admin-feedback', 'feedback', 'settings'] },
  ] : isDeptHead ? [
    { label: 'Department Overview', keys: ['dept-dashboard', 'dept-mgmt-summary'] },
    { label: 'Faculty & Deliverables', keys: ['dept-employees', 'dept-tasks', 'dept-reports'] },
    { label: 'Planning & Performance', keys: ['dept-targets', 'dept-performance', 'dept-achievements'] },
    { label: 'Communication', keys: ['messages', 'notifications', 'notif-preferences', 'feedback'] },
    { label: 'Administration & Reports', keys: ['dept-reports-center', 'dept-escalations'] },
    { label: 'Advanced Reporting', keys: ['reporting-hub', 'reporting-employee', 'reporting-department', 'reporting-monthly', 'reporting-achievements', 'reporting-late', 'reporting-pending', 'reporting-targets', 'reporting-data-quality', 'reporting-analytics', 'reporting-exports'] },
  ] : [
    { label: 'Monitoring', keys: ['dashboard'] },
    { label: 'My Work', keys: ['employee-dashboard', 'employee-tasks', 'employee-reports', 'employee-progress', 'employee-performance', 'employee-targets', 'employee-achievements', 'employee-calendar'] },
    { label: 'Communication', keys: ['messages', 'notifications', 'notif-preferences', 'feedback'] },
    { label: 'Organization', keys: ['employees', 'departments'] },
    { label: 'Planning & Execution', keys: ['planning', 'targets', 'tasks'] },
    { label: 'Review & Insights', keys: ['reports', 'analytics', 'achievements'] },
    { label: 'Reporting', keys: ['reporting-hub', 'reporting-employee', 'reporting-exports', 'reporting-analytics', 'reporting-department', 'reporting-monthly'] },
    { label: 'Leadership', keys: ['management-dashboard', 'management-dashboard-hr'] },
    { label: 'Account', keys: ['settings'] },
  ]

  return (
    <>
      <div
        className={`sidebar-backdrop${open ? ' sidebar-backdrop--open' : ''}`}
        onClick={onClose}
        aria-hidden="true"
      />
      <aside className={`sidebar${open ? ' sidebar--open' : ''}`}>
        <div className="sidebar__brand">
          <span className="sidebar__brand-mark" aria-hidden="true">
            JPC
          </span>
          <span className="sidebar__brand-name">JPC Portal</span>
        </div>

        <nav className="sidebar__nav" aria-label="Main navigation">
          {sections.map((section) => {
            const items = section.keys
              .map(item)
              .filter((entry): entry is NonNullable<typeof entry> => Boolean(entry))
              .filter(visible)

            if (items.length === 0) {
              return null
            }

            return (
              <div key={section.label}>
                <p className="sidebar__section-label">{section.label}</p>
                {items.map((entry) => (
                  <NavLink
                    key={entry.key}
                    to={entry.path}
                    className={({ isActive }) =>
                      `sidebar__link${isActive ? ' sidebar__link--active' : ''}`
                    }
                    onClick={onClose}
                  >
                    {entry.label}
                  </NavLink>
                ))}
              </div>
            )
          })}
        </nav>

        <div className="sidebar__footer">John Paul College</div>
      </aside>
    </>
  )
}