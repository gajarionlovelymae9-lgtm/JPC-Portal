import { useState } from 'react'
import {
  getAdminAuditTrail,
  getAdminGovernanceOptions,
  getAdminGovernanceSummary,
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
  Pagination,
  Panel,
  Select,
  StatCard,
  Table,
  Td,
  TextInput,
  Th,
  dateTime,
  humanise,
  num,
} from '../../components/admin/AdminUi'

/**
 * Admin section 13 (audit) - Audit trail.
 *
 * The backend merges `audit_logs` and `activity_logs` into one paginated
 * timeline, so an entry can come from either source. The `source` column keeps
 * that visible rather than presenting the union as a single log table.
 */
export default function AdminAuditTrailPage() {
  const [source, setSource] = useState('')
  const [action, setAction] = useState('')
  const [activityType, setActivityType] = useState('')
  const [entityType, setEntityType] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [expanded, setExpanded] = useState<string | null>(null)

  const options = useAdminResource(() => getAdminGovernanceOptions())
  const overview = useAdminResource(() => getAdminGovernanceSummary())
  const resource = useAdminResource(
    () =>
      getAdminAuditTrail({
        source,
        action,
        activity_type: activityType,
        entity_type: entityType,
        search,
        page,
        per_page: 20,
      }),
    [source, action, activityType, entityType, search, page],
  )

  const data = resource.data
  const summary = overview.data

  const apply = (setter: (value: string) => void) => (value: string) => {
    setter(value)
    setPage(1)
  }

  return (
    <AdminPage title="Audit Trail" subtitle="Unified timeline of audit log and activity log events">
      <FilterBar>
        <Field label="Source">
          <Select value={source} onChange={apply(setSource)} options={options.data?.sources ?? []} placeholder="All sources" />
        </Field>
        <Field label="Audit action">
          <Select value={action} onChange={apply(setAction)} options={options.data?.audit_actions ?? []} placeholder="All actions" />
        </Field>
        <Field label="Activity type">
          <Select
            value={activityType}
            onChange={apply(setActivityType)}
            options={options.data?.activity_types ?? []}
            placeholder="All activity"
          />
        </Field>
        <Field label="Entity type">
          <Select
            value={entityType}
            onChange={apply(setEntityType)}
            options={options.data?.entity_types ?? []}
            placeholder="All entities"
          />
        </Field>
        <Field label="Search" className="flex-1 min-w-[180px]">
          <TextInput value={search} onChange={apply(setSearch)} placeholder="Actor, description or IP" />
        </Field>
      </FilterBar>

      {resource.loading ? (
        <LoadingPanel label="Loading audit trail" />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load audit trail.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <StatCard label="Entries" value={num(data.meta.total)} />
            <StatCard label="Audit records" value={num(summary?.audit_logs.total ?? 0)} tone="info" />
            <StatCard label="Activity records" value={num(summary?.activity_logs.total ?? 0)} tone="info" />
            <StatCard label="Events (7 days)" value={num((summary?.audit_logs.last_7_days ?? 0) + (summary?.activity_logs.last_7_days ?? 0))} />
            <StatCard
              label="Failed logins (7d)"
              value={num(summary?.activity_logs.failed_logins_7d ?? 0)}
              tone={(summary?.activity_logs.failed_logins_7d ?? 0) > 0 ? 'danger' : 'ok'}
            />
          </div>

          <Panel title="Timeline" description={`${data.meta.total} entries`}>
            {data.items.length === 0 ? (
              <EmptyState title="No audit entries match these filters" message="Try clearing a filter to widen the search." />
            ) : (
              <>
                <Table>
                  <thead>
                    <tr>
                      <Th>When</Th>
                      <Th>Source</Th>
                      <Th>Event</Th>
                      <Th>Actor</Th>
                      <Th>Entity</Th>
                      <Th>IP</Th>
                      <Th />
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((entry) => (
                      <tr key={entry.id} className="border-b border-slate-100 last:border-0 align-top">
                        <Td className="text-slate-500 text-xs whitespace-nowrap">{dateTime(entry.created_at)}</Td>
                        <Td>
                          <Badge tone={entry.source === 'audit_log' ? 'info' : 'purple'}>
                            {entry.source === 'audit_log' ? 'Audit Log' : 'Activity Log'}
                          </Badge>
                        </Td>
                        <Td>
                          <p className="font-semibold text-slate-800">{humanise(entry.event)}</p>
                          <p className="text-[11px] text-slate-500 max-w-sm">{entry.description}</p>
                        </Td>
                        <Td>
                          <p className="text-slate-700 text-xs">{entry.actor}</p>
                          {entry.actor_email ? (
                            <p className="text-[11px] text-slate-400">{entry.actor_email}</p>
                          ) : null}
                        </Td>
                        <Td className="text-slate-500 text-xs">
                          {entry.entity || '—'}
                          {entry.entity_type ? (
                            <p className="text-[11px] text-slate-400">{humanise(entry.entity_type)}</p>
                          ) : null}
                        </Td>
                        <Td className="text-slate-500 text-xs font-mono">{entry.ip_address ?? '—'}</Td>
                        <Td>
                          {entry.changes.length > 0 || entry.metadata ? (
                            <Button
                              variant="ghost"
                              onClick={() => setExpanded(expanded === entry.id ? null : entry.id)}
                            >
                              {expanded === entry.id ? 'Hide' : 'Details'}
                            </Button>
                          ) : null}
                        </Td>
                      </tr>
                    ))}
                  </tbody>
                </Table>

                {expanded
                  ? data.items
                      .filter((entry) => entry.id === expanded)
                      .map((entry) => (
                        <div key={entry.id} className="mt-4 p-4 bg-slate-50 rounded-xl text-xs space-y-3">
                          {entry.changes.length > 0 ? (
                            <div>
                              <p className="font-bold text-slate-700 mb-1">Changes</p>
                              <ul className="space-y-1">
                                {entry.changes.map((change, index) => (
                                  <li key={index} className="font-mono text-slate-600">
                                    {change.field}: <span className="text-red-600 line-through">{change.old ?? 'null'}</span>{' '}
                                    → <span className="text-emerald-700">{change.new ?? 'null'}</span>
                                  </li>
                                ))}
                              </ul>
                            </div>
                          ) : null}
                          {entry.metadata ? (
                            <div>
                              <p className="font-bold text-slate-700 mb-1">Metadata</p>
                              <pre className="font-mono text-[11px] text-slate-600 whitespace-pre-wrap break-all">
                                {JSON.stringify(entry.metadata, null, 2)}
                              </pre>
                            </div>
                          ) : null}
                          {entry.user_agent ? (
                            <p className="text-slate-500">User agent: {entry.user_agent}</p>
                          ) : null}
                        </div>
                      ))
                  : null}

                <Pagination meta={data.meta} onPageChange={setPage} />
              </>
            )}
          </Panel>
        </div>
      )}
    </AdminPage>
  )
}
