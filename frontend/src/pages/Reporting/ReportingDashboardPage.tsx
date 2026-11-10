import { Fragment, useCallback, useEffect, useMemo, useState } from 'react'
import { useAuth } from '../../auth/useAuth'
import {
  AdminPage,
  Badge,
  Button,
  EmptyState,
  ErrorPanel,
  Field,
  FilterBar,
  LoadingPanel,
  Panel,
  Select,
  StatCard,
  StatusBadge,
  Table,
  Td,
  TextInput,
  Th,
  dateTime,
  humanise,
  num,
  shortDate,
} from '../../components/admin/AdminUi'
import { MONTH_NAMES, useReportingResource } from '../../hooks/useReporting'
import {
  downloadReportExportFile,
  getDepartmentReport,
  getEmployeeReport,
  getInstitutionReport,
  listReportExports,
  queueReportExport,
  retryReportExport,
} from '../../services/reportingService'
import { getEmployeePerformance } from '../../services/reportService'
import { getHrOptions, type HrOptions } from '../../services/hrService'
import { getApiErrorMessage } from '../../utils/apiError'
import type { EmployeePerformanceRow } from '../../types/report'
import type {
  DepartmentReport,
  EmployeeReport,
  InstitutionReport,
  ReportingFilters,
  ReportExport,
  ReportExportTypeValue,
} from '../../types/reporting'
import {
  KpiIcon,
  MonthlyPerformanceTab,
  OverviewTab,
  SubmissionMonitoringTab,
  TargetAchievementTab,
  DataQualityTab,
  EmployeePerformanceTab,
  DepartmentPerformanceTab,
  type HubTabKey,
  type ScopedReport,
} from './hub'
import { pctOrNa } from './hub/hubShared'

type PeriodType = 'monthly' | 'quarterly' | 'yearly' | 'custom'
type Scope = 'institution' | 'department' | 'employee'
type ExportFormat = 'csv' | 'xlsx' | 'pdf'

interface HubFilterState {
  periodType: PeriodType
  year: number
  month: number
  quarter: number
  startDate: string
  endDate: string
  departmentId: string
  employeeId: string
  academicYearId: string
  scope: Scope
}

const EXPORT_TYPES: Array<{ value: ReportExportTypeValue; label: string }> = [
  { value: 'employee_progress', label: 'Employee progress' },
  { value: 'department_performance', label: 'Department performance' },
  { value: 'institution_performance', label: 'Institution performance' },
  { value: 'monthly_performance', label: 'Monthly performance' },
  { value: 'achievement_summary', label: 'Achievement summary' },
  { value: 'late_reports', label: 'Late submissions' },
  { value: 'pending_reports', label: 'Pending reports' },
  { value: 'targets_summary', label: 'Target achievement' },
  { value: 'data_quality', label: 'Data quality' },
]

const EXPORT_STATUSES = ['QUEUED', 'PROCESSING', 'COMPLETED', 'FAILED', 'EXPIRED']
const PENDING_STATUSES = ['QUEUED', 'PROCESSING']

const TABS: Array<{ key: HubTabKey; label: string; description: string }> = [
  { key: 'overview', label: 'Overview', description: 'Institutional summary and management narrative' },
  { key: 'employees', label: 'Employee Performance', description: 'Per-employee KPIs and drill-down' },
  { key: 'departments', label: 'Department Performance', description: 'Comparisons and rankings' },
  { key: 'monthly', label: 'Monthly Performance', description: 'Highlights and month-over-month trends' },
  { key: 'submissions', label: 'Submission Monitoring', description: 'Late and pending report workflow' },
  { key: 'targets', label: 'Target Achievement', description: 'Targets, actuals and remaining value' },
  { key: 'data-quality', label: 'Data Quality', description: 'Validation scans and issue severity' },
]

function nowParts() {
  const now = new Date()
  return {
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    firstDay: `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`,
    lastDay: new Date(now.getFullYear(), now.getMonth() + 1, 0)
      .toISOString()
      .slice(0, 10),
  }
}

function defaultFilterState(): HubFilterState {
  const parts = nowParts()
  return {
    periodType: 'monthly',
    year: parts.year,
    month: parts.month,
    quarter: Math.floor((parts.month - 1) / 3) + 1,
    startDate: parts.firstDay,
    endDate: parts.lastDay,
    departmentId: '',
    employeeId: '',
    academicYearId: '',
    scope: 'institution',
  }
}

