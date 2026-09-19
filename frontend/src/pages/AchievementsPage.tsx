import { useCallback, useState } from 'react'
import { useAuth } from '../auth/useAuth'
import { ReportFilters } from '../components/reports/ReportFilters'
import { ExportButtons } from '../components/reports/ExportButtons'
import { AchievementLeaderboard } from '../components/reports/AchievementLeaderboard'
import { AchievementSeriesPanel } from '../components/reports/AchievementSeriesPanel'
import { useReportExport, useReportResource } from '../hooks/useReports'
import { getMyPerformance, getReportOptions } from '../services/reportService'
import type { ReportFilters as ReportFilterParams } from '../types/report'

function useAchievementsFilters() {
  const [filters, setFilters] = useState<ReportFilterParams>({})
  const [refreshVersion, setRefreshVersion] = useState(0)

  const options = useReportResource(
    useCallback(() => getReportOptions(), []),
    `achievements-options|${refreshVersion}`,
    'Unable to load achievement options.',
  )

  return {
    filters,
    setFilters,
    refreshVersion,
    bumpRefresh: () => setRefreshVersion((version) => version + 1),
    options,
  }
}

function ManagerAchievementsView() {
  const { filters, setFilters, refreshVersion, bumpRefresh, options } = useAchievementsFilters()
  const [selectedEmployeeId, setSelectedEmployeeId] = useState<number | null>(null)
  const filterKey = JSON.stringify(filters)

  const { exporting, exportError, handleExport } = useReportExport()

  const handleFilters = (next: ReportFilterParams) => {
    setFilters(next)
    setSelectedEmployeeId(null)
  }

  const lastUpdated = options.updatedAt

  return (
    <>
      {exportError ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{exportError}</p>
        </div>
      ) : null}

      <ReportFilters
        value={filters}
        options={options.data}
        optionsLoading={options.loading}
        onChange={handleFilters}
        onRefresh={bumpRefresh}
        refreshing={false}
        lastUpdated={lastUpdated}
      />

      <div className="dashboard-panel__head dashboard-panel__head--flush">
        <h3>Leaderboard</h3>
        <div className="dashboard-panel__hint">
          <ExportButtons
            report="achievements"
            filters={filters}
            onExport={handleExport}
            disabled={exporting}
          />
        </div>
      </div>

      <AchievementLeaderboard
        key={filterKey}
        scope={filters}
        refreshVersion={refreshVersion}
        selectedEmployeeId={selectedEmployeeId}
        onSelectEmployee={setSelectedEmployeeId}
      />

      <AchievementSeriesPanel
        employeeId={selectedEmployeeId}
        scope={filters}
        refreshVersion={refreshVersion}
        onClose={() => setSelectedEmployeeId(null)}
      />
    </>
  )
}

function EmployeeAchievementsView() {
  const me = useReportResource(
    useCallback(() => getMyPerformance(), []),
    'achievements-self',
    'Unable to load your achievement record.',
  )

  const employeeId = me.data?.employee_id ?? null

  if (me.loading && !me.data) {
    return <p className="page-state">Loading your achievement record…</p>
  }

  if (me.error && !me.data) {
    return (
      <div className="page-state page-state--denied" role="alert">
        <p>{me.error}</p>
        <button type="button" className="button button--secondary" onClick={me.refresh}>
          Retry
        </button>
      </div>
    )
  }

  if (employeeId === null) {
    return <p className="page-state">No linked employee profile is available to this account.</p>
  }

  return (
    <AchievementSeriesPanel employeeId={employeeId} scope={{}} refreshVersion={0} onClose={() => undefined} />
  )
}

export default function AchievementsPage() {
  const { user } = useAuth()

  if (!user) {
    return <p className="page-state">Sign in to view achievements.</p>
  }

  const isEmployee = user.role === 'employee'

  return (
    <div className="page">
      <div className="page__intro">
        <h2>Achievements</h2>
        <p>
          {isEmployee
            ? 'Your monthly achievement series, current rank and badges.'
            : 'Monthly achievement rankings, employee-of-the-month honours and each employee’s personal series.'}
        </p>
      </div>

      {isEmployee ? <EmployeeAchievementsView /> : <ManagerAchievementsView />}
    </div>
  )
}