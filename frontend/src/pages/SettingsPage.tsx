import { useAdminResource } from '../hooks/useAdminResource'
import {
  AdminPage,
  ErrorPanel,
  LoadingPanel,
  Panel,
  SampleDataNotice,
  StatCard,
  Table,
  Td,
  Th,
  humanise,
  num,
} from '../components/admin/AdminUi'
import { getRbacMatrix } from '../services/adminDashboardService'

/**
 * Settings (Phase 3) — read-only RBAC matrix.
 *
 * Renders GET /api/v1/admin/rbac: every permission in the catalog down the
 * left, every role across the top, granted/not-granted marks per cell. The
 * screen exposes no editing controls — role assignments are seeded from the
 * backend permission catalog and this page exists to inspect them.
 */
export default function SettingsPage() {
  const resource = useAdminResource(() => getRbacMatrix(), [])
  const data = resource.data

  const roleKeys = data ? Object.keys(data.roles) : []
  const groups = data ? Object.entries(data.catalog) : []
  const permissionCount = groups.reduce(
    (total, [, permissions]) => total + Object.keys(permissions).length,
    0,
  )

  return (
    <AdminPage
      title="Settings"
      subtitle="Role-based access control: the permission catalog and role assignments, read-only"
    >
      {resource.loading ? (
        <LoadingPanel label="Loading permission matrix" />
      ) : resource.error || !data ? (
        <ErrorPanel
          message={resource.error ?? 'Unable to load the permission matrix.'}
          onRetry={resource.reload}
        />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <StatCard label="Roles" value={num(roleKeys.length)} />
            <StatCard label="Permissions" value={num(permissionCount)} />
            <StatCard label="Permission groups" value={num(groups.length)} />
          </div>

          <Panel
            title="Role → permission matrix"
            description={`${permissionCount} permissions across ${roleKeys.length} roles`}
          >
            <Table>
              <thead>
                <tr>
                  <Th>Group</Th>
                  <Th>Permission</Th>
                  {roleKeys.map((roleKey) => (
                    <Th key={roleKey} className="text-center">
                      {data.roles[roleKey].label}
                    </Th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {groups.flatMap(([groupName, permissions]) =>
                  Object.entries(permissions).map(([name, label], index) => (
                    <tr key={name} className="border-b border-slate-100 last:border-0">
                      <Td className="text-[11px] text-slate-400 whitespace-nowrap">
                        {index === 0 ? humanise(groupName) : ''}
                      </Td>
                      <Td>
                        <p className="font-medium text-slate-800">{label}</p>
                        <p className="text-[10px] text-slate-400 font-mono">{name}</p>
                      </Td>
                      {roleKeys.map((roleKey) => {
                        const granted = data.roles[roleKey].permissions.includes(name)
                        return (
                          <Td key={roleKey} className="text-center">
                            {granted ? (
                              <span className="text-emerald-600 font-black" title="Granted">
                                ✓
                              </span>
                            ) : (
                              <span className="text-slate-300" title="Not granted">
                                —
                              </span>
                            )}
                          </Td>
                        )
                      })}
                    </tr>
                  )),
                )}
              </tbody>
            </Table>
          </Panel>

          <SampleDataNotice>
            <strong>Read-only by design.</strong> Role assignments are seeded from the backend permission
            catalog (App\Enums\Permissions); this screen inspects them and never writes.
          </SampleDataNotice>
        </div>
      )}
    </AdminPage>
  )
}
