import { useState } from 'react'
import { getAdminMonitoringOptions, getAdminReportMonitoring } from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import {
  AdminPage,
  Badge,
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
  TextInput,
  Th,
  num,
  pct,
  shortDate,
} from '../../components/admin/AdminUi'

/**
 * Admin section 10 - Report monitoring.
 *
 * `ai_validation_score` is populated from the seeded jpc.sample provider, so
 * the page always shows a provenance notice when any score is present.
 */
export default function AdminReportMonitoringPage() {
  const [departmentId, setDepartmentId] = useState('')
  const [employeeId, setEmployeeId] = useState('')
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [lateOnly, setLateOnly] = useState(false)
  const [page, setPage] = useState(1)

  const options = useAdminResource(() => getAdminMonitoringOptions())
  const resource = useAdminResource(
    () =>
      getAdminReportMonitoring({
        department_id: departmentId,
        employee_id: employeeId,
        status,
        search,
        late: lateOnly ? 1 : '',
        page,
        per_page: 15,
      }),
    [departmentId, employeeId, status, search, lateOnly, page],
  )

  const data = resource.data
  const summary = data?.summary ?? {}

  const apply = (setter: (value: string) => void) => (value: string) => {
    setter(value)
    setPage(1)
  }

  return (
    <AdminPage title="Report Monitoring" subtitle="Submission, review and validation status of every report">
      <FilterBar>
        <Field label="Department">
          <Select
            value={departmentId}
            onChange={apply(setDepartmentId)}
            options={(options.data?.departments ?? []).map((d) => ({ value: d.id, label: d.name }))}
            placeholder="All departments"
          />
        </Field>
        <Field label="Employee">
          <Select
            value={employeeId}
            onChange={apply(setEmployeeId)}
            options={(options.data?.employees ?? []).map((e) => ({ value: e.id, label: e.name }))}
            placeholder="All employees"
          />
        </Field>
        <Field label="Status">
          <Select
            value={status}
            onChange={apply(setStatus)}
            options={options.data?.report_statuses ?? []}
            placeholder="All statuses"
          />
        </Field>
        <Field label="Search" className="flex-1 min-w-[180px]">
          <TextInput value={search} onChange={apply(setSearch)} placeholder="Report title" />
        </Field>
        <button
          type="button"
          onClick={() => {
            setLateOnly((value) => !value)
            setPage(1)
          }}
          className={`px-4 py-2 rounded-lg text-sm font-bold border transition ${
            lateOnly
              ? 'bg-red-600 text-white border-red-600'
              : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
          }`}
        >
          Late only
        </button>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading report monitoring" />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load report monitoring.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-6 gap-4">
            <StatCard label="Reports" value={num(data.meta.total)} />
            <StatCard label="Approved" value={num(summary.approved ?? 0)} tone="ok" />
            <StatCard label="Under review" value={num(summary.under_review ?? 0)} tone="info" />
            <StatCard label="Submitted" value={num(summary.submitted ?? 0)} />
            <StatCard label="Needs revision" value={num(summary.revision_required ?? 0)} tone="warn" />
            <StatCard
              label="Late"
              value={num(summary.late ?? 0)}
              tone={(summary.late ?? 0) > 0 ? 'danger' : 'ok'}
            />
          </div>

          <Panel title="Report register" description={`${data.meta.total} reports`}>
            {data.items.length === 0 ? (
              <EmptyState title="No reports match these filters" message="Try clearing a filter to widen the search." />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>Report</Th>
                      <Th>Employee</Th>
                      <Th>Task</Th>
                      <Th>Status</Th>
                      <Th>Due</Th>
                      <Th>Submitted</Th>
                      <Th>AI validation</Th>
                      <Th className="text-right">Approvals</Th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((report) => (
                      <tr key={report.id} className="border-b border-slate-100 last:border-0">
                        <Td>
                          <p className="font-semibold text-slate-800">{report.title}</p>
                          <p className="text-[11px] text-slate-400">{report.department}</p>
                        </Td>
                        <Td>
                          <p className="text-slate-700">{report.employee}</p>
                          <p className="text-[11px] text-slate-400">{report.employee_number}</p>
                        </Td>
                        <Td className="text-slate-500 text-xs">{report.task}</Td>
                        <Td>
                          <div className="flex flex-wrap gap-1">
                            <StatusBadge status={report.status} label={report.status_label} />
                            {report.is_late ? <Badge tone="danger">Late</Badge> : null}
                          </div>
                        </Td>
                        <Td className="text-slate-500 text-xs">{shortDate(report.due_date)}</Td>
                        <Td className="text-slate-500 text-xs">
                          {shortDate(report.submitted_at ?? report.submission_date)}
                        </Td>
                        <Td>
                          <p className="tabular-nums font-semibold">{pct(report.ai_validation_score)}</p>
                          <p className="text-[10px] text-slate-400">{report.ai_status}</p>
                        </Td>
                        <Td className="text-right tabular-nums">
                          {num(report.approvals_count)}
                          <p className="text-[10px] text-slate-400">{report.approval_status}</p>
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>
                {data.items.some((r) => r.ai_validation_score !== null) ? (
                  <SampleDataNotice>
                    Validation scores come from the seeded <strong>jpc.sample</strong> provider
                    (<strong>development-sample</strong> model). They are development sample values, not live AI output.
                  </SampleDataNotice>
                ) : null}
                <Pagination meta={data.meta} onPageChange={setPage} />
              </>
            )}
          </Panel>
        </div>
      )}
    </AdminPage>
  )
}
