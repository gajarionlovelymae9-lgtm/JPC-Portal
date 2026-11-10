import { getAdminDepartments } from '../../services/adminPortalService'
import type { AdminDepartmentItem } from '../../services/adminPortalService'
import { getAdminPerformanceOverview } from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import {
  AdminPage,
  EmptyState,
  ErrorPanel,
  ExportButton,
  LoadingPanel,
  Panel,
  SampleDataNotice,
  StatCard,
  Table,
  Td,
  Th,
  num,
  pct,
} from '../../components/admin/AdminUi'
import { GroupedBarChartNullable, RankingBars, CHART_COLOURS } from '../../components/admin/AdminCharts'

interface ComparisonRow {
  id: number
  name: string
  code: string
  employees: number
  activeEmployees: number
  tasks: number
  completion: number
  onTime: number
  reports: number
  late: number
  kpi: number | null
  aiScore: number | null
}

/**
 * Admin section 7 - Department comparison.
 *
 * Combines two existing read-only endpoints so no figure is invented:
 *   /admin/departments          -> headcount, tasks, reports, on-time rate
 *   /admin/performance-overview -> KPI achievement and AI validation score
 */
export default function AdminDepartmentComparisonPage() {
  const departments = useAdminResource(() => getAdminDepartments())
  const performance = useAdminResource(() => getAdminPerformanceOverview())

  if (departments.loading || performance.loading) {
    return (
      <AdminPage title="Department Comparison">
        <LoadingPanel label="Loading department comparison" />
      </AdminPage>
    )
  }

  if (departments.error || performance.error) {
    return (
      <AdminPage title="Department Comparison">
        <ErrorPanel
          message={departments.error ?? performance.error ?? 'Unable to load department comparison.'}
          onRetry={() => {
            departments.reload()
            performance.reload()
          }}
        />
      </AdminPage>
    )
  }

  const deptList: AdminDepartmentItem[] = departments.data ?? []
  const perfByDept = new Map(
    (performance.data?.department_performance ?? []).map((row) => [row.id, row]),
  )

  const rows: ComparisonRow[] = deptList.map((dept) => {
    const perf = perfByDept.get(dept.id)
    return {
      id: dept.id,
      name: dept.name,
      code: dept.code,
      employees: dept.employees_count,
      activeEmployees: dept.active_employees,
      tasks: dept.tasks_count,
      completion: dept.completion_rate,
      onTime: dept.on_time_rate,
      reports: dept.reports_count,
      late: perf?.late_tasks ?? dept.overdue_tasks,
      kpi: perf?.kpi_achievement_pct ?? null,
      aiScore: perf?.ai_validation_score ?? null,
    }
  })

  if (rows.length === 0) {
    return (
      <AdminPage title="Department Comparison">
        <Panel>
          <EmptyState title="No departments found" message="The institution has no department records yet." />
        </Panel>
      </AdminPage>
    )
  }

  const totalEmployees = rows.reduce((sum, r) => sum + r.employees, 0)
  const totalTasks = rows.reduce((sum, r) => sum + r.tasks, 0)
  const totalLate = rows.reduce((sum, r) => sum + r.late, 0)
  const avgCompletion =
    totalTasks > 0 ? rows.reduce((sum, r) => sum + r.completion * r.tasks, 0) / totalTasks : 0

  return (
    <AdminPage
      title="Department Comparison"
      subtitle="Side-by-side performance of every college and administrative unit"
      actions={<ExportButton exportKey="departmentPerformance" />}
    >
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <StatCard label="Departments" value={num(rows.length)} />
        <StatCard label="Employees" value={num(totalEmployees)} />
        <StatCard label="Tasks" value={num(totalTasks)} />
        <StatCard label="Weighted completion" value={pct(avgCompletion)} tone="info" />
        <StatCard
          label="Late tasks"
          value={num(totalLate)}
          tone={totalLate > 0 ? 'danger' : 'ok'}
        />
      </div>

      <Panel title="Completion, on-time and KPI by department">
        <GroupedBarChartNullable
          labels={rows.map((r) => r.name)}
          suffix="%"
          series={[
            { key: 'completion', label: 'Task completion', colour: CHART_COLOURS[0], values: rows.map((r) => r.completion) },
            { key: 'on_time', label: 'On-time rate', colour: CHART_COLOURS[2], values: rows.map((r) => r.onTime) },
            { key: 'kpi', label: 'KPI achievement', colour: CHART_COLOURS[1], values: rows.map((r) => r.kpi) },
          ]}
        />
      </Panel>

      <div className="grid lg:grid-cols-2 gap-6">
        <Panel title="Ranked by task completion">
          <RankingBars
            suffix="%"
            rows={[...rows]
              .sort((a, b) => b.completion - a.completion)
              .map((r) => ({ key: r.id, label: r.name, value: r.completion }))}
          />
        </Panel>
        <Panel title="Ranked by on-time rate">
          <RankingBars
            suffix="%"
            rows={[...rows]
              .sort((a, b) => b.onTime - a.onTime)
              .map((r) => ({ key: r.id, label: r.name, value: r.onTime }))}
          />
        </Panel>
      </div>

      <Panel title="Department scorecard" description={`${rows.length} units`}>
        <Table>
          <thead>
            <tr>
              <Th>Department</Th>
              <Th>Code</Th>
              <Th className="text-right">Employees</Th>
              <Th className="text-right">Active</Th>
              <Th className="text-right">Tasks</Th>
              <Th className="text-right">Completion</Th>
              <Th className="text-right">On time</Th>
              <Th className="text-right">Reports</Th>
              <Th className="text-right">Late</Th>
              <Th className="text-right">KPI</Th>
              <Th className="text-right">AI score</Th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-b border-slate-100 last:border-0">
                <Td className="font-semibold text-slate-800">{row.name}</Td>
                <Td className="font-mono text-xs text-slate-500">{row.code}</Td>
                <Td className="text-right tabular-nums">{num(row.employees)}</Td>
                <Td className="text-right tabular-nums">{num(row.activeEmployees)}</Td>
                <Td className="text-right tabular-nums">{num(row.tasks)}</Td>
                <Td className="text-right tabular-nums font-bold">{pct(row.completion)}</Td>
                <Td className="text-right tabular-nums">{pct(row.onTime)}</Td>
                <Td className="text-right tabular-nums">{num(row.reports)}</Td>
                <Td className="text-right tabular-nums">{num(row.late)}</Td>
                <Td className="text-right tabular-nums">{pct(row.kpi)}</Td>
                <Td className="text-right tabular-nums">{pct(row.aiScore)}</Td>
              </tr>
            ))}
          </tbody>
        </Table>
        {rows.some((r) => r.aiScore !== null) ? (
          <SampleDataNotice>
            AI score values originate from the seeded <strong>jpc.sample</strong> provider and are sample data, not live
            analysis results.
          </SampleDataNotice>
        ) : null}
      </Panel>
    </AdminPage>
  )
}
