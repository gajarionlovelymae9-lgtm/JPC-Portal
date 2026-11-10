import { useCallback, useEffect, useState } from 'react'
import {
  createMyTargetAdjustment,
  createMyTargetBreakdown,
  createMyTargetMilestone,
  createMyTargetNote,
  deleteMyTargetBreakdown,
  deleteMyTargetMilestone,
  deleteMyTargetNote,
  getMyTarget,
  getMyTargetSuggestions,
  getMyTargets,
  updateMyTargetBreakdown,
  updateMyTargetMilestone,
  updateMyTargetNote,
} from '../../services/employeePortalService'
import type {
  EmployeeTargetAdjustment,
  EmployeeTargetAdjustmentType,
  EmployeeTargetBreakdown,
  EmployeeTargetDerivedStatus,
  EmployeeTargetDetail,
  EmployeeTargetItem,
  EmployeeTargetMilestone,
  EmployeeTargetNote,
  EmployeeTargetSuggestionsPayload,
  EmployeeTargetsPayload,
} from '../../services/employeePortalService'
import { Modal } from '../../components/admin/AdminUi'

type TabKey = 'breakdowns' | 'milestones' | 'adjustments' | 'notes'

const DERIVED_STATUS_LABEL: Record<EmployeeTargetDerivedStatus, string> = {
  completed: 'Completed',
  in_progress: 'In progress',
  missed: 'Missed',
  not_started: 'Not started',
}

const DERIVED_STATUS_TONE: Record<EmployeeTargetDerivedStatus, string> = {
  completed: 'bg-green-100 text-green-700',
  in_progress: 'bg-blue-100 text-blue-700',
  missed: 'bg-red-100 text-red-700',
  not_started: 'bg-gray-100 text-gray-600',
}

const ADJUSTMENT_TONE: Record<string, string> = {
  pending: 'bg-amber-100 text-amber-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
}

const ADJUSTMENT_TYPES: Array<{ value: EmployeeTargetAdjustmentType; label: string }> = [
  { value: 'target_value', label: 'Target value' },
  { value: 'deadline', label: 'Deadline' },
  { value: 'period', label: 'Target period' },
  { value: 'description', label: 'Description' },
  { value: 'other', label: 'Other' },
]

const progressSourceNote = (target: EmployeeTargetItem): string => {
  if (target.progress_source === 'breakdowns') {
    return 'Progress is the weighted average of this target’s sub-targets.'
  }
  if (target.progress_source === 'tasks') {
    return `Progress is derived from ${target.tasks_completed}/${target.tasks_total} linked task(s) completed.`
  }
  return 'No progress data yet — add sub-targets so progress can be measured.'
}

