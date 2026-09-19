import { useAuth } from '../../auth/useAuth'
import { Link } from 'react-router-dom'
import { TaskBreakdownChart } from '../../components/tasks/TaskBreakdownChart'
import { TaskCard } from '../../components/tasks/TaskCard'
import { TaskStatusBadge } from '../../components/tasks/TaskStatusBadge'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useMyUpcomingTasks } from '../../hooks/useUpcomingTasks'
import { useMyTasks } from '../../hooks/useTasks'
import { employeeTaskDetailPath } from '../../routes/paths'
import { deadlineLabel } from '../../utils/task'
import type { MyTaskSummary, Task, TaskAssignment } from '../../types/task'

function assignmentFor(task: Task, employeeId: number): TaskAssignment | undefined {
  return task.assignments?.find((assignment) => assignment.employee_id === employeeId)
}

function isOpen(task: Task): boolean {
  return task.status !== 'completed' && task.status !== 'cancelled'
}

function completionRate(summary: MyTaskSummary): number {
  return summary.total > 0 ? Math.round((summary.completed / summary.total) * 100) : 0
}

/**
 * Derived from the employee's own completed assignments (the API summary does
 * not expose an on-time metric). Returns null when there is nothing to score.
 */
function onTimeRate(tasks: Task[], employeeId: number): number | null {
  const completed = tasks.filter(
    (task) => assignmentFor(task, employeeId)?.status === 'completed',
  )
  if (completed.length === 0) {
    return null
  }
  const onTime = completed.filter((task) => {
    const assignment = assignmentFor(task, employeeId)
    if (!assignment?.completed_at || !assignment.due_date) {
      return false
    }
    return new Date(assignment.completed_at).getTime() <= new Date(assignment.due_date).getTime()
  })
  return Math.round((onTime.length / completed.length) * 100)
}

function EmployeeDashboardContent({ employeeId }: { employeeId: number }) {
  const { tasks, summary, loading, error, refresh } = useMyTasks({
    per_page: 100,
    sort: 'due_date',
    direction: 'asc',
  })
  const { upcoming: upcomingTasks, loading: upcomingLoading } = useMyUpcomingTasks()

  const openTasks = tasks.filter(isOpen)
  const overdueTasks = openTasks.filter((task) => task.is_overdue)

  const rate = summary ? onTimeRate(tasks, employeeId) : null
  const kpis = summary
    ? [
        { label: 'Total tasks', value: summary.total, tone: 'info' as const },
        { label: 'Completed', value: summary.completed, tone: 'ok' as const },
        { label: 'Pending', value: summary.total - summary.completed, tone: 'default' as const },
        { label: 'Overdue', value: summary.overdue, tone: 'error' as const },
        {
          label: 'Completion rate',
          value: `${completionRate(summary)}%`,
          tone: 'ok' as const,
        },
        {
          label: 'On-time rate',
          value: rate === null ? '—' : `${rate}%`,
          tone: 'info' as const,
        },
      ]
    : []

  return (
    <div className="page">
      <div className="page__intro">
        <h2>My Dashboard</h2>
        <p>
          A snapshot of every task assigned to you, with anything overdue or due
          soon highlighted first.
        </p>
      </div>

      {loading ? (
        <div className="task-stats" aria-hidden="true">
          {[0, 1, 2, 3].map((key) => (
            <div key={key} className="task-stat task-stat--skeleton" />
          ))}
        </div>
      ) : null}

      {error ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{error}</p>
          <button type="button" className="button button--secondary" onClick={refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {!loading && !error && summary ? (
        <>
          <TaskSummaryCards items={kpis} />

          {rate === null ? (
            <p className="task-note">
              On-time rate will appear once you complete your first task.
            </p>
          ) : null}

          <TaskBreakdownChart
            title="Task breakdown"
            items={[
              { label: 'Completed', value: summary.completed, tone: 'ok' },
              { label: 'In progress', value: summary.in_progress, tone: 'info' },
              {
                label: 'Pending',
                value: summary.submitted + summary.under_review + summary.rejected,
                tone: 'warning',
              },
              { label: 'Overdue', value: summary.overdue, tone: 'error' },
            ]}
          />

          <section className="task-section">
            <h3>Needs attention</h3>
            {overdueTasks.length === 0 ? (
              <p className="page-state">Nothing is overdue. Great work.</p>
            ) : (
              <div className="task-grid">
                {overdueTasks.map((task) => (
                  <TaskCard
                    key={task.id}
                    task={task}
                    assignment={assignmentFor(task, employeeId)}
                    to={employeeTaskDetailPath(task.id)}
                  />
                ))}
              </div>
            )}
          </section>

          <section className="task-section">
            <h3>Upcoming</h3>
            {upcomingLoading && upcomingTasks.length === 0 ? (
              <p className="page-state">Loading upcoming deadlines…</p>
            ) : upcomingTasks.length === 0 ? (
              <p className="page-state">No open tasks on your plate.</p>
            ) : (
              <ul className="upcoming-list">
                {upcomingTasks.map((task) => (
                  <li key={task.assignment_id} className="upcoming-list__item">
                    <span className="upcoming-list__code">{task.task_code}</span>
                    <Link
                      className="upcoming-list__title"
                      to={employeeTaskDetailPath(task.task_id)}
                    >
                      {task.title}
                    </Link>
                    <TaskStatusBadge status={task.status} />
                    <span
                      className="upcoming-list__due"
                      title={deadlineLabel(task.due_date) ?? undefined}
                    >
                      {deadlineLabel(task.due_date)}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      ) : null}
    </div>
  )
}

export default function EmployeeDashboardPage() {
  const { user } = useAuth()

  if (!user?.employee_id) {
    return (
      <div className="page-state">
        No employee profile is linked to this account, so there are no personal
        tasks to display.
      </div>
    )
  }

  return <EmployeeDashboardContent employeeId={user.employee_id} />
}
