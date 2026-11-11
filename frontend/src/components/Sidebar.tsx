import { useState, type ReactNode } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { NAVIGATION_ITEMS, type NavigationItem } from '../constants/navigation'
import { useAuth } from '../auth/useAuth'
import { JpcAideLogo } from './ai/JpcAideLogo'

interface SidebarProps {
  open: boolean
  onClose: () => void
}

type GroupIconName =
  | 'grid'
  | 'person'
  | 'users'
  | 'check'
  | 'doc'
  | 'chart'
  | 'star'
  | 'chat'
  | 'calendar'
  | 'shield'
  | 'sliders'
  | 'download'
  | 'chat-dots'
  | 'bell'
  | 'target'
  | 'user-check'

interface SidebarGroup {
  label: string
  icon: GroupIconName
  keys: string[]
  children?: SidebarGroup[]
}

interface SidebarSection {
  label: string
  keys?: string[]
  groups?: SidebarGroup[]
}

const ICONS: Record<GroupIconName, ReactNode> = {
  grid: (
    <>
      <rect x="2.5" y="2.5" width="4.5" height="4.5" rx="1" />
      <rect x="9" y="2.5" width="4.5" height="4.5" rx="1" />
      <rect x="2.5" y="9" width="4.5" height="4.5" rx="1" />
      <rect x="9" y="9" width="4.5" height="4.5" rx="1" />
    </>
  ),
  person: (
    <>
      <circle cx="8" cy="5.2" r="2.6" />
      <path d="M3.2 13.4a4.8 4.8 0 0 1 9.6 0" />
    </>
  ),
  users: (
    <>
      <circle cx="6.2" cy="5.4" r="2.3" />
      <path d="M1.8 13.2a4.4 4.4 0 0 1 8.8 0" />
      <path d="M10.4 3.4a2.3 2.3 0 0 1 0 4" />
      <path d="M11.6 9.4a4.4 4.4 0 0 1 2.6 3.8" />
    </>
  ),
  check: (
    <>
      <rect x="2.5" y="2.5" width="11" height="11" rx="2" />
      <path d="M5.4 8.2 7.2 10l3.4-3.6" />
    </>
  ),
  doc: (
    <>
      <path d="M9.2 2H4.8A1.8 1.8 0 0 0 3 3.8v8.4A1.8 1.8 0 0 0 4.8 14h6.4a1.8 1.8 0 0 0 1.8-1.8V5.8Z" />
      <path d="M9 2v3.8h4" />
    </>
  ),
  chart: (
    <>
      <path d="M3 13.5V9.5" />
      <path d="M8 13.5V4" />
      <path d="M13 13.5v-6" />
    </>
  ),
  star: (
    <path d="M8 2.2l1.75 3.55 3.9.57-2.82 2.75.66 3.88L8 11.16l-3.49 1.83.66-3.88L2.35 6.32l3.9-.57Z" />
  ),
  chat: (
    <path d="M14 9.6a1.9 1.9 0 0 1-1.9 1.9H6.4L3 14V4.3A1.9 1.9 0 0 1 4.9 2.4h7.2A1.9 1.9 0 0 1 14 4.3Z" />
  ),
  calendar: (
    <>
      <rect x="2.5" y="3.5" width="11" height="10" rx="1.8" />
      <path d="M2.5 6.8h11" />
      <path d="M5.5 1.8v3" />
      <path d="M10.5 1.8v3" />
    </>
  ),
  shield: (
    <>
      <path d="M8 1.9 13 3.8v4.1c0 3.1-2.1 5.3-5 6.2-2.9-.9-5-3.1-5-6.2V3.8Z" />
      <path d="M5.9 8 7.4 9.5l2.7-2.9" />
    </>
  ),
  sliders: (
    <>
      <path d="M3 5.2h1.1" />
      <path d="M7.9 5.2H13" />
      <path d="M3 10.8h5.4" />
      <path d="M12.3 10.8H13" />
      <circle cx="6" cy="5.2" r="1.7" />
      <circle cx="10.4" cy="10.8" r="1.7" />
    </>
  ),
  download: (
    <>
      <path d="M8 2.5v7" />
      <path d="M5 6.8 8 9.8l3-3" />
      <path d="M3 13.3h10" />
    </>
  ),
  'chat-dots': (
    <>
      <path d="M14 9.6a1.9 1.9 0 0 1-1.9 1.9H6.4L3 14V4.3A1.9 1.9 0 0 1 4.9 2.4h7.2A1.9 1.9 0 0 1 14 4.3Z" />
      <circle cx="6" cy="7.4" r="0.75" fill="currentColor" stroke="none" />
      <circle cx="8.5" cy="7.4" r="0.75" fill="currentColor" stroke="none" />
      <circle cx="11" cy="7.4" r="0.75" fill="currentColor" stroke="none" />
    </>
  ),
  bell: (
    <>
      <path d="M9.8 13.2v.6a1.8 1.8 0 0 1-3.6 0v-.6" />
      <path d="M12.4 13.2H3.6a2.3 2.3 0 0 0 1.8-2.2V6.4a2.6 2.6 0 0 1 5.2 0v4.6a2.3 2.3 0 0 0 1.8 2.2Z" />
      <path d="M6.4 3.5a2.6 2.6 0 0 1 3.2 0" />
    </>
  ),
  target: (
    <>
      <circle cx="8" cy="8" r="5.4" />
      <circle cx="8" cy="8" r="2.2" />
      <circle cx="8" cy="8" r="0.4" fill="currentColor" stroke="none" />
    </>
  ),
  'user-check': (
    <>
      <circle cx="6.2" cy="5.4" r="2.4" />
      <path d="M2.4 13.4a4.3 4.3 0 0 1 6.2-3.8" />
      <path d="M9.6 11.4 11.2 13l2.8-2.9" />
    </>
  ),
}

