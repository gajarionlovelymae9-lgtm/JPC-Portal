import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import EmployeeCreateReportPage from './EmployeeCreateReportPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

function stubApi() {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/employee/tasks')) {
        return Promise.resolve(
          json({
            success: true,
            message: 'ok',
            data: {
              tasks: [{ id: 12, task_id: 34, title: 'Q3 Performance Report', status: 'assigned' }],
              meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
              summary: {
                total_tasks: 1,
                completed_tasks: 0,
                pending_tasks: 1,
                in_progress_tasks: 0,
                submitted_tasks: 0,
                overdue_tasks: 0,
                completion_rate: 0,
              },
            },
          }),
        )
      }
      if (url.includes('/employee/reports') && url.includes('/submit')) {
        return Promise.resolve(json({ success: true, message: 'Submitted', data: { report: { id: 99, status: 'submitted' } } }))
      }
      if (url.includes('/employee/reports')) {
        return Promise.resolve(
          json({ success: true, message: 'Draft saved', data: { report: { id: 99, status: 'draft' } } }),
        )
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

function renderCreatePage() {
  return render(
    <MemoryRouter initialEntries={['/employee/reports/create']}>
      <Routes>
        <Route path="/employee/reports/create" element={<EmployeeCreateReportPage />} />
        <Route path="/employee/reports" element={<div>EMPLOYEE REPORTS LIST</div>} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('EmployeeCreateReportPage (GCS §38 report submission)', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('loads the employee tasks into the task selector', async () => {
    stubApi()
    renderCreatePage()

    const select = screen.getByRole('combobox')
    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Q3 Performance Report' })).toBeInTheDocument()
    })
    expect(select).toHaveValue('')
    expect(screen.getByRole('option', { name: 'Q3 Performance Report' })).toHaveAttribute('value', '12')
  })

  it('blocks saving a draft without a task and title', async () => {
    stubApi()
    const user = userEvent.setup()
    renderCreatePage()

    await screen.findByRole('combobox')
    await user.click(screen.getByRole('button', { name: 'Save Draft' }))

    expect(await screen.findByText('Task and title are required.')).toBeInTheDocument()
  })

  it('saves a draft for a selected task and then submits it through the confirmation dialog', async () => {
    stubApi()
    const user = userEvent.setup()
    renderCreatePage()

    const taskSelect = await screen.findByRole('combobox')
    await user.selectOptions(taskSelect, '12')
    await user.type(screen.getByPlaceholderText('Enter report title...'), 'Q3 Performance Report')
    await user.click(screen.getByRole('button', { name: 'Save Draft' }))

    expect(await screen.findByText(/Draft saved \(Report #99\)/)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Submit Report' }))
    expect(await screen.findByRole('heading', { name: 'Submit Report?' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Yes, Submit' }))

    expect(await screen.findByText('EMPLOYEE REPORTS LIST')).toBeInTheDocument()
  })

  it('keeps the draft when the submission confirmation is cancelled', async () => {
    stubApi()
    const user = userEvent.setup()
    renderCreatePage()

    const taskSelect = await screen.findByRole('combobox')
    await user.selectOptions(taskSelect, '12')
    await user.type(screen.getByPlaceholderText('Enter report title...'), 'Q3 Performance Report')
    await user.click(screen.getByRole('button', { name: 'Save Draft' }))
    await screen.findByText(/Draft saved/)

    await user.click(screen.getByRole('button', { name: 'Submit Report' }))
    await user.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.queryByRole('heading', { name: 'Submit Report?' })).not.toBeInTheDocument()
    expect(screen.getByText(/Draft saved \(Report #99\)/)).toBeInTheDocument()
  })
})