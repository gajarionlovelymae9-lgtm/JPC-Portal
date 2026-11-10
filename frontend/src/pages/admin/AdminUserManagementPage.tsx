import { useState } from 'react'
import { Eye, EyeOff } from 'lucide-react'
import {
  createAdminUser,
  deactivateAdminUser,
  getAdminUser,
  getAdminUsers,
  reactivateAdminUser,
  updateAdminUser,
  type EmployeeAccountOption,
  type ManagedUser,
  type UserPayload,
} from '../../services/adminDashboardService'
import { ApiError } from '../../services/api'
import { useAdminResource } from '../../hooks/useAdminResource'
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
} from '../../components/admin/AdminUi'
import { RankingBars } from '../../components/admin/AdminCharts'
import { SearchableSelect } from '../../components/common/SearchableSelect'

type FormState = {
  name: string
  email: string
  username: string
  password: string
  role: string
  status: string
  employee_id: string
}

const BLANK_FORM: FormState = {
  name: '',
  email: '',
  username: '',
  password: '',
  role: 'employee',
  status: 'active',
  employee_id: '',
}

const PASSWORD_MIN_LENGTH = 8
const PASSWORD_MIN_MESSAGE = 'Password must contain at least 8 characters.'

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

function FieldError({ errors, field }: { errors: Record<string, string[]>; field: string }) {
  const messages = errors[field]
  if (!messages || messages.length === 0) return null
  return (
    <span role="alert" className="text-[11px] text-red-600">
      {messages.join(' ')}
    </span>
  )
}

