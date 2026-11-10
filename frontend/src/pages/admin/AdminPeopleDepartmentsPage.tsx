/**
 * AdminPeopleDepartmentsPage.tsx
 *
 * Department Management tab of the People Management module.
 * Admin can: list, create, edit, activate/deactivate, assign department
 * heads, and transfer employees between departments.
 *
 * There is deliberately no delete action — departments are deactivated, never
 * hard-deleted, so historical records keep resolving. The head picker only
 * offers employees of THIS department whose accounts already hold the
 * Department Head role; the server re-validates every rule on every call.
 */

import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '../../services/api'
import { getApiErrorMessage, getApiFieldErrors, type FieldErrors } from '../../utils/apiError'
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
  Panel,
  Select,
  StatCard,
  StatusBadge,
  Table,
  Td,
  Th,
  TextInput,
  humanise,
  pct,
} from '../../components/admin/AdminUi'
import {
  assignDepartmentEmployees,
  assignDepartmentHead,
  createDepartment,
  getDepartment,
  getDepartmentOptions,
  getDepartmentSummary,
  getEligibleDepartmentHeads,
  listDepartments,
  setDepartmentStatus,
  updateDepartment,
  type Department,
  type DepartmentDetail,
  type DepartmentOptions,
  type DepartmentSummary,
  type EligibleDepartmentHeads,
} from '../../services/peopleManagementService'

// ─── Types ────────────────────────────────────────────────────────────────────

type ModalMode = 'create' | 'edit' | 'detail' | 'assign-head' | 'assign-employees' | null

interface FormState {
  name: string
  code: string
  description: string
  status: string
}

const EMPTY_FORM: FormState = { name: '', code: '', description: '', status: 'active' }

// ─── Component ────────────────────────────────────────────────────────────────

