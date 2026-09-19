import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { getDepartmentTask, cancelDepartmentTask } from '../../services/departmentHeadService'
import type { DepartmentTaskDetail } from '../../services/departmentHeadService'
import { PATHS } from '../../routes/paths'

export default function DepartmentTaskDetailPage() {
  const { taskId } = useParams()
  const navigate = useNavigate()
  const [task, setTask] = useState<DepartmentTaskDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [cancelModalOpen, setCancelModalOpen] = useState(false)
  const [cancelReason, setCancelReason] = useState('')
  const [cancelling, setCancelling] = useState(false)

  useEffect(() => {
    if (!taskId) return
    setLoading(true)
    getDepartmentTask(taskId)
      .then(res => setTask(res.data))
      .catch(() => setError('Failed to load task details.'))
      .finally(() => setLoading(false))
  }, [taskId])

  const handleCancelTask = async () => {
    if (!taskId || !cancelReason.trim()) return
    setCancelling(true)
    try {
      await cancelDepartmentTask(taskId, cancelReason)
      setCancelModalOpen(false)
      // Reload
      const updated = await getDepartmentTask(taskId)
      setTask(updated.data)
    } catch {
      setError('Failed to cancel task.')
    } finally {
      setCancelling(false)
    }
  }

  if (loading) {
    return <div className="p-6 space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />)}</div>
  }

  if (error || !task) {
    return <div className="p-6 bg-red-50 text-red-700 rounded-xl border border-red-200">{error ?? 'Task not found.'}</div>
  }

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      <button onClick={() => navigate(PATHS.departmentHeadTasks)} className="text-sm font-semibold text-gray-500 hover:text-gray-900 flex items-center gap-1">
        ← Back to Tasks
      </button>

      {/* Task Header */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
          <div className="flex items-center gap-2.5">
            <span className="font-mono text-xs text-gray-500 font-bold bg-gray-100 px-2.5 py-1 rounded-lg">{task.task_code}</span>
            <span className={`text-xs px-2.5 py-1 rounded-lg font-bold ${task.priority === 'urgent' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`}>
              {task.priority.toUpperCase()} PRIORITY
            </span>
            <span className={`text-xs px-2.5 py-1 rounded-lg font-semibold ${task.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : (task.status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700')}`}>
              {task.status.toUpperCase()}
            </span>
          </div>
          {task.status !== 'cancelled' && task.status !== 'completed' && (
            <button
              onClick={() => setCancelModalOpen(true)}
              className="px-3 py-1.5 border border-red-200 text-red-600 hover:bg-red-50 rounded-xl text-xs font-semibold"
            >
              Cancel Task
            </button>
          )}
        </div>

        <h1 className="text-2xl font-bold text-gray-900">{task.title}</h1>
        <p className="text-sm text-gray-600 leading-relaxed">{task.description ?? 'No description provided.'}</p>

        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-3 border-t border-gray-100 text-xs">
          <div><span className="text-gray-400 block">Department</span><span className="font-semibold text-gray-800">{task.department_name}</span></div>
          <div><span className="text-gray-400 block">Assigned By</span><span className="font-semibold text-gray-800">{task.created_by_name}</span></div>
          <div><span className="text-gray-400 block">Start Date</span><span className="font-semibold text-gray-800">{task.start_date ?? '—'}</span></div>
          <div><span className="text-gray-400 block">Deadline</span><span className="font-semibold text-red-600">{task.deadline ?? '—'}</span></div>
        </div>
      </div>

      {/* Individual Assignments Tracker */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <div>
          <h2 className="text-lg font-bold text-gray-900">Individual Faculty Assignments & Progress</h2>
          <p className="text-xs text-gray-500 mt-0.5">
            Every faculty member tracks and completes this deliverable independently.
          </p>
        </div>

        <div className="divide-y divide-gray-100">
          {task.assignments.map(a => (
            <div key={a.id} className="py-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
              <div>
                <h4 className="font-bold text-gray-900 text-sm">{a.employee_name}</h4>
                <p className="text-xs text-gray-400 font-mono">{a.employee_number} · {a.position}</p>
                {a.notes && <p className="text-xs text-gray-600 mt-1 italic">"{a.notes}"</p>}
              </div>
              <div className="flex items-center gap-4 w-full sm:w-auto justify-between sm:justify-end">
                <div className="flex items-center gap-2">
                  <div className="w-28 bg-gray-100 rounded-full h-2.5">
                    <div
                      className={`h-2.5 rounded-full ${a.progress === 100 ? 'bg-emerald-500' : 'bg-blue-600'}`}
                      style={{ width: `${a.progress}%` }}
                    />
                  </div>
                  <span className="text-xs font-bold text-gray-700 w-10 text-right">{a.progress}%</span>
                </div>
                <span className={`text-xs px-2.5 py-1 rounded-full font-bold ${a.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-50 text-blue-700'}`}>
                  {a.status.toUpperCase()}
                </span>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Requirements & Instructions */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
          <h3 className="font-bold text-gray-900 text-sm mb-2 flex items-center gap-1.5">
            <span>✅</span> Task Requirements
          </h3>
          <p className="text-xs text-gray-600 whitespace-pre-wrap leading-relaxed">
            {task.requirements ?? 'Standard departmental submission format required.'}
          </p>
        </div>
        <div className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
          <h3 className="font-bold text-gray-900 text-sm mb-2 flex items-center gap-1.5">
            <span>📚</span> Instructions & Guidelines
          </h3>
          <p className="text-xs text-gray-600 whitespace-pre-wrap leading-relaxed">
            {task.instructions ?? 'Adhere to curriculum deadlines.'}
          </p>
        </div>
      </div>

      {/* Cancel Modal */}
      {cancelModalOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl p-6 max-w-md w-full shadow-xl space-y-4">
            <h3 className="text-lg font-bold text-gray-900">Cancel Task</h3>
            <p className="text-xs text-gray-500">
              Provide a reason for task cancellation. This reason will be recorded in the audit trail.
            </p>
            <textarea
              value={cancelReason}
              onChange={e => setCancelReason(e.target.value)}
              rows={3}
              placeholder="Reason for cancellation..."
              className="w-full border border-gray-300 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <div className="flex justify-end gap-2">
              <button
                onClick={() => setCancelModalOpen(false)}
                className="px-4 py-2 border border-gray-300 rounded-xl text-xs font-semibold text-gray-700"
              >
                Back
              </button>
              <button
                onClick={handleCancelTask}
                disabled={!cancelReason.trim() || cancelling}
                className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold disabled:opacity-50"
              >
                {cancelling ? 'Cancelling...' : 'Confirm Cancellation'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}