const formatDate = (value: string | null): string => {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value
  return parsed.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

export default function EmployeeTargetsPage() {
  const [payload, setPayload] = useState<EmployeeTargetsPayload | null>(null)
  const [suggestions, setSuggestions] = useState<EmployeeTargetSuggestionsPayload | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [detail, setDetail] = useState<EmployeeTargetDetail | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [tab, setTab] = useState<TabKey>('breakdowns')

  const loadTargets = useCallback(async (): Promise<EmployeeTargetItem[]> => {
    const response = await getMyTargets()
    setPayload(response.data)
    return response.data?.items ?? []
  }, [])

  const loadDetail = useCallback(async (id: number) => {
    setDetailLoading(true)
    try {
      const response = await getMyTarget(id)
      setDetail(response.data)
    } catch {
      setError('Unable to load this target’s detail. Please try again.')
    } finally {
      setDetailLoading(false)
    }
  }, [])

  const loadSuggestions = useCallback(async () => {
    try {
      const response = await getMyTargetSuggestions()
      setSuggestions(response.data)
    } catch {
      // Suggestions are advisory; a failure must not block the page.
      setSuggestions(null)
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    Promise.all([loadTargets(), loadSuggestions()])
      .then(([items]) => {
        if (cancelled) return
        if (items.length > 0) {
          setSelectedId(items[0].id)
        }
      })
      .catch(() => {
        if (!cancelled) setError('Unable to load targets. Please try again.')
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [loadTargets, loadSuggestions])

  useEffect(() => {
    if (selectedId !== null) {
      void loadDetail(selectedId)
    } else {
      setDetail(null)
    }
  }, [selectedId, loadDetail])

  /** Refresh everything after a mutation so roll-up progress stays in sync. */
  const refresh = useCallback(async () => {
    await loadTargets()
    if (selectedId !== null) {
      await loadDetail(selectedId)
    }
    await loadSuggestions()
  }, [loadTargets, loadDetail, loadSuggestions, selectedId])

  const run = useCallback(
    async (action: () => Promise<unknown>, successMessage: string) => {
      setBusy(true)
      setError(null)
      setNotice(null)
      try {
        await action()
        await refresh()
        setNotice(successMessage)
      } catch (cause) {
        setError(cause instanceof Error ? cause.message : 'The action could not be completed.')
      } finally {
        setBusy(false)
      }
    },
    [refresh],
  )

  if (loading) {
    return (
      <div className="p-6 space-y-3">
        {[...Array(3)].map((_, i) => (
          <div key={i} className="h-24 bg-gray-100 rounded-xl animate-pulse" />
        ))}
      </div>
    )
  }

  const items = payload?.items ?? []
  const summary = payload?.summary ?? { total: 0, completed: 0, pending: 0 }

  return (
    <div className="p-6 space-y-6 max-w-5xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">My Targets</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          {summary.total} target{summary.total === 1 ? '' : 's'} · {summary.completed} completed ·{' '}
          {summary.pending} in progress
        </p>
      </div>

      {error ? (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 text-sm">{error}</div>
      ) : null}
      {notice ? (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700 text-sm">{notice}</div>
      ) : null}

      <SuggestionsPanel suggestions={suggestions} />

      {items.length === 0 ? (
        <div className="text-center py-16 text-gray-500 bg-white border border-gray-200 rounded-2xl">
          <div className="text-4xl mb-3">🎯</div>
          <p>No targets assigned yet.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {items.map((target) => (
            <TargetCard
              key={target.id}
              target={target}
              active={target.id === selectedId}
              onSelect={() => {
                setSelectedId(target.id)
                setTab('breakdowns')
              }}
            />
          ))}
        </div>
      )}

      {selectedId !== null ? (
        <TargetDetailPanel
          detail={detail}
          loading={detailLoading}
          tab={tab}
          onTabChange={setTab}
          busy={busy}
          onAddBreakdown={(payloadInput) =>
            run(() => createMyTargetBreakdown(selectedId, payloadInput), 'Sub-target added.')
          }
          onUpdateBreakdown={(id, payloadInput) =>
            run(() => updateMyTargetBreakdown(selectedId, id, payloadInput), 'Sub-target updated.')
          }
          onDeleteBreakdown={(id) => run(() => deleteMyTargetBreakdown(selectedId, id), 'Sub-target removed.')}
          onAddMilestone={(payloadInput) =>
            run(() => createMyTargetMilestone(selectedId, payloadInput), 'Milestone added.')
          }
          onUpdateMilestone={(id, payloadInput) =>
            run(() => updateMyTargetMilestone(selectedId, id, payloadInput), 'Milestone updated.')
          }
          onDeleteMilestone={(id) => run(() => deleteMyTargetMilestone(selectedId, id), 'Milestone removed.')}
          onRequestAdjustment={(payloadInput) =>
            run(() => createMyTargetAdjustment(selectedId, payloadInput), 'Adjustment request submitted.')
          }
          onAddNote={(body) => run(() => createMyTargetNote(selectedId, body), 'Note added.')}
          onUpdateNote={(id, body) => run(() => updateMyTargetNote(selectedId, id, body), 'Note updated.')}
          onDeleteNote={(id) => run(() => deleteMyTargetNote(selectedId, id), 'Note removed.')}
        />
      ) : null}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Suggestions (AI)                                                    */
/* ------------------------------------------------------------------ */

function SuggestionsPanel({ suggestions }: { suggestions: EmployeeTargetSuggestionsPayload | null }) {
  if (suggestions === null) return null

  const severityTone: Record<string, string> = {
    high: 'bg-red-50 border-red-200 text-red-800',
    medium: 'bg-amber-50 border-amber-200 text-amber-800',
    low: 'bg-sky-50 border-sky-200 text-sky-800',
  }

  return (
    <section className="bg-white border border-gray-200 rounded-2xl p-5 space-y-3">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h2 className="font-semibold text-gray-900">AI Target Suggestions</h2>
          <p className="text-xs text-gray-500">
            Advisory recommendations generated only from your {suggestions.targets_analyzed} assigned target(s) and their
            recorded progress.
          </p>
        </div>
      </div>

      {!suggestions.available ? (
        <p className="text-sm text-gray-500">{suggestions.no_data_reason}</p>
      ) : suggestions.suggestions.length === 0 ? (
        <p className="text-sm text-gray-500">
          No actions suggested right now — your targets are on track or already achieved.
        </p>
      ) : (
        <ul className="space-y-2">
          {suggestions.suggestions.map((suggestion) => (
            <li
              key={`${suggestion.key}-${suggestion.target_id}-${suggestion.title}`}
              className={`border rounded-xl px-4 py-3 text-sm ${severityTone[suggestion.severity] ?? severityTone.low}`}
            >
              <div className="flex items-center justify-between gap-2">
                <p className="font-semibold">{suggestion.title}</p>
                <span className="text-[10px] font-bold uppercase tracking-wide opacity-70">
                  {suggestion.target_title ?? 'Target'}
                </span>
              </div>
              <p className="mt-1">{suggestion.detail}</p>
            </li>
          ))}
        </ul>
      )}

      <p className="text-[11px] text-gray-400">{suggestions.disclaimer}</p>
    </section>
  )
}

/* ------------------------------------------------------------------ */
/* Target list card                                                    */
/* ------------------------------------------------------------------ */

function TargetCard({
  target,
  active,
  onSelect,
}: {
  target: EmployeeTargetItem
  active: boolean
  onSelect: () => void
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={`w-full text-left bg-white border rounded-xl p-5 transition ${
        active ? 'border-blue-400 ring-2 ring-blue-100' : 'border-gray-200 hover:border-gray-300'
      }`}
    >
      <div className="flex items-start justify-between gap-3 mb-2">
        <div className="min-w-0">
          <h3 className="font-semibold text-gray-900 truncate">{target.title ?? 'Untitled target'}</h3>
          {target.description ? <p className="text-sm text-gray-500 line-clamp-2">{target.description}</p> : null}
        </div>
        <span
          className={`shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold ${DERIVED_STATUS_TONE[target.derived_status]}`}
        >
          {DERIVED_STATUS_LABEL[target.derived_status]}
        </span>
      </div>

      {(target.start_date || target.end_date) && (
        <p className="text-xs text-gray-400 mb-3">
          {formatDate(target.start_date)} → {formatDate(target.end_date)}
        </p>
      )}

      <div className="flex items-center justify-between text-sm text-gray-500 mb-2">
        <span>
          {target.target_value === null ? '—' : target.target_value}
          {target.target_unit ? ` ${target.target_unit}` : ''}
          {target.breakdowns_count > 0
            ? ` · ${target.breakdowns_completed}/${target.breakdowns_count} sub-targets`
            : ''}
        </span>
        <span className="font-semibold text-gray-800">{target.progress === null ? '—' : `${target.progress}%`}</span>
      </div>
      <div className="w-full bg-gray-100 rounded-full h-2.5">
        <div
          className="bg-blue-600 h-2.5 rounded-full transition-all"
          style={{ width: `${Math.min(100, target.progress ?? 0)}%` }}
        />
      </div>
    </button>
  )
}

/* ------------------------------------------------------------------ */
/* Detail panel (breakdowns / milestones / adjustments / notes)        */
/* ------------------------------------------------------------------ */

function TargetDetailPanel({
  detail,
  loading,
  tab,
  onTabChange,
  busy,
  onAddBreakdown,
  onUpdateBreakdown,
  onDeleteBreakdown,
  onAddMilestone,
  onUpdateMilestone,
  onDeleteMilestone,
  onRequestAdjustment,
  onAddNote,
  onUpdateNote,
  onDeleteNote,
}: {
  detail: EmployeeTargetDetail | null
  loading: boolean
  tab: TabKey
  onTabChange: (tab: TabKey) => void
  busy: boolean
  onAddBreakdown: (payload: { title: string; description?: string | null; target_value?: number | null; progress?: number }) => void
  onUpdateBreakdown: (id: number, payload: { progress?: number; title?: string }) => void
  onDeleteBreakdown: (id: number) => void
  onAddMilestone: (payload: { title: string; threshold_percent: number; description?: string | null }) => void
  onUpdateMilestone: (id: number, payload: { title?: string; threshold_percent?: number }) => void
  onDeleteMilestone: (id: number) => void
  onRequestAdjustment: (payload: {
    reason: string
    request_type?: EmployeeTargetAdjustmentType
    proposed_target_value?: number | null
    proposed_target_unit?: string | null
    proposed_start_date?: string | null
    proposed_end_date?: string | null
    proposed_description?: string | null
  }) => void
  onAddNote: (body: string) => void
  onUpdateNote: (id: number, body: string) => void
  onDeleteNote: (id: number) => void
}) {
  const [breakdownOpen, setBreakdownOpen] = useState(false)
  const [milestoneOpen, setMilestoneOpen] = useState(false)
  const [adjustmentOpen, setAdjustmentOpen] = useState(false)
  const [noteBody, setNoteBody] = useState('')

  if (loading && detail === null) {
    return <div className="h-40 bg-gray-100 rounded-2xl animate-pulse" />
  }
  if (detail === null) return null

  const target = detail.target
  const tabs: Array<{ key: TabKey; label: string; count: number }> = [
    { key: 'breakdowns', label: 'Sub-targets', count: detail.breakdowns.length },
    { key: 'milestones', label: 'Milestones', count: detail.milestones.length },
    { key: 'adjustments', label: 'Adjustments', count: detail.adjustments.length },
    { key: 'notes', label: 'Notes', count: detail.notes.length },
  ]

  return (
    <section className="bg-white border border-gray-200 rounded-2xl">
      <div className="p-5 border-b border-gray-100">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <h2 className="text-lg font-bold text-gray-900 truncate">{target.title ?? 'Untitled target'}</h2>
            <p className="text-sm text-gray-500">{progressSourceNote(target)}</p>
          </div>
          <span
            className={`shrink-0 px-2.5 py-0.5 rounded-full text-xs font-semibold ${DERIVED_STATUS_TONE[target.derived_status]}`}
          >
            {DERIVED_STATUS_LABEL[target.derived_status]}
          </span>
        </div>
        <div className="mt-3 flex items-center gap-3">
          <div className="flex-1 bg-gray-100 rounded-full h-2.5">
            <div
              className="bg-blue-600 h-2.5 rounded-full transition-all"
              style={{ width: `${Math.min(100, target.progress ?? 0)}%` }}
            />
          </div>
          <span className="text-sm font-semibold text-gray-800 tabular-nums w-12 text-right">
            {target.progress === null ? '—' : `${target.progress}%`}
          </span>
        </div>
      </div>

      <div className="flex gap-1 px-5 pt-4 flex-wrap" role="tablist">
        {tabs.map((entry) => (
          <button
            key={entry.key}
            type="button"
            role="tab"
            aria-selected={tab === entry.key}
            onClick={() => onTabChange(entry.key)}
            className={`px-3 py-1.5 rounded-lg text-xs font-bold ${
              tab === entry.key ? 'bg-blue-50 text-blue-700' : 'text-gray-500 hover:text-gray-800'
            }`}
          >
            {entry.label} ({entry.count})
          </button>
        ))}
      </div>

      <div className="p-5">
        {tab === 'breakdowns' ? (
          <BreakdownsTab
            breakdowns={detail.breakdowns}
            busy={busy}
            onOpen={() => setBreakdownOpen(true)}
            onUpdate={(id, payload) => onUpdateBreakdown(id, payload)}
            onDelete={onDeleteBreakdown}
          />
        ) : null}
        {tab === 'milestones' ? (
          <MilestonesTab
            milestones={detail.milestones}
            progress={target.progress}
            busy={busy}
            onOpen={() => setMilestoneOpen(true)}
            onUpdate={onUpdateMilestone}
            onDelete={onDeleteMilestone}
          />
        ) : null}
        {tab === 'adjustments' ? (
          <AdjustmentsTab
            adjustments={detail.adjustments}
            busy={busy}
            onOpen={() => setAdjustmentOpen(true)}
          />
        ) : null}
        {tab === 'notes' ? (
          <NotesTab
            notes={detail.notes}
            busy={busy}
            draft={noteBody}
            onDraftChange={setNoteBody}
            onAdd={() => {
              if (noteBody.trim().length > 0) {
                onAddNote(noteBody.trim())
                setNoteBody('')
              }
            }}
            onUpdate={onUpdateNote}
            onDelete={onDeleteNote}
          />
        ) : null}
      </div>

      <BreakdownModal
        open={breakdownOpen}
        busy={busy}
        onClose={() => setBreakdownOpen(false)}
        onSubmit={(payload) => {
          onAddBreakdown(payload)
          setBreakdownOpen(false)
        }}
      />
      <MilestoneModal
        open={milestoneOpen}
        busy={busy}
        onClose={() => setMilestoneOpen(false)}
        onSubmit={(payload) => {
          onAddMilestone(payload)
          setMilestoneOpen(false)
        }}
      />
      <AdjustmentModal
        open={adjustmentOpen}
        busy={busy}
        onClose={() => setAdjustmentOpen(false)}
        onSubmit={(payload) => {
          onRequestAdjustment(payload)
          setAdjustmentOpen(false)
        }}
      />
    </section>
  )
}

/* ------------------------------- Tabs -------------------------------- */

function BreakdownsTab({
  breakdowns,
  busy,
  onOpen,
  onUpdate,
  onDelete,
}: {
  breakdowns: EmployeeTargetBreakdown[]
  busy: boolean
  onOpen: () => void
  onUpdate: (id: number, payload: { progress: number }) => void
  onDelete: (id: number) => void
}) {
  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-sm text-gray-500">Split this target into smaller sub-targets and track each individually.</p>
        <button
          type="button"
          onClick={onOpen}
          disabled={busy}
          className="shrink-0 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 disabled:opacity-50"
        >
          + Add sub-target
        </button>
      </div>

      {breakdowns.length === 0 ? (
        <p className="text-sm text-gray-400 py-6 text-center">No sub-targets yet.</p>
      ) : (
        <ul className="space-y-2">
          {breakdowns.map((breakdown) => (
            <li key={breakdown.id} className="border border-gray-200 rounded-xl p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="text-sm font-semibold text-gray-900">{breakdown.title}</p>
                  {breakdown.description ? (
                    <p className="text-xs text-gray-500 mt-0.5">{breakdown.description}</p>
                  ) : null}
                  <p className="text-[11px] text-gray-400 mt-1">
                    {breakdown.target_value === null ? '' : `${breakdown.target_value} `}
                    {breakdown.target_unit ?? ''}
                    {breakdown.completed_at ? ` · completed ${formatDate(breakdown.completed_at)}` : ''}
                  </p>
                </div>
                <span
                  className={`shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold ${
                    breakdown.status === 'completed'
                      ? 'bg-green-100 text-green-700'
                      : breakdown.status === 'in_progress'
                        ? 'bg-blue-100 text-blue-700'
                        : 'bg-gray-100 text-gray-600'
                  }`}
                >
                  {breakdown.status.replace('_', ' ')}
                </span>
              </div>

              <div className="mt-3 flex items-center gap-3">
                <input
                  type="range"
                  min={0}
                  max={100}
                  step={5}
                  value={breakdown.progress}
                  disabled={busy}
                  onChange={(e) => onUpdate(breakdown.id, { progress: Number(e.target.value) })}
                  className="flex-1 accent-blue-600"
                  aria-label={`${breakdown.title} progress`}
                />
                <span className="text-xs font-semibold text-gray-700 tabular-nums w-10 text-right">
                  {breakdown.progress}%
                </span>
                <button
                  type="button"
                  onClick={() => onDelete(breakdown.id)}
                  disabled={busy}
                  className="text-xs font-semibold text-red-600 hover:underline disabled:opacity-50"
                >
                  Remove
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function MilestonesTab({
  milestones,
  progress,
  busy,
  onOpen,
  onUpdate,
  onDelete,
}: {
  milestones: EmployeeTargetMilestone[]
  progress: number | null
  busy: boolean
  onOpen: () => void
  onUpdate: (id: number, payload: { title?: string; threshold_percent?: number }) => void
  onDelete: (id: number) => void
}) {
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editTitle, setEditTitle] = useState('')
  const [editThreshold, setEditThreshold] = useState('')

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-sm text-gray-500">
          Checkpoints reached automatically as progress passes each threshold.
        </p>
        <button
          type="button"
          onClick={onOpen}
          disabled={busy}
          className="shrink-0 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 disabled:opacity-50"
        >
          + Add milestone
        </button>
      </div>

      {milestones.length === 0 ? (
        <p className="text-sm text-gray-400 py-6 text-center">No milestones yet.</p>
      ) : (
        <ol className="space-y-2">
          {milestones.map((milestone) => {
            const achieved = milestone.status === 'achieved'

            if (editingId === milestone.id) {
              const threshold = Number(editThreshold)
              return (
                <li key={milestone.id} className="border border-gray-200 rounded-xl p-4 space-y-2">
                  <input
                    value={editTitle}
                    onChange={(e) => setEditTitle(e.target.value)}
                    maxLength={255}
                    aria-label="Milestone title"
                    className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <div className="flex items-center gap-2">
                    <input
                      type="number"
                      min={1}
                      max={100}
                      value={editThreshold}
                      onChange={(e) => setEditThreshold(e.target.value)}
                      aria-label="Milestone threshold percent"
                      className="w-24 rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    />
                    <span className="text-xs text-gray-500">% threshold</span>
                    <div className="ml-auto flex gap-2">
                      <button
                        type="button"
                        onClick={() => setEditingId(null)}
                        className="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-bold text-gray-600"
                      >
                        Cancel
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          if (editTitle.trim().length > 0 && threshold >= 1 && threshold <= 100) {
                            onUpdate(milestone.id, {
                              title: editTitle.trim(),
                              threshold_percent: threshold,
                            })
                            setEditingId(null)
                          }
                        }}
                        disabled={busy || editTitle.trim().length === 0 || !(threshold >= 1 && threshold <= 100)}
                        className="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 disabled:opacity-50"
                      >
                        Save
                      </button>
                    </div>
                  </div>
                </li>
              )
            }

            return (
              <li key={milestone.id} className="flex items-start gap-3 border border-gray-200 rounded-xl p-4">
                <span
                  className={`mt-0.5 w-6 h-6 shrink-0 grid place-items-center rounded-full text-xs font-bold ${
                    achieved ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                  }`}
                  aria-hidden="true"
                >
                  {achieved ? '✓' : milestone.threshold_percent}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-semibold text-gray-900">{milestone.title}</p>
                  {milestone.description ? (
                    <p className="text-xs text-gray-500 mt-0.5">{milestone.description}</p>
                  ) : null}
                  <p className="text-[11px] text-gray-400 mt-1">
                    Reaches {milestone.threshold_percent}%
                    {achieved && milestone.achieved_at ? ` · achieved ${formatDate(milestone.achieved_at)}` : ''}
                    {!achieved && progress !== null ? ` · current ${progress}%` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <span
                    className={`shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold ${
                      achieved ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'
                    }`}
                  >
                    {achieved ? 'achieved' : 'pending'}
                  </span>
                  <button
                    type="button"
                    onClick={() => {
                      setEditingId(milestone.id)
                      setEditTitle(milestone.title)
                      setEditThreshold(String(milestone.threshold_percent))
                    }}
                    className="text-xs font-semibold text-blue-600 hover:underline"
                  >
                    Edit
                  </button>
                  <button
                    type="button"
                    onClick={() => onDelete(milestone.id)}
                    disabled={busy}
                    className="text-xs font-semibold text-red-600 hover:underline disabled:opacity-50"
                  >
                    Remove
                  </button>
                </div>
              </li>
            )
          })}
        </ol>
      )}
    </div>
  )
}

function AdjustmentsTab({
  adjustments,
  busy,
  onOpen,
}: {
  adjustments: EmployeeTargetAdjustment[]
  busy: boolean
  onOpen: () => void
}) {
  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-sm text-gray-500">
          Request a change to this target. An administrator reviews every request.
        </p>
        <button
          type="button"
          onClick={onOpen}
          disabled={busy}
          className="shrink-0 px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 disabled:opacity-50"
        >
          + Request adjustment
        </button>
      </div>

      {adjustments.length === 0 ? (
        <p className="text-sm text-gray-400 py-6 text-center">No adjustment requests yet.</p>
      ) : (
        <ul className="space-y-2">
          {adjustments.map((request) => (
            <li key={request.id} className="border border-gray-200 rounded-xl p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="text-sm font-semibold text-gray-900">
                    {request.request_type ? request.request_type.replace('_', ' ') : 'Adjustment'}
                  </p>
                  <p className="text-xs text-gray-500 mt-0.5">{request.reason}</p>
                </div>
                <span
                  className={`shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold ${
                    ADJUSTMENT_TONE[request.status] ?? 'bg-gray-100 text-gray-600'
                  }`}
                >
                  {request.status}
                </span>
              </div>
              <p className="text-[11px] text-gray-400 mt-2">
                Requested {formatDate(request.created_at)}
                {request.reviewed_by ? ` · reviewed by ${request.reviewed_by}` : ''}
                {request.applied_at ? ` · applied ${formatDate(request.applied_at)}` : ''}
              </p>
              {request.decision_note ? (
                <p className="text-xs text-gray-600 mt-1 bg-gray-50 rounded-lg px-3 py-2">
                  Decision note: {request.decision_note}
                </p>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function NotesTab({
  notes,
  busy,
  draft,
  onDraftChange,
  onAdd,
  onUpdate,
  onDelete,
}: {
  notes: EmployeeTargetNote[]
  busy: boolean
  draft: string
  onDraftChange: (value: string) => void
  onAdd: () => void
  onUpdate: (id: number, body: string) => void
  onDelete: (id: number) => void
}) {
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editingBody, setEditingBody] = useState('')

  return (
    <div className="space-y-4">
      <div>
        <label className="text-[11px] font-bold uppercase tracking-wider text-gray-500" htmlFor="target-note">
          Add an achievement note
        </label>
        <textarea
          id="target-note"
          value={draft}
          onChange={(e) => onDraftChange(e.target.value)}
          rows={3}
          maxLength={5000}
          placeholder="Record progress, evidence or accomplishments for this target…"
          className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
        />
        <div className="mt-2 flex justify-end">
          <button
            type="button"
            onClick={onAdd}
            disabled={busy || draft.trim().length === 0}
            className="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 disabled:opacity-50"
          >
            Add note
          </button>
        </div>
      </div>

      {notes.length === 0 ? (
        <p className="text-sm text-gray-400 py-4 text-center">No notes yet.</p>
      ) : (
        <ul className="space-y-2">
          {notes.map((note) => (
            <li key={note.id} className="border border-gray-200 rounded-xl p-4">
              {editingId === note.id ? (
                <div className="space-y-2">
                  <textarea
                    value={editingBody}
                    onChange={(e) => setEditingBody(e.target.value)}
                    rows={3}
                    maxLength={5000}
                    className="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
                  />
                  <div className="flex justify-end gap-2">
                    <button
                      type="button"
                      onClick={() => setEditingId(null)}
                      className="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-bold text-gray-600"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        if (editingBody.trim().length > 0) {
                          onUpdate(note.id, editingBody.trim())
                          setEditingId(null)
                        }
                      }}
                      disabled={busy}
                      className="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 disabled:opacity-50"
                    >
                      Save
                    </button>
                  </div>
                </div>
              ) : (
                <>
                  <p className="text-sm text-gray-800 whitespace-pre-wrap">{note.body}</p>
                  <div className="mt-2 flex items-center justify-between">
                    <p className="text-[11px] text-gray-400">
                      {note.author ? `${note.author} · ` : ''}
                      {formatDate(note.created_at)}
                    </p>
                    <div className="flex gap-3">
                      <button
                        type="button"
                        onClick={() => {
                          setEditingId(note.id)
                          setEditingBody(note.body)
                        }}
                        className="text-xs font-semibold text-blue-600 hover:underline"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        onClick={() => onDelete(note.id)}
                        disabled={busy}
                        className="text-xs font-semibold text-red-600 hover:underline disabled:opacity-50"
                      >
                        Delete
                      </button>
                    </div>
                  </div>
                </>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

/* ------------------------------ Modals ------------------------------- */

function BreakdownModal({
  open,
  busy,
  onClose,
  onSubmit,
}: {
  open: boolean
  busy: boolean
  onClose: () => void
  onSubmit: (payload: { title: string; description?: string | null; target_value?: number | null; progress?: number }) => void
}) {
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [targetValue, setTargetValue] = useState('')
  const [progress, setProgress] = useState('0')
  const [error, setError] = useState<string | null>(null)

  const reset = () => {
    setTitle('')
    setDescription('')
    setTargetValue('')
    setProgress('0')
    setError(null)
  }

  return (
    <Modal
      open={open}
      title="Add sub-target"
      onClose={() => {
        reset()
        onClose()
      }}
      footer={
        <>
          <button
            type="button"
            onClick={() => {
              reset()
              onClose()
            }}
            className="px-4 py-2 rounded-lg border border-gray-300 text-sm font-bold text-gray-600"
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={busy}
            onClick={() => {
              if (title.trim().length === 0) {
                setError('A title is required.')
                return
              }
              onSubmit({
                title: title.trim(),
                description: description.trim() || null,
                target_value: targetValue.trim() === '' ? null : Number(targetValue),
                progress: Number(progress),
              })
              reset()
            }}
            className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 disabled:opacity-50"
          >
            Add
          </button>
        </>
      }
    >
      <div className="space-y-3">
        {error ? <p className="text-sm text-red-600">{error}</p> : null}
        <label className="block">
          <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Title *</span>
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            maxLength={200}
            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
        </label>
        <label className="block">
          <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Description</span>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={2}
            maxLength={2000}
            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
        </label>
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Target value</span>
            <input
              type="number"
              min={0}
              value={targetValue}
              onChange={(e) => setTargetValue(e.target.value)}
              className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
          </label>
          <label className="block">
            <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Progress %</span>
            <input
              type="number"
              min={0}
              max={100}
              value={progress}
              onChange={(e) => setProgress(e.target.value)}
              className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
          </label>
        </div>
      </div>
    </Modal>
  )
}

function MilestoneModal({
  open,
  busy,
  onClose,
  onSubmit,
}: {
  open: boolean
  busy: boolean
  onClose: () => void
  onSubmit: (payload: { title: string; threshold_percent: number; description?: string | null }) => void
}) {
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [threshold, setThreshold] = useState('25')
  const [error, setError] = useState<string | null>(null)

  const reset = () => {
    setTitle('')
    setDescription('')
    setThreshold('25')
    setError(null)
  }

  return (
    <Modal
      open={open}
      title="Add milestone"
      onClose={() => {
        reset()
        onClose()
      }}
      footer={
        <>
          <button
            type="button"
            onClick={() => {
              reset()
              onClose()
            }}
            className="px-4 py-2 rounded-lg border border-gray-300 text-sm font-bold text-gray-600"
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={busy}
            onClick={() => {
              const value = Number(threshold)
              if (title.trim().length === 0) {
                setError('A title is required.')
                return
              }
              if (!Number.isFinite(value) || value < 1 || value > 100) {
                setError('Threshold must be between 1 and 100.')
                return
              }
              onSubmit({ title: title.trim(), threshold_percent: value, description: description.trim() || null })
              reset()
            }}
            className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 disabled:opacity-50"
          >
            Add
          </button>
        </>
      }
    >
      <div className="space-y-3">
        {error ? <p className="text-sm text-red-600">{error}</p> : null}
        <label className="block">
          <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Title *</span>
          <input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            maxLength={200}
            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
        </label>
        <label className="block">
          <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Description</span>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={2}
            maxLength={2000}
            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
        </label>
        <label className="block">
          <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Threshold % *</span>
          <input
            type="number"
            min={1}
            max={100}
            value={threshold}
            onChange={(e) => setThreshold(e.target.value)}
            className="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
          <span className="text-[11px] text-gray-400">
            The milestone is marked achieved automatically once target progress reaches this value.
          </span>
        </label>
      </div>
    </Modal>
  )
}

function AdjustmentModal({
  open,
  busy,
  onClose,
  onSubmit,
}: {
  open: boolean
  busy: boolean
  onClose: () => void
  onSubmit: (payload: {
    reason: string
    request_type?: EmployeeTargetAdjustmentType
    proposed_target_value?: number | null
    proposed_target_unit?: string | null
    proposed_start_date?: string | null
    proposed_end_date?: string | null
    proposed_description?: string | null
  }) => void
}) {
  const [reason, setReason] = useState('')
  const [requestType, setRequestType] = useState<EmployeeTargetAdjustmentType>('target_value')
  const [proposedValue, setProposedValue] = useState('')
  const [proposedUnit, setProposedUnit] = useState('')
  const [proposedStart, setProposedStart] = useState('')
  const [proposedEnd, setProposedEnd] = useState('')
  const [proposedDescription, setProposedDescription] = useState('')
  const [error, setError] = useState<string | null>(null)

  const reset = () => {
    setReason('')
    setRequestType('target_value')
    setProposedValue('')
    setProposedUnit('')
    setProposedStart('')
    setProposedEnd('')
    setProposedDescription('')
    setError(null)
  }

  const inputClass =
    'mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100'

  return (
    <Modal
      open={open}
      title="Request target adjustment"
      onClose={() => {
        reset()
        onClose()
      }}
      footer={
        <>
          <button
            type="button"
            onClick={() => {
              reset()
              onClose()
            }}
            className="px-4 py-2 rounded-lg border border-gray-300 text-sm font-bold text-gray-600"
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={busy}
            onClick={() => {
              if (reason.trim().length < 5) {
                setError('Please give a reason of at least 5 characters.')
                return
              }
              onSubmit({
                reason: reason.trim(),
                request_type: requestType,
                proposed_target_value: proposedValue.trim() === '' ? null : Number(proposedValue),
                proposed_target_unit: proposedUnit.trim() || null,
                proposed_start_date: proposedStart || null,
                proposed_end_date: proposedEnd || null,
                proposed_description: proposedDescription.trim() || null,
              })
              reset()
            }}
            className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 disabled:opacity-50"
          >
            Submit request
          </button>
        </>
      }
    >
      <div className="space-y-3">
        {error ? <p className="text-sm text-red-600">{error}</p> : null}
        <label className="block">
          <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Reason *</span>
          <textarea
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            rows={3}
            maxLength={2000}
            placeholder="Explain why this target needs to change…"
            className={inputClass}
          />
        </label>
        <label className="block">
          <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Type of change</span>
          <select
            value={requestType}
            onChange={(e) => setRequestType(e.target.value as EmployeeTargetAdjustmentType)}
            className={inputClass}
          >
            {ADJUSTMENT_TYPES.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        {requestType === 'target_value' ? (
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">New target value</span>
              <input type="number" min={0} value={proposedValue} onChange={(e) => setProposedValue(e.target.value)} className={inputClass} />
            </label>
            <label className="block">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Unit</span>
              <input value={proposedUnit} onChange={(e) => setProposedUnit(e.target.value)} maxLength={50} className={inputClass} />
            </label>
          </div>
        ) : null}

        {requestType === 'deadline' || requestType === 'period' ? (
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Proposed start</span>
              <input type="date" value={proposedStart} onChange={(e) => setProposedStart(e.target.value)} className={inputClass} />
            </label>
            <label className="block">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Proposed end</span>
              <input type="date" value={proposedEnd} onChange={(e) => setProposedEnd(e.target.value)} className={inputClass} />
            </label>
          </div>
        ) : null}

        {requestType === 'description' ? (
          <label className="block">
            <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Proposed description</span>
            <textarea
              value={proposedDescription}
              onChange={(e) => setProposedDescription(e.target.value)}
              rows={3}
              maxLength={5000}
              className={inputClass}
            />
          </label>
        ) : null}
      </div>
    </Modal>
  )
}
