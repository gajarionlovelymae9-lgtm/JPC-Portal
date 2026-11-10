import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  assignEmployee,
  downloadTaskAttachment,
  getAssignmentSubmissions,
  resubmitAssignment,
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

    await submitAssignment(7, {
      method: 'written',
      writtenResponse: 'Done',
      notes: 'All complete',
    })
    await updateAssignmentProgress(7, { percentage: 50, comment: 'Halfway' })
    await assignEmployee(3, { employee_id: 5, due_date: '2026-10-01' })

    expect(String(fetchMock.mock.calls[0][0])).toContain('/task-assignments/7/submit')
    expect(String(fetchMock.mock.calls[1][0])).toContain('/task-assignments/7/progress')
    expect(String(fetchMock.mock.calls[2][0])).toContain('/tasks/3/assignments')

    expect(fetchMock.mock.calls[0][1]).toMatchObject({ method: 'POST' })
    expect(fetchMock.mock.calls[2][1]).toMatchObject({ method: 'POST' })

    const body = fetchMock.mock.calls[0][1]?.body as FormData
    expect(body).toBeInstanceOf(FormData)
    expect(body.get('method')).toBe('written')
    expect(body.get('written_response')).toBe('Done')
    expect(body.get('notes')).toBe('All complete')
  })

  it('sends multiple files for upload and folder submissions', async () => {
    const fetchMock = mockFetch()
    const first = new File(['one'], 'report.pdf', { type: 'application/pdf' })
    const second = new File(['two'], 'bundle.zip', { type: 'application/zip' })

    await submitAssignment(5, { method: 'upload', files: [first, second] })
    await resubmitAssignment(5, { method: 'folder', url: 'https://1drv.ms/f/s!abc' })

    const uploadBody = fetchMock.mock.calls[0][1]?.body as FormData
    expect(uploadBody).toBeInstanceOf(FormData)
    expect(uploadBody.get('method')).toBe('upload')
    expect(uploadBody.getAll('files[]')).toHaveLength(2)

    const folder = fetchMock.mock.calls[1][1]?.body as FormData
    expect(String(fetchMock.mock.calls[1][0])).toContain('/task-assignments/5/resubmit')
    expect(folder.get('method')).toBe('folder')
    expect(folder.get('url')).toBe('https://1drv.ms/f/s!abc')

    const uploadInit = fetchMock.mock.calls[0][1] as RequestInit
    const uploadHeaders = (uploadInit.headers ?? {}) as Record<string, string>
    expect(uploadHeaders['Content-Type']).toBeUndefined()
  })

  it('loads submission history and downloads attachments through the API', async () => {
    const fetchMock = mockFetch()

    await getAssignmentSubmissions(7)
    expect(String(fetchMock.mock.calls[0][0])).toContain('/task-assignments/7/submissions')

    const downloadMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      blob: async () => new Blob(['file']),
      headers: {
        get: (name: string) =>
          name.toLowerCase() === 'content-disposition'
            ? 'attachment; filename="evidence.pdf"'
            : null,
      },
    })
    vi.stubGlobal('fetch', downloadMock)
    vi.stubGlobal('URL', {
      createObjectURL: () => 'blob:mock',
      revokeObjectURL: () => undefined,
    })

    await downloadTaskAttachment(11, 'fallback.pdf')

    expect(String(downloadMock.mock.calls[0][0])).toContain('/task-attachments/11/download')
    expect(downloadMock.mock.calls[0][1]).toMatchObject({ method: 'GET' })
  })
})
