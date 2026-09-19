import { useState, useEffect } from 'react'
import {
  getNotificationMetrics,
  getNotificationLogs,
  retryNotification,
  type NotificationMetrics,
  type NotificationLogItem,
} from '../../services/notificationCenterService'

export default function AdminNotificationMonitoringPage() {
  const [metrics, setMetrics] = useState<NotificationMetrics | null>(null)
  const [logs, setLogs] = useState<NotificationLogItem[]>([])
  const [loading, setLoading] = useState(true)
  const [retryingId, setRetryingId] = useState<number | null>(null)
  const [channelFilter, setChannelFilter] = useState<string>('')
  const [statusFilter, setStatusFilter] = useState<string>('')

  useEffect(() => {
    loadData()
  }, [channelFilter, statusFilter])

  const loadData = async () => {
    setLoading(true)
    try {
      const params: Record<string, unknown> = { per_page: 25 }
      if (channelFilter) params.channel = channelFilter
      if (statusFilter) params.status = statusFilter

      const [mRes, lRes] = await Promise.all([
        getNotificationMetrics(),
        getNotificationLogs(params),
      ])
      setMetrics((mRes as unknown as { data?: NotificationMetrics }).data ?? (mRes as unknown as NotificationMetrics))
      const logData = lRes.data as unknown
      const logList = Array.isArray(logData)
        ? logData
        : (logData as { data?: NotificationLogItem[] })?.data ?? []
      setLogs(logList as NotificationLogItem[])
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }

  const handleRetry = async (logId: number) => {
    setRetryingId(logId)
    try {
      await retryNotification(logId)
      await loadData()
    } catch {
      // ignore
    } finally {
      setRetryingId(null)
    }
  }

  return (
    <div className="p-6 md:p-8 max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-200/80 pb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight flex items-center gap-3">
            <span>📡</span> Notification Delivery & Telemetry
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Real-time delivery rates, in-app/email dispatches, failure logging, and queue retry engine.
          </p>
        </div>

        <button
          onClick={loadData}
          className="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs rounded-2xl transition-colors"
        >
          <span>🔄</span> Refresh Telemetry
        </button>
      </div>

      {/* Metric Cards */}
      {metrics && (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
          <div className="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm">
            <span className="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">
              Total Dispatches
            </span>
            <span className="text-2xl font-black text-gray-900">{metrics.total_sent.toLocaleString()}</span>
          </div>

          <div className="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm">
            <span className="text-xs font-bold text-blue-500 uppercase tracking-wider block mb-1">
              In-App Bell
            </span>
            <span className="text-2xl font-black text-blue-600">{metrics.in_app_count.toLocaleString()}</span>
          </div>

          <div className="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm">
            <span className="text-xs font-bold text-indigo-500 uppercase tracking-wider block mb-1">
              Email Channel
            </span>
            <span className="text-2xl font-black text-indigo-600">{metrics.email_count.toLocaleString()}</span>
          </div>

          <div className="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm">
            <span className="text-xs font-bold text-amber-500 uppercase tracking-wider block mb-1">
              Queued
            </span>
            <span className="text-2xl font-black text-amber-600">{metrics.queued_count.toLocaleString()}</span>
          </div>

          <div className="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm">
            <span className="text-xs font-bold text-red-500 uppercase tracking-wider block mb-1">
              Failed
            </span>
            <span className="text-2xl font-black text-red-600">{metrics.failed_count.toLocaleString()}</span>
          </div>

          <div className="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm">
            <span className="text-xs font-bold text-emerald-500 uppercase tracking-wider block mb-1">
              Read Rate
            </span>
            <span className="text-2xl font-black text-emerald-600">{metrics.read_rate}%</span>
          </div>
        </div>
      )}

      {/* Logs Table */}
      <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden space-y-4">
        <div className="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h2 className="font-extrabold text-base text-gray-900">Notification Logs & Retries</h2>
            <p className="text-xs text-gray-500 mt-0.5">Audit trail of individual notification transmissions.</p>
          </div>

          {/* Filters */}
          <div className="flex items-center gap-3">
            <select
              value={channelFilter}
              onChange={(e) => setChannelFilter(e.target.value)}
              className="px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Channels</option>
              <option value="in_app">In-App</option>
              <option value="email">Email</option>
            </select>

            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="px-3 py-1.5 border border-gray-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500"
            >
              <option value="">All Statuses</option>
              <option value="sent">Sent / Delivered</option>
              <option value="queued">Queued</option>
              <option value="failed">Failed</option>
            </select>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-gray-100 bg-gray-50/50 text-[11px] font-extrabold uppercase tracking-wider text-gray-400">
                <th className="py-3 px-6">Event Type</th>
                <th className="py-3 px-6">Recipient</th>
                <th className="py-3 px-6">Channel</th>
                <th className="py-3 px-6">Status</th>
                <th className="py-3 px-6">Sent At</th>
                <th className="py-3 px-6">Retries</th>
                <th className="py-3 px-6 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50 text-xs">
              {loading ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-gray-400">
                    Loading logs...
                  </td>
                </tr>
              ) : logs.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-8 text-center text-gray-400">
                    No notification logs found.
                  </td>
                </tr>
              ) : (
                logs.map((log) => {
                  const isFailed = log.status === 'failed'
                  const isSent = log.status === 'sent'

                  return (
                    <tr key={log.id} className="hover:bg-gray-50/50 transition-colors">
                      <td className="py-3.5 px-6 font-bold text-gray-900 font-mono text-[11px]">
                        {log.type}
                      </td>
                      <td className="py-3.5 px-6 font-medium text-gray-700">
                        {log.user?.name ?? `User #${log.user_id}`}
                      </td>
                      <td className="py-3.5 px-6">
                        <span className="uppercase text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">
                          {log.channel}
                        </span>
                      </td>
                      <td className="py-3.5 px-6">
                        <span
                          className={`text-[10px] font-extrabold px-2.5 py-0.5 rounded-full capitalize ${
                            isSent
                              ? 'bg-emerald-100 text-emerald-800'
                              : isFailed
                              ? 'bg-red-100 text-red-800'
                              : 'bg-amber-100 text-amber-800'
                          }`}
                        >
                          {log.status}
                        </span>
                        {log.error_message && (
                          <span className="block text-[10px] text-red-500 font-mono mt-0.5 truncate max-w-xs">
                            {log.error_message}
                          </span>
                        )}
                      </td>
                      <td className="py-3.5 px-6 text-gray-500 font-medium">
                        {log.sent_at ? new Date(log.sent_at).toLocaleString() : '—'}
                      </td>
                      <td className="py-3.5 px-6 font-bold text-gray-700">{log.retry_count}</td>
                      <td className="py-3.5 px-6 text-right">
                        {isFailed && (
                          <button
                            onClick={() => handleRetry(log.id)}
                            disabled={retryingId === log.id}
                            className="px-3 py-1 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-bold text-[11px] rounded-lg shadow-xs transition-colors"
                          >
                            {retryingId === log.id ? 'Queuing...' : 'Retry'}
                          </button>
                        )}
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
