import type { FormEvent } from 'react'

interface TableSearchBarProps {
  value: string
  onChange: (value: string) => void
  onSubmit: () => void
  placeholder?: string
}

/** Compact search field used above the paginated dashboard tables. */
export function TableSearchBar({
  value,
  onChange,
  onSubmit,
  placeholder = 'Search',
}: TableSearchBarProps) {
  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    onSubmit()
  }

  return (
    <form className="task-filters" onSubmit={handleSubmit} role="search">
      <label className="task-filters__field task-filters__field--grow">
        <span className="task-filters__label">Search</span>
        <input
          type="search"
          value={value}
          onChange={(event) => onChange(event.target.value)}
          placeholder={placeholder}
        />
      </label>
      <div className="task-filters__actions">
        <button type="submit" className="button">
          Apply
        </button>
      </div>
    </form>
  )
}