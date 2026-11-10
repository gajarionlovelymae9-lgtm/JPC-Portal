import { useState, useEffect } from 'react'
import {
  getAnnouncements,
  createAnnouncement,
  updateAnnouncement,
  deleteAnnouncement,
  publishAnnouncement,
  scheduleAnnouncement,
  unscheduleAnnouncement,
  archiveAnnouncement,
  type AnnouncementItem,
} from '../../services/notificationCenterService'

const CATEGORIES = ['general', 'academic', 'event', 'deadline', 'policy', 'reminder', 'other']

export default function AdminAnnouncementsPage() {
  const [announcements, setAnnouncements] = useState<AnnouncementItem[]>([])
  const [loading, setLoading] = useState(true)
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [categoryFilter, setCategoryFilter] = useState<string>('')
  const [showModal, setShowModal] = useState(false)
  const [editingItem, setEditingItem] = useState<AnnouncementItem | null>(null)

  // Form Fields
  const [title, setTitle] = useState('')
  const [message, setMessage] = useState('')
  const [priority, setPriority] = useState('normal')
  const [category, setCategory] = useState('general')
  const [audienceType, setAudienceType] = useState('all')
  const [audienceId, setAudienceId] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  useEffect(() => {
    loadAnnouncements()
  }, [statusFilter, categoryFilter])

  const loadAnnouncements = async () => {
    setLoading(true)
    try {
      const params: Record<string, unknown> = { per_page: 20 }
      if (statusFilter) params.status = statusFilter
      if (categoryFilter) params.category = categoryFilter
      const res = await getAnnouncements(params)
      const dataAny = res.data as unknown
      const list = Array.isArray(dataAny)
        ? dataAny
        : (dataAny as { data?: AnnouncementItem[] })?.data ?? []
      setAnnouncements(list as AnnouncementItem[])
    } catch {
      setAnnouncements([])
    } finally {
      setLoading(false)
    }
  }

  const handleOpenCreate = () => {
    setEditingItem(null)
    setTitle('')
    setMessage('')
    setPriority('normal')
    setCategory('general')
    setAudienceType('all')
    setAudienceId('')
    setStartDate('')
    setEndDate('')
    setActionError(null)
    setShowModal(true)
  }

  const handleOpenEdit = (item: AnnouncementItem) => {
    setEditingItem(item)
    setTitle(item.title)
    setMessage(item.message)
    setPriority(item.priority)
    setCategory(item.category ?? 'general')
    setAudienceType(item.audience_type)
    setAudienceId(item.audience_id ?? '')
    setStartDate(item.start_date ? item.start_date.split('T')[0] : '')
    setEndDate(item.end_date ? item.end_date.split('T')[0] : '')
    setActionError(null)
    setShowModal(true)
  }

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    setActionError(null)

    const payload = {
      title,
      message,
      priority,
      category,
      audience_type: audienceType,
      audience_id: audienceId || null,
      start_date: startDate || null,
      end_date: endDate || null,
    }

    try {
      if (editingItem) {
        await updateAnnouncement(editingItem.id, payload)
      } else {
        await createAnnouncement(payload)
      }
      setShowModal(false)
      await loadAnnouncements()
    } catch (err: unknown) {
      setActionError((err as Error).message || 'Failed to save announcement.')
    } finally {
      setSubmitting(false)
    }
  }

  const handlePublish = async (id: number) => {
    if (!window.confirm('Are you sure you want to publish this announcement institution-wide?')) return
    try {
      await publishAnnouncement(id)
      await loadAnnouncements()
    } catch {
      alert('Failed to publish announcement.')
    }
  }

  const handleDelete = async (id: number) => {
    if (!window.confirm('Delete this announcement?')) return
    try {
      await deleteAnnouncement(id)
      await loadAnnouncements()
    } catch {
      alert('Failed to delete announcement.')
    }
  }

  const handleSchedule = async (id: number) => {
    const input = window.prompt(
      'Publish at (local time, format YYYY-MM-DDTHH:MM):',
      '2026-10-25T09:00',
    )
    if (!input) return

    const publishAt = new Date(input)
    if (Number.isNaN(publishAt.getTime()) || publishAt.getTime() <= Date.now()) {
      alert('Enter a valid future date and time.')
      return
    }

    try {
      await scheduleAnnouncement(id, publishAt.toISOString())
      await loadAnnouncements()
    } catch (err: unknown) {
      alert((err as Error).message || 'Failed to schedule announcement.')
    }
  }

  const handleUnschedule = async (id: number) => {
    if (!window.confirm('Cancel the schedule and return this announcement to draft?')) return
    try {
      await unscheduleAnnouncement(id)
      await loadAnnouncements()
    } catch (err: unknown) {
      alert((err as Error).message || 'Failed to cancel the schedule.')
    }
  }

  const handleArchive = async (id: number) => {
    if (!window.confirm('Archive this announcement? It stays in the list but stops being visible.')) return
    try {
      await archiveAnnouncement(id)
      await loadAnnouncements()
    } catch (err: unknown) {
      alert((err as Error).message || 'Failed to archive announcement.')
    }
  }

  return (
    <div className="p-6 md:p-8 max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-200/80 pb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight flex items-center gap-3">
            <span>📢</span> Institution Announcements
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Broadcast policy updates, system notices, and academic reminders across JPC departments.
          </p>
        </div>

        <button
          onClick={handleOpenCreate}
          className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-2xl shadow-sm transition-all"
        >
          <span>➕</span> Create Announcement
        </button>
      </div>

      {/* Filter Bar */}
      <div className="flex items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex-wrap">
        <div className="flex items-center gap-2">
          <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">Status:</span>
          <div className="flex gap-1">
            {['', 'draft', 'scheduled', 'published', 'expired'].map((st) => (
              <button
                key={st}
                onClick={() => setStatusFilter(st)}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold capitalize transition-colors ${
                  statusFilter === st
                    ? 'bg-blue-600 text-white shadow-xs'
                    : 'bg-gray-50 text-gray-600 hover:bg-gray-100'
                }`}
              >
                {st === '' ? 'All' : st}
              </button>
            ))}
          </div>
        </div>

        <div className="flex items-center gap-3">
          <label className="flex items-center gap-2">
            <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">Category:</span>
            <select
              value={categoryFilter}
              onChange={(e) => setCategoryFilter(e.target.value)}
              className="px-3 py-1.5 rounded-xl text-xs font-bold border border-gray-200 bg-gray-50 text-gray-600 focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All categories</option>
              {CATEGORIES.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </label>

          <span className="text-xs font-bold text-gray-500">{announcements.length} announcements</span>
        </div>
      </div>

      {/* Announcements List */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {loading ? (
          <div className="col-span-full py-16 text-center text-sm text-gray-400">
            Loading announcements...
          </div>
        ) : announcements.length === 0 ? (
          <div className="col-span-full py-16 text-center text-gray-500 bg-white rounded-3xl border border-gray-100">
            <span className="text-4xl block mb-2">📭</span>
            <p className="font-bold text-base">No announcements found</p>
            <p className="text-xs text-gray-400 mt-1">Create an announcement to broadcast notices to staff.</p>
          </div>
        ) : (
          announcements.map((a) => {
            const isPublished = a.status === 'published'
            const isCritical = a.priority === 'critical'
            const isHigh = a.priority === 'high'

            return (
              <div
                key={a.id}
                className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 flex flex-col justify-between space-y-4 hover:border-blue-200 transition-colors"
              >
                <div className="space-y-2">
                  <div className="flex items-center justify-between gap-2 flex-wrap">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span
                        className={`text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full ${
                          isPublished
                            ? 'bg-emerald-100 text-emerald-800'
                            : a.status === 'draft'
                            ? 'bg-gray-100 text-gray-700'
                            : 'bg-amber-100 text-amber-800'
                        }`}
                      >
                        {a.status}
                      </span>

                      <span className="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">
                        {a.category ?? 'general'}
                      </span>
                    </div>

                    <span
                      className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ${
                        isCritical
                          ? 'bg-red-100 text-red-700'
                          : isHigh
                          ? 'bg-amber-100 text-amber-700'
                          : 'bg-blue-100 text-blue-700'
                      }`}
                    >
                      {a.priority} Priority
                    </span>
                  </div>

                  <h3 className="font-extrabold text-base text-gray-900 leading-snug">{a.title}</h3>
                  <p className="text-xs text-gray-600 line-clamp-3 leading-relaxed">{a.message}</p>
                </div>

                <div className="pt-4 border-t border-gray-100 text-[11px] text-gray-400 space-y-3">
                  <div className="flex items-center justify-between">
                    <span>Audience: <strong className="text-gray-700 font-bold uppercase">{a.audience_type}</strong></span>
                    {a.published_at && (
                      <span>Published: {new Date(a.published_at).toLocaleDateString()}</span>
                    )}
                  </div>

                  <div className="flex items-center justify-end gap-2 pt-1 flex-wrap">
                    {isPublished ? (
                      <button
                        onClick={() => handleArchive(a.id)}
                        className="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-xs rounded-xl transition-colors"
                      >
                        📦 Archive
                      </button>
                    ) : (
                      <>
                        <button
                          onClick={() => handlePublish(a.id)}
                          className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition-colors"
                        >
                          📢 Publish
                        </button>
                        <button
                          onClick={() => handleSchedule(a.id)}
                          className="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs rounded-xl transition-colors"
                        >
                          🗓️ {a.status === 'scheduled' ? 'Reschedule' : 'Schedule'}
                        </button>
                        {a.status === 'scheduled' && (
                          <button
                            onClick={() => handleUnschedule(a.id)}
                            className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs rounded-xl transition-colors"
                          >
                            ↩ Unschedule
                          </button>
                        )}
                        {(a.status === 'draft' || a.status === 'scheduled') && (
                          <button
                            onClick={() => handleOpenEdit(a)}
                            className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs rounded-xl transition-colors"
                          >
                            ✏️ Edit
                          </button>
                        )}
                      </>
                    )}

                    {(a.status === 'draft' || a.status === 'expired') && (
                      <button
                        onClick={() => handleDelete(a.id)}
                        className="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs rounded-xl transition-colors"
                      >
                        🗑️ Delete
                      </button>
                    )}
                  </div>
                </div>
              </div>
            )
          })
        )}
      </div>

      {/* Modal Form */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-xs p-4">
          <div className="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-xl w-full p-6 space-y-5 animate-scale-in">
            <div className="flex items-center justify-between border-b border-gray-100 pb-4">
              <h3 className="font-extrabold text-lg text-gray-900 flex items-center gap-2">
                <span>📢</span> {editingItem ? 'Edit Announcement' : 'Create Announcement'}
              </h3>
              <button
                onClick={() => setShowModal(false)}
                className="text-gray-400 hover:text-gray-700 text-lg font-bold"
              >
                ✕
              </button>
            </div>

            {actionError && (
              <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl font-medium">
                {actionError}
              </div>
            )}

            <form onSubmit={handleFormSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Title <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  placeholder="e.g., Mid-Year Faculty Progress Report Submission Window"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Message <span className="text-red-500">*</span>
                </label>
                <textarea
                  rows={4}
                  placeholder="Announcement body text..."
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">Priority</label>
                  <select
                    value={priority}
                    onChange={(e) => setPriority(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                  </select>
                </div>

                <div>
                  <label htmlFor="announcement-category" className="block text-xs font-bold text-gray-700 mb-1">Category</label>
                  <select
                    id="announcement-category"
                    value={category}
                    onChange={(e) => setCategory(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500"
                  >
                    {CATEGORIES.map((c) => (
                      <option key={c} value={c}>
                        {c.charAt(0).toUpperCase() + c.slice(1)}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">Audience Type</label>
                <select
                  value={audienceType}
                  onChange={(e) => setAudienceType(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500"
                >
                  <option value="all">All Employees</option>
                  <option value="department">By Department</option>
                  <option value="role">By Role</option>
                </select>
              </div>

              {audienceType !== 'all' && (
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Audience ID ({audienceType === 'department' ? 'Department ID' : 'Role Name like hr/department_head'})
                  </label>
                  <input
                    type="text"
                    placeholder={audienceType === 'department' ? 'e.g., 1' : 'e.g., department_head'}
                    value={audienceId}
                    onChange={(e) => setAudienceId(e.target.value)}
                    className="w-full px-3.5 py-2 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              )}

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">Start Date (Optional)</label>
                  <input
                    type="date"
                    value={startDate}
                    onChange={(e) => setStartDate(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">End Date (Optional)</label>
                  <input
                    type="date"
                    value={endDate}
                    onChange={(e) => setEndDate(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-gray-100">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 text-sm font-bold text-gray-600 hover:bg-gray-100 rounded-xl transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold text-sm rounded-xl shadow-sm transition-all"
                >
                  {submitting ? 'Saving...' : editingItem ? 'Update' : 'Create Draft'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
