import { useCallback } from 'react'
import { getMonthlyAchievements } from '../../services/reportService'
import { useReportResource } from '../../hooks/useReports'
import { toNumber } from '../../utils/task'
import type { ReportFilters } from '../../types/report'

interface AchievementLeaderboardProps {
  scope: ReportFilters
  refreshVersion: number
  onSelectEmployee: (employeeId: number) => void
  selectedEmployeeId: number | null
}

/** Ranked monthly achievement leaderboard with employee-of-the-month marking. */
export function AchievementLeaderboard({
  scope,
  refreshVersion,
  onSelectEmployee,
  selectedEmployeeId,
}: AchievementLeaderboardProps) {
  const load = useCallback(() => getMonthlyAchievements(scope), [scope])

  const { data, loading, error, refresh } = useReportResource(
    load,
    `achievement-monthly|${refreshVersion}|${JSON.stringify(scope)}`,
    'Unable to load the achievement ranking.',
  )

  const rankings = data?.rankings ?? []

  return (
    <section className="task-section" aria-label="Monthly achievement ranking">
      <div className="dashboard-panel__head">
        <h3>Monthly achievement</h3>
        <span className="dashboard-panel__hint">{data?.period ?? ''}</span>
      </div>

      {loading && !data ? <p className="page-state">Loading achievement ranking…</p> : null}

      {error && !data ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{error}</p>
          <button type="button" className="button button--secondary" onClick={refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {rankings.length === 0 && data ? (
        <p className="page-state">No completed work yet for the selected period.</p>
      ) : null}

      {rankings.length > 0 ? (
        <div className="task-table-wrap">
          <table className="task-table">
            <thead>
              <tr>
                <th scope="col">Rank</th>
                <th scope="col">Employee</th>
                <th scope="col">Department</th>
                <th scope="col">Completed</th>
                <th scope="col">Total</th>
                <th scope="col">Score</th>
                <th scope="col">On time</th>
              </tr>
            </thead>
            <tbody>
              {rankings.map((row) => {
                const isSelected = row.employee_id === selectedEmployeeId
                return (
                  <tr key={row.employee_id} className={isSelected ? 'task-table__row--selected' : undefined}>
                    <td data-label="Rank">
                      <span className={`achievement-rank achievement-rank--${row.rank}`}>#{row.rank}</span>
                      {row.is_employee_of_month ? (
                        <span className="report-badge">Employee of the month</span>
                      ) : null}
                    </td>
                    <td data-label="Employee">
                      <button
                        type="button"
                        className="task-table__title task-table__select"
                        aria-pressed={isSelected}
                        onClick={() => row.employee_id !== null && onSelectEmployee(row.employee_id)}
                      >
                        {row.employee_name}
                      </button>
                      {isSelected ? <span className="task-table__code">Viewing series</span> : null}
                    </td>
                    <td data-label="Department">{row.department_name ?? '—'}</td>
                    <td data-label="Completed">{row.completed_tasks}</td>
                    <td data-label="Total">{row.total_tasks}</td>
                    <td data-label="Score">{toNumber(row.overall_score)}%</td>
                    <td data-label="On time">{toNumber(row.on_time_rate)}%</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      ) : null}
    </section>
  )
}