function GroupIcon({ name }: { name: GroupIconName }) {
  return (
    <svg
      viewBox="0 0 16 16"
      width="16"
      height="16"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      {ICONS[name]}
    </svg>
  )
}

// The admin portal regrouped into three sections and collapsible menus so
// the sidebar stays short. Every entry reuses an existing NAVIGATION_ITEMS key
// (so permissions, roles, paths and header titles are unchanged) — the groups
// only reorder what was already rendered flat in the legacy sections. People
// Management consolidates the Employees, User Management and Audit Trail
// entries under one parent without touching their pages or APIs.
const ADMIN_SECTIONS: SidebarSection[] = [
  {
    label: 'MAIN MENU',
    groups: [
      {
        label: 'Dashboard',
        icon: 'grid',
        keys: ['admin-dashboard', 'admin-performance', 'admin-exec-report'],
      },
      {
        label: 'Tasks & Planning',
        icon: 'check',
        keys: ['planning', 'tasks', 'admin-kpi-targets'],
      },
      {
        label: 'Reports & Monitoring',
        icon: 'doc',
        keys: [
          'admin-task-monitoring',
          'admin-report-monitoring',
          'admin-reports-center',
          // Every report now lives inside the unified Reports & Analytics
          // page; only its hub entry stays in the sidebar even though the
          // dedicated routes remain available.
          'reporting-hub',
        ],
        children: [
          { label: 'Exports', icon: 'download', keys: ['reporting-exports', 'admin-exports'] },
        ],
      },
      {
        label: 'Analytics & AI Insights',
        icon: 'chart',
        keys: [
          'admin-employee-progress',
          'admin-departments',
          'analytics',
          'reporting-management-summary',
          'reporting-analytics',
          'admin-ai-assistant',
          'admin-ai-insights',
          'admin-ai-monitor',
        ],
      },
      {
        label: 'Achievements & KPI',
        icon: 'star',
        keys: ['admin-achievements', 'reporting-achievements'],
      },
    ],
  },
  {
    label: 'COMMUNICATION',
    groups: [
      {
        label: 'Messages & Notifications',
        icon: 'chat',
        keys: [
          'messages',
          'admin-notification-centre',
          'admin-notif-monitor',
          'notif-preferences',
        ],
      },
      {
        label: 'Calendar & Announcements',
        icon: 'calendar',
        keys: ['admin-calendar', 'admin-announcements'],
      },
    ],
  },
  {
    label: 'ADMINISTRATION',
    groups: [
      {
        // One People Management entry whose unified page owns the Employees /
        // Departments / Accounts tabs; Roles & Access and Audit Trail remain
        // dedicated surfaces. Legacy user-account and department routes
        // redirect into the unified page's matching tab.
        label: 'People Management',
        icon: 'users',
        keys: ['admin-people'],
        children: [
          { label: 'Roles & Access', icon: 'sliders', keys: ['admin-roles-access'] },
          { label: 'Audit Trail', icon: 'doc', keys: ['admin-audit-trail'] },
        ],
      },
      {
        label: 'System Settings',
        icon: 'sliders',
        keys: ['admin-platform-status', 'admin-system-health', 'admin-maintenance', 'settings'],
        children: [{ label: 'Feedback', icon: 'chat-dots', keys: ['admin-feedback', 'feedback'] }],
      },
    ],
  },
]

