import { useState } from 'react'
import { ActivityTimeline } from '../components/ActivityTimeline'
import { useActivityLogs } from '../hooks/useActivityLogs'

const ACTION_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'auth.login', label: 'Sign in' },
  { value: 'auth.logout', label: 'Sign out' },
  { value: 'task.created', label: 'Task created' },
  { value: 'task.updated', label: 'Task updated' },
  { value: 'task.cancelled', label: 'Task cancelled' },
  { value: 'task.assigned', label: 'Task assigned' },
  { value: 'task.submitted', label: 'Task submitted' },
  { value: 'task.resubmitted', label: 'Task resubmitted' },
  { value: 'task.reviewed', label: 'Task reviewed' },
  { value: 'report.submitted', label: 'Report submitted' },
  { value: 'progress.updated', label: 'Progress updated' },
]

export default function ActivityLogsPage() {
  const [search, setSearch] = useState('')
  const [action, setAction] = useState('')
  const [subject, setSubject] = useState('')
  const [page, setPage] = useState(1)

  const { logs, meta, loading, error, refresh } = useActivityLogs({
    search: search.trim() || undefined,
    action: action || undefined,
    subject: subject || undefined,
    page,
    per_page: 20,
  })

  const totalPages = meta?.last_page ?? 1

  function applyFilters(): void {
    setPage(1)
  }

  return (
    <div className="page">
      <div className="page__intro">
        <h2>Activity Logs</h2>
        <p>
          An append-only timeline of every significant action on the portal.
          Logs are immutable and cannot be modified or deleted.
        </p>
      </div>

      <form
        className="activity-filters"
        onSubmit={(event) => {
          event.preventDefault()
          applyFilters()
        }}
      >
        <label className="task-form__field">
          <span>Search</span>
          <input
            type="search"
            value={search}
            placeholder="Task code, description or reviewer"
            onChange={(event) => setSearch(event.target.value)}
          />
        </label>

        <label className="task-form__field">
          <span>Action</span>
          <select value={action} onChange={(event) => setAction(event.target.value)}>
            <option value="">All actions</option>
            {ACTION_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="task-form__field">
          <span>Subject</span>
          <select value={subject} onChange={(event) => setSubject(event.target.value)}>
            <option value="">All subjects</option>
            <option value="Task">Task</option>
            <option value="Employee">Employee</option>
          </select>
        </label>

        <button type="submit" className="button">
          Apply filters
        </button>
      </form>

      {error ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{error}</p>
          <button type="button" className="button button--secondary" onClick={refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {loading ? <p className="page-state">Loading activity…</p> : null}

      {!loading && !error && logs.length === 0 ? (
        <p className="page-state">No activity matches these filters.</p>
      ) : null}

      {!loading && !error && logs.length > 0 ? (
        <>
          <section className="task-section">
            <ActivityTimeline items={logs} />
          </section>

          {totalPages > 1 ? (
            <div className="pagination">
              <button
                type="button"
                className="button button--small button--secondary"
                disabled={page <= 1}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                Previous
              </button>
              <span className="pagination__info">
                Page {page} of {totalPages}
              </span>
              <button
                type="button"
                className="button button--small button--secondary"
                disabled={page >= totalPages}
                onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
              >
                Next
              </button>
            </div>
          ) : null}
        </>
      ) : null}
    </div>
  )
}