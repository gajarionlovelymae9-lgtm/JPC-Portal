import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { ApiError } from '../../services/api'
import {
  createDepartmentEscalation,
  getDepartmentEscalation,
  getDepartmentEscalationOptions,
  getDepartmentEscalations,
  getDepartmentReports,
  getDepartmentTasks,
} from '../../services/departmentHeadService'
import type {
  DepartmentEscalation,
  DepartmentEscalationDetail,
  DepartmentEscalationOptions,
  DepartmentReportItem,
  DepartmentTask,
  EscalationSummary,
} from '../../services/departmentHeadService'

const EMPTY_SUMMARY: EscalationSummary = {
  total: 0,
  pending: 0,
  under_review: 0,
  action_required: 0,
  resolved: 0,
  rejected: 0,
  open: 0,
}

const STATUS_STYLES: Record<string, string> = {
  pending: 'bg-amber-100 text-amber-800',
  under_review: 'bg-blue-100 text-blue-700',
  action_required: 'bg-orange-100 text-orange-800',
  resolved: 'bg-emerald-100 text-emerald-800',
  rejected: 'bg-gray-200 text-gray-700',
}

const PRIORITY_STYLES: Record<string, string> = {
  low: 'bg-gray-100 text-gray-700',
  medium: 'bg-sky-100 text-sky-700',
  high: 'bg-orange-100 text-orange-700',
  critical: 'bg-red-100 text-red-700',
}

/** Pull Laravel's `{ field: [messages] }` validation bag out of an API error. */
function validationMessages(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError) || !error.details || typeof error.details !== 'object') {
    return {}
  }

  const out: Record<string, string> = {}
  Object.entries(error.details as Record<string, unknown>).forEach(([field, value]) => {
    if (Array.isArray(value) && value.length > 0) {
      out[field] = String(value[0])
    } else if (typeof value === 'string') {
      out[field] = value
    }
  })
  return out
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'
  return new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
}

