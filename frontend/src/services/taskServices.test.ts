import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  assignEmployee,
  submitAssignment,
  updateAssignmentProgress,
} from './taskAssignmentService'
import { getMyTasks, getTaskSummary, listTasks } from './taskService'

function mockFetch() {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({ success: true, message: 'ok', data: [] }),
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

function calledUrl(fetchMock: ReturnType<typeof vi.fn>): string {
  return String(fetchMock.mock.calls[0][0])
}

describe('taskService', () => {
  beforeEach(() => {
    vi.unstubAllGlobals()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('builds a filtered query string for the task list', async () => {
    const fetchMock = mockFetch()

    await listTasks({
      status: 'in_progress',
      priority: 'high',
      search: 'report',
      page: 2,
      per_page: 10,
      sort: 'due_date',
      direction: 'desc',
    })

    const url = calledUrl(fetchMock)
    expect(url).toContain('/tasks?')
    expect(url).toContain('status=in_progress')
    expect(url).toContain('priority=high')
    expect(url).toContain('search=report')
    expect(url).toContain('page=2')
    expect(url).toContain('sort=due_date')
    expect(url).toContain('direction=desc')
  })

  it('omits empty query parameters', async () => {
    const fetchMock = mockFetch()

    await listTasks({ status: '', priority: undefined, search: '' })

    expect(calledUrl(fetchMock).endsWith('/tasks')).toBe(true)
  })

  it('targets the summary and my-tasks endpoints', async () => {
    const fetchMock = mockFetch()

    await getTaskSummary()
    await getMyTasks()

    expect(String(fetchMock.mock.calls[0][0])).toContain('/tasks/summary')
    expect(String(fetchMock.mock.calls[1][0])).toContain('/tasks/me')
  })
})

describe('taskAssignmentService', () => {
  beforeEach(() => {
    vi.unstubAllGlobals()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('posts assignment actions to the correct endpoints', async () => {
    const fetchMock = mockFetch()

    await submitAssignment(7, 'Done')
    await updateAssignmentProgress(7, { percentage: 50, comment: 'Halfway' })
    await assignEmployee(3, { employee_id: 5, due_date: '2026-10-01' })

    expect(String(fetchMock.mock.calls[0][0])).toContain('/task-assignments/7/submit')
    expect(String(fetchMock.mock.calls[1][0])).toContain('/task-assignments/7/progress')
    expect(String(fetchMock.mock.calls[2][0])).toContain('/tasks/3/assignments')

    expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'POST' })
    expect(fetchMock.mock.calls[2][1]).toMatchObject({ method: 'POST' })
  })
})
