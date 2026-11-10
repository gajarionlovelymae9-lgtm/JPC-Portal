import { useCallback, useMemo, useState } from 'react'
import {
  Badge,
  Button,
  EmptyState,
  ErrorPanel,
  Field,
  FilterBar,
  LoadingPanel,
  Panel,
  Select,
  StatusBadge,
  Table,
  Td,
  TextInput,
  Th,
  dateTime,
  humanise,
  shortDate,
} from '../../../components/admin/AdminUi'
import { useReportingResource } from '../../../hooks/useReporting'
import {
  getEmployeePerformance,
  getEmployeePerformanceDetail,
} from '../../../services/reportService'
import type { HrOptions } from '../../../services/hrService'
import type {
  EmployeePerformanceDetail,
  EmployeePerformanceRow,
} from '../../../types/report'
import type { ReportingFilters } from '../../../types/reporting'
import { numOrNa, pctOf, fetchAllReportPages, toReportFilters } from './hubShared'

interface EmployeePerformanceTabProps {
  filters: ReportingFilters
  options: HrOptions | null
}

/**
 * TAB 2 — Employee performance table with search, department filter and an
 * authorised per-employee drill-down.
 */
export function EmployeePerformanceTab({ filters, options }: EmployeePerformanceTabProps) {
  const [search, setSearch] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [selected, setSelected] = useState<number | null>(null)

  // The table endpoint paginates (per_page capped at 100 server-side), so a
  // single request silently truncated the list at 100 employees and the
  // client-side search/department filter then ran over an incomplete set.
  // Fetch every page and merge so the filters see the full dataset.
  const employees = useReportingResource<EmployeePerformanceRow[]>(
    useCallback(
      () => fetchAllReportPages<EmployeePerformanceRow>(getEmployeePerformance, filters),
      [filters],
    ),
    `hub-employee-performance-${JSON.stringify(filters)}`,
    'Unable to load the employee performance report.',
  )

  const rows = useMemo(() => {
    const all = employees.data ?? []
    const term = search.trim().toLowerCase()
    return all.filter((row) => {
      if (filters.employee_id && row.employee_id !== filters.employee_id) {
        return false
      }
      if (departmentId !== '' && String(row.department_id ?? '') !== departmentId) {
        return false
      }
      if (!term) {
        return true
      }
      return (
        (row.employee_name ?? '').toLowerCase().includes(term) ||
        (row.employee_number ?? '').toLowerCase().includes(term)
      )
    })
  }, [employees.data, departmentId, filters.employee_id, search])

  return (
    <div className="space-y-4">
      <FilterBar>
        <Field label="Search employee">
          <TextInput
            value={search}
            placeholder="Name or employee ID"
            onChange={setSearch}
          />
        </Field>
        <Field label="Department">
          <Select
            value={departmentId}
            onChange={setDepartmentId}
            options={(options?.departments ?? []).map((department) => ({
              value: String(department.id),
              label: department.name,
            }))}
            placeholder="All departments"
          />
        </Field>
        <div className="ml-auto flex items-center gap-2">
          <Badge tone="neutral">{rows.length} employees</Badge>
        </div>
      </FilterBar>

      {selected !== null ? (
        <EmployeeDetailPanel
          employeeId={selected}
          filters={filters}
          onClose={() => setSelected(null)}
        />
      ) : null}

      {employees.loading && !employees.data ? (
        <LoadingPanel label="Loading employee performance" rows={4} />
      ) : employees.error ? (
        <ErrorPanel message={employees.error} onRetry={employees.refresh} />
      ) : rows.length === 0 ? (
        <EmptyState
          title="No employees match"
          message={
            search || departmentId
              ? 'Adjust the search or department filter.'
              : 'No employee performance records exist for this period.'
          }
        />
      ) : (
        <Table>
          <thead>
            <tr>
              <Th>Rank</Th>
              <Th>Employee</Th>
              <Th>Department</Th>
              <Th className="text-right">Assigned</Th>
              <Th className="text-right">Completed</Th>
              <Th className="text-right">Pending</Th>
              <Th className="text-right">Submitted reports</Th>
              <Th className="text-right">Late</Th>
              <Th className="text-right">Completion</Th>
              <Th className="text-right">Target</Th>
              <Th>Status</Th>
              <Th className="text-right">Detail</Th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.employee_id ?? row.employee_name} className="border-b border-slate-100 last:border-0">
                <Td className="text-slate-500 tabular-nums">{row.rank ?? '—'}</Td>
                <Td>
                  <p className="font-semibold text-slate-800">{row.employee_name ?? '—'}</p>
                  <p className="text-[11px] text-slate-400">{row.employee_number ?? 'No employee ID'}</p>
                </Td>
                <Td className="text-slate-600 text-xs">{row.department_name ?? '—'}</Td>
                <Td className="text-right tabular-nums">{numOrNa(row.total_tasks)}</Td>
                <Td className="text-right tabular-nums">{numOrNa(row.completed_tasks)}</Td>
                <Td className="text-right tabular-nums">{numOrNa(row.pending_tasks)}</Td>
                <Td className="text-right tabular-nums">{numOrNa(row.reports_submitted)}</Td>
                <Td className={`text-right tabular-nums ${row.late_reports > 0 ? 'text-red-600 font-bold' : ''}`}>
                  {numOrNa(row.late_reports)}
                </Td>
                <Td className="text-right tabular-nums">{pctOf(row.completion_rate, row.total_tasks)}</Td>
                <Td className="text-right tabular-nums">{pctOf(row.target_achievement, row.total_targets)}</Td>
                <Td>
                  {row.performance_status ? (
                    <StatusBadge status={row.performance_status} label={row.performance_status} />
                  ) : (
                    <span className="text-xs text-slate-400">N/A</span>
                  )}
                </Td>
                <Td className="text-right">
                  {row.employee_id !== null ? (
                    <Button
                      variant="ghost"
                      className="!px-2 !py-1 !text-xs"
                      onClick={() => setSelected(selected === row.employee_id ? null : row.employee_id)}
                    >
                      {selected === row.employee_id ? 'Hide' : 'View'}
                    </Button>
                  ) : null}
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </div>
  )
}

function EmployeeDetailPanel({
  employeeId,
  filters,
  onClose,
}: {
  employeeId: number
  filters: ReportingFilters
  onClose: () => void
}) {
  const detail = useReportingResource<EmployeePerformanceDetail>(
    useCallback(
      () => getEmployeePerformanceDetail(employeeId, toReportFilters(filters)),
      [employeeId, filters],
    ),
    `hub-employee-detail-${employeeId}-${JSON.stringify(filters)}`,
    'Unable to load the employee performance detail.',
  )

  return (
    <Panel
      title={`Employee detail — ${detail.data?.employee_name ?? 'Loading'}`}
      description="Individual performance detail with recent task assignments."
      actions={
        <Button variant="ghost" className="!px-2 !py-1 !text-xs" onClick={onClose}>
          Close
        </Button>
      }
    >
      {detail.loading && !detail.data ? (
        <LoadingPanel label="Loading employee detail" rows={2} />
      ) : detail.error ? (
        <ErrorPanel message={detail.error} onRetry={detail.refresh} />
      ) : detail.data ? (
        <div className="space-y-3">
          <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
            <div>
              <p className="font-bold uppercase tracking-wider text-slate-500">Employee ID</p>
              <p className="text-slate-800">{detail.data.employee_number ?? '—'}</p>
            </div>
            <div>
              <p className="font-bold uppercase tracking-wider text-slate-500">Department</p>
              <p className="text-slate-800">{detail.data.department_name ?? '—'}</p>
            </div>
            <div>
              <p className="font-bold uppercase tracking-wider text-slate-500">Completion</p>
              <p className="text-slate-800">{pctOf(detail.data.completion_rate, detail.data.total_tasks)}</p>
            </div>
            <div>
              <p className="font-bold uppercase tracking-wider text-slate-500">Target achievement</p>
              <p className="text-slate-800">{pctOf(detail.data.target_achievement, detail.data.total_targets)}</p>
            </div>
            <div>
              <p className="font-bold uppercase tracking-wider text-slate-500">Next due</p>
              <p className="text-slate-800">{shortDate(detail.data.next_due_date)}</p>
            </div>
          </div>

          {detail.data.recent_assignments.length === 0 ? (
            <EmptyState title="No recent assignments" message="This employee has no task assignments on record." />
          ) : (
            <Table>
              <thead>
                <tr>
                  <Th>Task</Th>
                  <Th>Department</Th>
                  <Th>Status</Th>
                  <Th>Due</Th>
                  <Th>Completed</Th>
                </tr>
              </thead>
              <tbody>
                {detail.data.recent_assignments.map((assignment) => (
                  <tr key={assignment.assignment_id} className="border-b border-slate-100 last:border-0">
                    <Td>
                      <p className="font-semibold text-slate-800">{assignment.task_title ?? '—'}</p>
                      <p className="text-[11px] text-slate-400">{assignment.task_code ?? '—'}</p>
                    </Td>
                    <Td className="text-xs text-slate-600">{assignment.department_name ?? '—'}</Td>
                    <Td>
                      <StatusBadge status={assignment.status} label={humanise(assignment.status)} />
                    </Td>
                    <Td className="text-xs text-slate-500">{shortDate(assignment.due_date)}</Td>
                    <Td className="text-xs text-slate-500">{dateTime(assignment.completed_at)}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          )}
        </div>
      ) : null}
    </Panel>
  )
}
