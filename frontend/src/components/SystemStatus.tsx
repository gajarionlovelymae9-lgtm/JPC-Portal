import { StatusDot } from './StatusDot'
import type { HealthState } from '../hooks/useHealthStatus'

interface SystemStatusProps {
  state: HealthState
  onRetry: () => void
}

/**
 * Development/test panel that communicates the live backend status:
 * Backend, Database and API connectivity as reported by GET /api/v1/health.
 */
export function SystemStatus({ state, onRetry }: SystemStatusProps) {
  if (state.status === 'loading') {
    return (
      <section className="system-status" aria-label="System status" aria-busy="true">
        <h2>System Status</h2>
        <p className="system-status__hint">Checking backend…</p>
        <StatusDot kind="loading" label="Checking…" />
      </section>
    )
  }

  if (state.status === 'error' || !state.health) {
    return (
      <section className="system-status system-status--error" aria-label="System status">
        <h2>System Status</h2>
        <p className="system-status__hint">
          The JPC Portal API could not be reached.
        </p>
        <StatusDot kind="error" label="Backend: Disconnected" />
        <p className="system-status__error" role="alert">
          {state.error ?? 'Backend is unavailable'}
        </p>
        <button type="button" className="button button--secondary" onClick={onRetry}>
          Retry
        </button>
      </section>
    )
  }

  const health = state.health

  return (
    <section className="system-status" aria-label="System status">
      <h2>System Status</h2>
      <div className="system-status__grid">
        <StatusDot kind={health.api === 'ok' ? 'ok' : 'error'} label="API: Connected" />
        <StatusDot
          kind={health.database === 'ok' ? 'ok' : 'error'}
          label={health.database === 'ok' ? 'Database: Connected' : 'Database: Error'}
        />
        <StatusDot
          kind={health.application === 'ok' ? 'ok' : 'error'}
          label="Backend: Connected"
        />
      </div>
      <p className="system-status__meta">
        Environment: {health.environment} &middot; API v{health.api_version}
      </p>
    </section>
  )
}