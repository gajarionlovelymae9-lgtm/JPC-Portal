import { useCallback, useMemo, useState } from 'react'
import {
  confirmIssue,
  dismissIssue,
  resolveIssue,
  reviewIssue,
} from '../api/dataQuality/dataQualityIssueApi'
import {
  AdminPage,
  Badge,
  Button,
  EmptyState,
  ErrorPanel,
  Field,
  FilterBar,
  LoadingPanel,
  Modal,
  Pagination,
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
  pct,
  shortDate,
} from '../components/admin/AdminUi'
import { useAuth } from '../auth/useAuth'
import { SearchableSelect } from '../components/common/SearchableSelect'
import { useAdminResource } from '../hooks/useAdminResource'
import { ApiError } from '../services/api'
import {
  checkHrEmployeeDuplicateName,
  createHrEmployee,
  getHrEmployeeProfile,
  getHrEmployees,
  getHrOptions,
  setHrEmployeeAccountStatus,
  updateHrEmployee,
  type HrDuplicateNameMatch,
  type HrEmployeeInput,
  type HrEmployeeProfile,
  type HrValidationAlert,
} from '../services/hrService'

/**
 * HR employee management and monitoring.
 *
 * Two screens in one route, because that is how HR uses it: a searchable
 * directory to find the person, then their full record to act on. The directory
 * stays on `GET /hr/employees` — the same endpoint the dashboard already reads —
 * so the two can never show different numbers for the same employee.
 *
 * Nothing here computes a statistic. Every count and rate arrives from the API,
 * and `pct()` renders `null` as `-` because `null` means "there is nothing to
 * divide by", which is not the same as 0%.
 *
 * Actions are permission-gated twice: the buttons only render when the signed-in
 * user holds the matching `employees.*` permission, and the API rejects the call
 * regardless. Hiding a button is a courtesy, not the control.
 */

type FormMode = { kind: 'create' } | { kind: 'edit'; employeeId: number }

const EMPLOYMENT_TYPES = ['regular', 'contractual', 'part_time', 'temporary']

/** Values the edit form starts from, derived from the loaded profile. */
function formFromProfile(profile: HrEmployeeProfile): HrEmployeeInput {
  return {
    employee_number: profile.personal.employee_number,
    first_name: profile.personal.first_name,
    middle_name: profile.personal.middle_name,
    last_name: profile.personal.last_name,
    suffix: profile.personal.suffix,
    email: profile.personal.email,
    username: profile.personal.username,
    department_id: profile.organisation.department_id,
    position_id: profile.organisation.position_id,
    supervisor_id: profile.organisation.supervisor_id,
    employment_status: profile.employment.employment_status,
    employment_type: profile.employment.employment_type,
    date_hired: profile.employment.date_hired,
    date_of_birth: profile.personal.date_of_birth,
    phone: profile.personal.phone,
    address: profile.personal.address,
  }
}

const EMPTY_FORM: HrEmployeeInput = {
  employee_number: '',
  first_name: '',
  middle_name: null,
  last_name: '',
  suffix: null,
  email: null,
  username: null,
  password: null,
  account_status: null,
  department_id: null,
  position_id: null,
  supervisor_id: null,
  employment_status: 'active',
  employment_type: 'regular',
  date_hired: null,
  date_of_birth: null,
  phone: null,
  address: null,
  reason: null,
}

/** Turns an `ApiError` into per-field messages for the form. */
function fieldErrors(error: unknown): Record<string, string[]> {
  if (error instanceof ApiError && error.details && typeof error.details === 'object') {
    const flat: Record<string, string[]> = {}
    for (const [field, messages] of Object.entries(error.details as Record<string, unknown>)) {
      flat[field] = Array.isArray(messages) ? messages.map(String) : [String(messages)]
    }
    return flat
  }
  return {}
}

