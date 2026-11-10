import { describe, expect, it } from 'vitest'
import { homePathForRole, managementDashboardPath, PATHS } from './paths'

/**
 * Every authenticated role must resolve to its own dashboard. The generic
 * foundation page is only a fallback for roles this build does not know about.
 */
describe('homePathForRole', () => {
  it('lands an employee on the employee dashboard', () => {
    expect(homePathForRole('employee')).toBe(PATHS.employeeDashboard)
    expect(homePathForRole('employee')).toBe('/employee/dashboard')
  })

  it('keeps each management role on its own dashboard', () => {
    expect(homePathForRole('admin')).toBe(PATHS.adminDashboard)
    expect(homePathForRole('hr')).toBe(PATHS.hrDashboard)
    expect(homePathForRole('department_head')).toBe(PATHS.departmentHeadDashboard)
  })

  it('never resolves a known role to the generic foundation page', () => {
    for (const role of ['employee', 'admin', 'hr', 'department_head']) {
      expect(homePathForRole(role)).not.toBe(PATHS.dashboard)
    }
  })

  it('falls back to the foundation page only for unknown roles', () => {
    expect(homePathForRole('')).toBe(PATHS.dashboard)
    expect(homePathForRole('unknown')).toBe(PATHS.dashboard)
  })
})

describe('managementDashboardPath', () => {
  it('resolves through the same role map', () => {
    expect(managementDashboardPath('employee')).toBe(homePathForRole('employee'))
    expect(managementDashboardPath('hr')).toBe(homePathForRole('hr'))
  })
})