const DEPT_SECTIONS: SidebarSection[] = [
  { label: 'Department Overview', keys: ['dept-dashboard', 'dept-mgmt-summary'] },
  { label: 'Faculty & Deliverables', keys: ['dept-employees', 'dept-tasks', 'dept-reports'] },
  { label: 'Planning & Performance', keys: ['dept-targets', 'dept-performance', 'dept-achievements'] },
  { label: 'Communication', keys: ['messages', 'notifications', 'notif-preferences', 'feedback'] },
  { label: 'Administration & Reports', keys: ['dept-reports-center', 'dept-escalations'] },
  {
    label: 'Advanced Reporting',
    keys: [
      'reporting-hub',
      'reporting-achievements',
      'reporting-analytics',
      'reporting-exports',
    ],
  },
]

const HR_SECTIONS: SidebarSection[] = [
  // HR is institution-wide, not department-scoped, so the monitoring views
  // lead and the admin-only governance surfaces are absent by design. The
  // grouping/order is the approved HR information architecture — each key
  // maps to an existing NAVIGATION_ITEMS entry, so permissions, roles, paths
  // and header titles are untouched and nothing is duplicated.
  { label: 'HR Monitoring', keys: ['management-dashboard-hr', 'employees', 'tasks'] },
  { label: 'Planning & Targets', keys: ['planning', 'targets'] },
  // HR institutional Calendar: deadlines, KPI periods, achievements and
  // published announcements (events and holidays) in month/week/day views.
  { label: 'Calendar & Events', keys: ['hr-calendar'] },
  { label: 'Communication', keys: ['messages', 'notifications', 'notif-preferences', 'feedback'] },
  // One reporting surface: the consolidated Reports & Analytics page. Report
  // exports stay withdrawn from HR; Admin and Department Head keep the export
  // queue.
  { label: 'Reports & Analytics', keys: ['reporting-hub'] },
  // Achievements & Badges reuses the already-built /hr/achievements surface
  // (cross-department records, verify/reject with reason, reports, pending
  // queue) — the sidebar entry was the only missing piece.
  { label: 'Recognition', keys: ['hr-achievements'] },
]

/**
 * Per-item icons for the HR portal links. Keys are specific NAVIGATION_ITEMS
 * keys, so Admin / Department Head / Employee links are untouched; the
 * registry reuses the same icon set the admin collapsible groups draw from.
 */
