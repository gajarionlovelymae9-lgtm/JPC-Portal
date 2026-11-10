import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getAdminMonitoringOptions, getAdminTaskMonitoring } from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import { PATHS } from '../../routes/paths'
import {
  AdminPage,
  Badge,
  Button,
  EmptyState,
  ErrorPanel,
  ExportButton,
  Field,
  FilterBar,
  LoadingPanel,
  Pagination,
  Panel,
  Select,
  StatCard,
  StatusBadge,
  Table,
  Td,
  TextInput,
  Th,
  num,
  pct,
  shortDate,
} from '../../components/admin/AdminUi'

/**
 * Admin section 9 - Task monitoring.
 *
 * `days_overdue` is only non-zero for assignments that are still open past
 * their due date, so it must not be added to a count of completed-late work.
 */
export default function AdminTaskMonitoringPage() {
  const navigate = useNavigate()
  const [departmentId, setDepartmentId] = useState('')
  const [employeeId, setEmployeeId] = useState('')
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [overdueOnly, setOverdueOnly] = useState(false)
  const [page, setPage] = useState(1)

  const options = useAdminResource(() => getAdminMonitoringOptions())
  const resource = useAdminResource(
    () =>
      getAdminTaskMonitoring({
        department_id: departmentId,
        employee_id: employeeId,
        status,
        search,
        overdue: overdueOnly ? 1 : '',
        page,
        per_page: 15,
      }),
    [departmentId, employeeId, status, search, overdueOnly, page],
  )

  const data = resource.data
  const summary = data?.summary ?? {}

  const apply = (setter: (value: string) => void) => (value: string) => {
    setter(value)
    setPage(1)
  }

  return (
    <AdminPage
      title="Task Monitoring"
      subtitle="Institution-wide view of every task assignment"
      actions={
        <>
          <Button onClick={() => navigate(PATHS.adminTaskCreate)}>+ Create Task</Button>
          <ExportButton
            exportKey="taskCompletion"
            filters={{ department_id: departmentId, employee_id: employeeId, status, search }}
          />
        </>
      }
    >
      <FilterBar>
        <Field label="Department">
          <Select
            value={departmentId}
            onChange={apply(setDepartmentId)}
            options={(options.data?.departments ?? []).map((d) => ({ value: d.id, label: d.name }))}
            placeholder="All departments"
          />
        </Field>
        <Field label="Employee">
          <Select
            value={employeeId}
            onChange={apply(setEmployeeId)}
            options={(options.data?.employees ?? []).map((e) => ({ value: e.id, label: e.name }))}
            placeholder="All employees"
          />
        </Field>
        <Field label="Status">
          <Select
            value={status}
            onChange={apply(setStatus)}
            options={options.data?.task_statuses ?? []}
            placeholder="All statuses"
          />
        </Field>
        <Field label="Search" className="flex-1 min-w-[180px]">
          <TextInput value={search} onChange={apply(setSearch)} placeholder="Task name or code" />
        </Field>
        <ButtonToggle
          active={overdueOnly}
          onChange={() => {
            setOverdueOnly((value) => !value)
            setPage(1)
          }}
        >
          Overdue only
        </ButtonToggle>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading task monitoring" />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load task monitoring.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <StatCard label="Total tasks" value={num(summary.total ?? data.meta.total ?? 0)} />
            <StatCard label="Completed" value={num(summary.completed ?? 0)} tone="ok" />
            <StatCard label="In progress" value={num(summary.in_progress ?? 0)} tone="info" />
            <StatCard label="Not started" value={num(summary.not_started ?? 0)} />
            <StatCard
              label="Overdue"
              value={num(summary.overdue ?? 0)}
              tone={(summary.overdue ?? 0) > 0 ? 'danger' : 'ok'}
            />
          </div>

          <Panel title="Assignment register" description={`${data.meta.total} assignments`}>
            {data.items.length === 0 ? (
              <EmptyState title="No assignments match these filters" message="Try clearing a filter to widen the search." />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Task</Th>
                      <Th>Assigned to</Th>
                      <Th>Department</Th>
                      <Th>Priority</Th>
                      <Th className="text-right">Progress</Th>
                      <Th>Status</Th>
                      <Th>Due</Th>
                      <Th className="text-right">Days overdue</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((task) => (
                      <tr key={task.assignment_id} className="border-b border-slate-100 last:border-0">
                        <Td>
                          <p className="font-semibold text-slate-800">{task.task_name}</p>
                          <p className="text-[11px] font-mono text-slate-400">{task.task_code}</p>
                        </Td>
                        <Td>
                          <p className="text-slate-700">{task.assigned_to}</p>
                          <p className="text-[11px] text-slate-400">{task.employee_number}</p>
                        </Td>
                        <Td className="text-slate-500">{task.department}</Td>
                        <Td>
                          <Badge
                            tone={
                              task.priority === 'high' || task.priority === 'urgent'
                                ? 'danger'
                                : task.priority === 'medium'
                                  ? 'warn'
                                  : 'neutral'
                            }
                          >
                            {task.priority}
                          </Badge>
                        </Td>
                        <Td className="text-right tabular-nums font-bold">{pct(task.progress)}</Td>
                        <Td>
                          <StatusBadge status={task.status} />
                          {task.status !== task.raw_status ? (
                            <p className="text-[10px] text-slate-400 mt-1">raw: {task.raw_status}</p>
                          ) : null}
                        </Td>
                        <Td className="text-slate-500 text-xs">{shortDate(task.due_date)}</Td>
                        <Td className="text-right tabular-nums">
                          {task.days_overdue > 0 ? (
                            <Badge tone="danger">{task.days_overdue}d</Badge>
                          ) : (
                            <span className="text-slate-400">—</span>
                          )}
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
    </AdminPage>
  )
}

function ButtonToggle({
  active,
  onChange,
  children,
}: {
  active: boolean
  onChange: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onChange}
      className={`px-4 py-2 rounded-lg text-sm font-bold border transition ${
        active
          ? 'bg-jpc-600 text-white border-jpc-600'
          : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
      }`}
    >
      {children}
    </button>
  )
}
