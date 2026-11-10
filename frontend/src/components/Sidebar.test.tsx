import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'

import { Sidebar } from './Sidebar'
import { AuthContext, type AuthContextValue } from '../auth/useAuth'
import type { UserProfile } from '../types/auth'

/**
 * The sidebar is built from role-partitioned section lists, so an item only
 * disappears for one role when that role's list drops the key. These tests pin
 * the Report Exports withdrawal from HR without touching the roles that keep
 * the export queue, plus the admin portal's collapsible group structure.
 */

function profile(role: string, permissions: string[]): UserProfile {
  return {
    id: 1,
    name: `JPC ${role}`,
    email: `${role}@jpc.local`,
    status: 'active',
    role,
    permissions,
    last_login_at: null,
  }
}

function renderSidebar(role: string, path = '/', extraPermissions: string[] = []) {
  const user = profile(role, [
    'dashboard.view',
    'reports.view',
    'analytics.view',
    'hr.dashboard.view',
    'employees.view',
    'tasks.view',
    'targets.view',
    ...extraPermissions,
  ])

  const value: AuthContextValue = {
    user,
    loading: false,
    error: null,
    login: vi.fn(),
    logout: vi.fn(),
    clearError: vi.fn(),
  }

  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthContext.Provider value={value}>
        <Sidebar open onClose={vi.fn()} />
      </AuthContext.Provider>
    </MemoryRouter>,
  )
}

function nav(): HTMLElement {
  return screen.getByRole('navigation', { name: /main navigation/i })
}

