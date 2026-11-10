import { useState } from 'react'
import { getAdminKpiTargets } from '../../services/adminDashboardService'
import type { KpiTargetItem } from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import {
  AdminPage,
  EmptyState,
  ErrorPanel,
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
  Th,
  num,
  pct,
  shortDate,
} from '../../components/admin/AdminUi'
import { DonutChart } from '../../components/admin/AdminCharts'

/**
 * Admin section 6 - KPI targets.
 *
 * `EmployeeTarget` has no `actual_value` column, so the actual is derived from
 * the target's linked completed tasks and the achievement percentage is null
 * (rendered as "—", never 0%) when a target has no task data at all.
 */
export default function AdminKpiTargetsPage() {
  const [departmentId, setDepartmentId] = useState('')
  const [academicYearId, setAcademicYearId] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)

  const resource = useAdminResource(
    () =>
      getAdminKpiTargets({
        department_id: departmentId,
        academic_year_id: academicYearId,
        status,
        page,
        per_page: 15,
      }),
    [departmentId, academicYearId, status, page],
  )

  const data = resource.data
  const options = data?.options

  const resetPage = (setter: (value: string) => void) => (value: string) => {
    setter(value)
    setPage(1)
  }

  return (
    <AdminPage title="KPI Targets" subtitle="Institution KPI tracking with derived achievement">
      <FilterBar>
        <Field label="Department">
          <Select
            value={departmentId}
            onChange={resetPage(setDepartmentId)}
            options={(options?.departments ?? []).map((d) => ({ value: d.id, label: d.name }))}
            placeholder="All departments"
          />
        </Field>
        <Field label="Academic year">
          <Select
            value={academicYearId}
            onChange={resetPage(setAcademicYearId)}
            options={(options?.academic_years ?? []).map((y) => ({ value: y.id, label: y.name }))}
            placeholder="All years"
          />
        </Field>
        <Field label="Derived status">
          <Select
            value={status}
            onChange={resetPage(setStatus)}
            options={options?.statuses ?? []}
            placeholder="All statuses"
          />
        </Field>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading KPI targets" />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load KPI targets.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <StatCard label="Total KPIs" value={num(data.summary.total_kpis)} />
            <StatCard label="Completed" value={num(data.summary.completed_targets)} tone="ok" />
            <StatCard label="In progress" value={num(data.summary.in_progress_targets)} tone="info" />
            <StatCard label="Missed" value={num(data.summary.missed_targets)} tone={data.summary.missed_targets > 0 ? 'danger' : 'ok'} />
            <StatCard label="Not started" value={num(data.summary.not_started_targets)} />
          </div>

          <div className="grid lg:grid-cols-3 gap-6">
            <Panel title="KPI status split" description="Derived from linked task completion">
              <DonutChart
                data={[
                  { key: 'completed', label: 'Completed', value: data.summary.completed_targets },
                  { key: 'in_progress', label: 'In progress', value: data.summary.in_progress_targets },
                  { key: 'missed', label: 'Missed', value: data.summary.missed_targets },
                  { key: 'not_started', label: 'Not started', value: data.summary.not_started_targets },
                ]}
                centreLabel="KPIs"
                centreValue={data.summary.total_kpis}
                emptyMessage="No KPI targets recorded."
              />
            </Panel>
            <Panel title="Average achievement" className="lg:col-span-2">
              <div className="flex items-center gap-6 flex-wrap">
                <div>
                  <p className="text-4xl font-black text-jpc-600 tabular-nums">
                    {pct(data.summary.avg_kpi_achievement)}
                  </p>
                  <p className="text-xs text-slate-500 mt-1">Mean derived achievement across listed targets</p>
                </div>
              </div>
              <SampleDataNotice>
                <strong>How the actual is derived.</strong> A KPI row has no stored actual value, so the actual is the
                count of completed linked tasks and the achievement is that count over the total linked tasks. Targets
                without linked tasks show &ldquo;&mdash;&rdquo; instead of a misleading 0%.
              </SampleDataNotice>
            </Panel>
          </div>

          <Panel title="KPI target register" description={`${data.meta.total} targets`}>
            {data.items.length === 0 ? (
              <EmptyState title="No targets match these filters" message="Try clearing a filter to widen the search." />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Employee</Th>
                      <Th>Department</Th>
                      <Th>KPI</Th>
                      <Th className="text-right">Target</Th>
                      <Th className="text-right">Actual</Th>
                      <Th className="text-right">Achievement</Th>
                      <Th className="text-right">Tasks</Th>
                      <Th>Derived status</Th>
                      <Th>Period end</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((target) => (
                      <TargetRow key={target.id} target={target} />
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

function TargetRow({ target }: { target: KpiTargetItem }) {
  return (
    <tr className="border-b border-slate-100 last:border-0">
      <Td>
        <p className="font-semibold text-slate-800">{target.employee_name}</p>
        <p className="text-[11px] text-slate-400">{target.employee_number}</p>
      </Td>
      <Td className="text-slate-500">{target.department_name}</Td>
      <Td>
        <p className="font-medium text-slate-800">{target.kpi}</p>
        {target.description ? (
          <p className="text-[11px] text-slate-400 max-w-xs">{target.description}</p>
        ) : null}
      </Td>
      <Td className="text-right tabular-nums">
        {num(target.target)}
        {target.target_unit ? <span className="text-[11px] text-slate-400"> {target.target_unit}</span> : null}
      </Td>
      <Td className="text-right tabular-nums">
        {target.actual === null ? (
          <span className="text-slate-400" title="No linked task data">
            &mdash;
          </span>
        ) : (
          num(target.actual)
        )}
      </Td>
      <Td className="text-right tabular-nums font-bold">
        {pct(target.achievement_pct)}
        {target.tasks_total > 0 ? (
          <p className="text-[10px] font-normal text-slate-400">
            {target.tasks_completed}/{target.tasks_total} tasks
          </p>
        ) : null}
      </Td>
      <Td className="text-right tabular-nums">{num(target.tasks_total)}</Td>
      <Td>
        <StatusBadge status={target.derived_status} />
        {target.derived_status !== target.stored_status ? (
          <p className="text-[10px] text-slate-400 mt-1">stored: {target.stored_status}</p>
        ) : null}
      </Td>
      <Td className="text-slate-500 text-xs">{shortDate(target.end_date)}</Td>
    </tr>
  )
}
