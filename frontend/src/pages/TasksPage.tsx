import { useState } from 'react'
import { Link } from 'react-router-dom'
import { TaskFilters } from '../components/tasks/TaskFilters'
import { TaskTable } from '../components/tasks/TaskTable'
import { useTaskList } from '../hooks/useTasks'
import { taskDetailPath } from '../routes/paths'
import type { TaskQueryParams } from '../types/task'

const INITIAL_FILTERS: TaskQueryParams = {
  sort: 'created_at',
  direction: 'desc',
  per_page: 15,
  page: 1,
}

export default function TasksPage() {
  const [filters, setFilters] = useState<TaskQueryParams>(INITIAL_FILTERS)
  const { tasks, meta, loading, error, refresh } = useTaskList(filters)

  function handleChange(patch: Partial<TaskQueryParams>) {
    setFilters((previous) => ({ ...previous, ...patch }))
  }

  return (
    <div className="page">
      <div className="page__intro">
        <h2>Task Management</h2>
        <p>
          Browse, filter and open every task registered across departments. Task
          workflow actions are enforced by the backend.
        </p>
      </div>

      <TaskFilters
        value={filters}
        onChange={handleChange}
        onReset={() => setFilters(INITIAL_FILTERS)}
      />

      <TaskTable
        tasks={tasks}
        meta={meta ?? undefined}
        loading={loading}
        error={error}
        onRetry={refresh}
        onPageChange={(page) => handleChange({ page })}
        detailPath={taskDetailPath}
        emptyMessage="No tasks match the current filters."
        renderRowActions={(task) => (
          <Link to={taskDetailPath(task.id)} className="task-table__action">
            {task.status === 'accepted' || task.status === 'in_progress' ? 'Continue' : 'View'}
          </Link>
        )}
      />
    </div>
  )
}
