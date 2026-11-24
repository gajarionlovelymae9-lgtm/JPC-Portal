import { useEffect, useState, type ChangeEvent } from 'react'
import { getMyProfile, updateMyProfile, uploadMyProfilePhoto } from '../../services/employeePortalService'
import type { EmployeeProfile } from '../../services/employeePortalService'

/** Fields the employee is authorized to edit themselves (personal info only). */
interface ProfileForm {
  first_name: string
  middle_name: string
  last_name: string
  suffix: string
  email: string
  username: string
  phone: string
  address: string
  date_of_birth: string
}

function emptyForm(): ProfileForm {
  return {
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    email: '',
    username: '',
    phone: '',
    address: '',
    date_of_birth: '',
  }
}

/** ISO datetime -> "yyyy-mm-dd" for <input type="date">. Falls back to "". */
function toDateInput(value: string | null): string {
  return value ? value.slice(0, 10) : ''
}

function formFromProfile(profile: EmployeeProfile): ProfileForm {
  return {
    first_name: profile.first_name ?? '',
    middle_name: profile.middle_name ?? '',
    last_name: profile.last_name ?? '',
    suffix: profile.suffix ?? '',
    email: profile.email ?? '',
    username: profile.username ?? '',
    phone: profile.phone ?? '',
    address: profile.address ?? '',
    date_of_birth: toDateInput(profile.date_of_birth),
  }
}

/** ISO date string -> friendly display ("May 15, 1990"). Falls back to "—". */
function formatDate(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
}

/** Colour the employment-status badge by value. */
function statusClasses(status: string | null): string {
  const value = (status ?? '').toLowerCase()
  if (value === 'active') return 'bg-ok-soft text-ok'
  if (['inactive', 'resigned', 'terminated', 'suspended'].includes(value)) {
    return 'bg-error-soft text-error'
  }
  return 'bg-mint-100 text-slate-600'
}

