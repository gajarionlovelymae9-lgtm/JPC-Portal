import { useState } from 'react'
import { changeMyPassword } from '../../services/employeePortalService'

export default function EmployeeSecurityPage() {
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (form.password !== form.password_confirmation) { setError('New passwords do not match.'); return }
    if (form.password.length < 8) { setError('Password must be at least 8 characters.'); return }
    setSaving(true); setError(null); setSuccess(false)
    try { await changeMyPassword(form); setSuccess(true); setForm({ current_password: '', password: '', password_confirmation: '' }) }
    catch { setError('Failed to change password. Please check your current password.') }
    finally { setSaving(false) }
  }

  return (
    <div className="p-6 max-w-lg mx-auto space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Security</h1>
      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      {success && <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700">✓ Password changed successfully.</div>}
      <form onSubmit={handleSubmit} className="bg-white border border-gray-200 rounded-xl p-6 space-y-5">
        <h2 className="font-semibold text-gray-800">Change Password</h2>
        {([['current_password','Current Password'],['password','New Password'],['password_confirmation','Confirm New Password']] as const).map(([field, label]) => (
          <div key={field}>
            <label className="block text-sm font-semibold text-gray-700 mb-1">{label}</label>
            <input type="password" value={form[field as keyof typeof form]} onChange={e => setForm(f => ({...f, [field]: e.target.value}))} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" required/>
          </div>
        ))}
        <button type="submit" disabled={saving} className="w-full px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">{saving ? 'Changing...' : 'Change Password'}</button>
      </form>
    </div>
  )
}
