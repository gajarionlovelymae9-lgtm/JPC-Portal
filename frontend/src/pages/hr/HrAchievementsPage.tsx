import { useEffect, useMemo, useState } from 'react'

import { AdminPage, Badge, Button, EmptyState, ErrorPanel, Field, FilterBar, LoadingPanel, Modal, Panel, Pagination, Select, StatCard, Table, Td, TextInput, Th } from '../../components/admin/AdminUi'
import { ConfirmDialog } from '../../components/common/ConfirmDialog'
import {
  exportHrAchievements,
  fetchHrAchievementDetail,
  fetchHrAchievementOptions,
  fetchHrAchievements,
  fetchHrAchievementReports,
  rejectHrAchievement,
  verifyHrAchievement,
  type HrAchievementDetail,
  type HrAchievementFilters,
  type HrAchievementKpiCards,
  type HrAchievementOption,
  type HrAchievementRecord,
  type HrAchievementReport,
} from '../../services/hrAchievementService'
import { toRecognitionEvidence } from '../../utils/recognitionEvidence'
import { RecognitionEvidencePanel } from '../../components/achievements/RecognitionEvidencePanel'

// Re-exported so existing imports keep working after the move to the shared module.
export { toRecognitionEvidence }

type TabKey = 'records' | 'reports'

function toOptions(list: HrAchievementOption[] = []) {
  return list.map((item) => ({ value: String(item.value), label: item.label }))
}

function humanise(value: string | null | undefined) {
  if (!value) return '—'
  // Lowercase first so UPPERCASE codes (TASK_CHAMPION) still become
  // "Task Champion" rather than staying all-caps.
  return value.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase())
}

function verificationTone(status: string) {
  if (status === 'verified') return 'ok'
  if (status === 'rejected') return 'danger'
  return 'warn'
}

function shortDate(value: string | null | undefined) {
  if (!value) return '—'
  try {
    const parsed = new Date(value.length >= 10 ? value : value)
    if (Number.isNaN(parsed.getTime())) return value
    return parsed.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  } catch {
    return value
  }
}

function dateTime(value: string | null | undefined) {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleString()
  } catch {
    return value
  }
}

function num(value: number | null | undefined) {
  if (value === null || value === undefined) return '0'
  return Number(value).toLocaleString()
}


interface BreakdownRow {
  label: string
  total: number
}

/**
 * Defensive normaliser for the achievement breakdowns.
 *
 * The reports endpoint returns a list of rows, but a keyed map
 * (`category => total`) — the historical shape — must never crash the page
 * with `… .map is not a function`. Anything unusable degrades to an empty
 * list so the panel falls back to its empty state.
 */
function toBreakdownRows(value: unknown): BreakdownRow[] {
  if (Array.isArray(value)) {
    return value
      .filter((row): row is Record<string, unknown> => row !== null && typeof row === 'object')
      .map((row) => ({
        label: String(row.category ?? row.tier ?? row.label ?? ''),
        total: Number(row.total ?? row.count ?? 0),
      }))
      .filter((row) => row.label !== '')
  }

  if (value !== null && typeof value === 'object') {
    return Object.entries(value as Record<string, unknown>)
      .filter(([, total]) => typeof total === 'number')
      .map(([label, total]) => ({ label, total: Number(total) }))
  }

  return []
}

