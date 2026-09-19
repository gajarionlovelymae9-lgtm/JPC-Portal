import { useCallback } from 'react'
import { getEmployeePerformanceDetail } from '../../services/reportService'
import { useReportResource } from '../../hooks/useReports'
import { TaskSummaryCards } from '../tasks/TaskSummaryCards'
import { formatDate, formatDateTime, statusLabel, toNumber } from '../../utils/task'
import type { ReportFilters } from '../../types/report'

interface EmployeePerformanceDetailPanelProps {
  employeeId: number | null
  scope: ReportFilters
  refreshVersion: number
  onClose: () => void
}

function detailCards(detail: { total_tasks: number; completed_tasks: number; pending_tasks: number; overdue_tasks: number; completion_rate: number; on_time_rate: number }) {
  return [
    { label: 'Total tasks', value: detail.total_tasks },
    { label: 'Completed', value: detail.completed_tasks, tone: 'ok' as const },
    { label: 'Pending', value: detail.pending_tasks, tone: 'warning' as const },
    { label: 'Overdue', value: detail.overdue_tasks, tone: 'error' as const },
    { label: 'Completion rate', value: `${toNumber(detail.completion_rate)}%`, tone: 'info' as const },
    { label: 'On-time rate', value: `${toNumber(detail.on_time_rate)}%`, tone: 'info' as const },
  ]
}

/** Employee drill-down: KPI cards plus the most recent assignments. */
export function EmployeePerformanceDetailPanel({
  employeeId,
  scope,
  refreshVersion,
  onClose,
}: EmployeePerformanceDetailPanelProps) {
  const load = useCallback(
    () => employeeId !== null ? getEmployeePerformanceDetail(employeeId, scope) : Promise.reject(new Error('No employee selected')),
    [employeeId, scope],
  )

  const { data, loading, error, refresh } = useReportResource(
    load,
    `employee-detail|${refreshVersion}|${employeeId ?? 'none'}|${JSON.stringify(scope)}`,
    'Unable to load the employee detail.',
  )

  if (!employeeId) {
    return null
  }

  return (
    <section className="task-section" aria-label="Employee performance detail">
      <div className="dashboard-panel__head">
        <div>
          <h3>{data?.employee_name ?? 'Employee detail'}</h3>
          {data?.department_name ? <span className="dashboard-panel__hint">{data.department_name}</span> : null}
        </div>
        {data ? (
          <span className="dashboard-panel__hint">
            Rank {data.rank ? `#${data.rank}` : '—'}
          </span>
        ) : null}
      </div>

      {loading && !data ? <p className="page-state">Loading detail…</p> : null}

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
          <TaskSummaryCards items={detailCards(data)} />

          <div className="dashboard-panel__sub">
            <h4>Recent assignments</h4>
            {data.recent_assignments.length === 0 ? (
              <p className="page-state">No recent assignments for this employee.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Task</th>
                      <th scope="col">Status</th>
                      <th scope="col">Due</th>
                      <th scope="col">Completed</th>
                      <th scope="col">Progress</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.recent_assignments.map((assignment) => (
                      <tr key={assignment.assignment_id}>
                        <td data-label="Task">
                          <span className="task-table__title">{assignment.task_title ?? 'Untitled task'}</span>
                          {assignment.task_code ? <span className="task-table__code">{assignment.task_code}</span> : null}
                        </td>
                        <td data-label="Status">{statusLabel(assignment.status)}</td>
                        <td data-label="Due">{formatDate(assignment.due_date)}</td>
                        <td data-label="Completed">{formatDateTime(assignment.completed_at)}</td>
                        <td data-label="Progress">{assignment.progress_percentage ?? '—'}%</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      ) : null}

      <button type="button" className="button button--secondary" onClick={onClose}>
        Close detail
      </button>
    </section>
  )
}