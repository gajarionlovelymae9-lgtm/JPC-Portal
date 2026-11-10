import { useCallback, useEffect, useState } from 'react'
import type { ApiResponse } from '../types/api'

interface AdminResourceState<T> {
  data: T | null
  loading: boolean
  error: string | null
  /** Re-runs the loader, e.g. after a filter change or a successful mutation. */
  reload: () => void
  setData: (value: T | null) => void
}

function messageFor(error: unknown): string {
  if (error instanceof Error) return error.message
  if (typeof error === 'object' && error !== null && 'message' in error) {
    return String((error as { message: unknown }).message)
  }
  return 'Request failed.'
}

/**
 * Minimal GET-only loader for the admin dashboard pages.
 *
 * `deps` behaves like a useEffect dependency list: change a filter and the
 * resource reloads. The loader is skipped until `deps` is non-empty so pages
 * can render a filter form before the first fetch.
 */
export function useAdminResource<T>(
  loader: () => Promise<ApiResponse<T>>,
  deps: readonly unknown[] = [],
): AdminResourceState<T> {
  const [data, setData] = useState<T | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [nonce, setNonce] = useState(0)

  const reload = useCallback(() => setNonce((value) => value + 1), [])

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)

    loader()
      .then((response) => {
        if (!cancelled) setData(response.data)
      })
      .catch((cause) => {
        if (!cancelled) setError(messageFor(cause))
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, nonce])

  return { data, loading, error, reload, setData }
}
