/**
 * AdminPeopleRolesPage.tsx
 *
 * Roles & Access tab of the People Management module.
 * Reads the RBAC catalogue from GET /admin/roles and allows Admin to manage a
 * non-admin role's permission set (PUT /admin/roles/{role}/permissions).
 *
 * Two guards mirror the server exactly:
 *  - the Administrator role is locked — its permission set is the invariant;
 *  - every "elevated" permission (users.* / roles.* / permissions.manage /
 *    settings.manage) stays disabled so it can never be attached to a lesser
 *    role and accidentally recreate admin access.
 *
 * The overview deliberately shows counts, not permission chips. Permissions are
 * only surfaced inside the grouped editor and the collapsible catalog, where
 * each entry leads with its readable label and keeps the technical code as
 * secondary text.
 */

import { useCallback, useEffect, useMemo, useState } from 'react'
import { ApiError } from '../../services/api'
import {
  AdminPage,
  Badge,
  Button,
  EmptyState,
  ErrorPanel,
  LoadingPanel,
  Modal,
  Panel,
  StatCard,
  TextInput,
  humanise,
} from '../../components/admin/AdminUi'
import {
  getRolesAccess,
  updateRolePermissions,
  type RolesCatalogue,
} from '../../services/peopleManagementService'

// ─── Types ────────────────────────────────────────────────────────────────────

type RoleSummary = RolesCatalogue['roles'][number]
type PermissionGroup = RolesCatalogue['permissions'][number]
type PermissionItem = PermissionGroup['permissions'][number]

// ─── Helpers ──────────────────────────────────────────────────────────────────

const ROLE_TONE: Record<string, string> = {
  admin: 'danger',
  hr: 'info',
  department_head: 'warn',
  employee: 'ok',
}

const ROLE_SHORT_LABEL: Record<string, string> = {
  hr: 'HR',
}

/** Case-insensitive search across a permission's label, code and module. */
function filterGroups(groups: PermissionGroup[], query: string): PermissionGroup[] {
  const term = query.trim().toLowerCase()
  if (!term) return groups

  return groups
    .map((group) => {
      const moduleMatches = `${group.label} ${group.group}`.toLowerCase().includes(term)
      const permissions = moduleMatches
        ? group.permissions
        : group.permissions.filter((permission) =>
            `${permission.label} ${permission.name}`.toLowerCase().includes(term),
          )

      return { ...group, permissions }
    })
    .filter((group) => group.permissions.length > 0)
}

/** Number of selectable (non-Administrator) permissions in a group. */
function selectableNames(group: PermissionGroup): string[] {
  return group.permissions.filter((permission) => !permission.elevated).map((p) => p.name)
}

function Chevron({ expanded }: { expanded: boolean }) {
  return (
    <svg
      viewBox="0 0 20 20"
      fill="none"
      className={`w-3.5 h-3.5 shrink-0 text-slate-400 transition-transform ${
        expanded ? 'rotate-90' : ''
      }`}
      aria-hidden="true"
    >
      <path d="M7 5l6 5-6 5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
    </svg>
  )
}

function PermissionCode({ permission }: { permission: PermissionItem }) {
  return (
    <code className="block font-mono text-[11px] leading-tight text-slate-400 truncate">
      {permission.name}
    </code>
  )
}

// ─── Role overview ────────────────────────────────────────────────────────────

