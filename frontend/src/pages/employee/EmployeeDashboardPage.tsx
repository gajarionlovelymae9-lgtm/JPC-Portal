import { useMemo } from 'react'
import { useAuth } from '../../auth/useAuth'
import AchievementOverview from '../../components/employee/AchievementOverview'
import ActionRequired from '../../components/employee/ActionRequired'
import CalendarPreview from '../../components/employee/CalendarPreview'
import EmployeeSummaryCards from '../../components/employee/EmployeeSummaryCards'
import EmployeeWelcome from '../../components/employee/EmployeeWelcome'
import MonthlyProgress from '../../components/employee/MonthlyProgress'
import NotificationPreview from '../../components/employee/NotificationPreview'
import OverdueItems from '../../components/employee/OverdueItems'
import PerformanceOverview from '../../components/employee/PerformanceOverview'
import QuickActions from '../../components/employee/QuickActions'
import ReportOverview from '../../components/employee/ReportOverview'
import TargetOverview from '../../components/employee/TargetOverview'
import TaskOverview from '../../components/employee/TaskOverview'
import UpcomingDeadlines from '../../components/employee/UpcomingDeadlines'
import { SkeletonBlock, SkeletonCards } from '../../components/employee/primitives'
import { useEmployeeDashboard } from '../../hooks/useEmployeeDashboard'
import { DashboardAiAssistant } from '../../components/ai/DashboardAiAssistant'

/** Skeleton mirrors the real layout so the page does not jump when data lands. */
function DashboardSkeleton() {
  return (
    <div className="space-y-4" role="status" aria-live="polite" aria-busy="true">
      <span className="sr-only">Loading your dashboard…</span>

      <SkeletonBlock className="h-32" />
      <SkeletonCards count={6} />

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div className="xl:col-span-2 space-y-4">
          <SkeletonBlock className="h-64" />
          <SkeletonBlock className="h-64" />
        </div>
        <div className="space-y-4">
          <SkeletonBlock className="h-64" />
          <SkeletonBlock className="h-52" />
        </div>
      </div>

      <SkeletonBlock className="h-48" />
    </div>
  )
}

export default function EmployeeDashboardPage() {
  const { user } = useAuth()
  const { state, retry } = useEmployeeDashboard()
  const today = useMemo(() => new Date(), [])

  // No linked employee record means the backend cannot resolve a personal
  // workspace. Say so plainly instead of rendering an empty dashboard.
  if (!user?.employee_id) {
    return (
      <>
        <div className="bg-white border border-gray-200 rounded-xl px-5 py-8 text-center">
          <p className="text-sm font-semibold text-gray-900">
            No employee profile is linked to this account
          </p>
          <p className="text-xs text-gray-500 mt-1">
            Ask an administrator to connect your account to an employee record.
          </p>
        </div>
        <DashboardAiAssistant scope="employee" />
      </>
    )
  }

  if (state.status === 'loading' && !state.data) {
    return (
      <>
        <DashboardSkeleton />
        <DashboardAiAssistant scope="employee" />
      </>
    )
  }

  if (state.status === 'error' && !state.data) {
    return (
      <>
        <div
          role="alert"
          className="bg-white border border-red-200 rounded-xl px-5 py-8 text-center"
        >
          <p className="text-sm font-semibold text-gray-900">
            Unable to load your dashboard
          </p>
          <p className="text-xs text-gray-600 mt-1">{state.error}</p>
          <button
            type="button"
            onClick={retry}
            className="mt-4 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
          >
            Retry
          </button>
        </div>
        <DashboardAiAssistant scope="employee" />
      </>
    )
  }

  if (!state.data) {
    return <DashboardAiAssistant scope="employee" />
  }

  const data = state.data

  return (
    <div className="space-y-4" aria-busy={state.status === 'loading'}>
      <EmployeeWelcome
        employee={data.employee}
        today={today}
        progressPercent={data.progress.percent}
      />

      <EmployeeSummaryCards kpis={data.kpis} />

      <ActionRequired
        items={data.action_required}
        caughtUp={data.caught_up}
      />

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div className="xl:col-span-2 space-y-4">
          <TaskOverview tasks={data.tasks} />
          <ReportOverview reports={data.reports} />
        </div>
        <div className="space-y-4">
          <UpcomingDeadlines deadlines={data.deadlines} />
          <OverdueItems tasks={data.overdue} reports={data.overdue_reports} />
        </div>
      </div>

      <MonthlyProgress progress={data.monthly_progress} />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <TargetOverview annual={data.targets.annual} items={data.targets.items} />
        <PerformanceOverview performance={data.performance} />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <AchievementOverview badges={data.achievements.badges} />
        <CalendarPreview events={data.schedule} />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <NotificationPreview
          notifications={data.recent_notifications}
          unreadCount={data.unread_notifications}
        />
        <QuickActions />
      </div>
    </div>
  )
}