function buildFilters(state: HubFilterState): ReportingFilters {
  const filters: ReportingFilters = {}

  if (state.scope === 'department' && state.departmentId !== '') {
    filters.department_id = Number(state.departmentId)
  }
  if (state.scope === 'employee' && state.employeeId !== '') {
    filters.employee_id = Number(state.employeeId)
  }

  if (state.academicYearId !== '') {
    filters.academic_year_id = Number(state.academicYearId)
    return filters
  }

  filters.period_type = state.periodType

  if (state.periodType === 'custom') {
    filters.start_date = state.startDate
    filters.end_date = state.endDate
    return filters
  }

  filters.year = state.year

  if (state.periodType === 'monthly') {
    filters.month = state.month
  }
  if (state.periodType === 'quarterly') {
    filters.quarter = state.quarter
  }

  return filters
}

function periodLabel(state: HubFilterState, options: HrOptions | null): string {
  if (state.academicYearId !== '') {
    const match = options?.academic_years.find((ay) => String(ay.id) === state.academicYearId)
    return match?.name ?? `Academic year #${state.academicYearId}`
  }

  if (state.periodType === 'quarterly') {
    return `Q${state.quarter} ${state.year}`
  }
  if (state.periodType === 'yearly') {
    return String(state.year)
  }
  if (state.periodType === 'custom') {
    return `${shortDate(state.startDate)} – ${shortDate(state.endDate)}`
  }

  return `${MONTH_NAMES[state.month - 1]} ${state.year}`
}

function formatBytes(bytes: number | null): string {
  if (bytes === null) {
    return '—'
  }
  if (bytes < 1024) {
    return `${bytes} B`
  }
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`
  }
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function exportPeriodLabel(item: ReportExport, options: HrOptions | null): string {
  const filters = item.filters ?? {}

  const asNumber = (value: unknown): number | null => {
    if (typeof value === 'number' && Number.isFinite(value)) return value
    if (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) {
      return Number(value)
    }
    return null
  }
  const asString = (value: unknown): string | null =>
    typeof value === 'string' && value !== '' ? value : null

  const academicYearId = asNumber(filters.academic_year_id)
  if (academicYearId !== null) {
    const match = options?.academic_years.find((ay) => ay.id === academicYearId)
    return match?.name ?? `Academic year #${academicYearId}`
  }

  const start = asString(filters.start_date)
  const end = asString(filters.end_date)
  if (start && end) {
    return `${shortDate(start)} – ${shortDate(end)}`
  }

  const year = asNumber(filters.year)
  const quarter = asNumber(filters.quarter)
  const month = asNumber(filters.month)

  if (year !== null && quarter !== null) {
    return `Q${quarter} ${year}`
  }
  if (year !== null && month !== null) {
    return `${MONTH_NAMES[month - 1] ?? month} ${year}`
  }
  if (year !== null) {
    return String(year)
  }

  return '—'
}

