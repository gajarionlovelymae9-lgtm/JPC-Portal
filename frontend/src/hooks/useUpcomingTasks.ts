import { useCallback, useEffect, useState } from 'react'
import { getMyUpcomingTasks } from '../services/taskService'
import { getApiErrorMessage } from '../utils/apiError'
import type { UpcomingTask } from '../types/notification'

export interface UpcomingTasksState {
  upcoming: UpcomingTask[]
  loading: boolean
  error: string | null
  refresh: () => void
}

/** Upcoming-deadline assignments from GET /me/tasks/upcoming. */
export function useMyUpcomingTasks(): UpcomingTasksState {
  const [upcoming, setUpcoming] = useState<UpcomingTask[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [reloadKey, setReloadKey] = useState(0)

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
        const response = await getMyUpcomingTasks()
        if (active) {
          setUpcoming(response.data)
        }
      } catch (error_: unknown) {
        if (active) {
          setError(getApiErrorMessage(error_, 'Unable to load upcoming deadlines.'))
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
  }, [reloadKey])

  const refresh = useCallback(() => setReloadKey((key) => key + 1), [])

  return { upcoming, loading, error, refresh }
}