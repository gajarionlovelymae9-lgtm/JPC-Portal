import { useCallback } from 'react'
import { getEmployeeAchievements } from '../../services/reportService'
import { useReportResource } from '../../hooks/useReports'
import { toNumber } from '../../utils/task'
import type { ReportFilters } from '../../types/report'

interface AchievementSeriesPanelProps {
  employeeId: number | null
  scope: ReportFilters
  refreshVersion: number
  onClose: () => void
}

/** Personal achievement series: 6-month history, current rank and badges. */
export function AchievementSeriesPanel({
  employeeId,
  scope,
  refreshVersion,
  onClose,
}: AchievementSeriesPanelProps) {
  const load = useCallback(
    () => employeeId !== null ? getEmployeeAchievements(employeeId, scope) : Promise.reject(new Error('No employee selected')),
    [employeeId, scope],
  )

  const { data, loading, error, refresh } = useReportResource(
    load,
    `achievement-employee|${refreshVersion}|${employeeId ?? 'none'}|${JSON.stringify(scope)}`,
    'Unable to load the employee achievement series.',
  )

  if (!employeeId) {
    return null
  }

  return (
    <section className="task-section" aria-label="Employee achievement series">
      <div className="dashboard-panel__head">
        <div>
          <h3>{data?.employee_name ?? 'Achievement series'}</h3>
          {data?.department_name ? <span className="dashboard-panel__hint">{data.department_name}</span> : null}
        </div>
        {data ? (
          <span className="dashboard-panel__hint">
            Rank {data.current_rank ? `#${data.current_rank}` : '—'}
            {data.is_employee_of_month ? ' · Employee of the month' : ''}
          </span>
        ) : null}
      </div>

      {loading && !data ? <p className="page-state">Loading series…</p> : null}

      {error && !data ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{error}</p>
          <button type="button" className="button button--secondary" onClick={refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {data ? (
        <>
          <div className="task-table-wrap">
            <table className="task-table">
              <thead>
                <tr>
                  <th scope="col">Period</th>
                  <th scope="col">Completed</th>
                  <th scope="col">Total</th>
                  <th scope="col">Completion</th>
                  <th scope="col">On time</th>
                  <th scope="col">Score</th>
                </tr>
              </thead>
              <tbody>
                {data.series.map((month) => (
                  <tr key={month.label}>
                    <td data-label="Period">{month.label}</td>
                    <td data-label="Completed">{month.completed_tasks}</td>
                    <td data-label="Total">{month.total_tasks}</td>
                    <td data-label="Completion">{toNumber(month.completion_rate)}%</td>
                    <td data-label="On time">{toNumber(month.on_time_rate)}%</td>
                    <td data-label="Score">{toNumber(month.overall_score)}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {data.badges.length > 0 ? (
            <div className="dashboard-panel__sub">
              <h4>Badges</h4>
              <ul className="report-badges">
                {data.badges.map((badge) => (
                  <li key={badge.id ?? badge.code ?? badge.name ?? undefined} className="report-badge">
                    {badge.name ?? badge.code}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          <button type="button" className="button button--secondary" onClick={onClose}>
            Close series
          </button>
        </>
      ) : null}
    </section>
  )
}