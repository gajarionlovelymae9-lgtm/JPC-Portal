import { useMemo, useState } from 'react'
import { getDashboardReviewQueue } from '../../services/managementDashboardService'
import { useDashboardTable } from '../../hooks/useManagementDashboard'
import { TablePagination } from './TablePagination'
import { TableSearchBar } from './TableSearchBar'
import { TaskStatusBadge } from '../tasks/TaskStatusBadge'
import { decisionLabel, formatDateTime, statusLabel } from '../../utils/task'
import type { DashboardScopeFilters, ReviewQueueItem } from '../../types/dashboard'

const QUEUE_STATUSES = ['submitted', 'under_review']

interface ReviewQueueTableProps {
  scope: DashboardScopeFilters
  refreshVersion: number
}

/** Paginated review queue: submitted / under-review assignments to be reviewed. */
export function ReviewQueueTable({ scope, refreshVersion }: ReviewQueueTableProps) {
  const [status, setStatus] = useState('')

  const localScope = useMemo(
    () => ({ ...scope, status: status || undefined }),
    [scope, status],
  )

  const { data, meta, loading, error, refresh, setPage, search, setSearch, applySearch } =
    useDashboardTable(
      getDashboardReviewQueue,
      localScope,
      refreshVersion,
      'Unable to load the review queue.',
    )

  if (loading && !data) {
    return <p className="page-state">Loading review queue…</p>
  }

  if (error && !data) {
    return (
      <div className="page-state page-state--denied" role="alert">
        <p>{error}</p>
        <button type="button" className="button button--secondary" onClick={refresh}>
          Retry
        </button>
      </div>
    )
  }

  const rows: ReviewQueueItem[] = data ?? []

  return (
    <div>
      <div className="dashboard-panel__head dashboard-panel__head--flush">
        <TableSearchBar
          value={search}
          onChange={setSearch}
          onSubmit={applySearch}
          placeholder="Search task or employee"
        />
        <label className="task-filters__field">
          <span className="task-filters__label">Status</span>
          <select
            value={status}
            onChange={(event) => setStatus(event.target.value)}
            aria-label="Filter by queue status"
          >
            <option value="">All statuses</option>
            {QUEUE_STATUSES.map((queueStatus) => (
              <option key={queueStatus} value={queueStatus}>
                {statusLabel(queueStatus)}
              </option>
            ))}
          </select>
        </label>
      </div>

      {rows.length === 0 ? (
        <p className="page-state">No submissions awaiting review.</p>
      ) : (
        <div className="task-table-wrap">
          <table className="task-table">
            <thead>
              <tr>
                <th scope="col">Task</th>
                <th scope="col">Employee</th>
                <th scope="col">Status</th>
                <th scope="col">Submitted</th>
                <th scope="col">Latest review</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => {
                const review = row.latest_review
                return (
                  <tr key={row.assignment_id}>
                    <td data-label="Task">
                      <span className="task-table__title">{row.task_title ?? 'Task'}</span>
                      {row.task_code ? <span className="task-table__code">{row.task_code}</span> : null}
                      {row.department_name ? (
                        <span className="task-table__deadline">{row.department_name}</span>
                      ) : null}
                    </td>
                    <td data-label="Employee">{row.employee_name ?? `Employee #${row.employee_id}`}</td>
                    <td data-label="Status">
                      <TaskStatusBadge status={row.status} />
                    </td>
                    <td data-label="Submitted">{formatDateTime(row.submitted_at)}</td>
                    <td data-label="Latest review">
                      {review ? (
                        <>
                          <span className={`task-badge task-badge--${review.decision.replace('_', '-')}`}>
                            {decisionLabel(review.decision)}
                          </span>
                          {review.remarks ? <p className="task-table__deadline">{review.remarks}</p> : null}
                          {review.reviewed_by ? (
                            <p className="task-table__deadline">
                              By {review.reviewed_by} · {formatDateTime(review.reviewed_at)}
                            </p>
                          ) : null}
                        </>
                      ) : (
                        <span className="task-table__deadline">Awaiting decision</span>
                      )}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>

          <TablePagination meta={meta} onPageChange={setPage} label="submissions" />
        </div>
      )}
    </div>
  )
}