function RoleCard({ role, onEdit }: { role: RoleSummary; onEdit: () => void }) {
  return (
    <Panel
      title={role.label || humanise(role.name)}
      description={role.description ?? 'No description provided.'}
      actions={
        role.is_locked ? (
          <Badge tone="danger">Administrator only</Badge>
        ) : (
          <Button
            variant="secondary"
            className="text-xs py-1.5 px-3"
            onClick={onEdit}
          >
            Edit permissions
          </Button>
        )
      }
    >
      <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-500">
        <Badge tone={ROLE_TONE[role.name] ?? 'neutral'}>
          {ROLE_SHORT_LABEL[role.name] ?? humanise(role.name)}
        </Badge>
        <span className="font-semibold text-slate-700 tabular-nums">
          {role.users_count} {role.users_count === 1 ? 'user' : 'users'}
        </span>
        <span className="font-semibold text-slate-700 tabular-nums">
          {role.permissions.length} {role.permissions.length === 1 ? 'permission' : 'permissions'}{' '}
          assigned
        </span>
        {role.is_locked ? (
          <span className="text-slate-400">Fixed system role</span>
        ) : null}
      </div>
    </Panel>
  )
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function AdminPeopleRolesPage() {
  const [data, setData] = useState<RolesCatalogue | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  // Permission editor state
  const [editingRole, setEditingRole] = useState<RoleSummary | null>(null)
  const [selected, setSelected] = useState<string[]>([])
  const [editorQuery, setEditorQuery] = useState('')
  const [collapsedModules, setCollapsedModules] = useState<string[]>([])
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  // Catalog state
  const [catalogQuery, setCatalogQuery] = useState('')
  const [openCatalogModules, setOpenCatalogModules] = useState<string[]>([])

  const load = useCallback(async () => {
    setLoading(true)
    setLoadError(null)
    try {
      const catalogue = await getRolesAccess()
      setData(catalogue)
    } catch (cause) {
      setLoadError(
        cause instanceof ApiError
          ? cause.message
          : 'Failed to load roles and permissions. Ensure you hold the roles/permissions permission.',
      )
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const editorGroups = useMemo(
    () => filterGroups(data?.permissions ?? [], editorQuery),
    [data, editorQuery],
  )

  const catalogGroups = useMemo(
    () => filterGroups(data?.permissions ?? [], catalogQuery),
    [data, catalogQuery],
  )

  const openEdit = (role: RoleSummary) => {
    setEditingRole(role)
    setSelected([...role.permissions])
    setEditorQuery('')
    setCollapsedModules([])
    setFormError(null)
  }

  const closeEdit = () => {
    setEditingRole(null)
    setFormError(null)
  }

  const toggle = (name: string) => {
    setFormError(null)
    setSelected((current) =>
      current.includes(name) ? current.filter((p) => p !== name) : [...current, name],
    )
  }

  const toggleEditorModule = (group: string) => {
    setCollapsedModules((current) =>
      current.includes(group) ? current.filter((g) => g !== group) : [...current, group],
    )
  }

  const toggleCatalogModule = (group: string) => {
    setOpenCatalogModules((current) =>
      current.includes(group) ? current.filter((g) => g !== group) : [...current, group],
    )
  }

  const selectAllInModule = (group: PermissionGroup) => {
    setFormError(null)
    setSelected((current) => [...new Set([...current, ...selectableNames(group)])])
  }

  const clearAllInModule = (group: PermissionGroup) => {
    setFormError(null)
    const names = new Set(selectableNames(group))
    setSelected((current) => current.filter((name) => !names.has(name)))
  }

  const save = async () => {
    if (!editingRole) return
    setSaving(true)
    setFormError(null)
    try {
      const catalogue = await updateRolePermissions(editingRole.id, [...selected].sort())
      setData(catalogue)
      closeEdit()
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to update role permissions.'
      setFormError(msg)
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <AdminPage title="Roles & Access" subtitle="System roles and their assigned permissions.">
        <LoadingPanel rows={4} />
      </AdminPage>
    )
  }

  if (loadError || !data) {
    return (
      <AdminPage title="Roles & Access" subtitle="System roles and their assigned permissions.">
        <ErrorPanel message={loadError ?? 'Unknown error.'} onRetry={load} />
      </AdminPage>
    )
  }

  const roles = data.roles
  const totalUsers = roles.reduce((sum, role) => sum + role.users_count, 0)
  const editableModules = data.permissions.filter((group) =>
    group.permissions.some((permission) => !permission.elevated),
  )

  return (
    <AdminPage
      title="Roles & Access"
      subtitle="System roles and their assigned permissions. Administrator permissions are fixed; elevated permissions can never be granted to another role."
    >
      {/* Summary cards */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <StatCard label="Roles" value={roles.length} />
        <StatCard label="Permissions" value={data.total_permissions} tone="info" />
        <StatCard
          label="Elevated (Admin-only)"
          value={data.elevated_permissions.length}
          tone="danger"
          hint="Reserved for the Administrator role"
        />
        <StatCard label="Users with access" value={totalUsers} tone="ok" />
      </div>

      {/* Role overview — counts only, no permission chip sprawl */}
      {roles.length === 0 ? (
        <Panel title="Roles">
          <EmptyState title="No roles found" message="The RBAC system has not been seeded." />
        </Panel>
      ) : (
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
          {roles.map((role) => (
            <RoleCard key={role.id} role={role} onEdit={() => openEdit(role)} />
          ))}
        </div>
      )}

      {/* Full permission catalog — searchable, collapsible modules */}
      <Panel
        title="Full Permission Catalog"
        description={`${data.permissions.length} modules · ${data.total_permissions} permissions. Entries marked Admin are reserved for the Administrator role.`}
      >
        <div className="space-y-3">
          <div className="flex flex-col sm:flex-row sm:items-center gap-2">
            <TextInput
              value={catalogQuery}
              onChange={setCatalogQuery}
              placeholder="Search permissions by name, module or code"
              className="w-full sm:max-w-md"
            />
            {catalogQuery.trim() ? (
              <span className="text-xs text-slate-500">
                {catalogGroups.reduce((sum, group) => sum + group.permissions.length, 0)} matches
              </span>
            ) : null}
          </div>

          {catalogGroups.length === 0 ? (
            <EmptyState
              title="No permissions match"
              message="Try a permission name, module or code such as employees.view."
            />
          ) : (
            <div className="space-y-2">
              {catalogGroups.map((group) => {
                const expanded = catalogQuery.trim() !== '' || openCatalogModules.includes(group.group)

                return (
                  <section
                    key={group.group}
                    className="rounded-xl border border-slate-200 overflow-hidden"
                  >
                    <button
                      type="button"
                      onClick={() => toggleCatalogModule(group.group)}
                      aria-expanded={expanded}
                      className="w-full flex items-center gap-2 px-3 py-2.5 bg-slate-50 hover:bg-slate-100 text-left"
                    >
                      <Chevron expanded={expanded} />
                      <span className="text-[11px] font-bold uppercase tracking-wider text-slate-600">
                        {group.label}
                      </span>
                      <span className="ml-auto text-[11px] text-slate-400 tabular-nums">
                        {group.permissions.length}
                      </span>
                    </button>

                    {expanded ? (
                      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-x-4 gap-y-1 px-3 py-3 bg-white">
                        {group.permissions.map((permission) => (
                          <div
                            key={permission.name}
                            className={`min-w-0 py-1 ${
                              permission.elevated ? 'text-red-700' : 'text-slate-700'
                            }`}
                          >
                            <div className="flex items-center gap-1.5">
                              <span className="text-sm truncate">
                                {permission.label || permission.name}
                              </span>
                              {permission.elevated ? <Badge tone="danger">Admin</Badge> : null}
                            </div>
                            <PermissionCode permission={permission} />
                          </div>
                        ))}
                      </div>
                    ) : null}
                  </section>
                )
              })}
            </div>
          )}
        </div>
      </Panel>

      {/* ── Edit role permissions ── */}
      {editingRole ? (
        <Modal
          open
          title={`Edit permissions — ${editingRole.label || humanise(editingRole.name)}`}
          onClose={closeEdit}
          footer={
            <>
              <Button variant="secondary" onClick={closeEdit} disabled={saving}>
                Cancel
              </Button>
              <Button onClick={save} disabled={saving}>
                {saving ? 'Saving…' : 'Save permissions'}
              </Button>
            </>
          }
        >
          <div className="space-y-4">
            {formError ? (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                {formError}
              </p>
            ) : null}

            <div className="flex flex-col sm:flex-row sm:items-center gap-2">
              <TextInput
                value={editorQuery}
                onChange={setEditorQuery}
                placeholder="Search permissions by name or code"
                className="w-full sm:flex-1"
              />
              <p className="text-xs text-slate-500 whitespace-nowrap">
                {selected.length} of {editableModules.reduce(
                  (sum, group) => sum + selectableNames(group).length,
                  0,
                )}{' '}
                selected
              </p>
            </div>

            <p className="text-xs text-slate-500">
              Elevated permissions stay reserved for the Administrator role and cannot be
              selected. Select all / Clear apply to the permissions visible in each module.
            </p>

            {editorGroups.length === 0 ? (
              <EmptyState
                title="No permissions match"
                message="Try a different permission name or code."
              />
            ) : (
              <div className="rounded-xl border border-slate-200 divide-y divide-slate-100">
                {editorGroups.map((group) => {
                  const expanded = editorQuery.trim() !== '' || !collapsedModules.includes(group.group)
                  const selectable = selectableNames(group)
                  const selectedInGroup = group.permissions.filter((permission) =>
                    selected.includes(permission.name),
                  ).length

                  return (
                    <section key={group.group}>
                      <div className="flex flex-wrap items-center gap-2 px-3 py-2 bg-slate-50">
                        <button
                          type="button"
                          onClick={() => toggleEditorModule(group.group)}
                          aria-expanded={expanded}
                          className="flex min-w-0 flex-1 items-center gap-2 text-left"
                        >
                          <Chevron expanded={expanded} />
                          <span className="text-[11px] font-bold uppercase tracking-wider text-slate-600">
                            {group.label}
                          </span>
                          <span className="text-[11px] text-slate-400 tabular-nums">
                            {selectedInGroup}/{group.permissions.length}
                          </span>
                        </button>

                        <div className="flex items-center gap-1">
                          <button
                            type="button"
                            onClick={() => selectAllInModule(group)}
                            disabled={selectable.length === 0}
                            aria-label={`Select all ${group.label} permissions`}
                            className="px-2 py-1 rounded-md text-[11px] font-bold text-jpc-700 hover:bg-jpc-50 disabled:opacity-40 disabled:cursor-not-allowed"
                          >
                            Select all
                          </button>
                          <button
                            type="button"
                            onClick={() => clearAllInModule(group)}
                            disabled={selectable.length === 0}
                            aria-label={`Clear all ${group.label} permissions`}
                            className="px-2 py-1 rounded-md text-[11px] font-bold text-slate-500 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed"
                          >
                            Clear
                          </button>
                        </div>
                      </div>

                      {expanded ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-1.5 p-3">
                          {group.permissions.map((permission) => {
                            const checked = selected.includes(permission.name)

                            return (
                              <label
                                key={permission.name}
                                title={
                                  permission.elevated
                                    ? 'Reserved for the Administrator role'
                                    : permission.name
                                }
                                className={`flex items-start gap-2.5 rounded-lg border px-2.5 py-2 ${
                                  permission.elevated
                                    ? 'bg-red-50/60 border-red-100 opacity-75'
                                    : 'border-transparent hover:bg-slate-50 cursor-pointer'
                                }`}
                              >
                                <input
                                  type="checkbox"
                                  className="mt-0.5 rounded border-slate-300 text-jpc-600 focus:ring-jpc-500 disabled:cursor-not-allowed"
                                  checked={checked}
                                  disabled={permission.elevated}
                                  onChange={() => toggle(permission.name)}
                                  aria-label={`${permission.label || permission.name} (${permission.name})`}
                                />
                                <span className="min-w-0 flex-1">
                                  <span className="block text-sm leading-snug text-slate-800">
                                    {permission.label || permission.name}
                                  </span>
                                  <PermissionCode permission={permission} />
                                </span>
                                {permission.elevated ? (
                                  <Badge tone="danger">Admin only</Badge>
                                ) : null}
                              </label>
                            )
                          })}
                        </div>
                      ) : null}
                    </section>
                  )
                })}
              </div>
            )}
          </div>
        </Modal>
      ) : null}
    </AdminPage>
  )
}
