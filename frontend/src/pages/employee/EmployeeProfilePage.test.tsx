import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import EmployeeProfilePage from './EmployeeProfilePage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const profile = {
  name: 'Juan Dela Cruz',
  email: 'juan.delacruz@jpc.edu.ph',
  employee_no: '2025-0001',
  department: 'College of Engineering',
  position: 'Instructor',
  employee_type: 'teaching',
  status: 'active',
}

function stubApi(updateStatus = 200) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL, requestInit: RequestInit | undefined) => {
      const url = String(input)
      if (url.includes('/employee/profile')) {
        const method = requestInit?.method ?? 'GET'
        if (method === 'PUT') {
          return Promise.resolve(
            updateStatus === 200
              ? json({ success: true, message: 'Updated', data: { ...profile, email: 'new@jpc.edu.ph' } })
              : json({ success: false, message: 'Failed to update profile.', errors: null }, updateStatus),
          )
        }
        return Promise.resolve(json({ success: true, message: 'ok', data: profile }))
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

describe('EmployeeProfilePage (GCS §38 profile)', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('renders the employee profile details from the backend', async () => {
    stubApi()
    render(<EmployeeProfilePage />)

    expect(await screen.findByRole('heading', { name: 'My Profile' })).toBeInTheDocument()
    expect(screen.getByText('Juan Dela Cruz')).toBeInTheDocument()
    expect(screen.getByText('2025-0001')).toBeInTheDocument()
    expect(screen.getByText('College of Engineering')).toBeInTheDocument()
    expect(screen.getByText('Instructor')).toBeInTheDocument()
    expect(screen.getByRole('textbox')).toHaveValue('juan.delacruz@jpc.edu.ph')
  })

  it('updates the email and shows a success confirmation', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<EmployeeProfilePage />)

    const email = await screen.findByRole('textbox')
    await user.clear(email)
    await user.type(email, 'new@jpc.edu.ph')
    await user.click(screen.getByRole('button', { name: 'Save Changes' }))

    expect(await screen.findByText('✓ Profile updated successfully.')).toBeInTheDocument()
  })

  it('surfaces an error when the profile update fails', async () => {
    stubApi(422)
    const user = userEvent.setup()
    render(<EmployeeProfilePage />)

    const email = await screen.findByRole('textbox')
    await user.clear(email)
    await user.type(email, 'bad-email')
    await user.click(screen.getByRole('button', { name: 'Save Changes' }))

    expect(await screen.findByText('Failed to update profile.')).toBeInTheDocument()
  })

  it('shows a load-error state when the profile cannot be fetched', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(json({ success: false, message: 'fail', errors: null }, 500)),
    )
    render(<EmployeeProfilePage />)

    expect(await screen.findByText('Unable to load profile.')).toBeInTheDocument()
  })
})