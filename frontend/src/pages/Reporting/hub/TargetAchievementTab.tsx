import { useCallback, useState } from 'react'
import {
  Badge,
  EmptyState,
  ErrorPanel,
  LoadingPanel,
  Panel,
  StatusBadge,
  Table,
  Td,
  Th,
  shortDate,
} from '../../../components/admin/AdminUi'
import { TargetVsActualCard } from '../../../components/dashboard/TargetVsActualCard'
import { useReportResource } from '../../../hooks/useReports'
import { useReportingResource } from '../../../hooks/useReporting'
import { getTargetReport } from '../../../services/reportingService'
import { getTargetVsActual } from '../../../services/reportService'
import type { ReportingFilters, TargetReport } from '../../../types/reporting'
import { MiniStat, numOrNa, pctOrNa, toReportFilters } from './hubShared'

type TargetPeriodFilter = 'all' | 'monthly' | 'quarterly' | 'annual'

const PERIOD_OPTIONS: Array<{ value: TargetPeriodFilter; label: string }> = [
  { value: 'all', label: 'All targets' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'annual', label: 'Annual' },
]

interface TargetAchievementTabProps {
  filters: ReportingFilters
  periodLabel: string
}

/**
 * TAB 6 — Target achievement, connected to the existing target management
 * records (EmployeeTarget -> Task). Actual accomplishment is the count of
 * completed tasks operationalising each target.
 */