export default function AdminUserManagementPage({ embedded = false }: { embedded?: boolean } = {}) {
  const [role, setRole] = useState('')
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const [editing, setEditing] = useState<ManagedUser | null>(null)
  const [creating, setCreating] = useState(false)
  const [form, setForm] = useState<FormState>(BLANK_FORM)
  const [formError, setFormError] = useState('')
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [saving, setSaving] = useState(false)
  const [actionError, setActionError] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [passwordWarning, setPasswordWarning] = useState('')

  const resource = useAdminResource(
    () => getAdminUsers({ role, status, search, page, per_page: 15 }),
    [role, status, search, page],
  )

  const data = resource.data
  const summary = data?.summary
  const options = data?.options
  const formOpen = creating || editing !== null

  // Every employee, including those already linked. The fallback keeps the old
  // `unlinked_employees` shape working if an older backend answers.
  const employeeOptions: EmployeeAccountOption[] = options
    ? (options.employees ??
        options.unlinked_employees.map((employee) => ({
          id: employee.id,
          name: employee.name,
          employee_number: employee.employee_number,
          department: employee.department || null,
          account: null,
        })))
    : []

  const selectedEmployee = employeeOptions.find(
    (employee) => String(employee.id) === form.employee_id,
  )
  // An employee can hold exactly one login. Selecting one that already belongs
  // to a different account blocks the save and points at that account.
  const conflictAccount =
    selectedEmployee?.account && selectedEmployee.account.id !== editing?.id
      ? selectedEmployee.account
      : null

  // A blank password keeps the current one when editing, but creating an
  // account still requires a password. Any entered value must be 8+ characters.
  const passwordTooShort = form.password.length > 0 && form.password.length < PASSWORD_MIN_LENGTH
  const passwordMissing = !editing && form.password.length === 0
  const passwordInvalid = passwordTooShort || passwordMissing

  const apply = (setter: (value: string) => void) => (value: string) => {
    setter(value)
    setPage(1)
  }

  const openCreate = () => {
    setEditing(null)
    setCreating(true)
    setForm(BLANK_FORM)
    setFormError('')
    setErrors({})
    setShowPassword(false)
    setPasswordWarning('')
  }

  const openEdit = (user: ManagedUser) => {
    setCreating(false)
    setEditing(user)
    setForm({
      name: user.name,
      email: user.email,
      username: user.username ?? '',
      password: '',
      role: user.role ?? 'employee',
      status: user.status ?? 'active',
      employee_id: user.employee_id ? String(user.employee_id) : '',
    })
    setFormError('')
    setErrors({})
    setShowPassword(false)
    setPasswordWarning('')
  }

  const close = () => {
    setCreating(false)
    setEditing(null)
    setFormError('')
    setErrors({})
    setShowPassword(false)
    setPasswordWarning('')
  }

  /** Jump to the account that already owns the selected employee. */
  const openExistingAccount = async (accountId: number) => {
    setFormError('')
    try {
      const response = await getAdminUser(accountId)
      openEdit(response.data)
    } catch (error) {
      setFormError(error instanceof Error ? error.message : 'Unable to open the existing account.')
    }
  }

  const save = async () => {
    if (passwordInvalid) {
      setPasswordWarning(PASSWORD_MIN_MESSAGE)
      return
    }

    if (conflictAccount) {
      setFormError(
        'That employee already has a login account. Open the existing account to change it.',
      )
      return
    }

    setSaving(true)
    setFormError('')
    setErrors({})
    setPasswordWarning('')

    const payload: UserPayload = {
      name: form.name.trim(),
      email: form.email.trim(),
      username: form.username.trim(),
      role: form.role,
      status: form.status,
      employee_id: form.employee_id ? Number(form.employee_id) : null,
    }
    if (form.password) payload.password = form.password

    try {
      if (editing) {
        await updateAdminUser(editing.id, payload)
      } else {
        await createAdminUser(payload)
      }
      close()
      resource.reload()
    } catch (error) {
      const fieldMessages = fieldErrors(error)
      if (Object.keys(fieldMessages).length > 0) {
        setErrors(fieldMessages)
      } else {
        setFormError(error instanceof Error ? error.message : 'Unable to save the user.')
      }
    } finally {
      setSaving(false)
    }
  }

  const setStatusOf = async (user: ManagedUser, next: 'deactivate' | 'reactivate') => {
    setActionError('')
    try {
      if (next === 'deactivate') {
        await deactivateAdminUser(user.id)
      } else {
        await reactivateAdminUser(user.id)
      }
      resource.reload()
    } catch (error) {
      setActionError(error instanceof Error ? error.message : `Unable to ${next} the user.`)
    }
  }

  return (
    <AdminPage
      title={embedded ? undefined : 'User Management'}
      subtitle={embedded ? undefined : 'Accounts, roles and employee linkage'}
      actions={<Button onClick={openCreate}>Add user</Button>}
    >
      <FilterBar>
        <Field label="Role">
          <Select
            value={role}
            onChange={apply(setRole)}
            options={(options?.roles ?? []).map((r) => ({ value: r.value, label: r.label }))}
            placeholder="All roles"
          />
        </Field>
        <Field label="Status">
          <Select
            value={status}
            onChange={apply(setStatus)}
            options={options?.statuses ?? []}
            placeholder="All statuses"
          />
        </Field>
        <Field label="Search" className="flex-1 min-w-[200px]">
          <TextInput value={search} onChange={apply(setSearch)} placeholder="Name, email or username" />
        </Field>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading users" />
      ) : resource.error || !data || !summary ? (
        <ErrorPanel message={resource.error ?? 'Unable to load users.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-6 gap-4">
            <StatCard label="Total users" value={num(summary.total_users)} />
            <StatCard label="Active" value={num(summary.active_users)} tone="ok" />
            <StatCard label="Inactive" value={num(summary.inactive_users)} tone="warn" />
            <StatCard label="Suspended" value={num(summary.suspended_users)} tone="danger" />
            <StatCard label="Unlinked employees" value={num(summary.unlinked_employees)} tone="info" />
            <StatCard label="Roles in use" value={num(summary.by_role.length)} />
          </div>

          <Panel title="Users by role" description="Distribution of accounts across roles">
            <RankingBars
              rows={summary.by_role.map((row) => ({ key: row.role, label: row.label, value: row.total }))}
              emptyMessage="No roles in use."
            />
          </Panel>

          {actionError ? <ErrorPanel message={actionError} /> : null}

          <Panel title="Accounts" description={`${data.meta.total} users`}>
            {data.items.length === 0 ? (
              <EmptyState title="No users match these filters" message="Try clearing a filter to widen the search." />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>User</Th>
                      <Th>Role</Th>
                      <Th>Status</Th>
                      <Th>Linked employee</Th>
                      <Th>Last login</Th>
                      <Th className="text-right">Actions</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((user) => (
                      <tr key={user.id} className="border-b border-slate-100 last:border-0">
                        <Td>
                          <p className="font-semibold text-slate-800">{user.name}</p>
                          <p className="text-[11px] text-slate-400">{user.email}</p>
                          {user.username ? (
                            <p className="text-[11px] text-slate-400">@{user.username}</p>
                          ) : null}
                          {user.can_authenticate ? null : (
                            <p className="text-[10px] text-amber-600">Cannot sign in while {humanise(user.status ?? '')}</p>
                          )}
                        </Td>
                        <Td>
                          <Badge tone="info">{user.role_label ?? 'No role'}</Badge>
                        </Td>
                        <Td>
                          <StatusBadge status={user.status ?? ''} label={user.status_label ?? undefined} />
                        </Td>
                        <Td>
                          {user.employee_name ? (
                            <>
                              <p className="text-slate-700 text-xs">{user.employee_name}</p>
                              <p className="text-[11px] text-slate-400">
                                {user.employee_number}
                                {user.department ? ` · ${user.department}` : ''}
                              </p>
                            </>
                          ) : (
                            <span className="text-[11px] text-slate-400">Not linked</span>
                          )}
                        </Td>
                        <Td className="text-slate-500 text-xs">
                          {user.last_login_at ? dateTime(user.last_login_at) : 'Never'}
                        </Td>
                        <Td>
                          <div className="flex justify-end gap-1">
                            <Button variant="secondary" onClick={() => openEdit(user)}>
                              Edit
                            </Button>
                            {user.is_active ? (
                              <Button variant="ghost" onClick={() => setStatusOf(user, 'deactivate')}>
                                Deactivate
                              </Button>
                            ) : (
                              <Button variant="ghost" onClick={() => setStatusOf(user, 'reactivate')}>
                                Reactivate
                              </Button>
                            )}
                          </div>
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                <Pagination meta={data.meta} onPageChange={setPage} />
              </>
            )}
          </Panel>
        </div>
      )}

      {formOpen ? (
        <Modal
          open={formOpen}
          title={editing ? `Edit ${editing.name}` : 'Add user'}
          onClose={close}
          footer={
            <>
              <Button variant="secondary" onClick={close} disabled={saving}>
                Cancel
              </Button>
              <Button
                onClick={() => void save()}
                disabled={saving || conflictAccount !== null}
                ariaDisabled={passwordInvalid}
                title={passwordInvalid ? PASSWORD_MIN_MESSAGE : undefined}
              >
                {saving ? 'Saving…' : editing ? 'Save changes' : 'Create user'}
              </Button>
            </>
          }
        >
          {passwordWarning && passwordInvalid ? (
            <div
              role="status"
              aria-live="polite"
              className="fixed bottom-4 right-4 z-[70] max-w-xs rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700 shadow-lg"
            >
              {passwordWarning}
            </div>
          ) : null}
          <form
            onSubmit={(event) => {
              event.preventDefault()
              void save()
            }}
            className="space-y-4"
          >
            {formError ? <ErrorPanel message={formError} /> : null}
            <Field label="Full name" required>
              <TextInput
                value={form.name}
                onChange={(value) => setForm({ ...form, name: value })}
                placeholder="e.g. Maria Santos"
              />
              <FieldError errors={errors} field="name" />
            </Field>
            <Field label="Email" required>
              <TextInput
                type="email"
                value={form.email}
                onChange={(value) => setForm({ ...form, email: value })}
                placeholder="name@jpc.edu"
              />
              <FieldError errors={errors} field="email" />
            </Field>
            <Field
              label="Username"
              required
              hint="Used to sign in; must be unique across all accounts."
            >
              <TextInput
                value={form.username}
                onChange={(value) => setForm({ ...form, username: value })}
                placeholder="name.surname"
              />
              <FieldError errors={errors} field="username" />
            </Field>
            <Field
              label={editing ? 'New password' : 'Password'}
              required={!editing}
              hint={editing ? 'Leave blank to keep the current password. Minimum 8 characters.' : 'Minimum 8 characters.'}
            >
              <div className="relative">
                <TextInput
                  type={showPassword ? 'text' : 'password'}
                  value={form.password}
                  onChange={(value) => {
                    setForm({ ...form, password: value })
                    setPasswordWarning('')
                    setErrors((previous) => ({ ...previous, password: [] }))
                  }}
                  placeholder="••••••••"
                  className="w-full pr-10"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((visible) => !visible)}
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                  aria-pressed={showPassword}
                  className="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition hover:text-slate-600"
                >
                  {showPassword ? (
                    <EyeOff className="h-4 w-4" aria-hidden="true" />
                  ) : (
                    <Eye className="h-4 w-4" aria-hidden="true" />
                  )}
                </button>
              </div>
              {passwordTooShort ? (
                <span role="alert" className="text-[11px] text-red-600">
                  {PASSWORD_MIN_MESSAGE}
                </span>
              ) : null}
              <FieldError errors={errors} field="password" />
            </Field>
            <Field label="Role" required>
              <Select
                value={form.role}
                onChange={(value) => setForm({ ...form, role: value })}
                options={(options?.roles ?? []).map((r) => ({ value: r.value, label: r.label }))}
              />
              <FieldError errors={errors} field="role" />
            </Field>
            <Field label="Status">
              <Select
                value={form.status}
                onChange={(value) => setForm({ ...form, status: value })}
                options={options?.statuses ?? []}
              />
              <FieldError errors={errors} field="status" />
            </Field>
            <Field
              label="Link employee record"
              hint="One login per employee. Employees who already have an account are marked, and linking one is blocked."
            >
              <SearchableSelect
                value={form.employee_id}
                onChange={(value) => {
                  setForm({ ...form, employee_id: value })
                  setErrors((previous) => ({ ...previous, employee_id: [] }))
                }}
                options={employeeOptions.map((employee) => ({
                  value: String(employee.id),
                  label: employee.name,
                  hint: employee.account
                    ? `${employee.employee_number} · account: ${employee.account.email}`
                    : employee.employee_number,
                }))}
                placeholder="Search by name or employee ID…"
                emptyMessage="No employee matches that search."
              />
              {conflictAccount ? (
                <div role="alert" className="mt-2 rounded-lg border border-amber-300 bg-amber-50 p-3">
                  <p className="text-xs font-semibold text-amber-900">
                    This employee already has a login account.
                  </p>
                  <p className="text-[11px] text-amber-800 mt-0.5">
                    {conflictAccount.name} · {conflictAccount.email}
                    {conflictAccount.username ? ` · @${conflictAccount.username}` : ''}
                  </p>
                  <Button
                    variant="secondary"
                    className="mt-2"
                    onClick={() => void openExistingAccount(conflictAccount.id)}
                  >
                    Open existing account
                  </Button>
                </div>
              ) : selectedEmployee?.account && editing?.id === selectedEmployee.account.id ? (
                <p className="text-[11px] text-slate-400 mt-1">
                  Currently linked to this account.
                </p>
              ) : null}
              <FieldError errors={errors} field="employee_id" />
            </Field>
          </form>
        </Modal>
      ) : null}
    </AdminPage>
  )
}
