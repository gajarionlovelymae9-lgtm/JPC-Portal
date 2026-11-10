import { useState } from 'react'
import { getAdminPerformanceOverview } from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import {
  AdminPage,
  EmptyState,
  ErrorPanel,
  Field,
  FilterBar,
  LoadingPanel,
  Panel,
  SampleDataNotice,
  Select,
  Table,
  Td,
  Th,
  num,
  pct,
} from '../../components/admin/AdminUi'
import { DonutChart, GroupedBarChartNullable, RankingBars, TrendChart, CHART_COLOURS } from '../../components/admin/AdminCharts'

/**
 * Admin sections 2-5 - the four performance chart sections.
 *
 * All four read from a single `/admin/performance-overview` call so the page
 * stays internally consistent for the selected year and department.
 */
export default function AdminPerformanceOverviewPage() {
  const currentYear = new Date().getFullYear()
  const [year, setYear] = useState(String(currentYear))
  const [departmentId, setDepartmentId] = useState('')

  const resource = useAdminResource(
    () => getAdminPerformanceOverview({ year, department_id: departmentId }),
    [year, departmentId],
  )

  const data = resource.data
  // Department names are only present in the response once it has loaded, so the
  // filter is built from live rows instead of a second hardcoded list.
  const departmentOptions = data
    ? [...data.department_performance].map((dept) => ({ value: dept.id, label: dept.name }))
    : []
  const years = Array.from({ length: 6 }, (_, i) => ({ value: currentYear - i, label: String(currentYear - i) }))

  return (
    <AdminPage
      title="Performance Overview"
      subtitle="Employee progress, department comparison, monthly trend and report status"
    >
      <FilterBar>
        <Field label="Academic year">
          <Select value={year} onChange={setYear} options={years} />
        </Field>
        <Field label="Department">
          <Select
            value={departmentId}
            onChange={setDepartmentId}
            options={departmentOptions}
            placeholder="All departments"
          />
        </Field>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading performance overview" />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load performance overview.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          {/* Section 2 - Employee progress distribution */}
          <Panel
            title="Employee Progress"
            description={`${data.employee_progress.total} task assignments grouped by state`}
          >
            <DonutChart
              data={data.employee_progress.items}
              centreLabel="assignments"
              centreValue={data.employee_progress.total}
              emptyMessage="No task assignments recorded for this selection."
            />
            <p className="text-[11px] text-slate-500 mt-3">
              &ldquo;Late&rdquo; counts assignments that are still open past their due date. It overlaps the open
              states, so the slices above cover every assignment exactly once.
            </p>
          </Panel>

          {/* Section 3 - Department comparison */}
          <Panel title="Department Comparison" description="Completion, coverage and KPI achievement by department">
            {data.department_performance.length === 0 ? (
              <EmptyState title="No departments to compare" message="No department has recorded activity for this year." />
            ) : (
              <div className="space-y-5">
                <GroupedBarChartNullable
                  labels={data.department_performance.map((d) => d.name)}
                  suffix="%"
                  series={[
                    {
                      key: 'task_completion',
                      label: 'Task completion',
                      colour: CHART_COLOURS[0],
                      values: data.department_performance.map((d) => d.task_completion_pct),
                    },
                    {
                      key: 'report_coverage',
                      label: 'Report coverage',
                      colour: CHART_COLOURS[1],
                      values: data.department_performance.map((d) => d.report_coverage_pct),
                    },
                    {
                      key: 'kpi',
                      label: 'KPI achievement',
                      colour: CHART_COLOURS[2],
                      values: data.department_performance.map((d) => d.kpi_achievement_pct),
                    },
                  ]}
                />
                <p className="text-[11px] text-slate-500">
                  Report coverage is the share of assigned tasks that have at least one report attached. It is not an
                  approval rate.
                </p>
                <Table>
                  <thead>
                    <tr>
                      <Th>Department</Th>
                      <Th className="text-right">Tasks</Th>
                      <Th className="text-right">Completion</Th>
                      <Th className="text-right">Report coverage</Th>
                      <Th className="text-right">KPI achievement</Th>
                      <Th className="text-right">Late</Th>
                      <Th className="text-right">AI score</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.department_performance.map((dept) => (
                      <tr key={dept.id} className="border-b border-slate-100 last:border-0">
                        <Td className="font-semibold text-slate-800">{dept.name}</Td>
                        <Td className="text-right tabular-nums">{num(dept.tasks_total)}</Td>
                        <Td className="text-right tabular-nums">{pct(dept.task_completion_pct)}</Td>
                        <Td className="text-right tabular-nums">{pct(dept.report_coverage_pct)}</Td>
                        <Td className="text-right tabular-nums">{pct(dept.kpi_achievement_pct)}</Td>
                        <Td className="text-right tabular-nums">{num(dept.late_tasks)}</Td>
                        <Td className="text-right tabular-nums">{pct(dept.ai_validation_score)}</Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                {data.department_performance.some((d) => d.ai_validation_score !== null) ? (
                  <SampleDataNotice>
                    The AI score column comes from the seeded <strong>jpc.sample</strong> provider and reflects sample
                    data, not a live analysis.
                  </SampleDataNotice>
                ) : null}
              </div>
            )}
          </Panel>

          {/* Section 4 - Monthly trend */}
          <Panel title="Monthly Performance Trend" description={`Activity recorded during ${data.year}`}>
            <TrendChart
              labels={data.monthly_progress.map((m) => m.label)}
              suffix=""
              series={[
                {
                  key: 'assigned',
                  label: 'Assigned',
                  colour: CHART_COLOURS[0],
                  values: data.monthly_progress.map((m) => m.total),
                },
                {
                  key: 'completed',
                  label: 'Completed',
                  colour: CHART_COLOURS[2],
                  values: data.monthly_progress.map((m) => m.completed),
                },
                {
                  key: 'late',
                  label: 'Late',
                  colour: CHART_COLOURS[4],
                  values: data.monthly_progress.map((m) => m.late),
                },
                {
                  key: 'reports',
                  label: 'Reports',
                  colour: CHART_COLOURS[1],
                  values: data.monthly_progress.map((m) => m.reports),
                },
              ]}
              emptyMessage="No monthly activity recorded for this year."
            />
          </Panel>

          {/* Section 5 - Report status distribution */}
          <Panel
            title="Report Status"
            description={`${data.report_status.total} reports in this selection`}
          >
            <DonutChart
              data={data.report_status.items}
              centreLabel="reports"
              centreValue={data.report_status.total}
              // `submitted` already contains the approved reports, so these
              // buckets overlap and must not be read as shares of one total.
              mutuallyExclusive={false}
              emptyMessage="No reports recorded for this selection."
            />
          </Panel>

          <div className="grid lg:grid-cols-2 gap-6">
            <Panel title="Departments by task completion">
              <RankingBars
                suffix="%"
                rows={[...data.department_performance]
                  .sort((a, b) => b.task_completion_pct - a.task_completion_pct)
                  .map((dept) => ({ key: dept.id, label: dept.name, value: dept.task_completion_pct }))}
              />
            </Panel>
            <Panel title="Departments by KPI achievement">
              <RankingBars
                suffix="%"
                rows={[...data.department_performance]
                  .sort((a, b) => b.kpi_achievement_pct - a.kpi_achievement_pct)
                  .map((dept) => ({ key: dept.id, label: dept.name, value: dept.kpi_achievement_pct }))}
              />
            </Panel>
          </div>
        </div>
      )}
    </AdminPage>
  )
}