export default function EmployeesPage({ embedded = false }: { embedded?: boolean } = {}) {
  const { user } = useAuth()
  const permissions = user?.permissions ?? []
  const canCreate = permissions.includes('employees.create')
  const canUpdate = permissions.includes('employees.update')

  /* ---------------------------------------------------------------- */
  /* Directory filters                                                 */
  /* ---------------------------------------------------------------- */
  const [filters, setFilters] = useState({
    search: '',
    department_id: '',
    position_id: '',
    status: '',
    account_status: '',
    page: 1,
  })

  const options = useAdminResource(() => getHrOptions())
  const directory = useAdminResource(
    () => getHrEmployees({ ...filters, per_page: 15 }),
    [filters],
  )

  /* ---------------------------------------------------------------- */
  /* Selected employee + profile                                       */
  /* ---------------------------------------------------------------- */
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [profile, setProfile] = useState<HrEmployeeProfile | null>(null)
  const [profileLoading, setProfileLoading] = useState(false)
  const [profileError, setProfileError] = useState<string | null>(null)

  const loadProfile = useCallback(async (id: number) => {
    setProfileLoading(true)
    setProfileError(null)
    try {
      const response = await getHrEmployeeProfile(id)
      setProfile(response.data)
    } catch (cause) {
      setProfileError(cause instanceof ApiError ? cause.message : 'Could not load this employee.')
      setProfile(null)
    } finally {
      setProfileLoading(false)
    }
  }, [])

  const openProfile = useCallback(
    (id: number) => {
      setSelectedId(id)
      setProfile(null)
      void loadProfile(id)
    },
    [loadProfile],
  )

  const closeProfile = useCallback(() => {
    setSelectedId(null)
    setProfile(null)
    setProfileError(null)
  }, [])

  /* ---------------------------------------------------------------- */
  /* Create / edit                                                     */
  /* ---------------------------------------------------------------- */
  const [formMode, setFormMode] = useState<FormMode | null>(null)
  const [form, setForm] = useState<HrEmployeeInput | null>(null)
  const [formLoading, setFormLoading] = useState(false)
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [duplicateMatches, setDuplicateMatches] = useState<HrDuplicateNameMatch[]>([])
  const [duplicateChecking, setDuplicateChecking] = useState(false)

  const openCreate = useCallback(() => {
    setFormMode({ kind: 'create' })
    setForm(EMPTY_FORM)
    setFormLoading(false)
    setFormErrors({})
    setFormError(null)
    setDuplicateMatches([])
  }, [])

  /**
   * The edit form is seeded from the full profile, not from the directory row,
   * so it must not open until that request lands. `form === null` means "still
   * loading" and the dialog says so, instead of briefly showing a create form
   * pre-filled with empty defaults that a fast click could save.
   */
  const openEdit = useCallback(
    async (employeeId: number) => {
      setFormMode({ kind: 'edit', employeeId })
      setForm(null)
      setFormLoading(true)
      setFormErrors({})
      setFormError(null)
      setDuplicateMatches([])
      try {
        const response = await getHrEmployeeProfile(employeeId)
        setForm(formFromProfile(response.data))
        setProfile(response.data)
        setSelectedId(employeeId)
      } catch (cause) {
        setFormError(cause instanceof ApiError ? cause.message : 'Could not load this employee.')
      } finally {
        setFormLoading(false)
      }
    },
    [],
  )

  const closeForm = useCallback(() => {
    setFormMode(null)
    setForm(null)
    setFormLoading(false)
    setFormErrors({})
    setFormError(null)
    setDuplicateMatches([])
  }, [])

  const setField = useCallback(<K extends keyof HrEmployeeInput>(key: K, value: HrEmployeeInput[K]) => {
    setForm((previous) => (previous ? { ...previous, [key]: value } : previous))
    // A warning about the previous names must not survive a rename.
    if (key === 'first_name' || key === 'middle_name' || key === 'last_name') {
      setDuplicateMatches([])
    }
  }, [])

  const submit = useCallback(
    async (proceedDespiteDuplicate = false) => {
      // Guard the loading window as well as "no dialog": an empty payload would
      // otherwise PATCH nothing while still writing an activity-log entry.
      if (!formMode || !form) return

      setFormErrors({})
      setFormError(null)

      // A same-name colleague is a warning, not a block: show who it is and let
      // the operator proceed deliberately. Check failures are advisory only, so
      // they never prevent a save.
      if (!proceedDespiteDuplicate) {
        setDuplicateChecking(true)
        try {
          const result = await checkHrEmployeeDuplicateName({
            first_name: form.first_name ?? '',
            middle_name: form.middle_name ?? null,
            last_name: form.last_name ?? '',
            exclude_id: formMode.kind === 'edit' ? formMode.employeeId : undefined,
          })
          if ((result.data?.matches ?? []).length > 0) {
            setDuplicateMatches(result.data.matches)
            return
          }
        } catch {
          // Advisory only.
        } finally {
          setDuplicateChecking(false)
        }
      }

      setDuplicateMatches([])
      setSaving(true)
      try {
        // The reason is only meaningful on an edit, where changed fields are
        // recorded against it; create has nothing to attribute.
        const payload: HrEmployeeInput = { ...form }
        if (formMode.kind === 'create') {
          delete payload.reason
          if (!payload.password) delete payload.password
        }

        const response =
          formMode.kind === 'create'
            ? await createHrEmployee(payload)
            : await updateHrEmployee(formMode.employeeId, payload)

        setProfile(response.data)
        setSelectedId(response.data.id)
        closeForm()
        directory.reload()
        void loadProfile(response.data.id)
      } catch (cause) {
        const errors = fieldErrors(cause)
        if (Object.keys(errors).length > 0) {
          setFormErrors(errors)
        } else {
          setFormError(cause instanceof ApiError ? cause.message : 'Could not save this employee.')
        }
      } finally {
        setSaving(false)
      }
    },
    [closeForm, directory, form, formMode, loadProfile],
  )

  /* ---------------------------------------------------------------- */
  /* Account status                                                    */
  /* ---------------------------------------------------------------- */
  const [accountBusy, setAccountBusy] = useState(false)
  const [accountError, setAccountError] = useState<string | null>(null)

  const changeAccountStatus = useCallback(
    async (id: number, accountStatus: string) => {
      setAccountBusy(true)
      setAccountError(null)
      try {
        const response = await setHrEmployeeAccountStatus(id, accountStatus)
        setProfile(response.data)
        directory.reload()
      } catch (cause) {
        setAccountError(cause instanceof ApiError ? cause.message : 'Could not change the account status.')
      } finally {
        setAccountBusy(false)
      }
    },
    [directory],
  )

  /* ---------------------------------------------------------------- */
  /* Data-quality alert actions                                        */
  /* ---------------------------------------------------------------- */
  const [alertBusy, setAlertBusy] = useState<number | null>(null)
  const [alertError, setAlertError] = useState<string | null>(null)

  /**
   * Runs one issue action against the existing data-quality endpoints, so the
   * review workflow stays in one place rather than growing a second set of
   * transitions behind this page.
   */
  const runAlertAction = useCallback(
    async (
      alert: HrValidationAlert,
      action: 'review' | 'confirm' | 'resolve' | 'dismiss',
      promptLabel: string,
    ) => {
      const note =
        action === 'dismiss'
          ? window.prompt(`Reason for dismissing "${alert.title ?? 'this alert'}":`)
          : window.prompt(`${promptLabel} (optional):`) ?? undefined

      if (note === null) return

      setAlertBusy(alert.id)
      setAlertError(null)
      try {
        if (action === 'review') await reviewIssue(alert.id, note || undefined)
        else if (action === 'confirm') await confirmIssue(alert.id, note || undefined)
        else if (action === 'resolve') await resolveIssue(alert.id, note || '')
        else await dismissIssue(alert.id, note || 'Dismissed from the HR employee record.')

        if (selectedId) await loadProfile(selectedId)
      } catch (cause) {
        setAlertError(cause instanceof ApiError ? cause.message : 'Could not update this alert.')
      } finally {
        setAlertBusy(null)
      }
    },
    [loadProfile, selectedId],
  )

  /* ---------------------------------------------------------------- */
  /* Derived                                                           */
  /* ---------------------------------------------------------------- */
  const rows = directory.data?.items ?? []
  const meta = directory.data?.meta ?? null
  const summary = directory.data?.summary ?? null

  const departmentOptions = useMemo(
    () => (options.data?.departments ?? []).map((d) => ({ value: d.id, label: d.name })),
    [options.data],
  )
  const positionOptions = useMemo(
    () => (options.data?.positions ?? []).map((p) => ({ value: p.id, label: p.name })),
    [options.data],
  )
  const employmentStatusOptions = useMemo(
    () => (options.data?.employment_statuses ?? []).map((s) => ({ value: s, label: humanise(s) })),
    [options.data],
  )
  const accountStatusOptions = useMemo(
    () => (options.data?.account_statuses ?? []).map((s) => ({ value: s, label: humanise(s) })),
    [options.data],
  )

  const openAlertCount = profile?.validation_alerts.filter((alert) => alert.is_open).length ?? 0

  /* ---------------------------------------------------------------- */
  /* Profile detail                                                    */
  /* ---------------------------------------------------------------- */
  const renderProfile = () => {
    if (profileLoading) return <LoadingPanel label="Loading employee" rows={2} />
    if (profileError) return <ErrorPanel message={profileError} onRetry={() => selectedId && void loadProfile(selectedId)} />
    if (!profile) return <EmptyState title="Select an employee" message="Choose someone from the directory to see their full record." />

    return (
      <div className="space-y-4">
        {/* Identity ------------------------------------------------------ */}
        <Panel
          title={profile.personal.full_name}
          description={`${profile.personal.employee_number}${
            profile.organisation.department ? ` · ${profile.organisation.department}` : ''
          }`}
          actions={
            <>
              <StatusBadge status={profile.employment.employment_status} />
              {profile.employment.has_account ? (
                <StatusBadge status={profile.employment.account_status} label={`Account: ${humanise(profile.employment.account_status)}`} />
              ) : (
                <Badge tone="neutral">No login account</Badge>
              )}
              {canUpdate ? (
                <Button variant="secondary" onClick={() => void openEdit(profile.id)}>
                  Edit record
                </Button>
              ) : null}
            </>
          }
        >
          <dl className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
            <Detail label="Employee ID" value={profile.personal.employee_number} />
            <Detail label="Department" value={profile.organisation.department} />
            <Detail label="Position" value={profile.organisation.position} />
            <Detail label="Email" value={profile.personal.email} />
            <Detail label="Username" value={profile.personal.username} />
            <Detail label="Phone" value={profile.personal.phone} />
            <Detail label="Employment type" value={profile.employment.employment_type ? humanise(profile.employment.employment_type) : null} />
            <Detail label="Date hired" value={profile.employment.date_hired ? shortDate(profile.employment.date_hired) : null} />
            <Detail label="Supervisor" value={profile.organisation.supervisor} />
            <Detail label="Employment status" value={profile.employment.employment_status} />
            <Detail label="Account status" value={profile.employment.has_account ? humanise(profile.employment.account_status) : null} />
            <Detail label="Date of birth" value={profile.personal.date_of_birth ? shortDate(profile.personal.date_of_birth) : null} />
            <Detail label="Role" value={profile.employment.role ? humanise(profile.employment.role) : null} />
            <Detail label="Last sign-in" value={profile.employment.last_login_at ? dateTime(profile.employment.last_login_at) : null} />
            <Detail label="Address" value={profile.personal.address} />
          </dl>
        </Panel>

        {/* Performance ---------------------------------------------------- */}
        <Panel title="Performance" description="Live aggregates over this employee's tasks and reports.">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <StatCard label="Task completion" value={pct(profile.performance.completion_rate)} tone="info" />
            <StatCard label="On-time reports" value={pct(profile.performance.on_time_submission_rate ?? null)} tone={(profile.performance.on_time_submission_rate ?? 0) >= 80 ? 'ok' : 'warn'} />
            <StatCard label="Report approval" value={pct(profile.performance.approval_rate ?? null)} tone={(profile.performance.approval_rate ?? 0) >= 70 ? 'ok' : 'warn'} />
            <StatCard
              label="Late items"
              value={num(profile.performance.late_tasks + profile.performance.late_reports)}
              tone={profile.performance.late_tasks + profile.performance.late_reports > 0 ? 'danger' : 'neutral'}
            />
          </div>

          <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
            <StatCard label="Tasks assigned" value={num(profile.tasks.total)} />
            <StatCard label="Tasks completed" value={num(profile.tasks.completed)} tone="ok" />
            <StatCard label="Tasks pending" value={num(profile.tasks.pending)} tone="warn" />
            <StatCard label="Tasks late" value={num(profile.tasks.late)} tone={profile.tasks.late > 0 ? 'danger' : 'neutral'} />
            <StatCard label="Reports total" value={num(profile.reports.total)} />
            <StatCard label="Reports submitted" value={num(profile.reports.submitted)} />
            <StatCard label="Awaiting review" value={num(profile.reports.pending)} tone="warn" />
            <StatCard label="Approved" value={num(profile.reports.approved)} tone="ok" />
            <StatCard label="Needs revision" value={num(profile.reports.revision_required)} tone="warn" />
            <StatCard label="Rejected" value={num(profile.reports.rejected)} tone={profile.reports.rejected > 0 ? 'danger' : 'neutral'} />
            <StatCard label="Late reports" value={num(profile.reports.late)} tone={profile.reports.late > 0 ? 'danger' : 'neutral'} />
          </div>
        </Panel>

        {/* Account control ----------------------------------------------- */}
        {canUpdate && profile.employment.has_account ? (
          <Panel title="Login account" description="Deactivating keeps the record and its audit history; it only blocks sign-in.">
            <div className="flex flex-wrap items-end gap-3">
              <Field label="Account status" className="w-56">
                <Select
                  value={profile.employment.account_status ?? ''}
                  onChange={(value) => void changeAccountStatus(profile.id, value)}
                  options={accountStatusOptions}
                  placeholder="Select status"
                />
              </Field>
              {accountBusy ? <span className="text-xs text-slate-500">Saving…</span> : null}
            </div>
            {accountError ? (
              <p role="alert" className="text-sm font-semibold text-red-600 mt-3">
                {accountError}
              </p>
            ) : null}
          </Panel>
        ) : null}

        {/* Data validation ------------------------------------------------ */}
        <Panel
          title="Data validation"
          description={
            profile.validation_alerts.length === 0
              ? 'No issues have been detected on this record.'
              : `${openAlertCount} still need${openAlertCount === 1 ? 's' : ''} a decision.`
          }
        >
          {alertError ? (
            <p role="alert" className="text-sm font-semibold text-red-600 mb-3">
              {alertError}
            </p>
          ) : null}
          {profile.validation_alerts.length === 0 ? (
            <EmptyState title="Nothing to review" message="Automated checks have not flagged this employee record." />
          ) : (
            <ul className="space-y-3">
              {profile.validation_alerts.map((alert) => (
                <li key={alert.id} className="border border-slate-200 rounded-xl p-3">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p className="text-sm font-bold text-slate-800">{alert.title ?? humanise(alert.issue_type)}</p>
                      {alert.description ? <p className="text-xs text-slate-600 mt-0.5">{alert.description}</p> : null}
                      <p className="text-[11px] text-slate-400 mt-1">
                        {humanise(alert.severity)} · detected {dateTime(alert.created_at)}
                        {alert.reviewed_at ? ` · reviewed ${dateTime(alert.reviewed_at)}` : ''}
                      </p>
                    </div>
                    <StatusBadge
                      status={alert.status}
                      label={alert.is_open ? humanise(alert.status) : `${humanise(alert.status)}`}
                    />
                  </div>
                  {alert.detected_value ? (
                    <p className="text-[11px] text-slate-500 mt-2">
                      Detected value: {JSON.stringify(alert.detected_value)}
                    </p>
                  ) : null}
                  {alert.is_open ? (
                    <div className="flex flex-wrap gap-2 mt-3">
                      <Button variant="secondary" disabled={alertBusy === alert.id} onClick={() => void runAlertAction(alert, 'review', 'Note for this review')}>
                        Review
                      </Button>
                      <Button variant="secondary" disabled={alertBusy === alert.id} onClick={() => void runAlertAction(alert, 'confirm', 'Note for this confirmation')}>
                        Confirm
                      </Button>
                      <Button variant="primary" disabled={alertBusy === alert.id} onClick={() => void runAlertAction(alert, 'resolve', 'How was this resolved?')}>
                        Resolve
                      </Button>
                      <Button variant="ghost" disabled={alertBusy === alert.id} onClick={() => void runAlertAction(alert, 'dismiss', 'Reason for dismissing')}>
                        Dismiss
                      </Button>
                    </div>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </Panel>

        {/* Audit history -------------------------------------------------- */}
        <Panel title="Activity & audit history" description="Field-level changes and the actions taken against this record.">
          {profile.history.length === 0 ? (
            <EmptyState title="No history yet" message="Changes made to this record will appear here." />
          ) : (
            <ol className="space-y-3">
              {profile.history.map((entry) => (
                <li key={`${entry.source}-${entry.id}`} className="flex gap-3">
                  <span className="mt-1.5 w-2 h-2 rounded-full bg-slate-300 shrink-0" aria-hidden="true" />
                  <div className="min-w-0">
                    <p className="text-sm text-slate-800 break-words">
                      {entry.description ?? humanise(entry.event)}
                    </p>
                    <p className="text-[11px] text-slate-500">
                      {entry.actor ?? 'System'} · {dateTime(entry.occurred_at)}
                    </p>
                  </div>
                </li>
              ))}
            </ol>
          )}
        </Panel>
      </div>
    )
  }

  /* ---------------------------------------------------------------- */
  /* Render                                                            */
  /* ---------------------------------------------------------------- */
  return (
    <AdminPage
      title={embedded ? undefined : "Employee Management"}
      subtitle={embedded ? undefined : "Search the directory, review an employee's record, and manage their HR details and login."}
      actions={
        canCreate ? (
          <Button onClick={openCreate}>Add employee</Button>
        ) : (
          <p className="text-xs text-slate-500">You have read-only access to the employee directory.</p>
        )
      }
    >
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Employees" value={num(summary?.total)} hint="Matching the current filters" />
        <StatCard label="With overdue tasks" value={num(summary?.with_overdue)} tone={summary?.with_overdue ? 'danger' : 'neutral'} />
        <StatCard label="With late reports" value={num(summary?.with_late_reports)} tone={summary?.with_late_reports ? 'danger' : 'neutral'} />
        <StatCard label="Accounts" value={num(rows.filter((row) => row.has_account).length)} hint="On this page" />
      </div>

      <FilterBar>
        <Field label="Search" className="w-56">
          <TextInput
            value={filters.search}
            onChange={(value) => setFilters((f) => ({ ...f, search: value, page: 1 }))}
            placeholder="Name, ID or email"
          />
        </Field>
        <Field label="Department" className="w-52">
          <SearchableSelect
            value={filters.department_id}
            onChange={(value) => setFilters((f) => ({ ...f, department_id: value, page: 1 }))}
            options={departmentOptions}
            placeholder="All departments"
            emptyMessage="No department matches that search."
          />
        </Field>
        <Field label="Position" className="w-52">
          <Select
            value={filters.position_id}
            onChange={(value) => setFilters((f) => ({ ...f, position_id: value, page: 1 }))}
            options={positionOptions}
            placeholder="All positions"
          />
        </Field>
        <Field label="Employment" className="w-44">
          <Select
            value={filters.status}
            onChange={(value) => setFilters((f) => ({ ...f, status: value, page: 1 }))}
            options={employmentStatusOptions}
            placeholder="Any status"
          />
        </Field>
        <Field label="Account" className="w-44">
          <Select
            value={filters.account_status}
            onChange={(value) => setFilters((f) => ({ ...f, account_status: value, page: 1 }))}
            options={accountStatusOptions}
            placeholder="Any account"
          />
        </Field>
      </FilterBar>

      {directory.loading ? (
        <LoadingPanel label="Loading employees" />
      ) : directory.error ? (
        <ErrorPanel message={directory.error} onRetry={directory.reload} />
      ) : rows.length === 0 ? (
        <Panel>
          <EmptyState
            title="No employees match these filters"
            message="Adjust the search or filters above, or clear them to see everyone."
          />
        </Panel>
      ) : (
        <Panel>
          <Table>
            <thead>
              <tr>
                <Th>Employee</Th>
                <Th>Employee ID</Th>
                <Th>Email</Th>
                <Th>Department</Th>
                <Th>Position</Th>
                <Th>Employment</Th>
                <Th>Account</Th>
                <Th>Hired</Th>
                <Th>Completion</Th>
                <Th>Late</Th>
                <Th aria-label="Actions" />
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-slate-100 last:border-0">
                  <Td>
                    <button
                      type="button"
                      onClick={() => openProfile(row.id)}
                      className="font-bold text-jpc-700 hover:underline text-left"
                    >
                      {row.name}
                    </button>
                  </Td>
                  <Td className="font-mono text-xs">{row.employee_number}</Td>
                  <Td>{row.email ?? <span className="text-slate-400">—</span>}</Td>
                  <Td>{row.department ?? '—'}</Td>
                  <Td>{row.position ?? '—'}</Td>
                  <Td>
                    <StatusBadge status={row.status} />
                  </Td>
                  <Td>
                    {row.has_account ? (
                      <StatusBadge status={row.account_status} />
                    ) : (
                      <Badge tone="neutral">None</Badge>
                    )}
                  </Td>
                  <Td>{row.date_hired ? shortDate(row.date_hired) : '—'}</Td>
                  <Td>{pct(row.completion_rate)}</Td>
                  <Td>
                    {row.late_tasks + row.late_reports > 0 ? (
                      <Badge tone="danger">{row.late_tasks + row.late_reports}</Badge>
                    ) : (
                      <span className="text-slate-400">0</span>
                    )}
                  </Td>
                  <Td>
                    <div className="flex gap-1.5">
                      <Button variant="ghost" onClick={() => openProfile(row.id)}>
                        View
                      </Button>
                      {canUpdate ? (
                        <Button variant="secondary" onClick={() => void openEdit(row.id)}>
                          Edit
                        </Button>
                      ) : null}
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
          {meta ? <Pagination meta={meta} onPageChange={(page) => setFilters((f) => ({ ...f, page }))} /> : null}
        </Panel>
      )}

      {/* Profile -------------------------------------------------------- */}
      {selectedId !== null ? (
        <Panel
          title={profile ? `${profile.personal.full_name} · detail` : 'Employee record'}
          description={profile ? [profile.personal.employee_number, profile.organisation.department, profile.organisation.position].filter(Boolean).join(' · ') : undefined}
          actions={<Button variant="secondary" onClick={closeProfile} aria-label="Close employee detail">× Close</Button>}
        >
          {renderProfile()}
        </Panel>
      ) : null}

      {/* Create / edit dialog ------------------------------------------- */}
      <Modal
        open={formMode !== null}
        title={formMode?.kind === 'edit' ? 'Edit employee' : 'Add employee'}
        onClose={closeForm}
        footer={
          formLoading || !form ? null : duplicateMatches.length > 0 ? (
            <>
              <Button variant="secondary" onClick={() => setDuplicateMatches([])} disabled={saving}>
                Edit names
              </Button>
              <Button variant="danger" onClick={() => void submit(true)} disabled={saving}>
                {saving ? 'Saving…' : 'Proceed anyway'}
              </Button>
            </>
          ) : (
            <>
              <Button variant="secondary" onClick={closeForm} disabled={saving || duplicateChecking}>
                Cancel
              </Button>
              <Button onClick={() => void submit()} disabled={saving || duplicateChecking}>
                {duplicateChecking
                  ? 'Checking name…'
                  : saving
                    ? 'Saving…'
                    : formMode?.kind === 'edit'
                      ? 'Save changes'
                      : 'Create employee'}
              </Button>
            </>
          )
        }
      >
        {formLoading ? (
          <p className="text-sm text-slate-500" role="status" aria-busy="true">
            Loading the current record…
          </p>
        ) : form === null ? (
          formError ? (
            <p role="alert" className="text-sm font-semibold text-red-600">
              {formError}
            </p>
          ) : null
        ) : (
          <>
            {duplicateMatches.length > 0 ? (
              <DuplicateNameWarning matches={duplicateMatches} />
            ) : null}
            {formMode?.kind === 'edit' ? (
              <EditForm
                form={form}
                errors={formErrors}
                error={formError}
                hasAccount={profile?.employment.has_account ?? false}
                departmentOptions={departmentOptions}
                positionOptions={positionOptions}
                employmentStatusOptions={employmentStatusOptions}
                setField={setField}
              />
            ) : (
              <CreateForm
                form={form}
                errors={formErrors}
                error={formError}
                departmentOptions={departmentOptions}
                positionOptions={positionOptions}
                employmentStatusOptions={employmentStatusOptions}
                accountStatusOptions={accountStatusOptions}
                setField={setField}
              />
            )}
          </>
        )}
      </Modal>
    </AdminPage>
  )
}

/** A label/value pair that renders an em dash rather than a blank cell. */
function Detail({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">{label}</dt>
      <dd className="text-slate-800 mt-0.5 break-words">{value ?? '—'}</dd>
    </div>
  )
}

function FieldError({ errors, field }: { errors: Record<string, string[]>; field: string }) {
  const messages = errors[field]
  if (!messages || messages.length === 0) return null
  return (
    <span role="alert" className="text-[11px] text-red-600">
      {messages.join(' ')}
    </span>
  )
}

/**
 * Same-name warning. It names the matching employee, their ID and department
 * so the operator can tell a genuine namesake from a duplicate record, and it
 * never blocks the save — that decision belongs to the person holding the
 * record.
 */
function DuplicateNameWarning({ matches }: { matches: HrDuplicateNameMatch[] }) {
  return (
    <div role="alert" className="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-2">
      <p className="text-sm font-bold text-amber-900">A matching employee name already exists</p>
      <p className="text-xs text-amber-800">
        Check this is a different person before continuing. You can still proceed if it is.
      </p>
      <ul className="space-y-1">
        {matches.map((match) => (
          <li key={match.id} className="text-xs text-amber-900">
            <span className="font-semibold">{match.full_name}</span>
            {' · Employee ID '}
            <span className="font-mono">{match.employee_number}</span>
            {' · '}
            {match.department ?? 'No department'}
            {match.email ? ` · ${match.email}` : ''}
          </li>
        ))}
      </ul>
    </div>
  )
}

type FormProps = {
  form: HrEmployeeInput
  errors: Record<string, string[]>
  error: string | null
  departmentOptions: Array<{ value: number; label: string }>
  positionOptions: Array<{ value: number; label: string }>
  employmentStatusOptions: Array<{ value: string; label: string }>
  setField: <K extends keyof HrEmployeeInput>(key: K, value: HrEmployeeInput[K]) => void
}

function CreateForm({
  form,
  errors,
  error,
  departmentOptions,
  positionOptions,
  employmentStatusOptions,
  accountStatusOptions,
  setField,
}: FormProps & { accountStatusOptions: Array<{ value: string; label: string }> }) {
  return (
    <div className="space-y-4">
      {error ? (
        <p role="alert" className="text-sm font-semibold text-red-600">
          {error}
        </p>
      ) : null}

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Field label="Employee ID" required>
          <TextInput
            value={form.employee_number ?? ''}
            onChange={(value) => setField('employee_number', value)}
            placeholder="EMP-2026-001"
          />
          <FieldError errors={errors} field="employee_number" />
        </Field>
        <Field label="Employment status">
          <Select
            value={form.employment_status ?? 'active'}
            onChange={(value) => setField('employment_status', value)}
            options={employmentStatusOptions}
          />
        </Field>
        <Field label="First name" required>
          <TextInput value={form.first_name ?? ''} onChange={(value) => setField('first_name', value)} />
          <FieldError errors={errors} field="first_name" />
        </Field>
        <Field label="Last name" required>
          <TextInput value={form.last_name ?? ''} onChange={(value) => setField('last_name', value)} />
          <FieldError errors={errors} field="last_name" />
        </Field>
        <Field label="Middle name">
          <TextInput value={form.middle_name ?? ''} onChange={(value) => setField('middle_name', value || null)} />
        </Field>
        <Field label="Suffix">
          <TextInput value={form.suffix ?? ''} onChange={(value) => setField('suffix', value || null)} />
        </Field>
        <Field label="Department" required>
          <SearchableSelect
            value={form.department_id ? String(form.department_id) : ''}
            onChange={(value) => setField('department_id', value ? Number(value) : null)}
            options={departmentOptions}
            placeholder="Type to search a department"
            emptyMessage="No department matches that search."
          />
          <FieldError errors={errors} field="department_id" />
        </Field>
        <Field label="Position">
          <Select
            value={form.position_id ? String(form.position_id) : ''}
            onChange={(value) => setField('position_id', value ? Number(value) : null)}
            options={positionOptions}
            placeholder="No position"
          />
        </Field>
        <Field label="Employment type">
          <Select
            value={form.employment_type ?? 'regular'}
            onChange={(value) => setField('employment_type', value)}
            options={EMPLOYMENT_TYPES.map((t) => ({ value: t, label: humanise(t) }))}
          />
        </Field>
        <Field label="Date hired">
          <TextInput
            value={form.date_hired ?? ''}
            onChange={(value) => setField('date_hired', value || null)}
            type="date"
          />
        </Field>
        <Field label="Date of birth">
          <TextInput
            value={form.date_of_birth ?? ''}
            onChange={(value) => setField('date_of_birth', value || null)}
            type="date"
          />
          <FieldError errors={errors} field="date_of_birth" />
        </Field>
        <Field label="Phone">
          <TextInput value={form.phone ?? ''} onChange={(value) => setField('phone', value || null)} />
        </Field>
      </div>

      <Field label="Address">
        <TextInput value={form.address ?? ''} onChange={(value) => setField('address', value || null)} />
      </Field>

      {/* Account ------------------------------------------------------ */}
      <fieldset className="border border-slate-200 rounded-xl p-4 space-y-3">
        <legend className="text-xs font-bold uppercase tracking-wider text-slate-500 px-1">
          Login account (optional)
        </legend>
        <p className="text-xs text-slate-500">
          Leave the email blank to record the employee without a login. Supplying an email
          requires a password, otherwise the account could not sign in.
        </p>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Field label="Email">
            <TextInput
              value={form.email ?? ''}
              onChange={(value) => setField('email', value || null)}
              type="email"
              placeholder="name@jpc.local"
            />
            <FieldError errors={errors} field="email" />
          </Field>
          <Field label="Username" required hint="Required with an account; must be unique.">
            <TextInput
              value={form.username ?? ''}
              onChange={(value) => setField('username', value || null)}
              placeholder="name.surname"
            />
            <FieldError errors={errors} field="username" />
          </Field>
          <Field label="Temporary password" hint="They can change it after signing in.">
            <TextInput
              value={form.password ?? ''}
              onChange={(value) => setField('password', value || null)}
              type="password"
            />
            <FieldError errors={errors} field="password" />
          </Field>
          <Field label="Account status">
            <Select
              value={form.account_status ?? 'active'}
              onChange={(value) => setField('account_status', value)}
              options={accountStatusOptions}
            />
          </Field>
        </div>
      </fieldset>
    </div>
  )
}

function EditForm({
  form,
  errors,
  error,
  hasAccount,
  departmentOptions,
  positionOptions,
  employmentStatusOptions,
  setField,
}: FormProps & { hasAccount: boolean }) {
  return (
    <div className="space-y-4">
      {error ? (
        <p role="alert" className="text-sm font-semibold text-red-600">
          {error}
        </p>
      ) : null}

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Field label="Employee ID" required>
          <TextInput
            value={form.employee_number ?? ''}
            onChange={(value) => setField('employee_number', value)}
          />
          <FieldError errors={errors} field="employee_number" />
        </Field>
        <Field label="Employment status">
          <Select
            value={form.employment_status ?? ''}
            onChange={(value) => setField('employment_status', value)}
            options={employmentStatusOptions}
          />
          <FieldError errors={errors} field="employment_status" />
        </Field>
        <Field label="First name" required>
          <TextInput value={form.first_name ?? ''} onChange={(value) => setField('first_name', value)} />
          <FieldError errors={errors} field="first_name" />
        </Field>
        <Field label="Last name" required>
          <TextInput value={form.last_name ?? ''} onChange={(value) => setField('last_name', value)} />
          <FieldError errors={errors} field="last_name" />
        </Field>
        <Field label="Middle name">
          <TextInput value={form.middle_name ?? ''} onChange={(value) => setField('middle_name', value || null)} />
        </Field>
        <Field label="Suffix">
          <TextInput value={form.suffix ?? ''} onChange={(value) => setField('suffix', value || null)} />
        </Field>
        <Field label="Department" required>
          <SearchableSelect
            value={form.department_id ? String(form.department_id) : ''}
            onChange={(value) => setField('department_id', value ? Number(value) : null)}
            options={departmentOptions}
            placeholder="Type to search a department"
            emptyMessage="No department matches that search."
          />
          <FieldError errors={errors} field="department_id" />
        </Field>
        <Field label="Position">
          <Select
            value={form.position_id ? String(form.position_id) : ''}
            onChange={(value) => setField('position_id', value ? Number(value) : null)}
            options={positionOptions}
            placeholder="No position"
          />
        </Field>
        <Field label="Employment type">
          <Select
            value={form.employment_type ?? ''}
            onChange={(value) => setField('employment_type', value || null)}
            options={EMPLOYMENT_TYPES.map((t) => ({ value: t, label: humanise(t) }))}
            placeholder="Unchanged"
          />
        </Field>
        <Field label="Date hired">
          <TextInput
            value={form.date_hired ?? ''}
            onChange={(value) => setField('date_hired', value || null)}
            type="date"
          />
        </Field>
        <Field label="Date of birth">
          <TextInput
            value={form.date_of_birth ?? ''}
            onChange={(value) => setField('date_of_birth', value || null)}
            type="date"
          />
          <FieldError errors={errors} field="date_of_birth" />
        </Field>
        <Field label="Phone">
          <TextInput value={form.phone ?? ''} onChange={(value) => setField('phone', value || null)} />
        </Field>
      </div>

      <Field label="Address">
        <TextInput value={form.address ?? ''} onChange={(value) => setField('address', value || null)} />
      </Field>

      <fieldset className="border border-slate-200 rounded-xl p-4 space-y-3">
        <legend className="text-xs font-bold uppercase tracking-wider text-slate-500 px-1">
          Login account
        </legend>
        <p className="text-xs text-slate-500">
          {hasAccount
            ? 'Changing the email or password reuses the existing account. Account status is managed from the profile panel so it is always audited separately.'
            : 'This employee has no login yet. Supplying an email and password here creates one.'}
        </p>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Field label="Email">
            <TextInput
              value={form.email ?? ''}
              onChange={(value) => setField('email', value || null)}
              type="email"
            />
            <FieldError errors={errors} field="email" />
          </Field>
          <Field label="Username" hint="Must be unique across all accounts.">
            <TextInput
              value={form.username ?? ''}
              onChange={(value) => setField('username', value || null)}
            />
            <FieldError errors={errors} field="username" />
          </Field>
          <Field label="New password" hint="Leave blank to keep the current one.">
            <TextInput
              value={form.password ?? ''}
              onChange={(value) => setField('password', value || null)}
              type="password"
            />
            <FieldError errors={errors} field="password" />
          </Field>
        </div>
      </fieldset>

      <Field
        label="Reason for this change"
        hint="Recorded against every field you change, so the audit trail explains itself later."
      >
        <TextInput value={form.reason ?? ''} onChange={(value) => setField('reason', value || null)} />
        <FieldError errors={errors} field="reason" />
      </Field>
    </div>
  )
}