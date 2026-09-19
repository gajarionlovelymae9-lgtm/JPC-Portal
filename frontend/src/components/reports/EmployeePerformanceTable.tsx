import { useCallback, useState } from 'react'
import { getEmployeePerformance } from '../../services/reportService'
import { useReportTable } from '../../hooks/useReports'
import { TablePagination } from '../dashboard/TablePagination'
import { TableSearchBar } from '../dashboard/TableSearchBar'
import { TaskProgressBar } from '../tasks/TaskProgressBar'
import { SortableTh } from './SortableTh'
import { formatDate, toNumber } from '../../utils/task'
import type { EmployeePerformanceRow, ReportFilters } from '../../types/report'

interface EmployeePerformanceTableProps {
  scope: ReportFilters
  refreshVersion: number
  selectedEmployeeId: number | null
  onSelect: (employeeId: number) => void
}

/** Ranked, sortable, paginated per-employee KPIs with drill-down selection. */
export function EmployeePerformanceTable({
  scope,
  refreshVersion,
  selectedEmployeeId,
  onSelect,
}: EmployeePerformanceTableProps) {
  const [sort, setSort] = useState<string>('employee_name')
  const [direction, setDirection] = useState<'asc' | 'desc'>('asc')

  const handleSort = useCallback((sortKey: string) => {
    setSort((current) => {
      if (current === sortKey) {
        setDirection((dir) => (dir === 'asc' ? 'desc' : 'asc'))
        return current
      }
      setDirection('asc')
      return sortKey
    })
  }, [])

  const { data, pagination, loading, error, refresh, setPage, search, setSearch, applySearch } =
    useReportTable<EmployeePerformanceRow>(
      getEmployeePerformance,
      scope,
      refreshVersion,
      'Unable to load employee performance.',
      { sort, direction },
    )

  if (loading && !data) {
    return <p className="page-state">Loading employee performance…</p>
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
        <p className="page-state">No employees match the current report filters.</p>
      ) : (
        <div className="task-table-wrap">
          <table className="task-table">
            <thead>
              <tr>
                <th scope="col">Rank</th>
                <SortableTh
                  label="Employee"
                  sortKey="employee_name"
                  sort={sort}
                  direction={direction}
                  onSort={handleSort}
                />
                <SortableTh
                  label="Department"
                  sortKey="department_name"
                  sort={sort}
                  direction={direction}
                  onSort={handleSort}
                />
                <SortableTh
                  label="Completed"
                  sortKey="completed_tasks"
                  sort={sort}
                  direction={direction}
                  onSort={handleSort}
                />
                <SortableTh
                  label="Overdue"
                  sortKey="overdue_tasks"
                  sort={sort}
                  direction={direction}
                  onSort={handleSort}
                />
                <SortableTh
                  label="Completion"
                  sortKey="completion_rate"
                  sort={sort}
                  direction={direction}
                  onSort={handleSort}
                />
                <SortableTh
                  label="On time"
                  sortKey="on_time_rate"
                  sort={sort}
                  direction={direction}
                  onSort={handleSort}
                />
                <th scope="col">Next due</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row: EmployeePerformanceRow) => {
                const isSelected = row.employee_id === selectedEmployeeId
                return (
                  <tr key={row.employee_id} className={isSelected ? 'task-table__row--selected' : undefined}>
                    <td data-label="Rank">
                      <span className={`achievement-rank achievement-rank--${row.rank}`}>#{row.rank}</span>
                    </td>
                    <td data-label="Employee">
                      <button
                        type="button"
                        className="task-table__title task-table__select"
                        aria-pressed={isSelected}
                        onClick={() => row.employee_id !== null && onSelect(row.employee_id)}
                      >
                        {row.employee_name}
                      </button>
                      {isSelected ? <span className="task-table__code">Viewing details</span> : null}
                    </td>
                    <td data-label="Department">{row.department_name ?? '—'}</td>
                    <td data-label="Completed">{row.completed_tasks}</td>
                    <td data-label="Overdue">
                      <span className={row.overdue_tasks > 0 ? 'task-table__overdue' : undefined}>
                        {row.overdue_tasks}
                      </span>
                    </td>
                    <td className="task-table__progress" data-label="Completion rate">
                      <TaskProgressBar value={toNumber(row.completion_rate)} size="sm" />
                    </td>
                    <td className="task-table__progress" data-label="On time rate">
                      <TaskProgressBar value={toNumber(row.on_time_rate)} size="sm" />
                    </td>
                    <td data-label="Next due">{formatDate(row.next_due_date)}</td>
                  </tr>
                )
              })}
            </tbody>
          </table>

          <TablePagination meta={pagination} onPageChange={setPage} label="employees" />
        </div>
      )}
    </div>
  )
}