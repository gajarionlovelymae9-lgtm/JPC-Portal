import { useCallback, useEffect, useState } from 'react'
import { listActivityLogs } from '../services/notificationService'
import { getApiErrorMessage } from '../utils/apiError'
import type { PaginationMeta } from '../types/api'
import type { ActivityLogFilter, ActivityLogItem } from '../types/notification'

export interface ActivityLogsState {
  logs: ActivityLogItem[]
  meta: PaginationMeta | null
  loading: boolean
  error: string | null
  refresh: () => void
}

/** Paginated append-only activity timeline behind the `activity_logs.view` gate. */
export function useActivityLogs(params: ActivityLogFilter = {}): ActivityLogsState {
  const queryKey = JSON.stringify(params)
  const [logs, setLogs] = useState<ActivityLogItem[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    const requestParams = JSON.parse(queryKey) as ActivityLogFilter
    let active = true

    void (async () => {
      await Promise.resolve()
      if (!active) {
        return
      }
      setLoading(true)
      setError(null)

      try {
        const response = await listActivityLogs(requestParams)
        if (active) {
          setLogs(response.data)
          setMeta(response.meta ?? null)
        }
      } catch (error_: unknown) {
        if (active) {
          setError(getApiErrorMessage(error_, 'Unable to load activity logs.'))
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
  }, [queryKey, reloadKey])

  const refresh = useCallback(() => setReloadKey((key) => key + 1), [])

  return { logs, meta, loading, error, refresh }
}