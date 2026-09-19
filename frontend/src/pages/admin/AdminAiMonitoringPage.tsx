import { useEffect, useState } from 'react'
import { getAdminAiStatus } from '../../services/adminPortalService'
import type { AdminAiStatusData } from '../../services/adminPortalService'

export default function AdminAiMonitoringPage() {
  const [data, setData] = useState<AdminAiStatusData | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    getAdminAiStatus()
      .then(res => setData(res.data))
      .finally(() => setLoading(false))
  }, [])

  if (loading || !data) return <div className="p-8 text-center text-gray-500">Loading AI health metrics...</div>

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">AI & NLP Pipeline Health Monitoring</h1>
        <p className="text-sm text-gray-500 mt-0.5">Status, throughput, and error rates of natural language processing jobs</p>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-bold uppercase block">Pipeline Success</span>
          <span className="text-3xl font-black text-emerald-600 block mt-1">{data.health.success_rate}%</span>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-bold uppercase block">Avg Processing</span>
          <span className="text-3xl font-black text-blue-600 block mt-1">{data.health.average_time_seconds}s</span>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-bold uppercase block">Active Pipelines</span>
          <span className="text-3xl font-black text-indigo-600 block mt-1">{data.pipelines.length}</span>
        </div>
        <div className="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm text-center">
          <span className="text-xs text-gray-400 font-bold uppercase block">System State</span>
          <span className="text-2xl font-black text-purple-600 block mt-1 uppercase">{data.health.status}</span>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
        <h3 className="font-extrabold text-gray-900 text-lg">AI Pipeline Components</h3>
        <div className="divide-y divide-gray-100">
          {data.pipelines.map((p, i) => (
            <div key={i} className="py-3.5 flex justify-between items-center">
              <div>
                <h4 className="font-bold text-sm text-gray-900">{p.name}</h4>
                <span className="text-xs text-emerald-600 font-semibold">● Operational</span>
              </div>
              <span className="text-xs font-bold bg-emerald-50 text-emerald-700 px-3 py-1 rounded-full">
                {p.success_rate}% Success
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}