export function ReportingDashboardPage() {
  const { user } = useAuth()
  const role = user?.role ?? ''
  const canInstitution = role === 'admin' || role === 'hr'
  const canDepartment = canInstitution || role === 'department_head'
  const isDeptHead = role === 'department_head'
  const isEmployee = role === 'employee'

  const [draft, setDraft] = useState<HubFilterState>(defaultFilterState)
  const [applied, setApplied] = useState<HubFilterState>(defaultFilterState)
  const [activeTab, setActiveTab] = useState<HubTabKey>('overview')
  const [filterError, setFilterError] = useState<string | null>(null)
  const [refreshedAt, setRefreshedAt] = useState<Date | null>(null)
  const [refreshToken, setRefreshToken] = useState(0)

  const [exportType, setExportType] = useState<ReportExportTypeValue>('institution_performance')
  const [exportFormat, setExportFormat] = useState<ExportFormat>('xlsx')
  const [exporting, setExporting] = useState(false)
  const [exportNotice, setExportNotice] = useState<string | null>(null)
  const [statusFilter, setStatusFilter] = useState('')
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [downloadingId, setDownloadingId] = useState<number | null>(null)
  const [retryingId, setRetryingId] = useState<number | null>(null)
  const [libraryError, setLibraryError] = useState<string | null>(null)

  // Department heads and employees are always scoped to their own data; the
  // scope selector is only exposed to institution-wide roles.
  const effectiveState = useMemo<HubFilterState>(
    () => ({
      ...applied,
      scope: isDeptHead ? 'department' : isEmployee ? 'employee' : applied.scope,
    }),
    [applied, isDeptHead, isEmployee],
  )

  const filters = useMemo(() => buildFilters(effectiveState), [effectiveState])

  const options = useReportingResource<HrOptions | null>(
    useCallback(
      () => (canInstitution ? getHrOptions() : Promise.resolve({ data: null })),
      [canInstitution],
    ),
    'hub-options',
    'Unable to load filter options.',
  )

  const employeePicker = useReportingResource<EmployeePerformanceRow[] | null>(
    useCallback(
      () =>
        canInstitution && applied.scope === 'employee'
          ? (async () => {
              // The endpoint caps per_page at 100 (server-side validation),
              // so the picker claims the first page and keeps requesting
              // until the last page — up to a hard ceiling to stay a sane
              // number of round-trips. Previous code sent per_page: 200 and
              // got HTTP 422 "Validation failed" on every open, leaving the
              // dropdown empty.
              const pageLimit = 4
              const first = await getEmployeePerformance({ page: 1, per_page: 100 })
              const rows = [...((first.data ?? []) as EmployeePerformanceRow[])]
              const lastPage = first.meta?.pagination?.last_page ?? 1

              for (let page = 2; page <= Math.min(lastPage, pageLimit); page += 1) {
                const next = await getEmployeePerformance({ page, per_page: 100 })
                rows.push(...((next.data ?? []) as EmployeePerformanceRow[]))
              }

              return { data: rows as EmployeePerformanceRow[] }
            })()
          : Promise.resolve({ data: null }),
      [canInstitution, applied.scope],
    ),
    'hub-employee-pickers',
    'Unable to load the employee list.',
  )

  const institution = useReportingResource<InstitutionReport | null>(
    useCallback(
      () =>
        canInstitution && effectiveState.scope === 'institution'
          ? getInstitutionReport(filters)
          : Promise.resolve({ data: null }),
      [canInstitution, effectiveState.scope, filters],
    ),
    `hub-institution-${JSON.stringify(filters)}`,
    'Unable to load the institution report.',
  )

  const department = useReportingResource<DepartmentReport | null>(
    useCallback(
      () =>
        canDepartment && effectiveState.scope === 'department'
          ? getDepartmentReport(filters)
          : Promise.resolve({ data: null }),
      [canDepartment, effectiveState.scope, filters],
    ),
    `hub-department-${JSON.stringify(filters)}`,
    'Unable to load the department report.',
  )

  const employeeSelf = useReportingResource<EmployeeReport | null>(
    useCallback(
      () =>
        effectiveState.scope === 'employee' && (isEmployee || canInstitution)
          ? getEmployeeReport(filters)
          : Promise.resolve({ data: null }),
      [effectiveState.scope, filters, isEmployee, canInstitution],
    ),
    `hub-self-${JSON.stringify(filters)}`,
    'Unable to load the employee report.',
  )

  const library = useReportingResource(
    useCallback(() => listReportExports(), []),
    'hub-library',
    'Unable to load the report library.',
  )

  const refreshLibrary = library.refresh

  useEffect(() => {
    const hasPending = (library.data ?? []).some((item) =>
      PENDING_STATUSES.includes(item.status),
    )
    if (!hasPending) {
      return undefined
    }
    // Status polling every 4s while work is pending, but paused entirely if
    // the tab is hidden — no background refetches burning the rate-limiters.
    const timer = window.setInterval(() => {
      if (document.hidden) {
        return
      }
      refreshLibrary()
    }, 4000)
    // One immediate refresh when the tab becomes visible again so a
    // long-running export does not wait the full interval.
    const onVisible = () => {
      if (!document.hidden && (library.data ?? []).some((item) => PENDING_STATUSES.includes(item.status))) {
        refreshLibrary()
      }
    }
    document.addEventListener('visibilitychange', onVisible)
    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [library.data, refreshLibrary])

  function applyFilters() {
    if (draft.academicYearId === '' && draft.periodType === 'custom') {
      if (!draft.startDate || !draft.endDate) {
        setFilterError('Choose both a start date and an end date.')
        return
      }
      if (draft.startDate > draft.endDate) {
        setFilterError('The start date must be on or before the end date.')
        return
      }
    }
    if (canInstitution && draft.scope === 'department' && draft.departmentId === '') {
      setFilterError('Choose a department for the department scope.')
      return
    }
    if (canInstitution && draft.scope === 'employee' && draft.employeeId === '') {
      setFilterError('Choose an employee for the employee scope.')
      return
    }
    setFilterError(null)
    setApplied(draft)
    setRefreshedAt(new Date())
    setRefreshToken((token) => token + 1)
  }

  function resetFilters() {
    const fresh = defaultFilterState()
    setDraft(fresh)
    setApplied(fresh)
    setFilterError(null)
    setRefreshedAt(new Date())
    setRefreshToken((token) => token + 1)
  }

  function refreshAll() {
    institution.refresh()
    department.refresh()
    employeeSelf.refresh()
    options.refresh()
    employeePicker.refresh()
    library.refresh()
    setRefreshToken((token) => token + 1)
    setRefreshedAt(new Date())
  }

  async function handleGenerateExport() {
    setExporting(true)
    setExportNotice(null)
    try {
      await queueReportExport({
        report_type: exportType,
        format: exportFormat,
        filters,
      })
      setExportNotice('Export queued. It will appear below as soon as a file is ready.')
      library.refresh()
    } catch (cause: unknown) {
      setExportNotice(getApiErrorMessage(cause, 'The export request failed.'))
    } finally {
      setExporting(false)
    }
  }

  async function handleRetry(item: ReportExport) {
    setLibraryError(null)
    setRetryingId(item.id)
    try {
      await retryReportExport(item.id)
      setExportNotice('Export re-queued. It will be generated again shortly.')
      library.refresh()
    } catch (cause: unknown) {
      setLibraryError(getApiErrorMessage(cause, 'The retry failed.'))
    } finally {
      setRetryingId(null)
    }
  }

  async function handleDownload(item: ReportExport) {
    setLibraryError(null)
    setDownloadingId(item.id)
    try {
      await downloadReportExportFile(item.id)
    } catch (cause: unknown) {
      setLibraryError(getApiErrorMessage(cause, 'The download failed.'))
      library.refresh()
    } finally {
      setDownloadingId(null)
    }
  }

  const appliedLabel = periodLabel(effectiveState, options.data)

  const scoped: ScopedReport | null = institution.data
    ? { kind: 'institution', data: institution.data }
    : department.data
      ? { kind: 'department', data: department.data }
      : employeeSelf.data
        ? { kind: 'employee', data: employeeSelf.data }
        : null

  const scopedLoading = institution.loading || department.loading || employeeSelf.loading
  const scopedError = institution.error ?? department.error ?? employeeSelf.error
  const retryScoped = () => {
    institution.refresh()
    department.refresh()
    employeeSelf.refresh()
  }

  const kpi = useMemo(() => {
    if (institution.data && effectiveState.scope === 'institution') {
      const summary = institution.data.summary
      return {
        employees: num(summary.total_employees),
        completion: pctOrNa(summary.task_completion_rate),
        submitted: num(summary.reports_submitted),
        late: num(summary.late_reports),
        pending: num(summary.pending_reports),
        approval: pctOrNa(summary.approval_rate),
        employeesHint: `${num(summary.active_employees)} active across ${num(summary.total_departments)} departments`,
        completionHint: `${num(summary.tasks_completed)} of ${num(summary.tasks_assigned)} tasks completed`,
        submittedHint: `In ${institution.data.period}`,
        lateHint: `${num(summary.late_outstanding)} overdue and unsubmitted`,
        pendingHint: `${num(summary.awaiting_action)} awaiting action`,
        approvalHint: 'Approved out of reviewed submissions',
      }
    }
    if (department.data && effectiveState.scope === 'department') {
      const metrics = department.data.metrics
      return {
        employees: num(department.data.department.employee_count),
        completion: pctOrNa(metrics.task_completion_rate),
        submitted: num(metrics.reports_submitted),
        late: num(metrics.late_reports),
        pending: 'N/A',
        approval: pctOrNa(metrics.approval_rate),
        employeesHint: department.data.department.name,
        completionHint: `${num(metrics.tasks_completed)} of ${num(metrics.tasks_assigned)} tasks completed`,
        submittedHint: `In ${department.data.period}`,
        lateHint: `On-time rate ${pctOrNa(metrics.on_time_rate)}`,
        pendingHint: 'Pending report counts are only available institution-wide',
        approvalHint: 'Approved out of reviewed submissions',
      }
    }
    if (employeeSelf.data && effectiveState.scope === 'employee') {
      const metrics = employeeSelf.data.metrics
      return {
        employees: 'N/A',
        completion: pctOrNa(metrics.task_completion_rate),
        submitted: num(metrics.reports_submitted),
        late: 'N/A',
        pending: num(Math.max(0, metrics.reports_expected - metrics.reports_submitted)),
        approval: pctOrNa(metrics.approval_rate),
        employeesHint: 'Employee scope shows a single employee',
        completionHint: `${num(metrics.tasks_completed)} of ${num(metrics.tasks_assigned)} tasks completed`,
        submittedHint: `${num(metrics.reports_expected)} expected`,
        lateHint: 'Late counts are available per employee in the Employee Performance tab',
        pendingHint: 'Expected reports still outstanding',
        approvalHint: 'Approved out of reviewed submissions',
      }
    }
    return null
  }, [institution.data, department.data, employeeSelf.data, effectiveState.scope])

  const availableTabs = TABS.filter((tab) => {
    if (tab.key === 'overview') return true
    if (tab.key === 'data-quality') return canInstitution
    if (tab.key === 'employees') return canDepartment
    if (tab.key === 'departments') return canDepartment
    if (tab.key === 'monthly') return canDepartment
    if (tab.key === 'submissions') return canDepartment
    if (tab.key === 'targets') return canDepartment
    return false
  })

  const exportsAll = library.data ?? []
  const exportsVisible = exportsAll.filter((item) => !statusFilter || item.status === statusFilter)

  const kpiSkeleton = (
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
      {Array.from({ length: 6 }).map((_, index) => (
        <div key={index} className="h-28 rounded-2xl bg-slate-100 animate-pulse" />
      ))}
    </div>
  )

  return (
    <AdminPage
      title="Reports & Analytics"
      subtitle="One workspace for performance, submissions, targets and data quality"
      actions={
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone="info">{appliedLabel}</Badge>
          {refreshedAt ? (
            <span className="text-[11px] text-slate-400">
              Refreshed {refreshedAt.toLocaleTimeString()}
            </span>
          ) : null}
          <Button variant="secondary" onClick={refreshAll}>
            Refresh
          </Button>
        </div>
      }
    >
      <div className="space-y-6">
        <FilterBar>
          {canInstitution ? (
            <Field label="Scope">
              <Select
                value={draft.scope}
                onChange={(value) =>
                  setDraft((prev) => ({ ...prev, scope: value as Scope }))
                }
                options={[
                  { value: 'institution', label: 'Institution' },
                  { value: 'department', label: 'Department' },
                  { value: 'employee', label: 'Employee' },
                ]}
              />
            </Field>
          ) : (
            <Field label="Scope">
              <TextInput
                value={isEmployee ? 'My performance' : 'My department'}
                onChange={() => undefined}
              />
            </Field>
          )}

          {canInstitution && draft.scope === 'department' ? (
            <Field label="Department">
              <Select
                value={draft.departmentId}
                onChange={(value) => setDraft((prev) => ({ ...prev, departmentId: value }))}
                options={(options.data?.departments ?? []).map((dept) => ({
                  value: String(dept.id),
                  label: dept.name,
                }))}
                placeholder="All departments"
              />
            </Field>
          ) : null}

          {canInstitution && draft.scope === 'employee' ? (
            <Field label="Employee">
              <Select
                value={draft.employeeId}
                onChange={(value) => setDraft((prev) => ({ ...prev, employeeId: value }))}
                options={(employeePicker.data ?? [])
                  .filter((row) => row.employee_id !== null)
                  .map((row) => ({
                    value: String(row.employee_id),
                    label: `${row.employee_name ?? 'Employee'}${row.employee_number ? ` · ${row.employee_number}` : ''}`,
                  }))}
                placeholder={employeePicker.loading ? 'Loading employees…' : 'Choose an employee'}
              />
            </Field>
          ) : null}

          {canInstitution ? (
            <Field label="Academic year">
              <Select
                value={draft.academicYearId}
                onChange={(value) => setDraft((prev) => ({ ...prev, academicYearId: value }))}
                options={(options.data?.academic_years ?? []).map((academicYear) => ({
                  value: String(academicYear.id),
                  label: academicYear.name,
                }))}
                placeholder="Use period below"
              />
            </Field>
          ) : null}

          {draft.academicYearId === '' ? (
            <>
              <Field label="Period">
                <Select
                  value={draft.periodType}
                  onChange={(value) =>
                    setDraft((prev) => ({ ...prev, periodType: value as PeriodType }))
                  }
                  options={[
                    { value: 'monthly', label: 'Monthly' },
                    { value: 'quarterly', label: 'Quarterly' },
                    { value: 'yearly', label: 'Yearly' },
                    { value: 'custom', label: 'Custom range' },
                  ]}
                />
              </Field>
              {draft.periodType !== 'custom' ? (
                <Field label="Year">
                  <Select
                    value={String(draft.year)}
                    onChange={(value) => setDraft((prev) => ({ ...prev, year: Number(value) }))}
                    options={
                      options.data?.years?.length
                        ? options.data.years
                        : Array.from({ length: 6 }).map((_, index) => ({
                            value: String(new Date().getFullYear() - 3 + index),
                            label: String(new Date().getFullYear() - 3 + index),
                          }))
                    }
                  />
                </Field>
              ) : null}
              {draft.periodType === 'monthly' ? (
                <Field label="Month">
                  <Select
                    value={String(draft.month)}
                    onChange={(value) => setDraft((prev) => ({ ...prev, month: Number(value) }))}
                    options={MONTH_NAMES.map((name, index) => ({
                      value: String(index + 1),
                      label: name,
                    }))}
                  />
                </Field>
              ) : null}
              {draft.periodType === 'quarterly' ? (
                <Field label="Quarter">
                  <Select
                    value={String(draft.quarter)}
                    onChange={(value) => setDraft((prev) => ({ ...prev, quarter: Number(value) }))}
                    options={[1, 2, 3, 4].map((quarter) => ({
                      value: String(quarter),
                      label: `Q${quarter}`,
                    }))}
                  />
                </Field>
              ) : null}
              {draft.periodType === 'custom' ? (
                <>
                  <Field label="Start date">
                    <TextInput
                      type="date"
                      value={draft.startDate}
                      onChange={(value) => setDraft((prev) => ({ ...prev, startDate: value }))}
                    />
                  </Field>
                  <Field label="End date">
                    <TextInput
                      type="date"
                      value={draft.endDate}
                      onChange={(value) => setDraft((prev) => ({ ...prev, endDate: value }))}
                    />
                  </Field>
                </>
              ) : null}
            </>
          ) : null}

          <div className="flex items-center gap-2 ml-auto">
            <Button onClick={applyFilters}>Apply</Button>
            <Button variant="secondary" onClick={resetFilters}>
              Reset
            </Button>
          </div>
        </FilterBar>

        {filterError ? (
          <p className="text-xs font-semibold text-red-600" role="alert">
            {filterError}
          </p>
        ) : null}

        <section aria-label="Key performance indicators" className="space-y-3">
          {scopedLoading && !scoped ? (
            kpiSkeleton
          ) : scopedError ? (
            <ErrorPanel message={scopedError} onRetry={retryScoped} />
          ) : kpi ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
              <StatCard
                label="Total Employees"
                value={kpi.employees}
                hint={kpi.employeesHint}
                icon={
                  <span className="text-slate-400">
                    <KpiIcon>
                      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                      <circle cx="9" cy="7" r="4" />
                      <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </KpiIcon>
                  </span>
                }
              />
              <StatCard
                label="Task Completion Rate"
                value={kpi.completion}
                hint={kpi.completionHint}
                tone="info"
                icon={
                  <span className="text-slate-400">
                    <KpiIcon>
                      <path d="M9 11l3 3L22 4" />
                      <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                    </KpiIcon>
                  </span>
                }
              />
              <StatCard
                label="Reports Submitted"
                value={kpi.submitted}
                hint={kpi.submittedHint}
                tone="ok"
                icon={
                  <span className="text-slate-400">
                    <KpiIcon>
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                      <polyline points="14 2 14 8 20 8" />
                      <line x1="16" y1="13" x2="8" y2="13" />
                      <line x1="16" y1="17" x2="8" y2="17" />
                    </KpiIcon>
                  </span>
                }
              />
              <StatCard
                label="Late Reports"
                value={kpi.late}
                hint={kpi.lateHint}
                tone={typeof kpi.late === 'string' && kpi.late !== '0' && kpi.late !== 'N/A' ? 'danger' : 'ok'}
                icon={
                  <span className="text-slate-400">
                    <KpiIcon>
                      <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                      <line x1="12" y1="9" x2="12" y2="13" />
                      <line x1="12" y1="17" x2="12.01" y2="17" />
                    </KpiIcon>
                  </span>
                }
              />
              <StatCard
                label="Pending Reports"
                value={kpi.pending}
                hint={kpi.pendingHint}
                tone="warn"
                icon={
                  <span className="text-slate-400">
                    <KpiIcon>
                      <circle cx="12" cy="12" r="10" />
                      <polyline points="12 6 12 12 16 14" />
                    </KpiIcon>
                  </span>
                }
              />
              <StatCard
                label="Approval Rate"
                value={kpi.approval}
                hint={kpi.approvalHint}
                tone="ok"
                icon={
                  <span className="text-slate-400">
                    <KpiIcon>
                      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                      <polyline points="22 4 12 14.01 9 11.01" />
                    </KpiIcon>
                  </span>
                }
              />
            </div>
          ) : null}
        </section>

        <div className="space-y-3">
          <div className="flex flex-wrap gap-1 border-b border-slate-200 pb-1" role="tablist" aria-label="Reporting categories">
            {availableTabs.map((tab) => (
              <button
                key={tab.key}
                type="button"
                role="tab"
                aria-selected={activeTab === tab.key}
                title={tab.description}
                onClick={() => setActiveTab(tab.key)}
                className={`px-3 py-2 rounded-t-lg text-sm font-bold border-b-2 transition ${
                  activeTab === tab.key
                    ? 'border-jpc-600 text-jpc-700 bg-jpc-50/60'
                    : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>

          {activeTab === 'overview' ? (
            <OverviewTab
              key={`overview-${refreshToken}-${JSON.stringify(filters)}`}
              filters={filters}
              scoped={scoped}
              loading={scopedLoading}
              error={scopedError}
              onRetry={retryScoped}
              canInstitution={canInstitution}
              periodLabel={appliedLabel}
            />
          ) : null}

          {activeTab === 'employees' ? (
            <EmployeePerformanceTab
              key={`employees-${refreshToken}-${JSON.stringify(filters)}`}
              filters={filters}
              options={options.data}
            />
          ) : null}

          {activeTab === 'departments' ? (
            <DepartmentPerformanceTab
              key={`departments-${refreshToken}-${JSON.stringify(filters)}`}
              filters={filters}
              periodLabel={appliedLabel}
            />
          ) : null}

          {activeTab === 'monthly' ? (
            <MonthlyPerformanceTab
              key={`monthly-${refreshToken}-${JSON.stringify(filters)}`}
              filters={filters}
              scoped={scoped}
              periodLabel={appliedLabel}
            />
          ) : null}

          {activeTab === 'submissions' ? (
            <SubmissionMonitoringTab
              key={`submissions-${refreshToken}-${JSON.stringify(filters)}`}
              filters={filters}
              periodLabel={appliedLabel}
            />
          ) : null}

          {activeTab === 'targets' ? (
            <TargetAchievementTab
              key={`targets-${refreshToken}-${JSON.stringify(filters)}`}
              filters={filters}
              periodLabel={appliedLabel}
            />
          ) : null}

          {activeTab === 'data-quality' ? (
            <DataQualityTab
              key={`data-quality-${refreshToken}-${JSON.stringify(filters)}`}
              filters={filters}
            />
          ) : null}
        </div>

        <Panel
          title="Report Library"
          description="Exports you have generated. Downloads unlock once a file is ready."
          actions={<Badge tone="neutral">{`${exportsAll.length} on record`}</Badge>}
        >
          <div className="flex flex-wrap items-end justify-between gap-3 pb-4">
            <div className="flex flex-wrap items-end gap-3">
              <Field label="Report type">
                <Select
                  value={exportType}
                  onChange={(value) => setExportType(value as ReportExportTypeValue)}
                  options={EXPORT_TYPES}
                />
              </Field>
              <Field label="Format">
                <Select
                  value={exportFormat}
                  onChange={(value) => setExportFormat(value as ExportFormat)}
                  options={[
                    { value: 'xlsx', label: 'XLSX' },
                    { value: 'csv', label: 'CSV' },
                    { value: 'pdf', label: 'PDF' },
                  ]}
                />
              </Field>
              <Button onClick={() => void handleGenerateExport()} disabled={exporting}>
                {exporting ? 'Queueing…' : 'Generate export'}
              </Button>
            </div>
            <Field label="Status">
              <Select
                value={statusFilter}
                onChange={setStatusFilter}
                options={EXPORT_STATUSES.map((status) => ({
                  value: status,
                  label: humanise(status),
                }))}
                placeholder="All statuses"
              />
            </Field>
          </div>

          {exportNotice ? (
            <p className="text-xs text-slate-600 pb-3" role="status">
              {exportNotice}
            </p>
          ) : null}

          {libraryError ? (
            <p className="text-xs font-semibold text-red-600 pb-3" role="alert">
              {libraryError}
            </p>
          ) : null}

          {library.loading && !library.data ? (
            <LoadingPanel label="Loading report library" rows={2} />
          ) : library.error ? (
            <ErrorPanel message={library.error} onRetry={library.refresh} />
          ) : exportsVisible.length === 0 ? (
            <EmptyState
              title="No reports in the library"
              message={
                statusFilter
                  ? `No exports with status ${humanise(statusFilter)}.`
                  : 'Generate an export above to add the first entry.'
              }
            />
          ) : (
            <Table>
              <thead>
                <tr>
                  <Th>Report</Th>
                  <Th>Period</Th>
                  <Th>Requested</Th>
                  <Th>Status</Th>
                  <Th className="text-right">Actions</Th>
                </tr>
              </thead>
              <tbody>
                {exportsVisible.map((item) => {
                  const expanded = expandedId === item.id
                  const canDownload = item.status === 'COMPLETED'
                  return (
                    <Fragment key={item.id}>
                      <tr className="border-b border-slate-100 last:border-0">
                        <Td>
                          <p className="font-semibold text-slate-800">{humanise(item.report_type)}</p>
                          <p className="text-[11px] text-slate-400">{item.format.toUpperCase()}</p>
                        </Td>
                        <Td className="text-slate-500 text-xs">{exportPeriodLabel(item, options.data)}</Td>
                        <Td className="text-slate-500 text-xs">{dateTime(item.requested_at)}</Td>
                        <Td>
                          <StatusBadge status={item.status} label={item.status_label} />
                        </Td>
                        <Td className="text-right">
                          <div className="flex justify-end gap-2">
                            <Button
                              variant="ghost"
                              className="!px-2 !py-1 !text-xs"
                              onClick={() => setExpandedId(expanded ? null : item.id)}
                            >
                              {expanded ? 'Hide' : 'View'}
                            </Button>
                            {canDownload || item.status === 'FAILED' ? (
                              <Button
                                variant={canDownload ? 'secondary' : 'ghost'}
                                className="!px-2 !py-1 !text-xs"
                                disabled={canDownload
                                  ? downloadingId === item.id
                                  : retryingId === item.id}
                                onClick={() =>
                                  void (canDownload ? handleDownload(item) : handleRetry(item))}
                              >
                                {canDownload
                                  ? (downloadingId === item.id ? 'Downloading…' : 'Download')
                                  : (retryingId === item.id ? 'Re-queuing…' : 'Retry')}
                              </Button>
                            ) : null}
                          </div>
                        </Td>
                      </tr>
                      {expanded ? (
                        <tr className="bg-slate-50/70">
                          <td colSpan={5} className="px-3 py-3">
                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-[11px]">
                              <div>
                                <p className="font-bold uppercase tracking-wider text-slate-500">Rows</p>
                                <p className="text-slate-700">{num(item.row_count)}</p>
                              </div>
                              <div>
                                <p className="font-bold uppercase tracking-wider text-slate-500">Size</p>
                                <p className="text-slate-700">{formatBytes(item.file_size)}</p>
                              </div>
                              <div>
                                <p className="font-bold uppercase tracking-wider text-slate-500">Completed</p>
                                <p className="text-slate-700">{dateTime(item.completed_at)}</p>
                              </div>
                              <div>
                                <p className="font-bold uppercase tracking-wider text-slate-500">Expires</p>
                                <p className="text-slate-700">{dateTime(item.expires_at)}</p>
                              </div>
                              <div>
                                <p className="font-bold uppercase tracking-wider text-slate-500">File</p>
                                <p className="text-slate-700 break-all">{item.file_name ?? '—'}</p>
                              </div>
                              <div>
                                <p className="font-bold uppercase tracking-wider text-slate-500">Status</p>
                                <p className="text-slate-700">{item.status_label}</p>
                              </div>
                            </div>
                            {item.error_message ? (
                              <p className="mt-2 text-[11px] text-red-600">
                                {item.error_message}
                                {item.error_line ? ` (line ${item.error_line})` : ''}
                              </p>
                            ) : null}
                          </td>
                        </tr>
                      ) : null}
                    </Fragment>
                  )
                })}
              </tbody>
            </Table>
          )}
        </Panel>
      </div>
    </AdminPage>
  )
}
