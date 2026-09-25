import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  getSystemHealth,
  getSystemHealthDashboard,
  getSystemHealthHistory,
  type HealthHistoryEntry,
  type SystemHealthDashboard,
  type SystemHealthData,
} from '../../services/systemMaintenanceService'

const STATUS_COLOR: Record<string, string> = {
  Healthy: 'bg-emerald-100 text-emerald-700',
  Degraded: 'bg-amber-100 text-amber-700',
  Error: 'bg-red-100 text-red-700',
}

const COMPONENT_LABELS: Record<string, string> = {
  application: 'Application',
  database: 'Database',
  queue: 'Queue',
  storage: 'Storage',
  ai_provider: 'AI Provider',
  ocr_provider: 'OCR Provider',
  notifications: 'Notifications',
  last_backup: 'Last Backup',
}

export default function SystemHealthPage() {
  const [health, setHealth] = useState<SystemHealthData | null>(null)
  const [dashboard, setDashboard] = useState<SystemHealthDashboard | null>(null)
  const [history, setHistory] = useState<HealthHistoryEntry[]>([])
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    try {
      const [healthRes, dashboardRes, historyRes] = await Promise.all([
        getSystemHealth(),
        getSystemHealthDashboard(),
        getSystemHealthHistory(),
      ])
      setHealth(healthRes.data)
      setDashboard(dashboardRes.data)
      setHistory(historyRes.data)
      setError('')
    } catch {
      setError('Unable to load system health. Please verify the backend is running.')
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">System Health Monitor</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Application, database, queue, storage, AI and OCR provider status — refreshed at check time.
          </p>
        </div>
        <button
          onClick={() => void load()}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-2xl"
        >
          Run Health Check
        </button>
      </div>

      {error && <div className="bg-red-50 border border-red-200 text-red-700 text-sm p-4 rounded-2xl">{error}</div>}

      <div>
        <h2 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Component Status</h2>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {Object.entries(COMPONENT_LABELS).map(([key, label]) => {
            const status = dashboard?.system_status?.[key] ?? 'Unknown'
            const color = STATUS_COLOR[status] ?? 'bg-gray-100 text-gray-600'
            return (
              <div key={key} className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
                <p className="text-xs text-gray-500">{label}</p>
                <p className={`mt-1 inline-block px-2 py-0.5 rounded-full text-xs font-bold ${color}`}>{status}</p>
              </div>
            )
          })}
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[
          { label: 'Failed Jobs Today', value: dashboard?.counters.failed_jobs_today ?? 0 },
          { label: 'Failed Jobs (Week)', value: dashboard?.counters.failed_jobs_week ?? 0 },
          { label: 'API Errors Today', value: dashboard?.counters.api_errors_today ?? 0 },
          { label: 'AI Failures', value: dashboard?.counters.ai_failures ?? 0 },
          { label: 'OCR Failures', value: dashboard?.counters.ocr_failures ?? 0 },
          { label: 'Security Events Today', value: dashboard?.counters.security_events ?? 0 },
          { label: 'Auth Failures Today', value: dashboard?.counters.auth_failures ?? 0 },
          { label: 'Storage Warnings', value: dashboard?.counters.storage_warnings ?? 0 },
        ].map((counter) => (
          <div key={counter.label} className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
            <p className="text-xs text-gray-500">{counter.label}</p>
            <p className="mt-1 text-2xl font-bold text-gray-900">{counter.value}</p>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
          <h2 className="text-lg font-bold text-gray-900">Recent Critical Errors</h2>
          {!dashboard?.recent_critical_errors?.length ? (
            <p className="text-sm text-gray-500">No critical errors recorded today.</p>
          ) : (
            <ul className="space-y-2">
              {dashboard.recent_critical_errors.map((entry, index) => (
                <li key={index} className="text-xs font-mono bg-red-50 border border-red-100 text-red-800 p-3 rounded-xl break-all">
                  {entry}
                </li>
              ))}
            </ul>
          )}
          <p className="text-xs text-gray-400">
            Metrics are aggregated counts only — log contents and credentials are never exposed.
          </p>
        </section>

        <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
          <h2 className="text-lg font-bold text-gray-900">Check History</h2>
          {history.length === 0 ? (
            <p className="text-sm text-gray-500">No recorded health checks yet. Run a check to populate history.</p>
          ) : (
            <ul className="space-y-2">
              {history.map((row) => (
                <li key={row.id} className="flex items-center justify-between text-sm border border-gray-100 rounded-xl px-3 py-2">
                  <span className="text-gray-700">
                    {new Date(row.checked_at).toLocaleString()} — <span className="text-gray-400">source: {row.source}</span>
                  </span>
                  <span className={`px-2 py-0.5 rounded-full text-xs font-bold ${
                    row.status === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                  }`}>
                    {row.status.toUpperCase()}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>

      <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm">
        <h2 className="text-lg font-bold text-gray-900 mb-3">AI / OCR Component Details</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="border border-gray-100 rounded-2xl p-4 text-sm space-y-1">
            <p className="font-semibold text-gray-900">AI Provider</p>
            <p className="text-gray-500">Provider: {(health?.components.ai.metrics.provider as string) || '—'}</p>
            <p className="text-gray-500">Models: {(health?.components.ai.metrics.models as string) || '—'}</p>
            <p className="text-gray-500">Failure rate: {(health?.components.ai.metrics.failure_rate ?? 0)}%</p>
          </div>
          <div className="border border-gray-100 rounded-2xl p-4 text-sm space-y-1">
            <p className="font-semibold text-gray-900">OCR Provider</p>
            <p className="text-gray-500">Provider: {(health?.components.ocr.metrics.provider as string) || '—'}</p>
            <p className="text-gray-500">Low confidence: {(health?.components.ocr.metrics.low_confidence ?? 0)}</p>
            <p className="text-gray-500">Failure rate: {(health?.components.ocr.metrics.failure_rate ?? 0)}%</p>
          </div>
        </div>
        <p className="text-xs text-gray-400 mt-3">
          Deep-dive AI & OCR job monitoring is available in the{' '}
          <Link to="/admin/ai" className="text-blue-600 hover:underline font-semibold">AI & OCR Monitor</Link>.
        </p>
      </section>
    </div>
  )
}