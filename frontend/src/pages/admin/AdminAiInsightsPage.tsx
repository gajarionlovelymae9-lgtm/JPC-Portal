import { useState } from 'react'
import {
  generateAdminAiInsight,
  getAdminAiInsightOptions,
  getAdminAiInsights,
  type AiAnalysisRun,
  type AiInsightItem,
} from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import {
  AdminPage,
  Badge,
  Button,
  EmptyState,
  ErrorPanel,
  Field,
  FilterBar,
  LoadingPanel,
  Panel,
  SampleDataNotice,
  Select,
  StatCard,
  Table,
  Td,
  TextInput,
  Th,
  dateTime,
  humanise,
  num,
  pct,
} from '../../components/admin/AdminUi'

/**
 * Admin section 15 - AI Insights.
 *
 * HONESTY CONTRACT, mirrored from AdminInsightService:
 *  - The `ai_insights` table is the only source of generated insight content.
 *    When it is empty the page says "No AI insight available" instead of
 *    rendering placeholder cards.
 *  - Development seeder output (`jpc.sample`) is never counted as live. The
 *    backend excludes those rows from the live KPIs and reports them
 *    separately so the UI can label them honestly.
 *  - "Connected" is only shown after a real provider request has actually
 *    been recorded, never merely because a key exists in .env.
 */
