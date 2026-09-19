import { useCallback, useEffect, useState } from 'react'
import { fetchHealth } from '../services/health'
import type { HealthData } from '../types/api'
import { ApiError } from '../services/api'

export type ConnectionStatus = 'loading' | 'connected' | 'error'

export interface HealthState {
  status: ConnectionStatus
  health: HealthData | null
  error: string | null
}

/**
 * Loads and tracks the backend health status, exposed as
 * { status: 'loading' | 'connected' | 'error', health, error }.
 */
export function useHealthStatus(): { state: HealthState; retry: () => void } {
  const [state, setState] = useState<HealthState>({
    status: 'loading',
    health: null,
    error: null,
  })

  const loadHealth = useCallback(async (): Promise<HealthState> => {
    try {
      const response = await fetchHealth()
      if (response?.success && response?.data) {
        return { status: 'connected', health: response.data, error: null }
      }

      return {
        status: 'error',
        health: null,
        error: response?.message ?? 'Unexpected health response',
      }
    } catch (error) {
      const message =
        error instanceof ApiError ? error.message : 'Backend is unreachable'

      return { status: 'error', health: null, error: message }
    }
  }, [])

  useEffect(() => {
    let ignore = false

    loadHealth().then((result) => {
      if (!ignore) {
        setState(result)
      }
    })

    return () => {
      ignore = true
    }
  }, [loadHealth])

  const retry = useCallback(() => {
    setState((prev) => ({ ...prev, status: 'loading', error: null }))
    void loadHealth().then(setState)
  }, [loadHealth])

  return { state, retry }
}