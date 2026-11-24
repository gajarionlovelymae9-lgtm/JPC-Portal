import { useEffect, useState } from 'react'
import {
  getMyPasswordChangeRequest,
  requestPasswordChange,
  type PasswordChangeRequest,
} from '../../services/employeePortalService'

const EMPTY_FORM = {
  current_password: '',
  password: '',
  password_confirmation: '',
  reason: '',
}

export default function EmployeeSecurityPage() {
  const [form, setForm] = useState(EMPTY_FORM)
  const [request, setRequest] = useState<PasswordChangeRequest | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  const loadRequest = async () => {
    try {
      const response = await getMyPasswordChangeRequest()
      setRequest(response.data)
    } catch {
      // A missing status must not block the form; treat it as "no request".
      setRequest(null)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadRequest()
  }, [])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (form.password !== form.password_confirmation) {
      setError('New passwords do not match.')
      return
    }
    if (form.password.length < 8) {
      setError('Password must be at least 8 characters.')
      return
    }
    setSaving(true)
    setError(null)
    setSuccess(false)
    try {
      await requestPasswordChange({
        current_password: form.current_password,
        password: form.password,
        password_confirmation: form.password_confirmation,
        reason: form.reason.trim() || null,
      })
      setSuccess(true)
      setForm(EMPTY_FORM)
      await loadRequest()
    } catch {
      setError('Failed to submit the request. Please check your current password.')
    } finally {
      setSaving(false)
    }
  }

  const pending = request?.status === 'pending'
  const rejected = request?.status === 'rejected'

  return (
    <div className="p-6 max-w-lg mx-auto space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Security</h1>

      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>
      )}
      {success && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700">
          ✓ Request submitted. Your password will change once an administrator approves it.
        </div>
      )}

      {!loading && pending && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 text-amber-800 space-y-1">
          <p className="font-semibold">A password change request is awaiting approval.</p>
          <p className="text-sm">
            You cannot submit another request until an administrator reviews the pending one.
          </p>
        </div>
      )}

      {!loading && rejected && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 space-y-1">
          <p className="font-semibold">Your last password change request was rejected.</p>
          {request?.decision_note ? (
            <p className="text-sm">Reason: {request.decision_note}</p>
          ) : null}
          <p className="text-sm">You may submit a new request below.</p>
        </div>
      )}

      <form onSubmit={handleSubmit} className="bg-white border border-gray-200 rounded-xl p-6 space-y-5">
        <div>
          <h2 className="font-semibold text-gray-800">Request Password Change</h2>
          <p className="text-xs text-gray-500 mt-1">
            For your security, password changes take effect only after an administrator approves
            the request.
          </p>
        </div>

        {(
          [
            ['current_password', 'Current Password'],
            ['password', 'New Password'],
            ['password_confirmation', 'Confirm New Password'],
          ] as const
        ).map(([field, label]) => (
          <div key={field}>
            <label htmlFor={field} className="block text-sm font-semibold text-gray-700 mb-1">
              {label}
            </label>
            <input
              id={field}
              type="password"
              value={form[field]}
              disabled={pending}
              onChange={(e) => setForm((f) => ({ ...f, [field]: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none disabled:bg-gray-50 disabled:text-gray-400"
              required
            />
          </div>
        ))}

        <div>
          <label htmlFor="reason" className="block text-sm font-semibold text-gray-700 mb-1">
            Reason (optional)
          </label>
          <textarea
            id="reason"
            value={form.reason}
            disabled={pending}
            onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
            placeholder="Let the administrator know why you need the change"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none disabled:bg-gray-50 disabled:text-gray-400 min-h-[80px]"
          />
        </div>

        <button
          type="submit"
          disabled={saving || pending}
          className="w-full px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? 'Submitting...' : 'Submit Request'}
        </button>
      </form>
    </div>
  )
}
