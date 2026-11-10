
interface AiSuggestionListProps {
  onSelectSuggestion: (text: string) => void
}

/**
 * Intentionally renders no predefined prompts. JPC AIDE starts with a clean
 * chat interface where users enter their own messages.
 */
export function AiSuggestionList(_props: AiSuggestionListProps) {
  void _props
  return null
}
