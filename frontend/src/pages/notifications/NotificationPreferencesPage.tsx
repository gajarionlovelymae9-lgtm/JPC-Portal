import { useState, useEffect } from 'react'
import { getPreferences, updatePreferences, type NotifPreference } from '../../services/notificationCenterService'

interface EventCategoryGroup {
  name: string
  icon: string
  events: Array<{
    key: string
    label: string
    description: string
    isCritical?: boolean
  }>
}

const CATEGORY_GROUPS: EventCategoryGroup[] = [
  {
    name: 'Task Deliverables',
    icon: '📋',
    events: [
      { key: 'task_assigned', label: 'Task Assigned', description: 'When a new task is assigned to you', isCritical: true },
      { key: 'task_updated', label: 'Task Updated', description: 'When task details or instructions are modified' },
      { key: 'task_deadline', label: 'Deadline Reminders', description: 'Approaching deadline alerts (7d, 3d, 1d)' },
      { key: 'task_due_today', label: 'Due Today', description: 'Urgent reminder on the day of deadline' },
      { key: 'task_overdue', label: 'Overdue Alert', description: 'Alert when a task has passed its deadline', isCritical: true },
      { key: 'task_completed', label: 'Task Completed', description: 'Confirmation when a task is completed' },
    ],
  },
  {
    name: 'Report Submissions & Review',
    icon: '📄',
    events: [
      { key: 'report_submitted', label: 'Report Submitted', description: 'Confirmation upon submitting a report' },
      { key: 'report_under_review', label: 'Report Under Review', description: 'When reviewer starts evaluating your report' },
      { key: 'report_approved', label: 'Report Approved', description: 'Notification when report is approved' },
      { key: 'revision_required', label: 'Revision Required', description: 'Action required when revisions are requested', isCritical: true },
      { key: 'report_rejected', label: 'Report Rejected', description: 'Notification if a report is rejected' },
    ],
  },
  {
    name: 'AI & OCR Processing',
    icon: '🤖',
    events: [
      { key: 'ocr_completed', label: 'OCR Extraction Completed', description: 'When text extraction finishes' },
      { key: 'ocr_failed', label: 'OCR Failed', description: 'Alert if document OCR fails extraction', isCritical: true },
      { key: 'ai_validation_completed', label: 'AI Validation Ready', description: 'When AI compliance check completes' },
      { key: 'ai_processing_failed', label: 'AI Processing Error', description: 'Alert if AI analysis encounters an error', isCritical: true },
    ],
  },
  {
    name: 'Targets & Milestones',
    icon: '🎯',
    events: [
      { key: 'target_reminder', label: 'Target Milestone Reminder', description: 'Progress reminder for annual/quarterly targets' },
      { key: 'target_at_risk', label: 'Target At Risk', description: 'Warning if target progress is below schedule' },
      { key: 'achievement_earned', label: 'Badge / Achievement Awarded', description: 'Celebration when badges are unlocked' },
      { key: 'monthly_achievement', label: 'Monthly Top Achiever', description: 'Monthly top performer recognition' },
    ],
  },
  {
    name: 'Escalations & System',
    icon: '⚙️',
    events: [
      { key: 'escalation_created', label: 'Escalation Alert', description: 'Alert when an issue is escalated to management', isCritical: true },
      { key: 'escalation_resolved', label: 'Escalation Resolved', description: 'Notice when an escalation is resolved', isCritical: true },
      { key: 'announcement_published', label: 'Institution Announcements', description: 'Broad announcements from JPC Leadership' },
      { key: 'system', label: 'System Maintenance & Policy', description: 'Essential system maintenance notices', isCritical: true },
    ],
  },
]

