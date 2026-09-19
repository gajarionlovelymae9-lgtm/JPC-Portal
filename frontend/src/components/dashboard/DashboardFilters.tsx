import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import type { DashboardOptions, DashboardScopeFilters } from '../../types/dashboard'

const MONTHS = Array.from({ length: 12 }, (_, index) => index + 1)

const YEARS = Array.from({ length: 11 }, (_, index) => {
  const current = new Date().getFullYear()
  return current - index
})

interface DashboardFiltersProps {
  value: DashboardScopeFilters
  options: DashboardOptions | null
  optionsLoading: boolean
  onChange: (filters: DashboardScopeFilters) => void
  onRefresh: () => void
  refreshing: boolean
  lastUpdated: Date | null
}

/** Scope toolbar for the management dashboard (apply once, then every section reloads). */
export function DashboardFilters({
  value,
  options,
  optionsLoading,
  onChange,
  onRefresh,
  refreshing,
  lastUpdated,
}: DashboardFiltersProps) {
  const [draft, setDraft] = useState<DashboardScopeFilters>(value)

  useEffect(() => {
    setDraft(value)
  }, [value])

  function patch(patch: Partial<DashboardScopeFilters>) {
    setDraft((current) => ({ ...current, ...patch }))
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    onChange(draft)
  }

  function handleReset() {
    const empty: DashboardScopeFilters = {}
    setDraft(empty)
    onChange(empty)
  }

  const monthOf = draft.month ?? null
  const yearOf = draft.year ?? null

  return (
    <div className="dashboard-filters-wrap">
      <form className="task-filters" onSubmit={handleSubmit} role="search" aria-label="Dashboard scope filters">
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
          <span className="task-filters__label">Month</span>
          <select
            value={monthOf ?? ''}
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
            value={yearOf ?? ''}
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
          <span className="task-filters__label">From</span>
          <input
            type="date"
            value={draft.from_date ?? ''}
            onChange={(event) => patch({ from_date: event.target.value || null })}
          />
        </label>

        <label className="task-filters__field">
          <span className="task-filters__label">To</span>
          <input
            type="date"
            value={draft.to_date ?? ''}
            onChange={(event) => patch({ to_date: event.target.value || null })}
          />
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