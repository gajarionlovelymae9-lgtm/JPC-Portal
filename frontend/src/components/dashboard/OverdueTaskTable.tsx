import { getDashboardOverdue } from '../../services/managementDashboardService'
import { useDashboardTable } from '../../hooks/useManagementDashboard'
import { TablePagination } from './TablePagination'
import { TableSearchBar } from './TableSearchBar'
import { TaskPriorityBadge } from '../tasks/TaskPriorityBadge'
import { deadlineLabel, formatDate } from '../../utils/task'
import type { DashboardScopeFilters, OverdueTask } from '../../types/dashboard'

interface OverdueTaskTableProps {
  scope: DashboardScopeFilters
  refreshVersion: number
}

/** Paginated overdue task list with days overdue. */
export function OverdueTaskTable({ scope, refreshVersion }: OverdueTaskTableProps) {
  const { data, meta, loading, error, refresh, setPage, search, setSearch, applySearch } =
    useDashboardTable(getDashboardOverdue, scope, refreshVersion, 'Unable to load overdue tasks.')

  if (loading && !data) {
    return <p className="page-state">Loading overdue tasks…</p>
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

  const rows: OverdueTask[] = data ?? []

  return (
    <div>
      <TableSearchBar
        value={search}
        onChange={setSearch}
        onSubmit={applySearch}
        placeholder="Search by task code or title"
      />

      {rows.length === 0 ? (
        <p className="page-state">No overdue tasks match the current filters.</p>
      ) : (
        <div className="task-table-wrap">
          <table className="task-table">
            <thead>
              <tr>
                <th scope="col">Task</th>
                <th scope="col">Department</th>
                <th scope="col">Assignee</th>
                <th scope="col">Due</th>
                <th scope="col">Overdue by</th>
                <th scope="col">Priority</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.task_id} className="task-table__row--overdue">
                  <td data-label="Task">
                    <span className="task-table__title">{row.title}</span>
                    <span className="task-table__code">{row.task_code}</span>
                  </td>
                  <td data-label="Department">{row.department_name ?? '—'}</td>
                  <td data-label="Assignee">
                    {row.assignee_names.length > 0 ? row.assignee_names.join(', ') : '—'}
                  </td>
                  <td data-label="Due">
                    <span className="task-table__overdue">{formatDate(row.due_date)}</span>
                    <span className="task-table__deadline">{deadlineLabel(row.due_date)}</span>
                  </td>
                  <td data-label="Overdue by">
                    {row.days_overdue !== null ? `${row.days_overdue} day${row.days_overdue === 1 ? '' : 's'}` : '—'}
                  </td>
                  <td data-label="Priority">
                    <TaskPriorityBadge priority={row.priority} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          <TablePagination meta={meta} onPageChange={setPage} label="overdue tasks" />
        </div>
      )}
    </div>
  )
}