export function TargetAchievementTab({ filters, periodLabel }: TargetAchievementTabProps) {
  const [periodFilter, setPeriodFilter] = useState<TargetPeriodFilter>('all')

  const targets = useReportingResource<TargetReport>(
    useCallback(() => getTargetReport(filters), [filters]),
    `hub-targets-${JSON.stringify(filters)}`,
    'Unable to load the target achievement report.',
  )

  const data = targets.data
  const rows = (data?.targets ?? []).filter(
    (row) => periodFilter === 'all' || row.period_type === periodFilter,
  )

  // Lifted from the legacy Reports page: the same /reports/target-vs-actual
  // snapshot card, so attainment is shown identically in both surfaces.
  const reportFilters = toReportFilters(filters)
  const targetVsActual = useReportResource(
    useCallback(() => getTargetVsActual(reportFilters), [reportFilters]),
    `hub-target-vs-actual|${JSON.stringify(reportFilters)}`,
    'Unable to load target vs actual.',
  )

  return (
    <div className="space-y-4">
      {targets.loading && !data ? (
        <LoadingPanel label="Loading target achievement" rows={3} />
      ) : targets.error ? (
        <ErrorPanel message={targets.error} onRetry={targets.refresh} />
      ) : !data || !data.has_data ? (
        <EmptyState
          title="No targets recorded"
          message="No employee or department targets exist for the selected period."
        />
      ) : (
        <>
          <Panel
            title="Target achievement summary"
            description={`Connected to the target management records for ${periodLabel}.`}
            actions={<Badge tone="neutral">{`${numOrNa(data.total_targets)} targets`}</Badge>}
          >
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
              <MiniStat label="Targets set" value={numOrNa(data.total_targets)} />
              <MiniStat label="Achieved" value={numOrNa(data.achieved_targets)} />
              <MiniStat label="Achievement rate" value={pctOrNa(data.achievement_rate)} />
              <MiniStat
                label="Value achievement"
                value={pctOrNa(data.achievement_percentage)}
                hint={`${numOrNa(data.total_actual)} of ${numOrNa(data.total_target_value)} target value`}
              />
              <MiniStat label="In progress" value={numOrNa(data.in_progress)} />
              <MiniStat label="Partially achieved" value={numOrNa(data.partially_achieved)} />
              <MiniStat label="Unachieved" value={numOrNa(data.unachieved)} />
              <MiniStat
                label="Departments covered"
                value={numOrNa(data.by_department.length)}
                hint="Targets without a department are excluded from department rows"
              />
            </div>
          </Panel>

          {targetVsActual.data ? (
            <div className="dashboard-grid">
              <TargetVsActualCard data={targetVsActual.data} />
            </div>
          ) : null}

          <Panel title="Department target achievement" description="Ranked by achieved-target rate.">
            {data.by_department.length === 0 ? (
              <EmptyState
                title="No department targets"
                message="No department-linked targets exist for this period."
              />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Department</Th>
                    <Th className="text-right">Targets</Th>
                    <Th className="text-right">Achieved</Th>
                    <Th className="text-right">Achievement rate</Th>
                    <Th className="text-right">Target value</Th>
                    <Th className="text-right">Actual</Th>
                    <Th className="text-right">Value achievement</Th>
                  </tr>
                </thead>
                <tbody>
                  {data.by_department.map((row) => (
                    <tr key={row.department_id ?? row.department_name} className="border-b border-slate-100 last:border-0">
                      <Td className="font-semibold text-slate-800">{row.department_name}</Td>
                      <Td className="text-right tabular-nums">{numOrNa(row.total_targets)}</Td>
                      <Td className="text-right tabular-nums">{numOrNa(row.achieved)}</Td>
                      <Td className="text-right tabular-nums">{pctOrNa(row.achievement_rate)}</Td>
                      <Td className="text-right tabular-nums">{numOrNa(row.total_target_value)}</Td>
                      <Td className="text-right tabular-nums">{numOrNa(row.total_actual)}</Td>
                      <Td className="text-right tabular-nums">{pctOrNa(row.achievement_percentage)}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </Panel>

          <Panel
            title="Target detail"
            description="Individual targets with accomplishment, remaining value and lifecycle status."
            actions={
              <div className="flex flex-wrap gap-1">
                {PERIOD_OPTIONS.map((option) => (
                  <button
                    key={option.value}
                    type="button"
                    aria-pressed={periodFilter === option.value}
                    onClick={() => setPeriodFilter(option.value)}
                    className={`px-2.5 py-1 rounded-lg text-[11px] font-bold border transition ${
                      periodFilter === option.value
                        ? 'bg-jpc-600 text-white border-jpc-600'
                        : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'
                    }`}
                  >
                    {option.label}
                  </button>
                ))}
              </div>
            }
          >
            {rows.length === 0 ? (
              <EmptyState
                title="No targets for this period type"
                message="Choose another target period type or a different reporting period."
              />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Employee</Th>
                    <Th>Department</Th>
                    <Th>Target</Th>
                    <Th>Period</Th>
                    <Th className="text-right">Target value</Th>
                    <Th className="text-right">Actual accomplishment</Th>
                    <Th className="text-right">Achievement</Th>
                    <Th className="text-right">Remaining</Th>
                    <Th>Status</Th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.id} className="border-b border-slate-100 last:border-0">
                      <Td className="font-semibold text-slate-800">{row.employee}</Td>
                      <Td className="text-xs text-slate-600">{row.department ?? '—'}</Td>
                      <Td>
                        <p className="text-slate-800">{row.title}</p>
                        <p className="text-[11px] text-slate-400">
                          {shortDate(row.start_date)} – {shortDate(row.end_date)}
                        </p>
                      </Td>
                      <Td>
                        <Badge tone="neutral">{row.period_type}</Badge>
                      </Td>
                      <Td className="text-right tabular-nums">
                        {numOrNa(row.target_value)} {row.target_unit ?? ''}
                      </Td>
                      <Td className="text-right tabular-nums">{numOrNa(row.actual_value)}</Td>
                      <Td className="text-right tabular-nums">{pctOrNa(row.achievement_percentage)}</Td>
                      <Td className="text-right tabular-nums">{numOrNa(row.remaining_target)}</Td>
                      <Td>
                        <StatusBadge status={row.status} label={row.status_label} />
                      </Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )}
          </Panel>
        </>
      )}
    </div>
  )
}
