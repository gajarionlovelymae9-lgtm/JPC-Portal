

interface AiReportResultCardProps {
  data: {
    id: string | number
    title: string
    employee: string
    status: string
    submission_date: string
    summary?: string
  }
}

export function AiReportResultCard({ data }: AiReportResultCardProps) {
  return (
    <div className="bg-white border rounded-lg p-4 shadow-sm my-2 max-w-md">
      <div className="flex justify-between items-start mb-2">
        <h3 className="font-semibold text-gray-800 line-clamp-1">{data.title}</h3>
        <span className="px-2 py-0.5 bg-gray-100 border text-gray-600 text-xs rounded-full ml-2 whitespace-nowrap">
          {data.status}
        </span>
      </div>
      <p className="text-xs text-gray-500 mb-2">By {data.employee} • {data.submission_date}</p>
      {data.summary && (
        <p className="text-sm text-gray-700 bg-gray-50 p-2 rounded mb-3 line-clamp-3">
          {data.summary}
        </p>
      )}
      <a 
        href={`/reports/${data.id}`}
        className="inline-block text-xs text-blue-600 hover:text-blue-800 font-medium"
      >
        View Report &rarr;
      </a>
    </div>
  )
}
