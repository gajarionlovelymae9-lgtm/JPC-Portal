import { useSearchParams } from 'react-router-dom'
import {
  AdminPage,
  Badge,
  StatCard,
  num,
} from '../../components/admin/AdminUi'
import { useAdminResource } from '../../hooks/useAdminResource'
import { getPeopleSummary } from '../../services/peopleManagementService'
import EmployeesPage from '../EmployeesPage'
import AdminPeopleDepartmentsPage from './AdminPeopleDepartmentsPage'
import AdminUserManagementPage from './AdminUserManagementPage'

type PeopleTab = 'employees' | 'departments' | 'accounts'

const TABS: Array<{ key: PeopleTab; label: string; description: string }> = [
  {
    key: 'employees',
    label: 'Employees',
    description: 'Employee directory, records and HR details',
  },
  {
    key: 'departments',
    label: 'Departments',
    description: 'Departments, heads and staffing',
  },
  {
    key: 'accounts',
    label: 'Accounts',
    description: 'Logins, roles and employee linking',
  },
]

/**
 * Unified People Management console (Employees / Departments / Accounts).
 *
 * The three tabs reuse the existing employee, department and user-account
 * pages unchanged — the same services, CRUD modal and filters — so there are
 * no duplicated records or endpoints. The tab is mirrored in the URL
 * (`?tab=`), which lets the legacy routes deep-link straight to their tab and
 * keeps browser refresh/back behaviour intact.
 */
export default function AdminPeoplePage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const rawTab = searchParams.get('tab')
  const tab: PeopleTab = TABS.some((t) => t.key === rawTab)
    ? (rawTab as PeopleTab)
    : 'employees'

  const summary = useAdminResource(() => getPeopleSummary())

  const selectTab = (key: PeopleTab) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      next.set('tab', key)
      return next
    })
  }

  return (
    <AdminPage
      title="People Management"
      subtitle="Employees, departments and login accounts in one place."
      actions={
        summary.data ? (
          <Badge tone="info">
            {`${num(summary.data.employees.total)} employees · ${num(summary.data.departments.total)} departments · ${num(summary.data.accounts.total)} accounts`}
          </Badge>
        ) : null
      }
    >
      <div className="space-y-6">
        {/* People summary strip — a single endpoint feeding all three tabs. */}
        {summary.data ? (
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <StatCard
              label="Employees"
              value={num(summary.data.employees.total)}
              hint={`${num(summary.data.employees.active)} active`}
            />
            <StatCard
              label="Departments"
              value={num(summary.data.departments.total)}
              hint={`${num(summary.data.departments.active)} active`}
            />
            <StatCard
              label="Accounts"
              value={num(summary.data.accounts.total)}
              hint={`${num(summary.data.accounts.unlinked_employee_accounts)} without an employee`}
            />
            <StatCard
              label="Inactive"
              value={num(summary.data.employees.inactive)}
              hint={`${num(summary.data.accounts.suspended)} suspended accounts`}
            />
          </div>
        ) : null}

        {summary.error ? (
          <p className="text-xs font-semibold text-red-600" role="alert">
            {summary.error}
          </p>
        ) : null}

        {/* Tab navigation */}
        <div className="flex flex-wrap gap-1 border-b border-slate-200 pb-1" role="tablist" aria-label="People sections">
          {TABS.map((item) => (
            <button
              key={item.key}
              type="button"
              role="tab"
              aria-selected={tab === item.key}
              title={item.description}
              onClick={() => selectTab(item.key)}
              className={`px-3 py-2 rounded-t-lg text-sm font-bold border-b-2 transition ${
                tab === item.key
                  ? 'border-jpc-600 text-jpc-700 bg-jpc-50/60'
                  : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50'
              }`}
            >
              {item.label}
            </button>
          ))}
        </div>

        {tab === 'employees' ? <EmployeesPage embedded /> : null}
        {tab === 'departments' ? <AdminPeopleDepartmentsPage embedded /> : null}
        {tab === 'accounts' ? <AdminUserManagementPage embedded /> : null}
      </div>
    </AdminPage>
  )
}
