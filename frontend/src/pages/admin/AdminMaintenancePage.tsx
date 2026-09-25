import { useCallback, useEffect, useState } from 'react'
import {
  getMaintenanceLogs,
  recordMaintenanceLog,
  type MaintenanceLogItem,
} from '../../services/systemMaintenanceService'

const RESULT_COLOR: Record<string, string> = {
  completed: 'bg-emerald-100 text-emerald-700',
  failed: 'bg-red-100 text-red-700',
  pending: 'bg-amber-100 text-amber-700',
  rolled_back: 'bg-gray-200 text-gray-700',
}

export default function MaintenanceLogPage() {
  const [logs, setLogs] = useState<MaintenanceLogItem[]>([])
  const [filter, setFilter] = useState('')
  const [error, setError] = useState('')
  const [form, setForm] = useState({
    maintenance_type: '',
    affected_module: '',
    description: '',
    result: 'completed',
    rollback_required: false,
    notes: '',
  })
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    try {
      const res = await getMaintenanceLogs(filter ? { type: filter } : {})
      setLogs(res.data.data)
      setError('')
    } catch {
      setError('Unable to load maintenance logs.')
    }
  }, [filter])

  useEffect(() => {
    void load()
  }, [load])

  const handleSubmit = async () => {
    setSaving(true)
    setError('')
    try {
      await recordMaintenanceLog(form)
      setForm({ maintenance_type: '', affected_module: '', description: '', result: 'completed', rollback_required: false, notes: '' })
      await load()
    } catch {
      setError('Unable to record the maintenance action. Please fill the required fields.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Maintenance Log</h1>
        <p className="text-sm text-gray-500 mt-0.5">Every maintenance action is recorded and auditable.</p>
      </div>

      {error && <div className="bg-red-50 border border-red-200 text-red-700 text-sm p-4 rounded-2xl">{error}</div>}

      <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
        <h2 className="text-lg font-bold text-gray-900">Record Maintenance Action</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <input
            value={form.maintenance_type}
            onChange={(e) => setForm({ ...form, maintenance_type: e.target.value })}
            placeholder="Maintenance type (e.g. Database Backup)"
            className="border border-gray-300 rounded-2xl px-4 py-2.5 text-sm"
          />
          <input
            value={form.affected_module}
            onChange={(e) => setForm({ ...form, affected_module: e.target.value })}
            placeholder="Affected module (optional)"
            className="border border-gray-300 rounded-2xl px-4 py-2.5 text-sm"
          />
        </div>
        <textarea
          value={form.description}
          onChange={(e) => setForm({ ...form, description: e.target.value })}
          placeholder="Describe what was performed"
          rows={3}
          className="w-full border border-gray-300 rounded-2xl px-4 py-2.5 text-sm"
        />
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <select
            value={form.result}
            onChange={(e) => setForm({ ...form, result: e.target.value })}
            className="border border-gray-300 rounded-2xl px-4 py-2.5 text-sm bg-white"
          >
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
            <option value="pending">Pending</option>
            <option value="rolled_back">Rolled Back</option>
          </select>
          <label className="flex items-center gap-2 text-sm text-gray-700">
            <input
              type="checkbox"
              checked={form.rollback_required}
              onChange={(e) => setForm({ ...form, rollback_required: e.target.checked })}
            />
            Rollback required
          </label>
        </div>
        <input
          value={form.notes}
          onChange={(e) => setForm({ ...form, notes: e.target.value })}
          placeholder="Notes (optional)"
          className="w-full border border-gray-300 rounded-2xl px-4 py-2.5 text-sm"
        />
        <button
          onClick={() => void handleSubmit()}
          disabled={saving || !form.maintenance_type || !form.description}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-semibold rounded-2xl"
        >
          {saving ? 'Recording…' : 'Record Action'}
        </button>
      </section>

      <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-gray-900">Recent Records</h2>
          <select
            value={filter}
            onChange={(e) => setFilter(e.target.value)}
            className="border border-gray-300 rounded-xl px-3 py-1.5 text-sm bg-white"
          >
            <option value="">All types</option>
            <option value="Database Backup">Database Backup</option>
            <option value="Database Restore">Database Restore</option>
            <option value="Security Patch">Security Patch</option>
            <option value="Incident Response">Incident Response</option>
            <option value="File Storage">File Storage</option>
            <option value="AI Maintenance">AI Maintenance</option>
            <option value="OCR Maintenance">OCR Maintenance</option>
          </select>
        </div>

        {logs.length === 0 ? (
          <p className="text-sm text-gray-500">No maintenance records yet.</p>
        ) : (
          <ul className="space-y-3">
            {logs.map((log) => (
              <li key={log.id} className="border border-gray-100 rounded-2xl p-4 text-sm">
                <div className="flex items-center justify-between">
                  <p className="font-semibold text-gray-900">
                    {log.maintenance_type}
                    {log.affected_module ? <span className="text-gray-400 font-normal"> — {log.affected_module}</span> : null}
                  </p>
                  <span className={`px-2 py-0.5 rounded-full text-xs font-bold ${RESULT_COLOR[log.result] ?? 'bg-gray-100 text-gray-600'}`}>
                    {log.result}
                  </span>
                </div>
                <p className="text-gray-600 mt-1">{log.description}</p>
                <p className="text-xs text-gray-400 mt-2">
                  {new Date(log.performed_at).toLocaleDateString()}
                  {log.version ? ` · v${log.version}` : ''}
                  {log.rollback_required ? ' · rollback required' : ''}
                  {log.performer ? ` · ${log.performer.name}` : ''}
                </p>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}