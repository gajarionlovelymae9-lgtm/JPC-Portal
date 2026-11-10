import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
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
import { deadlineLabel, decisionLabel, formatDate, formatDateTime, toNumber } from '../../utils/task'
import { PATHS } from '../../routes/paths'
import type {
  ReviewDecision,
  SubmissionMethod,
  Task,
  TaskAssignment,
  TaskProgress,
  TaskReview,
  TaskSubmission,
  TaskTimelineItem,
} from '../../types/task'

const PROGRESS_STATUSES = ['assigned', 'accepted', 'in_progress', 'rejected']

const SUBMISSION_METHODS: { value: SubmissionMethod; label: string; hint: string }[] = [
  {
    value: 'upload',
    label: 'Upload files',
    hint: 'PDF, Word, Excel, PowerPoint, TXT, JPEG, PNG or ZIP. Up to 10 files, 20 MB each.',
  },
  {
    value: 'link',
    label: 'Submit a link',
    hint: 'Share an HTTPS link to Google Drive, OneDrive, Google Docs or similar.',
  },
  {
    value: 'folder',
    label: 'Submit a folder',
    hint: 'Upload a ZIP archive or paste a shared folder link.',
  },
  {
    value: 'written',
    label: 'Written response',
    hint: 'Describe the work you completed directly in the portal.',
  },
]

const ACCEPTED_FILE_TYPES =
  '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.zip'

type PendingAction = 'cancel'

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
  const assignments = task.assignments ?? []
  if (assignments.length === 0) {
    return task.status === 'completed' ? 100 : 0
  }

  // Overall progress is the average of the real assignment progress values.
  // Approved assignments are guaranteed to be 100 by the backend.
  const total = assignments.reduce(
    (sum, assignment) => sum + toNumber(assignment.completion_percentage),
    0,
  )
  return total / assignments.length
}

function latestReview(reviews: TaskReview[]): TaskReview | null {
  if (reviews.length === 0) {
    return null
  }
  return [...reviews].sort(
    (a, b) => new Date(b.reviewed_at ?? 0).getTime() - new Date(a.reviewed_at ?? 0).getTime(),
  )[0]
}

function submissionMethodLabel(method: string): string {
  return SUBMISSION_METHODS.find((option) => option.value === method)?.label ?? method
}

function fileSizeLabel(bytes: number | null | undefined): string {
  if (!bytes || bytes <= 0) {
    return '—'
  }
  const units = ['B', 'KB', 'MB', 'GB']
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit += 1
  }
  return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
}

interface SubmissionCardProps {
  submission: TaskSubmission
  downloadingId: number | null
  onDownload: (attachmentId: number, fileName: string) => void
}

