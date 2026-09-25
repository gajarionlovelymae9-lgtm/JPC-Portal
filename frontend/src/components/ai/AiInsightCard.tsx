

interface AiInsightCardProps {
  insight: {
    title: string
    description: string
    type: 'trend' | 'performance' | 'risk' | 'opportunity'
    date?: string
  }
}

export function AiInsightCard({ insight }: AiInsightCardProps) {
  const getIcon = () => {
    switch (insight.type) {
      case 'trend': return '📈'
      case 'performance': return '⭐'
      case 'risk': return '⚠️'
      case 'opportunity': return '💡'
      default: return 'ℹ️'
    }
  }

  return (
    <div className="bg-white border rounded-lg shadow-sm overflow-hidden flex flex-col h-full">
      <div className="bg-indigo-50 border-b px-4 py-2 flex justify-between items-center">
        <span className="text-xs font-bold text-indigo-700 flex items-center gap-1">
          ⚠️ AI INSIGHT (Advisory Only)
        </span>
        {insight.date && <span className="text-xs text-gray-500">{insight.date}</span>}
      </div>
      <div className="p-4 flex-1">
        <div className="flex items-start gap-3">
          <span className="text-2xl mt-1">{getIcon()}</span>
          <div>
            <h4 className="font-semibold text-gray-900 mb-1">{insight.title}</h4>
            <p className="text-sm text-gray-600">{insight.description}</p>
          </div>
        </div>
      </div>
    </div>
  )
}
