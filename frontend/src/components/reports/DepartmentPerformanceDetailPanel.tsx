import { useCallback } from 'react'
import { getDepartmentPerformanceDetail } from '../../services/reportService'
import { useReportResource } from '../../hooks/useReports'
import { TaskSummaryCards } from '../tasks/TaskSummaryCards'
import { formatDate, formatDateTime, statusLabel, toNumber } from '../../utils/task'
import type { ReportFilters } from '../../types/report'

interface DepartmentPerformanceDetailPanelProps {
  departmentId: number | null
  scope: ReportFilters
  refreshVersion: number
  onClose: () => void
}

function detailCards(detail: { employee_count: number; total_tasks: number; completed_tasks: number; pending_tasks: number; overdue_tasks: number; completion_rate: number; on_time_rate: number }) {
  return [
    { label: 'Staff', value: detail.employee_count },
    { label: 'Total tasks', value: detail.total_tasks },
    { label: 'Completed', value: detail.completed_tasks, tone: 'ok' as const },
    { label: 'Pending', value: detail.pending_tasks, tone: 'warning' as const },
    { label: 'Overdue', value: detail.overdue_tasks, tone: 'error' as const },
    { label: 'Completion rate', value: `${toNumber(detail.completion_rate)}%`, tone: 'info' as const },
    { label: 'On-time rate', value: `${toNumber(detail.on_time_rate)}%`, tone: 'info' as const },
  ]
}

/** Department drill-down: KPI cards plus the most recent tasks. */
export function DepartmentPerformanceDetailPanel({
  departmentId,
  scope,
  refreshVersion,
  onClose,
}: DepartmentPerformanceDetailPanelProps) {
  const load = useCallback(
    () => departmentId !== null ? getDepartmentPerformanceDetail(departmentId, scope) : Promise.reject(new Error('No department selected')),
    [departmentId, scope],
  )

  const { data, loading, error, refresh } = useReportResource(
    load,
    `department-detail|${refreshVersion}|${departmentId ?? 'none'}|${JSON.stringify(scope)}`,
    'Unable to load the department detail.',
  )

  if (!departmentId) {
    return null
  }

  return (
    <section className="task-section" aria-label="Department performance detail">
      <div className="dashboard-panel__head">
        <div>
          <h3>{data?.department_name ?? 'Department detail'}</h3>
          {data?.department_code ? <span className="dashboard-panel__hint">{data.department_code}</span> : null}
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
            <h4>Recent tasks</h4>
            {data.recent_tasks.length === 0 ? (
              <p className="page-state">No recent tasks for this department.</p>
            ) : (
              <div className="task-table-wrap">
                <table className="task-table">
                  <thead>
                    <tr>
                      <th scope="col">Task</th>
                      <th scope="col">Status</th>
                      <th scope="col">Assignees</th>
                      <th scope="col">Due</th>
                      <th scope="col">Completed</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.recent_tasks.map((task) => (
                      <tr key={task.task_id}>
                        <td data-label="Task">
                          <span className="task-table__title">{task.title ?? 'Untitled task'}</span>
                          {task.task_code ? <span className="task-table__code">{task.task_code}</span> : null}
                        </td>
                        <td data-label="Status">{statusLabel(task.status)}</td>
                        <td data-label="Assignees">{task.assignee_names.join(', ') || '—'}</td>
                        <td data-label="Due">{formatDate(task.due_date)}</td>
                        <td data-label="Completed">{formatDateTime(task.completed_at)}</td>
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