/** Read-only render of one preserved submission version for employees/reviewers. */
function SubmissionCard({ submission, downloadingId, onDownload }: SubmissionCardProps) {
  return (
    <article className="task-submission">
      <header className="task-submission__head">
        <strong>Version {submission.version}</strong>
        <span className="task-submission__meta">
          {submissionMethodLabel(submission.method)} · {formatDateTime(submission.submitted_at)}
          {submission.submitted_by_name ? ` · ${submission.submitted_by_name}` : ''}
        </span>
      </header>

      {submission.notes ? (
        <p className="task-submission__notes">
          <strong>Notes:</strong> {submission.notes}
        </p>
      ) : null}

      {submission.written_response ? (
        <div className="task-submission__written">
          <h5>Written response</h5>
          <p>{submission.written_response}</p>
        </div>
      ) : null}

      {submission.url ? (
        <p className="task-submission__link">
          <strong>Shared link:</strong>{' '}
          <a href={submission.url} target="_blank" rel="noopener noreferrer">
            {submission.url}
          </a>
        </p>
      ) : null}

      {submission.attachments.length > 0 ? (
        <ul className="task-submission__files">
          {submission.attachments.map((file) => (
            <li key={file.id}>
              <button
                type="button"
                className="task-file-link"
                disabled={downloadingId === file.id}
                onClick={() => onDownload(file.id, file.file_name)}
              >
                {file.file_name}
              </button>
              <span className="task-submission__filesize">{fileSizeLabel(file.file_size)}</span>
            </li>
          ))}
        </ul>
      ) : null}
    </article>
  )
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
  const navigate = useNavigate()
  const location = useLocation()

  // This page is mounted at both /tasks/:taskId and /employee/tasks/:taskId,
  // so return the user to whichever Tasks list they actually came from.
  const backToTasksPath = location.pathname.startsWith(PATHS.employeeTasks)
    ? PATHS.employeeTasks
    : PATHS.tasks

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
  const [submissions, setSubmissions] = useState<TaskSubmission[]>([])
  const [submissionMethod, setSubmissionMethod] = useState<SubmissionMethod>('upload')
  const [submissionFiles, setSubmissionFiles] = useState<File[]>([])
  const [submissionUrl, setSubmissionUrl] = useState('')
  const [submissionWritten, setSubmissionWritten] = useState('')
  const [submissionNotes, setSubmissionNotes] = useState('')
  const [submissionOpen, setSubmissionOpen] = useState(false)
  const [downloadingId, setDownloadingId] = useState<number | null>(null)
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
        const [progressResponse, reviewResponse, submissionResponse] = await Promise.all([
          assignmentService.getAssignmentProgress(effectiveSelectedId),
          assignmentService.getAssignmentReviews(effectiveSelectedId),
          assignmentService.getAssignmentSubmissions(effectiveSelectedId),
        ])
        if (active) {
          setProgress(progressResponse.data)
          setReviews(reviewResponse.data)
          setSubmissions(submissionResponse.data)
        }
      } catch {
        if (active) {
          setProgress([])
          setReviews([])
          setSubmissions([])
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
  const submissionHistory = submissions.length > 0 ? submissions : selected?.submissions ?? []

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
  }

  function resetSubmissionForm() {
    setSubmissionMethod('upload')
    setSubmissionFiles([])
    setSubmissionUrl('')
    setSubmissionWritten('')
    setSubmissionNotes('')
    setSubmissionOpen(false)
  }

  async function handleSubmissionSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!selected) {
      return
    }

    resetMessages()

    if (submissionMethod === 'upload' && submissionFiles.length === 0) {
      setActionError('Select at least one file before submitting.')
      setFieldErrors({ files: ['At least one file is required.'] })
      return
    }
    if (submissionMethod === 'link') {
      if (!/^https:\/\/\S+$/i.test(submissionUrl.trim())) {
        setActionError('Enter a valid HTTPS link (it must start with https://).')
        setFieldErrors({ url: ['A valid HTTPS link is required.'] })
        return
      }
    }
    if (submissionMethod === 'folder' && submissionFiles.length === 0) {
      if (!/^https:\/\/\S+$/i.test(submissionUrl.trim())) {
        setActionError('Upload a ZIP archive or enter a valid HTTPS folder link.')
        setFieldErrors({ url: ['Upload a ZIP archive or provide an HTTPS folder link.'] })
        return
      }
    }
    if (submissionMethod === 'written' && !submissionWritten.trim()) {
      setActionError('Describe the work you completed before submitting.')
      setFieldErrors({ written_response: ['A written response is required.'] })
      return
    }

    const payload = {
      method: submissionMethod,
      notes: submissionNotes.trim() || null,
      url: submissionUrl.trim() || null,
      writtenResponse: submissionWritten.trim() || null,
      files: submissionFiles,
    }

    setBusy(true)
    try {
      const response = canResubmit
        ? await assignmentService.resubmitAssignment(selected.id, payload)
        : await assignmentService.submitAssignment(selected.id, payload)

      applyUpdated(response.data)
      setSubmissions(response.data.submissions ?? [])
      resetSubmissionForm()
      setFeedback(
        canResubmit ? 'Task resubmitted for review.' : 'Task submitted for review.',
      )
    } catch (error_: unknown) {
      fail(error_, 'The task could not be submitted.')
    } finally {
      setBusy(false)
    }
  }

  async function handleAttachmentDownload(attachmentId: number, fileName: string) {
    setDownloadingId(attachmentId)
    try {
      await assignmentService.downloadTaskAttachment(attachmentId, fileName)
    } catch (error_: unknown) {
      fail(error_, 'The file could not be downloaded.')
    } finally {
      setDownloadingId(null)
    }
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
      <button
        type="button"
        onClick={() => navigate(backToTasksPath)}
        className="mb-4 text-sm font-semibold text-gray-500 hover:text-gray-900 flex items-center gap-1"
      >
        ← Back to Tasks
      </button>

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
                    Assignment:{' '}
                    <TaskStatusBadge
                      status={assignment.status}
                      decision={assignment.latest_review_decision}
                    />
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
            <TaskStatusBadge status={selected.status} decision={review?.decision} />
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

              {canSubmit || canResubmit ? (
                <button
                  type="button"
                  className="button"
                  disabled={busy}
                  onClick={() => {
                    resetMessages()
                    setSubmissionOpen((open) => !open)
                  }}
                >
                  {canResubmit ? 'Resubmit task' : 'Submit task'}
                </button>
              ) : null}
            </div>
          ) : null}

          {isOwner && (canSubmit || canResubmit) && submissionOpen ? (
            <form className="task-form task-form--submission" onSubmit={handleSubmissionSubmit}>
              <h4>{canResubmit ? 'Resubmit task' : 'Submit task'}</h4>

              <label className="task-form__field">
                <span>Submission method</span>
                <select
                  value={submissionMethod}
                  onChange={(event) => {
                    setSubmissionMethod(event.target.value as SubmissionMethod)
                    setSubmissionFiles([])
                    setSubmissionUrl('')
                    setSubmissionWritten('')
                  }}
                >
                  {SUBMISSION_METHODS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>

              <p className="task-form__hint">
                {SUBMISSION_METHODS.find((option) => option.value === submissionMethod)?.hint}
              </p>

              {submissionMethod === 'upload' || submissionMethod === 'folder' ? (
                <label className="task-form__field task-form__field--block">
                  <span>
                    {submissionMethod === 'folder' ? 'ZIP archive' : 'Files'}
                  </span>
                  <input
                    type="file"
                    multiple
                    accept={submissionMethod === 'folder' ? '.zip' : ACCEPTED_FILE_TYPES}
                    onChange={(event) =>
                      setSubmissionFiles(Array.from(event.target.files ?? []))
                    }
                  />
                  {submissionFiles.length > 0 ? (
                    <span className="task-form__hint">
                      {submissionFiles.length} file
                      {submissionFiles.length === 1 ? '' : 's'} selected
                    </span>
                  ) : null}
                  {fieldErrors.files?.[0] ?? fieldErrors['files.0']?.[0] ? (
                    <span className="task-form__error">
                      {fieldErrors.files?.[0] ?? fieldErrors['files.0']?.[0]}
                    </span>
                  ) : null}
                </label>
              ) : null}

              {submissionMethod === 'link' || submissionMethod === 'folder' ? (
                <label className="task-form__field task-form__field--grow">
                  <span>
                    {submissionMethod === 'folder'
                      ? 'Shared folder link (HTTPS)'
                      : 'HTTPS link'}
                  </span>
                  <input
                    type="url"
                    inputMode="url"
                    placeholder="https://drive.google.com/..."
                    value={submissionUrl}
                    onChange={(event) => setSubmissionUrl(event.target.value)}
                  />
                  {fieldErrors.url?.[0] ? (
                    <span className="task-form__error">{fieldErrors.url[0]}</span>
                  ) : null}
                </label>
              ) : null}

              {submissionMethod === 'written' ? (
                <label className="task-form__field task-form__field--block">
                  <span>Written response</span>
                  <textarea
                    rows={5}
                    maxLength={20000}
                    value={submissionWritten}
                    placeholder="Summarise the work you completed"
                    onChange={(event) => setSubmissionWritten(event.target.value)}
                  />
                  {fieldErrors.written_response?.[0] ? (
                    <span className="task-form__error">{fieldErrors.written_response[0]}</span>
                  ) : null}
                </label>
              ) : null}

              <label className="task-form__field task-form__field--block">
                <span>Submission notes (optional)</span>
                <textarea
                  rows={2}
                  maxLength={5000}
                  value={submissionNotes}
                  placeholder="Anything the reviewer should know"
                  onChange={(event) => setSubmissionNotes(event.target.value)}
                />
                {fieldErrors.notes?.[0] ? (
                  <span className="task-form__error">{fieldErrors.notes[0]}</span>
                ) : null}
              </label>

              <div className="task-actions task-actions--end">
                <button
                  type="button"
                  className="button button--secondary"
                  disabled={busy}
                  onClick={resetSubmissionForm}
                >
                  Cancel
                </button>
                <button type="submit" className="button" disabled={busy}>
                  Submit for review
                </button>
              </div>
            </form>
          ) : null}

          {submissionHistory.length > 0 ? (
            <div className="task-submissions">
              <h4 className="task-timeline__heading">Submission history</h4>
              {[...submissionHistory]
                .sort((a, b) => b.version - a.version)
                .map((submission) => (
                  <SubmissionCard
                    key={submission.id}
                    submission={submission}
                    downloadingId={downloadingId}
                    onDownload={(attachmentId, fileName) =>
                      void handleAttachmentDownload(attachmentId, fileName)
                    }
                  />
                ))}
            </div>
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
        tone="danger"
        title="Cancel task"
        message="Are you sure you want to cancel this task? This cannot be undone."
        confirmLabel="Cancel task"
        cancelLabel="Keep task"
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