export default function DepartmentEscalationsPage() {
  const [items, setItems] = useState<DepartmentEscalation[]>([])
  const [summary, setSummary] = useState<EscalationSummary>(EMPTY_SUMMARY)
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, per_page: 10, total: 0 })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [priorityFilter, setPriorityFilter] = useState('all')
  const [sort, setSort] = useState('created_at')
  const [direction, setDirection] = useState<'asc' | 'desc'>('desc')
  const [page, setPage] = useState(1)

  const [raiseOpen, setRaiseOpen] = useState(false)
  const [detailId, setDetailId] = useState<number | null>(null)

  const requestId = useRef(0)

  // Debounce the free-text search so typing does not fire a request per key.
  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(searchInput)
      setPage(1)
    }, 350)
    return () => window.clearTimeout(timer)
  }, [searchInput])

  const loadEscalations = useCallback(() => {
    const current = ++requestId.current
    setLoading(true)
    setError(null)

    getDepartmentEscalations({
      search,
      status: statusFilter,
      priority: priorityFilter,
      sort,
      direction,
      page,
      per_page: pagination.per_page,
    })
      .then(res => {
        if (current !== requestId.current) return
        setItems(res.data.items)
        setSummary(res.data.summary)
        setPagination(res.data.pagination)
      })
      .catch(() => {
        if (current !== requestId.current) return
        setError('Failed to load escalations. Please try again.')
      })
      .finally(() => {
        if (current === requestId.current) setLoading(false)
      })
  }, [search, statusFilter, priorityFilter, sort, direction, page, pagination.per_page])

  useEffect(() => {
    loadEscalations()
  }, [loadEscalations])

  const summaryCards = [
    { label: 'Total Escalations', value: summary.total, accent: 'text-gray-900' },
    { label: 'Pending', value: summary.pending, accent: 'text-amber-600' },
    { label: 'Under Review', value: summary.under_review, accent: 'text-blue-600' },
    { label: 'Resolved', value: summary.resolved, accent: 'text-emerald-600' },
  ]

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Escalations</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Raise serious task, report or target issues to Administration for formal review.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setRaiseOpen(true)}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl text-sm transition-all shadow-sm"
        >
          + Raise Escalation
        </button>
      </div>

      {/* Summary cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {summaryCards.map(card => (
          <div key={card.label} className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">{card.label}</p>
            <p className={`text-3xl font-bold mt-2 ${card.accent}`}>{card.value}</p>
          </div>
        ))}
      </div>

      {/* Filter bar */}
      <div className="flex flex-col lg:flex-row gap-3 bg-white p-4 rounded-2xl border border-gray-200 shadow-sm">
        <input
          type="text"
          value={searchInput}
          onChange={e => setSearchInput(e.target.value)}
          placeholder="Search by subject, employee or reason..."
          className="flex-1 border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <select
          value={statusFilter}
          onChange={e => {
            setStatusFilter(e.target.value)
            setPage(1)
          }}
          className="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
        >
          <option value="all">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="under_review">Under Review</option>
          <option value="action_required">Action Required</option>
          <option value="resolved">Resolved</option>
          <option value="rejected">Rejected</option>
        </select>
        <select
          value={priorityFilter}
          onChange={e => {
            setPriorityFilter(e.target.value)
            setPage(1)
          }}
          className="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
        >
          <option value="all">All Priorities</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
        <select
          value={sort}
          onChange={e => {
            setSort(e.target.value)
            setPage(1)
          }}
          className="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
        >
          <option value="created_at">Sort: Date Raised</option>
          <option value="priority">Sort: Priority</option>
          <option value="status">Sort: Status</option>
        </select>
        <button
          type="button"
          onClick={() => setDirection(d => (d === 'asc' ? 'desc' : 'asc'))}
          className="px-4 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          {direction === 'asc' ? 'Ascending ↑' : 'Descending ↓'}
        </button>
      </div>

      {error && (
        <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200 flex justify-between items-center">
          <span className="text-sm">{error}</span>
          <button type="button" onClick={loadEscalations} className="text-xs font-bold underline">
            Retry
          </button>
        </div>
      )}

      {/* Table */}
      <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-4 space-y-3">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="h-12 bg-gray-100 rounded-xl animate-pulse" />
            ))}
          </div>
        ) : items.length === 0 ? (
          <div className="p-12 text-center text-gray-500">
            <span className="text-4xl block mb-2">🛡️</span>
            <p className="font-semibold">No escalations found.</p>
            <p className="text-xs text-gray-400 mt-1">
              Raise an escalation when a serious issue needs Administration intervention.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50 text-gray-500">
                <tr>
                  {['Escalation ID', 'Employee', 'Department', 'Issue Type', 'Related Task / Report', 'Priority', 'Date Raised', 'Assigned Administrator', 'Status', 'Actions'].map(
                    heading => (
                      <th
                        key={heading}
                        className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider whitespace-nowrap"
                      >
                        {heading}
                      </th>
                    ),
                  )}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {items.map(esc => (
                  <tr key={esc.id} className="hover:bg-gray-50/70">
                    <td className="px-4 py-3 font-mono text-xs font-bold text-gray-700 whitespace-nowrap">
                      {esc.reference}
                    </td>
                    <td className="px-4 py-3 text-gray-800 whitespace-nowrap">
                      {esc.employee_name ?? '—'}
                      {esc.employee_number && (
                        <span className="block text-xs text-gray-400">{esc.employee_number}</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-gray-600 whitespace-nowrap">{esc.department_name ?? '—'}</td>
                    <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{esc.type_label}</td>
                    <td className="px-4 py-3 text-gray-600 max-w-[16rem]">
                      <span className="line-clamp-1">{esc.related_label ?? '—'}</span>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <span
                        className={`text-xs px-2 py-0.5 rounded-md font-semibold ${
                          PRIORITY_STYLES[esc.priority] ?? 'bg-gray-100 text-gray-700'
                        }`}
                      >
                        {esc.priority_label}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                      {formatDateTime(esc.created_at)}
                    </td>
                    <td className="px-4 py-3 text-gray-600 whitespace-nowrap">
                      {esc.assigned_to_name ?? 'Unassigned'}
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <span
                        className={`text-xs px-2.5 py-0.5 rounded-full font-semibold ${
                          STATUS_STYLES[esc.status] ?? 'bg-gray-100 text-gray-700'
                        }`}
                      >
                        {esc.status_label}
                      </span>
                    </td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <button
                        type="button"
                        onClick={() => setDetailId(esc.id)}
                        className="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-bold"
                      >
                        View
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {!loading && items.length > 0 && (
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-gray-100">
            <p className="text-xs text-gray-500">
              Page {pagination.current_page} of {pagination.last_page} · {pagination.total} escalation
              {pagination.total === 1 ? '' : 's'}
            </p>
            <div className="flex gap-2">
              <button
                type="button"
                disabled={pagination.current_page <= 1}
                onClick={() => setPage(p => Math.max(1, p - 1))}
                className="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 disabled:opacity-40"
              >
                Previous
              </button>
              <button
                type="button"
                disabled={pagination.current_page >= pagination.last_page}
                onClick={() => setPage(p => p + 1)}
                className="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {raiseOpen && (
        <RaiseEscalationModal
          onClose={() => setRaiseOpen(false)}
          onCreated={() => {
            setRaiseOpen(false)
            setPage(1)
            loadEscalations()
          }}
        />
      )}

      {detailId !== null && <EscalationDetailPanel escalationId={detailId} onClose={() => setDetailId(null)} />}
    </div>
  )
}

// ─── Raise escalation modal ─────────────────────────────────────────────────

interface RaiseEscalationModalProps {
  onClose: () => void
  onCreated: () => void
}

function RaiseEscalationModal({ onClose, onCreated }: RaiseEscalationModalProps) {
  const [options, setOptions] = useState<DepartmentEscalationOptions | null>(null)
  const [tasks, setTasks] = useState<DepartmentTask[]>([])
  const [reports, setReports] = useState<DepartmentReportItem[]>([])
  const [optionsError, setOptionsError] = useState<string | null>(null)

  const [type, setType] = useState('')
  const [employeeId, setEmployeeId] = useState('')
  const [related, setRelated] = useState('')
  const [priority, setPriority] = useState('medium')
  const [title, setTitle] = useState('')
  const [reason, setReason] = useState('')
  const [requestedAction, setRequestedAction] = useState('')
  const [assignedTo, setAssignedTo] = useState('')
  const [attachment, setAttachment] = useState<File | null>(null)

  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    let active = true
    Promise.all([
      getDepartmentEscalationOptions(),
      getDepartmentTasks({ per_page: 100 }),
      getDepartmentReports({ per_page: 100 }),
    ])
      .then(([optionsRes, tasksRes, reportsRes]) => {
        if (!active) return
        setOptions(optionsRes.data)
        setTasks(tasksRes.data)
        setReports(reportsRes.data)
        setType(optionsRes.data.issue_types[0]?.value ?? 'other')
        setPriority(optionsRes.data.priorities[0]?.value ?? 'medium')
      })
      .catch(() => {
        if (active) setOptionsError('Unable to load escalation form data. Please close and retry.')
      })

    return () => {
      active = false
    }
  }, [])

  // Reports must belong to the selected employee (enforced by the backend too).
  const reportChoices = useMemo(
    () => reports.filter(r => !employeeId || r.employee_id === Number(employeeId)),
    [reports, employeeId],
  )

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (submitting) return

    const errors: Record<string, string> = {}
    if (!title.trim()) errors.title = 'Please enter a subject.'
    if (!type) errors.type = 'Please choose an issue type.'
    if (!employeeId) errors.employee_id = 'Please select an employee.'
    if (!priority) errors.priority = 'Please choose a priority.'
    if (!reason.trim()) errors.reason = 'Please describe the issue.'

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      setFormError('Please correct the highlighted fields.')
      return
    }

    setSubmitting(true)
    setFormError(null)
    setFieldErrors({})

    const [relatedType, relatedId] = related ? related.split(':') : ['', '']

    try {
      await createDepartmentEscalation({
        title: title.trim(),
        type,
        reason: reason.trim(),
        employee_id: Number(employeeId),
        priority,
        related_type: relatedType || undefined,
        related_id: relatedId ? Number(relatedId) : undefined,
        requested_action: requestedAction.trim() || undefined,
        assigned_to: assignedTo ? Number(assignedTo) : undefined,
        attachment,
      })
      onCreated()
    } catch (err) {
      const messages = validationMessages(err)
      setFieldErrors(messages)
      setFormError(
        Object.keys(messages).length > 0
          ? 'Please correct the highlighted fields.'
          : err instanceof ApiError
            ? err.message
            : 'Failed to raise the escalation. Please try again.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  const field = (name: string) =>
    fieldErrors[name] ? <p className="text-xs text-red-600 mt-1">{fieldErrors[name]}</p> : null

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-start sm:items-center justify-center p-4 z-50 overflow-y-auto">
      <div className="bg-white rounded-2xl p-6 max-w-2xl w-full shadow-xl space-y-4 my-8">
        <div className="flex justify-between items-start">
          <div>
            <h2 className="text-lg font-bold text-gray-900">Raise Escalation</h2>
            <p className="text-xs text-gray-500 mt-0.5">
              Forward a serious issue to Administration for formal review and resolution.
            </p>
          </div>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600 text-xl leading-none">
            ×
          </button>
        </div>

        {optionsError && (
          <div className="bg-red-50 text-red-700 p-3 rounded-xl border border-red-200 text-sm">{optionsError}</div>
        )}
        {formError && (
          <div className="bg-red-50 text-red-700 p-3 rounded-xl border border-red-200 text-sm">{formError}</div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                Issue Type *
              </label>
              <select
                value={type}
                onChange={e => setType(e.target.value)}
                disabled={!options}
                className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                {options?.issue_types.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              {field('type')}
            </div>
            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Priority *</label>
              <select
                value={priority}
                onChange={e => setPriority(e.target.value)}
                disabled={!options}
                className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                {options?.priorities.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              {field('priority')}
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                Employee *
              </label>
              <select
                value={employeeId}
                onChange={e => {
                  setEmployeeId(e.target.value)
                  setRelated('')
                }}
                disabled={!options}
                className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select employee...</option>
                {options?.employees.map(employee => (
                  <option key={employee.id} value={employee.id}>
                    {employee.name}
                  </option>
                ))}
              </select>
              {field('employee_id')}
            </div>
            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Department</label>
              <input
                type="text"
                value={options?.department.name ?? ''}
                readOnly
                className="w-full border border-gray-200 bg-gray-50 rounded-xl px-3 py-2 text-sm text-gray-600"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
              Related Task or Report
            </label>
            <select
              value={related}
              onChange={e => setRelated(e.target.value)}
              className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">None</option>
              {tasks.length > 0 && (
                <optgroup label="Tasks">
                  {tasks.map(task => (
                    <option key={`task-${task.id}`} value={`task:${task.id}`}>
                      {task.task_code ? `${task.task_code} — ${task.title}` : task.title}
                    </option>
                  ))}
                </optgroup>
              )}
              {reportChoices.length > 0 && (
                <optgroup label="Reports">
                  {reportChoices.map(report => (
                    <option key={`report-${report.id}`} value={`report:${report.id}`}>
                      {report.title}
                    </option>
                  ))}
                </optgroup>
              )}
            </select>
            {field('related_id')}
          </div>

          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Subject *</label>
            <input
              type="text"
              value={title}
              onChange={e => setTitle(e.target.value)}
              placeholder="e.g. Repeated late submission on syllabus delivery"
              className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {field('title')}
          </div>

          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
              Detailed Description *
            </label>
            <textarea
              value={reason}
              onChange={e => setReason(e.target.value)}
              rows={4}
              placeholder="Describe the issue, its impact and the evidence behind it..."
              className="w-full border border-gray-300 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {field('reason')}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                Requested Action
              </label>
              <textarea
                value={requestedAction}
                onChange={e => setRequestedAction(e.target.value)}
                rows={2}
                placeholder="What would you like Administration to do?"
                className="w-full border border-gray-300 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              {field('requested_action')}
            </div>
            <div className="space-y-3">
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                  Assign To
                </label>
                <select
                  value={assignedTo}
                  onChange={e => setAssignedTo(e.target.value)}
                  className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">Administration review queue</option>
                  {options?.administrators.map(admin => (
                    <option key={admin.id} value={admin.id}>
                      {admin.name}
                    </option>
                  ))}
                </select>
                {field('assigned_to')}
              </div>
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                  Supporting Evidence
                </label>
                <input
                  type="file"
                  accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                  onChange={e => setAttachment(e.target.files?.[0] ?? null)}
                  className="w-full text-xs text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:text-xs file:font-bold"
                />
                {field('attachment')}
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 border border-gray-300 rounded-xl text-xs font-semibold text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting || !options}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold disabled:opacity-50"
            >
              {submitting ? 'Submitting...' : 'Submit Escalation'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ─── Detail panel ───────────────────────────────────────────────────────────

interface EscalationDetailPanelProps {
  escalationId: number
  onClose: () => void
}

function EscalationDetailPanel({ escalationId, onClose }: EscalationDetailPanelProps) {
  const [detail, setDetail] = useState<DepartmentEscalationDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true
    setLoading(true)
    setError(null)

    getDepartmentEscalation(escalationId)
      .then(res => {
        if (active) setDetail(res.data)
      })
      .catch(() => {
        if (active) setError('Failed to load this escalation.')
      })
      .finally(() => {
        if (active) setLoading(false)
      })

    return () => {
      active = false
    }
  }, [escalationId])

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-start sm:items-center justify-center p-4 z-50 overflow-y-auto">
      <div className="bg-white rounded-2xl p-6 max-w-2xl w-full shadow-xl space-y-4 my-8">
        <div className="flex justify-between items-start">
          <div>
            <h2 className="text-lg font-bold text-gray-900">
              {detail ? detail.reference : 'Escalation'}
            </h2>
            {detail && <p className="text-sm text-gray-700 mt-0.5">{detail.title}</p>}
          </div>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600 text-xl leading-none">
            ×
          </button>
        </div>

        {loading ? (
          <div className="space-y-3">
            {[...Array(4)].map((_, i) => (
              <div key={i} className="h-10 bg-gray-100 rounded-xl animate-pulse" />
            ))}
          </div>
        ) : error ? (
          <div className="bg-red-50 text-red-700 p-3 rounded-xl border border-red-200 text-sm">{error}</div>
        ) : detail ? (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              <span
                className={`text-xs px-2.5 py-0.5 rounded-full font-semibold ${
                  STATUS_STYLES[detail.status] ?? 'bg-gray-100 text-gray-700'
                }`}
              >
                {detail.status_label}
              </span>
              <span
                className={`text-xs px-2 py-0.5 rounded-md font-semibold ${
                  PRIORITY_STYLES[detail.priority] ?? 'bg-gray-100 text-gray-700'
                }`}
              >
                {detail.priority_label}
              </span>
              <span className="text-xs px-2 py-0.5 rounded-md font-medium bg-gray-100 text-gray-700">
                {detail.type_label}
              </span>
            </div>

            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
              <Detail label="Employee" value={detail.employee_name} />
              <Detail label="Department" value={detail.department_name} />
              <Detail label="Related Record" value={detail.related_label} />
              <Detail label="Date Raised" value={formatDateTime(detail.created_at)} />
              <Detail label="Raised By" value={detail.created_by_name} />
              <Detail label="Assigned Administrator" value={detail.assigned_to_name ?? 'Unassigned'} />
              <Detail label="Reviewed By" value={detail.reviewed_by_name} />
              <Detail label="Resolved By" value={detail.resolver_name} />
            </dl>

            <div>
              <p className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Description</p>
              <p className="text-sm text-gray-700 bg-gray-50 border border-gray-100 rounded-xl p-3 leading-relaxed">
                {detail.reason}
              </p>
            </div>

            {detail.requested_action && (
              <div>
                <p className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Requested Action</p>
                <p className="text-sm text-gray-700 bg-gray-50 border border-gray-100 rounded-xl p-3 leading-relaxed">
                  {detail.requested_action}
                </p>
              </div>
            )}

            {detail.attachment && (
              <p className="text-xs text-gray-500">
                Attachment: <span className="font-semibold text-gray-700">{detail.attachment.name}</span>
              </p>
            )}

            {detail.resolution_notes && (
              <div className="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-sm text-emerald-800">
                <b>Resolution{detail.resolver_name ? ` from ${detail.resolver_name}` : ''}:</b>{' '}
                {detail.resolution_notes}
              </div>
            )}

            <div>
              <p className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Activity History</p>
              <ol className="space-y-2">
                {detail.history.length === 0 && <li className="text-xs text-gray-400">No activity recorded yet.</li>}
                {detail.history.map(entry => (
                  <li key={entry.id} className="flex gap-3">
                    <div className="mt-1.5 h-2 w-2 rounded-full bg-blue-500 shrink-0" />
                    <div>
                      <p className="text-xs font-semibold text-gray-700 capitalize">
                        {entry.action.replace(/_/g, ' ')}
                        {entry.new_status ? ` · ${entry.new_status.replace(/_/g, ' ')}` : ''}
                      </p>
                      {entry.note && <p className="text-xs text-gray-600 mt-0.5">{entry.note}</p>}
                      <p className="text-xs text-gray-400 mt-0.5">
                        {entry.actor_name} · {formatDateTime(entry.created_at)}
                      </p>
                    </div>
                  </li>
                ))}
              </ol>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  )
}

function Detail({ label, value }: { label: string; value: string | null }) {
  return (
    <div>
      <dt className="text-xs font-bold text-gray-500 uppercase tracking-wider">{label}</dt>
      <dd className="text-sm text-gray-800 mt-0.5">{value ?? '—'}</dd>
    </div>
  )
}
