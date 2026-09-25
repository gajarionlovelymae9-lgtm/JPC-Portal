import { MONTH_NAMES } from '../../hooks/useReporting'

interface PeriodFilterProps {
  year: number
  month: number
  onYearChange: (year: number) => void
  onMonthChange: (month: number) => void
}

const YEARS = [
  new Date().getFullYear() - 4,
  new Date().getFullYear() - 3,
  new Date().getFullYear() - 2,
  new Date().getFullYear() - 1,
  new Date().getFullYear(),
]

/** Compact year/month selectors for the reporting pages. */
export function PeriodFilter({ year, month, onYearChange, onMonthChange }: PeriodFilterProps) {
  return (
    <div className="task-filters" role="group" aria-label="Reporting period">
      <label className="task-filters__field">
        <span className="task-filters__label">Year</span>
        <select value={year} onChange={(event) => onYearChange(Number(event.target.value))}>
          {YEARS.map((value) => (
            <option key={value} value={value}>
              {value}
            </option>
          ))}
        </select>
      </label>
      <label className="task-filters__field">
        <span className="task-filters__label">Month</span>
        <select value={month} onChange={(event) => onMonthChange(Number(event.target.value))}>
          {MONTH_NAMES.map((name, index) => (
            <option key={name} value={index + 1}>
              {name}
            </option>
          ))}
        </select>
      </label>
    </div>
  )
}