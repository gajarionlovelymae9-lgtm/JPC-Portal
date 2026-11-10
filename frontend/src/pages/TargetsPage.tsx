import { useEffect, useState } from 'react'
import {
  AdminPage,
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
  num,
  pct,
  shortDate,
} from '../components/admin/AdminUi'
import type { ApiError } from '../services/api'
import {
  createHrTarget,
  deleteHrTarget,
  fetchHrTargetDetail,
  fetchHrTargets,
  updateHrTarget,
} from '../services/targetService'
import type {
  HrTargetFilters,
  HrTargetItem,
  HrTargetOptions,
  HrTargetSummary,
} from '../services/targetService'

interface HrTargetMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface ListState {
  summary: HrTargetSummary
  items: HrTargetItem[]
  meta: HrTargetMeta
  options: HrTargetOptions
}

type FieldErrors = Record<string, string[]>

interface SelectorOption {
  value: string | number
  label: string
}

/**
 * Empty form state. IDs stay '' until chosen so the backend's `required`
 * rules fire instead of a numeric 0 being sent.
 */
function blankForm(): Record<string, string> {
  return {
    annual_plan_id: '',
    employee_id: '',
    title: '',
    description: '',
    target_value: '',
    target_unit: '',
    start_date: '',
    end_date: '',
    status: 'draft',
  }
}

function firstError(errors: FieldErrors, field: string): string {
  return errors[field]?.[0] ?? ''
}

function normaliseErrorDetails(details: unknown): FieldErrors {
  if (details === null || typeof details !== 'object') return {}
  return Object.fromEntries(
    Object.entries(details as Record<string, unknown>).map(([key, value]) => [
      key,
      Array.isArray(value) ? value.map(String) : [String(value)],
    ]),
  )
}

/**
 * Targets Management (HR) — the real surface behind what used to be a
 * "Coming soon" placeholder.
 *
 * Every number comes from the live `employee_targets` chain via
 * GET /api/v1/hr/targets — the same summary cards and decorated rows the
 * Admin KPI targets page reads, so both portals agree on progress and
 * status. No mock data.
 */