export default function AdminAiInsightsPage() {
  const [type, setType] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [scope, setScope] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const [employeeId, setEmployeeId] = useState('')
  const [reportId, setReportId] = useState('')
  const [genType, setGenType] = useState('')
  const [period, setPeriod] = useState('')
  const [generating, setGenerating] = useState(false)
  const [generateError, setGenerateError] = useState<string | null>(null)
  const [generateSuccess, setGenerateSuccess] = useState<string | null>(null)

  const options = useAdminResource(() => getAdminAiInsightOptions())
  const resource = useAdminResource(
    () => getAdminAiInsights({ type, department_id: departmentId, scope, from, to }),
    [type, departmentId, scope, from, to],
  )

  const data = resource.data
  const validation = data?.validation
  const provider = data?.provider_status
  const counts = data?.counts

  const insights = data?.insights ?? []
  const hasSample = Boolean(validation?.sample?.present || provider?.sample_data_present)

  async function handleGenerate() {
    setGenerating(true)
    setGenerateError(null)
    setGenerateSuccess(null)

    try {
      const response = await generateAdminAiInsight({
        type: genType || undefined,
        employee_id: employeeId || undefined,
        report_id: reportId || undefined,
        period: period || undefined,
      })
      setGenerateSuccess(
        `Insight #${response.data.insight.id} generated and stored. Human verification is required before administrative action.`,
      )
      resource.reload()
    } catch (error) {
      setGenerateError(error instanceof Error ? error.message : 'AI insight generation failed.')
    } finally {
      setGenerating(false)
    }
  }

  return (
    <AdminPage title="JPC AIDE - AI Insights" subtitle="Intelligent performance analysis, generated insights, and validation provenance">
      <FilterBar>
        <Field label="Insight type">
          <Select
            value={type}
            onChange={setType}
            options={options.data?.types ?? []}
            placeholder="All types"
          />
        </Field>
        <Field label="Department">
          <Select
            value={departmentId}
            onChange={setDepartmentId}
            options={(options.data?.departments ?? []).map((d) => ({ value: d.id, label: d.name }))}
            placeholder="All departments"
          />
        </Field>
        <Field label="Scope">
          <Select
            value={scope}
            onChange={setScope}
            options={[
              { value: 'institution', label: 'Institution' },
              { value: 'department', label: 'Department' },
              { value: 'employee', label: 'Employee' },
            ]}
            placeholder="All scopes"
          />
        </Field>
        <Field label="From">
          <TextInput type="date" value={from} onChange={setFrom} />
        </Field>
        <Field label="To">
          <TextInput type="date" value={to} onChange={setTo} />
        </Field>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading AI insights" />
      ) : resource.error || !data || !validation || !provider || !counts ? (
        <ErrorPanel message={resource.error ?? 'Unable to load AI insights.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <Panel title="AI provider" description="Actual configured provider and recorded connection state">
            <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-slate-400">Provider</dt>
                <dd className="text-sm font-semibold text-slate-800">{provider.configured_provider}</dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-slate-400">Model</dt>
                <dd className="text-sm font-semibold text-slate-800">{provider.model ?? '-'}</dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-slate-400">Connection</dt>
                <dd>
                  <Badge tone={provider.connected ? 'ok' : 'warn'}>
                    {provider.connected ? 'Connected' : 'Not Connected'}
                  </Badge>
                </dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-slate-400">Data source</dt>
                <dd className="text-sm font-semibold text-slate-800">{provider.data_source_label}</dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-slate-400">Last successful AI request</dt>
                <dd className="text-sm text-slate-700">{dateTime(provider.last_successful_ai_request)}</dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-slate-400">Last successful analysis</dt>
                <dd className="text-sm text-slate-700">{dateTime(provider.last_successful_analysis)}</dd>
              </div>
              <div>
                <dt className="text-[11px] uppercase tracking-wide text-slate-400">Sample data</dt>
                <dd>
                  <Badge tone={provider.sample_data_present ? 'warn' : 'ok'}>
                    {provider.sample_data_present ? 'Present (excluded)' : 'None'}
                  </Badge>
                </dd>
              </div>
            </dl>
            {provider.note ? (
              <p className="mt-3 text-[11px] leading-relaxed text-slate-500">{provider.note}</p>
            ) : null}
          </Panel>

          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-4">
            <StatCard label="Insights stored" value={num(counts.total)} />
            <StatCard label="Reports analyzed" value={num(data.reports_analyzed)} tone="info" />
            <StatCard label="Validations" value={num(validation.total)} tone="info" />
            <StatCard label="Passed" value={num(validation.passed)} tone="ok" />
            <StatCard label="Warnings" value={num(validation.warning)} tone="warn" />
            <StatCard label="Failed" value={num(validation.failed)} tone="danger" />
            <StatCard label="Average score" value={pct(validation.avg_score, 2)} tone="info" />
          </div>

          {hasSample ? (
            <SampleDataNotice title="Development Sample Data Notice">
              Development sample records are available for testing but are excluded from live analytics and employee
              performance metrics.
            </SampleDataNotice>
          ) : null}

          <Panel
            title="Generate evidence-based insight"
            description="Stores a real AI-generated advisory insight from authorized database records. Human verification is required."
          >
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <Field label="Type">
                <Select
                  value={genType}
                  onChange={setGenType}
                  options={options.data?.types ?? []}
                  placeholder="Default type"
                />
              </Field>
              <Field label="Employee">
                <Select
                  value={employeeId}
                  onChange={setEmployeeId}
                  options={options.data?.employees ?? []}
                  placeholder="Select employee"
                />
              </Field>
              <Field label="Report">
                <Select
                  value={reportId}
                  onChange={setReportId}
                  options={options.data?.reports ?? []}
                  placeholder="Select report"
                />
              </Field>
              <Field label="Period">
                <TextInput value={period} onChange={setPeriod} placeholder="YYYY-MM" />
              </Field>
            </div>
            <div className="mt-4 flex flex-wrap items-center gap-3">
              <Button onClick={handleGenerate} disabled={generating || (!employeeId && !reportId)}>
                {generating ? 'Generating…' : 'Generate insight'}
              </Button>
              <p className="text-xs text-slate-500">
                A report selection forces a report insight. Insights are advisory and never approve reports or make
                employment decisions.
              </p>
            </div>
            {generateError ? <p className="mt-3 text-sm text-red-600">{generateError}</p> : null}
            {generateSuccess ? <p className="mt-3 text-sm text-emerald-700">{generateSuccess}</p> : null}
          </Panel>

          <div className="grid lg:grid-cols-2 gap-6">
            <Panel title="Stored insights by type" description="Real rows from ai_insights">
              <ul className="space-y-2">
                {counts.by_type.map((row) => (
                  <li key={row.type} className="flex items-center justify-between gap-3 text-sm">
                    <span className="text-slate-600">{humanise(row.label)}</span>
                    <span className="tabular-nums font-semibold text-slate-800">{num(row.total)}</span>
                  </li>
                ))}
              </ul>
            </Panel>

            <Panel title="Recent live analysis runs" description="Latest non-sample rows from ai_analyses">
              {data.recent_runs.length === 0 ? (
                <EmptyState
                  title="No live analysis runs recorded"
                  message="No real AI analysis run has been stored in ai_analyses yet. Sample runs are listed separately below."
                />
              ) : (
                <ul className="space-y-3">
                  {data.recent_runs.map((run) => (
                    <AnalysisRunRow key={run.id} run={run} />
                  ))}
                </ul>
              )}
            </Panel>
          </div>

          {data.sample_runs.length > 0 ? (
            <Panel
              title="Development sample runs"
              description="Excluded from live metrics and clearly labelled"
              actions={<Badge tone="warn">Sample</Badge>}
            >
              <ul className="space-y-3">
                {data.sample_runs.map((run) => (
                  <AnalysisRunRow key={run.id} run={run} />
                ))}
              </ul>
            </Panel>
          ) : null}

          <Panel
            title="Generated insight content"
            description={data.available ? `${insights.length} shown` : undefined}
          >
            {!data.available ? (
              <EmptyState
                title={data.message ?? 'No AI insight available'}
                message="The ai_insights table has no verified live insight rows matching these filters. Nothing is substituted or estimated."
              />
            ) : insights.length === 0 ? (
              <EmptyState
                title="No insights match these filters"
                message="Try clearing the type, department, scope or date filters."
              />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Type</Th>
                    <Th>Scope</Th>
                    <Th>Subject</Th>
                    <Th>Content</Th>
                    <Th>Generated</Th>
                  </tr>
                </thead>
                <tbody>
                  {insights.map((row) => (
                    <InsightRow key={row.id} row={row} />
                  ))}
                </tbody>
              </Table>
            )}
          </Panel>
        </div>
      )}
    </AdminPage>
  )
}

