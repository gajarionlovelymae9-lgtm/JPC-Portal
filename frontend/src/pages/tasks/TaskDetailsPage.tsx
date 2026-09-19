import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useParams } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { ActivityTimeline } from '../../components/ActivityTimeline'
import { ConfirmDialog } from '../../components/common/ConfirmDialog'
import { TaskPriorityBadge } from '../../components/tasks/TaskPriorityBadge'
import { TaskProgressBar } from '../../components/tasks/TaskProgressBar'
import { TaskProgressHistory } from '../../components/tasks/TaskProgressHistory'
import { TaskStatusBadge } from '../../components/tasks/TaskStatusBadge'
import { TaskTimeline } from '../../components/tasks/TaskTimeline'
import { useActivityLogs } from '../../hooks/useActivityLogs'
import { useTask } from '../../hooks/useTasks'
import { getApiErrorMessage, getApiFieldErrors, type FieldErrors } from '../../utils/apiError'
import * as assignmentService from '../../services/taskAssignmentService'
import { cancelTask } from '../../services/taskService'
import { deadlineLabel, decisionLabel, formatDate, toNumber } from '../../utils/task'
import type {
  ReviewDecision,
  Task,
  TaskAssignment,
  TaskProgress,
  TaskReview,
  TaskTimelineItem,
} from '../../types/task'

const PROGRESS_STATUSES = ['assigned', 'accepted', 'in_progress', 'rejected']

type PendingAction = 'submit' | 'resubmit' | 'cancel'

function replaceAssignment(task: Task, updated: TaskAssignment): Task {
  const existing = task.assignments ?? []
  const found = existing.some((assignment) => assignment.id === updated.id)

  return {
    ...task,
    assignments: found
      ? existing.map((assignment) => (assignment.id === updated.id ? updated : assignment))
      : [...existing, updated],
  }
}

function assignmentLabel(assignment: TaskAssignment): string {
  return assignment.employee?.name ?? `Employee #${assignment.employee_id}`
}

function overallProgress(task: Task): number {
  if (task.status === 'completed') {
    return 100
  }
  const assignments = task.assignments ?? []
  if (assignments.length === 0) {
    return 0
  }
  return Math.max(...assignments.map((assignment) => toNumber(assignment.completion_percentage)))
}

function latestReview(reviews: TaskReview[]): TaskReview | null {
  if (reviews.length === 0) {
    return null
  }
  return [...reviews].sort(
    (a, b) => new Date(b.reviewed_at ?? 0).getTime() - new Date(a.reviewed_at ?? 0).getTime(),
  )[0]
}

/** Append-only audit trail for this task, shown only with activity_logs.view. */
function ActivityLogSection({ taskId }: { taskId: number }) {
  const { logs, loading, error, refresh } = useActivityLogs({
    subject: 'Task',
    subject_id: taskId,
    per_page: 10,
  })

  return (
    <>
      <h4 className="task-timeline__heading">System activity</h4>
      {error ? (
        <p className="page-state">{error}</p>
      ) : loading && logs.length === 0 ? (
        <p className="page-state">Loading system activity…</p>
      ) : (
        <ActivityTimeline items={logs} emptyMessage="No system activity has been recorded yet." />
      )}
      <button type="button" className="button button--small button--secondary" onClick={refresh}>
        Refresh
      </button>
    </>
  )
}

