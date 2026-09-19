import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { getDepartmentTasks } from '../../services/departmentHeadService'
import type { DepartmentTask } from '../../services/departmentHeadService'
import { PATHS, departmentHeadTaskDetailPath } from '../../routes/paths'

export default function DepartmentTasksPage() {
  const [tasks, setTasks] = useState<DepartmentTask[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [priorityFilter, setPriorityFilter] = useState('all')
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    getDepartmentTasks({ search, status: statusFilter, priority: priorityFilter })
      .then(res => setTasks(res.data))
      .catch(() => setError('Failed to load department tasks.'))
      .finally(() => setLoading(false))
  }, [search, statusFilter, priorityFilter])

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Department Tasks</h1>
          <p className="text-sm text-gray-500 mt-0.5">Manage deliverables and track individual teacher assignments</p>
        </div>
        <Link
          to={PATHS.departmentHeadTaskCreate}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl text-sm transition-all shadow-sm"
        >
          + Create & Assign Task
        </Link>
      </div>

      {/* Filter Bar */}
      <div className="flex flex-col sm:flex-row gap-3 bg-white p-4 rounded-2xl border border-gray-200 shadow-sm">
        <input
          type="text"
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Search tasks..."
          className="flex-1 border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <select
          value={statusFilter}
          onChange={e => setStatusFilter(e.target.value)}
          className="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
        >
          <option value="all">All Statuses</option>
          <option value="assigned">Assigned</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select
          value={priorityFilter}
          onChange={e => setPriorityFilter(e.target.value)}
          className="border border-gray-300 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
        >
          <option value="all">All Priorities</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>

      {error && <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200">{error}</div>}

      {loading ? (
        <div className="space-y-3">
          {[...Array(5)].map((_, i) => (
            <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : tasks.length === 0 ? (
        <div className="bg-white border border-gray-200 rounded-2xl p-12 text-center text-gray-500">
          <span className="text-4xl block mb-2">📋</span>
          <p className="font-semibold">No tasks found.</p>
          <p className="text-xs text-gray-400 mt-1">Create a new task to assign work to faculty members.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4">
          {tasks.map(t => (
            <div
              key={t.id}
              className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col md:flex-row justify-between gap-4 items-start md:items-center"
            >
              <div className="space-y-1.5 flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-mono text-xs text-gray-400 font-bold">{t.task_code}</span>
                  <span className={`text-xs px-2 py-0.5 rounded-md font-semibold ${t.priority === 'urgent' ? 'bg-red-100 text-red-700' : (t.priority === 'high' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-700')}`}>
                    {t.priority.toUpperCase()}
                  </span>
                  {t.is_overdue && (
                    <span className="text-xs px-2 py-0.5 rounded-md font-bold bg-red-600 text-white">
                      OVERDUE
                    </span>
                  )}
                </div>
                <h3 className="font-bold text-gray-900 text-base">{t.title}</h3>
                <p className="text-xs text-gray-500 line-clamp-1">{t.description ?? 'No description provided.'}</p>
                <div className="flex items-center gap-4 text-xs text-gray-400 pt-1">
                  <span>Deadline: <b className="text-gray-700">{t.deadline ?? 'None'}</b></span>
                  <span>Assigned: <b className="text-gray-700">{t.total_assigned} Faculty Member(s)</b></span>
                </div>
              </div>

              {/* Individual Assignee Progress Chips */}
              <div className="w-full md:w-auto flex flex-col md:items-end gap-2 shrink-0 border-t md:border-t-0 pt-3 md:pt-0 border-gray-100">
                <div className="flex items-center gap-2">
                  <div className="w-28 bg-gray-100 rounded-full h-2">
                    <div className="bg-blue-600 h-2 rounded-full" style={{ width: `${t.average_progress}%` }} />
                  </div>
                  <span className="text-xs font-bold text-gray-700">{t.average_progress}% Avg</span>
                </div>
                <div className="flex gap-1.5 flex-wrap">
                  {t.assignments.slice(0, 3).map(a => (
                    <span key={a.id} className="text-xs bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-md text-gray-600 font-medium">
                      {a.employee_name}: <b>{a.progress}%</b>
                    </span>
                  ))}
                  {t.assignments.length > 3 && (
                    <span className="text-xs text-gray-400 font-medium">+{t.assignments.length - 3} more</span>
                  )}
                </div>
                <button
                  onClick={() => navigate(departmentHeadTaskDetailPath(t.id))}
                  className="px-3.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl text-xs font-bold mt-1"
                >
                  Manage & Details →
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}