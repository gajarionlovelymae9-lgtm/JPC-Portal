import { useState } from 'react'
import { getTopPerformers } from '../../services/reportService'
import { useReportResource } from '../../hooks/useReports'
import { toNumber } from '../../utils/task'
import type { ReportFilters, ReportScope } from '../../types/report'

const SCOPES: ReportScope[] = ['daily', 'weekly', 'monthly']

interface TopPerformersPanelProps {
  scope: ReportFilters
  refreshVersion: number
}

/** Leaderboard of the highest-completing employees for daily/weekly/monthly. */
export function TopPerformersPanel({ scope, refreshVersion }: TopPerformersPanelProps) {
  const [selectedScope, setSelectedScope] = useState<ReportScope>('monthly')

  const { data, loading, error, refresh } = useReportResource(
    () => getTopPerformers({ ...scope, scope: selectedScope }),
    `top-performers|${refreshVersion}|${selectedScope}|${JSON.stringify(scope)}`,
    'Unable to load top performers.',
  )

  const performers = data?.performers ?? []

  return (
    <section className="task-section" aria-label="Top performers">
      <div className="dashboard-panel__head">
        <h3>Top performers</h3>
        <div className="dashboard-panel__hint">
          <select
            value={selectedScope}
            onChange={(event) => setSelectedScope(event.target.value as ReportScope)}
          >
            {SCOPES.map((value) => (
              <option key={value} value={value}>
                {value}
              </option>
            ))}
          </select>
          {data?.period ? <span>{data.period}</span> : null}
        </div>
      </div>

      {loading && !data ? <p className="page-state">Loading top performers…</p> : null}

      {error && !data ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{error}</p>
          <button type="button" className="button button--secondary" onClick={refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {performers.length === 0 && data ? (
        <p className="page-state">No completed work for the selected window.</p>
      ) : null}

      {performers.length > 0 ? (
        <div className="task-table-wrap">
          <table className="task-table">
            <thead>
              <tr>
                <th scope="col">Rank</th>
                <th scope="col">Employee</th>
                <th scope="col">Department</th>
                <th scope="col">Completed</th>
                <th scope="col">Rate</th>
              </tr>
            </thead>
            <tbody>
              {performers.map((performer) => (
                <tr key={performer.employee_id}>
                  <td data-label="Rank">
                    <span className={`achievement-rank achievement-rank--${performer.rank}`}>#{performer.rank}</span>
                  </td>
                  <td data-label="Employee">{performer.employee_name}</td>
                  <td data-label="Department">{performer.department_name ?? '—'}</td>
                  <td data-label="Completed">{performer.completed_tasks}</td>
                  <td data-label="Rate">{toNumber(performer.completion_rate)}%</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </section>
  )
}