const HR_ITEM_ICONS: Record<string, GroupIconName> = {
  'management-dashboard-hr': 'grid',
  employees: 'users',
  tasks: 'check',
  planning: 'doc',
  targets: 'target',
  'hr-calendar': 'calendar',
  messages: 'chat',
  notifications: 'bell',
  'notif-preferences': 'sliders',
  feedback: 'chat-dots',
  'reporting-hub': 'chart',
  'hr-achievements': 'user-check',
}

const EMPLOYEE_SECTIONS: SidebarSection[] = [
  // Employees only ever see their own portal: no org-wide, management or
  // admin surfaces are exposed here even if the API permissions allow them.
  { label: 'Dashboard', keys: ['employee-dashboard'] },
  {
    label: 'My Work',
    keys: [
      'employee-tasks',
      'employee-reports',
      'employee-progress',
      'employee-performance',
      'employee-ai-assistant',
      'employee-ai-insights',
      'employee-targets',
      'employee-achievements',
      'employee-calendar',
    ],
  },
  { label: 'Communication', keys: ['messages', 'employee-notifications', 'notif-preferences', 'feedback'] },
  { label: 'Account', keys: ['employee-profile', 'employee-security'] },
]

function collectGroups(sections: SidebarSection[]): SidebarGroup[] {
  return sections.flatMap((section) =>
    (section.groups ?? []).flatMap((group) => [group, ...(group.children ?? [])]),
  )
}

/** True when the group owns a menu entry for `pathname` (exact or nested). */
function groupHasPath(group: SidebarGroup, pathname: string): boolean {
  const keys = [...group.keys, ...(group.children ?? []).flatMap((child) => child.keys)]
  return keys.some((key) => {
    const entry = NAVIGATION_ITEMS.find((candidate) => candidate.key === key)
    return entry !== undefined && (pathname === entry.path || pathname.startsWith(`${entry.path}/`))
  })
}

