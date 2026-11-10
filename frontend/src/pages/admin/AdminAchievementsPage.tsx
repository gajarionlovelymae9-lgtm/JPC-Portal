import { useState } from 'react'
import {
  createAdminBadge,
  deleteAdminBadge,
  evaluateAdminAchievements,
  getAdminAchievementOptions,
  getAdminAchievements,
  getAdminAchievementSummaries,
  getAdminAwardDetail,
  getAdminBadgeAwards,
  getAdminBadgeDefinitions,
  rejectAdminAward,
  updateAdminBadge,
  verifyAdminAward,
  type AwardDetail,
  type BadgeDefinition,
  type BadgeDefinitionPayload,
} from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import { ConfirmDialog } from '../../components/common/ConfirmDialog'
import { RecognitionEvidencePanel } from '../../components/achievements/RecognitionEvidencePanel'
import {
  CRITERIA_PERIOD_OPTIONS,
  criteriaToGuided,
  buildCriteriaValue,
  parseCriteriaJson,
  describeCriteriaSummary,
} from '../../utils/badgeCriteria'
import {
  AdminPage,
  Badge,
  Button,
  EmptyState,
  ErrorPanel,
  ExportButton,
  Field,
  FilterBar,
  LoadingPanel,
  Modal,
  Pagination,
  Panel,
  Select,
  StatCard,
  Table,
  Td,
  TextInput,
  Th,
  dateTime,
  humanise,
  num,
  pct,
  shortDate,
} from '../../components/admin/AdminUi'
import { RankingBars, TrendChart, CHART_COLOURS } from '../../components/admin/AdminCharts'

/**
 * Admin Achievement & Badges management.
 *
 * Three real sources, never merged: monthly_achievements rollups, the badge
 * definition catalogue, and employee_badges award grants (with the pending
 * verification workflow). Every figure comes from the API; nothing is
 * estimated in the browser.
 */
type TabKey = 'records' | 'definitions' | 'summaries'

const ICON_OPTIONS = [
  'award',
  'clock',
  'target',
  'trending-up',
  'check-circle',
  'check-square',
  'file-text',
  'repeat',
  'star',
  'user-check',
]

const CRITERIA_TYPE_OPTIONS = [
  'overall_score',
  'on_time_rate',
  'targets_completed',
  'consecutive_months',
  'completed_tasks',
  'approved_reports',
  'monthly_rank',
]

const BADGE_CATEGORIES = ['Performance', 'Punctuality', 'Goals', 'Progress']

type BadgeFormState = {
  name: string
  code: string
  description: string
  icon: string
  category: string
  criteria_type: string
  /** Guided form for the recognised criteria shape; raw JSON otherwise. */
  criteria_mode: 'guided' | 'json'
  criteria_period: string
  criteria_threshold: string
  criteria_min_score: string
  criteria_json: string
  is_active: boolean
  allow_repeats: boolean
}

const BLANK_BADGE_FORM: BadgeFormState = {
  name: '',
  code: '',
  description: '',
  icon: 'star',
  category: 'Performance',
  criteria_type: 'overall_score',
  criteria_mode: 'guided',
  criteria_period: 'monthly',
  criteria_threshold: '',
  criteria_min_score: '',
  criteria_json: '',
  is_active: true,
  allow_repeats: false,
}

function verificationTone(status: string | null | undefined): string {
  if (status === 'verified') return 'ok'
  if (status === 'pending') return 'warn'
  if (status === 'rejected') return 'danger'
  return 'neutral'
}

