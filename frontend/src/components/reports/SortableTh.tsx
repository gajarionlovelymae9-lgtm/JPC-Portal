import type { ReactNode } from 'react'

interface SortableThProps {
  label: string
  sortKey: string
  sort: string
  direction: 'asc' | 'desc'
  onSort: (sortKey: string) => void
  children?: ReactNode
}

/**
 * Click-to-sort table header. The same key toggles asc/desc; a new key starts
 * on the sort order the backend default expects for that column.
 */
export function SortableTh({ label, sortKey, sort, direction, onSort }: SortableThProps) {
  const active = sort === sortKey
  const arrow = active ? (direction === 'asc' ? ' ▲' : ' ▼') : ''

  return (
    <th scope="col" aria-sort={active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'}>
      <button
        type="button"
        className="task-table__sort"
        aria-pressed={active}
        onClick={() => onSort(sortKey)}
      >
        {label}
        {arrow}
      </button>
    </th>
  )
}