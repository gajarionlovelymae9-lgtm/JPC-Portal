import { useAiInsights } from '../../hooks/ai/useAiInsights'
import { AiInsightCard } from '../../components/ai/AiInsightCard'
import { JpcAideLogo, JPC_AIDE_NAME } from '../../components/ai/JpcAideLogo'

export default function AiInsightsPage() {
  const { insights, performanceInsights, reportInsights, loading, error } = useAiInsights()

  if (loading && !insights) {
    return <div className="p-8 text-center text-gray-500">Loading {JPC_AIDE_NAME} insights...</div>
  }

  if (error) {
    return <div className="p-8 text-center text-red-600 bg-red-50 border border-red-200 m-4 rounded">{error}</div>
  }

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="mb-6 flex items-start gap-3">
        <JpcAideLogo size={34} />
        <div>
          <h1 className="text-2xl font-bold text-gray-800">{JPC_AIDE_NAME} - AI Insights</h1>
          <p className="text-sm font-medium text-indigo-700">Intelligent Performance Analysis and Productivity Recommendations</p>
        </div>
      </div>
      
      {/* General Insights */}
      {insights && insights.summary && (
        <div className="bg-white p-6 rounded-lg shadow-sm border mb-8">
          <h2 className="text-lg font-semibold mb-2">Executive Summary</h2>
          <p className="text-gray-700">{insights.summary}</p>
        </div>
      )}

      {/* Performance Insights */}
      <div className="mb-8">
        <h2 className="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Performance Anomalies & Trends</h2>
        {performanceInsights.length === 0 ? (
          <p className="text-gray-500">No performance insights available at the moment.</p>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {performanceInsights.map((insight, idx) => (
              <AiInsightCard key={`perf-${idx}`} insight={{ ...insight, type: insight.type || 'performance' }} />
            ))}
          </div>
        )}
      </div>

      {/* Report Insights */}
      <div className="mb-8">
        <h2 className="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Report Analytics & Risks</h2>
        {reportInsights.length === 0 ? (
          <p className="text-gray-500">No report insights available at the moment.</p>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {reportInsights.map((insight, idx) => (
              <AiInsightCard key={`rep-${idx}`} insight={{ ...insight, type: insight.type || 'risk' }} />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
