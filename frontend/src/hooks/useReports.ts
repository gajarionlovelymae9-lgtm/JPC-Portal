import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { downloadReportExport } from '../services/reportService'
import type { ReportExportType } from '../services/reportService'
import { getApiErrorMessage } from '../utils/apiError'
import type { PaginationMeta } from '../types/api'
import type { ExportFormat, ReportFilters, ReportMeta, ReportResponse } from '../types/report'

export interface ReportResourceState<T> {
  data: T | null
  meta: ReportMeta | null
  loading: boolean
  error: string | null
  refresh: () => void
  updatedAt: Date | null
}

/**
 * Generic fetch hook for report sections. `queryKey` changes whenever the
 * loader's inputs change; `refresh()` forces one retry without changing the
 * inputs. Previous data stays on screen while reloading, and `meta` carries
 * the report envelope (filters echo + pagination for table endpoints).
 */
export function useReportResource<T>(
  loader: () => Promise<ReportResponse<T>>,
  queryKey: string,
  fallbackError = 'Unable to load report data.',
): ReportResourceState<T> {
  const [data, setData] = useState<T | null>(null)
  const [meta, setMeta] = useState<ReportMeta | null>(null)
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

export interface ReportTableState<T> extends ReportResourceState<T[]> {
  page: number
  setPage: (page: number) => void
  search: string
  setSearch: (search: string) => void
  applySearch: () => void
  pagination: PaginationMeta | null
}

/**
 * Paginated, searchable table state for the report tables. Holds page +
 * search locally while the parent supplies the report scope; bumping
 * `refreshVersion` re-fetches the current snapshot.
 */
export function useReportTable<T>(
  load: (params: ReportFilters) => Promise<ReportResponse<T[]>>,
  scope: ReportFilters,
  refreshVersion: number,
  fallbackError = 'Unable to load report data.',
  extra: ReportFilters = {},
): ReportTableState<T> {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [appliedSearch, setAppliedSearch] = useState('')

  const params = useMemo<ReportFilters>(
    () => ({
      ...scope,
      ...extra,
      page,
      per_page: 10,
      search: appliedSearch.trim() || undefined,
    }),
    [scope, extra, page, appliedSearch],
  )

  const state = useReportResource<T[]>(
    useCallback(() => load(params), [load, params]),
    `${refreshVersion}|${JSON.stringify(params)}`,
    fallbackError,
  )

  const pagination = state.meta?.pagination ?? null

  const applySearch = useCallback(() => {
    setPage(1)
    setAppliedSearch(search)
  }, [search])

  return { ...state, page, setPage, search, setSearch, applySearch, pagination }
}

export interface ReportExportState {
  exporting: boolean
  exportError: string | null
  handleExport: (report: ReportExportType, format: ExportFormat, filters: ReportFilters) => void
}

/**
 * Shared report export flow: streams the authenticated file, surfaces rate
 * limit / permission errors as a dismissible message.
 */
export function useReportExport(fallbackError = 'The report export failed.'): ReportExportState {
  const [exporting, setExporting] = useState(false)
  const [exportError, setExportError] = useState<string | null>(null)

  const handleExport = useCallback(
    (report: ReportExportType, format: ExportFormat, filters: ReportFilters) => {
      setExporting(true)
      setExportError(null)
      void downloadReportExport(report, format, filters)
        .catch((cause: unknown) => {
          setExportError(getApiErrorMessage(cause, fallbackError))
        })
        .finally(() => setExporting(false))
    },
    [fallbackError],
  )

  return { exporting, exportError, handleExport }
}