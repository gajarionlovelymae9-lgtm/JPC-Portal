import { useState } from 'react'
import {
  getAdminEmployeeProgress,
  getAdminEmployeeProgressDetail,
  getAdminEmployeeProgressOptions,
} from '../../services/adminDashboardService'
import type { EmployeeProgressDetail } from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
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
  SampleDataNotice,
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
import { RankingBars } from '../../components/admin/AdminCharts'

/**
 * Admin section 8 - Employee progress.
 *
 * Every column is a real aggregate over task assignments, reports and
 * employee performance records. `kpi_pct` and `ai_score` are null when the
 * employee has no data, which renders as "—" rather than a fabricated zero.
 */
export default function AdminEmployeeProgressPage() {
  const [departmentId, setDepartmentId] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [selectedId, setSelectedId] = useState<number | null>(null)

  const options = useAdminResource(() => getAdminEmployeeProgressOptions())
  const resource = useAdminResource(
    () => getAdminEmployeeProgress({ department_id: departmentId, search, page, per_page: 15 }),
    [departmentId, search, page],
  )
  const detail = useAdminResource(
    () => (selectedId ? getAdminEmployeeProgressDetail(selectedId) : Promise.resolve(null as never)),
    [selectedId],
  )

  const data = resource.data
  const detailData: EmployeeProgressDetail | null = selectedId ? (detail.data as EmployeeProgressDetail | null) : null

  const applyDepartment = (value: string) => {
    setDepartmentId(value)
    setPage(1)
  }
  const applySearch = (value: string) => {
    setSearch(value)
    setPage(1)
  }

  return (
    <AdminPage
      title="Employee Progress"
      subtitle="Per-employee task, report and KPI progress across the institution"
      actions={
        <div className="flex items-center gap-3">
          <ExportButton exportKey="employeePerformance" filters={{ department_id: departmentId, search }} />
          {detailData ? (
            <Button variant="secondary" onClick={() => setSelectedId(null)}>
              Close detail
            </Button>
          ) : null}
        </div>
      }
    >
      <FilterBar>
        <Field label="Department">
          <Select
            value={departmentId}
            onChange={applyDepartment}
            options={(options.data?.departments ?? []).map((d) => ({ value: d.id, label: d.name }))}
            placeholder="All departments"
          />
        </Field>
        <Field label="Search" className="flex-1 min-w-[200px]">
          <TextInput value={search} onChange={applySearch} placeholder="Name or employee number" />
        </Field>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading employee progress" />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load employee progress.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-6 gap-4">
            <StatCard label="Employees" value={num(data.summary.total_employees)} />
            <StatCard label="Assigned" value={num(data.summary.assigned_tasks)} hint="Task assignments" />
            <StatCard label="Completed" value={num(data.summary.completed_tasks)} tone="ok" />
            <StatCard label="Pending" value={num(data.summary.pending_tasks)} />
            <StatCard
              label="Late"
              value={num(data.summary.late_tasks)}
              tone={data.summary.late_tasks > 0 ? 'danger' : 'ok'}
              hint={`${pct(data.summary.late_rate)} of assigned`}
            />
            <StatCard label="Completion" value={pct(data.summary.completion_rate)} tone="info" />
          </div>

          <div className="grid lg:grid-cols-2 gap-6">
            <Panel title="Lowest overall progress" description="Employees ranked by derived progress">
              <RankingBars
                suffix="%"
                rows={[...data.items]
                  .sort((a, b) => (a.overall_progress ?? -1) - (b.overall_progress ?? -1))
                  .slice(0, 8)
                  .map((row) => ({ key: row.employee_id, label: row.name, value: row.overall_progress }))}
                emptyMessage="No employees with recorded progress."
              />
            </Panel>
            <Panel title="Health distribution" description="Derived from completion and lateness">
              <RankingBars
                suffix=" employees"
                rows={['completed', 'on_track', 'needs_attention', 'at_risk'].map((status) => ({
                  key: status,
                  label: status.replace(/_/g, ' '),
                  value: data.items.filter((row) => row.status === status).length,
                }))}
              />
            </Panel>
          </div>

          {selectedId && detailData ? (
            <EmployeeDetail
              detail={detailData}
              loading={detail.loading}
              onClose={() => setSelectedId(null)}
            />
          ) : null}

          <Panel title="Employee progress register" description={`${data.meta.total} employees`}>
            {data.items.length === 0 ? (
              <EmptyState title="No employees match these filters" message="Try clearing the search or department filter." />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Employee</Th>
                      <Th>Department</Th>
                      <Th className="text-right">Assigned</Th>
                      <Th className="text-right">Completed</Th>
                      <Th className="text-right">Pending</Th>
                      <Th className="text-right">Late</Th>
                      <Th className="text-right">KPI</Th>
                      <Th className="text-right">AI score</Th>
                      <Th className="text-right">Progress</Th>
                      <Th>Status</Th>
                      <Th />
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((row) => (
                      <tr key={row.employee_id} className="border-b border-slate-100 last:border-0">
                        <Td>
                          <p className="font-semibold text-slate-800">{row.name}</p>
                          <p className="text-[11px] text-slate-400">{row.employee_number}</p>
                        </Td>
                        <Td className="text-slate-500">{row.department ?? '—'}</Td>
                        <Td className="text-right tabular-nums">{num(row.assigned_tasks)}</Td>
                        <Td className="text-right tabular-nums">{num(row.completed_tasks)}</Td>
                        <Td className="text-right tabular-nums">{num(row.pending_tasks)}</Td>
                        <Td className="text-right tabular-nums">
                          {row.late_tasks > 0 ? (
                            <Badge tone="danger">{num(row.late_tasks)}</Badge>
                          ) : (
                            <span className="text-slate-400">0</span>
                          )}
                        </Td>
                        <Td className="text-right tabular-nums">{pct(row.kpi_pct)}</Td>
                        <Td className="text-right tabular-nums">{pct(row.ai_score)}</Td>
                        <Td className="text-right tabular-nums font-bold text-jpc-600">{pct(row.overall_progress)}</Td>
                        <Td>
                          <StatusBadge status={row.status} label={row.status_label} />
                        </Td>
                        <Td>
                          <Button variant="ghost" onClick={() => setSelectedId(row.employee_id)}>
                            View
                          </Button>
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                {data.items.some((row) => row.ai_score !== null) ? (
                  <SampleDataNotice>
                    AI scores average the seeded <strong>jpc.sample</strong> validation results for each employee&rsquo;s
                    reports. They are sample values, not live provider output.
                  </SampleDataNotice>
                ) : null}
                <Pagination meta={data.meta} onPageChange={setPage} />
              </>
            )}
          </Panel>
        </div>
      )}
    </AdminPage>
  )
}

