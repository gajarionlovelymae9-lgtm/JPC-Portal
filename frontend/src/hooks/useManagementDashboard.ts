import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { getApiErrorMessage } from '../utils/apiError'
import type { ApiResponse } from '../types/api'
import type { PaginationMeta } from '../types/api'
import type { DashboardScopeFilters, DashboardTableParams } from '../types/dashboard'

export interface ResourceState<T> {
  data: T | null
  meta: PaginationMeta | null
  loading: boolean
  error: string | null
  refresh: () => void
  updatedAt: Date | null
}

/**
 * Generic fetch hook for dashboard sections. The `queryKey` string changes
 * whenever the loader's inputs change; `refresh()` forces one retry without
 * changing the inputs. Previous data is kept on screen while reloading, and
 * `updatedAt` records the moment the current data arrived.
 */
export function useDashboardResource<T>(
  loader: () => Promise<ApiResponse<T>>,
  queryKey: string,
  fallbackError = 'Unable to load dashboard data.',
): ResourceState<T> {
  const [data, setData] = useState<T | null>(null)
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [refreshKey, setRefreshKey] = useState(0)
  const [updatedAt, setUpdatedAt] = useState<Date | null>(null)

  const loaderRef = useRef(loader)

  useEffect(() => {
    loaderRef.current = loader
  }, [loader])

  const refresh = useCallback(() => setRefreshKey((key) => key + 1), [])

  useEffect(() => {
    let active = true

    void (async () => {
      await Promise.resolve()
      if (!active) {
        return
      }
      setLoading(true)
      setError(null)

      try {
        const response = await loaderRef.current()
        if (active) {
          setData(response.data)
          setMeta(response.meta ?? null)
          setUpdatedAt(new Date())
        }
      } catch (cause: unknown) {
        if (active) {
          setError(getApiErrorMessage(cause, fallbackError))
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    })()

    return () => {
      active = false
    }
  }, [queryKey, refreshKey, fallbackError])

  return { data, meta, loading, error, refresh, updatedAt }
}

export interface DashboardTableState<T> extends ResourceState<T[]> {
  page: number
  setPage: (page: number) => void
  search: string
  setSearch: (search: string) => void
  applySearch: () => void
}

/**
 * Self-contained paginated table state for the dashboard lists: holds the
 * page + search while the server-side scope filters come from the page.
 * The `refreshVersion` bump re-fetches when the user clicks "Refresh".
 *
 * Page/search reset when the scope changes is handled by the parent by
 * keying each table instance on the serialized scope.
 */
export function useDashboardTable<T>(
  load: (params: DashboardTableParams) => Promise<ApiResponse<T[]>>,
  scope: DashboardScopeFilters,
  refreshVersion: number,
  fallbackError = 'Unable to load dashboard data.',
): DashboardTableState<T> {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [appliedSearch, setAppliedSearch] = useState('')

  const params = useMemo<DashboardTableParams>(
    () => ({
      ...scope,
      page,
      per_page: 10,
      search: appliedSearch.trim() || undefined,
    }),
    [scope, page, appliedSearch],
  )

  const queryKey = `${refreshVersion}|${JSON.stringify(params)}`

  const resource = useDashboardResource<T[]>(
    useCallback(() => load(params), [load, params]),
    queryKey,
    fallbackError,
  )

  const applySearch = useCallback(() => {
    setPage(1)
    setAppliedSearch(search)
  }, [search])

  return { ...resource, page, setPage, search, setSearch, applySearch }
}