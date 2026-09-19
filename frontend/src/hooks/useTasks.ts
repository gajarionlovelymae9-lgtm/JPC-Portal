import { useCallback, useEffect, useState } from 'react'
import { getMyTaskSummary, getMyTasks, getTask, listTasks } from '../services/taskService'
import { getApiErrorMessage } from '../utils/apiError'
import type { PaginationMeta } from '../types/api'
import type { MyTaskSummary, Task, TaskQueryParams } from '../types/task'

function errorMessage(error: unknown, fallback: string): string {
  return getApiErrorMessage(error, fallback)
}

export interface TaskListState {
  tasks: Task[]
  meta: PaginationMeta | null
  loading: boolean
  error: string | null
  refresh: () => void
}

/** Organisation-wide task list for the management view. */
export function useTaskList(params: TaskQueryParams = {}): TaskListState {
  const queryKey = JSON.stringify(params)
  const [tasks, setTasks] = useState<Task[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    const requestParams = JSON.parse(queryKey) as TaskQueryParams
    let active = true

    void (async () => {
      await Promise.resolve()
      if (!active) {
        return
      }
      setLoading(true)
      setError(null)

      try {
        const response = await listTasks(requestParams)
        if (active) {
          setTasks(response.data)
          setMeta(response.meta ?? null)
        }
      } catch (error_: unknown) {
        if (active) {
          setError(errorMessage(error_, 'Unable to load tasks.'))
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

  return { tasks, meta, loading, error, refresh }
}

export interface MyTasksState extends TaskListState {
  summary: MyTaskSummary | null
}

/** Tasks assigned to the authenticated employee, plus their summary counts. */
export function useMyTasks(params: TaskQueryParams = {}): MyTasksState {
  const queryKey = JSON.stringify(params)
  const [tasks, setTasks] = useState<Task[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [summary, setSummary] = useState<MyTaskSummary | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    const requestParams = JSON.parse(queryKey) as TaskQueryParams
    let active = true

    void (async () => {
      await Promise.resolve()
      if (!active) {
        return
      }
      setLoading(true)
      setError(null)

      try {
        const [list, summaryResponse] = await Promise.all([
          getMyTasks(requestParams),
          getMyTaskSummary(),
        ])
        if (active) {
          setTasks(list.data)
          setMeta(list.meta ?? null)
          setSummary(summaryResponse.data)
        }
      } catch (error_: unknown) {
        if (active) {
          setError(errorMessage(error_, 'Unable to load your tasks.'))
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

  return { tasks, meta, summary, loading, error, refresh }
}

/** A single task with its assignments, progress and reviews. */
export function useTask(taskId: number | string | undefined) {
  const [task, setTask] = useState<Task | null>(null)
  const [loading, setLoading] = useState(Boolean(taskId))
  const [error, setError] = useState<string | null>(null)
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    if (!taskId) {
      return
    }

    let active = true

    void (async () => {
      await Promise.resolve()
      if (!active) {
        return
      }
      setLoading(true)
      setError(null)

      try {
        const response = await getTask(taskId)
        if (active) {
          setTask(response.data)
        }
      } catch (error_: unknown) {
        if (active) {
          setError(errorMessage(error_, 'Unable to load this task.'))
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
  }, [taskId, reloadKey])

  const refresh = useCallback(() => setReloadKey((key) => key + 1), [])

  return { task, setTask, loading, error, refresh }
}