function EmployeeDetail({
  detail,
  loading,
  onClose,
}: {
  detail: EmployeeProgressDetail
  loading: boolean
  onClose: () => void
}) {
  return (
    <Panel
      title={`${detail.name} · detail`}
      description={[detail.employee_number, detail.department, detail.position].filter(Boolean).join(' · ')}
      actions={
        <Button variant="secondary" onClick={onClose} aria-label="Close employee detail">
          × Close
        </Button>
      }
    >
      {loading ? (
        <p className="text-sm text-slate-400">Loading detail…</p>
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <StatCard label="Overall progress" value={pct(detail.overall_progress)} tone="info" />
            <StatCard label="KPI achievement" value={pct(detail.kpi_pct)} />
            <StatCard label="AI score" value={pct(detail.ai_score)} />
            <StatCard
              label="Reports"
              value={num(detail.reports_total)}
              hint={`${detail.reports_approved} approved · ${detail.reports_late} late`}
            />
          </div>

          <div>
            <h3 className="text-sm font-bold text-slate-800 mb-2">Task assignments</h3>
            {detail.tasks.length === 0 ? (
              <EmptyState title="No task assignments" />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Task</Th>
                    <Th>Code</Th>
                    <Th>Priority</Th>
                    <Th>Status</Th>
                    <Th className="text-right">Progress</Th>
                    <Th>Due</Th>
                  </tr>
                </thead>
                <tbody>
                  {detail.tasks.map((task) => (
                    <tr key={task.assignment_id} className="border-b border-slate-100 last:border-0">
                      <Td className="font-medium text-slate-800">{task.task_name}</Td>
                      <Td className="font-mono text-xs">{task.task_code}</Td>
                      <Td className="text-slate-500">{task.priority}</Td>
                      <Td>
                        <StatusBadge status={task.status} />
                      </Td>
                      <Td className="text-right tabular-nums">{pct(task.progress)}</Td>
                      <Td className="text-slate-500 text-xs">{shortDate(task.due_date)}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </div>

          <div>
            <h3 className="text-sm font-bold text-slate-800 mb-2">Reports</h3>
            {detail.reports.length === 0 ? (
              <EmptyState title="No reports submitted" />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Report</Th>
                    <Th>Status</Th>
                    <Th>Deadline</Th>
                    <Th>Submitted</Th>
                    <Th className="text-right">AI score</Th>
                  </tr>
                </thead>
                <tbody>
                  {detail.reports.map((report) => (
                    <tr key={report.id} className="border-b border-slate-100 last:border-0">
                      <Td className="font-medium text-slate-800">{report.title}</Td>
                      <Td>
                        <StatusBadge status={report.status} />
                        {report.is_late ? <Badge tone="danger">Late</Badge> : null}
                      </Td>
                      <Td className="text-slate-500 text-xs">{shortDate(report.deadline)}</Td>
                      <Td className="text-slate-500 text-xs">{shortDate(report.submitted_at)}</Td>
                      <Td className="text-right tabular-nums">{pct(report.ai_score)}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </div>

          <div>
            <h3 className="text-sm font-bold text-slate-800 mb-2">Monthly achievements</h3>
            {detail.monthly_achievements.length === 0 ? (
              <EmptyState title="No monthly achievement records" />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Period</Th>
                    <Th className="text-right">Completion</Th>
                    <Th className="text-right">On time</Th>
                    <Th className="text-right">Approved reports</Th>
                    <Th className="text-right">Completed tasks</Th>
                    <Th className="text-right">Score</Th>
                    <Th>Employee of the month</Th>
                  </tr>
                </thead>
                <tbody>
                  {detail.monthly_achievements.map((achievement) => (
                    <tr key={achievement.id} className="border-b border-slate-100 last:border-0">
                      <Td className="font-medium text-slate-800">
                        {achievement.year}-{String(achievement.month).padStart(2, '0')}
                      </Td>
                      <Td className="text-right tabular-nums">{pct(achievement.completion_rate)}</Td>
                      <Td className="text-right tabular-nums">{pct(achievement.on_time_rate)}</Td>
                      <Td className="text-right tabular-nums">{num(achievement.approved_reports)}</Td>
                      <Td className="text-right tabular-nums">{num(achievement.completed_tasks)}</Td>
                      <Td className="text-right tabular-nums font-bold">{num(achievement.overall_score)}</Td>
                      <Td>
                        {achievement.is_employee_of_month ? (
                          <Badge tone="purple">Employee of the month</Badge>
                        ) : (
                          <span className="text-slate-400 text-xs">—</span>
                        )}
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </div>
        </div>
      )}
    </Panel>
  )
}
