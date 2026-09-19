import { useCallback, useState } from 'react'
import { useAuth } from '../../auth/useAuth'
import { DashboardFilters } from '../../components/dashboard/DashboardFilters'
import { KpiGrid } from '../../components/dashboard/KpiGrid'
import { MonthlyAchievementPanel } from '../../components/dashboard/MonthlyAchievementPanel'
import { MonthlyProgressCard } from '../../components/dashboard/MonthlyProgressCard'
import { TargetVsActualCard } from '../../components/dashboard/TargetVsActualCard'
import { EmployeeProgressTable } from '../../components/dashboard/EmployeeProgressTable'
import { EmployeeDetailPanel } from '../../components/dashboard/EmployeeDetailPanel'
import { DepartmentProgressTable } from '../../components/dashboard/DepartmentProgressTable'
import { OverdueTaskTable } from '../../components/dashboard/OverdueTaskTable'
import { ReviewQueueTable } from '../../components/dashboard/ReviewQueueTable'
import { useDashboardResource } from '../../hooks/useManagementDashboard'
import {
  getDashboardMonthlyAchievement,
  getDashboardMonthlyProgress,
  getDashboardOptions,
  getDashboardSummary,
  getDashboardTargetVsActual,
} from '../../services/managementDashboardService'
import type { DashboardScopeFilters, DashboardSummary } from '../../types/dashboard'
import { titleCase } from '../../utils/task'

const ROLE_LABELS: Record<string, string> = {
  admin: 'Management',
  hr: 'HR',
  department_head: 'Department Head',
}

const EMPTY_FILTERS: DashboardScopeFilters = {}

function ManagementDashboardContent({ roleName }: { roleName: string }) {
  const [filters, setFilters] = useState<DashboardScopeFilters>(EMPTY_FILTERS)
  const [refreshVersion, setRefreshVersion] = useState(0)
  const [selectedEmployeeId, setSelectedEmployeeId] = useState<number | null>(null)

  const options = useDashboardResource(
    getDashboardOptions,
    `options|${refreshVersion}`,
    'Unable to load dashboard options.',
  )

  const filterKey = JSON.stringify(filters)

  const summary = useDashboardResource<DashboardSummary>(
    useCallback(() => getDashboardSummary(filters), [filters]),
    `summary|${refreshVersion}|${filterKey}`,
    'Unable to load the dashboard summary.',
  )

  const monthlyProgress = useDashboardResource(
    useCallback(() => getDashboardMonthlyProgress(filters), [filters]),
    `monthly|${refreshVersion}|${filterKey}`,
    'Unable to load monthly progress.',
  )

  const achievement = useDashboardResource(
    useCallback(() => getDashboardMonthlyAchievement(filters), [filters]),
    `achievement|${refreshVersion}|${filterKey}`,
    'Unable to load the achievement ranking.',
  )

  const targetVsActual = useDashboardResource(
    useCallback(() => getDashboardTargetVsActual(filters), [filters]),
    `targets|${refreshVersion}|${filterKey}`,
    'Unable to load target vs actual.',
  )

  const handleFilters = useCallback((next: DashboardScopeFilters) => {
    setFilters(next)
    setSelectedEmployeeId(null)
  }, [])

  const handleRefresh = useCallback(() => {
    setRefreshVersion((version) => version + 1)
  }, [])

  const refreshing = summary.loading && summary.data !== null

  return (
    <div className="page">
      <div className="page__intro">
        <h2>{ROLE_LABELS[roleName] ?? titleCase(roleName)} Dashboard</h2>
        <p>
          Track task completion, target attainment, monthly achievement and the
          review pipeline across the organisation.
        </p>
      </div>

      <DashboardFilters
        value={filters}
        options={options.data}
        optionsLoading={options.loading}
        onChange={handleFilters}
        onRefresh={handleRefresh}
        refreshing={refreshing}
        lastUpdated={summary.updatedAt}
      />

      {summary.loading && !summary.data ? <p className="page-state">Loading dashboard…</p> : null}

      {summary.error && !summary.data ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{summary.error}</p>
          <button type="button" className="button button--secondary" onClick={summary.refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {summary.data ? (
        <>
          <section className="task-section">
            <h3>At a glance</h3>
            <KpiGrid summary={summary.data} />
          </section>
        </>
      ) : null}

      <div className="dashboard-grid">
        <TargetVsActualCard data={targetVsActual.data} />
        <section className="task-section" aria-label="Monthly achievement">
          <div className="dashboard-panel__head">
            <h3>Monthly achievement</h3>
            <span className="dashboard-panel__hint">Top performers</span>
          </div>
          <MonthlyAchievementPanel
            rows={achievement.data ?? []}
            loading={achievement.loading && !achievement.data}
            error={achievement.data ? null : achievement.error}
            onRetry={achievement.refresh}
          />
        </section>
      </div>

      <MonthlyProgressCard data={monthlyProgress.data} />

      <section className="task-section">
        <h3>Employee progress</h3>
        <EmployeeProgressTable
          key={filterKey}
          scope={filters}
          refreshVersion={refreshVersion}
          selectedEmployeeId={selectedEmployeeId}
          onSelect={setSelectedEmployeeId}
        />
      </section>

      <EmployeeDetailPanel
        employeeId={selectedEmployeeId}
        scope={filters}
        refreshVersion={refreshVersion}
        onClose={() => setSelectedEmployeeId(null)}
      />

      <section className="task-section">
        <h3>Department progress</h3>
        <DepartmentProgressTable key={filterKey} scope={filters} refreshVersion={refreshVersion} />
      </section>

      <section className="task-section">
        <h3>Overdue tasks</h3>
        <OverdueTaskTable key={filterKey} scope={filters} refreshVersion={refreshVersion} />
      </section>

      <section className="task-section">
        <h3>Review queue</h3>
        <ReviewQueueTable key={filterKey} scope={filters} refreshVersion={refreshVersion} />
      </section>
    </div>
  )
}

export default function ManagementDashboardPage() {
  const { user } = useAuth()

  if (!user) {
    return <p className="page-state">Sign in to view the management dashboard.</p>
  }

  return <ManagementDashboardContent roleName={user.role} />
}