function AnalysisRunRow({ run }: { run: AiAnalysisRun }) {
  return (
    <li className="border-b border-slate-100 last:border-0 pb-3 last:pb-0">
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-sm font-semibold text-slate-800">
          {run.report ?? `Report #${run.report_id ?? '-'}`}
        </p>
        <Badge tone={run.status === 'completed' ? 'ok' : run.status === 'failed' ? 'danger' : 'warn'}>
          {humanise(run.status)}
        </Badge>
        {run.is_sample_data ? <Badge tone="warn">Sample</Badge> : null}
      </div>
      <p className="text-[11px] text-slate-500 mt-0.5">
        {humanise(run.analysis_type)} · {run.provider ?? 'no provider'} · {run.model ?? 'no model'}
      </p>
      <p className="text-[11px] text-slate-400">{dateTime(run.completed_at ?? run.started_at)}</p>
      {run.error_message ? <p className="text-[11px] text-red-600 mt-0.5">{run.error_message}</p> : null}
    </li>
  )
}

function InsightRow({ row }: { row: AiInsightItem }) {
  const [open, setOpen] = useState(false)
  const long = (row.content ?? '').length > 180

  return (
    <tr className="border-b border-slate-100 last:border-0 align-top">
      <Td>
        <Badge tone="info">{row.type_label ?? humanise(row.type)}</Badge>
      </Td>
      <Td className="text-slate-500 text-xs">{humanise(row.scope)}</Td>
      <Td className="text-slate-700 text-xs">
        {row.employee ?? row.department ?? 'Institution-wide'}
      </Td>
      <Td>
        <p className="text-[13px] text-slate-700 whitespace-pre-wrap">
          {open || !long ? row.content : `${row.content.slice(0, 180)}…`}
        </p>
        {long ? (
          <Button variant="ghost" onClick={() => setOpen((value) => !value)}>
            {open ? 'Show less' : 'Read more'}
          </Button>
        ) : null}
        {row.metadata && open ? (
          <pre className="mt-2 font-mono text-[10px] text-slate-500 bg-slate-50 rounded-lg p-2 whitespace-pre-wrap break-all">
            {JSON.stringify(row.metadata, null, 2)}
          </pre>
        ) : null}
      </Td>
      <Td className="text-slate-500 text-xs whitespace-nowrap">{dateTime(row.generated_at)}</Td>
    </tr>
  )
}
