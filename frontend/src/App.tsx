import { Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthProvider'
import { ProtectedRoute } from './components/ProtectedRoute'
import AppLayout from './layouts/AppLayout'
import { PATHS } from './routes/paths'
import DashboardPage from './pages/DashboardPage'
import LoginPage from './pages/LoginPage'
import EmployeesPage from './pages/EmployeesPage'
import DepartmentsPage from './pages/DepartmentsPage'
import PlanningPage from './pages/PlanningPage'
import TargetsPage from './pages/TargetsPage'
import TasksPage from './pages/TasksPage'
import EmployeeDashboardPage from './pages/tasks/EmployeeDashboardPage'
import MyTasksPage from './pages/tasks/MyTasksPage'
import TaskDetailsPage from './pages/tasks/TaskDetailsPage'
import TaskProgressPage from './pages/tasks/TaskProgressPage'
import ReportsPage from './pages/ReportsPage'
import AnalyticsPage from './pages/AnalyticsPage'
import AchievementsPage from './pages/AchievementsPage'
import NotificationsPage from './pages/NotificationsPage'
import ActivityLogsPage from './pages/ActivityLogsPage'
import SettingsPage from './pages/SettingsPage'
import ManagementDashboardPage from './pages/dashboard/ManagementDashboardPage'
import NotFoundPage from './pages/NotFoundPage'

// Phase 3.3-V Employee Portal Pages
import EmployeeReportsPage from './pages/employee/EmployeeReportsPage'
import EmployeeCreateReportPage from './pages/employee/EmployeeCreateReportPage'
import EmployeeReportDetailPage from './pages/employee/EmployeeReportDetailPage'
import EmployeeEditReportPage from './pages/employee/EmployeeEditReportPage'
import EmployeeReviseReportPage from './pages/employee/EmployeeReviseReportPage'
import EmployeePerformancePage from './pages/employee/EmployeePerformancePage'
import EmployeeTargetsPage from './pages/employee/EmployeeTargetsPage'
import EmployeeAchievementsPage from './pages/employee/EmployeeAchievementsPage'
import EmployeeCalendarPage from './pages/employee/EmployeeCalendarPage'
import EmployeeNotificationsPage from './pages/employee/EmployeeNotificationsPage'
import EmployeeProfilePage from './pages/employee/EmployeeProfilePage'
import EmployeeSecurityPage from './pages/employee/EmployeeSecurityPage'

// Phase 3.3-X Department Head Portal Pages
import DepartmentDashboardPage from './pages/departmentHead/DepartmentDashboardPage'
import DepartmentEmployeesPage from './pages/departmentHead/DepartmentEmployeesPage'
import DepartmentEmployeeDetailPage from './pages/departmentHead/DepartmentEmployeeDetailPage'
import DepartmentTasksPage from './pages/departmentHead/DepartmentTasksPage'
import DepartmentTaskCreatePage from './pages/departmentHead/DepartmentTaskCreatePage'
import DepartmentTaskDetailPage from './pages/departmentHead/DepartmentTaskDetailPage'
import DepartmentReportsPage from './pages/departmentHead/DepartmentReportsPage'
import DepartmentReportDetailPage from './pages/departmentHead/DepartmentReportDetailPage'
import DepartmentTargetsPage from './pages/departmentHead/DepartmentTargetsPage'
import DepartmentPerformancePage from './pages/departmentHead/DepartmentPerformancePage'
import DepartmentAchievementsPage from './pages/departmentHead/DepartmentAchievementsPage'
import DepartmentReportsCenterPage from './pages/departmentHead/DepartmentReportsCenterPage'
import DepartmentEscalationsPage from './pages/departmentHead/DepartmentEscalationsPage'
import DepartmentManagementSummaryPage from './pages/departmentHead/DepartmentManagementSummaryPage'

// Phase 3.3-Y Administration Portal Pages
import AdminExecutiveDashboardPage from './pages/admin/AdminExecutiveDashboardPage'
import AdminExecutiveReportPage from './pages/admin/AdminExecutiveReportPage'
import AdminDepartmentsPage from './pages/admin/AdminDepartmentsPage'
import AdminDepartmentDetailPage from './pages/admin/AdminDepartmentDetailPage'
import AdminReportsPage from './pages/admin/AdminReportsPage'
import AdminReportDetailPage from './pages/admin/AdminReportDetailPage'
import AdminAiMonitoringPage from './pages/admin/AdminAiMonitoringPage'
import AdminOcrMonitoringPage from './pages/admin/AdminOcrMonitoringPage'
import AdminEscalationsPage from './pages/admin/AdminEscalationsPage'
import AdminExportsPage from './pages/admin/AdminExportsPage'

// Phase 13 — System Health, Maintenance & Feedback
import SystemHealthPage from './pages/admin/AdminSystemHealthPage'
import MaintenanceLogPage from './pages/admin/AdminMaintenancePage'
import FeedbackAdminPage from './pages/admin/AdminFeedbackPage'
import FeedbackPage from './pages/FeedbackPage'

// Phase 3.3-Z Notification & Communication Pages
import NotificationPreferencesPage from './pages/notifications/NotificationPreferencesPage'
import MessagesPage from './pages/messages/MessagesPage'
import AdminNotificationMonitoringPage from './pages/admin/AdminNotificationMonitoringPage'
import AdminAnnouncementsPage from './pages/admin/AdminAnnouncementsPage'

// Phase 4 AI / OCR / NLP Pages
import ReportAiAnalysisPage from './pages/ai/ReportAiAnalysisPage'
import ReportAiReviewPage from './pages/ai/ReportAiReviewPage'

// Phase 5 Report Workflow & Review Workspace
import { ReportReviewWorkspacePage } from './pages/reports/ReportReviewWorkspacePage'

// Phase 7 AI Assistant & Analytics
import AiChatPage from './pages/ai/AiChatPage'
import AiInsightsPage from './pages/ai/AiInsightsPage'

// Phase 8 Data Quality & Integrity Monitoring
import { DataQualityDashboardPage } from './pages/DataQuality/DataQualityDashboardPage'
import { DataQualityIssuesPage } from './pages/DataQuality/DataQualityIssuesPage'
import { DataQualityIssueDetailsPage } from './pages/DataQuality/DataQualityIssueDetailsPage'
import { DataQualityScansPage } from './pages/DataQuality/DataQualityScansPage'

// Phase 9 Advanced Reporting & Management Intelligence
import { ReportingDashboardPage } from './pages/Reporting/ReportingDashboardPage'
import { EmployeeReportPage } from './pages/Reporting/EmployeeReportPage'
import { DepartmentReportPage } from './pages/Reporting/DepartmentReportPage'
import { MonthlyPerformancePage } from './pages/Reporting/MonthlyPerformancePage'
import { AchievementReportPage } from './pages/Reporting/AchievementReportPage'
import { LateReportsPage } from './pages/Reporting/LateReportsPage'
import { PendingReportsPage } from './pages/Reporting/PendingReportsPage'
import { TargetReportPage } from './pages/Reporting/TargetReportPage'
import { DataQualityReportPage } from './pages/Reporting/DataQualityReportPage'
import { ManagementSummaryPage } from './pages/Reporting/ManagementSummaryPage'
import { AnalyticsHubPage } from './pages/Reporting/AnalyticsHubPage'
import { ReportExportsPage } from './pages/Reporting/ReportExportsPage'

export default function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route path={PATHS.login} element={<LoginPage />} />

        <Route
          element={
            <ProtectedRoute>
              <AppLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Navigate to={PATHS.dashboard} replace />} />
          <Route
            path={PATHS.dashboard}
            element={
              <ProtectedRoute permission="dashboard.view">
                <DashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employees}
            element={
              <ProtectedRoute permission="employees.view">
                <EmployeesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departments}
            element={
              <ProtectedRoute permission="departments.view">
                <DepartmentsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.planning}
            element={
              <ProtectedRoute permission="planning.view">
                <PlanningPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.targets}
            element={
              <ProtectedRoute permission="targets.view">
                <TargetsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.tasks}
            element={
              <ProtectedRoute permission="tasks.view">
                <TasksPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.taskDetail}
            element={
              <ProtectedRoute permission="tasks.view">
                <TaskDetailsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeDashboard}
            element={
              <ProtectedRoute permission="tasks.view">
                <EmployeeDashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeTasks}
            element={
              <ProtectedRoute permission="tasks.view">
                <MyTasksPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeTaskDetail}
            element={
              <ProtectedRoute permission="tasks.view">
                <TaskDetailsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeProgress}
            element={
              <ProtectedRoute permission="tasks.view">
                <TaskProgressPage />
              </ProtectedRoute>
            }
          />

          {/* Phase 3.3-V Employee Portal Routes */}
          <Route
            path={PATHS.employeeReports}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeReportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeReportCreate}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeCreateReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeReportDetail}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeReportDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeReportEdit}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeEditReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeReportRevise}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeReviseReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeePerformance}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeePerformancePage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeTargets}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeTargetsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeAchievements}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeAchievementsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeCalendar}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeCalendarPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeNotifications}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeNotificationsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeProfile}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeProfilePage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.employeeSecurity}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeSecurityPage />
              </ProtectedRoute>
            }
          />

          {/* Phase 3.3-X Department Head Portal Routes */}
          <Route
            path={PATHS.departmentHeadDashboard}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentDashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadEmployees}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentEmployeesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadEmployeeDetail}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentEmployeeDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadTasks}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentTasksPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadTaskCreate}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentTaskCreatePage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadTaskDetail}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentTaskDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadReports}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentReportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadReportDetail}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentReportDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportReviewWorkspace}
            element={
              <ProtectedRoute role={['department_head', 'admin', 'hr']}>
                <ReportReviewWorkspacePage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadTargets}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentTargetsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadPerformance}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentPerformancePage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadAchievements}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentAchievementsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadReportsCenter}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentReportsCenterPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadEscalations}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentEscalationsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.departmentHeadManagementSummary}
            element={
              <ProtectedRoute role={['department_head', 'admin']}>
                <DepartmentManagementSummaryPage />
              </ProtectedRoute>
            }
          />

          {/* Phase 3.3-Y Administration Portal Routes */}
          <Route
            path={PATHS.adminExecutiveDashboard}
            element={
              <ProtectedRoute role="admin">
                <AdminExecutiveDashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminExecutiveReport}
            element={
              <ProtectedRoute role="admin">
                <AdminExecutiveReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminDepartments}
            element={
              <ProtectedRoute role="admin">
                <AdminDepartmentsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminDepartmentDetail}
            element={
              <ProtectedRoute role="admin">
                <AdminDepartmentDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminReports}
            element={
              <ProtectedRoute role="admin">
                <AdminReportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminReportDetail}
            element={
              <ProtectedRoute role="admin">
                <AdminReportDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminAiMonitoring}
            element={
              <ProtectedRoute role="admin">
                <AdminAiMonitoringPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminOcrMonitoring}
            element={
              <ProtectedRoute role="admin">
                <AdminOcrMonitoringPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminEscalations}
            element={
              <ProtectedRoute role="admin">
                <AdminEscalationsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminExports}
            element={
              <ProtectedRoute role="admin">
                <AdminExportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminSystemHealth}
            element={
              <ProtectedRoute role="admin">
                <SystemHealthPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminMaintenance}
            element={
              <ProtectedRoute role="admin">
                <MaintenanceLogPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminFeedback}
            element={
              <ProtectedRoute role="admin">
                <FeedbackAdminPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.feedback}
            element={
              <ProtectedRoute>
                <FeedbackPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminNotificationMonitoring}
            element={
              <ProtectedRoute role="admin">
                <AdminNotificationMonitoringPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.adminAnnouncements}
            element={
              <ProtectedRoute role="admin">
                <AdminAnnouncementsPage />
              </ProtectedRoute>
            }
          />

          <Route
            path={PATHS.messages}
            element={
              <ProtectedRoute permission="notifications.view">
                <MessagesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.notificationPreferences}
            element={
              <ProtectedRoute permission="notifications.view">
                <NotificationPreferencesPage />
              </ProtectedRoute>
            }
          />

          <Route
            path={PATHS.reportAnalysis}
            element={
              <ProtectedRoute permission="reports.view">
                <ReportAiAnalysisPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportAiReview}
            element={
              <ProtectedRoute permission="reports.view">
                <ReportAiReviewPage />
              </ProtectedRoute>
            }
          />

          <Route
            path={PATHS.reports}
            element={
              <ProtectedRoute permission="reports.view">
                <ReportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.analytics}
            element={
              <ProtectedRoute permission="analytics.view">
                <AnalyticsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.achievements}
            element={
              <ProtectedRoute permission="achievements.view">
                <AchievementsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.notifications}
            element={
              <ProtectedRoute permission="notifications.view">
                <NotificationsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.activityLogs}
            element={
              <ProtectedRoute permission="activity_logs.view">
                <ActivityLogsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.settings}
            element={
              <ProtectedRoute permission="settings.view">
                <SettingsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.hrDashboard}
            element={
              <ProtectedRoute role="hr">
                <ManagementDashboardPage />
              </ProtectedRoute>
            }
          />

          <Route
            path={PATHS.aiChat}
            element={
              <ProtectedRoute>
                <AiChatPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.aiInsights}
            element={
              <ProtectedRoute role={['admin', 'hr', 'department_head']}>
                <AiInsightsPage />
              </ProtectedRoute>
            }
          />

          {/* Phase 8 Data Quality & Integrity Monitoring */}
          <Route
            path={PATHS.dataQualityDashboard}
            element={
              <ProtectedRoute role={['admin', 'hr', 'department_head']}>
                <DataQualityDashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.dataQualityIssues}
            element={
              <ProtectedRoute role={['admin', 'hr', 'department_head']}>
                <DataQualityIssuesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.dataQualityIssueDetail}
            element={
              <ProtectedRoute role={['admin', 'hr', 'department_head']}>
                <DataQualityIssueDetailsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.dataQualityScans}
            element={
              <ProtectedRoute role={['admin', 'hr', 'department_head']}>
                <DataQualityScansPage />
              </ProtectedRoute>
            }
          />

          {/* Phase 9 Advanced Reporting & Management Intelligence */}
          <Route
            path={PATHS.reportingHub}
            element={
              <ProtectedRoute permission="reports.view">
                <ReportingDashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingEmployee}
            element={
              <ProtectedRoute permission="reports.view">
                <EmployeeReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingExports}
            element={
              <ProtectedRoute permission="reports.view">
                <ReportExportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingDepartment}
            element={
              <ProtectedRoute permission="analytics.view">
                <DepartmentReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingMonthly}
            element={
              <ProtectedRoute permission="analytics.view">
                <MonthlyPerformancePage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingAchievements}
            element={
              <ProtectedRoute permission="analytics.view">
                <AchievementReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingLate}
            element={
              <ProtectedRoute permission="analytics.view">
                <LateReportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingPending}
            element={
              <ProtectedRoute permission="analytics.view">
                <PendingReportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingTargets}
            element={
              <ProtectedRoute permission="analytics.view">
                <TargetReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingDataQuality}
            element={
              <ProtectedRoute permission="analytics.view">
                <DataQualityReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingManagementSummary}
            element={
              <ProtectedRoute permission="analytics.view">
                <ManagementSummaryPage />
              </ProtectedRoute>
            }
          />
          <Route
            path={PATHS.reportingAnalytics}
            element={
              <ProtectedRoute permission="analytics.view">
                <AnalyticsHubPage />
              </ProtectedRoute>
            }
          />

          <Route path="*" element={<NotFoundPage />} />
        </Route>
      </Routes>
    </AuthProvider>
  )
}