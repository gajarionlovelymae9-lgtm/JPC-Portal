

export function AiTypingIndicator() {
  return (
    <div className="flex items-center space-x-2 text-gray-500 p-4">
      <span className="flex space-x-1">
        <span className="animate-bounce inline-block w-2 h-2 bg-gray-400 rounded-full" style={{ animationDelay: '0ms' }} />
        <span className="animate-bounce inline-block w-2 h-2 bg-gray-400 rounded-full" style={{ animationDelay: '150ms' }} />
        <span className="animate-bounce inline-block w-2 h-2 bg-gray-400 rounded-full" style={{ animationDelay: '300ms' }} />
      </span>
      <span className="text-sm font-medium">AI is analyzing...</span>
    </div>
  )
}