export default function TargetsPage() {
  const [filters, setFilters] = useState<HrTargetFilters>({})
  const [data, setData] = useState<ListState | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [searchDraft, setSearchDraft] = useState('')

  // Detail view modal.
  const [viewTarget, setViewTarget] = useState<HrTargetItem | null>(null)
  const [viewLoading, setViewLoading] = useState(false)

  // Create / edit modal.
  const [formOpen, setFormOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState(blankForm())
  const [formErrors, setFormErrors] = useState<FieldErrors>({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  // Delete confirmation.
  const [deleting, setDeleting] = useState<HrTargetItem | null>(null)
  const [deleteBusy, setDeleteBusy] = useState(false)

  const load = async (nextFilters: HrTargetFilters = filters) => {
    setLoading(true)
    setError('')
    try {
      const response = await fetchHrTargets(nextFilters)
      setData(response.data as unknown as ListState)
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Unable to load targets.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void load({})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const options = data?.options

  const updateFilter = (key: keyof HrTargetFilters, value: string) => {
    const next = { ...filters, [key]: value === '' ? undefined : value, page: 1 }
    setFilters(next)
    void load(next)
  }

  const employeeOptions = (): SelectorOption[] =>
    (options?.employees ?? [])
      .filter(
        (employee) =>
          !filters.department_id || String(employee.department_id) === filters.department_id,
      )
      .map((employee) => ({
        value: employee.id,
        label: `${employee.name} (${employee.employee_number ?? 'no ID'}) — ${employee.department_name ?? 'No department'}`,
      }))

  const planOptions = (): SelectorOption[] =>
    (options?.annual_plans ?? []).map((plan) => ({
      value: plan.id,
      label: `${plan.title ?? `Plan #${plan.id}`}${plan.academic_year ? ` — ${plan.academic_year}` : ''}`,
    }))

  const openCreate = () => {
    setEditingId(null)
    setForm(blankForm())
    setFormErrors({})
    setFormError('')
    setFormOpen(true)
  }

  const openEdit = async (id: number) => {
    setFormErrors({})
    setFormError('')
    setViewTarget(null)
    setViewLoading(true)
    try {
      const detail = await fetchHrTargetDetail(id)
      setEditingId(detail.id)
      setForm({
        // employee_id is intentionally absent: edits never re-parent a target.
        annual_plan_id: detail.annual_plan_id ? String(detail.annual_plan_id) : '',
        employee_id: '',
        title: detail.kpi,
        description: detail.description ?? '',
        target_value: detail.target === null ? '' : String(detail.target),
        target_unit: detail.target_unit ?? '',
        start_date: detail.start_date ?? '',
        end_date: detail.end_date ?? '',
        status: detail.stored_status,
      })
      setFormOpen(true)
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Unable to load the target.')
    } finally {
      setViewLoading(false)
    }
  }

  const openView = async (id: number) => {
    setViewTarget(null)
    setViewLoading(true)
    try {
      const detail = await fetchHrTargetDetail(id)
      setViewTarget(detail)
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Unable to load the target.')
    } finally {
      setViewLoading(false)
    }
  }

  const validateForm = (): FieldErrors => {
    const errors: FieldErrors = {}
    if (!form.annual_plan_id) errors.annual_plan_id = ['Select the annual plan (academic year).']
    if (!editingId && !form.employee_id) errors.employee_id = ['Select the employee assigned to this target.']
    if (!form.title.trim()) errors.title = ['Give the target a title.']
    if (form.target_value !== '' && Number(form.target_value) < 0) {
      errors.target_value = ['The target value cannot be negative.']
    }
    if (!form.start_date && !form.end_date) {
      errors.start_date = ['Set a start date, a due date, or both.']
    }
    return errors
  }

  const saveForm = async () => {
    const local = validateForm()
    if (form.start_date && form.end_date && new Date(form.end_date) < new Date(form.start_date)) {
      local.end_date = ['The due date cannot fall before the start date.']
    }
    setFormErrors({})
    setFormError('')
    if (Object.keys(local).length > 0) {
      setFormErrors(local)
      return
    }

    setSaving(true)
    try {
      if (editingId === null) {
        setViewTarget(await createHrTarget(form))
      } else {
        setViewTarget(await updateHrTarget(editingId, form))
      }
      setFormOpen(false)
      await load()
    } catch (cause: unknown) {
      const apiError = cause as ApiError
      if (apiError?.details && typeof apiError.details === 'object') {
        setFormErrors(normaliseErrorDetails(apiError.details))
      } else {
        setFormError(cause instanceof Error ? cause.message : 'Unable to save the target.')
      }
    } finally {
      setSaving(false)
    }
  }

  const confirmDelete = async () => {
    if (!deleting) return
    setDeleteBusy(true)
    try {
      await deleteHrTarget(deleting.id)
      setDeleting(null)
      setViewTarget(null)
      await load()
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Unable to delete the target.')
      setDeleting(null)
    } finally {
      setDeleteBusy(false)
    }
  }

  return (
    <AdminPage
      title="Targets"
      subtitle="Employee targets: assign, measure and track real progress from live task data"
      actions={<Button onClick={openCreate}>New target</Button>}
    >
      <FilterBar>
        <Field label="Search" className="min-w-56 flex-1">
          <TextInput
            value={searchDraft}
            onChange={(value) => setSearchDraft(value)}
            placeholder="Title or description…"
          />
        </Field>
        <Field label="Department">
          <Select
            value={filters.department_id ?? ''}
            onChange={(value) => updateFilter('department_id', value)}
            options={(options?.departments ?? []).map((d) => ({ value: d.id, label: d.name }))}
            placeholder="All departments"
          />
        </Field>
        <Field label="Employee">
          <Select
            key={filters.department_id ?? 'all'}
            value={filters.employee_id ?? ''}
            onChange={(value) => updateFilter('employee_id', value)}
            options={employeeOptions()}
            placeholder="All employees"
          />
        </Field>
        <Field label="Academic year">
          <Select
            value={filters.academic_year_id ?? ''}
            onChange={(value) => updateFilter('academic_year_id', value)}
            options={(options?.academic_years ?? []).map((y) => ({ value: y.id, label: y.name }))}
            placeholder="All years"
          />
        </Field>
        <Field label="Status">
          <Select
            value={filters.status ?? ''}
            onChange={(value) => updateFilter('status', value)}
            options={options?.statuses ?? []}
            placeholder="All statuses"
          />
        </Field>
        <Field label="Apply">
          <Button variant="secondary" onClick={() => updateFilter('search', searchDraft)}>
            Search
          </Button>
        </Field>
      </FilterBar>

      {loading ? (
        <LoadingPanel label="Loading targets" />
      ) : error && !data ? (
        <ErrorPanel message={error} onRetry={() => void load()} />
      ) : data ? (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Total Targets" value={num(data.summary.total_kpis)} />
            <StatCard label="Active Targets" value={num(data.summary.in_progress_targets)} tone="info" />
            <StatCard label="Completed Targets" value={num(data.summary.completed_targets)} tone="ok" />
            <StatCard
              label="Pending Targets"
              value={num(data.summary.not_started_targets + data.summary.missed_targets)}
              hint="Not started, or past due with unfinished work"
            />
          </div>

          <Panel title="Target register" description={`${num(data.meta.total)} target${data.meta.total === 1 ? '' : 's'}`}>
            {error && data.items.length === 0 ? (
              <EmptyState title="Could not refresh the table" message={error} />
            ) : data.items.length === 0 ? (
              <EmptyState
                title="No targets match"
                message="Adjust the filters, or create the first target with “New target”."
              />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Target Title</Th>
                      <Th>Employee</Th>
                      <Th>Department</Th>
                      <Th>Target Period</Th>
                      <Th className="text-right">Target Value</Th>
                      <Th className="text-right">Progress</Th>
                      <Th>Status</Th>
                      <Th>Actions</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((target) => (
                      <TargetRow
                        key={target.id}
                        target={target}
                        onView={() => void openView(target.id)}
                        onEdit={() => void openEdit(target.id)}
                        onDelete={() => setDeleting(target)}
                      />
                    ))}
                  </tbody>
                </Table>
                <Pagination meta={data.meta} onPageChange={(page) => updateFilter('page', String(page))} />
              </>
            )}
          </Panel>
        </div>
      ) : (
        <ErrorPanel message="Unable to load targets." onRetry={() => void load()} />
      )}

      {/* ---------------- Create / edit modal ---------------- */}
      <Modal
        open={formOpen}
        title={editingId === null ? 'Create target' : 'Edit target'}
        onClose={() => setFormOpen(false)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setFormOpen(false)}>Cancel</Button>
            <Button onClick={() => void saveForm()} disabled={saving}>
              {saving ? 'Saving…' : editingId === null ? 'Create target' : 'Save changes'}
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          {formError ? <ErrorPanel message={formError} /> : null}
          <Field label="Annual plan (academic year)" required hint={firstError(formErrors, 'annual_plan_id')}>
            <Select
              value={form.annual_plan_id}
              onChange={(value) => setForm((state) => ({ ...state, annual_plan_id: value }))}
              options={planOptions()}
              placeholder="Select an annual plan"
            />
          </Field>
          {!editingId ? (
            <Field label="Employee" required hint={firstError(formErrors, 'employee_id')}>
              <Select
                key={filters.department_id ?? 'all'}
                value={form.employee_id}
                onChange={(value) => setForm((state) => ({ ...state, employee_id: value }))}
                options={employeeOptions()}
                placeholder="Select an employee"
              />
            </Field>
          ) : null}
          <Field label="Title" required hint={firstError(formErrors, 'title')}>
            <TextInput
              value={form.title}
              onChange={(value) => setForm((state) => ({ ...state, title: value }))}
              placeholder="e.g. Enrolment follow-ups completed"
            />
          </Field>
          <Field label="Description">
            <textarea
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-jpc-500 focus:outline-none"
              rows={3}
              value={form.description}
              onChange={(event) => setForm((state) => ({ ...state, description: event.target.value }))}
              placeholder="Measurable objective — what does success look like?"
            />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Target value" hint={firstError(formErrors, 'target_value')}>
              <TextInput
                value={form.target_value}
                onChange={(value) => setForm((state) => ({ ...state, target_value: value }))}
                type="number"
                placeholder="e.g. 25"
              />
            </Field>
            <Field label="Unit">
              <TextInput
                value={form.target_unit}
                onChange={(value) => setForm((state) => ({ ...state, target_unit: value }))}
                placeholder="e.g. forms"
              />
            </Field>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Start date" hint={firstError(formErrors, 'start_date')}>
              <TextInput
                value={form.start_date}
                onChange={(value) => setForm((state) => ({ ...state, start_date: value }))}
                type="date"
              />
            </Field>
            <Field label="Due date" hint={firstError(formErrors, 'end_date')}>
              <TextInput
                value={form.end_date}
                onChange={(value) => setForm((state) => ({ ...state, end_date: value }))}
                type="date"
              />
            </Field>
          </div>
          <Field label="Status" hint={firstError(formErrors, 'status')}>
            <Select
              value={form.status}
              onChange={(value) => setForm((state) => ({ ...state, status: value }))}
              options={options?.stored_statuses ?? []}
              placeholder="Draft"
            />
          </Field>
          {editingId !== null ? (
            <p className="text-[11px] text-slate-500">
              The employee assignment is fixed once created; progress comes from linked tasks and cannot be
              edited by hand.
            </p>
          ) : null}
        </div>
      </Modal>

      {/* ---------------- View modal ---------------- */}
      <Modal
        open={viewTarget !== null || viewLoading}
        title="Target details"
        onClose={() => setViewTarget(null)}
        footer={
          viewTarget ? (
            <>
              <Button variant="secondary" onClick={() => setViewTarget(null)}>Close</Button>
              <Button
                variant="danger"
                onClick={() => {
                  setDeleting(viewTarget)
                  setViewTarget(null)
                }}
              >
                Delete
              </Button>
              <Button onClick={() => void openEdit(viewTarget.id)}>Edit</Button>
            </>
          ) : null
        }
      >
        {viewLoading && !viewTarget ? (
          <LoadingPanel label="Loading target" />
        ) : viewTarget ? (
          <div className="space-y-2 text-sm text-slate-700">
            <p className="text-lg font-semibold text-slate-900">{viewTarget.kpi}</p>
            {viewTarget.description ? (
              <p className="text-slate-600 whitespace-pre-line">{viewTarget.description}</p>
            ) : null}
            <dl className="grid grid-cols-2 gap-2 pt-2">
              <dt className="text-slate-500">Employee</dt>
              <dd className="font-medium">{viewTarget.employee_name ?? '—'}</dd>
              <dt className="text-slate-500">Department</dt>
              <dd className="font-medium">{viewTarget.department_name ?? '—'}</dd>
              <dt className="text-slate-500">Period</dt>
              <dd className="font-medium">{viewTarget.period}</dd>
              <dt className="text-slate-500">Target value</dt>
              <dd className="font-medium tabular-nums">
                {viewTarget.target === null
                  ? '—'
                  : `${num(viewTarget.target)}${viewTarget.target_unit ? ` ${viewTarget.target_unit}` : ''}`}
              </dd>
              <dt className="text-slate-500">Progress</dt>
              <dd className="font-medium tabular-nums">
                {viewTarget.achievement_pct === null
                  ? '— (no linked task data)'
                  : `${pct(viewTarget.achievement_pct)} (${viewTarget.tasks_completed}/${viewTarget.tasks_total} tasks)`}
              </dd>
              <dt className="text-slate-500">Status</dt>
              <dd className="font-medium">
                <StatusBadge status={viewTarget.derived_status} />
              </dd>
              <dt className="text-slate-500">Start date</dt>
              <dd className="font-medium">{shortDate(viewTarget.start_date)}</dd>
              <dt className="text-slate-500">Due date</dt>
              <dd className="font-medium">{shortDate(viewTarget.end_date)}</dd>
            </dl>
          </div>
        ) : null}
      </Modal>

      {/* ---------------- Delete confirmation ---------------- */}
      <Modal
        open={deleting !== null}
        title="Delete target"
        onClose={() => setDeleting(null)}
        footer={
          <>
            <Button variant="secondary" onClick={() => setDeleting(null)}>Cancel</Button>
            <Button variant="danger" onClick={() => void confirmDelete()} disabled={deleteBusy}>
              {deleteBusy ? 'Deleting…' : 'Delete target'}
            </Button>
          </>
        }
      >
        <p className="text-sm text-slate-700">
          Delete <strong>{deleting?.kpi}</strong> for{' '}
          <strong>{deleting?.employee_name ?? 'the assigned employee'}</strong>? The record is archived — reports
          keep their history — but it disappears from the register.
        </p>
      </Modal>
    </AdminPage>
  )
}

function TargetRow({
  target,
  onView,
  onEdit,
  onDelete,
}: {
  target: HrTargetItem
  onView: () => void
  onEdit: () => void
  onDelete: () => void
}) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <Td>
        <p className="font-medium text-slate-800">{target.kpi}</p>
        {target.description ? (
          <p className="text-[11px] text-slate-400 max-w-xs truncate">{target.description}</p>
        ) : null}
      </Td>
      <Td>
        <p className="font-semibold text-slate-700">{target.employee_name}</p>
        <p className="text-[11px] text-slate-400">{target.employee_number}</p>
      </Td>
      <Td className="text-slate-500">{target.department_name ?? '—'}</Td>
      <Td className="text-slate-500 text-xs">{target.period}</Td>
      <Td className="text-right tabular-nums">
        {target.target === null ? '—' : num(target.target)}
        {target.target_unit ? <span className="text-[11px] text-slate-400"> {target.target_unit}</span> : null}
      </Td>
      <Td className="text-right tabular-nums">
        <div className="flex items-center justify-end gap-2">
          <div className="h-1.5 w-16 rounded-full bg-slate-100 overflow-hidden">
            <div
              className={`h-full rounded-full ${toneForProgress(target.achievement_pct)}`}
              style={{ width: `${Math.min(Math.max(target.achievement_pct ?? 0, 0), 100)}%` }}
            />
          </div>
          <span className="font-semibold">
            {target.achievement_pct === null ? '—' : pct(target.achievement_pct, 0)}
          </span>
        </div>
        <p className="text-[10px] text-slate-400 pt-1">
          {target.tasks_completed}/{target.tasks_total} tasks
        </p>
      </Td>
      <Td>
        <StatusBadge status={target.derived_status} />
      </Td>
      <Td>
        <div className="flex gap-1.5">
          <Button variant="ghost" onClick={onView}>View</Button>
          <Button variant="ghost" onClick={onEdit}>Edit</Button>
          <Button variant="ghost" className="!text-rose-600" onClick={onDelete}>Delete</Button>
        </div>
      </Td>
    </tr>
  )
}

function toneForProgress(value: number | null): string {
  if (value === null) return 'bg-slate-300'
  if (value >= 100) return 'bg-emerald-500'
  if (value >= 50) return 'bg-jpc-500'
  return 'bg-amber-400'
}