describe('Sidebar', () => {
  it('renders the HR sections in the approved order with every HR module linked', () => {
    const view = renderSidebar('hr', '/', [
      'planning.view',
      'notifications.view',
      'achievements.view',
    ])

    const links = nav()
    const names = within(links).getAllByRole('link').map((link) => link.textContent?.trim())

    // The approved information architecture — grouping and order are pinned.
    expect(names).toEqual([
      'HR Dashboard',
      'Employees',
      'Tasks',
      'Planning',
      'Targets',
      'Calendar',
      'Messages',
      'Notifications',
      'Alert Preferences',
      'Feedback',
      'Reports & Analytics',
      'Achievements & Badges',
    ])

    // A distinguishing icon is rendered for every HR entry.
    const iconCount = view.baseElement.querySelectorAll('.sidebar__link-icon').length
    expect(iconCount).toBe(12)
  })

  it('withholds Report Exports and the achievement report from HR', () => {
    renderSidebar('hr')

    const links = nav()
    expect(within(links).queryByRole('link', { name: 'Report Exports' })).not.toBeInTheDocument()
    expect(
      within(links).queryByRole('link', { name: 'Achievement Report' }),
    ).not.toBeInTheDocument()
  })

  it('keeps the rest of the HR monitoring sections intact', () => {
    const view = renderSidebar('hr')

    const links = nav()
    expect(within(links).getByRole('link', { name: 'HR Dashboard' })).toBeInTheDocument()
    expect(within(links).getByRole('link', { name: 'Employees' })).toBeInTheDocument()
    expect(within(links).getByRole('link', { name: 'Tasks' })).toBeInTheDocument()
    expect(within(links).getByRole('link', { name: 'Reports & Analytics' })).toBeInTheDocument()
    // AI Insights was withdrawn from HR: no menu entry, no star icon and no
    // direct navigation link may remain in the HR portal.
    expect(within(links).queryByRole('link', { name: 'AI Insights' })).not.toBeInTheDocument()
    expect(view.baseElement.querySelector('a[href="/ai/insights"]')).toBeNull()
    // The Data Quality surface is Admin-only now: HR must not see it.
    expect(within(links).queryByRole('link', { name: 'Data Quality' })).not.toBeInTheDocument()

    // The separate Analytics and Analytics Hub items/statements are gone and
    // neither link leaks back in for HR.
    expect(within(links).queryByRole('link', { name: 'Analytics' })).not.toBeInTheDocument()
    expect(within(links).queryByRole('link', { name: 'Advanced Reporting' })).not.toBeInTheDocument()
  })

  it('keeps Report Exports for admin and department heads', async () => {
    const click = userEvent.setup()

    // The department head portal still renders Advanced Reporting flat.
    const dept = renderSidebar('department_head')
    expect(within(dept.baseElement).getByRole('link', { name: 'Report Exports' })).toBeInTheDocument()
    dept.unmount()

    // Admin reaches both export pages through the Exports submenu.
    const admin = renderSidebar('admin')
    await click.click(within(admin.baseElement).getByRole('button', { name: 'Reports & Monitoring' }))
    await click.click(within(admin.baseElement).getByRole('button', { name: 'Exports' }))
    expect(within(admin.baseElement).getByRole('link', { name: 'Report Exports' })).toBeInTheDocument()
    expect(within(admin.baseElement).getByRole('link', { name: 'Executive Exports' })).toBeInTheDocument()
  })

  it('groups the admin portal under three collapsible sections', () => {
    renderSidebar('admin', '/admin/dashboard')

    const links = nav()
    expect(within(links).getByText('MAIN MENU')).toBeInTheDocument()
    expect(within(links).getByText('COMMUNICATION')).toBeInTheDocument()
    expect(within(links).getByText('ADMINISTRATION')).toBeInTheDocument()

    // Only the group owning the active route opens on first render.
    expect(within(links).getByRole('button', { name: 'Dashboard' })).toHaveAttribute(
      'aria-expanded',
      'true',
    )
    expect(within(links).getByRole('button', { name: 'Tasks & Planning' })).toHaveAttribute(
      'aria-expanded',
      'false',
    )
    expect(within(links).getByRole('link', { name: 'Executive Dashboard' })).toBeInTheDocument()
    expect(within(links).queryByRole('link', { name: 'KPI Targets' })).not.toBeInTheDocument()
  })

  it('auto-expands the group that owns the current route', () => {
    renderSidebar('admin', '/reporting')

    const links = nav()
    expect(within(links).getByRole('button', { name: 'Reports & Monitoring' })).toHaveAttribute(
      'aria-expanded',
      'true',
    )
    expect(within(links).getByRole('link', { name: 'Reports & Analytics' })).toHaveAttribute(
      'href',
      '/reporting',
    )
  })

  it('keeps the duplicate report links out of the consolidated sidebar', () => {
    renderSidebar('admin', '/reporting')

    const links = nav()
    for (const label of [
      'Employee Report',
      'Department Report',
      'Monthly Report',
      'Late Submissions',
      'Pending Reports',
      'Target Report',
      'Data Quality Report',
    ]) {
      expect(within(links).queryByRole('link', { name: label })).not.toBeInTheDocument()
    }
  })

  it('expands and collapses a group from its header', async () => {
    const click = userEvent.setup()
    renderSidebar('admin', '/admin/dashboard')

    const toggle = within(nav()).getByRole('button', { name: 'Tasks & Planning' })
    expect(toggle).toHaveAttribute('aria-expanded', 'false')

    await click.click(toggle)
    expect(toggle).toHaveAttribute('aria-expanded', 'true')
    expect(within(nav()).getByRole('link', { name: 'KPI Targets' })).toBeInTheDocument()

    await click.click(toggle)
    expect(toggle).toHaveAttribute('aria-expanded', 'false')
    expect(within(nav()).queryByRole('link', { name: 'KPI Targets' })).not.toBeInTheDocument()
  })

  it('consolidates Employees, User Management and Audit Trail under People Management', async () => {
    const click = userEvent.setup()
    renderSidebar('admin', '/admin/dashboard', ['users.view', 'roles.view', 'activity_logs.view'])
    const links = nav()
    // The former flat Employees link and combined User Access & Audit Trail
    // group are replaced by the single People Management parent.
    expect(within(links).queryByRole('button', { name: 'User Access & Audit Trail' })).toBeNull()

    const toggle = within(links).getByRole('button', { name: 'People Management' })
    expect(toggle).toHaveAttribute('aria-expanded', 'false')

    await click.click(toggle)
    expect(within(links).getByRole('link', { name: 'People Management' })).toHaveAttribute(
      'href',
      '/admin/people',
    )
    expect(within(links).getByRole('link', { name: 'Roles & Access' })).toBeInTheDocument()
    expect(within(links).getByRole('link', { name: 'Audit Trail' })).toHaveAttribute(
      'href',
      '/admin/audit-trail',
    )
  })

  it('auto-expands People Management for each of its member routes', () => {
    for (const path of ['/admin/people', '/admin/people/roles', '/admin/audit-trail']) {
      const view = renderSidebar(
        'admin',
        path,
        ['users.view', 'roles.view', 'activity_logs.view'],
      )
      expect(
        within(view.baseElement).getByRole('button', { name: 'People Management' }),
      ).toHaveAttribute('aria-expanded', 'true')
      view.unmount()
    }
  })

  it('hides People Management entries the admin lacks permission for', () => {
    // Without any of the section's permissions nothing renders.
    renderSidebar('admin', '/admin/dashboard')

    const links = nav()
    expect(within(links).queryByRole('link', { name: 'People Management' })).not.toBeInTheDocument()
    expect(within(links).queryByRole('link', { name: 'Audit Trail' })).not.toBeInTheDocument()
    expect(within(links).queryByRole('button', { name: 'People Management' })).not.toBeInTheDocument()
  })

  it('exposes AI Insights to employees in the My Work section', () => {
    renderSidebar('employee')

    const link = within(nav()).getByRole('link', { name: 'AI Insights' })
    expect(link).toHaveAttribute('href', '/employee/ai-insights')
  })

  it('does not leak the self-service AI Insights link into any other role portal', () => {
    // HR no longer has an institution-wide "AI Insights" entry, and no other
    // portal may link to the employee self-service insights page.
    for (const role of ['admin', 'hr', 'department_head']) {
      const view = renderSidebar(role)
      expect(
        view.baseElement.querySelector('a[href="/employee/ai-insights"]'),
      ).toBeNull()
      view.unmount()
    }
  })
})