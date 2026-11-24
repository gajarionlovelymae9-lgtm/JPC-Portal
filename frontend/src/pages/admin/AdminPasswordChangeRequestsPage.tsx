import { useState } from 'react'
import {
  approveAdminPasswordChangeRequest,
  getAdminPasswordChangeRequest,
  getAdminPasswordChangeRequests,
  rejectAdminPasswordChangeRequest,
  type PasswordChangeRequestItem,
} from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
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
  dateTime,
  humanise,
  num,
} from '../../components/admin/AdminUi'

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pending approval' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
]

/**
 * Admin — password change request review queue.
 *
 * Employees submit password change requests from their Security page. This page
 * lists them and lets an admin approve (which applies the stored password hash)
 * or reject (which leaves the account untouched). The password itself is never
 * exposed here.
 */
export default function AdminPasswordChangeRequestsPage() {
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const [reviewId, setReviewId] = useState<number | null>(null)
  const [detail, setDetail] = useState<PasswordChangeRequestItem | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [actionError, setActionError] = useState('')
  const [busy, setBusy] = useState(false)
  const [rejectMode, setRejectMode] = useState(false)
  const [decisionNote, setDecisionNote] = useState('')

  const resource = useAdminResource(
    () => getAdminPasswordChangeRequests({ status, search, page, per_page: 15 }),
    [status, search, page],
  )

  const data = resource.data

  const openReview = async (id: number) => {
    setReviewId(id)
    setDetail(null)
    setActionError('')
    setRejectMode(false)
    setDecisionNote('')
    setDetailLoading(true)
    try {
      const response = await getAdminPasswordChangeRequest(id)
      setDetail(response.data)
    } catch (cause) {
      setActionError(cause instanceof Error ? cause.message : 'Unable to load the request.')
    } finally {
      setDetailLoading(false)
    }
  }

  const closeReview = () => {
    setReviewId(null)
    setDetail(null)
    setActionError('')
    setRejectMode(false)
    setDecisionNote('')
  }

  const runDecision = async (action: 'approve' | 'reject') => {
    if (!reviewId) return
    setBusy(true)
    setActionError('')
    try {
      const note = decisionNote.trim() || null
      const response =
        action === 'approve'
          ? await approveAdminPasswordChangeRequest(reviewId, note)
          : await rejectAdminPasswordChangeRequest(reviewId, note)
      setDetail((previous) => (previous ? { ...previous, ...response.data } : previous))
      setRejectMode(false)
      resource.reload()
    } catch (cause) {
      setActionError(cause instanceof Error ? cause.message : 'Unable to record the decision.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <AdminPage
      title="Password Requests"
      subtitle="Review employee password change requests and record the decision"
    >
      <FilterBar>
        <Field label="Status">
          <Select
            value={status}
            onChange={(value) => {
              setStatus(value)
              setPage(1)
            }}
            options={STATUS_OPTIONS}
            placeholder="All requests"
          />
        </Field>
        <Field label="Search" className="flex-1 min-w-[200px]">
          <TextInput
            value={search}
            onChange={(value) => {
              setSearch(value)
              setPage(1)
            }}
            placeholder="Employee name or email"
          />
        </Field>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading password change requests" />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load password change requests.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Total requests" value={num(data.summary.total)} />
            <StatCard
              label="Pending approval"
              value={num(data.summary.pending)}
              tone={data.summary.pending > 0 ? 'warn' : 'neutral'}
            />
            <StatCard label="Approved" value={num(data.summary.approved)} tone="ok" />
            <StatCard
              label="Rejected"
              value={num(data.summary.rejected)}
              tone={data.summary.rejected > 0 ? 'danger' : 'neutral'}
            />
          </div>

          <Panel
            title="Password change requests"
            description={`${data.meta.total} request${data.meta.total === 1 ? '' : 's'} · pending items are listed first`}
          >
            {data.items.length === 0 ? (
              <EmptyState
                title="No password change requests"
                message="When an employee requests a password change it will appear here for review."
              />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Employee</Th>
                      <Th>Reason</Th>
                      <Th>Status</Th>
                      <Th>Requested</Th>
                      <Th>Action</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((item) => (
                      <RequestRow key={item.id} item={item} onReview={openReview} />
                    ))}
                  </tbody>
                </Table>
                <Pagination meta={data.meta} onPageChange={setPage} />
              </>
            )}
          </Panel>
        </div>
      )}

      <Modal
        open={reviewId !== null}
        title="Password change request"
        onClose={closeReview}
        footer={
          <>
            {detail?.status === 'pending' && !rejectMode ? (
              <>
                <Button variant="danger" onClick={() => setRejectMode(true)} disabled={busy}>
                  Reject…
                </Button>
                <Button onClick={() => runDecision('approve')} disabled={busy}>
                  {busy ? 'Working…' : 'Approve'}
                </Button>
              </>
            ) : null}
            {rejectMode ? (
              <>
                <Button variant="secondary" onClick={() => setRejectMode(false)} disabled={busy}>
                  Cancel
                </Button>
                <Button
                  variant="danger"
                  onClick={() => runDecision('reject')}
                  disabled={busy || !decisionNote.trim()}
                >
                  {busy ? 'Working…' : 'Confirm rejection'}
                </Button>
              </>
            ) : null}
            <Button variant="secondary" onClick={closeReview}>
              Close
            </Button>
          </>
        }
      >
        {detailLoading ? (
          <p className="text-sm text-slate-400">Loading request…</p>
        ) : actionError && !detail ? (
          <ErrorPanel message={actionError} />
        ) : detail ? (
          <div className="space-y-4 text-sm">
            {actionError ? (
              <p className="text-red-700 bg-red-50 border border-red-200 rounded-xl px-3 py-2">{actionError}</p>
            ) : null}

            <dl className="grid sm:grid-cols-2 gap-x-4 gap-y-2">
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Employee</dt>
                <dd className="text-slate-800 font-semibold">
                  {detail.requester_name ?? '—'}
                  {detail.employee_number ? (
                    <span className="text-slate-400 font-normal"> · {detail.employee_number}</span>
                  ) : null}
                </dd>
                <dd className="text-slate-500">{detail.requester_email ?? '—'}</dd>
                <dd className="text-slate-500">{detail.department_name ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</dt>
                <dd>
                  <StatusBadge status={detail.status} />
                </dd>
              </div>
            </dl>

            <div>
              <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Reason</dt>
              <p className="text-slate-700 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 whitespace-pre-wrap">
                {detail.reason ?? 'No reason provided.'}
              </p>
            </div>

            {detail.status !== 'pending' ? (
              <div className="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 space-y-1">
                <p className="font-bold text-slate-700">Decision recorded</p>
                <p className="text-slate-600">
                  {humanise(detail.status)} by {detail.reviewed_by ?? '—'} · {dateTime(detail.reviewed_at)}
                </p>
                {detail.decision_note ? (
                  <p className="text-slate-600">Note: {detail.decision_note}</p>
                ) : null}
                {detail.applied_at ? (
                  <p className="text-emerald-700">Password updated on {dateTime(detail.applied_at)}</p>
                ) : null}
              </div>
            ) : (
              <Field
                label={rejectMode ? 'Rejection reason' : 'Decision note (optional)'}
                required={rejectMode}
                hint={
                  rejectMode
                    ? 'The employee sees this reason when the request is rejected.'
                    : 'Shown to the employee with the decision.'
                }
              >
                <textarea
                  className="w-full text-sm rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-jpc-500 focus:border-jpc-500 min-h-[80px]"
                  value={decisionNote}
                  onChange={(event) => setDecisionNote(event.target.value)}
                  placeholder={rejectMode ? 'Explain why this request is rejected' : 'Optional note for the employee'}
                />
              </Field>
            )}
          </div>
        ) : null}
      </Modal>
    </AdminPage>
  )
}

function RequestRow({
  item,
  onReview,
}: {
  item: PasswordChangeRequestItem
  onReview: (id: number) => void
}) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <Td>
        <p className="font-semibold text-slate-800">{item.requester_name ?? '—'}</p>
        <p className="text-[11px] text-slate-400">
          {item.employee_number ?? ''}
          {item.department_name ? ` · ${item.department_name}` : ''}
        </p>
      </Td>
      <Td className="text-slate-600 max-w-xs">
        <p className="line-clamp-2">{item.reason ?? '—'}</p>
      </Td>
      <Td>
        <StatusBadge status={item.status} />
      </Td>
      <Td className="text-slate-500 text-xs">{dateTime(item.created_at)}</Td>
      <Td>
        <Button variant="ghost" onClick={() => onReview(item.id)}>
          Review
        </Button>
      </Td>
    </tr>
  )
}
