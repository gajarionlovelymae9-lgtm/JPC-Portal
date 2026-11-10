import { useState } from 'react'
import {
  approveAdminTargetAdjustment,
  getAdminTargetAdjustment,
  getAdminTargetAdjustments,
  rejectAdminTargetAdjustment,
  type TargetAdjustmentDetail,
  type TargetAdjustmentItem,
  type TargetAdjustmentProposal,
} from '../../services/adminDashboardService'
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
  pct,
  shortDate,
} from '../../components/admin/AdminUi'

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pending review' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
]

/** Human-readable summary of the fields an employee asked to change. */
function proposalLines(proposed: TargetAdjustmentProposal): string[] {
  const lines: string[] = []
  if (proposed.target_value !== null) {
    lines.push(`Target: ${num(proposed.target_value)}${proposed.target_unit ? ` ${proposed.target_unit}` : ''}`)
  }
  if (proposed.start_date || proposed.end_date) {
    lines.push(`Period: ${shortDate(proposed.start_date)} → ${shortDate(proposed.end_date)}`)
  }
  if (proposed.description) {
    lines.push('Description updated')
  }
  return lines
}

/**
 * Admin — target adjustment review queue.
 *
 * Employees raise adjustment requests from My Targets; this page lists them,
 * lets an admin inspect the original and proposed values side by side, and
 * records an approve/reject decision. Approving applies the change to the
 * target while the original values are preserved in `original_snapshot`.
 */
export default function AdminTargetAdjustmentsPage() {
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const [reviewId, setReviewId] = useState<number | null>(null)
  const [detail, setDetail] = useState<TargetAdjustmentDetail | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [actionError, setActionError] = useState('')
  const [busy, setBusy] = useState(false)
  const [rejectMode, setRejectMode] = useState(false)
  const [decisionNote, setDecisionNote] = useState('')

  const resource = useAdminResource(
    () => getAdminTargetAdjustments({ status, search, page, per_page: 15 }),
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
      const response = await getAdminTargetAdjustment(id)
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
          ? await approveAdminTargetAdjustment(reviewId, note)
          : await rejectAdminTargetAdjustment(reviewId, note)
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
      title="Target Adjustments"
      subtitle="Review employee requests to change their targets and record the decision"
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
            placeholder="Employee name or target title"
          />
        </Field>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading adjustment requests" />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load adjustment requests.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Total requests" value={num(data.summary.total)} />
            <StatCard
              label="Pending review"
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
            title="Adjustment requests"
            description={`${data.meta.total} request${data.meta.total === 1 ? '' : 's'} · pending items are listed first`}
          >
            {data.items.length === 0 ? (
              <EmptyState
                title="No adjustment requests"
                message="When an employee requests a target change it will appear here for review."
              />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Employee</Th>
                      <Th>Target</Th>
                      <Th>Change</Th>
                      <Th>Proposed values</Th>
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
        title="Adjustment request"
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
                  {detail.employee_name ?? '—'}
                  {detail.employee_number ? (
                    <span className="text-slate-400 font-normal"> · {detail.employee_number}</span>
                  ) : null}
                </dd>
                <dd className="text-slate-500">{detail.department_name ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Target</dt>
                <dd className="text-slate-800 font-semibold">{detail.target_title ?? '—'}</dd>
                {detail.target ? (
                  <dd className="text-slate-500">
                    Current: {num(detail.target.target_value)}
                    {detail.target.target_unit ? ` ${detail.target.target_unit}` : ''} · {pct(detail.target.progress)} complete
                  </dd>
                ) : null}
              </div>
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Request type</dt>
                <dd className="text-slate-700">{humanise(detail.request_type)}</dd>
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
                {detail.reason}
              </p>
            </div>

            <div>
              <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Proposed change</dt>
              {proposalLines(detail.proposed).length === 0 ? (
                <p className="text-slate-500">No field changes were proposed.</p>
              ) : (
                <ul className="list-disc list-inside text-slate-700 space-y-0.5">
                  {proposalLines(detail.proposed).map((line) => (
                    <li key={line}>{line}</li>
                  ))}
                </ul>
              )}
              {detail.proposed.description ? (
                <p className="text-slate-600 mt-1 whitespace-pre-wrap">{detail.proposed.description}</p>
              ) : null}
            </div>

            {detail.original_snapshot ? (
              <div>
                <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                  Original values (preserved)
                </dt>
                <p className="text-[11px] text-slate-500 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 font-mono break-all">
                  {JSON.stringify(detail.original_snapshot)}
                </p>
              </div>
            ) : null}

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
                  <p className="text-emerald-700">Applied to the target on {dateTime(detail.applied_at)}</p>
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
  item: TargetAdjustmentItem
  onReview: (id: number) => void
}) {
  const lines = proposalLines(item.proposed)
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <Td>
        <p className="font-semibold text-slate-800">{item.employee_name ?? '—'}</p>
        <p className="text-[11px] text-slate-400">
          {item.employee_number ?? ''}
          {item.department_name ? ` · ${item.department_name}` : ''}
        </p>
      </Td>
      <Td>
        <p className="font-medium text-slate-800">{item.target_title ?? '—'}</p>
        {item.request_type ? <Badge tone="info">{humanise(item.request_type)}</Badge> : null}
      </Td>
      <Td className="text-slate-600 max-w-xs">
        <p className="line-clamp-2">{item.reason}</p>
      </Td>
      <Td className="text-slate-600 text-xs">
        {lines.length === 0 ? (
          <span className="text-slate-400">—</span>
        ) : (
          <ul className="space-y-0.5">
            {lines.map((line) => (
              <li key={line}>{line}</li>
            ))}
          </ul>
        )}
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
