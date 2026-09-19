import type { FormEvent } from 'react'
import { statusLabel, titleCase } from '../../utils/task'
import type { TaskQueryParams, TaskSort } from '../../types/task'

const DEFAULT_STATUS_OPTIONS = [
  'draft',
  'assigned',
  'in_progress',
  'submitted',
  'under_review',
  'completed',
  'rejected',
  'cancelled',
]

const PRIORITY_OPTIONS = ['low', 'medium', 'high', 'urgent']

const SORT_OPTIONS: Array<{ value: TaskSort; label: string }> = [
  { value: 'due_date', label: 'Due date' },
  { value: 'priority', label: 'Priority' },
  { value: 'status', label: 'Status' },
  { value: 'title', label: 'Title' },
  { value: 'created_at', label: 'Created' },
  { value: 'updated_at', label: 'Updated' },
]

interface TaskFiltersProps {
  value: TaskQueryParams
  onChange: (patch: Partial<TaskQueryParams>) => void
  onReset: () => void
  statusOptions?: string[]
  showSort?: boolean
}

/** Filter bar shared by the task management list and "my tasks" views. */
export function TaskFilters({
  value,
  onChange,
  onReset,
  statusOptions = DEFAULT_STATUS_OPTIONS,
  showSort = true,
}: TaskFiltersProps) {
  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const term = String(new FormData(event.currentTarget).get('search') ?? '').trim()
    onChange({ search: term || undefined, page: 1 })
  }

  return (
    <form className="task-filters" onSubmit={handleSearch} role="search">
      <label className="task-filters__field task-filters__field--grow">
        <span className="task-filters__label">Search</span>
        <input
          type="search"
          name="search"
          key={value.search ?? ''}
          defaultValue={value.search ?? ''}
          placeholder="Task code, title or description"
        />
      </label>

      <label className="task-filters__field">
        <span className="task-filters__label">Status</span>
        <select
          value={value.status ?? ''}
          onChange={(event) => onChange({ status: event.target.value || undefined, page: 1 })}
        >
          <option value="">All statuses</option>
          {statusOptions.map((status) => (
            <option key={status} value={status}>
              {statusLabel(status)}
            </option>
          ))}
        </select>
      </label>

      <label className="task-filters__field">
        <span className="task-filters__label">Priority</span>
        <select
          value={value.priority ?? ''}
          onChange={(event) => onChange({ priority: event.target.value || undefined, page: 1 })}
        >
          <option value="">All priorities</option>
          {PRIORITY_OPTIONS.map((priority) => (
            <option key={priority} value={priority}>
              {titleCase(priority)}
            </option>
          ))}
        </select>
      </label>

      <label className="task-filters__field">
        <span className="task-filters__label">Due from</span>
        <input
          type="date"
          value={value.due_date_from ?? ''}
          onChange={(event) =>
            onChange({ due_date_from: event.target.value || undefined, page: 1 })
          }
        />
      </label>

      <label className="task-filters__field">
        <span className="task-filters__label">Due to</span>
        <input
          type="date"
          value={value.due_date_to ?? ''}
          onChange={(event) =>
            onChange({ due_date_to: event.target.value || undefined, page: 1 })
          }
        />
      </label>

      {showSort ? (
        <>
          <label className="task-filters__field">
            <span className="task-filters__label">Sort by</span>
            <select
              value={value.sort ?? 'created_at'}
              onChange={(event) => onChange({ sort: event.target.value, page: 1 })}
            >
              {SORT_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label className="task-filters__field">
            <span className="task-filters__label">Direction</span>
            <select
              value={value.direction ?? 'asc'}
              onChange={(event) =>
                onChange({ direction: event.target.value === 'desc' ? 'desc' : 'asc', page: 1 })
              }
            >
              <option value="asc">Ascending</option>
              <option value="desc">Descending</option>
            </select>
          </label>
        </>
      ) : null}

      <div className="task-filters__actions">
        <button type="submit" className="button">
          Apply
        </button>
        <button type="button" className="button button--secondary" onClick={onReset}>
          Reset
        </button>
      </div>
    </form>
  )
}
