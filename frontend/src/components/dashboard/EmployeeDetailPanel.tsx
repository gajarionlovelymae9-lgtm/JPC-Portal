import { getEmployeeDetailProgress } from '../../services/managementDashboardService'
import { useDashboardResource } from '../../hooks/useManagementDashboard'
import { TaskBreakdownChart } from '../tasks/TaskBreakdownChart'
import { TaskMonthlyProgress } from '../tasks/TaskMonthlyProgress'
import { TaskProgressBar } from '../tasks/TaskProgressBar'
import { TaskStatusBadge } from '../tasks/TaskStatusBadge'
import { friendlyMonthLabel } from '../../utils/dashboard'
import { formatDate, toNumber } from '../../utils/task'
import type { DashboardScopeFilters } from '../../types/dashboard'

interface EmployeeDetailPanelProps {
  employeeId: number | null
  scope: DashboardScopeFilters
  refreshVersion: number
  onClose: () => void
}

/** Drill-down view for a single employee's assignment progress. */
export function EmployeeDetailPanel({ employeeId, scope, refreshVersion, onClose }: EmployeeDetailPanelProps) {
  const queryKey = employeeId ? `${refreshVersion}|${employeeId}|${JSON.stringify(scope)}` : 'none'
  const { data, loading, error, refresh } = useDashboardResource(
    () => {
      if (!employeeId) {
        return Promise.resolve({
          success: true,
          message: '',
          data: null,
        })
      }
      return getEmployeeDetailProgress(employeeId, scope)
    },
    queryKey,
    'Unable to load employee details.',
  )

  if (!employeeId) {
    return null
  }

  if (loading && !data) {
    return <p className="page-state">Loading employee details…</p>
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

  if (!data) {
    return null
  }

  const months = data.months.map((month) => ({
    label: friendlyMonthLabel(month.label),
    total: month.total,
    completed: month.completed,
  }))

  return (
    <section className="task-section" aria-label={`Progress of ${data.employee_name}`}>
      <div className="dashboard-panel__head">
        <h3>{data.employee_name}</h3>
        <span className="dashboard-panel__hint">{data.department_name ?? 'No department'}</span>
        <button type="button" className="button button--secondary" onClick={onClose}>
          Close
        </button>
      </div>

      <div className="task-stats">
        <div className="task-stat">
          <span className="task-stat__value">{data.summary.total}</span>
          <span className="task-stat__label">Assigned</span>
        </div>
        <div className="task-stat task-stat--ok">
          <span className="task-stat__value">{data.summary.completed}</span>
          <span className="task-stat__label">Completed</span>
        </div>
        <div className="task-stat task-stat--info">
          <span className="task-stat__value">{data.summary.completion_rate}%</span>
          <span className="task-stat__label">Completion</span>
        </div>
        <div className="task-stat task-stat--warning">
          <span className="task-stat__value">{data.summary.overdue}</span>
          <span className="task-stat__label">Overdue</span>
        </div>
      </div>

      <div className="dashboard-grid dashboard-grid--detail">
        <div>
          <h4 className="dashboard-panel__sub">Six-month activity</h4>
          <TaskMonthlyProgress items={months} />
        </div>
        <div>
          <h4 className="dashboard-panel__sub">Status distribution</h4>
          <TaskBreakdownChart
            items={[
              { label: 'In progress', value: data.summary.in_progress, tone: 'default' },
              { label: 'Submitted', value: data.summary.submitted, tone: 'default' },
              { label: 'Under review', value: data.summary.under_review, tone: 'info' },
              { label: 'Rejected', value: data.summary.rejected, tone: 'error' },
              { label: 'Overdue', value: data.summary.overdue, tone: 'warning' },
            ]}
          />
        </div>
      </div>

      <h4 className="dashboard-panel__sub">Recent assignments</h4>
      {data.recent_assignments.length === 0 ? (
        <p className="page-state">No recent assignments.</p>
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
                    <span className="task-table__title">{assignment.task_title ?? 'Task'}</span>
                    {assignment.task_code ? (
                      <span className="task-table__code">{assignment.task_code}</span>
                    ) : null}
                  </td>
                  <td data-label="Status">
                    <TaskStatusBadge status={assignment.status} />
                  </td>
                  <td data-label="Due">{formatDate(assignment.due_date)}</td>
                  <td data-label="Completed">{formatDate(assignment.completed_at)}</td>
                  <td className="task-table__progress" data-label="Progress">
                    <TaskProgressBar value={toNumber(assignment.progress_percentage)} size="sm" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}