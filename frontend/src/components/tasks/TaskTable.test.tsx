import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { TaskTable } from './TaskTable'
import type { Task } from '../../types/task'

const task: Task = {
  id: 11,
  task_code: 'TASK-2026-0011',
  title: 'Submit monthly report',
  description: null,
  priority: 'high',
  status: 'in_progress',
  start_date: null,
  due_date: '2026-09-20T00:00:00+00:00',
  completed_at: null,
  estimated_hours: 4,
  is_overdue: false,
  assignments: [
    {
      id: 21,
      task_id: 11,
      employee_id: 3,
      status: 'in_progress',
      assigned_at: null,
      accepted_at: null,
      due_date: '2026-09-20T00:00:00+00:00',
      completed_at: null,
      completion_percentage: 40,
      remarks: null,
      is_overdue: false,
    },
  ],
}

describe('TaskTable', () => {
  it('renders a row per task linking to its detail page', () => {
    render(
      <MemoryRouter>
        <TaskTable tasks={[task]} detailPath={(id) => `/tasks/${id}`} />
      </MemoryRouter>,
    )

    expect(screen.getByText('Submit monthly report')).toBeInTheDocument()
    expect(screen.getByText('TASK-2026-0011')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Submit monthly report' })).toHaveAttribute(
      'href',
      '/tasks/11',
    )
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '40')
  })

  it('renders an empty state when there are no tasks', () => {
    render(
      <MemoryRouter>
        <TaskTable tasks={[]} detailPath={(id) => `/tasks/${id}`} />
      </MemoryRouter>,
    )

    expect(screen.getByText('No tasks match the current filters.')).toBeInTheDocument()
  })
})
