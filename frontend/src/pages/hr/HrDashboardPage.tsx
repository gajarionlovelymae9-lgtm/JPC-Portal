import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { useAuth } from '../../auth/useAuth'
import {
  getHrDashboard,
  getHrEmployees,
  getHrOptions,
  getHrReports,
  getHrTaskMonitoring,
  type HrEmployeeRow,
  type HrReportRow,
  type HrTaskRow,
} from '../../services/hrService'
import { useAdminResource } from '../../hooks/useAdminResource'
import { useActivityLogs } from '../../hooks/useActivityLogs'
import { useHealthStatus } from '../../hooks/useHealthStatus'
import { CHART_COLOURS, DonutChart, GroupedBarChart, RankingBars, TrendChart } from '../../components/admin/AdminCharts'
import {
  AdminPage,
  Badge,
  Button,
  EmptyState,
  ErrorPanel,
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
  dateTime,
  num,
  pct,
  shortDate,
} from '../../components/admin/AdminUi'

/**
 * HR Portal - the institution-wide monitoring dashboard.
 *
 * Every number here comes from `/api/v1/hr/*`; nothing is computed in the
 * browser and nothing is invented. Two display rules follow from that contract:
 *
 *  - a rate is rendered as an em dash when the API sends `null`, because
 *    `null` means "there is nothing to divide by", which is not the same as 0%;
 *  - the four headline cards and the completion panel are the only summary
 *    figures on the page. The tables below them carry the detail, so a number
 *    is never printed twice in two different cards.
 *
 * The page is intentionally one scroll rather than a tabbed set of routes: HR
 * needs the whole picture at once to spot a late backlog, and every panel here
 * is a read-only view of a different table.
 */
