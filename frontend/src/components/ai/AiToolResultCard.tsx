

interface AiToolResultCardProps {
  title: string
  count?: number
  summary?: string
  data?: any[]
  linkUrl?: string
}

export function AiToolResultCard({ title, count, summary, data, linkUrl }: AiToolResultCardProps) {
  return (
    <div className="bg-white border rounded-lg p-4 shadow-sm my-2">
      <h3 className="font-semibold text-gray-800 mb-2">{title}</h3>
      {summary && <p className="text-sm text-gray-600 mb-2">{summary}</p>}
      {count !== undefined && <p className="text-sm text-gray-600 mb-4">Total: {count}</p>}
      
      {data && data.length > 0 && (
        <div className="overflow-x-auto mb-4 border rounded">
          <table className="min-w-full text-sm text-left">
            <thead className="bg-gray-50 border-b">
              <tr>
                {Object.keys(data[0]).map((key) => (
                  <th key={key} className="px-4 py-2 font-medium text-gray-700 capitalize">{key.replace(/_/g, ' ')}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {data.slice(0, 5).map((row, i) => (
                <tr key={i} className="border-b last:border-0 hover:bg-gray-50">
                  {Object.values(row).map((val: any, j) => (
                    <td key={j} className="px-4 py-2 text-gray-600 truncate max-w-[200px]">{String(val)}</td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
          {data.length > 5 && (
            <div className="px-4 py-2 text-xs text-gray-500 text-center bg-gray-50">
              Showing 5 of {data.length} results
            </div>
          )}
        </div>
      )}
      
      {linkUrl && (
        <a href={linkUrl} className="text-sm text-blue-600 hover:underline">
          View Details &rarr;
        </a>
      )}
    </div>
  )
}
