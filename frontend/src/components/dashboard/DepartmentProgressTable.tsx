import { getDashboardDepartments } from '../../services/managementDashboardService'
import { useDashboardTable } from '../../hooks/useManagementDashboard'
import { TablePagination } from './TablePagination'
import { TableSearchBar } from './TableSearchBar'
import { TaskProgressBar } from '../tasks/TaskProgressBar'
import { toNumber } from '../../utils/task'
import type { DashboardScopeFilters, DepartmentProgressRow } from '../../types/dashboard'

interface DepartmentProgressTableProps {
  scope: DashboardScopeFilters
  refreshVersion: number
}

/** Paginated per-department task progress. */
export function DepartmentProgressTable({ scope, refreshVersion }: DepartmentProgressTableProps) {
  const { data, meta, loading, error, refresh, setPage, search, setSearch, applySearch } =
    useDashboardTable(
      getDashboardDepartments,
      scope,
      refreshVersion,
      'Unable to load department progress.',
    )

  if (loading && !data) {
    return <p className="page-state">Loading department progress…</p>
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

  const rows: DepartmentProgressRow[] = data ?? []

  return (
    <div>
      <TableSearchBar
        value={search}
        onChange={setSearch}
        onSubmit={applySearch}
        placeholder="Search by department name or code"
      />

      {rows.length === 0 ? (
        <p className="page-state">No departments match the current filters.</p>
      ) : (
        <div className="task-table-wrap">
          <table className="task-table">
            <thead>
              <tr>
                <th scope="col">Department</th>
                <th scope="col">Total tasks</th>
                <th scope="col">Completed</th>
                <th scope="col">Pending</th>
                <th scope="col">Overdue</th>
                <th scope="col">Completion rate</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.department_id}>
                  <td data-label="Department">{row.department_name}</td>
                  <td data-label="Total tasks">{row.total_tasks}</td>
                  <td data-label="Completed">{row.completed_tasks}</td>
                  <td data-label="Pending">{row.pending_tasks}</td>
                  <td data-label="Overdue">
                    <span className={row.overdue_tasks > 0 ? 'task-table__overdue' : undefined}>
                      {row.overdue_tasks}
                    </span>
                  </td>
                  <td className="task-table__progress" data-label="Completion rate">
                    <TaskProgressBar value={toNumber(row.completion_rate)} size="sm" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          <TablePagination meta={meta} onPageChange={setPage} label="departments" />
        </div>
      )}
    </div>
  )
}