export default function NotificationPreferencesPage() {
  const [prefs, setPrefs] = useState<Record<string, { in_app: boolean; email: boolean }>>({})
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  useEffect(() => {
    loadPreferences()
  }, [])

  const loadPreferences = async () => {
    setLoading(true)
    try {
      const res = await getPreferences()
      const dataAny = res as unknown
      const list: NotifPreference[] = Array.isArray(dataAny)
        ? dataAny
        : (dataAny as { data?: NotifPreference[] })?.data ?? []
      const map: Record<string, { in_app: boolean; email: boolean }> = {}

      list.forEach((item) => {
        map[item.event] = {
          in_app: item.in_app_enabled ?? true,
          email: item.email_enabled ?? true,
        }
      })

      // Ensure all grouped events have default state if not in DB yet
      CATEGORY_GROUPS.forEach((grp) => {
        grp.events.forEach((evt) => {
          if (!map[evt.key]) {
            map[evt.key] = { in_app: true, email: true }
          }
        })
      })

      setPrefs(map)
    } catch {
      setToast({ type: 'error', message: 'Failed to load notification preferences.' })
    } finally {
      setLoading(false)
    }
  }

  const handleToggle = (eventKey: string, channel: 'in_app' | 'email', isCritical?: boolean) => {
    if (isCritical) return // Cannot toggle critical events

    setPrefs((prev) => ({
      ...prev,
      [eventKey]: {
        ...prev[eventKey],
        [channel]: !prev[eventKey]?.[channel],
      },
    }))
  }

  const handleSave = async () => {
    setSaving(true)
    setToast(null)
    try {
      await updatePreferences(prefs)
      setToast({ type: 'success', message: 'Notification preferences saved successfully!' })
      setTimeout(() => setToast(null), 4000)
    } catch {
      setToast({ type: 'error', message: 'Failed to save preferences. Please try again.' })
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="p-8 max-w-5xl mx-auto">
        <div className="animate-pulse space-y-6">
          <div className="h-8 bg-gray-200 rounded w-1/3"></div>
          <div className="h-4 bg-gray-100 rounded w-1/2"></div>
          <div className="h-64 bg-gray-100 rounded-3xl"></div>
        </div>
      </div>
    )
  }

  return (
    <div className="p-6 md:p-8 max-w-5xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-200/80 pb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight flex items-center gap-3">
            <span>⚙️</span> Notification Preferences
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Configure how and when you receive in-app alerts and transactional email notices.
          </p>
        </div>

        <button
          onClick={handleSave}
          disabled={saving}
          className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-2xl shadow-sm transition-all disabled:opacity-50"
        >
          {saving ? (
            <>
              <span className="animate-spin text-sm">⏳</span> Saving...
            </>
          ) : (
            <>
              <span>💾</span> Save Preferences
            </>
          )}
        </button>
      </div>

      {toast && (
        <div
          className={`p-4 rounded-2xl border text-sm font-semibold flex items-center justify-between ${
            toast.type === 'success'
              ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
              : 'bg-red-50 border-red-200 text-red-800'
          }`}
        >
          <span>{toast.message}</span>
          <button onClick={() => setToast(null)} className="text-xs opacity-70 hover:opacity-100">
            ✕
          </button>
        </div>
      )}

      {/* Institutional Policy Notice */}
      <div className="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-3">
        <span className="text-lg">🔒</span>
        <p className="leading-relaxed">
          <strong className="font-bold">Institutional Policy Guarantee:</strong> Critical alerts (such as direct task assignments, overdue escalations, AI errors, and required report revisions) cannot be disabled to ensure compliance and institutional accountability across John Paul College.
        </p>
      </div>

      {/* Categories */}
      <div className="space-y-6">
        {CATEGORY_GROUPS.map((group) => (
          <div key={group.name} className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div className="px-6 py-4 bg-gray-50/70 border-b border-gray-100 flex items-center justify-between">
              <h2 className="font-extrabold text-base text-gray-900 flex items-center gap-2.5">
                <span>{group.icon}</span> {group.name}
              </h2>
              <div className="hidden sm:flex items-center gap-8 text-xs font-bold text-gray-500 uppercase tracking-wider pr-4">
                <span>In-App Bell</span>
                <span>Email Digest</span>
              </div>
            </div>

            <div className="divide-y divide-gray-100">
              {group.events.map((evt) => {
                const isCritical = Boolean(evt.isCritical)
                const inAppOn = isCritical || (prefs[evt.key]?.in_app ?? true)
                const emailOn = isCritical || (prefs[evt.key]?.email ?? true)

                return (
                  <div
                    key={evt.key}
                    className="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-gray-50/50 transition-colors"
                  >
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <h3 className="font-bold text-sm text-gray-900">{evt.label}</h3>
                        {isCritical && (
                          <span className="text-[10px] bg-gray-100 text-gray-600 font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                            🔒 Required
                          </span>
                        )}
                      </div>
                      <p className="text-xs text-gray-500 mt-0.5">{evt.description}</p>
                    </div>

                    <div className="flex items-center gap-8 sm:pr-4 justify-between sm:justify-end">
                      {/* In-App Toggle */}
                      <label className="flex items-center gap-2 cursor-pointer select-none">
                        <span className="sm:hidden text-xs text-gray-500 font-medium">In-App:</span>
                        <input
                          type="checkbox"
                          checked={inAppOn}
                          disabled={isCritical}
                          onChange={() => handleToggle(evt.key, 'in_app', isCritical)}
                          className="w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 disabled:opacity-50 cursor-pointer"
                        />
                      </label>

                      {/* Email Toggle */}
                      <label className="flex items-center gap-2 cursor-pointer select-none">
                        <span className="sm:hidden text-xs text-gray-500 font-medium">Email:</span>
                        <input
                          type="checkbox"
                          checked={emailOn}
                          disabled={isCritical}
                          onChange={() => handleToggle(evt.key, 'email', isCritical)}
                          className="w-5 h-5 text-blue-600 rounded border-gray-300 focus:ring-blue-500 disabled:opacity-50 cursor-pointer"
                        />
                      </label>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
