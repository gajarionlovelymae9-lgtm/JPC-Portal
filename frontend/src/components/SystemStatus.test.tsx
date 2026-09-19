import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { SystemStatus } from '../components/SystemStatus'
import type { HealthState } from '../hooks/useHealthStatus'
import type { HealthData } from '../types/api'

const health: HealthData = {
  application: 'ok',
  database: 'ok',
  api: 'ok',
  environment: 'local',
  api_version: 'v1',
  timestamp: '2026-09-14T00:00:00+00:00',
}

describe('SystemStatus', () => {
  it('shows the connected state when the backend responds', () => {
    const state: HealthState = { status: 'connected', health, error: null }

    render(<SystemStatus state={state} onRetry={() => {}} />)

    expect(screen.getByText('Backend: Connected')).toBeInTheDocument()
    expect(screen.getByText('Database: Connected')).toBeInTheDocument()
    expect(screen.getByText('API: Connected')).toBeInTheDocument()
    expect(screen.getByText(/Environment: local/)).toBeInTheDocument()
  })

  it('shows the error state when the backend is unreachable', () => {
    const state: HealthState = {
      status: 'error',
      health: null,
      error: 'Unable to reach the JPC Portal API',
    }

    render(<SystemStatus state={state} onRetry={() => {}} />)

    expect(screen.getByText('Backend: Disconnected')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Unable to reach the JPC Portal API',
    )
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('shows the loading state while checking', () => {
    const state: HealthState = { status: 'loading', health: null, error: null }

    render(<SystemStatus state={state} onRetry={() => {}} />)

    expect(screen.getByText('Checking backend…')).toBeInTheDocument()
    expect(screen.getByText('Checking…')).toBeInTheDocument()
  })

  it('reports a database error when the DB reports an error', () => {
    const degraded: HealthData = { ...health, database: 'error' }
    const state: HealthState = {
      status: 'connected',
      health: degraded,
      error: null,
    }

    render(<SystemStatus state={state} onRetry={() => {}} />)

    expect(screen.getByText('Database: Error')).toBeInTheDocument()
  })
})