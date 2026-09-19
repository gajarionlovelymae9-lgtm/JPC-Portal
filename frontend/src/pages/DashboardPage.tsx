import { useHealthStatus } from '../hooks/useHealthStatus'
import { SystemStatus } from '../components/SystemStatus'

export default function DashboardPage() {
  const { state, retry } = useHealthStatus()

  return (
    <div className="page">
      <div className="page__intro">
        <h2>Welcome to JPC Portal</h2>
        <p>
          AI-driven employee progress monitoring for John Paul College. This
          foundation release verifies the platform architecture end to end.
        </p>
      </div>

      <SystemStatus state={state} onRetry={retry} />

      <section className="card">
        <h3>Platform architecture</h3>
        <ul className="stack-list">
          <li>React Frontend</li>
          <li>REST API</li>
          <li>Laravel 12 Backend</li>
          <li>MySQL &rarr; acadtrack_db</li>
        </ul>
      </section>
    </div>
  )
}