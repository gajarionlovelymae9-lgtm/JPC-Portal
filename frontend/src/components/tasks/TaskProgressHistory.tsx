import { formatDateTime, toNumber } from '../../utils/task'
import type { TaskProgress } from '../../types/task'

interface TaskProgressHistoryProps {
  entries: TaskProgress[]
  /** Who recorded the updates (the assignment owner). */
  updatedBy?: string
  emptyMessage?: string
}

/** Tabular progress history: date, previous %, new %, comment and author. */
export function TaskProgressHistory({
  entries,
  updatedBy,
  emptyMessage = 'No progress updates recorded yet.',
}: TaskProgressHistoryProps) {
  if (entries.length === 0) {
    return <p className="page-state">{emptyMessage}</p>
  }

  return (
    <div className="task-table-wrap">
      <table className="task-table">
        <thead>
          <tr>
            <th scope="col">Date</th>
            <th scope="col">Previous</th>
            <th scope="col">New</th>
            <th scope="col">Comment</th>
            <th scope="col">Updated by</th>
          </tr>
        </thead>
        <tbody>
          {entries.map((entry) => (
            <tr key={entry.id}>
              <td data-label="Date">{formatDateTime(entry.created_at)}</td>
              <td data-label="Previous">
                {entry.previous_percentage === null || entry.previous_percentage === undefined
                  ? '—'
                  : `${toNumber(entry.previous_percentage)}%`}
              </td>
              <td data-label="New">
                <strong>{toNumber(entry.new_percentage)}%</strong>
              </td>
              <td data-label="Comment">{entry.comment ?? '—'}</td>
              <td data-label="Updated by">{updatedBy ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
