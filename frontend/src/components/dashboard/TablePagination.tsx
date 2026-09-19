import type { PaginationMeta } from '../../types/api'

interface TablePaginationProps {
  meta: PaginationMeta | null
  onPageChange: (page: number) => void
  label?: string
}

/** Prev/next pager shared by the dashboard tables (same markup as tasks). */
export function TablePagination({ meta, onPageChange, label = 'items' }: TablePaginationProps) {
  if (!meta || meta.last_page <= 1) {
    return null
  }

  const rangeStart = (meta.current_page - 1) * meta.per_page + 1
  const rangeEnd = Math.min(meta.current_page * meta.per_page, meta.total)

  return (
    <div className="task-pagination">
      <button
        type="button"
        className="button button--secondary"
        disabled={meta.current_page <= 1}
        onClick={() => onPageChange(meta.current_page - 1)}
      >
        Previous
      </button>
      <span className="task-pagination__status">
        Showing {rangeStart}–{rangeEnd} of {meta.total} {label} · Page {meta.current_page} of{' '}
        {meta.last_page}
      </span>
      <button
        type="button"
        className="button button--secondary"
        disabled={meta.current_page >= meta.last_page}
        onClick={() => onPageChange(meta.current_page + 1)}
      >
        Next
      </button>
    </div>
  )
}