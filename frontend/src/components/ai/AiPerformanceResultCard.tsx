

interface AiPerformanceResultCardProps {
  data: {
    name: string
    score: number
    task_completion: number
    report_completion: number
    on_time_rate: number
    approval_rate: number
    target_achievement: number
    rating: string
  }
}

export function AiPerformanceResultCard({ data }: AiPerformanceResultCardProps) {
  return (
    <div className="bg-white border rounded-lg p-4 shadow-sm my-2 max-w-sm">
      <div className="flex justify-between items-center mb-3">
        <h3 className="font-bold text-gray-800">{data.name}</h3>
        <span className="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded">
          {data.rating}
        </span>
      </div>
      <div className="grid grid-cols-2 gap-3 text-sm">
        <div className="flex flex-col">
          <span className="text-gray-500 text-xs">Score</span>
          <span className="font-semibold text-lg">{data.score.toFixed(1)}</span>
        </div>
        <div className="flex flex-col">
          <span className="text-gray-500 text-xs">Task Completion</span>
          <span className="font-medium">{data.task_completion}%</span>
        </div>
        <div className="flex flex-col">
          <span className="text-gray-500 text-xs">Report Completion</span>
          <span className="font-medium">{data.report_completion}%</span>
        </div>
        <div className="flex flex-col">
          <span className="text-gray-500 text-xs">On-time Rate</span>
          <span className="font-medium">{data.on_time_rate}%</span>
        </div>
        <div className="flex flex-col">
          <span className="text-gray-500 text-xs">Approval Rate</span>
          <span className="font-medium">{data.approval_rate}%</span>
        </div>
        <div className="flex flex-col">
          <span className="text-gray-500 text-xs">Target Achievement</span>
          <span className="font-medium">{data.target_achievement}%</span>
        </div>
      </div>
    </div>
  )
}
