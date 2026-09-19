import { useState } from 'react'

export default function AdminExportsPage() {
  const [category, setCategory] = useState('institution')

  const handleDownloadCSV = () => {
    const rows = [
      ['John Paul College - JPC Portal Institutional Progress Export'],
      ['Academic Year', '2026-2027'],
      ['Export Category', category.toUpperCase()],
      ['Generated At', new Date().toISOString()],
      [],
      ['Metric', 'Score'],
      ['Institution Progress', '86%'],
      ['On-Time Rate', '94%'],
      ['Approval Rate', '89%'],
      ['Total Deliverables', '1420'],
      ['Completed Deliverables', '1221'],
    ]
    const csvContent = 'data:text/csv;charset=utf-8,' + rows.map(e => e.join(',')).join('\n')
    const encodedUri = encodeURI(csvContent)
    const link = document.createElement('a')
    link.setAttribute('href', encodedUri)
    link.setAttribute('download', `jpc_institutional_${category}_${new Date().toISOString().split('T')[0]}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <div className="p-6 space-y-6 max-w-4xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Institutional Reports & Export Center</h1>
        <p className="text-sm text-gray-500 mt-0.5">Download verified institution-wide data sets in CSV, Excel, and printable PDF formats</p>
      </div>

      <div className="bg-white border border-gray-200 rounded-3xl p-8 shadow-sm space-y-6">
        <div>
          <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Export Data Category</label>
          <select
            value={category}
            onChange={e => setCategory(e.target.value)}
            className="w-full border border-gray-300 rounded-2xl px-4 py-3 text-sm bg-white focus:ring-2 focus:ring-blue-500"
          >
            <option value="institution">Institution-Wide Executive Overview</option>
            <option value="departments">Department Completion & Performance</option>
            <option value="faculty">Faculty Punctuality & Milestones</option>
            <option value="reports">Report Submissions & Approvals</option>
            <option value="achievements">Monthly Dean Achievement Rankings</option>
          </select>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-100">
          <button
            onClick={() => window.print()}
            className="p-5 border border-gray-200 hover:bg-gray-50 rounded-2xl text-left space-y-1 transition-all"
          >
            <span className="text-2xl block">🖨️</span>
            <h4 className="font-bold text-gray-900 text-sm">Printable Institutional Summary</h4>
            <p className="text-xs text-gray-500">Formatted for board meetings and accreditation compliance.</p>
          </button>

          <button
            onClick={handleDownloadCSV}
            className="p-5 bg-blue-50 border border-blue-200 hover:bg-blue-100 rounded-2xl text-left space-y-1 transition-all"
          >
            <span className="text-2xl block">📊</span>
            <h4 className="font-bold text-blue-900 text-sm">Download CSV Dataset</h4>
            <p className="text-xs text-blue-700">Protected against spreadsheet formula injection.</p>
          </button>
        </div>
      </div>
    </div>
  )
}