export function Sidebar({ open, onClose }: SidebarProps) {
  const { user } = useAuth()
  const permissions = user?.permissions ?? []
  const location = useLocation()

  const can = (permission: string) => permissions.includes(permission)
  const item = (key: string) => NAVIGATION_ITEMS.find((entry) => entry.key === key)

  const visible = (entry: { permission: string; roles?: string[] }) =>
    can(entry.permission) && (!entry.roles || entry.roles.includes(user?.role ?? ''))

  const isAdmin = user?.role === 'admin'
  const isDeptHead = user?.role === 'department_head'
  const isHr = user?.role === 'hr'

  const sections: SidebarSection[] = isAdmin
    ? ADMIN_SECTIONS
    : isDeptHead
      ? DEPT_SECTIONS
      : isHr
        ? HR_SECTIONS
        : EMPLOYEE_SECTIONS

  const entriesOf = (keys: string[]): NavigationItem[] =>
    keys
      .map(item)
      .filter((entry): entry is NavigationItem => Boolean(entry))
      .filter(visible)

  // A group is open when the active route lives inside it, unless the user
  // explicitly toggled it — their choice wins and survives navigation. This
  // is derived during render, so navigating to a nested route opens its
  // parent group without an effect.
  const [overrides, setOverrides] = useState<Record<string, boolean>>({})

  const activeGroupLabels = new Set(
    collectGroups(ADMIN_SECTIONS)
      .filter((group) => groupHasPath(group, location.pathname))
      .map((group) => group.label),
  )

  const isGroupOpen = (label: string): boolean =>
    label in overrides ? overrides[label] : activeGroupLabels.has(label)

  const toggleGroup = (label: string) => {
    setOverrides((prev) => ({ ...prev, [label]: !isGroupOpen(label) }))
  }

  const renderLink = (entry: NavigationItem) => {
    const iconName = HR_ITEM_ICONS[entry.key]
    const isJpcAide = entry.key === 'employee-ai-assistant' || entry.key === 'ai-assistant' || entry.key === 'admin-ai-assistant'

    return (
      <NavLink
        key={entry.key}
        to={entry.path}
        title={entry.label}
        className={({ isActive }) =>
          `sidebar__link${iconName || isJpcAide ? ' sidebar__link--iconised' : ''}${isActive ? ' sidebar__link--active' : ''}`
        }
        onClick={onClose}
      >
        {iconName ? (
          <span className="sidebar__link-icon" aria-hidden="true">
            <GroupIcon name={iconName} />
          </span>
        ) : isJpcAide ? (
          <span className="sidebar__link-icon" aria-hidden="true">
            <JpcAideLogo size={16} />
          </span>
        ) : null}
        <span className="sidebar__link-label">{entry.label}</span>
      </NavLink>
    )
  }

  const renderGroup = (group: SidebarGroup, depth: number): ReactNode => {
    const items = entriesOf(group.keys)
    const childGroups = (group.children ?? [])
      .map((child) => renderGroup(child, depth + 1))
      .filter(Boolean)

    if (items.length === 0 && childGroups.length === 0) {
      return null
    }

    // A group that resolves to a single entry renders as a plain link instead
    // of a pointless one-item accordion (e.g. the Employees menu).
    if (items.length === 1 && childGroups.length === 0) {
      return renderLink(items[0])
    }

    const isExpanded = isGroupOpen(group.label)
    const groupId = `sidebar-group-${group.label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`

    return (
      <div className="sidebar__group" key={group.label}>
        <button
          type="button"
          className="sidebar__group-toggle"
          title={group.label}
          aria-expanded={isExpanded}
          aria-controls={groupId}
          onClick={() => toggleGroup(group.label)}
        >
          <span className="sidebar__group-icon" aria-hidden="true">
            <GroupIcon name={group.icon} />
          </span>
          <span className="sidebar__group-label">{group.label}</span>
          <svg
            className={`sidebar__group-chevron${isExpanded ? ' sidebar__group-chevron--open' : ''}`}
            viewBox="0 0 16 16"
            width="14"
            height="14"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
          >
            <path d="M4 6l4 4 4-4" />
          </svg>
        </button>
        <div
          className={`sidebar__group-children${depth > 0 ? ' sidebar__group-children--nested' : ''}`}
          id={groupId}
          hidden={!isExpanded}
        >
          {items.map(renderLink)}
          {childGroups}
        </div>
      </div>
    )
  }

  return (
    <>
      <div
        className={`sidebar-backdrop${open ? ' sidebar-backdrop--open' : ''}`}
        onClick={onClose}
        aria-hidden="true"
      />
      <aside className={`sidebar${isHr ? ' sidebar--hr' : ''}${open ? ' sidebar--open' : ''}`}>
        <div className="sidebar__brand">
          <div className="sidebar__brand-mark" aria-hidden="true">
            <img src="/logo.png" alt="JPC Crest" className="sidebar__brand-logo" />
          </div>
          <div className="sidebar__brand-text">
            <span className="sidebar__brand-name">JPC Portal</span>
            <span className="sidebar__brand-sub">John Paul College</span>
          </div>
        </div>

        <nav className="sidebar__nav" aria-label="Main navigation">
          {sections.map((section) => {
            if (section.groups) {
              const groups = section.groups.map((group) => renderGroup(group, 0)).filter(Boolean)

              if (groups.length === 0) {
                return null
              }

              return (
                <div key={section.label}>
                  <p className="sidebar__section-label">{section.label}</p>
                  {groups}
                </div>
              )
            }

            const items = entriesOf(section.keys ?? [])

            if (items.length === 0) {
              return null
            }

            return (
              <div key={section.label}>
                <p className="sidebar__section-label">{section.label}</p>
                {items.map(renderLink)}
              </div>
            )
          })}
        </nav>

        <div className="sidebar__footer">John Paul College</div>
      </aside>
    </>
  )
}
