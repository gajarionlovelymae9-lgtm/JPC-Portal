import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import EmployeeReviseReportPage from './EmployeeReviseReportPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const report = {
  id: 7,
  title: 'Q3 Performance Report',
  status: 'revision_requested',
  version: 2,
  is_late: false,
  task_id: 12,
  task_title: 'Q3 Performance Report',
  submitted_at: '2026-08-01T09:00:00.000000Z',
  approved_at: null,
  rejected_at: null,
  deadline: '2026-08-31T00:00:00.000000Z',
  submission_date: '2026-08-01T09:00:00.000000Z',
  content: 'Original content here.',
  references: 'Source A',
  files: [],
  ocr: null,
  ai_summary: null,
  ai_validation: null,
  review_history: [
    { reviewer: 'Head of Department', decision: 'revision_requested', comments: 'Please add more detail on KPIs.', date: '2026-08-03T10:00:00.000000Z' },
  ],
  versions: [{ version: 1, status: 'submitted', created_at: '2026-08-01T09:00:00.000000Z' }],
  version_count: 1,
  ai_status: null,
  advisory_label: 'ok',
  disclaimer: '',
}

function stubApi(submitStatus = 200) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/employee/reports/7') && url.includes('/revision')) {
        return Promise.resolve(
          submitStatus === 200
            ? json({ success: true, message: 'Revision submitted', data: { id: 7, version: 3 } })
            : json({ success: false, message: 'Failed to submit revision.', errors: null }, submitStatus),
        )
      }
      if (url.includes('/employee/reports/7')) {
        return Promise.resolve(json({ success: true, message: 'ok', data: report }))
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

function renderRevisePage() {
  return render(
    <MemoryRouter initialEntries={['/employee/reports/7/revise']}>
      <Routes>
        <Route path="/employee/reports/:reportId/revise" element={<EmployeeReviseReportPage />} />
        <Route path="/employee/reports" element={<div>EMPLOYEE REPORTS LIST</div>} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('EmployeeReviseReportPage (GCS §38 report revision)', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the reviewer feedback alongside the pre-filled fields', async () => {
    stubApi()
    renderRevisePage()

    expect(await screen.findByRole('heading', { name: 'Submit Revision' })).toBeInTheDocument()
    expect(screen.getByText('Reviewer Feedback:')).toBeInTheDocument()
    expect(screen.getByText('Please add more detail on KPIs.')).toBeInTheDocument()
    expect(screen.getByDisplayValue('Q3 Performance Report')).toBeInTheDocument()
    expect(screen.getByDisplayValue('Original content here.')).toBeInTheDocument()
  })

  it('submits the revision and navigates back to the reports list', async () => {
    stubApi()
    const user = userEvent.setup()
    renderRevisePage()

    await screen.findByRole('heading', { name: 'Submit Revision' })
    const notes = screen.getByPlaceholderText('Describe the changes you made...')
    await user.type(notes, 'Added KPI tables')
    await user.click(screen.getByRole('button', { name: 'Submit Revision' }))

    expect(await screen.findByText('EMPLOYEE REPORTS LIST')).toBeInTheDocument()
  })

  it('surfaces an error when the revision request fails', async () => {
    stubApi(500)
    const user = userEvent.setup()
    renderRevisePage()

    await screen.findByRole('heading', { name: 'Submit Revision' })
    await user.click(screen.getByRole('button', { name: 'Submit Revision' }))

    expect(await screen.findByText('Failed to submit revision.')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Submit Revision' })).toBeInTheDocument()
  })

  it('shows a load-error state when the report cannot be fetched', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(json({ success: false, message: 'Not found', errors: null }, 404)),
    )
    renderRevisePage()

    expect(await screen.findByText('Could not load report.')).toBeInTheDocument()
  })
})