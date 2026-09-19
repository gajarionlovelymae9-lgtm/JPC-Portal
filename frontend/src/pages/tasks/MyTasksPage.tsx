import { useState } from 'react'
import { useAuth } from '../../auth/useAuth'
import { TaskCard } from '../../components/tasks/TaskCard'
import { TaskFilters } from '../../components/tasks/TaskFilters'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useMyTasks } from '../../hooks/useTasks'
import { employeeTaskDetailPath } from '../../routes/paths'
import type { MyTaskSummary, Task, TaskAssignment, TaskQueryParams } from '../../types/task'

const INITIAL_FILTERS: TaskQueryParams = {
  sort: 'due_date',
  direction: 'asc',
  per_page: 12,
  page: 1,
}

function assignmentFor(task: Task, employeeId: number): TaskAssignment | undefined {
  return task.assignments?.find((assignment) => assignment.employee_id === employeeId)
}

function toCards(summary: MyTaskSummary) {
  return [
    { label: 'Assigned', value: summary.total, tone: 'info' as const },
    { label: 'In Progress', value: summary.in_progress, tone: 'default' as const },
    { label: 'Submitted', value: summary.submitted, tone: 'default' as const },
    { label: 'Under Review', value: summary.under_review, tone: 'info' as const },
    { label: 'Completed', value: summary.completed, tone: 'ok' as const },
    { label: 'Overdue', value: summary.overdue, tone: 'warning' as const },
  ]
}

function MyTasksContent({ employeeId }: { employeeId: number }) {
  const [filters, setFilters] = useState<TaskQueryParams>(INITIAL_FILTERS)
  const { tasks, meta, summary, loading, error, refresh } = useMyTasks(filters)

  function handleChange(patch: Partial<TaskQueryParams>) {
    setFilters((previous) => ({ ...previous, ...patch }))
  }

  return (
    <div className="page">
      <div className="page__intro">
        <h2>My Tasks</h2>
        <p>
          Every task assigned to you. Open a task to accept, start, update
          progress, submit or resubmit your work.
        </p>
      </div>

      {summary ? <TaskSummaryCards items={toCards(summary)} /> : null}

      <TaskFilters
        value={filters}
        onChange={handleChange}
        onReset={() => setFilters(INITIAL_FILTERS)}
      />

      {loading ? <p className="page-state">Loading your tasks…</p> : null}
      {error ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{error}</p>
          <button type="button" className="button button--secondary" onClick={refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {!loading && !error ? (
        tasks.length === 0 ? (
          <p className="page-state">No tasks assigned yet.</p>
        ) : (
          <>
            <div className="task-grid">
              {tasks.map((task) => (
                <TaskCard
                  key={task.id}
                  task={task}
                  assignment={assignmentFor(task, employeeId)}
                  to={employeeTaskDetailPath(task.id)}
                />
              ))}
            </div>

            {meta && meta.last_page > 1 ? (
              <div className="task-pagination">
                <button
                  type="button"
                  className="button button--secondary"
                  disabled={meta.current_page <= 1}
                  onClick={() => handleChange({ page: meta.current_page - 1 })}
                >
                  Previous
                </button>
                <span className="task-pagination__status">
                  Page {meta.current_page} of {meta.last_page} · {meta.total} tasks
                </span>
                <button
                  type="button"
                  className="button button--secondary"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => handleChange({ page: meta.current_page + 1 })}
                >
                  Next
                </button>
              </div>
            ) : null}
          </>
        )
      ) : null}
    </div>
  )
}

export default function MyTasksPage() {
  const { user } = useAuth()

  if (!user?.employee_id) {
    return (
      <div className="page-state">
        No employee profile is linked to this account, so there are no personal
        tasks to display.
      </div>
    )
  }

  return <MyTasksContent employeeId={user.employee_id} />
}
