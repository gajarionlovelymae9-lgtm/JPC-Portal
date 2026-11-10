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
  employeeAiInsights: '/employee/ai-insights',
  employeeAiAssistant: '/employee/ai-assistant',
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
  adminSystemHealth: '/admin/system-health',
  adminMaintenance: '/admin/maintenance',
  adminFeedback: '/admin/feedback',
  feedback: '/feedback',

  // Full Admin Dashboard sections (all API-backed, no hardcoded metrics)
  adminPerformanceOverview: '/admin/performance',
  adminKpiTargets: '/admin/kpi-targets',
  adminTargetAdjustments: '/admin/target-adjustments',
  adminEmployeeProgress: '/admin/employee-progress',
  adminTaskMonitoring: '/admin/task-monitoring',
  adminTaskCreate: '/admin/tasks/create',
  adminReportMonitoring: '/admin/report-monitoring',
  adminCalendar: '/admin/calendar',
  adminAchievements: '/admin/achievements',
  // Distinct from `adminNotificationMonitoring` (delivery monitor) below, which
  // already owns `/admin/notifications`.
  adminNotificationsPage: '/admin/notification-centre',
  adminAuditTrail: '/admin/audit-trail',
  adminUserManagement: '/admin/users',
  adminAiInsights: '/admin/ai-insights',
  adminPlatformStatus: '/admin/platform-status',

  // People Management (consolidated)
  adminPeople: '/admin/people',
  adminPeopleDepartments: '/admin/people/departments',
  adminPeopleRoles: '/admin/people/roles',

  // Notification & Communication (Phase 3.3-Z)
  notificationPreferences: '/settings/notifications',
  messages: '/messages',
  adminAnnouncements: '/admin/announcements',
  adminNotificationMonitoring: '/admin/notifications',

  // Phase 4 AI / OCR / NLP Engine
  reportAnalysis: '/reports/:reportId/analysis',
  reportAiReview: '/reports/:reportId/ai-review',

  // Phase 7 AI Assistant & Analytics
  aiChat: '/ai/chat',
  aiInsights: '/ai/insights',
  adminAiAssistant: '/admin/ai-assistant',

  // Phase 8 Data Quality & Integrity Monitoring
  dataQualityDashboard: '/data-quality',
  dataQualityIssues: '/data-quality/issues',
  dataQualityIssueDetail: '/data-quality/issues/:id',
  dataQualityScans: '/data-quality/scans',

  // Phase 9 Advanced Reporting & Management Intelligence
  reportingHub: '/reporting',
  reportingEmployee: '/reporting/employee',
  reportingDepartment: '/reporting/department',
  reportingMonthly: '/reporting/monthly',
  reportingAchievements: '/reporting/achievements',
  reportingLate: '/reporting/late',
  reportingPending: '/reporting/pending',
  reportingTargets: '/reporting/targets',
  reportingDataQuality: '/reporting/data-quality',
  reportingManagementSummary: '/reporting/management-summary',
  reportingAnalytics: '/reporting/analytics',
  reportingExports: '/reporting/exports',

  // Phase 5 Report Workflow & Review Workspace
  reportReviewWorkspace: '/reports/:reportId/review-workspace',

  // Management dashboards
  adminDashboard: '/admin/dashboard',
  hrDashboard: '/hr/dashboard',
  hrAchievements: '/hr/achievements',
  hrCalendar: '/hr/calendar',
} as const

export function reportAnalysisPath(reportId: number | string): string {
  return `/reports/${reportId}/analysis`
}

export function reportAiReviewPath(reportId: number | string): string {
  return `/reports/${reportId}/ai-review`
}

export function reportReviewWorkspacePath(reportId: number | string): string {
  return `/reports/${reportId}/review-workspace`
}

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

/**
 * Home route for an authenticated user's role.
 *
 * Employee accounts land on the Employee Dashboard, managers on their own
 * portals. The generic foundation page (/dashboard) is never the landing
 * destination for any role.
 */
export function homePathForRole(role: string): string {
  switch (role) {
    case 'employee':
      return PATHS.employeeDashboard
    case 'hr':
      return PATHS.hrDashboard
    case 'department_head':
      return PATHS.departmentHeadDashboard
    case 'admin':
      return PATHS.adminDashboard
    default:
      return PATHS.dashboard
  }
}


/** Legacy alias kept for callers that reason about "management" dashboards. */
export function managementDashboardPath(role: string): string {
  return homePathForRole(role)
}
