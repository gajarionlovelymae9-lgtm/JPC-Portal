import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { createDepartmentTask, getDepartmentEmployees } from '../../services/departmentHeadService'
import type { DepartmentEmployee } from '../../services/departmentHeadService'
import { PATHS } from '../../routes/paths'

export default function DepartmentTaskCreatePage() {
  const navigate = useNavigate()
  const [employees, setEmployees] = useState<DepartmentEmployee[]>([])
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [priority, setPriority] = useState('medium')
  const [startDate, setStartDate] = useState(new Date().toISOString().split('T')[0])
  const [deadline, setDeadline] = useState('')
  const [requirements, setRequirements] = useState('')
  const [instructions, setInstructions] = useState('')
  const [selectedEmpIds, setSelectedEmpIds] = useState<number[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getDepartmentEmployees({ status: 'active' })
      .then(res => setEmployees(res.data))
      .catch(() => setError('Could not load faculty list.'))
  }, [])

  const toggleEmployee = (id: number) => {
    setSelectedEmpIds(prev => 
      prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]
    )
  }

  const selectAll = () => {
    if (selectedEmpIds.length === employees.length) {
      setSelectedEmpIds([])
    } else {
      setSelectedEmpIds(employees.map(e => e.id))
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!title.trim()) {
      setError('Task title is required.')
      return
    }
    if (selectedEmpIds.length === 0) {
      setError('Please select at least one faculty member to assign.')
      return
    }

    setSubmitting(true)
    setError(null)

    try {
      await createDepartmentTask({
        title,
        description,
        priority,
        start_date: startDate,
        deadline: deadline || undefined,
        requirements,
        instructions,
        assigned_employee_ids: selectedEmpIds,
      })
      navigate(PATHS.departmentHeadTasks)
    } catch (err: unknown) {
      setError((err as { message?: string })?.message ?? 'Failed to create task.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-6">
      <button onClick={() => navigate(PATHS.departmentHeadTasks)} className="text-sm font-semibold text-gray-500 hover:text-gray-900 flex items-center gap-1">
        ← Back to Tasks
      </button>

      <div className="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Create & Assign Department Task</h1>
          <p className="text-sm text-gray-500 mt-1">
            Assign individual task obligations to teachers in your department. Each teacher will receive their own progress tracker.
          </p>
        </div>

        {error && <div className="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200 text-sm">{error}</div>}

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                Task Title <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={title}
                onChange={e => setTitle(e.target.value)}
                placeholder="e.g. Prepare Midterm Exam Syllabus & Activity Sheets"
                className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                Description & Deliverables
              </label>
              <textarea
                value={description}
                onChange={e => setDescription(e.target.value)}
                rows={3}
                placeholder="Explain the scope and output required..."
                className="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Priority</label>
                <select
                  value={priority}
                  onChange={e => setPriority(e.target.value)}
                  className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Start Date</label>
                <input
                  type="date"
                  value={startDate}
                  onChange={e => setStartDate(e.target.value)}
                  className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Submission Deadline</label>
                <input
                  type="date"
                  value={deadline}
                  onChange={e => setDeadline(e.target.value)}
                  className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                  Requirements (AI Validation Criteria)
                </label>
                <textarea
                  value={requirements}
                  onChange={e => setRequirements(e.target.value)}
                  rows={3}
                  placeholder="e.g. Include Table of Specifications, 50-item question bank..."
                  className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div>
                <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                  Instructions & Reference Guidelines
                </label>
                <textarea
                  value={instructions}
                  onChange={e => setInstructions(e.target.value)}
                  rows={3}
                  placeholder="e.g. Follow CHED CMO 2026 format..."
                  className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>
          </div>

          {/* Multi-Employee Assignment Checklist */}
          <div className="border border-gray-200 rounded-2xl p-5 bg-gray-50/50 space-y-3">
            <div className="flex justify-between items-center">
              <div>
                <h3 className="font-bold text-gray-900 text-sm">Assign Faculty Members <span className="text-red-500">*</span></h3>
                <p className="text-xs text-gray-500">Each selected teacher receives their own separate progress tracking</p>
              </div>
              <button
                type="button"
                onClick={selectAll}
                className="text-xs font-semibold text-blue-600 hover:underline"
              >
                {selectedEmpIds.length === employees.length ? 'Deselect All' : 'Select All'}
              </button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-56 overflow-y-auto pr-1">
              {employees.map(emp => {
                const checked = selectedEmpIds.includes(emp.id)
                return (
                  <label
                    key={emp.id}
                    className={`flex items-center gap-3 p-3 rounded-xl border transition-all cursor-pointer ${checked ? 'bg-blue-50/80 border-blue-300 text-blue-900' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'}`}
                  >
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={() => toggleEmployee(emp.id)}
                      className="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 border-gray-300"
                    />
                    <div className="min-w-0">
                      <p className="font-semibold text-xs truncate">{emp.full_name}</p>
                      <p className="text-[11px] text-gray-400 font-mono">{emp.employee_number} · {emp.position}</p>
                    </div>
                  </label>
                )
              })}
            </div>
            <p className="text-xs text-gray-500 font-medium">Selected: {selectedEmpIds.length} faculty member(s)</p>
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <button
              type="button"
              onClick={() => navigate(PATHS.departmentHeadTasks)}
              className="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-xl text-sm font-semibold hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-sm disabled:opacity-50 transition-all"
            >
              {submitting ? 'Creating...' : 'Create & Dispatch Task'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}