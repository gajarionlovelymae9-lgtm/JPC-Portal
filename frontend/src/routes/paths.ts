export const PATHS = {
  login: '/login',
  dashboard: '/dashboard',
  employees: '/employees',
  departments: '/departments',
  planning: '/planning',
  targets: '/targets',
  tasks: '/tasks',
  taskDetail: '/tasks/:taskId',
  reports: '/reports',
  analytics: '/analytics',
  achievements: '/achievements',
  notifications: '/notifications',
  activityLogs: '/activity-logs',
  settings: '/settings',

  // Employee Portal (Phase 3.3-V)
  employeeDashboard: '/employee/dashboard',
  employeeTasks: '/employee/tasks',
  employeeTaskDetail: '/employee/tasks/:taskId',
  employeeProgress: '/employee/progress',
  employeeReports: '/employee/reports',
  employeeReportCreate: '/employee/reports/create',
  employeeReportDetail: '/employee/reports/:reportId',
  employeeReportEdit: '/employee/reports/:reportId/edit',
  employeeReportRevise: '/employee/reports/:reportId/revise',
  employeePerformance: '/employee/performance',
  employeeTargets: '/employee/targets',
  employeeAchievements: '/employee/achievements',
  employeeCalendar: '/employee/calendar',
  employeeNotifications: '/employee/notifications',
  employeeProfile: '/employee/profile',
  employeeSecurity: '/employee/security',

  // Department Head Portal (Phase 3.3-X)
  departmentHeadDashboard: '/department-head/dashboard',
  departmentHeadEmployees: '/department-head/employees',
  departmentHeadEmployeeDetail: '/department-head/employees/:employeeId',
  departmentHeadTasks: '/department-head/tasks',
  departmentHeadTaskCreate: '/department-head/tasks/create',
  departmentHeadTaskDetail: '/department-head/tasks/:taskId',
  departmentHeadReports: '/department-head/reports',
  departmentHeadReportDetail: '/department-head/reports/:reportId',
  departmentHeadTargets: '/department-head/targets',
  departmentHeadPerformance: '/department-head/performance',
  departmentHeadAchievements: '/department-head/achievements',
  departmentHeadReportsCenter: '/department-head/reports-center',
  departmentHeadEscalations: '/department-head/escalations',
  departmentHeadManagementSummary: '/department-head/management-summary',

  // Administration Portal (Phase 3.3-Y)
  adminExecutiveDashboard: '/admin/dashboard',
  adminExecutiveReport: '/admin/executive-report',
  adminDepartments: '/admin/departments',
  adminDepartmentDetail: '/admin/departments/:departmentId',
  adminReports: '/admin/reports-center',
  adminReportDetail: '/admin/reports/:reportId',
  adminAiMonitoring: '/admin/ai',
  adminOcrMonitoring: '/admin/ai/ocr',
  adminEscalations: '/admin/escalations',
  adminExports: '/admin/exports',

  // Notification & Communication (Phase 3.3-Z)
  notificationPreferences: '/settings/notifications',
  messages: '/messages',
  adminAnnouncements: '/admin/announcements',
  adminNotificationMonitoring: '/admin/notifications',

  // Management dashboards
  adminDashboard: '/admin/dashboard',
  hrDashboard: '/hr/dashboard',
} as const

export function taskDetailPath(taskId: number | string): string {
  return `/tasks/${taskId}`
}

export function employeeTaskDetailPath(taskId: number | string): string {
  return `/employee/tasks/${taskId}`
}

export function employeeReportDetailPath(reportId: number | string): string {
  return `/employee/reports/${reportId}`
}

export function employeeReportEditPath(reportId: number | string): string {
  return `/employee/reports/${reportId}/edit`
}

export function employeeReportRevisePath(reportId: number | string): string {
  return `/employee/reports/${reportId}/revise`
}

export function departmentHeadEmployeeDetailPath(employeeId: number | string): string {
  return `/department-head/employees/${employeeId}`
}

export function departmentHeadTaskDetailPath(taskId: number | string): string {
  return `/department-head/tasks/${taskId}`
}

export function departmentHeadReportDetailPath(reportId: number | string): string {
  return `/department-head/reports/${reportId}`
}

export function adminDepartmentDetailPath(departmentId: number | string): string {
  return `/admin/departments/${departmentId}`
}

export function adminReportDetailPath(reportId: number | string): string {
  return `/admin/reports/${reportId}`
}

/** Management dashboard route for the authenticated user's role. */
export function managementDashboardPath(role: string): string {
  if (role === 'hr') return PATHS.hrDashboard
  if (role === 'department_head') return PATHS.departmentHeadDashboard
  return PATHS.adminDashboard
}