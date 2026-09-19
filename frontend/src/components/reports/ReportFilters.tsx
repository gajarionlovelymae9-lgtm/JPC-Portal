import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import type { ReportFilters, ReportOptions, ReportScope } from '../../types/report'

const MONTHS = Array.from({ length: 12 }, (_, index) => index + 1)

const YEARS = Array.from({ length: 11 }, (_, index) => {
  const current = new Date().getFullYear()
  return current - index
})

interface ReportFiltersProps {
  value: ReportFilters
  options: ReportOptions | null
  optionsLoading: boolean
  onChange: (filters: ReportFilters) => void
  onRefresh: () => void
  refreshing: boolean
  lastUpdated: Date | null
}

/**
 * Scope toolbar for the report workbench (manager role). Every report section
 * reloads against the applied filter set.
 */
export function ReportFilters({
  value,
  options,
  optionsLoading,
  onChange,
  onRefresh,
  refreshing,
  lastUpdated,
}: ReportFiltersProps) {
  const [draft, setDraft] = useState<ReportFilters>(value)

  useEffect(() => {
    setDraft(value)
  }, [value])

  function patch(patch: Partial<ReportFilters>) {
    setDraft((current) => ({ ...current, ...patch }))
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    onChange(draft)
  }

  function handleReset() {
    const empty: ReportFilters = {}
    setDraft(empty)
    onChange(empty)
  }

  return (
    <div className="dashboard-filters-wrap">
      <form className="task-filters" onSubmit={handleSubmit} role="search" aria-label="Report scope filters">
        <label className="task-filters__field">
          <span className="task-filters__label">Department</span>
          <select
            value={draft.department_id ?? ''}
            onChange={(event) => patch({ department_id: event.target.value ? Number(event.target.value) : null })}
            disabled={optionsLoading}
          >
            <option value="">All departments</option>
            {(options?.departments ?? []).map((department) => (
              <option key={department.id} value={department.id}>
                {department.name}
              </option>
            ))}
          </select>
        </label>

        <label className="task-filters__field">
          <span className="task-filters__label">Academic year</span>
          <select
            value={draft.academic_year_id ?? ''}
            onChange={(event) => patch({ academic_year_id: event.target.value ? Number(event.target.value) : null })}
            disabled={optionsLoading}
          >
            <option value="">All academic years</option>
            {(options?.academic_years ?? []).map((year) => (
              <option key={year.id} value={year.id}>
                {year.name}
              </option>
            ))}
          </select>
        </label>

        <label className="task-filters__field">
          <span className="task-filters__label">Status</span>
          <select
            value={draft.status ?? ''}
            onChange={(event) => patch({ status: event.target.value || null })}
          >
            <option value="">Any status</option>
            {(options?.statuses ?? []).map((status) => (
              <option key={status} value={status}>
                {status.replaceAll('_', ' ')}
              </option>
            ))}
          </select>
        </label>

        <label className="task-filters__field">
          <span className="task-filters__label">Priority</span>
          <select
            value={draft.priority ?? ''}
            onChange={(event) => patch({ priority: event.target.value || null })}
          >
            <option value="">Any priority</option>
            {(options?.priorities ?? []).map((priority) => (
              <option key={priority} value={priority}>
                {priority}
              </option>
            ))}
          </select>
        </label>

        <label className="task-filters__field">
          <span className="task-filters__label">Month</span>
          <select
            value={draft.month ?? ''}
            onChange={(event) => patch({ month: event.target.value ? Number(event.target.value) : null })}
          >
            <option value="">Any month</option>
            {MONTHS.map((month) => (
              <option key={month} value={month}>
                {new Date(2000, month - 1, 1).toLocaleDateString(undefined, { month: 'long' })}
              </option>
            ))}
          </select>
        </label>

        <label className="task-filters__field">
          <span className="task-filters__label">Year</span>
          <select
            value={draft.year ?? ''}
            onChange={(event) => patch({ year: event.target.value ? Number(event.target.value) : null })}
          >
            <option value="">Any year</option>
            {YEARS.map((year) => (
              <option key={year} value={year}>
                {year}
              </option>
            ))}
          </select>
        </label>

        <label className="task-filters__field">
          <span className="task-filters__label">Scope</span>
          <select
            value={draft.scope ?? ''}
            onChange={(event) => patch({ scope: (event.target.value || undefined) as ReportScope | undefined })}
          >
            <option value="">Default scope</option>
            {(options?.scopes ?? []).map((scope) => (
              <option key={scope} value={scope}>
                {scope}
              </option>
            ))}
          </select>
        </label>

        <div className="task-filters__actions">
          <button type="submit" className="button">
            Apply
          </button>
          <button type="button" className="button button--secondary" onClick={handleReset}>
            Reset
          </button>
        </div>
      </form>

      <div className="dashboard-toolbar">
        <p className="dashboard-toolbar__meta">
          {lastUpdated
            ? `Last updated ${lastUpdated.toLocaleTimeString(undefined, {
                hour: '2-digit',
                minute: '2-digit',
              })}`
            : 'Not loaded yet'}
        </p>
        <button
          type="button"
          className="button button--secondary"
          onClick={onRefresh}
          disabled={refreshing}
        >
          {refreshing ? 'Refreshing…' : 'Refresh'}
        </button>
      </div>
    </div>
  )
}