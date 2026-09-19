import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ConfirmDialog } from './ConfirmDialog'

describe('ConfirmDialog', () => {
  it('renders nothing when closed', () => {
    render(
      <ConfirmDialog
        open={false}
        title="Submit for review"
        message="Are you sure?"
        onConfirm={() => {}}
        onCancel={() => {}}
      />,
    )

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('renders an accessible dialog and confirms', () => {
    const onConfirm = vi.fn()

    render(
      <ConfirmDialog
        open
        title="Submit for review"
        message="Are you sure you want to submit this task for review?"
        confirmLabel="Submit"
        onConfirm={onConfirm}
        onCancel={() => {}}
      />,
    )

    expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true')
    expect(screen.getByText('Are you sure you want to submit this task for review?')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Submit' }))
    expect(onConfirm).toHaveBeenCalledTimes(1)
  })

  it('cancels on Escape and on backdrop click', () => {
    const onCancel = vi.fn()

    render(
      <ConfirmDialog
        open
        title="Cancel task"
        message="This cannot be undone."
        onConfirm={() => {}}
        onCancel={onCancel}
      />,
    )

    fireEvent.keyDown(document, { key: 'Escape' })
    expect(onCancel).toHaveBeenCalledTimes(1)

    fireEvent.click(screen.getByRole('presentation'))
    expect(onCancel).toHaveBeenCalledTimes(2)
  })
})
