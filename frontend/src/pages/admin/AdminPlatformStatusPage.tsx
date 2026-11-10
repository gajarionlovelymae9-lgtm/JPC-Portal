import { getAdminPlatformStatus } from '../../services/adminDashboardService'
import { useAdminResource } from '../../hooks/useAdminResource'
import {
  AdminPage,
  Badge,
  Button,
  ErrorPanel,
  LoadingPanel,
  Panel,
  StatusBadge,
  dateTime,
  humanise,
} from '../../components/admin/AdminUi'

const STATE_STYLES: Record<string, { dot: string; ring: string }> = {
  connected: { dot: 'bg-emerald-500', ring: 'ring-emerald-100' },
  warning: { dot: 'bg-amber-500', ring: 'ring-amber-100' },
  disconnected: { dot: 'bg-red-500', ring: 'ring-red-100' },
}

const OVERALL_COPY: Record<string, string> = {
  ok: 'All probed components reported healthy.',
  degraded: 'At least one component reported degraded or unavailable.',
  error: 'One or more components are unavailable.',
}

/**
 * Admin section 14 - Platform Status.
 *
 * Every value comes from the backend's existing SystemHealthService plus live
 * config. Nothing here is hardcoded, so the card cannot drift from the running
 * application.
 */
export default function AdminPlatformStatusPage() {
  const resource = useAdminResource(() => getAdminPlatformStatus())
  const data = resource.data

  return (
    <AdminPage title="Platform Status" subtitle="Live service health and the deployed architecture">
      {resource.loading ? (
        <LoadingPanel label="Checking platform status" />
      ) : resource.error || !data ? (
        <ErrorPanel message={resource.error ?? 'Unable to load platform status.'} onRetry={resource.reload} />
      ) : (
        <div className="space-y-6">
          <Panel
            title="Overall"
            description={`Checked ${dateTime(data.checked_at)}`}
            actions={
              <div className="flex items-center gap-2">
                <StatusBadge status={data.overall} label={humanise(data.overall)} />
                <Button variant="secondary" onClick={resource.reload}>
                  Re-check
                </Button>
              </div>
            }
          >
            <p className="text-sm text-slate-600">{OVERALL_COPY[data.overall] ?? humanise(data.overall)}</p>
            <div className="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Environment</p>
                <p className="text-sm font-semibold text-slate-800">{humanise(data.environment)}</p>
              </div>
              <div>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">API version</p>
                <p className="text-sm font-semibold text-slate-800">{data.api_version}</p>
              </div>
              <div>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Last backup</p>
                <p className="text-sm font-semibold text-slate-800">
                  {data.last_backup ? dateTime(data.last_backup) : 'No backup recorded'}
                </p>
              </div>
              <div>
                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Services probed</p>
                <p className="text-sm font-semibold text-slate-800">{data.services.length}</p>
              </div>
            </div>
          </Panel>

          <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
            {data.services.map((service) => {
              const style = STATE_STYLES[service.state] ?? STATE_STYLES.warning
              return (
                <div
                  key={service.key}
                  className="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-3"
                >
                  <span className={`mt-1 w-2.5 h-2.5 rounded-full ${style.dot} ring-4 ${style.ring}`} />
                  <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-800">{service.label}</p>
                    <p className="text-[11px] text-slate-500 break-words">{service.detail}</p>
                    <p className="mt-1.5">
                      <Badge tone={service.state === 'connected' ? 'ok' : service.state === 'warning' ? 'warn' : 'danger'}>
                        {humanise(service.state)}
                      </Badge>
                    </p>
                  </div>
                </div>
              )
            })}
          </div>

          <Panel title="Platform architecture" description="Read from the running configuration">
            <dl className="grid sm:grid-cols-2 gap-x-8 gap-y-4">
              {data.architecture.map((item) => (
                <div key={item.key} className="border-b border-slate-100 pb-3">
                  <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-400">{item.label}</dt>
                  <dd className="text-sm text-slate-800 mt-0.5">{item.value}</dd>
                </div>
              ))}
            </dl>
          </Panel>
        </div>
      )}
    </AdminPage>
  )
}
