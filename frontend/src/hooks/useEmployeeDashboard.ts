import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '../services/api'
import {
  getEmployeeDashboard,
  type EmployeeDashboard,
} from '../services/employeePortalService'

export type EmployeeDashboardStatus = 'loading' | 'success' | 'error'

export interface EmployeeDashboardState {
  status: EmployeeDashboardStatus
  data: EmployeeDashboard | null
  error: string | null
}

export interface EmployeeDashboardFilters {
  year?: number
  month?: number
}

/**
 * Loads the authenticated employee's dashboard payload.
 *
 * Guards against setting state after unmount and keeps the previous payload
 * visible while refetching a different month.
 */
export function useEmployeeDashboard(filters: EmployeeDashboardFilters = {}): {
  state: EmployeeDashboardState
  retry: () => void
} {
  const { year, month } = filters
  const [state, setState] = useState<EmployeeDashboardState>({
    status: 'loading',
    data: null,
    error: null,
  })
  const [attempt, setAttempt] = useState(0)

  const load = useCallback(async (): Promise<EmployeeDashboardState> => {
    try {
      const response = await getEmployeeDashboard({ year, month })

      if (response?.success && response.data) {
        return { status: 'success', data: response.data, error: null }
      }

      return {
        status: 'error',
        data: null,
        error: response?.message ?? 'Unable to load your dashboard.',
      }
    } catch (error) {
      return {
        status: 'error',
        data: null,
        error:
          error instanceof ApiError
            ? error.message
            : 'Unable to load your dashboard. Please try again.',
      }
    }
  }, [year, month])

  useEffect(() => {
    let ignore = false

    // Keep existing data on screen while a new month is loading.
    setState(prev => ({ ...prev, status: 'loading', error: null }))

    load().then(result => {
      if (!ignore) {
        setState(result)
      }
    })

    return () => {
      ignore = true
    }
  }, [load, attempt])

  const retry = useCallback(() => {
    setAttempt(prev => prev + 1)
  }, [])

  return { state, retry }
}
