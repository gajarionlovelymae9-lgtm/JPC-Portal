import { useCallback } from 'react'
import {
  Badge,
  EmptyState,
  ErrorPanel,
  LoadingPanel,
  Panel,
  Table,
  Td,
  Th,
} from '../../../components/admin/AdminUi'
import { useReportingResource } from '../../../hooks/useReporting'
import { getDepartmentPerformance } from '../../../services/reportService'
import type { DepartmentPerformanceRow } from '../../../types/report'
import type { ReportingFilters } from '../../../types/reporting'
import { MiniStat, numOrNa, pctOf, fetchAllReportPages } from './hubShared'

interface DepartmentPerformanceTabProps {
  filters: ReportingFilters
  periodLabel: string
}

/**
 * TAB 3 — Department performance with rankings and head-to-head metrics.
 */
export function DepartmentPerformanceTab({ filters, periodLabel }: DepartmentPerformanceTabProps) {
  // Paginated endpoint (per_page capped at 100 server-side): merge every page
  // so the ranking/totals are not silently truncated to the first 100 rows.
  const departments = useReportingResource<DepartmentPerformanceRow[]>(
    useCallback(
      () => fetchAllReportPages<DepartmentPerformanceRow>(getDepartmentPerformance, filters),
      [filters],
    ),
    `hub-department-performance-${JSON.stringify(filters)}`,
    'Unable to load the department performance report.',
  )

  const rows = departments.data ?? []
  const top = rows.slice(0, 3)

  return (
    <div className="space-y-4">
      {departments.loading && !departments.data ? (
        <LoadingPanel label="Loading department performance" rows={4} />
      ) : departments.error ? (
        <ErrorPanel message={departments.error} onRetry={departments.refresh} />
      ) : rows.length === 0 ? (
        <EmptyState
          title="No department records"
          message="No departments have performance records for this period."
        />
      ) : (
        <>
          <Panel
            title="Department ranking highlights"
            description={`Top performers for ${periodLabel}. Rankings use the completion rate across all in-scope departments.`}
            actions={<Badge tone="neutral">{`${rows.length} departments`}</Badge>}
          >
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              {top.map((row, index) => (
                <MiniStat
                  key={row.department_id ?? row.department_name}
                  label={`#${index + 1} ${row.department_name ?? 'Unassigned'}`}
                  value={pctOf(row.completion_rate, row.total_tasks)}
                  hint={`${numOrNa(row.completed_tasks)} of ${numOrNa(row.total_tasks)} tasks · ${numOrNa(row.reports_submitted)} reports`}
                />
              ))}
            </div>
          </Panel>

          <Table>
            <thead>
              <tr>
                <Th>Rank</Th>
                <Th>Department</Th>
                <Th className="text-right">Employees</Th>
                <Th className="text-right">Assigned</Th>
                <Th className="text-right">Completed</Th>
                <Th className="text-right">Completion</Th>
                <Th className="text-right">Reports submitted</Th>
                <Th className="text-right">Late reports</Th>
                <Th className="text-right">On-time submission</Th>
                <Th className="text-right">Approval</Th>
                <Th className="text-right">Target</Th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.department_id ?? row.department_name} className="border-b border-slate-100 last:border-0">
                  <Td className="tabular-nums">
                    {row.rank !== null && row.rank <= 3 ? (
                      <Badge tone={row.rank === 1 ? 'ok' : 'info'}>{`#${row.rank}`}</Badge>
                    ) : (
                      <span className="text-slate-500">{row.rank ?? '—'}</span>
                    )}
                  </Td>
                  <Td>
                    <p className="font-semibold text-slate-800">{row.department_name ?? '—'}</p>
                    <p className="text-[11px] text-slate-400">{row.department_code ?? '—'}</p>
                  </Td>
                  <Td className="text-right tabular-nums">{numOrNa(row.employee_count)}</Td>
                  <Td className="text-right tabular-nums">{numOrNa(row.total_tasks)}</Td>
                  <Td className="text-right tabular-nums">{numOrNa(row.completed_tasks)}</Td>
                  <Td className="text-right tabular-nums">{pctOf(row.completion_rate, row.total_tasks)}</Td>
                  <Td className="text-right tabular-nums">{numOrNa(row.reports_submitted)}</Td>
                  <Td className={`text-right tabular-nums ${row.late_reports > 0 ? 'text-red-600 font-bold' : ''}`}>
                    {numOrNa(row.late_reports)}
                  </Td>
                  <Td className="text-right tabular-nums">{pctOf(row.on_time_submission_rate, row.reports_submitted)}</Td>
                  <Td className="text-right tabular-nums">{pctOf(row.approval_rate, row.reports_submitted)}</Td>
                  <Td className="text-right tabular-nums">{pctOf(row.target_achievement, row.total_targets)}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        </>
      )}
    </div>
  )
}