export default function HrDashboardPage() {
  const { user } = useAuth()

  /* ---------------------------------------------------------------- */
  /* Filter state - one set per panel so filtering one table never      */
  /* silently re-filters another.                                       */
  /* ---------------------------------------------------------------- */
  const [employeeFilters, setEmployeeFilters] = useState({
    department_id: '',
    status: '',
    search: '',
    sort: '',
    page: 1,
  })
  const [taskFilters, setTaskFilters] = useState({ status: '', overdue: '', page: 1 })
  const [reportFilters, setReportFilters] = useState({ status: '', search: '', late: '', page: 1 })

  const [refreshedAt, setRefreshedAt] = useState<Date | null>(null)

  // The greeting shows the time on screen, so it is kept current rather than
  // frozen at the moment the page was opened.
  const [now, setNow] = useState(() => new Date())
  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), 60_000)
    return () => window.clearInterval(timer)
  }, [])

  const options = useAdminResource(() => getHrOptions())
  const dashboard = useAdminResource(() => getHrDashboard())
  const employees = useAdminResource(
    () => getHrEmployees({ ...employeeFilters, per_page: 10 }),
    [employeeFilters],
  )
  const tasks = useAdminResource(
    () => getHrTaskMonitoring({ ...taskFilters, per_page: 10 }),
    [taskFilters],
  )
  const reports = useAdminResource(
    () => getHrReports({ ...reportFilters, per_page: 10 }),
    [reportFilters],
  )
  // HR holds activity_logs.view, so the recent-activity panel is real data too.
  const activity = useActivityLogs({ per_page: 6 })
  const { state: health, retry: retryHealth } = useHealthStatus()

  const kpis = dashboard.data

  const greetingName = user?.name?.trim() ? user.name.trim() : 'JPC HR'
  const nowLabel = now.toLocaleString(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })

  // Charts are derived from the single KPI payload, not from extra requests.
  const trends = useMemo(() => {
    const reports_ = kpis?.trends.reports ?? []
    const taskPoints = kpis?.trends.tasks ?? []
    return {
      labels: reports_.map((point) => point.label),
      reportPoints: reports_,
      taskPoints,
    }
  }, [kpis])

  const reportStatusBreakdown = useMemo(() => {
    if (!kpis) return []
    return [
      { key: 'approved', label: 'Approved', value: kpis.reports.approved },
      { key: 'pending', label: 'In review', value: kpis.reports.pending },
      { key: 'revision_required', label: 'Revision required', value: kpis.reports.revision_required },
      { key: 'rejected', label: 'Rejected', value: kpis.reports.rejected },
      { key: 'late', label: 'Late', value: kpis.reports.late },
    ]
  }, [kpis])

  const taskStatusBreakdown = useMemo(() => {
    if (!kpis) return []
    return [
      { key: 'completed', label: 'Completed', value: kpis.tasks.completed },
      { key: 'pending', label: 'Pending', value: kpis.tasks.pending },
      { key: 'overdue', label: 'Overdue', value: kpis.tasks.overdue },
    ]
  }, [kpis])

  const employeeRanking = useMemo(() => {
    const rows = employees.data?.items ?? []
    return rows
      .filter((row) => row.assigned_tasks > 0)
      .map((row) => ({ key: row.employee_id, label: row.name, value: row.completion_rate }))
  }, [employees.data])

  const lateReports = useMemo(() => {
    const rows = reports.data?.items ?? []
    return rows.filter((row) => row.is_late)
  }, [reports.data])

  const applyFilter = <K extends keyof typeof employeeFilters>(
    key: K,
    value: (typeof employeeFilters)[K],
  ) => {
    setEmployeeFilters((prev) => ({ ...prev, [key]: value, page: 1 }))
  }

  const applyTaskFilter = (key: keyof typeof taskFilters, value: string) => {
    setTaskFilters((prev) => ({ ...prev, [key]: value, page: 1 }))
  }

  const applyReportFilter = (key: keyof typeof reportFilters, value: string) => {
    setReportFilters((prev) => ({ ...prev, [key]: value, page: 1 }))
  }

  const retryAll = () => {
    options.reload()
    dashboard.reload()
    employees.reload()
    tasks.reload()
    reports.reload()
    activity.refresh()
    retryHealth()
    setRefreshedAt(new Date())
  }

  if (dashboard.loading) {
    return (
      <AdminPage title="HR Dashboard" subtitle="Institution-wide employee, task and report monitoring">
        <LoadingPanel label="Loading HR dashboard" />
      </AdminPage>
    )
  }

  if (dashboard.error || !kpis) {
    return (
      <AdminPage title="HR Dashboard" subtitle="Institution-wide employee, task and report monitoring">
        <ErrorPanel message={dashboard.error ?? 'Unable to load the HR dashboard.'} onRetry={retryAll} />
      </AdminPage>
    )
  }

  const taskMonth = kpis.tasks.month
  const completionRate = taskMonth.completion_rate
  const completionPercent = completionRate === null ? 0 : Math.max(0, Math.min(100, completionRate))

  return (
    <AdminPage
      title="HR Dashboard"
      subtitle="Institution-wide employee, task and report monitoring"
      actions={
        <div className="flex flex-wrap items-center gap-2">
          {refreshedAt ? (
            <span className="text-[11px] text-slate-400">Refreshed {refreshedAt.toLocaleTimeString()}</span>
          ) : null}
          <Button variant="secondary" onClick={retryAll}>
            Refresh
          </Button>
        </div>
      }
    >
      <div className="space-y-6">
        {/* ---------------------------------------------------------------- */}
        {/* 1. Welcome                                                        */}
        {/* ---------------------------------------------------------------- */}
        <section
          aria-label="Welcome"
          className="rounded-2xl bg-gradient-to-r from-jpc-600 to-jpc-700 px-5 py-6 sm:px-6 shadow-jpc"
        >
          <h2 className="text-xl sm:text-2xl font-black tracking-tight text-white">
            Good day, {greetingName}!
          </h2>
          <p className="text-sm text-white/80 mt-1">Here&apos;s what&apos;s happening with your team today.</p>
          <p className="text-[11px] text-white/70 mt-3">{nowLabel}</p>
        </section>

        {/* ---------------------------------------------------------------- */}
        {/* 2. The four headline cards                                        */}
        {/* ---------------------------------------------------------------- */}
        <section aria-label="Key performance indicators">
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <StatCard
              label="Total Employees"
              value={num(kpis.employees.total)}
              hint={`${num(kpis.employees.active)} active`}
              icon={
                <span className="text-blue-600">
                  <KpiIcon>
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                  </KpiIcon>
                </span>
              }
            />
            <StatCard
              label="Reports Submitted"
              value={num(kpis.reports.submitted_this_month)}
              hint={`In ${kpis.reports.month.label}`}
              tone="ok"
              icon={
                <span className="text-emerald-600">
                  <KpiIcon>
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                  </KpiIcon>
                </span>
              }
            />
            <StatCard
              label="Pending Reports"
              value={num(kpis.reports.awaiting_action)}
              hint={`${num(kpis.reports.awaiting_submission)} still to be submitted`}
              tone="warn"
              icon={
                <span className="text-amber-600">
                  <KpiIcon>
                    <circle cx="12" cy="12" r="9" />
                    <polyline points="12 7 12 12 15.5 14" />
                  </KpiIcon>
                </span>
              }
            />
            <StatCard
              label="Late Reports"
              value={num(kpis.reports.late)}
              hint={`${num(kpis.reports.late_outstanding)} not submitted yet`}
              tone={kpis.reports.late > 0 ? 'danger' : 'ok'}
              icon={
                <span className="text-red-600">
                  <KpiIcon>
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                  </KpiIcon>
                </span>
              }
            />
          </div>
        </section>

        {/* ---------------------------------------------------------------- */}
        {/* 3. Task completion rate (the one wide card)                        */}
        {/* ---------------------------------------------------------------- */}
        <Panel
          title="Task Completion Rate"
          description={`Assigned work completed in ${taskMonth.label}.`}
          actions={<StatusBadge status="live" label="Live data" />}
        >
          <div className="space-y-4">
            <div className="flex flex-wrap items-end justify-between gap-4">
              <div>
                <p className="text-3xl sm:text-4xl font-black text-slate-900 tabular-nums">
                  {pct(completionRate)}
                </p>
                <p className="text-sm text-slate-500 mt-1">
                  {num(taskMonth.completed)} of {num(taskMonth.total)} tasks completed
                </p>
              </div>
              <div className="text-right">
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">Still open</p>
                <p className="text-xl font-black text-amber-600 tabular-nums">
                  {num(Math.max(0, taskMonth.total - taskMonth.completed))}
                </p>
              </div>
            </div>

            <div
              role="progressbar"
              aria-label={`Task completion rate for ${taskMonth.label}`}
              aria-valuemin={0}
              aria-valuemax={100}
              aria-valuenow={completionRate === null ? undefined : Math.round(completionPercent)}
              className="h-3 w-full rounded-full bg-slate-100 overflow-hidden"
            >
              <div
                className="h-full rounded-full bg-emerald-500"
                style={{ width: `${completionPercent}%` }}
              />
            </div>

            <p className="text-[11px] text-slate-500">
              {completionRate === null
                ? `No tasks were assigned in ${taskMonth.label} yet, so there is nothing to measure.`
                : `Covers ${taskMonth.label}. Cancelled assignments are left out of the total.`}
            </p>
          </div>
        </Panel>

        {/* ---------------------------------------------------------------- */}
        {/* 4. Employee progress overview                                     */}
        {/* ---------------------------------------------------------------- */}
        <Panel
          title="Employee progress overview"
          description="Every column is a live aggregate per employee. Rates show an em dash when there is nothing to measure."
          actions={<StatusBadge status="live" label="Live data" />}
        >
          <FilterBar>
            <Field label="Department">
              <Select
                value={employeeFilters.department_id}
                onChange={(value) => applyFilter('department_id', value)}
                options={(options.data?.departments ?? []).map((d) => ({ value: String(d.id), label: d.name }))}
                placeholder="All departments"
              />
            </Field>
            <Field label="Employment status">
              <Select
                value={employeeFilters.status}
                onChange={(value) => applyFilter('status', value)}
                options={(options.data?.employment_statuses ?? []).map((s) => ({ value: s, label: s }))}
                placeholder="All statuses"
              />
            </Field>
            <Field label="Sort by">
              <Select
                value={employeeFilters.sort}
                onChange={(value) => applyFilter('sort', value)}
                options={[
                  { value: 'name', label: 'Name' },
                  { value: 'completion_rate', label: 'Completion rate' },
                  { value: 'completed_tasks', label: 'Completed tasks' },
                  { value: 'late_tasks', label: 'Late tasks' },
                ]}
                placeholder="Default"
              />
            </Field>
            <Field label="Search" className="flex-1 min-w-[180px]">
              <TextInput
                value={employeeFilters.search}
                onChange={(value) => applyFilter('search', value)}
                placeholder="Name or employee number"
              />
            </Field>
          </FilterBar>

          {employees.loading ? (
            <LoadingPanel label="Loading employees" rows={2} />
          ) : employees.error || !employees.data ? (
            <ErrorPanel
              message={employees.error ?? 'Unable to load employees.'}
              onRetry={employees.reload}
            />
          ) : employees.data.items.length === 0 ? (
            <EmptyState
              title="No employees match these filters"
              message="Try clearing a filter to widen the search."
            />
          ) : (
            <div className="space-y-4">
              <Table>
                <thead>
                  <tr>
                    <Th>Employee</Th>
                    <Th>Department</Th>
                    <Th>Tasks</Th>
                    <Th>Completion</Th>
                    <Th>Reports</Th>
                    <Th>On time</Th>
                    <Th>Current status</Th>
                  </tr>
                </thead>
                <tbody>
                  {employees.data.items.map((row) => (
                    <EmployeeRowView key={row.employee_id} row={row} />
                  ))}
                </tbody>
              </Table>
              <Pagination
                meta={employees.data.meta}
                onPageChange={(page) => setEmployeeFilters((prev) => ({ ...prev, page }))}
              />
            </div>
          )}
        </Panel>

        {/* ---------------------------------------------------------------- */}
          {/* 5 + 6. Report monitoring + task progress (side by side)           */}
        {/* ---------------------------------------------------------------- */}
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
          <Panel
            title="Report monitoring"
            description="Submission status, deadlines and the revision queue."
          >
            <FilterBar>
              <Field label="Status">
                <Select
                  value={reportFilters.status}
                  onChange={(value) => applyReportFilter('status', value)}
                  options={options.data?.report_statuses ?? []}
                  placeholder="All statuses"
                />
              </Field>
              <Field label="Search" className="flex-1 min-w-[150px]">
                <TextInput
                  value={reportFilters.search}
                  onChange={(value) => applyReportFilter('search', value)}
                  placeholder="Report or employee"
                />
              </Field>
              <button
                type="button"
                onClick={() => applyReportFilter('late', reportFilters.late === '1' ? '' : '1')}
                className={`px-4 py-2 rounded-lg text-sm font-bold border transition ${
                  reportFilters.late === '1'
                    ? 'bg-red-600 text-white border-red-600'
                    : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                }`}
              >
                Late only
              </button>
            </FilterBar>

            {reports.loading ? (
              <LoadingPanel label="Loading reports" rows={2} />
            ) : reports.error || !reports.data ? (
              <ErrorPanel message={reports.error ?? 'Unable to load reports.'} onRetry={reports.reload} />
            ) : reports.data.items.length === 0 ? (
              <EmptyState title="No reports match these filters" />
            ) : (
              <div className="space-y-4">
                <Table>
                  <thead>
                    <tr>
                      <Th>Report</Th>
                      <Th>Employee</Th>
                      <Th>Status</Th>
                      <Th>Deadline</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {reports.data.items.map((row) => (
                      <ReportRowView key={row.id} row={row} />
                    ))}
                  </tbody>
                </Table>
                <Pagination
                  meta={reports.data.meta}
                  onPageChange={(page) => setReportFilters((prev) => ({ ...prev, page }))}
                />
              </div>
            )}
          </Panel>

          <Panel title="Task progress" description="Live assignments, progress and deadlines.">
            <FilterBar>
              <Field label="Status">
                <Select
                  value={taskFilters.status}
                  onChange={(value) => applyTaskFilter('status', value)}
                  options={options.data?.task_statuses ?? []}
                  placeholder="All statuses"
                />
              </Field>
              <button
                type="button"
                onClick={() => applyTaskFilter('overdue', taskFilters.overdue === '1' ? '' : '1')}
                className={`px-4 py-2 rounded-lg text-sm font-bold border transition ${
                  taskFilters.overdue === '1'
                    ? 'bg-red-600 text-white border-red-600'
                    : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                }`}
              >
                Overdue only
              </button>
            </FilterBar>

            {tasks.loading ? (
              <LoadingPanel label="Loading tasks" rows={2} />
            ) : tasks.error || !tasks.data ? (
              <ErrorPanel message={tasks.error ?? 'Unable to load tasks.'} onRetry={tasks.reload} />
            ) : tasks.data.items.length === 0 ? (
              <EmptyState title="No task assignments match these filters" />
            ) : (
              <div className="space-y-4">
                <Table>
                  <thead>
                    <tr>
                      <Th>Task</Th>
                      <Th>Employee</Th>
                      <Th>Progress</Th>
                      <Th>Status</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {tasks.data.items.map((row) => (
                      <TaskRowView key={row.id} row={row} />
                    ))}
                  </tbody>
                </Table>
                <Pagination
                  meta={tasks.data.meta}
                  onPageChange={(page) => setTaskFilters((prev) => ({ ...prev, page }))}
                />
              </div>
            )}
          </Panel>
        </div>

        {/* ---------------------------------------------------------------- */}
        {/* 7. Analytics & insights                                           */}
        {/* ---------------------------------------------------------------- */}
        <Panel
          title="Analytics & insights"
          description="Six-month series computed from the same records as the tables above."
        >
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                Report submissions
              </h3>
              <TrendChart
                labels={trends.labels}
                series={[
                  {
                    key: 'total',
                    label: 'Submitted',
                    colour: CHART_COLOURS[0],
                    values: trends.reportPoints.map((p) => p.total),
                  },
                  {
                    key: 'late',
                    label: 'Late',
                    colour: CHART_COLOURS[3],
                    values: trends.reportPoints.map((p) => p.late),
                  },
                  {
                    key: 'approved',
                    label: 'Approved',
                    colour: CHART_COLOURS[2],
                    values: trends.reportPoints.map((p) => p.approved),
                  },
                ]}
              />
            </div>

            <div>
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                Task volume
              </h3>
              <GroupedBarChart
                labels={trends.labels}
                series={[
                  {
                    key: 'total',
                    label: 'Assigned',
                    colour: CHART_COLOURS[0],
                    values: trends.taskPoints.map((p) => p.total),
                  },
                  {
                    key: 'completed',
                    label: 'Completed',
                    colour: CHART_COLOURS[2],
                    values: trends.taskPoints.map((p) => p.completed),
                  },
                ]}
              />
            </div>

            <div>
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                Report status mix
              </h3>
              <DonutChart
                data={reportStatusBreakdown}
                centreLabel="Reports"
                centreValue={num(kpis.reports.total)}
              />
            </div>

            <div>
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                Task status mix
              </h3>
              <DonutChart
                data={taskStatusBreakdown}
                centreLabel="Tasks"
                centreValue={num(kpis.tasks.total)}
              />
            </div>

            <div>
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                Completion rate by employee
              </h3>
              <RankingBars rows={employeeRanking} suffix="%" emptyMessage="No employee has task data yet." />
            </div>
          </div>
        </Panel>

        {/* ---------------------------------------------------------------- */}
        {/* 8. Late reports + recent activity + system status                 */}
        {/* ---------------------------------------------------------------- */}
        <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
          <Panel
            title="Late reports"
            description="Only rows loaded on the current page are listed here; use the Late only filter above for the full set."
            className="xl:col-span-2"
          >
            {reports.loading ? (
              <LoadingPanel label="Loading late reports" rows={2} />
            ) : lateReports.length === 0 ? (
              <EmptyState
                title="No late reports on this page"
                message="Switch the report table to the late filter to review the full list."
              />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Report</Th>
                    <Th>Employee</Th>
                    <Th>Deadline</Th>
                    <Th className="text-right">Days late</Th>
                  </tr>
                </thead>
                <tbody>
                  {lateReports.map((row) => (
                    <tr key={row.id} className="border-b border-slate-100 last:border-0">
                      <Td>
                        <p className="font-semibold text-slate-800">{row.title}</p>
                        <p className="text-[11px] text-slate-400">{row.department ?? '-'}</p>
                      </Td>
                      <Td>{row.employee_name ?? '-'}</Td>
                      <Td className="text-slate-500 text-xs">{shortDate(row.deadline)}</Td>
                      <Td className="text-right tabular-nums font-semibold text-red-600">
                        {/* The column header says "Days late"; repeat it for
                            screen readers so the bare number is not read alone. */}
                        <span className="sr-only">{num(row.days_late)} days late</span>
                        <span aria-hidden="true">{num(row.days_late)}</span>
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </Panel>

          <div className="space-y-6">
            <Panel title="Recent activity" description="Latest recorded activity across the institution.">
              {activity.loading ? (
                <LoadingPanel label="Loading activity" rows={2} />
              ) : activity.error ? (
                <ErrorPanel message={activity.error} onRetry={activity.refresh} />
              ) : activity.logs.length === 0 ? (
                <EmptyState title="No activity recorded yet" />
              ) : (
                <ul className="space-y-3">
                  {activity.logs.map((log) => (
                    <li key={log.id} className="border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                      <p className="text-sm text-slate-700 font-semibold">{log.description ?? log.action}</p>
                      <p className="text-[11px] text-slate-400">
                        {log.user_name ?? 'System'} · {dateTime(log.created_at)}
                      </p>
                    </li>
                  ))}
                </ul>
              )}
            </Panel>

            <Panel title="System status" description="Backend health for this session.">
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-slate-600">API connection</span>
                  {health.status === 'loading' ? (
                    <Badge tone="neutral">Checking...</Badge>
                  ) : health.status === 'connected' ? (
                    <StatusBadge status="ok" label="Connected" />
                  ) : (
                    <StatusBadge status="down" label="Unreachable" />
                  )}
                </div>
                {health.status === 'connected' && health.health ? (
                  <p className="text-[11px] text-slate-400">
                    API {health.health.api} · DB {health.health.database} · {health.health.environment} ·{' '}
                    {health.health.api_version} · {health.health.timestamp}
                  </p>
                ) : null}
                {health.status === 'error' ? (
                  <ErrorPanel message={health.error ?? 'Backend unreachable.'} onRetry={retryHealth} />
                ) : null}
              </div>
            </Panel>
          </div>
        </div>
      </div>
    </AdminPage>
  )
}

/* ------------------------------------------------------------------ */
/* Icons - drawn inline so the page needs no icon library             */
/* ------------------------------------------------------------------ */

function KpiIcon({ children }: { children: ReactNode }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.7}
      strokeLinecap="round"
      strokeLinejoin="round"
      className="h-6 w-6"
      aria-hidden="true"
      focusable="false"
    >
      {children}
    </svg>
  )
}

/* ------------------------------------------------------------------ */
/* Row views - extracted so the tables stay readable                    */
/* ------------------------------------------------------------------ */

function EmployeeRowView({ row }: { row: HrEmployeeRow }) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <Td>
        <p className="font-semibold text-slate-800">{row.name}</p>
        <p className="text-[11px] text-slate-400">
          {row.employee_number}
          {row.position ? ` · ${row.position}` : ''}
        </p>
      </Td>
      <Td className="text-slate-500 text-xs">{row.department ?? '-'}</Td>
      <Td className="tabular-nums">
        {num(row.completed_tasks)}/{num(row.assigned_tasks)}
        {row.late_tasks > 0 ? (
          <p className="text-[10px] text-red-600 font-bold">{num(row.late_tasks)} overdue</p>
        ) : null}
      </Td>
      <Td className="tabular-nums">{pct(row.completion_rate)}</Td>
      <Td className="tabular-nums">
        {num(row.approved_reports)}/{num(row.submitted_reports)}
        {row.late_reports > 0 ? (
          <p className="text-[10px] text-red-600 font-bold">{num(row.late_reports)} late</p>
        ) : null}
      </Td>
      <Td className="tabular-nums">{pct(row.on_time_rate)}</Td>
      <Td>
        <StatusBadge status={row.current_status} />
      </Td>
    </tr>
  )
}