export default function AdminAchievementsPage() {
  const [tab, setTab] = useState<TabKey>('records')

  const [departmentId, setDepartmentId] = useState('')
  const [academicYearId, setAcademicYearId] = useState('')

  const [badgeId, setBadgeId] = useState('')
  const [awardCategory, setAwardCategory] = useState('')
  const [status, setStatus] = useState('')
  const [awardSearch, setAwardSearch] = useState('')
  const [awardsPage, setAwardsPage] = useState(1)

  const [year, setYear] = useState('')
  const [month, setMonth] = useState('')
  const [rollupSearch, setRollupSearch] = useState('')
  const [rollupsPage, setRollupsPage] = useState(1)

  const [defCategory, setDefCategory] = useState('')
  const [defActive, setDefActive] = useState('')
  const [defSearch, setDefSearch] = useState('')
  const [defPage, setDefPage] = useState(1)

  const [badgeModalOpen, setBadgeModalOpen] = useState(false)
  const [editingBadge, setEditingBadge] = useState<BadgeDefinition | null>(null)
  const [form, setForm] = useState<BadgeFormState>(BLANK_BADGE_FORM)
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  const [archiveTarget, setArchiveTarget] = useState<BadgeDefinition | null>(null)
  const [archiveBusy, setArchiveBusy] = useState(false)
  const [archiveError, setArchiveError] = useState('')

  const [awardDetailId, setAwardDetailId] = useState<number | null>(null)
  const [awardDetail, setAwardDetail] = useState<AwardDetail | null>(null)
  const [awardDetailLoading, setAwardDetailLoading] = useState(false)
  const [awardActionError, setAwardActionError] = useState('')
  const [awardBusy, setAwardBusy] = useState(false)
  const [rejectMode, setRejectMode] = useState(false)
  const [rejectReason, setRejectReason] = useState('')

  const [evaluateOpen, setEvaluateOpen] = useState(false)
  const now = new Date()
  const [evalYear, setEvalYear] = useState(String(now.getFullYear()))
  const [evalMonth, setEvalMonth] = useState(String(now.getMonth() + 1))
  const [evalBusy, setEvalBusy] = useState(false)
  const [evalError, setEvalError] = useState('')
  const [evalResult, setEvalResult] = useState<{
    period: string
    total_evaluated: number
    employee_of_the_month: string | null
  } | null>(null)

  const options = useAdminResource(() => getAdminAchievementOptions())
  const summaries = useAdminResource(
    () => getAdminAchievementSummaries({ department_id: departmentId, academic_year_id: academicYearId }),
    [departmentId, academicYearId],
  )
  const awards = useAdminResource(
    () =>
      getAdminBadgeAwards({
        badge_id: badgeId,
        department_id: departmentId,
        academic_year_id: academicYearId,
        category: awardCategory,
        status,
        search: awardSearch,
        page: awardsPage,
        per_page: 12,
      }),
    [badgeId, departmentId, academicYearId, awardCategory, status, awardSearch, awardsPage],
  )
  const rollups = useAdminResource(
    () => getAdminAchievements({ year, month, department_id: departmentId, search: rollupSearch, page: rollupsPage, per_page: 12 }),
    [year, month, departmentId, rollupSearch, rollupsPage],
  )
  const definitions = useAdminResource(
    () =>
      getAdminBadgeDefinitions({
        category: defCategory,
        is_active: defActive,
        search: defSearch,
        page: defPage,
        per_page: 12,
      }),
    [defCategory, defActive, defSearch, defPage],
  )

  const cards = summaries.data?.cards
  const formOpen = badgeModalOpen

  const setShared = (setter: (value: string) => void) => (value: string) => {
    setter(value)
    setAwardsPage(1)
    setRollupsPage(1)
  }

  const openCreateBadge = () => {
    setEditingBadge(null)
    setForm(BLANK_BADGE_FORM)
    setFormError('')
    setBadgeModalOpen(true)
  }

  const openEditBadge = (badge: BadgeDefinition) => {
    const guided = criteriaToGuided(badge.criteria_value)
    setEditingBadge(badge)
    setForm({
      name: badge.name,
      code: badge.code,
      description: badge.description ?? '',
      icon: badge.icon ?? 'star',
      category: badge.category ?? '',
      criteria_type: badge.criteria_type ?? '',
      criteria_mode: guided ? 'guided' : 'json',
      criteria_period: guided?.period ?? 'monthly',
      criteria_threshold: guided?.threshold ?? '',
      criteria_min_score: guided?.minScore ?? '',
      // Keep the original JSON so switching to the raw editor never loses
      // unknown properties (the guided form is only offered when it can
      // round-trip the whole object safely).
      criteria_json: badge.criteria_value ? JSON.stringify(badge.criteria_value, null, 2) : '',
      is_active: badge.is_active,
      allow_repeats: badge.allow_repeats,
    })
    setFormError('')
    setBadgeModalOpen(true)
  }

  const closeBadgeModal = () => {
    setBadgeModalOpen(false)
    setEditingBadge(null)
    setFormError('')
  }

  const saveBadge = async () => {
    const name = form.name.trim()
    const code = form.code.trim().toUpperCase()
    if (!name || !code) {
      setFormError('Name and code are required.')
      return
    }

    let criteriaValue: Record<string, unknown> | null = null
    if (form.criteria_mode === 'guided') {
      const built = buildCriteriaValue({
        period: form.criteria_period,
        threshold: form.criteria_threshold,
        minScore: form.criteria_min_score,
      })
      if (!built.ok) {
        setFormError(built.error)
        return
      }
      criteriaValue = built.value
    } else {
      const parsed = parseCriteriaJson(form.criteria_json)
      if (!parsed.ok) {
        setFormError(parsed.error)
        return
      }
      criteriaValue = parsed.value
    }

    const payload: BadgeDefinitionPayload = {
      name,
      code,
      description: form.description.trim() || null,
      icon: form.icon || null,
      category: form.category.trim() || null,
      criteria_type: form.criteria_type || null,
      criteria_value: criteriaValue,
      is_active: form.is_active,
      allow_repeats: form.allow_repeats,
    }

    setSaving(true)
    setFormError('')
    try {
      if (editingBadge) {
        await updateAdminBadge(editingBadge.id, payload)
      } else {
        await createAdminBadge(payload)
      }
      closeBadgeModal()
      definitions.reload()
      summaries.reload()
      options.reload()
    } catch (cause) {
      setFormError(cause instanceof Error ? cause.message : 'Unable to save the badge.')
    } finally {
      setSaving(false)
    }
  }

  const switchCriteriaMode = () => {
    setFormError('')

    if (form.criteria_mode === 'guided') {
      const built = buildCriteriaValue({
        period: form.criteria_period,
        threshold: form.criteria_threshold,
        minScore: form.criteria_min_score,
      })
      if (!built.ok) {
        setFormError(built.error)
        return
      }
      setForm({
        ...form,
        criteria_mode: 'json',
        criteria_json: built.value ? JSON.stringify(built.value, null, 2) : '',
      })
      return
    }

    const parsed = parseCriteriaJson(form.criteria_json)
    if (!parsed.ok) {
      setFormError(parsed.error)
      return
    }
    const guided = criteriaToGuided(parsed.value)
    if (guided === null) {
      setFormError(
        'This criteria contains custom properties that the guided form cannot represent. Keep editing it as JSON so no data is discarded.',
      )
      return
    }
    setForm({
      ...form,
      criteria_mode: 'guided',
      criteria_period: guided.period,
      criteria_threshold: guided.threshold,
      criteria_min_score: guided.minScore,
    })
  }

  const confirmArchive = async () => {
    if (!archiveTarget) return
    setArchiveBusy(true)
    setArchiveError('')
    try {
      await deleteAdminBadge(archiveTarget.id)
      setArchiveTarget(null)
      definitions.reload()
      summaries.reload()
      options.reload()
    } catch (cause) {
      setArchiveError(cause instanceof Error ? cause.message : 'Unable to remove the badge.')
    } finally {
      setArchiveBusy(false)
    }
  }

  const openAwardDetail = async (id: number) => {
    setAwardDetailId(id)
    setAwardDetail(null)
    setAwardActionError('')
    setRejectMode(false)
    setRejectReason('')
    setAwardDetailLoading(true)
    try {
      const response = await getAdminAwardDetail(id)
      setAwardDetail(response.data)
    } catch (cause) {
      setAwardActionError(cause instanceof Error ? cause.message : 'Unable to load the award.')
    } finally {
      setAwardDetailLoading(false)
    }
  }

  const closeAwardDetail = () => {
    setAwardDetailId(null)
    setAwardDetail(null)
    setRejectMode(false)
    setRejectReason('')
    setAwardActionError('')
  }

  const runAwardAction = async (action: 'verify' | 'reject') => {
    if (!awardDetailId) return
    setAwardBusy(true)
    setAwardActionError('')
    try {
      const response =
        action === 'verify'
          ? await verifyAdminAward(awardDetailId)
          : await rejectAdminAward(awardDetailId, rejectReason.trim())
      setAwardDetail(response.data)
      setRejectMode(false)
      setRejectReason('')
      awards.reload()
      summaries.reload()
    } catch (cause) {
      setAwardActionError(cause instanceof Error ? cause.message : 'Unable to update the award.')
    } finally {
      setAwardBusy(false)
    }
  }

  const runEvaluation = async () => {
    setEvalBusy(true)
    setEvalError('')
    setEvalResult(null)
    try {
      const response = await evaluateAdminAchievements(Number(evalYear), Number(evalMonth))
      setEvalResult(response.data)
      rollups.reload()
      awards.reload()
      summaries.reload()
    } catch (cause) {
      setEvalError(cause instanceof Error ? cause.message : 'Evaluation failed.')
    } finally {
      setEvalBusy(false)
    }
  }

  const evalYearOptions = (() => {
    const years = new Set((options.data?.years ?? []).map((entry) => entry.value))
    years.add(now.getFullYear())
    return [...years]
      .sort((a, b) => b - a)
      .map((value) => ({ value, label: String(value) }))
  })()

  return (
    <AdminPage
      title="Achievement & Badges"
      subtitle="Badge catalogue, award verification and achievement summaries"
      actions={
        <div className="flex items-center gap-3">
          <ExportButton exportKey="achievements" filters={{ year, month, department_id: departmentId }} />
          <Button variant="secondary" onClick={() => setEvaluateOpen(true)}>
            Evaluate month
          </Button>
        </div>
      }
    >
      {cards ? (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard label="Active badges" value={num(cards.total_active_badges)} />
          <StatCard label="Achievements awarded" value={num(cards.total_achievements_awarded)} tone="ok" />
          <StatCard label="Pending reviews" value={num(cards.pending_reviews)} tone={cards.pending_reviews > 0 ? 'warn' : 'neutral'} />
          <StatCard label="Employees recognized" value={num(cards.employees_recognized)} tone="info" />
        </div>
      ) : summaries.error ? (
        <ErrorPanel message={summaries.error} onRetry={summaries.reload} />
      ) : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {[0, 1, 2, 3].map((index) => (
            <div key={index} className="h-24 rounded-2xl bg-slate-100 animate-pulse" />
          ))}
        </div>
      )}

      <div className="flex gap-1 border-b border-slate-200">
        {(
          [
            ['records', 'Achievement records'],
            ['definitions', 'Badge definitions'],
            ['summaries', 'Summaries'],
          ] as Array<[TabKey, string]>
        ).map(([key, label]) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={`px-4 py-2.5 text-sm font-bold border-b-2 -mb-px transition ${
              tab === key
                ? 'border-jpc-600 text-jpc-700'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'records' ? (
        <div className="space-y-6">
          <FilterBar>
            <Field label="Department">
              <Select
                value={departmentId}
                onChange={setShared(setDepartmentId)}
                options={(options.data?.departments ?? []).map((entry) => ({ value: entry.id, label: entry.name }))}
                placeholder="All departments"
              />
            </Field>
            <Field label="Academic year">
              <Select
                value={academicYearId}
                onChange={setShared(setAcademicYearId)}
                options={(options.data?.academic_years ?? []).map((entry) => ({ value: entry.id, label: entry.name }))}
                placeholder="All years"
              />
            </Field>
            <Field label="Badge">
              <Select
                value={badgeId}
                onChange={(value) => {
                  setBadgeId(value)
                  setAwardsPage(1)
                }}
                options={(options.data?.badges ?? []).map((entry) => ({ value: entry.id, label: entry.name }))}
                placeholder="All badges"
              />
            </Field>
            <Field label="Category">
              <Select
                value={awardCategory}
                onChange={(value) => {
                  setAwardCategory(value)
                  setAwardsPage(1)
                }}
                options={options.data?.categories ?? []}
                placeholder="All categories"
              />
            </Field>
            <Field label="Verification">
              <Select
                value={status}
                onChange={(value) => {
                  setStatus(value)
                  setAwardsPage(1)
                }}
                options={options.data?.statuses ?? []}
                placeholder="Any status"
              />
            </Field>
            <Field label="Search" className="flex-1 min-w-[180px]">
              <TextInput
                value={awardSearch}
                onChange={(value) => {
                  setAwardSearch(value)
                  setAwardsPage(1)
                }}
                placeholder="Employee or badge name"
              />
            </Field>
          </FilterBar>

          <Panel
            title="Badge awards"
            description={`${awards.data?.meta.total ?? 0} grants recorded in employee_badges; pending awards need review before they count as recognition`}
          >
            {awards.loading ? (
              <p className="text-sm text-slate-400">Loading badge awards…</p>
            ) : (awards.data?.items.length ?? 0) === 0 ? (
              <EmptyState title="No badge awards" message="No badge grants match these filters." />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Employee</Th>
                      <Th>Department</Th>
                      <Th>Badge</Th>
                      <Th>Academic year</Th>
                      <Th>Period</Th>
                      <Th>Awarded</Th>
                      <Th>Verification</Th>
                      <Th>Actions</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {awards.data?.items.map((award) => (
                      <tr key={award.id} className="border-b border-slate-100 last:border-0">
                        <Td>
                          <p className="font-semibold text-slate-800">{award.name}</p>
                          <p className="text-[11px] text-slate-400">{award.employee_number}</p>
                        </Td>
                        <Td className="text-slate-500">{award.department ?? '—'}</Td>
                        <Td>
                          <p className="text-slate-800">{award.badge}</p>
                          <p className="text-[11px] font-mono text-slate-400">{award.badge_code}</p>
                          {award.badge_category ? (
                            <Badge tone="info">{award.badge_category}</Badge>
                          ) : null}
                        </Td>
                        <Td className="text-slate-500 text-xs">{award.academic_year ?? '—'}</Td>
                        <Td className="text-slate-500 text-xs">
                          {shortDate(award.period_start)} → {shortDate(award.period_end)}
                        </Td>
                        <Td className="text-slate-500 text-xs">{shortDate(award.awarded_at)}</Td>
                        <Td>
                          <Badge tone={verificationTone(award.verification_status)}>
                            {humanise(award.verification_status)}
                          </Badge>
                          {award.rejection_reason ? (
                            <p className="text-[11px] text-red-600 mt-1 max-w-[180px]">{award.rejection_reason}</p>
                          ) : null}
                        </Td>
                        <Td>
                          <Button variant="ghost" onClick={() => openAwardDetail(award.id)}>
                            View
                          </Button>
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                {awards.data ? <Pagination meta={awards.data.meta} onPageChange={setAwardsPage} /> : null}
              </>
            )}
          </Panel>

          <Panel title="Monthly achievement rollups" description="Per-employee performance scores mirrored from real task and report data">
            <div className="flex flex-wrap items-end gap-3 pb-4">
              <Field label="Year">
                <Select
                  value={year}
                  onChange={(value) => {
                    setYear(value)
                    setRollupsPage(1)
                  }}
                  options={options.data?.years ?? []}
                  placeholder="All years"
                />
              </Field>
              <Field label="Month">
                <Select
                  value={month}
                  onChange={(value) => {
                    setMonth(value)
                    setRollupsPage(1)
                  }}
                  options={options.data?.months ?? []}
                  placeholder="All months"
                />
              </Field>
              <Field label="Search" className="flex-1 min-w-[180px]">
                <TextInput
                  value={rollupSearch}
                  onChange={(value) => {
                    setRollupSearch(value)
                    setRollupsPage(1)
                  }}
                  placeholder="Employee name or number"
                />
              </Field>
            </div>
            {rollups.loading ? (
              <LoadingPanel label="Loading rollups" rows={2} />
            ) : rollups.error || !rollups.data ? (
              <ErrorPanel message={rollups.error ?? 'Unable to load rollups.'} onRetry={rollups.reload} />
            ) : rollups.data.items.length === 0 ? (
              <EmptyState title="No achievement records" message="Try clearing a filter to widen the search." />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Period</Th>
                      <Th>Employee</Th>
                      <Th>Department</Th>
                      <Th className="text-right">Completion</Th>
                      <Th className="text-right">On time</Th>
                      <Th className="text-right">Approved reports</Th>
                      <Th className="text-right">Completed tasks</Th>
                      <Th className="text-right">Score</Th>
                      <Th className="text-right">Rank</Th>
                      <Th>Recognition</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {rollups.data.items.map((row) => (
                      <tr key={row.id} className="border-b border-slate-100 last:border-0">
                        <Td className="font-medium text-slate-800">{row.period}</Td>
                        <Td>
                          <p className="font-semibold text-slate-800">{row.name}</p>
                          <p className="text-[11px] text-slate-400">{row.employee_number}</p>
                        </Td>
                        <Td className="text-slate-500">{row.department ?? '—'}</Td>
                        <Td className="text-right tabular-nums">{pct(row.completion_rate)}</Td>
                        <Td className="text-right tabular-nums">{pct(row.on_time_rate)}</Td>
                        <Td className="text-right tabular-nums">{num(row.approved_reports)}</Td>
                        <Td className="text-right tabular-nums">{num(row.completed_tasks)}</Td>
                        <Td className="text-right tabular-nums font-bold">{num(row.overall_score)}</Td>
                        <Td className="text-right tabular-nums">{row.rank ?? '—'}</Td>
                        <Td>
                          {row.employee_of_the_month ? (
                            <Badge tone="purple">Employee of the month</Badge>
                          ) : (
                            <span className="text-[11px] text-slate-400">Recorded</span>
                          )}
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                <Pagination meta={rollups.data.meta} onPageChange={setRollupsPage} />
              </>
            )}
          </Panel>
        </div>
      ) : null}

      {tab === 'definitions' ? (
        <div className="space-y-6">
          <FilterBar>
            <Field label="Category">
              <Select
                value={defCategory}
                onChange={(value) => {
                  setDefCategory(value)
                  setDefPage(1)
                }}
                options={options.data?.categories ?? []}
                placeholder="All categories"
              />
            </Field>
            <Field label="Status">
              <Select
                value={defActive}
                onChange={(value) => {
                  setDefActive(value)
                  setDefPage(1)
                }}
                options={[
                  { value: '1', label: 'Active' },
                  { value: '0', label: 'Inactive' },
                ]}
                placeholder="Any status"
              />
            </Field>
            <Field label="Search" className="flex-1 min-w-[180px]">
              <TextInput
                value={defSearch}
                onChange={(value) => {
                  setDefSearch(value)
                  setDefPage(1)
                }}
                placeholder="Name, code or description"
              />
            </Field>
            <div className="flex items-end">
              <Button onClick={openCreateBadge}>New badge</Button>
            </div>
          </FilterBar>

          {definitions.error ? (
            <ErrorPanel message={definitions.error} onRetry={definitions.reload} />
          ) : definitions.loading ? (
            <LoadingPanel label="Loading badge definitions" rows={2} />
          ) : (definitions.data?.items.length ?? 0) === 0 ? (
            <EmptyState title="No badge definitions" message="Create a badge to start recognizing achievements." />
          ) : (
            <Panel title="Badge catalogue" description={`${definitions.data?.meta.total ?? 0} definitions; deleting a badge with history only deactivates it`}>
              <Table>
                <thead>
                  <tr>
                    <Th>Badge</Th>
                    <Th>Category</Th>
                    <Th>Criteria</Th>
                    <Th>Repeats</Th>
                    <Th>Status</Th>
                    <Th className="text-right">Earned</Th>
                    <Th>Actions</Th>
                  </tr>
                </thead>
                <tbody>
                  {definitions.data?.items.map((badge) => (
                    <tr key={badge.id} className="border-b border-slate-100 last:border-0">
                      <Td>
                        <p className="font-semibold text-slate-800">{badge.name}</p>
                        <p className="text-[11px] font-mono text-slate-400">
                          {badge.code}
                          {badge.icon ? ` · ${badge.icon}` : ''}
                        </p>
                        {badge.description ? (
                          <p className="text-[11px] text-slate-400 max-w-sm">{badge.description}</p>
                        ) : null}
                      </Td>
                      <Td>{badge.category ? <Badge tone="info">{badge.category}</Badge> : '—'}</Td>
                      <Td className="text-xs text-slate-500">
                        {describeCriteriaSummary(badge.criteria_type, badge.criteria_value, humanise)}
                      </Td>
                      <Td className="text-xs text-slate-500">{badge.allow_repeats ? 'Monthly' : 'Once per year'}</Td>
                      <Td>
                        <Badge tone={badge.is_active ? 'ok' : 'neutral'}>
                          {badge.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </Td>
                      <Td className="text-right tabular-nums font-bold">{num(badge.earned_count)}</Td>
                      <Td>
                        <div className="flex items-center gap-1">
                          <Button variant="ghost" onClick={() => openEditBadge(badge)}>
                            Edit
                          </Button>
                          <Button
                            variant="ghost"
                            className="text-red-600 hover:bg-red-50"
                            onClick={() => {
                              setArchiveTarget(badge)
                              setArchiveError('')
                            }}
                          >
                            Remove
                          </Button>
                        </div>
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
              {definitions.data ? <Pagination meta={definitions.data.meta} onPageChange={setDefPage} /> : null}
            </Panel>
          )}
        </div>
      ) : null}

      {tab === 'summaries' ? (
        <div className="space-y-6">
          {summaries.loading ? (
            <LoadingPanel label="Loading summaries" />
          ) : summaries.error || !summaries.data ? (
            <ErrorPanel message={summaries.error ?? 'Unable to load summaries.'} onRetry={summaries.reload} />
          ) : (
            <>
              <div className="grid lg:grid-cols-2 gap-6">
                <Panel title="Top employees" description="Ranked by verified achievements; ties share a rank">
                  {summaries.data.top_employees.length === 0 ? (
                    <EmptyState title="No verified awards yet" message="Verify pending awards to build the leaderboard." />
                  ) : (
                    <Table>
                      <thead>
                        <tr>
                          <Th className="text-right">Rank</Th>
                          <Th>Employee</Th>
                          <Th>Department</Th>
                          <Th className="text-right">Verified awards</Th>
                        </tr>
                      </thead>
                      <tbody>
                        {summaries.data.top_employees.map((row) => (
                          <tr key={row.employee_id} className="border-b border-slate-100 last:border-0">
                            <Td className="text-right tabular-nums font-bold">{row.rank}</Td>
                            <Td>
                              <p className="font-semibold text-slate-800">{row.name}</p>
                              <p className="text-[11px] text-slate-400">{row.employee_number}</p>
                            </Td>
                            <Td className="text-slate-500">{row.department ?? '—'}</Td>
                            <Td className="text-right tabular-nums font-bold">{row.total}</Td>
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  )}
                </Panel>

                <Panel title="Verified awards per month" description="All-time monthly totals derived from awarded_at">
                  <TrendChart
                    labels={summaries.data.monthly_counts.map(
                      (row) => `${row.year}-${String(row.month).padStart(2, '0')}`,
                    )}
                    series={[
                      {
                        key: 'awards',
                        label: 'Verified awards',
                        colour: CHART_COLOURS[0],
                        values: summaries.data.monthly_counts.map((row) => row.total),
                      },
                    ]}
                    emptyMessage="No verified awards recorded in any month."
                  />
                </Panel>

                <Panel title="Department distribution" description="Verified awards by department">
                  <RankingBars
                    suffix=" awards"
                    rows={summaries.data.department_distribution.map((row) => ({
                      key: row.department,
                      label: row.department,
                      value: row.total,
                    }))}
                    emptyMessage="No verified awards to distribute."
                  />
                </Panel>

                <Panel title="Badge frequency" description="Most frequently earned badges, verified awards only">
                  <RankingBars
                    suffix=" earned"
                    rows={summaries.data.badge_frequency.map((row) => ({
                      key: row.badge_id,
                      label: row.category ? `${row.name} (${row.category})` : row.name,
                      value: row.total,
                    }))}
                    emptyMessage="No badges have been verified yet."
                  />
                </Panel>
              </div>

              <Panel title="Academic year summaries" description="Verified awards and distinct recipients per academic year">
                {summaries.data.academic_year_summaries.length === 0 ? (
                  <EmptyState title="No academic-year data" message="Awards tied to an academic year will appear here once verified." />
                ) : (
                  <Table>
                    <thead>
                      <tr>
                        <Th>Academic year</Th>
                        <Th className="text-right">Verified awards</Th>
                        <Th className="text-right">Distinct employees</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {summaries.data.academic_year_summaries.map((row) => (
                        <tr key={row.academic_year_id} className="border-b border-slate-100 last:border-0">
                          <Td className="font-semibold text-slate-800">{row.name}</Td>
                          <Td className="text-right tabular-nums">{row.total}</Td>
                          <Td className="text-right tabular-nums">{row.employees}</Td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                )}
              </Panel>
            </>
          )}
        </div>
      ) : null}

      <Modal
        open={formOpen}
        title={editingBadge ? `Edit badge: ${editingBadge.name}` : 'Create badge'}
        onClose={closeBadgeModal}
        footer={
          <>
            <Button variant="secondary" onClick={closeBadgeModal}>
              Cancel
            </Button>
            <Button onClick={saveBadge} disabled={saving}>
              {saving ? 'Saving…' : editingBadge ? 'Save changes' : 'Create badge'}
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          {formError ? (
            <p className="text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl px-3 py-2">{formError}</p>
          ) : null}
          <div className="grid sm:grid-cols-2 gap-4">
            <Field label="Name" required>
              <TextInput value={form.name} onChange={(value) => setForm({ ...form, name: value })} placeholder="e.g. Safety Star" />
            </Field>
            <Field label="Code" required hint="A-Z, 0-9 and underscores; stored uppercase">
              <TextInput value={form.code} onChange={(value) => setForm({ ...form, code: value })} placeholder="e.g. SAFETY_STAR" />
            </Field>
          </div>
          <Field label="Description">
            <TextInput
              value={form.description}
              onChange={(value) => setForm({ ...form, description: value })}
              placeholder="What the badge recognizes"
            />
          </Field>
          <div className="grid sm:grid-cols-2 gap-4">
            <Field label="Icon">
              <Select
                value={form.icon}
                onChange={(value) => setForm({ ...form, icon: value })}
                options={ICON_OPTIONS.map((icon) => ({ value: icon, label: icon }))}
              />
            </Field>
            <Field label="Category">
              <Select
                value={form.category}
                onChange={(value) => setForm({ ...form, category: value })}
                options={BADGE_CATEGORIES.map((category) => ({ value: category, label: category }))}
                placeholder="No category"
              />
            </Field>
          </div>
          <div className="grid sm:grid-cols-2 gap-4">
            <Field label="Criteria type">
              <Select
                value={form.criteria_type}
                onChange={(value) => setForm({ ...form, criteria_type: value })}
                options={[
                  ...CRITERIA_TYPE_OPTIONS.map((type) => ({ value: type, label: humanise(type) })),
                  ...(form.criteria_type && !CRITERIA_TYPE_OPTIONS.includes(form.criteria_type)
                    ? [{ value: form.criteria_type, label: humanise(form.criteria_type) }]
                    : []),
                ]}
                placeholder="No criteria"
              />
            </Field>
            {form.criteria_mode === 'guided' ? (
              <Field label="Period" hint="Evaluation window for this criteria">
                <Select
                  value={form.criteria_period}
                  onChange={(value) => setForm({ ...form, criteria_period: value })}
                  options={[
                    ...CRITERIA_PERIOD_OPTIONS,
                    ...(form.criteria_period &&
                    !CRITERIA_PERIOD_OPTIONS.some((option) => option.value === form.criteria_period)
                      ? [{ value: form.criteria_period, label: humanise(form.criteria_period) }]
                      : []),
                  ]}
                  placeholder="No period"
                />
              </Field>
            ) : null}
          </div>

          {form.criteria_mode === 'guided' ? (
            <div className="grid sm:grid-cols-2 gap-4">
              <Field
                label="Threshold"
                hint="Required value — e.g. 85 for a score rule, 10 for a task count"
              >
                <TextInput
                  type="number"
                  value={form.criteria_threshold}
                  onChange={(value) => setForm({ ...form, criteria_threshold: value })}
                  placeholder="e.g. 85"
                />
              </Field>
              <Field label="Minimum score" hint="Optional — used by progressive criteria">
                <TextInput
                  type="number"
                  value={form.criteria_min_score}
                  onChange={(value) => setForm({ ...form, criteria_min_score: value })}
                  placeholder="e.g. 80"
                />
              </Field>
            </div>
          ) : (
            <Field
              label="Criteria value (JSON)"
              hint="This criteria has custom properties. Edit as JSON; unknown values are preserved exactly as stored."
            >
              <textarea
                className="w-full text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 font-mono focus:outline-none focus:ring-2 focus:ring-jpc-500 focus:border-jpc-500 min-h-[80px]"
                value={form.criteria_json}
                onChange={(event) => setForm({ ...form, criteria_json: event.target.value })}
                spellCheck={false}
              />
            </Field>
          )}

          <div className="flex justify-end">
            <Button variant="ghost" className="text-xs" onClick={switchCriteriaMode}>
              {form.criteria_mode === 'guided' ? 'Edit as JSON' : 'Use guided form'}
            </Button>
          </div>
          <div className="flex flex-wrap gap-6">
            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(event) => setForm({ ...form, is_active: event.target.checked })}
                className="rounded border-slate-300 text-jpc-600 focus:ring-jpc-500"
              />
              Active
            </label>
            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={form.allow_repeats}
                onChange={(event) => setForm({ ...form, allow_repeats: event.target.checked })}
                className="rounded border-slate-300 text-jpc-600 focus:ring-jpc-500"
              />
              Allow repeated awards (monthly recurrence)
            </label>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={archiveTarget !== null}
        title={archiveTarget ? `Remove ${archiveTarget.name}?` : 'Remove badge'}
        message="A badge with award history is only deactivated so past awards keep their meaning. A never-awarded badge is permanently deleted."
        confirmLabel={archiveBusy ? 'Working…' : 'Remove'}
        busy={archiveBusy}
        tone="danger"
        onConfirm={confirmArchive}
        onCancel={() => setArchiveTarget(null)}
      />
      {archiveError ? (
        <p className="text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl px-3 py-2 mt-3">{archiveError}</p>
      ) : null}

      <Modal
        open={awardDetailId !== null}
        title="Award detail"
        onClose={closeAwardDetail}
        footer={
          <>
            {awardDetail?.verification_status === 'pending' && !rejectMode ? (
              <>
                <Button variant="danger" onClick={() => setRejectMode(true)} disabled={awardBusy}>
                  Reject…
                </Button>
                <Button onClick={() => runAwardAction('verify')} disabled={awardBusy}>
                  {awardBusy ? 'Working…' : 'Verify'}
                </Button>
              </>
            ) : null}
            {rejectMode ? (
              <>
                <Button variant="secondary" onClick={() => setRejectMode(false)} disabled={awardBusy}>
                  Cancel
                </Button>
                <Button variant="danger" onClick={() => runAwardAction('reject')} disabled={awardBusy || !rejectReason.trim()}>
                  {awardBusy ? 'Working…' : 'Confirm rejection'}
                </Button>
              </>
            ) : null}
            <Button variant="secondary" onClick={closeAwardDetail}>
              Close
            </Button>
          </>
        }
      >
        {awardDetailLoading ? (
          <p className="text-sm text-slate-400">Loading award…</p>
        ) : awardActionError && !awardDetail ? (
          <ErrorPanel message={awardActionError} />
        ) : awardDetail ? (
          <div className="space-y-4 text-sm">
            {awardActionError ? (
              <p className="text-red-700 bg-red-50 border border-red-200 rounded-xl px-3 py-2">{awardActionError}</p>
            ) : null}
            <dl className="grid sm:grid-cols-2 gap-x-4 gap-y-2">
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Employee</dt>
                <dd className="text-slate-800 font-semibold">
                  {awardDetail.employee_name}
                  <span className="text-slate-400 font-normal"> · {awardDetail.employee_number}</span>
                </dd>
                <dd className="text-slate-500">{awardDetail.department ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Badge</dt>
                <dd className="text-slate-800 font-semibold">{awardDetail.badge_name}</dd>
                <dd className="text-slate-400 font-mono text-xs">{awardDetail.badge_code}</dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Academic year</dt>
                <dd className="text-slate-700">{awardDetail.academic_year ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Award period</dt>
                <dd className="text-slate-700">
                  {shortDate(awardDetail.period_start)} → {shortDate(awardDetail.period_end)}
                </dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Awarded</dt>
                <dd className="text-slate-700">
                  {dateTime(awardDetail.awarded_at)}
                  {awardDetail.awarded_by ? <span className="text-slate-400"> by {awardDetail.awarded_by}</span> : null}
                </dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Verification</dt>
                <dd>
                  <Badge tone={verificationTone(awardDetail.verification_status)}>
                    {humanise(awardDetail.verification_status)}
                  </Badge>
                </dd>
                {awardDetail.verified_by ? (
                  <dd className="text-slate-500 text-xs mt-1">
                    {awardDetail.verified_by} · {dateTime(awardDetail.verified_at)}
                  </dd>
                ) : null}
                {awardDetail.rejection_reason ? (
                  <dd className="text-red-700 text-xs mt-1">Reason: {awardDetail.rejection_reason}</dd>
                ) : null}
              </div>
            </dl>
            {rejectMode ? (
              <Field label="Rejection reason" required>
                <textarea
                  className="w-full text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-jpc-500 focus:border-jpc-500 min-h-[80px]"
                  value={rejectReason}
                  onChange={(event) => setRejectReason(event.target.value)}
                  placeholder="Explain why this award is rejected"
                />
              </Field>
            ) : null}
            <div>
              <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Evidence snapshot</dt>
              <RecognitionEvidencePanel
                evidence={awardDetail.evidence}
                emptyMessage="No evidence snapshot recorded for this award."
              />
            </div>
          </div>
        ) : null}
      </Modal>

      <Modal
        open={evaluateOpen}
        title="Evaluate month"
        onClose={() => {
          setEvaluateOpen(false)
          setEvalResult(null)
          setEvalError('')
        }}
        footer={
          <>
            <Button
              variant="secondary"
              onClick={() => {
                setEvaluateOpen(false)
                setEvalResult(null)
                setEvalError('')
              }}
            >
              Close
            </Button>
            <Button onClick={runEvaluation} disabled={evalBusy}>
              {evalBusy ? 'Evaluating…' : 'Run evaluation'}
            </Button>
          </>
        }
      >
        <div className="space-y-4 text-sm">
          <p className="text-slate-500">
            Runs the automatic achievement engine against real performance records for the selected month. Re-running
            the same month never duplicates awards.
          </p>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Year">
              <Select value={evalYear} onChange={setEvalYear} options={evalYearOptions} />
            </Field>
            <Field label="Month">
              <Select value={evalMonth} onChange={setEvalMonth} options={options.data?.months ?? []} />
            </Field>
          </div>
          {evalError ? (
            <p className="text-red-700 bg-red-50 border border-red-200 rounded-xl px-3 py-2">{evalError}</p>
          ) : null}
          {evalResult ? (
            <div className="bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2 text-emerald-800">
              <p className="font-bold">{evalResult.period}</p>
              <p>{num(evalResult.total_evaluated)} employee(s) evaluated.</p>
              {evalResult.employee_of_the_month ? (
                <p>Employee of the month: {evalResult.employee_of_the_month}</p>
              ) : (
                <p>No employee of the month was recorded for this period.</p>
              )}
            </div>
          ) : null}
        </div>
      </Modal>
    </AdminPage>
  )
}