export default function AdminPeopleDepartmentsPage({ embedded = false }: { embedded?: boolean } = {}) {
  const [departments, setDepartments] = useState<Department[]>([])
  const [summary, setSummary] = useState<DepartmentSummary | null>(null)
  const [options, setOptions] = useState<DepartmentOptions | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Filters
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  // Modal state
  const [modal, setModal] = useState<ModalMode>(null)
  const [selected, setSelected] = useState<Department | null>(null)
  const [detail, setDetail] = useState<DepartmentDetail | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)

  // Form state
  const [form, setForm] = useState<FormState>(EMPTY_FORM)
  const [headEmployeeId, setHeadEmployeeId] = useState('')
  const [headOptions, setHeadOptions] = useState<EligibleDepartmentHeads | null>(null)
  const [headLoading, setHeadLoading] = useState(false)
  const [headError, setHeadError] = useState<string | null>(null)
  const [headSearch, setHeadSearch] = useState('')
  const [transferIds, setTransferIds] = useState<number[]>([])
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})

  // ─── Data Loading ─────────────────────────────────────────────────────────

  const loadDepartments = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)
      const filters: Record<string, string> = {}
      if (search) filters.search = search
      if (statusFilter) filters.status = statusFilter
      const [data, counts] = await Promise.all([
        listDepartments(filters),
        getDepartmentSummary().catch(() => null),
      ])
      setDepartments(data)
      if (counts) setSummary(counts)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'Failed to load departments. Please try again.')
    } finally {
      setLoading(false)
    }
  }, [search, statusFilter])

  const loadOptions = useCallback(async () => {
    try {
      const data = await getDepartmentOptions()
      setOptions(data)
    } catch {
      // non-critical; transfer picker will show empty
    }
  }, [])

  /**
   * Fetch the server-resolved eligible heads for the selected department.
   * This is the single source of truth for the Assign Head modal, so an empty
   * result is accompanied by diagnostics instead of a silent blank dropdown.
   */
  const loadEligibleHeads = useCallback(async (departmentId: number) => {
    setHeadLoading(true)
    setHeadError(null)
    try {
      const data = await getEligibleDepartmentHeads(departmentId)
      setHeadOptions(data)
    } catch (err: unknown) {
      setHeadOptions(null)
      setHeadError(
        getApiErrorMessage(err, 'Could not load eligible employees. Please try again.'),
      )
    } finally {
      setHeadLoading(false)
    }
  }, [])

  useEffect(() => {
    loadDepartments()
  }, [loadDepartments])

  useEffect(() => {
    loadOptions()
  }, [loadOptions])

  useEffect(() => {
    if (modal === 'assign-head' && selected) {
      void loadEligibleHeads(selected.id)
    } else {
      setHeadOptions(null)
      setHeadError(null)
      setHeadSearch('')
      setHeadLoading(false)
    }
  }, [modal, selected, loadEligibleHeads])

  // ─── Modal Helpers ────────────────────────────────────────────────────────

  const openCreate = () => {
    setForm(EMPTY_FORM)
    setFormError(null)
    setFieldErrors({})
    setSelected(null)
    setModal('create')
  }

  const openEdit = (dept: Department) => {
    setSelected(dept)
    setForm({
      name: dept.name,
      code: dept.code ?? '',
      description: dept.description ?? '',
      status: dept.status,
    })
    setFormError(null)
    setFieldErrors({})
    setModal('edit')
  }

  const openDetail = async (dept: Department) => {
    setSelected(dept)
    setDetail(null)
    setModal('detail')
    setDetailLoading(true)
    try {
      const data = await getDepartment(dept.id)
      setDetail(data)
    } catch {
      setDetail(null)
    } finally {
      setDetailLoading(false)
    }
  }

  const openAssignHead = (dept: Department) => {
    setSelected(dept)
    setHeadEmployeeId('')
    setFormError(null)
    setModal('assign-head')
  }

  const openAssignEmployees = (dept: Department) => {
    setSelected(dept)
    setTransferIds([])
    setFormError(null)
    setModal('assign-employees')
  }

  const closeModal = () => {
    setModal(null)
    setSelected(null)
    setDetail(null)
    setFormError(null)
    setFieldErrors({})
  }

  /** Drop a field's server error as soon as the Admin edits that field. */
  const clearFieldError = (field: keyof FormState) => {
    setFieldErrors((current) => {
      if (!current[field]) return current
      const next = { ...current }
      delete next[field]
      return next
    })
  }

  // ─── Actions ──────────────────────────────────────────────────────────────

  const handleSave = async () => {
    const name = form.name.trim()

    if (!name) {
      setFieldErrors({ name: ['Please enter a department name.'] })
      setFormError(null)
      return
    }
    if (name.length > 255) {
      setFieldErrors({ name: ['The department name may not be longer than 255 characters.'] })
      setFormError(null)
      return
    }

    setSaving(true)
    setFormError(null)
    setFieldErrors({})
    try {
      if (modal === 'create') {
        await createDepartment({
          name,
          code: form.code.trim() || undefined,
          description: form.description.trim() || undefined,
          status: form.status,
        })
      } else if (modal === 'edit' && selected) {
        await updateDepartment(selected.id, {
          name,
          code: form.code.trim() || null,
          description: form.description.trim() || null,
          status: form.status,
        })
      }
      closeModal()
      await loadDepartments()
    } catch (err: unknown) {
      // Laravel returns `{ message, errors: { field: [message, ...] } }`;
      // surface each field error next to its input instead of one generic line.
      const fields = getApiFieldErrors(err)
      const knownFields = ['name', 'code', 'description', 'status']
      const hasFieldError = Object.keys(fields).some((field) => knownFields.includes(field))

      setFieldErrors(fields)
      setFormError(
        hasFieldError
          ? null
          : getApiErrorMessage(err, 'Could not save the department. Please try again.'),
      )
    } finally {
      setSaving(false)
    }
  }

  const handleToggleStatus = async (dept: Department) => {
    const action = dept.status === 'active' ? 'deactivate' : 'activate'
    if (!confirm(`Are you sure you want to ${action} "${dept.name}"?\n\nInactive departments are hidden from new employee assignments but keep all their members and history.`)) return
    try {
      await setDepartmentStatus(dept.id, dept.status === 'active' ? 'inactive' : 'active')
      await loadDepartments()
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to update department status. Please try again.'
      alert(msg)
    }
  }

  const handleAssignHead = async () => {
    if (!headEmployeeId) {
      setFormError('Please select an employee.')
      return
    }
    if (!selected) return
    setSaving(true)
    setFormError(null)
    try {
      await assignDepartmentHead(selected.id, parseInt(headEmployeeId, 10))
      closeModal()
      await loadDepartments()
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to assign department head.'
      setFormError(msg)
    } finally {
      setSaving(false)
    }
  }

  const handleTransfer = async () => {
    if (transferIds.length === 0) {
      setFormError('Select at least one employee to transfer.')
      return
    }
    if (!selected) return
    setSaving(true)
    setFormError(null)
    try {
      const result = await assignDepartmentEmployees(
        selected.id,
        transferIds,
      )
      closeModal()
      await loadDepartments()
      alert(`${result.transferred} employee(s) transferred to ${result.department.name}.`)
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to transfer employees.'
      setFormError(msg)
    } finally {
      setSaving(false)
    }
  }

  // ─── Derived ──────────────────────────────────────────────────────────────

  // Head candidates come from the server eligibility endpoint, which already
  // enforces department membership, active account, and Department Head role.
  const headCandidates = headOptions?.employees ?? []
  const headDiagnostics = headOptions?.diagnostics ?? null
  const currentHead = headOptions?.current_head_employee_id
    ? headCandidates.find((e) => e.id === headOptions.current_head_employee_id) ?? null
    : null
  const headSearchTerm = headSearch.trim().toLowerCase()
  const filteredHeadCandidates = headSearchTerm
    ? headCandidates.filter((e) =>
        [e.name, e.employee_number, e.position ?? ''].some((value) =>
          value.toLowerCase().includes(headSearchTerm),
        ),
      )
    : headCandidates

  // A clear, data-backed explanation for an empty picker. This never implies a
  // bug and always points Admins to User Management for role changes.
  const headEmptyExplanation = (() => {
    if (!headDiagnostics) {
      return 'No employees in this department can be assigned as head yet.'
    }
    if (headDiagnostics.total_employees === 0) {
      return 'This department has no employees yet. Transfer employees into it first, then configure a Department Head account in User Management.'
    }
    if (headDiagnostics.active_employees === 0) {
      return `This department has ${headDiagnostics.total_employees} employee(s), but none are currently employed.`
    }

    const reasons: string[] = []
    if (headDiagnostics.without_account > 0) {
      reasons.push(`${headDiagnostics.without_account} active employee(s) have no linked login account.`)
    }
    if (headDiagnostics.inactive_account > 0) {
      reasons.push(`${headDiagnostics.inactive_account} active employee(s) have an inactive account.`)
    }
    if (headDiagnostics.without_head_role > 0) {
      reasons.push(`${headDiagnostics.without_head_role} active employee(s) do not hold the Department Head role.`)
    }

    return reasons.length > 0
      ? reasons.join(' ')
      : 'No active employee in this department currently meets the Department Head eligibility rules.'
  })()

  // Transfer candidates: any active employee NOT already in this department.
  const transferCandidates = options
    ? options.employees.filter(
        (e) =>
          selected !== null &&
          e.department_id !== selected.id &&
          e.employment_status === 'active',
      )
    : []

  const departmentsWithoutHead = departments.filter((d) => !d.head_name || d.head_name === 'Unassigned').length

  // ─── Render ───────────────────────────────────────────────────────────────

  return (
    <AdminPage
      title={embedded ? undefined : 'Department Management'}
      subtitle={embedded ? undefined : 'Create, edit, and manage departments. Departments are deactivated instead of deleted so records always keep their history.'}
      actions={
        <Button onClick={openCreate}>+ Add Department</Button>
      }
    >
      {/* Summary cards — real counts from GET /admin/departments/summary */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <StatCard
          label="Total Departments"
          value={summary ? summary.total : departments.length}
          hint={summary ? `Across the institution` : undefined}
        />
        <StatCard label="Active" value={summary ? summary.active : '—'} tone="ok" />
        <StatCard label="Inactive" value={summary ? summary.inactive : '—'} tone={summary && summary.inactive > 0 ? 'warn' : 'neutral'} />
        <StatCard
          label="Without a Head"
          value={departmentsWithoutHead}
          tone={departmentsWithoutHead > 0 ? 'info' : 'neutral'}
          hint="Assign a head below"
        />
      </div>

      {/* Filters */}
      <FilterBar>
        <Field label="Search" className="flex-1 min-w-[180px]">
          <TextInput
            value={search}
            onChange={setSearch}
            placeholder="Search department name or code…"
          />
        </Field>
        <Field label="Status">
          <Select
            value={statusFilter}
            onChange={setStatusFilter}
            options={[
              { value: 'active', label: 'Active' },
              { value: 'inactive', label: 'Inactive' },
            ]}
            placeholder="All Statuses"
          />
        </Field>
        <Button variant="secondary" onClick={() => { setSearch(''); setStatusFilter('') }}>
          Clear
        </Button>
      </FilterBar>

      {/* Department Table */}
      <Panel title={`Departments (${departments.length})`}>
        {loading ? (
          <LoadingPanel rows={3} />
        ) : error ? (
          <ErrorPanel message={error} onRetry={loadDepartments} />
        ) : departments.length === 0 ? (
          <EmptyState
            title="No departments found"
            message="Add a new department using the button above, or clear your filters."
          />
        ) : (
          <Table>
            <thead>
              <tr>
                <Th>Department</Th>
                <Th>Code</Th>
                <Th>Head</Th>
                <Th>Employees</Th>
                <Th>Completion</Th>
                <Th>Status</Th>
                <Th>Actions</Th>
              </tr>
            </thead>
            <tbody>
              {departments.map((dept) => (
                <tr key={dept.id} className="border-b border-slate-100 hover:bg-slate-50">
                  <Td>
                    <button
                      type="button"
                      className="font-semibold text-jpc-700 hover:underline text-left"
                      onClick={() => openDetail(dept)}
                    >
                      {dept.name}
                    </button>
                    {dept.description && (
                      <p className="text-[11px] text-slate-400 mt-0.5 max-w-xs truncate">
                        {dept.description}
                      </p>
                    )}
                  </Td>
                  <Td>
                    {dept.code ? (
                      <Badge tone="info">{dept.code}</Badge>
                    ) : (
                      <span className="text-slate-300">—</span>
                    )}
                  </Td>
                  <Td>
                    <span className="text-sm">
                      {dept.head_employee_id ? dept.head_name : 'Unassigned'}
                    </span>
                    {dept.head_employee_number && (
                      <p className="text-[11px] text-slate-400">#{dept.head_employee_number}</p>
                    )}
                  </Td>
                  <Td>
                    <span className="tabular-nums">{dept.employees_count ?? 0}</span>
                    {dept.active_employees !== undefined && (
                      <span className="text-[11px] text-slate-400 ml-1">
                        ({dept.active_employees} active)
                      </span>
                    )}
                  </Td>
                  <Td>
                    {dept.completion_rate !== undefined ? (
                      <span
                        className={`tabular-nums font-semibold ${
                          dept.completion_rate >= 80
                            ? 'text-emerald-600'
                            : dept.completion_rate >= 50
                              ? 'text-amber-600'
                              : 'text-red-500'
                        }`}
                      >
                        {pct(dept.completion_rate, 1)}
                      </span>
                    ) : (
                      <span className="text-slate-300">—</span>
                    )}
                  </Td>
                  <Td>
                    <StatusBadge status={dept.status} />
                  </Td>
                  <Td>
                    <div className="flex flex-wrap gap-1">
                      <Button variant="ghost" className="text-xs py-1 px-2" onClick={() => openEdit(dept)}>
                        Edit
                      </Button>
                      <Button
                        variant="ghost"
                        className="text-xs py-1 px-2"
                        onClick={() => openAssignHead(dept)}
                      >
                        Assign Head
                      </Button>
                      <Button
                        variant="ghost"
                        className="text-xs py-1 px-2"
                        onClick={() => openAssignEmployees(dept)}
                      >
                        Assign Employees
                      </Button>
                      <Button
                        variant="secondary"
                        className="text-xs py-1 px-2"
                        onClick={() => handleToggleStatus(dept)}
                      >
                        {dept.status === 'active' ? 'Deactivate' : 'Activate'}
                      </Button>
                    </div>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Panel>

      {/* ── Create / Edit Modal ── */}
      {(modal === 'create' || modal === 'edit') && (
        <Modal
          open
          title={modal === 'create' ? 'Add New Department' : `Edit — ${selected?.name}`}
          onClose={closeModal}
          footer={
            <>
              <Button variant="secondary" onClick={closeModal} disabled={saving}>
                Cancel
              </Button>
              <Button onClick={handleSave} disabled={saving}>
                {saving ? 'Saving…' : modal === 'create' ? 'Create Department' : 'Save Changes'}
              </Button>
            </>
          }
        >
          <div className="space-y-4">
            {formError && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                {formError}
              </p>
            )}

            <Field
              label="Department Name"
              required
              hint="Up to 255 characters. Letters, spaces, numbers and punctuation are allowed."
            >
              <input
                type="text"
                className="text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-jpc-500 w-full"
                value={form.name}
                maxLength={255}
                aria-invalid={fieldErrors.name ? 'true' : 'false'}
                aria-describedby={fieldErrors.name ? 'department-name-error' : undefined}
                onChange={(e) => {
                  clearFieldError('name')
                  setForm((f) => ({ ...f, name: e.target.value }))
                }}
                placeholder="e.g. Bachelor of Science in Civil Engineering"
              />
              {fieldErrors.name?.[0] && (
                <p id="department-name-error" className="text-xs text-red-600 mt-1">
                  {fieldErrors.name[0]}
                </p>
              )}
            </Field>

            <Field label="Department Code" hint="Optional short unique identifier (e.g. BSCE, BSIT, BSED, BSBA)">
              <input
                type="text"
                className="text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-jpc-500 w-full uppercase"
                value={form.code}
                maxLength={20}
                aria-invalid={fieldErrors.code ? 'true' : 'false'}
                aria-describedby={fieldErrors.code ? 'department-code-error' : undefined}
                onChange={(e) => {
                  clearFieldError('code')
                  setForm((f) => ({ ...f, code: e.target.value.toUpperCase() }))
                }}
                placeholder="e.g. BSCE"
              />
              {fieldErrors.code?.[0] && (
                <p id="department-code-error" className="text-xs text-red-600 mt-1">
                  {fieldErrors.code[0]}
                </p>
              )}
            </Field>

            <Field label="Description">
              <textarea
                className="text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-jpc-500 w-full resize-none"
                rows={3}
                value={form.description}
                maxLength={2000}
                aria-invalid={fieldErrors.description ? 'true' : 'false'}
                aria-describedby={fieldErrors.description ? 'department-description-error' : undefined}
                onChange={(e) => {
                  clearFieldError('description')
                  setForm((f) => ({ ...f, description: e.target.value }))
                }}
                placeholder="Brief description of this department…"
              />
              {fieldErrors.description?.[0] && (
                <p id="department-description-error" className="text-xs text-red-600 mt-1">
                  {fieldErrors.description[0]}
                </p>
              )}
            </Field>

            <Field label="Status">
              <Select
                value={form.status}
                onChange={(v) => setForm((f) => ({ ...f, status: v }))}
                options={[
                  { value: 'active', label: 'Active' },
                  { value: 'inactive', label: 'Inactive' },
                ]}
              />
            </Field>
          </div>
        </Modal>
      )}

      {/* ── Assign Head Modal ── */}
      {modal === 'assign-head' && selected && (
        <Modal
          open
          title={`Assign Department Head — ${selected.name}`}
          onClose={closeModal}
          footer={
            <>
              <Button variant="secondary" onClick={closeModal} disabled={saving}>
                Cancel
              </Button>
              <Button
                onClick={handleAssignHead}
                disabled={saving || headLoading || headError !== null || !headEmployeeId}
              >
                {saving ? 'Assigning…' : 'Assign Head'}
              </Button>
            </>
          }
        >
          <div className="space-y-4">
            {formError && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                {formError}
              </p>
            )}

            <p className="text-sm text-slate-500">
              The head must belong to this department and hold the Department Head role. The role is
              never granted automatically — create the account with that role in User Management first.
            </p>

            {headLoading ? (
              <LoadingPanel label="Loading eligible employees" rows={2} />
            ) : headError ? (
              <ErrorPanel
                message={headError}
                onRetry={() => {
                  if (selected) void loadEligibleHeads(selected.id)
                }}
              />
            ) : headCandidates.length === 0 ? (
              <div className="space-y-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p className="text-sm font-semibold text-amber-900">No eligible employees found</p>
                <p className="text-xs text-amber-800">{headEmptyExplanation}</p>
                <p className="text-xs text-amber-700">
                  Account roles are managed in User Management → Accounts. Assign the Department Head
                  role there, activate the account, then return here.
                </p>
              </div>
            ) : (
              <>
                {currentHead && (
                  <p className="text-xs text-slate-500">
                    Current head: <span className="font-semibold text-slate-700">{currentHead.name}</span>
                  </p>
                )}

                <Field label="Search Employees">
                  <TextInput
                    value={headSearch}
                    onChange={setHeadSearch}
                    placeholder="Search by name, employee ID, or position…"
                  />
                </Field>

                <Field label="Select Employee" required hint="Shows full name, employee ID, and position.">
                  <Select
                    value={headEmployeeId}
                    onChange={setHeadEmployeeId}
                    placeholder="— Select employee —"
                    options={filteredHeadCandidates.map((e) => ({
                      value: String(e.id),
                      label: `${e.name} · #${e.employee_number} · ${e.position ?? 'No position'}${
                        e.id === headOptions?.current_head_employee_id ? ' (current head)' : ''
                      }`,
                    }))}
                  />
                </Field>

                {filteredHeadCandidates.length === 0 && (
                  <p className="text-xs text-slate-500">
                    No employees match “{headSearch.trim()}”. Clear the search to see all{' '}
                    {headCandidates.length} eligible employee(s).
                  </p>
                )}
              </>
            )}
          </div>
        </Modal>
      )}

      {/* ── Assign Employees Modal ── */}
      {modal === 'assign-employees' && selected && (
        <Modal
          open
          title={`Assign Employees — ${selected.name}`}
          onClose={closeModal}
          footer={
            <>
              <Button variant="secondary" onClick={closeModal} disabled={saving}>
                Cancel
              </Button>
              <Button onClick={handleTransfer} disabled={saving}>
                {saving ? 'Transferring…' : `Transfer ${transferIds.length}`}
              </Button>
            </>
          }
        >
          <div className="space-y-4">
            {formError && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                {formError}
              </p>
            )}

            <p className="text-sm text-slate-500">
              Transferred employees keep their tasks and reports. If a transferred employee heads
              another department, that head record is closed automatically.
            </p>

            {transferCandidates.length === 0 ? (
              <EmptyState
                title="No candidates"
                message="Every other active employee is already in this department."
              />
            ) : (
              <div className="max-h-72 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                {transferCandidates.map((e) => (
                  <label
                    key={e.id}
                    className="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      className="rounded border-slate-300 text-jpc-600 focus:ring-jpc-500"
                      checked={transferIds.includes(e.id)}
                      onChange={(event) =>
                        setTransferIds((ids) =>
                          event.target.checked
                            ? [...ids, e.id]
                            : ids.filter((id) => id !== e.id),
                        )
                      }
                    />
                    <span className="flex-1 text-sm text-slate-700">
                      {e.name}
                      <span className="text-[11px] text-slate-400 ml-2">
                        {e.department ?? 'No department'} · #{e.employee_number}
                      </span>
                    </span>
                  </label>
                ))}
              </div>
            )}

            <p className="text-xs text-slate-400">
              {transferIds.length} selected. Inactive departments cannot receive assignments.
            </p>
          </div>
        </Modal>
      )}

      {/* ── Department Detail Modal ── */}
      {modal === 'detail' && selected && (
        <Modal
          open
          title={selected.name}
          onClose={closeModal}
          footer={
            <>
              <Button variant="secondary" onClick={() => openEdit(selected)}>
                Edit Department
              </Button>
              <Button onClick={() => { closeModal(); openAssignHead(selected) }}>
                Assign Head
              </Button>
              <Button variant="secondary" onClick={closeModal}>
                Close
              </Button>
            </>
          }
        >
          <div className="space-y-5">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-xl font-black text-slate-900">{selected.name}</h2>
                {selected.code && <Badge tone="info">{selected.code}</Badge>}
              </div>
              <StatusBadge status={selected.status} />
            </div>

            {detailLoading ? (
              <LoadingPanel rows={2} />
            ) : detail ? (
              <>
                {detail.head?.name && detail.head.name !== 'Unassigned' ? (
                  <div className="bg-jpc-50 border border-jpc-200 rounded-xl px-4 py-3">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-jpc-500 mb-1">
                      Current Department Head
                    </p>
                    <p className="font-semibold text-jpc-900">{detail.head.name}</p>
                    <p className="text-xs text-jpc-600">{detail.head.position}</p>
                  </div>
                ) : (
                  <div className="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                    <p className="text-xs font-semibold text-amber-800">
                      No department head assigned yet.
                    </p>
                  </div>
                )}

                {detail.description && (
                  <p className="text-sm text-slate-600">{detail.description}</p>
                )}

                {/* Metrics */}
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                  <StatCard label="Total Employees" value={detail.metrics.total_employees} />
                  <StatCard label="Active Employees" value={detail.metrics.active_employees} tone="ok" />
                  <StatCard
                    label="Completion Rate"
                    value={pct(detail.metrics.completion_rate, 1)}
                    tone={
                      detail.metrics.completion_rate >= 80
                        ? 'ok'
                        : detail.metrics.completion_rate >= 50
                          ? 'warn'
                          : 'danger'
                    }
                  />
                </div>

                {/* Employee List */}
                {detail.employees.length > 0 ? (
                  <Panel title={`Department Employees (${detail.employees.length})`}>
                    <Table>
                      <thead>
                        <tr>
                          <Th>Employee</Th>
                          <Th>Position</Th>
                          <Th>Status</Th>
                        </tr>
                      </thead>
                      <tbody>
                        {detail.employees.map((emp) => (
                          <tr key={emp.id} className="border-b border-slate-100">
                            <Td>
                              <p className="font-medium">{emp.name}</p>
                              <p className="text-[11px] text-slate-400">{emp.employee_number}</p>
                            </Td>
                            <Td>{emp.position}</Td>
                            <Td>
                              <StatusBadge status={emp.status} label={humanise(emp.status)} />
                            </Td>
                          </tr>
                        ))}
                      </tbody>
                    </Table>
                  </Panel>
                ) : (
                  <EmptyState
                    title="No employees assigned"
                    message="Use Assign Employees to transfer employees into this department."
                  />
                )}
              </>
            ) : (
              <p className="text-sm text-slate-400">Could not load department details.</p>
            )}
          </div>
        </Modal>
      )}
    </AdminPage>
  )
}
