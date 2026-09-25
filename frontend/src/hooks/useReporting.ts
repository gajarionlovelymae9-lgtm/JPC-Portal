import { useCallback, useEffect, useRef, useState } from 'react'
import { getApiErrorMessage } from '../utils/apiError'

export interface ReportingResourceState<T> {
  data: T | null
  loading: boolean
  error: string | null
  refresh: () => void
  updatedAt: Date | null
}

/**
 * Generic fetch hook for the Phase 9 reporting endpoints. `queryKey` changes
 * whenever the loader's inputs change; `refresh()` forces one retry without
 * changing the inputs. Previous data stays on screen while reloading.
 */
export function useReportingResource<T>(
  loader: () => Promise<{ data: T }>,
  queryKey: string,
  fallbackError = 'Unable to load report data.',
): ReportingResourceState<T> {
  const [data, setData] = useState<T | null>(null)
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

  return { data, loading, error, refresh, updatedAt }
}

export interface ReportingPeriodState {
  year: number
  month: number
  setYear: (year: number) => void
  setMonth: (month: number) => void
  /** Monotonic counter bumped when the period changes, for effect keys. */
  refreshVersion: number
  periodLabel: string
}

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

function nowParts(date = new Date()): { year: number; month: number } {
  return { year: date.getFullYear(), month: date.getMonth() + 1 }
}

/**
 * Year/month selector state shared by the monthly-style reports. Defaults to
 * the current period and bumps `refreshVersion` on every change.
 */
export function useReportingPeriod(): ReportingPeriodState {
  const [parts, setParts] = useState(() => nowParts())
  const [refreshVersion, setRefreshVersion] = useState(0)

  const setYear = useCallback((year: number) => {
    setParts((prev) => ({ ...prev, year }))
    setRefreshVersion((v) => v + 1)
  }, [])

  const setMonth = useCallback((month: number) => {
    setParts((prev) => ({ ...prev, month }))
    setRefreshVersion((v) => v + 1)
  }, [])

  const periodLabel = `${MONTH_NAMES[parts.month - 1]} ${parts.year}`

  return { ...parts, setYear, setMonth, refreshVersion, periodLabel }
}

export { MONTH_NAMES }