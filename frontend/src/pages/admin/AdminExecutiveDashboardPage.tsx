import { Link } from 'react-router-dom'
import { getAdminExecutiveDashboard } from '../../services/adminPortalService'
import type { AdminExecutiveDashboardData } from '../../services/adminPortalService'
import { getAdminPlatformStatus } from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import {
  AdminPage,
  Badge,
  EmptyState,
  ErrorPanel,
  LoadingPanel,
  Panel,
  StatCard,
  StatusBadge,
  Table,
  Td,
  Th,
  num,
  pct,
} from '../../components/admin/AdminUi'
import { RankingBars, StackedProportionBar, CHART_COLOURS } from '../../components/admin/AdminCharts'
import { PATHS } from '../../routes/paths'

/**
 * Admin section 1 - Executive Dashboard.
 *
 * Shows the twelve headline KPIs, live service health, the platform
 * architecture, and critical alerts.
 */
export default function AdminExecutiveDashboardPage() {
  const dashboard = useAdminResource<AdminExecutiveDashboardData>(getAdminExecutiveDashboard)
  const platform = useAdminResource(() => getAdminPlatformStatus())

  if (dashboard.loading) {
    return (
      <AdminPage title="Executive Dashboard">
        <LoadingPanel label="Loading executive metrics" rows={8} />
      </AdminPage>
    )
  }

  if (dashboard.error || !dashboard.data) {
    return (
      <AdminPage title="Executive Dashboard">
        <ErrorPanel message={dashboard.error ?? 'Unable to load executive metrics.'} onRetry={dashboard.reload} />
      </AdminPage>
    )
  }

  const { institution, kpis, critical_alerts, operational_digest } = dashboard.data
  const overallTone = platform.data?.overall === 'ok' ? 'ok' : platform.data?.overall === 'degraded' ? 'warn' : 'danger'

  return (
    <AdminPage
      title={`${institution.name} · Executive Dashboard`}
      subtitle={`${institution.academic_year} (${institution.academic_year_status}) · ${institution.current_term}`}
      actions={
        <Link
          to={PATHS.adminPlatformStatus}
          className={`px-4 py-2 rounded-lg text-sm font-bold border ${
            overallTone === 'ok'
              ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
              : overallTone === 'warn'
                ? 'bg-amber-50 text-amber-700 border-amber-200'
                : 'bg-red-50 text-red-700 border-red-200'
          }`}
        >
          System: {platform.data?.overall ?? 'unknown'}
        </Link>
      }
    >
      {/* Section 1 - headline KPIs */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <StatCard label="Total Employees" value={num(kpis.total_employees)} hint={`${num(kpis.active_employees)} active`} />
        <StatCard label="Task Completion" value={pct(kpis.completion_rate)} hint={`${num(kpis.completed_tasks)} of ${num(kpis.total_tasks)} tasks`} tone="info" />
        <StatCard label="On-Time Rate" value={pct(kpis.on_time_rate)} hint={`${num(kpis.late_tasks)} late`} tone={kpis.on_time_rate >= 80 ? 'ok' : 'warn'} />
        <StatCard label="Overdue Tasks" value={num(kpis.overdue_tasks)} hint="Open and past due date" tone={kpis.overdue_tasks > 0 ? 'danger' : 'ok'} />
        <StatCard label="Pending Reviews" value={num(kpis.pending_reviews)} hint="Awaiting a decision" tone={kpis.pending_reviews > 0 ? 'warn' : 'ok'} />
        <StatCard label="Reports Submitted" value={num(kpis.submitted_reports)} hint={`${num(kpis.revision_required)} need revision`} />
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        {/* System status */}
        <Panel
          title="System Status"
          description="Live health reported by the backend"
          actions={
            <Link to={PATHS.adminPlatformStatus} className="text-xs font-bold text-jpc-700 hover:underline">
              Details
            </Link>
          }
        >
          {platform.loading ? (
            <p className="text-sm text-slate-400">Checking services…</p>
          ) : platform.error ? (
            <p className="text-sm text-amber-700">{platform.error}</p>
          ) : platform.data ? (
            <ul className="space-y-2.5">
              {platform.data.services.map((service) => (
                <li key={service.key} className="flex items-start justify-between gap-3 text-xs">
                  <div className="min-w-0">
                    <p className="font-bold text-slate-800">{service.label}</p>
                    <p className="text-slate-500 truncate">{service.detail}</p>
                  </div>
                  <StatusBadge status={service.state} />
                </li>
              ))}
            </ul>
          ) : null}
        </Panel>

        {/* Operational digest - explicitly not AI */}
        <Panel title="Operational Digest" description="Computed from live records — not AI output">
          {operational_digest.available ? (
            <div className="space-y-3 text-xs">
              <DigestList title="Key accomplishments" items={operational_digest.key_accomplishments} marker="+" tone="ok" />
              <DigestList title="Needs attention" items={operational_digest.areas_needing_attention} marker="!" tone="danger" />
              <DigestList title="Recommendations" items={operational_digest.recommendations} marker="i" tone="info" />
              <p className="text-[11px] text-slate-400">{operational_digest.disclaimer}</p>
            </div>
          ) : (
            <EmptyState title="No digest available" message={operational_digest.text} />
          )}
        </Panel>
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        {/* Task and report composition */}
        <Panel title="Task & Report Composition" description="Proportions of all current records">
          <div className="space-y-5">
            <div>
              <p className="text-xs font-bold text-slate-600 mb-2">Tasks</p>
              <StackedProportionBar
                segments={[
                  { key: 'completed', label: 'Completed', value: kpis.completed_tasks, colour: CHART_COLOURS[2] },
                  { key: 'in_progress', label: 'In progress', value: kpis.in_progress_tasks, colour: CHART_COLOURS[1] },
                  { key: 'not_started', label: 'Not started', value: kpis.not_started_tasks, colour: CHART_COLOURS[7] },
                  { key: 'overdue', label: 'Overdue', value: kpis.overdue_tasks, colour: CHART_COLOURS[4] },
                ]}
              />
            </div>
            <div>
              <p className="text-xs font-bold text-slate-600 mb-2">Reports</p>
              <StackedProportionBar
                segments={[
                  { key: 'approved', label: 'Approved', value: kpis.reports_approved, colour: CHART_COLOURS[2] },
                  { key: 'under_review', label: 'Under review', value: kpis.reports_under_review, colour: CHART_COLOURS[1] },
                  { key: 'pending', label: 'Pending / draft', value: kpis.reports_pending, colour: CHART_COLOURS[7] },
                  { key: 'revision', label: 'Needs revision', value: kpis.reports_needs_revision, colour: CHART_COLOURS[3] },
                  { key: 'rejected', label: 'Rejected', value: kpis.reports_rejected, colour: CHART_COLOURS[4] },
                ]}
              />
            </div>
          </div>
        </Panel>

        {/* Department performance ranking */}
<Panel
          title="Department Performance"
          description="Ranked by task completion rate"
          actions={
            <Link to={PATHS.adminDepartments} className="text-xs font-bold text-jpc-700 hover:underline">
              Manage
            </Link>
          }
>
          <RankingBars
            rows={[...dashboard.data.department_performance]
              .sort((a, b) => a.rank - b.rank)
              .map((dept) => ({
                key: dept.id,
                label: dept.name,
                value: dept.completion_rate,
                colour: CHART_COLOURS[(dept.rank - 1) % CHART_COLOURS.length],
              }))}
            suffix="%"
          />
        </Panel>
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        {/* Critical alerts */}
        <Panel
          title="Critical Alerts"
          description="Highest-impact open items"
          actions={
            <Link to={PATHS.adminTaskMonitoring} className="text-xs font-bold text-jpc-700 hover:underline">
              Task monitoring
            </Link>
          }
        >
          {critical_alerts.overdue_tasks.length === 0 ? (
            <EmptyState title="No overdue tasks" message="Nothing is currently past its due date." />
          ) : (
            <Table>
              <thead>
                <tr>
                  <Th>Task</Th>
                  <Th>Employee</Th>
                  <Th>Department</Th>
                  <Th className="text-right">Days overdue</Th>
                </tr>
              </thead>
              <tbody>
                {critical_alerts.overdue_tasks.map((task) => (
                  <tr key={task.assignment_id}>
                    <Td className="font-semibold text-slate-800">{task.task_title}</Td>
                    <Td>{task.employee_name}</Td>
                    <Td className="text-slate-500">{task.department_name}</Td>
                    <Td className="text-right">
                      <Badge tone="danger">{task.days_overdue}d</Badge>
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          )}
        </Panel>

      </div>
    </AdminPage>
  )
}

function DigestList({
  title,
  items,
  marker,
  tone,
}: {
  title: string
  items: string[]
  marker: string
  tone: 'ok' | 'danger' | 'info'
}) {
  if (items.length === 0) return null
  return (
    <div>
      <p className="font-bold text-slate-700 mb-1">{title}</p>
      <ul className="space-y-1">
        {items.map((item, index) => (
          <li key={index} className="flex items-start gap-2 text-slate-600">
            <Badge tone={tone}>{marker}</Badge>
            <span>{item}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