export default function EmployeeProfilePage() {
  const [profile, setProfile] = useState<EmployeeProfile | null>(null)
  const [form, setForm] = useState<ProfileForm>(emptyForm())
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)

  useEffect(() => {
    getMyProfile()
      .then(r => {
        setProfile(r.data)
        setForm(formFromProfile(r.data))
      })
      .catch(() => setError('Unable to load profile.'))
      .finally(() => setLoading(false))
  }, [])

  const handleChange = (field: keyof ProfileForm) => (value: string) => {
    setForm(prev => ({ ...prev, [field]: value }))
  }

  const handleCancel = () => {
    if (!profile) return
    setForm(formFromProfile(profile))
    setError(null)
    setSuccess(false)
  }

  const handleSave = async () => {
    setSaving(true)
    setError(null)
    setSuccess(false)
    try {
      const r = await updateMyProfile({
        ...form,
        date_of_birth: form.date_of_birth || null,
      })
      setProfile(r.data)
      setForm(formFromProfile(r.data))
      setSuccess(true)
    } catch {
      setError('Failed to update profile.')
    } finally {
      setSaving(false)
    }
  }

  const handlePhotoChange = async (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return

    setUploading(true)
    setError(null)
    setSuccess(false)
    try {
      const r = await uploadMyProfilePhoto(file)
      setProfile(r.data)
      setForm(formFromProfile(r.data))
      setSuccess(true)
    } catch {
      setError('Failed to update profile photo.')
    } finally {
      setUploading(false)
      e.target.value = ''
    }
  }

  if (loading) {
    return (
      <div className="p-4 sm:p-6 max-w-5xl mx-auto space-y-4">
        <div className="h-10 w-48 bg-mint-100 rounded-jpc animate-pulse" />
        <div className="h-32 bg-mint-100 rounded-jpc-lg animate-pulse" />
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="h-64 bg-mint-100 rounded-jpc-lg animate-pulse" />
          <div className="h-64 bg-mint-100 rounded-jpc-lg animate-pulse" />
        </div>
      </div>
    )
  }

  if (!profile) {
    return (
      <div className="p-4 sm:p-6 max-w-5xl mx-auto">
        <div className="p-6 bg-error-soft text-error rounded-jpc-lg">{error}</div>
      </div>
    )
  }

  return (
    <div className="p-4 sm:p-6 max-w-5xl mx-auto space-y-6">
      {/* Page heading */}
      <div>
        <h1 className="text-2xl font-bold text-slate-900">My Profile</h1>
        <p className="text-sm text-slate-500">View and manage your account information.</p>
      </div>

      {error && (
        <div className="p-4 bg-error-soft border border-error/20 text-error rounded-jpc-lg text-sm">
          {error}
        </div>
      )}
      {success && (
        <div className="p-4 bg-ok-soft border border-ok/20 text-ok rounded-jpc-lg text-sm">
          ✓ Profile updated successfully.
        </div>
      )}

      {/* 1. Profile header */}
      <section className="bg-surface border border-line rounded-jpc-lg shadow-jpc p-6">
        <div className="flex flex-col sm:flex-row items-center sm:items-start gap-5 text-center sm:text-left">
          <div className="flex flex-col items-center gap-2">
            {profile.profile_photo_url ? (
              <img
                src={profile.profile_photo_url}
                alt={profile.name}
                className="w-20 h-20 rounded-full object-cover ring-2 ring-jpc-100"
              />
            ) : (
              <div className="w-20 h-20 rounded-full bg-jpc-100 flex items-center justify-center text-3xl font-bold text-jpc-700">
                {profile.name.charAt(0).toUpperCase()}
              </div>
            )}
            <label
              htmlFor="profile-photo-input"
              className={`text-xs font-medium text-jpc-600 hover:text-jpc-700 ${
                uploading ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'
              }`}
            >
              {uploading ? 'Uploading...' : 'Change photo'}
            </label>
            <input
              id="profile-photo-input"
              type="file"
              accept="image/*"
              className="hidden"
              onChange={handlePhotoChange}
              disabled={uploading}
            />
          </div>
          <div className="space-y-1">
            <h2 className="text-xl font-bold text-slate-900">{profile.name}</h2>
            <p className="text-sm text-slate-500">
              Employee ID: <span className="font-medium text-slate-700">{profile.employee_no ?? '—'}</span>
            </p>
            <p className="text-sm text-slate-500">{profile.position ?? '—'}</p>
            <span
              className={`inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold capitalize ${statusClasses(profile.status)}`}
            >
              {profile.status ?? 'unknown'}
            </span>
          </div>
        </div>
      </section>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* 2. Personal information (editable) */}
        <section className="bg-surface border border-line rounded-jpc-lg shadow-jpc p-6">
          <h3 className="text-base font-semibold text-slate-900 mb-4">Personal Information</h3>
          <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <EditableField
                id="profile-first-name"
                label="First Name"
                type="text"
                value={form.first_name}
                onChange={handleChange('first_name')}
              />
              <EditableField
                id="profile-middle-name"
                label="Middle Name"
                type="text"
                value={form.middle_name}
                onChange={handleChange('middle_name')}
              />
              <EditableField
                id="profile-last-name"
                label="Last Name"
                type="text"
                value={form.last_name}
                onChange={handleChange('last_name')}
              />
              <EditableField
                id="profile-suffix"
                label="Suffix"
                type="text"
                value={form.suffix}
                onChange={handleChange('suffix')}
              />
            </div>
            <EditableField
              id="profile-email"
              label="Email Address"
              type="email"
              value={form.email}
              onChange={handleChange('email')}
            />
            <EditableField
              id="profile-phone"
              label="Contact Number"
              type="tel"
              value={form.phone}
              onChange={handleChange('phone')}
            />
            <EditableField
              id="profile-address"
              label="Address"
              type="text"
              value={form.address}
              onChange={handleChange('address')}
            />
            <EditableField
              id="profile-date-of-birth"
              label="Date of Birth"
              type="date"
              value={form.date_of_birth}
              onChange={handleChange('date_of_birth')}
            />
          </div>
        </section>

        {/* 3. Employment information (read-only) */}
        <section className="bg-surface border border-line rounded-jpc-lg shadow-jpc p-6">
          <h3 className="text-base font-semibold text-slate-900 mb-4">Employment Information</h3>
          <div className="space-y-3">
            <InfoRow label="Department" value={profile.department} />
            <InfoRow label="Position" value={profile.position} />
            <InfoRow label="Employee Type" value={profile.employee_type} />
            <InfoRow label="Date Hired" value={formatDate(profile.date_hired)} />
            <InfoRow label="Employment Status" value={profile.status} />
          </div>
          <p className="mt-4 text-xs text-slate-400">
            Managed by HR. Contact your administrator for changes.
          </p>
        </section>
      </div>

      {/* 4. Account details (username editable) */}
      <section className="bg-surface border border-line rounded-jpc-lg shadow-jpc p-6">
        <h3 className="text-base font-semibold text-slate-900 mb-4">Account Details</h3>
        <div className="space-y-4">
          <EditableField
            id="profile-username"
            label="Username"
            type="text"
            value={form.username}
            onChange={handleChange('username')}
          />
          <InfoRow label="Last Updated" value={formatDate(profile.updated_at)} />
        </div>
      </section>

      {/* Actions */}
      <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
        <button
          type="button"
          onClick={handleCancel}
          disabled={saving}
          className="px-5 py-2 border border-line text-slate-700 rounded-jpc text-sm font-medium hover:bg-mint-100 disabled:opacity-50"
        >
          Cancel
        </button>
        <button
          type="button"
          onClick={handleSave}
          disabled={saving}
          className="px-5 py-2 bg-jpc-600 text-white rounded-jpc text-sm font-medium hover:bg-jpc-700 disabled:opacity-50"
        >
          {saving ? 'Saving...' : 'Save Changes'}
        </button>
      </div>
    </div>
  )
}

/** Editable label + input. */
function EditableField({
  id,
  label,
  type,
  value,
  onChange,
}: {
  id: string
  label: string
  type: string
  value: string
  onChange: (value: string) => void
}) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-slate-700 mb-1">
        {label}
      </label>
      <input
        id={id}
        type={type}
        value={value}
        onChange={e => onChange(e.target.value)}
        className="w-full border border-line rounded-jpc px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-jpc-500"
      />
    </div>
  )
}

/** Compact read-only label/value pair used in the info sections. */
function InfoRow({ label, value }: { label: string; value: string | null }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-line pb-2 last:border-0">
      <span className="text-sm font-medium text-slate-500">{label}</span>
      <span className="text-sm text-slate-900 text-right capitalize">{value ?? '—'}</span>
    </div>
  )
}