export default function HrAchievementsPage() {
  const [tab, setTab] = useState<TabKey>('records')
  const [options, setOptions] = useState<Awaited<ReturnType<typeof fetchHrAchievementOptions>>['data'] | null>(null)
  const [optionsError, setOptionsError] = useState('')

  const [filters, setFilters] = useState<HrAchievementFilters>({})
  const [records, setRecords] = useState<{ items: HrAchievementRecord[]; meta: { current_page: number; last_page: number; per_page: number; total: number }; cards: HrAchievementKpiCards } | null>(null)
  const [recordsLoading, setRecordsLoading] = useState(true)
  const [recordsError, setRecordsError] = useState('')

  const [reportFilters, setReportFilters] = useState<HrAchievementFilters>({})
  const [report, setReport] = useState<HrAchievementReport | null>(null)
  const [reportLoading, setReportLoading] = useState(false)
  const [reportError, setReportError] = useState('')

  const [detailId, setDetailId] = useState<number | null>(null)
  const [detail, setDetail] = useState<HrAchievementDetail | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [detailError, setDetailError] = useState('')
  const [actionBusy, setActionBusy] = useState(false)
  const [rejectOpen, setRejectOpen] = useState(false)
  const [rejectReason, setRejectReason] = useState('')
  const [confirmVerify, setConfirmVerify] = useState(false)
  const [exportBusy, setExportBusy] = useState(false)
  const [exportError, setExportError] = useState('')

  // The reports endpoint has historically returned the breakdowns as either a
  // list of rows or a keyed map; normalise both so the render never calls
  // `.map` on an object/undefined.
  const categoryRows = useMemo(() => toBreakdownRows(report?.by_category), [report])
  const tierRows = useMemo(() => toBreakdownRows(report?.by_tier), [report])

  const loadOptions = async () => {
    try {
      const response = await fetchHrAchievementOptions()
      setOptions(response.data)
      setOptionsError('')
    } catch (cause) {
      setOptionsError(cause instanceof Error ? cause.message : 'Unable to load options.')
    }
  }

  const loadRecords = async (nextFilters: HrAchievementFilters = filters) => {
    setRecordsLoading(true)
    setRecordsError('')
    try {
      const response = await fetchHrAchievements(nextFilters)
      setRecords(response.data)
    } catch (cause) {
      setRecordsError(cause instanceof Error ? cause.message : 'Unable to load recognitions.')
    } finally {
      setRecordsLoading(false)
    }
  }

  const loadReport = async (nextFilters: HrAchievementFilters = reportFilters) => {
    setReportLoading(true)
    setReportError('')
    try {
      const response = await fetchHrAchievementReports(nextFilters)
      setReport(response.data)
    } catch (cause) {
      setReportError(cause instanceof Error ? cause.message : 'Unable to load reports.')
    } finally {
      setReportLoading(false)
    }
  }

  useEffect(() => {
    void loadOptions()
    void loadRecords({})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const updateFilter = (key: keyof HrAchievementFilters, value: string) => {
    const next = { ...filters, [key]: value === '' ? undefined : value, page: 1 }
    setFilters(next)
    void loadRecords(next)
  }

  const changePage = (page: number) => {
    const next = { ...filters, page }
    setFilters(next)
    void loadRecords(next)
  }

  const openDetail = async (id: number) => {
    setDetailId(id)
    setDetail(null)
    setDetailError('')
    setRejectOpen(false)
    setRejectReason('')
    setConfirmVerify(false)
    setDetailLoading(true)
    try {
      const response = await fetchHrAchievementDetail(id)
      setDetail(response.data)
    } catch (cause) {
      setDetailError(cause instanceof Error ? cause.message : 'Unable to load detail.')
    } finally {
      setDetailLoading(false)
    }
  }

  const closeDetail = () => {
    setDetailId(null)
    setDetail(null)
    setRejectOpen(false)
    setRejectReason('')
    setConfirmVerify(false)
    setDetailError('')
  }

  const runVerify = async () => {
    if (detailId === null) return
    setActionBusy(true)
    setDetailError('')
    try {
      const response = await verifyHrAchievement(detailId)
      setDetail(response.data)
      setConfirmVerify(false)
      await loadRecords(filters)
    } catch (cause) {
      setDetailError(cause instanceof Error ? cause.message : 'Unable to verify award.')
    } finally {
      setActionBusy(false)
    }
  }

  const runReject = async () => {
    if (detailId === null || !rejectReason.trim()) return
    setActionBusy(true)
    setDetailError('')
    try {
      const response = await rejectHrAchievement(detailId, rejectReason.trim())
      setDetail(response.data)
      setRejectOpen(false)
      setRejectReason('')
      await loadRecords(filters)
    } catch (cause) {
      setDetailError(cause instanceof Error ? cause.message : 'Unable to reject award.')
    } finally {
      setActionBusy(false)
    }
  }

  const runExport = async (format: 'csv' | 'xlsx' | 'pdf') => {
    setExportBusy(true)
    setExportError('')
    try {
      await exportHrAchievements({ ...filters, format })
    } catch (cause) {
      setExportError(cause instanceof Error ? cause.message : 'Unable to export.')
    } finally {
      setExportBusy(false)
    }
  }

  const cards = records?.cards

  const yearOptions = useMemo(() => toOptions(options?.years ?? []), [options])
  const monthOptions = useMemo(() => toOptions(options?.months ?? []), [options])
  const departmentOptions = useMemo(() => toOptions(options?.departments ?? []), [options])
  const badgeOptions = useMemo(() => toOptions(options?.badges ?? []), [options])
  const categoryOptions = useMemo(() => toOptions(options?.categories ?? []), [options])
  const tierOptions = useMemo(() => toOptions(options?.tiers ?? []), [options])
  const statusOptions = useMemo(() => toOptions(options?.statuses ?? []), [options])

  return (
    <AdminPage
      title="HR Achievements & Badges"
      subtitle="Recognition records, verification, reports and exports"
      actions={
        <div className="flex flex-wrap items-center gap-2">
          <Button variant="secondary" onClick={() => runExport('csv')} disabled={exportBusy}>
            Export CSV
          </Button>
          <Button variant="secondary" onClick={() => runExport('xlsx')} disabled={exportBusy}>
            Export XLSX
          </Button>
          <Button variant="secondary" onClick={() => runExport('pdf')} disabled={exportBusy}>
            Export PDF
          </Button>
        </div>
      }
    >
      {cards ? (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard label="Total recognitions" value={num(cards.total_recognitions)} />
          <StatCard label="Verified" value={num(cards.verified)} tone="ok" />
          <StatCard label="Pending verification" value={num(cards.pending_verification)} tone={cards.pending_verification > 0 ? 'warn' : 'neutral'} />
          <StatCard label="This month" value={num(cards.this_month)} tone="info" />
        </div>
      ) : (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {[0, 1, 2, 3].map((index) => (
            <div key={index} className="h-24 rounded-2xl bg-slate-100 animate-pulse" />
          ))}
        </div>
      )}

      {/* Pending-queue monitor: one click jumps the records table to the
          review backlog, recommendation-backed rows flagged by dept heads. */}
      {cards && cards.pending_verification > 0 && filters.status !== 'pending' ? (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
          <p className="text-sm font-semibold text-amber-800">
            {num(cards.pending_verification)} recognition{cards.pending_verification === 1 ? '' : 's'} awaiting verification
            {' '}— recommendations from department heads use the same queue.
          </p>
          <Button
            variant="secondary"
            onClick={() => {
              const next = { ...filters, status: 'pending', page: 1 }
              setFilters(next)
              void loadRecords(next)
            }}
          >
            Review pending queue
          </Button>
        </div>
      ) : null}

      {exportError ? <p className="text-sm text-red-700">{exportError}</p> : null}
      {optionsError ? <p className="text-sm text-red-700">{optionsError}</p> : null}

      <div className="flex gap-1 border-b border-slate-200">
        {([
          ['records', 'Recognition records'],
          ['reports', 'Monthly/Yearly reports'],
        ] as Array<[TabKey, string]>).map(([key, label]) => (
          <button
            key={key}
            type="button"
            onClick={() => {
              setTab(key)
              if (key === 'reports' && report === null && !reportLoading) {
                void loadReport(reportFilters)
              }
            }}
            className={`px-4 py-2.5 text-sm font-bold border-b-2 -mb-px transition ${
              tab === key ? 'border-jpc-600 text-jpc-700' : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'records' ? (
        <div className="space-y-6">
          <FilterBar>
            <Field label="Year">
              <Select value={String(filters.year ?? '')} onChange={(value) => updateFilter('year', value)} options={yearOptions} placeholder="All years" />
            </Field>
            <Field label="Month">
              <Select value={String(filters.month ?? '')} onChange={(value) => updateFilter('month', value)} options={monthOptions} placeholder="All months" />
            </Field>
            <Field label="Department">
              <Select value={String(filters.department_id ?? '')} onChange={(value) => updateFilter('department_id', value)} options={departmentOptions} placeholder="All departments" />
            </Field>
            <Field label="Badge">
              <Select value={String(filters.badge_id ?? '')} onChange={(value) => updateFilter('badge_id', value)} options={badgeOptions} placeholder="All badges" />
            </Field>
            <Field label="Category">
              <Select value={filters.category ?? ''} onChange={(value) => updateFilter('category', value)} options={categoryOptions} placeholder="All categories" />
            </Field>
            <Field label="Tier">
              <Select value={filters.tier ?? ''} onChange={(value) => updateFilter('tier', value)} options={tierOptions} placeholder="All tiers" />
            </Field>
            <Field label="Status">
              <Select value={filters.status ?? ''} onChange={(value) => updateFilter('status', value)} options={statusOptions} placeholder="Any status" />
            </Field>
            <Field label="Search" className="flex-1 min-w-[200px]">
              <TextInput value={filters.search ?? ''} onChange={(value) => updateFilter('search', value)} placeholder="Employee or badge name" />
            </Field>
          </FilterBar>

          <Panel title="Recognition records" description={`${records?.meta.total ?? 0} records`}>
            {recordsLoading ? (
              <LoadingPanel label="Loading recognitions" rows={4} />
            ) : recordsError ? (
              <ErrorPanel message={recordsError} onRetry={() => loadRecords(filters)} />
            ) : (records?.items.length ?? 0) === 0 ? (
              <EmptyState title="No recognitions" message="No records match the selected filters." />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Employee</Th>
                      <Th>Department</Th>
                      <Th>Achievement</Th>
                      <Th>Category / Tier</Th>
                      <Th>Achievement date</Th>
                      <Th>Status</Th>
                      <Th>Actions</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {records?.items.map((row) => (
                      <tr key={row.id} className="border-b border-slate-100 last:border-0">
                        <Td>
                          <p className="font-semibold text-slate-800">{row.employee_name ?? '—'}</p>
                          <p className="text-[11px] text-slate-400">{row.employee_number ?? '—'}</p>
                        </Td>
                        <Td className="text-slate-500">{row.department ?? '—'}</Td>
                        <Td>
                          <p className="text-slate-800 break-words">{row.achievement_title ?? row.badge_name ?? '—'}</p>
                          <p className="text-[11px] font-mono text-slate-400">{row.badge_code ?? '—'}</p>
                          {row.recommended_by ? (
                            <p className="text-[11px] text-slate-500 break-words whitespace-normal">
                              Recommended by {row.recommended_by}
                            </p>
                          ) : null}
                        </Td>
                        <Td>
                          <div className="flex flex-wrap gap-1">
                            {row.badge_category || row.category ? <Badge tone="info">{row.badge_category ?? row.category}</Badge> : null}
                            {row.badge_tier || row.tier ? <Badge tone="neutral">{row.badge_tier ?? row.tier}</Badge> : null}
                          </div>
                        </Td>
                        <Td className="text-slate-500 text-xs">{shortDate(row.achievement_date ?? row.awarded_at)}</Td>
                        <Td>
                          <Badge tone={verificationTone(row.verification_status)}>{humanise(row.verification_status)}</Badge>
                          {row.rejection_reason ? <p className="text-[11px] text-red-600 mt-1 max-w-[200px] break-words whitespace-normal">{row.rejection_reason}</p> : null}
                        </Td>
                        <Td>
                          <Button variant="ghost" onClick={() => void openDetail(row.id)}>
                            View
                          </Button>
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                {records ? <Pagination meta={records.meta} onPageChange={changePage} /> : null}
              </>
            )}
          </Panel>
        </div>
      ) : null}

      {tab === 'reports' ? (
        <div className="space-y-6">
          <FilterBar>
            <Field label="Year">
              <Select
                value={String(reportFilters.year ?? new Date().getFullYear())}
                onChange={(value) => setReportFilters({ ...reportFilters, year: value, month: undefined })}
                options={yearOptions.length > 0 ? yearOptions : [{ value: String(new Date().getFullYear()), label: String(new Date().getFullYear()) }]}
              />
            </Field>
            <Field label="Month">
              <Select value={String(reportFilters.month ?? '')} onChange={(value) => setReportFilters({ ...reportFilters, month: value === '' ? undefined : value })} options={monthOptions} placeholder="Yearly (all months)" />
            </Field>
            <Field label="Department">
              <Select value={String(reportFilters.department_id ?? '')} onChange={(value) => setReportFilters({ ...reportFilters, department_id: value === '' ? undefined : value })} options={departmentOptions} placeholder="All departments" />
            </Field>
            <div className="flex items-end">
              <Button onClick={() => void loadReport(reportFilters)}>Generate report</Button>
            </div>
          </FilterBar>

          {reportLoading ? (
            <LoadingPanel label="Loading report" rows={4} />
          ) : reportError ? (
            <ErrorPanel message={reportError} onRetry={() => loadReport(reportFilters)} />
          ) : report ? (
            <div className="space-y-6">
              <Panel title="Report period" description={report.period.label}>
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                  <StatCard label="Total" value={num(report.totals.total)} />
                  <StatCard label="Verified" value={num(report.totals.verified)} tone="ok" />
                  <StatCard label="Pending" value={num(report.totals.pending)} tone="warn" />
                  <StatCard label="Rejected" value={num(report.totals.rejected)} tone="danger" />
                </div>
              </Panel>

              <div className="grid lg:grid-cols-2 gap-6">
                <Panel title="By category">
                  {(categoryRows.length === 0) ? <EmptyState title="No data" message="No category breakdown available." /> : (
                    <Table>
                      <thead>
                        <tr><Th>Category</Th><Th className="text-right">Count</Th></tr>
                      </thead>
                      <tbody>
                        {categoryRows.map((row) => (
                          <tr key={row.label} className="border-b border-slate-100 last:border-0">
                            <Td>{row.label}</Td>
                            <Td className="text-right tabular-nums">{num(row.total)}</Td>
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  )}
                </Panel>
                <Panel title="By tier">
                  {(tierRows.length === 0) ? <EmptyState title="No data" message="No tier breakdown available." /> : (
                    <Table>
                      <thead>
                        <tr><Th>Tier</Th><Th className="text-right">Count</Th></tr>
                      </thead>
                      <tbody>
                        {tierRows.map((row) => (
                          <tr key={row.label} className="border-b border-slate-100 last:border-0">
                            <Td>{row.label}</Td>
                            <Td className="text-right tabular-nums">{num(row.total)}</Td>
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  )}
                </Panel>
              </div>

              <Panel title="Employee breakdown">
                {(report.employees.length === 0) ? <EmptyState title="No data" message="No employee breakdown for this period." /> : (
                  <Table>
                    <thead>
                      <tr>
                        <Th>Employee</Th>
                        <Th>Department</Th>
                        <Th className="text-right">Total</Th>
                        <Th className="text-right">Verified</Th>
                        <Th className="text-right">Pending</Th>
                        <Th className="text-right">Rejected</Th>
                      </tr>
                    </thead>
                    <tbody>
                      {report.employees.map((row) => (
                        <tr key={row.employee_id ?? row.employee_number ?? row.name} className="border-b border-slate-100 last:border-0">
                          <Td>
                            <p className="font-semibold text-slate-800">{row.name ?? '—'}</p>
                            <p className="text-[11px] text-slate-400">{row.employee_number ?? '—'}</p>
                          </Td>
                          <Td className="text-slate-500">{row.department ?? '—'}</Td>
                          <Td className="text-right tabular-nums">{num(row.total)}</Td>
                          <Td className="text-right tabular-nums">{num(row.verified)}</Td>
                          <Td className="text-right tabular-nums">{num(row.pending)}</Td>
                          <Td className="text-right tabular-nums">{num(row.rejected)}</Td>
                        </tr>
                      ))}
                    </tbody>
                  </Table>
                )}
              </Panel>
            </div>
          ) : (
            <EmptyState title="Generate a report" message="Select year and optional month/department, then generate the report." />
          )}
        </div>
      ) : null}

      <Modal open={detailId !== null} title="Recognition detail" onClose={closeDetail}>
        {detailLoading ? (
          <p className="text-sm text-slate-400">Loading detail…</p>
        ) : detailError ? (
          <ErrorPanel message={detailError} />
        ) : detail ? (
          <div className="space-y-4 text-sm">
            <dl className="grid sm:grid-cols-2 gap-x-4 gap-y-2">
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Employee</dt>
                <dd className="text-slate-800 font-semibold">{detail.employee_name ?? '—'} <span className="text-slate-400 font-normal">· {detail.employee_number ?? '—'}</span></dd>
                <dd className="text-slate-500">{detail.department ?? '—'}{detail.position ? ` · ${detail.position}` : ''}</dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Achievement</dt>
                <dd className="text-slate-800 font-semibold">{detail.achievement_title ?? detail.badge_name ?? '—'}</dd>
                <dd className="text-slate-500 text-xs">{humanise(detail.badge_code)}{detail.badge_code ? <span className="font-mono"> · {detail.badge_code}</span> : ''}</dd>
                <dd className="flex flex-wrap gap-1 mt-1">
                  {(detail.badge_category ?? detail.category) ? <Badge tone="info">{humanise(detail.badge_category ?? detail.category)}</Badge> : null}
                  {(detail.badge_tier ?? detail.tier) ? <Badge tone="neutral">{humanise(detail.badge_tier ?? detail.tier)}</Badge> : null}
                </dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Achievement date</dt>
                <dd className="text-slate-700">{shortDate(detail.achievement_date ?? detail.awarded_at)}</dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Period / Academic year</dt>
                <dd className="text-slate-700">{detail.period_start && detail.period_end ? `${shortDate(detail.period_start)} – ${shortDate(detail.period_end)}` : '—'}{detail.academic_year ? ` · ${detail.academic_year}` : ''}</dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Verification</dt>
                <dd><Badge tone={verificationTone(detail.verification_status)}>{humanise(detail.verification_status)}</Badge></dd>
                {detail.verified_by ? <dd className="text-slate-500 text-xs mt-1">{detail.verified_by} · {dateTime(detail.verified_at)}</dd> : null}
                {detail.rejection_reason ? <dd className="text-red-700 text-xs mt-1">Reason: {detail.rejection_reason}</dd> : null}
              </div>
            </dl>

            {detail.recommended_by ? (
              <div className="rounded-xl border border-blue-200 bg-blue-50/60 px-3 py-2">
                <p className="text-[11px] font-bold uppercase tracking-wider text-blue-700">Department Head recommendation</p>
                <p className="text-slate-700 text-xs mt-1">
                  Submitted by {detail.recommended_by}
                  {detail.recommended_at ? ` · ${dateTime(detail.recommended_at)}` : ''}
                </p>
                {detail.recommendation_details ? (
                  <p className="text-slate-700 text-xs mt-1 break-words whitespace-normal">{detail.recommendation_details}</p>
                ) : null}
              </div>
            ) : null}

            {detail.badge_description ? (
              <div>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Badge description</p>
                <p className="text-slate-700">{detail.badge_description}</p>
              </div>
            ) : null}

            <div>
              <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Evidence / Criteria snapshot</p>
              <RecognitionEvidencePanel evidence={detail.evidence} />
            </div>

            {detail.history && detail.history.length > 0 ? (
              <div>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">History</p>
                <div className="space-y-2">
                  {detail.history.map((item) => (
                    <div key={item.id} className="border border-slate-200 rounded-xl px-3 py-2">
                      <p className="text-slate-800">{item.description}</p>
                      <p className="text-[11px] text-slate-400">{item.actor ?? 'System'} · {dateTime(item.created_at)}</p>
                    </div>
                  ))}
                </div>
              </div>
            ) : null}

            {detail.verification_status === 'pending' ? (
              <div className="flex flex-wrap items-center gap-2 pt-2">
                <Button onClick={() => setConfirmVerify(true)} disabled={actionBusy}>Verify</Button>
                <Button variant="danger" onClick={() => setRejectOpen(true)} disabled={actionBusy}>Reject</Button>
              </div>
            ) : null}
          </div>
        ) : null}
      </Modal>

      <ConfirmDialog
        open={confirmVerify}
        title="Verify award"
        message="Verify this pending recognition? This action will be recorded in the audit/history trail."
        confirmLabel="Verify"
        busy={actionBusy}
        onConfirm={() => void runVerify()}
        onCancel={() => setConfirmVerify(false)}
      />

      <Modal open={rejectOpen} title="Reject award" onClose={() => setRejectOpen(false)}>
        <div className="space-y-3">
          <Field label="Rejection reason" required>
            <textarea
              className="w-full text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-jpc-500 focus:border-jpc-500 min-h-[90px]"
              value={rejectReason}
              onChange={(event) => setRejectReason(event.target.value)}
              placeholder="Explain why this award is rejected"
            />
          </Field>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={() => setRejectOpen(false)} disabled={actionBusy}>Cancel</Button>
            <Button variant="danger" onClick={() => void runReject()} disabled={actionBusy || !rejectReason.trim()}>Confirm rejection</Button>
          </div>
        </div>
      </Modal>
    </AdminPage>
  )
}
