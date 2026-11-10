import { useEffect, useState } from 'react'
import { getMyProfile, updateMyProfile } from '../../services/employeePortalService'
import type { EmployeeProfile } from '../../services/employeePortalService'

export default function EmployeeProfilePage() {
  const [profile, setProfile] = useState<EmployeeProfile | null>(null)
  const [email, setEmail] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  useEffect(() => {
    getMyProfile()
      .then(r => { setProfile(r.data); setEmail(r.data.email) })
      .catch(() => setError('Unable to load profile.'))
      .finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    setSaving(true); setError(null); setSuccess(false)
    try { await updateMyProfile({ email }); setSuccess(true) }
    catch { setError('Failed to update profile.') }
    finally { setSaving(false) }
  }

  if (loading) return <div className="p-6 space-y-4">{[...Array(5)].map((_,i) => <div key={i} className="h-12 bg-gray-100 rounded-lg animate-pulse"/>)}</div>
  if (!profile) return <div className="p-6 bg-red-50 text-red-700 rounded-lg">{error}</div>

  return (
    <div className="p-6 max-w-2xl mx-auto space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">My Profile</h1>
      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}
      {success && <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700">✓ Profile updated successfully.</div>}
      <div className="bg-white border border-gray-200 rounded-xl p-6 space-y-4">
        <div className="flex items-center gap-4 pb-4 border-b border-gray-100">
          <div className="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-2xl font-bold text-blue-600">{profile.name.charAt(0).toUpperCase()}</div>
          <div><h2 className="text-xl font-bold text-gray-900">{profile.name}</h2><p className="text-gray-500 text-sm">{profile.employee_no ?? '—'}</p></div>
        </div>
        {[
          { label: 'Department', value: profile.department },
          { label: 'Position', value: profile.position },
          { label: 'Employee Type', value: profile.employee_type },
          { label: 'Status', value: profile.status },
        ].map(f => (
          <div key={f.label} className="flex items-center justify-between py-2 border-b border-gray-50">
            <span className="text-sm font-medium text-gray-500">{f.label}</span>
            <span className="text-sm text-gray-900">{f.value ?? '—'}</span>
          </div>
        ))}
        <div>
          <label className="block text-sm font-semibold text-gray-700 mb-1">Email (editable)</label>
          <input value={email} onChange={e => setEmail(e.target.value)} type="email" className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"/>
        </div>
        <div className="flex justify-end pt-2">
          <button onClick={handleSave} disabled={saving} className="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">{saving ? 'Saving...' : 'Save Changes'}</button>
        </div>
      </div>
    </div>
  )
}
