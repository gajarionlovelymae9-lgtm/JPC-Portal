import { useCallback, useState } from 'react'
import { useAuth } from '../auth/useAuth'
import { ReportFilters } from '../components/reports/ReportFilters'
import { ReportSection } from '../components/reports/ReportSection'
import { BarChart } from '../components/reports/BarChart'
import { ExportButtons } from '../components/reports/ExportButtons'
import { EmployeePerformanceTable } from '../components/reports/EmployeePerformanceTable'
import { EmployeePerformanceDetailPanel } from '../components/reports/EmployeePerformanceDetailPanel'
import { DepartmentPerformanceTable } from '../components/reports/DepartmentPerformanceTable'
import { DepartmentPerformanceDetailPanel } from '../components/reports/DepartmentPerformanceDetailPanel'
import { TopPerformersPanel } from '../components/reports/TopPerformersPanel'
import { TargetVsActualCard } from '../components/dashboard/TargetVsActualCard'
import { TaskSummaryCards } from '../components/tasks/TaskSummaryCards'
import { useReportExport, useReportResource } from '../hooks/useReports'
import {
  getMyPerformance,
  getReportOptions,
  getTargetVsActual,
  getTaskCompletionReport,
} from '../services/reportService'
import { toNumber } from '../utils/task'
import type { ReportFilters as ReportFilterParams } from '../types/report'

function completionCards(report: { total_tasks: number; completed_tasks: number; pending_tasks: number; overdue_tasks: number; submitted_tasks: number; rejected_tasks: number; cancelled_tasks: number; on_time: number; late: number; completion_rate: number; on_time_rate: number }) {
  return [
    { label: 'Total tasks', value: report.total_tasks },
    { label: 'Completed', value: report.completed_tasks, tone: 'ok' as const },
    { label: 'Pending', value: report.pending_tasks, tone: 'warning' as const },
    { label: 'Overdue', value: report.overdue_tasks, tone: 'error' as const },
    { label: 'Submitted', value: report.submitted_tasks, tone: 'info' as const },
    { label: 'Rejected', value: report.rejected_tasks, tone: 'error' as const },
    { label: 'Cancelled', value: report.cancelled_tasks },
    { label: 'On time', value: report.on_time, tone: 'ok' as const },
    { label: 'Late', value: report.late, tone: 'warning' as const },
    { label: 'Completion rate', value: `${toNumber(report.completion_rate)}%`, tone: 'info' as const },
    { label: 'On-time rate', value: `${toNumber(report.on_time_rate)}%`, tone: 'info' as const },
  ]
}

function useReportPageFilters() {
  const [filters, setFilters] = useState<ReportFilterParams>({})
  const [refreshVersion, setRefreshVersion] = useState(0)

  const options = useReportResource(
    useCallback(() => getReportOptions(), []),
    `report-options|${refreshVersion}`,
    'Unable to load report options.',
  )

  return {
    filters,
    setFilters,
    refreshVersion,
    bumpRefresh: () => setRefreshVersion((version) => version + 1),
    options,
  }
}

export function ReportExportNotice({ message }: { message: string | null }) {
  if (!message) {
    return null
  }
  return (
    <div className="page-state page-state--denied" role="alert">
      <p>{message}</p>
    </div>
  )
}

