export interface NavigationItem {
  path: string
  label: string
  key: string
  icon?: string
  /** Permission required to see this item in the sidebar. */
  permission: string
  /** When present, only these roles see this item in the sidebar. */
  roles?: string[]
}

export const NAVIGATION_ITEMS: NavigationItem[] = [
  { path: '/dashboard', label: 'Dashboard', key: 'dashboard', permission: 'dashboard.view' },
  { path: '/employee/dashboard', label: 'My Dashboard', key: 'employee-dashboard', permission: 'tasks.view' },
  { path: '/employee/tasks', label: 'My Tasks', key: 'employee-tasks', permission: 'tasks.view' },
  { path: '/employee/reports', label: 'My Reports', key: 'employee-reports', permission: 'reports.view', roles: ['employee'] },
  { path: '/employee/progress', label: 'My Progress', key: 'employee-progress', permission: 'tasks.view' },
  { path: '/employee/performance', label: 'My Performance', key: 'employee-performance', permission: 'reports.view', roles: ['employee'] },
  { path: '/employee/targets', label: 'My Targets', key: 'employee-targets', permission: 'reports.view', roles: ['employee'] },
  { path: '/employee/achievements', label: 'My Achievements', key: 'employee-achievements', permission: 'reports.view', roles: ['employee'] },
  { path: '/employee/calendar', label: 'My Calendar', key: 'employee-calendar', permission: 'reports.view', roles: ['employee'] },

  // Department Head Portal (Phase 3.3-X)
  { path: '/department-head/dashboard', label: 'Dept Dashboard', key: 'dept-dashboard', permission: 'tasks.view', roles: ['department_head'] },
  { path: '/department-head/employees', label: 'Faculty Members', key: 'dept-employees', permission: 'tasks.view', roles: ['department_head'] },
  { path: '/department-head/tasks', label: 'Dept Tasks', key: 'dept-tasks', permission: 'tasks.view', roles: ['department_head'] },
  { path: '/department-head/reports', label: 'Report Reviews', key: 'dept-reports', permission: 'reports.view', roles: ['department_head'] },
  { path: '/department-head/targets', label: 'Dept Targets', key: 'dept-targets', permission: 'tasks.view', roles: ['department_head'] },
  { path: '/department-head/performance', label: 'Dept Performance', key: 'dept-performance', permission: 'reports.view', roles: ['department_head'] },
  { path: '/department-head/achievements', label: 'Dept Achievements', key: 'dept-achievements', permission: 'reports.view', roles: ['department_head'] },
  { path: '/department-head/reports-center', label: 'Dept Reports Center', key: 'dept-reports-center', permission: 'reports.view', roles: ['department_head'] },
  { path: '/department-head/escalations', label: 'Escalations', key: 'dept-escalations', permission: 'tasks.view', roles: ['department_head'] },
  { path: '/department-head/management-summary', label: 'Management View', key: 'dept-mgmt-summary', permission: 'reports.view', roles: ['department_head'] },

  // Administration Portal (Phase 3.3-Y)
  { path: '/admin/dashboard', label: 'Executive Dashboard', key: 'admin-dashboard', permission: 'dashboard.view', roles: ['admin'] },
  { path: '/admin/executive-report', label: 'Executive Report', key: 'admin-exec-report', permission: 'reports.view', roles: ['admin'] },
  { path: '/admin/departments', label: 'Dept Comparison', key: 'admin-departments', permission: 'departments.view', roles: ['admin'] },
  { path: '/admin/reports-center', label: 'Institution Reports', key: 'admin-reports-center', permission: 'reports.view', roles: ['admin'] },
  { path: '/admin/ai', label: 'AI & OCR Monitor', key: 'admin-ai-monitor', permission: 'dashboard.view', roles: ['admin'] },
  { path: '/admin/escalations', label: 'Escalations Center', key: 'admin-escalations', permission: 'tasks.view', roles: ['admin'] },
  { path: '/admin/exports', label: 'Executive Exports', key: 'admin-exports', permission: 'reports.view', roles: ['admin'] },

  // Phase 13 — System Health, Maintenance & Feedback (admin)
  { path: '/admin/system-health', label: 'System Health', key: 'admin-system-health', permission: 'dashboard.view', roles: ['admin'] },
  { path: '/admin/maintenance', label: 'Maintenance', key: 'admin-maintenance', permission: 'dashboard.view', roles: ['admin'] },
  { path: '/admin/feedback', label: 'Feedback Center', key: 'admin-feedback', permission: 'dashboard.view', roles: ['admin'] },
  { path: '/feedback', label: 'Feedback', key: 'feedback', permission: 'dashboard.view' },

  // Notification & Communication (Phase 3.3-Z)
  { path: '/messages', label: 'Messages', key: 'messages', permission: 'notifications.view' },
  { path: '/settings/notifications', label: 'Alert Preferences', key: 'notif-preferences', permission: 'notifications.view' },
  { path: '/admin/announcements', label: 'Announcements', key: 'admin-announcements', permission: 'settings.view', roles: ['admin'] },
  { path: '/admin/notifications', label: 'Delivery Monitor', key: 'admin-notif-monitor', permission: 'dashboard.view', roles: ['admin'] },

  { path: '/employees', label: 'Employees', key: 'employees', permission: 'employees.view', roles: ['admin', 'hr'] },
  { path: '/departments', label: 'Departments', key: 'departments', permission: 'departments.view', roles: ['admin', 'hr'] },
  { path: '/planning', label: 'Planning', key: 'planning', permission: 'planning.view', roles: ['admin', 'hr'] },
  { path: '/targets', label: 'Targets', key: 'targets', permission: 'targets.view', roles: ['admin', 'hr'] },
  { path: '/tasks', label: 'Tasks', key: 'tasks', permission: 'tasks.view', roles: ['admin', 'hr'] },
  { path: '/reports', label: 'Reports', key: 'reports', permission: 'reports.view', roles: ['admin', 'hr'] },
  { path: '/analytics', label: 'Analytics', key: 'analytics', permission: 'analytics.view', roles: ['admin', 'hr'] },
  { path: '/achievements', label: 'Achievements', key: 'achievements', permission: 'achievements.view', roles: ['admin', 'hr'] },
  { path: '/notifications', label: 'Notifications', key: 'notifications', permission: 'notifications.view' },
  { path: '/activity-logs', label: 'Activity Logs', key: 'activity-logs', permission: 'activity_logs.view', roles: ['admin'] },
  { path: '/settings', label: 'Settings', key: 'settings', permission: 'settings.view', roles: ['admin'] },
  { path: '/admin/dashboard', label: 'Admin Dashboard', key: 'management-dashboard', permission: 'analytics.view', roles: ['admin'] },
  { path: '/hr/dashboard', label: 'HR Dashboard', key: 'management-dashboard-hr', permission: 'analytics.view', roles: ['hr'] },
  { path: '/ai/chat', label: 'AI Assistant', key: 'ai-assistant', permission: 'dashboard.view' },
  { path: '/ai/insights', label: 'AI Insights', key: 'ai-insights', permission: 'analytics.view', roles: ['admin', 'hr', 'department_head'] },
  { path: '/data-quality', label: 'Data Quality', key: 'data-quality', permission: 'dashboard.view', roles: ['admin', 'hr', 'department_head'] },

  // Phase 9 Advanced Reporting & Management Intelligence
  { path: '/reporting', label: 'Reporting Hub', key: 'reporting-hub', permission: 'reports.view' },
  { path: '/reporting/employee', label: 'Employee Report', key: 'reporting-employee', permission: 'reports.view' },
  { path: '/reporting/department', label: 'Department Report', key: 'reporting-department', permission: 'analytics.view' },
  { path: '/reporting/monthly', label: 'Monthly Report', key: 'reporting-monthly', permission: 'analytics.view' },
  { path: '/reporting/achievements', label: 'Achievement Report', key: 'reporting-achievements', permission: 'analytics.view' },
  { path: '/reporting/late', label: 'Late Submissions', key: 'reporting-late', permission: 'analytics.view' },
  { path: '/reporting/pending', label: 'Pending Reports', key: 'reporting-pending', permission: 'analytics.view' },
  { path: '/reporting/targets', label: 'Target Report', key: 'reporting-targets', permission: 'analytics.view' },
  { path: '/reporting/data-quality', label: 'Data Quality Report', key: 'reporting-data-quality', permission: 'analytics.view' },
  { path: '/reporting/management-summary', label: 'Management Summary', key: 'reporting-management-summary', permission: 'analytics.view', roles: ['admin', 'hr'] },
  { path: '/reporting/analytics', label: 'Analytics Hub', key: 'reporting-analytics', permission: 'analytics.view' },
  { path: '/reporting/exports', label: 'Report Exports', key: 'reporting-exports', permission: 'reports.view' },
]

export const SETTINGS_ITEM: NavigationItem = {
  path: '/settings',
  label: 'Settings',
  key: 'settings',
  permission: 'settings.view',
}