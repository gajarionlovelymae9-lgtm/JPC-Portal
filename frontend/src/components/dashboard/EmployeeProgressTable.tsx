import { getDashboardEmployees } from '../../services/managementDashboardService'
import { useDashboardTable } from '../../hooks/useManagementDashboard'
import { TablePagination } from './TablePagination'
import { TableSearchBar } from './TableSearchBar'
import { TaskProgressBar } from '../tasks/TaskProgressBar'
import { toNumber } from '../../utils/task'
import type { DashboardScopeFilters, EmployeeProgressRow } from '../../types/dashboard'

interface EmployeeProgressTableProps {
  scope: DashboardScopeFilters
  refreshVersion: number
  selectedEmployeeId: number | null
  onSelect: (employeeId: number) => void
}

/** Paginated per-employee progress list; selecting a row loads the detail panel. */
export function EmployeeProgressTable({
  scope,
  refreshVersion,
  selectedEmployeeId,
  onSelect,
}: EmployeeProgressTableProps) {
  const { data, meta, loading, error, refresh, setPage, search, setSearch, applySearch } =
    useDashboardTable(
      getDashboardEmployees,
      scope,
      refreshVersion,
      'Unable to load employee progress.',
    )

  if (loading && !data) {
    return <p className="page-state">Loading employee progress…</p>
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

  const rows = data ?? []

  return (
    <div>
      <TableSearchBar
        value={search}
        onChange={setSearch}
        onSubmit={applySearch}
        placeholder="Search by employee name"
      />

      {rows.length === 0 ? (
        <p className="page-state">No employees match the current filters.</p>
      ) : (
        <div className="task-table-wrap">
          <table className="task-table">
            <thead>
              <tr>
                <th scope="col">Employee</th>
                <th scope="col">Assigned</th>
                <th scope="col">Completed</th>
                <th scope="col">Pending</th>
                <th scope="col">Overdue</th>
                <th scope="col">Completion rate</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row: EmployeeProgressRow) => {
                const isSelected = row.employee_id === selectedEmployeeId
                return (
                  <tr key={row.employee_id} className={isSelected ? 'task-table__row--selected' : undefined}>
                    <td data-label="Employee">
                      <button
                        type="button"
                        className="task-table__title task-table__select"
                        aria-pressed={isSelected}
                        onClick={() => onSelect(row.employee_id)}
                      >
                        {row.employee_name}
                      </button>
                      {isSelected ? <span className="task-table__code">Viewing details</span> : null}
                    </td>
                    <td data-label="Assigned">{row.assigned_tasks}</td>
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
                )
              })}
            </tbody>
          </table>

          <TablePagination meta={meta} onPageChange={setPage} label="employees" />
        </div>
      )}
    </div>
  )
}