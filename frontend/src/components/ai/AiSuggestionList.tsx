

const SUGGESTIONS = [
  "Show all late reports this month.",
  "Who has the highest completion rate?",
  "Show pending reports.",
  "Show employees with overdue tasks.",
  "What is my performance this month?",
  "Which department has the highest performance?",
  "Show this month's achievements."
]

interface AiSuggestionListProps {
  onSelectSuggestion: (text: string) => void
}

export function AiSuggestionList({ onSelectSuggestion }: AiSuggestionListProps) {
  return (
    <div className="flex flex-wrap gap-2 mt-4">
      {SUGGESTIONS.map((suggestion, idx) => (
        <button
          key={idx}
          className="text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 py-1.5 px-3 rounded-full transition-colors"
          onClick={() => onSelectSuggestion(suggestion)}
        >
          {suggestion}
        </button>
      ))}
    </div>
  )
}
