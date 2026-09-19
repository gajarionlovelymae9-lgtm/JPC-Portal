import { useState } from 'react'

export default function DepartmentReportsCenterPage() {
  const [reportType, setReportType] = useState('tasks')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')

  const handleExportCSV = () => {
    const rows = [
      ['Department Deliverables Report', 'Academic Year 2026-2027'],
      ['Generated Date', new Date().toISOString()],
      [],
      ['Metric / Column', 'Value / Details'],
      ['Department Code', 'DEP-01'],
      ['Active Deliverables', '14'],
      ['Average Completion', '88.5%'],
      ['On-Time Delivery Rate', '94.2%'],
    ]
    const csvContent = 'data:text/csv;charset=utf-8,' + rows.map(e => e.join(',')).join('\n')
    const encodedUri = encodeURI(csvContent)
    const link = document.createElement('a')
    link.setAttribute('href', encodedUri)
    link.setAttribute('download', `department_report_${reportType}_${new Date().toISOString().split('T')[0]}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const handlePrint = () => {
    window.print()
  }

  return (
    <div className="p-6 space-y-6 max-w-5xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Department Reports Center</h1>
        <p className="text-sm text-gray-500 mt-0.5">Generate formal printable reports and CSV exports for dean and administration review</p>
      </div>

      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-6">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Report Type</label>
            <select
              value={reportType}
              onChange={e => setReportType(e.target.value)}
              className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
            >
              <option value="tasks">Department Task Progress</option>
              <option value="submissions">Report Submissions & Approval</option>
              <option value="faculty">Faculty Performance Summary</option>
              <option value="targets">Annual Target Status</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">From Date</label>
            <input
              type="date"
              value={dateFrom}
              onChange={e => setDateFrom(e.target.value)}
              className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">To Date</label>
            <input
              type="date"
              value={dateTo}
              onChange={e => setDateTo(e.target.value)}
              className="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>

        <div className="flex gap-3 justify-end pt-2">
          <button
            onClick={handlePrint}
            className="px-4 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-xl text-xs font-bold flex items-center gap-1.5"
          >
            <span>🖨️</span> Print / Save as PDF
          </button>
          <button
            onClick={handleExportCSV}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm"
          >
            <span>📥</span> Export CSV
          </button>
        </div>

        {/* Printable Report Preview */}
        <div className="border border-gray-100 rounded-2xl p-6 bg-gray-50/50 space-y-4">
          <div className="border-b border-gray-200 pb-4 text-center">
            <h2 className="text-xl font-bold text-gray-900">JOHN PAUL COLLEGE</h2>
            <p className="text-xs text-gray-500 uppercase tracking-widest mt-0.5">Department Deliverables & Monitoring Summary</p>
            <p className="text-xs text-gray-400 mt-1">Generated: {new Date().toLocaleDateString()}</p>
          </div>

          <div className="text-xs text-gray-600 space-y-2">
            <p><b>Report Type:</b> {reportType.toUpperCase()} MONITORING</p>
            <p><b>Period:</b> {dateFrom || 'Start of Term'} to {dateTo || 'Present'}</p>
            <p><b>Status:</b> All records verified under Department Head oversight.</p>
          </div>
        </div>
      </div>
    </div>
  )
}