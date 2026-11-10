import { useAdminResource } from '../hooks/useAdminResource'
import {
  AdminPage,
  EmptyState,
  ErrorPanel,
  LoadingPanel,
  Panel,
  SampleDataNotice,
  StatCard,
  StatusBadge,
  Table,
  Td,
  Th,
  num,
  shortDate,
} from '../components/admin/AdminUi'
import { getPlanningOverview } from '../services/adminDashboardService'

/**
 * Planning overview (Phase 3/6) — read-only.
 *
 * Renders the full academic-year → annual-plan → department-plan →
 * employee-target chain from GET /api/v1/planning in a single request.
 * The screen deliberately exposes no create/edit/delete controls: this
 * phase inspects the planning chain, it does not mutate it.
 */
export default function PlanningPage() {
  const resource = useAdminResource(() => getPlanningOverview(), [])
  const data = resource.data

  return (
    <AdminPage
      title="Planning"
      subtitle="Academic years, annual plans, department plans and employee targets in one read-only view"
    >
      {resource.loading ? (
        <LoadingPanel label="Loading planning overview" />
      ) : resource.error || !data ? (
        <ErrorPanel
          message={resource.error ?? 'Unable to load the planning overview.'}
          onRetry={resource.reload}
        />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Academic years" value={num(data.summary.academic_years)} />
            <StatCard label="Annual plans" value={num(data.summary.annual_plans)} />
            <StatCard label="Department plans" value={num(data.summary.department_plans)} />
            <StatCard label="Employee targets" value={num(data.summary.employee_targets)} />
          </div>

          <Panel title="Academic years" description={`${data.academic_years.length} recorded`}>
            {data.academic_years.length === 0 ? (
              <EmptyState
                title="No academic years yet"
                message="Academic years appear once the planning chain is seeded."
              />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Year</Th>
                    <Th>Start</Th>
                    <Th>End</Th>
                    <Th>Status</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.academic_years.map((year) => (
                    <tr key={year.id} className="border-b border-slate-100 last:border-0">
                      <Td className="font-semibold text-slate-800">{year.name}</Td>
                      <Td className="text-slate-500 text-xs">{shortDate(year.start_date)}</Td>
                      <Td className="text-slate-500 text-xs">{shortDate(year.end_date)}</Td>
                      <Td>
                        <StatusBadge status={year.status} />
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </Panel>

          <Panel title="Annual plans" description={`${data.annual_plans.length} plans`}>
            {data.annual_plans.length === 0 ? (
              <EmptyState title="No annual plans yet" message="Plans appear once the planning chain is seeded." />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Plan</Th>
                    <Th>Academic year</Th>
                    <Th>Department</Th>
                    <Th>Status</Th>
                    <Th className="text-right">Dept plans</Th>
                    <Th className="text-right">Targets</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.annual_plans.map((plan) => (
                    <tr key={plan.id} className="border-b border-slate-100 last:border-0">
                      <Td>
                        <p className="font-semibold text-slate-800">{plan.title}</p>
                        {plan.description ? (
                          <p className="text-[11px] text-slate-400 max-w-md">{plan.description}</p>
                        ) : null}
                      </Td>
                      <Td className="text-slate-500 text-xs">{plan.academic_year?.name ?? '—'}</Td>
                      <Td className="text-slate-500">{plan.department?.name ?? '—'}</Td>
                      <Td>
                        <StatusBadge status={plan.status} />
                      </Td>
                      <Td className="text-right tabular-nums">{num(plan.department_plans_count)}</Td>
                      <Td className="text-right tabular-nums">{num(plan.employee_targets_count)}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </Panel>

          <Panel title="Department plans" description={`${data.department_plans.length} plans`}>
            {data.department_plans.length === 0 ? (
              <EmptyState title="No department plans yet" message="Department plans appear once the planning chain is seeded." />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Plan</Th>
                    <Th>Department</Th>
                    <Th>Annual plan</Th>
                    <Th>Status</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.department_plans.map((plan) => (
                    <tr key={plan.id} className="border-b border-slate-100 last:border-0">
                      <Td className="font-medium text-slate-800">{plan.title}</Td>
                      <Td className="text-slate-500">{plan.department?.name ?? '—'}</Td>
                      <Td className="text-slate-500 text-xs">{plan.annual_plan?.title ?? '—'}</Td>
                      <Td>
                        <StatusBadge status={plan.status} />
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </Panel>

          <Panel title="Employee targets" description={`${data.employee_targets.length} targets`}>
            {data.employee_targets.length === 0 ? (
              <EmptyState title="No employee targets yet" message="Targets appear once the planning chain is seeded." />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Target</Th>
                    <Th>Employee</Th>
                    <Th>Department</Th>
                    <Th className="text-right">Value</Th>
                    <Th>Window</Th>
                    <Th className="text-right">Tasks</Th>
                    <Th>Status</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.employee_targets.map((target) => (
                    <tr key={target.id} className="border-b border-slate-100 last:border-0">
                      <Td className="font-medium text-slate-800">{target.title}</Td>
                      <Td>
                        <p className="text-slate-800">{target.employee_name ?? '—'}</p>
                        {target.employee_number ? (
                          <p className="text-[11px] text-slate-400">{target.employee_number}</p>
                        ) : null}
                      </Td>
                      <Td className="text-slate-500">{target.department_name ?? '—'}</Td>
                      <Td className="text-right tabular-nums">
                        {num(Number(target.target_value))}
                        {target.target_unit ? (
                          <span className="text-[11px] text-slate-400"> {target.target_unit}</span>
                        ) : null}
                      </Td>
                      <Td className="text-slate-500 text-xs">
                        {shortDate(target.start_date)} &ndash; {shortDate(target.end_date)}
                      </Td>
                      <Td className="text-right tabular-nums">{num(target.tasks_count)}</Td>
                      <Td>
                        <StatusBadge status={target.status} />
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </Panel>

          <SampleDataNotice>
            <strong>Read-only by design.</strong> This screen never mutates the planning chain — it renders
            what is stored so leadership can inspect progress without touching plan records.
          </SampleDataNotice>
        </div>
      )}
    </AdminPage>
  )
}
