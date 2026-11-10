import { afterEach, describe, expect, it, vi } from 'vitest'

import {
  createDepartment,
  getDepartmentSummary,
  getRolesAccess,
  listDepartments,
  updateDepartment,
  updateRolePermissions,
} from './peopleManagementService'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => null },
    json: async () => payload,
  }
}

describe('peopleManagementService — roles contract', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.clearAllMocks()
  })

  it('requests the roles catalogue at exactly /api/v1/admin/roles', async () => {
    const fetchMock = vi.fn(async (_url: string, _init?: RequestInit) => json({ success: true, data: { roles: [] } }))
    vi.stubGlobal('fetch', fetchMock)

    await getRolesAccess()

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/admin/roles')
  })

  it('updates a role at /api/v1/admin/roles/{id}/permissions with a JSON body', async () => {
    const fetchMock = vi.fn(async (_url: string, _init?: RequestInit) => json({ success: true, data: { roles: [] } }))
    vi.stubGlobal('fetch', fetchMock)

    await updateRolePermissions(2, ['roles.view'])

    expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/admin/roles/2/permissions')
    expect(fetchMock.mock.calls[0][1]).toMatchObject({
      method: 'PUT',
      body: JSON.stringify({ permissions: ['roles.view'] }),
    })
  })
})

describe('peopleManagementService — departments contract', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.clearAllMocks()
  })

  it('does not repeat the /api/v1 prefix when listing departments', async () => {
    const fetchMock = vi.fn(async (_url: string, _init?: RequestInit) => json({ success: true, data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    await listDepartments()

    expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/admin/departments')
  })

  it('requests the department summary once under the versioned prefix', async () => {
    const fetchMock = vi.fn(async (_url: string, _init?: RequestInit) => json({ success: true, data: {} }))
    vi.stubGlobal('fetch', fetchMock)

    await getDepartmentSummary()

    expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/admin/departments/summary')
  })

  it('posts a single-encoded JSON object when creating a department', async () => {
    const fetchMock = vi.fn(async (_url: string, _init?: RequestInit) => json({ success: true, data: {} }))
    vi.stubGlobal('fetch', fetchMock)

    await createDepartment({
      name: 'Bachelor of Science in Criminology',
      code: 'BSC',
      status: 'active',
    })

    expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/admin/departments')
    const init = fetchMock.mock.calls[0][1] as RequestInit
    expect(init.method).toBe('POST')

    // The body must be a JSON *object*, never a stringified string. A double
    // encoded body makes Laravel see a scalar payload and report the name as
    // missing ("Please enter a department name.").
    expect(typeof init.body).toBe('string')
    expect(JSON.parse(init.body as string)).toEqual({
      name: 'Bachelor of Science in Criminology',
      code: 'BSC',
      status: 'active',
    })
  })

  it('puts a single-encoded JSON object when updating a department', async () => {
    const fetchMock = vi.fn(async (_url: string, _init?: RequestInit) => json({ success: true, data: {} }))
    vi.stubGlobal('fetch', fetchMock)

    await updateDepartment(7, {
      name: 'College of Arts & Sciences (O\'Brien Hall)',
      code: null,
      status: 'active',
    })

    expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/admin/departments/7')
    const init = fetchMock.mock.calls[0][1] as RequestInit
    expect(init.method).toBe('PUT')
    expect(JSON.parse(init.body as string)).toEqual({
      name: "College of Arts & Sciences (O'Brien Hall)",
      code: null,
      status: 'active',
    })
  })
})
