

interface AiAchievementResultCardProps {
  data: {
    employee: string
    month: string
    achievement_name: string
    score: number
  }
}

export function AiAchievementResultCard({ data }: AiAchievementResultCardProps) {
  return (
    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 shadow-sm my-2 flex items-center max-w-sm">
      <div className="text-3xl mr-4">🏆</div>
      <div>
        <h3 className="font-bold text-yellow-900">{data.achievement_name}</h3>
        <p className="text-xs text-yellow-700">{data.employee} • {data.month}</p>
        <p className="text-sm font-medium text-yellow-800 mt-1">Score: {data.score}</p>
      </div>
    </div>
  )
}