function ReportRowView({ row }: { row: HrReportRow }) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <Td>
        <p className="font-semibold text-slate-800">{row.title}</p>
        <p className="text-[11px] text-slate-400">{row.department ?? '-'}</p>
      </Td>
      <Td>
        <p className="text-slate-700">{row.employee_name ?? '-'}</p>
        <p className="text-[11px] text-slate-400">{row.employee_number}</p>
      </Td>
      <Td>
        <div className="flex flex-wrap gap-1">
          <StatusBadge status={row.status} label={row.status_label} />
          {row.is_late ? <Badge tone="danger">{num(row.days_late)}d late</Badge> : null}
          {row.needs_revision ? <Badge tone="warn">Needs revision</Badge> : null}
        </div>
      </Td>
      <Td className="text-slate-500 text-xs">{shortDate(row.deadline)}</Td>
    </tr>
  )
}

function TaskRowView({ row }: { row: HrTaskRow }) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <Td>
        <p className="font-semibold text-slate-800">{row.task_name ?? '-'}</p>
        <p className="text-[11px] text-slate-400">{row.task_code}</p>
      </Td>
      <Td>
        <p className="text-slate-700">{row.employee_name ?? '-'}</p>
        <p className="text-[11px] text-slate-400">{row.department ?? '-'}</p>
      </Td>
      <Td>
        <p className="tabular-nums font-semibold">{pct(row.progress_percentage, 0)}</p>
        <p className="text-[10px] text-slate-400">due {shortDate(row.deadline)}</p>
      </Td>
      <Td>
        <div className="flex flex-wrap gap-1">
          <StatusBadge status={row.status} />
          {row.report_status ? <Badge tone="info">Report {row.report_status}</Badge> : null}
        </div>
      </Td>
    </tr>
  )
}