function EmployeeReportView({ filters, refreshVersion }: { filters: ReportFilterParams; refreshVersion: number }) {
  const { data, loading, error, refresh } = useReportResource(
    useCallback(() => getMyPerformance(filters), [filters]),
    `my-performance|${refreshVersion}|${JSON.stringify(filters)}`,
    'Unable to load your performance report.',
  )

  const { exporting, exportError, handleExport } = useReportExport()

  if (loading && !data) {
    return <p className="page-state">Loading your performance…</p>
  }

  if (error && !data) {
    return (
      <div className="page-state page-state--denied" role="alert">
        <p>{error}</p>
        <button type="button" className="button button--secondary" onClick={refresh}>
          Retry
        </button>
      </div>
    )
  }

  if (!data) {
    return null
  }

  return (
    <>
      <ReportExportNotice message={exportError} />

      <ReportSection
        title="My performance"
        action={
          <ExportButtons
            report="employee-performance"
            filters={filters}
            onExport={handleExport}
            disabled={exporting}
          />
        }
      >
        <TaskSummaryCards
          items={[
            { label: 'Total tasks', value: data.total_tasks },
            { label: 'Completed', value: data.completed_tasks, tone: 'ok' as const },
            { label: 'Pending', value: data.pending_tasks, tone: 'warning' as const },
            { label: 'Overdue', value: data.overdue_tasks, tone: 'error' as const },
            { label: 'On time', value: data.on_time, tone: 'ok' as const },
            { label: 'Late', value: data.late, tone: 'warning' as const },
            { label: 'Completion rate', value: `${toNumber(data.completion_rate)}%`, tone: 'info' as const },
            { label: 'On-time rate', value: `${toNumber(data.on_time_rate)}%`, tone: 'info' as const },
          ]}
        />
        <div className="dashboard-panel__sub">
          <h4>Recent assignments</h4>
          {data.recent_assignments.length === 0 ? (
            <p className="page-state">No recent assignments.</p>
          ) : (
            <div className="task-table-wrap">
              <table className="task-table">
                <thead>
                  <tr>
                    <th scope="col">Task</th>
                    <th scope="col">Status</th>
                    <th scope="col">Due</th>
                    <th scope="col">Completed</th>
                    <th scope="col">Progress</th>
                  </tr>
                </thead>
                <tbody>
                  {data.recent_assignments.map((assignment) => (
                    <tr key={assignment.assignment_id}>
                      <td data-label="Task">
                        <span className="task-table__title">{assignment.task_title ?? 'Untitled task'}</span>
                        {assignment.task_code ? <span className="task-table__code">{assignment.task_code}</span> : null}
                      </td>
                      <td data-label="Status">{assignment.status.replaceAll('_', ' ')}</td>
                      <td data-label="Due">{assignment.due_date ?? '—'}</td>
                      <td data-label="Completed">{assignment.completed_at ? new Date(assignment.completed_at).toLocaleDateString() : '—'}</td>
                      <td data-label="Progress">{assignment.progress_percentage ?? '—'}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </ReportSection>
    </>
  )
}

function ManagerReportView() {
  const { filters, setFilters, refreshVersion, bumpRefresh, options } = useReportPageFilters()

  const taskCompletion = useReportResource(
    useCallback(() => getTaskCompletionReport(filters), [filters]),
    `task-completion|${refreshVersion}|${JSON.stringify(filters)}`,
    'Unable to load the task completion report.',
  )

  const targetVsActual = useReportResource(
    useCallback(() => getTargetVsActual(filters), [filters]),
    `target-vs-actual|${refreshVersion}|${JSON.stringify(filters)}`,
    'Unable to load target vs actual.',
  )

  const { exporting, exportError, handleExport } = useReportExport()

  const [selectedEmployeeId, setSelectedEmployeeId] = useState<number | null>(null)
  const [selectedDepartmentId, setSelectedDepartmentId] = useState<number | null>(null)
  const filterKey = JSON.stringify(filters)

  const handleFilters = (next: ReportFilterParams) => {
    setFilters(next)
    setSelectedEmployeeId(null)
    setSelectedDepartmentId(null)
  }

  return (
    <>
      <ReportExportNotice message={exportError} />

      <ReportFilters
        value={filters}
        options={options.data}
        optionsLoading={options.loading}
        onChange={handleFilters}
        onRefresh={bumpRefresh}
        refreshing={taskCompletion.loading && taskCompletion.data !== null}
        lastUpdated={taskCompletion.updatedAt}
      />

      {taskCompletion.loading && !taskCompletion.data ? (
        <p className="page-state">Loading reports…</p>
      ) : null}

      {taskCompletion.error && !taskCompletion.data ? (
        <div className="page-state page-state--denied" role="alert">
          <p>{taskCompletion.error}</p>
          <button type="button" className="button button--secondary" onClick={taskCompletion.refresh}>
            Retry
          </button>
        </div>
      ) : null}

      {taskCompletion.data ? (
        <>
          <ReportSection
            title="Task completion"
            hint={taskCompletion.data.period}
            action={
              <ExportButtons
                report="task-completion"
                filters={filters}
                onExport={handleExport}
                disabled={exporting}
              />
            }
          >
            <TaskSummaryCards items={completionCards(taskCompletion.data)} />
            <BarChart
              title="Completed versus pending tasks by month"
              labels={taskCompletion.data.chart.labels}
              completed={taskCompletion.data.chart.completed}
              pending={taskCompletion.data.chart.pending}
              rates={taskCompletion.data.chart.completion_rates}
              monthOverMonthPp={taskCompletion.data.chart.month_over_month_pp}
            />
          </ReportSection>

          <ReportSection
            title="Employee performance"
            hint="Ranked by overall standing"
            action={
              <ExportButtons
                report="employee-performance"
                filters={filters}
                onExport={handleExport}
                disabled={exporting}
              />
            }
          >
            <EmployeePerformanceTable
              key={filterKey}
              scope={filters}
              refreshVersion={refreshVersion}
              selectedEmployeeId={selectedEmployeeId}
              onSelect={setSelectedEmployeeId}
            />
          </ReportSection>

          <EmployeePerformanceDetailPanel
            employeeId={selectedEmployeeId}
            scope={filters}
            refreshVersion={refreshVersion}
            onClose={() => setSelectedEmployeeId(null)}
          />

          <ReportSection
            title="Department performance"
            hint="Ranked by overall standing"
            action={
              <ExportButtons
                report="department-performance"
                filters={filters}
                onExport={handleExport}
                disabled={exporting}
              />
            }
          >
            <DepartmentPerformanceTable
              key={filterKey}
              scope={filters}
              refreshVersion={refreshVersion}
              selectedDepartmentId={selectedDepartmentId}
              onSelect={setSelectedDepartmentId}
            />
          </ReportSection>

          <DepartmentPerformanceDetailPanel
            departmentId={selectedDepartmentId}
            scope={filters}
            refreshVersion={refreshVersion}
            onClose={() => setSelectedDepartmentId(null)}
          />

          <div className="dashboard-grid">
            <TargetVsActualCard data={targetVsActual.data} />
            <div className="task-section" aria-label="Top performers preview">
              <TopPerformersPanel scope={filters} refreshVersion={refreshVersion} />
            </div>
          </div>
        </>
      ) : null}
    </>
  )
}

export default function ReportsPage() {
  const { user } = useAuth()

  if (!user) {
    return <p className="page-state">Sign in to view reports.</p>
  }

  const isEmployee = user.role === 'employee'

  return (
    <div className="page">
      <div className="page__intro">
        <h2>Reports</h2>
        <p>
          {isEmployee
            ? 'Your performance snapshot: task completion, on-time delivery and recent assignments.'
            : 'Task completion, per-employee and per-department performance, attainment and top performers across the organisation.'}
        </p>
      </div>

      {isEmployee ? (
        <EmployeeReportView filters={{}} refreshVersion={0} />
      ) : (
        <ManagerReportView />
      )}
    </div>
  )
}