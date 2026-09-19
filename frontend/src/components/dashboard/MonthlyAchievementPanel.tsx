import type { MonthlyAchievementRow } from '../../types/dashboard'

const TOP = 10

interface MonthlyAchievementPanelProps {
  rows: MonthlyAchievementRow[]
  loading: boolean
  error: string | null
  onRetry: () => void
}

/** Ranked leaderboard of monthly achievement (top performers for the period). */
export function MonthlyAchievementPanel({ rows, loading, error, onRetry }: MonthlyAchievementPanelProps) {
  if (loading) {
    return <p className="page-state">Loading achievement ranking…</p>
  }

  if (error) {
    return (
      <div className="page-state page-state--denied" role="alert">
        <p>{error}</p>
        <button type="button" className="button button--secondary" onClick={onRetry}>
          Retry
        </button>
      </div>
    )
  }

  if (rows.length === 0) {
    return <p className="page-state">No completed work yet for the selected period.</p>
  }

  const visible = rows.slice(0, TOP)

  return (
    <div className="task-table-wrap">
      <table className="task-table">
        <thead>
          <tr>
            <th scope="col">Rank</th>
            <th scope="col">Employee</th>
            <th scope="col">Department</th>
            <th scope="col">Completed</th>
            <th scope="col">Achievement rate</th>
          </tr>
        </thead>
        <tbody>
          {visible.map((row) => (
            <tr key={row.employee_id}>
              <td data-label="Rank">
                <span className={`achievement-rank achievement-rank--${row.rank}`}>#{row.rank}</span>
              </td>
              <td data-label="Employee">{row.employee_name}</td>
              <td data-label="Department">{row.department_name ?? '—'}</td>
              <td data-label="Completed">{row.completed_tasks}</td>
              <td data-label="Achievement rate">{row.completion_rate}%</td>
            </tr>
          ))}
        </tbody>
      </table>
      {rows.length > TOP ? (
        <p className="dashboard-toolbar__meta">Showing top {TOP} of {rows.length}</p>
      ) : null}
    </div>
  )
}