function TaskDetailsContent({ taskId }: { taskId: string }) {
  const { user } = useAuth()
  const { task, setTask, loading, error, refresh } = useTask(taskId)

  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [progress, setProgress] = useState<TaskProgress[]>([])
  const [reviews, setReviews] = useState<TaskReview[]>([])
  const [historyLoading, setHistoryLoading] = useState(false)
  const [busy, setBusy] = useState(false)
  const [feedback, setFeedback] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [percentage, setPercentage] = useState('')
  const [progressComment, setProgressComment] = useState('')
  const [submitRemarks, setSubmitRemarks] = useState('')
  const [decision, setDecision] = useState<ReviewDecision>('approved')
  const [reviewRemarks, setReviewRemarks] = useState('')
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null)

  const assignments = task?.assignments ?? []
  const ownAssignment = user?.employee_id
    ? assignments.find((assignment) => assignment.employee_id === user.employee_id)
    : undefined
  const effectiveSelectedId = selectedId ?? ownAssignment?.id ?? assignments[0]?.id ?? null
  const selected = assignments.find((assignment) => assignment.id === effectiveSelectedId) ?? null
  const isManager = user?.role === 'admin' || user?.role === 'department_head'
  const isOwner = Boolean(
    selected && user?.employee_id && selected.employee_id === user.employee_id,
  )

  useEffect(() => {
    if (!effectiveSelectedId) {
      return
    }

    let active = true

    void (async () => {
      await Promise.resolve()
      if (!active) {
        return
      }
      setHistoryLoading(true)

      try {
        const [progressResponse, reviewResponse] = await Promise.all([
          assignmentService.getAssignmentProgress(effectiveSelectedId),
          assignmentService.getAssignmentReviews(effectiveSelectedId),
        ])
        if (active) {
          setProgress(progressResponse.data)
          setReviews(reviewResponse.data)
        }
      } catch {
        if (active) {
          setProgress([])
          setReviews([])
        }
      } finally {
        if (active) {
          setHistoryLoading(false)
        }
      }
    })()

    return () => {
      active = false
    }
  }, [effectiveSelectedId, taskId])

  const timelineItems = useMemo<TaskTimelineItem[]>(() => {
    const lifecycle: TaskTimelineItem[] = []
    if (selected) {
      if (selected.assigned_at) {
        lifecycle.push({
          id: `assigned-${selected.id}`,
          title: 'Assigned',
          subtitle: assignmentLabel(selected),
          at: selected.assigned_at,
          tone: 'info',
        })
      }
      if (selected.accepted_at) {
        lifecycle.push({
          id: `accepted-${selected.id}`,
          title: 'Accepted',
          subtitle: 'Assignment accepted',
          at: selected.accepted_at,
          tone: 'info',
        })
      }
    }

    const progressItems = progress.map<TaskTimelineItem>((entry) => ({
      id: `progress-${entry.id}`,
      title: `${toNumber(entry.new_percentage)}% progress`,
      subtitle:
        entry.previous_percentage === null || entry.previous_percentage === undefined
          ? 'Progress update'
          : `From ${toNumber(entry.previous_percentage)}%`,
      description: entry.comment ?? undefined,
      at: entry.created_at,
      tone: 'info',
    }))

    const reviewItems = reviews.map<TaskTimelineItem>((review) => ({
      id: `review-${review.id}`,
      title: decisionLabel(review.decision),
      subtitle: 'Review decision',
      description: review.remarks ?? undefined,
      at: review.reviewed_at,
      tone:
        review.decision === 'approved'
          ? 'ok'
          : review.decision === 'rejected'
            ? 'error'
            : 'warning',
    }))

    if (selected?.completed_at) {
      lifecycle.push({
        id: `completed-${selected.id}`,
        title: 'Completed',
        subtitle: 'Assignment completed',
        at: selected.completed_at,
        tone: 'ok',
      })
    }

    return [...lifecycle, ...progressItems, ...reviewItems].sort(
      (a, b) => new Date(b.at ?? 0).getTime() - new Date(a.at ?? 0).getTime(),
    )
  }, [progress, reviews, selected])

  const review = latestReview(reviews)

  function applyUpdated(updated: TaskAssignment) {
    if (task) {
      setTask(replaceAssignment(task, updated))
    }
  }

  function resetMessages() {
    setFeedback(null)
    setActionError(null)
    setFieldErrors({})
  }

  function fail(error_: unknown, fallback: string) {
    setActionError(getApiErrorMessage(error_, fallback))
    setFieldErrors(getApiFieldErrors(error_))
  }

  async function runAssignmentAction(
    action: () => Promise<{ data: TaskAssignment }>,
    successMessage: string,
  ) {
    if (!selected) {
      return
    }
    setBusy(true)
    resetMessages()

    try {
      const response = await action()
      applyUpdated(response.data)
      setFeedback(successMessage)
      const history = await assignmentService.getAssignmentProgress(selected.id)
      setProgress(history.data)
    } catch (error_: unknown) {
      fail(error_, 'The action could not be completed.')
    } finally {
      setBusy(false)
    }
  }

  async function handleProgressSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!selected) {
      return
    }

    const value = Number.parseFloat(percentage)
    if (!Number.isFinite(value) || value < 0 || value > 100) {
      setActionError('Enter a percentage between 0 and 100.')
      setFieldErrors({ percentage: ['Progress must be between 0 and 100.'] })
      return
    }

    setBusy(true)
    resetMessages()

    try {
      await assignmentService.updateAssignmentProgress(selected.id, {
        percentage: value,
        comment: progressComment.trim() || null,
      })
      const [refreshed, history] = await Promise.all([
        assignmentService.getAssignment(selected.id),
        assignmentService.getAssignmentProgress(selected.id),
      ])
      applyUpdated(refreshed.data)
      setProgress(history.data)
      setPercentage('')
      setProgressComment('')
      setFeedback('Progress updated.')
    } catch (error_: unknown) {
      fail(error_, 'Progress could not be updated.')
    } finally {
      setBusy(false)
    }
  }

  async function handleReviewSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!selected) {
      return
    }
    if (!reviewRemarks.trim()) {
      setActionError('Review remarks are required.')
      setFieldErrors({ remarks: ['Review remarks are required.'] })
      return
    }

    setBusy(true)
    resetMessages()

    try {
      await assignmentService.reviewAssignment(selected.id, {
        decision,
        remarks: reviewRemarks.trim(),
      })
      const [refreshed, reviewList] = await Promise.all([
        assignmentService.getAssignment(selected.id),
        assignmentService.getAssignmentReviews(selected.id),
      ])
      applyUpdated(refreshed.data)
      setReviews(reviewList.data)
      setReviewRemarks('')
      setFeedback('Review recorded.')
    } catch (error_: unknown) {
      fail(error_, 'The review could not be recorded.')
    } finally {
      setBusy(false)
    }
  }

  async function handleConfirmedAction() {
    if (!selected || !pendingAction || !task) {
      return
    }

    if (pendingAction === 'cancel') {
      setBusy(true)
      resetMessages()
      try {
        await cancelTask(task.id)
        refresh()
        setFeedback('Task cancelled.')
      } catch (error_: unknown) {
        fail(error_, 'The task could not be cancelled.')
      } finally {
        setBusy(false)
        setPendingAction(null)
      }
      return
    }

    const action =
      pendingAction === 'submit'
        ? () => assignmentService.submitAssignment(selected.id, submitRemarks.trim() || null)
        : () => assignmentService.resubmitAssignment(selected.id)

    await runAssignmentAction(
      action,
      pendingAction === 'submit' ? 'Assignment submitted for review.' : 'Assignment resubmitted.',
    )
    setSubmitRemarks('')
    setPendingAction(null)
  }

  if (loading) {
    return <p className="page-state">Loading task…</p>
  }

  if (error || !task) {
    return (
      <div className="page-state page-state--denied" role="alert">
        <p>{error ?? 'Task not found.'}</p>
        <button type="button" className="button button--secondary" onClick={refresh}>
          Retry
        </button>
      </div>
    )
  }

  const canAccept = Boolean(selected && isOwner && selected.status === 'assigned')
  const canStart = Boolean(
    selected && isOwner && ['assigned', 'accepted'].includes(selected.status),
  )
  const canSubmit = Boolean(
    selected && isOwner && ['accepted', 'in_progress'].includes(selected.status),
  )
  const canResubmit = Boolean(selected && isOwner && selected.status === 'rejected')
  const canUpdateProgress = Boolean(
    selected && isOwner && PROGRESS_STATUSES.includes(selected.status),
  )
  const canReview = Boolean(
    selected && isManager && ['submitted', 'under_review'].includes(selected.status),
  )
  const canCancel = isManager && ['draft', 'assigned'].includes(task.status)

  return (
    <div className="page">
      <div className="page__intro">
        <h2>{task.title}</h2>
        <p>
          {task.task_code} · {deadlineLabel(task.due_date)}
        </p>
      </div>

      {feedback ? <p className="task-feedback task-feedback--ok">{feedback}</p> : null}
      {actionError ? (
        <p className="task-feedback task-feedback--error" role="alert">
          {actionError}
        </p>
      ) : null}

      <section className="card task-detail">
        <div className="task-detail__badges">
          <TaskStatusBadge status={task.status} />
          <TaskPriorityBadge priority={task.priority} />
          {task.is_overdue ? <span className="task-badge task-badge--overdue">OVERDUE</span> : null}
        </div>

        {task.description ? <p className="task-detail__description">{task.description}</p> : null}

        <dl className="task-detail__meta">
          <div>
            <dt>Start date</dt>
            <dd>{formatDate(task.start_date)}</dd>
          </div>
          <div>
            <dt>Due date</dt>
            <dd>
              {formatDate(task.due_date)}
              <span className="task-detail__hint">{deadlineLabel(task.due_date)}</span>
            </dd>
          </div>
          <div>
            <dt>Completed</dt>
            <dd>{formatDate(task.completed_at)}</dd>
          </div>
          <div>
            <dt>Estimated hours</dt>
            <dd>{task.estimated_hours ? `${task.estimated_hours}h` : '—'}</dd>
          </div>
          {task.annual_plan ? (
            <div>
              <dt>Annual plan</dt>
              <dd>{task.annual_plan.title}</dd>
            </div>
          ) : null}
          {task.department_plan ? (
            <div>
              <dt>Department plan</dt>
              <dd>{task.department_plan.title}</dd>
            </div>
          ) : null}
          {task.department_target ? (
            <div>
              <dt>Department target</dt>
              <dd>{task.department_target.title}</dd>
            </div>
          ) : null}
          {task.employee_target ? (
            <div>
              <dt>Employee target</dt>
              <dd>{task.employee_target.title}</dd>
            </div>
          ) : null}
        </dl>

        <TaskProgressBar value={overallProgress(task)} label="Overall progress" />

        {canCancel ? (
          <div className="task-actions task-actions--end">
            <button
              type="button"
              className="button button--danger"
              disabled={busy}
              onClick={() => {
                resetMessages()
                setPendingAction('cancel')
              }}
            >
              Cancel task
            </button>
          </div>
        ) : null}
      </section>

      <section className="task-section">
        <h3>Assignments</h3>
        {assignments.length === 0 ? (
          <p className="page-state">This task has not been assigned yet.</p>
        ) : (
          <div className="task-assignment-list">
            {assignments.map((assignment) => (
              <button
                key={assignment.id}
                type="button"
                className={`task-assignment${
                  assignment.id === effectiveSelectedId ? ' task-assignment--active' : ''
                }`}
                onClick={() => setSelectedId(assignment.id)}
              >
                <span className="task-assignment__head">
                  <span className="task-assignment__name">{assignmentLabel(assignment)}</span>
                  <span className="task-assignment__status">
                    Assignment: <TaskStatusBadge status={assignment.status} />
                  </span>
                </span>
                <TaskProgressBar
                  value={assignment.completion_percentage}
                  label="Progress"
                  size="sm"
                />
                <span className="task-assignment__due">
                  Due {formatDate(assignment.due_date)} · {deadlineLabel(assignment.due_date)}
                  {assignment.is_overdue ? ' · Overdue' : ''}
                </span>
              </button>
            ))}
          </div>
        )}
      </section>

      {selected ? (
        <section className="task-section">
          <h3>Work on {assignmentLabel(selected)}</h3>

          <p className="task-assignment__status task-assignment__status--detail">
            Task status: <TaskStatusBadge status={task.status} /> · Your assignment:{' '}
            <TaskStatusBadge status={selected.status} />
          </p>

          {review ? (
            <div
              className={`task-review task-review--${
                review.decision === 'approved' ? 'ok' : 'warning'
              }`}
              role="status"
            >
              <strong>
                {review.decision === 'approved'
                  ? '✓ Approved'
                  : review.decision === 'needs_revision'
                    ? '✕ Revision Required'
                    : '✕ Rejected'}
              </strong>
              {review.remarks ? <p className="task-review__remarks">{review.remarks}</p> : null}
            </div>
          ) : null}

          {isOwner ? (
            <div className="task-actions">
              {canAccept ? (
                <button
                  type="button"
                  className="button"
                  disabled={busy}
                  onClick={() =>
                    runAssignmentAction(
                      () => assignmentService.acceptAssignment(selected.id),
                      'Task accepted.',
                    )
                  }
                >
                  Accept
                </button>
              ) : null}

              {canStart ? (
                <button
                  type="button"
                  className="button"
                  disabled={busy}
                  onClick={() =>
                    runAssignmentAction(
                      () => assignmentService.startAssignment(selected.id),
                      'Assignment started.',
                    )
                  }
                >
                  Start work
                </button>
              ) : null}

              {canSubmit ? (
                <button
                  type="button"
                  className="button"
                  disabled={busy}
                  onClick={() => {
                    resetMessages()
                    setPendingAction('submit')
                  }}
                >
                  Submit for review
                </button>
              ) : null}

              {canResubmit ? (
                <button
                  type="button"
                  className="button"
                  disabled={busy}
                  onClick={() => {
                    resetMessages()
                    setPendingAction('resubmit')
                  }}
                >
                  Resubmit
                </button>
              ) : null}
            </div>
          ) : null}

          {canSubmit || canResubmit ? (
            <label className="task-form__field task-form__field--block">
              <span>Submission remarks (optional)</span>
              <textarea
                rows={2}
                maxLength={5000}
                value={submitRemarks}
                placeholder="Anything the reviewer should know"
                onChange={(event) => setSubmitRemarks(event.target.value)}
              />
              {fieldErrors.remarks?.[0] ? (
                <span className="task-form__error">{fieldErrors.remarks[0]}</span>
              ) : null}
            </label>
          ) : null}

          {canUpdateProgress ? (
            <form className="task-form" onSubmit={handleProgressSubmit}>
              <h4>Update progress</h4>
              <label className="task-form__field task-form__field--grow">
                <span>Percentage</span>
                <input
                  type="range"
                  min={0}
                  max={100}
                  step={1}
                  value={percentage === '' ? 0 : Number(percentage)}
                  aria-label="Progress percentage slider"
                  onChange={(event) => setPercentage(event.target.value)}
                />
              </label>
              <label className="task-form__field">
                <span>Value</span>
                <input
                  type="number"
                  min={0}
                  max={100}
                  value={percentage}
                  required
                  onChange={(event) => setPercentage(event.target.value)}
                />
              </label>
              <label className="task-form__field task-form__field--grow">
                <span>Comment</span>
                <textarea
                  rows={3}
                  maxLength={5000}
                  value={progressComment}
                  placeholder="What did you complete?"
                  onChange={(event) => setProgressComment(event.target.value)}
                />
              </label>
              {fieldErrors.percentage?.[0] ? (
                <span className="task-form__error task-form__error--block">
                  {fieldErrors.percentage[0]}
                </span>
              ) : null}
              <button type="submit" className="button" disabled={busy}>
                Save progress
              </button>
            </form>
          ) : null}

          {canReview ? (
            <form className="task-form" onSubmit={handleReviewSubmit}>
              <h4>Review submission</h4>
              <label className="task-form__field">
                <span>Decision</span>
                <select
                  value={decision}
                  onChange={(event) => setDecision(event.target.value as ReviewDecision)}
                >
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                  <option value="needs_revision">Needs revision</option>
                </select>
              </label>
              <label className="task-form__field task-form__field--grow">
                <span>Remarks</span>
                <textarea
                  rows={3}
                  maxLength={5000}
                  value={reviewRemarks}
                  required
                  placeholder="Explain your decision"
                  onChange={(event) => setReviewRemarks(event.target.value)}
                />
              </label>
              {fieldErrors.remarks?.[0] ? (
                <span className="task-form__error task-form__error--block">
                  {fieldErrors.remarks[0]}
                </span>
              ) : null}
              <button type="submit" className="button" disabled={busy}>
                Record review
              </button>
            </form>
          ) : null}

          <h4 className="task-timeline__heading">Progress history</h4>
          {historyLoading ? (
            <p className="page-state">Loading history…</p>
          ) : (
            <TaskProgressHistory entries={progress} updatedBy={assignmentLabel(selected)} />
          )}

          <h4 className="task-timeline__heading">Activity</h4>
          {historyLoading ? (
            <p className="page-state">Loading activity…</p>
          ) : (
            <TaskTimeline items={timelineItems} />
          )}

          {user?.permissions.includes('activity_logs.view') ? (
            <ActivityLogSection taskId={Number(taskId)} />
          ) : null}
        </section>
      ) : null}

      <ConfirmDialog
        open={pendingAction !== null}
        busy={busy}
        tone={pendingAction === 'cancel' ? 'danger' : 'default'}
        title={
          pendingAction === 'submit'
            ? 'Submit for review'
            : pendingAction === 'resubmit'
              ? 'Resubmit task'
              : 'Cancel task'
        }
        message={
          pendingAction === 'submit'
            ? 'Are you sure you want to submit this task for review?'
            : pendingAction === 'resubmit'
              ? 'Are you sure you want to resubmit this task for review?'
              : 'Are you sure you want to cancel this task? This cannot be undone.'
        }
        confirmLabel={
          pendingAction === 'submit'
            ? 'Submit'
            : pendingAction === 'resubmit'
              ? 'Resubmit'
              : 'Cancel task'
        }
        cancelLabel={pendingAction === 'cancel' ? 'Keep task' : 'Back'}
        onConfirm={handleConfirmedAction}
        onCancel={() => setPendingAction(null)}
      />
    </div>
  )
}

export default function TaskDetailsPage() {
  const { taskId } = useParams<{ taskId: string }>()

  if (!taskId) {
    return <p className="page-state">Task not found.</p>
  }

  return <TaskDetailsContent taskId={taskId} />
}
