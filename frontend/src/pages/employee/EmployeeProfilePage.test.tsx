import { fireEvent, render, screen } from '@testing-library/react'
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
  first_name: 'Juan',
  middle_name: 'Ramos',
  last_name: 'Dela Cruz',
  suffix: null,
  email: 'juan.delacruz@jpc.edu.ph',
  employee_no: '2025-0001',
  department: 'College of Engineering',
  position: 'Instructor',
  employee_type: 'teaching',
  status: 'active',
  phone: '0917 000 0000',
  address: '123 Main St, Legazpi City',
  date_of_birth: '1990-05-15T00:00:00.000000Z',
  date_hired: '2020-06-01T00:00:00.000000Z',
  username: 'juan.delacruz',
  updated_at: '2026-01-10T08:00:00.000000Z',
  profile_photo_url: null,
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
    // The name and position appear in both the header and the info sections.
    expect(screen.getAllByText('Juan Dela Cruz').length).toBeGreaterThan(0)
    expect(screen.getByText('2025-0001')).toBeInTheDocument()
    expect(screen.getByText('College of Engineering')).toBeInTheDocument()
    expect(screen.getAllByText('Instructor').length).toBeGreaterThan(0)
    expect(screen.getByLabelText('Username')).toHaveValue('juan.delacruz')
    expect(screen.getByLabelText('First Name')).toHaveValue('Juan')
    expect(screen.getByLabelText('Email Address')).toHaveValue('juan.delacruz@jpc.edu.ph')
  })

  it('updates the name and shows a success confirmation', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<EmployeeProfilePage />)

    const firstName = await screen.findByLabelText('First Name')
    await user.clear(firstName)
    await user.type(firstName, 'Maria')
    await user.click(screen.getByRole('button', { name: 'Save Changes' }))

    expect(await screen.findByText('✓ Profile updated successfully.')).toBeInTheDocument()
  })

  it('uploads a new profile photo and shows a success confirmation', async () => {
    stubApi()
    render(<EmployeeProfilePage />)

    const input = await screen.findByLabelText('Change photo')
    const file = new File(['photo'], 'me.png', { type: 'image/png' })
    fireEvent.change(input, { target: { files: [file] } })

    expect(await screen.findByText('✓ Profile updated successfully.')).toBeInTheDocument()
  })

  it('updates the email and shows a success confirmation', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<EmployeeProfilePage />)

    const email = await screen.findByLabelText('Email Address')
    await user.clear(email)
    await user.type(email, 'new@jpc.edu.ph')
    await user.click(screen.getByRole('button', { name: 'Save Changes' }))

    expect(await screen.findByText('✓ Profile updated successfully.')).toBeInTheDocument()
  })

  it('restores the original values when Cancel is clicked', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<EmployeeProfilePage />)

    const email = await screen.findByLabelText('Email Address')
    await user.clear(email)
    await user.type(email, 'changed@jpc.edu.ph')
    await user.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(email).toHaveValue('juan.delacruz@jpc.edu.ph')
  })

  it('surfaces an error when the profile update fails', async () => {
    stubApi(422)
    const user = userEvent.setup()
    render(<EmployeeProfilePage />)

    const email = await screen.findByLabelText('Email Address')
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
