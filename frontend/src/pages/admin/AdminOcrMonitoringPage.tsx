import { useEffect, useState } from 'react'
import { getAdminOcrList, retryAdminAiJob } from '../../services/adminPortalService'
import type { AdminOcrItem } from '../../services/adminPortalService'

export default function AdminOcrMonitoringPage() {
  const [docs, setDocs] = useState<AdminOcrItem[]>([])
  const [loading, setLoading] = useState(true)

  const loadDocs = () => {
    setLoading(true)
    getAdminOcrList()
      .then(res => setDocs(res.data))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadDocs()
  }, [])

  const handleRetry = async (id: number) => {
    try {
      await retryAdminAiJob(id)
      alert('OCR retry job dispatched successfully.')
      loadDocs()
    } catch {
      alert('Failed to retry OCR.')
    }
  }

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">OCR Document Extraction Dashboard</h1>
        <p className="text-sm text-gray-500 mt-0.5">Monitor optical character recognition processing, confidence scores, and retry failed uploads</p>
      </div>

      {loading ? (
        <div className="space-y-3">{[...Array(4)].map((_, i) => <div key={i} className="h-16 bg-gray-100 rounded-xl animate-pulse" />)}</div>
      ) : (
        <div className="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
          <table className="w-full text-sm text-left">
            <thead className="bg-gray-50 text-gray-600 text-xs font-bold uppercase tracking-wider border-b border-gray-200">
              <tr>
                <th className="py-3 px-4">Document</th>
                <th className="py-3 px-4">Report Context</th>
                <th className="py-3 px-4">Confidence</th>
                <th className="py-3 px-4">Status</th>
                <th className="py-3 px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {docs.map(doc => (
                <tr key={doc.id} className="hover:bg-gray-50/80">
                  <td className="py-3.5 px-4 font-semibold text-gray-900 flex items-center gap-2">
                    <span>📄</span> {doc.document_name}
                  </td>
                  <td className="py-3.5 px-4 text-gray-600 text-xs">{doc.report_title}</td>
                  <td className="py-3.5 px-4 font-bold text-emerald-600">{doc.confidence}%</td>
                  <td className="py-3.5 px-4">
                    <span className="text-xs px-2.5 py-0.5 rounded-full font-bold bg-emerald-100 text-emerald-800 uppercase">
                      {doc.status}
                    </span>
                  </td>
                  <td className="py-3.5 px-4 text-right">
                    <button
                      onClick={() => handleRetry(doc.id)}
                      className="px-3 py-1 bg-gray-100 hover:bg-blue-50 text-blue-700 font-bold rounded-lg text-xs"
                    >
                      Re-Extract / Retry
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}