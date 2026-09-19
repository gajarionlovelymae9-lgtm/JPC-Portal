import { useAuth } from '../../auth/useAuth'
import { TaskCard } from '../../components/tasks/TaskCard'
import { TaskMonthlyProgress, type MonthlyProgressItem } from '../../components/tasks/TaskMonthlyProgress'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useMyTasks } from '../../hooks/useTasks'
import { employeeTaskDetailPath } from '../../routes/paths'
import { monthLabel } from '../../utils/task'
import type { MyTaskSummary, Task, TaskAssignment } from '../../types/task'

function assignmentFor(task: Task, employeeId: number): TaskAssignment | undefined {
  return task.assignments?.find((assignment) => assignment.employee_id === employeeId)
}

function isOpen(task: Task): boolean {
  return task.status !== 'completed' && task.status !== 'cancelled'
}

function completionRate(summary: MyTaskSummary): number {
  if (summary.total === 0) {
    return 0
  }
  return Math.round((summary.completed / summary.total) * 100)
}

interface MonthlyBucket extends MonthlyProgressItem {
  sort: number
}

/** Group the employee's tasks by due-date month (most recent three). */
function monthlyBreakdown(tasks: Task[], employeeId: number): MonthlyProgressItem[] {
  const buckets = new Map<string, MonthlyBucket>()

  tasks.forEach((task) => {
    const assignment = assignmentFor(task, employeeId)
    const raw = task.due_date ?? assignment?.due_date
    if (!raw) {
      return
    }
    const date = new Date(raw)
    if (Number.isNaN(date.getTime())) {
      return
    }

    const key = `${date.getFullYear()}-${date.getMonth()}`
    const bucket =
      buckets.get(key) ??
      ({
        label: monthLabel(date),
        completed: 0,
        total: 0,
        sort: date.getFullYear() * 12 + date.getMonth(),
      } satisfies MonthlyBucket)

    bucket.total += 1
    const status = assignment?.status ?? task.status
    if (status === 'completed') {
      bucket.completed += 1
    }
    buckets.set(key, bucket)
  })

  return [...buckets.values()]
    .sort((a, b) => a.sort - b.sort)
    .slice(-3)
    .map(({ label, completed, total }) => ({ label, completed, total }))
}

function toCards(summary: MyTaskSummary) {
  return [
    { label: 'Assigned', value: summary.total, tone: 'info' as const },
    { label: 'Completion rate', value: `${completionRate(summary)}%`, tone: 'ok' as const },
    { label: 'In Progress', value: summary.in_progress, tone: 'default' as const },
    { label: 'Submitted', value: summary.submitted, tone: 'default' as const },
    { label: 'Under Review', value: summary.under_review, tone: 'info' as const },
    { label: 'Rejected', value: summary.rejected, tone: 'error' as const },
    { label: 'Overdue', value: summary.overdue, tone: 'warning' as const },
  ]
}

function TaskProgressContent({ employeeId }: { employeeId: number }) {
  const { tasks, summary, loading, error, refresh } = useMyTasks({
    per_page: 100,
    sort: 'due_date',
    direction: 'asc',
  })

  const openTasks = tasks.filter(isOpen)
  const settledTasks = tasks.filter((task) => !isOpen(task))
  const months = monthlyBreakdown(tasks, employeeId)

  return (
    <div className="page">
      <div className="page__intro">
        <h2>My Progress</h2>
        <p>
          Track how much of your assigned work is in progress, under review or
          already completed.
        </p>
      </div>

      {loading ? <p className="page-state">Loading your progress…</p> : null}
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
          <TaskSummaryCards items={toCards(summary)} />

          <section className="task-section">
            <h3>Monthly progress</h3>
            <TaskMonthlyProgress items={months} />
          </section>

          <section className="task-section">
            <h3>Open work</h3>
            {openTasks.length === 0 ? (
              <p className="page-state">You have no open tasks.</p>
            ) : (
              <div className="task-grid">
                {openTasks.map((task) => (
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

          {settledTasks.length > 0 ? (
            <section className="task-section">
              <h3>Completed &amp; cancelled</h3>
              <div className="task-grid">
                {settledTasks.map((task) => (
                  <TaskCard
                    key={task.id}
                    task={task}
                    assignment={assignmentFor(task, employeeId)}
                    to={employeeTaskDetailPath(task.id)}
                  />
                ))}
              </div>
            </section>
          ) : null}
        </>
      ) : null}
    </div>
  )
}

export default function TaskProgressPage() {
  const { user } = useAuth()

  if (!user?.employee_id) {
    return (
      <div className="page-state">
        No employee profile is linked to this account, so there is no personal
        progress to display.
      </div>
    )
  }

  return <TaskProgressContent employeeId={user.employee_id} />
}
