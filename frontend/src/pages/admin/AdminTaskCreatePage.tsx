import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  getAdminMonitoringOptions,
  type EmployeeOption,
} from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import { createTask } from '../../services/taskService'
import type { Task } from '../../types/task'
import { getApiErrorMessage, getApiFieldErrors, type FieldErrors } from '../../utils/apiError'
import { PATHS } from '../../routes/paths'
import {
  AdminPage,
  Button,
  Field,
  Panel,
  Select,
  TextInput,
} from '../../components/admin/AdminUi'
import { SearchableMultiSelect, type SearchableOption } from '../../components/common/SearchableSelect'

const PRIORITIES = [
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
  // The stored domain value stays `urgent`; the UI label matches the
  // institution's "Critical" wording.
  { value: 'urgent', label: 'Critical' },
]

const textareaClass =
  'w-full text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-jpc-500 focus:border-jpc-500'

function today(): string {
  return new Date().toISOString().split('T')[0]
}

/**
 * Admin task creation.
 *
 * Creates a task and assigns every selected employee in one API call, so the
 * task never exists in a half-assigned state. The employee picker is scoped to
 * the chosen department; the backend re-validates employee existence and the
 * date order.
 */
export default function AdminTaskCreatePage() {
  const navigate = useNavigate()
  const options = useAdminResource(() => getAdminMonitoringOptions())

  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [employeeIds, setEmployeeIds] = useState<string[]>([])
  const [priority, setPriority] = useState('medium')
  const [startDate, setStartDate] = useState(today())
  const [dueDate, setDueDate] = useState('')
  const [targetValue, setTargetValue] = useState('')
  const [targetUnit, setTargetUnit] = useState('')
  const [requiredOutput, setRequiredOutput] = useState('')

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [created, setCreated] = useState<Task | null>(null)

  const departmentEmployees = useMemo<EmployeeOption[]>(() => {
    if (!departmentId) return []

    return (options.data?.employees ?? []).filter(
      (employee) =>
        String(employee.department_id ?? '') === departmentId &&
        (!employee.employment_status || employee.employment_status === 'active'),
    )
  }, [departmentId, options.data?.employees])

  const employeeOptions = useMemo<SearchableOption[]>(
    () =>
      departmentEmployees.map((employee) => ({
        value: employee.id,
        label: employee.name,
        hint: employee.employee_number,
      })),
    [departmentEmployees],
  )

  const changeDepartment = (value: string) => {
    setDepartmentId(value)
    // Keep the assignment list consistent with the chosen department.
    setEmployeeIds([])
  }

  const validate = (): string | null => {
    if (!title.trim()) return 'Task title is required.'
    if (!departmentId) return 'Select a department.'
    if (employeeIds.length === 0) return 'Select at least one employee to assign this task to.'
    if (!dueDate) return 'Due date is required.'
    if (startDate && dueDate < startDate) return 'Due date must be on or after the start date.'
    return null
  }

  const submit = async () => {
    setError(null)
    setFieldErrors({})
    setCreated(null)

    const validationError = validate()
    if (validationError) {
      setError(validationError)
      return
    }

    setSubmitting(true)

    try {
      const response = await createTask({
        title: title.trim(),
        description: description.trim() || null,
        priority,
        department_id: Number(departmentId),
        start_date: startDate || null,
        due_date: dueDate,
        required_output: requiredOutput.trim() || null,
        target_value: targetValue === '' ? null : Number(targetValue),
        target_unit: targetUnit.trim() || null,
        assigned_employee_ids: employeeIds.map(Number),
      })

      setCreated(response.data)
      setTitle('')
      setDescription('')
      setEmployeeIds([])
      setTargetValue('')
      setTargetUnit('')
      setRequiredOutput('')
    } catch (cause) {
      setFieldErrors(getApiFieldErrors(cause))
      setError(getApiErrorMessage(cause, 'Unable to create the task.'))
    } finally {
      setSubmitting(false)
    }
  }

  const fieldError = (name: string): string | null => {
    const messages = fieldErrors[name]
    return messages && messages.length > 0 ? messages[0] : null
  }

  return (
    <AdminPage
      title="Create Task"
      subtitle="Create a task and assign it to one or more employees"
      actions={
        <Button variant="secondary" onClick={() => navigate(PATHS.adminTaskMonitoring)}>
          Back to monitoring
        </Button>
      }
    >
      {created ? (
        <Panel title="Task created" className="mb-6">
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <p className="font-semibold">
              {created.title} ({created.task_code}) was created and assigned.
            </p>
            <p className="mt-0.5 text-emerald-700">
              {created.assignments?.length ?? 0} employee
              {(created.assignments?.length ?? 0) === 1 ? '' : 's'} received an assignment notification.
            </p>
          </div>
          <div className="mt-4 flex flex-wrap gap-2">
            <Button onClick={() => navigate(PATHS.adminTaskMonitoring)}>View task monitoring</Button>
            <Button
              variant="secondary"
              onClick={() => {
                setCreated(null)
                setTitle('')
                setDescription('')
                setEmployeeIds([])
                setTargetValue('')
                setTargetUnit('')
                setRequiredOutput('')
              }}
            >
              Create another
            </Button>
          </div>
        </Panel>
      ) : null}

      <Panel title="Task details" description="Required fields are marked with an asterisk.">
        {error ? (
          <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        ) : null}

        {options.error ? (
          <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {options.error}
          </div>
        ) : null}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label="Task title" required className="md:col-span-2">
            <TextInput
              value={title}
              onChange={setTitle}
              placeholder="e.g. Submit monthly faculty accomplishment report"
            />
            {fieldError('title') ? <p className="text-xs text-red-600">{fieldError('title')}</p> : null}
          </Field>

          <Field label="Description" className="md:col-span-2">
            <textarea
              className={textareaClass}
              rows={3}
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              placeholder="Scope, context and expectations for this task"
            />
          </Field>

          <Field label="Department" required>
            <Select
              value={departmentId}
              onChange={changeDepartment}
              options={(options.data?.departments ?? []).map((department) => ({
                value: department.id,
                label: department.name,
              }))}
              placeholder={options.loading ? 'Loading departments…' : 'Select a department'}
            />
            {fieldError('department_id') ? (
              <p className="text-xs text-red-600">{fieldError('department_id')}</p>
            ) : null}
          </Field>

          <Field label="Priority" required>
            <Select value={priority} onChange={setPriority} options={PRIORITIES} />
            {fieldError('priority') ? <p className="text-xs text-red-600">{fieldError('priority')}</p> : null}
          </Field>

          <Field
            label="Assign employees"
            required
            className="md:col-span-2"
            hint={
              departmentId
                ? `${departmentEmployees.length} active employee${departmentEmployees.length === 1 ? '' : 's'} in this department`
                : 'Choose a department first to load its employees.'
            }
          >
            <SearchableMultiSelect
              values={employeeIds}
              onChange={setEmployeeIds}
              options={employeeOptions}
              disabled={!departmentId}
              placeholder={departmentId ? 'Type a name to assign…' : 'Select a department first'}
              emptyMessage="No active employees found in this department"
            />
            {fieldError('assigned_employee_ids') ? (
              <p className="text-xs text-red-600">{fieldError('assigned_employee_ids')}</p>
            ) : null}
          </Field>

          <Field label="Start date">
            <TextInput type="date" value={startDate} onChange={setStartDate} />
            {fieldError('start_date') ? <p className="text-xs text-red-600">{fieldError('start_date')}</p> : null}
          </Field>

          <Field label="Due date" required>
            <TextInput type="date" value={dueDate} onChange={setDueDate} />
            {fieldError('due_date') ? <p className="text-xs text-red-600">{fieldError('due_date')}</p> : null}
          </Field>

          <Field label="Measurable target value" hint="Optional. Numeric target the assignee must reach.">
            <TextInput
              type="number"
              value={targetValue}
              onChange={setTargetValue}
              placeholder="e.g. 100"
            />
            {fieldError('target_value') ? (
              <p className="text-xs text-red-600">{fieldError('target_value')}</p>
            ) : null}
          </Field>

          <Field label="Target unit" hint="Optional. Unit for the target value (e.g. reports, %).">
            <TextInput value={targetUnit} onChange={setTargetUnit} placeholder="e.g. reports" />
            {fieldError('target_unit') ? <p className="text-xs text-red-600">{fieldError('target_unit')}</p> : null}
          </Field>

          <Field
            label="Required output"
            className="md:col-span-2"
            hint="Describe the deliverable the employee must submit. Attachment uploads are not part of this form."
          >
            <textarea
              className={textareaClass}
              rows={3}
              value={requiredOutput}
              onChange={(event) => setRequiredOutput(event.target.value)}
              placeholder="e.g. Signed PDF report with supporting photos"
            />
            {fieldError('required_output') ? (
              <p className="text-xs text-red-600">{fieldError('required_output')}</p>
            ) : null}
          </Field>
        </div>

        <div className="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
          <Button variant="secondary" onClick={() => navigate(PATHS.adminTaskMonitoring)} disabled={submitting}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={submitting || options.loading}>
            {submitting ? 'Creating…' : 'Create & assign task'}
          </Button>
        </div>
      </Panel>
    </AdminPage>
  )
}
